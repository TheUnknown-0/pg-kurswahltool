<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Context;

/**
 * Protokoll der Admin-Aktionen. Passwörter und PDF-Inhalte werden nie protokolliert.
 */
final class AdminLog
{
    public const LABELS = [
        'login' => 'Anmeldung',
        'upload' => 'PDFs hochgeladen',
        'konten_import' => 'Konten importiert',
        'konto_neu' => 'Konto angelegt',
        'passwort' => 'Passwort gesetzt',
        'konto_geloescht' => 'Konto gelöscht',
        'pdf_zugeordnet' => 'PDF zugeordnet',
        'pdf_geloescht' => 'PDF gelöscht',
        'freigeschaltet' => 'Wahl freigeschaltet',
        'frist' => 'Abgabefrist geändert',
        'pflicht' => 'Pflichtkurse geändert',
        'angebot' => 'Kursangebot geändert',
        'jahrgang_geloescht' => 'Jahrgang gelöscht',
        'datenschutz' => 'Datenschutzhinweis geändert',
        'export' => 'Export',
        'wahl_angesehen' => 'Wahl angesehen',
        'formular' => 'Formular heruntergeladen',
        'eigenes_passwort' => 'Eigenes Passwort geändert',
    ];

    public static function add(Context $ctx, string $action, string $details = ''): void
    {
        $ctx->db->run(
            'INSERT INTO admin_log (admin_login, action, details, ip_address) VALUES (?, ?, ?, ?)',
            [(string) ($ctx->auth->user()['login'] ?? '?'), $action, mb_substr($details, 0, 1000), $ctx->clientIp()],
        );
    }
}
