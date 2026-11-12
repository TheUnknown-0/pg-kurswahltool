"""Kurswahl-Erklärvideo: 2D-Motion-Graphics mit skia, Ton aus voice/*.wav.

python render.py stills t1 t2 ...   einzelne Bilder (Sekunden) nach out/stills/
python render.py video               komplettes Video nach out/Kurswahl-Video.mp4
"""
import json
import math
import subprocess
import sys
from pathlib import Path

import numpy as np
import skia
import soundfile as sf
from scipy.signal import resample_poly

HERE = Path(__file__).resolve().parent
OUT = HERE / "out"
W, H, FPS = 1920, 1080, 30
SR = 48000

# ------------------------------------------------------------------ Farben
# Farben des Kurswahl-Planers (public/assets/planer.css): hell, weiße Karten, Navy als Leitfarbe
PAL = dict(
    bg0="#e8eef5", bg1="#f4f7fb", grid="#d7dee8", card="#ffffff", card2="#f5f7fa", line="#d7dee8",
    ink="#1c2733", ink2="#5d6877", ink3="#8793a2", head="#142f50", shadow="#193653", white="#ffffff",
    blue="#3a74b9", blue_soft="#e7eff8", lk="#153d6f", lk_soft="#dfe8f4", pk="#365d44", pk_soft="#edf6f0",
    sport="#b66a00", sport_soft="#fff2d9", red="#a52424", red_soft="#fae7e7", orange="#b66a00", orange_soft="#fff2d9",
    green="#226038", green_soft="#dff2e5", zk="#8793a2", zk_soft="#eef1f5",
    # Netzplan der Hilfeseite (Linienfarben wie im Hero-Plan)
    navy="#153d6f", pf="#4f84c4", gkline="#235a96", strong="#b9c4d2", zone="#edf5ff", paper3="#e8edf3",
    gktxt="#40546b", sp="#b66a00", mapline="#cbd8e7",
)
LINECOL = dict(lk="navy", pf="pf", pk="pk", gk="gkline", zk="zk", sp="sp")


def col(name, a=1.0):
    h = PAL.get(name, name)
    r, g, b = int(h[1:3], 16), int(h[3:5], 16), int(h[5:7], 16)
    return skia.ColorSetARGB(max(0, min(255, int(round(a * 255)))), r, g, b)


def paint(c, a=1.0, stroke=0.0):
    p = skia.Paint(AntiAlias=True, Color=col(c, a))
    if stroke:
        p.setStyle(skia.Paint.kStroke_Style)
        p.setStrokeWidth(stroke)
        p.setStrokeCap(skia.Paint.kRound_Cap)
        p.setStrokeJoin(skia.Paint.kRound_Join)
    return p


# ------------------------------------------------------------------ Schriften
FONTS = Path(r"C:\Windows\Fonts")
# Systemschrift wie im Planer, als statische Schnitte (variable Klone rendern in skia unzuverlässig)
_STATIC = [(350, "segoeuil.ttf"), (500, "segoeui.ttf"), (650, "seguisb.ttf"), (760, "segoeuib.ttf"), (2000, "seguibl.ttf")]
_tf_cache, _font_cache = {}, {}


def typeface(kind, wght):
    w = wght if kind != "m" else max(600, wght)
    fname = next(f for lim, f in _STATIC if w <= lim)
    if fname not in _tf_cache:
        _tf_cache[fname] = skia.Typeface.MakeFromFile(str(FONTS / fname))
    return _tf_cache[fname]


def font(kind, size, wght=400):
    key = (kind, round(size, 1), wght)
    if key not in _font_cache:
        f = skia.Font(typeface(kind, wght), size)
        f.setEdging(skia.Font.Edging.kAntiAlias)
        f.setSubpixel(True)
        _font_cache[key] = f
    return _font_cache[key]


def tw(s, f):
    return f.measureText(s)


def text(c, s, x, y, f, color="ink", a=1.0, align="l", track=0.0):
    if a <= 0.003:
        return
    p = paint(color, a)
    if track:
        w = sum(tw(ch, f) for ch in s) + track * (len(s) - 1)
        if align == "c":
            x -= w / 2
        elif align == "r":
            x -= w
        for ch in s:
            c.drawString(ch, x, y, f, p)
            x += tw(ch, f) + track
        return
    w = tw(s, f)
    if align == "c":
        x -= w / 2
    elif align == "r":
        x -= w
    c.drawString(s, x, y, f, p)


def wrap(s, f, maxw):
    lines, cur = [], ""
    for word in s.split():
        t = (cur + " " + word).strip()
        if tw(t, f) <= maxw or not cur:
            cur = t
        else:
            lines.append(cur)
            cur = word
    if cur:
        lines.append(cur)
    return lines


def para(c, s, x, y, f, maxw, lh, color="ink2", a=1.0, align="l"):
    for i, ln in enumerate(wrap(s, f, maxw)):
        text(c, ln, x, y + i * lh, f, color, a, align)
    return len(wrap(s, f, maxw))


# ------------------------------------------------------------------ Formen
def rrect(c, x, y, w, h, r, color, a=1.0, stroke=0.0):
    if a <= 0.003:
        return
    c.drawRRect(skia.RRect.MakeRectXY(skia.Rect.MakeXYWH(x, y, w, h), r, r), paint(color, a, stroke))


def circle(c, x, y, r, color, a=1.0, stroke=0.0):
    if a <= 0.003:
        return
    c.drawCircle(x, y, r, paint(color, a, stroke))


def line(c, x1, y1, x2, y2, color, a=1.0, w=3.0):
    if a <= 0.003:
        return
    c.drawLine(x1, y1, x2, y2, paint(color, a, w))


def check(c, x, y, s, color="green", a=1.0, w=4.0, prog=1.0):
    """Häkchen mit Mittelpunkt x,y und Größe s; prog zeichnet es schrittweise."""
    if a <= 0.003 or prog <= 0:
        return
    p1, p2, p3 = (x - s * 0.45, y + s * 0.02), (x - s * 0.12, y + s * 0.33), (x + s * 0.48, y - s * 0.32)
    l1 = math.dist(p1, p2)
    l2 = math.dist(p2, p3)
    d = prog * (l1 + l2)
    pa = paint(color, a, w)
    if d <= l1:
        k = d / l1
        c.drawLine(p1[0], p1[1], p1[0] + (p2[0] - p1[0]) * k, p1[1] + (p2[1] - p1[1]) * k, pa)
    else:
        c.drawLine(*p1, *p2, pa)
        k = (d - l1) / l2
        c.drawLine(p2[0], p2[1], p2[0] + (p3[0] - p2[0]) * k, p2[1] + (p3[1] - p2[1]) * k, pa)


def cross(c, x, y, s, color="red", a=1.0, w=4.0):
    line(c, x - s / 2, y - s / 2, x + s / 2, y + s / 2, color, a, w)
    line(c, x - s / 2, y + s / 2, x + s / 2, y - s / 2, color, a, w)


def lock(c, x, y, s, color, a=1.0, open_=0.0):
    """Schloss, Mittelpunkt des Körpers bei x,y; open_ hebt den Bügel an."""
    rrect(c, x - s * 0.42, y - s * 0.12, s * 0.84, s * 0.62, s * 0.1, color, a)
    p = paint(color, a, s * 0.11)
    p.setStyle(skia.Paint.kStroke_Style)
    lift = open_ * s * 0.22
    rect = skia.Rect.MakeXYWH(x - s * 0.27, y - s * 0.5 - lift, s * 0.54, s * 0.62)
    path = skia.Path()
    path.addArc(rect, 180, 180)
    path.lineTo(x + s * 0.27, y - s * 0.12 - lift + (s * 0.1 if open_ < 0.5 else -s * 0.05))
    path.moveTo(x - s * 0.27, y - s * 0.19 - lift)
    path.lineTo(x - s * 0.27, y - s * 0.12 - lift * (1 - open_) * 0 + (0 if open_ < 0.5 else -s * 0.3))
    c.drawPath(path, p)


def speaker(c, x, y, s, color, a=1.0):
    path = skia.Path()
    path.moveTo(x - s * 0.5, y - s * 0.18)
    path.lineTo(x - s * 0.22, y - s * 0.18)
    path.lineTo(x + s * 0.08, y - s * 0.45)
    path.lineTo(x + s * 0.08, y + s * 0.45)
    path.lineTo(x - s * 0.22, y + s * 0.18)
    path.lineTo(x - s * 0.5, y + s * 0.18)
    path.close()
    c.drawPath(path, paint(color, a))
    p = paint(color, a, s * 0.1)
    for r in (0.32, 0.55):
        c.drawArc(skia.Rect.MakeXYWH(x + s * 0.1 - s * r, y - s * r, 2 * s * r, 2 * s * r), -45, 90, False, p)


def ball(c, x, y, r, color, a=1.0):
    circle(c, x, y, r, color, a, r * 0.22)
    p = paint(color, a, r * 0.18)
    c.drawArc(skia.Rect.MakeXYWH(x - r * 1.6, y - r, r * 2, r * 2), -60, 120, False, p)
    c.drawArc(skia.Rect.MakeXYWH(x + r * -0.4, y - r, r * 2, r * 2), 120, 120, False, p)


