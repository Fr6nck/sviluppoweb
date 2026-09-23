<?php
/**
 * Il riepilogo di quello che si sta chiedendo: camera, date, notti, ospiti,
 * tariffa, totale. Compare al passo dei dati e nella conferma.
 *
 * Il totale porta sempre la sua nota: tassa di soggiorno esclusa e conferma
 * di Daniele. Una cifra senza condizioni è una promessa che il sito non può
 * mantenere.
 *
 * @var string $camera
 * @var string $arrivo     ISO
 * @var string $partenza   ISO
 * @var int    $notti
 * @var int    $ospiti
 * @var float  $tariffa
 * @var float  $totale
 * @var bool   $dimostrativa
 */
?>
<dl class="adv-riepilogo">
  <div class="adv-riepilogo__riga">
    <dt><?= te('book.summary.room') ?></dt>
    <dd><?= e($camera) ?></dd>
  </div>
  <div class="adv-riepilogo__riga">
    <dt><?= te('book.summary.dates') ?></dt>
    <dd>
      <time datetime="<?= e($arrivo) ?>"><?= e(dataEstesa($arrivo)) ?></time>
      &rarr;
      <time datetime="<?= e($partenza) ?>"><?= e(dataEstesa($partenza)) ?></time>
    </dd>
  </div>
  <div class="adv-riepilogo__riga">
    <dt><?= te('book.summary.nights') ?></dt>
    <dd><?= (int) $notti ?></dd>
  </div>
  <div class="adv-riepilogo__riga">
    <dt><?= te('book.summary.guests') ?></dt>
    <dd><?= (int) $ospiti ?></dd>
  </div>
  <div class="adv-riepilogo__riga">
    <dt><?= te('book.summary.rate') ?></dt>
    <dd><?= e(euro($tariffa)) ?></dd>
  </div>
  <div class="adv-riepilogo__riga adv-riepilogo__riga--totale">
    <dt><?= te('book.summary.total') ?></dt>
    <dd>
      <span class="adv-riepilogo__totale"><?= e(euro($totale)) ?></span>
      <?php if ($dimostrativa): ?><?= prezzoDaConfermare() ?><?php endif; ?>
    </dd>
  </div>
</dl>
<p class="adv-nota"><?= te('book.summary.total_note') ?></p>
