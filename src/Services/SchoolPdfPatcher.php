<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Setzt in einer Kurswahl-PDF des Schulservers genau die gewünschten Kreuze und die Form der
 * 5. Prüfungskomponente. Alle anderen Objekte bleiben byte-gleich; nur die xref-Tabelle wird neu
 * berechnet. Gegenstück zu patchSchoolPdf() in public/assets/planer.js – beide müssen identische
 * Bytes liefern.
 */
final class SchoolPdfPatcher
{
    /**
     * @param list<string> $checks Feldnamen der anzukreuzenden Kästchen
     * @param string $pk 'Praesentation_0' oder 'BLL_0'
     * @param list<string>|null $keys statt $checks: Feldschlüssel „Fach|Kursart|Rang|Spalte“ (unabhängig von
     *                                den Kurs-Nummern des Jahrgangs, siehe fieldKeys() in planer.js)
     * @throws \RuntimeException wenn die PDF nicht den erwarteten Aufbau hat oder Felder fehlen
     */
    public static function patch(string $pdf, array $checks, string $pk, ?array $keys = null): string
    {
        if (!str_starts_with($pdf, '%PDF-') || !preg_match('/\r\n\d+ 0 obj\r\n/', $pdf, $m, PREG_OFFSET_CAPTURE)) {
            throw new \RuntimeException('Keine Kurswahl-PDF des Schulservers.');
        }
        $first = $m[0][1] + 2;
        $xrefPos = strrpos($pdf, "\r\nxref\r\n");
        if ($xrefPos === false) {
            throw new \RuntimeException('Unbekannter Aufbau der PDF.');
        }

        // Objekte in Dateireihenfolge
        $objs = [];
        $pos = $first;
        while ($pos < $xrefPos + 2) {
            if (!preg_match('/\G(\d+) 0 obj\r\n/', $pdf, $om, 0, $pos)) {
                throw new \RuntimeException('Unbekannter Aufbau der PDF.');
            }
            $end = strpos($pdf, 'endobj', $pos);
            $streamAt = strpos($pdf, ">>\r\nstream\r\n", $pos);
            if ($streamAt !== false && $streamAt < $end) {
                preg_match('/\/Length (\d+)/', substr($pdf, $pos, $streamAt - $pos), $lm);
                $end = strpos($pdf, 'endobj', $streamAt + 12 + (int) $lm[1]);
            }
            $objs[] = ['n' => (int) $om[1], 'start' => $pos, 'end' => $end + 8];
            $pos = $end + 8;
        }
        if (!preg_match('/\Gxref\r\n0 (\d+)\r\n/', $pdf, $xm, 0, $xrefPos + 2)) {
            throw new \RuntimeException('Unbekannter Aufbau der PDF.');
        }
        $size = (int) $xm[1];
        $entriesAt = $xrefPos + 2 + strlen($xm[0]);
        $entries = [];
        for ($i = 0; $i < $size; $i++) {
            $entries[] = substr($pdf, $entriesAt + 20 * $i, 18);
        }
        $trailer = substr($pdf, $entriesAt + 20 * $size, strrpos($pdf, 'startxref') - ($entriesAt + 20 * $size));

        $text = [];
        foreach ($objs as $o) {
            $text[$o['n']] = substr($pdf, $o['start'], $o['end'] - $o['start']);
        }

        // Kontrollkästchen: Objektnummer → Feldname
        $boxes = [];
        foreach ($objs as $o) {
            $t = $text[$o['n']];
            if (str_contains($t, '/FT /Btn') && !str_contains($t, '/Kids') && !str_contains($t, '/Parent ')
                && preg_match('/\/T <([0-9A-F]+)>/', $t, $bm)) {
                $boxes[$o['n']] = self::utf16FromHex($bm[1]);
            }
        }
        if ($keys !== null) {
            $byKey = array_flip(self::fieldKeys(array_values($boxes)));
            $checks = [];
            foreach ($keys as $k) {
                $checks[] = $byKey[$k] ?? $k;
            }
        }

        $want = array_flip($checks);
        $changed = [];
        $found = [];
        $radioParent = null;
        foreach ($objs as $o) {
            $t = $text[$o['n']];
            if (str_contains($t, '/FT /Btn') && str_contains($t, '/Kids')) {
                $radioParent = $o['n'];
                $changed[$o['n']] = (string) preg_replace('/\/V \/\S+/', '/V /' . self::pdfName($pk), $t, 1);
                continue;
            }
            if (!isset($boxes[$o['n']])) {
                continue;
            }
            $name = $boxes[$o['n']];
            $on = isset($want[$name]);
            if ($on) {
                $found[$name] = true;
            }
            $v = $on ? '/Yes' : '/Off';
            $t = (string) preg_replace('/\/AS \/(Yes|Off)/', '/AS ' . $v, $t, 1);
            $changed[$o['n']] = (string) preg_replace('/\/V \/(Yes|Off)/', '/V ' . $v, $t, 1);
        }
        $missing = array_diff($checks, array_keys($found));
        if ($missing !== []) {
            throw new \RuntimeException('Diese Felder fehlen in der PDF: ' . implode(', ', $missing));
        }
        if ($radioParent !== null) {
            foreach ($objs as $o) {
                $t = $text[$o['n']];
                if (!str_contains($t, "/Parent {$radioParent} 0 R") || !preg_match('/\/AP (\d+) 0 R/', $t, $am)) {
                    continue;
                }
                $ap = explode('>>', explode('/N', $text[(int) $am[1]], 2)[1], 2)[0];
                preg_match_all('/\/(\S+) \d+ 0 R/', $ap, $states);
                $onName = '';
                foreach ($states[1] as $st) {
                    if ($st !== 'Off') {
                        $onName = $st;
                        break;
                    }
                }
                $plain = (string) preg_replace_callback('/#([0-9A-F]{2})/', static fn (array $x): string => chr((int) hexdec($x[1])), $onName);
                $changed[$o['n']] = (string) preg_replace('/\/AS \/\S+/', '/AS /' . ($plain === $pk ? $onName : 'Off'), $t, 1);
            }
        }

        $out = substr($pdf, 0, $first);
        $offsets = [];
        foreach ($objs as $o) {
            $offsets[$o['n']] = strlen($out);
            $out .= $changed[$o['n']] ?? $text[$o['n']];
        }
        $xref = "xref\r\n0 {$size}\r\n";
        foreach ($entries as $i => $e) {
            $xref .= (str_ends_with($e, ' f') ? $e : sprintf('%010d 00000 n', $offsets[$i])) . "\r\n";
        }

        return $out . $xref . $trailer . 'startxref' . "\r\n" . strlen($out) . "\r\n%%EOF\r\n";
    }