def doc_icon(c, x, y, s, color, a=1.0):
    path = skia.Path()
    path.moveTo(x - s * 0.35, y - s * 0.5)
    path.lineTo(x + s * 0.15, y - s * 0.5)
    path.lineTo(x + s * 0.35, y - s * 0.3)
    path.lineTo(x + s * 0.35, y + s * 0.5)
    path.lineTo(x - s * 0.35, y + s * 0.5)
    path.close()
    c.drawPath(path, paint(color, a, s * 0.07))
    for i in range(3):
        line(c, x - s * 0.2, y - s * 0.1 + i * s * 0.17, x + s * 0.2, y - s * 0.1 + i * s * 0.17, color, a, s * 0.06)


def bubble(c, x, y, s, color, a=1.0, flip=False):
    rrect(c, x - s * 0.5, y - s * 0.35, s, s * 0.62, s * 0.18, color, a, s * 0.08)
    path = skia.Path()
    bx = x + (s * 0.2 if flip else -s * 0.2)
    path.moveTo(bx - s * 0.08, y + s * 0.27)
    path.lineTo(bx + (s * 0.12 if not flip else -s * 0.12) * -1, y + s * 0.5)
    path.lineTo(bx + s * 0.1, y + s * 0.27)
    c.drawPath(path, paint(color, a, s * 0.08))


def magnifier(c, x, y, s, color, a=1.0):
    circle(c, x - s * 0.1, y - s * 0.1, s * 0.3, color, a, s * 0.09)
    line(c, x + s * 0.12, y + s * 0.12, x + s * 0.42, y + s * 0.42, color, a, s * 0.12)


# ------------------------------------------------------------------ Zeit
def clamp(x, lo=0.0, hi=1.0):
    return lo if x < lo else hi if x > hi else x


def ez(t, t0, d=0.6):
    x = clamp((t - t0) / d) if d > 0 else (1.0 if t >= t0 else 0.0)
    return 1 - (1 - x) ** 3


def ezio(t, t0, d=0.8):
    x = clamp((t - t0) / d)
    return 4 * x ** 3 if x < 0.5 else 1 - (-2 * x + 2) ** 3 / 2


def pop(t, t0, d=0.5):
    x = clamp((t - t0) / d)
    c1, c3 = 1.70158, 2.70158
    return 1 + c3 * (x - 1) ** 3 + c1 * (x - 1) ** 2 if x > 0 else 0.0


def lerp(a, b, k):
    return a + (b - a) * k


# ------------------------------------------------------------------ Zeitplan
SCRIPT = json.loads((HERE / "script.json").read_text(encoding="utf-8"))
BEATS = json.loads((HERE / "voice" / "beats.json").read_text(encoding="utf-8")) if (HERE / "voice" / "beats.json").exists() else {}
PRE, POST = 1.1, 1.0


class Scene:
    pass


def build_timeline():
    scenes, t = [], 0.0
    n = len(SCRIPT["scenes"])
    for i, s in enumerate(SCRIPT["scenes"]):
        sc = Scene()
        sc.id, sc.title, sc.idx = s["id"], s["title"], i
        info = BEATS.get(s["id"])
        if info:
            dur = info["dur"]
            starts = info["starts"]
        else:  # Platzhalter ohne Ton: 2,6 s je Abschnitt plus Zeichenanteil
            lens = [len(b) for b in s["beats"]]
            dur = sum(lens) / 15.5 + 0.8 * (len(lens) - 1)
            starts, acc = [], 0.3
            for L in lens:
                starts.append(acc)
                acc += L / 15.5 + 0.8
        pre = PRE + (0.4 if i == 0 else 0)
        post = POST + (5.0 if i == n - 1 else 0)
        sc.start = t
        sc.audio_at = t + pre
        sc.dur_audio = dur
        sc.b = [sc.audio_at + st for st in starts]
        sc.end = sc.audio_at + dur + post
        sc.audio_end = sc.audio_at + dur
        scenes.append(sc)
        t = sc.end
    return scenes, t


SCENES, TOTAL = build_timeline()


# ------------------------------------------------------------------ Hintergrund
def make_grid_image():
    """Feines Kartenraster wie auf Netzplan-Papier."""
    s = skia.Surface(W + 240, H + 240)
    c = s.getCanvas()
    c.clear(skia.ColorTRANSPARENT)
    for i, gx in enumerate(range(0, W + 240, 55)):
        line(c, gx, 0, gx, H + 240, "grid", 0.9 if i % 4 == 0 else 0.45, 1.2)
    for i, gy in enumerate(range(0, H + 240, 55)):
        line(c, 0, gy, W + 240, gy, "grid", 0.9 if i % 4 == 0 else 0.45, 1.2)
    return s.makeImageSnapshot()


BG = None
GLOWS = ((1500, 260, 820, "white", 0.55, 0.07), (300, 900, 760, "blue", 0.05, 0.05))


def _img(w, h, draw):
    s = skia.Surface(w, h)
    c = s.getCanvas()
    c.clear(skia.ColorTRANSPARENT)
    draw(c)
    return s.makeImageSnapshot()


def _bg_cache():
    """Alles Teure einmal vorab: Verlauf, Raster (Alpha eingebacken), Lichtflecken als Sprites, Vignette."""
    base = _img(W, H, lambda c: c.drawPaint(skia.Paint(Shader=skia.GradientShader.MakeLinear(
        [skia.Point(0, 0), skia.Point(W, H)], [col("bg0"), col("bg1")]))))

    def grid(c):
        c.saveLayerAlpha(None, 128)
        c.drawImage(make_grid_image(), 0, 0)
        c.restore()
    gridimg = _img(W + 240, H + 240, grid)
    sprites = []
    for (cx, cy, r, cname, a, sp) in GLOWS:
        sprites.append(_img(2 * r, 2 * r, lambda c, r=r, cname=cname, a=a: c.drawPaint(skia.Paint(
            Shader=skia.GradientShader.MakeRadial(skia.Point(r, r), r, [col(cname, a), col(cname, 0)])))))
    vig = _img(W, H, lambda c: c.drawPaint(skia.Paint(Shader=skia.GradientShader.MakeRadial(
        skia.Point(W / 2, H / 2), 1250, [col("grid", 0), col("grid", 0.0), col("grid", 0.45)], [0, 0.55, 1]))))
    return base, gridimg, sprites, vig


def background(c, t):
    global BG
    if BG is None:
        BG = _bg_cache()
    base, gridimg, sprites, vig = BG
    c.drawImage(base, 0, 0)
    c.drawImage(gridimg, -((t * 7) % 220), -((t * 4) % 220))
    for (cx, cy, r, cname, a, sp), spr in zip(GLOWS, sprites):
        x = cx + math.sin(t * sp) * 120
        y = cy + math.cos(t * sp * 1.3) * 80
        c.drawImage(spr, x - r, y - r)
    c.drawImage(vig, 0, 0)


HUD_NAMES = ["Start", "Ausgangslage", "Leistungskurse", "Prüfungsfächer", "Belegpflichten", "Sport", "Nachrechnen", "Abgabe"]


def hud(c, t):
    """Fortschritt als U-Bahn-Linie: eine Station je Kapitel, gefahrene Strecke in Navy."""
    n = len(SCENES)
    fade = ez(t, 0.2, 1.0) * (1 - ez(t, TOTAL - 1.6, 1.2))
    if fade > 0.003:
        xs = np.linspace(214, 1780, n)
        y = 62
        bw = badge(c, 120, y - 17, "Q1–4", "lk", fade, 19, h=34)
        line(c, xs[0], y, xs[-1], y, "strong", 0.8 * fade, 7)
        cur = 0
        for i, sc in enumerate(SCENES):
            if t >= sc.start:
                cur = i
        sc = SCENES[cur]
        k = clamp((t - sc.start) / (sc.end - sc.start))
        xr = xs[cur] + ((xs[cur + 1] - xs[cur]) * k if cur < n - 1 else 0)
        line(c, xs[0], y, xr, y, "navy", fade, 7)
        for i, x in enumerate(xs):
            on = i <= cur
            r = 10 if i == cur else 7.5
            circle(c, x, y, r, "white", fade)
            circle(c, x, y, r, "navy" if on else "strong", fade, 3.2 if i == cur else 2.6)
            if i == cur:
                circle(c, x, y, 4.2, "navy", fade)
            f = font("b", 18, 650 if i == cur else 500)
            text(c, HUD_NAMES[i], x, y + 38, f, "head" if i == cur else ("ink2" if on else "ink3"), fade, "c")
    # KI-Hinweis unten rechts
    f = font("b", 21, 560)
    label = "KI-generierte Stimme (Gemini TTS)"
    w = tw(label, f) + 70
    xr, yc = 1800, 1022
    rrect(c, xr - w, yc - 22, w, 44, 22, "card", 0.82 * fade)
    rrect(c, xr - w, yc - 22, w, 44, 22, "line", 0.9 * fade, 1.2)
    speaker(c, xr - w + 28, yc, 22, "ink2", 0.95 * fade)
    text(c, label, xr - 20, yc + 7.5, f, "ink2", 0.95 * fade, "r")


