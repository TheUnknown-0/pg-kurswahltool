<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Services\PdfImporter;
use App\Services\UserImport;

/**
 * Admin-Bereich: PDFs hochladen (Verarbeitung im Hintergrund), Konten importieren,
 * PDFs zuordnen und den Stand der Schüler einsehen.
 */
final class AdminController extends Controller
{
    public function index(): string
    {
        $this->ctx->auth->requireAdmin();
        $db = $this->ctx->db;
        $q = trim((string) ($_GET['q'] ?? ''));
        $filter = (string) ($_GET['filter'] ?? '');

        $where = "u.role = 'student'";
        $params = [];
        if ($q !== '') {
            $where .= ' AND (u.login LIKE ? OR u.display_name LIKE ? OR p.name LIKE ?)';
            $like = '%' . $q . '%';
            $params = [$like, $like, $like];
        }
        if ($filter === 'ohne-pdf') {
            $where .= ' AND p.id IS NULL';
        } elseif ($filter === 'ohne-wahl') {
            $where .= ' AND s.user_id IS NULL';
        }
        $students = $db->fetchAll(
            "SELECT u.id, u.login, u.display_name, u.last_login_at, p.id AS pdf_id, p.name AS pdf_name, p.klasse,
                    p.schueler_id, s.updated_at AS saved_at
             FROM users u
             LEFT JOIN school_pdfs p ON p.user_id = u.id AND p.active = 1
             LEFT JOIN selections s ON s.user_id = u.id
             WHERE {$where} ORDER BY u.login LIMIT 500",
            $params,
        );

        return $this->ctx->view->render('admin/index', [
            'title' => 'Admin',
            'subtitle' => 'Administration',
            'stats' => $this->stats(),
            'students' => $students,
            'unassigned' => $db->fetchAll(
                'SELECT id, name, klasse, login_key, filename, imported_at FROM school_pdfs WHERE user_id IS NULL AND active = 1 ORDER BY name',
            ),
            'studentOptions' => $db->fetchAll("SELECT id, login FROM users WHERE role = 'student' ORDER BY login"),
            'log' => $db->fetchAll('SELECT source, filename, status, message, created_at FROM import_log ORDER BY id DESC LIMIT 100'),
            'flashes' => $this->ctx->session->pullFlashes(),
            'q' => $q,
            'filter' => $filter,
        ]);
    }

    /** Für die Live-Anzeige im Admin-Bereich (Warteschlange, Worker, Zähler). */
    public function status(): array
    {
        $this->ctx->auth->requireAdmin();

        return ['success' => true] + $this->stats();
    }

