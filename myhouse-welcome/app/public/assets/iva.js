/* 9O · «Sono un privato: mostra i prezzi IVA inclusa». Ogni cifra porta l'imponibile in data-iva-netto: con
   l'interruttore acceso si mostra imponibile + IVA (arrotondata al centesimo, come il server e Stripe Tax), «+ IVA»
   diventa «IVA inclusa» e compare «di cui IVA 22%: …». La scelta resta nel browser (localStorage) e in sessione
   (POST /prezzi/iva). Senza JavaScript il modulo dell'interruttore fa lo stesso con «Applica». */
(function () {
  'use strict';
  if (window.mhwIva) return;   // lo script può essere incluso due volte (home + questionario)
  var interruttori = document.querySelectorAll('[data-iva-interruttore]');
  var aliquota = interruttori.length ? parseInt(interruttori[0].getAttribute('data-aliquota'), 10) || 22 : 22;
  var CHIAVE = 'mhw-iva-inclusa';
  function soldi(cents) {
    var intero = cents % 100 === 0, n = (Math.abs(cents) / 100).toFixed(intero ? 0 : 2).split('.');
    n[0] = n[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    return (cents < 0 ? '-' : '') + n.join(',') + ' €';
  }
  function iva(netto) { return Math.round(netto * aliquota / 100); }
  function acceso() { var c = interruttori[0] && interruttori[0].querySelector('input[type=checkbox]'); return !!(c && c.checked); }
  function aggiorna(radice) {
    var si = acceso(), r = radice || document;
    r.querySelectorAll('[data-iva-netto]').forEach(function (x) {
      var n = parseInt(x.getAttribute('data-iva-netto'), 10);
      if (!isNaN(n)) x.textContent = soldi(si ? n + iva(n) : n);
    });
    r.querySelectorAll('[data-iva-nota]').forEach(function (x) {
      if (!x.hasAttribute('data-netto')) x.setAttribute('data-netto', x.textContent);   // il testo del server, IVA esclusa
      x.textContent = x.getAttribute(si ? 'data-lordo' : 'data-netto');
    });
    r.querySelectorAll('[data-iva-dettaglio]').forEach(function (x) {
      var n = parseInt(x.getAttribute('data-iva-dettaglio'), 10);
      x.hidden = !si;
      if (!isNaN(n)) x.textContent = x.getAttribute('data-etichetta') + soldi(iva(n));
    });
    document.documentElement.classList.toggle('iva-inclusa', si);
  }
  // Il testo di un imponibile secondo l'interruttore (per prezzi.js e questionario.js, che ricalcolano i prezzi).
  function testo(netto) { return soldi(acceso() ? netto + iva(netto) : netto); }
  window.mhwIva = { aggiorna: aggiorna, acceso: acceso, soldi: soldi, iva: iva, testo: testo };
  if (!interruttori.length) return;
  var salvato = null;
  try { salvato = window.localStorage.getItem(CHIAVE); } catch (e) { salvato = null; }
  interruttori.forEach(function (f) {
    var c = f.querySelector('input[type=checkbox]'), applica = f.querySelector('.iva-interruttore__applica');
    if (applica) applica.hidden = true;
    if (salvato !== null) c.checked = salvato === '1';
    c.addEventListener('change', function () {
      var si = c.checked;
      interruttori.forEach(function (g) { g.querySelector('input[type=checkbox]').checked = si; });
      try { window.localStorage.setItem(CHIAVE, si ? '1' : '0'); } catch (e) { /* senza localStorage resta la sessione */ }
      var dati = new FormData(f); dati.set('si', si ? '1' : '0'); dati.set('js', '1');
      if (window.fetch) fetch(f.action, { method: 'POST', body: dati, credentials: 'same-origin' }).catch(function () {});
      aggiorna();
    });
  });
  aggiorna();
})();
