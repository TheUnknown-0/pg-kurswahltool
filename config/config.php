<?php

declare(strict_types=1);

/**
 * Zentrale Konfiguration — liest Umgebungsvariablen mit sinnvollen Defaults.
 */

$env = static fn (string $key, ?string $default = null): ?string => (
    ($v = getenv($key)) !== false && $v !== '' ? $v : $default
);

$root = dirname(__DIR__);

return [
    'app' => [
        'env' => $env('APP_ENV', 'production'),
        'base_url' => rtrim($env('BASE_URL', '/'), '/'),
        // Secure-Cookie-Flag automatisch bei HTTPS, per Env erzwingbar.
        'secure_cookies' => $env('SECURE_COOKIES') === '1'
            || (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? 'off') !== 'off'),
        'trusted_proxies' => array_values(array_filter(array_map('trim', explode(',', (string) $env('TRUSTED_PROXIES', ''))))),
    ],
    'db' => [
        'host' => $env('DB_HOST', '127.0.0.1'),
        'name' => $env('DB_NAME', 'kurswahl'),
        'user' => $env('DB_USER', 'kurswahl'),
        'pass' => $env('DB_PASS', ''),
    ],
    'import' => [
        // Hochgeladene Dateien warten hier auf den Worker
        'queue_dir' => $env('QUEUE_DIR', $root . '/uploads/queue'),
        // Import-Ordner: PDFs oder ZIPs hier ablegen, der Worker liest sie ein
        'folder' => $env('IMPORT_DIR', $root . '/import'),
        'interval_seconds' => (int) $env('IMPORT_INTERVAL', '15'),
        'max_upload_bytes' => 200 * 1024 * 1024,
    ],
];