# ------------------------------------------------------------------ Bausteine
def badge(c, x, y, label, kind="lk", a=1.0, size=20, h=None, w=None, pad=None):
    """Linienschild wie im Netzplan: farbig mit weißer Schrift, GK weiß mit Rand, ZK grau."""
    if a <= 0.003:
        return 0
    f = font("b", size, 700)
    h = h or size * 1.75
    pad = size * 0.6 if pad is None else pad
    w = w or tw(label, f) + 2 * pad
    r = h * 0.24
    if kind in ("gk", "zk"):
        rrect(c, x, y, w, h, r, "white" if kind == "gk" else "paper3", a)
        rrect(c, x, y, w, h, r, "strong", a, 1.6)
        tc = "gktxt"
    else:
        rrect(c, x, y, w, h, r, LINECOL.get(kind, kind), a)
        tc = "white"
    text(c, label, x + w / 2, y + h / 2 + size * 0.36, f, tc, a, "c")
    return w


def station(c, x, y, r, a=1.0, ring="navy", k=1.0):
    if k <= 0 or a <= 0.003:
        return
    rr = r * k
    circle(c, x, y, rr, "white", a)
    circle(c, x, y, rr, ring, a, r * 0.36)


def header(c, t, sc, eyebrow, title):
    k = ez(t, sc.start + 0.15, 0.7)
    dy = (1 - k) * 26
    badge(c, 120, 158 + dy, eyebrow, "lk", k, 23, h=42)
    text(c, title, 116, 292 + dy, font("d", 80, 750), "head", k)


def chip(c, x, y, label, color="blue", a=1.0, size=30, fill=True, h=None, pad=22, txt=None):
    f = font("b", size, 600)
    w = tw(label, f) + pad * 2
    h = h or size * 1.75
    r = h * 0.24
    if fill:
        rrect(c, x, y, w, h, r, color + "_soft" if color + "_soft" in PAL else color, a)
    rrect(c, x, y, w, h, r, color, a, 2)
    text(c, label, x + pad, y + h / 2 + size * 0.36, f, txt or ("ink" if fill else color), a)
    return w


def chips_flow(c, t, t0, x, y, maxw, labels, color, size=30, gapx=14, gapy=16, stagger=0.09, fill=True):
    f = font("b", size, 600)
    h = size * 1.75
    cx, cy = x, y
    for i, lb in enumerate(labels):
        w = tw(lb, f) + 44
        if cx + w > x + maxw:
            cx, cy = x, cy + h + gapy
        k = pop(t, t0 + i * stagger, 0.5)
        if k > 0:
            a = clamp(k)
            c.save()
            c.translate(cx + w / 2, cy + h / 2)
            c.scale(0.6 + 0.4 * k, 0.6 + 0.4 * k)
            chip(c, -w / 2, -h / 2, lb, color, a, size, fill)
            c.restore()
        cx += w + gapx
    return cy + h


SHADOW_MF = skia.MaskFilter.MakeBlur(skia.kNormal_BlurStyle, 14)


def card(c, x, y, w, h, a=1.0, accent=None):
    if a > 0.003:
        sp = paint("shadow", 0.10 * a)
        sp.setMaskFilter(SHADOW_MF)
        c.drawRRect(skia.RRect.MakeRectXY(skia.Rect.MakeXYWH(x, y + 8, w, h), 14, 14), sp)
    rrect(c, x, y, w, h, 14, "card", a)
    if accent and a > 0.003:
        # Akzent oben wie die Hero-Karte der Seite (border-top)
        c.save()
        c.clipRRect(skia.RRect.MakeRectXY(skia.Rect.MakeXYWH(x, y, w, h), 14, 14), skia.ClipOp.kIntersect, True)
        c.drawRect(skia.Rect.MakeXYWH(x, y, w, 6), paint(accent, a))
        c.restore()


def dotted(c, x1, y, x2, color="strong", a=1.0, w=3.0):
    if a <= 0.003:
        return
    p = paint(color, a, w)
    p.setPathEffect(skia.DashPathEffect.Make([0.1, 11.0], 0))
    c.drawLine(x1, y, x2, y, p)


def qcells(c, t, t0, x, y, cells, color, cw=54, ch=40, gap=8, stagger=0.08, labels=None, a=1.0, codes=None):
    """Halbjahre als Linie mit Stationen (wie die Belegpflichten der Seite)."""
    cy = y + ch / 2
    xs = [x + i * (cw + gap) + cw / 2 for i in range(4)]
    r = max(8.0, ch * 0.27)
    lw = max(7.0, ch * 0.3)
    dotted(c, xs[0], cy, xs[3], "strong", a, 3)
    for i in range(3):
        if cells[i] and cells[i + 1]:
            k = ezio(t, t0 + i * stagger, 0.45)
            if k > 0:
                line(c, xs[i], cy, xs[i] + (xs[i + 1] - xs[i]) * k, cy, color, a, lw)
    for i in range(4):
        if cells[i]:
            station(c, xs[i], cy, r, a, color, clamp(pop(t, t0 + i * stagger, 0.4), 0, 1.3))
        else:
            circle(c, xs[i], cy, r * 0.5, "card2", a)
            circle(c, xs[i], cy, r * 0.5, "strong", a, 1.8)
        if labels:
            text(c, f"Q{i + 1}", xs[i], y - 12, font("m", 18), "ink3", a, "c")


def zone_bands(c, xs, y0, y1, a=1.0, half=56, lab_size=18):
    """Hellblaue Zonen für 1. und 2. Jahr wie im Netzplan."""
    for (i, j, lab) in ((0, 1, "1. Jahr"), (2, 3, "2. Jahr")):
        rrect(c, xs[i] - half, y0, xs[j] - xs[i] + 2 * half, y1 - y0, 16, "zone", a)
        text(c, lab, (xs[i] + xs[j]) / 2, y1 - 14, font("b", lab_size, 700), "ink3", a, "c")


def netz(c, t, rows, starts, x, xs, y0, step, a=1.0, s=1.0, top_labels=True, dim=None):
    """Netzplan: je Fach ein Linienschild, eine Linie über die Halbjahre und Stationen.
    rows: (name, schild, art, [q1..q4]); starts: Startzeit je Zeile."""
    if top_labels:
        for i, qx in enumerate(xs):
            text(c, f"Q{i + 1}", qx, y0 - 34 * s, font("d", 30 * s, 800), "navy", a, "c")
    bw, bh = 86 * s, 34 * s
    for i, (name, lab, kind, cells) in enumerate(rows):
        t0 = starts[i]
        k = ez(t, t0 - 0.1, 0.45)
        if k <= 0:
            continue
        ra = a * (dim[i] if dim else 1.0)
        y = y0 + i * step
        badge(c, x, y - bh / 2 + (1 - k) * 10, lab, kind, k * ra, 17 * s, h=bh, w=bw)
        text(c, name, x + bw + 16 * s, y + 9 * s, font("b", 25 * s, 600), "ink", k * ra)
        first, last = cells.index(1), 3 - cells[::-1].index(1)
        kl = ezio(t, t0, 0.8)
        lc = LINECOL[kind]
        if kl > 0:
            line(c, xs[first], y, xs[first] + (xs[last] - xs[first]) * kl, y, lc, ra * (0.45 if kind == "gk" else 1.0), 12 * s)
        for q in range(4):
            if cells[q]:
                station(c, xs[q], y, 10.5 * s, ra, "navy", clamp(pop(t, t0 + 0.35 + 0.12 * (q - first), 0.35), 0, 1.3))


def meter(c, t, x, y, w, label, val, target, color="blue", a=1.0, fmt="{:.0f}"):
    text(c, label, x, y, font("b", 28, 500), "ink2", a)
    ok = val >= target - 1e-6
    vs = fmt.format(val)
    text(c, f"/ {target}", x + w, y + 72, font("d", 36, 500), "ink3", a, "r")
    tw_t = tw(f"/ {target}", font("d", 36, 500))
    text(c, vs, x + w - tw_t - 14, y + 72, font("d", 76, 700), "green" if ok else "ink", a, "r")
    rrect(c, x, y + 96, w, 14, 7, "line", a)
    k = clamp(val / target)
    if k > 0:
        rrect(c, x, y + 96, max(14, w * k), 14, 7, "green" if ok else color, a)


# ------------------------------------------------------------------ Szenen
def map_head(c, x, y, w, a, okk, title="Beispiel einer gültigen Wahl", size=32):
    """Kopf der Netzplan-Karte wie auf der Seite: Titel links, grüne Plakette rechts."""
    text(c, title, x, y, font("d", size, 700), "head", a)
    if okk > 0:
        f = font("b", size * 0.72, 650)
        lab = "40 Kurse · 66 JWS"
        pw = tw(lab, f) + 76
        h = size * 1.3
        px = x + w - pw
        rrect(c, px, y - h * 0.72, pw, h, h / 2, "green_soft", a * okk)
        check(c, px + 28, y - h * 0.72 + h / 2, h * 0.42, "green", a * okk, 3.5, ez(okk, 0, 1))
        text(c, lab, px + 50, y - h * 0.72 + h / 2 + size * 0.26, f, "green", a * okk)


