<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Pflichtkurse der Schule je Abiturjahrgang: Fach und Halbjahre, die jeder Schüler belegen muss.
 *
 * Es gibt eine Vorlage für neue Jahrgänge. Taucht ein Jahrgang zum ersten Mal auf (Import seiner PDFs,
 * Admin-Bereich, Planer), bekommt er eine Kopie der Vorlage. Spätere Änderungen an der Vorlage wirken
 * deshalb nicht rückwirkend auf bestehende Jahrgänge. Schüler ohne Schul-PDF (also ohne Jahrgang)
 * erhalten die Vorlage.
 *
 * Fächer und erlaubte Halbjahre entsprechen SUBJECTS in public/assets/planer.js. Bilinguale Kurse
 * stehen hier nicht: Sie erfüllen im Planer automatisch die Pflicht des regulären Fachs. Fremdsprachen
 * und Fächer mit Wahlpflicht-Voraussetzung fehlen bewusst – sie hängen vom Schüler ab.
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

    private const DEFAULT = [['id' => 'de', 'sems' => [1, 2, 3, 4]], ['id' => 'ma', 'sems' => [1, 2, 3, 4]]];
    private const PRESET = 'pflicht_vorlage';
    private const PREFIX = 'pflicht_jg:';

    public function __construct(private readonly Database $db)
    {
    }

    /** @return list<array{id: string, sems: list<int>}> */
    public function preset(): array
    {
        // 'pflicht' = Einstellung aus der Zeit vor den Jahrgängen, wird zur Vorlage
        $raw = $this->get(self::PRESET) ?? $this->get('pflicht');

        return $raw === null ? self::DEFAULT : self::clean(json_decode($raw, true));
    }

    /**
     * Pflichtkurse eines Jahrgangs; beim ersten Zugriff wird die Vorlage kopiert.
     *
     * @return list<array{id: string, sems: list<int>}>
     */
    public function forJahrgang(?string $jahrgang): array
    {
        $jahrgang = trim((string) $jahrgang);
        if ($jahrgang === '') {
            return $this->preset();
        }
        $raw = $this->get(self::PREFIX . $jahrgang);
        if ($raw !== null) {
            return self::clean(json_decode($raw, true));
        }
        $list = $this->preset();
        $this->set(self::PREFIX . $jahrgang, $list);

        return $list;
    }

    /** Alle bekannten Jahrgänge: aus den Schul-PDFs und bereits angelegten Einstellungen, neueste zuerst. */
    public function jahrgaenge(): array
    {
        $fromPdfs = $this->db->fetchAll("SELECT DISTINCT jahrgang FROM school_pdfs WHERE jahrgang <> ''");
        $fromSettings = $this->db->fetchAll('SELECT name FROM settings WHERE name LIKE ?', [self::PREFIX . '%']);
        $all = array_merge(
            array_column($fromPdfs, 'jahrgang'),
            array_map(static fn (array $r): string => substr($r['name'], strlen(self::PREFIX)), $fromSettings),
        );
        $all = array_values(array_unique($all));
        rsort($all, SORT_NATURAL);

        return $all;
    }

    /**
     * Speichert die Kreuze aus dem Admin-Raster. Ein Kreuz zählt immer für den Halbjahresblock
     * (Q1+Q2 bzw. Q3+Q4), so wie im Planer.
     *
     * @param array<string, mixed> $marks id => Liste angekreuzter Halbjahre
     * @param string|null $jahrgang null = Vorlage
     */
    public function save(?string $jahrgang, array $marks): void
    {
        $list = [];
        foreach (self::SUBJECTS as $id => [, $allowed]) {
            $q = array_map('intval', is_array($marks[$id] ?? null) ? $marks[$id] : []);
            $sems = [];
            foreach ([[1, 2], [3, 4]] as $block) {
                if (array_intersect($block, $q) !== [] && array_diff($block, $allowed) === []) {
                    array_push($sems, ...$block);
                }
            }
            if ($sems !== []) {
                $list[] = ['id' => $id, 'sems' => $sems];
            }
        }
        $this->set($jahrgang === null ? self::PRESET : self::PREFIX . $jahrgang, $list);
    }

    /** @return array<string, list<int>> id => Halbjahre, für das Raster */
    public static function grid(array $list): array
    {
        $out = array_fill_keys(array_keys(self::SUBJECTS), []);
        foreach ($list as $p) {
            $out[$p['id']] = $p['sems'];
        }

        return $out;
    }

    /** @return list<array{id: string, sems: list<int>}> */
    private static function clean(mixed $list): array
    {
        if (!is_array($list)) {
            return self::DEFAULT;
        }
        $out = [];
        foreach ($list as $p) {
            if (is_array($p) && isset(self::SUBJECTS[$p['id'] ?? '']) && is_array($p['sems'] ?? null)) {
                $out[] = ['id' => (string) $p['id'], 'sems' => array_values(array_map('intval', $p['sems']))];
            }
        }

        return $out;
    }

    private function get(string $name): ?string
    {
        $v = $this->db->fetchValue('SELECT value FROM settings WHERE name = ?', [$name]);

        return $v === null ? null : (string) $v;
    }

    /** @param list<array{id: string, sems: list<int>}> $list */
    private function set(string $name, array $list): void
    {
        $this->db->run(
            'INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)',
            [$name, json_encode($list)],
        );
    }
}
