<?php
/**
 * Il marchio intero: la rosa dei venti e, accanto, il logotipo
 * «arco del vento · Assisi».
 *
 * La rosa è decorativa per chi usa uno screen reader (alt vuoto): il nome lo
 * dice già il logotipo, e sentirlo due volte non aiuta. È servita in WebP
 * con la PNG di ripiego; il logotipo è un SVG, nitido a ogni misura.
 *
 * @var string $variante  mattone (su fondo chiaro) | avorio (sul mattone)
 * @var string $classe    classe del link
 */

$variante = ($variante ?? 'mattone') === 'avorio' ? 'avorio' : 'mattone';
$classe   = $classe ?? 'adv-marchio';
?>
<a class="<?= e($classe) ?>" href="<?= e(url('home')) ?>">
  <picture>
    <source type="image/webp" srcset="<?= e(asset('img/logo/icona-72.webp')) ?> 1x, <?= e(asset('img/logo/icona-144.webp')) ?> 2x">
    <img class="adv-marchio__rosa" src="<?= e(asset('img/logo/icona-96.png')) ?>" width="72" height="72" alt="" decoding="async">
  </picture>
  <img class="adv-marchio__nome" src="<?= e(asset('img/logo/logotipo-' . $variante . '.svg')) ?>" width="111" height="66"
       alt="<?= te('common.brand') ?>, Assisi">
</a>
