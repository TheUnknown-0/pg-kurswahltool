"""Erzeugt aus dem Leitfaden (Artefakt-Quelle) die Hilfe-Unterseite des Planers.

python build_hilfe.py

- templates/hilfe.html          Seite, ausgeliefert über die Route /hilfe
- public/assets/hilfe/hilfe.js  alle Skripte (die CSP des Planers erlaubt keine Inline-Skripte)
- public/assets/hilfe/          Video, Vorschaubild, Untertitel
"""
import re
import shutil
from pathlib import Path

HERE = Path(__file__).resolve().parent
REPO = HERE.parent
SRC = HERE / "leitfaden" / "kurswahl-leitfaden.html"
ASSETS = REPO / "public" / "assets" / "hilfe"

src = SRC.read_text(encoding="utf-8")

# Untertitel nicht mehr eingebettet: echte Spur aus assets/hilfe
src, n = re.subn(r'\s*<script type="text/vtt" id="kw-vtt">.*?</script>', "", src, flags=re.S)
assert n == 1, "VTT-Block nicht gefunden"
src, n = re.subn(r"\s*// Untertitel liegen in der Seite.*?catch\(e\)\{\}", "", src, flags=re.S)
assert n == 1, "Blob-Spur nicht gefunden"
src = src.replace('poster="kurswahl-video-poster.jpg"', 'poster="assets/hilfe/kurswahl-video-poster.jpg"')
src = src.replace('<source src="kurswahl-video.mp4" type="video/mp4">',
                  '<source src="assets/hilfe/kurswahl-video.mp4" type="video/mp4">\n'
                  '              <track kind="subtitles" srclang="de" label="Deutsch" src="assets/hilfe/kurswahl-video.vtt">')
assert "assets/hilfe/kurswahl-video.mp4" in src and "assets/hilfe/kurswahl-video-poster.jpg" in src

# Kopf: Rückweg zum Planer statt Hinweis-Plakette
src, n = re.subn(r'<span class="tag">[^<]*</span>',
                 '<a class="tag back" href="./">← Zum Kurswahl-Planer</a>', src, count=1)
assert n == 1, "Kopf-Plakette nicht gefunden"
src = src.replace("<h1>Kurswahl-Leitfaden</h1>", "<h1>Hilfe zur Kurswahl</h1>", 1)
src = src.replace(".top .tag{", ".top a.tag{text-decoration:none;color:var(--blue);font-weight:650}\n.top .tag{", 1)

# Skripte herauslösen
scripts = re.findall(r"<script>(.*?)</script>", src, flags=re.S)
assert len(scripts) >= 2, "Skripte nicht gefunden"
src = re.sub(r"\s*<script>.*?</script>", "", src, flags=re.S)
assert "<script" not in src, "Inline-Skript übrig"

title = re.search(r"<title>(.*?)</title>", src).group(0)
src = src.replace(title, "", 1).lstrip()
page = ("<!DOCTYPE html>\n<html lang=\"de\">\n<head>\n<meta charset=\"utf-8\">\n"
        "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n"
        "<title>Hilfe zur Kurswahl – Kurswahl-Planer</title>\n"
        + src.replace("</style>", "</style>\n</head>\n<body>", 1)
        + "\n<script src=\"assets/hilfe/hilfe.js\"></script>\n</body>\n</html>\n")
page = re.sub(r'<meta name="claude:[^>]*>\n?', "", page)

ASSETS.mkdir(parents=True, exist_ok=True)
(REPO / "templates" / "hilfe.html").write_text(page, encoding="utf-8", newline="\n")
js = "/* Hilfe zur Kurswahl: erzeugt von extras/build_hilfe.py aus dem Leitfaden */\n" + "\n".join(s.strip() + "\n" for s in scripts)
(ASSETS / "hilfe.js").write_text(js, encoding="utf-8", newline="\n")
for f in ("kurswahl-video.mp4", "kurswahl-video-poster.jpg", "kurswahl-video.vtt"):
    shutil.copy2(HERE / "leitfaden" / f, ASSETS / f)
print("ok:", REPO / "templates" / "hilfe.html", ASSETS)
