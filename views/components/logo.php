<?php
/**
 * Il marchio intero: la rosa dei venti e, accanto, il logotipo
 * «arco del vento · Assisi».
 *
 * Tutti e due si possono sostituire dall'area riservata (Immagini → Marchio);
 * qui si prende quello che c'è. La rosa è decorativa per chi usa uno screen
 * reader (alt vuoto): il nome lo dice già il logotipo, e sentirlo due volte
 * non aiuta.
 *
 * @var string $variante  mattone (su fondo chiaro) | avorio (sul mattone)
 * @var string $classe    classe del link
 */

$variante = ($variante ?? 'mattone') === 'avorio' ? 'avorio' : 'mattone';
$classe   = $classe ?? 'adv-marchio';

$rosa  = immagine('marchio.rosa');
$logo  = immagine($variante === 'avorio' ? 'marchio.logotipo-chiaro' : 'marchio.logotipo');
$base  = (string) $rosa['src'];

// Le misure scritte nel markup servono solo a riservare lo spazio con le
// proporzioni giuste; l'altezza vera la decide il foglio di stile.
$altezza   = $variante === 'avorio' ? 110 : 66;
$larghezza = ($logo['w'] && $logo['h']) ? (int) round($altezza * $logo['w'] / $logo['h']) : (int) round($altezza * 1.68);
?>
<a class="<?= e($classe) ?>" href="<?= e(url('home')) ?>">
  <picture>
    <?php if (assetEsiste($base . '-72.webp')): ?>
      <source type="image/webp" srcset="<?= e(asset($base . '-72.webp')) ?> 1x, <?= e(asset($base . '-144.webp')) ?> 2x">
    <?php endif; ?>
    <img class="adv-marchio__rosa" src="<?= e(asset($base . '-96.png')) ?>" width="72" height="72" alt="" decoding="async">
  </picture>
  <img class="adv-marchio__nome" src="<?= e(asset((string) $logo['src'])) ?>" width="<?= $larghezza ?>" height="<?= $altezza ?>"
       alt="<?= te('common.brand') ?>, Assisi">
</a>
