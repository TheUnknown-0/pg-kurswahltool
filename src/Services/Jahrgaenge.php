<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Abiturjahrgänge: der aktuelle und die folgenden (im Voraus einstellbar) sowie alle, für die es schon
 * Schul-PDFs oder Einstellungen gibt.
 */
final class Jahrgaenge
{
    /** Wie viele Abiturjahrgänge ab dem aktuellen im Voraus einstellbar sind. */
    public const YEARS_AHEAD = 3;

    /**
     * Aktueller Abiturjahrgang und die folgenden (Schuljahreswechsel im August),
     * z. B. im Herbst 2026: Abitur 2026/27, 2027/28, 2028/29.
     *
     * @return list<string>
     */
    public static function upcoming(?\DateTimeInterface $now = null): array
    {
        $now ??= new \DateTimeImmutable();
        $y = (int) $now->format('Y') - ((int) $now->format('n') >= 8 ? 0 : 1);
        $out = [];
        for ($i = 0; $i < self::YEARS_AHEAD; $i++) {
            $out[] = sprintf('Abitur %d/%02d', $y + $i, ($y + $i + 1) % 100);
        }

        return $out;
    }

    /**
     * Der aktuelle, die folgenden und alle späteren mit PDFs oder Einstellungen (aufsteigend),
     * danach ältere Jahrgänge (neueste zuerst).
     *
     * @return list<string>
     */
    public static function all(Database $db): array
    {
        $upcoming = self::upcoming();
        $fromPdfs = array_column($db->fetchAll("SELECT DISTINCT jahrgang FROM school_pdfs WHERE jahrgang <> ''"), 'jahrgang');
        $fromSettings = array_map(
            static fn (array $r): string => substr($r['name'], (int) strpos($r['name'], '_jg:') + 4),
            $db->fetchAll("SELECT name FROM settings WHERE name LIKE '%\\_jg:%'"),
        );
        $all = array_values(array_unique(array_merge($upcoming, $fromPdfs, $fromSettings)));
        $current = $upcoming[0];
        $later = array_values(array_filter($all, static fn (string $j): bool => strnatcmp($j, $current) >= 0));
        $older = array_values(array_filter($all, static fn (string $j): bool => strnatcmp($j, $current) < 0));
        sort($later, SORT_NATURAL);
        rsort($older, SORT_NATURAL);

        return array_merge($later, $older);
    }
}
