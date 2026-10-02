/* =========================== Daten =========================== */
const SUBJECTS = [
  {id:'de', name:'Deutsch', af:1, lk1:true, lk2:true, group:'de'},
  {id:'en', name:'Englisch', af:1, lang:true, lk2:true, group:'fs'},
  {id:'fr', name:'Französisch', af:1, lang:true, lk2:true, group:'fs'},
  {id:'la', name:'Latein', af:1, lang:true, lk2:true, group:'fs'},
  {id:'sp', name:'Spanisch', af:1, lang:true, group:'fs'},
  {id:'mu', name:'Musik', af:1, kf:true, lk2:true},
  {id:'ku', name:'Bildende Kunst', af:1, kf:true, lk2:true},
  {id:'ds', name:'Darstellendes Spiel', af:1, kf:true, needsWpf:'wpfDs', roles:['pk5'], note:'nur Grundkurs, nur als Referenzfach der 5. PK'},
  {id:'ge', name:'Geschichte', af:2, lk2:true, ge:true},
  {id:'geb', name:'Geschichte bilingual', af:2, ge:true, twin:'ge', note:'für den bilingualen Zug'},
  {id:'pw', name:'Politikwissenschaft', af:2, lk2:true, pw:true},
  {id:'pwb', name:'Politikwissenschaft bilingual', af:2, pw:true, twin:'pw', note:'für den bilingualen Zug'},
  {id:'geo', name:'Geografie', af:2, lk2:true},
  {id:'phi', name:'Philosophie', af:2},
  {id:'psy', name:'Psychologie', af:2, roles:[], sems:[1,2], note:'nur Q1 und Q2, kein Prüfungsfach'},
  {id:'sw', name:'Sozialwissenschaften', af:2, lk2:true, needsWpf:'wpfSw', roles:['lk2'], gk:false, note:'nur als 2. Leistungskurs'},
  {id:'ma', name:'Mathematik', af:3, lk1:true, lk2:true, group:'ma'},
  {id:'ph', name:'Physik', af:3, lk1:true, lk2:true, nw:true},
  {id:'ch', name:'Chemie', af:3, lk1:true, lk2:true, nw:true},
  {id:'bi', name:'Biologie', af:3, lk1:true, lk2:true, nw:true},
  {id:'inf', name:'Informatik', af:3, lk2:true, needsWpf:'wpfInf', note:'nur mit Wahlpflicht in Klasse 10'},
  {id:'spo', name:'Sport', af:0, roles:['pf4','pk5'], gk:false, note:'Praxis wird unten gewählt; als 4. PF oder 5. PK zusätzlich zwei Kurse Sporttheorie'},
];
const AF_NAMES = {1:'Aufgabenfeld I – sprachlich-literarisch-künstlerisch',2:'Aufgabenfeld II – gesellschaftswissenschaftlich',3:'Aufgabenfeld III – mathematisch-naturwissenschaftlich-technisch',0:'Keinem Aufgabenfeld zugeordnet'};
const ROLES = [['lk1','1. LK'],['lk2','2. LK'],['pf3','3. PF'],['pf4','4. PF'],['pk5','5. PK']];
const ROLE_LABEL = Object.fromEntries(ROLES);
const SCHULJAHRE = ['2026/27','2027/28']; // Q1/Q2 und Q3/Q4 – bei jeder Kurswahl anpassen
const LANG_OPTS = [['none','nicht belegt'],['early','ab Klasse 5–7'],['mid','ab Klasse 8–9'],['new','neu ab Klasse 10']];
// Eingabe „seit Klasse“ → Stufe für die Regeln: bis Kl. 7 = early, Kl. 8–9 = mid, Kl. 10 = neu begonnen
const parseKl = v => { const n=Number(v); return Number.isInteger(n) && n>=1 && n<=10 ? n : null; };
const langCat = kl => !kl ? 'none' : kl<=7 ? 'early' : kl<=9 ? 'mid' : 'new';
const langLabel = id => { const kl=st.langKl&&st.langKl[id]; return kl ? `seit Klasse ${kl}${kl===10?' (neu)':''}` : LANG_OPTS.find(o=>o[0]===st.langs[id])[1]; };
const langSortKey = id => (st.langKl&&st.langKl[id]) || {early:5,mid:8,new:10,none:99}[st.langs[id]];

const SPORT = {
  A1:'Leichtathletik', B1:'Basketball', B3:'Fußball', B4:'Handball', B5:'Hockey', B6:'Rugby', B7:'Volleyball',
  B8:'Badminton', B10:'Tischtennis', B12:'Ultimate Frisbee', C1:'Geräteturnen', D1:'Gymnastik/Tanz',
  E1:'Schwimmen', G2:'Rudern', H1:'Fitness'
};
const SPORT_ODD = ['A1','B3','B4','B12','B7','B8','B10','C1','D1','E1','G2','H1'];
const SPORT_EVEN = ['A1','B1','B3','B5','B6','B7','B8','B10','D1','E1','G2','H1'];
const SPORT_BY_SEM = [SPORT_ODD, SPORT_EVEN, SPORT_ODD, SPORT_EVEN];

const ZUSATZ = [
  {id:'z_ks', name:'Kreatives Schreiben', fach:'de', sems:[1,2,3,4], pair:true},
  {id:'z_toefl', name:'TOEFL', fach:'en', sems:[1,2,3,4], pair:true},
  {id:'z_endeb', name:'Debating (Englisch)', fach:'en', sems:[1,2], pair:true},
  {id:'z_tg', name:'Textiles Gestalten', fach:'ku', sems:[1,2,3,4], pair:true},
  {id:'z_chor', name:'Ensemble Chor', fach:'mu', sems:[1,2,3,4], ens:true},
  {id:'z_band', name:'Big Band', fach:'mu', sems:[1,2,3,4], ens:true},
  {id:'z_sub', name:'Studium und Beruf', fach:'pw', sems:[1,2,3,4], pair:true, sub:true},
  {id:'z_pwdeb', name:'Debating (Politikwissenschaft)', fach:'pw', sems:[3,4], pair:true},
  {id:'z_gere', name:'Geschichte und Religion', fach:'ge', sems:[1,2,3,4]},
  {id:'z_rt', name:'Relativitätstheorie', fach:'ph', sems:[1,2,3,4], pair:true},
  {id:'z_astro', name:'Astronomie', fach:'ph', sems:[1,2,3,4], pair:true},
];
const S = Object.fromEntries(SUBJECTS.map(s=>[s.id,s]));
const Z = Object.fromEntries(ZUSATZ.map(z=>[z.id,z]));

/* =========================== State =========================== */
const KEY = 'kurswahl-planer-v1';

/* ---- Server-Betrieb ----
   Mit Startdaten vom Server (#kw-boot) ist der Planer an ein Schülerkonto gebunden:
   - Normalfall: Stammdaten aus der Schul-PDF auf dem Server, die Wahl wird auf dem Server gespeichert.
   - Lokaler Modus: Der Schüler hat seine eigene Schul-PDF hochgeladen. Dann bleiben PDF, Stammdaten
     und Wahl nur in diesem Browser, bis er die eigene PDF wieder entfernt.
   Ohne Startdaten (Datei direkt geöffnet) arbeitet der Planer wie bisher nur im Browser. */
const BOOT = (()=>{ try{ const el=document.getElementById('kw-boot'); return el ? JSON.parse(el.textContent) : null; }catch(e){ return null; } })();
const SERVER = !!BOOT;
const STORE_KEY = SERVER ? `kurswahl-planer-${BOOT.login}` : KEY;
const LOCAL_PDF_KEY = SERVER ? `kurswahl-eigene-pdf-${BOOT.login}` : 'kurswahl-eigene-pdf';
const lsGet = k => { try{ return localStorage.getItem(k); }catch(e){ return null; } };
const lsSet = (k,v) => { try{ localStorage.setItem(k,v); return true; }catch(e){ return false; } };
const lsDel = k => { try{ localStorage.removeItem(k); }catch(e){} };
// Stammdaten der selbst hochgeladenen PDF (null = keine eigene PDF)
let localProfile = SERVER && BOOT.readOnly ? null : (()=>{ try{ return JSON.parse(lsGet(LOCAL_PDF_KEY)||'null'); }catch(e){ return null; } })();
// Nur lesen: Admin-Ansicht, abgegebene Wahl oder abgelaufene Frist
let readOnly = SERVER && !!BOOT.readOnly;
const localMode = () => !!localProfile;

/* ---- Pflichtkurse der Schule ----
   Vom Admin festgelegt (Fach + Halbjahre); ohne Server Deutsch und Mathematik in allen Halbjahren.
   Ein bilingualer Kurs erfüllt die Pflicht des regulären Fachs. Regulär und bilingual schließen sich aus. */
const PFLICHT_DEFAULT=[{id:'de',sems:[1,2,3,4]},{id:'ma',sems:[1,2,3,4]}];
const PFLICHT=(SERVER && Array.isArray(BOOT.pflicht) ? BOOT.pflicht : PFLICHT_DEFAULT)
  .filter(p=>p && S[p.id] && !S[p.id].twin && Array.isArray(p.sems))
  .map(p=>({id:p.id, sems:p.sems.map(Number).filter(q=>(S[p.id].sems||[1,2,3,4]).includes(q))}));
const partnerOf = id => S[id] && S[id].twin ? S[id].twin : ((SUBJECTS.find(x=>x.twin===id)||{}).id || null);
const pflichtOf = id => PFLICHT.find(p=>p.id===id || (S[id] && S[id].twin===p.id)) || null;
// Fach, das die Pflicht gerade erfüllt: die bilinguale Variante, sobald sie belegt ist
// (läuft schon beim ersten normalize(), daher direkt auf st statt über sems()/hasRole())
const semsOf = id => st.sem[id] || [false,false,false,false];
const pflichtCarrier = p => { const tw=partnerOf(p.id); return tw && semsOf(tw).some(Boolean) ? tw : p.id; };
// Durch Pflicht gesperrte Halbjahre dieses Fachs
const lockedSems = id => { const p=pflichtOf(id); return [0,1,2,3].map(i=>!!p && pflichtCarrier(p)===id && p.sems.includes(i+1)); };
const semText = qs => qs.length===4 ? 'allen vier Halbjahren' : qs.map(q=>'Q'+q).join(', ').replace(/, (Q\d)$/,' und $1');
// Reguläres bzw. bilinguales Gegenstück abwählen (Kreuze und Prüfungsfach-Rollen)
function dropPartner(id){
  const p=partnerOf(id); if(!p) return;
  delete st.sem[p];
  Object.keys(st.roles).forEach(r=>{ if(st.roles[r]===p) st.roles[r]=null; });
}
const profile = () => localProfile || (BOOT && BOOT.profile) || null;
let st = {
  name:'', klasse:'', jahrgang:'', schuelerId:'', langs:{en:'early',fr:'none',la:'none',sp:'none'}, langKl:{en:3},
  meta:{wpfInf:false,wpfSw:false,wpfDs:false,ruderAg:false,befreit:false},
  pkForm:'praes',
  roles:{lk1:null,lk2:null,pf3:null,pf4:null,pk5:null},
  sem:{}, sport:[null,null,null,null], zusatz:{}
};
function mergeState(o){ if(o && typeof o==='object') st={...st,...o,langs:{...st.langs,...o.langs},langKl:o.langKl?{...o.langKl}:(o.langs?{}:{...st.langKl}),meta:{...st.meta,...o.meta},roles:{...st.roles,...o.roles}}; }
function load(){
  if(SERVER && !localMode()){ mergeState(BOOT.state); return; }
  try{ const r=lsGet(STORE_KEY); if(r) mergeState(JSON.parse(r)); else if(SERVER) mergeState(BOOT.state); }catch(e){}
}
function save(){
  if(readOnly) return;
  if(SERVER && !localMode()){ scheduleServerSave(); return; }
  lsSet(STORE_KEY,JSON.stringify(st));
}
// Name, Klasse, Jahrgang und Schüler-ID kommen aus der Schul-PDF, sobald eine vorliegt
function applyProfile(){
  const p=profile(); if(!p) return;
  st.name=p.name||''; st.klasse=p.klasse||''; st.jahrgang=p.jahrgang||''; st.schuelerId=p.schuelerId||'';
}

/* ---- Kurzfassung der Wahl (für Admin-Liste, CSV- und Formular-Export) ---- */
function buildSummary(){
  const ev=evaluate(), name=id=>id==='spt'?'Sporttheorie':(S[id]?S[id].name:id);
  const perSem=[0,1,2,3].map(i=>[
    ...SUBJECTS.filter(s=>s.id!=='spo' && sems(s.id)[i]).map(s=>s.name),
    ...(sems('spt')[i]?['Sporttheorie']:[]),
    ...(!sportOff() && st.sport[i] ? [`Sport ${SPORT[st.sport[i]]||st.sport[i]}`] : []),
    ...ZUSATZ.filter(z=>(st.zusatz[z.id]||[])[i]).map(z=>`Zusatzkurs ${z.name}`),
  ]);
  const {checks}=formChecks();
  return {
    errors:ev.R.filter(r=>r.level==='error').length, warnings:ev.R.filter(r=>r.level==='warn').length,
    total:ev.total, hours:ev.hours,
    roles:Object.fromEntries(ROLES.map(([r])=>[r, st.roles[r]?name(st.roles[r]):''])),
    pkForm:st.pkForm==='bll'?'Besondere Lernleistung':'Präsentationsprüfung', pkField:st.pkForm==='bll'?'BLL_0':'Praesentation_0',
    sems:perSem, checks, fieldKeys:checks.map(c=>TEMPLATE_KEYS().get(c)).filter(Boolean),
  };
}

/* ---- Speichern auf dem Server ---- */
let srvVersion = SERVER ? BOOT.version : 0, saveTimer=null, saving=false, saveAgain=false, dirty=false;
function scheduleServerSave(){ dirty=true; setSync('pending'); clearTimeout(saveTimer); saveTimer=setTimeout(serverSave, 800); }
async function serverSave(force=false){
  if(saving){ saveAgain=true; return; }
  saving=true; saveAgain=false; dirty=false; clearTimeout(saveTimer);
  try{
    const r=await fetch('api/state',{method:'POST', credentials:'same-origin',
      headers:{'Content-Type':'application/json','X-CSRF-Token':BOOT.csrf,'Accept':'application/json'},
      body:JSON.stringify({state:st, version:srvVersion, force, summary:buildSummary()})});
    const j=await r.json().catch(()=>({}));
    if(r.status===409 && j.conflict){
      const when=j.savedAt?` (${fmtDateTime(j.savedAt)})`:'';
      if(confirm(`Deine Wahl wurde inzwischen auf einem anderen Gerät gespeichert${when}.\n\nOK: deine Wahl von diesem Gerät behalten und die andere überschreiben.\nAbbrechen: die Wahl vom anderen Gerät laden.`)){
        srvVersion=j.version; saving=false; return serverSave(true);
      }
      srvVersion=j.version; st=blankState(); mergeState(j.state); applyProfile();
      normalize(); syncControls(); renderAll(); setSync('saved', j.savedAt); return;
    }
    if(r.status===401){ setSync('error','Du bist abgemeldet. Bitte melde dich neu an – deine letzte Änderung ist nicht gespeichert.'); dirty=true; return; }
    if(r.status===419){ setSync('error','Die Sitzung ist abgelaufen. Bitte lade die Seite neu.'); dirty=true; return; }
    if(r.status===423){ dirty=false; applyReadOnly(); setSync('error', j.error||'Die Wahl ist gesperrt.'); return; }
    if(!r.ok) throw new Error(j.error||r.status);
    srvVersion=j.version; setSync('saved', j.savedAt);
  }catch(e){
    dirty=true; setSync('error','Speichern fehlgeschlagen – neuer Versuch in 10 Sekunden.');
    clearTimeout(saveTimer); saveTimer=setTimeout(serverSave, 10000);
  }finally{
    saving=false;
    if(saveAgain) serverSave();
  }
}
window.addEventListener('beforeunload', e=>{ if(SERVER && !localMode() && (dirty||saving)){ e.preventDefault(); e.returnValue=''; } });
const fmtDateTime = d => { const t=new Date(String(d).replace(' ','T')); return isNaN(t) ? String(d) : t.toLocaleString('de-DE',{dateStyle:'short',timeStyle:'short'}); };
let syncEl=null;
function setSync(kind, info){
  if(!syncEl) return;
  syncEl.className='kw-sync '+kind;
  syncEl.textContent = kind==='pending' ? 'Wird gespeichert …'
    : kind==='saved' ? `Auf dem Server gespeichert${info?' · '+fmtDateTime(info):''}`
    : kind==='local' ? 'Nur in diesem Browser (eigene PDF)'
    : kind==='locked' ? info
    : (info||'Fehler');
}
// Paar-Kurse: gewähltes Jahr (0 = Q1+Q2, 1 = Q3+Q4) oder null
const pairYear = a => (a[0]||a[1]) ? 0 : (a[2]||a[3]) ? 1 : null;
const ALL4 = () => [true,true,true,true];
// Belegungen nur in Blöcken (Q1+Q2, Q3+Q4): halbe Blöcke werden vervollständigt
const toBlocks = (a, allowed=[1,2,3,4]) => {
  const b=[0,1,2,3].map(i=>!!(a&&a[i]) && allowed.includes(i+1));
  [0,2].forEach(y=>{ if((b[y]||b[y+1]) && allowed.includes(y+1) && allowed.includes(y+2)) b[y]=b[y+1]=true; else if(!(allowed.includes(y+1)&&allowed.includes(y+2))) b[y]=b[y+1]=false; });
  return b;
};
function normalize(){
  // Sporttheorie gibt es nur, wenn Sport 4. PF oder 5. PK ist – fällt die Rolle weg, Kreuze entfernen
  if(!(st.roles.pf4==='spo' || st.roles.pk5==='spo')) delete st.sem.spt;
  if(!st.langKl || typeof st.langKl!=='object') st.langKl={};
  Object.keys(st.langKl).forEach(id=>{ const kl=parseKl(st.langKl[id]); if(!['en','fr','la','sp'].includes(id) || !kl || langCat(kl)!==st.langs[id]) delete st.langKl[id]; else st.langKl[id]=kl; });
  Object.keys(st.sem).forEach(id=>{
    const allowed = id==='spt' ? [3,4] : (S[id]&&S[id].sems) || [1,2,3,4];
    st.sem[id]=toBlocks(st.sem[id], allowed);
  });
  // Regulär und bilingual zugleich (z. B. aus einer geladenen Datei): das Fach mit Rolle bzw. die bilinguale Variante behalten
  SUBJECTS.filter(s=>s.twin).forEach(s=>{
    if(!semsOf(s.id).some(Boolean) || !semsOf(s.twin).some(Boolean)) return;
    const role=id=>Object.values(st.roles).includes(id);
    dropPartner(role(s.twin) && !role(s.id) ? s.twin : s.id);
  });
  PFLICHT.forEach(p=>{
    const c=pflichtCarrier(p), a=[...semsOf(c)];
    p.sems.forEach(q=>{ a[q-1]=true; });
    st.sem[c]=a;
  });
  Object.keys(st.zusatz).forEach(id=>{
    const z=Z[id]; if(!z){ delete st.zusatz[id]; return; }
    let a=toBlocks(st.zusatz[id], z.sems);
    if(z.pair){ const y=pairYear(a); a=[0,1,2,3].map(i=>y!==null && (i<2?0:1)===y); }
    st.zusatz[id]=a;
  });
}
load();
applyProfile();
normalize();

/* =========================== Helpers =========================== */
const sems = id => st.sem[id] || [false,false,false,false];
const cnt = id => sems(id).filter(Boolean).length;
const durch = id => sems(id).every(Boolean);
const roleOf = id => Object.entries(st.roles).filter(([r,v])=>v===id).map(([r])=>r);
const hasRole = id => roleOf(id).length>0;
const isLK = id => st.roles.lk1===id || st.roles.lk2===id;
const isPF = id => ['lk1','lk2','pf3','pf4'].some(r=>st.roles[r]===id);
const langActive = id => !S[id].lang || st.langs[id]!=='none';
function mergedSems(pred){ // OR over subjects matching pred
  const out=[false,false,false,false];
  SUBJECTS.filter(pred).forEach(s=>sems(s.id).forEach((v,i)=>{if(v)out[i]=true;}));
  return out;
}
const geSems = () => mergedSems(s=>s.ge);
const pwSems = () => mergedSems(s=>s.pw);
const kfExempt = () => Object.values(st.langs).includes('new');
const sportOff = () => st.meta.befreit;
const sportRole = () => st.roles.pf4==='spo' || st.roles.pk5==='spo';
// 2. Fremdsprache erst ab Klasse 8/9 begonnen (und keine 2. FS ab Kl. 5–7): Belegpflicht bis Ende Q2
const zweitFsMid = () => {
  if(Object.values(st.langs).includes('new')) return [];
  const early=SUBJECTS.filter(s=>s.lang&&st.langs[s.id]==='early');
  return early.length===1 ? SUBJECTS.filter(s=>s.lang&&st.langs[s.id]==='mid') : [];
};

