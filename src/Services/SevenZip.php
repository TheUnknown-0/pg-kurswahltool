<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Packt und entpackt Archive mit dem 7-Zip-Programm (Paket „7zip“, Befehl 7z bzw. 7zz).
 *
 * Gelesen werden alle Formate, die 7-Zip kennt (u. a. .7z und .zip); geschrieben wird .7z.
 * Aufrufe laufen ohne Shell über proc_open, Dateinamen gehen über UTF-8-Listendateien und
 * ohne Platzhalter-Auswertung (-spd) an 7-Zip. Kein „--“: danach liest 7-Zip keine @Listendatei mehr.
 */
final class SevenZip
{
    private static ?string $bin = null;

    /** Pfad des 7-Zip-Programms; Umgebungsvariable SEVENZIP_BIN hat Vorrang. */
    public static function binary(): string
    {
        if (self::$bin !== null) {
            return self::$bin;
        }
        $candidates = array_filter([getenv('SEVENZIP_BIN') ?: null, '7z', '7zz', '7za']);
        foreach ($candidates as $c) {
            if (str_contains($c, '/')) {
                if (is_executable($c)) {
                    return self::$bin = $c;
                }
                continue;
            }
            foreach (explode(PATH_SEPARATOR, (string) (getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin')) as $dir) {
                if ($dir !== '' && is_executable("{$dir}/{$c}")) {
                    return self::$bin = "{$dir}/{$c}";
                }
            }
        }
        throw new \RuntimeException('7-Zip ist nicht installiert (Befehl 7z oder 7zz nicht gefunden).');
    }

    /**
     * Schreibt ein .7z-Archiv aus Name → Inhalt.
     *
     * @param array<string, string> $files
     */
    public static function create(string $archive, array $files): void
    {
        $dir = self::tempDir();
        try {
            $names = [];
            foreach ($files as $name => $bytes) {
                $name = (string) $name;
                if ($name === '' || str_contains($name, '/') || str_contains($name, '\\') || $name === '.' || $name === '..') {
                    throw new \InvalidArgumentException('Ungültiger Dateiname im Archiv: ' . $name);
                }
                file_put_contents("{$dir}/{$name}", $bytes);
                $names[] = $name;
            }
            if ($names === []) {
                throw new \InvalidArgumentException('Ein Archiv ohne Dateien wird nicht angelegt.');
            }
            @unlink($archive);
            $list = self::listFile($dir, $names);
            self::run(['a', '-t7z', '-mx=5', '-spd', '-scsUTF-8', '-y', self::arg($archive), '@' . $list], $dir);
        } finally {
            self::removeDir($dir);
        }
    }

    /**
     * Listet die Dateien eines Archivs (ohne Ordner).
     *
     * @return list<array{name: string, size: int, encrypted: bool}>
     */
    public static function entries(string $archive): array
    {
        $out = self::run(['l', '-slt', '-ba', '-sccUTF-8', self::arg($archive)]);
        $entries = [];
        foreach (preg_split('/\R\R+/', trim($out)) ?: [] as $block) {
            $f = [];
            foreach (preg_split('/\R/', $block) ?: [] as $line) {
                if (preg_match('/^([^=]+?) = (.*)$/', $line, $m)) {
                    $f[$m[1]] = $m[2];
                }
            }
            if (!isset($f['Path']) || ($f['Folder'] ?? '-') === '+' || str_contains($f['Attributes'] ?? '', 'D')) {
                continue;
            }
            $entries[] = ['name' => $f['Path'], 'size' => (int) ($f['Size'] ?? 0), 'encrypted' => ($f['Encrypted'] ?? '-') === '+'];
        }

        return $entries;
    }

    /**
     * Entpackt die genannten Einträge in einen neuen temporären Ordner und gibt Eintrag → Inhalt
     * zurück. Fehlt ein Eintrag danach (Symlink, verschlüsselt, beschädigt, Pfad mit „..“), ist sein Wert null.
     *
     * @param list<string> $names
     * @return array<string, ?string>
     */
    public static function extract(string $archive, array $names): array
    {
        if ($names === []) {
            return [];
        }
        $dir = self::tempDir();
        try {
            $out = "{$dir}/out";
            mkdir($out, 0700);
            $list = self::listFile($dir, $names);
            // -p-: festes Kennwort statt Rückfrage; verschlüsselte Einträge schlagen damit fehl
            // Code 2 bei einzelnen Einträgen (verschlüsselt, Link, beschädigt) bricht nicht alles ab;
            // was danach fehlt, meldet der Aufrufer je Datei
            $err = '';
            self::run(['x', '-y', '-spd', '-scsUTF-8', '-p-', '-o' . $out, self::arg($archive), '@' . $list], null, [0, 1, 2], $err);
            // „ERROR: Wrong password : name“, „ERROR: CRC Failed : name“ …: Datei liegt evtl. halb da
            $failed = [];
            foreach (preg_split('/\R/', $err) ?: [] as $line) {
                foreach ($names as $name) {
                    if (str_starts_with($line, 'ERROR:') && str_ends_with($line, ' : ' . $name)) {
                        $failed[$name] = true;
                    }
                }
            }
            $result = [];
            $root = realpath($out);
            foreach ($names as $name) {
                $path = realpath($out . '/' . $name);
                $ok = !isset($failed[$name]) && $path !== false && $root !== false && str_starts_with($path, $root . '/')
                    && is_file($path) && !is_link($out . '/' . $name);
                $result[$name] = $ok ? (string) file_get_contents($path) : null;
            }

            return $result;
        } finally {
            self::removeDir($dir);
        }
    }

    /** Archivpfad so, dass 7-Zip ihn nie als Schalter (-…) oder Listendatei (@…) liest. */
    private static function arg(string $path): string
    {
        return str_starts_with($path, '/') ? $path : './' . $path;
    }

    /** @param list<string> $names */
    private static function listFile(string $dir, array $names): string
    {
        $list = "{$dir}/.liste.txt";
        file_put_contents($list, implode("\n", $names) . "\n");

        return $list;
    }

    /**
     * Führt 7-Zip aus und gibt die Standardausgabe zurück.
     *
     * @param list<string> $args
     * @param list<int> $okCodes
     */
    private static function run(array $args, ?string $cwd = null, array $okCodes = [0], string &$stderr = ''): string
    {
        // -bd: keine Fortschrittsanzeige
        $cmd = array_merge([self::binary(), array_shift($args), '-bd'], $args);
        $proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $cwd, ['LC_ALL' => 'C.UTF-8', 'PATH' => getenv('PATH') ?: '/usr/bin:/bin']);
        if (!is_resource($proc)) {
            throw new \RuntimeException('7-Zip konnte nicht gestartet werden.');
        }
        fclose($pipes[0]); // keine Rückfragen (Kennwort, Überschreiben)
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $out = $err = '';
        while (true) {
            $r = [$pipes[1], $pipes[2]];
            $w = $e = null;
            if (stream_select($r, $w, $e, 60) === false) {
                break;
            }
            foreach ($r as $p) {
                $chunk = (string) fread($p, 65536);
                $p === $pipes[1] ? $out .= $chunk : $err .= $chunk;
            }
            if (feof($pipes[1]) && feof($pipes[2])) {
                break;
            }
        }
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($proc);
        $stderr = $err;
        // 0 = OK, 1 = Warnung, 2 = Fehler, 7/8 = Aufruf/Speicher
        if (!in_array($code, $okCodes, true)) {
            $msg = trim(preg_replace('/\s+/', ' ', $err !== '' ? $err : $out) ?? '');
            throw new \RuntimeException('7-Zip-Fehler (Code ' . $code . ')' . ($msg !== '' ? ': ' . mb_substr($msg, 0, 300) : '.'));
        }

        return $out;
    }

    private static function tempDir(): string
    {
        $dir = sys_get_temp_dir() . '/kw7z-' . bin2hex(random_bytes(8));
        if (!mkdir($dir, 0700)) {
            throw new \RuntimeException('Temporärer Ordner nicht anlegbar: ' . $dir);
        }

        return $dir;
    }

    private static function removeDir(string $dir): void
    {
        if (!is_dir($dir) || is_link($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            /** @var \SplFileInfo $f */
            $f->isDir() && !$f->isLink() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
    }
}
