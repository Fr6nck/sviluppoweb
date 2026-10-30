/* Portfolio: il totale segue il numero di strutture. Base, costo per struttura
   aggiuntiva, minimo e massimo arrivano dal listino (attributi data-*): qui non
   c'è nessun prezzo. Senza JavaScript il modulo funziona lo stesso: il server
   ricalcola e controlla tutto. */
(function () {
  'use strict';
  // 8E: sulla home inglese (/en) i messaggi dello script sono in inglese.
  var en = document.documentElement.lang === 'en';
  // 9N · La stessa formula del server (Sconti::netto): lo sconto arrotondato una volta, il netto mai sotto 1 €.
  function netto(p, perc) { return perc > 0 ? p - Math.max(0, Math.min(Math.round(p * Math.min(100, perc) / 100), p - 100)) : p; }
  function soldi(cents, valuta) {
    var intero = cents % 100 === 0, n = (cents / 100).toFixed(intero ? 0 : 2).split('.');
    n[0] = n[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    return n.join(',') + ' ' + (valuta === 'EUR' ? '€' : valuta);
  }
  // 9O · Una cifra: l'imponibile in data-iva-netto (per l'interruttore «IVA inclusa», assets/iva.js) e il testo giusto.
  function scrivi(el, cents, valuta) { if (!el) return; el.setAttribute('data-iva-netto', cents); el.textContent = window.mhwIva ? window.mhwIva.testo(cents) : soldi(cents, valuta); }
  var blocchi = document.querySelectorAll('[data-quantita]');
  for (var i = 0; i < blocchi.length; i++) (function (b) {
    var campo = b.querySelector('input[type=number]'), totale = b.querySelector('[data-totale]');
    var base = parseInt(b.getAttribute('data-base'), 10), extra = parseInt(b.getAttribute('data-extra'), 10);
    // Gli scaglioni: [[dalla struttura, centesimi], …]; senza, ogni struttura in più costa «extra».
    var scaglioni = []; try { scaglioni = JSON.parse(b.getAttribute('data-scaglioni') || '[]'); } catch (e) { scaglioni = []; }
    function prezzo(q) {
      var tot = base;
      for (var n = 2; n <= q; n++) {
        var c = extra;
        for (var k = 0; k < scaglioni.length; k++) if (n >= scaglioni[k][0]) c = scaglioni[k][1];
        tot += c;
      }
      return tot;
    }
    // 9A: il massimo del listino (data-max-prezzo) e quello del campo (999): oltre il primo niente prezzo,
    // si apre il riquadro del preventivo e il bottone del piano si spegne. Il server rifiuta comunque l'ordine.
    var min = parseInt(campo.min, 10), max = parseInt(campo.max, 10), valuta = b.getAttribute('data-valuta') || 'EUR';
    var maxPrezzo = parseInt(campo.getAttribute('data-max-prezzo') || campo.max, 10);
    var preventivo = document.querySelector('[data-preventivo]');
    // 9A: lo sconto del primo anno che vale per chi guarda (invito, giudizio, codice): prezzo barrato e scontato.
    var scontoPct = parseInt(b.getAttribute('data-sconto') || '0', 10), scontoFisso = parseInt(b.getAttribute('data-sconto-fisso') || '0', 10);
    function scontato(tot) {
      var sc = scontoPct > 0 ? Math.round(tot * scontoPct / 100) : scontoFisso;
      return tot - Math.max(0, Math.min(sc, tot - 100));
    }
    function aggiorna() {
      var v = campo.value.trim(), q = /^\d+$/.test(v) ? parseInt(v, 10) : NaN;
      var ok = !isNaN(q) && q >= min && q <= max;
      campo.setCustomValidity(ok ? '' : (en ? 'Enter a whole number of properties between ' + min + ' and ' + max + '.' : 'Indica un numero intero di strutture tra ' + min + ' e ' + max + '.'));
      if (ok) {
        var tot = prezzo(q);
        var principale = scontoPct > 0 || scontoFisso > 0 ? scontato(tot) : tot;
        scrivi(totale, principale, valuta);
        b.querySelectorAll('[data-iva-dettaglio]').forEach(function (d) { d.setAttribute('data-iva-dettaglio', principale); });
        scrivi(b.querySelector('[data-pieno]'), tot, valuta);
        scrivi(b.querySelector('[data-pieno-poi]'), tot, valuta);
        // L'equivalente mensile: prezzo annuale / 12, arrotondato al centesimo.
        scrivi(b.querySelector('[data-mensile]'), Math.round(tot / 12), valuta);
        // In media a struttura, e quante.
        scrivi(b.querySelector('[data-media]'), Math.round(tot / q), valuta);
        b.querySelectorAll('[data-quante]').forEach(function (x) { x.textContent = q; });   // 9M: anche «per N strutture» accanto al prezzo
        // 9M: il riquadro dello sconto sul rinnovo legato a questo campo (/piu-strutture) e il bottone che porta il numero.
        if (campo.id && q <= maxPrezzo) document.querySelectorAll('[data-rinnovo][data-campo="' + campo.id + '"]').forEach(function (r) {   // 9U: oltre il massimo resta il conto dell'ultimo prezzo
          ['[data-q-anno]', '[data-q-anno-s]'].forEach(function (s) { scrivi(r.querySelector(s), tot, valuta); });
          var ultimo = tot;
          r.querySelectorAll('[data-q-perc]').forEach(function (td) { ultimo = netto(tot, parseInt(td.getAttribute('data-q-perc'), 10)); scrivi(td, ultimo, valuta); });
          scrivi(r.querySelector('[data-q-min]'), ultimo, valuta);
          if (window.mhwIva) window.mhwIva.aggiorna(r);
        });
        if (window.mhwIva) window.mhwIva.aggiorna(b);
        b.querySelectorAll('[data-crea][data-href]').forEach(function (a) { a.href = a.getAttribute('data-href') + (a.getAttribute('data-href').indexOf('?') > -1 ? '&strutture=' + q : ''); });
        oltre(q > maxPrezzo, q);
      }
    }
    campo.addEventListener('input', aggiorna);

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
        inviaDaSolo();
      });
      return x;
    }
    // Passo «Pubblica» (data-aggiorna sul modulo): dopo l'ultimo clic su − o + il modulo parte da solo e il riepilogo (sconti,
    // IVA, totale) si ricalcola sul server, come quando si spunta la casella del giudizio. Il bottone «Aggiorna» resta solo senza JavaScript.
    var daSolo = campo.form && campo.form.hasAttribute('data-aggiorna'), attesa = null;
    if (daSolo) {
      var btnAgg = campo.form.querySelector('[data-aggiorna-btn]'); if (btnAgg) btnAgg.style.display = 'none';   // (hidden perderebbe contro il display del bottone)
      campo.form.addEventListener('submit', function () { var st = campo.form.querySelector('[data-aggiorna-stato]'); if (st) st.hidden = false; campo.readOnly = true; });
    }
    function inviaDaSolo() {
      if (!daSolo) return;
      clearTimeout(attesa);
      attesa = setTimeout(function () {
        if (!campo.checkValidity()) return;
        if (campo.form.requestSubmit) campo.form.requestSubmit(); else campo.form.submit();
      }, 700);
    }
    // 9A: oltre il massimo del listino sparisce il prezzo, resta «Ti facciamo una proposta su misura», il bottone
    // del piano si spegne e il riquadro del preventivo (fuori dalle schede) si apre con il numero già scritto.
    var boxOltre = b.querySelector('[data-oltre]'), prezzi = b.querySelectorAll('.price, .plan__mese, .plan__sconto-riga, .plan__poi, .scaglioni__media');
    var modulo = campo.form, radio = campo.getAttribute('data-sceglie') ? document.getElementById(campo.getAttribute('data-sceglie')) : null;
    var invio = modulo ? modulo.querySelectorAll('button:not([type=button]), input[type=submit]') : [];
    function oltre(si, q) {
      if (boxOltre) boxOltre.hidden = !si;
      for (var k = 0; k < prezzi.length; k++) prezzi[k].hidden = si;
      // 9U · pagine per tipologia: spariscono anche «+ IVA all'anno», «Sono un privato» e il bottone; il «Chiedi il preventivo»
      // che porta a /piu-strutture si porta dietro il numero scelto.
      b.querySelectorAll('[data-nascondi-oltre]').forEach(function (x) { x.hidden = si; });
      if (boxOltre && si) boxOltre.querySelectorAll('[data-oltre-href]').forEach(function (a) { a.href = a.getAttribute('data-oltre-href') + '?strutture=' + q + '#preventivo'; });
      var blocca = si && (!radio || radio.checked);
      for (var j = 0; j < invio.length; j++) { if (blocca) invio[j].setAttribute('disabled', ''); else invio[j].removeAttribute('disabled'); }
      if (preventivo && si) {
        preventivo.hidden = false;
        var dettagli = preventivo.closest('details'); if (dettagli) dettagli.open = true;   // 9N · pagine per tipologia: il modulo non resta chiuso
        var n = preventivo.querySelector('[data-preventivo-n]'), campoN = preventivo.querySelector('[data-preventivo-strutture]');
        if (n) n.textContent = q;
        if (campoN) campoN.value = q;
      } else if (preventivo && !si && preventivo.getAttribute('data-aperto') !== '1') {
        preventivo.hidden = true;
      }
    }
    if (preventivo && !preventivo.hidden) preventivo.setAttribute('data-aperto', '1');   // aperto dal server: resta
    if (radio && modulo) modulo.addEventListener('change', function () { aggiorna(); });
    aggiorna();
    var meno = bottone('\u2212', en ? 'One property less' : 'Una struttura in meno', -1), piu = bottone('+', en ? 'One more property' : 'Una struttura in più', 1);
    gruppo.appendChild(meno); gruppo.appendChild(campo); gruppo.appendChild(piu);
    function limiti() {
      var q = parseInt(campo.value, 10);
      meno.disabled = !(q > min); piu.disabled = !(q < max);
    }
    campo.addEventListener('input', limiti); limiti();
  })(blocchi[i]);
})();

/* 9T · «Più di 10 strutture? Chiedi un preventivo →» dalle pagine per tipologia: su /piu-strutture#preventivo il modulo è già aperto. */
(function () {
  var d = document.getElementById('preventivo');
  if (d && d.tagName === 'DETAILS' && location.hash === '#preventivo') d.open = true;
})();