function allowedRoles(s){
  let base = s.roles ? [...s.roles] : ['pf3','pf4','pk5'];
  if(!s.roles){ if(s.lk1) base.unshift('lk1'); if(s.lk2) base.splice(s.lk1?1:0,0,'lk2'); }
  if(s.lang){ base = ['pf3','pf4','pk5']; if(s.lk2 && st.langs[s.id]!=='new'){ base.unshift('lk2'); if(st.langs[s.id]!=='none') base.unshift('lk1'); } }
  return base;
}
function roleBlocked(s, r){
  if(s.needsWpf && !st.meta[s.needsWpf]) return 'Nur mit Wahlpflichtfach in Klasse 10';
  if(s.lang && st.langs[s.id]==='none') return 'Sprache nicht belegt';
  if(r==='pk5' && st.pkForm==='praes' && isPF(s.id)) return 'Bei der Präsentationsprüfung muss das Referenzfach ein anderes Fach als die vier Prüfungsfächer sein';
  if(r!=='pk5' && st.roles.pk5===s.id && st.pkForm==='praes') return 'Fach ist bereits Referenzfach der 5. PK';
  if((r==='lk1'||r==='lk2') && s.twin) return 'Bilinguale Kurse nur als Grundkurs';
  return null;
}
function setRole(id, r){
  if(st.roles[r]===id){ st.roles[r]=null; return; }
  // remove other roles of this subject (except pk5+PF combination for BLL)
  Object.keys(st.roles).forEach(k=>{
    if(st.roles[k]===id){
      const keep = st.pkForm==='bll' && ((k==='pk5' && r!=='pk5') || (k!=='pk5' && r==='pk5'));
      if(!keep) st.roles[k]=null;
    }
  });
  st.roles[r]=id;
  dropPartner(id);
  const s=S[id];
  if(id==='spo'){ st.sem.spt=[false,false,true,true]; }
  else if(s.gk!==false || r==='lk1' || r==='lk2'){ st.sem[id]=[true,true,true,true]; }
}
function toggleSem(id,i){
  const a=[...sems(id)], y=i<2?0:2, on=!(a[y]&&a[y+1]), lock=lockedSems(id);
  if(!on && (lock[y]||lock[y+1])) return;
  a[y]=a[y+1]=on; st.sem[id]=a;
  if(on) dropPartner(id);
}

/* =========================== Validation =========================== */
function evaluate(){
  const R=[]; // {level, text, group}
  const add=(level,text,group)=>R.push({level,text,group});
  const ok=(cond,text,group,level='error')=>add(cond?'ok':level,text,group);
  const roles=st.roles;
  const G1='Prüfungsfächer', G2='Belegverpflichtungen', G3='Umfang und Einbringung';

  /* --- Prüfungsfächer --- */
  ok(!!roles.lk1 && !!roles.lk2, 'Zwei Leistungskursfächer gewählt', G1);
  if(roles.lk1){
    const s=S[roles.lk1];
    const okLk1 = s.group==='de'||s.group==='ma'||s.nw|| (s.lang && ['early','mid'].includes(st.langs[s.id]));
    ok(okLk1, '1. LK ist Deutsch, Mathematik, eine Naturwissenschaft oder eine spätestens ab Klasse 9 gelernte Fremdsprache', G1);
  }
  ok(!!roles.pf3 && !!roles.pf4, '3. und 4. Prüfungsfach gewählt', G1);
  ok(!!roles.pk5, 'Referenzfach der 5. Prüfungskomponente gewählt', G1);

  const pfIds=['lk1','lk2','pf3','pf4'].map(r=>roles[r]).filter(Boolean);
  const groups=new Set(pfIds.map(id=>S[id].group).filter(Boolean));
  ok(groups.size>=2, 'Zwei der drei Fächer(-gruppen) Deutsch, Mathematik, Fremdsprache sind Prüfungsfächer (1.–4. PF)', G1);
  const fsCount=pfIds.filter(id=>S[id].lang).length;
  if(fsCount>=2) ok(groups.has('de')||groups.has('ma'), 'Zwei Fremdsprachen als Prüfungsfächer nur zusammen mit Deutsch oder Mathematik als Prüfungsfach', G1);

  const artPf=[roles.pf3,roles.pf4].filter(id=>id && (S[id].kf || id==='spo')).length;
  ok(artPf<=1, 'Unter 3. und 4. PF höchstens eines der Fächer Musik, Bildende Kunst, Darstellendes Spiel, Sport', G1);

  const fiveIds=[...pfIds, roles.pk5].filter(Boolean);
  const afs=new Set(fiveIds.map(id=>S[id].af));
  ok([1,2,3].every(a=>afs.has(a)), 'Die vier Prüfungsfächer und das Referenzfach der 5. PK decken alle drei Aufgabenfelder ab', G1);
  ok(fiveIds.some(id=>S[id].af===2), 'Ein Fach des Aufgabenfelds II ist Prüfungsfach oder Referenzfach der 5. PK', G1);

  fiveIds.forEach(id=>{
    if(id==='spo') return;
    ok(durch(id), `${S[id].name} (${roleOf(id).map(r=>ROLE_LABEL[r]).join('/')}) in allen vier Halbjahren belegt`, G1);
  });
  if(roles.pk5 && st.pkForm==='bll' && isPF(roles.pk5)){
    const afs4=new Set(pfIds.map(id=>S[id].af));
    ok([1,2,3].every(a=>afs4.has(a)), 'BLL mit einem Prüfungsfach als Referenzfach: die vier Prüfungsfächer allein decken alle Aufgabenfelder ab', G1);
  }
  SUBJECTS.forEach(s=>{
    if(s.needsWpf && !st.meta[s.needsWpf] && (cnt(s.id)>0||hasRole(s.id))) add('error',`${s.name} ist nur wählbar, wenn es in Klasse 10 als Wahlpflichtfach belegt wurde`,G1);
    if(s.lang && st.langs[s.id]==='none' && (cnt(s.id)>0||hasRole(s.id))) add('error',`${s.name}: gib oben an, seit wann du die Sprache lernst`,G1);
    if(s.lang && st.langs[s.id]==='new' && isLK(s.id)) add('error',`${s.name} wurde erst in Klasse 10 begonnen und kann kein Leistungskurs sein`,G1);
  });
  if(sportRole()){
    ok(sems('spt')[2] && sems('spt')[3], 'Sport als Prüfungsfach: zwei Kurse Sporttheorie (Q3, Q4) belegt', G1);
  }
  if(st.roles.pk5==='ds') add('info','Darstellendes Spiel als Referenzfach setzt Theater-Erfahrung oder das Wahlpflichtfach voraus',G1);

  /* --- Belegverpflichtungen --- */
  ok(durch('de'), 'Deutsch in allen vier Halbjahren', G2);
  ok(durch('ma'), 'Mathematik in allen vier Halbjahren', G2);
  const langsDurch=SUBJECTS.filter(s=>s.lang&&durch(s.id));
  const contDurch=langsDurch.filter(s=>['early','mid'].includes(st.langs[s.id]));
  const newDurch=langsDurch.filter(s=>st.langs[s.id]==='new');
  ok(langsDurch.length>0, 'Eine Fremdsprache durchgängig in allen vier Halbjahren', G2);
  if(langsDurch.length>0 && contDurch.length===0 && newDurch.length>0){
    const contQ12=SUBJECTS.filter(s=>s.lang&&['early','mid'].includes(st.langs[s.id])&&sems(s.id)[0]&&sems(s.id)[1]);
    ok(contQ12.length>0, 'Neu begonnene Fremdsprache als Pflichtsprache: die fortgesetzte Fremdsprache muss zusätzlich in Q1 und Q2 belegt werden', G2);
  }
  Object.entries(st.langs).forEach(([id,v])=>{ if(v==='new' && cnt(id)>0 && !durch(id)) add('error',`${S[id].name} (neu ab Klasse 10) muss bis Q4 durchgängig belegt werden`,G2); });

  zweitFsMid().forEach(s=>ok(sems(s.id)[0]&&sems(s.id)[1], `${s.name} (2. Fremdsprache ab Klasse 8–9) in Q1 und Q2 belegt`, G2));

  const nwDurch=SUBJECTS.filter(s=>s.nw&&durch(s.id));
  ok(nwDurch.length>0, 'Eine Naturwissenschaft (Physik, Chemie oder Biologie) durchgängig', G2);
  if(nwDurch.length>0 && nwDurch.every(s=>s.id==='bi')){
    const pc=['ph','ch'].some(id=>(sems(id)[0]&&sems(id)[1])||(sems(id)[2]&&sems(id)[3]));
    ok(pc, 'Biologie als einzige durchgängige Naturwissenschaft: zusätzlich Physik oder Chemie in Q1+Q2 oder Q3+Q4', G2);
  }

  const af2Durch=SUBJECTS.filter(s=>s.af===2&&durch(s.id));
  const ge=geSems(), pw=pwSems();
  const geDurch=ge.every(Boolean), pwDurch=pw.every(Boolean);
  const otherAf2Durch=SUBJECTS.filter(s=>s.af===2&&!s.ge&&durch(s.id));
  ok(af2Durch.length>0||geDurch, 'Ein Fach des Aufgabenfelds II durchgängig', G2);
  if(geDurch && otherAf2Durch.length===0){
    ok(pw[2]&&pw[3], 'Geschichte als durchgängiges Fach: zusätzlich Politikwissenschaft in Q3 und Q4 (oder ein weiteres Fach des AF II durchgängig)', G2);
  } else if(otherAf2Durch.length>0){
    ok(ge[2]&&ge[3], 'Zusätzlich Geschichte in Q3 und Q4', G2);
  }
  SUBJECTS.filter(s=>s.twin).forEach(s=>{
    if(sems(s.id).some(Boolean) && sems(s.twin).some(Boolean)) add('error',`${S[s.twin].name}: entweder regulär oder bilingual belegen, nicht beides`,G2);
  });
  PFLICHT.forEach(p=>{
    const c=pflichtCarrier(p);
    ok(p.sems.every(q=>sems(c)[q-1]), `${S[p.id].name} in ${semText(p.sems)} (Pflichtkurs der Schule${partnerOf(p.id)?', auch bilingual':''})`, G2);
  });

  if(!kfExempt()){
    const kf=SUBJECTS.filter(s=>s.kf).some(s=>(sems(s.id)[0]&&sems(s.id)[1])||(sems(s.id)[2]&&sems(s.id)[3]));
    ok(kf, 'Künstlerisches Fach (Musik, Kunst oder DS): zwei Kurse in Q1+Q2 oder Q3+Q4', G2);
  } else add('info','Künstlerisches Fach: entfällt, weil du eine Fremdsprache neu ab Klasse 10 begonnen hast',G2);

  if(!sportOff()){
    const chosen=st.sport.filter(Boolean);
    ok(chosen.length===4, 'Sportpraxis in allen vier Halbjahren gewählt', G2);
    const balls=chosen.filter(c=>c[0]==='B').length;
    if(chosen.length>0) ok(balls<=3, 'Höchstens drei Ballspiel-Kurse (B1–B12)', G2);
    if(chosen.length===4) ok(balls<4, 'Mindestens ein Sportkurs aus einem anderen Bewegungsfeld als Ballspiele', G2);
    if(chosen.includes('G2')&&!st.meta.ruderAg) add('error','Rudern setzt mindestens ein Jahr Ruder-AG voraus',G2);
    const times=c=>chosen.filter(x=>x===c).length;
    const triple=[...new Set(chosen)].filter(c=>times(c)>=3);
    if(triple.length) add('error',`Sportkurs höchstens zweimal wählbar (${triple.map(c=>SPORT[c]).join(', ')} ist ${times(triple[0])}-mal gewählt)`,G2);
    const dupes=chosen.filter((c,i)=>chosen.indexOf(c)!==i && times(c)===2);
    if(dupes.length) add('warn',`Sportkurs mehrfach gewählt (${[...new Set(dupes)].map(c=>SPORT[c]).join(', ')}) – nur mit abweichenden Inhalten oder höherer Leistungsstufe möglich`,G2);
  } else add('info','Sportpraxis entfällt wegen Befreiung; die Mindestzahl sinkt auf 39 Kurse',G2);
  ZUSATZ.forEach(z=>{
    const zs=st.zusatz[z.id]||[]; const n=zs.filter(Boolean).length;
    if(n===0) return;
    if(z.ens) return;
    const regular=isLK(z.fach) ? 4 : cnt(z.fach);
    if(regular<2) add('info',`Zusatzkurs ${z.name}: zählt zur Belegung, kann aber nur eingebracht werden, wenn du ${S[z.fach].name} mindestens zweimal belegst`,G3);
  });

  /* --- Umfang --- */
  const lkCourses=[roles.lk1,roles.lk2].filter(Boolean).reduce((a,id)=>a+cnt(id),0);
  const gkCourses=SUBJECTS.filter(s=>!isLK(s.id)&&s.id!=='spo').reduce((a,s)=>a+cnt(s.id),0);
  const theo=cnt('spt');
  const sportCourses=sportOff()?0:st.sport.filter(Boolean).length;
  const zCourses=Object.values(st.zusatz).reduce((a,arr)=>a+arr.filter(Boolean).length,0);
  const total=lkCourses+gkCourses+theo+sportCourses+zCourses;
  const hours=(lkCourses*5 + (gkCourses+theo+zCourses)*3 + sportCourses*2)/2;
  const minK=sportOff()?39:40;
  ok(total>=minK, `Mindestens ${minK} Kurse belegt (aktuell ${total})`, G3);
  ok(hours>=66, `Mindestens 66 Jahreswochenstunden (aktuell ${hours})`, G3);
  ok(lkCourses===8 || !(roles.lk1&&roles.lk2), 'Beide Leistungskurse in allen vier Halbjahren (8 LK-Kurse)', G3);

  /* --- Einbringpflichtige Grundkurse --- */
  const E=new Set(); const put=(id,i)=>{ if(!isLK(id)&&sems(id)[i]) E.add(id+':'+i); };
  const put4=id=>{ if(id) for(let i=0;i<4;i++) put(id,i); };
  put4(roles.pf3); put4(roles.pf4); put4('de'); put4('ma');
  const fsPflicht=contDurch[0]||langsDurch[0]; if(fsPflicht) put4(fsPflicht.id);
  const nwPflicht=nwDurch.find(s=>s.id!=='bi')||nwDurch[0]; if(nwPflicht) put4(nwPflicht.id);
  if(nwDurch.length>0 && nwDurch.every(s=>s.id==='bi')){
    const pcId=['ph','ch'].find(id=>(sems(id)[0]&&sems(id)[1])||(sems(id)[2]&&sems(id)[3]));
    if(pcId){ if(sems(pcId)[0]&&sems(pcId)[1]){put(pcId,0);put(pcId,1);} else {put(pcId,2);put(pcId,3);} }
  }
  const af2Pflicht=SUBJECTS.find(s=>s.af===2&&durch(s.id)); if(af2Pflicht) put4(af2Pflicht.id);
  if(geDurch && otherAf2Durch.length===0){ ['pw','pwb'].forEach(id=>{put(id,2);put(id,3);}); }
  else if(otherAf2Durch.length>0){ ['ge','geb'].forEach(id=>{put(id,2);put(id,3);}); }
  if(!kfExempt()){
    const kfS=SUBJECTS.filter(s=>s.kf).find(s=>(sems(s.id)[0]&&sems(s.id)[1])||(sems(s.id)[2]&&sems(s.id)[3]));
    if(kfS){ if(sems(kfS.id)[0]&&sems(kfS.id)[1]){put(kfS.id,0);put(kfS.id,1);} else {put(kfS.id,2);put(kfS.id,3);} }
  }
  if(roles.pk5 && roles.pk5!=='spo'){ put(roles.pk5,3); }
  if(sportRole()){ if(sems('spt')[3]) E.add('spt:3'); }
  const einbring=E.size;
  ok(einbring<=24, `Höchstens 24 einbringpflichtige Grundkurse (aktuell ${einbring}); es müssen genau 24 eingebracht werden`, G3);
  if(einbring<24 && einbring>0) add('info',`${24-einbring} weitere Grundkurse kannst du frei zum Einbringen wählen`,G3);

  return {R,total,hours,einbring,minK,lkCourses,gkCourses,E,theo,sportCourses,zCourses};
}

/* =========================== Render =========================== */
const $=s=>document.querySelector(s);
function checkSvg(){return '<svg viewBox="0 0 24 24"><path d="M5 5l14 14M19 5L5 19"/></svg>';}

function setLang(id, kl){
  const cat=langCat(kl);
  st.langs[id]=cat;
  if(kl) st.langKl[id]=kl; else delete st.langKl[id];
  if(cat==='none'){ delete st.sem[id]; Object.keys(st.roles).forEach(r=>{ if(st.roles[r]===id) st.roles[r]=null; }); }
  if(cat==='new'){ ['lk1','lk2'].forEach(r=>{ if(st.roles[r]===id) st.roles[r]=null; }); }
}
// Zahlenfeld „seit Klasse“; leer = nicht belegt. Ohne Zahl, aber mit alter Stufe zeigt der Platzhalter die Stufe
function langInput(kl, cat, onSet, label){
  const w=document.createElement('span'); w.className='klfield';
  w.innerHTML=`<span>seit Klasse</span><input type="number" inputmode="numeric" min="1" max="10" step="1" aria-label="${esc(label)}: seit Klasse">`;
  const inp=w.querySelector('input');
  inp.value=kl||'';
  inp.placeholder={none:'–',early:'1–7',mid:'8–9',new:'10'}[cat];
  inp.onchange=()=>{
    const v=inp.value.trim(), n=v===''?null:parseKl(v);
    if(v!=='' && n===null){ inp.value=kl||''; inp.title='Bitte eine Klasse von 1 bis 10 eingeben'; inp.reportValidity&&inp.reportValidity(); return; }
    onSet(n);
  };
  return w;
}
function renderLangs(){
  const el=$('#langs'); el.innerHTML='';
  ['en','fr','la','sp'].forEach(id=>{
    const row=document.createElement('div'); row.className='langrow';
    row.innerHTML=`<span>${S[id].name}</span>`;
    row.appendChild(langInput(st.langKl[id], st.langs[id], kl=>{ setLang(id,kl); update(); renderLangs(); }, S[id].name));
    el.appendChild(row);
  });
}

function qboxes(id, semsArr, allowed, locked, onToggle, disabledAll, lockArr){
  let html='';
  for(let i=0;i<4;i++){
    const on=semsArr[i], pl=!!(lockArr && lockArr[i] && on), dis=disabledAll || pl || !allowed.includes(i+1);
    html+=`<div class="qwrap"><i>Q${i+1}</i><button type="button" class="qbox ${on?'on':''} ${locked||pl?'lock':''}" data-id="${id}" data-i="${i}" ${dis?'disabled':''} aria-pressed="${on}" aria-label="${S[id]?S[id].name:id} Q${i+1}${pl?' (Pflicht)':''}">${checkSvg()}</button></div>`;
  }
  return html;
}

function renderFaecher(){
  const el=$('#faecher'); el.innerHTML='';
  [1,2,3,0].forEach(af=>{
    const box=document.createElement('div'); box.className='af';
    box.innerHTML=`<div class="af-title"><h3>${AF_NAMES[af]}</h3></div>
      <div class="colhead"><span>Fach</span><span>Rolle</span><span>Q1</span><span>Q2</span><span>Q3</span><span>Q4</span><span>Σ</span></div>`;
    SUBJECTS.filter(s=>s.af===af).forEach(s=>{
      const row=document.createElement('div'); row.className='row';
      const off=(s.needsWpf&&!st.meta[s.needsWpf])||(s.lang&&st.langs[s.id]==='none')||(s.id==='spo'&&sportOff());
      if(off) row.classList.add('off');
      if(hasRole(s.id)||cnt(s.id)>0) row.classList.add('active');
      const rs=roleOf(s.id);
      const chips=allowedRoles(s).map(r=>{
        const on=st.roles[r]===s.id; const blocked=roleBlocked(s,r);
        return `<button type="button" class="rchip ${on?'on':''} ${r==='pk5'?'pk':''}" data-role="${r}" data-id="${s.id}" ${(blocked&&!on)?'disabled':''} title="${blocked||''}">${ROLE_LABEL[r]}</button>`;
      }).join('');
      const pf=pflichtOf(s.id);
      const pfNote=pf ? (s.twin ? `erfüllt die Pflicht ${S[s.twin].name} in ${semText(pf.sems)}` : `Pflicht in ${semText(pf.sems)}${partnerOf(s.id)?' (oder bilingual)':''}`) : '';
      const note=[s.note||(s.lang?esc(langLabel(s.id)):''), pfNote].filter(Boolean).map(t=>`<small>${t}</small>`).join('');
      let boxes='';
      if(s.id==='spo'){
        const sp=sportOff()?[false,false,false,false]:st.sport.map(Boolean);
        boxes=qboxes('spo',sp,[1,2,3,4],true,null,true);
      } else {
        const allowed=s.sems||[1,2,3,4];
        const gkOnly = s.gk===false && !isLK(s.id);
        boxes=qboxes(s.id,sems(s.id),allowed,isLK(s.id),null,off||gkOnly,lockedSems(s.id));
      }
      const n=s.id==='spo'?(sportOff()?0:st.sport.filter(Boolean).length):cnt(s.id);
      row.innerHTML=`<div class="subj"><b>${s.name}</b>${note}</div><div class="roles">${chips}</div>${boxes}<div class="cnt ${n?'has':''}">${n}</div>`;
      box.appendChild(row);
      if(s.id==='spo'){
        const t=document.createElement('div'); t.className='row'; if(cnt('spt')>0)t.classList.add('active');
        t.innerHTML=`<div class="subj"><b>Sporttheorie</b><small>${sportRole()?'Pflicht bei Sport als 4. PF / 5. PK':'nur wenn Sport 4. PF oder 5. PK ist'}</small></div><div class="roles"></div>${qboxes('spt',sems('spt'),[3,4],false,null,!sportRole())}<div class="cnt ${cnt('spt')?'has':''}">${cnt('spt')}</div>`;
        box.appendChild(t);
      }
    });
    el.appendChild(box);
  });
}

