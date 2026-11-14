<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Sichtbarkeit der Hilfeseite /hilfe: standardmäßig nur für angemeldete Konten,
 * der Admin kann sie in den Einstellungen für alle freigeben.
 */
final class HilfeSetting
{
    private const NAME = 'hilfe_oeffentlich';

    public function __construct(private readonly Database $db)
    {
    }

    public function isPublic(): bool
    {
        return $this->db->fetchValue('SELECT value FROM settings WHERE name = ?', [self::NAME]) === '1';
    }

    public function setPublic(bool $public): void
    {
        $this->db->run(
            'INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)',
            [self::NAME, $public ? '1' : '0'],
        );
    }
}
