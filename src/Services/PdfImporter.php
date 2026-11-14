<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Liest Kurswahl-PDFs (einzeln oder in ZIP-/7z-Archiven, entpackt mit 7-Zip) in die Datenbank ein und ordnet sie
 * über den Login vorname.nachname den Schülerkonten zu.
 *
 * Gibt es für einen Schüler mehrere PDFs, gilt die mit dem jüngsten Erstelldatum.
 */
final class PdfImporter
{
    private const MAX_PDF_BYTES = 10 * 1024 * 1024;

    /** Archive, die 7-Zip für den Import entpackt. */
    public const ARCHIVE_EXTENSIONS = ['zip', '7z'];

    public function __construct(private readonly Database $db)
    {
    }

    /**
     * Importiert eine Datei (.pdf, .zip oder .7z) und protokolliert jedes enthaltene PDF.
     *
     * @return array{ok: int, failed: int}
     */
    public function importFile(string $path, string $filename, string $source): array
    {
        $result = ['ok' => 0, 'failed' => 0];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (in_array($ext, self::ARCHIVE_EXTENSIONS, true)) {
            return $this->importArchive($path, $filename, $source);
        }

        if ($ext !== 'pdf') {
            $this->log($source, $filename, 'fehler', 'Nur .pdf-, .zip- und .7z-Dateien werden eingelesen.');
            $result['failed']++;

            return $result;
        }
        if (filesize($path) > self::MAX_PDF_BYTES) {
            $this->log($source, $filename, 'fehler', 'Datei ist größer als 10 MB.');
            $result['failed']++;

            return $result;
        }
        $this->importPdf((string) file_get_contents($path), $filename, $source) ? $result['ok']++ : $result['failed']++;

        return $result;
    }

    /**
     * Entpackt ein Archiv mit 7-Zip und importiert jede enthaltene PDF.
     *
     * @return array{ok: int, failed: int}
     */
    private function importArchive(string $path, string $filename, string $source): array
    {
        $result = ['ok' => 0, 'failed' => 0];
        try {
            $entries = SevenZip::entries($path);
        } catch (\Throwable $e) {
            $this->log($source, $filename, 'fehler', 'Archiv lässt sich nicht öffnen: ' . $e->getMessage());
            $result['failed']++;

            return $result;
        }
        $wanted = [];
        foreach ($entries as $entry) {
            $name = str_replace('\\', '/', $entry['name']);
            if (str_starts_with(basename($name), '.') || !str_ends_with(strtolower($name), '.pdf')) {
                continue;
            }
            if ($entry['size'] > self::MAX_PDF_BYTES) {
                $this->log($source, $filename . ' → ' . $name, 'fehler', 'Datei ist größer als 10 MB.');
                $result['failed']++;
                continue;
            }
            if ($entry['encrypted']) {
                $this->log($source, $filename . ' → ' . $name, 'fehler', 'Datei ist verschlüsselt (Archiv mit Kennwort).');
                $result['failed']++;
                continue;
            }
            $wanted[] = $entry['name'];
        }
        try {
            $contents = SevenZip::extract($path, $wanted);
        } catch (\Throwable $e) {
            $this->log($source, $filename, 'fehler', 'Archiv lässt sich nicht entpacken: ' . $e->getMessage());
            $result['failed'] += count($wanted);

            return $result;
        }
        foreach ($contents as $name => $data) {
            $label = $filename . ' → ' . $name;
            if ($data === null) {
                $this->log($source, $label, 'fehler', 'Datei ließ sich nicht entpacken (verschlüsselt oder beschädigt?).');
                $result['failed']++;
                continue;
            }
            $this->importPdf($data, $label, $source, basename(str_replace('\\', '/', $name))) ? $result['ok']++ : $result['failed']++;
        }

        return $result;
    }

