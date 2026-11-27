/* L'anteprima dal vivo di Amministrazione → Testi e foto. La home è aperta in un iframe
   con ?anteprima=testi (solo per l'amministratore): ogni testo sta in uno <span data-testo>,
   gli elenchi hanno data-testo-lista, le foto data-foto. La pagina del pannello manda qui
   quello che si scrive (postMessage, stessa origine) e questo script lo mette al posto giusto,
   con la stessa sintassi del server: **grassetto**, [testo](link), a capo. I link non portano
   da nessuna parte: è un'anteprima. */
(function () {
  'use strict';
  if (window.parent === window) return;

  var esc = function (s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c]; }); };
  function formatta(t, sost) {
    var h = esc(String(t).trim());
    h = h.replace(/\*\*([\s\S]+?)\*\*/g, '<b>$1</b>');
    h = h.replace(/\[([^\]]+)\]\(([^)\s]+)\)/g, function (m, testo, u) {
      if (!/^(https?:\/\/|mailto:|\/|#)/i.test(u)) return m;
      return '<a href="' + u + '"' + (/^https?:\/\//i.test(u) ? ' rel="noopener" target="_blank"' : '') + '>' + testo + '</a>';
    });
    h = h.replace(/\n/g, '<br>\n');
    if (sost) Object.keys(sost).forEach(function (k) { h = h.split('{' + k + '}').join(sost[k]); });
    return h;
  }

  function testo(chiave, valore) {
    document.querySelectorAll('[data-testo="' + chiave + '"]').forEach(function (el) {
      var sost = el.getAttribute('data-sost');
      el.innerHTML = formatta(valore, sost ? JSON.parse(sost) : null);
    });
    document.querySelectorAll('[data-testo-lista="' + chiave + '"]').forEach(function (el) {
      var modello = el.firstElementChild; if (!modello) return;
      var righe = String(valore).split('\n').map(function (r) { return r.trim(); }).filter(Boolean);
      var nuovi = righe.map(function (r) {
        var c = modello.cloneNode(true);
        [].slice.call(c.childNodes).forEach(function (n) { if (n.nodeType === 3) n.remove(); });   // resta l'icona, cambia il testo
        c.insertAdjacentHTML('beforeend', formatta(r));
        return c;
      });
      el.replaceChildren.apply(el, nuovi);
    });
  }

  function foto(nome, url) {
    document.querySelectorAll('[data-foto="' + nome + '"]').forEach(function (img) {
      var p = img.closest('picture');
      if (p) p.querySelectorAll('source').forEach(function (s) { s.remove(); });
      img.removeAttribute('srcset'); img.src = url;
    });
  }

  var evidenza = null;
  function vai(chiave) {
    var el = document.querySelector('[data-testo="' + chiave + '"], [data-testo-lista="' + chiave + '"], [data-foto="' + chiave + '"]');
    if (!el) return;
    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    if (evidenza) evidenza.classList.remove('anteprima-evidenzia');
    evidenza = el; el.classList.add('anteprima-evidenzia');
    clearTimeout(vai.t); vai.t = setTimeout(function () { el.classList.remove('anteprima-evidenzia'); }, 1800);
  }

  window.addEventListener('message', function (e) {
    if (e.origin !== location.origin || !e.data || typeof e.data !== 'object') return;
    var m = e.data;
    if (m.tipo === 'testo') testo(String(m.chiave), String(m.valore));
    else if (m.tipo === 'foto') foto(String(m.nome), String(m.url));
    else if (m.tipo === 'vai') vai(String(m.chiave));
  });

  // Niente navigazione dentro l'anteprima: link e moduli non fanno nulla.
  document.addEventListener('click', function (e) { if (e.target.closest('a, button')) e.preventDefault(); }, true);
  document.addEventListener('submit', function (e) { e.preventDefault(); }, true);

  var st = document.createElement('style');
  st.textContent = '.anteprima-evidenzia{outline:3px solid #ee7a4a;outline-offset:4px;border-radius:6px;transition:outline-color .6s}';
  document.head.appendChild(st);
  window.parent.postMessage({ tipo: 'pronta' }, location.origin);
})();
