<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AppVersion;

/**
 * Hilfe zur Kurswahl: Leitfaden mit Erklärvideo. Ohne Anmeldung erreichbar, damit Schüler
 * sich schon vor dem ersten Login informieren können; enthält keine persönlichen Daten.
 */
final class HilfeController extends Controller
{
    public function index(): string
    {
        $html = (string) file_get_contents(dirname(__DIR__, 2) . '/templates/hilfe.html');
        $html = str_replace('<meta charset="utf-8">', '<meta charset="utf-8">' . "\n" . '<base href="' . e($this->ctx->url('/')) . '">', $html);
        $html = str_replace('<footer>', '<footer>' . "\n    " . '<span>Kurswahl-Planer, Version ' . e(AppVersion::current()) . '</span>', $html);

        return $html;
    }
}
