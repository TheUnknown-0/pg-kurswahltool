"""Sprechtexte aus script.json mit Gemini TTS erzeugen und Abschnittsgrenzen bestimmen.

Aufruf:  python tts.py gen [szene ...]   erzeugt fehlende (oder genannte) Szenen
         python tts.py beats              bestimmt die Abschnittsstarts aus den Pausen

Ein Aufruf pro Szene (Gratis-Kontingent: 10 Aufrufe pro Modell und Tag). Der Schlüssel
bleibt im GeminiTTS-Modul und wird nie ausgegeben.
"""
import io
import json
import os
import sys
from pathlib import Path

import numpy as np
import soundfile as sf

HERE = Path(__file__).resolve().parent
# Ordner mit dem Modul gemini_tts.py (nicht Teil dieses Repos), per Umgebungsvariable
COMMON = Path(os.environ.get("GEMINI_TTS_DIR", "."))
VOICE = HERE / "voice"
MODEL = "gemini-3.8-flash-tts"


def load_script():
    return json.loads((HERE / "script.json").read_text(encoding="utf-8"))


def gen(only):
    sys.path.insert(0, str(COMMON))
    from gemini_tts import GeminiTTS, QuotaExhausted  # noqa: E402

    sc = load_script()
    VOICE.mkdir(exist_ok=True)
    g = GeminiTTS(counter_file=VOICE / "tts_stats.json")
    for s in sc["scenes"]:
        out = VOICE / f"{s['id']}.wav"
        if only and s["id"] not in only:
            continue
        if out.exists() and not only:
            print(f"{s['id']}: schon da")
            continue
        text = " <long pause> ".join(s["beats"])
        print(f"{s['id']}: {len(text)} Zeichen ...", flush=True)
        try:
            _, _, _, raw = g.say_v3(text, sc["voice"], sc["style"], MODEL)
        except QuotaExhausted as e:
            print("Kontingent erschöpft:", e)
            return
        data, sr = sf.read(io.BytesIO(raw), dtype="float32")
        if data.ndim > 1:
            data = data.mean(axis=1)
        sf.write(out, data, sr, subtype="PCM_16")
        print(f"{s['id']}: {len(data) / sr:.1f} s", flush=True)


def silences(x, sr, frame=0.02, rel_db=-38.0, min_len=0.22):
    n = int(sr * frame)
    m = len(x) // n
    rms = np.sqrt(np.mean(x[: m * n].reshape(m, n) ** 2, axis=1) + 1e-12)
    db = 20 * np.log10(rms)
    thr = np.percentile(db, 95) + rel_db
    quiet = db < thr
    runs, i = [], 0
    while i < m:
        if quiet[i]:
            j = i
            while j < m and quiet[j]:
                j += 1
            if (j - i) * frame >= min_len:
                runs.append((i * frame, j * frame))
            i = j
        else:
            i += 1
    return runs


def beats():
    sc = load_script()
    res = {}
    for s in sc["scenes"]:
        p = VOICE / f"{s['id']}.wav"
        if not p.exists():
            continue
        x, sr = sf.read(p, dtype="float32")
        dur = len(x) / sr
        runs = silences(x, sr)
        head = runs[0][1] if runs and runs[0][0] == 0 else 0.0
        tail = runs[-1][0] if runs and runs[-1][1] >= dur - 0.05 else dur
        inner = [r for r in runs if r[0] > head + 0.05 and r[1] < tail - 0.05]
        k = len(s["beats"]) - 1
        # Erwartete Grenzen nach Zeichenanteil, um falsche Treffer zu erkennen
        lens = [len(b) for b in s["beats"]]
        cum = np.cumsum(lens)[:-1] / sum(lens)
        expect = [head + c * (tail - head) for c in cum]
        # Beste geordnete Auswahl: lange Pause nahe der erwarteten Stelle (0,15 Punkte Abzug je Sekunde Abstand)
        from itertools import combinations
        best, picked = -1e9, []
        for combo in combinations(inner, k):
            sc_ = sum((r[1] - r[0]) - 0.15 * abs((r[0] + r[1]) / 2 - e) for r, e in zip(combo, expect))
            if sc_ > best:
                best, picked = sc_, list(combo)
        starts = [head] + [r[1] - 0.08 for r in picked]
        dev = [abs(st - e) for st, e in zip(starts[1:], expect)]
        gaps = [round(r[1] - r[0], 2) for r in picked]
        ok = len(picked) == k and all(d < 0.18 * (tail - head) for d in dev)
        res[s["id"]] = dict(dur=round(dur, 3), head=round(head, 3), tail=round(tail, 3),
                            starts=[round(v, 3) for v in starts], gaps=gaps, ok=ok)
        print(f"{s['id']:13s} {dur:6.1f}s  Starts {[round(v, 1) for v in starts]}  Pausen {gaps}  erwartet {[round(v, 1) for v in expect]}  {'ok' if ok else 'PRÜFEN'}")
    (VOICE / "beats.json").write_text(json.dumps(res, indent=1), encoding="utf-8")


if __name__ == "__main__":
    cmd = sys.argv[1] if len(sys.argv) > 1 else "gen"
    if cmd == "gen":
        gen(set(sys.argv[2:]))
    else:
        beats()
