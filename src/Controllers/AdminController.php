<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Services\AdminLog;
use App\Services\Datenschutz;
use App\Services\Deadline;
use App\Services\LoginName;
use App\Services\PlanerPage;
use App\Services\SchoolPdfPatcher;
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
        } elseif ($filter === 'abgegeben') {
            $where .= ' AND s.submitted_at IS NOT NULL';
        } elseif ($filter === 'nicht-abgegeben') {
            $where .= ' AND s.submitted_at IS NULL';
        } elseif ($filter === 'fehler') {
            $where .= " AND JSON_VALUE(s.summary, '$.errors') > 0";
        }
        $students = $db->fetchAll(
            "SELECT u.id, u.login, u.display_name, u.last_login_at, p.id AS pdf_id, p.name AS pdf_name, p.klasse,
                    p.schueler_id, s.updated_at AS saved_at, s.submitted_at,
                    JSON_VALUE(s.summary, '$.errors') AS errors
             FROM users u
             LEFT JOIN school_pdfs p ON p.user_id = u.id AND p.active = 1
             LEFT JOIN selections s ON s.user_id = u.id
             WHERE {$where} ORDER BY u.login LIMIT 500",
            $params,
        );

        return $this->ctx->view->render('admin/index', [
            'title' => 'Admin',
            'subtitle' => 'Administration',
            'nav' => 'uebersicht',
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
            AdminLog::add($this->ctx, 'upload', "{$queued} Datei(en)");
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
        AdminLog::add($this->ctx, 'konten_import', "{$r['created']} neu, {$r['updated']} aktualisiert, " . count($r['errors']) . ' Fehler');
        $this->ctx->session->flash('ok', "Konten: {$r['created']} neu, {$r['updated']} aktualisiert. {$matched} PDF(s) neu zugeordnet.");
        foreach (array_slice($r['errors'], 0, 30) as $e) {
            $this->ctx->session->flash('error', $e);
        }
        if (count($r['errors']) > 30) {
            $this->ctx->session->flash('error', '… und ' . (count($r['errors']) - 30) . ' weitere Fehler.');
        }

        return $this->redirect('/admin');
    }

    /** Einzelnes Schülerkonto von Hand anlegen; eine passende PDF wird gleich zugeordnet. */
    public function createUser(): string
    {
        $this->ctx->auth->requireAdmin();
        $this->verifyCsrf();
        $name = trim((string) ($_POST['name'] ?? ''));
        $login = LoginName::normalize((string) ($_POST['login'] ?? ''));
        $pw = (string) ($_POST['password'] ?? '');
        if ($login === '') {
            $login = LoginName::fromName($name);
        }
        $error = match (true) {
            $login === '' => 'Gib einen Namen oder Login an.',
            !preg_match('/^[a-z0-9-]+(\.[a-z0-9-]+)?$/', $login) => "Ungültiger Login „{$login}“ (nur a–z, 0–9, Bindestrich, ein Punkt).",
            strlen($pw) < 4 => 'Das Passwort muss mindestens 4 Zeichen haben.',
            $this->ctx->db->fetchValue('SELECT id FROM users WHERE login = ?', [$login]) !== null => "Das Konto {$login} gibt es schon.",
            default => null,
        };
        if ($error !== null) {
            $this->ctx->session->flash('error', $error);

            return $this->redirect('/admin');
        }
        $this->ctx->db->run(
            "INSERT INTO users (login, password_hash, role, display_name) VALUES (?, ?, 'student', ?)",
            [$login, password_hash($pw, PASSWORD_DEFAULT), mb_substr($name, 0, 160)],
        );
        $matched = (new PdfImporter($this->ctx->db))->rematch();
        AdminLog::add($this->ctx, 'konto_neu', $login);
        $this->ctx->session->flash('ok', "Konto {$login} angelegt." . ($matched > 0 ? ' Seine Kurswahl-PDF wurde zugeordnet.' : ' Noch keine passende PDF vorhanden.'));

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
        AdminLog::add($this->ctx, 'passwort', $this->loginOf((int) $p['id']));
        $this->ctx->session->flash('ok', 'Passwort gesetzt.');

        return $this->redirect('/admin');
    }

    public function deleteUser(array $p): string
    {
        $this->ctx->auth->requireAdmin();
        $this->verifyCsrf();
        AdminLog::add($this->ctx, 'konto_geloescht', $this->loginOf((int) $p['id']));
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
        AdminLog::add($this->ctx, 'pdf_zugeordnet', 'PDF ' . (int) $p['id'] . ' → ' . ($userId > 0 ? $this->loginOf($userId) : 'ohne Konto'));
        $this->ctx->session->flash('ok', $userId > 0 ? 'PDF zugeordnet.' : 'Zuordnung gelöst.');

        return $this->redirect('/admin');
    }

    public function deletePdf(array $p): string
    {
        $this->ctx->auth->requireAdmin();
        $this->verifyCsrf();
        $name = (string) $this->ctx->db->fetchValue('SELECT name FROM school_pdfs WHERE id = ?', [(int) $p['id']]);
        AdminLog::add($this->ctx, 'pdf_geloescht', $name);
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

    public function saveSettings(): string
    {
        $this->ctx->auth->requireAdmin();
        $this->verifyCsrf();
        $raw = trim((string) ($_POST['deadline'] ?? ''));
        $mode = (string) ($_POST['mode'] ?? 'readonly');
        $deadline = null;
        if ($raw !== '') {
            $dt = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $raw);
            if ($dt === false) {
                $this->ctx->session->flash('error', 'Ungültiges Datum.');

                return $this->redirect('/admin/einstellungen');
            }
            $deadline = $dt->format('Y-m-d H:i:00');
        }
        if (!isset(Deadline::MODES[$mode])) {
            throw new HttpException(400);
        }
        (new Deadline($this->ctx->db))->save($deadline, $mode);
        AdminLog::add($this->ctx, 'frist', ($deadline ?? 'keine') . ", {$mode}");
        $this->ctx->session->flash('ok', $deadline === null ? 'Abgabefrist entfernt.' : 'Abgabefrist gespeichert: ' . date('d.m.Y H:i', (int) strtotime($deadline)) . ' Uhr.');

        return $this->redirect('/admin/einstellungen');
    }

    /** Planer mit der Wahl des Schülers, nur lesend. */
    public function viewStudent(array $p): string
    {
        $this->ctx->auth->requireAdmin();

        $student = $this->student((int) $p['id']);
        AdminLog::add($this->ctx, 'wahl_angesehen', $student['login']);

        return PlanerPage::render($this->ctx, $student, ['adminView' => true]);
    }

    /** Original-PDF des Schülers (Vorlage für den Planer in der Admin-Ansicht). */
    public function studentPdf(array $p): string
    {
        $this->ctx->auth->requireAdmin();
        $row = $this->ctx->db->fetchOne('SELECT pdf FROM school_pdfs WHERE user_id = ? AND active = 1', [(int) $p['id']]);
        if ($row === null) {
            throw new HttpException(404, 'Für dieses Konto liegt keine Kurswahl-PDF vor.');
        }
        header('Content-Type: application/pdf');
        header('Cache-Control: private, no-store');

        return (string) $row['pdf'];
    }

    /** Ausgefülltes Formular eines Schülers: seine Original-PDF mit den Kreuzen seiner gespeicherten Wahl. */
    public function studentForm(array $p): string
    {
        $this->ctx->auth->requireAdmin();
        $row = $this->formRows('u.id = ?', [(int) $p['id']])[0] ?? null;
        if ($row === null || $row['pdf'] === null) {
            throw new HttpException(404, 'Für dieses Konto liegt keine Kurswahl-PDF vor.');
        }
        [$bytes] = $this->filledPdf($row);
        AdminLog::add($this->ctx, 'formular', $row['login']);
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', (string) $row['filename']) . '"');

        return $bytes;
    }

    public function unlock(array $p): string
    {
        $this->ctx->auth->requireAdmin();
        $this->verifyCsrf();
        $this->ctx->db->run('UPDATE selections SET submitted_at = NULL WHERE user_id = ?', [(int) $p['id']]);
        AdminLog::add($this->ctx, 'freigeschaltet', $this->loginOf((int) $p['id']));
        $this->ctx->session->flash('ok', 'Wahl wieder freigeschaltet – der Schüler kann sie ändern und erneut abgeben.');

        return $this->redirect('/admin');
    }

    /**
     * Alle Formulare als ZIP: jede Original-PDF mit den Kreuzen der gespeicherten Wahl, unter dem
     * Original-Dateinamen. Ohne gespeicherte Wahl bleibt die PDF unausgefüllt (siehe Hinweise.txt).
     */
    public function exportForms(): string
    {
        $this->ctx->auth->requireAdmin();
        $nur = (string) ($_GET['nur'] ?? '');
        $where = 'p.id IS NOT NULL' . ($nur === 'abgegeben' ? ' AND s.submitted_at IS NOT NULL' : '');
        $rows = $this->formRows($where, []);
        $tmp = tempnam(sys_get_temp_dir(), 'kw');
        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::OVERWRITE);
        $used = [];
        $notes = [];
        foreach ($rows as $r) {
            try {
                [$bytes, $note] = $this->filledPdf($r);
            } catch (\Throwable $e) {
                $notes[] = "{$r['login']}: nicht exportiert – {$e->getMessage()}";
                continue;
            }
            if ($note !== null) {
                $notes[] = "{$r['login']}: {$note}";
            }
            $name = (string) $r['filename'];
            $base = preg_replace('/\.pdf$/i', '', $name);
            for ($i = 2; isset($used[strtolower($name)]); $i++) {
                $name = "{$base}-{$i}.pdf";
            }
            $used[strtolower($name)] = true;
            $zip->addFromString($name, $bytes);
        }
        if ($notes !== []) {
            $zip->addFromString('Hinweise.txt', "Hinweise zum Export vom " . date('d.m.Y H:i') . "\r\n\r\n" . implode("\r\n", $notes) . "\r\n");
        }
        $zip->close();
        AdminLog::add($this->ctx, 'export', 'Formulare (ZIP' . ($nur === 'abgegeben' ? ', nur abgegebene' : '') . '): ' . count($used) . ' PDFs');
        $data = (string) file_get_contents($tmp);
        @unlink($tmp);
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="Kurswahlformulare_' . date('Y-m-d_Hi') . '.zip"');

        return $data;
    }

    /** Alle Wahlen als CSV (Excel-tauglich: UTF-8 mit BOM, Semikolon). */
    public function exportCsv(): string
    {
        $this->ctx->auth->requireAdmin();
        $rows = $this->ctx->db->fetchAll(
            "SELECT u.login, u.display_name, p.name, p.klasse, p.schueler_id, s.updated_at, s.submitted_at, s.summary
             FROM users u
             LEFT JOIN school_pdfs p ON p.user_id = u.id AND p.active = 1
             LEFT JOIN selections s ON s.user_id = u.id
             WHERE u.role = 'student' ORDER BY p.klasse, u.login",
        );
        $out = fopen('php://temp', 'w+');
        fwrite($out, "\xEF\xBB\xBF");
        $cols = ['Login', 'Name', 'Klasse', 'Schüler-ID', 'Status', 'Abgegeben am', 'Gespeichert am', 'Offene Fehler', 'Hinweise',
            '1. LK', '2. LK', '3. PF', '4. PF', '5. PK', 'Form 5. PK', 'Kurse', 'Jahreswochenstunden', 'Q1', 'Q2', 'Q3', 'Q4'];
        fputcsv($out, $cols, ';', '"', '');
        $d = static fn (?string $v): string => $v ? date('d.m.Y H:i', (int) strtotime($v)) : '';
        foreach ($rows as $r) {
            $sum = $r['summary'] ? (json_decode((string) $r['summary'], true) ?: []) : [];
            $roles = $sum['roles'] ?? [];
            $q = $sum['sems'] ?? [];
            fputcsv($out, [
                $r['login'], $r['name'] ?? $r['display_name'], $r['klasse'] ?? '', $r['schueler_id'] ?? '',
                $r['submitted_at'] ? 'abgegeben' : ($r['updated_at'] ? 'gespeichert' : 'keine Wahl'),
                $d($r['submitted_at']), $d($r['updated_at']),
                $sum['errors'] ?? '', $sum['warnings'] ?? '',
                $roles['lk1'] ?? '', $roles['lk2'] ?? '', $roles['pf3'] ?? '', $roles['pf4'] ?? '', $roles['pk5'] ?? '',
                $sum['pkForm'] ?? '', $sum['total'] ?? '', $sum['hours'] ?? '',
                implode(', ', $q[0] ?? []), implode(', ', $q[1] ?? []), implode(', ', $q[2] ?? []), implode(', ', $q[3] ?? []),
            ], ';', '"', '');
        }
        rewind($out);
        AdminLog::add($this->ctx, 'export', 'Wahlen (CSV): ' . count($rows) . ' Konten');
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="Kurswahlen_' . date('Y-m-d_Hi') . '.csv"');

        return (string) stream_get_contents($out);
    }

    /** @return array<string, mixed> */
    private function student(int $id): array
    {
        $u = $this->ctx->db->fetchOne("SELECT id, login, display_name FROM users WHERE id = ? AND role = 'student'", [$id]);
        if ($u === null) {
            throw new HttpException(404, 'Konto nicht gefunden.');
        }

        return $u;
    }

    /** Einstellungen: Abgabefrist und Datenschutzhinweis */
    public function settings(): string
    {
        $this->ctx->auth->requireAdmin();

        return $this->ctx->view->render('admin/einstellungen', [
            'title' => 'Einstellungen', 'subtitle' => 'Administration', 'nav' => 'einstellungen',
            'flashes' => $this->ctx->session->pullFlashes(),
            'deadline' => (new Deadline($this->ctx->db))->settings(),
            'deadlineModes' => Deadline::MODES,
            'datenschutz' => Datenschutz::text($this->ctx->db),
        ]);
    }

    public function saveDatenschutz(): string
    {
        $this->ctx->auth->requireAdmin();
        $this->verifyCsrf();
        Datenschutz::save($this->ctx->db, (string) ($_POST['text'] ?? ''));
        AdminLog::add($this->ctx, 'datenschutz');
        $this->ctx->session->flash('ok', 'Datenschutzhinweis gespeichert.');

        return $this->redirect('/admin/einstellungen');
    }

    /** Protokoll der Admin-Aktionen */
    public function log(): string
    {
        $this->ctx->auth->requireAdmin();

        return $this->ctx->view->render('admin/protokoll', [
            'title' => 'Protokoll', 'subtitle' => 'Administration', 'nav' => 'protokoll',
            'rows' => $this->ctx->db->fetchAll('SELECT admin_login, action, details, ip_address, created_at FROM admin_log ORDER BY id DESC LIMIT 500'),
        ]);
    }

    private function loginOf(int $id): string
    {
        return (string) ($this->ctx->db->fetchValue('SELECT login FROM users WHERE id = ?', [$id]) ?? "#{$id}");
    }

    /** @return list<array<string, mixed>> */
    private function formRows(string $where, array $params): array
    {
        return $this->ctx->db->fetchAll(
            "SELECT u.id, u.login, p.pdf, p.filename, s.summary, s.state
             FROM users u
             LEFT JOIN school_pdfs p ON p.user_id = u.id AND p.active = 1
             LEFT JOIN selections s ON s.user_id = u.id
             WHERE u.role = 'student' AND {$where} ORDER BY u.login",
            $params,
        );
    }

    /**
     * @param array<string, mixed> $r
     * @return array{string, ?string} PDF-Bytes und ggf. Hinweis
     */
    private function filledPdf(array $r): array
    {
        $sum = $r['summary'] ? json_decode((string) $r['summary'], true) : null;
        if (!is_array($sum) || !is_array($sum['checks'] ?? null)) {
            return [(string) $r['pdf'], $r['state'] ? 'Wahl noch ohne Kurzfassung (Schüler muss den Planer einmal öffnen) – PDF unausgefüllt.' : 'keine Wahl gespeichert – PDF unausgefüllt.'];
        }
        $pk = ($sum['pkField'] ?? '') === 'BLL_0' ? 'BLL_0' : 'Praesentation_0';

        $keys = is_array($sum['fieldKeys'] ?? null) ? array_values(array_map('strval', $sum['fieldKeys'])) : null;

        return [SchoolPdfPatcher::patch((string) $r['pdf'], array_values(array_map('strval', $sum['checks'])), $pk, $keys), null];
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
            'submitted' => (int) $db->fetchValue('SELECT COUNT(*) FROM selections WHERE submitted_at IS NOT NULL'),
            'queue' => count(array_filter($queue, static fn (string $f): bool => !str_ends_with($f, '.part'))),
            'folder' => count($folder),
            'worker_seen' => $beat,
            'worker_ok' => $beat !== null && strtotime((string) $beat) > time() - 3 * max(15, (int) $this->ctx->config['import']['interval_seconds']),
            'last_log_id' => (int) $db->fetchValue('SELECT COALESCE(MAX(id), 0) FROM import_log'),
        ];
    }
}
