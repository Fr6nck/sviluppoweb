<?php /* Va nel <head>, prima del foglio di stile: la scelta si applica prima
         che la pagina si disegni, così non si vede il lampo bianco. */ ?>
<script>
(function () {
  var d = document.documentElement;
  try {
    var t = localStorage.getItem('mhw-tema');
    if (t === 'scuro' || t === 'chiaro') d.setAttribute('data-theme', t);
  } catch (e) {}

  function effettivo() {
    var scelto = d.getAttribute('data-theme');
    if (scelto === 'scuro' || scelto === 'chiaro') return scelto;
    return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'scuro' : 'chiaro';
  }

  function segna() {
    var t = effettivo();
    var bottoni = document.querySelectorAll('.tema');
    for (var i = 0; i < bottoni.length; i++) {
      var b = bottoni[i];
      var etichetta = t === 'scuro' ? 'Passa al tema chiaro' : 'Passa al tema scuro';
      b.setAttribute('data-tema', t);
      b.setAttribute('aria-label', etichetta);
      b.setAttribute('title', etichetta);
    }
    var meta = document.querySelector('meta[name="theme-color"]');
    if (meta && !(document.body && document.body.classList.contains('night'))) {
      meta.setAttribute('content', t === 'scuro' ? '#17130d' : '#faf5ec');
    }
  }

  window.mhwTema = function () {
    var nuovo = effettivo() === 'scuro' ? 'chiaro' : 'scuro';
    d.setAttribute('data-theme', nuovo);
    try { localStorage.setItem('mhw-tema', nuovo); } catch (e) {}
    segna();
  };

  // Finché nessuno ha scelto, si continua a seguire il sistema anche se cambia
  // mentre la pagina è aperta.
  try {
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function () {
      if (!localStorage.getItem('mhw-tema')) segna();
    });
  } catch (e) {}

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', segna);
  else segna();
})();
</script>
