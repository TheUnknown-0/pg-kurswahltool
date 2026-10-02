// Login und Admin-Bereich: Logo-Ausfall, Rückfragen vor dem Löschen, Live-Stand des Hintergrund-Imports
document.querySelectorAll('img[data-hide-on-error]').forEach(img => {
  img.addEventListener('error', () => img.remove());
  if (img.complete && !img.naturalWidth) img.remove();
});
document.querySelectorAll('form[data-confirm]').forEach(f => {
  f.addEventListener('submit', e => { if (!confirm(f.dataset.confirm)) e.preventDefault(); });
});

(() => {
  const box = document.getElementById('stats');
  if (!box) return;
  let lastLog = +box.dataset.lastLog;
  const tick = async () => {
    try {
      const r = await fetch(box.dataset.url, {headers: {Accept: 'application/json'}, credentials: 'same-origin'});
      if (!r.ok) return;
      const s = await r.json();
      ['students', 'with_pdf', 'unassigned', 'saved', 'submitted'].forEach(k => { const el = box.querySelector(`[data-k="${k}"]`); if (el) el.textContent = s[k]; });
      box.querySelector('[data-k="queue"]').textContent = s.queue + s.folder;
      const w = document.getElementById('worker');
      w.className = 'msg small ' + (s.worker_ok ? 'ok' : 'warn');
      w.textContent = s.worker_ok ? 'Import-Worker läuft.' : 'Import-Worker meldet sich nicht – hochgeladene Dateien werden erst eingelesen, wenn er läuft.';
      // Import fertig: Seite neu laden, damit Protokoll und Listen aktuell sind
      if (s.last_log_id !== lastLog && s.queue + s.folder === 0) { lastLog = s.last_log_id; location.reload(); }
    } catch (e) { /* nächster Versuch */ }
  };
  setInterval(tick, 5000);
})();

// Pflichtkurse: ein Kreuz gilt für den Halbjahresblock (Q1+Q2 bzw. Q3+Q4)
document.querySelectorAll('.pbox').forEach(cb => {
  cb.addEventListener('change', () => {
    document.querySelectorAll(`.pbox[data-pair="${cb.dataset.pair}"]`).forEach(o => { o.checked = cb.checked; });
  });
});