def s_intro(c, t, sc):
    b = sc.b
    morph = ezio(t, b[3] - 0.2, 1.0)
    k0 = ez(t, sc.start + 0.2, 0.9)
    size = lerp(150, 92, morph)
    f = font("d", size, 700)
    y1 = lerp(400, 300, morph)
    x2 = lerp(118, 118 + tw("Kurswahl ", f), morph)
    y2 = lerp(548, 300, morph)
    text(c, "PAULSEN-GYMNASIUM  ·  QUALIFIKATIONSPHASE", 122, lerp(232, 196, morph) + (1 - k0) * 20, font("b", 24, 700), "blue", k0, track=3)
    text(c, "Kurswahl", 112, y1 + (1 - k0) * 30, f, "head", k0)
    k1 = ez(t, sc.start + 0.55, 0.9)
    text(c, "Q1–Q4", x2 - (1 - morph) * 6, y2 + (1 - k1) * 30, f, "blue", k1)
    fade = 1 - ez(t, b[3] - 0.4, 0.5)
    text(c, "Was du wissen musst, Schritt für Schritt", 120, 640, font("b", 38, 450), "ink2", ez(t, sc.start + 0.9, 0.8) * fade)

    # Kennzahlen als Kacheln wie im Hero der Seite
    facts = [("2", "Leistungs-", "kurse"), ("5", "Prüfungs-", "komponenten"), ("40", "Kurse", "mindestens")]
    for i, (num, l1, l2) in enumerate(facts):
        k = pop(t, b[2] + 0.2 + 0.7 * i, 0.55)
        if k <= 0:
            continue
        a = clamp(k) * fade
        x, y = 120 + i * 270, 712 + (1 - clamp(k)) * 24
        rrect(c, x, y, 248, 188, 14, "card", a)
        rrect(c, x, y, 248, 188, 14, "mapline", a, 1.8)
        text(c, num, x + 28, y + 92, font("d", 80, 800), "navy", a)
        text(c, l1, x + 30, y + 136, font("b", 25, 550), "ink2", a)
        text(c, l2, x + 30, y + 168, font("b", 25, 550), "ink2", a)

    # Netzplan der Beispielwahl
    ma = ez(t, b[0] + 1.2, 0.8) * fade
    if ma > 0:
        x, y, w, h = 1000, 168 + (1 - ma) * 30, 800, 812
        card(c, x, y, w, h, ma)
        rrect(c, x, y, w, h, 14, "mapline", ma, 1.5)
        map_head(c, x + 34, y + 62, w - 68, ma, ez(t, b[1] + 6.6, 0.6))
        xs = [x + 404, x + 502, x + 600, x + 698]
        zone_bands(c, xs, y + 96, y + h - 18, ma, half=46)
        starts = [b[1] + 0.3 + i * 0.5 for i in range(len(EX))]
        netz(c, t, EX, starts, x + 30, xs, y + 182, 54.5, ma, s=0.92)

    # Dein Weg: Kapitel als Linie
    wa = ez(t, b[3] + 0.6, 0.7)
    text(c, "Dein Weg durch die Kurswahl", 120, 392, font("b", 40, 450), "ink2", wa)
    labels = ["Ausgangslage", "Leistungskurse", "Prüfungsfächer", "Belegpflichten", "Sport und\nZusatzkurse", "Nachrechnen", "Abgeben"]
    xs = np.linspace(280, 1720, len(labels))
    ly = 640
    kl = ezio(t, b[3] + 0.3, 2.4)
    if kl > 0:
        badge(c, 120, ly - 22, "Q1–4", "lk", wa, 22, h=44, w=86)
        line(c, xs[0], ly, xs[0] + (xs[-1] - xs[0]) * kl, ly, "navy", 1.0, 16)
    for i, (x, lab) in enumerate(zip(xs, labels)):
        k = pop(t, b[3] + 0.45 + i * 0.3, 0.5)
        if k <= 0:
            continue
        station(c, x, ly, 26, 1.0, "navy", clamp(k, 0, 1.3))
        text(c, str(i + 1), x, ly - 52, font("d", 30, 800), "navy", clamp(k), "c")
        for li, ln in enumerate(lab.split("\n")):
            text(c, ln, x, ly + 82 + li * 36, font("b", 29, 600), "ink", clamp(k), "c")


def s_ausgangslage(c, t, sc):
    b = sc.b
    header(c, t, sc, "Schritt 1", "Deine Ausgangslage")
    text(c, "Sie entscheidet, was du überhaupt wählen darfst.", 120, 360, font("b", 36, 450), "ink2", ez(t, b[0] + 1.0, 0.6))
    cw, y0, hh = 530, 420, 520
    xs = [120, 695, 1270]
    titles = ["Fremdsprachen", "Wahlpflichtfach Kl. 10", "Sport"]
    for i in range(3):
        k = ez(t, b[i + 1] - 0.1, 0.6)
        if k <= 0:
            continue
        x, y = xs[i], y0 + (1 - k) * 40
        card(c, x, y, cw, hh, k, ["blue", "lk", "sport"][i])
        text(c, titles[i], x + 40, y + 70, font("d", 44, 700), "ink", k)
    # Karte 1
    x, y = xs[0], y0
    rows = [("Englisch", "seit Kl. 3", "LK möglich", "green"), ("Französisch", "seit Kl. 7", "LK möglich", "green"),
            ("Spanisch", "neu ab Kl. 10", "kein LK, bis Q4", "orange")]
    for j, (n, s, badge, bc) in enumerate(rows):
        k = ez(t, b[1] + 0.5 + j * 0.5, 0.5)
        if k <= 0:
            continue
        ry = y + 140 + j * 118
        text(c, n, x + 40, ry + 18, font("b", 32, 600), "ink", k)
        text(c, s, x + 40, ry + 56, font("m", 23), "ink3", k)
        f = font("b", 23, 600)
        bw = tw(badge, f) + 30
        rrect(c, x + cw - 40 - bw, ry - 6, bw, 42, 21, bc + "_soft", k)
        text(c, badge, x + cw - 40 - bw / 2, ry + 23, f, bc, k, "c")
    # Karte 2
    x = xs[1]
    names = ["Informatik", "Sozialwissenschaften", "Darstellendes Spiel"]
    for j, n in enumerate(names):
        k = ez(t, b[2] + 0.5 + j * 0.35, 0.5)
        if k <= 0:
            continue
        ry = y + 150 + j * 92
        rrect(c, x + 36, ry - 36, cw - 72, 70, 14, "card2", k)
        text(c, n, x + 64, ry + 10, font("b", 30, 600), "ink", k)
        op = ez(t, b[2] + 2.2 + j * 0.3, 0.5)
        lock(c, x + cw - 80, ry + 2, 34, "green" if op > 0.5 else "ink3", k, op)
    para(c, "nur wählbar, wenn du das Fach in Klasse 10 schon hattest", x + 40, y + 450, font("b", 26, 450), cw - 80, 34, "ink2",
         ez(t, b[2] + 1.6, 0.5))
    # Karte 3
    x = xs[2]
    opts = [("Ruder-AG, mind. 1 Jahr", "Rudern wird wählbar", True), ("Attest: dauerhaft befreit", "kein Sport, trotzdem 40 Kurse", False)]
    for j, (n, res, on) in enumerate(opts):
        k = ez(t, b[3] + 0.4 + j * 0.9, 0.5)
        if k <= 0:
            continue
        ry = y + 150 + j * 165
        text(c, n, x + 40, ry + 10, font("b", 30, 600), "ink", k)
        tk = ez(t, b[3] + 0.9 + j * 0.9, 0.4)
        sx, sy = x + 40, ry + 42
        rrect(c, sx, sy, 76, 40, 20, "sport" if tk > 0.5 else "line", k)
        circle(c, sx + 20 + 36 * tk, sy + 20, 15, "ink", k)
        text(c, res, sx + 96, sy + 30, font("b", 26, 450), "ink2", k * tk)


def s_lk(c, t, sc):
    b = sc.b
    header(c, t, sc, "Schritt 2", "Zwei Leistungskurse")
    for j, lab in enumerate(["1. LK", "2. LK"]):
        k = ez(t, b[0] + 0.3 + j * 0.3, 0.5)
        y = 372 + j * 78
        badge(c, 120, y + 3, lab, "lk", k, 21, h=40, w=104)
        qcells(c, t, b[0] + 0.5 + j * 0.3, 260, y, [1, 1, 1, 1], "lk", cw=90, ch=46, gap=10, a=k)
    ka = ez(t, b[0] + 2.4, 0.5)
    chip(c, 690, 380, "5 Std. pro Woche", "lk", ka, 28)
    kb = pop(t, b[0] + 3.6, 0.5)
    if kb > 0:
        c.save()
        c.translate(690, 486)
        chip(c, 0, -24, "zählt im Abitur doppelt", "lk", clamp(kb), 28)
        c.restore()
    # Bilingual-Hinweis
    kc = ez(t, b[3] - 0.1, 0.5)
    if kc > 0:
        card(c, 1180, 362, 620, 150, kc, "orange")
        text(c, "Geschichte / Politik bilingual", 1220, 420, font("b", 30, 650), "ink", kc)
        text(c, "nur als Grundkurs", 1220, 466, font("b", 28, 450), "ink2", kc)
        chip(c, 1640, 404, "LK", "red", kc, 26, fill=True)
        cross(c, 1675, 427, 50, "red", kc * ez(t, b[3] + 0.5, 0.3), 5)
    # 1. LK
    k1 = ez(t, b[1] - 0.1, 0.6)
    if k1 > 0:
        card(c, 120, 590, 760, 335, k1, "lk")
        text(c, "1. LK", 160, 650, font("d", 42, 700), "lk", k1)
        text(c, "strenge Regel", 290, 648, font("b", 26, 450), "ink3", k1)
        end = chips_flow(c, t, b[1] + 0.9, 160, 690, 690, ["Deutsch", "Mathematik", "Physik", "Chemie", "Biologie"], "lk", 28, stagger=0.25)
        chips_flow(c, t, b[1] + 3.6, 160, end + 16, 690, ["Fremdsprache, seit spätestens Kl. 9"], "lk", 28)
    k2 = ez(t, b[2] - 0.1, 0.6)
    if k2 > 0:
        card(c, 920, 590, 880, 335, k2, "blue")
        text(c, "2. LK", 960, 650, font("d", 42, 700), "blue", k2)
        text(c, "alles vom 1. LK, zusätzlich", 1090, 648, font("b", 26, 450), "ink3", k2)
        chips_flow(c, t, b[2] + 1.0, 960, 690, 810,
                   ["Musik", "Kunst", "Geschichte", "Politikwiss.", "Geografie", "Informatik", "Sozialwiss."], "blue", 28, stagger=0.42)


