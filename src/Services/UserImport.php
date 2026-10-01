<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Import der Schülerkonten mit Passwörtern.
 *
 * VORLÄUFIGES FORMAT – das endgültige Format der Schule steht noch aus; dann wird nur
 * parse() angepasst. Bis dahin: CSV (Trennzeichen ; oder , oder Tab, UTF-8) mit Kopfzeile
 * und den Spalten
 *     login;passwort[;name]
 * „login“ ist vorname.nachname. Fehlt die Spalte login, aber „name“ ist da, wird der Login
 * aus dem Namen abgeleitet (LoginName). Vorhandene Konten bekommen das neue Passwort.
 */
final class UserImport
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * @return array{created: int, updated: int, errors: list<string>}
     */
    public function import(string $csv): array
    {
        $rows = $this->parse($csv);
        $result = ['created' => 0, 'updated' => 0, 'errors' => $rows['errors']];

        foreach ($rows['users'] as [$line, $login, $password, $name]) {
            if (!preg_match('/^[a-z0-9-]+(\.[a-z0-9-]+)?$/', $login)) {
                $result['errors'][] = "Zeile {$line}: ungültiger Login „{$login}“.";
                continue;
            }
            if (strlen($password) < 4) {
                $result['errors'][] = "Zeile {$line}: Passwort für {$login} ist kürzer als 4 Zeichen.";
                continue;
            }
            $existing = $this->db->fetchOne('SELECT id, role FROM users WHERE login = ?', [$login]);
            if ($existing !== null && $existing['role'] !== 'student') {
                $result['errors'][] = "Zeile {$line}: {$login} ist ein Admin-Konto und wird nicht überschrieben.";
                continue;
            }
            $hash = password_hash($password, PASSWORD_DEFAULT);
            if ($existing === null) {
                $this->db->run(
                    "INSERT INTO users (login, password_hash, role, display_name) VALUES (?, ?, 'student', ?)",
                    [$login, $hash, $name],
                );
                $result['created']++;
            } else {
                $this->db->run(
                    'UPDATE users SET password_hash = ?, display_name = IF(? = \'\', display_name, ?) WHERE id = ?',
                    [$hash, $name, $name, $existing['id']],
                );
                $result['updated']++;
            }
        }

        return $result;
    }

    /**
     * @return array{users: list<array{int, string, string, string}>, errors: list<string>}
     */
    private function parse(string $csv): array
    {
        $csv = (string) preg_replace('/^\xEF\xBB\xBF/', '', $csv);
        if (!mb_check_encoding($csv, 'UTF-8')) {
            $csv = mb_convert_encoding($csv, 'UTF-8', 'Windows-1252');
        }
        $lines = preg_split('/\r\n|\r|\n/', $csv) ?: [];
        $header = '';
        while ($lines !== [] && trim($header) === '') {
            $header = (string) array_shift($lines);
        }
        $sep = substr_count($header, ';') > 0 ? ';' : (substr_count($header, "\t") > 0 ? "\t" : ',');
        $cols = array_map(static fn (string $c): string => mb_strtolower(trim($c, " \"'")), str_getcsv($header, $sep, '"', ''));
        $idx = static function (array $names) use ($cols): ?int {
            foreach ($names as $n) {
                $i = array_search($n, $cols, true);
                if ($i !== false) {
                    return (int) $i;
                }
            }

            return null;
        };
        $iLogin = $idx(['login', 'benutzername', 'benutzer']);
        $iPass = $idx(['passwort', 'password', 'kennwort']);
        $iName = $idx(['name', 'vollständiger name']);

        $out = ['users' => [], 'errors' => []];
        if ($iPass === null || ($iLogin === null && $iName === null)) {
            $out['errors'][] = 'Kopfzeile muss die Spalten „login“ (oder „name“) und „passwort“ enthalten.';

            return $out;
        }
        foreach ($lines as $n => $line) {
            if (trim($line) === '') {
                continue;
            }
            $f = str_getcsv($line, $sep, '"', '');
            $name = trim((string) ($iName !== null ? ($f[$iName] ?? '') : ''));
            $login = LoginName::normalize((string) ($iLogin !== null ? ($f[$iLogin] ?? '') : ''));
            if ($login === '' && $name !== '') {
                $login = LoginName::fromName($name);
            }
            $out['users'][] = [$n + 2, $login, (string) ($f[$iPass] ?? ''), $name];
        }

        return $out;
    }
}
