<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AppVersion;
use App\Services\HilfeSetting;

/**
 * Hilfe zur Kurswahl: Leitfaden mit Erklärvideo. Standardmäßig nur für angemeldete Konten; der Admin kann
 * die Seite in den Einstellungen für alle freigeben (enthält keine persönlichen Daten).
 */
final class HilfeController extends Controller
{
    public function index(): string
    {
        if (!(new HilfeSetting($this->ctx->db))->isPublic()) {
            $this->ctx->auth->require(); // ohne Anmeldung: Umleitung zur Anmeldeseite
        }
        header('Cache-Control: private, no-store');
        $html = (string) file_get_contents(dirname(__DIR__, 2) . '/templates/hilfe.html');
        $html = str_replace('<meta charset="utf-8">', '<meta charset="utf-8">' . "\n" . '<base href="' . e($this->ctx->url('/')) . '">', $html);
        $html = str_replace('<footer>', '<footer>' . "\n    " . '<span>Kurswahl-Planer, Version ' . e(AppVersion::current()) . '</span>', $html);

        return $html;
    }
}
