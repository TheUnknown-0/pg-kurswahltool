# Changelog

Neue Einträge oben. Format: `## Version – Datum`, darunter Stichpunkte mit `- `.
Bei jeder Änderung auf `main` die Nummer in `VERSION` erhöhen (Fehlerbehebung 1.0.1, neue Funktion 1.1.0),
sonst merken die laufenden Installationen nichts vom Update.

## 1.0.0 – 2026-11-06

- Versionsnummer auf allen Seiten; Admins sehen per Klick auf die Nummer diesen Changelog.
- Update-Prüfung bei jeder Admin-Anmeldung und einmal täglich: Liegt auf GitHub eine neuere Version vor, erscheint ein Hinweis im Admin-Bereich.
- Archive mit 7-Zip: Export aller Formulare als .7z, Import von .zip und .7z.
- Formular-Export ohne Serverfehler; Formular (PDF) und Wahl (CSV) je Schüler direkt aus der Übersicht.
- Kursangebot je Jahrgang (Fächer, Halbjahre, Zusatz- und Sportkurse), Statistik je Jahrgang, Jahrgang löschen.
- Eigenes Passwort ändern für Schüler und Admin; Protokoll aller Admin-Aktionen.
- Pflichtkurse je Abiturjahrgang mit Vorlage und Ankreuz-Raster, bis zu drei Jahrgänge im Voraus; regulärer und bilingualer Kurs schließen sich aus.
- Abgabe der Wahl, Abgabefrist mit wählbarem Verhalten, Admin-Ansicht der Wahl, Export aller Formulare und Wahlen (CSV).
- Formularfelder werden unabhängig von den Kurs-Nummern des Jahrgangs zugeordnet.
- Schüler von Hand anlegen.
- Server mit Konten und Schul-PDFs (Docker): Import per Upload oder Import-Ordner, Zuordnung über `vorname.nachname`, Speichern der Wahl auf dem Server, Notfall-Modus mit eigener PDF.
- Kurswahlformular byte-genau wie vom Schulserver.
