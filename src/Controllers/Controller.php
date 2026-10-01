<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Context;
use App\Core\HttpException;

abstract class Controller
{
    public function __construct(protected readonly Context $ctx)
    {
    }

    /** CSRF-Prüfung für Formulare (Feld _csrf) und API-Aufrufe (Header X-CSRF-Token). */
    protected function verifyCsrf(): void
    {
        $token = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        if (!$this->ctx->csrf->validate(is_string($token) ? $token : null)) {
            throw new HttpException(419);
        }
    }

    protected function redirect(string $path): string
    {
        header('Location: ' . $this->ctx->url($path), true, 303);

        return '';
    }
}
