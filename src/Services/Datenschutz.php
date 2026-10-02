<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Datenschutzhinweis: pflegt der Admin im Admin-Bereich. Der Standardtext ist nur ein Entwurf
 * und muss von der Schule (Datenschutzbeauftragte/r) geprüft und ergänzt werden.
 */
final class Datenschutz
{
    public const ENTWURF = <<<'TXT'
ENTWURF – bitte von der Schule prüfen und anpassen, bevor der Planer genutzt wird.

Verantwortlich
[Name der Schule, Anschrift, Kontakt der Schulleitung]
Datenschutzbeauftragte/r: [Name, Kontakt]

Zweck
Der Kurswahl-Planer unterstützt Schülerinnen und Schüler bei der Kurswahl für die Qualifikationsphase und erzeugt das Kurswahlformular der Schule.

Welche Daten verarbeitet werden
- Konto: Login (vorname.nachname) und Passwort (nur als Hash gespeichert)
- Kurswahl-PDF der Schule: Name, Klasse, Jahrgang, Schüler-ID
- die geplante Kurswahl und der Zeitpunkt der Abgabe
- technische Daten: Zeitpunkt der letzten Anmeldung; IP-Adressen bei fehlgeschlagenen Anmeldungen (Schutz vor Missbrauch, nach etwa einem Tag gelöscht) und bei Aktionen der Administration (Protokoll)

Wer Zugriff hat
Nur die Administration der Schule. Die Daten werden nicht an Dritte weitergegeben. Die Anwendung lädt keine Inhalte von fremden Servern außer dem Schullogo.

Speicherdauer
Die Daten eines Jahrgangs werden nach Abschluss der Kurswahl, spätestens nach dem Abitur, gelöscht.

Lokale Speicherung im Browser
Wer seine eigene Kurswahl-PDF hochlädt, speichert sie und die Wahl nur im eigenen Browser. Diese Daten lassen sich mit „Eigene PDF entfernen“ löschen.

Ihre Rechte
Auskunft, Berichtigung, Löschung und Einschränkung der Verarbeitung sowie Beschwerde bei der Berliner Beauftragten für Datenschutz und Informationsfreiheit. Wenden Sie sich dazu an [Kontakt].
TXT;

    public static function text(Database $db): string
    {
        $v = $db->fetchValue("SELECT value FROM settings WHERE name = 'datenschutz'");

        return $v === null ? self::ENTWURF : (string) $v;
    }

    public static function save(Database $db, string $text): void
    {
        $text = trim(str_replace("\r\n", "\n", $text));
        $db->run(
            "INSERT INTO settings (name, value) VALUES ('datenschutz', ?) ON DUPLICATE KEY UPDATE value = VALUES(value)",
            [mb_substr($text, 0, 20000)],
        );
    }

    /** Text als HTML: Leerzeilen trennen Absätze, Zeilen mit „- “ werden zu Listen */
    public static function html(string $text): string
    {
        $out = '';
        foreach (preg_split('/\n\s*\n/', trim($text)) ?: [] as $block) {
            $lines = explode("\n", $block);
            $items = array_filter($lines, static fn (string $l): bool => str_starts_with(ltrim($l), '- '));
            if ($items !== [] && count($items) === count($lines)) {
                $out .= '<ul>' . implode('', array_map(static fn (string $l): string => '<li>' . e(substr(ltrim($l), 2)) . '</li>', $lines)) . '</ul>';
                continue;
            }
            if (count($lines) > 1 && mb_strlen($lines[0]) < 60 && !str_ends_with($lines[0], '.')) {
                $out .= '<h3>' . e(array_shift($lines)) . '</h3>';
            }
            $rest = array_values(array_filter($lines, static fn (string $l): bool => !str_starts_with(ltrim($l), '- ')));
            $list = array_values(array_filter($lines, static fn (string $l): bool => str_starts_with(ltrim($l), '- ')));
            if ($rest !== []) {
                $out .= '<p>' . implode('<br>', array_map('e', $rest)) . '</p>';
            }
            if ($list !== []) {
                $out .= '<ul>' . implode('', array_map(static fn (string $l): string => '<li>' . e(substr(ltrim($l), 2)) . '</li>', $list)) . '</ul>';
            }
        }

        return $out;
    }
}
