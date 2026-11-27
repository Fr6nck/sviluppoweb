/* La finestra «Passa a Plus / Portfolio» (8B, views/host/_sblocca.php). Un elemento con data-sblocca="…"
   la apre al posto della sua azione (il server controlla comunque i limiti); dopo un limite del server
   la pagina arriva con data-apri e la finestra si apre da sola. Esc, «Non ora» e ✕ la chiudono. */
(function () {
  'use strict';
  var d = document.getElementById('sblocca');
  if (!d) return;
  function apri(k, da) {
    var v = d.querySelector('[data-variante="' + k + '"]');
    if (!v) return false;
    d.querySelectorAll('[data-variante]').forEach(function (x) {
      x.hidden = x !== v;
      var t = x.querySelector('.sblocca__titolo'); if (t) t.removeAttribute('id');
    });
    v.querySelector('.sblocca__titolo').id = 'sblocca-titolo';
    d.ritorno = da || null;
    if (typeof d.showModal === 'function') { if (!d.open) d.showModal(); } else d.setAttribute('open', '');
    var b = v.querySelector('.btn'); if (b) b.focus();
    return true;
  }
  function chiudi() { if (d.close) d.close(); else d.removeAttribute('open'); }
  d.addEventListener('close', function () { if (d.ritorno && d.ritorno.focus) d.ritorno.focus(); });
  document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-sblocca]');
    if (t) { if (apri(t.getAttribute('data-sblocca'), t)) { e.preventDefault(); e.stopPropagation(); } return; }
    if (e.target.closest('[data-sblocca-chiudi]')) { chiudi(); return; }
    if (e.target === d) chiudi();   // un clic fuori dalla finestra
  }, true);
  // Una casella con il lucchetto (la firma, una lingua) non si spunta: apre la finestra.
  document.addEventListener('change', function (e) {
    var t = e.target.closest && e.target.closest('[data-sblocca]');
    if (t && t.type === 'checkbox') { t.checked = false; apri(t.getAttribute('data-sblocca'), t); }
  });
  if (d.hasAttribute('data-apri')) {
    var k = d.getAttribute('data-apri');
    // Togli ?sblocca= dall'indirizzo: ricaricando la pagina non si riapre.
    try { var u = new URL(location.href); if (u.searchParams.has('sblocca')) { u.searchParams.delete('sblocca'); history.replaceState(null, '', u.toString()); } } catch (e) {}
    d.removeAttribute('open');
    apri(k);
  }
})();
