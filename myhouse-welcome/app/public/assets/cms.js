/* Il pannello funziona anche senza JavaScript: ogni modulo si invia e salva.
   Questo file aggiunge solo comodità — righe in più, salvataggio mentre si
   scrive, anteprima che si aggiorna, copia del link — e se non si carica non
   si perde niente. */
(function () {
  'use strict';

  // I bottoni che servono solo con JavaScript compaiono solo con JavaScript.
  var nascosti = document.querySelectorAll('[data-togli-riga][hidden],[data-aggiungi-riga][hidden],[data-copia][hidden],[data-copia-da][hidden],[data-ricarica-anteprima][hidden],[data-solo-js][hidden]');
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
    var copia = e.target.closest('[data-copia],[data-copia-da]');
    if (copia) {
      e.preventDefault();
      // data-copia-da: si copia quello che c'è adesso nel campo (il messaggio di benvenuto, magari ritoccato).
      var da = copia.hasAttribute('data-copia-da') ? document.getElementById(copia.getAttribute('data-copia-da')) : null;
      var testo = da ? da.value : copia.getAttribute('data-copia');
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

  /* ---- Messaggio di benvenuto: «Apri WhatsApp» manda il testo come è adesso. */
  document.addEventListener('input', function (e) {
    if (!e.target.hasAttribute || !e.target.hasAttribute('data-benvenuto')) return;
    var wa = document.querySelector('[data-wa-da="' + e.target.id + '"]');
    if (wa) wa.href = 'https://wa.me/?text=' + encodeURIComponent(e.target.value);
    // 7D: anche l'email parte con il testo di adesso (l'oggetto resta).
    var em = document.querySelector('[data-mail-da="' + e.target.id + '"]');
    if (em) em.href = em.href.replace(/([?&]body=)[^&]*/, '$1' + encodeURIComponent(e.target.value));
  });

  /* ---- 8B · Impostazioni: i portali si spostano su e giù (l'ordine dei bottoni per l'ospite),
     le righe «altro portale» si aggiungono e si tolgono, il nome serve solo per «Altro…»,
     e la domanda sul sito mostra i campi giusti. */
  document.addEventListener('click', function (e) {
    var sp = e.target.closest('[data-sposta]');
    if (sp) {
      var li = sp.closest('[data-canale-riga]'), su = sp.getAttribute('data-sposta') === 'su';
      var altro = su ? li.previousElementSibling : li.nextElementSibling;
      if (altro) { li.parentNode.insertBefore(li, su ? altro : altro.nextSibling); sp.focus(); }
      return;
    }
    var agg = e.target.closest('[data-aggiungi-altro]');
    if (agg) {
      var box = agg.closest('[data-canali-altri]'), mod = box.querySelector('[data-modello]');
      var righe = box.querySelectorAll('[data-canale-altro]'), n = righe.length;
      var nuova = mod.cloneNode(true); nuova.removeAttribute('data-modello');
      nuova.querySelectorAll('[name],[id],[for]').forEach(function (el) {
        ['name', 'id', 'for'].forEach(function (a) { var v = el.getAttribute(a); if (v) el.setAttribute(a, v.replace(/\[\d+\]|-\d+-/g, function (m) { return m[0] === '[' ? '[' + n + ']' : '-' + n + '-'; })); });
        if (el.tagName === 'INPUT') el.value = ''; if (el.tagName === 'SELECT') el.selectedIndex = 0;
      });
      box.insertBefore(nuova, righe[righe.length - 1].nextSibling);
      altroKind(nuova.querySelector('[data-altro-kind]'));
      nuova.querySelector('select').focus();
      return;
    }
    var tog = e.target.closest('[data-togli-altro]');
    if (tog) {
      var riga = tog.closest('[data-canale-altro]');
      riga.querySelectorAll('input').forEach(function (i) { i.value = ''; }); riga.querySelector('select').selectedIndex = 0;
      if (!riga.hasAttribute('data-modello')) riga.remove(); else altroKind(riga.querySelector('[data-altro-kind]'));
    }
  });
  function altroKind(sel) {
    if (!sel) return;
    var f = sel.closest('[data-canale-altro]').querySelector('[data-solo-altro]');
    if (f) f.hidden = sel.value !== 'altro';
  }
  document.querySelectorAll('[data-altro-kind]').forEach(altroKind);
  document.addEventListener('change', function (e) {
    if (e.target.matches && e.target.matches('[data-altro-kind]')) altroKind(e.target);
    if (e.target.matches && e.target.matches('[data-ha-sito]')) {
      var si = e.target.value === '1';
      document.querySelectorAll('[data-sito-si]').forEach(function (x) { x.hidden = !si; });
      document.querySelectorAll('[data-sito-no]').forEach(function (x) { x.hidden = si; });
    }
  });

  /* ---- 8B · QR & Link: i messaggi per lingua (linguette), il testo pronto con {link} e {host} riempiti,
     l'anteprima stile WhatsApp, «Copia» e «Apri WhatsApp» con il testo di adesso, l'avviso se manca il link. */
  function prontoDi(pan) {
    var t = pan.querySelector('[data-modello]').value;
    return t.split('{link}').join(pan.getAttribute('data-link')).split('{host}').join(pan.getAttribute('data-host'));
  }
  function aggiornaMsg(pan) {
    var t = prontoDi(pan), esc = t.replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; });
    var a = pan.querySelector('[data-anteprima-msg]');
    if (a) a.innerHTML = esc.replace(/(https?:\/\/\S+)/g, '<span class="wa-link">$1</span>').replace(/\n/g, '<br>');
    var wa = pan.querySelector('[data-wa-msg]'); if (wa) wa.href = 'https://wa.me/?text=' + encodeURIComponent(t);
    var m = pan.querySelector('[data-manca-link]'); if (m) m.hidden = pan.querySelector('[data-modello]').value.indexOf('{link}') !== -1;
  }
  document.querySelectorAll('[data-messaggio-box]').forEach(function (box) {
    var tabs = box.querySelectorAll('[data-linguetta]'), pans = box.querySelectorAll('[data-pannello]');
    if (tabs.length) pans.forEach(function (p, i) { p.hidden = i > 0; });
    tabs.forEach(function (t, i) {
      t.addEventListener('click', function () {
        tabs.forEach(function (x, j) { x.setAttribute('aria-selected', j === i ? 'true' : 'false'); });
        pans.forEach(function (p, j) { p.hidden = j !== i; });
      });
      t.addEventListener('keydown', function (e) {
        if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') return;
        var n = (i + (e.key === 'ArrowRight' ? 1 : tabs.length - 1)) % tabs.length; tabs[n].click(); tabs[n].focus();
      });
    });
    pans.forEach(function (p) { p.querySelector('[data-modello]').addEventListener('input', function () { aggiornaMsg(p); }); });
  });
  document.addEventListener('click', function (e) {
    var c = e.target.closest('[data-copia-msg]'); if (!c) return;
    var pan = c.closest('[data-pannello]'), prima = c.innerHTML;
    if (navigator.clipboard) navigator.clipboard.writeText(prontoDi(pan)).then(function () {
      c.textContent = c.getAttribute('data-copiato'); setTimeout(function () { c.innerHTML = prima; }, 1600);
    }, function () {});
  });
  document.querySelectorAll('[data-copia-msg][hidden]').forEach(function (b) { if (navigator.clipboard) b.hidden = false; });

  /* ---- Una casella che si salva da sola (l'anteprima dell'invito): senza JS c'è il bottone «Salva». */
  document.addEventListener('change', function (e) {
    if (e.target.hasAttribute && e.target.hasAttribute('data-invia-subito') && e.target.form) e.target.form.submit();
  });

  /* ---- Anteprima nel telefono -------------------------------------------- */
  function aggiornaAnteprima() {
    var f = document.querySelector('.phoneframe iframe');
    if (!f) return;
    try { f.contentWindow.location.reload(); } catch (e) { f.src = f.src; }
  }

  /* ---- Telefoni col prefisso: un campo vuoto prende «+39 » quando lo tocchi,
     e se resta solo il prefisso torna vuoto. I numeri brevi (112, 113…) si
     scrivono cancellando il prefisso; il server li lascia comunque come sono. */
  document.addEventListener('focusin', function (e) {
    var el = e.target;
    if (el && el.matches && el.matches('input[data-prefisso]') && el.value.trim() === '') {
      el.value = el.getAttribute('data-prefisso');
      try { el.setSelectionRange(el.value.length, el.value.length); } catch (x) {}
    }
  });
  document.addEventListener('focusout', function (e) {
    var el = e.target;
    if (el && el.matches && el.matches('input[data-prefisso]') && el.value.trim() === el.getAttribute('data-prefisso').trim()) el.value = '';
  });

  /* ---- Tastiera aperta (telefono): la barra in basso si toglie di mezzo ----
     Con il dito si scrive in un campo: la barra fissa non deve coprirlo. */
  if (window.matchMedia && matchMedia('(pointer: coarse)').matches) {
    var scrive = function (el) { return el && el.matches && el.matches('input:not([type=checkbox]):not([type=radio]):not([type=file]):not([type=submit]):not([type=button]),textarea,select'); };
    document.addEventListener('focusin', function (e) { if (scrive(e.target)) document.documentElement.classList.add('tastiera'); });
    document.addEventListener('focusout', function () {
      setTimeout(function () { if (!scrive(document.activeElement)) document.documentElement.classList.remove('tastiera'); }, 150);
    });
  }
  // La scheda del pannello aperta resta in vista anche quando le schede scorrono di lato.
  var schedaAttiva = document.querySelector('.schede-guida [aria-current]');
  if (schedaAttiva && schedaAttiva.scrollIntoView) schedaAttiva.scrollIntoView({ block: 'nearest', inline: 'center' });

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
        if (x[0] && x[1].ok) { form._sporco = false; mostraQui(((form.querySelector('[data-stato-salvataggio]') || stato || {}).getAttribute ? (form.querySelector('[data-stato-salvataggio]') || stato).getAttribute('data-salvato') : null) || 'Salvato', true); aggiornaAnteprima(); }
        else mostraQui(x[1].errore || 'Non salvato: controlla i campi.');
      })
      .catch(function () { mostraQui('Non salvato: controlla la connessione, poi premi Salva.'); });
  }
  var forms = document.querySelectorAll('form[data-autosave]');
  for (var i = 0; i < forms.length; i++) (function (form) {
    var timer = null;
    form.addEventListener('input', function (e) {
      if (e.target && (e.target.type === 'file' || e.target.closest('[data-no-autosave]'))) return;
      var statoEl = form.querySelector('[data-stato-salvataggio]') || stato;
      mostra((statoEl && statoEl.getAttribute('data-non-salvato')) || 'Modifiche non salvate', false, form.querySelector('[data-stato-salvataggio]'));
      form._sporco = true;
      clearTimeout(timer);
      timer = setTimeout(function () { salva(form); }, 1200);
    });
  })(forms[i]);

  /* ---- «Già in <struttura>» ----------------------------------------------
     Il modulo che copia un luogo o una riga da un'altra struttura sta subito
     dopo l'editor: prima di mandarlo si salva quello che si è appena scritto,
     altrimenti la pagina che si ricarica lo perderebbe. */
  var daAltra = document.querySelectorAll('form[data-salva-prima]');
  for (var d = 0; d < daAltra.length; d++) (function (form) {
    form.addEventListener('submit', function (e) {
      var editor = form.previousElementSibling;
      if (!editor || !editor.matches('form[data-autosave]') || !editor._sporco) return;
      e.preventDefault();
      var b = e.submitter;
      if (b && b.name) {
        var h = document.createElement('input');
        h.type = 'hidden'; h.name = b.name; h.value = b.value;
        form.appendChild(h);
      }
      salva(editor).then(function () { form.submit(); });
    });
  })(daAltra[d]);
  // Traduzioni suggerite: «Modifica» porta il testo nel campo, da correggere; il salvataggio
  // automatico lo registra come traduzione tua e la suggerita sparisce. Senza JavaScript la approva.
  document.addEventListener('click', function (e) {
    var b = e.target.closest && e.target.closest('[data-modifica]');
    if (!b) return;
    var campo = document.getElementById(b.getAttribute('data-modifica'));
    if (!campo) return;
    e.preventDefault();
    campo.value = b.getAttribute('data-testo');
    campo.dispatchEvent(new Event('input', { bubbles: true }));
    var box = b.closest('.suggerita');
    if (box) box.remove();
    campo.focus();
  });
  // Dati di fatturazione: persona fisica o azienda. Si vedono solo i campi del tipo scelto
  // (data-per) e l'etichetta giusta (data-per-etichetta); senza un tipo, solo i campi comuni.
  var fatt = document.querySelectorAll('form[data-tipo-fatt]');
  for (var ft = 0; ft < fatt.length; ft++) (function (form) {
    function aggiorna() {
      var scelto = form.querySelector('input[name="billing_type"]:checked');
      var t = scelto ? scelto.value : '';
      var campi = form.querySelectorAll('[data-per]');
      for (var i = 0; i < campi.length; i++) campi[i].hidden = campi[i].getAttribute('data-per') !== t;
      var et = form.querySelectorAll('[data-per-etichetta]');
      for (var j = 0; j < et.length; j++) et[j].hidden = et[j].getAttribute('data-per-etichetta') !== t;
    }
    form.addEventListener('change', function (e) { if (e.target.name === 'billing_type') aggiorna(); });
    aggiorna();
  })(fatt[ft]);

  // Cambio di piano: «scegli le N sezioni da tenere», con il conteggio; raggiunto il numero, le altre si fermano.
  var gruppi = document.querySelectorAll('[data-conta-max]');
  for (var gc = 0; gc < gruppi.length; gc++) (function (g) {
    var max = parseInt(g.getAttribute('data-conta-max'), 10), out = g.querySelector('[data-conta]');
    var box = g.querySelectorAll('input[type=checkbox]');
    function conta() {
      var n = 0;
      for (var i = 0; i < box.length; i++) if (box[i].checked) n++;
      for (var j = 0; j < box.length; j++) box[j].disabled = !box[j].checked && n >= max;
      if (out) out.textContent = n + ' di ' + max + ' scelte.';
    }
    g.addEventListener('change', conta);
    conta();
  })(gruppi[gc]);

  // 9F · Provincia → Regione: scritta la sigla, la regione si completa da sola (se è vuota o era quella di prima).
  var provInput = document.querySelector('[data-provincia-regione]');
  if (provInput) (function (prov) {
    var reg = document.getElementById(prov.getAttribute('data-provincia-regione'));
    var lista = document.getElementById('province-elenco');
    var regioneDi = {};
    if (lista) for (var oi = 0; oi < lista.options.length; oi++) regioneDi[lista.options[oi].value] = lista.options[oi].getAttribute('data-regione') || '';
    var messa = '';
    prov.addEventListener('change', function () {
      var sg = prov.value.trim().toUpperCase();
      if (sg.length === 2) prov.value = sg;
      if (!reg || !regioneDi[sg]) return;
      if (reg.value.trim() === '' || reg.value === messa) { reg.value = regioneDi[sg]; messa = regioneDi[sg]; reg.dispatchEvent(new Event('input', { bubbles: true })); }
    });
  })(provInput);
  // «Anche il tuo sito»: dalla 9F il riquadro resta sempre (niente ✕, niente valore salvato nel browser).
  var invito = document.querySelectorAll('[data-sito-invito]');
  try { localStorage.removeItem('mhw-sito-invito'); } catch (e) {}
  for (var si = 0; si < invito.length; si++) (function (box) {
    var x = box.querySelector('[data-sito-chiudi]');
    if (!x) return;   // 9F: il riquadro non si chiude più
    x.hidden = false;
    x.addEventListener('click', function () {
      try { localStorage.setItem('mhw-sito-invito', String(Date.now() + 30 * 86400000)); } catch (e) {}
      box.hidden = true;
    });
  })(invito[si]);
  // Con più di 12 luoghi già scritti, un campo per cercarli.
  var cerche = document.querySelectorAll('[data-gia-cerca]');
  for (var c = 0; c < cerche.length; c++) (function (campo) {
    var box = campo.closest('[data-gia-altrove]');
    campo.addEventListener('input', function () {
      var q = campo.value.trim().toLowerCase();
      var chip = box.querySelectorAll('[data-nome]');
      for (var j = 0; j < chip.length; j++) chip[j].hidden = q !== '' && chip[j].getAttribute('data-nome').indexOf(q) === -1;
      var gruppi = box.querySelectorAll('.suggerimenti');
      for (var g = 0; g < gruppi.length; g++) gruppi[g].hidden = !gruppi[g].querySelector('[data-nome]:not([hidden])');
    });
  })(cerche[c]);

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
      pesoEl.textContent = peso(f.size) + ' · si carica quando salvi';
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
      // 9K: una foto HEIC passa: foto-leggere.js la converte, o spiega come caricarla.
      if (accetta[0] && accetta.indexOf(f.type) === -1 && !(/hei[cf]$/i.test(f.type) || /\.hei[cf]$/i.test(f.name))) {
        pesoEl.textContent = 'Questo tipo di file non va bene qui.';
        zona.classList.add('drop--pieno', 'drop--nuovo'); nome.textContent = f.name; input.value = '';
        return;
      }
      try {
        var tieni = new DataTransfer(); tieni.items.add(f); input.files = tieni.files;
      } catch (err) { input.files = dt.files; }
      // Il «change» (e non solo l'anteprima): così foto-leggere.js prepara anche la foto trascinata.
      input.dispatchEvent(new Event('change', { bubbles: true }));
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
    // Campi che spariscono per un valore di un altro campo della riga (i costi di un posto privato).
    // Fase 6G: anche il contrario (data-solo-con: si vede solo con quei valori, gli eventi) e
    // l'etichetta che cambia («Dal» diventa «Giorno» con un evento di un giorno).
    var nascondi = function (r) {
      var valore = function (nome) {
        var scelto = r.querySelector('[name$="[' + nome + ']"]:checked') || r.querySelector('select[name$="[' + nome + ']"]');
        return scelto ? scelto.value : '';
      };
      var cc = r.querySelectorAll('[data-nascosto-con]');
      for (var i = 0; i < cc.length; i++) {
        cc[i].hidden = (cc[i].getAttribute('data-nascosto-valori') || '').split(',').indexOf(valore(cc[i].getAttribute('data-nascosto-con'))) !== -1;
      }
      var ss = r.querySelectorAll('[data-solo-con]');
      for (var j = 0; j < ss.length; j++) {
        ss[j].hidden = (ss[j].getAttribute('data-solo-valori') || '').split(',').indexOf(valore(ss[j].getAttribute('data-solo-con'))) === -1;
      }
      var ee = r.querySelectorAll('[data-etichetta-con]');
      for (var k = 0; k < ee.length; k++) {
        var lab = ee[k].querySelector('label'); if (!lab) continue;
        if (!lab.hasAttribute('data-originale')) lab.setAttribute('data-originale', lab.textContent);
        var mappa = {}; try { mappa = JSON.parse(ee[k].getAttribute('data-etichette')); } catch (e) {}
        lab.textContent = mappa[valore(ee[k].getAttribute('data-etichetta-con'))] || lab.getAttribute('data-originale');
      }
    };
    rip.addEventListener('change', function (e) { var r = e.target.closest('[data-rip-riga]'); if (r) nascondi(r); });
    // Righe compresse (6D · X2): la linea di riepilogo con i valori della riga, senza la password.
    var riassunto = function (r) {
      var parti = [];
      Array.prototype.forEach.call(r.querySelectorAll('.rip__c'), function (c) {
        if (c.hidden || parti.length >= 4) return;
        var el = c.querySelector('input[type=radio]:checked');
        if (el) { if (el.value !== '') parti.push(el.parentNode.textContent.trim()); return; }
        el = c.querySelector('select');
        if (el) { if (el.value !== '') parti.push(el.options[el.selectedIndex].text); return; }
        if (c.querySelector('.rip__giorni')) {
          var gg = c.querySelectorAll('input:checked');
          if (gg.length) parti.push(Array.prototype.map.call(gg, function (g) { return g.parentNode.textContent.trim(); }).join(', '));
          return;
        }
        el = c.querySelector('input[type=text],input[type=tel],input[type=time],input[type=date],textarea');
        if (el && el.type === 'date' && /^\d{4}-\d{2}-\d{2}$/.test(el.value)) {   // le date per esteso: «3 ottobre 2027»
          var d = el.value.split('-');
          parti.push(parseInt(d[2], 10) + ' ' + ['gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno', 'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre'][parseInt(d[1], 10) - 1] + ' ' + d[0]);
          return;
        }
        if (el && !el.hasAttribute('data-segreto') && el.value.trim() !== '') parti.push(el.value.trim().split('\n')[0] + (c.querySelector('.soldi') ? ' €' : ''));
      });
      return parti.join(' · ');
    };
    var chiudi = function (r, si) {
      var b = r.querySelector('[data-rip-apri]'), campi = r.querySelector('.rip__campi');
      if (!b || !campi) return;
      campi.hidden = si; r.classList.toggle('rip__riga--chiusa', si);
      b.setAttribute('aria-expanded', si ? 'false' : 'true');
      b.querySelector('[data-rip-riassunto]').textContent = si ? (riassunto(r) || 'Riga vuota') : '';
      b.querySelector('[data-rip-azione]').textContent = si ? 'Apri' : 'Comprimi';
    };
    Array.prototype.forEach.call(tutte(), function (r) {
      var b = r.querySelector('[data-rip-apri]'); if (b) b.hidden = false;
      if (r.hasAttribute('data-rip-chiusa')) { nascondi(r); chiudi(r, true); }
    });
    rip.addEventListener('click', function (e) {
      var b = e.target.closest('[data-rip-apri]'); if (!b || !rip.contains(b)) return;
      var r = b.closest('[data-rip-riga]'), apri = b.getAttribute('aria-expanded') === 'false';
      chiudi(r, !apri);
      if (apri) { var primo = r.querySelector('.rip__campi input:not([type=hidden]),.rip__campi select,.rip__campi textarea'); if (primo) primo.focus(); }
    });
    // Un campo non valido dentro una riga compressa: la riga si apre, così il browser può mostrarlo.
    rip.addEventListener('invalid', function (e) { var r = e.target.closest('[data-rip-riga]'); if (r && r.classList.contains('rip__riga--chiusa')) chiudi(r, false); }, true);
    var aggiorna = function () {
      var n = tutte();
      add.hidden = n.length >= max;
      for (var i = 0; i < n.length; i++) {
        n[i].querySelector('[data-rip-su]').disabled = i === 0;
        n[i].querySelector('[data-rip-giu]').disabled = i === n.length - 1;
        // Il numero nell'intestazione («Parcheggio 2») segue la posizione.
        var num = n[i].querySelector('[data-rip-num]'); if (num) num.textContent = String(i + 1);
        // Campi che servono solo con due o più righe (la zona del Wi-Fi).
        var soloPiu = n[i].querySelectorAll('[data-rip-solo-piu]');
        for (var j = 0; j < soloPiu.length; j++) soloPiu[j].hidden = n.length < 2;
        nascondi(n[i]);
      }
    };
    // Ogni riga nuova riceve subito il suo id: così i salvataggi automatici
    // successivi la riconoscono e le traduzioni restano agganciate.
    var nuovoId = function () {
      var b = new Uint8Array(4); (window.crypto || window.msCrypto).getRandomValues(b);
      return 'r' + Array.prototype.map.call(b, function (x) { return ('0' + x.toString(16)).slice(-2); }).join('');
    };
    var daiId = function (r) {
      var h = r.querySelector('input[type=hidden][name$="[id]"]');
      if (h && !h.value) h.value = nuovoId();
    };
    Array.prototype.forEach.call(tutte(), daiId);
    var aggiungi = function () {
      var k = 'n' + Date.now().toString(36) + Math.floor(Math.random() * 1000);
      var tmp = document.createElement('div');
      tmp.innerHTML = modello.innerHTML.split('__K__').join(k);
      var nuova = tmp.firstElementChild;
      daiId(nuova);
      var ap = nuova.querySelector('[data-rip-apri]'); if (ap) ap.hidden = false;   // la riga nuova nasce aperta
      righe.appendChild(nuova); aggiorna();
      return nuova;
    };
    add.addEventListener('click', function () {
      var primo = aggiungi().querySelector('input:not([type=hidden]),select,textarea');
      if (primo) primo.focus();
    });
    // Righe pronte (Guardia medica, 112…): una riga nuova già compilata, o la riga vuota se c'è.
    rip.addEventListener('click', function (e) {
      var b = e.target.closest('[data-rip-preset]'); if (!b) return;
      var valori = JSON.parse(b.getAttribute('data-rip-preset'));
      var n = tutte(), r = null;
      for (var i = 0; i < n.length; i++) {
        var campi = n[i].querySelectorAll('input:not([type=hidden]):not([type=checkbox]):not([type=radio]):not([type=file]),textarea');
        var vuota = true;
        for (var j = 0; j < campi.length; j++) if (campi[j].value.trim() !== '') vuota = false;
        if (vuota) { r = n[i]; break; }
      }
      if (!r) { if (tutte().length >= max) return; r = aggiungi(); }
      var primo = null;
      Object.keys(valori).forEach(function (sub) {
        var c = r.querySelector('[name$="[' + sub + ']"]');
        // Le pillole sono radio: si spunta quella col valore, non si cambia il valore.
        if (c && c.type === 'radio') { c = r.querySelector('[name$="[' + sub + ']"][value="' + valori[sub] + '"]'); if (c) c.checked = true; c = null; }
        if (c) { c.value = valori[sub]; primo = primo || c; }
      });
      cambiato(rip);
      var tel = r.querySelector('input[type=tel]');
      // Riga pronta con un nome (Guardia medica): manca il telefono. Con solo il tipo (Taxi): si scrive il nome.
      (primo ? (tel && !tel.value ? tel : primo) : (r.querySelector('input[type=text],textarea') || r.querySelector('input:not([type=hidden])'))).focus();
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

  /* ---- La password del Wi-Fi: coperta, con «Mostra» -------------------------
     Senza JavaScript resta in chiaro (si scrive meglio); con JavaScript si copre. */
  var segreti = document.querySelectorAll('input[data-segreto]');
  var copri = function (inp) {
    if (inp.dataset.coperto) return; inp.dataset.coperto = '1';
    var b = inp.parentNode.querySelector('[data-mostra-segreto]'); if (!b) return;
    inp.type = 'password'; b.hidden = false;
  };
  for (var sg = 0; sg < segreti.length; sg++) copri(segreti[sg]);
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-mostra-segreto]'); if (!b) return;
    var inp = b.parentNode.querySelector('input');
    var mostra = inp.type === 'password';
    inp.type = mostra ? 'text' : 'password';
    b.textContent = mostra ? 'Nascondi' : 'Mostra';
  });
  // Le righe aggiunte dopo (dal modello) hanno anche loro la password da coprire.
  document.addEventListener('focusin', function (e) { if (e.target.matches && e.target.matches('input[data-segreto]')) copri(e.target); });

  /* ---- Interruttori: i campi legati si vedono solo da accesi ----------------
     (l'orario del silenzio). Accendendo, gli orari vuoti prendono i valori consigliati. */
  var interruttori = document.querySelectorAll('[data-interruttore]');
  for (var it = 0; it < interruttori.length; it++) (function (cb) {
    var box = document.getElementById(cb.getAttribute('data-interruttore')); if (!box) return;
    var segui = function () {
      box.hidden = !cb.checked;
      if (cb.checked) {
        var pre = box.querySelectorAll('[data-predefinito]');
        for (var p = 0; p < pre.length; p++) if (!pre[p].value) pre[p].value = pre[p].getAttribute('data-predefinito');
      }
    };
    cb.addEventListener('change', segui); segui();
  })(interruttori[it]);

  /* ---- Pillole con «Altro…»: il testo libero si vede solo quando serve ------
     (categoria ed etichetta del luogo). Senza JavaScript si vede sempre. */
  var apri = document.querySelectorAll('input[type=radio][data-apre]');
  for (var ap = 0; ap < apri.length; ap++) (function (r) {
    var box = document.getElementById(r.getAttribute('data-apre')); if (!box) return;
    var gruppo = document.querySelectorAll('input[type=radio][name="' + r.name + '"]');
    var segui = function () { box.hidden = !r.checked; };
    for (var g = 0; g < gruppo.length; g++) gruppo[g].addEventListener('change', segui);
    segui();
  })(apri[ap]);

  /* ---- Tipologia «Altro»: il campo «Che tipo di struttura è?» ---------------- */
  var altro = document.querySelector('[data-se-altro]');
  if (altro) {
    var tipi = document.querySelectorAll('input[name=property_type]');
    var seguiTipo = function () { var x = document.querySelector('input[name=property_type]:checked'); altro.hidden = !(x && x.value === 'altro'); };
    for (var ti = 0; ti < tipi.length; ti++) tipi[ti].addEventListener('change', seguiTipo);
    seguiTipo();
  }

  /* ---- Foto e PDF nelle righe: il nome del file scelto accanto al bottone ---- */
  document.addEventListener('change', function (e) {
    var inp = e.target; if (!inp.matches || !inp.matches('.rip__carica input[type=file]')) return;
    var nome = inp.closest('.rip__file').querySelector('[data-rip-file-nome]');
    if (nome) nome.textContent = inp.files && inp.files[0] ? inp.files[0].name + ' — si carica quando salvi' : 'Nessun file scelto';
  });

  /* ---- Scheda luogo: dal link di Google Maps il nome e i minuti a piedi -----
     Si chiede al server (che segue i link brevi); si compila solo ciò che è
     ancora vuoto. Se non si ricava niente, nessun errore: si scrive a mano. */
  var mappe = document.querySelectorAll('[data-mappe]');
  for (var mq = 0; mq < mappe.length; mq++) (function (inp) {
    var form = inp.closest('form'), stato = form.querySelector('[data-mappe-stato]'), ultimo = inp.value.trim();
    var leggi = function () {
      var url = inp.value.trim();
      if (url === '' || url === ultimo || !/^https?:\/\//i.test(url)) return;
      ultimo = url;
      var dati = new FormData(); dati.append('url', url);
      var t = form.querySelector('input[name=_csrf]'); if (t) dati.append('_csrf', t.value);
      if (stato) stato.textContent = 'Leggo il link…';
      fetch(inp.getAttribute('data-mappe'), { method: 'POST', body: dati, credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          var fatto = [];
          var nome = form.querySelector('[name=name]'), piedi = form.querySelector('[name=walk_minutes]');
          if (j.name && nome && nome.value.trim() === '') { nome.value = j.name; fatto.push('il nome'); }
          if (j.walk_minutes && piedi && !piedi.value) { piedi.value = j.walk_minutes; fatto.push(j.walk_minutes + ' min a piedi (stima, modificabile, in «Altri dettagli»)'); }
          if (stato) stato.textContent = fatto.length ? 'Dal link: ' + fatto.join(', ') + '.'
            : (j.ok ? 'Link letto. Il resto scrivilo tu.' : 'Dal link non si ricava niente: scrivi tu nome e dettagli.');
        })
        .catch(function () { if (stato) stato.textContent = ''; });
    };
    inp.addEventListener('change', leggi);
    inp.addEventListener('paste', function () { setTimeout(leggi, 0); });
  })(mappe[mq]);

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
      // 9J · La riga e la casella di «Bianco e nero» solo con quella palette.
      var soloBn = scelta.querySelectorAll('[data-solo-palette]');
      for (var k = 0; k < soloBn.length; k++) soloBn[k].hidden = soloBn[k].getAttribute('data-solo-palette') !== pal.value;
      var bn = scelta.querySelector('input[name="photos_bw"]');
      var f = document.querySelector('.phoneframe iframe');
      if (!f) return;
      try {
        var doc = f.contentWindow.document;
        var st = doc.getElementById('palette');
        if (st) st.textContent = css;
        // 9J · le classi della palette (bordo dei riquadri, pillole) e le foto in bianco e nero nell'anteprima
        var cl = doc.documentElement.classList;
        for (var c = cl.length - 1; c >= 0; c--) if (cl[c].indexOf('palette-') === 0) cl.remove(cl[c]);
        cl.add('palette-' + pal.value);
        cl.toggle('foto-bn', pal.value === 'monocromo' && !!(bn && bn.checked));
        // "testo scuro" = fondo chiaro = tema chiaro
        doc.documentElement.setAttribute('data-theme', tono && tono.value === 'chiaro' ? 'scuro' : 'chiaro');
      } catch (e) {}
    };
    scelta.addEventListener('change', applica);
    var fr = document.querySelector('.phoneframe iframe');
    if (fr) fr.addEventListener('load', applica);
  }

})();

