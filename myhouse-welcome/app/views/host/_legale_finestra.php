<?php
/* 8J · «Abbiamo aggiornato i Termini e l'Informativa». Due casi (Legale):
   - documenti in vigore e non ancora accettati: la finestra con le novità, i link e «Accetto». Finché non accetta,
     il cliente può solo consultare il pannello (Legale::solaLettura blocca i moduli). La finestra si apre come
     finestra modale una volta per sessione (poi resta il riquadro in cima); senza JavaScript è un riquadro.
   - documenti annunciati per una data futura: una riga, con «Accetto già adesso».
   Mai durante l'accesso dell'assistenza. Riceve $daAccettare e $annunciati (elenchi di documenti). */
use function MHW\b;
use MHW\{Support, Csrf, Legale, TestiSito};
$docs = $daAccettare ?: $annunciati;
$nomi = array_map(fn($d) => '<a href="' . b() . Legale::DOCUMENTI[$d][2] . '" target="_blank" rel="noopener">' . Support::e(Legale::DOCUMENTI[$d][0]) . '</a>', $docs);
$elenco = count($nomi) > 1 ? implode(', ', array_slice($nomi, 0, -1)) . ' e ' . end($nomi) : $nomi[0];
$quando = max(array_map(fn($d) => Legale::versioni()[$d], $docs));
if ($daAccettare): ?>
<section class="riaccetta" id="riaccetta" role="region" aria-labelledby="riaccetta-titolo" data-riaccetta tabindex="-1">
  <h2 id="riaccetta-titolo">Abbiamo aggiornato i Termini e l'Informativa</h2>
  <ul class="riaccetta__novita">
    <?php foreach (TestiSito::lista('legale.novita') as $riga): ?><li><?= $riga ?></li><?php endforeach; ?>
  </ul>
  <p>Leggi: <?= $elenco ?>. Valgono dal <?= Support::e(Support::date($quando)) ?>.
    Finché non li accetti puoi consultare il pannello, ma non modificare né pubblicare le guide. Se non li accetti puoi disdire o chiedere la chiusura dell'account
    scrivendo a <a href="mailto:<?= Support::e(Support::contatto()) ?>"><?= Support::e(Support::contatto()) ?></a>.</p>
  <div class="riaccetta__azioni">
    <form method="post" action="<?= b() ?>/legale/accetta" style="margin:0"><?= Csrf::field() ?>
      <button class="btn">Accetto</button></form>
    <button type="button" class="btn btn--ghost" data-riaccetta-dopo hidden>Prima leggo con calma</button>
  </div>
</section>
<script>
/* La prima volta in questa sessione la finestra si apre sopra la pagina; «Prima leggo con calma» la rimette in cima. */
(function () {
  var f = document.querySelector('[data-riaccetta]'); if (!f) return;
  var dopo = f.querySelector('[data-riaccetta-dopo]'), vista = false;
  try { vista = sessionStorage.getItem('mhw-riaccetta') === '1'; } catch (e) {}
  if (vista) return;
  f.classList.add('riaccetta--finestra'); f.setAttribute('role', 'dialog'); f.setAttribute('aria-modal', 'true');
  document.body.classList.add('con-finestra'); dopo.hidden = false; f.focus();
  var chiudi = function () {
    f.classList.remove('riaccetta--finestra'); f.setAttribute('role', 'region'); f.removeAttribute('aria-modal');
    document.body.classList.remove('con-finestra'); dopo.hidden = true;
    try { sessionStorage.setItem('mhw-riaccetta', '1'); } catch (e) {}
  };
  dopo.addEventListener('click', chiudi);
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && f.classList.contains('riaccetta--finestra')) chiudi(); });
})();
</script>
<?php else: ?>
<aside class="note riaccetta-annuncio" role="status">
  <span>Dal <b><?= Support::e(Support::date($quando)) ?></b> cambiano <?= $elenco ?>. Fino ad allora valgono quelli di prima; se non li accetti puoi disdire senza penali.</span>
  <form method="post" action="<?= b() ?>/legale/accetta" style="margin:0"><?= Csrf::field() ?><button class="btn btn--sm btn--ghost">Li ho letti, accetto</button></form>
</aside>
<?php endif;
