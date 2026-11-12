# Changelog

Neue Einträge oben. Format: `## Version – Datum`, darunter Stichpunkte mit `- `.
Bei jeder Änderung auf `main` die Nummer in `VERSION` erhöhen (Fehlerbehebung 1.0.1, neue Funktion 1.1.0),
sonst merken die laufenden Installationen nichts vom Update.

## 1.1.0 – 2026-11-12

- Neue Unterseite „Hilfe zur Kurswahl“ (`/hilfe`, ohne Anmeldung): Leitfaden mit Erklärvideo, Untertiteln und Kapiteln, Fächertabelle, Belegpflichten, Rechner, Beispielwahl, Stolperfallen und Checkliste. Verlinkt im Planer und auf der Anmeldeseite.
- Zusätzliche Geschichte richtet sich nach dem Abiturjahrgang (§ 25 Abs. 3 und § 49 Abs. 11 VO-GO): bis Abitur 2027/28 in Q3 und Q4, ab Abitur 2028/29 (Q1 ab 2027/28) in Q1 und Q2. Maßgeblich ist der Jahrgang aus dem Formular, ohne Angabe der Jahrgang des Planers. Korrigiert 1.0.1, das die neue Regel schon für alle Jahrgänge angewandt hat.
- Hinweis, wenn der einzige Sportkurs außerhalb der Ballspiele in Q4 liegt: Fällt er aus, bleiben nur Ballspiele.

## 1.0.1 – 2026-11-12

- Neu ab Schuljahr 2026/27: Ist ein anderes Fach des Aufgabenfelds II durchgängig, muss Geschichte zusätzlich in Q1 und Q2 belegt werden (bisher Q3 und Q4). Regelprüfung, Einbringpflicht und Assistent sind angepasst.
- Sportbefreiung: Die Mindestzahl bleibt bei 40 Kursen (bisher 39); der Hinweis sagt, dass der Sport durch Grundkurse ersetzt wird.
- Assistent Sport: Derselbe Kurs ist bis zu zweimal wählbar (höchstens drei Ballspiele), wie in der Regelprüfung.

## 1.0.0 – 2026-11-06

- Versionsnummer auf allen Seiten; Admins sehen per Klick auf die Nummer diesen Changelog.
- Update-Prüfung bei der Admin-Anmeldung, einmal täglich und per Knopf „Nach neuen Versionen suchen“ (höchstens einmal pro Minute): Liegt auf GitHub eine neuere Version vor, erscheint ein Hinweis im Admin-Bereich. Schonend für die Limits von GitHub (ETag, Pause nach Rate Limit).
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
