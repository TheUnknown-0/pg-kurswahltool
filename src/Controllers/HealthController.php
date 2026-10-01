<?php

declare(strict_types=1);

namespace App\Controllers;

final class HealthController extends Controller
{
    /** Die Anwendung antwortet – bewusst auch bei Datenbankausfall (ein Neustart behebt den nicht). */
    public function healthz(): string
    {
        header('Content-Type: text/plain');

        return 'ok';
    }

    /** Vollständig bedienbereit inklusive Datenbank. */
    public function readyz(): string
    {
        header('Content-Type: text/plain');
        try {
            $this->ctx->db->fetchValue('SELECT 1 FROM users LIMIT 1');

            return 'ready';
        } catch (\Throwable) {
            http_response_code(503);

            return 'not ready';
        }
    }
}
