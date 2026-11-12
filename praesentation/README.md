# Präsentation „Auf Kurs zum Abitur“

HTML-Präsentation für den Infoabend zur Kurswahl, im Stil des Kurswahl-Planers.

- **Schüler-Fassung** (36 Folien): entlang der sieben Entscheidungen der Kurswahl (Ausgangslage, Leistungskurse,
  Prüfungsfächer, Pflichten, Sport und Zusatzkurse, Nachrechnen, Abgabe), danach das Abitur.
- **Eltern-Fassung** (22 Folien): sechs Fragen von „Wie läuft die Oberstufe ab?“ bis „Wie kann ich helfen?“.

| Datei | Inhalt |
|---|---|
| `Auf-Kurs-zum-Abitur.html` | fertige Präsentation, per Doppelklick im Browser öffnen (läuft offline; QR-Codes brauchen Internet) |
| `praesentation.src.html` | Quelle |
| `build.py` | bettet die Bilder aus `bilder/` ein und schreibt `Auf-Kurs-zum-Abitur.html` (`python build.py`, braucht Pillow) |

## Angaben

Beim ersten Öffnen erscheint oben der Bereich **Angaben**: Animationen an/aus, Schuljahr von Q1, Adresse des Planers,
Kontakt der Pädagogischen Koordination und Termine. Alles wird sofort übernommen und im Browser gespeichert.
Aus dem Schuljahr ergeben sich alle Jahreszahlen und die passende Geschichte-Regel (§ 25 Abs. 3, § 49 Abs. 11 VO-GO).
„Als Datei mit diesen Angaben speichern“ legt eine Kopie an, in der die Angaben fest stehen, etwa für den Laptop am Infoabend.

## Bedienung

| Taste | Wirkung |
|---|---|
| → Leertaste, Bild ab | nächster Punkt bzw. nächste Folie |
| ← Bild auf | zurück |
| N | Notizen neben der Folie |
| F | Vollbild |
| Ü oder O | Übersicht aller Folien |
| L | Leseansicht (alle Folien mit Erläuterungen, auch am Handy) |

„Referentenansicht“ öffnet Notizen, nächste Folie und Uhr in einem eigenen Fenster. Strg+P druckt eine Folie je A4-Seite quer.