/* I campi di una sezione che valgono solo per certi valori di una scelta (Riscaldamento e aria
   condizionata: gli orari solo «a orari»). Senza JavaScript restano tutti visibili. */
(function () {
  var campi = document.querySelectorAll('[data-campo-solo-con]');
  if (!campi.length) return;
  var aggiorna = function () {
    for (var i = 0; i < campi.length; i++) {
      var c = campi[i], f = c.closest('form') || document;
      var nome = c.getAttribute('data-campo-solo-con');
      var scelto = f.querySelector('input[name="' + nome + '"]:checked') || f.querySelector('select[name="' + nome + '"]');
      c.hidden = (c.getAttribute('data-solo-valori') || '').split(',').indexOf(scelto ? scelto.value : '') === -1;
    }
  };
  document.addEventListener('change', function (e) { if (e.target.name && document.querySelector('[data-campo-solo-con="' + e.target.name + '"]')) aggiorna(); });
  aggiorna();

  // 7C · Recensioni: «Prova il link» segue quello che si scrive nel campo.
  document.querySelectorAll('[data-prova-campo]').forEach(function (campo) {
    var link = campo.closest('.recensione-scheda').querySelector('[data-prova-link]');
    campo.addEventListener('input', function () {
      var v = campo.value.trim(), ok = /^https?:\/\/\S+\.\S+/.test(v);
      link.hidden = !ok; if (ok) link.href = v;
    });
  });
  // 7C · Prenotazione diretta: l'anteprima della scheda dell'ospite e l'avviso «senza il link».
  var dir = document.querySelector('.diretta');
  if (dir) (function () {
    var url = dir.querySelector('[data-diretta=url]'), cod = dir.querySelector('[data-diretta=codice]'), sc = dir.querySelector('[data-diretta=sconto]');
    var off = dir.querySelector('[data-diretta-offerta]'), copia = dir.querySelector('[data-diretta-copia]'), avviso = dir.querySelector('[data-diretta-avviso]');
    function aggiorna() {
      var c = cod.value.replace(/\s+/g, ''), s = sc.value.trim().replace(/^[-\u2212]+/, '');
      off.hidden = copia.hidden = c === '';
      off.textContent = s !== '' ? '\u2212' + s + ' con il codice ' : 'Con il codice ';
      var b = document.createElement('b'); b.textContent = c; off.appendChild(b);
      avviso.hidden = !(c !== '' && url.value.trim() === '');
    }
    [url, cod, sc].forEach(function (x) { x.addEventListener('input', aggiorna); });
  })();

})();

