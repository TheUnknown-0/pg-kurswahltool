<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Liest eine Kurswahl-PDF des Schulservers (DevExpress) aus.
 *
 * Die PDFs haben unkomprimierte Objekte und eine klassische xref-Tabelle; Texte
 * stehen als Glyphennummern im Seiteninhalt und werden über die ToUnicode-Tabellen
 * der beiden Arial-Schriften zurückübersetzt.
 */
final class SchoolPdf
{
    /** @var array<int, int> Objektnummer → Startposition */
    private array $objects = [];

    private function __construct(private readonly string $raw)
    {
        preg_match_all('/\r\n(\d+) 0 obj\r\n/', $raw, $m, PREG_OFFSET_CAPTURE);
        foreach ($m[1] as [$num, $pos]) {
            $this->objects[(int) $num] = $pos;
        }
    }

    /**
     * @return array{name: string, klasse: string, jahrgang: string, schueler_id: string,
     *               abitur_jahrgang_id: string, created_at: ?string, checks: list<string>, pk: string}
     * @throws \RuntimeException wenn die Datei kein Kurswahlformular der Schule ist
     */
    public static function parse(string $raw): array
    {
        if (!str_starts_with($raw, '%PDF-')) {
            throw new \RuntimeException('Keine PDF-Datei.');
        }
        $pdf = new self($raw);
        if (!str_contains($raw, '/Producer <FEFF' . self::utf16Hex('Developer Express'))) {
            throw new \RuntimeException('Die PDF stammt nicht vom Schulserver (DevExpress).');
        }

        $texts = $pdf->pageTexts();
        $after = static function (string $prefix) use ($texts): string {
            foreach ($texts as $t) {
                if (str_starts_with($t, $prefix) && !str_contains($t, "\u{FFFD}")) {
                    return substr($t, strlen($prefix));
                }
            }
            throw new \RuntimeException("Feld „" . rtrim($prefix, ': ') . "“ nicht gefunden – kein Kurswahlformular?");
        };

        return [
            'name' => trim($after('Name: ')),
            'klasse' => trim($after('Klasse: ')),
            'jahrgang' => trim((string) preg_replace('/,\s*GYM_SEK_II$/', '', $after('Jahrgang: '))),
            'schueler_id' => $pdf->textField('SchuelerId'),
            'abitur_jahrgang_id' => $pdf->textField('AbiturJahrgangId'),
            'created_at' => $pdf->creationDate(),
            'checks' => $pdf->checkedBoxes(),
            'pk' => $pdf->radioValue(),
        ];
    }

    public static function utf16Hex(string $s): string
    {
        return strtoupper(bin2hex(mb_convert_encoding($s, 'UTF-16BE', 'UTF-8')));
    }

    private static function fromUtf16Hex(string $hex): string
    {
        $bin = (string) hex2bin($hex);
        if (str_starts_with($bin, "\xFE\xFF")) {
            $bin = substr($bin, 2);
        }

        return mb_convert_encoding($bin, 'UTF-8', 'UTF-16BE');
    }

    private function obj(int $num): string
    {
        if (!isset($this->objects[$num])) {
            throw new \RuntimeException("Objekt {$num} fehlt.");
        }
        $start = $this->objects[$num];
        $streamAt = strpos($this->raw, ">>\r\nstream\r\n", $start);
        $end = strpos($this->raw, 'endobj', $start);
        if ($streamAt !== false && $streamAt < $end) {
            // Binärdaten überspringen, damit "endobj" darin nicht stört
            preg_match('/\/Length (\d+)/', substr($this->raw, $start, $streamAt - $start), $m);
            $end = strpos($this->raw, 'endobj', $streamAt + 12 + (int) ($m[1] ?? 0));
        }

        return substr($this->raw, $start, $end - $start);
    }

    private function stream(int $num): string
    {
        $o = $this->obj($num);
        $i = strpos($o, "stream\r\n");
        if ($i === false || !preg_match('/\/Length (\d+)/', substr($o, 0, $i), $m)) {
            throw new \RuntimeException("Objekt {$num} hat keinen Stream.");
        }
        $data = substr($o, $i + 8, (int) $m[1]);
        if (!str_contains(substr($o, 0, $i), '/FlateDecode')) {
            return $data;
        }
        $out = @gzinflate(substr($data, 2, -4));
        if ($out === false) {
            throw new \RuntimeException("Stream {$num} lässt sich nicht entpacken.");
        }

        return $out;
    }