function renderSport(){
  const el=$('#sport'); el.innerHTML='';
  for(let i=0;i<4;i++){
    const cell=document.createElement('div'); cell.className='sportcell';
    const sel=document.createElement('select'); sel.className='sel'; sel.disabled=sportOff();
    const o0=document.createElement('option'); o0.value=''; o0.textContent='– Kurs wählen –'; sel.appendChild(o0);
    SPORT_BY_SEM[i].forEach(c=>{
      const o=document.createElement('option'); o.value=c; o.textContent=`${SPORT[c]} (${c})`;
      // Ein Kurs darf höchstens zweimal gewählt werden: dritte Wahl sperren
      const elsewhere=st.sport.filter((x,j)=>j!==i && x===c).length;
      if(elsewhere>=2 && st.sport[i]!==c){ o.disabled=true; o.textContent+=' – schon zweimal gewählt'; }
      if(st.sport[i]===c) o.selected=true;
      sel.appendChild(o);
    });
    sel.onchange=()=>{st.sport[i]=sel.value||null; update();};
    cell.innerHTML=`<label>Kurshalbjahr Q${i+1}</label>`; cell.appendChild(sel);
    if(i>=2 && sportRole()) cell.insertAdjacentHTML('beforeend',`<div class="theo">+ Sporttheorie ${i-1} (${i===2?'IJ':'IK'})</div>`);
    el.appendChild(cell);
  }
}

function renderZusatz(){
  const el=$('#zusatz'); el.innerHTML=`<div class="colhead zhead"><span>Kurs</span><span>Q1</span><span>Q2</span><span>Q3</span><span>Q4</span><span>Σ</span></div>`;
  ZUSATZ.forEach(z=>{
    const zs=st.zusatz[z.id]||[false,false,false,false]; const n=zs.filter(Boolean).length;
    const row=document.createElement('div'); row.className='zrow'; if(n)row.classList.add('active');
    let boxes='';
    const py=z.pair?pairYear(zs):null;
    for(let i=0;i<4;i++){ const dis=!z.sems.includes(i+1) || (py!==null && (i<2?0:1)!==py); boxes+=`<div class="qwrap"><i>Q${i+1}</i><button type="button" class="qbox ${zs[i]?'on':''}" data-z="${z.id}" data-i="${i}" ${dis?'disabled':''} aria-pressed="${zs[i]}" aria-label="${z.name} Q${i+1}">${checkSvg()}</button></div>`; }
    row.innerHTML=`<div class="subj"><b>${z.name}</b><small>zu ${S[z.fach].name}${z.sems.length===2?` · nur Q${z.sems[0]}+Q${z.sems[1]}`:z.pair?' · einmal: Q1+Q2 oder Q3+Q4':''}</small></div>${boxes}<div class="cnt ${n?'has':''}">${n}</div>`;
    el.appendChild(row);
  });
}

function renderCheck(ev){
  const pf=$('#pfcards'); pf.innerHTML='';
  ROLES.forEach(([r,l])=>{
    const id=st.roles[r]; const c=document.createElement('div');
    c.className='pfcard '+(id?'':'empty ')+((r==='lk1'||r==='lk2')?'lk':r==='pk5'?'pk':'');
    c.innerHTML=`<span>${l}${r==='pk5'?(st.pkForm==='bll'?' (BLL)':' (Präsentation)'):''}</span><b>${id?S[id].name:'noch offen'}</b>`;
    pf.appendChild(c);
  });
  const stats=$('#stats');
  const st1=(lab,val,max,good)=>`<div class="stat ${good?'good':'bad'}"><span>${lab}</span><b>${val}<em> / ${max}</em></b></div>`;
  stats.innerHTML=st1('Belegte Kurse',ev.total,ev.minK,ev.total>=ev.minK)+st1('Jahreswochenstunden',ev.hours,66,ev.hours>=66)+st1('Einbringpflichtige Grundkurse',ev.einbring,24,ev.einbring<=24)+`<div class="stat"><span>Leistungskurse / Grundkurse</span><b>${ev.lkCourses}<em> / ${ev.gkCourses}</em></b></div>`;

  const cl=$('#checklist'); cl.innerHTML='';
  const groups=[...new Set(ev.R.map(r=>r.group))];
  const order={error:0,warn:1,info:2,ok:3};
  groups.forEach(g=>{
    const h=document.createElement('div'); h.className='clgroup'; h.textContent=g; cl.appendChild(h);
    const ul=document.createElement('ul'); ul.className='cl';
    ev.R.filter(r=>r.group===g).sort((a,b)=>order[a.level]-order[b.level]).forEach(r=>{
      const li=document.createElement('li'); li.className=r.level;
      const ic={ok:'✓',error:'!',warn:'!',info:'i'}[r.level];
      li.innerHTML=`<span class="ic">${ic}</span><span>${r.text}</span>`; ul.appendChild(li);
    });
    cl.appendChild(ul);
  });
  const errs=ev.R.filter(r=>r.level==='error').length, warns=ev.R.filter(r=>r.level==='warn').length;
  $('#sb-k').textContent=ev.total; $('#sb-h').textContent=ev.hours; $('#sb-e').textContent=ev.einbring;
  const sb=$('#sb-state');
  if(errs){sb.className='sb-state bad';sb.textContent=`${errs} ${errs===1?'Punkt':'Punkte'} offen`;}
  else if(warns){sb.className='sb-state warn';sb.textContent=`Zulässig, ${warns} Hinweis${warns>1?'e':''}`;}
  else {sb.className='sb-state ok';sb.textContent='Wahl ist zulässig';}
}

function update(){
  applyProfile();
  normalize();
  save();
  renderAll();
}
function renderAll(){
  document.body.classList.toggle('bll', st.pkForm==='bll');
  renderFaecher(); renderSport(); renderZusatz();
  renderCheck(evaluate());
}

