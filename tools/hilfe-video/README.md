# Erklärvideo und Hilfeseite

Quellen des Erklärvideos auf der Hilfeseite (`/hilfe`). Die Seite selbst liegt in `templates/hilfe.html`,
Skript, Video, Vorschaubild und Untertitel in `public/assets/hilfe/`.

- `script.json`: Sprechtexte je Szene (Stimme Gemini TTS „Charon“)
- `tts.py`: Sprachaufnahmen erzeugen (`GEMINI_TTS_DIR` zeigt auf den Ordner mit `gemini_tts.py`) und Abschnittsgrenzen bestimmen
- `render.py`: Video mit skia-python und ffmpeg (Netzplan-Design, 1080p)
- `build_hilfe.py`: erzeugt aus dem eigenständigen Leitfaden die Unterseite (CSP-konform, Skripte nach `hilfe.js`)

```bash
GEMINI_TTS_DIR=<Ordner> python tts.py gen
python tts.py beats
python render.py video
```

Sprachaufnahmen und Musik liegen nicht im Repo. Die Sprecherstimme ist KI-generiert; das Video weist darauf hin.
