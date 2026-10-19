<?php
/* L'anteprima dal vivo: il telefono accanto all'editor, sui monitor larghi.
   Sui telefoni e sui tablet c'è il bottone che apre l'anteprima intera. */
use MHW\Support;
$src = $src ?? '/pannello/' . (int) $prop['id'] . '/anteprima'; ?>
<aside class="phoneframe" aria-label="Anteprima della guida">
  <div class="phoneframe__bar">
    <span class="kicker"><?= MHW\Pannello::t('Anteprima') ?></span>
    <span class="row" style="gap:6px">
      <button type="button" class="btn btn--quiet btn--sm" data-ricarica-anteprima><?= MHW\Pannello::t('Aggiorna') ?></button>
      <a class="btn btn--ghost btn--sm" href="<?= Support::e(Support::url($src)) ?>" target="_blank" rel="noopener"><?= MHW\Pannello::t('Apri') ?></a>
    </span>
  </div>
  <div class="phoneframe__device">
    <div class="phoneframe__schermo"><iframe src="<?= Support::e(Support::url($src)) ?>" title="Anteprima della guida sul telefono dell'ospite" loading="lazy"></iframe></div>
  </div>
  <p class="tiny muted"><?= MHW\Pannello::t('Questa è l\'anteprima: gli ospiti vedono la guida solo dopo la pubblicazione.') ?></p>
</aside>
