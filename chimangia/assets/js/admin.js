/* ChiMangia — script del pannello (nessuno script inline: CSP restrittiva). */
(function () {
  'use strict';

  /* ---------- Menu laterale a scomparsa su mobile ---------- */
  var toggle = document.querySelector('.menu-toggle');
  var sidebar = document.getElementById('sidebar');
  if (toggle && sidebar) {
    toggle.addEventListener('click', function () {
      var open = sidebar.classList.toggle('open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (open) {
        var first = sidebar.querySelector('a');
        if (first) first.focus();
      }
    });
    document.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape' && sidebar.classList.contains('open')) {
        sidebar.classList.remove('open');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.focus();
      }
    });
  }

  /* ---------- Caratteri consigliati (non bloccante) ---------- */
  function updateCount(input) {
    var rec = parseInt(input.getAttribute('data-rec'), 10);
    var out = document.getElementById(input.id + '-count');
    if (!rec || !out) return;
    var len = (input.value || '').length;
    out.textContent = len + ' di ' + rec + ' caratteri consigliati' + (len > rec ? ': il testo è più lungo del consigliato.' : '.');
    out.classList.toggle('over', len > rec);
  }
  document.querySelectorAll('[data-rec]').forEach(function (el) {
    updateCount(el);
    el.addEventListener('input', function () { updateCount(el); });
  });

  /* ---------- Avviso per modifiche non salvate ---------- */
  var dirty = false;
  var guarded = document.querySelectorAll('form[data-dirty-check]');
  guarded.forEach(function (form) {
    form.addEventListener('input', function () { dirty = true; });
    form.addEventListener('change', function () { dirty = true; });
    form.addEventListener('submit', function (ev) {
      var submitter = ev.submitter;
      // L'anteprima si apre in un'altra scheda e salva la bozza: le modifiche risultano salvate.
      if (!submitter || submitter.getAttribute('formtarget') !== '_blank') {
        dirty = false;
      } else {
        setTimeout(function () { dirty = false; }, 0);
      }
    });
  });
  window.addEventListener('beforeunload', function (ev) {
    if (!dirty) return;
    ev.preventDefault();
    ev.returnValue = '';
  });
  window.cmMarkDirty = function () { dirty = true; };

  /* ---------- Conferme ---------- */
  document.addEventListener('click', function (ev) {
    var el = ev.target.closest('[data-confirm]');
    if (el && !window.confirm(el.getAttribute('data-confirm'))) {
      ev.preventDefault();
    }
  });

  /* ---------- Editor semplice: grassetto, corsivo, link, elenchi ---------- */
  var ALLOWED = { P: 1, BR: 1, STRONG: 1, EM: 1, A: 1, UL: 1, OL: 1, LI: 1 };
  var RENAME = { B: 'STRONG', I: 'EM', DIV: 'P' };

  function cleanNode(node, doc) {
    var out = doc.createDocumentFragment();
    node.childNodes.forEach(function (child) {
      if (child.nodeType === 3) {
        out.appendChild(doc.createTextNode(child.textContent));
        return;
      }
      if (child.nodeType !== 1) return;
      var tag = RENAME[child.tagName] || child.tagName;
      var inner = cleanNode(child, doc);
      if (!ALLOWED[tag]) {
        out.appendChild(inner);
        return;
      }
      var el = doc.createElement(tag);
      if (tag === 'A') {
        var href = child.getAttribute('href') || '';
        if (/^(https?:|mailto:|tel:|#|\/|\.\.?\/)/i.test(href)) el.setAttribute('href', href);
      }
      el.appendChild(inner);
      out.appendChild(el);
    });
    return out;
  }

  function cleanHtml(html) {
    var doc = document.implementation.createHTMLDocument('');
    var box = doc.createElement('div');
    box.innerHTML = html;
    var res = doc.createElement('div');
    res.appendChild(cleanNode(box, doc));
    return res.innerHTML.replace(/<p><\/p>/g, '').replace(/<p><br><\/p>/g, '');
  }

  document.querySelectorAll('[data-rte]').forEach(function (wrap) {
    var bar = wrap.querySelector('.rte-bar');
    var area = wrap.querySelector('.rte-area');
    var src = wrap.querySelector('.rte-source');
    if (!bar || !area || !src) return;
    bar.hidden = false;
    area.hidden = false;
    src.hidden = true;
    src.setAttribute('tabindex', '-1');
    area.innerHTML = cleanHtml(src.value);
    // L'etichetta del campo porta al riquadro modificabile.
    var lab = document.querySelector('label[for="' + src.id + '"]');
    if (lab) lab.addEventListener('click', function (ev) { ev.preventDefault(); area.focus(); });
    if (src.getAttribute('aria-describedby')) area.setAttribute('aria-describedby', src.getAttribute('aria-describedby'));
    try { document.execCommand('defaultParagraphSeparator', false, 'p'); } catch (e) { /* ignorato */ }

    function sync() {
      src.value = cleanHtml(area.innerHTML);
      if (window.cmMarkDirty) window.cmMarkDirty();
    }
    area.addEventListener('input', sync);
    area.addEventListener('blur', function () { src.value = cleanHtml(area.innerHTML); });
    area.addEventListener('paste', function (ev) {
      ev.preventDefault();
      var text = (ev.clipboardData || window.clipboardData).getData('text/plain');
      document.execCommand('insertText', false, text);
    });
    area.addEventListener('keydown', function (ev) {
      if ((ev.ctrlKey || ev.metaKey) && (ev.key === 'b' || ev.key === 'i')) {
        ev.preventDefault();
        document.execCommand(ev.key === 'b' ? 'bold' : 'italic');
        sync();
      }
    });
    bar.addEventListener('click', function (ev) {
      var btn = ev.target.closest('.rte-btn');
      if (!btn) return;
      area.focus();
      var cmd = btn.getAttribute('data-cmd');
      if (cmd === 'bold' || cmd === 'italic') document.execCommand(cmd);
      else if (cmd === 'ul') document.execCommand('insertUnorderedList');
      else if (cmd === 'ol') document.execCommand('insertOrderedList');
      else if (cmd === 'unlink') document.execCommand('unlink');
      else if (cmd === 'link') {
        var url = window.prompt('Indirizzo del link (https://…, mailto:…, tel:… oppure #sezione):', 'https://');
        if (url && /^(https?:|mailto:|tel:|#|\/|\.\.?\/)/i.test(url.trim())) document.execCommand('createLink', false, url.trim());
        else if (url) window.alert('Indirizzo non valido: usa https://, mailto:, tel: oppure #sezione.');
      }
      sync();
    });
    var form = wrap.closest('form');
    if (form) form.addEventListener('submit', function () { src.value = cleanHtml(area.innerHTML); });
  });

  /* ---------- Elenchi riordinabili (trascinamento e frecce) ---------- */
  function renumber(list) {
    var items = list.querySelectorAll(':scope > .item');
    items.forEach(function (item, i) {
      var pos = item.querySelector('[data-pos]');
      if (pos) pos.textContent = String(i + 1);
      var up = item.querySelector('[data-move="up"]');
      var down = item.querySelector('[data-move="down"]');
      if (up) up.disabled = i === 0;
      if (down) down.disabled = i === items.length - 1;
    });
  }

  function announce(list, text) {
    var live = list.parentNode.querySelector('.sr-live');
    if (live) live.textContent = text;
  }

  document.querySelectorAll('[data-repeater]').forEach(function (list) {
    var dragged = null;
    renumber(list);

    list.addEventListener('click', function (ev) {
      var btn = ev.target.closest('[data-move], [data-remove]');
      if (!btn || !list.contains(btn)) return;
      var item = btn.closest('.item');
      if (btn.hasAttribute('data-remove')) {
        if (!window.confirm(btn.getAttribute('data-remove') || 'Eliminare questo elemento?')) return;
        var next = item.nextElementSibling || item.previousElementSibling;
        item.remove();
        renumber(list);
        if (window.cmMarkDirty) window.cmMarkDirty();
        announce(list, 'Elemento eliminato.');
        var addBtn = list.parentNode.querySelector('[data-add]');
        if (next) { var f = next.querySelector('input, textarea, select, button'); if (f) f.focus(); }
        else if (addBtn) addBtn.focus();
        return;
      }
      var dir = btn.getAttribute('data-move');
      if (dir === 'up' && item.previousElementSibling) list.insertBefore(item, item.previousElementSibling);
      if (dir === 'down' && item.nextElementSibling) list.insertBefore(item.nextElementSibling, item);
      renumber(list);
      btn.focus();
      if (btn.disabled) { var other = item.querySelector('[data-move]:not([disabled])'); if (other) other.focus(); }
      if (window.cmMarkDirty) window.cmMarkDirty();
      var idx = Array.prototype.indexOf.call(list.children, item) + 1;
      announce(list, 'Spostato in posizione ' + idx + '.');
    });

    list.addEventListener('dragstart', function (ev) {
      var item = ev.target.closest('.item');
      if (!item || !ev.target.closest('.drag-handle')) { return; }
      dragged = item;
      item.classList.add('dragging');
      ev.dataTransfer.effectAllowed = 'move';
      try { ev.dataTransfer.setData('text/plain', 'item'); } catch (e) { /* ignorato */ }
    });
    list.addEventListener('dragover', function (ev) {
      if (!dragged) return;
      ev.preventDefault();
      var over = ev.target.closest('.item');
      list.querySelectorAll('.drop-target').forEach(function (n) { n.classList.remove('drop-target'); });
      if (over && over !== dragged) over.classList.add('drop-target');
    });
    list.addEventListener('drop', function (ev) {
      if (!dragged) return;
      ev.preventDefault();
      var over = ev.target.closest('.item');
      if (over && over !== dragged) {
        var rect = over.getBoundingClientRect();
        var after = ev.clientY > rect.top + rect.height / 2;
        list.insertBefore(dragged, after ? over.nextElementSibling : over);
      }
      renumber(list);
      if (window.cmMarkDirty) window.cmMarkDirty();
    });
    list.addEventListener('dragend', function () {
      if (dragged) dragged.classList.remove('dragging');
      list.querySelectorAll('.drop-target').forEach(function (n) { n.classList.remove('drop-target'); });
      dragged = null;
    });
    // Le maniglie rendono trascinabile solo l'elemento corrispondente.
    list.addEventListener('mousedown', function (ev) {
      var h = ev.target.closest('.drag-handle');
      if (h) h.closest('.item').setAttribute('draggable', 'true');
    });
    list.addEventListener('mouseup', function () {
      list.querySelectorAll('.item[draggable]').forEach(function (n) { n.removeAttribute('draggable'); });
    });
  });

  /* Aggiunta di un nuovo elemento da <template> (gli indici __N__ diventano univoci). */
  document.querySelectorAll('[data-add]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var list = document.getElementById(btn.getAttribute('data-add'));
      var tpl = document.getElementById(btn.getAttribute('data-template'));
      if (!list || !tpl) return;
      var n = 'n' + Date.now().toString(36);
      var html = tpl.innerHTML.replace(/__N__/g, n);
      var holder = document.createElement('div');
      holder.innerHTML = html.trim();
      var item = holder.firstElementChild;
      list.appendChild(item);
      renumber(list);
      if (window.cmMarkDirty) window.cmMarkDirty();
      var first = item.querySelector('input[type="text"], textarea, input:not([type="hidden"])');
      if (first) first.focus();
      initRte(item);
      announce(list, 'Nuovo elemento aggiunto in fondo.');
    });
  });

  function initRte(scope) {
    // Gli editor aggiunti dinamicamente restano come area di testo semplice con HTML ammesso.
    scope.querySelectorAll('[data-rte] .rte-source').forEach(function (src) { src.hidden = false; });
  }

  /* ---------- Prezzi: calcolo dei prezzi di lancio ---------- */
  var calc = document.getElementById('calc-launch');
  if (calc) {
    calc.addEventListener('click', function () {
      var pctField = document.getElementById('f-offer-percent');
      var pct = parseFloat(String(pctField ? pctField.value : '0').replace(',', '.')) || 0;
      if (pct <= 0 || pct >= 100) { window.alert('Imposta prima una percentuale di sconto tra 1 e 99.'); return; }
      var count = 0;
      document.querySelectorAll('[data-full]').forEach(function (full) {
        var target = document.getElementById(full.getAttribute('data-full'));
        var v = parseFloat(String(full.value).replace(/\./g, '').replace(',', '.'));
        if (target && !isNaN(v) && v > 0) {
          target.value = String(Math.round(v * (1 - pct / 100)));
          count++;
        }
      });
      if (window.cmMarkDirty) window.cmMarkDirty();
      var out = document.getElementById('calc-result');
      if (out) out.textContent = 'Calcolati ' + count + ' prezzi di lancio con lo sconto del ' + pct + '%, arrotondati all\'euro. Puoi ancora modificarli a mano; ricordati di salvare.';
    });
  }

  /* ---------- Email di prova e altri invii con conferma visiva ---------- */
  document.querySelectorAll('[data-autosubmit]').forEach(function (el) {
    el.addEventListener('change', function () { el.form.submit(); });
  });
})();
