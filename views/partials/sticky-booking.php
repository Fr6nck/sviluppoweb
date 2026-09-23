<?php
/**
 * La barra di prenotazione appiccicata in fondo, solo su schermo stretto.
 *
 * Il design system chiede fondo pieno e ombra quando una barra resta
 * appiccicata: mai una trasparenza, che sopra una fotografia rende
 * illeggibile quello che c'è dentro.
 *
 * Non compare nella pagina di prenotazione: lì il pulsante c'è già, ed è
 * quello vero.
 */

if (($paginaCorrente ?? '') === 'book') {
    return;
}

$whatsapp = site('contacts.whatsapp');
?>
<div class="adv-sticky" data-sticky>
  <a class="adv-btn adv-btn--primario adv-btn--pieno" href="<?= e(url('book')) ?>"><?= te('cta.book') ?></a>
  <?php if ($whatsapp): ?>
    <a class="adv-btn adv-btn--secondario adv-sticky__secondaria"
       href="https://wa.me/<?= e(preg_replace('/\D/', '', (string) $whatsapp)) ?>" rel="noopener">
      <?= te('cta.whatsapp') ?>
    </a>
  <?php else: ?>
    <?php /* Senza un numero confermato la seconda azione porta ai contatti,
             dove il modulo funziona davvero. Un pulsante WhatsApp che non
             apre WhatsApp è peggio di un pulsante in meno. */ ?>
    <a class="adv-btn adv-btn--secondario adv-sticky__secondaria" href="<?= e(url('contact')) ?>">
      <?= te('cta.write') ?>
    </a>
  <?php endif; ?>
</div>