    private function ref(string $text, string $key): ?int
    {
        return preg_match('/\/' . $key . ' (\d+) 0 R/', $text, $m) ? (int) $m[1] : null;
    }

    /** @return list<string> */
    private function pageTexts(): array
    {
        $trailer = substr($this->raw, (int) strrpos($this->raw, 'trailer'));
        $root = $this->obj((int) $this->ref($trailer, 'Root'));
        $pages = $this->obj((int) $this->ref($root, 'Pages'));
        if (!preg_match('/\/Kids \[(\d+) 0 R/', $pages, $m)) {
            throw new \RuntimeException('Seiten nicht gefunden.');
        }
        $content = $this->stream((int) $this->ref($this->obj((int) $m[1]), 'Contents'));

        $maps = [];
        preg_match_all('/\/BaseFont \/DEVEXP#2BArial\S* \/Encoding \/Identity#2DH \/ToUnicode (\d+) 0 R/', $this->raw, $fm);
        foreach ($fm[1] as $tu) {
            preg_match_all('/<([0-9A-F]{4})> <([0-9A-F]{4})>/', $this->stream((int) $tu), $pairs, PREG_SET_ORDER);
            $map = [];
            foreach ($pairs as [, $g, $u]) {
                $map[$g] = mb_chr((int) hexdec($u), 'UTF-8');
            }
            $maps[] = $map;
        }

        $texts = [];
        preg_match_all('/\[([^\]]*)\] TJ|<([0-9A-F]+)> Tj/', $content, $tm, PREG_SET_ORDER);
        foreach ($tm as $t) {
            if (($t[1] ?? '') !== '') {
                preg_match_all('/<([0-9A-F]+)>/', $t[1], $hx);
                $hex = implode('', $hx[1]);
            } else {
                $hex = $t[2];
            }
            // Beide Schriften haben dieselben Glyphennummern: jede Lesart aufnehmen
            foreach ($maps as $map) {
                $s = '';
                foreach (str_split($hex, 4) as $g) {
                    $s .= $map[$g] ?? "\u{FFFD}";
                }
                $texts[] = $s;
            }
        }

        return $texts;
    }

    private function textField(string $name): string
    {
        $re = '/\/T <FEFF' . self::utf16Hex($name) . '>(?:(?!endobj).)*?\/V <([0-9A-F]*)>/s';

        return preg_match($re, $this->raw, $m) ? self::fromUtf16Hex($m[1]) : '';
    }

    /** @return list<string> */
    private function checkedBoxes(): array
    {
        preg_match_all('/\/FT \/Btn \/T <([0-9A-F]+)> \/V \/Yes/', $this->raw, $m);

        return array_map(self::fromUtf16Hex(...), $m[1]);
    }

    private function radioValue(): string
    {
        if (!preg_match('/\/Ff 49152 \/V \/(\S+)/', $this->raw, $m)) {
            return '';
        }

        return (string) preg_replace_callback('/#([0-9A-F]{2})/', static fn (array $x): string => chr((int) hexdec($x[1])), $m[1]);
    }

    private function creationDate(): ?string
    {
        $trailer = substr($this->raw, (int) strrpos($this->raw, 'trailer'));
        $info = $this->ref($trailer, 'Info');
        if ($info === null || !preg_match('/\/CreationDate <([0-9A-F]+)>/', $this->obj($info), $m)) {
            return null;
        }
        $d = (string) hex2bin($m[1]);
        if (!preg_match("/D:(\d{4})(\d\d)(\d\d)(\d\d)(\d\d)(\d\d)([+-])(\d\d)'(\d\d)'/", $d, $p)) {
            return null;
        }
        $dt = new \DateTimeImmutable("{$p[1]}-{$p[2]}-{$p[3]}T{$p[4]}:{$p[5]}:{$p[6]}{$p[7]}{$p[8]}:{$p[9]}");

        return $dt->setTimezone(new \DateTimeZone(date_default_timezone_get()))->format('Y-m-d H:i:s');
    }
}