    /** Importiert ein einzelnes PDF. Gibt false bei Fehlern zurück (protokolliert). */
    public function importPdf(string $data, string $filename, string $source, ?string $storeName = null): bool
    {
        $storeName ??= basename($filename);
        try {
            $info = SchoolPdf::parse($data);
        } catch (\Throwable $e) {
            $this->log($source, $filename, 'fehler', $e->getMessage());

            return false;
        }

        $hash = hash('sha256', $data);
        $existing = $this->db->fetchValue('SELECT id FROM school_pdfs WHERE sha256 = ?', [$hash]);
        if ($existing !== null) {
            $this->log($source, $filename, 'doppelt', "Bereits vorhanden ({$info['name']}).", (int) $existing);

            return true;
        }

        // Neuer Jahrgang: Pflichtkurse aus der aktuellen Vorlage übernehmen (danach unabhängig von ihr)
        (new Pflicht($this->db))->forJahrgang($info['jahrgang']);
        (new Kursangebot($this->db))->forJahrgang($info['jahrgang']);

        $loginKey = LoginName::fromName($info['name']);
        // Gleicher Name, andere Schüler-ID: vermutlich zwei Personen. Die jüngere PDF würde sonst unbemerkt die andere ersetzen.
        $otherId = $info['schueler_id'] === '' ? null : $this->db->fetchValue(
            "SELECT schueler_id FROM school_pdfs WHERE login_key = ? AND schueler_id <> '' AND schueler_id <> ? LIMIT 1",
            [$loginKey, $info['schueler_id']],
        );
        $userId = $this->db->fetchValue('SELECT id FROM users WHERE login = ? AND role = ?', [$loginKey, 'student']);

        return $this->db->transaction(function (Database $db) use ($info, $data, $hash, $filename, $source, $loginKey, $userId, $storeName, $otherId): bool {
            $db->run(
                'INSERT INTO school_pdfs (user_id, login_key, name, klasse, jahrgang, schueler_id, abitur_jahrgang_id,
                     pdf_created_at, filename, sha256, size_bytes, pdf, active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)',
                [$userId, $loginKey, $info['name'], $info['klasse'], $info['jahrgang'], $info['schueler_id'],
                    $info['abitur_jahrgang_id'], $info['created_at'], mb_substr($storeName, 0, 255), $hash, strlen($data), $data],
            );
            $id = $db->lastInsertId();
            $replaced = $this->activateNewest($userId !== null ? (int) $userId : null, $loginKey);
            $active = (int) $db->fetchValue('SELECT active FROM school_pdfs WHERE id = ?', [$id]) === 1;

            $who = $info['name'] . ($userId !== null ? " → {$loginKey}" : " (kein Konto {$loginKey})");
            if ($otherId !== null) {
                $who .= ". Achtung: Für {$loginKey} liegt schon eine PDF mit anderer Schüler-ID vor ({$otherId} statt {$info['schueler_id']}), vermutlich zwei Personen mit gleichem Namen. Bitte die Zuordnung prüfen";
            }
            if (!$active) {
                $this->log($source, $filename, 'importiert', "{$who}; älter als die vorhandene PDF, nicht aktiv.", $id);
            } else {
                $this->log($source, $filename, $replaced ? 'aktualisiert' : 'importiert', $who, $id);
            }

            return true;
        });
    }

    /**
     * Ordnet nicht zugeordnete PDFs den Konten mit passendem Login zu (z. B. nach einem Benutzer-Import).
     */
    public function rematch(): int
    {
        $rows = $this->db->fetchAll(
            "SELECT p.id, u.id AS user_id, p.login_key FROM school_pdfs p
             JOIN users u ON u.login = p.login_key AND u.role = 'student'
             WHERE p.user_id IS NULL",
        );
        foreach ($rows as $r) {
            $this->db->run('UPDATE school_pdfs SET user_id = ? WHERE id = ?', [$r['user_id'], $r['id']]);
        }
        foreach (array_unique(array_column($rows, 'user_id')) as $uid) {
            $this->activateNewest((int) $uid, null);
        }

        return count($rows);
    }

    /** Ordnet eine PDF von Hand einem Konto zu (oder löst die Zuordnung mit null). */
    public function assign(int $pdfId, ?int $userId): void
    {
        $this->db->transaction(function (Database $db) use ($pdfId, $userId): void {
            $old = $db->fetchOne('SELECT user_id, login_key FROM school_pdfs WHERE id = ?', [$pdfId]);
            if ($old === null) {
                return;
            }
            $db->run('UPDATE school_pdfs SET user_id = ? WHERE id = ?', [$userId, $pdfId]);
            if ($old['user_id'] !== null) {
                $this->activateNewest((int) $old['user_id'], null);
            }
            $this->activateNewest($userId, $old['login_key']);
        });
    }

    /**
     * Markiert je Konto (bzw. je Login-Schlüssel bei nicht zugeordneten PDFs) die jüngste PDF als aktiv.
     * Gibt true zurück, wenn vorher schon eine andere PDF aktiv war.
     */
    private function activateNewest(?int $userId, ?string $loginKey): bool
    {
        [$where, $param] = $userId !== null ? ['user_id = ?', $userId] : ['user_id IS NULL AND login_key = ?', $loginKey];
        $hadActive = (int) $this->db->fetchValue("SELECT COUNT(*) FROM school_pdfs WHERE {$where} AND active = 1", [$param]) > 0;
        $newest = $this->db->fetchValue(
            "SELECT id FROM school_pdfs WHERE {$where} ORDER BY pdf_created_at IS NULL, pdf_created_at DESC, id DESC LIMIT 1",
            [$param],
        );
        $this->db->run("UPDATE school_pdfs SET active = (id = ?) WHERE {$where}", [$newest, $param]);

        return $hadActive;
    }

    private function log(string $source, string $filename, string $status, string $message, ?int $pdfId = null): void
    {
        $this->db->run(
            'INSERT INTO import_log (source, filename, status, message, pdf_id) VALUES (?, ?, ?, ?, ?)',
            [$source, mb_substr($filename, 0, 255), $status, mb_substr($message, 0, 500), $pdfId],
        );
    }
}
