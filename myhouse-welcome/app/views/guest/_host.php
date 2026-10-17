<?php
/* 7C · Chi ti accoglie: il tondo con la foto dell'host sopra la copertina (indipendente dal logo) e, al tocco,
   un <dialog> con la foto intera, «Ciao, sono {nome}», le due parole di benvenuto e «Scrivi a {nome}»
   (WhatsApp se il primo contatto lo ha, altrimenti il telefono). Senza foto profilo non compare niente.
   Riceve: $pr, $loc, $def. */
use MHW\{Support, I18n, Media, Icon};
$fotoHost = !empty($pr['profile_id']) ? Media::url((int) $pr['profile_id']) : null;
if (!$fotoHost) return;
$contattiH = array_values(array_filter($pr['contacts'] ?? [], fn($c) => trim((string) ($c['phone'] ?? '')) !== ''));
$nomeH = trim((string) ($contattiH[0]['name'] ?? '')) ?: trim((string) ($pr['host_name'] ?? ''));
$nomeH = $nomeH !== '' ? explode(' ', $nomeH)[0] : $pr['name'];
$benvenutoH = (string) (($pr['host_welcome'] ?? [])[$loc] ?? ($pr['host_welcome'] ?? [])[$def] ?? '') ?: I18n::t($loc, 'host_benvenuto');
$scriviH = '';
if ($contattiH) {
    $num = Support::telHref((string) $contattiH[0]['phone']);
    $scriviH = !empty($contattiH[0]['whatsapp']) ? 'https://wa.me/' . ltrim($num, '+') : 'tel:' . $num;
} ?>
<button type="button" class="host-tondo" data-host-apri="chi-ti-accoglie" aria-label="<?= Support::e(I18n::t($loc, 'host_tondo', $nomeH)) ?>"
   aria-haspopup="dialog"><img src="<?= Support::e($fotoHost) ?>" alt="" width="64" height="64"><span class="host-tondo__punto" aria-hidden="true"></span></button>
<dialog class="host-foglio" id="chi-ti-accoglie" aria-labelledby="host-foglio-titolo">
  <div class="host-foglio__dentro">
    <span class="host-foglio__maniglia" aria-hidden="true"></span>
    <button type="button" class="host-foglio__chiudi" data-host-chiudi aria-label="<?= Support::e(I18n::t($loc, 'close')) ?>"><?= Icon::svg('close', 18, 2) ?></button>
    <img class="host-foglio__foto" src="<?= Support::e($fotoHost) ?>" alt="">
    <h2 class="host-foglio__titolo" id="host-foglio-titolo"><?= Support::e(I18n::t($loc, 'host_ciao', $nomeH)) ?></h2>
    <p class="host-foglio__testo"><?= Support::e($benvenutoH) ?></p>
    <?php if ($scriviH !== ''): ?>
      <a class="btn btn--block" href="<?= Support::e($scriviH) ?>" rel="noopener"><?= Icon::svg(str_starts_with($scriviH, 'tel:') ? 'phone' : 'whatsapp', 17) ?><?= Support::e(I18n::t($loc, 'host_scrivi', $nomeH)) ?></a>
    <?php endif; ?>
  </div>
</dialog>