/* =========================== Druckansicht =========================== */
const esc = t => String(t??'').replace(/[&<>"]/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));

// Vorschlag für Beleg- und Einbringpflicht je Fach (aus derselben Logik wie die Checkliste)
function obligations(ev){
  const ein={}, beleg={};
  ev.E.forEach(k=>{ const id=k.split(':')[0]; ein[id]=(ein[id]||0)+1; });
  [st.roles.lk1, st.roles.lk2].filter(Boolean).forEach(id=>{ ein[id]=4; });
  Object.entries(ein).forEach(([id,n])=>beleg[id]=n);
  const up=(id,n)=>{ beleg[id]=Math.max(beleg[id]||0,n); };
  if(st.roles.pk5 && st.roles.pk5!=='spo') up(st.roles.pk5,4);
  Object.entries(st.langs).forEach(([id,v])=>{ if(v==='new') up(id,4); });
  zweitFsMid().forEach(s=>up(s.id,2));
  if(!sportOff()) up('spo',4);
  if(sportRole()) up('spt',2);
  return {ein,beleg};
}

function renderPrint(){
  const ev=evaluate(), ob=obligations(ev);
  const X=a=>a.map(v=>`<td class="x">${v?'X':''}</td>`).join('');
  const sug=n=>`<td class="sug">${n||''}</td>`;
  const roleTxt=id=>roleOf(id).filter(r=>!(r==='pk5')).map(r=>ROLE_LABEL[r]).concat(st.roles.pk5===id?['5. PK']:[]).join(' / ');
  let body='', sumBeleg=0, sumEin=0;

  const subjRow=(s)=>{
    const off=(s.needsWpf&&!st.meta[s.needsWpf])||(s.lang&&st.langs[s.id]==='none');
    const a = s.id==='spo' ? (sportOff()?[false,false,false,false]:st.sport.map(Boolean)) : sems(s.id);
    const n=a.filter(Boolean).length;
    const name = s.id==='spo' ? 'Sport-Praxis' : s.name;
    const extra = s.lang && !off ? `<small>${esc(langLabel(s.id))}</small>` : (s.twin?'<small>bilingual</small>':'');
    const form = st.roles.pk5===s.id ? (st.pkForm==='bll'?'BLL':'Präs.') : '';
    sumEin+=ob.ein[s.id]||0;
    return `<tr class="${n||hasRole(s.id)?'has':''} ${off?'na':''}"><td class="fach">${esc(name)}${extra}</td><td class="role">${roleTxt(s.id)}</td><td class="c">${form}</td>${X(a)}<td class="c">${n||''}</td>${sug(ob.beleg[s.id])}${sug(ob.ein[s.id])}</tr>`;
  };
  const afRow=t=>`<tr class="afh"><td colspan="10">${t}</td></tr>`;
  const AFP={1:'Aufgabenfeld I – sprachlich-literarisch-künstlerisch',2:'Aufgabenfeld II – gesellschaftswissenschaftlich',3:'Aufgabenfeld III – mathematisch-naturwissenschaftlich-technisch'};
  [1,2,3].forEach(af=>{ body+=afRow(AFP[af]); SUBJECTS.filter(s=>s.af===af).forEach(s=>body+=subjRow(s)); });
  body+=afRow('Weitere Fächer');
  body+=subjRow(S.spo);
  const spt=sems('spt'), nspt=spt.filter(Boolean).length; sumEin+=ob.ein.spt||0;
  body+=`<tr class="${nspt?'has':''}"><td class="fach">Sport-Theorie</td><td class="role"></td><td class="c"></td>${X(spt)}<td class="c">${nspt||''}</td>${sug(ob.beleg.spt)}${sug(ob.ein.spt)}</tr>`;
  body+=afRow('Zusatzkurse');
  ZUSATZ.forEach(z=>{
    const a=st.zusatz[z.id]||[false,false,false,false]; const n=a.filter(Boolean).length;
    body+=`<tr class="${n?'has':''}"><td class="fach">${esc(z.name)}<small>zu ${esc(S[z.fach].name)}</small></td><td class="role"></td><td class="c"></td>${X(a)}<td class="c">${n||''}</td><td class="sug"></td><td class="sug"></td></tr>`;
  });

  // Fremdsprachen in Reihenfolge des Beginns
  const ord={early:0,mid:1,new:2};
  const fs=Object.entries(st.langs).filter(([,v])=>v!=='none').sort((a,b)=>langSortKey(a[0])-langSortKey(b[0]));
  const fsLines=[0,1,2].map(i=>{ const f=fs[i]; return `<span>${i+1}. FS:</span><b>${f?`${esc(S[f[0]].name)}, ${esc(langLabel(f[0]))}`:'–'}</b>`; }).join('');

  const errs=ev.R.filter(r=>r.level==='error'), warns=ev.R.filter(r=>r.level==='warn');
  const heute=new Date().toLocaleDateString('de-DE');

  const page1=`<div class="pv-page">
    <div class="pv-head">
      <div><h1>Übersichtsplan Qualifikationsphase</h1><div class="school">Paulsen-Gymnasium Berlin · Schuljahre ${SCHULJAHRE[0]} (Q1/Q2) und ${SCHULJAHRE[1]} (Q3/Q4)</div></div>
      <div class="pv-meta"><span>Zeile (Tabelle der Wahlmöglichkeiten):</span><span class="pv-fill">&nbsp;</span><span>Zweitfach der Präsentation:</span><span class="pv-fill">&nbsp;</span></div>
    </div>
    <div style="display:grid;grid-template-columns:1.3fr 1fr;gap:8mm;margin-bottom:2mm;align-items:end">
      <div class="pv-name">${esc([st.name,st.klasse].map(x=>(x||'').trim()).filter(Boolean).join(', '))||'&nbsp;'}<small>Name, Klasse</small></div>
      <div class="pv-meta">${fsLines}</div>
    </div>
    <table class="pv">
      <colgroup><col style="width:33%"><col style="width:10%"><col style="width:7%"><col style="width:5.5%"><col style="width:5.5%"><col style="width:5.5%"><col style="width:5.5%"><col style="width:8%"><col style="width:10%"><col style="width:10%"></colgroup>
      <thead><tr><th style="text-align:left">Fach</th><th>Prüfungs-<br>fach</th><th>5. PK:<br>Präs. / BLL</th><th>Q1</th><th>Q2</th><th>Q3</th><th>Q4</th><th>Anzahl<br>belegt</th><th>Beleg-<br>pflicht*</th><th>Einbring-<br>pflicht*</th></tr></thead>
      <tbody>${body}</tbody>
      <tfoot>
        <tr><td class="lab" colspan="7">Summe belegter Kurse <small>(mind. ${ev.minK})</small></td><td class="c">${ev.total}</td><td colspan="2"></td></tr>
        <tr><td class="lab" colspan="7">Summe einbringpflichtiger Kurse <small>(höchstens 32, eingebracht werden genau 32)</small></td><td></td><td></td><td class="c">${sumEin}</td></tr>
      </tfoot>
    </table>
    <div class="pv-foot">
      <div>* Kursiv gesetzte Beleg- und Einbringpflichten sind ein Vorschlag des Planers. Verbindlich prüft der Pädagogische Koordinator. LK = Leistungskurs, PF = Prüfungsfach, 5. PK = fünfte Prüfungskomponente. Fehlende Kurse bis zu genau 32 werden nach Q4 aus den weiteren belegten Kursen eingebracht.</div>
      <div style="white-space:nowrap">${ev.hours} Jahreswochenstunden (mind. 66)<br>Stand: ${heute}</div>
    </div>
    <div class="pv-sign"><div>Datum, Unterschrift Schüler/in</div><div>Unterschrift Erziehungsberechtigte</div><div>Genehmigt (Päko)</div></div>
  </div>`;

  const order={error:0,warn:1,info:2,ok:3}, ic={ok:'✓',error:'✗',warn:'!',info:'i'};
  const groups=[...new Set(ev.R.map(r=>r.group))];
  const cl=groups.map(g=>`<div class="pv-h3">${esc(g)}</div><ul class="pv-cl">${ev.R.filter(r=>r.group===g).sort((a,b)=>order[a.level]-order[b.level]).map(r=>`<li class="${r.level}"><b>${ic[r.level]}</b><span>${esc(r.text)}</span></li>`).join('')}</ul>`).join('');
  const status = errs.length ? `Nicht zulässig: ${errs.length} ${errs.length===1?'Punkt muss':'Punkte müssen'} behoben werden` : warns.length ? `Zulässig, ${warns.length} Hinweis${warns.length>1?'e':''} mit dem Päko besprechen` : 'Die Wahl ist nach Prüfung des Planers zulässig';
  const sportRows=[0,1,2,3].map(i=>{
    const c=st.sport[i];
    const theo = sportRole() && i>=2 ? `Sporttheorie ${i-1}${sems('spt')[i]?'':' (nicht angekreuzt)'}` : '';
    return `<tr><td class="c"><b>Q${i+1}</b></td><td>${sportOff()?'befreit':c?esc(SPORT[c]):'– nicht gewählt –'}</td><td class="c">${c&&!sportOff()?c:''}</td><td>${theo}</td></tr>`;
  }).join('');

  const page2=`<div class="pv-page">
    <div class="pv-h2">Prüfergebnis des Planers${st.name?' – '+esc(st.name):''}</div>
    <div class="pv-status">${status}</div>
    <div class="pv-kv">
      ${ROLES.map(([r,l])=>`<div><span>${l}${r==='pk5'?(st.pkForm==='bll'?' (BLL)':' (Präsentation)'):''}</span><b>${st.roles[r]?esc(S[st.roles[r]].name):'offen'}</b></div>`).join('')}
    </div>
    <div class="pv-kv" style="grid-template-columns:repeat(4,1fr)">
      <div><span>Belegte Kurse</span><b>${ev.total} / mind. ${ev.minK}</b></div>
      <div><span>Jahreswochenstunden</span><b>${ev.hours} / mind. 66</b></div>
      <div><span>Einbringpflichtige Grundkurse</span><b>${ev.einbring} / höchstens 24</b></div>
      <div><span>Leistungskurse / Grundkurse</span><b>${ev.lkCourses} / ${ev.gkCourses}</b></div>
    </div>
    <div class="pv-h3">Sportkurse</div>
    <table class="pv" style="width:100%"><colgroup><col style="width:10%"><col style="width:40%"><col style="width:12%"><col style="width:38%"></colgroup>
      <thead><tr><th>Halbjahr</th><th style="text-align:left">Praxiskurs</th><th>Kürzel</th><th style="text-align:left">Zusätzlich</th></tr></thead>
      <tbody>${sportRows}</tbody></table>
    <div class="pv-legend" style="margin-top:3mm">✗ muss behoben werden · ! mit dem Päko besprechen · i Hinweis · ✓ erfüllt</div>
    ${cl}
    <p class="pv-legend" style="margin-top:3mm">Dieser Planer ersetzt nicht die Beratung durch den Pädagogischen Koordinator und nicht den offiziellen digitalen Kurswahlbogen im Lernraum. Grundlage: Kursangebot 19.11.2025, Sportangebot 10.12.2025, VO-GO §§ 23, 25, 26, AV Prüfungen Anlage 6a.</p>
  </div>`;

  $('#printview').innerHTML=page1+page2;
}
window.addEventListener('beforeprint', renderPrint);

/* =========================== Events =========================== */
document.addEventListener('click',e=>{
  const rc=e.target.closest('.rchip'); if(rc){ setRole(rc.dataset.id, rc.dataset.role); update(); return; }
  const qb=e.target.closest('.qbox'); if(qb && !qb.disabled){
    if(qb.dataset.z){
      const a=[...(st.zusatz[qb.dataset.z]||[false,false,false,false])], i=+qb.dataset.i;
      const y=i<2?0:2, on=!(a[y]&&a[y+1]); a[y]=a[y+1]=on;
      st.zusatz[qb.dataset.z]=a;
    }
    else if(qb.dataset.id==='spo'){ return; }
    else toggleSem(qb.dataset.id,+qb.dataset.i);
    update();
  }
});
document.querySelectorAll('[data-meta]').forEach(cb=>{
  cb.checked=!!st.meta[cb.dataset.meta];
  cb.onchange=()=>{
    st.meta[cb.dataset.meta]=cb.checked;
    SUBJECTS.forEach(s=>{ if(s.needsWpf===cb.dataset.meta && !cb.checked){ delete st.sem[s.id]; Object.keys(st.roles).forEach(r=>{if(st.roles[r]===s.id)st.roles[r]=null;}); } });
    if(cb.dataset.meta==='befreit'&&cb.checked){ st.sport=[null,null,null,null]; }
    update();
  };
});
$('#pkform').querySelectorAll('button').forEach(b=>{
  b.classList.toggle('on', st.pkForm===b.dataset.v);
  b.onclick=()=>{ st.pkForm=b.dataset.v; $('#pkform').querySelectorAll('button').forEach(x=>x.classList.toggle('on',x===b));
    if(st.pkForm==='praes' && st.roles.pk5 && isPF(st.roles.pk5)) st.roles.pk5=null; update(); };
});
$('#name').value=st.name||''; $('#name').oninput=e=>{st.name=e.target.value;save();};
$('#klasse').value=st.klasse||''; $('#klasse').oninput=e=>{st.klasse=e.target.value;save();};
$('#jahrgang').value=st.jahrgang||''; $('#jahrgang').oninput=e=>{st.jahrgang=e.target.value;save();};
$('#schuelerid').value=st.schuelerId||''; $('#schuelerid').oninput=e=>{st.schuelerId=e.target.value;save();};
// Vorschläge: aktuelles Schuljahr (Wechsel im August) und die drei folgenden
(()=>{ const now=new Date(), y=now.getMonth()>=7?now.getFullYear():now.getFullYear()-1;
  $('#jahrgang-list').innerHTML=[0,1,2,3].map(i=>`<option value="Abitur ${y+i}/${String((y+i+1)%100).padStart(2,'0')}">`).join(''); })();
function syncControls(){
  document.querySelectorAll('[data-meta]').forEach(cb=>cb.checked=!!st.meta[cb.dataset.meta]);
  $('#pkform').querySelectorAll('button').forEach(b=>b.classList.toggle('on', st.pkForm===b.dataset.v));
  $('#name').value=st.name||'';
  $('#klasse').value=st.klasse||'';
  $('#jahrgang').value=st.jahrgang||'';
  $('#schuelerid').value=st.schuelerId||'';
  renderLangs();
}
$('#print').onclick=()=>{ renderPrint(); window.print(); };
$('#reset').onclick=()=>{
  if(!confirm('Wirklich alle Eingaben löschen?')) return;
  if(SERVER && !localMode()){ st=blankState(); syncControls(); update(); return; }
  lsDel(STORE_KEY); location.reload();
};
/* ---------- Export / Import ---------- */
const EXPORT_APP='kurswahl-planer', EXPORT_VERSION=1;
function showIo(text, kind){
  const m=$('#io-msg'); m.textContent=text; m.className='io-msg '+kind; m.hidden=false;
  clearTimeout(showIo.t); showIo.t=setTimeout(()=>{m.hidden=true;}, 7000);
}
$('#export').onclick=()=>{
  const data={app:EXPORT_APP, version:EXPORT_VERSION, exportiert:new Date().toISOString(), schuljahre:SCHULJAHRE, planer:st};
  const blob=new Blob([JSON.stringify(data,null,2)],{type:'application/json'});
  const safe=(st.name||'').trim().replace(/[^\p{L}\p{N}]+/gu,'-').replace(/^-|-$/g,'');
  const a=document.createElement('a');
  a.href=URL.createObjectURL(blob);
  a.download=`Kurswahl${safe?'_'+safe:''}_${new Date().toISOString().slice(0,10)}.json`;
  document.body.appendChild(a); a.click(); a.remove();
  setTimeout(()=>URL.revokeObjectURL(a.href), 1000);
  showIo('Datei gespeichert. Du findest sie in deinem Download-Ordner.','ok');
};

// Übernimmt nur bekannte Felder mit gültigen Werten – manipulierte oder fremde Dateien richten keinen Schaden an
function sanitizeImport(o){
  const bool4=a=>Array.isArray(a) ? [0,1,2,3].map(i=>a[i]===true) : null;
  const langVals=LANG_OPTS.map(o=>o[0]);
  const n={
    name: typeof o.name==='string' ? o.name.slice(0,80) : '',
    klasse: typeof o.klasse==='string' ? o.klasse.slice(0,20) : '',
    jahrgang: typeof o.jahrgang==='string' ? o.jahrgang.slice(0,30) : '',
    schuelerId: typeof o.schuelerId==='string' ? o.schuelerId.slice(0,10) : '',
    langs:{en:'early',fr:'none',la:'none',sp:'none'},
    meta:{wpfInf:false,wpfSw:false,wpfDs:false,ruderAg:false,befreit:false},
    pkForm: o.pkForm==='bll' ? 'bll' : 'praes',
    roles:{lk1:null,lk2:null,pf3:null,pf4:null,pk5:null},
    sem:{}, sport:[null,null,null,null], zusatz:{}
  };
  n.langKl = o.langs ? {} : {en:3};
  if(o.langs) Object.keys(n.langs).forEach(k=>{ if(langVals.includes(o.langs[k])) n.langs[k]=o.langs[k]; });
  if(o.langKl && typeof o.langKl==='object') Object.keys(n.langs).forEach(k=>{ const kl=parseKl(o.langKl[k]); if(kl){ n.langKl[k]=kl; n.langs[k]=langCat(kl); } });
  if(o.meta) Object.keys(n.meta).forEach(k=>{ n.meta[k]=o.meta[k]===true; });
  if(o.roles){ const prev=st; st=n; Object.keys(n.roles).forEach(r=>{ const id=o.roles[r]; if(S[id] && allowedRoles(S[id]).includes(r) && !n.roles[r]) n.roles[r]=id; }); st=prev; }
  if(o.sem) Object.keys(o.sem).forEach(id=>{ if((S[id]&&id!=='spo') || id==='spt'){ const a=bool4(o.sem[id]); if(a) n.sem[id]=a; } });
  if(Array.isArray(o.sport)) [0,1,2,3].forEach(i=>{ const c=o.sport[i]; if(c && SPORT_BY_SEM[i].includes(c)) n.sport[i]=c; });
  if(o.zusatz) Object.keys(o.zusatz).forEach(id=>{ if(Z[id]){ const a=bool4(o.zusatz[id]); if(a) n.zusatz[id]=a; } });
  return n;
}
$('#import').onclick=()=>$('#importfile').click();
$('#importfile').onchange=async e=>{
  const f=e.target.files[0]; e.target.value='';
  if(!f) return;
  if(f.size>200000){ showIo('Die Datei ist zu groß für eine Kurswahl-Datei.','error'); return; }
  let data;
  try{ data=JSON.parse(await f.text()); }
  catch(err){ showIo('Die Datei konnte nicht gelesen werden. Wähle eine .json-Datei, die du mit „Als Datei sichern“ erstellt hast.','error'); return; }
  if(!data || data.app!==EXPORT_APP || typeof data.planer!=='object'){
    showIo('Das ist keine Datei aus dem Kurswahl-Planer.','error'); return;
  }
  if(data.version>EXPORT_VERSION){ showIo('Die Datei stammt aus einer neueren Version des Planers und kann hier nicht geladen werden.','error'); return; }
  const hasData = st.name || Object.values(st.roles).some(Boolean) || Object.keys(st.sem).some(id=>sems(id).some((v,i)=>v && !lockedSems(id)[i])) || st.sport.some(Boolean) || Object.values(st.zusatz).some(a=>a.some(Boolean));
  const who = data.planer.name ? ` von ${String(data.planer.name).slice(0,80)}` : '';
  if(hasData && !confirm(`Die Kurswahl${who} laden? Deine aktuellen Eingaben werden dabei ersetzt.`)) return;
  st=sanitizeImport(data.planer);
  syncControls(); update();
  const hint = Array.isArray(data.schuljahre) && data.schuljahre.join()!==SCHULJAHRE.join() ? ` Achtung: Die Datei wurde für die Schuljahre ${data.schuljahre.join(' und ')} erstellt.` : '';
  showIo(`Kurswahl${who} geladen.${hint}`, hint?'warn':'ok');
};

/* ---------- Offizielles Kurswahlformular (PDF) befüllen ---------- */
// Feldnamen im Schulformular: "<Fach>$<AF-Code>$<FachId>$<KursId>$<Spalte>_0".
// AF-Code -10 = Zusatzkurs. Bei gleichem Fach+AF-Code (z. B. Geschichte / Geschichte bili)
// entscheidet die Reihenfolge der KursId, die der Zeilenreihenfolge im Formular entspricht.
const FORM_MAP = {
  'Deutsch|reg':['de'], 'Englisch|reg':['en'], 'Französisch|reg':['fr'], 'Spanisch|reg':['sp'],
  'Latein|reg':['la'], 'Musik|reg':['mu'], 'Bildende Kunst|reg':['ku'], 'Darstellendes Spiel|reg':['ds'],
  'Geschichte|reg':['ge','geb'], 'Geografie|reg':['geo'], 'Philosophie|reg':['phi'], 'Psychologie|reg':['psy'],
  'Politikwissenschaft|reg':['pw','pwb'], 'Sozialwissenschaften|reg':['sw'],
  'Mathematik|reg':['ma'], 'Biologie|reg':['bi'], 'Chemie|reg':['ch'], 'Physik|reg':['ph'], 'Informatik|reg':['inf'],
  'Sport|reg':['spo'], 'Sport/Theorie|reg':['spt'],
  'Deutsch|z':['z_ks'], 'Englisch|z':['z_toefl','z_endeb'], 'Musik|z':['z_band','z_chor'],
  'Bildende Kunst|z':['z_tg'], 'Geschichte|z':['z_gere'], 'Politikwissenschaft|z':['z_pwdeb'],
  'Physik|z':['z_rt'], 'Astronomie|z':['z_astro'], 'Studium und Beruf|z':['z_sub']
};
const ROLE_COL = {lk1:'LK1', lk2:'LK2', pf3:'PF3', pf4:'PF4', pk5:'PK5'};

// Ordnet jeder Planer-ID (de, geb, z_band …) den Feld-Präfix im Formular zu
function mapFormFields(fieldNames){
  const groups={};
  fieldNames.forEach(n=>{
    const p=n.split('$'); if(p.length!==5) return;
    const key=p[0]+'|'+(p[1]==='-10'?'z':'reg');
    const prefix=p.slice(0,4).join('$');
    (groups[key]=groups[key]||{})[prefix]=+p[3];
  });
  const map={};
  Object.entries(FORM_MAP).forEach(([key,ids])=>{
    const prefixes=Object.entries(groups[key]||{}).sort((a,b)=>a[1]-b[1]).map(e=>e[0]);
    ids.forEach((id,i)=>{ if(prefixes[i]) map[id]=prefixes[i]; });
  });
  return map;
}

// Die Kurs-Nummern in den Feldnamen ändern sich je Jahrgang. Ein Feld wird deshalb über
// Fach | Kursart | Rang der Kurs-Nummer innerhalb des Fachs | Spalte wiedererkannt.
function fieldKeys(names){
  const groups={}, parts=n=>{ const p=n.split('$'); return p.length===5 ? p : null; };
  names.forEach(n=>{ const p=parts(n); if(!p) return; const g=p[0]+'|'+(p[1]==='-10'?'z':'reg'); (groups[g]=groups[g]||new Set()).add(+p[3]); });
  const sorted=Object.fromEntries(Object.entries(groups).map(([g,set])=>[g,[...set].sort((a,b)=>a-b)]));
  const out=new Map();
  names.forEach(n=>{ const p=parts(n); if(!p) return; const g=p[0]+'|'+(p[1]==='-10'?'z':'reg'); out.set(n, `${g}|${sorted[g].indexOf(+p[3])}|${p[4]}`); });
  return out;
}
const TEMPLATE_KEYS = lazy(()=>fieldKeys(Object.keys(FORM_TPL.checkboxes)));
// Feldnamen der Vorlage → Feldnamen einer konkreten Schul-PDF
function translateChecks(checks, pdfNames){
  const byKey=new Map([...fieldKeys(pdfNames)].map(([n,k])=>[k,n]));
  return checks.map(c=>{ const k=TEMPLATE_KEYS().get(c); return (k && byKey.get(k)) || c; });
}

// Liefert die anzukreuzenden Zellen als [planerId, Spalte, Beschriftung]
function formWants(st, h){
  const out=[];
  const push=(id,label,arr)=>arr.forEach((v,i)=>{ if(v) out.push([id,'Q'+(i+1),`${label} Q${i+1}`]); });
  h.SUBJECTS.forEach(s=>{ if(s.id!=='spo') push(s.id, s.name, h.sems(s.id)); });
  push('spt','Sport-Theorie',h.sems('spt'));
  if(!h.sportOff()) push('spo','Sport',st.sport.map(Boolean));
  Object.entries(st.roles).forEach(([r,id])=>{ if(id) out.push([id, ROLE_COL[r], `${h.ROLE_LABEL[r]} ${h.S[id].name}`]); });
  h.ZUSATZ.forEach(z=>push(z.id, 'Zusatzkurs '+z.name, st.zusatz[z.id]||[]));
  return out;
}

// Das Formular wird so zusammengesetzt, wie der Schulserver (DevExpress unter .NET 9) es erzeugt:
// unveränderte Objekte byte-genau aus der Vorlage, Jahrgang/Name/Klasse/IDs/Datum/Kreuze neu,
// Schrift-Teilmengen, Breiten und ToUnicode nach DevExpress-Art, Kompression mit zlib-ng Level 6.
// Vorlage neu erzeugen: python3 tools/build_form_template.py <Kurswahl-PDF der Schule>
// Formularvorlage und zlib-ng: assets/form-template.js (erzeugt von tools/build_form_template.py)
const b64ToBytes = b => Uint8Array.from(atob(b), c=>c.charCodeAt(0));
const L1 = s => { const u=new Uint8Array(s.length); for(let i=0;i<s.length;i++) u[i]=s.charCodeAt(i)&255; return u; };
const concatBytes = parts => { const out=new Uint8Array(parts.reduce((n,p)=>n+p.length,0)); let o=0; parts.forEach(p=>{ out.set(p,o); o+=p.length; }); return out; };
const hex4 = n => n.toString(16).toUpperCase().padStart(4,'0');
const sameBytes = (a,b) => a.length===b.length && a.every((v,i)=>v===b[i]);

class FormError extends Error {}

/* ---- zlib-ng (WebAssembly) ---- */
let ZNG=null;
async function zng(){
  if(!ZNG){ const {instance}=await WebAssembly.instantiate(b64ToBytes(ZNG_WASM_B64), {}); ZNG=instance.exports; }
  return ZNG;
}
function zngCall(z, fn, input, cap, ...args){
  z.heap_reset();
  const ip=z.zalloc(input.length||1), op=z.zalloc(cap);
  new Uint8Array(z.memory.buffer, ip, input.length).set(input);
  const n=z[fn](ip, input.length, op, cap, ...args);
  if(n<0) throw new Error('zlib-ng '+fn+' '+n);
  return new Uint8Array(z.memory.buffer, op, n).slice();
}
function adler32(u8){ let a=1,b=0; for(let i=0;i<u8.length;){ const end=Math.min(i+3800,u8.length); for(;i<end;i++){ a+=u8[i]; b+=a; } a%=65521; b%=65521; } return ((b<<16)|a)>>>0; }
// DevExpress schreibt den zlib-Kopf 58 85 und hängt Adler-32 an
function flate(z, u8){
  const body=zngCall(z,'deflate_raw',u8,u8.length+(u8.length>>3)+1024,6), ad=adler32(u8);
  return concatBytes([Uint8Array.of(0x58,0x85), body, Uint8Array.of(ad>>>24,(ad>>>16)&255,(ad>>>8)&255,ad&255)]);
}

let FORM_BLOB=null;
const blobPart = ([off,len]) => FORM_BLOB.subarray(off, off+len);
const bytesToL1 = u8 => { let s=''; for(let i=0;i<u8.length;i+=8192) s+=String.fromCharCode.apply(null, u8.subarray(i,i+8192)); return s; };

/* ---- TrueType ---- */
const u16=(b,o)=>(b[o]<<8)|b[o+1], s16=(b,o)=>{ const v=u16(b,o); return v>32767?v-65536:v; }, u32=(b,o)=>((b[o]<<24)|(b[o+1]<<16)|(b[o+2]<<8)|b[o+3])>>>0;
const TT_TABLES=['cvt ','fpgm','head','hhea','hmtx','maxp','prep'];

// Schrift aus der Vorlage: Tabellen und die dort vorhandenen Glyphen
function libFont(key){
  const f=FORM_TPL.fonts[key], tables={};
  TT_TABLES.forEach(t=>{ tables[t]=blobPart(f.tables[t]); });
  const glyphs=new Map(Object.entries(f.glyphs).map(([g,r])=>[+g, blobPart(r)]));
  const cmap=new Map(Object.entries(f.cmap).map(([u,g])=>[+u,g]));
  return {tables, checksums:f.checksums, glyph:g=>glyphs.get(g)||null, gid:u=>cmap.get(u), glyphs};
}

// Vollständige Schrift vom Rechner (Local Font Access)
function parseTTF(buf){
  const b=new Uint8Array(buf);
  if(u32(b,0)!==0x10000) return null;
  const t={};
  for(let i=0,n=u16(b,4);i<n;i++){ const o=12+16*i; t[String.fromCharCode(...b.subarray(o,o+4))]=b.subarray(u32(b,o+8),u32(b,o+8)+u32(b,o+12)); }
  if(!t.head||!t.loca||!t.glyf||!t.cmap) return null;
  const longLoca=s16(t.head,50)===1, n=u16(t.maxp,4);
  const loca=i=>longLoca?u32(t.loca,4*i):2*u16(t.loca,2*i);
  const cmap=new Map();
  const sub=[...Array(u16(t.cmap,2)).keys()].map(i=>({pid:u16(t.cmap,4+8*i),eid:u16(t.cmap,6+8*i),off:u32(t.cmap,8+8*i)}));
  const pick=sub.find(s=>s.pid===3&&s.eid===10)||sub.find(s=>s.pid===3&&s.eid===1);
  if(pick){
    const c=t.cmap, o=pick.off, fmt=u16(c,o);
    if(fmt===4){
      const seg=u16(c,o+6)/2, ends=o+14, starts=ends+2*seg+2, deltas=starts+2*seg, ros=deltas+2*seg;
      for(let s=0;s<seg;s++){
        const end=u16(c,ends+2*s), st=u16(c,starts+2*s), d=u16(c,deltas+2*s), ro=u16(c,ros+2*s);
        for(let u=st;u<=end&&u!==0xFFFF;u++){
          let g= ro===0 ? (u+d)&0xFFFF : u16(c,ros+2*s+ro+2*(u-st));
          if(ro!==0&&g) g=(g+d)&0xFFFF;
          if(g) cmap.set(u,g);
        }
      }
    }else if(fmt===12){
      for(let i=0,k=u32(c,o+12);i<k;i++){ const p=o+16+12*i, a=u32(c,p), e=u32(c,p+4), g=u32(c,p+8); for(let u=a;u<=e;u++) cmap.set(u,g+u-a); }
    }
  }
  return {tables:t, numGlyphs:n, glyph:g=>g<n?t.glyf.subarray(loca(g),loca(g+1)):null, gid:u=>cmap.get(u)};
}

// Die Schrift vom Rechner wird nur verwendet, wenn sie genau der Version des Schulservers entspricht
function mergeLocal(lib, local){
  if(!local) return null;
  if(!TT_TABLES.every(t=>local.tables[t] && sameBytes(local.tables[t], lib.tables[t]))) return null;
  for(const [g,data] of lib.glyphs) if(!sameBytes(local.glyph(g)||new Uint8Array(0), data)) return null;
  return {...lib, glyph:g=>lib.glyph(g)||local.glyph(g), gid:u=>lib.gid(u)??local.gid(u)};
}

const LOCAL_FONT_NAMES={reg:'ArialMT', bold:'Arial-BoldMT', times:'TimesNewRomanPSMT'};
async function localFonts(keys){
  if(!window.queryLocalFonts) return {};
  let list;
  try{ list=await window.queryLocalFonts({postscriptNames:keys.map(k=>LOCAL_FONT_NAMES[k])}); }catch(e){ return {}; }
  const out={};
  for(const k of keys){
    const fd=list.find(f=>f.postscriptName===LOCAL_FONT_NAMES[k]);
    if(fd){ try{ out[k]=parseTTF(await (await fd.blob()).arrayBuffer()); }catch(e){} }
  }
  return out;
}

function glyphComponents(font, g, seen){
  const d=font.glyph(g);
  if(!d||d.length<10||s16(d,0)>=0) return;
  let p=10;
  for(;;){
    const fl=u16(d,p), c=u16(d,p+2); p+=4;
    if(!seen.has(c)){ seen.add(c); glyphComponents(font,c,seen); }
    p+= fl&1 ? 4 : 2;
    if(fl&8) p+=2; else if(fl&0x40) p+=4; else if(fl&0x80) p+=8;
    if(!(fl&0x20)) break;
  }
}
function ttChecksum(b){ let s=0; for(let i=0;i<b.length;i+=4) s=(s+(((b[i]<<24)|((b[i+1]||0)<<16)|((b[i+2]||0)<<8)|(b[i+3]||0))>>>0))>>>0; return s; }

// Teilmenge wie DevExpress: alle Glyph-Plätze bleiben, nur benutzte Glyphen haben Daten, loca lang
function subsetFont(font, textGids){
  const used=new Set(textGids);
  textGids.forEach(g=>glyphComponents(font,g,used));
  const n=u16(font.tables.maxp,4), parts=[], loca=new Uint8Array(4*(n+1));
  let off=0;
  for(let g=0;g<n;g++){
    loca.set([off>>>24,(off>>>16)&255,(off>>>8)&255,off&255],4*g);
    if(used.has(g)){ const d=font.glyph(g); if(d==null) throw new FormError('Glyphe fehlt'); parts.push(d); off+=d.length; }
  }
  loca.set([off>>>24,(off>>>16)&255,(off>>>8)&255,off&255],4*n);
  const glyf=concatBytes(parts);
  const tables={...font.tables, glyf, loca}, tags=Object.keys(tables).sort();
  const dir=new Uint8Array(12+16*tags.length), dv=new DataView(dir.buffer);
  dv.setUint32(0,0x10000); dv.setUint16(4,tags.length); dv.setUint16(6,128); dv.setUint16(8,3); dv.setUint16(10,16);
  const body=[]; let pos=dir.length;
  tags.forEach((t,i)=>{
    const d=tables[t], o=12+16*i;
    for(let k=0;k<4;k++) dir[o+k]=t.charCodeAt(k);
    dv.setUint32(o+4, font.checksums[t] ?? ttChecksum(d)); dv.setUint32(o+8,pos); dv.setUint32(o+12,d.length);
    const pad=(4-d.length%4)%4; body.push(d, new Uint8Array(pad)); pos+=d.length+pad;
  });
  return concatBytes([dir, ...body]);
}

// Breite in 1/1000 em mit höchstens vier Nachkommastellen, wie DevExpress sie schreibt
function fmtWidth(adv, upem){
  const q=Math.floor((2*adv*1e7+upem)/(2*upem)), i=Math.floor(q/1e4), f=String(q%1e4).padStart(4,'0').replace(/0+$/,'');
  return f ? `${i}.${f}` : String(i);
}
function widthsArray(font, gids){
  const upem=u16(font.tables.head,18), hm=font.tables.hmtx, nh=u16(font.tables.hhea,34);
  const adv=g=>u16(hm,4*Math.min(g,nh-1));
  const sorted=[...new Set(gids)].sort((a,b)=>a-b), out=[];
  for(let i=0;i<sorted.length;){
    let j=i; while(j+1<sorted.length && sorted[j+1]===sorted[j]+1) j++;
    out.push(`${sorted[i]} [${sorted.slice(i,j+1).map(g=>fmtWidth(adv(g),upem)).join(' ')}]`);
    i=j+1;
  }
  return out.join(' ');
}

// Glyphen je Schrift in der Reihenfolge ihres ersten Auftretens
function scanGlyphs(content, fontNames, order){
  let cur=null;
  for(const m of content.matchAll(/\/(\w+) [\d.]+ Tf|<([0-9A-F]+)>/g)){
    if(m[1]) cur=fontNames[m[1]]||null;
    else if(cur) for(let i=0;i<m[2].length;i+=4){ const g=parseInt(m[2].slice(i,i+4),16); if(!order[cur].includes(g)) order[cur].push(g); }
  }
}

const pdfName = s => s.replace(/[^A-Za-z0-9]/g, c=>'#'+c.charCodeAt(0).toString(16).toUpperCase().padStart(2,'0'));
const pdfTextHex = s => 'FEFF'+[...s].map(c=>hex4(c.charCodeAt(0))).join('');

/* ---- Formular erzeugen ----
   opts: {name, klasse, jahrgang ("Abitur 2026/27"), schuelerId, jahrgangId, checks:[Feldname], pk:'Praesentation_0'|'BLL_0',
          date:Date, tzMinutes (Offset östlich von UTC), docId:16 Bytes als Hex, fonts:{reg,bold,times} (optional, vom Rechner)} */
async function buildSchoolPdf(opts){
  const z=await zng();
  if(!FORM_BLOB) FORM_BLOB=zngCall(z,'inflate_raw',b64ToBytes(FORM_BLOB_B64),FORM_TPL.blobSize);
  const T=FORM_TPL;
  const lib={reg:libFont('reg'), bold:libFont('bold'), times:libFont('times')};
  const font={...lib};
  const inexact=[];
  Object.entries(opts.fonts||{}).forEach(([k,f])=>{ const m=mergeLocal(lib[k],f); if(m) font[k]=m; else if(f) inexact.push(k); });

  // Unicode je Glyphe für ToUnicode: feste Texte aus der Vorlage, dazu die eingesetzten Zeichen
  const uni={};
  Object.keys(lib).forEach(k=>{ uni[k]=new Map(); Object.entries(T.fonts[k].cmap).forEach(([u,g])=>{ if(!uni[k].has(g)) uni[k].set(g,+u); }); });
  const missing={};
  const gids=(key,text)=>[...text].map(ch=>{
    const cp=ch.codePointAt(0), g=cp>0xFFFF ? null : font[key].gid(cp);
    if(g==null||font[key].glyph(g)==null){ (missing[key]=missing[key]||new Set()).add(ch); return 0; }
    if(!uni[key].has(g)) uni[key].set(g,cp);
    return g;
  });
  const hexOf=(key,text)=>gids(key,text).map(hex4).join('');

  // Seite 1: Jahrgang (mit den Abstandskorrekturen des Schulservers), Name, Klasse
  const jgText=`Jahrgang: ${opts.jahrgang}, GYM_SEK_II`;
  const jgG=gids('reg',jgText), toks=[]; let grp='';
  [...jgText].forEach((ch,i)=>{
    grp+=hex4(jgG[i]);
    const a=T.tjAdjust[ch];
    if(i===jgText.length-1 || a!=='0'){ toks.push(`<${grp}>`); grp=''; if(i<jgText.length-1){ if(a==null) (missing.reg=missing.reg||new Set()).add(ch); toks.push(a); } }
  });
  const page0=bytesToL1(blobPart(T.page0)).replace('\0JAHRGANG\0','[ '+toks.join(' ')+']').replace('\0NAME\0',hexOf('bold',opts.name)).replace('\0KLASSE\0',hexOf('bold',opts.klasse));
  const apText={SchuelerId:hexOf('times',opts.schuelerId), AbiturJahrgangId:hexOf('times',opts.jahrgangId)};
  if(Object.keys(missing).length){ const e=new FormError('Zeichen fehlen'); e.missing=missing; throw e; }

  const page1=bytesToL1(blobPart(T.page1));
  const order={reg:[],bold:[],times:[]};
  scanGlyphs(page0,T.pageFonts[0],order); scanGlyphs(page1,T.pageFonts[1],order);
  const apContent={};
  ['SchuelerId','AbiturJahrgangId'].forEach(k=>{ apContent[k]=T.apTemplate.replace('\0TEXT\0',apText[k]); scanGlyphs(apContent[k],{F0:'times'},order); });

  const pad=n=>String(n).padStart(2,'0'), d=opts.date, tz=opts.tzMinutes;
  const local=new Date(d.getTime()+tz*60000);
  const ymd=`${local.getUTCFullYear()}${pad(local.getUTCMonth()+1)}${pad(local.getUTCDate())}`, hms=`${pad(local.getUTCHours())}${pad(local.getUTCMinutes())}${pad(local.getUTCSeconds())}`;
  const sign=tz<0?'-':'+', th=pad(Math.floor(Math.abs(tz)/60)), tm=pad(Math.abs(tz)%60);
  const pdfDate=`D:${ymd}${hms}${sign}${th}'${tm}'`;
  const xmpDate=`${ymd.slice(0,4)}-${ymd.slice(4,6)}-${ymd.slice(6)}T${hms.slice(0,2)}:${hms.slice(2,4)}:${hms.slice(4)}${sign}${th}:${tm}`;

  const streamObj=(tpl,data,len1)=>concatBytes([L1(tpl.replace('\0LEN\0',data.length).replace('\0LEN1\0',len1)), L1('\r\nstream\r\n'), data, L1('\r\nendstream\r\nendobj\r\n')]);
  const dyn=new Map(), objAt=new Map();
  T.objs.reduce((off,[n,len])=>{ objAt.set(n,[off,len]); return off+len; }, T.objStart);
  const tplOf=n=>bytesToL1(blobPart(objAt.get(n)));
  const checks=new Set(opts.checks||[]);
  const cbObjs=new Map(Object.entries(T.checkboxes).map(([k,n])=>[n,k]));

  Object.entries(T.fonts).forEach(([k,f])=>{
    const tu=T.tounicode.replace('\0BFCHAR\0', `${order[k].length} beginbfchar\r\n`+order[k].map(g=>`<${hex4(g)}> <${hex4(uni[k].get(g))}>\r\n`).join('')+'endbfchar');
    dyn.set(f.obj.tounicode, streamObj(tplOf(f.obj.tounicode), flate(z,L1(tu))));
    dyn.set(f.obj.cid, L1(tplOf(f.obj.cid).replace('\0W\0', widthsArray(font[k],order[k]))));
    const ttf=subsetFont(font[k],order[k]);
    dyn.set(f.obj.file, streamObj(tplOf(f.obj.file), flate(z,ttf), ttf.length));
  });
  dyn.set(T.contents0, streamObj(tplOf(T.contents0), flate(z,L1(page0))));
  ['SchuelerId','AbiturJahrgangId'].forEach(k=>{
    const id=T.ids[k], v=k==='SchuelerId'?opts.schuelerId:opts.jahrgangId;
    dyn.set(id.ap, streamObj(tplOf(id.ap), flate(z,L1(apContent[k]))));
    dyn.set(id.field, L1(tplOf(id.field).replace('\0V\0', pdfTextHex(v))));
  });
  dyn.set(T.info, L1(tplOf(T.info).replaceAll('\0DATE\0', [...pdfDate].map(c=>c.charCodeAt(0).toString(16).toUpperCase().padStart(2,'0')).join(''))));
  const xmp=tplOf(T.meta).replace(/\0DATE\0/g, xmpDate);
  dyn.set(T.meta, L1(xmp));
  cbObjs.forEach((name,n)=>{ let s=tplOf(n); if(checks.has(name)) s=s.replace('/AS /Off','/AS /Yes').replace('/V /Off','/V /Yes'); dyn.set(n, L1(s)); });
  const pk=opts.pk||'Praesentation_0';
  dyn.set(T.radio.parent, L1(tplOf(T.radio.parent).replace(/\/V \/\S+/, '/V /'+pdfName(pk))));
  Object.entries(T.radio.kids).forEach(([on,n])=>dyn.set(n, L1(tplOf(n).replace(/\/AS \/\S+/, '/AS /'+(on===pk?pdfName(on):'Off')))));

  // Zusammensetzen mit xref-Tabelle
  const parts=[blobPart(T.header)], offsets=new Map(); let pos=parts[0].length;
  const isDyn=new Set(T.dynamic);
  T.objs.forEach(([n])=>{
    const b=isDyn.has(n) ? dyn.get(n) : blobPart(objAt.get(n));
    if(!b) throw new Error('Objekt '+n+' fehlt');
    offsets.set(n,pos); parts.push(b); pos+=b.length;
  });
  let xref=`xref\r\n0 ${T.size}\r\n`;
  for(let i=0;i<T.size;i++) xref+= (T.free[i] ?? `${String(offsets.get(i)).padStart(10,'0')} 00000 n`)+'\r\n';
  const id=opts.docId.toUpperCase();
  xref+=T.trailer.replaceAll('\0ID\0',id)+`startxref\r\n${pos}\r\n%%EOF\r\n`;
  parts.push(L1(xref));
  return {bytes:concatBytes(parts), inexact};
}

/* ---- Eigene Schul-PDF: Kreuze setzen, Stammdaten lesen ---- */
const utf16FromHex = h => { let s=''; for(let i=4;i<h.length;i+=4) s+=String.fromCharCode(parseInt(h.slice(i,i+4),16)); return h.startsWith('FEFF') ? s : ''; };

// Zerlegt eine PDF des Schulservers in ihre Objekte (Dateireihenfolge), xref-Tabelle und Trailer
function splitSchoolPdf(bytes){
  const s=bytesToL1(bytes);
  const first=s.search(/\r\n\d+ 0 obj\r\n/);
  const xrefPos=s.lastIndexOf('\r\nxref\r\n');
  if(!s.startsWith('%PDF-') || first<0 || xrefPos<0) throw new FormError('Das ist keine Kurswahl-PDF des Schulservers.');
  const objs=[]; let pos=first+2;
  while(pos<xrefPos+2){
    const m=/^(\d+) 0 obj\r\n/.exec(s.slice(pos,pos+24));
    if(!m) throw new FormError('Unbekannter Aufbau der PDF.');
    let e=s.indexOf('endobj',pos);
    const so=s.indexOf('>>\r\nstream\r\n',pos);
    if(so>=0 && so<e){ const len=+/\/Length (\d+)/.exec(s.slice(pos,so))[1]; e=s.indexOf('endobj',so+12+len); }
    objs.push({n:+m[1], start:pos, end:e+8}); pos=e+8;
  }
  const xm=/^xref\r\n0 (\d+)\r\n/.exec(s.slice(xrefPos+2));
  if(!xm) throw new FormError('Unbekannter Aufbau der PDF.');
  const size=+xm[1], entriesAt=xrefPos+2+xm[0].length;
  const entries=[...Array(size).keys()].map(i=>s.slice(entriesAt+20*i, entriesAt+20*i+18));
  const trailer=s.slice(entriesAt+20*size, s.lastIndexOf('startxref'));
  const text=new Map(objs.map(o=>[o.n, s.slice(o.start,o.end)]));
  return {s, objs, entries, trailer, header:s.slice(0,first+2), text};
}

// Setzt in der eigenen Schul-PDF genau die gewünschten Kreuze und die Form der 5. PK; alles andere bleibt byte-gleich
// Kontrollkästchen der PDF: Objektnummer → Feldname
function checkboxFields(P){
  const out=new Map();
  P.objs.forEach(({n})=>{
    const t=P.text.get(n);
    if(!t.includes('/FT /Btn') || t.includes('/Kids') || t.includes('/Parent ')) return;
    const m=/\/T <([0-9A-F]+)>/.exec(t); if(m) out.set(n, utf16FromHex(m[1]));
  });
  return out;
}
function patchSchoolPdf(bytes, checks, pk){
  const P=splitSchoolPdf(bytes), boxes=checkboxFields(P), changed=new Map(), found=new Set();
  checks=translateChecks(checks, [...boxes.values()]);
  const want=new Set(checks);
  let radioParent=null;
  P.objs.forEach(({n})=>{
    const t=P.text.get(n);
    if(t.includes('/FT /Btn') && t.includes('/Kids')){ radioParent=n; changed.set(n, t.replace(/\/V \/\S+/, '/V /'+pdfName(pk))); return; }
    if(!boxes.has(n)) return;
    const name=boxes.get(n), on=want.has(name); if(on) found.add(name);
    const v=on?'/Yes':'/Off';
    changed.set(n, t.replace(/\/AS \/(Yes|Off)/, '/AS '+v).replace(/\/V \/(Yes|Off)/, '/V '+v));
  });
  const notFound=checks.filter(c=>!found.has(c));
  if(notFound.length) throw new FormError('Diese Felder fehlen in deiner Schul-PDF: '+notFound.join(', '));
  if(radioParent!==null) P.objs.forEach(({n})=>{
    const t=P.text.get(n);
    if(!t.includes(`/Parent ${radioParent} 0 R`)) return;
    const ap=P.text.get(+/\/AP (\d+) 0 R/.exec(t)[1]);
    const onName=[...ap.split('/N')[1].split('>>')[0].matchAll(/\/(\S+) \d+ 0 R/g)].map(x=>x[1]).find(x=>x!=='Off');
    const plain=onName.replace(/#([0-9A-F]{2})/g,(_,h)=>String.fromCharCode(parseInt(h,16)));
    changed.set(n, t.replace(/\/AS \/\S+/, '/AS /'+(plain===pk?onName:'Off')));
  });
  const parts=[bytes.subarray(0,P.header.length)], offsets=new Map(); let pos=P.header.length;
  P.objs.forEach(o=>{
    const b=changed.has(o.n) ? L1(changed.get(o.n)) : bytes.subarray(o.start,o.end);
    offsets.set(o.n,pos); parts.push(b); pos+=b.length;
  });
  let xref=`xref\r\n0 ${P.entries.length}\r\n`;
  P.entries.forEach((e,i)=>{ xref+=(e.endsWith(' f') ? e : `${String(offsets.get(i)).padStart(10,'0')} 00000 n`)+'\r\n'; });
  parts.push(L1(xref+P.trailer+`startxref\r\n${pos}\r\n%%EOF\r\n`));
  return concatBytes(parts);
}

// Liest Name, Klasse, Jahrgang, IDs, Erstelldatum und Kreuze aus einer Schul-PDF
async function readSchoolPdf(bytes){
  const z=await zng(), P=splitSchoolPdf(bytes), s=P.s;
  if(!s.includes('/Producer <FEFF'+[...'Developer Express'].map(c=>hex4(c.charCodeAt(0))).join(''))) throw new FormError('Die PDF stammt nicht vom Schulserver.');
  const stream=n=>{
    const t=P.text.get(n), i=t.indexOf('stream\r\n')+8, len=+/\/Length (\d+)/.exec(t)[1];
    const o=P.objs.find(x=>x.n===n), data=bytes.subarray(o.start+i+2, o.start+i+len-4);
    return bytesToL1(zngCall(z,'inflate_raw',data,Math.max(1<<20,data.length*30)));
  };
  const ref=(t,k)=>{ const m=new RegExp('/'+k+' (\\d+) 0 R').exec(t); return m?+m[1]:null; };
  const pages=P.text.get(ref(P.text.get(ref(P.trailer,'Root')),'Pages'));
  const content=stream(ref(P.text.get(+/\/Kids \[(\d+) 0 R/.exec(pages)[1]),'Contents'));
  const maps=[...s.matchAll(/\/BaseFont \/DEVEXP#2BArial\S* \/Encoding \/Identity#2DH \/ToUnicode (\d+) 0 R/g)].map(m=>{
    const map={}; for(const [,g,u] of stream(+m[1]).matchAll(/<([0-9A-F]{4})> <([0-9A-F]{4})>/g)) map[g]=String.fromCharCode(parseInt(u,16)); return map;
  });
  const texts=[...content.matchAll(/\[([^\]]*)\] TJ|<([0-9A-F]+)> Tj/g)].flatMap(m=>{
    const hex=m[1] ? [...m[1].matchAll(/<([0-9A-F]+)>/g)].map(x=>x[1]).join('') : m[2];
    return maps.map(map=>(hex.match(/.{4}/g)||[]).map(g=>map[g]??'�').join(''));
  });
  const after=p=>{ const t=texts.find(x=>x.startsWith(p)&&!x.includes('�')); if(t==null) throw new FormError('Das ist kein Kurswahlformular der Schule.'); return t.slice(p.length).trim(); };
  const field=k=>{ const m=new RegExp('/T <FEFF'+[...k].map(c=>hex4(c.charCodeAt(0))).join('')+'>(?:(?!endobj)[^])*?/V <([0-9A-F]*)>').exec(s); return m?utf16FromHex(m[1]):''; };
  const info=P.text.get(ref(P.trailer,'Info'))||'';
  const dm=/\/CreationDate <([0-9A-F]+)>/.exec(info), d=dm ? dm[1].match(/../g).map(h=>String.fromCharCode(parseInt(h,16))).join('') : '';
  const dp=/D:(\d{4})(\d\d)(\d\d)(\d\d)(\d\d)/.exec(d);
  return {
    name:after('Name: '), klasse:after('Klasse: '), jahrgang:after('Jahrgang: ').replace(/,\s*GYM_SEK_II$/,''),
    schuelerId:field('SchuelerId'), jahrgangId:field('AbiturJahrgangId'),
    pdfCreatedAt: dp ? `${dp[1]}-${dp[2]}-${dp[3]} ${dp[4]}:${dp[5]}:00` : null,
    names:[...checkboxFields(P).values()],
    checks:[...checkboxFields(P)].filter(([n])=>/\/V \/Yes/.test(P.text.get(n))).map(([,name])=>name),
    pk:(/\/Ff 49152 \/V \/(\S+)/.exec(s)||[,''])[1].replace(/#([0-9A-F]{2})/g,(_,h)=>String.fromCharCode(parseInt(h,16))),
  };
}

// Kreuze aus einem Formular zurück in den Planer übernehmen (Sportkurse lassen sich nicht ablesen)
function applyFormChecks(checks, pk, pdfNames){
  const names=pdfNames&&pdfNames.length ? pdfNames : Object.keys(FORM_TPL.checkboxes), map=mapFormFields(names), rev={};
  Object.entries(map).forEach(([id,prefix])=>{ rev[prefix]=id; });
  const roleOf=Object.fromEntries(Object.entries(ROLE_COL).map(([r,c])=>[c,r]));
  st.roles={lk1:null,lk2:null,pf3:null,pf4:null,pk5:null}; st.sem={}; st.zusatz={};
  const sport=[false,false,false,false];
  checks.forEach(f=>{
    const m=/^(.*)\$([A-Z0-9]+)_0$/.exec(f); if(!m) return;
    const id=rev[m[1]], col=m[2]; if(!id) return;
    if(roleOf[col]){ st.roles[roleOf[col]]=id; return; }
    const q=/^Q([1-4])$/.exec(col); if(!q) return;
    const i=+q[1]-1;
    if(id==='spo') sport[i]=true;
    else if(id.startsWith('z_')){ (st.zusatz[id]=st.zusatz[id]||[false,false,false,false])[i]=true; }
    else { (st.sem[id]=st.sem[id]||[false,false,false,false])[i]=true; }
  });
  st.sport=st.sport.map((c,i)=>sport[i]?c:null);
  st.pkForm = pk==='BLL_0' ? 'bll' : 'praes';
  return sport.some((v,i)=>v && !st.sport[i]);
}

/* ---- Ablage der eigenen PDF im Browser (IndexedDB) ---- */
function idb(){
  return new Promise((res,rej)=>{ const r=indexedDB.open('kurswahl-planer',1); r.onupgradeneeded=()=>r.result.createObjectStore('pdf'); r.onsuccess=()=>res(r.result); r.onerror=()=>rej(r.error); });
}
async function idbDo(mode, fn){
  const db=await idb();
  return new Promise((res,rej)=>{ const tx=db.transaction('pdf',mode), req=fn(tx.objectStore('pdf')); tx.oncomplete=()=>res(req&&req.result); tx.onerror=()=>rej(tx.error); });
}
const localPdfGet = () => idbDo('readonly', os=>os.get(LOCAL_PDF_KEY));
const localPdfPut = b => idbDo('readwrite', os=>os.put(b, LOCAL_PDF_KEY));
const localPdfDel = () => idbDo('readwrite', os=>os.delete(LOCAL_PDF_KEY));

async function schoolPdfBytes(){
  if(localMode()){
    const b=await localPdfGet();
    if(!b) throw new FormError('Deine hochgeladene PDF ist in diesem Browser nicht mehr vorhanden. Lade sie erneut hoch.');
    return new Uint8Array(b);
  }
  const r=await fetch(SERVER && BOOT.pdfUrl || 'api/pdf',{credentials:'same-origin'});
  if(r.status===401) throw new FormError('Du bist abgemeldet. Bitte melde dich neu an.');
  if(!r.ok) throw new FormError('Deine Kurswahl-PDF konnte nicht vom Server geladen werden.');
  return new Uint8Array(await r.arrayBuffer());
}

/* ---- Nur lesen ---- */
const READONLY_IDS=['#modebar','#sec-wizard','#sec-basis','#sec-faecher','#sec-sport','#sec-zusatz','.hero-card .namefield'];
function applyReadOnly(){
  readOnly=true;
  if(document.body.classList.contains('mode-wizard')) setMode('check');
  READONLY_IDS.forEach(sel=>document.querySelectorAll(sel).forEach(el=>{ el.inert=true; el.classList.add('kw-ro'); }));
  ['#import','#reset'].forEach(sel=>{ const b=$(sel); if(b) b.hidden=true; });
  document.querySelectorAll('.kw-account .kw-up,.kw-account .kw-submit').forEach(b=>{ b.hidden=true; });
  document.body.classList.add('kw-readonly');
}
const fmtDeadline = d => fmtDateTime(d)+' Uhr';
function lockText(){
  const L=BOOT.lock||{};
  if(BOOT.adminView) return `Ansicht als Admin – nur lesen${L.submittedAt?` · abgegeben am ${fmtDateTime(L.submittedAt)}`:''}`;
  if(L.submittedAt) return `Abgegeben am ${fmtDateTime(L.submittedAt)} – Änderungen nur nach Freischaltung durch die Schule`;
  if(L.expired && L.mode!=='hint') return `Abgabefrist am ${fmtDeadline(L.deadline)} abgelaufen – nur noch ansehen`;
  return null;
}
async function submitChoice(){
  const ev=evaluate(), errs=ev.R.filter(r=>r.level==='error').length;
  if(errs && !confirm(`Deine Wahl ist laut Planer noch nicht zulässig (${errs} ${errs===1?'Punkt':'Punkte'} offen). Trotzdem abgeben?`)) return;
  if(!confirm('Wahl jetzt verbindlich abgeben?\n\nDanach kannst du nichts mehr ändern, bis die Schule deine Wahl wieder freischaltet.')) return;
  if(dirty||saving||!srvVersion){ clearTimeout(saveTimer); await serverSave(); }
  for(let i=0;i<20&&saving;i++) await new Promise(r=>setTimeout(r,150));
  if(dirty){ showIo('Deine Wahl konnte nicht gespeichert werden – Abgabe abgebrochen.','error'); return; }
  try{
    const r=await fetch('api/submit',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':BOOT.csrf,'Accept':'application/json'},body:JSON.stringify({version:srvVersion})});
    const j=await r.json().catch(()=>({}));
    if(!r.ok){ showIo(j.error||'Die Abgabe ist fehlgeschlagen.','error'); return; }
    BOOT.lock={...(BOOT.lock||{}), submittedAt:j.submittedAt, locked:true};
    applyReadOnly(); setSync('locked', lockText());
    showIo('Deine Wahl ist abgegeben. Lade jetzt dein Kurswahlformular herunter, prüfe es und gib es ab.','ok');
  }catch(e){ showIo('Die Abgabe ist fehlgeschlagen. Prüfe deine Internetverbindung.','error'); }
}

/* ---- Kontoleiste: Anmeldung, Speicherstand, eigene PDF ---- */
function setupAccountBar(){
  const bar=document.createElement('div'); bar.className='kw-account';
  const who=document.createElement('span'); who.className='kw-who';
  who.textContent = !SERVER ? 'Ohne Anmeldung – alles bleibt in diesem Browser'
    : BOOT.adminView ? `Wahl von ${(BOOT.profile&&BOOT.profile.name)||BOOT.displayName||BOOT.login} (${BOOT.login})` : `Angemeldet als ${BOOT.login}`;
  syncEl=document.createElement('span'); syncEl.className='kw-sync';
  const file=document.createElement('input'); file.type='file'; file.accept='.pdf,application/pdf'; file.hidden=true;
  const up=document.createElement('button'); up.type='button'; up.className='btn kw-up'; up.textContent='Eigene Schul-PDF hochladen';
  const sub=document.createElement('button'); sub.type='button'; sub.className='btn primary kw-submit'; sub.textContent='Wahl abgeben';
  sub.hidden=!SERVER || localMode(); sub.onclick=submitChoice;
  const dl=document.createElement('span'); dl.className='kw-deadline';
  const L=SERVER?BOOT.lock||{}:{};
  if(L.deadline && !L.expired) dl.textContent=`Abgabe bis ${fmtDeadline(L.deadline)}`;
  else if(L.deadline && L.mode==='hint') dl.textContent=`Abgabefrist am ${fmtDeadline(L.deadline)} abgelaufen`;
  const rm=document.createElement('button'); rm.type='button'; rm.className='btn'; rm.textContent=SERVER?'Eigene PDF entfernen (Server-Stand laden)':'Eigene PDF entfernen';
  rm.hidden=!localMode();
  up.onclick=()=>file.click();
  file.onchange=async()=>{
    const f=file.files[0]; file.value=''; if(!f) return;
    if(f.size>10*1024*1024){ showIo('Die Datei ist zu groß für eine Kurswahl-PDF.','error'); return; }
    let bytes, info;
    try{ bytes=new Uint8Array(await f.arrayBuffer()); info=await readSchoolPdf(bytes); patchSchoolPdf(bytes,[],info.pk||'Praesentation_0'); }
    catch(e){ showIo(e instanceof FormError ? e.message : 'Die PDF konnte nicht gelesen werden. Lade die Kurswahl-PDF hoch, die du von der Schule bekommen hast.','error'); console.error(e); return; }
    if(SERVER && BOOT.profile && info.name!==BOOT.profile.name && !confirm(`Die PDF gehört zu „${info.name}“, dein Konto zu „${BOOT.profile.name}“. Trotzdem verwenden?`)) return;
    if(!confirm(`PDF von ${info.name} (${info.klasse}) verwenden?\n\n`+(SERVER
      ? 'Ab jetzt bleiben deine Daten und deine Wahl nur in diesem Browser und werden nicht mehr auf dem Server gespeichert. Mit „Eigene PDF entfernen“ kehrst du zum Server-Stand zurück.'
      : 'Name, Klasse, Jahrgang und Schüler-ID werden aus der PDF übernommen.')
      + (info.checks.length?`\n\nDie PDF enthält ${info.checks.length} Kreuze – sie ersetzen deine aktuelle Wahl.`:''))) return;
    try{ await localPdfPut(bytes.buffer.slice(bytes.byteOffset, bytes.byteOffset+bytes.byteLength)); }
    catch(e){ showIo('Die PDF konnte in diesem Browser nicht gespeichert werden (privates Fenster?).','error'); return; }
    localProfile={name:info.name, klasse:info.klasse, jahrgang:info.jahrgang, schuelerId:info.schuelerId, jahrgangId:info.jahrgangId, pdfCreatedAt:info.pdfCreatedAt};
    lsSet(LOCAL_PDF_KEY, JSON.stringify(localProfile));
    clearTimeout(saveTimer); dirty=false;
    const sportOpen = info.checks.length ? applyFormChecks(info.checks, info.pk, info.names) : false;
    rm.hidden=false; setSync('local');
    syncControls(); update();
    showIo('Eigene PDF übernommen.'+(sportOpen?' Wähle bitte deine Sportkurse im Planer – die lassen sich aus der PDF nicht ablesen.':''), sportOpen?'warn':'ok');
  };
  rm.onclick=async()=>{
    if(!confirm(SERVER ? 'Eigene PDF und die nur hier gespeicherte Wahl löschen und zum Stand auf dem Server zurückkehren?' : 'Eigene PDF entfernen?')) return;
    try{ await localPdfDel(); }catch(e){}
    lsDel(LOCAL_PDF_KEY); if(SERVER) lsDel(STORE_KEY);
    location.reload();
  };
  bar.append(who, syncEl, dl, sub, up, rm, file);
  if(SERVER && BOOT.adminView){
    const back=document.createElement('a'); back.className='btn'; back.href=BOOT.adminUrl||'admin'; back.textContent='Zurück zur Übersicht';
    bar.append(back);
  } else if(SERVER){
    const out=document.createElement('form'); out.method='post'; out.action='logout'; out.className='kw-logout';
    const t=document.createElement('input'); t.type='hidden'; t.name='_csrf'; t.value=BOOT.csrf;
    const b=document.createElement('button'); b.type='submit'; b.className='btn'; b.textContent='Abmelden';
    out.onsubmit=e=>{ if((dirty||saving) && !confirm('Deine letzte Änderung ist noch nicht gespeichert. Trotzdem abmelden?')) e.preventDefault(); };
    out.append(t,b); bar.append(out);
  }
  document.querySelector('header.hero .wrap').prepend(bar);
  if(localMode()) setSync('local');
  else if(SERVER && lockText()) setSync('locked', lockText());
  else if(SERVER) setSync(BOOT.savedAt?'saved':'pending', BOOT.savedAt||undefined);
  if(SERVER && !localMode() && !lockText() && !BOOT.savedAt) syncEl.textContent='Noch nichts gespeichert';
  // Felder aus der Schul-PDF sind nicht änderbar
  const p=profile();
  ['#name','#klasse','#jahrgang','#schuelerid'].forEach(sel=>{ const el=$(sel); el.readOnly=!!p; el.title=p?'Aus deiner Kurswahl-PDF der Schule':''; });
}
document.querySelectorAll('img[data-hide-on-error]').forEach(img=>{ img.addEventListener('error',()=>img.remove()); if(img.complete && !img.naturalWidth) img.remove(); });

const FONT_LABEL={reg:'Arial', bold:'Arial Fett', times:'Times New Roman'};
const JAHRGANG_RE=/^Abitur (\d{4})\/(\d{2})$/;
// AbiturJahrgangId der Schule: bekannt ist nur Abitur 2026/27 → 6; angenommen wird ein Schritt pro Jahr
const jahrgangId = jg => { const m=JAHRGANG_RE.exec(jg); return m && +m[2]===(+m[1]+1)%100 ? String(+m[1]-2020) : null; };
// Schriften, deren Zeichen nicht in der Vorlage stecken und vom Rechner geladen werden müssen
function fontsNeeded(texts){
  return Object.entries(texts).filter(([k,t])=>[...t].some(ch=>FORM_TPL.fonts[k].cmap[ch.codePointAt(0)]==null)).map(([k])=>k);
}
// Kreuze für das Formular aus der aktuellen Wahl
function formChecks(){
  const names=Object.keys(FORM_TPL.checkboxes), map=mapFormFields(names), checks=[], missing=[];
  formWants(st,{SUBJECTS,S,ZUSATZ,ROLE_LABEL,sems,sportOff}).forEach(([id,col,lbl])=>{
    const f=map[id] && `${map[id]}$${col}_0`;
    if(f && names.includes(f)) checks.push(f); else missing.push(lbl);
  });
  return {checks, missing};
}
const fileStamp = d => { const z=n=>String(n).padStart(2,'0'); return `${d.getFullYear()}${z(d.getMonth()+1)}${z(d.getDate())}${z(d.getHours())}${z(d.getMinutes())}`; };
const fileName = (klasse, jahrgang, name, stamp) => { const part=t=>(t||'').trim().replace(/[^\p{L}\p{N}]+/gu,'_').replace(/^_|_$/g,''); return [part(klasse),'Kurswahl',part(jahrgang),part(name),stamp].filter(Boolean).join('_')+'.pdf'; };
function downloadPdf(bytes, fname){
  const a=document.createElement('a');
  a.href=URL.createObjectURL(new Blob([bytes],{type:'application/pdf'})); a.download=fname;
  document.body.appendChild(a); a.click(); a.remove();
  setTimeout(()=>URL.revokeObjectURL(a.href), 1000);
}
// Mit Schul-PDF: genau diese Datei, nur die Kreuze und die Form der 5. PK gesetzt
async function fillFromSchoolPdf(p){
  const errs=evaluate().R.filter(r=>r.level==='error');
  if(errs.length && !confirm(`Deine Wahl ist laut Planer noch nicht zulässig (${errs.length} ${errs.length===1?'Punkt':'Punkte'} offen). Trotzdem das Kurswahlformular erstellen?`)) return;
  const btn=$('#fillform'); btn.disabled=true; const label=btn.textContent; btn.textContent='Formular wird erstellt …';
  try{
    const {checks, missing}=formChecks();
    if(missing.length){ showIo(`Nicht erstellt: Diese Kurse gibt es im Kurswahlformular nicht: ${missing.join(', ')}.`,'error'); return; }
    const out=patchSchoolPdf(await schoolPdfBytes(), checks, st.pkForm==='bll'?'BLL_0':'Praesentation_0');
    const created=p.pdfCreatedAt ? new Date(String(p.pdfCreatedAt).replace(' ','T')) : new Date();
    downloadPdf(out, fileName(p.klasse, p.jahrgang, p.name, fileStamp(isNaN(created)?new Date():created)));
    showIo(`Kurswahlformular erstellt (${checks.length} Kreuze) und heruntergeladen. Öffne die PDF, prüfe sie und drucke sie in Originalgröße (100 %, nicht „an Seite anpassen“).`,'ok');
  }catch(err){
    showIo(err instanceof FormError ? err.message : 'Das Kurswahlformular konnte nicht erstellt werden.','error');
    console.error(err);
  }finally{ btn.disabled=false; btn.textContent=label; }
}
$('#fillform').onclick=async()=>{
  const prof=profile();
  if(prof) return fillFromSchoolPdf(prof);
  const name=(st.name||'').trim(), klasse=(st.klasse||'').trim(), jahrgang=(st.jahrgang||'').trim().replace(/\s*,?\s*GYM_SEK_II$/i,''), sid=(st.schuelerId||'').trim();
  if(!JAHRGANG_RE.test(jahrgang) || !jahrgangId(jahrgang)){ showIo('Gib den Jahrgang genau wie im Formular der Schule an, zum Beispiel „Abitur 2026/27“.','error'); $('#jahrgang').focus(); return; }
  if(!/^\d*$/.test(sid)){ showIo('Die Schüler-ID besteht nur aus Ziffern.','error'); $('#schuelerid').focus(); return; }
  const jid=jahrgangId(jahrgang);
  // Schriften vom Rechner sofort anfragen, solange der Klick als Nutzeraktion gilt
  const need=fontsNeeded({bold:name+klasse, reg:jahrgang, times:sid+jid});
  const fontsPromise=need.length ? localFonts(need) : Promise.resolve({});
  const errs=evaluate().R.filter(r=>r.level==='error');
  if(errs.length && !confirm(`Deine Wahl ist laut Planer noch nicht zulässig (${errs.length} ${errs.length===1?'Punkt':'Punkte'} offen). Trotzdem das Kurswahlformular erstellen?`)) return;
  const leer=[!name&&'Name', !klasse&&'Klasse', !sid&&'Schüler-ID'].filter(Boolean);
  if(leer.length && !confirm(`${leer.length>1?leer.slice(0,-1).join(', ')+' und '+leer[leer.length-1]:leer[0]} ${leer.length>1?'sind':'ist'} nicht ausgefüllt und ${leer.length>1?'bleiben':'bleibt'} im Formular leer. Trotzdem erstellen?`)) return;
  const btn=$('#fillform'); btn.disabled=true; const label=btn.textContent; btn.textContent='Formular wird erstellt …';
  try{
    const {checks, missing}=formChecks();
    if(missing.length){ showIo(`Nicht erstellt: Diese Kurse gibt es im Kurswahlformular nicht: ${missing.join(', ')}.`,'error'); return; }
    const date=new Date(), docId=Array.from(crypto.getRandomValues(new Uint8Array(16)),b=>b.toString(16).padStart(2,'0')).join('');
    let r;
    try{
      r=await buildSchoolPdf({name, klasse, jahrgang, schuelerId:sid, jahrgangId:jid, checks, pk:st.pkForm==='bll'?'BLL_0':'Praesentation_0',
        date, tzMinutes:-date.getTimezoneOffset(), docId, fonts:await fontsPromise});
    }catch(err){
      if(!(err instanceof FormError) || !err.missing) throw err;
      const list=Object.entries(err.missing).map(([k,s])=>`${[...s].map(c=>c===' '?'Leerzeichen':`„${c}“`).join(', ')} (${FONT_LABEL[k]})`).join('; ');
      const why=!window.queryLocalFonts
        ? 'Dein Browser kann die Schriften deines Rechners nicht lesen. Nutze Chrome oder Edge auf einem Windows-Rechner.'
        : 'Erlaube den Zugriff auf lokale Schriften, wenn der Browser fragt. Die Schrift muss dieselbe Version wie auf dem Schulserver sein (Windows 10/11).';
      showIo(`Nicht erstellt: Für ${list} braucht der Planer die Originalschrift von deinem Rechner. ${why}`,'error');
      return;
    }
    downloadPdf(r.bytes, fileName(klasse, jahrgang, name, fileStamp(date)));
    showIo(`Kurswahlformular erstellt (${checks.length} Kreuze) und heruntergeladen. Öffne die PDF, prüfe sie und drucke sie in Originalgröße (100 %, nicht „an Seite anpassen“).`
      + (r.inexact.length?` Hinweis: ${r.inexact.map(k=>FONT_LABEL[k]).join(', ')} auf deinem Rechner ist eine andere Version als auf dem Schulserver und wurde nicht verwendet.`:''),'ok');
  }catch(err){
    showIo('Das Kurswahlformular konnte nicht erstellt werden.','error');
    console.error(err);
  }finally{ btn.disabled=false; btn.textContent=label; }
};

/* =========================== Assistent =========================== */
// Der Assistent arbeitet auf einem Entwurf. Erst "Übernehmen" ersetzt die Wahl im Ankreuzmodus.
const blankState = () => ({
  name:'', klasse:'', jahrgang:'', schuelerId:'', langs:{en:'early',fr:'none',la:'none',sp:'none'}, langKl:{en:3},
  meta:{wpfInf:false,wpfSw:false,wpfDs:false,ruderAg:false,befreit:false},
  pkForm:'praes', roles:{lk1:null,lk2:null,pf3:null,pf4:null,pk5:null},
  sem:{}, sport:[null,null,null,null], zusatz:{}
});
const clone = o => JSON.parse(JSON.stringify(o));
// Führt fn mit dem Entwurf d als globalem Zustand aus, damit alle bestehenden Regeln wiederverwendet werden
const withState = (d, fn) => { const prev=st; st=d; try{ return fn(); } finally{ st=prev; } };
const ROLE_ORDER = ['lk1','lk2','pf3','pf4','pk5'];
const LANG_IDS = ['en','fr','la','sp'];
const YEAR_LABEL = ['Q1+Q2','Q3+Q4'];
const planHasData = () => !!st.name || Object.values(st.roles).some(Boolean) || Object.keys(st.sem).some(id=>sems(id).some((v,i)=>v && !lockedSems(id)[i])) || st.sport.some(Boolean) || Object.values(st.zusatz).some(a=>a.some(Boolean));

/* ---- Prüfungsfächer: nur Fächer anbieten, mit denen noch eine zulässige Kombination möglich ist ---- */
// Spiegelt die Regeln der Gruppe „Prüfungsfächer“ in evaluate(); läuft mit st = Entwurf
function wzAssignOk(R, id, r){
  const s=S[id];
  if(id==='spo' && sportOff()) return false;
  if(!allowedRoles(s).includes(r)) return false;
  if(s.needsWpf && !st.meta[s.needsWpf]) return false;
  if(s.lang && st.langs[id]==='none') return false;
  if((r==='lk1'||r==='lk2') && (s.twin || (s.lang && st.langs[id]==='new'))) return false;
  for(const [k,v] of Object.entries(R)){
    if(!v) continue;
    if(v===id){ if(!(st.pkForm==='bll' && (k==='pk5')!==(r==='pk5'))) return false; }
    else if(S[v].twin===id || s.twin===v) return false;
  }
  if(r==='lk1' && !(s.group==='de'||s.group==='ma'||s.nw||(s.lang&&['early','mid'].includes(st.langs[id])))) return false;
  if(r==='pf3'||r==='pf4'){
    const other=R[r==='pf3'?'pf4':'pf3'], art=x=>x&&(S[x].kf||x==='spo');
    if(art(id)&&art(other)) return false;
  }
  return true;
}
function wzFourOk(R){
  const pf=['lk1','lk2','pf3','pf4'].map(r=>R[r]);
  const groups=new Set(pf.map(id=>S[id].group).filter(Boolean));
  if(groups.size<2) return false;
  if(pf.filter(id=>S[id].lang).length>=2 && !groups.has('de') && !groups.has('ma')) return false;
  const afs=new Set(pf.map(id=>S[id].af));
  return [1,2,3].filter(a=>!afs.has(a)).length<=1;
}
function wzFiveOk(R){
  const pf=['lk1','lk2','pf3','pf4'].map(r=>R[r]);
  const afs=new Set([...pf,R.pk5].map(id=>S[id].af));
  if(![1,2,3].every(a=>afs.has(a))) return false;
  if(st.pkForm==='bll' && pf.includes(R.pk5)){
    const a4=new Set(pf.map(id=>S[id].af));
    if(![1,2,3].every(a=>a4.has(a))) return false;
  }
  return true;
}
// Gibt es für die vier Prüfungsfächer in R ein Referenzfach bei der Form st.pkForm?
const wzPk5Possible = R => SUBJECTS.some(s=>wzAssignOk(R,s.id,'pk5') && wzFiveOk({...R,pk5:s.id}));
// Die Form der 5. PK wird erst nach dem 4. PF gefragt: bis dahin reicht es, wenn eine der beiden Formen passt
function wzWithForm(form, fn){ const prev=st.pkForm; st.pkForm=form; try{ return fn(); } finally{ st.pkForm=prev; } }
function wzFeasible(R, idx, forms){
  if(idx===4){
    if(!wzFourOk(R)) return false;
    return forms.some(f=>wzWithForm(f, ()=>wzPk5Possible(R)));
  }
  const r=ROLE_ORDER[idx];
  return SUBJECTS.some(s=>wzAssignOk(R,s.id,r) && wzFeasible({...R,[r]:s.id}, idx+1, forms));
}
function wzRoleOptions(d, r){
  return withState(d, ()=>{
    const idx=ROLE_ORDER.indexOf(r), R={};
    ROLE_ORDER.slice(0,idx).forEach(k=>{ R[k]=st.roles[k]; });
    if(r==='pk5') return SUBJECTS.filter(s=>wzAssignOk(R,s.id,r) && wzFiveOk({...R,pk5:s.id})).map(s=>s.id);
    return SUBJECTS.filter(s=>wzAssignOk(R,s.id,r) && wzFeasible({...R,[r]:s.id}, idx+1, ['praes','bll'])).map(s=>s.id);
  });
}

/* ---- Hilfen für Blöcke und Zählung ---- */
const setBlock = (id, y) => { const a=[...sems(id)]; a[2*y]=a[2*y+1]=true; st.sem[id]=a; };
const hasBlock = id => { const a=sems(id); return (a[0]&&a[1])||(a[2]&&a[3]); };
// Fehler ohne die Mindestzahl-Regeln (die füllt der Schritt „Kurse auffüllen“)
const wzErrors = d => withState(d, ()=>{ normalize(); return evaluate().R.filter(r=>r.level==='error' && !(r.group==='Umfang und Einbringung' && r.text.startsWith('Mindestens'))).length; });
const wzCounts = d => withState(d, ()=>{ normalize(); const ev=evaluate(); return {total:ev.total, minK:ev.minK, hours:ev.hours}; });

// Fehler, die nach allen Pflicht-Schritten noch offen wären (Sport und Mindestzahl kommen später)
const wzProjErrors = d => withState(d, ()=>{ normalize(); return evaluate().R.filter(r=>r.level==='error' && !(r.group==='Umfang und Einbringung' && r.text.startsWith('Mindestens')) && !(r.group==='Belegverpflichtungen' && /Sport|Ballspiel|Rudern/.test(r.text))).length; });
const wzEinbring = d => withState(d, ()=>{ normalize(); return evaluate().einbring; });
const WZ_OBL = ['fs','nw','af2','kf'];
// Erfüllt die restlichen Pflicht-Schritte ab Index from mit der jeweils günstigsten Antwort
function wzComplete(d, from){
  for(let j=from;j<WZ_STEPS.length;j++){
    const step=WZ_STEPS[j]; if(!WZ_OBL.includes(step.id)) continue;
    const p=step.plan(d);
    if(!p.ask){ step.apply(d,p,undefined); withState(d,normalize); continue; }
    let best=null;
    step.answers(d,p).forEach(a=>{
      const t=clone(d); step.apply(t,p,a); withState(t,normalize);
      const sc=[wzProjErrors(t), wzEinbring(t)];
      if(!best || sc[0]<best.sc[0] || (sc[0]===best.sc[0] && sc[1]<best.sc[1])) best={t,sc};
    });
    if(best) d=best.t;
  }
  return d;
}
function wzAllowed(id, d, p){
  const idx=WZ_STEPS.findIndex(x=>x.id===id), step=WZ_STEPS[idx];
  return step.answers(d,p).filter(a=>{
    let t=clone(d); step.apply(t,p,a); withState(t,normalize);
    return wzProjErrors(wzComplete(t, idx+1))===0;
  });
}
function lazy(fn){ let v; return () => v===undefined ? (v=fn()) : v; }

function wzCandidates(d){
  return withState(d, ()=>{
    const out=[];
    SUBJECTS.forEach(s=>{
      if(s.id==='spo' || s.gk===false) return;
      if(partnerOf(s.id) && sems(partnerOf(s.id)).some(Boolean)) return;
      if(s.needsWpf && !st.meta[s.needsWpf]) return;
      if(s.lang && st.langs[s.id]==='none') return;
      const allowed=s.sems||[1,2,3,4], a=sems(s.id);
      [0,1].forEach(y=>{
        if(!allowed.includes(2*y+1) || !allowed.includes(2*y+2) || a[2*y] || a[2*y+1]) return;
        out.push({key:`s:${s.id}:${y}`, name:s.name, y, part:a.some(Boolean)});
      });
    });
    ZUSATZ.forEach(z=>{
      if((st.zusatz[z.id]||[]).some(Boolean)) return;
      [0,1].forEach(y=>{ if(z.sems.includes(2*y+1) && z.sems.includes(2*y+2)) out.push({key:`z:${z.id}:${y}`, name:z.name, y, zusatz:true, pair:!!z.pair, zid:z.id}); });
    });
    return out;
  });
}
function wzApplyKey(d, key){
  const [t,id,y]=key.split(':');
  withState(d, ()=>{
    if(t==='s') setBlock(id,+y);
    else { const a=[...(st.zusatz[id]||[false,false,false,false])]; a[2*y]=a[2*y+1]=true; st.zusatz[id]=a; }
  });
}
// Vorschlag: zuerst Fächer ergänzen, die schon ein Jahr belegt sind, dann weitere Grundkurse in Listenreihenfolge
function wzSuggest(base){
  const d=clone(base), keys=[];
  const cands=wzCandidates(base).filter(c=>!c.zusatz);
  const order=[...cands.filter(c=>c.part), ...cands.filter(c=>!c.part)];
  let err=wzErrors(d);
  for(const c of order){
    const n=wzCounts(d); if(n.total>=n.minK && n.hours>=66) break;
    const t=clone(d); wzApplyKey(t,c.key);
    const e=wzErrors(t);
    if(e<=err){ wzApplyKey(d,c.key); keys.push(c.key); err=e; }
  }
  return keys;
}

/* ---- Bausteine für die Oberfläche ---- */
function wzCards(opts, selected, onPick){
  const g=document.createElement('div'); g.className='wz-opts';
  opts.forEach(o=>{
    const b=document.createElement('button'); b.type='button'; b.className='wz-opt'+(o.v===selected?' on':'');
    b.setAttribute('aria-pressed', o.v===selected);
    b.innerHTML=`<b>${esc(o.label)}</b>${o.hint?`<small>${esc(o.hint)}</small>`:''}`;
    b.onclick=()=>onPick(o.v);
    g.appendChild(b);
  });
  return g;
}
function wzQ(el, text){ const q=document.createElement('div'); q.className='wz-q'; q.textContent=text; el.appendChild(q); }
function wzNote(el, text, kind=''){ const p=document.createElement('p'); p.className='wz-note '+kind; p.textContent=text; el.appendChild(p); }
const subjHint = s => s.id==='spo' ? 'mit zwei Kursen Sporttheorie in Q3 und Q4' : s.lang ? langLabel(s.id) : s.twin ? 'für den bilingualen Zug' : `Aufgabenfeld ${['','I','II','III'][s.af]}`;
const yearCards = (a, set, ys=[0,1]) => wzCards([{v:0,label:'Q1+Q2',hint:'1. Jahr'},{v:1,label:'Q3+Q4',hint:'2. Jahr'}].filter(o=>ys.includes(o.v)), a.y, y=>set({...a,y}));
const wzHiddenNote = (el, n) => { if(n>0) wzNote(el, `${n} weitere ${n===1?'Option ist':'Optionen sind'} nicht aufgeführt, weil ${n===1?'sie':'sie'} mit deiner bisherigen Wahl zu Fehlern führen ${n===1?'würde':'würden'}, zum Beispiel zu mehr als 24 einbringpflichtigen Grundkursen.`); };
const wzAllowedCached = (id, d, p) => p._allowed || (p._allowed = wzAllowed(id, d, p));

/* ---- Schritte ---- */
const ROLE_TITLE = {lk1:'1. Leistungskurs', lk2:'2. Leistungskurs', pf3:'3. Prüfungsfach', pf4:'4. Prüfungsfach', pk5:'Referenzfach der 5. Prüfungskomponente'};
const ROLE_HINT = {
  lk1:()=>'Der 1. Leistungskurs muss Deutsch, Mathematik, eine Naturwissenschaft oder eine Fremdsprache sein, die du spätestens seit Klasse 9 lernst. Leistungskurse belegst du in allen vier Halbjahren.',
  lk2:()=>'Auch den 2. Leistungskurs belegst du in allen vier Halbjahren.',
  pf3:()=>'Das 3. Prüfungsfach wird schriftlich geprüft. Du belegst es in allen vier Halbjahren als Grundkurs.',
  pf4:()=>'Das 4. Prüfungsfach wird mündlich geprüft. Du belegst es in allen vier Halbjahren.',
  pk5:()=>st.pkForm==='bll'
    ? 'Das Referenzfach der Besonderen Lernleistung darf auch eines deiner vier Prüfungsfächer sein.'
    : 'Das Referenzfach der Präsentationsprüfung muss ein anderes Fach als deine vier Prüfungsfächer sein.'
};

const wzRoleStep = r => ({id:'role_'+r, title:ROLE_TITLE[r],
    hint:d=>withState(d, ROLE_HINT[r]),
    plan:d=>({ask:true, opts:wzRoleOptions(d,r), all:withState(d,()=>SUBJECTS.filter(s=>allowedRoles(s).includes(r)).length)}),
    init:()=>st.roles[r]||null,
    valid:(p,a)=>p.opts.includes(a),
    apply:(d,p,a)=>{ if(p.opts.includes(a)) withState(d,()=>setRole(a,r)); },
    render:(el,d,p,a,set)=>{
      if(!p.opts.length){ wzNote(el,'Mit deinen bisherigen Angaben ist hier keine zulässige Wahl möglich. Geh zurück und ändere eine frühere Entscheidung.','warn'); return; }
      el.appendChild(wzCards(withState(d,()=>p.opts.map(id=>({v:id, label:S[id].name, hint:subjHint(S[id])}))), a, set));
      const hidden=p.all-p.opts.length;
      if(hidden>0) wzNote(el,`${hidden} weitere ${hidden===1?'Fach ist':'Fächer sind'} hier nicht aufgeführt, weil ${hidden===1?'es':'sie'} mit deinen bisherigen Angaben zu keiner zulässigen Kombination führen ${hidden===1?'würde':'würden'}.`);
    }});
const WZ_ROLE_STEP_PRE = ['lk1','lk2','pf3','pf4'].map(wzRoleStep);
const WZ_ROLE_STEP_PK5 = [wzRoleStep('pk5')];

const WZ_STEPS = [
  {id:'langs', title:'Deine Fremdsprachen',
    hint:()=>'Gib für jede Sprache an, seit welcher Klasse du sie lernst, und lass das Feld leer, wenn du sie nicht lernst. Davon hängt ab, welche Sprachen Leistungskurs oder Prüfungsfach sein dürfen und welche du weiter belegen musst.',
    plan:()=>({ask:true}),
    init:()=>({langs:{...st.langs}, kl:{...st.langKl}}),
    valid:(p,a)=>Object.values(a.langs).some(v=>v==='early'||v==='mid'),
    apply:(d,p,a)=>{ d.langs={...a.langs}; d.langKl={...a.kl}; },
    render:(el,d,p,a,set)=>{
      const g=document.createElement('div'); g.className='langs wz-langs';
      LANG_IDS.forEach(id=>{
        const row=document.createElement('div'); row.className='langrow';
        row.innerHTML=`<span>${S[id].name}</span>`;
        row.appendChild(langInput(a.kl[id], a.langs[id], kl=>{
          const kls={...a.kl}; if(kl) kls[id]=kl; else delete kls[id];
          set({langs:{...a.langs,[id]:langCat(kl)}, kl:kls});
        }, S[id].name));
        g.appendChild(row);
      });
      el.appendChild(g);
      if(!Object.values(a.langs).some(v=>v==='early'||v==='mid')) wzNote(el,'Mindestens eine Fremdsprache musst du schon vor Klasse 10 begonnen haben und fortführen.','warn');
    }},

  {id:'meta', title:'Wahlpflicht und Sport',
    hint:()=>'Einige Fächer kannst du nur wählen, wenn du sie in Klasse 10 als Wahlpflichtfach hattest.',
    plan:()=>({ask:true}),
    init:()=>({...st.meta}),
    valid:()=>true,
    apply:(d,p,a)=>{ d.meta={...a}; },
    render:(el,d,p,a,set)=>{
      const box=(k,label)=>{ const l=document.createElement('label'); l.className='check'; l.innerHTML=`<input type="checkbox" ${a[k]?'checked':''}> ${label}`; l.querySelector('input').onchange=e=>set({...a,[k]:e.target.checked}); el.appendChild(l); };
      wzQ(el,'In Klasse 10 als Wahlpflichtfach belegt');
      box('wpfInf','Informatik'); box('wpfSw','Sozialwissenschaften'); box('wpfDs','Darstellendes Spiel (oder Theater-Erfahrung)');
      wzQ(el,'Sport');
      box('ruderAg','Mindestens ein Jahr in der Ruder-AG'); box('befreit','Dauerhaft vom Sport befreit (Attest)');
    }},

  ...WZ_ROLE_STEP_PRE,
  {id:'pkform', title:'Form der 5. Prüfungskomponente',
    hint:()=>'Die Form bestimmt, welche Fächer Referenzfach sein dürfen: Bei der Präsentationsprüfung muss es ein anderes Fach als deine vier Prüfungsfächer sein, bei der Besonderen Lernleistung darf es auch eines davon sein.',
    plan:d=>withState(d,()=>{
      const R={}; ['lk1','lk2','pf3','pf4'].forEach(k=>{ R[k]=st.roles[k]; });
      return {ask:true, forms:['praes','bll'].filter(f=>wzWithForm(f, ()=>wzPk5Possible(R)))};
    }),
    init:(d,p)=>p.forms.includes(st.pkForm) ? st.pkForm : null,
    valid:(p,a)=>p.forms.includes(a),
    apply:(d,p,a)=>{ if(p.forms.includes(a)) d.pkForm=a; },
    render:(el,d,p,a,set)=>{
      el.appendChild(wzCards([
        {v:'praes', label:'Präsentationsprüfung', hint:'Referenzfach ist ein anderes Fach als deine vier Prüfungsfächer'},
        {v:'bll', label:'Besondere Lernleistung', hint:'Eigene Arbeit über mindestens zwei Halbjahre; Referenzfach darf ein Prüfungsfach sein'}
      ].filter(o=>p.forms.includes(o.v)), a, set));
      if(p.forms.length===1) wzNote(el, p.forms[0]==='bll'
        ? 'Die Präsentationsprüfung ist nicht aufgeführt: Mit deinen vier Prüfungsfächern gibt es dafür kein zulässiges Referenzfach.'
        : 'Die Besondere Lernleistung ist nicht aufgeführt: Mit deinen vier Prüfungsfächern gibt es dafür kein zulässiges Referenzfach.');
    }},

  ...WZ_ROLE_STEP_PK5,

  {id:'fs', title:'Fremdsprache weiter belegen',
    plan:d=>{
      const p=withState(d,()=>{
        const cont=LANG_IDS.filter(id=>['early','mid'].includes(st.langs[id])).sort((x,y)=>(st.langs[x]==='early'?0:1)-(st.langs[y]==='early'?0:1));
        const durchL=LANG_IDS.filter(id=>durch(id));
        const mode = durchL.length===0 ? 'durch' : (!durchL.some(id=>cont.includes(id)) && !cont.some(id=>sems(id)[0]&&sems(id)[1])) ? 'q12' : null;
        return {cont, mode, ask:!!mode && cont.length>1};
      });
      p.allowed=lazy(()=>wzAllowed('fs',d,p));
      return p;
    },
    answers:(d,p)=>p.cont,
    hint:(d,p)=>p.mode==='durch'
      ? 'Eine Fremdsprache musst du in allen vier Halbjahren belegen. Keines deiner Prüfungsfächer ist eine Fremdsprache – welche Sprache führst du fort?'
      : 'Deine durchgängige Fremdsprache hast du erst in Klasse 10 begonnen. Deshalb musst du eine fortgesetzte Fremdsprache zusätzlich in Q1 und Q2 belegen.',
    init:(d,p)=>p.allowed()[0]||null,
    valid:(p,a)=>p.allowed().includes(a),
    apply:(d,p,a)=>withState(d,()=>{
      const pick = !p.mode ? null : p.ask ? (p.cont.includes(a)?a:null) : p.cont[0];
      if(pick){ if(p.mode==='durch') st.sem[pick]=ALL4(); else setBlock(pick,0); }
      zweitFsMid().forEach(s=>setBlock(s.id,0));
    }),
    render:(el,d,p,a,set)=>{
      const al=p.allowed();
      if(!al.length){ wzNote(el,'Mit deiner bisherigen Wahl ist hier keine zulässige Belegung möglich. Geh zurück und ändere eine frühere Entscheidung.','warn'); return; }
      el.appendChild(wzCards(withState(d,()=>al.map(id=>({v:id,label:S[id].name,hint:subjHint(S[id])}))), a, set));
      wzHiddenNote(el, p.cont.length-al.length);
    }},

  {id:'nw', title:'Naturwissenschaft',
    plan:d=>withState(d,()=>{
      const nwD=['ph','ch','bi'].filter(durch);
      const pcBlock=['ph','ch'].some(hasBlock);
      const needNw=nwD.length===0;
      const needPc=nw=>{ const set=needNw?(nw?[nw]:[]):nwD; return set.length>0 && set.every(x=>x==='bi') && !pcBlock; };
      return {needNw, needPc, ask:needNw||needPc(null)};
    }),
    answers:(d,p)=>{
      const out=[];
      (p.needNw?['ph','ch','bi']:[null]).forEach(nw=>{
        if(p.needPc(nw)) ['ph','ch'].forEach(pc=>[0,1].forEach(y=>out.push({nw,pc,y})));
        else out.push({nw,pc:null,y:0});
      });
      return out;
    },
    hint:(d,p)=>p.needNw ? 'Eine Naturwissenschaft musst du in allen vier Halbjahren belegen.' : 'Biologie ist deine einzige durchgängige Naturwissenschaft. Dann brauchst du zusätzlich Physik oder Chemie für ein Jahr.',
    init:()=>({nw:null, pc:null, y:0}),
    valid:(p,a,d)=>wzAllowedCached('nw',d,p).some(x=>x.nw===(p.needNw?a.nw:null) && (!x.pc || (x.pc===a.pc && x.y===a.y))),
    apply:(d,p,a={})=>withState(d,()=>{
      if(p.needNw && a.nw) st.sem[a.nw]=ALL4();
      if(p.needPc(a.nw) && a.pc) setBlock(a.pc, a.y);
    }),
    render:(el,d,p,a,set)=>{
      const al=wzAllowedCached('nw',d,p);
      if(!al.length){ wzNote(el,'Mit deiner bisherigen Wahl ist hier keine zulässige Belegung möglich. Geh zurück und ändere eine frühere Entscheidung.','warn'); return; }
      if(p.needNw){
        const opts=['ph','ch','bi'].filter(id=>al.some(x=>x.nw===id));
        wzQ(el,'Welche Naturwissenschaft belegst du durchgängig?');
        el.appendChild(wzCards(opts.map(id=>({v:id,label:S[id].name,hint:id==='bi'?'dann zusätzlich Physik oder Chemie für ein Jahr':''})), a.nw, nw=>set({...a,nw,pc:null})));
        wzHiddenNote(el, 3-opts.length);
      }
      const nwSel=p.needNw?a.nw:null;
      if((!p.needNw||a.nw) && p.needPc(nwSel)){
        const m=al.filter(x=>x.nw===nwSel);
        const pcs=['ph','ch'].filter(id=>m.some(x=>x.pc===id));
        wzQ(el,'Zusätzlich für ein Jahr'); el.appendChild(wzCards(pcs.map(id=>({v:id,label:S[id].name})), a.pc, pc=>set({...a,pc})));
        if(a.pc){
          const ys=m.filter(x=>x.pc===a.pc).map(x=>x.y);
          if(!ys.includes(a.y)){ set({...a,y:ys[0]}); return; }
          wzQ(el,'In welchem Jahr?'); el.appendChild(yearCards(a,set,ys));
        }
      }
    }},

  {id:'af2', title:'Gesellschaftswissenschaften',
    plan:d=>{
      const info=withState(d,()=>({durchIds:SUBJECTS.filter(s=>s.af===2&&durch(s.id)).map(s=>s.id), geD:geSems().every(Boolean)}));
      const needAf = info.durchIds.length===0 && !info.geD;
      const choices=['ge','geb','pw','pwb','geo','phi'];
      const sup = pick => withState(d,()=>{
        const ds=[...info.durchIds, ...(pick?[pick]:[])];
        const geD = info.geD || (pick && S[pick].ge);
        const other = ds.filter(id=>!S[id].ge);
        if(geD && other.length===0){ const pw=pwSems(); return pw[2]&&pw[3] ? null : {kind:'pw', opts:['pw','pwb']}; }
        if(other.length>0){ const ge=geSems(); return ge[2]&&ge[3] ? null : {kind:'ge', opts:['ge','geb']}; }
        return null;
      });
      return {needAf, choices, sup, ask:needAf||!!sup(null)};
    },
    answers:(d,p)=>{
      const out=[];
      (p.needAf?p.choices:[null]).forEach(af=>{ const s=p.sup(af); (s?s.opts:[null]).forEach(sup=>out.push({af,sup})); });
      return out;
    },
    hint:(d,p)=>p.needAf ? 'Ein Fach des gesellschaftswissenschaftlichen Aufgabenfelds musst du in allen vier Halbjahren belegen.' : 'Zu deinem durchgängigen Fach gehört noch eine Pflichtbelegung in Q3 und Q4.',
    init:()=>({af:null, sup:null}),
    valid:(p,a,d)=>{ const af=p.needAf?a.af:null; const s=p.sup(af); const sup=s?(s.opts.includes(a.sup)?a.sup:s.opts[0]):null; return wzAllowedCached('af2',d,p).some(x=>x.af===af && x.sup===sup); },
    apply:(d,p,a={})=>{
      if(!p.ask) return;
      const s=p.sup(p.needAf?a.af:null);
      withState(d,()=>{
        if(p.needAf && a.af) st.sem[a.af]=ALL4();
        if(s){ const id=s.opts.includes(a.sup)?a.sup:s.opts[0]; setBlock(id,1); }
      });
    },
    render:(el,d,p,a,set)=>{
      const al=wzAllowedCached('af2',d,p);
      if(!al.length){ wzNote(el,'Mit deiner bisherigen Wahl ist hier keine zulässige Belegung möglich. Geh zurück und ändere eine frühere Entscheidung.','warn'); return; }
      if(p.needAf){
        const opts=p.choices.filter(id=>al.some(x=>x.af===id));
        wzQ(el,'Welches Fach belegst du durchgängig?');
        el.appendChild(wzCards(opts.map(id=>({v:id,label:S[id].name,hint:S[id].twin?'für den bilingualen Zug':''})), a.af, af=>set({...a,af,sup:null})));
        wzHiddenNote(el, p.choices.length-opts.length);
      }
      const af=p.needAf?a.af:null;
      const s=(!p.needAf||a.af) ? p.sup(af) : null;
      if(s){
        const sups=s.opts.filter(id=>al.some(x=>x.af===af && x.sup===id));
        const cur=s.opts.includes(a.sup)?a.sup:s.opts[0];
        if(sups.length && !sups.includes(cur)){ set({...a,sup:sups[0]}); return; }
        wzQ(el, s.kind==='pw' ? 'Geschichte ist dein durchgängiges Fach: Du belegst zusätzlich Politikwissenschaft in Q3 und Q4.' : 'Zusätzlich belegst du Geschichte in Q3 und Q4.');
        el.appendChild(wzCards(sups.map(id=>({v:id,label:S[id].name,hint:S[id].twin?'für den bilingualen Zug':'regulärer Kurs'})), cur, sup=>set({...a,sup})));
      }
    }},

  {id:'kf', title:'Künstlerisches Fach',
    plan:d=>withState(d,()=>({opts:SUBJECTS.filter(s=>s.kf && !(s.needsWpf&&!st.meta[s.needsWpf])).map(s=>s.id), ask:!kfExempt() && !SUBJECTS.filter(s=>s.kf).some(s=>hasBlock(s.id))})),
    hint:()=>'Musik, Bildende Kunst oder Darstellendes Spiel musst du mindestens ein Jahr belegen.',
    answers:(d,p)=>p.opts.flatMap(kf=>[0,1].map(y=>({kf,y}))),
    init:()=>({kf:null, y:0}),
    valid:(p,a,d)=>wzAllowedCached('kf',d,p).some(x=>x.kf===a.kf && x.y===a.y),
    apply:(d,p,a)=>{ if(a && p.opts.includes(a.kf)) withState(d,()=>setBlock(a.kf,a.y)); },
    render:(el,d,p,a,set)=>{
      const al=wzAllowedCached('kf',d,p);
      if(!al.length){ wzNote(el,'Mit deiner bisherigen Wahl ist hier keine zulässige Belegung möglich. Geh zurück und ändere eine frühere Entscheidung.','warn'); return; }
      const opts=p.opts.filter(id=>al.some(x=>x.kf===id));
      wzQ(el,'Welches Fach?'); el.appendChild(wzCards(opts.map(id=>({v:id,label:S[id].name})), a.kf, kf=>set({...a,kf})));
      wzHiddenNote(el, p.opts.length-opts.length);
      if(a.kf){
        const ys=al.filter(x=>x.kf===a.kf).map(x=>x.y);
        if(!ys.includes(a.y)){ set({...a,y:ys[0]}); return; }
        wzQ(el,'In welchem Jahr?'); el.appendChild(yearCards(a,set,ys));
      }
    }},

  {id:'sport', title:'Sportkurse',
    plan:d=>withState(d,()=>({ask:!sportOff(), ruder:st.meta.ruderAg, theo:sportRole()})),
    hint:(d,p)=>'In jedem Halbjahr ein Praxiskurs, jeder Kurs nur einmal und höchstens drei Ballspiele. Ob ein Kurs stattfindet, hängt von den Wahlen ab.'+(p.theo?' Weil Sport Prüfungsfach ist, sind die zwei Kurse Sporttheorie in Q3 und Q4 schon eingetragen.':''),
    init:()=>[...st.sport],
    valid:(p,a)=>a.every((c,i)=>c && wzSportOpts(p,a,i).includes(c)),
    apply:(d,p,a)=>{ if(!a) return; d.sport=a.map((c,i)=>c && wzSportOpts(p,a,i).includes(c) ? c : null); },
    render:(el,d,p,a,set)=>{
      const g=document.createElement('div'); g.className='sportgrid';
      for(let i=0;i<4;i++){
        const cell=document.createElement('div'); cell.className='sportcell';
        cell.innerHTML=`<label>Kurshalbjahr Q${i+1}</label>`;
        const sel=document.createElement('select'); sel.className='sel';
        sel.appendChild(new Option('– Kurs wählen –',''));
        wzSportOpts(p,a,i).forEach(c=>{ const o=new Option(`${SPORT[c]} (${c})`, c); o.selected=a[i]===c; sel.appendChild(o); });
        sel.onchange=()=>{ const b=[...a]; b[i]=sel.value||null; b.forEach((c,j)=>{ if(c && !wzSportOpts(p,b,j).includes(c)) b[j]=null; }); set(b); };
        cell.appendChild(sel); g.appendChild(cell);
      }
      el.appendChild(g);
    }},

  {id:'extra', title:'Kurse auffüllen',
    plan:()=>({ask:true}),
    hint:()=>'Der Planer schlägt weitere Grundkurse vor, bis du mindestens 40 Kurse (bei Sportbefreiung 39) und 66 Jahreswochenstunden erreichst. Zuerst ergänzt er Fächer, die du schon ein Jahr belegst, danach weitere Fächer in der Reihenfolge der Fächerliste – das ist keine inhaltliche Empfehlung. Tausche frei aus.',
    init:d=>wzSuggest(d),
    valid:()=>true,
    apply:(d,p,a)=>{ (a||[]).forEach(k=>wzApplyKey(d,k)); },
    render:(el,d,p,a,set)=>{
      const sel=clone(d); a.forEach(k=>wzApplyKey(sel,k));
      const n=wzCounts(sel), errSel=wzErrors(sel);
      const stat=(lab,val,max,good)=>`<div class="stat ${good?'good':'bad'}"><span>${lab}</span><b>${val}<em> / ${max}</em></b></div>`;
      const stats=document.createElement('div'); stats.className='stats';
      stats.innerHTML=stat('Belegte Kurse',n.total,n.minK,n.total>=n.minK)+stat('Jahreswochenstunden',n.hours,66,n.hours>=66);
      el.appendChild(stats);
      const cands=wzCandidates(d);
      const group=(title,list)=>{
        if(!list.length) return;
        wzQ(el,title);
        const g=document.createElement('div'); g.className='wz-chips';
        list.forEach(c=>{
          const on=a.includes(c.key);
          let dis=false;
          if(!on){
            if(c.pair && a.some(k=>k.startsWith(`z:${c.zid}:`))) dis=true;
            else { const t=clone(sel); wzApplyKey(t,c.key); dis=wzErrors(t)>errSel; }
          }
          const b=document.createElement('button'); b.type='button'; b.className='wz-chip'+(on?' on':''); b.disabled=dis;
          b.setAttribute('aria-pressed', on);
          b.innerHTML=`${esc(c.name)} <span>${YEAR_LABEL[c.y]}</span>`;
          if(dis) b.title='Passt nicht zu deiner bisherigen Wahl';
          b.onclick=()=>set(on ? a.filter(k=>k!==c.key) : [...a, c.key]);
          g.appendChild(b);
        });
        el.appendChild(g);
      };
      group('Grundkurse', cands.filter(c=>!c.zusatz));
      group('Zusatzkurse', cands.filter(c=>c.zusatz));
      const act=document.createElement('div'); act.className='wz-inline';
      act.innerHTML='<button type="button" class="btn">Vorschlag neu berechnen</button><button type="button" class="btn">Alle abwählen</button>';
      act.children[0].onclick=()=>set(wzSuggest(d)); act.children[1].onclick=()=>set([]);
      el.appendChild(act);
      if(n.total<n.minK || n.hours<66) wzNote(el,'Du erreichst die Mindestwerte noch nicht. Wähle weitere Kurse aus.','warn');
    }},

  {id:'summary', title:'Deine Wahl im Überblick',
    plan:()=>({ask:true}),
    hint:()=>'Mit „Übernehmen“ wechselst du in den Ankreuzmodus. Dort kannst du alles weiter ändern, die Prüfung ansehen und das Kurswahlformular ausfüllen.',
    valid:()=>true,
    render:(el,d)=>withState(d,()=>{
      normalize();
      const ev=evaluate();
      const cards=document.createElement('div'); cards.className='pfgrid';
      ROLES.forEach(([r,l])=>{ const id=st.roles[r]; cards.insertAdjacentHTML('beforeend',`<div class="pfcard ${id?'':'empty '}${(r==='lk1'||r==='lk2')?'lk':r==='pk5'?'pk':''}"><span>${l}${r==='pk5'?(st.pkForm==='bll'?' (BLL)':' (Präsentation)'):''}</span><b>${id?esc(S[id].name):'offen'}</b></div>`); });
      el.appendChild(cards);
      const stat=(lab,val,max,good)=>`<div class="stat ${good?'good':'bad'}"><span>${lab}</span><b>${val}<em> / ${max}</em></b></div>`;
      el.insertAdjacentHTML('beforeend',`<div class="stats">${stat('Belegte Kurse',ev.total,ev.minK,ev.total>=ev.minK)}${stat('Jahreswochenstunden',ev.hours,66,ev.hours>=66)}${stat('Einbringpflichtige Grundkurse',ev.einbring,24,ev.einbring<=24)}</div>`);
      const X=a=>a.map(v=>`<td class="c">${v?'✓':''}</td>`).join('');
      let rows='';
      SUBJECTS.forEach(s=>{
        if(s.id==='spo') return;
        if(!cnt(s.id)) return;
        rows+=`<tr><td>${esc(s.name)}</td><td>${roleOf(s.id).map(r=>ROLE_LABEL[r]).join(' / ')}</td>${X(sems(s.id))}</tr>`;
      });
      if(cnt('spt')) rows+=`<tr><td>Sporttheorie</td><td></td>${X(sems('spt'))}</tr>`;
      if(!sportOff()) rows+=`<tr><td>Sport-Praxis</td><td>${roleOf('spo').map(r=>ROLE_LABEL[r]).join(' / ')}</td>${st.sport.map(c=>`<td class="c">${c||''}</td>`).join('')}</tr>`;
      ZUSATZ.forEach(z=>{ const zs=st.zusatz[z.id]||[]; if(zs.some(Boolean)) rows+=`<tr><td>${esc(z.name)} <small>Zusatzkurs</small></td><td></td>${X([0,1,2,3].map(i=>!!zs[i]))}</tr>`; });
      el.insertAdjacentHTML('beforeend',`<div class="wz-tablewrap"><table class="wz-table"><thead><tr><th>Fach</th><th>Rolle</th><th>Q1</th><th>Q2</th><th>Q3</th><th>Q4</th></tr></thead><tbody>${rows}</tbody></table></div>`);
      const probs=ev.R.filter(r=>r.level==='error'||r.level==='warn');
      if(!probs.length) wzNote(el,'Der Planer findet in dieser Wahl keine Fehler.','ok');
      else {
        wzQ(el,'Noch zu klären');
        const ul=document.createElement('ul'); ul.className='cl';
        probs.forEach(r=>ul.insertAdjacentHTML('beforeend',`<li class="${r.level}"><span class="ic">!</span><span>${esc(r.text)}</span></li>`));
        el.appendChild(ul);
      }
    })}
];
function wzSportOpts(p, a, i){
  const others=a.filter((x,j)=>j!==i && x);
  return SPORT_BY_SEM[i].filter(c=>(c!=='G2'||p.ruder) && !others.includes(c) && !(c[0]==='B' && others.filter(x=>x[0]==='B').length>=3));
}

/* ---- Ablauf ---- */
let wz=null; // {ans, hist, cur}
// Baut den Entwurf aus allen Antworten vor Schritt upto und liefert den Plan des Schritts upto
function wzBuild(upto){
  const d=blankState();
  withState(d, normalize);
  for(let i=0;i<upto && i<WZ_STEPS.length;i++){
    const step=WZ_STEPS[i], p=step.plan(d);
    if(step.apply){
      // Übersprungene Schritte bekommen keine Antwort, tragen aber automatische Pflichten ein
      let a=undefined;
      if(p.ask){ a=wz.ans[step.id]; if(a===undefined && step.init) a=step.init(d,p); }
      step.apply(d,p,a);
    }
    withState(d, normalize);
  }
  return {d, p: upto<WZ_STEPS.length ? WZ_STEPS[upto].plan(d) : null};
}
function wzRender(){
  const step=WZ_STEPS[wz.cur];
  const {d,p}=wzBuild(wz.cur);
  if(wz.ans[step.id]===undefined && step.init) wz.ans[step.id]=step.init(d,p);
  const a=wz.ans[step.id];
  $('#wz-stepno').textContent=`Frage ${wz.hist.length+1}`;
  $('#wz-title').textContent=step.title;
  $('#wz-hint').textContent=step.hint ? step.hint(d,p) : '';
  $('#wz-bar').style.width=`${Math.round(wz.cur/(WZ_STEPS.length-1)*100)}%`;
  const body=$('#wz-body'); body.innerHTML='';
  step.render(body, d, p, a, v=>{
    wz.ans[step.id]=v;
    WZ_STEPS.slice(wz.cur+1).forEach(s=>{ delete wz.ans[s.id]; });
    wzRender();
  });
  const last=step.id==='summary';
  $('#wz-next').hidden=last; $('#wz-finish').hidden=!last;
  $('#wz-next').disabled=!step.valid(p,a,d);
  $('#wz-back').disabled=!wz.hist.length;
}
function wzGo(){
  const sec=$('#sec-wizard');
  wzRender();
  if(sec.getBoundingClientRect().top<0) sec.scrollIntoView({block:'start'});
}
$('#wz-next').onclick=()=>{
  let i=wz.cur+1;
  while(i<WZ_STEPS.length-1 && !wzBuild(i).p.ask) i++;
  wz.hist.push(wz.cur); wz.cur=i; wzGo();
};
$('#wz-back').onclick=()=>{ if(wz.hist.length){ wz.cur=wz.hist.pop(); wzGo(); } };
$('#wz-cancel').onclick=()=>{
  if(!confirm('Assistent abbrechen? Deine Antworten im Assistenten gehen verloren. Deine Wahl im Ankreuzmodus bleibt unverändert.')) return;
  wz=null; setMode('check');
};
$('#wz-finish').onclick=()=>{
  if(planHasData() && Object.values(st.roles).some(Boolean) && !confirm('Die Wahl aus dem Assistenten ersetzt deine bisherigen Kreuze im Ankreuzmodus. Fortfahren?')) return;
  const {d}=wzBuild(WZ_STEPS.length);
  d.name=st.name; d.klasse=st.klasse; d.jahrgang=st.jahrgang; d.schuelerId=st.schuelerId;
  st=d; wz=null;
  syncControls(); update();
  setMode('check');
  const m=$('#mode-msg'); m.textContent='Deine Wahl aus dem Assistenten ist übernommen. Prüfe sie unten und fülle danach das Kurswahlformular aus.'; m.className='io-msg ok'; m.hidden=false;
  $('#modebar').scrollIntoView({block:'start'});
};
function setMode(m){
  document.body.classList.toggle('mode-wizard', m==='wizard');
  document.querySelectorAll('#modeseg button').forEach(b=>{ const on=b.dataset.mode===m; b.classList.toggle('on',on); b.setAttribute('aria-pressed',on); });
  $('#mode-msg').hidden=true;
  if(m==='wizard'){ if(!wz) wz={ans:{}, hist:[], cur:0}; wzRender(); }
}
$('#modeseg').onclick=e=>{ const b=e.target.closest('button'); if(b) setMode(b.dataset.mode); };

$('#sb-state').onclick=()=>$('#sec-check').scrollIntoView({behavior:'smooth',block:'start'});
$('#copy').onclick=async()=>{
  const ev=evaluate();
  const lines=[`Kurswahl ${st.name||''}`.trim(),''];
  ROLES.forEach(([r,l])=>lines.push(`${l}: ${st.roles[r]?S[st.roles[r]].name:'–'}`));
  lines.push(`5. PK als ${st.pkForm==='bll'?'Besondere Lernleistung':'Präsentationsprüfung'}`,'');
  SUBJECTS.forEach(s=>{ if(s.id!=='spo'&&cnt(s.id)) lines.push(`${s.name}: ${sems(s.id).map((v,i)=>v?'Q'+(i+1):null).filter(Boolean).join(' ')}`); });
  if(cnt('spt')) lines.push(`Sporttheorie: ${sems('spt').map((v,i)=>v?'Q'+(i+1):null).filter(Boolean).join(' ')}`);
  lines.push(`Sportpraxis: ${st.sport.map((c,i)=>c?`Q${i+1} ${SPORT[c]} (${c})`:`Q${i+1} –`).join(', ')}`);
  ZUSATZ.forEach(z=>{const zs=st.zusatz[z.id]||[]; if(zs.some(Boolean)) lines.push(`Zusatz ${z.name}: ${zs.map((v,i)=>v?'Q'+(i+1):null).filter(Boolean).join(' ')}`);});
  lines.push('',`Kurse: ${ev.total} / Jahreswochenstunden: ${ev.hours} / Einbringpflichtige GK: ${ev.einbring}`);
  const errs=ev.R.filter(r=>r.level==='error'); if(errs.length){lines.push('','Offen:');errs.forEach(r=>lines.push('- '+r.text));}
  try{ await navigator.clipboard.writeText(lines.join('\n')); $('#copy').textContent='Kopiert'; setTimeout(()=>$('#copy').textContent='Zusammenfassung kopieren',1500);}catch(e){ alert(lines.join('\n')); }
};

setupAccountBar();
renderLangs();
if(SERVER && !localMode()){ applyProfile(); normalize(); renderAll(); }  // ohne Änderung nichts speichern
else update();
if(readOnly) applyReadOnly();
else if(SERVER && !localMode() && BOOT.needsSummary) serverSave();   // Kurzfassung für ältere Speicherstände nachtragen
