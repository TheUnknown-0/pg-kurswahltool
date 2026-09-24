#!/usr/bin/env python3
"""Erzeugt die eingebettete Formularvorlage des Kurswahl-Planers aus einer Kurswahl-PDF der Schule.

Aufruf:  python3 tools/build_form_template.py <Kurswahl-PDF der Schule> [kurswahl-planer.html]

Die PDF wird in unveränderliche Objekte (byte-genau übernommen) und veränderliche Objekte zerlegt:
Seiteninhalt mit Jahrgang/Name/Klasse, die drei Schrift-Teilmengen samt Breiten und ToUnicode,
die versteckten Felder SchuelerId/AbiturJahrgangId, Datum/Metadaten und die Kreuzfelder.
Persönliche Daten der Vorlage (Name, Klasse, IDs, Datum) werden nicht übernommen: Die veränderlichen
Stellen werden durch Platzhalter ersetzt, von den Schriften werden nur Tabellen und Glyphen gespeichert.

Das Ergebnis wird zwischen den Markierungen FORM-TEMPLATE-BEGIN/END in die HTML-Datei geschrieben,
zusammen mit dem zlib-ng-WebAssembly aus tools/zlib-ng-wasm/zng.wasm.
"""
import base64, json, re, struct, sys, zlib
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent


def die(msg):
    sys.exit('Fehler: ' + msg)


class Pdf:
    def __init__(self, data):
        self.b = data
        first = re.search(rb'\r\n(\d+) 0 obj\r\n', data)
        self.header = data[:first.start() + 2]
        self.xref_pos = data.rindex(b'\r\nxref\r\n') + 2
        self.order, self.raw = [], {}
        pos = len(self.header)
        while pos < self.xref_pos:
            m = re.compile(rb'(\d+) 0 obj\r\n').match(data, pos)
            if not m:
                die(f'Objekt an Position {pos} nicht erkannt')
            end = data.index(b'endobj\r\n', self.stream_end(m.end())) + 8
            n = int(m.group(1))
            self.order.append(n)
            self.raw[n] = data[pos:end]
            pos = end
        if pos != self.xref_pos:
            die('unerwartete Daten vor der xref-Tabelle')
        xref = data[self.xref_pos:]
        m = re.match(rb'xref\r\n0 (\d+)\r\n', xref)
        self.size = int(m.group(1))
        lines = xref[m.end():m.end() + 20 * self.size]
        self.entries = [lines[i * 20:i * 20 + 18] for i in range(self.size)]
        self.trailer = xref[m.end() + 20 * self.size:]

    def stream_end(self, pos):
        """Überspringt einen Stream, damit 'endobj' in Binärdaten nicht stört."""
        d = self.b
        e = d.index(b'endobj', pos)
        s = d.find(b'>>\r\nstream\r\n', pos, e)
        if s < 0:
            return pos
        ln = int(re.search(rb'/Length (\d+)', d[pos:s]).group(1))
        return s + 12 + ln

    def dict_of(self, n):
        r = self.raw[n]
        i = r.find(b'>>\r\nstream\r\n')
        return (r if i < 0 else r[:i + 2]).decode('latin1')

    def stream(self, n, raw=False):
        r = self.raw[n]
        i = r.index(b'>>\r\nstream\r\n') + 12
        ln = int(re.search(rb'/Length (\d+)', r[:i]).group(1))
        s = r[i:i + ln]
        if raw or b'/FlateDecode' not in r[:i]:
            return s
        if s[:2] != b'\x58\x85':
            die(f'Objekt {n}: unbekannter zlib-Kopf {s[:2].hex()}')
        return zlib.decompress(s[2:], -15)

    def ref(self, n, key):
        m = re.search(r'/' + key + r' (\d+) 0 R', self.dict_of(n))
        return int(m.group(1)) if m else None


def hexstr(s):
    return 'FEFF' + ''.join(f'{ord(c):04X}' for c in s)


def unhex_name(h):
    b = bytes.fromhex(h)
    return b[2:].decode('utf-16-be') if b[:2] == b'\xfe\xff' else b.decode('latin1')