def s_pruefung(c, t, sc):
    b = sc.b
    header(c, t, sc, "Schritt 3", "Prüfungsfächer und 5. PK")
    slots = [("1. PF  ·  LK", "Mathematik", "lk", 3), ("2. PF  ·  LK", "Englisch", "lk", 1), ("3. PF", "Geschichte", "blue", 2),
             ("4. PF", "Biologie", "blue", 3), ("5. PK", "Bildende Kunst", "pk", 1)]
    sw, gap, y0 = 300, 45, 360
    hl = ez(t, b[1] + 0.2, 0.5) * (1 - ez(t, b[2] - 0.2, 0.5))
    afk = ez(t, b[2] - 0.1, 0.6) * (1 - ez(t, b[4] - 0.3, 0.5))
    centers = []
    for i, (role, name, cc, af) in enumerate(slots):
        k = pop(t, b[0] + 0.3 + i * 0.22, 0.55)
        x = 120 + i * (sw + gap)
        centers.append(x + sw / 2)
        if k <= 0:
            continue
        a = clamp(k)
        hh = 205
        y = y0 + (1 - a) * 30
        if i < 2 and hl > 0:
            rrect(c, x - 8, y - 8, sw + 16, hh + 16, 24, "lk", 0.25 * hl)
        card(c, x, y, sw, hh, a)
        rrect(c, x, y, sw, 8, 4, cc, a)
        text(c, role, x + 26, y + 52, font("m", 22), cc, a)
        text(c, name, x + 26, y + 104, font("d", 38 if len(name) < 12 else 32, 750), "ink", a)
        qcells(c, t, b[3] + 0.2 + i * 0.18, x + 26, y + 138, [1, 1, 1, 1], cc, cw=56, ch=36, gap=8, stagger=0.06, a=a)
    # Regel 1
    if hl > 0:
        x0, x1 = 120, 120 + 2 * sw + gap
        line(c, x0 + 10, 600, x1 - 10, 600, "lk", hl, 3)
        line(c, x0 + 10, 590, x0 + 10, 600, "lk", hl, 3)
        line(c, x1 - 10, 590, x1 - 10, 600, "lk", hl, 3)
        text(c, "mind. 2 aus Deutsch · Mathe · Fremdsprache", x0, 650, font("b", 32, 600), "ink", hl)
        check(c, x0 + 690, 638, 30, "green", hl, 5, ez(t, b[1] + 1.4, 0.5))
    # Aufgabenfelder
    if afk > 0:
        bins = [("I", "sprachlich-künstlerisch", [1, 4]), ("II", "gesellschaftswiss.", [2]), ("III", "math.-naturwiss.", [0, 3])]
        bw, bg = 530, 45
        for j, (num, lab, members) in enumerate(bins):
            x = 120 + j * (bw + bg)
            y = 690
            card(c, x, y, bw, 250, afk)
            text(c, num, x + 30, y + 72, font("d", 64, 700), "blue", afk)
            text(c, lab, x + 30 + tw(num, font("d", 64, 700)) + 18, y + 64, font("b", 30, 550), "ink2", afk)
            for m_i, m in enumerate(members):
                kk = ez(t, b[2] + 0.8 + m * 0.35, 0.7)
                if kk <= 0:
                    continue
                sx, sy = centers[m], 570
                tx, ty = x + 30 + m_i * 250, 170 + y - 10
                px, py = lerp(sx, tx + 100, kk), lerp(sy, ty, kk) - math.sin(kk * math.pi) * 60
                name = slots[m][1]
                f = font("b", 28, 600)
                wv = tw(name, f) + 40
                cx_ = px - 100 if kk >= 1 else px - wv / 2
                rrect(c, cx_, py - 24, wv, 48, 24, slots[m][2], 0.95 * afk)
                text(c, name, cx_ + wv / 2, py + 9, f, "white", afk, "c")
            ck = ez(t, b[2] + 2.6 + j * 0.25, 0.4)
            circle(c, x + bw - 50, y + 55, 26, "green_soft", afk * ck)
            check(c, x + bw - 50, y + 55, 26, "green", afk, 4, ck)
    # Durchgehend
    ka = ez(t, b[3] + 1.2, 0.5) * (1 - ez(t, b[4] - 0.3, 0.4))
    text(c, "alle fünf: Q1 bis Q4", 1800, 340, font("b", 30, 600), "ink2", ka, "r")
    # 5. PK Formen
    kf = ez(t, b[4] + 0.1, 0.6)
    if kf > 0:
        for j, (ttl, body, cc) in enumerate([("Präsentationsprüfung", "Referenzfach = eigenes, fünftes Fach, kein Prüfungsfach", "pk"),
                                             ("Besondere Lernleistung", "darf ein Prüfungsfach sein; dann decken die 4 PF allein alle Aufgabenfelder ab", "blue")]):
            k = ez(t, b[4] + 0.1 + j * 1.6, 0.6)
            x = 120 + j * 860
            y = 690 + (1 - k) * 30
            card(c, x, y, 820, 250, k, cc)
            text(c, ttl, x + 44, y + 72, font("d", 46, 700), cc, k)
            para(c, body, x + 44, y + 128, font("b", 31, 450), 740, 42, "ink", k)


def s_pflichten(c, t, sc):
    b = sc.b
    header(c, t, sc, "Schritt 4", "Belegpflichten")
    text(c, "Diese Kurse brauchst du immer.", 120, 360, font("b", 36, 450), "ink2", ez(t, b[0] + 1.0, 0.6))
    rows = [
        (1, "Deutsch", [1, 1, 1, 1], "navy", ""),
        (1, "Mathematik", [1, 1, 1, 1], "navy", ""),
        (1, "Eine Fremdsprache", [1, 1, 1, 1], "navy", ""),
        (1, "Eine Naturwissenschaft", [1, 1, 1, 1], "navy", "nur Bio: + 2 Kurse Ph/Ch"),
        (2, "Gesellschaftswiss. Fach", [1, 1, 1, 1], "navy", ""),
        (2, "Ist es Geschichte: + Politik", [0, 0, 1, 1], "pf", "Politikwiss. in Q3+Q4"),
        (2, "Sonst: + Geschichte", [1, 1, 0, 0], "pf", "NEU"),
        (3, "Musik, Kunst oder DS", [1, 1, 0, 0], "pk", "2 Kurse, Q1+Q2 oder Q3+Q4"),
    ]
    y0, rh = 404, 66
    timing = {1: [0.2, 1.6, 3.4, 5.6], 2: [0.2, 3.3, 7.8], 3: [0.2]}
    seen = {1: 0, 2: 0, 3: 0}
    for i, (bi, name, cells, cc, note) in enumerate(rows):
        t0 = b[bi] + timing[bi][seen[bi]]
        seen[bi] += 1
        k = ez(t, t0 - 0.1, 0.5)
        if k <= 0:
            continue
        y = y0 + i * rh
        rrect(c, 120, y, 1180, rh - 10, 12, "card", 0.9 * k)
        text(c, name, 150, y + 38, font("b", 29, 600), "ink", k)
        qcells(c, t, t0 + 0.2, 556, y + 9, cells, cc, cw=58, ch=38, gap=8, a=k)
        if note == "NEU":
            kn = ez(t, t0 + 2.0, 0.5)
            w = badge(c, 836, y + 10, "neu ab 2026/27", "red", k * kn, 19, h=36)
            ko = ez(t, t0 + 5.6, 0.5)
            if ko > 0:
                f = font("b", 23, 500)
                lab = "bisher Q3+Q4"
                tx = 836 + w + 18
                text(c, lab, tx, y + 36, f, "ink3", k * ko)
                line(c, tx - 2, y + 28, tx + (tw(lab, f) + 4) * ez(t, t0 + 6.1, 0.5), y + 28, "red", k * ko, 2.5)
        elif note:
            text(c, note, 836, y + 37, font("b", 23, 450), "ink3", ez(t, t0 + 0.8, 0.5))
        check(c, 1262, y + 28, 24, "green", k, 4, ez(t, t0 + 0.6, 0.4))
    # Block-Regel
    k = ez(t, b[4] - 0.1, 0.6)
    if k > 0:
        x, y = 1350, 404
        card(c, x, y, 450, 518, k, "navy")
        text(c, "Grundkurse", x + 40, y + 74, font("d", 44, 700), "ink", k)
        text(c, "nur im Block", x + 40, y + 120, font("b", 32, 500), "ink2", k)
        for blk in range(2):
            by = y + 222 + blk * 168
            tk = ezio(t, b[4] + 0.9 + blk * 1.2, 0.5)
            xa, xb = x + 110, x + 300
            dotted(c, xa, by, xb, "strong", k, 3)
            if tk > 0:
                line(c, xa, by, xa + (xb - xa) * tk, by, "navy", k, 14)
            for q, qx in enumerate((xa, xb)):
                station(c, qx, by, 20, k, "navy", clamp(pop(t, b[4] + 0.9 + blk * 1.2 + q * 0.3, 0.4), 0, 1.3))
                text(c, f"Q{blk * 2 + q + 1}", qx, by + 58, font("d", 28, 750), "navy", k, "c")
            if blk == 0:
                text(c, "oder", x + 205, by + 104, font("b", 27, 500), "ink3", k, "c")