    /**
     * Feldname → „Fach|Kursart|Rang|Spalte“; Rang = Position der Kurs-Nummer innerhalb von Fach und Kursart.
     *
     * @param list<string> $names
     * @return array<string, string>
     */
    public static function fieldKeys(array $names): array
    {
        $groups = [];
        $parsed = [];
        foreach ($names as $n) {
            $p = explode('$', $n);
            if (count($p) !== 5) {
                continue;
            }
            $g = $p[0] . '|' . ($p[1] === '-10' ? 'z' : 'reg');
            $groups[$g][(int) $p[3]] = true;
            $parsed[$n] = [$g, (int) $p[3], $p[4]];
        }
        $rank = [];
        foreach ($groups as $g => $ids) {
            $ids = array_keys($ids);
            sort($ids);
            $rank[$g] = array_flip($ids);
        }
        $out = [];
        foreach ($parsed as $n => [$g, $id, $col]) {
            $out[$n] = "{$g}|{$rank[$g][$id]}|{$col}";
        }

        return $out;
    }

    private static function pdfName(string $s): string
    {
        return (string) preg_replace_callback('/[^A-Za-z0-9]/', static fn (array $c): string => sprintf('#%02X', ord($c[0])), $s);
    }

    private static function utf16FromHex(string $hex): string
    {
        if (!str_starts_with($hex, 'FEFF')) {
            return '';
        }

        return mb_convert_encoding((string) hex2bin(substr($hex, 4)), 'UTF-8', 'UTF-16BE');
    }
}
