<?php
/**
 * Pagina non trovata.
 *
 * Non una faccia triste e un «torna alla home»: le strade che servono
 * davvero a chi si è perso — le camere, la casa, Assisi, le informazioni
 * pratiche, i contatti.
 */
?>

<header class="adv-contenuto adv-testa">
  <?= occhiello(t('not_found.eyebrow'), 'adv-occhiello--centro') ?>
  <h1 class="adv-titolo adv-titolo--xl"><?= titolo(t('not_found.title'), t('not_found.sign')) ?></h1>
  <p class="adv-testa__testo"><?= te('not_found.lead') ?></p>
</header>

<section class="adv-stretto adv-sezione adv-sezione--stretta-sopra">
  <?= component('index-list', [
      'voci' => [
          ['etichetta' => t('nav.rooms'),    'nota' => t('rooms.eyebrow'),    'href' => url('rooms')],
          ['etichetta' => t('nav.property'), 'nota' => t('property.eyebrow'), 'href' => url('property')],
          ['etichetta' => t('nav.assisi'),   'nota' => t('assisi.eyebrow'),   'href' => url('assisi')],
          ['etichetta' => t('nav.info'),     'nota' => t('info.eyebrow'),     'href' => url('info')],
          ['etichetta' => t('nav.contact'),  'nota' => t('contact.eyebrow'),  'href' => url('contact')],
      ],
  ]) ?>

  <div class="adv-azioni">
    <a class="adv-btn adv-btn--primario" href="<?= e(url('home')) ?>"><?= te('book.done.home') ?><?= icona('freccia-su-destra', 14) ?></a>
  </div>
</section>
