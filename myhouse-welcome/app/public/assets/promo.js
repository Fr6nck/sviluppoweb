/* 9U · Il conto alla rovescia della fascia della promo (views/pub/_promo.php): giorni, ore e minuti fino alla fine
   (data-promo-fine, millisecondi). Senza JavaScript resta la data nel titolo; a tempo scaduto la fascia sparisce. */
(function () {
  var fasce = document.querySelectorAll('[data-promo-fine]');
  if (!fasce.length) return;
  function due(n) { return n < 10 ? '0' + n : String(n); }
  function aggiorna() {
    for (var i = 0; i < fasce.length; i++) {
      var c = fasce[i], resta = parseInt(c.getAttribute('data-promo-fine'), 10) - Date.now();
      if (!(resta > 0)) { var f = c.closest('[data-promo]'); if (f) f.hidden = true; continue; }
      var m = Math.floor(resta / 60000), g = Math.floor(m / 1440), o = Math.floor((m % 1440) / 60);
      c.querySelector('[data-promo-giorni]').textContent = g;
      c.querySelector('[data-promo-ore]').textContent = due(o);
      c.querySelector('[data-promo-min]').textContent = due(m % 60);
      c.hidden = false;
    }
  }
  aggiorna();
  setInterval(aggiorna, 30000);
})();
