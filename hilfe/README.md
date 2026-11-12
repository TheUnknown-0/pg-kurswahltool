# Hilfe zur Kurswahl: Leitfaden und Erklärvideo

Eigenständige Hilfeseite zur Kurswahl der Qualifikationsphase, vorerst getrennt vom Kurswahl-Planer
(nicht im Tool verlinkt). Die Regeln entsprechen der Prüfung im Planer (`public/assets/planer.js`,
VO-GO Berlin §§ 23, 25, 26), mit zwei Neuerungen ab dem Schuljahr 2026/27, die der Planer noch nicht prüft:
Die zusätzliche Geschichte liegt in Q1+Q2 (bisher Q3+Q4), und auch mit Sportbefreiung sind mindestens 40 Kurse
Pflicht (bisher 39). Inoffiziell: Verbindlich sind die Vorgaben der Schule und die Beratung durch den Päko.

| Datei | Inhalt |
|---|---|
| `index.html` | Leitfaden im Design des Planers mit Netzplan der Beispielwahl: Erklärvideo mit Kapiteln, Ablauf, Begriffe, Fächertabelle, Prüfungsregeln, Belegpflichten, Rechner, Beispielwahl, Stolperfallen, Checkliste. Läuft ohne Server direkt im Browser. |
| `kurswahl-video.mp4` | Erklärvideo im Netzplan-Stil, 5:34 Min., 1080p, Web-Fassung (13,1 MB) |
| `kurswahl-video-poster.jpg` | Vorschaubild des Videos |
| `kurswahl-video.srt`, `.vtt` | Deutsche Untertitel (in `index.html` zusätzlich eingebettet) |
| `video-quellen/` | Drehbuch und Skripte, aus denen das Video entstanden ist |

Die Sprecherstimme ist KI-generiert (Google Gemini TTS); das Video weist unten rechts darauf hin.
Die Hintergrundmusik ist lokal mit ACE-Step erzeugt.

Die Beispielwahl in Seite und Video (1. LK Mathematik, 2. LK Englisch, 3. PF Geschichte, 4. PF Biologie,
5. PK Kunst als Präsentationsprüfung) ist mit `evaluate()` aus dem Planer geprüft: keine Fehler, 40 Kurse,
66 Jahreswochenstunden, 19 einbringpflichtige Grundkurse.

## Video neu bauen

Voraussetzungen: Python mit `skia-python`, `numpy`, `scipy`, `soundfile`; `ffmpeg`; Windows-Schriften (Segoe UI).
Sprachaufnahmen und Musik liegen nicht im Repo.

```bash
cd hilfe/video-quellen
GEMINI_TTS_DIR=<Ordner mit gemini_tts.py> python tts.py gen   # Sprechtexte aus script.json, 1 Aufruf je Szene
python tts.py beats                                          # Abschnittsgrenzen aus den Pausen
python render.py stills 30 120                               # Probebilder
python render.py video                                       # Video nach out/
```

Musik (optional) als Schleife unter `music/loop.ogg`; ohne Datei entsteht das Video ohne Musik.