SPORT_EX = [
    (True, "gültig", "3 Ballspiele", [("B7", "Volleyball"), ("B1", "Basketball"), ("B7", "Volleyball"), ("E1", "Schwimmen")]),
    (False, "nicht gültig", "4 Ballspiele", [("B7", "Volleyball"), ("B1", "Basketball"), ("B3", "Fußball"), ("B8", "Badminton")]),
    (False, "nicht gültig", "3-mal derselbe Kurs", [("B7", "Volleyball"), ("B7", "Volleyball"), ("B7", "Volleyball"), ("A1", "Leichtathl.")]),
]


def s_sport(c, t, sc):
    b = sc.b
    header(c, t, sc, "Schritt 5", "Sport und Zusatzkurse")
    main = 1 - ez(t, b[4] - 0.3, 0.5)
    if main > 0.003:
        c.saveLayerAlpha(None, int(255 * main))
        # Sportpraxis mit drei Beispielen
        k = ez(t, b[1] - 0.1, 0.6)
        if k > 0:
            x, y, w, h = 120, 356, 920, 610
            card(c, x, y, w, h, k, "sp")
            text(c, "Sportpraxis", x + 36, y + 66, font("d", 40, 700), "ink", k)
            text(c, "ein Kurs pro Halbjahr", x + 290, y + 64, font("b", 27, 450), "ink2", k)
            xs = [x + 418, x + 546, x + 674, x + 802]
            for q, qx in enumerate(xs):
                text(c, f"Q{q + 1}", qx, y + 126, font("d", 28, 800), "navy", k, "c")
            # Zeitpunkte: Zeile 0 im Takt der Sprache, Zeilen 1 und 2 danach als Gegenbeispiele
            pops = [[b[1] + 2.8, b[1] + 3.4, b[1] + 4.0, b[1] + 10.6], [b[1] + 12.3 + 0.15 * q for q in range(4)],
                    [b[1] + 13.9 + 0.15 * q for q in range(4)]]
            verdict_at = [b[1] + 11.4, b[1] + 13.1, b[1] + 14.7]
            for r, (ok, lab, sub, courses) in enumerate(SPORT_EX):
                ry = y + 222 + r * 142
                rk = ez(t, (b[1] + 0.4) if r == 0 else pops[r][0] - 0.3, 0.4) * k
                if rk <= 0:
                    continue
                if ok:
                    line(c, xs[0], ry, xs[3], ry, "sp", rk * 0.9, 10)
                else:
                    dotted(c, xs[0], ry, xs[3], "strong", rk, 3.5)
                for q, (code, name) in enumerate(courses):
                    pk = pop(t, pops[r][q], 0.45)
                    if pk <= 0:
                        station(c, xs[q], ry, 11, rk, "strong")
                        continue
                    a = clamp(pk) * rk
                    isball = code[0] == "B"
                    c.save()
                    c.translate(xs[q], ry)
                    c.scale(0.7 + 0.3 * pk, 0.7 + 0.3 * pk)
                    bad = (not ok) and t > verdict_at[r] and (r == 1 or code == "B7")
                    if bad:
                        rrect(c, -58, -30, 116, 60, 14, "red", a, 3.5)
                    badge(c, -50, -23, code, "sp" if isball else "gk", a, 24, h=46, w=100)
                    c.restore()
                    text(c, name, xs[q], ry + 50, font("b", 19, 550), "ink2", a, "c")
                # Ergebnis links
                vk = ez(t, verdict_at[r], 0.4) * rk
                if vk > 0:
                    cc = "green" if ok else "red"
                    circle(c, x + 58, ry - 6, 24, cc + "_soft", vk)
                    if ok:
                        check(c, x + 58, ry - 6, 24, cc, vk, 4, vk)
                    else:
                        cross(c, x + 58, ry - 6, 18, cc, vk, 4)
                    text(c, lab, x + 96, ry + 2, font("b", 28, 700), cc, vk)
                    para(c, sub, x + 96, ry + 36, font("b", 20, 450), 230, 25, "ink2", vk)
            # Klammer: Volleyball doppelt
            bk = ez(t, b[1] + 6.2, 0.5) * k
            if bk > 0:
                by = y + 222 - 46
                x1, x2 = xs[0], xs[0] + (xs[2] - xs[0]) * ezio(t, b[1] + 6.2, 0.6)
                line(c, x1, by, x2, by, "sp", bk, 3.5)
                line(c, x1, by, x1, by + 12, "sp", bk, 3.5)
                if x2 > xs[2] - 2:
                    line(c, xs[2], by, xs[2], by + 12, "sp", bk, 3.5)
                f = font("b", 21, 700)
                lab = "2-mal Volleyball"
                lw = tw(lab, f) + 24
                mx = (xs[0] + xs[2]) / 2
                rrect(c, mx - lw / 2, by - 16, lw, 32, 8, "sport_soft", bk)
                text(c, lab, mx, by + 7.5, f, "sp", bk, "c")
            text(c, "höchstens 3 Ballspiele (B)", x + w - 36, y + 64, font("b", 24, 600), "sp", ez(t, b[1] + 3.0, 0.5) * k, "r")
        # Befreiung
        k = ez(t, b[2] - 0.1, 0.6)
        if k > 0:
            x, y, w, h = 1080, 356, 720, 340
            card(c, x, y, w, h, k, "navy")
            text(c, "Vom Sport befreit?", x + 36, y + 64, font("d", 38, 700), "ink", k)
            gone = ez(t, b[2] + 1.6, 0.5)
            text(c, "Sport", x + 36, y + 136, font("b", 27, 600), "ink", k)
            text(c, "entfällt", x + 36 + tw("Sport ", font("b", 27, 600)), y + 136, font("b", 27, 600), "red", k * gone)
            qcells(c, t, b[2] + 0.3, x + 280, y + 115, [1, 1, 1, 1], "sp", cw=80, ch=40, gap=12, a=k * (1 - 0.7 * gone))
            gk = ez(t, b[2] + 5.6, 0.5)
            text(c, "+ Grundkurse", x + 36, y + 206, font("b", 27, 600), "gkline", k * gk)
            if gk > 0:
                qcells(c, t, b[2] + 5.8, x + 280, y + 185, [1, 1, 1, 1], "gkline", cw=80, ch=40, gap=12, a=k * gk)
            pk = ez(t, b[2] + 3.4, 0.5)
            text(c, "40 Kurse bleiben Pflicht", x + 36, y + 296, font("d", 40, 750), "navy", k * pk)
            check(c, x + w - 64, y + 284, 34, "green", k * pk, 5, ez(t, b[2] + 3.9, 0.5))
        # Sporttheorie
        k = ez(t, b[3] - 0.1, 0.6)
        if k > 0:
            x, y, w, h = 1080, 726, 720, 240
            card(c, x, y, w, h, k, "sp")
            text(c, "Sporttheorie", x + 36, y + 64, font("d", 38, 700), "ink", k)
            para(c, "nur bei Sport als 4. PF oder 5. PK", x + 36, y + 112, font("b", 25, 450), 300, 32, "ink2", k)
            qcells(c, t, b[3] + 1.0, x + 360, y + 150, [0, 0, 1, 1], "sp", cw=70, ch=42, gap=12, labels=True, a=k)
        c.restore()
    # Zusatzkurse
    k = ez(t, b[4] + 0.1, 0.6)
    if k > 0:
        x, y = 120, 380 + (1 - k) * 30
        card(c, x, y, 1680, 450, k, "zk")
        text(c, "Zusatzkurse", x + 44, y + 76, font("d", 46, 700), "ink", k)
        end = chips_flow(c, t, b[4] + 0.5, x + 44, y + 118, 1580,
                         ["Astronomie", "Kreatives Schreiben", "TOEFL", "Debating", "Chor", "Big Band", "Studium und Beruf",
                          "Textiles Gestalten", "Relativitätstheorie"], "zk", 28, gapx=14, gapy=14, stagger=0.12)
        r1 = ez(t, b[4] + 2.6, 0.5)
        check(c, x + 64, end + 70, 30, "green", r1, 4.5, r1)
        text(c, "zählen zu deinen Kursen, also zu den 40 Kursen und 66 Stunden", x + 104, end + 81, font("b", 31, 550), "ink", r1)
        r2 = ez(t, b[4] + 5.4, 0.5)
        circle(c, x + 64, end + 148, 17, "orange", r2, 3.5)
        text(c, "!", x + 64, end + 159, font("d", 28, 700), "orange", r2, "c")
        text(c, "ins Abitur einbringen nur mit mindestens 2 regulären Kursen im passenden Fach", x + 104, end + 159,
             font("b", 31, 550), "ink", r2)


