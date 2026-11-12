/* Hilfe zur Kurswahl: erzeugt von extras/build_hilfe.py aus dem Leitfaden */
(function(){
        var CH=[[0,'Einführung'],[32.9,'Ausgangslage'],[71.2,'Leistungskurse'],[107.8,'Prüfungsfächer und 5. PK'],[149.2,'Belegpflichten'],[196.1,'Sport und Zusatzkurse'],[260,'Nachrechnen'],[310.9,'Prüfen und abgeben']];
        var v=document.getElementById('kw-video'), list=document.getElementById('chapters');
        function mmss(s){s=Math.floor(s);return Math.floor(s/60)+':'+String(s%60).padStart(2,'0');}
        list.innerHTML=CH.map(function(c,i){return '<li><button type="button" data-t="'+c[0]+'"><time>'+mmss(c[0])+'</time><span>'+c[1]+'</span></button></li>';}).join('');
        var btns=list.querySelectorAll('button');
        list.addEventListener('click',function(e){
          var b=e.target.closest('button'); if(!b) return;
          v.currentTime=parseFloat(b.dataset.t);
          var p=v.play(); if(p&&p.catch) p.catch(function(){});
        });
        v.addEventListener('timeupdate',function(){
          var t=v.currentTime, cur=0;
          CH.forEach(function(c,i){ if(t>=c[0]-0.2) cur=i; });
          btns.forEach(function(b,i){ b.classList.toggle('on', i===cur); });
        });
      })();

