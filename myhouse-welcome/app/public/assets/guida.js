/* La guida dell'ospite (7C): il foglio «Chi ti accoglie» e le foto delle sezioni da ingrandire.
   Nessuna libreria, nessun cookie. Senza JavaScript la guida si legge lo stesso. */
(function () {
  'use strict';
  // Un <dialog> si chiude con ✕, con Esc (lo fa il browser) e toccando fuori dal contenuto.
  function chiudibile(d) {
    d.addEventListener('click', function (e) { if (e.target === d) d.close(); });
    d.querySelectorAll('[data-host-chiudi],[data-zoom-chiudi]').forEach(function (b) { b.addEventListener('click', function () { d.close(); }); });
  }
  function apri(d) { if (typeof d.showModal === 'function') d.showModal(); else d.setAttribute('open', ''); }

  // Chi ti accoglie: il tondo apre il foglio con la foto intera.
  document.querySelectorAll('[data-host-apri]').forEach(function (b) {
    var d = document.getElementById(b.getAttribute('data-host-apri'));
    if (!d) return;
    chiudibile(d);
    b.addEventListener('click', function () { apri(d); });
  });

  // 8C · «In che lingua vuoi leggerla?»: la scelta si ricorda per questa guida in localStorage (niente cookie).
  // Con una scelta già fatta la finestra non si apre più; il selettore in alto resta per cambiarla.
  (function () {
    var slug = document.documentElement.getAttribute('data-guida');
    if (!slug) return;
    var chiave = 'mhw-lingua-' + slug, salvata = null;
    function scrivi(l) { try { localStorage.setItem(chiave, l); } catch (e) {} }
    try { salvata = localStorage.getItem(chiave); } catch (e) {}
    var qs = null; try { qs = new URLSearchParams(location.search); } catch (e) {}
    if (qs && qs.get('l')) { scrivi(qs.get('l')); salvata = qs.get('l'); }
    var d = document.getElementById('scegli-lingua');
    if (!d) return;
    var corrente = document.documentElement.getAttribute('lang');
    var link = {};
    d.querySelectorAll('[data-lingua]').forEach(function (a) { link[a.getAttribute('data-lingua')] = a; });
    if (salvata && link[salvata]) {
      if (salvata !== corrente) location.replace(link[salvata].href);
      return;
    }
    var scelta = false;
    d.addEventListener('click', function (e) {
      var a = e.target.closest('[data-lingua]'); if (!a) return;
      scelta = true; scrivi(a.getAttribute('data-lingua'));
      if (a.getAttribute('data-lingua') === corrente) { e.preventDefault(); d.close(); }
    });
    // Chiusa con Esc: vale la lingua che si sta leggendo, e non si richiede più.
    d.addEventListener('close', function () { if (!scelta) scrivi(corrente); });
    if (typeof d.showModal === 'function') d.showModal(); else d.setAttribute('open', '');
    var primo = d.querySelector('[data-lingua]'); if (primo) primo.focus();
  })();

  // Le stelle (8C, guest/_voto.php) sono un modulo GET: la pagina si ricarica con ?voto=N e mostra il blocco giusto.

  // Le foto dentro le sezioni: un tocco apre la foto più grande, con lo zoom delle dita e della rotella.
  var foto = document.querySelectorAll('[data-zoom]');
  if (!foto.length) return;
  var d = document.createElement('dialog');
  d.className = 'zoom-foglio';
  d.innerHTML = '<button type="button" class="zoom-foglio__chiudi" data-zoom-chiudi></button><div class="zoom-foglio__area"><img alt=""></div>';
  document.body.appendChild(d);
  var chiudi = d.querySelector('[data-zoom-chiudi]'), area = d.querySelector('.zoom-foglio__area'), img = d.querySelector('img');
  chiudi.setAttribute('aria-label', document.documentElement.getAttribute('data-chiudi') || 'Chiudi');
  chiudi.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>';
  chiudibile(d);
  area.addEventListener('click', function (e) { if (e.target === area) d.close(); });
  var scala = 1;
  function zoom(s) { scala = Math.max(1, Math.min(4, s)); img.style.transform = 'scale(' + scala + ')'; area.classList.toggle('zoom-foglio__area--su', scala > 1); }
  area.addEventListener('wheel', function (e) { e.preventDefault(); zoom(scala * (e.deltaY < 0 ? 1.15 : 1 / 1.15)); }, { passive: false });
  img.addEventListener('dblclick', function () { zoom(scala > 1 ? 1 : 2); });
  d.addEventListener('close', function () { zoom(1); img.removeAttribute('src'); });
  foto.forEach(function (a) {
    a.addEventListener('click', function (e) {
      e.preventDefault();
      img.src = a.getAttribute('href');
      img.alt = (a.querySelector('img') || {}).alt || '';
      apri(d);
    });
  });
})();

