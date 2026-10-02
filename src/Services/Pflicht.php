<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Pflichtkurse der Schule: Fach und Halbjahre, die jeder Schüler belegen muss.
 *
 * Fächer und erlaubte Halbjahre entsprechen SUBJECTS in public/assets/planer.js. Bilinguale
 * Kurse stehen hier nicht: Sie erfüllen im Planer automatisch die Pflicht des regulären Fachs.
 * Fremdsprachen und Fächer mit Wahlpflicht-Voraussetzung fehlen bewusst – sie hängen vom Schüler ab.
 */
final class Pflicht
{
    /** id => [Name, erlaubte Halbjahre, bilinguale Variante vorhanden] */
    public const SUBJECTS = [
        'de' => ['Deutsch', [1, 2, 3, 4], false],
        'mu' => ['Musik', [1, 2, 3, 4], false],
        'ku' => ['Bildende Kunst', [1, 2, 3, 4], false],
        'ge' => ['Geschichte', [1, 2, 3, 4], true],
        'pw' => ['Politikwissenschaft', [1, 2, 3, 4], true],
        'geo' => ['Geografie', [1, 2, 3, 4], false],
        'phi' => ['Philosophie', [1, 2, 3, 4], false],
        'psy' => ['Psychologie', [1, 2], false],
        'ma' => ['Mathematik', [1, 2, 3, 4], false],
        'ph' => ['Physik', [1, 2, 3, 4], false],
        'ch' => ['Chemie', [1, 2, 3, 4], false],
        'bi' => ['Biologie', [1, 2, 3, 4], false],
    ];

    /** Auswahl im Admin-Bereich: Schlüssel = Halbjahre */
    public const OPTIONS = ['' => 'keine Pflicht', '12' => 'Q1–Q2', '34' => 'Q3–Q4', '1234' => 'Q1–Q4'];

    private const DEFAULT = [['id' => 'de', 'sems' => [1, 2, 3, 4]], ['id' => 'ma', 'sems' => [1, 2, 3, 4]]];

    public function __construct(private readonly Database $db)
    {
    }

    /** @return list<array{id: string, sems: list<int>}> */
    public function all(): array
    {
        $raw = $this->db->fetchValue("SELECT value FROM settings WHERE name = 'pflicht'");
        $list = $raw === null ? self::DEFAULT : json_decode((string) $raw, true);

        return is_array($list) ? array_values(array_filter($list, static fn ($p): bool => isset(self::SUBJECTS[$p['id'] ?? '']))) : self::DEFAULT;
    }

    /** @return array<string, string> id => Schlüssel aus OPTIONS */
    public function options(): array
    {
        $out = array_fill_keys(array_keys(self::SUBJECTS), '');
        foreach ($this->all() as $p) {
            $out[$p['id']] = implode('', $p['sems']);
        }

        return $out;
    }

    /** @param array<string, string> $choice id => Schlüssel aus OPTIONS */
    public function save(array $choice): void
    {
        $list = [];
        foreach (self::SUBJECTS as $id => [, $allowed]) {
            $key = (string) ($choice[$id] ?? '');
            if ($key === '' || !isset(self::OPTIONS[$key])) {
                continue;
            }
            $sems = array_map('intval', str_split($key));
            if (array_diff($sems, $allowed) !== []) {
                continue;
            }
            $list[] = ['id' => $id, 'sems' => $sems];
        }
        $this->db->run(
            "INSERT INTO settings (name, value) VALUES ('pflicht', ?) ON DUPLICATE KEY UPDATE value = VALUES(value)",
            [json_encode($list)],
        );
    }
}
