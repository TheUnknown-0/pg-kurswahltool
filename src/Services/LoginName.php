<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Leitet aus „Vorname Nachname“ den Login vorname.nachname ab.
 *
 * Regel: Kleinbuchstaben, ä→ae, ö→oe, ü→ue, ß→ss, andere Akzente entfallen (é→e).
 * Das letzte Wort ist der Nachname, alle Wörter davor bilden den Vornamen und werden
 * mit Bindestrich verbunden (Anna Maria Schmidt → anna-maria.schmidt). Bindestriche
 * im Namen bleiben erhalten, alle anderen Zeichen entfallen.
 *
 * Passt die Regel bei einzelnen Schülern nicht (z. B. „von“ im Nachnamen), ordnet der
 * Admin die PDF im Admin-Bereich von Hand zu.
 */
final class LoginName
{
    public static function fromName(string $name): string
    {
        $words = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $words = array_values(array_filter(array_map(self::normalizeWord(...), $words), static fn (string $w): bool => $w !== ''));
        if ($words === []) {
            return '';
        }
        if (count($words) === 1) {
            return $words[0];
        }
        $last = array_pop($words);

        return implode('-', $words) . '.' . $last;
    }

    public static function normalize(string $login): string
    {
        return mb_strtolower(trim($login), 'UTF-8');
    }

    private static function normalizeWord(string $w): string
    {
        $w = mb_strtolower($w, 'UTF-8');
        $w = strtr($w, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);
        if (class_exists(\Normalizer::class)) {
            $w = (string) \Normalizer::normalize($w, \Normalizer::FORM_D);
        }
        $w = (string) preg_replace('/\p{Mn}+/u', '', $w);
        $w = (string) preg_replace('/[^a-z0-9-]+/', '', $w);

        return trim($w, '-');
    }
}
