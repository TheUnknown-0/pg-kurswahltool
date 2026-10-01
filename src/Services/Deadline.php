<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Abgabefrist und Sperre der Wahl.
 *
 * Der Admin stellt Datum und Verhalten nach Fristende ein:
 *   readonly – Schüler können sich anmelden und ansehen, aber nichts mehr ändern
 *   login    – Schüler können sich nicht mehr anmelden
 *   hint     – nur Hinweis, Änderungen bleiben möglich
 * Eine abgegebene Wahl ist immer gesperrt, bis der Admin sie wieder freischaltet.
 */
final class Deadline
{
    public const MODES = [
        'readonly' => 'Nur noch ansehen (keine Änderungen)',
        'login' => 'Anmeldung für Schüler gesperrt',
        'hint' => 'Nur Hinweis, Änderungen bleiben möglich',
    ];

    public function __construct(private readonly Database $db)
    {
    }

    /** @return array{deadline: ?string, mode: string} */
    public function settings(): array
    {
        $rows = $this->db->fetchAll("SELECT name, value FROM settings WHERE name IN ('deadline', 'deadline_mode')");
        $s = array_column($rows, 'value', 'name');
        $mode = $s['deadline_mode'] ?? 'readonly';

        return [
            'deadline' => ($s['deadline'] ?? '') !== '' ? $s['deadline'] : null,
            'mode' => isset(self::MODES[$mode]) ? $mode : 'readonly',
        ];
    }

    public function save(?string $deadline, string $mode): void
    {
        foreach (['deadline' => $deadline ?? '', 'deadline_mode' => $mode] as $name => $value) {
            $this->db->run(
                'INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)',
                [$name, $value],
            );
        }
    }

    public function expired(): bool
    {
        $d = $this->settings()['deadline'];

        return $d !== null && strtotime($d) <= time();
    }

    /** Darf sich ein Schüler noch anmelden? */
    public function loginBlocked(): bool
    {
        return $this->expired() && $this->settings()['mode'] === 'login';
    }

    /**
     * Sperrzustand für einen Schüler.
     *
     * @return array{deadline: ?string, mode: string, expired: bool, submittedAt: ?string, locked: bool}
     */
    public function stateFor(int $userId): array
    {
        $s = $this->settings();
        $expired = $this->expired();
        $submitted = $this->db->fetchValue('SELECT submitted_at FROM selections WHERE user_id = ?', [$userId]);

        return [
            'deadline' => $s['deadline'],
            'mode' => $s['mode'],
            'expired' => $expired,
            'submittedAt' => $submitted,
            'locked' => $submitted !== null || ($expired && $s['mode'] !== 'hint'),
        ];
    }
}
