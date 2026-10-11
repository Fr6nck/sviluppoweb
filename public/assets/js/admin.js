/* Arco del Vento — area riservata.
   Solo comodità: il tema chiaro o scuro senza ricaricare, l'anteprima del
   file scelto prima di caricarlo e il file trascinato sul riquadro. Senza
   JavaScript funziona tutto lo stesso, con un passaggio in più. */
(function () {
  'use strict';

  var TIPI = ['image/jpeg', 'image/png', 'image/webp'];

  /* ---------------------------------------------------- chiaro e scuro
     Il server ha già scritto il tema scelto su <html>; qui il pulsante lo
     cambia senza ricaricare e scrive lo stesso cookie che scriverebbe il
     server. Senza una scelta vale quello del sistema. */
  var radice = document.documentElement;
  var sistemaScuro = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

  function temaAttuale() {
    var scelto = radice.getAttribute('data-tema');
    if (scelto === 'chiaro' || scelto === 'scuro') return scelto;
    return sistemaScuro && sistemaScuro.matches ? 'scuro' : 'chiaro';
  }

  document.querySelectorAll('[data-tema-modulo]').forEach(function (modulo) {
    var bottone = modulo.querySelector('button');
    if (!bottone) return;

    function allinea() {
      var scuro = temaAttuale() === 'scuro';
      bottone.setAttribute('aria-pressed', scuro ? 'true' : 'false');
      bottone.value = scuro ? 'chiaro' : 'scuro';
    }

    modulo.addEventListener('submit', function (e) {
      e.preventDefault();
      var nuovo = temaAttuale() === 'scuro' ? 'chiaro' : 'scuro';
      radice.setAttribute('data-tema', nuovo);
      document.cookie = 'adv_tema=' + nuovo + '; path=' + (modulo.getAttribute('data-tema-percorso') || '/') +
        '; max-age=31536000; samesite=lax' + (location.protocol === 'https:' ? '; secure' : '');
      document.querySelectorAll('[data-tema-modulo] button').forEach(function (b) {
        b.setAttribute('aria-pressed', nuovo === 'scuro' ? 'true' : 'false');
        b.value = nuovo === 'scuro' ? 'chiaro' : 'scuro';
      });
    });

    allinea();
    if (sistemaScuro && sistemaScuro.addEventListener) sistemaScuro.addEventListener('change', allinea);
  });

  function misura(byte) {
    return byte >= 1048576
      ? (byte / 1048576).toFixed(1).replace('.', ',') + ' MB'
      : Math.max(1, Math.round(byte / 1024)) + ' KB';
  }

  document.querySelectorAll('[data-carica]').forEach(function (modulo) {
    var campo = modulo.querySelector('input[type="file"]');
    var zona = modulo.querySelector('[data-carica-zona]');
    var anteprima = modulo.querySelector('[data-carica-anteprima]');
    var nome = modulo.querySelector('[data-carica-nome]');
    if (!campo || !zona) return;

    function mostra() {
      var file = campo.files && campo.files[0];
      zona.classList.toggle('adm-zona--scelto', !!file);
      if (!file) {
        if (anteprima) anteprima.hidden = true;
        if (nome) nome.textContent = '';
        return;
      }
      if (nome) {
        nome.textContent = file.name + ' · ' + misura(file.size) +
          (TIPI.indexOf(file.type) === -1 ? ' — non è una JPG, PNG o WebP' : '');
      }
      // Un data: URL e non un blob:, che la politica di sicurezza non ammette.
      if (anteprima && TIPI.indexOf(file.type) !== -1 && file.size < 16 * 1048576 && window.FileReader) {
        var lettore = new FileReader();
        lettore.onload = function () {
          anteprima.src = String(lettore.result);
          anteprima.hidden = false;
        };
        lettore.readAsDataURL(file);
      } else if (anteprima) {
        anteprima.hidden = true;
      }
    }

    campo.addEventListener('change', mostra);

    ['dragenter', 'dragover'].forEach(function (evento) {
      zona.addEventListener(evento, function (e) {
        e.preventDefault();
        zona.classList.add('adm-zona--sopra');
      });
    });
    ['dragleave', 'drop'].forEach(function (evento) {
      zona.addEventListener(evento, function () {
        zona.classList.remove('adm-zona--sopra');
      });
    });
    zona.addEventListener('drop', function (e) {
      e.preventDefault();
      if (!e.dataTransfer || !e.dataTransfer.files.length) return;
      try {
        campo.files = e.dataTransfer.files;
      } catch (err) {
        return;
      }
      mostra();
    });

    // Il pulsante si ferma mentre il file sale: un secondo click lo
    // caricherebbe due volte.
    modulo.addEventListener('submit', function () {
      var bottone = modulo.querySelector('button[type="submit"]');
      if (bottone && campo.files && campo.files.length) {
        window.setTimeout(function () { bottone.disabled = true; }, 0);
        bottone.classList.add('adm-bottone--attesa');
      }
    });
  });
  /* ------------------------------------------------ l'editor dei testi
     I campi a sinistra, la pagina vera a destra in un riquadro. Scegliendo
     un campo il suo testo si illumina nella pagina; cliccando un testo nella
     pagina si apre il suo campo. Il riquadro e il pannello stanno sullo
     stesso sito, quindi lo script legge la pagina direttamente. */
  (function () {
    var editor = document.querySelector('[data-editor]');
    if (!editor) return;

    var modulo   = editor.querySelector('[data-modulo-testi]');
    var aside    = editor.querySelector('[data-anteprima]');
    var cornice  = editor.querySelector('[data-anteprima-cornice]');
    var schermo  = editor.querySelector('[data-schermo-cornice]');
    var stato    = editor.querySelector('[data-anteprima-stato]');
    var apri     = editor.querySelector('[data-apri-pagina]');
    var avviso   = editor.querySelector('[data-da-salvare]');
    var CHIAVE_LINGUA = 'adv_testi_lingua';
    var LARGO = window.matchMedia('(min-width: 1181px)');
    var linguaPagina = null;   // la lingua della pagina nel riquadro
    var cache = {};            // chiave|lingua → { el, nodo, corrente }
    var evidenziato = null;
    var sporco = false;
    var dopoCaricamento = null;

    function norm(t) { return String(t || '').replace(/\s+/g, ' ').trim().toLowerCase(); }

    /* I pezzi di un testo che si possono cercare nella pagina: il «|» va a
       capo, i segnaposto (:metres, {checkout}) diventano numeri. Si cerca il
       pezzo più lungo. */
    function pezzi(testo) {
      return String(testo || '').split(/\||\n|:[a-z_]+|\{[a-z]+\}/)
        .map(function (p) { return p.replace(/\s+/g, ' ').trim(); })
        .filter(function (p) { return p.length >= 4; })
        .sort(function (a, b) { return b.length - a.length; });
    }

    function documento() {
      try { return cornice && cornice.contentDocument && cornice.contentDocument.body ? cornice.contentDocument : null; }
      catch (e) { return null; }
    }

    function visibile(el) { return el && el.getClientRects && el.getClientRects().length > 0; }

    function trova(doc, testo) {
      var cercati = pezzi(testo);
      for (var i = 0; i < cercati.length && i < 3; i++) {
        var ago = cercati[i].toLowerCase();
        var primo = null;
        var giro = doc.createTreeWalker(doc.body, NodeFilter.SHOW_TEXT, {
          acceptNode: function (n) {
            var p = n.parentElement;
            if (!p || /^(SCRIPT|STYLE|NOSCRIPT)$/.test(p.tagName)) return NodeFilter.FILTER_REJECT;
            return NodeFilter.FILTER_ACCEPT;
          }
        });
        var n;
        while ((n = giro.nextNode())) {
          if (norm(n.data).indexOf(ago) !== -1) {
            if (visibile(n.parentElement)) return { el: n.parentElement, nodo: n };
            primo = primo || { el: n.parentElement, nodo: n };
          }
        }
        // Le descrizioni delle foto e le etichette: stanno negli attributi.
        var attr = doc.querySelectorAll('[alt],[aria-label],[title],[placeholder],[data-didascalia]');
        for (var j = 0; j < attr.length; j++) {
          var a = attr[j];
          var valori = [a.getAttribute('alt'), a.getAttribute('aria-label'), a.getAttribute('title'), a.getAttribute('placeholder'), a.getAttribute('data-didascalia')];
          if (valori.some(function (v) { return v && norm(v).indexOf(ago) !== -1; })) {
            var bersaglio = a.closest('figure, picture, button, a') || a;
            if (visibile(bersaglio)) return { el: bersaglio, nodo: null };
            primo = primo || { el: bersaglio, nodo: null };
          }
        }
        if (primo) return primo;
      }
      return null;
    }

    function campoDi(fieldset, lingua) {
      return fieldset.querySelector('.adm-lingua[data-lingua="' + lingua + '"] [data-campo]');
    }

    function cerca(fieldset, lingua) {
      var doc = documento();
      if (!doc) return null;
      var k = fieldset.getAttribute('data-testo') + '|' + lingua;
      var c = cache[k];
      if (c && c.el && c.el.isConnected) return c;
      var input = campoDi(fieldset, lingua);
      if (!input) return null;
      var r = trova(doc, input.defaultValue);
      cache[k] = r ? { el: r.el, nodo: r.nodo, corrente: input.defaultValue } : { el: null };
      return cache[k];
    }

    function evidenzia(el) {
      if (evidenziato && evidenziato !== el) evidenziato.classList.remove('adv-anteprima-evidenzia');
      evidenziato = el;
      if (!el) return;
      el.classList.add('adv-anteprima-evidenzia');
      el.scrollIntoView({ block: 'center', behavior: 'smooth' });
    }

    function linguaCampo() {
      var l = editor.getAttribute('data-lingua');
      return l === 'tutte' ? 'it' : l;
    }

    function carica(lingua, poi) {
      if (!cornice) return;
      var url = editor.getAttribute('data-anteprima-' + lingua);
      if (!url) return;
      if (linguaPagina === lingua && documento()) { if (poi) poi(); return; }
      linguaPagina = lingua;
      cache = {};
      dopoCaricamento = poi || null;
      if (apri) apri.href = url;
      cornice.src = url;
    }

    function mostraNellaPagina(fieldset, lingua) {
      lingua = lingua || linguaCampo();
      var vai = function () {
        var r = cerca(fieldset, lingua);
        if (r && r.el) {
          evidenzia(r.el);
          if (stato) stato.textContent = 'Il testo illuminato nella pagina è quello del campo scelto.';
        } else {
          evidenzia(null);
          if (stato) stato.textContent = 'Questo testo non si vede in questa pagina: compare solo in certe situazioni (un errore, la ricerca, un’e-mail) o in un’altra pagina.';
        }
      };
      if (linguaPagina !== lingua) carica(lingua, vai); else vai();
    }

    function attiva(fieldset) {
      editor.querySelectorAll('.adm-testo.is-attivo').forEach(function (f) { if (f !== fieldset) f.classList.remove('is-attivo'); });
      fieldset.classList.add('is-attivo');
    }

    /* ------------------------------ la lingua da modificare */
    function scegliLingua(l) {
      editor.setAttribute('data-lingua', l);
      editor.querySelectorAll('[data-mostra-lingua]').forEach(function (b) {
        b.setAttribute('aria-pressed', b.getAttribute('data-mostra-lingua') === l ? 'true' : 'false');
      });
      try { window.localStorage.setItem(CHIAVE_LINGUA, l); } catch (e) { /* niente */ }
      if (aside && !aside.hidden) carica(linguaCampo());
    }
    editor.querySelectorAll('[data-mostra-lingua]').forEach(function (b) {
      b.addEventListener('click', function () { scegliLingua(b.getAttribute('data-mostra-lingua')); });
    });
    var salvata = null;
    try { salvata = window.localStorage.getItem(CHIAVE_LINGUA); } catch (e) { salvata = null; }
    scegliLingua(salvata && editor.querySelector('[data-mostra-lingua="' + salvata + '"]') ? salvata : 'it');

    /* ------------------------------ l'anteprima */
    function adatta() {
      if (!cornice || !schermo) return;
      if (schermo.classList.contains('is-telefono')) {
        cornice.style.transform = '';
        cornice.style.height = '100%';
        return;
      }
      var scala = schermo.clientWidth / 1280;
      cornice.style.transform = 'scale(' + scala + ')';
      cornice.style.height = (schermo.clientHeight / scala) + 'px';
    }

    function preparaPagina() {
      var doc = documento();
      if (!doc) return;
      doc.documentElement.classList.add('adv-anteprima-attiva');
      // Nella pagina di anteprima un clic non porta altrove: trova il campo.
      doc.addEventListener('submit', function (e) { e.preventDefault(); }, true);
      doc.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var lingua = linguaPagina;
        var migliore = null;
        editor.querySelectorAll('[data-testo]').forEach(function (fieldset) {
          var input = campoDi(fieldset, lingua);
          if (!input) return;
          var p = pezzi(input.value)[0];
          if (!p) return;
          var ago = p.toLowerCase();
          var el = e.target, livello = 0;
          while (el && el !== doc.body && livello < 5) {
            var t = norm(el.textContent) + ' ' + norm((el.getAttribute && (el.getAttribute('alt') || el.getAttribute('aria-label'))) || '');
            if (t.indexOf(ago) !== -1) {
              if (!migliore || livello < migliore.livello || (livello === migliore.livello && p.length > migliore.lunghezza)) {
                migliore = { fieldset: fieldset, livello: livello, lunghezza: p.length };
              }
              break;
            }
            el = el.parentElement;
            livello++;
          }
        });
        if (!migliore) {
          if (stato) stato.textContent = 'Questo testo non è in questa sezione: può essere un dato di «La struttura», di una camera o di un’altra sezione dei testi.';
          return;
        }
        apriCampo(migliore.fieldset, true);
      }, true);

      // Quali testi si vedono in questa pagina: gli altri restano senza «Mostra».
      editor.querySelectorAll('[data-testo]').forEach(function (fieldset) {
        var bottone = fieldset.querySelector('[data-trova]');
        if (!bottone) return;
        bottone.hidden = false;
        var r = cerca(fieldset, linguaPagina);
        bottone.classList.toggle('is-assente', !(r && r.el));
        bottone.title = r && r.el ? 'Mostra dove sta nella pagina' : 'Non si vede in questa pagina';
      });
      adatta();
      if (dopoCaricamento) { var f = dopoCaricamento; dopoCaricamento = null; f(); }
    }

    function apriCampo(fieldset, dallaPagina) {
      var d = fieldset.closest('details');
      while (d) { d.open = true; d = d.parentElement ? d.parentElement.closest('details') : null; }
      attiva(fieldset);
      fieldset.scrollIntoView({ block: 'center', behavior: 'smooth' });
      var input = campoDi(fieldset, linguaCampo()) || fieldset.querySelector('[data-campo]');
      if (input) input.focus({ preventScroll: true });
      if (dallaPagina) mostraNellaPagina(fieldset, linguaPagina);
    }

    function accendiAnteprima() {
      if (!aside || !cornice) return;
      if (!LARGO.matches) {
        aside.hidden = true;
        editor.classList.remove('adm-editor--con-anteprima');
        return;
      }
      aside.hidden = false;
      editor.classList.add('adm-editor--con-anteprima');
      carica(linguaCampo());
      adatta();
    }
    if (cornice) cornice.addEventListener('load', preparaPagina);
    accendiAnteprima();
    if (LARGO.addEventListener) LARGO.addEventListener('change', accendiAnteprima);
    window.addEventListener('resize', adatta);

    editor.querySelectorAll('[data-schermo]').forEach(function (b) {
      b.addEventListener('click', function () {
        var telefono = b.getAttribute('data-schermo') === 'telefono';
        schermo.classList.toggle('is-telefono', telefono);
        editor.querySelectorAll('[data-schermo]').forEach(function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); });
        cache = {};
        adatta();
      });
    });

    /* ------------------------------ i campi */
    editor.addEventListener('focusin', function (e) {
      var input = e.target.closest && e.target.closest('[data-campo]');
      if (!input) return;
      var fieldset = input.closest('[data-testo]');
      attiva(fieldset);
      if (aside && !aside.hidden) mostraNellaPagina(fieldset, input.closest('[data-lingua]').getAttribute('data-lingua'));
    });

    editor.addEventListener('click', function (e) {
      var b = e.target.closest && e.target.closest('[data-trova]');
      if (!b) return;
      var fieldset = b.closest('[data-testo]');
      attiva(fieldset);
      mostraNellaPagina(fieldset);
    });

    // Quello che si scrive compare subito nella pagina, quando il testo vi
    // sta in un pezzo solo (i titoli con l'a capo si vedono dopo «Salva»).
    editor.addEventListener('input', function (e) {
      var input = e.target.closest && e.target.closest('[data-campo]');
      if (!input) return;
      if (!sporco) { sporco = true; if (avviso) avviso.hidden = false; }
      var fieldset = input.closest('[data-testo]');
      var lingua = input.closest('[data-lingua]').getAttribute('data-lingua');
      if (lingua !== linguaPagina) return;
      var r = cerca(fieldset, lingua);
      if (!r || !r.nodo || !r.nodo.isConnected) return;
      var prima = String(r.corrente || '');
      if (prima.indexOf('|') !== -1 || /:[a-z_]+|\{[a-z]+\}/.test(prima) || input.value.indexOf('|') !== -1) return;
      var dato = r.nodo.data;
      var pos = dato.indexOf(prima);
      if (pos === -1) return;
      r.nodo.data = dato.slice(0, pos) + input.value + dato.slice(pos + prima.length);
      r.corrente = input.value;
      evidenzia(r.el);
    });

    if (modulo) modulo.addEventListener('submit', function () { sporco = false; });
    window.addEventListener('beforeunload', function (e) {
      if (!sporco) return;
      e.preventDefault();
      e.returnValue = '';
    });

    // Arrivando dalla ricerca (#k-…), il campo si apre e si mette in vista.
    if (location.hash && location.hash.indexOf('#k-') === 0) {
      var cercato = document.getElementById(location.hash.slice(1));
      if (cercato) window.setTimeout(function () { apriCampo(cercato, false); }, 60);
    }
  })();
  /* ------------------------------------------------ l'inquadratura
     Due riquadri, computer e telefono, che si comportano come la foto sul
     sito: si trascina la foto per spostarla, la rotella o il cursore la
     ingrandiscono. I cursori restano il modo di farlo senza mouse. */
  (function () {
    var box = document.querySelector('[data-inquadra]');
    if (!box) return;
    function q(nome) { return box.querySelector('[data-q="' + nome + '"]'); }

    function disegna(p) {
      var cornice = box.querySelector('[data-inquadra-cornice="' + p + '"]');
      if (!cornice) return;
      var img = cornice.querySelector('img');
      var mirino = cornice.querySelector('.adm-inquadra__mirino');
      var x = parseFloat(q(p + 'x').value), y = parseFloat(q(p + 'y').value), z = parseFloat(q(p + 'z').value);
      img.style.objectPosition = x + '% ' + y + '%';
      img.style.transformOrigin = x + '% ' + y + '%';
      img.style.transform = 'scale(' + z + ')';
      mirino.style.left = x + '%';
      mirino.style.top = y + '%';
    }

    ['', 'm'].forEach(function (p) {
      var cornice = box.querySelector('[data-inquadra-cornice="' + p + '"]');
      if (!cornice) return;
      ['x', 'y', 'z'].forEach(function (k) { q(p + k).addEventListener('input', function () { disegna(p); }); });

      var inizio = null;
      cornice.addEventListener('pointerdown', function (e) {
        cornice.setPointerCapture(e.pointerId);
        inizio = { x: e.clientX, y: e.clientY, fx: parseFloat(q(p + 'x').value), fy: parseFloat(q(p + 'y').value) };
      });
      cornice.addEventListener('pointermove', function (e) {
        if (!inizio) return;
        var r = cornice.getBoundingClientRect();
        // Trascinare la foto verso destra mostra la parte sinistra: il
        // punto di fuoco va nel verso opposto al gesto.
        var nx = inizio.fx - (e.clientX - inizio.x) / r.width * 100;
        var ny = inizio.fy - (e.clientY - inizio.y) / r.height * 100;
        q(p + 'x').value = Math.max(0, Math.min(100, nx)).toFixed(1);
        q(p + 'y').value = Math.max(0, Math.min(100, ny)).toFixed(1);
        disegna(p);
      });
      ['pointerup', 'pointercancel'].forEach(function (t) { cornice.addEventListener(t, function () { inizio = null; }); });
      cornice.addEventListener('wheel', function (e) {
        e.preventDefault();
        var z = q(p + 'z');
        z.value = Math.max(1, Math.min(2.5, parseFloat(z.value) - e.deltaY * 0.002)).toFixed(2);
        disegna(p);
      }, { passive: false });
      cornice.addEventListener('keydown', function (e) {
        var passo = e.shiftKey ? 5 : 1;
        var mappa = { ArrowLeft: ['x', passo], ArrowRight: ['x', -passo], ArrowUp: ['y', passo], ArrowDown: ['y', -passo] };
        var m = mappa[e.key];
        if (!m) return;
        e.preventDefault();
        var campo = q(p + m[0]);
        campo.value = Math.max(0, Math.min(100, parseFloat(campo.value) - m[1])).toFixed(1);
        disegna(p);
      });
      disegna(p);
    });

    var copia = box.querySelector('[data-inquadra-copia]');
    if (copia) copia.addEventListener('click', function () {
      ['x', 'y', 'z'].forEach(function (k) { q('m' + k).value = q(k).value; });
      disegna('m');
    });
  })();
})();
