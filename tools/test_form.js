#!/usr/bin/env node
// Prüft, ob der Kurswahl-Planer eine Kurswahl-PDF der Schule byte-genau nachbaut.
// Liest Jahrgang, Name, Klasse, IDs, Kreuze, Datum und Datei-ID aus der PDF, erzeugt sie im Browser neu und vergleicht.
// Aufruf: node tools/test_form.js <Kurswahl-PDF der Schule> [...weitere PDFs]
// Benötigt: npm i playwright (Chromium; PLAYWRIGHT_CHROMIUM=/pfad/zu/chrome für einen vorhandenen Browser)
const fs = require('fs'), path = require('path'), zlib = require('zlib');
const {chromium} = require('playwright');

const latin1 = b => b.toString('latin1');
const utf16 = h => { const b = Buffer.from(h, 'hex'); let s = ''; for (let i = 2; i < b.length; i += 2) s += String.fromCharCode(b.readUInt16BE(i)); return s; };

function readSchoolPdf(file) {
  const b = fs.readFileSync(file), s = latin1(b);
  const objs = {};
  for (const m of s.matchAll(/\r\n(\d+) 0 obj\r\n/g)) objs[m[1]] = m.index + 2;
  const obj = n => s.slice(objs[n], s.indexOf('endobj', objs[n]));
  const stream = n => {
    const o = obj(n), i = o.indexOf('stream\r\n') + 8, len = +/\/Length (\d+)/.exec(o)[1];
    return latin1(zlib.inflateRawSync(b.subarray(objs[n] + i + 2, objs[n] + i + len - 4)));
  };
  const trailer = s.slice(s.lastIndexOf('trailer'));
  const info = obj(/\/Info (\d+) 0 R/.exec(trailer)[1]);
  const d = Buffer.from(/\/CreationDate <([0-9A-F]+)>/.exec(info)[1], 'hex').toString('latin1');
  const [, Y, M, D, h, mi, se, sg, th, tm] = /D:(\d{4})(\d\d)(\d\d)(\d\d)(\d\d)(\d\d)([+-])(\d\d)'(\d\d)'/.exec(d);
  const tz = (sg === '-' ? -1 : 1) * (+th * 60 + +tm);
  const date = new Date(Date.UTC(+Y, M - 1, +D, +h, +mi, +se) - tz * 60000);
  const field = name => { const m = new RegExp('/T <' + ('FEFF' + [...name].map(c => c.charCodeAt(0).toString(16).toUpperCase().padStart(4, '0')).join('')) + '>[^]*?/V <([0-9A-F]*)>').exec(s); return m ? utf16(m[1]) : ''; };
  const checks = [...s.matchAll(/\/FT \/Btn \/T <([0-9A-F]+)> \/V \/Yes/g)].map(m => utf16(m[1]));
  const pk = /\/Ff 49152 \/V \/(\S+)/.exec(s)[1].replace(/#([0-9A-F]{2})/g, (_, x) => String.fromCharCode(parseInt(x, 16)));
  // Texte der ersten Seite über die ToUnicode-Tabellen der beiden Arial-Schriften lesen
  const page = /\/Kids \[(\d+) 0 R/.exec(s)[1], content = stream(/\/Contents (\d+) 0 R/.exec(obj(page))[1]);
  const maps = [...s.matchAll(/\/BaseFont \/DEVEXP#2BArial\S* \/Encoding \/Identity#2DH \/ToUnicode (\d+) 0 R/g)].map(m => {
    const map = {}; for (const [, g, u] of stream(m[1]).matchAll(/<([0-9A-F]{4})> <([0-9A-F]{4})>/g)) map[g] = String.fromCharCode(parseInt(u, 16)); return map;
  });
  const texts = [...content.matchAll(/\[([^\]]*)\] TJ|<([0-9A-F]+)> Tj/g)].flatMap(m => {
    const hex = m[1] ? [...m[1].matchAll(/<([0-9A-F]+)>/g)].map(x => x[1]).join('') : m[2];
    return maps.map(map => hex.match(/.{4}/g).map(g => map[g] ?? '�').join(''));
  });
  // Beide Schriften haben dieselben Glyphennummern: die Lesart ohne unbekannte Zeichen gilt
  const after = p => { const t = texts.find(x => x.startsWith(p) && !x.includes('\uFFFD')); if (t == null) throw new Error(p + ' nicht gefunden'); return t.slice(p.length); };
  return {
    name: after('Name: '), klasse: after('Klasse: '), jahrgang: after('Jahrgang: ').replace(/, GYM_SEK_II$/, ''),
    schuelerId: field('SchuelerId'), jahrgangId: field('AbiturJahrgangId'), checks, pk,
    dateIso: date.toISOString(), tzMinutes: tz, docId: /\/ID \[<([0-9A-F]+)>/.exec(trailer)[1],
  };
}

(async () => {
  const files = process.argv.slice(2);
  if (!files.length) { console.error('Aufruf: node tools/test_form.js <Kurswahl-PDF> [...]'); process.exit(2); }
  const browser = await chromium.launch(process.env.PLAYWRIGHT_CHROMIUM ? {executablePath: process.env.PLAYWRIGHT_CHROMIUM} : {});
  const page = await browser.newPage();
  await page.route(/^https?:/, r => r.abort());
  await page.goto('file://' + path.resolve(__dirname, '../public/planer.html'));
  let failed = 0;
  for (const file of files) {
    const opts = readSchoolPdf(file), ref = fs.readFileSync(file);
    const out = await page.evaluate(async o => {
      try { const r = await buildSchoolPdf({...o, date: new Date(o.dateIso)}); return Array.from(r.bytes); }
      catch (e) { return e.message + (e.missing ? ' ' + JSON.stringify(Object.fromEntries(Object.entries(e.missing).map(([k, v]) => [k, [...v]]))) : ''); }
    }, opts);
    if (typeof out === 'string') { failed++; console.log(`FEHLER ${file}: ${out}`); continue; }
    const gen = Buffer.from(out);
    if (Buffer.compare(gen, ref) === 0) { console.log(`OK     ${file} (${ref.length} Bytes identisch)`); continue; }
    failed++;
    let i = 0; while (i < ref.length && ref[i] === gen[i]) i++;
    console.log(`ANDERS ${file}: erste Abweichung bei Byte ${i} von ${ref.length} (erzeugt ${gen.length})`);
  }
  await browser.close();
  process.exit(failed ? 1 : 0);
})();
