# Changelog

Neue Einträge oben. Format: `## Version – Datum`, darunter Stichpunkte mit `- `.
Bei jeder Änderung auf `main` die Nummer in `VERSION` erhöhen (Fehlerbehebung 1.0.1, neue Funktion 1.1.0),
sonst merken die laufenden Installationen nichts vom Update.

## 1.2.0 – 2026-11-14

- Hilfeseite `/hilfe` ist standardmäßig nur nach Anmeldung erreichbar. Unter „Einstellungen“ kann der Admin sie für alle freigeben; dann steht der Link auch wieder auf der Anmeldeseite. Jede Umstellung steht im Protokoll.
- Künstlerisches Fach: Die Belegpflicht entfällt nur, wenn die zweite Fremdsprache erst in Klasse 10 begonnen wurde (§ 25 Abs. 2 VO-GO). Eine neu begonnene dritte Fremdsprache befreit nicht mehr.
- Sport als 4. Prüfungsfach: Neben einem Kurs Sporttheorie zählen drei Kurse Sportpraxis zu den einbringpflichtigen Kursen (§ 13 Abs. 5 VO-GO).
- Jahreswochenstunden: Grundkurse einer in Klasse 10 neu begonnenen Fremdsprache zählen mit vier Stunden (§ 20 Abs. 1 VO-GO).
- Schuljahre im Übersichtsplan und im Datei-Export richten sich nach dem Abiturjahrgang statt nach einer festen Angabe; ohne Jahrgang gilt die nächste Qualifikationsphase.
- CSV-Export: Werte, die Excel als Formel lesen würde, werden als Text geschrieben.
- Formular-Export lädt die PDFs einzeln nach und braucht weniger Speicher.
- Import: Hinweis im Protokoll, wenn für denselben Namen schon eine PDF mit anderer Schüler-ID vorliegt (vermutlich zwei Personen mit gleichem Namen).
- Regeltests für den Planer (`tools/test_regeln.js`), `.gitattributes` hält Shell-Skripte auf LF.

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
