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

  // Le stelle del commiato: il voto (anonimo) parte senza ricaricare la pagina; poi i bottoni delle recensioni,
  // uguali per ogni voto, e da 1 a 3 anche «Scrivigli».
  document.querySelectorAll('form[data-voto-url]').forEach(function (f) {
    var box = f.closest('[data-voto-box]'), url = f.getAttribute('data-voto-url');
    f.addEventListener('click', function (e) {
      var b = e.target.closest('button[name=voto]'); if (!b) return;
      e.preventDefault();
      var n = parseInt(b.value, 10);
      if (url) { try { fetch(url + n, { method: 'GET', keepalive: true, credentials: 'omit' }); } catch (x) {} }
      f.querySelectorAll('button[name=voto]').forEach(function (s) {
        s.classList.toggle('stella--su', parseInt(s.value, 10) <= n);
        if (s === b) s.setAttribute('aria-pressed', 'true'); else s.removeAttribute('aria-pressed');
      });
      var dopo = box.querySelector('[data-voto-dopo]'), basso = box.querySelector('[data-voto-basso]');
      if (dopo) dopo.hidden = false;
      if (basso) basso.hidden = n > 3;
    });
  });

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
