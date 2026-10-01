<?php

declare(strict_types=1);

/**
 * Legt das Admin-Konto an, falls es noch nicht existiert (aus ADMIN_LOGIN / ADMIN_PASSWORD).
 * Ein bestehendes Konto wird nicht verändert – außer mit --reset (setzt das Passwort neu).
 *
 * Aufruf: php bin/create-admin.php [--reset]
 */

[$config, $db] = require dirname(__DIR__) . '/src/bootstrap.php';

$login = strtolower(trim((string) (getenv('ADMIN_LOGIN') ?: 'admin')));
$password = (string) getenv('ADMIN_PASSWORD');
$reset = in_array('--reset', $argv, true);

$existing = $db->fetchOne('SELECT id, role FROM users WHERE login = ?', [$login]);
if ($existing !== null && !$reset) {
    echo "Admin-Konto {$login} existiert bereits.\n";
    exit(0);
}
if (strlen($password) < 10) {
    fwrite(STDERR, "ADMIN_PASSWORD fehlt oder ist kürzer als 10 Zeichen – kein Admin-Konto angelegt.\n");
    exit($existing === null ? 0 : 1);
}
$hash = password_hash($password, PASSWORD_DEFAULT);
if ($existing === null) {
    $db->run("INSERT INTO users (login, password_hash, role, display_name) VALUES (?, ?, 'admin', 'Administration')", [$login, $hash]);
    echo "Admin-Konto {$login} angelegt.\n";
} else {
    $db->run("UPDATE users SET password_hash = ?, role = 'admin' WHERE id = ?", [$hash, $existing['id']]);
    echo "Passwort für {$login} neu gesetzt.\n";
}