/* ---- 9F · Traduzione in corso: un blocco a sé, su ogni pagina del pannello (non dentro quello della prenotazione diretta). */
(function () {
  'use strict';
  /* ---- 9F · Traduzione in corso ------------------------------------------------
     Con JavaScript le suggerite si chiedono a gruppi (una lingua, pochi campi per volta) all'endpoint
     …/suggerite.json: il bottone si disabilita con il cerchio che gira, la barra avanza, ogni campo
     tradotto compare con una sfumatura; alla fine «Fatto» (verde, 3 secondi) oppure l'errore (giallo)
     con «Riprova». Senza JavaScript restano i moduli di prima. */
  function chiediSuggerite(url, csrf, dati) {
    var corpo = new URLSearchParams(); corpo.set('_csrf', csrf);
    Object.keys(dati).forEach(function (k) { corpo.set(k, dati[k]); });
    return fetch(url, { method: 'POST', body: corpo, credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'fetch' } })
      .then(function (r) { return r.json(); });
  }
  function boxSuggerita(f, loc) {
    var box = document.createElement('div'); box.className = 'suggerita suggerita--nuova';
    var badge = document.createElement('span'); badge.className = 'badge badge--ochre'; badge.textContent = f.aggiornata ? 'Aggiornata: da ricontrollare' : 'Suggerita: da controllare';
    var testo = document.createElement('p'); testo.className = 'suggerita__testo'; testo.setAttribute('lang', loc); testo.textContent = f.testo;
    var az = document.createElement('div'); az.className = 'suggerita__azioni';
    [['approva', 'Approva', 'btn btn--sm'], ['modifica', 'Modifica', 'btn btn--ghost btn--sm'], ['scarta', 'Scarta', 'linkbtn']].forEach(function (b) {
      var btn = document.createElement('button'); btn.type = 'submit'; btn.setAttribute('form', 'sugg-f'); btn.className = b[2]; btn.name = b[0]; btn.value = String(f.id); btn.textContent = b[1];
      if (b[0] === 'modifica') { btn.setAttribute('data-modifica', f.dom); btn.setAttribute('data-testo', f.testo); }
      az.appendChild(btn);
    });
    box.appendChild(badge); box.appendChild(testo); box.appendChild(az);
    return box;
  }
  function mettiNellaPagina(f, loc) {
    var el = document.getElementById(f.dom);
    if (!el) return;
    var cont = el.closest('.field, .stack') || el.parentNode;
    cont.querySelectorAll(':scope > .suggerita, :scope > .traduci-campo').forEach(function (x) { x.remove(); });
    var badge = cont.querySelector('label .badge');
    if (badge) badge.remove();
    cont.appendChild(boxSuggerita(f, loc));
  }
  function statoBarra(stato, perc, testo, classe) {
    stato.hidden = false; stato.className = 'traduci-stato' + (classe ? ' traduci-stato--' + classe : '');
    stato.querySelector('.traduci-stato__barra i').style.width = Math.max(0, Math.min(100, perc)) + '%';
    var p = stato.querySelector('[data-traduci-testo]'); p.textContent = '';
    if (typeof testo === 'string') p.textContent = testo; else p.appendChild(testo);
  }
  function occupato(btn, si, etichetta) {
    btn.setAttribute('aria-busy', si ? 'true' : 'false'); btn.disabled = si;
    var sp = btn.querySelector('[data-traduci-etichetta]');
    if (si) { if (!btn.querySelector('.spin')) { var g = document.createElement('span'); g.className = 'spin'; g.setAttribute('aria-hidden', 'true'); btn.insertBefore(g, btn.firstChild); } if (sp) sp.textContent = 'Traduzione in corso…'; }
    else { var g2 = btn.querySelector('.spin'); if (g2) g2.remove(); if (sp && etichetta) sp.textContent = etichetta; }
  }

  // La pagina di una lingua: «Traduci tutti i campi mancanti (N)» a gruppi, con «12 di 30 testi».
  var tradu = document.querySelector('[data-traduci]');
  if (tradu && window.fetch) (function (box) {
    var btn = box.querySelector('[data-traduci-tutti]'), stato = box.parentNode.querySelector('[data-traduci-stato]');
    var url = box.getAttribute('data-url'), csrf = box.getAttribute('data-csrf'), lingua = box.getAttribute('data-lingua');
    var loc = (url.match(/\/lingue\/([a-zA-Z-]+)\//) || [])[1] || '';
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      var fatte = 0, totale = 0;
      occupato(btn, true);
      statoBarra(stato, 2, lingua + ' · avvio…');
      (function giro() {
        chiediSuggerite(url, csrf, { tutti: 1, limite: 4 }).then(function (r) {
          if (!totale) totale = r.totale || (r.fatte.length + r.restanti);
          r.fatte.forEach(function (f) { fatte++; mettiNellaPagina(f, loc); });
          var perc = totale ? Math.round(fatte * 100 / totale) : 100;
          statoBarra(stato, perc, lingua + ' · ' + fatte + ' di ' + totale + ' test' + (totale === 1 ? 'o' : 'i'));
          if (r.stop) {
            var msg = document.createDocumentFragment(); msg.appendChild(document.createTextNode(r.stop + ' '));
            var ri = document.createElement('button'); ri.type = 'button'; ri.className = 'linkbtn'; ri.textContent = 'Riprova'; ri.addEventListener('click', function () { statoBarra(stato, perc, lingua + ' · riprovo…'); giro(); });
            msg.appendChild(ri);
            statoBarra(stato, perc, msg, 'err'); occupato(btn, false, 'Traduci di nuovo'); return;
          }
          if (r.restanti > 0 && r.fatte.length) return giro();
          statoBarra(stato, 100, 'Fatto: ' + fatte + ' test' + (fatte === 1 ? 'o' : 'i') + '. Controllal' + (fatte === 1 ? 'o' : 'i') + ' e approva.', 'ok');
          occupato(btn, false, 'Traduci di nuovo');
          if (fatte) {
            var tutte = document.createElement('button'); tutte.type = 'submit'; tutte.setAttribute('form', 'sugg-f'); tutte.className = 'btn btn--ghost btn--sm'; tutte.name = 'tutte'; tutte.value = '1'; tutte.textContent = 'Approva tutte (' + fatte + ')';
            box.appendChild(tutte);
          }
          setTimeout(function () { stato.hidden = true; }, 3000);
        }).catch(function () {
          var msg = document.createDocumentFragment(); msg.appendChild(document.createTextNode('La traduzione non ha risposto: riprova. '));
          var ri = document.createElement('button'); ri.type = 'button'; ri.className = 'linkbtn'; ri.textContent = 'Riprova'; ri.addEventListener('click', giro); msg.appendChild(ri);
          statoBarra(stato, 0, msg, 'err'); occupato(btn, false, 'Traduci di nuovo');
        });
      })();
    });
  })(tradu);

  // L'editor di una sezione e il passo Pubblica: una lingua per volta, «Francese · 2 di 5 lingue», i campi che compaiono.
  var traduSez = document.querySelectorAll('[data-traduci-sezione]');
  for (var ts = 0; ts < traduSez.length; ts++) (function (box) {
    if (!window.fetch) return;
    box.hidden = false;
    var btn = box.querySelector('[data-traduci-avvia]'), stato = box.querySelector('[data-traduci-stato]'), esito = box.querySelector('[data-traduci-esito]');
    var lingue = JSON.parse(box.getAttribute('data-lingue') || '[]'), csrf = box.getAttribute('data-csrf'), sezione = box.getAttribute('data-traduci-sezione') || '0';
    var etichettaIniziale = (btn.querySelector('[data-traduci-etichetta]') || {}).textContent;
    function unaLingua(l, suCampo) {
      return new Promise(function (ok) {
        var fatte = 0;
        (function giro() {
          chiediSuggerite(l.url, csrf, { tutti: 1, limite: 6, sezione: sezione }).then(function (r) {
            r.fatte.forEach(function (f) { fatte++; suCampo(l, f); });
            if (r.stop) return ok({ lingua: l, fatte: fatte, errore: r.stop });
            if (r.restanti > 0 && r.fatte.length) return giro();
            ok({ lingua: l, fatte: fatte, errore: '' });
          }).catch(function () { ok({ lingua: l, fatte: fatte, errore: 'La traduzione non ha risposto.' }); });
        })();
      });
    }
    function avvia(soloLingue) {
      var daFare = soloLingue || lingue, esiti = [], i = 0;
      occupato(btn, true);
      esito.textContent = '';
      function mostraCampo(l, f) {
        var c = document.createElement('div'); c.className = 'campo campo--nuovo';
        var sm = document.createElement('small'); sm.textContent = f.etichetta + ' · ' + l.nome; c.appendChild(sm); c.appendChild(document.createTextNode(f.testo));
        esito.appendChild(c);
      }
      (function prossima() {
        if (i >= daFare.length) {
          var errori = esiti.filter(function (x) { return x.errore; }), testi = esiti.reduce(function (n, x) { return n + x.fatte; }, 0);
          if (errori.length) {
            var msg = document.createDocumentFragment();
            msg.appendChild(document.createTextNode(errori.map(function (x) { return x.lingua.nome; }).join(', ') + (errori.length === 1 ? ' non tradotto' : ' non tradotte') + ': ' + errori[0].errore + ' ' + (esiti.length - errori.length > 0 ? 'Le altre ' + (esiti.length - errori.length) + ' lingue sono pronte. ' : '')));
            errori.forEach(function (x) {
              var ri = document.createElement('button'); ri.type = 'button'; ri.className = 'btn btn--ghost btn--sm'; ri.textContent = 'Riprova ' + x.lingua.nome.toLowerCase();
              ri.addEventListener('click', function () { avvia([x.lingua]); }); msg.appendChild(ri);
            });
            statoBarra(stato, Math.round((esiti.length - errori.length) * 100 / esiti.length), msg, 'err');
          } else {
            var ok = document.createDocumentFragment();
            ok.appendChild(document.createTextNode('Fatto: ' + esiti.length + ' lingu' + (esiti.length === 1 ? 'a' : 'e') + ', ' + testi + ' test' + (testi === 1 ? 'o' : 'i') + '. Controllali e approva: '));
            esiti.forEach(function (x, k) { var a = document.createElement('a'); a.href = x.lingua.pagina; a.textContent = x.lingua.nome; ok.appendChild(a); if (k < esiti.length - 1) ok.appendChild(document.createTextNode(', ')); });
            statoBarra(stato, 100, ok, 'ok');
            var dove = document.createElement('p'); dove.className = 'traduci-esito__lingua'; dove.appendChild(ok.cloneNode(true)); esito.appendChild(dove);
            setTimeout(function () { stato.hidden = true; }, 3000);
          }
          occupato(btn, false, 'Traduci di nuovo');
          return;
        }
        var l = daFare[i];
        statoBarra(stato, Math.round(i * 100 / daFare.length) + 2, l.nome + ' · ' + (i + 1) + ' di ' + daFare.length + ' lingu' + (daFare.length === 1 ? 'a' : 'e'));
        unaLingua(l, mostraCampo).then(function (r) { esiti.push(r); i++; prossima(); });
      })();
    }
    btn.addEventListener('click', function () { avvia(null); });
  })(traduSez[ts]);
})();

/* 9I · «/» apre «Cerca» in Amministrazione (fuori dai campi di testo). */
(function () {
  'use strict';
  var campo = document.querySelector('[data-cerca-admin]');
  if (!campo) return;
  document.addEventListener('keydown', function (e) {
    if (e.key !== '/' || e.ctrlKey || e.metaKey || e.altKey) return;
    var t = e.target, tag = t && t.tagName ? t.tagName.toLowerCase() : '';
    if (tag === 'input' || tag === 'textarea' || tag === 'select' || (t && t.isContentEditable)) return;
    e.preventDefault(); campo.focus(); campo.select();
  });
})();