    /** Hochgeladene PDFs/ZIPs landen in der Warteschlange; der Worker liest sie im Hintergrund ein. */
    public function upload(): string
    {
        $this->ctx->auth->requireAdmin();
        $this->verifyCsrf();
        $files = $_FILES['files'] ?? null;
        if (!is_array($files) || !is_array($files['name'] ?? null)) {
            $this->ctx->session->flash('error', 'Keine Dateien ausgewählt.');

            return $this->redirect('/admin');
        }
        $dir = $this->ctx->config['import']['queue_dir'];
        if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
            throw new \RuntimeException('Warteschlange nicht beschreibbar: ' . $dir);
        }
        $queued = 0;
        $errors = [];
        foreach ($files['name'] as $i => $name) {
            $name = basename((string) $name);
            if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $errors[] = "{$name}: Upload fehlgeschlagen (Code {$files['error'][$i]}).";
                continue;
            }
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, ['pdf', 'zip'], true)) {
                $errors[] = "{$name}: nur .pdf und .zip.";
                continue;
            }
            // Eindeutiger Name; der Originalname bleibt für das Protokoll erhalten
            $target = $dir . '/' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '--' . preg_replace('/[^\w.\- ]+/u', '_', $name);
            if (!move_uploaded_file((string) $files['tmp_name'][$i], $target . '.part') || !rename($target . '.part', $target)) {
                $errors[] = "{$name}: konnte nicht gespeichert werden.";
                continue;
            }
            $queued++;
        }
        if ($queued > 0) {
            $this->ctx->session->flash('ok', "{$queued} Datei(en) in der Warteschlange. Der Import läuft im Hintergrund – das Protokoll unten aktualisiert sich.");
        }
        foreach ($errors as $e) {
            $this->ctx->session->flash('error', $e);
        }

        return $this->redirect('/admin');
    }

    public function importUsers(): string
    {
        $this->ctx->auth->requireAdmin();
        $this->verifyCsrf();
        $file = $_FILES['csv'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->ctx->session->flash('error', 'Keine Datei ausgewählt.');

            return $this->redirect('/admin');
        }
        $r = (new UserImport($this->ctx->db))->import((string) file_get_contents((string) $file['tmp_name']));
        $matched = (new PdfImporter($this->ctx->db))->rematch();
        $this->ctx->session->flash('ok', "Konten: {$r['created']} neu, {$r['updated']} aktualisiert. {$matched} PDF(s) neu zugeordnet.");
        foreach (array_slice($r['errors'], 0, 30) as $e) {
            $this->ctx->session->flash('error', $e);
        }
        if (count($r['errors']) > 30) {
            $this->ctx->session->flash('error', '… und ' . (count($r['errors']) - 30) . ' weitere Fehler.');
        }

        return $this->redirect('/admin');
    }

    public function setPassword(array $p): string
    {
        $this->ctx->auth->requireAdmin();
        $this->verifyCsrf();
        $pw = (string) ($_POST['password'] ?? '');
        if (strlen($pw) < 4) {
            $this->ctx->session->flash('error', 'Das Passwort muss mindestens 4 Zeichen haben.');

            return $this->redirect('/admin');
        }
        $this->ctx->db->run("UPDATE users SET password_hash = ? WHERE id = ? AND role = 'student'", [password_hash($pw, PASSWORD_DEFAULT), (int) $p['id']]);
        $this->ctx->session->flash('ok', 'Passwort gesetzt.');

        return $this->redirect('/admin');
    }

    public function deleteUser(array $p): string
    {
        $this->ctx->auth->requireAdmin();
        $this->verifyCsrf();
        $this->ctx->db->run("DELETE FROM users WHERE id = ? AND role = 'student'", [(int) $p['id']]);
        $this->ctx->session->flash('ok', 'Konto gelöscht. Seine PDF ist jetzt ohne Zuordnung, die gespeicherte Wahl ist gelöscht.');

        return $this->redirect('/admin');
    }

    public function assignPdf(array $p): string
    {
        $this->ctx->auth->requireAdmin();
        $this->verifyCsrf();
        $userId = (int) ($_POST['user_id'] ?? 0);
        if ($userId > 0 && $this->ctx->db->fetchValue("SELECT id FROM users WHERE id = ? AND role = 'student'", [$userId]) === null) {
            throw new HttpException(400, 'Konto nicht gefunden.');
        }
        (new PdfImporter($this->ctx->db))->assign((int) $p['id'], $userId > 0 ? $userId : null);
        $this->ctx->session->flash('ok', $userId > 0 ? 'PDF zugeordnet.' : 'Zuordnung gelöst.');

        return $this->redirect('/admin');
    }

    public function deletePdf(array $p): string
    {
        $this->ctx->auth->requireAdmin();
        $this->verifyCsrf();
        $this->ctx->db->run('DELETE FROM school_pdfs WHERE id = ?', [(int) $p['id']]);
        $this->ctx->session->flash('ok', 'PDF gelöscht.');

        return $this->redirect('/admin');
    }

    public function downloadPdf(array $p): string
    {
        $this->ctx->auth->requireAdmin();
        $row = $this->ctx->db->fetchOne('SELECT pdf, filename FROM school_pdfs WHERE id = ?', [(int) $p['id']]);
        if ($row === null) {
            throw new HttpException(404);
        }
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', (string) $row['filename']) . '"');

        return (string) $row['pdf'];
    }

    /** @return array<string, mixed> */
    private function stats(): array
    {
        $db = $this->ctx->db;
        $queue = glob($this->ctx->config['import']['queue_dir'] . '/*') ?: [];
        $folder = glob($this->ctx->config['import']['folder'] . '/*.{pdf,PDF,zip,ZIP}', GLOB_BRACE) ?: [];
        $beat = $db->fetchValue("SELECT updated_at FROM settings WHERE name = 'worker_heartbeat'");

        return [
            'students' => (int) $db->fetchValue("SELECT COUNT(*) FROM users WHERE role = 'student'"),
            'with_pdf' => (int) $db->fetchValue("SELECT COUNT(DISTINCT user_id) FROM school_pdfs WHERE user_id IS NOT NULL AND active = 1"),
            'unassigned' => (int) $db->fetchValue('SELECT COUNT(*) FROM school_pdfs WHERE user_id IS NULL AND active = 1'),
            'saved' => (int) $db->fetchValue('SELECT COUNT(*) FROM selections'),
            'queue' => count(array_filter($queue, static fn (string $f): bool => !str_ends_with($f, '.part'))),
            'folder' => count($folder),
            'worker_seen' => $beat,
            'worker_ok' => $beat !== null && strtotime((string) $beat) > time() - 3 * max(15, (int) $this->ctx->config['import']['interval_seconds']),
            'last_log_id' => (int) $db->fetchValue('SELECT COALESCE(MAX(id), 0) FROM import_log'),
        ];
    }
}
