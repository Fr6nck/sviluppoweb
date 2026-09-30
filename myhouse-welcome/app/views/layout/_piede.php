<?php /* Il piè di pagina del sito: chi c'è dietro a sinistra, come raggiungerci a
         destra. I dati vengono da config.php → legal; un valore vuoto non compare. */
use function MHW\b; use MHW\{Support, Config};
$l = Config::get('legal');
$tel = ($l['contact_phone'] ?? '') !== '' ? Support::telHref($l['contact_phone']) : '';
$wa = preg_replace('/\D/', '', (string) ($l['contact_whatsapp'] ?? '')); ?>
<footer class="wrap piede">
  <hr class="rule">
  <div class="piede__colonne">
    <div class="piede__chi">
      <span class="piede__marchio"><?= MHW\Icon::brand(22) ?>MyHouse Welcome<?= ($l['company'] ?? '') !== '' ? ' · un progetto ' . Support::e($l['company']) : '' ?></span>
      <?php if (($l['company_vat'] ?? '') !== ''): ?><span>P.IVA <?= Support::e($l['company_vat']) ?></span><?php endif; ?>
      <?php if (($l['company_city'] ?? '') !== ''): ?><span><?= Support::e($l['company_city']) ?></span><?php endif; ?>
    </div>
    <div class="piede__contatti">
      <?php if (($l['contact_email'] ?? '') !== ''): ?><a href="mailto:<?= Support::e($l['contact_email']) ?>"><?= Support::e($l['contact_email']) ?></a><?php endif; ?>
      <?php if ($tel !== ''): ?><a href="tel:<?= Support::e($tel) ?>">Tel. <?= Support::e($l['contact_phone']) ?></a><?php endif; ?>
      <?php if ($wa !== ''): ?><a href="https://wa.me/<?= Support::e($wa) ?>" rel="noopener">WhatsApp</a><?php endif; ?>
      <span><a href="<?= b() ?>/termini">Termini</a> · <a href="<?= b() ?>/privacy">Privacy</a></span>
    </div>
  </div>
</footer>
