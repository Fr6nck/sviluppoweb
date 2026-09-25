<?php
/**
 * L'informativa.
 *
 * Esiste perché le due spunte nei moduli rimandano qui: una spunta che punta
 * a una pagina vuota è peggio di nessuna spunta. Il testo è impostato e
 * onesto, e i punti che il titolare deve decidere sono segnati.
 */

$blocchi   = tlist('privacy.blocks');
$daSegnare = ['who', 'how_long'];   // i punti che il titolare deve completare
?>

<header class="adv-contenuto adv-testa">
  <?= occhiello(t('privacy.eyebrow'), 'adv-occhiello--centro') ?>
  <h1 class="adv-titolo adv-titolo--xl"><?= titolo(t('privacy.title'), t('privacy.sign')) ?></h1>
  <p class="adv-testa__testo"><?= te('privacy.lead') ?></p>
</header>

<section class="adv-stretto adv-sezione adv-sezione--stretta-sopra">

  <?= component('alert', [
      'tipo'   => 'avviso',
      'titolo' => t('privacy.notice_title'),
      'testo'  => t('privacy.notice_text'),
  ]) ?>

  <div class="adv-pannello adv-spazio-sopra">
    <?php foreach ($blocchi as $chiave => $blocco): ?>
      <div class="adv-blocco-testo">
        <h2 class="adv-titolo adv-titolo--s"><?= e($blocco['title']) ?></h2>
        <p class="adv-testo"><?= e($blocco['text']) ?></p>
        <?php if (in_array($chiave, $daSegnare, true)): ?>
          <p class="adv-nota"><?= daConfermare() ?></p>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="adv-azioni">
    <a class="adv-btn adv-btn--contorno" href="<?= e(url('contact')) ?>"><?= te('cta.write') ?><?= icona('freccia-su-destra', 14) ?></a>
  </div>
</section>