/* 10 · «Chiedi alla guida»: il bottone apre la finestra; le domande vanno in JSON a …/chiedi e le risposte compaiono
   come fumetti, con il link alla sezione o il contatto di chi ospita. Gli scambi restano nella pagina (al massimo gli
   ultimi sei si rimandano come contesto): niente cookie, niente memoria nel telefono. Senza JavaScript il bottone è
   un link alla stessa pagina con il modulo. */
(function () {
  'use strict';
  var d = document.querySelector('[data-chiedi]');
  if (!d || !window.fetch) return;
  var T = {}; try { T = JSON.parse(d.getAttribute('data-testi') || '{}'); } catch (e) {}
  var url = d.getAttribute('data-url'), contatto = d.getAttribute('data-contatti-href') || '';
  var msgs = d.querySelector('[data-chiedi-msgs]'), form = d.querySelector('[data-chiedi-form]'), campo = form.querySelector('input[name=q]');
  var invia = form.querySelector('button'), esempi = d.querySelector('[data-chiedi-esempi]');
  var storia = [], occupato = false;
  function apri() { if (typeof d.showModal === 'function') d.showModal(); else d.setAttribute('open', ''); setTimeout(function () { campo.focus(); }, 50); }
  document.querySelectorAll('[data-chiedi-apri]').forEach(function (b) { b.addEventListener('click', function (e) { e.preventDefault(); apri(); }); });
  d.querySelectorAll('[data-chiedi-chiudi]').forEach(function (b) { b.addEventListener('click', function () { d.close(); }); });
  d.addEventListener('click', function (e) { if (e.target === d) d.close(); });
  function fumetto(classe, testo, etichetta) {
    var m = document.createElement('div'); m.className = 'chiedi__msg ' + classe;
    var sr = document.createElement('span'); sr.className = 'sr-only'; sr.textContent = etichetta + ': ';
    m.appendChild(sr); m.appendChild(document.createTextNode(testo));
    msgs.appendChild(m); msgs.scrollTop = msgs.scrollHeight; return m;
  }
  function chiedi(q) {
    q = (q || '').trim(); if (!q || occupato) return;
    occupato = true; invia.disabled = true; campo.value = '';
    if (esempi) esempi.style.display = 'none';
    fumetto('chiedi__msg--tu', q, T.you || '');
    var attesa = fumetto('chiedi__msg--guida chiedi__msg--attesa', T.thinking || '…', T.answer || '');
    fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, credentials: 'omit',
                 body: JSON.stringify({ q: q, l: T.lingua || '', storia: storia.slice(-6) }) })
      .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
      .then(function (x) {
        var j = x.j || {};
        attesa.remove();
        if (!x.ok || !j.ok) { fumetto('chiedi__msg--guida chiedi__msg--errore', j.errore || T.error || '', T.answer || ''); return; }
        fumetto('chiedi__msg--guida', j.testo, T.answer || '');
        storia.push({ r: 'u', t: q }); storia.push({ r: 'a', t: j.testo });
        if (j.sezione && j.sezione.url) {
          var a = document.createElement('a'); a.className = 'chiedi__fonte'; a.href = j.sezione.url;
          a.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 4.5h6a3 3 0 0 1 3 3V20a2 2 0 0 0-2-2H4z"/><path d="M20 4.5h-6a3 3 0 0 0-3 3V20a2 2 0 0 1 2-2h7z"/></svg>';
          a.appendChild(document.createTextNode((T.source || '%s').replace('%s', j.sezione.titolo) + ' · '));
          var b = document.createElement('b'); b.textContent = T.open || ''; a.appendChild(b);
          msgs.appendChild(a);
        } else if (j.contatti && contatto) {
          var c = document.createElement('a'); c.className = 'btn btn--sm chiedi__contatto'; c.href = contatto; c.rel = 'noopener'; c.textContent = T.contact || '';
          msgs.appendChild(c);
        }
        msgs.scrollTop = msgs.scrollHeight;
      })
      .catch(function () { attesa.remove(); fumetto('chiedi__msg--guida chiedi__msg--errore', T.error || '', T.answer || ''); })
      .then(function () { occupato = false; invia.disabled = false; campo.focus(); });
  }
  form.addEventListener('submit', function (e) { e.preventDefault(); chiedi(campo.value); });
  d.querySelectorAll('[data-esempio]').forEach(function (b) { b.addEventListener('click', function () { chiedi(b.textContent); }); });
})();