EX = [
    ("Mathematik", "1. LK", "lk", [1, 1, 1, 1]), ("Englisch", "2. LK", "lk", [1, 1, 1, 1]),
    ("Geschichte", "3. PF", "pf", [1, 1, 1, 1]), ("Biologie", "4. PF", "pf", [1, 1, 1, 1]),
    ("Bildende Kunst", "5. PK", "pk", [1, 1, 1, 1]), ("Deutsch", "GK", "gk", [1, 1, 1, 1]),
    ("Chemie", "GK", "gk", [1, 1, 1, 1]), ("Philosophie", "GK", "gk", [1, 1, 1, 1]),
    ("Politikwiss.", "GK", "gk", [0, 0, 1, 1]), ("Kreat. Schreiben", "ZK", "zk", [1, 1, 0, 0]),
    ("Sport", "Sport", "sp", [1, 1, 1, 1]),
]
EX_HOURS = {"lk": 5, "pf": 3, "pk": 3, "gk": 3, "zk": 3, "sp": 2}


def s_umfang(c, t, sc):
    b = sc.b
    header(c, t, sc, "Nachrechnen", "Reicht das?")
    # Formel
    fa = ez(t, b[1] - 0.1, 0.6) * (1 - ez(t, b[2] - 0.4, 0.5))
    if fa > 0:
        y = 470
        parts = [("(", "ink2"), ("LK", "lk"), (" × 5  +  ", "ink2"), ("GK", "gkline"), (" × 3  +  ", "ink2"), ("Sport", "sp"),
                 (" × 2", "ink2"), (")  ÷  2", "ink2")]
        f = font("d", 58, 600)
        x = 120
        for i, (s, cc) in enumerate(parts):
            k = ez(t, b[1] + 0.2 + i * 0.35, 0.4)
            text(c, s, x, y, f, cc, fa * k)
            x += tw(s, f)
        text(c, "= Jahreswochenstunden", 120, 550, font("b", 36, 500), "ink2", fa * ez(t, b[1] + 3.6, 0.5))
        cx = 120
        for i, (lb, kind) in enumerate([("LK = 5 Std.", "lk"), ("GK = 3 Std.", "gk"), ("Sport = 2 Std.", "sp")]):
            k = ez(t, b[1] + 0.6 + i * 1.0, 0.4)
            cx += badge(c, cx, 610, lb, kind, fa * k, 28, h=52) + 18
    # Netzplan der Beispielwahl
    starts = [b[2] + 0.6 + i * 1.3 if i < 5 else b[3] + 0.3 + (i - 5) * 1.25 for i in range(len(EX))]
    ra = ez(t, b[2] - 0.2, 0.5)
    if ra > 0:
        x, y, w, h = 100, 330, 1080, 662
        card(c, x, y, w, h, ra)
        rrect(c, x, y, w, h, 14, "mapline", ra, 1.5)
        map_head(c, x + 36, y + 58, w - 72, ra, ez(t, starts[-1] + 1.4, 0.6), size=34)
        xs = [x + 560, x + 690, x + 820, x + 950]
        zone_bands(c, xs, y + 84, y + h - 14, ra, half=54)
        netz(c, t, EX, starts, x + 36, xs, y + 150, 44, ra, s=0.88)
    count, hours = 0.0, 0.0
    for i, (name, lab, kind, cells) in enumerate(EX):
        for qi in range(4):
            if cells[qi]:
                kk = ez(t, starts[i] + 0.35 + qi * 0.12, 0.35)
                count += kk
                hours += kk * EX_HOURS[kind] / 2
    # Messwerte
    ma = ez(t, b[0] + 0.3, 0.6)
    if ma > 0:
        x, w = 1240, 560
        meter(c, t, x, 380, w, "Kurse", count, 40, "navy", ma)
        meter(c, t, x, 560, w, "Jahreswochenstunden", hours, 66, "navy", ma)
        fk = ez(t, b[3] + 8.5, 0.6) * ra
        text(c, "pro Halbjahr: 10 Kurse,", x, 922, font("b", 28, 600), "ink2", fk)
        text(c, "33 Wochenstunden", x, 960, font("b", 28, 600), "ink2", fk)
        k3 = ez(t, b[4] - 0.1, 0.6)
        if k3 > 0:
            v = 19 * ez(t, b[4] + 1.6, 1.4)
            text(c, "Einbringpflichtige Grundkurse", x, 740, font("b", 28, 500), "ink2", k3)
            text(c, "höchstens", x + w, 740, font("b", 24, 450), "ink3", k3, "r")
            text(c, "/ 24", x + w, 812, font("d", 36, 500), "ink3", k3, "r")
            text(c, f"{v:.0f}", x + w - tw("/ 24", font("d", 36, 500)) - 14, 812, font("d", 76, 700), "green" if v > 18.5 else "ink", k3, "r")
            rrect(c, x, 836, w, 14, 7, "line", k3)
            if v > 0:
                rrect(c, x, 836, max(14, w * v / 24), 14, 7, "green", k3)
            rrect(c, x + w - 3, 828, 3, 30, 1.5, "orange", k3)


def s_abgabe(c, t, sc):
    b = sc.b
    outro = ez(t, b[4] - 0.3, 0.7)
    main = 1 - outro
    if main > 0.003:
        c.saveLayerAlpha(None, int(255 * main))
        header(c, t, sc, "Zum Schluss", "Prüfen, beraten, abgeben")
        steps = [("Prüfen", magnifier), ("Beraten", None), ("Abgeben", None)]
        active = 0 if t < b[2] else (1 if t < b[2] + 3.2 else 2)
        for i, (lab, fn) in enumerate(steps):
            k = pop(t, b[0] + 0.3 + i * 0.35, 0.5)
            if k <= 0:
                continue
            x, y = 1250 + i * 190, 250
            act = (i == active) and t > b[1] - 0.2
            circle(c, x, y, 62 * clamp(k), "blue_soft" if act else "card", 1)
            circle(c, x, y, 62 * clamp(k), "navy" if act else "strong", 1, 5 if act else 3.5)
            if i == 0:
                magnifier(c, x, y, 66, "ink", clamp(k))
            elif i == 1:
                bubble(c, x - 12, y - 10, 50, "ink", clamp(k))
                bubble(c, x + 16, y + 14, 40, "ink2", clamp(k), True)
            else:
                lock(c, x, y + 8, 56, "ink", clamp(k), 0)
            text(c, lab, x, y + 104, font("b", 27, 600), "ink" if act else "ink2", clamp(k), "c")
            if i < 2:
                line(c, x + 70, y, x + 120, y, "navy", clamp(k), 10)
        # Planer-Prüfung
        k = ez(t, b[1] - 0.1, 0.6)
        if k > 0:
            x, y = 120, 400
            card(c, x, y, 980, 540, k)
            text(c, "Prüfung deiner Wahl", x + 40, y + 66, font("d", 40, 700), "ink", k)
            items = [("red", "Referenzfach der 5. PK fehlt", "behoben", 1.4), ("orange", "Sportkurs doppelt gewählt", "mit dem Päko besprechen", 3.4),
                     ("green", "Mindestens 40 Kurse belegt", "", 0.6)]
            for j, (cc, msg, res, fix_at) in enumerate(items):
                kk = ez(t, b[1] + 0.4 + j * 0.4, 0.4)
                ry = y + 110 + j * 112
                fixed = cc == "red" and t > b[1] + fix_at + 2.6
                c_now = "green" if fixed else cc
                rrect(c, x + 36, ry, 908, 92, 14, c_now + "_soft", k * kk)
                rrect(c, x + 36, ry, 8, 92, 4, c_now, k * kk)
                text(c, msg, x + 72, ry + 42, font("b", 30, 600), "ink", k * kk)
                lab = {"red": "muss behoben werden", "orange": "Hinweis", "green": "erfüllt"}[cc]
                if fixed:
                    lab = res
                elif cc == "orange" and t > b[1] + fix_at + 1.0:
                    lab = res
                text(c, lab, x + 72, ry + 76, font("b", 24, 450), c_now, k * kk)
            fy = y + 470
            text(c, "Kurse 40/40   ·   Wochenstunden 66/66   ·   Einbringen 19/24", x + 40, fy + 20, font("m", 24), "ink2", k * ez(t, b[1] + 1.2, 0.5))
        # Abgeben
        k = ez(t, b[2] + 1.6, 0.5)
        if k > 0:
            x, y = 1160, 470
            press = ez(t, b[2] + 3.3, 0.18) * (1 - ez(t, b[2] + 3.5, 0.2))
            done = t > b[2] + 3.5
            rrect(c, x, y + press * 6, 640, 104, 52, "pk" if not done else "card2", k)
            if done:
                lock(c, x + 70, y + 58, 44, "pk", k, 0)
                text(c, "Wahl abgegeben, gesperrt", x + 120, y + 64, font("b", 32, 650), "pk", k)
            else:
                text(c, "Wahl abgeben", x + 320, y + 64 + press * 6, font("b", 36, 700), "white", k, "c")
        # Formular
        k = ez(t, b[3] - 0.1, 0.6)
        if k > 0:
            x, y = 1160, 620
            card(c, x, y, 640, 320, k, "blue")
            doc_icon(c, x + 92, y + 110, 96, "ink", k)
            text(c, "Kurswahlformular", x + 170, y + 92, font("b", 32, 650), "ink", k)
            text(c, "drucken in 100 %", x + 170, y + 136, font("b", 28, 450), "ink2", k)
            cx = x + 40
            for i, lb in enumerate(["rechtzeitig abgeben", "Lernraum"]):
                kk = ez(t, b[3] + 2.0 + i * 1.8, 0.4)
                cx += chip(c, cx, y + 210, lb, "blue", k * kk, 27) + 14
        c.restore()
    if outro > 0:
        k = outro
        text(c, "Viel Erfolg", 960, 470 + (1 - k) * 30, font("d", 150, 700), "ink", k, "c")
        text(c, "bei deiner Kurswahl!", 960, 560 + (1 - k) * 30, font("d", 64, 600), "blue", k, "c")
        k2 = ez(t, b[4] + 1.2, 0.6)
        text(c, "Alle Regeln zum Nachlesen: im Kurswahl-Leitfaden", 960, 690, font("b", 34, 500), "ink2", k2, "c")
        k3 = ez(t, b[4] + 2.2, 0.6)
        text(c, "Inoffiziell. Verbindlich sind die Vorgaben der Schule und die Beratung durch den Päko.", 960, 790,
             font("b", 26, 450), "ink3", k3, "c")
        text(c, "Grundlage: Regeln des Kurswahl-Planers (VO-GO Berlin §§ 23, 25, 26)", 960, 832, font("b", 24, 450), "ink3", k3, "c")


