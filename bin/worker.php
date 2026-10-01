<?php

declare(strict_types=1);

/**
 * Hintergrund-Import: liest Kurswahl-PDFs (oder ZIPs) aus der Upload-Warteschlange und dem
 * Import-Ordner ein. Läuft als eigener Container (siehe compose.yaml).
 *
 * Verarbeitete Dateien aus dem Import-Ordner wandern nach import/verarbeitet/, fehlerhafte
 * nach import/fehler/. Dateien aus der Warteschlange werden nach dem Einlesen gelöscht.
 *
 * Aufruf: php bin/worker.php [--once]
 */

use App\Services\PdfImporter;

[$config, $db] = require dirname(__DIR__) . '/src/bootstrap.php';

$once = in_array('--once', $argv, true);
$interval = max(2, (int) $config['import']['interval_seconds']);
$queueDir = $config['import']['queue_dir'];
$folder = $config['import']['folder'];
$stop = false;
if (function_exists('pcntl_signal')) {
    pcntl_async_signals(true);
    pcntl_signal(SIGTERM, static function () use (&$stop): void { $stop = true; });
    pcntl_signal(SIGINT, static function () use (&$stop): void { $stop = true; });
}

$ensureDirs = static function () use ($queueDir, $folder): void {
    foreach ([$queueDir, $folder, $folder . '/verarbeitet', $folder . '/fehler'] as $d) {
        if (!is_dir($d)) {
            @mkdir($d, 0775, true);
        }
    }
};

$say = static fn (string $m) => fwrite(STDOUT, '[' . date('Y-m-d H:i:s') . "] {$m}\n");
$say("Import-Worker gestartet (Warteschlange {$queueDir}, Ordner {$folder}, alle {$interval}s).");

/** Datei gilt als fertig geschrieben, wenn sie seit mindestens 5 s nicht mehr verändert wurde. */
$stable = static function (string $f): bool {
    clearstatcache(true, $f);

    return time() - (int) filemtime($f) >= 5;
};

do {
    try {
        $db->run(
            "INSERT INTO settings (name, value) VALUES ('worker_heartbeat', ?) ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = CURRENT_TIMESTAMP",
            [date('c')],
        );
        $ensureDirs();
        $importer = new PdfImporter($db);

        // 1. Uploads aus dem Admin-Bereich
        foreach (glob($queueDir . '/*') ?: [] as $f) {
            if ($stop || !is_file($f) || str_ends_with($f, '.part')) {
                continue;
            }
            $original = preg_replace('/^\d{8}-\d{6}-[0-9a-f]{8}--/', '', basename($f));
            $r = $importer->importFile($f, $original, 'upload');
            $say("Upload {$original}: {$r['ok']} eingelesen, {$r['failed']} Fehler");
            @unlink($f);
        }

        // 2. Import-Ordner
        foreach (glob($folder . '/*') ?: [] as $f) {
            $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
            if ($stop || !is_file($f) || !in_array($ext, ['pdf', 'zip'], true) || str_starts_with(basename($f), '.') || !$stable($f)) {
                continue;
            }
            $name = basename($f);
            $r = $importer->importFile($f, $name, 'ordner');
            $say("Ordner {$name}: {$r['ok']} eingelesen, {$r['failed']} Fehler");
            $dest = $folder . '/' . ($r['failed'] > 0 && $r['ok'] === 0 ? 'fehler' : 'verarbeitet') . '/' . date('Ymd-His') . '-' . $name;
            if (!@rename($f, $dest)) {
                $say("Konnte {$name} nicht verschieben – Datei wird gelöscht, damit sie nicht erneut eingelesen wird.");
                @unlink($f);
            }
        }
    } catch (Throwable $e) {
        // Datenbank kurz weg o. ä.: nicht abstürzen, beim nächsten Durchlauf erneut versuchen
        $say('Fehler: ' . $e->getMessage());
    }
    if ($once) {
        break;
    }
    for ($i = 0; $i < $interval && !$stop; $i++) {
        sleep(1);
    }
} while (!$stop);

$say('Import-Worker beendet.');
