/* Il pannello funziona anche senza JavaScript: ogni modulo si invia e salva.
   Questo file aggiunge solo comodità — righe in più, salvataggio mentre si
   scrive, anteprima che si aggiorna, copia del link — e se non si carica non
   si perde niente. */
(function () {
  'use strict';

  // I bottoni che servono solo con JavaScript compaiono solo con JavaScript.
  var nascosti = document.querySelectorAll('[data-togli-riga][hidden],[data-aggiungi-riga][hidden],[data-copia][hidden],[data-ricarica-anteprima][hidden],[data-solo-js][hidden]');
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
  function mostra(t, ok, dove) {
    var el = dove || stato;
    if (!el) return;
    el.textContent = t;
    el.classList.toggle('stato--ok', !!ok);
  }
  function salva(form) {
    // Ogni modulo mostra il suo stato, se ne ha uno (l'editor aperto nella procedura).
    var qui = form.querySelector('[data-stato-salvataggio]') || stato;
    var mostraQui = function (t, ok) { mostra(t, ok, qui); };
    var dati = new FormData(form);
    var chiavi = [];
    dati.forEach(function (v, k) { if (v instanceof File) chiavi.push(k); });
    chiavi.forEach(function (k) { dati.delete(k); });
    dati.delete('dopo');
    mostraQui('Salvataggio…');
    return fetch(form.getAttribute('action') || location.href, {
      method: 'POST', body: dati, credentials: 'same-origin', headers: { 'Accept': 'application/json' }
    }).then(function (r) { return r.json().then(function (j) { return [r.ok, j]; }); })
      .then(function (x) {
        if (x[0] && x[1].ok) { mostraQui('Salvato', true); aggiornaAnteprima(); }
        else mostraQui(x[1].errore || 'Non salvato: controlla i campi.');
      })
      .catch(function () { mostraQui('Non salvato: sei offline? Usa il bottone Salva.'); });
  }
  var forms = document.querySelectorAll('form[data-autosave]');
  for (var i = 0; i < forms.length; i++) (function (form) {
    var timer = null;
    form.addEventListener('input', function (e) {
      if (e.target && (e.target.type === 'file' || e.target.closest('[data-no-autosave]'))) return;
      mostra('Modifiche non salvate', false, form.querySelector('[data-stato-salvataggio]'));
      clearTimeout(timer);
      timer = setTimeout(function () { salva(form); }, 1200);
    });
  })(forms[i]);

  /* ---- Caricamento dei file: trascina, anteprima, annulla ----------------
     L'input vero resta dentro la zona; qui si mostra cosa si è scelto e si
     accetta anche il file trascinato sopra. */
  function peso(b) {
    if (b < 1024 * 1024) return Math.max(1, Math.round(b / 1024)) + ' KB';
    return (b / 1024 / 1024).toFixed(1).replace('.', ',') + ' MB';
  }
  var zone = document.querySelectorAll('[data-drop]');
  for (var z = 0; z < zone.length; z++) (function (zona) {
    var input = zona.querySelector('.drop__input');
    var mini = zona.querySelector('[data-drop-mini]');
    var nome = zona.querySelector('[data-drop-nome]');
    var pesoEl = zona.querySelector('[data-drop-peso]');
    var prima = { pieno: zona.classList.contains('drop--pieno'), mini: mini.innerHTML, nome: nome.textContent, peso: pesoEl.textContent };
    var url = null;
    function ripristina() {
      if (url) { URL.revokeObjectURL(url); url = null; }
      zona.classList.remove('drop--nuovo');
      zona.classList.toggle('drop--pieno', prima.pieno);
      mini.innerHTML = prima.mini; nome.textContent = prima.nome; pesoEl.textContent = prima.peso;
    }
    function mostra() {
      var f = input.files && input.files[0];
      if (!f) { ripristina(); return; }
      if (url) URL.revokeObjectURL(url);
      zona.classList.add('drop--pieno', 'drop--nuovo');
      nome.textContent = f.name;
      pesoEl.textContent = peso(f.size) + ' · si salva con il bottone';
      if (/^image\//.test(f.type)) {
        url = URL.createObjectURL(f);
        mini.innerHTML = '';
        var img = document.createElement('img'); img.alt = ''; img.src = url; mini.appendChild(img);
      } else { url = null; mini.innerHTML = prima.mini.indexOf('<img') === -1 ? prima.mini : ''; }
    }
    input.addEventListener('change', mostra);
    zona.addEventListener('click', function (e) {
      if (e.target.closest('[data-drop-annulla]')) { e.preventDefault(); input.value = ''; ripristina(); input.focus(); }
    });
    ['dragenter', 'dragover'].forEach(function (t) {
      zona.addEventListener(t, function (e) { e.preventDefault(); zona.classList.add('drop--sopra'); });
    });
    ['dragleave', 'dragend'].forEach(function (t) {
      zona.addEventListener(t, function (e) { if (!zona.contains(e.relatedTarget)) zona.classList.remove('drop--sopra'); });
    });
    zona.addEventListener('drop', function (e) {
      e.preventDefault();
      zona.classList.remove('drop--sopra');
      var dt = e.dataTransfer;
      if (!dt || !dt.files || !dt.files.length) return;
      var accetta = (input.getAttribute('accept') || '').split(',');
      var f = dt.files[0];
      if (accetta[0] && accetta.indexOf(f.type) === -1) {
        pesoEl.textContent = 'Questo tipo di file non va bene qui.';
        zona.classList.add('drop--pieno', 'drop--nuovo'); nome.textContent = f.name; input.value = '';
        return;
      }
      try {
        var tieni = new DataTransfer(); tieni.items.add(f); input.files = tieni.files;
      } catch (err) { input.files = dt.files; }
      mostra();
    });
  })(zone[z]);

  /* ---- Menu ⋯ delle righe: uno aperto per volta, Esc e clic fuori chiudono -- */
  document.addEventListener('click', function (e) {
    var aperti = document.querySelectorAll('details.menu-riga[open]');
    for (var i = 0; i < aperti.length; i++) if (!aperti[i].contains(e.target)) aperti[i].open = false;
  });
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    var d = document.querySelector('details.menu-riga[open]');
    if (d) { d.open = false; d.querySelector('summary').focus(); }
  });

  /* ---- Trascina per ordinare ----------------------------------------------
     Si sposta la riga nella lista e poi si chiedono al server tanti «su» o
     «giù» quanti servono: le stesse rotte del menu, nessuna rotta nuova.
     Da tastiera e sul telefono restano «Sposta su / giù» nel menu ⋯. */
  var liste = document.querySelectorAll('[data-ordina]');
  for (var l = 0; l < liste.length; l++) (function (lista) {
    var trascinata = null, da = -1;
    var righe = function () { return Array.prototype.slice.call(lista.querySelectorAll('[data-riga]')); };
    righe().forEach(function (r) {
      var maniglia = r.querySelector('.riga__maniglia');
      if (!maniglia) return;
      maniglia.addEventListener('mousedown', function () { r.draggable = true; });
      r.addEventListener('dragstart', function (e) {
        trascinata = r; da = righe().indexOf(r);
        r.classList.add('riga--trascina');
        e.dataTransfer.effectAllowed = 'move';
        try { e.dataTransfer.setData('text/plain', ''); } catch (err) {}
      });
      r.addEventListener('dragend', function () {
        r.draggable = false; r.classList.remove('riga--trascina');
        righe().forEach(function (x) { x.classList.remove('riga--sopra'); });
      });
      r.addEventListener('dragover', function (e) {
        if (!trascinata || trascinata === r) return;
        e.preventDefault();
        righe().forEach(function (x) { x.classList.toggle('riga--sopra', x === r); });
      });
      r.addEventListener('drop', function (e) {
        if (!trascinata || trascinata === r) return;
        e.preventDefault();
        var tutte = righe(), a = tutte.indexOf(r);
        lista.insertBefore(trascinata, a > da ? r.nextSibling : r);
        var passi = a - da, riga = trascinata;
        trascinata = null;
        if (!passi) return;
        var token = riga.querySelector('input[name="_csrf"]');
        var corpo = function () {
          var f = new FormData();
          f.append('_csrf', token ? token.value : '');
          f.append('fai', passi < 0 ? 'su' : 'giu');
          f.append('torna', riga.getAttribute('data-torna') || '');
          return f;
        };
        var n = Math.abs(passi), catena = Promise.resolve();
        lista.setAttribute('aria-busy', 'true');
        for (var k = 0; k < n; k++) catena = catena.then(function () {
          return fetch(riga.getAttribute('data-azione'), { method: 'POST', body: corpo(), credentials: 'same-origin' });
        });
        catena.then(function () { location.reload(); }, function () { location.reload(); });
      });
    });
  })(liste[l]);

  /* ---- Righe ripetibili (reti, contatti, …) -------------------------------
     Aggiungi, togli, sposta su/giù (anche da tastiera) e trascina con la
     maniglia. L'ordine lo decide la posizione nella pagina: il server legge le
     righe nell'ordine in cui arrivano. */
  var cambiato = function (el) { var f = el.closest('form'); if (f) f.dispatchEvent(new Event('input', { bubbles: true })); };
  var rips = document.querySelectorAll('[data-rip]');
  for (var q = 0; q < rips.length; q++) (function (rip) {
    var righe = rip.querySelector('[data-rip-righe]'), modello = rip.querySelector('[data-rip-modello]');
    var add = rip.querySelector('[data-rip-aggiungi]'), max = parseInt(rip.getAttribute('data-rip-max'), 10) || 30;
    var tutte = function () { return righe.querySelectorAll('[data-rip-riga]'); };
    var vuote = righe.querySelectorAll('[data-rip-vuota]');
    if (tutte().length > vuote.length) for (var v = 0; v < vuote.length; v++) vuote[v].remove();
    var aggiorna = function () {
      var n = tutte();
      add.hidden = n.length >= max;
      for (var i = 0; i < n.length; i++) {
        n[i].querySelector('[data-rip-su]').disabled = i === 0;
        n[i].querySelector('[data-rip-giu]').disabled = i === n.length - 1;
      }
    };
    add.addEventListener('click', function () {
      var k = 'n' + Date.now().toString(36);
      var tmp = document.createElement('div');
      tmp.innerHTML = modello.innerHTML.split('__K__').join(k);
      var nuova = tmp.firstElementChild;
      righe.appendChild(nuova); aggiorna();
      var primo = nuova.querySelector('input:not([type=hidden]),select,textarea');
      if (primo) primo.focus();
    });
    rip.addEventListener('click', function (e) {
      var b = e.target.closest('[data-rip-su],[data-rip-giu],[data-rip-togli]');
      if (!b || !rip.contains(b)) return;
      var r = b.closest('[data-rip-riga]');
      if (b.hasAttribute('data-rip-togli')) {
        var dopo = r.nextElementSibling || r.previousElementSibling;
        r.remove(); aggiorna(); cambiato(rip);
        if (dopo && dopo.querySelector('[data-rip-togli]')) dopo.querySelector('[data-rip-togli]').focus(); else add.focus();
        return;
      }
      if (b.hasAttribute('data-rip-su') && r.previousElementSibling) righe.insertBefore(r, r.previousElementSibling);
      if (b.hasAttribute('data-rip-giu') && r.nextElementSibling) righe.insertBefore(r.nextElementSibling, r);
      aggiorna(); cambiato(rip);
      if (!b.disabled) b.focus(); else (b.hasAttribute('data-rip-su') ? r.querySelector('[data-rip-giu]') : r.querySelector('[data-rip-su]')).focus();
    });
    // Trascinamento dalla maniglia.
    var presa = null;
    righe.addEventListener('mousedown', function (e) {
      var m = e.target.closest('.rip__maniglia'); if (m) m.closest('[data-rip-riga]').draggable = true;
    });
    righe.addEventListener('dragstart', function (e) {
      presa = e.target.closest('[data-rip-riga]'); if (!presa) return;
      presa.classList.add('riga--trascina'); e.dataTransfer.effectAllowed = 'move';
      try { e.dataTransfer.setData('text/plain', ''); } catch (err) {}
    });
    righe.addEventListener('dragover', function (e) {
      if (!presa) return; e.preventDefault();
      var sopra = e.target.closest('[data-rip-riga]');
      if (!sopra || sopra === presa) return;
      var box = sopra.getBoundingClientRect();
      righe.insertBefore(presa, e.clientY > box.top + box.height / 2 ? sopra.nextElementSibling : sopra);
    });
    righe.addEventListener('dragend', function () {
      if (!presa) return;
      presa.classList.remove('riga--trascina'); presa.draggable = false; presa = null;
      aggiorna(); cambiato(rip);
    });
    add.hidden = false; aggiorna();
  })(rips[q]);

  /* ---- Suggerimenti a un tocco (lista «Prima di partire») ------------------ */
  document.addEventListener('click', function (e) {
    var s = e.target.closest('[data-suggerisci]'); if (!s) return;
    var box = document.getElementById(s.getAttribute('data-suggerisci'));
    if (!box) return;
    var vuota = null, ins = box.querySelectorAll('.r input');
    for (var i = 0; i < ins.length; i++) if (!ins[i].value.trim()) { vuota = ins[i]; break; }
    if (!vuota) {
      var modello = box.querySelector('.r'); if (!modello) return;
      var nuova = modello.cloneNode(true); vuota = nuova.querySelector('input'); vuota.value = '';
      box.appendChild(nuova); rinumera(box);
    }
    vuota.value = s.getAttribute('data-testo');
    vuota.focus(); vuota.setSelectionRange(vuota.value.length, vuota.value.length);
    cambiato(box);
  });

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
