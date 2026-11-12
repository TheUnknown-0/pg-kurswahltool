"""Baut die Präsentation: Bilder aus bilder/ als data-URI einbetten, Artefakt- und eigenständige Fassung schreiben.

python build.py
"""
import base64, io, re
from pathlib import Path
from PIL import Image
HERE = Path(__file__).resolve().parent
IMGS = {"logo": ("bilder/logo.png", 560, "png"), "start": ("bilder/planer-start.png", 1200, "webp"), "assistent": ("bilder/planer-assistent.png", 1200, "webp"),
        "kennzahlen": ("bilder/planer-kennzahlen.png", 1100, "webp"), "meldungen": ("bilder/planer-meldungen.png", 1100, "webp")}
def uri(name):
    f, w, fmt = IMGS[name]
    im = Image.open(HERE / f)
    if im.width > w:
        im = im.resize((w, round(im.height * w / im.width)), Image.LANCZOS)
    b = io.BytesIO()
    if fmt == "png": im.save(b, "PNG", optimize=True)
    else: im.convert("RGB").save(b, "WEBP", quality=88, method=6)
    return f"data:image/{fmt};base64," + base64.b64encode(b.getvalue()).decode()
src = (HERE / "praesentation.src.html").read_text(encoding="utf-8")
cache = {}
src = re.sub(r'IMG:(\w+)', lambda m: cache.setdefault(m.group(1), uri(m.group(1))), src)
assert "IMG:" not in src
(HERE / "auf-kurs-zum-abitur.html").write_text(src, encoding="utf-8")
full = ('<!doctype html>\n<html lang="de">\n<head>\n<meta charset="utf-8">\n'
        '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">\n' + src + '\n</html>\n')
(HERE / "Auf-Kurs-zum-Abitur.html").write_text(full, encoding="utf-8")
print("ok", len(full) // 1024, "KB")
