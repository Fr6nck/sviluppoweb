/* 9K · «Qual è il piano giusto per te?» (pagine per tipologia). Senza JavaScript le tre domande sono un modulo che
   ricarica la pagina con l'esito; qui le domande vanno una alla volta (si risponde, si passa alla successiva, si torna
   indietro), l'esito compare subito (i tre esiti sono già nella pagina, nascosti) e il Portfolio si ricalcola con
   − e + : prezzo progressivo, sconto di chi guarda, tabella del rinnovo, link «Crea gratis» con il numero di strutture.
   9L · Lo stesso script per la finestra della home (<dialog>): «Rispondi a 3 domande» la apre (senza JavaScript è un link a
   /piano), × / Esc / clic fuori la chiudono, la pagina sotto resta ferma (anche Safari su iPhone). L'esito va nelle
   statistiche come «piano_test» (piano · origine). */
(function () {
  'use strict';
  var box = document.querySelector('[data-questionario]');
  if (!box) return;
  var modulo = box.querySelector('[data-q-modulo]'), passi = box.querySelectorAll('[data-q-passo]');
  var barre = box.querySelectorAll('.questionario__avanzamento i'), esiti = box.querySelectorAll('[data-q-esito]');
  var invia = box.querySelector('[data-q-invia]'), ricalcola = box.querySelector('[data-q-ricalcola]');
  var ora = 1, finestra = box.closest('dialog');

  // 9N · La stessa formula del server (Sconti::netto): lo sconto arrotondato una volta, il netto mai sotto 1 €.
  function netto(p, perc) { return perc > 0 ? p - Math.max(0, Math.min(Math.round(p * Math.min(100, perc) / 100), p - 100)) : p; }
  function soldi(c) {
    var e = Math.floor(c / 100), cent = c % 100, s = String(e).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    return (cent ? s + ',' + (cent < 10 ? '0' : '') + cent : s) + ' €';
  }
  // 9O · Una cifra: l'imponibile in data-iva-netto (per l'interruttore «IVA inclusa», assets/iva.js) e il testo giusto.
  function scrivi(el, c) { if (!el) return; el.setAttribute('data-iva-netto', c); el.textContent = window.mhwIva ? window.mhwIva.testo(c) : soldi(c); }
  function risposta(n) { var x = modulo.querySelector('input[name=d' + n + ']:checked'); return x ? x.value : ''; }

  function vai(n, fuoco) {
    ora = n;
    for (var i = 0; i < passi.length; i++) passi[i].hidden = +passi[i].getAttribute('data-q-passo') !== n;
    for (var j = 0; j < barre.length; j++) barre[j].classList.toggle('on', j < n);
    if (fuoco) { var p = box.querySelector('[data-q-passo="' + n + '"]'); p.setAttribute('tabindex', '-1'); p.focus({ preventScroll: true }); }
  }
  function mostraEsito() {
    var r = [risposta(1), risposta(2), risposta(3)];
    var piano = r[2] === 'no' ? 'portfolio' : (r[0] === 'no' || r[1] === 'no' ? 'plus' : 'essential');
    modulo.hidden = true;
    for (var i = 0; i < esiti.length; i++) {
      var e = esiti[i], mio = e.getAttribute('data-q-esito') === piano;
      e.hidden = !mio;
      if (!mio) continue;
      for (var n = 1; n <= 3; n++) { var s = e.querySelector('[data-q-r="' + n + '"]'); if (s) s.textContent = r[n - 1] === 'si' ? (box.getAttribute('data-si') || 'sì') : 'no'; }
      var h = e.querySelector('h3'); h.setAttribute('tabindex', '-1'); h.focus({ preventScroll: true });
    }
    var ivaQ = box.querySelector('[data-q-iva]'); if (ivaQ) ivaQ.hidden = false;   // 9O · l'interruttore IVA inclusa con l'esito
    if (finestra) finestra.scrollTop = 0;
    else box.scrollIntoView({ block: 'start', behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
    if (window.mhwStatistiche) window.mhwStatistiche.evento('piano_test', piano + ' · ' + (box.getAttribute('data-origine') || ''));
  }

  // Con JavaScript: niente bottone «Vedi il piano giusto», il «← Indietro» visibile, una domanda alla volta.
  if (invia) invia.hidden = true;
  box.querySelectorAll('[data-q-indietro]').forEach(function (b) { b.hidden = false; b.addEventListener('click', function () { vai(ora - 1, true); }); });
  if (!box.hasAttribute('data-esito-iniziale')) vai(1, false);
  modulo.addEventListener('change', function (e) {
    if (!e.target.matches('input[type=radio]')) return;
    var n = +e.target.name.slice(1);
    if (n < 3) setTimeout(function () { vai(n + 1, true); }, 180); else setTimeout(mostraEsito, 180);
  });
  modulo.addEventListener('submit', function (e) { e.preventDefault(); mostraEsito(); });
  box.querySelectorAll('[data-q-rifai]').forEach(function (a) {
    a.addEventListener('click', function (e) {
      e.preventDefault();
      modulo.reset(); modulo.querySelectorAll('input[type=radio]').forEach(function (x) { x.checked = false; });
      for (var i = 0; i < esiti.length; i++) esiti[i].hidden = true;
      var ivaR = box.querySelector('[data-q-iva]'); if (ivaR) ivaR.hidden = true;
      box.removeAttribute('data-esito-iniziale');
      modulo.hidden = false; vai(1, true);
      if (finestra) finestra.scrollTop = 0;
      else if (history.replaceState) history.replaceState(null, '', location.pathname + '#piano-giusto');
    });
  });

  /* ---- 9L · La finestra della home ------------------------------------------- */
  if (finestra && typeof finestra.showModal === 'function') {
    var chi = null, y = 0, corpo = document.body;
    function blocca() {   // la pagina sotto non scorre: overflow per tutti, position: fixed per Safari su iPhone
      y = window.scrollY || 0;
      document.documentElement.classList.add('finestra-aperta');
      corpo.style.position = 'fixed'; corpo.style.top = -y + 'px'; corpo.style.left = '0'; corpo.style.right = '0';
    }
    function sblocca() {
      document.documentElement.classList.remove('finestra-aperta');
      corpo.style.position = ''; corpo.style.top = ''; corpo.style.left = ''; corpo.style.right = '';
      window.scrollTo(0, y);
    }
    document.querySelectorAll('[data-q-apri]').forEach(function (a) {
      a.addEventListener('click', function (e) {
        e.preventDefault(); chi = a;
        blocca(); finestra.showModal(); finestra.scrollTop = 0;
        var t = finestra.querySelector('#piano-giusto-titolo'); if (t) { t.setAttribute('tabindex', '-1'); t.focus(); }
      });
    });
    finestra.querySelectorAll('[data-q-chiudi]').forEach(function (b) { b.addEventListener('click', function () { finestra.close(); }); });
    finestra.addEventListener('click', function (e) { if (e.target === finestra) finestra.close(); });   // il clic sullo sfondo
    finestra.addEventListener('close', function () { sblocca(); if (chi) chi.focus(); });
  }

  /* ---- Portfolio: − / + e i prezzi ------------------------------------------ */
  var porto = box.querySelector('[data-q-esito="portfolio"]');
  if (!porto) return;
  var campo = porto.querySelector('[data-q-quante]');
  if (!campo) return;
  if (ricalcola) { var rb = ricalcola.querySelector('button'); if (rb) rb.hidden = true; }
  var base = +porto.getAttribute('data-base'), scaglioni = [];
  try { scaglioni = JSON.parse(porto.getAttribute('data-scaglioni') || '[]'); } catch (err) {}
  var perc = +porto.getAttribute('data-sconto') || 0, fisso = +porto.getAttribute('data-sconto-fisso') || 0;
  function prezzo(n) {   // la prima a prezzo pieno, poi ognuna al prezzo del suo scaglione
    var tot = base;
    for (var k = 2; k <= n; k++) { var c = 0; scaglioni.forEach(function (s) { if (k >= s[0]) c = s[1]; }); tot += c; }
    return tot;
  }
  function aggiorna() {
    var min = +campo.min || 2, max = +campo.max || 10, n = Math.max(min, Math.min(max, parseInt(campo.value, 10) || min));
    campo.value = n;
    var pieno = prezzo(n), totale = perc ? netto(pieno, perc) : (fisso ? Math.max(0, pieno - fisso) : pieno);
    var t = porto.querySelector('[data-q-totale]'), p = porto.querySelector('[data-q-pieno]'), q = porto.querySelector('[data-q-n]');
    scrivi(t, totale); scrivi(p, pieno); if (q) q.textContent = n;
    porto.querySelectorAll('[data-iva-dettaglio]').forEach(function (d) { d.setAttribute('data-iva-dettaglio', totale); });
    var ultimo = pieno;
    porto.querySelectorAll('[data-q-perc]').forEach(function (td) { ultimo = netto(pieno, +td.getAttribute('data-q-perc')); scrivi(td, ultimo); });
    scrivi(porto.querySelector('[data-q-anno]'), pieno); scrivi(porto.querySelector('[data-q-anno-s]'), pieno);
    scrivi(porto.querySelector('[data-q-min]'), ultimo);
    if (window.mhwIva) window.mhwIva.aggiorna(porto);
    var crea = porto.querySelector('[data-q-crea]'); if (crea) crea.href = crea.getAttribute('data-href') + '&strutture=' + n;
    porto.querySelector('[data-q-meno]').disabled = n <= min; porto.querySelector('[data-q-piu]').disabled = n >= max;
  }
  porto.querySelectorAll('[data-q-meno],[data-q-piu]').forEach(function (b) {
    b.hidden = false;
    b.addEventListener('click', function () { campo.value = (parseInt(campo.value, 10) || 2) + (b.hasAttribute('data-q-piu') ? 1 : -1); aggiorna(); });
  });
  campo.addEventListener('input', aggiorna); campo.addEventListener('change', aggiorna);
  aggiorna();
})();
