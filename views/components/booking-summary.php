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

<?php
/* La tassa di soggiorno non entra nel totale perché non si paga al sito: si
   paga a Daniele al check-in. Ma va detta prima, e con la cifra, perché
   trovarsela all'arrivo è la sorpresa che rovina un soggiorno per tre euro. */
$tassa = tassaSoggiorno((int) $ospiti, (int) $notti);
$cfg   = site('stay.city_tax');
?>
<?php if ($tassa !== null): ?>
  <dl class="adv-riepilogo adv-riepilogo--tassa">
    <div class="adv-riepilogo__riga">
      <dt><?= te('book.summary.city_tax') ?></dt>
      <dd><?= te('book.summary.city_tax_upto', ['amount' => euro($tassa['amount'])]) ?></dd>
    </div>
  </dl>
  <p class="adv-nota">
    <?= te('book.summary.city_tax_note', [
        'amount' => euro((float) $cfg['amount']),
        'nights' => (int) $cfg['max_nights'],
        'age'    => (int) $cfg['exempt_under'],
    ]) ?>
  </p>
<?php endif; ?>

<p class="adv-nota"><?= te('book.summary.total_note') ?></p>
