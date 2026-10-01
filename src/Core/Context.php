<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Bündelt die Kern-Dienste, die jeder Controller braucht.
 */
final class Context
{
    /** @param array<string, mixed> $config */
    public function __construct(
        public readonly array $config,
        public readonly Database $db,
        public readonly Session $session,
        public readonly Csrf $csrf,
        public readonly Auth $auth,
        public readonly View $view,
    ) {
    }

    /** Absoluter Pfad innerhalb der Anwendung (berücksichtigt BASE_URL). */
    public function url(string $path): string
    {
        return $this->config['app']['base_url'] . '/' . ltrim($path, '/');
    }

    /** Client-IP; X-Forwarded-For nur von eingetragenen Reverse-Proxys. */
    public function clientIp(): string
    {
        $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $fwd = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
        if ($fwd !== '' && in_array($remote, $this->config['app']['trusted_proxies'], true)) {
            $parts = array_map('trim', explode(',', $fwd));

            return (string) end($parts);
        }

        return $remote;
    }
}
