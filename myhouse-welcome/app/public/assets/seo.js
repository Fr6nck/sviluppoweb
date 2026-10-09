/* Amministrazione → SEO e GEO: contatori dei caratteri, anteprima stile Google e
   anteprima di robots.txt, aggiornati mentre scrivi. Senza JavaScript la pagina
   funziona lo stesso: si vede l'ultimo salvataggio. */
(function () {
  'use strict';
  var f = document.querySelector('[data-seo]'); if (!f) return;

  // Contatori: «42 / 60», in rosso oltre il massimo.
  var contatori = f.querySelectorAll('[data-contatore]');
  function conta(c) {
    var campo = document.getElementById(c.getAttribute('data-contatore')); if (!campo) return;
    var n = Array.from(campo.value.trim()).length, max = +c.getAttribute('data-max');
    c.textContent = n + ' / ' + max;
    c.classList.toggle('contatore--oltre', n > max);
  }
  for (var i = 0; i < contatori.length; i++) conta(contatori[i]);

  // Anteprima nei risultati: il titolo vuoto mostra il testo segnaposto.
  function serp(box) {
    var t = box.querySelector('[data-serp="titolo"]'), d = box.querySelector('[data-serp="testo"]');
    var vt = box.querySelector('[data-serp-titolo]'), vd = box.querySelector('[data-serp-testo]');
    if (t && vt) vt.textContent = t.value.trim() || 'Titolo di serie della pagina';
    if (d && vd) { var s = d.value.trim(); vd.textContent = s.length > 158 ? s.slice(0, 155) + '…' : s; }
  }
  var pagine = f.querySelectorAll('[data-serp-pagina]');
  for (var p = 0; p < pagine.length; p++) serp(pagine[p]);

  // robots.txt: il blocco comune resta com'è, i bot spenti si aggiungono in fondo.
  var robots = f.querySelector('[data-robots]');
  var righe = robots ? robots.textContent.split('\n') : [];
  var comune = [], sitemap = '';
  for (var r = 0; r < righe.length; r++) {
    if (righe[r] === '') break;
    comune.push(righe[r]);
  }
  for (r = 0; r < righe.length; r++) if (righe[r].indexOf('Sitemap:') === 0) sitemap = righe[r];
  function aggiornaRobots() {
    if (!robots) return;
    var t = comune.join('\n') + '\n';
    var bot = f.querySelectorAll('[data-bot]');
    for (var b = 0; b < bot.length; b++) if (!bot[b].checked) t += '\nUser-agent: ' + bot[b].getAttribute('data-bot') + '\nDisallow: /\n';
    robots.textContent = t + '\n' + sitemap + '\n';
  }

  f.addEventListener('input', function (e) {
    for (var i = 0; i < contatori.length; i++) if (contatori[i].getAttribute('data-contatore') === e.target.id) conta(contatori[i]);
    var box = e.target.closest('[data-serp-pagina]'); if (box) serp(box);
  });
  f.addEventListener('change', function (e) { if (e.target.hasAttribute('data-bot')) aggiornaRobots(); });
})();
