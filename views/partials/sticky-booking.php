<?php
/**
 * La pillola in fondo allo schermo, solo su schermo stretto: prenotare e
 * scrivere restano a un pollice di distanza.
 *
 * Con JavaScript compare quando la barra di prenotazione della pagina è
 * uscita dallo schermo, così non ne copre una uguale; senza, c'è sempre.
 * Non compare nella pagina di prenotazione: lì il pulsante c'è già, ed è
 * quello vero.
 */

if (($paginaCorrente ?? '') === 'book') {
    return;
}

$whatsapp = site('contacts.whatsapp');
?>
<div class="adv-sticky" data-sticky>
  <a class="adv-btn adv-btn--primario" href="<?= e(url('book')) ?>"><?= te('cta.book') ?></a>
  <?php if ($whatsapp): ?>
    <a class="adv-btn adv-btn--contorno"
       href="https://wa.me/<?= e(preg_replace('/\D/', '', (string) $whatsapp)) ?>" rel="noopener">
      <?= icona('messaggio', 15) ?><?= te('cta.whatsapp') ?>
    </a>
  <?php else: ?>
    <?php /* Senza un numero confermato la seconda azione porta ai contatti,
             dove il modulo funziona davvero. */ ?>
    <a class="adv-btn adv-btn--contorno" href="<?= e(url('contact')) ?>"><?= te('cta.write') ?></a>
  <?php endif; ?>
</div>
