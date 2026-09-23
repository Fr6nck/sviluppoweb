<?php
/**
 * Pagina non trovata.
 *
 * Non una faccia triste e un «torna alla home»: le tre strade che servono
 * davvero a chi si è perso — le camere, le informazioni pratiche, i contatti.
 */
?>

<div class="adv-contenuto">
  <header class="adv-heroT">
    <span class="adv-heroT__occhiello"><?= te('not_found.eyebrow') ?></span>
    <h1 class="adv-heroT__titolo">
      <?= te('not_found.title') ?> <span class="adv-firma-inline"><?= te('not_found.sign') ?></span>
    </h1>
    <p class="adv-heroT__spalla"><?= te('not_found.lead') ?></p>
  </header>
</div>

<section class="adv-stretto adv-editoriale">
  <?= component('index-list', [
      'voci' => [
          ['etichetta' => t('nav.rooms'),    'nota' => t('rooms.eyebrow'),   'href' => url('rooms')],
          ['etichetta' => t('nav.property'), 'nota' => t('property.eyebrow'), 'href' => url('property')],
          ['etichetta' => t('nav.assisi'),   'nota' => t('assisi.eyebrow'),  'href' => url('assisi')],
          ['etichetta' => t('nav.info'),     'nota' => t('info.eyebrow'),    'href' => url('info')],
          ['etichetta' => t('nav.contact'),  'nota' => t('contact.eyebrow'), 'href' => url('contact')],
      ],
  ]) ?>

  <p class="adv-azione-coda">
    <a class="adv-btn adv-btn--primario" href="<?= e(url('home')) ?>"><?= te('book.done.home') ?></a>
  </p>
</section>