DRAW = dict(intro=s_intro, ausgangslage=s_ausgangslage, lk=s_lk, pruefung=s_pruefung, pflichten=s_pflichten, sport=s_sport,
            umfang=s_umfang, abgabe=s_abgabe)


def frame(c, t):
    background(c, t)
    hud(c, t)
    for sc in SCENES:
        if sc.start <= t < sc.end:
            fin = ez(t, sc.start, 0.45)
            last = sc is SCENES[-1]
            fout = 1 - ez(t, sc.end - (1.6 if last else 0.45), 1.2 if last else 0.45)
            a = fin * fout
            if a <= 0.003:
                continue
            c.saveLayerAlpha(None, int(255 * a))
            DRAW[sc.id](c, t, sc)
            c.restore()


# ------------------------------------------------------------------ Ton
def mix_audio():
    n = int(TOTAL * SR) + SR
    voice = np.zeros(n, np.float32)
    for sc in SCENES:
        x, sr = sf.read(HERE / "voice" / f"{sc.id}.wav", dtype="float32")
        if x.ndim > 1:
            x = x.mean(axis=1)
        if sr != SR:
            g = math.gcd(sr, SR)
            x = resample_poly(x, SR // g, sr // g).astype(np.float32)
        i = int(sc.audio_at * SR)
        voice[i:i + len(x)] += x[: n - i]
    pk = np.max(np.abs(voice)) + 1e-9
    voice *= 0.89 / pk  # ca. -1 dBFS
    music = np.zeros(n, np.float32)
    mp = HERE / "music" / "loop.ogg"
    if mp.exists():
        m, msr = sf.read(mp, dtype="float32")
        if m.ndim > 1:
            m = m.mean(axis=1)
        if msr != SR:
            g = math.gcd(msr, SR)
            m = resample_poly(m, SR // g, msr // g).astype(np.float32)
        reps = n // len(m) + 1
        music = np.tile(m, reps)[:n]
        # Ducking: Hüllkurve der Stimme
        env = np.abs(voice)
        win = int(0.35 * SR)
        env = np.convolve(env, np.ones(win, np.float32) / win, mode="same")
        duck = np.where(env > 0.01, 1.0, 0.0).astype(np.float32)
        duck = np.convolve(duck, np.ones(int(0.6 * SR), np.float32) / int(0.6 * SR), mode="same")
        gain = 10 ** ((-23 + (-7) * duck) / 20)  # -23 dB in Pausen, -30 dB unter Sprache
        rms = np.sqrt(np.mean(m ** 2)) + 1e-9
        music = music / rms * 0.25 * gain
        t = np.arange(n) / SR
        fade = np.clip(t / 2.5, 0, 1) * np.clip((TOTAL - t) / 4.0, 0, 1)
        music *= fade
    out = voice + music
    out = np.clip(out, -0.98, 0.98)
    out = out[: int(TOTAL * SR)]
    OUT.mkdir(exist_ok=True)
    sf.write(OUT / "mix.wav", np.stack([out, out], axis=1), SR, subtype="PCM_16")
    return OUT / "mix.wav"


def srt():
    def ts(x):
        h, r = divmod(x, 3600)
        m, s = divmod(r, 60)
        return f"{int(h):02d}:{int(m):02d}:{int(s):02d},{int(round((s - int(s)) * 1000)):03d}"

    import re
    out, n = [], 1
    for sc, s in zip(SCENES, SCRIPT["scenes"]):
        info = BEATS[sc.id]
        ends = sc.b[1:] + [sc.audio_end]
        for bt, (t0, t1) in enumerate(zip(sc.b, ends)):
            txt = s["beats"][bt].replace("Q eins", "Q1").replace("Q zwei", "Q2").replace("Q drei", "Q3").replace("Q vier", "Q4").replace("sechsundzwanzig siebenundzwanzig", "2026/27")
            sents = [x for x in re.split(r"(?<=[.!?])\s+", txt) if x]
            tot = sum(len(x) for x in sents)
            cur = t0 + 0.05
            span = (t1 - 0.25) - cur
            for st in sents:
                d = span * len(st) / tot
                out.append(f"{n}\n{ts(cur)} --> {ts(cur + d - 0.05)}\n{st}\n")
                n += 1
                cur += d
    p = OUT / "Kurswahl-Video.srt"
    p.write_text("\n".join(out), encoding="utf-8")
    return p


# ------------------------------------------------------------------ Ausgabe
def stills(times):
    d = OUT / "stills"
    d.mkdir(parents=True, exist_ok=True)
    surf = skia.Surface(W, H)
    for tt in times:
        c = surf.getCanvas()
        frame(c, tt)
        surf.makeImageSnapshot().save(str(d / f"t{tt:07.2f}.png"), skia.kPNG)
        print("still", tt)


def video():
    OUT.mkdir(exist_ok=True)
    mix = mix_audio()
    sub = srt()
    nfr = int(TOTAL * FPS)
    raw = OUT / "_video_noaudio.mp4"
    cmd = ["ffmpeg", "-y", "-loglevel", "error", "-f", "rawvideo", "-pix_fmt", "rgba", "-s", f"{W}x{H}", "-r", str(FPS), "-i", "-",
           "-c:v", "libx264", "-preset", "medium", "-crf", "17", "-pix_fmt", "yuv420p", "-tune", "animation", str(raw)]
    proc = subprocess.Popen(cmd, stdin=subprocess.PIPE)
    surf = skia.Surface(W, H)
    buf = np.empty((H, W, 4), np.uint8)
    info = skia.ImageInfo.Make(W, H, skia.kRGBA_8888_ColorType, skia.kPremul_AlphaType)
    import time
    t0 = time.time()
    for i in range(nfr):
        c = surf.getCanvas()
        frame(c, i / FPS)
        surf.readPixels(info, buf, W * 4, 0, 0)
        proc.stdin.write(buf.tobytes())
        if i % 300 == 0:
            el = time.time() - t0
            print(f"{i}/{nfr}  {el:.0f}s", flush=True)
    proc.stdin.close()
    proc.wait()
    final = OUT / "Kurswahl-Video.mp4"
    subprocess.run(["ffmpeg", "-y", "-loglevel", "error", "-i", str(raw), "-i", str(mix), "-i", str(sub),
                    "-map", "0:v", "-map", "1:a", "-map", "2:s", "-c:v", "copy", "-c:a", "aac", "-b:a", "192k", "-c:s", "mov_text",
                    "-metadata:s:s:0", "language=ger", "-metadata", "title=Kurswahl Q1–Q4",
                    "-movflags", "+faststart", "-t", f"{TOTAL:.3f}", str(final)], check=True)
    raw.unlink(missing_ok=True)
    print("fertig", final, f"{TOTAL:.1f}s")


if __name__ == "__main__":
    if sys.argv[1] == "stills":
        stills([float(x) for x in sys.argv[2:]])
    elif sys.argv[1] == "timeline":
        for sc in SCENES:
            print(f"{sc.id:13s} {sc.start:7.2f}-{sc.end:7.2f}  Abschnitte {[round(x, 1) for x in sc.b]}")
        print("Gesamt", round(TOTAL, 1))
    else:
        video()
