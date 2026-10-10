<?php
/* Va nel <head>, prima del foglio di stile: la scelta si applica prima che la
   pagina si disegni, così non si vede il lampo del tema sbagliato.

   $temaChiave  dove si ricorda la scelta (il pannello e la guida ne hanno una a testa)
   $temaBase    se c'è, il tema di partenza lo decide il server (la guida: lo sceglie
                l'host) e il sistema non conta; se manca, si segue il sistema. */
$temaChiave = $temaChiave ?? 'mhw-tema';
$temaBase = $temaBase ?? '';
?>
<script>
(function () {
  var d = document.documentElement, K = <?= json_encode($temaChiave) ?>, BASE = <?= json_encode($temaBase) ?>;
  try {
    var t = localStorage.getItem(K);
    if (t === 'scuro' || t === 'chiaro') d.setAttribute('data-theme', t);
    else if (BASE) d.setAttribute('data-theme', BASE);
  } catch (e) { if (BASE) d.setAttribute('data-theme', BASE); }

  function effettivo() {
    var scelto = d.getAttribute('data-theme');
    if (scelto === 'scuro' || scelto === 'chiaro') return scelto;
    return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'scuro' : 'chiaro';
  }
  function segna() {
    var t = effettivo(), bottoni = document.querySelectorAll('.tema');
    for (var i = 0; i < bottoni.length; i++) {
      var b = bottoni[i], et = t === 'scuro' ? b.getAttribute('data-et-chiaro') : b.getAttribute('data-et-scuro');
      b.setAttribute('data-tema', t);
      if (et) { b.setAttribute('aria-label', et); b.setAttribute('title', et); }
    }
  }
  window.mhwTema = function () {
    var nuovo = effettivo() === 'scuro' ? 'chiaro' : 'scuro';
    d.setAttribute('data-theme', nuovo);
    try { localStorage.setItem(K, nuovo); } catch (e) {}
    segna();
  };
  if (!BASE) {
    try {
      window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function () {
        if (!localStorage.getItem(K)) segna();
      });
    } catch (e) {}
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', segna); else segna();
})();
</script>
