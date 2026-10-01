/* La landing: i passi di «Come funziona» diventano schede.
   Senza questo script i passi sono link alle schermate, tutte visibili.
   Con lo script si vede una schermata alla volta: clic, oppure frecce,
   Home e Fine sulla tastiera (lo schema delle schede accessibili). */
(function () {
  var box = document.querySelector('[data-passi]');
  if (!box) return;
  var passi = Array.prototype.slice.call(box.querySelectorAll('.passo'));
  var schede = passi.map(function (p) { return document.getElementById(p.getAttribute('href').slice(1)); });
  if (schede.some(function (s) { return !s; })) return;

  var lista = box.querySelector('.passi__lista');
  lista.setAttribute('role', 'tablist');
  lista.setAttribute('aria-label', 'I passi');
  passi.forEach(function (p, i) {
    p.parentNode.setAttribute('role', 'presentation');
    p.setAttribute('role', 'tab');
    p.setAttribute('aria-controls', schede[i].id);
    schede[i].setAttribute('role', 'tabpanel');
    schede[i].setAttribute('aria-labelledby', p.id);
    schede[i].setAttribute('tabindex', '0');
  });

  function scegli(n, fuoco) {
    passi.forEach(function (p, i) {
      var si = i === n;
      p.setAttribute('aria-selected', si ? 'true' : 'false');
      p.setAttribute('tabindex', si ? '0' : '-1');
      schede[i].hidden = !si;
    });
    if (fuoco) passi[n].focus();
  }

  passi.forEach(function (p, i) {
    p.addEventListener('click', function (e) { e.preventDefault(); scegli(i, false); });
    p.addEventListener('keydown', function (e) {
      var n = { ArrowDown: i + 1, ArrowRight: i + 1, ArrowUp: i - 1, ArrowLeft: i - 1, Home: 0, End: passi.length - 1 }[e.key];
      if (n === undefined) return;
      e.preventDefault();
      scegli((n + passi.length) % passi.length, true);
    });
  });
  scegli(0, false);
})();
