<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;

/**
 * Planer für Schüler: liefert die Seite mit den Startdaten aus, die eigene Schul-PDF und
 * speichert die aktuelle Wahl.
 */
final class PlanerController extends Controller
{
    private const MAX_STATE_BYTES = 64 * 1024;

    public function index(): string
    {
        $user = $this->ctx->auth->require();
        if ($user['role'] === 'admin') {
            return $this->redirect('/admin');
        }

        $pdf = $this->activePdf((int) $user['id']);
        $sel = $this->ctx->db->fetchOne('SELECT state, version, updated_at FROM selections WHERE user_id = ?', [$user['id']]);
        $boot = [
            'login' => $user['login'],
            'displayName' => $user['display_name'],
            'csrf' => $this->ctx->csrf->token(),
            'base' => $this->ctx->config['app']['base_url'],
            'profile' => $pdf === null ? null : [
                'name' => $pdf['name'],
                'klasse' => $pdf['klasse'],
                'jahrgang' => $pdf['jahrgang'],
                'schuelerId' => $pdf['schueler_id'],
                'jahrgangId' => $pdf['abitur_jahrgang_id'],
                'pdfCreatedAt' => $pdf['pdf_created_at'],
                'sha256' => $pdf['sha256'],
            ],
            'state' => $sel === null ? null : json_decode((string) $sel['state'], true),
            'version' => $sel === null ? 0 : (int) $sel['version'],
            'savedAt' => $sel['updated_at'] ?? null,
        ];

        $html = (string) file_get_contents(dirname(__DIR__, 2) . '/public/planer.html');
        $json = json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
        $inject = '<base href="' . e($this->ctx->url('/')) . '">';
        $html = str_replace('<meta charset="utf-8">', '<meta charset="utf-8">' . "\n" . $inject, $html);
        $html = str_replace('<script src="assets/form-template.js">',
            '<script type="application/json" id="kw-boot">' . $json . "</script>\n" . '<script src="assets/form-template.js">', $html);
        header('Cache-Control: no-store');

        return $html;
    }

    /** Die eigene, aktive Schul-PDF (Vorlage für das Formular mit Kreuzen). */
    public function pdf(): string
    {
        $user = $this->ctx->auth->require();
        $row = $this->ctx->db->fetchOne(
            'SELECT pdf, filename, sha256 FROM school_pdfs WHERE user_id = ? AND active = 1',
            [$user['id']],
        );
        if ($row === null) {
            throw new HttpException(404, 'Für dein Konto liegt keine Kurswahl-PDF vor.');
        }
        header('Content-Type: application/pdf');
        header('Cache-Control: private, no-store');
        header('X-Content-SHA256: ' . $row['sha256']);

        return (string) $row['pdf'];
    }

    /**
     * Speichert die Wahl. Mit „version“ wird erkannt, ob in der Zwischenzeit ein anderes
     * Gerät gespeichert hat; dann antwortet der Server mit 409 und dem aktuellen Stand.
     */
    public function saveState(): array
    {
        $user = $this->ctx->auth->require();
        $this->verifyCsrf();
        if ($user['role'] !== 'student') {
            throw new HttpException(403);
        }
        $raw = (string) file_get_contents('php://input');
        if (strlen($raw) > self::MAX_STATE_BYTES) {
            throw new HttpException(400, 'Die Wahl ist zu groß.');
        }
        $body = json_decode($raw, true);
        if (!is_array($body) || !is_array($body['state'] ?? null) || !is_int($body['version'] ?? null)) {
            throw new HttpException(400);
        }
        $state = json_encode($body['state'], JSON_UNESCAPED_UNICODE);
        $force = ($body['force'] ?? false) === true;

        return $this->ctx->db->transaction(function () use ($user, $state, $body, $force): array {
            $cur = $this->ctx->db->fetchOne('SELECT state, version, updated_at FROM selections WHERE user_id = ? FOR UPDATE', [$user['id']]);
            $curVersion = $cur === null ? 0 : (int) $cur['version'];
            if (!$force && $curVersion !== $body['version']) {
                http_response_code(409);

                return ['success' => false, 'conflict' => true, 'version' => $curVersion,
                    'state' => $cur === null ? null : json_decode((string) $cur['state'], true), 'savedAt' => $cur['updated_at'] ?? null];
            }
            $next = $curVersion + 1;
            $this->ctx->db->run(
                'INSERT INTO selections (user_id, state, version) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE state = VALUES(state), version = VALUES(version), updated_at = CURRENT_TIMESTAMP',
                [$user['id'], $state, $next],
            );

            return ['success' => true, 'version' => $next,
                'savedAt' => $this->ctx->db->fetchValue('SELECT updated_at FROM selections WHERE user_id = ?', [$user['id']])];
        });
    }

    /** @return array<string, mixed>|null */
    private function activePdf(int $userId): ?array
    {
        return $this->ctx->db->fetchOne(
            'SELECT name, klasse, jahrgang, schueler_id, abitur_jahrgang_id, pdf_created_at, sha256
             FROM school_pdfs WHERE user_id = ? AND active = 1',
            [$userId],
        );
    }
}
