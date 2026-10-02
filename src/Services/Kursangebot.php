<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Kursangebot je Abiturjahrgang: angebotene Fächer mit Halbjahren, Zusatzkurse und Sportkurse.
 * Vorlage und Kopie je Jahrgang siehe JahrgangSetting. Das Standardangebot und die Namen stehen in
 * public/assets/kursangebot.js, die auch der Planer lädt.
 *
 * Gespeichert wird:
 *   faecher: id => angebotene Halbjahre (fehlt/leer = nicht angeboten)
 *   zusatz:  id => angebotene Halbjahre (nur innerhalb der Halbjahre aus dem Kurswahlformular)
 *   sport:   katalog (Kürzel => Name), sems (4 Listen von Kürzeln für Q1–Q4)
 */
final class Kursangebot extends JahrgangSetting
{
    private static ?array $catalog = null;

    /** Inhalt von public/assets/kursangebot.js */
    public static function catalog(): array
    {
        if (self::$catalog === null) {
            $src = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/kursangebot.js');
            if (!preg_match('#/\*JSON\*/(.*)/\*JSON\*/#s', $src, $m) || !is_array($c = json_decode($m[1], true))) {
                throw new \RuntimeException('public/assets/kursangebot.js ist ungültig.');
            }
            self::$catalog = $c;
        }

        return self::$catalog;
    }

    protected function name(): string
    {
        return 'angebot';
    }

    protected function standard(): array
    {
        $c = self::catalog();

        return [
            'faecher' => array_column($c['faecher'], 'sems', 'id'),
            'zusatz' => array_column($c['zusatz'], 'sems', 'id'),
            'sport' => $c['sport'],
        ];
    }

    protected function clean(mixed $v): array
    {
        $c = self::catalog();
        $v = is_array($v) ? $v : [];
        $out = ['faecher' => [], 'zusatz' => [], 'sport' => ['katalog' => [], 'sems' => [[], [], [], []]]];
        foreach ($c['faecher'] as $f) {
            $sems = !empty($f['immer']) ? $f['sems'] : self::sems($v['faecher'][$f['id']] ?? [], [1, 2, 3, 4]);
            if ($sems !== []) {
                $out['faecher'][$f['id']] = $sems;
            }
        }
        foreach ($c['zusatz'] as $z) {
            $sems = self::sems($v['zusatz'][$z['id']] ?? [], $z['sems']);
            if ($sems !== []) {
                $out['zusatz'][$z['id']] = $sems;
            }
        }
        foreach ((array) ($v['sport']['katalog'] ?? []) as $code => $name) {
            $code = strtoupper(trim((string) $code));
            $name = trim((string) $name);
            if (preg_match('/^[A-Z][A-Z0-9]{0,4}$/', $code) && $name !== '') {
                $out['sport']['katalog'][$code] = mb_substr($name, 0, 60);
            }
        }
        for ($i = 0; $i < 4; $i++) {
            $codes = array_map('strval', (array) ($v['sport']['sems'][$i] ?? []));
            $out['sport']['sems'][$i] = array_values(array_unique(array_intersect($codes, array_keys($out['sport']['katalog']))));
        }

        return $out;
    }

    /** Gültige, sortierte Halbjahre innerhalb der erlaubten */
    private static function sems(mixed $sems, array $allowed): array
    {
        return array_values(array_intersect($allowed, array_map('intval', is_array($sems) ? $sems : [])));
    }
}
