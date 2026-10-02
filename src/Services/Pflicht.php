<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Pflichtkurse der Schule je Abiturjahrgang: Fach und Halbjahre, die jeder Schüler belegen muss.
 * Vorlage und Kopie je Jahrgang siehe JahrgangSetting.
 *
 * Fächer und erlaubte Halbjahre entsprechen SUBJECTS in public/assets/planer.js. Bilinguale Kurse
 * stehen hier nicht: Sie erfüllen im Planer automatisch die Pflicht des regulären Fachs. Fremdsprachen
 * und Fächer mit Wahlpflicht-Voraussetzung fehlen bewusst – sie hängen vom Schüler ab.
 */
final class Pflicht extends JahrgangSetting
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
        'psy' => ['Psychologie', [1, 2, 3, 4], false],
        'ma' => ['Mathematik', [1, 2, 3, 4], false],
        'ph' => ['Physik', [1, 2, 3, 4], false],
        'ch' => ['Chemie', [1, 2, 3, 4], false],
        'bi' => ['Biologie', [1, 2, 3, 4], false],
    ];

    private const DEFAULT = [['id' => 'de', 'sems' => [1, 2, 3, 4]], ['id' => 'ma', 'sems' => [1, 2, 3, 4]]];

    protected function name(): string
    {
        return 'pflicht';
    }

    protected function standard(): array
    {
        return self::DEFAULT;
    }

    /** Einstellung aus der Zeit vor den Jahrgängen wird zur Vorlage */
    protected function legacyPreset(): ?string
    {
        return $this->get('pflicht');
    }

    /** @return list<array{id: string, sems: list<int>}> */
    protected function clean(mixed $list): array
    {
        if (!is_array($list)) {
            return self::DEFAULT;
        }
        $out = [];
        foreach ($list as $p) {
            if (is_array($p) && isset(self::SUBJECTS[$p['id'] ?? '']) && is_array($p['sems'] ?? null)) {
                $sems = array_values(array_intersect([1, 2, 3, 4], array_map('intval', $p['sems'])));
                if ($sems !== []) {
                    $out[] = ['id' => (string) $p['id'], 'sems' => $sems];
                }
            }
        }

        return $out;
    }

    /** @return list<string> */
    public function jahrgaenge(): array
    {
        return Jahrgaenge::all($this->db);
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
            $sems = self::blocks(is_array($marks[$id] ?? null) ? $marks[$id] : [], $allowed);
            if ($sems !== []) {
                $list[] = ['id' => $id, 'sems' => $sems];
            }
        }
        $this->store($jahrgang, $list);
    }

    /** Angekreuzte Halbjahre → ganze Blöcke (Q1+Q2, Q3+Q4) innerhalb der erlaubten Halbjahre */
    public static function blocks(array $marked, array $allowed = [1, 2, 3, 4]): array
    {
        $q = array_map('intval', $marked);
        $sems = [];
        foreach ([[1, 2], [3, 4]] as $block) {
            if (array_intersect($block, $q) !== [] && array_diff($block, $allowed) === []) {
                array_push($sems, ...$block);
            }
        }

        return $sems;
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
}
