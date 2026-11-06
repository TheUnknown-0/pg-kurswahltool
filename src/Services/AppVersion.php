<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Versionsnummer (Datei VERSION) und Changelog (CHANGELOG.md) der installierten Anwendung.
 */
final class AppVersion
{
    private static ?string $current = null;

    public static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public static function current(): string
    {
        if (self::$current === null) {
            $v = trim((string) @file_get_contents(self::root() . '/VERSION'));
            self::$current = self::valid($v) ? $v : '0.0.0';
        }

        return self::$current;
    }

    public static function valid(string $v): bool
    {
        return preg_match('/^\d+\.\d+\.\d+$/', $v) === 1;
    }

    /** @return list<array{version: string, date: string, items: list<string>}> */
    public static function changelog(): array
    {
        return self::parse((string) @file_get_contents(self::root() . '/CHANGELOG.md'));
    }

    /**
     * Liest Einträge der Form „## 1.2.0 – 2026-11-06“ mit Stichpunkten „- …“ darunter.
     *
     * @return list<array{version: string, date: string, items: list<string>}>
     */
    public static function parse(string $md): array
    {
        $out = [];
        $cur = null;
        foreach (preg_split('/\R/', $md) ?: [] as $line) {
            if (preg_match('/^##\s+v?(\d+\.\d+\.\d+)\s*(?:[–—-]\s*(.*))?$/u', $line, $m)) {
                if ($cur !== null) {
                    $out[] = $cur;
                }
                $cur = ['version' => $m[1], 'date' => trim($m[2] ?? ''), 'items' => []];
            } elseif ($cur !== null && preg_match('/^\s*[-*]\s+(.+)$/u', $line, $m)) {
                $cur['items'][] = trim($m[1]);
            } elseif ($cur !== null && $cur['items'] !== [] && preg_match('/^\s{2,}(\S.*)$/u', $line, $m)) {
                // Fortsetzungszeile eines Stichpunkts
                $cur['items'][count($cur['items']) - 1] .= ' ' . trim($m[1]);
            }
        }
        if ($cur !== null) {
            $out[] = $cur;
        }

        return $out;
    }

    /** Stichpunkt als HTML: escaped, `Code` wird zu <code>. */
    public static function itemHtml(string $item): string
    {
        return (string) preg_replace('/`([^`]+)`/', '<code>$1</code>', e($item));
    }
}
