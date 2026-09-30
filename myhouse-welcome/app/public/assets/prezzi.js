/* Portfolio: il totale segue il numero di strutture. Base, costo per struttura
   aggiuntiva, minimo e massimo arrivano dal listino (attributi data-*): qui non
   c'è nessun prezzo. Senza JavaScript il modulo funziona lo stesso: il server
   ricalcola e controlla tutto. */
(function () {
  'use strict';
  function soldi(cents, valuta) {
    var intero = cents % 100 === 0, n = (cents / 100).toFixed(intero ? 0 : 2).split('.');
    n[0] = n[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    return n.join(',') + ' ' + (valuta === 'EUR' ? '€' : valuta);
  }
  var blocchi = document.querySelectorAll('[data-quantita]');
  for (var i = 0; i < blocchi.length; i++) (function (b) {
    var campo = b.querySelector('input[type=number]'), totale = b.querySelector('[data-totale]');
    var base = parseInt(b.getAttribute('data-base'), 10), extra = parseInt(b.getAttribute('data-extra'), 10);
    var min = parseInt(campo.min, 10), max = parseInt(campo.max, 10), valuta = b.getAttribute('data-valuta') || 'EUR';
    function aggiorna() {
      var v = campo.value.trim(), q = /^\d+$/.test(v) ? parseInt(v, 10) : NaN;
      var ok = !isNaN(q) && q >= min && q <= max;
      campo.setCustomValidity(ok ? '' : 'Indica un numero intero di strutture tra ' + min + ' e ' + max + '.');
      if (ok) totale.textContent = soldi(base + (q - 1) * extra, valuta);
    }
    campo.addEventListener('input', aggiorna);
    aggiorna();
  })(blocchi[i]);
})();
