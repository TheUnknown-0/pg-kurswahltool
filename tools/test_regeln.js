// Regeltests für den Planer: lädt public/assets/planer.js ohne Browser und prüft evaluate() an festen Fällen.
// Aufruf: node tools/test_regeln.js   (Rückgabewert 1, wenn ein Fall fehlschlägt)
const fs = require('fs'), vm = require('vm'), path = require('path');
const ASSETS = path.join(__dirname, '..', 'public', 'assets');
const stub = () => new Proxy(function(){}, {get:(t,k)=>k===Symbol.toPrimitive?(()=>''):k==='length'?0:stub(), apply:()=>stub(), construct:()=>stub(), set:()=>true});
const ctx = {console, setTimeout:()=>0, clearTimeout(){}, setInterval:()=>0, fetch:()=>new Promise(()=>{}), URL, Blob:function(){},
  location:{search:'',hash:'',href:''}, navigator:{}, matchMedia:()=>({matches:false,addEventListener(){}})};
ctx.window = ctx; ctx.addEventListener = function(){}; ctx.removeEventListener = function(){}; ctx.document = stub();
ctx.localStorage = {getItem:()=>null, setItem(){}, removeItem(){}}; ctx.sessionStorage = ctx.localStorage;
vm.createContext(ctx);

const TESTS = `
(function(){
const A=[true,true,true,true], Q12=[true,true,false,false], Q34=[false,false,true,true];
let failed=0;
const run=d=>{ withState(d,normalize); return withState(d,evaluate); };
const errs=ev=>ev.R.filter(r=>r.level==='error').map(r=>r.text);
const check=(name,cond,info)=>{ console.log((cond?'ok    ':'FEHLER')+' '+name+(cond?'':' ['+info+']')); if(!cond) failed++; };
// Beispielwahl aus der Hilfe: Mathe und Englisch LK, Geschichte, Biologie, Kunst als 5. PK
const base=()=>{ const d=blankState(); d.jahrgang='Abitur 2028/29';
  d.roles={lk1:'ma',lk2:'en',pf3:'ge',pf4:'bi',pk5:'ku'};
  d.sem={ma:A,en:A,de:A,ge:A,bi:A,ku:A,pw:Q34,geo:A,ph:A,ch:Q12};
  d.sport=['B7','E1','B7','B1']; return d; };

let ev=run(base());
check('Beispielwahl ist zulässig', errs(ev).length===0, errs(ev).join(' | '));
check('Beispielwahl: 40 Kurse, 66 Stunden', ev.total===40 && ev.hours===66, ev.total+' / '+ev.hours);

// Geschichte zusätzlich: Kurse 1 und 2 ab Abitur 2028/29, vorher 3 und 4 (§ 25 Abs. 3, § 49 Abs. 11)
const geFall=(jg,ge)=>{ const d=base(); d.jahrgang=jg; d.roles.pf3='geo'; d.sem.ge=ge; return errs(run(d)).filter(t=>/Geschichte in Q/.test(t)).length; };
check('Geschichte Q1+Q2 reicht ab Abitur 2028/29', geFall('Abitur 2028/29',Q12)===0, '');
check('Geschichte Q3+Q4 reicht ab Abitur 2028/29 nicht', geFall('Abitur 2028/29',Q34)===1, '');
check('Geschichte Q3+Q4 reicht bis Abitur 2027/28', geFall('Abitur 2027/28',Q34)===0, '');
check('Geschichte Q1+Q2 reicht bis Abitur 2027/28 nicht', geFall('Abitur 2027/28',Q12)===1, '');

// Künstlerisches Fach (§ 25 Abs. 2): entfällt nur bei neu begonnener ZWEITER Fremdsprache
const kfFall=langs=>{ const d=base(); d.roles.pk5='ph'; delete d.sem.ku; d.langs={en:'early',fr:'none',la:'none',sp:'none',...langs}; d.langKl={};
  d.sem.sp=A; return errs(run(d)).filter(t=>/Künstlerisches Fach/.test(t)).length; };
check('Zweite Fremdsprache neu in Klasse 10: künstlerisches Fach entfällt', kfFall({sp:'new'})===0, '');
check('Dritte Fremdsprache neu in Klasse 10: künstlerisches Fach bleibt Pflicht', kfFall({fr:'early',sp:'new'})===1, '');

// Sport als 4. Prüfungsfach (§ 13 Abs. 5): ein Kurs Theorie und drei Kurse Praxis einbringpflichtig
{ const d=base(); d.roles.pf4='spo'; d.sem.spt=Q34; ev=run(d);
  const spo=[...ev.E].filter(k=>k.startsWith('spo:')).length, spt=[...ev.E].filter(k=>k.startsWith('spt:')).length;
  check('Sport als 4. PF: 3 Praxis und 1 Theorie einbringpflichtig', spo===3 && spt===1, spo+' Praxis, '+spt+' Theorie'); }
{ const d=base(); d.roles.pk5='spo'; d.sem.spt=Q34; ev=run(d);
  check('Sport als 5. PK: nur der letzte Theoriekurs einbringpflichtig', [...ev.E].filter(k=>k.startsWith('spo:')).length===0 && ev.E.has('spt:3'), [...ev.E].join(',')); }

// Wochenstunden (§ 20 Abs. 1): Grundkurs einer neu begonnenen Fremdsprache hat vier Stunden
{ const d=base(); d.langs={en:'early',fr:'none',la:'none',sp:'new'}; d.sem.sp=A; ev=run(d);
  check('Neu begonnene Fremdsprache zählt 4 Wochenstunden', ev.hours===66+4*4/2, String(ev.hours)); }

// Sportpraxis
{ const d=base(); d.sport=['B7','B1','B7','B1']; check('Vier Ballspiele sind unzulässig', errs(run(d)).some(t=>/anderen Bewegungsfeld/.test(t)), ''); }
{ const d=base(); d.meta.befreit=true; ev=run(d); check('Sportbefreit: 40 Kurse bleiben Pflicht', ev.minK===40 && errs(ev).some(t=>/Mindestens 40 Kurse/.test(t)), ''); }

// Schuljahre folgen dem Jahrgang
{ const d=base(); d.jahrgang='Abitur 2028/29'; const sj=withState(d,schuljahre); check('Schuljahre zum Jahrgang', sj.join()==='2027/28,2028/29', sj.join()); }

globalThis.__failed=failed;
})();
`;
const code = ['kursangebot.js', 'form-template.js', 'planer.js'].map(f => fs.readFileSync(path.join(ASSETS, f), 'utf8')).join('\n;\n') + '\n;\n' + TESTS;
try { vm.runInContext(code, ctx, {filename: 'planer+tests.js'}); } catch (e) { console.log('FEHLER beim Laden:', e.message); process.exit(1); }
console.log(ctx.__failed ? `\n${ctx.__failed} Fall/Fälle fehlgeschlagen.` : '\nAlle Fälle bestanden.');
process.exit(ctx.__failed ? 1 : 0);
