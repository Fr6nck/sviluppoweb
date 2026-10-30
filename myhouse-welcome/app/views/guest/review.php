<?php
/* La pagina delle recensioni (8C): /g/{slug}/recensione, il link del messaggio di fine soggiorno (8B).
   Copertina piccola e nome della struttura, poi «Com'è andato il soggiorno?» con le cinque stelle
   (guest/_voto.php, lo stesso blocco della pagina di saluto). Layout della guida, stesso noindex. */
use MHW\{Support, I18n, Media};
$pr = $snap['property'];
$title = I18n::t($loc, 'rating_q') . ' — ' . $pr['name'];
$copertina = !empty($pr['cover_id']) ? Media::url((int) $pr['cover_id']) : ($pr['cover_url'] ?? null);
$coda = '/recensione'; ?>

<?php include __DIR__ . '/_top.php'; ?>

<div class="recensione">
  <?php if ($copertina): ?>
    <div class="recensione__copertina"><img src="<?= Support::e($copertina) ?>" alt="" decoding="async"></div>
  <?php endif; ?>
  <p class="recensione__nome"><?= Support::e($pr['name']) ?></p>
  <?php $azione = $base . '/recensione'; $chiaro = false; include __DIR__ . '/_voto.php'; ?>
  <a class="recensione__guida" href="<?= Support::e($base) ?>?l=<?= Support::e($loc) ?>"><?= Support::e(I18n::t($loc, 'farewell_back')) ?></a>
</div>
