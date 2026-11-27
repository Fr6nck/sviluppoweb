/* 9U · La finta chat del Concierge AI in home: parte nella lingua della pagina e poi, ogni pochi secondi, mostra la stessa
   domanda e la stessa risposta in un'altra lingua della guida (data-chat-lingue: [{l, d, r, f}, …]), per far vedere «In ogni
   lingua». Si ferma con «riduci il movimento», quando la pagina non è visibile e quando ci si passa sopra; senza JavaScript
   resta la lingua della pagina. La chat è un'immagine per lo screen reader (role="img" con la sua descrizione). */
(function () {
  'use strict';
  var fig = document.querySelector('[data-chat-lingue]');
  if (!fig || (window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches)) return;
  var altre = []; try { altre = JSON.parse(fig.getAttribute('data-chat-lingue') || '[]'); } catch (e) { altre = []; }
  var box = fig.querySelector('.assistente__fumetti'), d = fig.querySelector('[data-chat-d]'), r = fig.querySelector('[data-chat-r]'), f = fig.querySelector('[data-chat-f]');
  if (!altre.length || !box || !d || !r || !f) return;
  var lingue = [{ l: box.getAttribute('lang') || '', d: d.textContent, r: r.textContent, f: f.textContent }].concat(altre);
  var i = 0, fermo = false;
  function mostra(v) { box.setAttribute('lang', v.l); d.textContent = v.d; r.textContent = v.r; f.textContent = v.f; }
  function avanti() {
    if (fermo || document.hidden) return;
    box.classList.add('chat-lingue--via');
    setTimeout(function () { i = (i + 1) % lingue.length; mostra(lingue[i]); box.classList.remove('chat-lingue--via'); }, 350);
  }
  fig.addEventListener('mouseenter', function () { fermo = true; });
  fig.addEventListener('mouseleave', function () { fermo = false; });
  setInterval(avanti, 4000);
})();
