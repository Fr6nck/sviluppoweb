/* ChiMangia — piattaforma: comportamento delle tre aree.
 * Tutto funziona anche senza script (moduli e collegamenti normali): qui si aggiungono
 * conferme, contatori, righe ripetibili, suggerimenti e piccoli aiuti. */
(function () {
  'use strict';
  var d = document;
  var $ = function (sel, root) { return (root || d).querySelector(sel); };
  var $$ = function (sel, root) { return Array.prototype.slice.call((root || d).querySelectorAll(sel)); };
  d.documentElement.classList.add('js');

  /* ---------- Menu laterale su schermi piccoli ---------- */
  var side = $('#side');
  var toggle = $('.nav-toggle');
  if (side && toggle) {
    var setOpen = function (open) {
      side.classList.toggle('open', open);
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    };
    toggle.addEventListener('click', function () { setOpen(!side.classList.contains('open')); });
    side.addEventListener('click', function (e) { if (e.target === side) { setOpen(false); } });
    d.addEventListener('keydown', function (e) { if (e.key === 'Escape' && side.classList.contains('open')) { setOpen(false); toggle.focus(); } });
  }

  /* ---------- Conferma prima delle azioni delicate ---------- */
  d.addEventListener('submit', function (e) {
    var form = e.target;
    var msg = form.getAttribute && form.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) { e.preventDefault(); return; }
    // Evita il doppio invio.
    if (form.hasAttribute && form.hasAttribute('data-once')) {
      if (form.getAttribute('data-sent') === '1') { e.preventDefault(); return; }
      form.setAttribute('data-sent', '1');
    }
  });

  /* ---------- Invio automatico al cambio (filtri, selettori) ---------- */
  $$('[data-autosubmit]').forEach(function (el) {
    el.addEventListener('change', function () { if (el.form) { el.form.submit(); } });
  });

  /* ---------- Copia negli appunti ---------- */
  $$('[data-copy]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var target = btn.getAttribute('data-copy');
      var el = target ? $(target) : null;
      var text = el ? (el.value !== undefined && el.tagName !== 'CODE' ? el.value : el.textContent) : '';
      var done = function () {
        var old = btn.getAttribute('data-label') || btn.textContent;
        btn.setAttribute('data-label', old);
        btn.textContent = 'Copiato ✓';
        setTimeout(function () { btn.textContent = old; }, 1800);
      };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(done, function () {});
      } else {
        var ta = d.createElement('textarea');
        ta.value = text; ta.className = 'sr-only'; d.body.appendChild(ta); ta.select();
        try { d.execCommand('copy'); done(); } catch (err) { /* nessuna copia disponibile */ }
        d.body.removeChild(ta);
      }
    });
  });

  /* ---------- Stampa ---------- */
  $$('[data-print]').forEach(function (btn) { btn.addEventListener('click', function () { window.print(); }); });

  /* ---------- CAPTCHA: nuovo codice senza ricaricare ---------- */
  $$('[data-captcha]').forEach(function (box) {
    var btn = $('.captcha-new', box);
    var img = $('.captcha-img', box);
    var hidden = $('input[name="captcha_t"]', box);
    var input = $('input[name="captcha"]', box);
    if (!btn || !img || !hidden) { return; }
    btn.hidden = false;
    btn.addEventListener('click', function () {
      var url = box.getAttribute('data-captcha');
      fetch(url + '?new=1', { credentials: 'same-origin', cache: 'no-store' })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          if (!j || !j.t) { return; }
          hidden.value = j.t;
          img.src = url + '?t=' + encodeURIComponent(j.t);
          if (input) { input.value = ''; input.focus(); }
        })
        .catch(function () { window.location.reload(); });
    });
  });

  /* ---------- Righe ripetibili (piatti, formule, camere) ---------- */
  d.addEventListener('click', function (e) {
    var add = e.target.closest ? e.target.closest('[data-add-row]') : null;
    if (add) {
      var tpl = d.getElementById(add.getAttribute('data-add-row'));
      var box = d.getElementById(add.getAttribute('data-into'));
      if (tpl && box) {
        var node = tpl.content.firstElementChild.cloneNode(true);
        box.appendChild(node);
        var first = $('input, select, textarea', node);
        if (first) { first.focus(); }
      }
      return;
    }
    var rem = e.target.closest ? e.target.closest('[data-remove-row]') : null;
    if (rem) {
      var row = rem.closest('[data-row]');
      if (row) { row.parentNode.removeChild(row); }
    }
  });

  /* ---------- Società: suggerimenti mentre si scrive ---------- */
  $$('[data-suggest]').forEach(function (input) {
    var list;
    try { list = JSON.parse(input.getAttribute('data-suggest') || '[]'); } catch (err) { list = []; }
    var box = d.getElementById(input.getAttribute('data-suggest-box'));
    var note = d.getElementById(input.getAttribute('data-suggest-note'));
    if (!box) { return; }
    var render = function () {
      var q = input.value.trim().toLowerCase();
      box.textContent = '';
      if (q.length < 3) { box.hidden = true; if (note) { note.hidden = true; } return; }
      var found = list.filter(function (n) { return n.toLowerCase().indexOf(q) !== -1; }).slice(0, 6);
      var exact = list.some(function (n) { return n.toLowerCase() === q; });
      found.forEach(function (n) {
        var b = d.createElement('button');
        b.type = 'button'; b.textContent = n;
        b.addEventListener('click', function () { input.value = n; box.hidden = true; if (note) { note.textContent = '✓ Società selezionata'; note.hidden = false; } });
        box.appendChild(b);
      });
      if (!exact && input.hasAttribute('data-suggest-new')) {
        var a = d.createElement('button');
        a.type = 'button'; a.className = 'add';
        a.textContent = '+ Aggiungi «' + input.value.trim() + '» come nuova società';
        a.addEventListener('click', function () { box.hidden = true; if (note) { note.textContent = 'Nuova società: la reception la verificherà'; note.hidden = false; } });
        box.appendChild(a);
      }
      box.hidden = box.children.length === 0 || exact;
      if (note && !exact) { note.hidden = true; }
    };
    input.addEventListener('input', render);
    input.addEventListener('focus', render);
  });

  /* ---------- Ospite: pranzi e cene (contatore e scelte rapide) ---------- */
  var mealForm = $('[data-meals]');
  if (mealForm) {
    var counter = $('[data-meal-count]', mealForm);
    var count = function () {
      if (counter) { counter.textContent = String($$('.mealbox input:checked', mealForm).length); }
    };
    mealForm.addEventListener('change', count);
    $$('[data-check]', mealForm).forEach(function (btn) {
      btn.addEventListener('click', function () {
        var which = btn.getAttribute('data-check');
        $$('.mealbox input', mealForm).forEach(function (cb) {
          if (cb.disabled) { return; }
          if (which === 'none') { cb.checked = false; } else if (cb.getAttribute('data-meal') === which) { cb.checked = true; }
        });
        count();
      });
    });
    count();
  }

  /* ---------- Ospite: formula, portate e totale ---------- */
  var choice = $('[data-choice]');
  if (choice) {
    var money = function (n) { return n.toFixed(2).replace('.', ',') + ' €'; };
    var update = function () {
      var formula = $('input[name="formula"]:checked', choice);
      var pathBox = $('[data-paths]', choice);
      var need = null;
      if (formula) {
        var paths = [];
        try { paths = JSON.parse(formula.getAttribute('data-courses') || '[]'); } catch (err) { paths = []; }
        if (pathBox) { pathBox.hidden = paths.length < 2; }
        var pi = 0;
        if (paths.length > 1) {
          var p = $('input[name="path"]:checked', choice);
          pi = p ? parseInt(p.value, 10) : 0;
          if (!p) { var firstPath = $('input[name="path"]', choice); if (firstPath) { firstPath.checked = true; } }
        }
        need = paths[pi] || paths[0] || null;
      }
      $$('[data-course]', choice).forEach(function (sec) {
        var show = !need || need.indexOf(sec.getAttribute('data-course')) !== -1;
        sec.hidden = !show;
        $$('input', sec).forEach(function (i) { i.disabled = !show; });
      });
      var total = $('[data-total]', choice);
      if (total) {
        var sum = 0;
        var mode = choice.getAttribute('data-choice');
        $$('input:checked', choice).forEach(function (i) {
          if (i.disabled) { return; }
          var price = parseFloat(i.getAttribute('data-price') || '0') || 0;
          if (i.name.indexOf('dish[') === 0 && mode === 'formule') { return; }
          if (i.name === 'path') { return; }
          sum += price;
        });
        total.textContent = money(sum);
      }
    };
    choice.addEventListener('change', update);
    update();
    $$('[data-show]', choice).forEach(function (btn) {
      btn.addEventListener('click', function () {
        var box = d.getElementById(btn.getAttribute('data-show'));
        if (box) { box.hidden = !box.hidden; var ta = $('textarea', box); if (ta && !box.hidden) { ta.focus(); } }
      });
    });
  }

  /* ---------- Mostra / nascondi blocchi collegati a una scelta ---------- */
  $$('[data-toggle-for]').forEach(function (box) {
    var name = box.getAttribute('data-toggle-for');
    var value = box.getAttribute('data-toggle-value');
    var sync = function () {
      var el = $('[name="' + name + '"]:checked') || $('select[name="' + name + '"]') || $('input[type="hidden"][name="' + name + '"]');
      box.hidden = !el || el.value !== value;
    };
    d.addEventListener('change', function (e) { if (e.target && e.target.name === name) { sync(); } });
    sync();
  });

  /* ---------- Anteprima della foto scelta ---------- */
  $$('input[type="file"][data-preview]').forEach(function (input) {
    input.addEventListener('change', function () {
      var img = d.getElementById(input.getAttribute('data-preview'));
      var f = input.files && input.files[0];
      if (img && f && window.URL) { img.src = URL.createObjectURL(f); img.hidden = false; }
    });
  });
})();