def main():
    if len(sys.argv) < 2:
        die(__doc__)
    pdf = Pdf(Path(sys.argv[1]).read_bytes())
    html_path = Path(sys.argv[2]) if len(sys.argv) > 2 else ROOT / 'kurswahl-planer.html'
    wasm = (ROOT / 'tools/zlib-ng-wasm/zng.wasm').read_bytes()

    # Kompressor prüfen: jeder Flate-Stream der Vorlage muss sich mit zlib-ng Level 6 reproduzieren lassen.
    # (Die Prüfung selbst läuft im Test tools/test_form.js mit der WebAssembly-Fassung.)

    trailer = pdf.trailer.decode('latin1')
    root = int(re.search(r'/Root (\d+) 0 R', trailer).group(1))
    info = int(re.search(r'/Info (\d+) 0 R', trailer).group(1))
    pages = pdf.ref(root, 'Pages')
    meta = pdf.ref(root, 'Metadata')
    kids = [int(k) for k in re.findall(r'(\d+) 0 R', re.search(r'/Kids \[([^\]]*)\]', pdf.dict_of(pages)).group(1))]
    if len(kids) != 2:
        die('Vorlage muss zwei Seiten haben')

    # Schriften: Type0 → CIDFont (W) → FontDescriptor → FontFile2, dazu ToUnicode
    fonts = {}
    for n in pdf.order:
        d = pdf.dict_of(n)
        m = re.search(r'/Subtype /Type0 /BaseFont /DEVEXP#2B(\S+)', d)
        if not m:
            continue
        key = {'Arial': 'reg', 'Arial#2CBold': 'bold', 'TimesNewRoman': 'times'}.get(m.group(1))
        if not key:
            die('unbekannte Schrift ' + m.group(1))
        cid = int(re.search(r'/DescendantFonts \[(\d+) 0 R\]', d).group(1))
        desc = pdf.ref(cid, 'FontDescriptor')
        fonts[key] = dict(type0=n, tounicode=pdf.ref(n, 'ToUnicode'), cid=cid, file=pdf.ref(desc, 'FontFile2'))
    if set(fonts) != {'reg', 'bold', 'times'}:
        die('Schriften nicht vollständig gefunden: ' + ', '.join(fonts))

    def page_fonts(page):
        res = pdf.ref(page, 'Resources')
        d = pdf.dict_of(res)
        by_obj = {v['type0']: k for k, v in fonts.items()}
        return {name: by_obj[int(o)] for name, o in re.findall(r'/(FNT\d+) (\d+) 0 R', d)}

    contents = [pdf.ref(p, 'Contents') for p in kids]
    content0 = pdf.stream(contents[0]).decode('latin1')
    content1 = pdf.stream(contents[1]).decode('latin1')
    fmap = [page_fonts(p) for p in kids]
    bold_name = [k for k, v in fmap[0].items() if v == 'bold'][0]
    reg_name = [k for k, v in fmap[0].items() if v == 'reg'][0]

    # Platzhalter im Seiteninhalt
    def gid_hex(font_key, text):
        cmap = font_cmaps[font_key]
        return ''.join(f'{cmap[ord(c)]:04X}' for c in text)

    font_cmaps, font_libs = {}, {}
    for key, f in fonts.items():
        tu = pdf.stream(f['tounicode']).decode('latin1')
        pairs = re.findall(r'<([0-9A-F]{4})> <([0-9A-F]{4})>', tu.split('beginbfchar', 1)[1])
        font_cmaps[key] = {int(u, 16): int(g, 16) for g, u in pairs}

    m = re.search(r'(/' + bold_name + r' 12 Tf [\d.]+ [\d.]+ Td <)' + gid_hex('bold', 'Name: ') + r'([0-9A-F]*)(> Tj)', content0)
    if not m:
        die('Name im Seiteninhalt nicht gefunden')
    content0 = content0[:m.start(2)] + '\x00NAME\x00' + content0[m.end(2):]
    m = re.search(r'(/' + bold_name + r' 12 Tf [\d.]+ [\d.]+ Td <)' + gid_hex('bold', 'Klasse: ') + r'([0-9A-F]*)(> Tj)', content0)
    if not m:
        die('Klasse im Seiteninhalt nicht gefunden')
    content0 = content0[:m.start(2)] + '\x00KLASSE\x00' + content0[m.end(2):]
    jg = '<' + gid_hex('reg', 'Ja')
    i = content0.index('[ ' + jg)
    j = content0.index('] TJ', i)
    tj = content0[i + 2:j]
    content0 = content0[:i] + '\x00JAHRGANG\x00' + content0[j + 1:]

    # Abstände (TJ-Korrekturen) je Zeichen aus der Jahrgangszeile ablesen
    inv = {g: u for u, g in font_cmaps['reg'].items()}
    toks = re.findall(r'<([0-9A-F]+)>|(-?[\d.]+)', tj)
    seq = []  # [Zeichen, Korrektur danach]
    for h, num in toks:
        if h:
            seq += [[chr(inv[int(h[k:k + 4], 16)]), '0'] for k in range(0, len(h), 4)]
        else:
            seq[-1][1] = num
    text = ''.join(c for c, _ in seq)
    if not text.startswith('Jahrgang: ') or not text.endswith(', GYM_SEK_II'):
        die('Jahrgangszeile unerwartet: ' + text)
    tj_adjust = {}
    for c, a in seq[:-1]:  # nach dem letzten Zeichen steht nie eine Korrektur
        if tj_adjust.setdefault(c, a) != a:
            die(f'Zeichen {c!r} hat unterschiedliche Abstände')
    # Alle Ziffern sind in Arial gleich breit; fehlende Ziffern übernehmen den Wert der vorhandenen
    known = {tj_adjust[c] for c in '0123456789' if c in tj_adjust}
    if len(known) != 1:
        die('Ziffern haben unterschiedliche Abstände')
    tj_adjust.update({c: next(iter(known)) for c in '0123456789'})

    # Schriftbibliothek: Tabellen und vorhandene Glyphen der Teilmengen
    blob = bytearray()

    def put(data):
        off = len(blob)
        blob.extend(data)
        return [off, len(data)]

    for key, f in fonts.items():
        ttf = pdf.stream(f['file'])
        num = struct.unpack('>H', ttf[4:6])[0]
        tables, dirs = {}, {}
        for k in range(num):
            tag, cs, off, ln = struct.unpack('>4sIII', ttf[12 + 16 * k:28 + 16 * k])
            tag = tag.decode('latin1')
            tables[tag] = ttf[off:off + ln]
            dirs[tag] = cs
        if sorted(tables) != ['cvt ', 'fpgm', 'glyf', 'head', 'hhea', 'hmtx', 'loca', 'maxp', 'prep']:
            die('unerwartete Tabellen in Schrift ' + key)
        head = tables['head']
        if struct.unpack('>h', head[50:52])[0] != 1:
            die('loca-Format erwartet: lang')
        loca = struct.unpack('>%dI' % (len(tables['loca']) // 4), tables['loca'])
        # auch leere Glyphen (Leerzeichen) aufnehmen, sofern sie im Text vorkommen
        used = set(font_cmaps[key].values())
        glyphs = {g: put(tables['glyf'][loca[g]:loca[g + 1]]) for g in range(len(loca) - 1) if loca[g + 1] > loca[g] or g in used}
        font_libs[key] = dict(
            tables={t: put(tables[t]) for t in ('cvt ', 'fpgm', 'head', 'hhea', 'hmtx', 'maxp', 'prep')},
            checksums={t: dirs[t] for t in ('cvt ', 'fpgm', 'head', 'hhea', 'hmtx', 'maxp', 'prep')},
            glyphs=glyphs,
            cmap=font_cmaps[key])

    # Veränderliche Objekte
    ids = {}
    for n in pdf.order:
        d = pdf.dict_of(n)
        m = re.search(r'/FT /Tx /T <([0-9A-F]+)>', d)
        if m:
            ids[unhex_name(m.group(1))] = n
    if set(ids) != {'SchuelerId', 'AbiturJahrgangId'}:
        die('ID-Felder nicht gefunden')
    id_ap = {k: pdf.ref(pdf.ref(n, 'AP'), 'N') for k, n in ids.items()}
    ap_text = {k: pdf.stream(n).decode('latin1') for k, n in id_ap.items()}
    ap_tpl = {}
    for k, s in ap_text.items():
        m = re.search(r'<([0-9A-F]*)> Tj', s)
        ap_tpl[k] = s[:m.start(1)] + '\x00TEXT\x00' + s[m.end(1):]
    if len(set(ap_tpl.values())) != 1:
        die('ID-Felder haben unterschiedliche Darstellung')

    checkboxes, radio = {}, None
    for n in pdf.order:
        d = pdf.dict_of(n)
        m = re.search(r'/T <([0-9A-F]+)>', d) if '/FT /Btn' in d else None
        if m and '/Kids' not in d:
            checkboxes[unhex_name(m.group(1))] = n
        elif m:
            radio = dict(parent=n, name=unhex_name(m.group(1)))
    radio['kids'] = {}
    for n in pdf.order:
        d = pdf.dict_of(n)
        if f'/Parent {radio["parent"]} 0 R' in d:
            ap = pdf.dict_of(pdf.ref(n, 'AP'))
            on = [x for x in re.findall(r'/(\S+) \d+ 0 R', ap.split('/N', 1)[1].split('>>', 1)[0]) if x != 'Off']
            radio['kids'][on[0].replace('#5F', '_')] = n

    dynamic = {contents[0], info, meta, radio['parent'], *radio['kids'].values(), *checkboxes.values(), *ids.values(), *id_ap.values()}
    for f in fonts.values():
        dynamic |= {f['tounicode'], f['cid'], f['file']}

    def template(n):
        """Objekt mit Platzhaltern statt persönlicher Daten."""
        r = pdf.raw[n].decode('latin1')
        if n in (contents[0], *id_ap.values()) or n in [f['tounicode'] for f in fonts.values()] or n in [f['file'] for f in fonts.values()]:
            i = r.index('>>\r\nstream\r\n')
            head = re.sub(r'/Length1 \d+', '/Length1 \x00LEN1\x00', r[:i + 2])
            return re.sub(r'/Length \d+', '/Length \x00LEN\x00', head)
        if n in [f['cid'] for f in fonts.values()]:
            return re.sub(r'/W \[.*\] /CIDToGIDMap', '/W [\x00W\x00] /CIDToGIDMap', r, flags=re.S)
        if n in ids.values():
            return re.sub(r'/V <[0-9A-F]*>', '/V <\x00V\x00>', r)
        if n == info:
            return re.sub(r'/(CreationDate|ModDate) <[0-9A-F]+>', lambda m: f'/{m.group(1)} <\x00DATE\x00>', r)
        if n == meta:
            return re.sub(r'\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d[+-]\d\d:\d\d', '\x00DATE\x00', r)
        if n in checkboxes.values() or n in radio['kids'].values() or n == radio['parent']:
            return r
        die(f'kein Platzhalter für Objekt {n}')

    # Alle Objekte liegen in Dateireihenfolge hintereinander im Datenblock; veränderliche als Vorlage mit Platzhaltern
    objs, obj_start = [], len(blob)
    for n in pdf.order:
        data = template(n).encode('latin1') if n in dynamic else pdf.raw[n]
        put(data)
        objs.append([n, len(data)])

    free = {i: e.decode('latin1') for i, e in enumerate(pdf.entries) if e.endswith(b' f')}
    trailer_tpl = re.sub(r'/ID \[<[0-9A-F]+> <[0-9A-F]+>\]', '/ID [<\x00ID\x00> <\x00ID\x00>]', trailer.split('startxref')[0])

    tu_text = pdf.stream(fonts['reg']['tounicode']).decode('latin1')
    a, b = tu_text.index('beginbfchar'), tu_text.index('endbfchar')
    tounicode_tpl = tu_text[:tu_text.rindex('\r\n', 0, a) + 2] + '\x00BFCHAR\x00' + tu_text[b + 9:]
    for f in fonts.values():
        t = pdf.stream(f['tounicode']).decode('latin1')
        body = t[t.rindex('\r\n', 0, t.index('beginbfchar')) + 2:t.index('endbfchar') + 9]
        if tounicode_tpl.replace('\x00BFCHAR\x00', body) != t:
            die('ToUnicode-Aufbau unterscheidet sich zwischen den Schriften')

    tpl = dict(
        header=put(pdf.header),
        size=pdf.size,
        objs=objs,
        objStart=obj_start,
        dynamic=sorted(dynamic),
        free=free,
        trailer=trailer_tpl,
        page0=put(content0.encode('latin1')),
        page1=put(content1.encode('latin1')),
        pageFonts=fmap,
        tjAdjust=tj_adjust,
        tounicode=tounicode_tpl,
        apTemplate=ap_tpl['SchuelerId'],
        fonts={k: dict(obj=v, **font_libs[k]) for k, v in fonts.items()},
        ids={k: dict(field=ids[k], ap=id_ap[k]) for k in ids},
        info=info, meta=meta, contents0=contents[0],
        checkboxes=checkboxes,
        radio=radio,
    )

    tpl['blobSize'] = len(blob)
    packed = zlib.compressobj(9, zlib.DEFLATED, -15)
    packed = packed.compress(bytes(blob)) + packed.flush()
    js = ('const FORM_TPL=' + json.dumps(tpl, ensure_ascii=True, separators=(',', ':')) + ';\n'
          + f"const FORM_BLOB_B64='{base64.b64encode(packed).decode()}'; // {len(blob)} Bytes entpackt\n"
          + f"const ZNG_WASM_B64='{base64.b64encode(wasm).decode()}';\n")
    html = html_path.read_text(encoding='utf-8')
    a, b = '// FORM-TEMPLATE-BEGIN (erzeugt von tools/build_form_template.py, nicht von Hand ändern)\n', '// FORM-TEMPLATE-END\n'
    if a not in html or b not in html:
        die('Markierungen FORM-TEMPLATE-BEGIN/END fehlen in ' + str(html_path))
    html = html[:html.index(a) + len(a)] + js + html[html.index(b):]
    html_path.write_text(html, encoding='utf-8')
    print(f'Vorlage geschrieben: {len(pdf.order)} Objekte, {len(dynamic)} veränderlich, '
          f'Daten {len(blob)} → {len(packed)} Bytes, WebAssembly {len(wasm)} Bytes')


if __name__ == '__main__':
    main()
