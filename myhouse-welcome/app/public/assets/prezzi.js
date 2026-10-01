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
      if (ok) {
        totale.textContent = soldi(base + (q - 1) * extra, valuta);
        // L'equivalente mensile: prezzo annuale / 12, arrotondato al centesimo.
        var mese = b.querySelector('[data-mensile]');
        if (mese) mese.textContent = soldi(Math.round((base + (q - 1) * extra) / 12), valuta);
      }
    }
    campo.addEventListener('input', aggiorna);
    aggiorna();

    // − e + ai lati del campo: si scrive ancora a mano, ma col pollice si fa prima.
    var gruppo = document.createElement('span'); gruppo.className = 'passo-num';
    campo.parentNode.insertBefore(gruppo, campo);
    function bottone(segno, testo, delta) {
      var x = document.createElement('button');
      x.type = 'button'; x.className = 'passo-num__btn'; x.textContent = segno; x.setAttribute('aria-label', testo);
      x.addEventListener('click', function () {
        var q = parseInt(campo.value, 10); if (isNaN(q)) q = min;
        q = Math.max(min, Math.min(max, q + delta));
        campo.value = q;
        campo.dispatchEvent(new Event('input', { bubbles: true }));
        totale.classList.remove('prezzo--cambia'); void totale.offsetWidth; totale.classList.add('prezzo--cambia');
      });
      return x;
    }
    var meno = bottone('\u2212', 'Una struttura in meno', -1), piu = bottone('+', 'Una struttura in più', 1);
    gruppo.appendChild(meno); gruppo.appendChild(campo); gruppo.appendChild(piu);
    function limiti() {
      var q = parseInt(campo.value, 10);
      meno.disabled = !(q > min); piu.disabled = !(q < max);
    }
    campo.addEventListener('input', limiti); limiti();
  })(blocchi[i]);
})();
