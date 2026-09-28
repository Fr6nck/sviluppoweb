/* Il pannello funziona anche senza JavaScript: ogni modulo si invia e salva.
   Questo file aggiunge solo comodità — righe in più, salvataggio mentre si
   scrive, anteprima che si aggiorna, copia del link — e se non si carica non
   si perde niente. */
(function () {
  'use strict';

  // I bottoni che servono solo con JavaScript compaiono solo con JavaScript.
  var nascosti = document.querySelectorAll('[data-togli-riga][hidden],[data-aggiungi-riga][hidden],[data-copia][hidden],[data-ricarica-anteprima][hidden]');
  for (var h = 0; h < nascosti.length; h++) nascosti[h].hidden = false;

  /* ---- Righe dei passaggi e degli elenchi -------------------------------- */
  function rinumera(box) {
    var righe = box.querySelectorAll('.r');
    for (var i = 0; i < righe.length; i++) {
      var n = righe[i].querySelector('.num');
      if (n) n.textContent = String(i + 1);
    }
  }
  document.addEventListener('click', function (e) {
    var add = e.target.closest('[data-aggiungi-riga]');
    if (add) {
      e.preventDefault();
      var box = document.getElementById(add.getAttribute('data-aggiungi-riga'));
      var modello = box && box.querySelector('.r');
      if (!modello) return;
      var nuova = modello.cloneNode(true);
      var inp = nuova.querySelector('input');
      inp.value = '';
      box.appendChild(nuova);
      rinumera(box);
      inp.focus();
      return;
    }
    var via = e.target.closest('[data-togli-riga]');
    if (via) {
      e.preventDefault();
      var riga = via.closest('.r'), contenitore = riga.parentNode;
      if (contenitore.querySelectorAll('.r').length > 1) riga.remove();
      else riga.querySelector('input').value = '';
      rinumera(contenitore);
      var f = contenitore.closest('form');
      if (f) f.dispatchEvent(new Event('input', { bubbles: true }));
      return;
    }
    var copia = e.target.closest('[data-copia]');
    if (copia) {
      e.preventDefault();
      var testo = copia.getAttribute('data-copia');
      var fatto = function () {
        var prima = copia.textContent;
        copia.textContent = copia.getAttribute('data-copiato') || 'Copiato';
        setTimeout(function () { copia.textContent = prima; }, 1600);
      };
      if (navigator.clipboard) navigator.clipboard.writeText(testo).then(fatto, function () {});
      return;
    }
    var ricarica = e.target.closest('[data-ricarica-anteprima]');
    if (ricarica) { e.preventDefault(); aggiornaAnteprima(); }
  });

  /* ---- Anteprima nel telefono -------------------------------------------- */
  function aggiornaAnteprima() {
    var f = document.querySelector('.phoneframe iframe');
    if (!f) return;
    try { f.contentWindow.location.reload(); } catch (e) { f.src = f.src; }
  }

  /* ---- Salvataggio mentre si scrive ---------------------------------------
     Solo i moduli con data-autosave. Si manda il modulo com'è, senza i file:
     quelli partono solo col bottone. */
  var stato = document.querySelector('[data-stato-salvataggio]');
  function mostra(t) { if (stato) stato.textContent = t; }
  function salva(form) {
    var dati = new FormData(form);
    var chiavi = [];
    dati.forEach(function (v, k) { if (v instanceof File) chiavi.push(k); });
    chiavi.forEach(function (k) { dati.delete(k); });
    dati.delete('dopo');
    mostra('Salvataggio…');
    return fetch(form.getAttribute('action') || location.href, {
      method: 'POST', body: dati, credentials: 'same-origin', headers: { 'Accept': 'application/json' }
    }).then(function (r) { return r.json().then(function (j) { return [r.ok, j]; }); })
      .then(function (x) {
        if (x[0] && x[1].ok) { mostra('Salvato'); aggiornaAnteprima(); }
        else mostra(x[1].errore || 'Non salvato: controlla i campi.');
      })
      .catch(function () { mostra('Non salvato: sei offline? Usa il bottone Salva.'); });
  }
  var forms = document.querySelectorAll('form[data-autosave]');
  for (var i = 0; i < forms.length; i++) (function (form) {
    var timer = null;
    form.addEventListener('input', function (e) {
      if (e.target && e.target.type === 'file') return;
      mostra('Modifiche non salvate');
      clearTimeout(timer);
      timer = setTimeout(function () { salva(form); }, 1200);
    });
  })(forms[i]);

  /* ---- Palette: prova dal vivo nell'anteprima ---------------------------- */
  var scelta = document.querySelector('[data-palette-scelta]');
  if (scelta) {
    var applica = function () {
      var pal = scelta.querySelector('input[name="palette"]:checked');
      var tono = scelta.querySelector('input[name="text_tone"]:checked');
      if (!pal) return;
      var css = pal.getAttribute('data-css');
      // Le varianti non leggibili di questa palette si spengono.
      var toni = (pal.getAttribute('data-toni') || '').split(',');
      var radio = scelta.querySelectorAll('input[name="text_tone"]');
      for (var i = 0; i < radio.length; i++) {
        var ok = toni.indexOf(radio[i].value) !== -1;
        radio[i].disabled = !ok;
        radio[i].closest('.tone').classList.toggle('tone--off', !ok);
        if (!ok && radio[i].checked) { radio[i].checked = false; }
      }
      tono = scelta.querySelector('input[name="text_tone"]:checked');
      if (!tono) {
        for (var j = 0; j < radio.length; j++) if (!radio[j].disabled) { radio[j].checked = true; tono = radio[j]; break; }
      }
      var f = document.querySelector('.phoneframe iframe');
      if (!f) return;
      try {
        var doc = f.contentWindow.document;
        var st = doc.getElementById('palette');
        if (st) st.textContent = css;
        // "testo scuro" = fondo chiaro = tema chiaro
        doc.documentElement.setAttribute('data-theme', tono && tono.value === 'chiaro' ? 'scuro' : 'chiaro');
      } catch (e) {}
    };
    scelta.addEventListener('change', applica);
    var fr = document.querySelector('.phoneframe iframe');
    if (fr) fr.addEventListener('load', applica);
  }

})();