(function(){
  var NS='http://www.w3.org/2000/svg';
  function el(tag, attrs, parent){var e=document.createElementNS(NS,tag);for(var k in attrs)e.setAttribute(k,attrs[k]);if(parent)parent.appendChild(e);return e;}
  var COL={lk:'var(--blue)',pf:'var(--blue3)',pk:'var(--bll)',gk:'var(--line-strong)',zk:'var(--grey)',sp:'var(--orange-line)'};
  var CHIP={lk:'',pf:'pf',pk:'pk',gk:'gk',zk:'zk',sp:'sp'};

  /* Beispielwahl, mit evaluate() des Planers geprüft: 0 Fehler, 40 Kurse, 66 JWS, 19 einbringpflichtige GK */
  var EX=[
    {n:'Mathematik',b:'1. LK',t:'lk',s:[1,1,1,1]},
    {n:'Englisch',b:'2. LK',t:'lk',s:[1,1,1,1]},
    {n:'Geschichte',b:'3. PF',t:'pf',s:[1,1,1,1]},
    {n:'Biologie',b:'4. PF',t:'pf',s:[1,1,1,1]},
    {n:'Bildende Kunst',b:'5. PK',t:'pk',s:[1,1,1,1]},
    {n:'Deutsch',b:'GK',t:'gk',s:[1,1,1,1]},
    {n:'Chemie',b:'GK',t:'gk',s:[1,1,1,1]},
    {n:'Philosophie',b:'GK',t:'gk',s:[1,1,1,1]},
    {n:'Politikwiss.',b:'GK',t:'gk',s:[0,0,1,1]},
    {n:'Kreat. Schreiben',b:'ZK',t:'zk',s:[1,1,0,0]},
    {n:'Sport',b:'Sport',t:'sp',s:[1,1,1,1]}
  ];
  var HRS={lk:5,pf:3,pk:3,gk:3,zk:3,sp:2};

  /* Netzplan */
  var svg=document.getElementById('netz');
  var X=[310,425,540,655], top=62, step=38;
  [[0,1,'1. Jahr'],[2,3,'2. Jahr']].forEach(function(p){
    el('rect',{x:X[p[0]]-44,y:16,width:X[p[1]]-X[p[0]]+88,height:472,rx:12,fill:'var(--blue-soft)'},svg);
    var tx=el('text',{x:(X[p[0]]+X[p[1]])/2,y:482,'text-anchor':'middle','font-size':12,'font-weight':700,fill:'var(--ink-3)'},svg);tx.textContent=p[2];
  });
  X.forEach(function(x,i){var t=el('text',{x:x,y:44,'text-anchor':'middle','font-size':16,'font-weight':800,fill:'var(--blue)'},svg);t.textContent='Q'+(i+1);});
  EX.forEach(function(r,i){
    var y=top+i*step+10, g=el('g',{},svg), bw=50;
    el('rect',{x:10,y:y-11,width:bw,height:22,rx:5,fill:r.t==='gk'?'#fff':COL[r.t],stroke:r.t==='gk'||r.t==='zk'?'var(--line-strong)':COL[r.t]},g);
    var bt=el('text',{x:10+bw/2,y:y+4.5,'text-anchor':'middle','font-size':11.5,'font-weight':700,fill:(r.t==='gk'||r.t==='zk')?'#40546b':'#fff'},g);bt.textContent=r.b;
    var nt=el('text',{x:70,y:y+5,'font-size':14.5,'font-weight':600,fill:'var(--ink)'},g);nt.textContent=r.n;
    var first=r.s.indexOf(1), last=r.s.lastIndexOf(1);
    el('path',{d:'M'+X[first]+' '+y+' L'+X[last]+' '+y,class:'ln',pathLength:1,stroke:r.t==='gk'?'var(--blue2)':COL[r.t],'stroke-opacity':r.t==='gk'?.45:1,style:'--i:'+i},g);
    r.s.forEach(function(on,q){ if(on) el('circle',{cx:X[q],cy:y,r:7,class:'st',style:'--i:'+i},g); });
  });

  /* Begriffe */
  var TERMS=[
    ['Q1 bis Q4','','Die vier Halbjahre der Qualifikationsphase. Grundkurse belegst du im Block: Q1+Q2 oder Q3+Q4.'],
    ['Leistungskurs','lk','5 Wochenstunden, alle vier Halbjahre, schriftliche Prüfung. Du hast genau zwei.'],
    ['Grundkurs','gk','3 Wochenstunden pro Halbjahr. Sport-Praxis hat 2 Stunden.'],
    ['Prüfungsfach','pf','1. und 2. PF sind die LKs, 3. und 4. PF sind Grundkurse mit Prüfung.'],
    ['5. PK','pk','Fünfte Prüfungskomponente: Präsentationsprüfung oder Besondere Lernleistung (BLL) in einem Referenzfach.'],
    ['Aufgabenfeld','','I sprachlich-literarisch-künstlerisch, II gesellschaftswissenschaftlich, III mathematisch-naturwissenschaftlich-technisch.'],
    ['Durchgängig','','In allen vier Halbjahren Q1 bis Q4 belegt.'],
    ['Einbringen','','Kurse, die in die Abiturnote zählen: alle 8 LK-Kurse und genau 24 Grundkurse.'],
    ['JWS','','Jahreswochenstunden: alle Wochenstunden der vier Halbjahre geteilt durch zwei. Mindestens 66.'],
    ['Päko','','Pädagogischer Koordinator. Berät dich bei der Kurswahl.'],
    ['Zusatzkurs','zk','Kurs außerhalb der Fächerliste, z. B. Astronomie. Zählt zur Belegung.'],
    ['VO-GO','','Verordnung über die gymnasiale Oberstufe in Berlin, Grundlage aller Regeln hier.']
  ];
  var CL={lk:['','LK'],gk:['gk','GK'],pf:['pf','PF'],pk:['pk','PK'],zk:['zk','ZK']};
  document.getElementById('terms').innerHTML=TERMS.map(function(t){
    var c=t[1]?'<span class="rchip '+CL[t[1]][0]+'">'+CL[t[1]][1]+'</span>':'';
    return '<div><dt>'+c+t[0]+'</dt><dd>'+t[2]+'</dd></div>';
  }).join('');

  /* Fächertabelle: Y möglich, N nicht, P mit Bedingung */
  var F=[
    ['Aufgabenfeld I: sprachlich-literarisch-künstlerisch',[
      ['Deutsch','Y','Y','Y','Y','Pflicht in allen vier Halbjahren'],
      ['Englisch, Französisch, Latein','P','P','Y','Y','1. LK nur, wenn spätestens seit Klasse 9 gelernt; neu ab Klasse 10: kein LK'],
      ['Spanisch','N','N','Y','Y','kein Leistungskurs'],
      ['Musik, Bildende Kunst','N','Y','Y','Y','unter 3./4. PF höchstens ein künstlerisches Fach oder Sport'],
      ['Darstellendes Spiel','N','N','N','P','nur Grundkurs, nur 5. PK; Wahlpflichtfach oder Theater-Erfahrung']]],
    ['Aufgabenfeld II: gesellschaftswissenschaftlich',[
      ['Geschichte','N','Y','Y','Y','bilingual nur als Grundkurs'],
      ['Politikwissenschaft','N','Y','Y','Y','bilingual nur als Grundkurs'],
      ['Geografie','N','Y','Y','Y',''],
      ['Philosophie','N','N','Y','Y',''],
      ['Psychologie','N','N','N','N','nur Q1+Q2, kein Prüfungsfach'],
      ['Sozialwissenschaften','N','P','N','N','nur als 2. LK, nur mit Wahlpflichtfach in Klasse 10']]],
    ['Aufgabenfeld III: mathematisch-naturwissenschaftlich-technisch',[
      ['Mathematik','Y','Y','Y','Y','Pflicht in allen vier Halbjahren'],
      ['Physik, Chemie, Biologie','Y','Y','Y','Y','eine davon durchgängig Pflicht'],
      ['Informatik','N','P','P','P','nur mit Wahlpflichtfach Informatik in Klasse 10']]],
    ['Keinem Aufgabenfeld zugeordnet',[
      ['Sport','N','N','P','Y','nur als 4. PF, dann plus Sporttheorie Q3+Q4']]]
  ];
  var HEADS=['1. LK','2. LK','3./4. PF','5. PK'];
  var LBL={Y:'möglich',N:'nicht möglich',P:'mit Bedingung'};
  var TICK='<svg viewBox="0 0 24 24"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>';
  function box(v,i){ return '<i class="qbox '+v.toLowerCase()+'" data-l="'+HEADS[i]+'" title="'+HEADS[i]+': '+LBL[v]+'" aria-label="'+HEADS[i]+': '+LBL[v]+'">'+(v==='Y'?TICK:v==='P'?'!':'')+'</i>'; }
  document.getElementById('ftable').innerHTML=F.map(function(g){
    return '<div class="af"><div class="af-title"><h3>'+g[0]+'</h3></div><div class="colhead"><span>Fach</span>'+HEADS.map(function(h){return '<span>'+h+'</span>';}).join('')+'<span>Hinweis</span></div>'+
      g[1].map(function(r){ return '<div class="frow"><b>'+r[0]+'</b>'+[1,2,3,4].map(function(i){return box(r[i],i-1);}).join('')+'<small>'+r[5]+'</small></div>'; }).join('')+'</div>';
  }).join('');

  /* Belegpflichten als Linie über Q1 bis Q4 */
  var DUTY=[
    ['Deutsch',[1,1,1,1],'var(--red)','auch Pflichtkurs der Schule'],
    ['Mathematik',[1,1,1,1],'var(--red)','auch Pflichtkurs der Schule'],
    ['Eine Fremdsprache',[1,1,1,1],'var(--blue)','Neu in Klasse 10 begonnen? Dann zusätzlich eine fortgeführte Sprache in Q1+Q2. Eine neue Sprache läuft immer bis Q4.'],
    ['2. Fremdsprache ab Kl. 8/9',[1,1,0,0],'var(--blue)','nur wenn du lediglich eine Sprache seit Klasse 5 bis 7 lernst'],
    ['Eine Naturwissenschaft',[1,1,1,1],'var(--blue)','Physik, Chemie oder Biologie. Nur Biologie? Dann zusätzlich Physik oder Chemie in einem Block.'],
    ['Ein Fach aus AF II',[1,1,1,1],'var(--blue)','Geschichte, Politik, Geografie, Philosophie oder Sozialwissenschaften'],
    ['Politik, wenn Geschichte durchgängig',[0,0,1,1],'var(--blue3)','Ist dein durchgängiges AF-II-Fach Geschichte, kommt Politikwissenschaft in Q3+Q4 dazu.'],
    ['Geschichte, wenn ein anderes Fach durchgängig',[1,1,0,0],'var(--blue3)','<span class="neu">neu ab Q1 2027/28</span>Geschichte in Q1+Q2 (Abitur ab 2028/29). Wer 2026/27 in Q1 ist, belegt sie noch in Q3+Q4.'],
    ['Musik, Kunst oder DS',[1,1,0,0],'var(--bll)','zwei Kurse, Q1+Q2 oder Q3+Q4; entfällt bei neuer Fremdsprache ab Kl. 10'],
    ['Sport',[1,1,1,1],'var(--orange-line)','ein Praxiskurs je Halbjahr; mit Attest entfällt Sport, 40 Kurse brauchst du trotzdem']
  ];
  function lineDiagram(s,c){
    var xs=[18,72,138,192];
    var h='<svg viewBox="0 0 210 26" aria-hidden="true"><path d="M18 13H192" stroke="var(--line-strong)" stroke-width="2.5" stroke-dasharray="2 6" stroke-linecap="round"/>';
    [[0,1],[1,2],[2,3]].forEach(function(p){ if(s[p[0]]&&s[p[1]]) h+='<path d="M'+xs[p[0]]+' 13H'+xs[p[1]]+'" stroke="'+c+'" stroke-width="7" stroke-linecap="round"/>'; });
    xs.forEach(function(x,i){ h+= s[i] ? '<circle cx="'+x+'" cy="13" r="6" fill="#fff" stroke="'+c+'" stroke-width="2.8"/>' : '<circle cx="'+x+'" cy="13" r="4" fill="var(--paper-2)" stroke="var(--line-strong)" stroke-width="1.6"/>'; });
    return h+'</svg>';
  }
  document.getElementById('duty').innerHTML='<div class="duty-row head"><span>Pflicht</span><span class="qs"><span>Q1</span><span>Q2</span><span>Q3</span><span>Q4</span></span><span>Hinweis</span></div>'+
    DUTY.map(function(d){ return '<div class="duty-row"><b>'+d[0]+'</b>'+lineDiagram(d[1],d[2])+'<small>'+d[3]+'</small></div>'; }).join('');

  /* Rechner */
  var inLk=document.getElementById('in-lk'), inGk=document.getElementById('in-gk'), inSp=document.getElementById('in-sp');
  function num(e,max){var v=parseInt(e.value,10);if(isNaN(v)||v<0)v=0;return Math.min(v,max);}
  function de(v){return String(v).replace('.',',');}
  function stamp(id,val,min,unit){
    document.getElementById('out-'+id).textContent=de(val);
    var s=document.getElementById('st-'+id), ok=val>=min;
    s.className='st8 '+(ok?'ok':'bad');
    s.textContent=ok?'✓ erfüllt (mind. '+min+')':'es fehlen '+de(Math.round((min-val)*10)/10)+' '+unit;
  }
  function calc(){
    var lk=num(inLk,8), gk=num(inGk,60), sp=num(inSp,4);
    stamp('k',lk+gk+sp,40,'Kurse');
    stamp('h',(lk*5+gk*3+sp*2)/2,66,'Std.');
  }
  [inLk,inGk,inSp].forEach(function(e){e.addEventListener('input',calc);});
  calc();

  /* Halbjahre des Beispiels */
  document.getElementById('halves').innerHTML=[0,1,2,3].map(function(q){
    var rows=EX.filter(function(r){return r.s[q];});
    var h=rows.reduce(function(a,r){return a+HRS[r.t];},0);
    return '<div class="half"><span class="q">Q'+(q+1)+'</span><div class="bars">'+rows.map(function(r){return '<i style="--c:'+(r.t==='gk'?'var(--blue2)':COL[r.t])+';opacity:'+(r.t==='gk'?.45:1)+'" title="'+r.n+'"></i>';}).join('')+'</div><span class="sum">'+rows.length+' Kurse · '+h+' Std./Woche</span></div>';
  }).join('');

  /* Sportkurse */
  var SPORT=[['A1','Leichtathletik'],['B1','Basketball'],['B3','Fußball'],['B4','Handball'],['B5','Hockey'],['B6','Rugby'],['B7','Volleyball'],['B8','Badminton'],['B10','Tischtennis'],['B12','Ultimate Frisbee'],['C1','Geräteturnen'],['D1','Gymnastik/Tanz'],['E1','Schwimmen'],['G2','Rudern'],['H1','Fitness']];
  document.getElementById('sports').innerHTML=SPORT.map(function(s){return '<span><b class="rchip '+(s[0][0]==='B'?'sp':'gk')+'">'+s[0]+'</b>'+s[1]+'</span>';}).join('');

  /* Stolperfallen */
  var ALERTS=[
    ['Präsentationsprüfung im Prüfungsfach','Bei der Präsentationsprüfung muss die 5. PK ein eigenes, fünftes Fach sein. Nur die BLL darf in einem Prüfungsfach liegen.'],
    ['Aufgabenfeld II fehlt','Ohne Geschichte, Politik, Geografie, Philosophie oder Sozialwissenschaften unter den fünf Komponenten fehlt ein Aufgabenfeld.'],
    ['Referenzfach nicht durchgängig','Auch das Fach der 5. PK muss in allen vier Halbjahren belegt sein.'],
    ['Zu viele Pflicht-Grundkurse','Mehr als 24 einbringpflichtige Grundkurse machen die Wahl ungültig.'],
    ['Biologie als einzige Naturwissenschaft','Dann fehlt fast immer der zusätzliche Block Physik oder Chemie.'],
    ['Einzelnes Halbjahr gewählt','Grundkurse gibt es nur als Q1+Q2 oder Q3+Q4.'],
    ['Geschichte im falschen Jahr','Wer ab dem Schuljahr 2027/28 in Q1 kommt (Abitur 2028/29), belegt die zusätzliche Geschichte in Q1+Q2 (§ 25 Abs. 3 VO-GO). Für frühere Jahrgänge gilt noch Q3+Q4 (§ 49 Abs. 11 VO-GO).'],
    ['Nicht-Ballsport erst in Q4','Fällt dein einziger Kurs außerhalb der Ballspiele in Q4 aus, bleiben nur Ballspiele. Lege ihn in Q1 bis Q3.'],
    ['Sportattest heißt nicht weniger Kurse','Auch mit Sportbefreiung brauchst du mindestens 40 Kurse. Die Sportkurse ersetzt du durch Grundkurse.'],
    ['Psychologie als Prüfungsfach','Psychologie läuft nur in Q1+Q2 und kann kein Prüfungsfach sein.'],
    ['Wahlpflichtfach nicht angegeben','Informatik, Sozialwissenschaften und Darstellendes Spiel setzen das Wahlpflichtfach aus Klasse 10 voraus.']
  ];
  document.getElementById('alerts').innerHTML=ALERTS.map(function(a){return '<div class="alert"><b>'+a[0]+'</b><span>'+a[1]+'</span></div>';}).join('');

  /* Checkliste (nur in diesem Browser gespeichert) */
  var ITEMS=[
    'Fremdsprachen mit Startklasse und Wahlpflichtfach aus Klasse 10 im Planer eingetragen',
    'Zwei Leistungskurse gewählt, der 1. LK erfüllt die strengere Regel',
    '3. PF, 4. PF und Referenzfach der 5. PK gewählt, Form der 5. PK festgelegt',
    'Alle drei Aufgabenfelder unter den fünf Prüfungskomponenten',
    'Alle fünf Prüfungskomponenten in Q1 bis Q4 belegt',
    'Belegpflichten erfüllt (Deutsch, Mathe, Fremdsprache, Naturwissenschaft, AF II, Kunst/Musik/DS)',
    'Vier Sportkurse gewählt: höchstens drei Ballspiele, kein Kurs öfter als zweimal, Nicht-Ballsport in Q1 bis Q3',
    'Zusatzkurse mit dem Päko besprochen',
    'Mindestens 40 Kurse und 66 Jahreswochenstunden, auch bei Sportbefreiung',
    'Keine rote Meldung mehr im Planer',
    'Orange Hinweise mit dem Päko besprochen',
    'Im Planer „Wahl abgeben“ geklickt',
    'Kurswahlformular in 100 % gedruckt und abgegeben, Kurswahlbogen im Lernraum erledigt'
  ];
  var KEY='kurswahl-leitfaden-checkliste', saved={};
  try{saved=JSON.parse(localStorage.getItem(KEY)||'{}')||{};}catch(e){saved={};}
  var cbox=document.getElementById('checklist');
  cbox.innerHTML=ITEMS.map(function(t,i){return '<label for="ck-'+i+'"><input type="checkbox" id="ck-'+i+'"'+(saved[i]?' checked':'')+'><span>'+t+'</span></label>';}).join('');
  function prog(){
    var n=cbox.querySelectorAll('input:checked').length;
    document.getElementById('progress').textContent=n+' von '+ITEMS.length+' erledigt';
    document.getElementById('meter').style.width=(n/ITEMS.length*100)+'%';
  }
  cbox.addEventListener('change',function(e){
    saved[e.target.id.replace('ck-','')]=e.target.checked;
    try{localStorage.setItem(KEY,JSON.stringify(saved));}catch(err){}
    prog();
  });
  document.getElementById('reset').addEventListener('click',function(){
    saved={};try{localStorage.removeItem(KEY);}catch(e){}
    cbox.querySelectorAll('input').forEach(function(c){c.checked=false;});prog();
  });
  prog();

  /* Farbvariablen aus SVG-Attributen in style verschieben (zuverlässig in allen Browsern) */
  document.querySelectorAll('svg [fill*="var("], svg [stroke*="var("]').forEach(function(n){
    ['fill','stroke'].forEach(function(a){
      var v=n.getAttribute(a);
      if(v && v.indexOf('var(')>-1){ n.style.setProperty(a,v); n.removeAttribute(a); }
    });
  });
})();
