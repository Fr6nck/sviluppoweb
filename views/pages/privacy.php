<?php
/**
 * L'informativa.
 *
 * Esiste perché le due spunte nei moduli rimandano qui: una spunta che punta
 * a una pagina vuota è peggio di nessuna spunta. Il testo è impostato e
 * onesto, e i punti che il titolare deve decidere sono segnati.
 */
?>

<div class="adv-contenuto">
  <header class="adv-heroT">
    <span class="adv-heroT__occhiello"><?= te('privacy.eyebrow') ?></span>
    <h1 class="adv-heroT__titolo">
      <?= te('privacy.title') ?> <span class="adv-firma-inline"><?= te('privacy.sign') ?></span>
    </h1>
    <p class="adv-heroT__spalla"><?= te('privacy.lead') ?></p>
  </header>
</div>

<section class="adv-stretto adv-editoriale">

  <?= component('alert', [
      'tipo'   => 'avviso',
      'titolo' => t('privacy.notice_title'),
      'testo'  => t('privacy.notice_text'),
  ]) ?>

  <?php
  $blocchi   = tlist('privacy.blocks');
  $daSegnare = ['who', 'how_long'];   // i punti che il titolare deve completare
  ?>

  <?php foreach ($blocchi as $chiave => $blocco): ?>
    <div class="adv-blocco-testo">
      <h2 class="adv-titolo-md"><?= e($blocco['title']) ?></h2>
      <p class="adv-testo"><?= e($blocco['text']) ?></p>
      <?php if (in_array($chiave, $daSegnare, true)): ?>
        <p class="adv-nota"><?= daConfermare() ?></p>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>

  <?= component('ornament', ['corto' => true]) ?>

  <p class="adv-azione-coda">
    <a class="adv-elenco__link" href="<?= e(url('contact')) ?>">
      <?= te('cta.write') ?><span aria-hidden="true">&rarr;</span>
    </a>
  </p>
</section>
