<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Context;

/**
 * Liefert den Planer mit den Startdaten eines Schülers aus – für den Schüler selbst oder
 * nur lesend für den Admin.
 */
final class PlanerPage
{
    /**
     * @param array<string, mixed> $student Zeile aus users (id, login, display_name)
     * @param array{adminView?: bool} $opt
     */
    public static function render(Context $ctx, array $student, array $opt = []): string
    {
        $id = (int) $student['id'];
        $adminView = $opt['adminView'] ?? false;
        $pdf = $ctx->db->fetchOne(
            'SELECT name, klasse, jahrgang, schueler_id, abitur_jahrgang_id, pdf_created_at, filename
             FROM school_pdfs WHERE user_id = ? AND active = 1',
            [$id],
        );
        $sel = $ctx->db->fetchOne('SELECT state, version, updated_at, summary FROM selections WHERE user_id = ?', [$id]);
        $lock = (new Deadline($ctx->db))->stateFor($id);

        $boot = [
            'login' => $student['login'],
            'displayName' => $student['display_name'],
            'csrf' => $ctx->csrf->token(),
            'profile' => $pdf === null ? null : [
                'name' => $pdf['name'],
                'klasse' => $pdf['klasse'],
                'jahrgang' => $pdf['jahrgang'],
                'schuelerId' => $pdf['schueler_id'],
                'jahrgangId' => $pdf['abitur_jahrgang_id'],
                'pdfCreatedAt' => $pdf['pdf_created_at'],
                'filename' => $pdf['filename'],
            ],
            'state' => $sel === null ? null : json_decode((string) $sel['state'], true),
            'version' => $sel === null ? 0 : (int) $sel['version'],
            'savedAt' => $sel['updated_at'] ?? null,
            // Ältere Speicherstände ohne Kurzfassung ergänzt der Planer beim nächsten Öffnen
            'needsSummary' => $sel !== null && $sel['summary'] === null,
            'lock' => $lock,
            // Pflichtkurse des Jahrgangs aus der Schul-PDF; ohne PDF die Vorlage
            'pflicht' => (new Pflicht($ctx->db))->forJahrgang($pdf['jahrgang'] ?? null),
            'angebot' => (new Kursangebot($ctx->db))->forJahrgang($pdf['jahrgang'] ?? null),
            'adminView' => $adminView,
            'readOnly' => $adminView || $lock['locked'],
            'pdfUrl' => $adminView ? "admin/students/{$id}/pdf" : 'api/pdf',
            'adminUrl' => $adminView ? 'admin' : null,
        ];

        $html = (string) file_get_contents(dirname(__DIR__, 2) . '/public/planer.html');
        $json = json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
        $html = str_replace('<meta charset="utf-8">', '<meta charset="utf-8">' . "\n" . '<base href="' . e($ctx->url('/')) . '">', $html);
        $html = str_replace('<script src="assets/kursangebot.js">',
            '<script type="application/json" id="kw-boot">' . $json . "</script>\n" . '<script src="assets/kursangebot.js">', $html);
        header('Cache-Control: no-store');

        return $html;
    }
}
