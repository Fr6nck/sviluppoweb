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
    if (nome) nome.textContent = inp.files && inp.files[0] ? inp.files[0].name + ' — si carica col bottone Salva' : 'Nessun file scelto';
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
