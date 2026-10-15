<?php
/* «In che lingua vuoi leggerla?» (8C): alla prima apertura della guida, se ci sono più lingue e
   nell'indirizzo non c'è ?l=. Un <dialog> chiuso: lo apre guida.js solo se per questa guida non
   c'è già una scelta in localStorage (niente cookie: le guide non ne usano). Senza JavaScript non
   compare e vale la lingua del telefono, come prima. Nell'anteprima del pannello non c'è.
   Le lingue sono quelle della guida pubblicata (attivate dall'host e consentite dal piano), col
   nome nativo; quella del telefono, se c'è, in cima ed evidenziata.
   Riceve $snap, $loc, $base, $coda (il resto dell'indirizzo: '' o '/benvenuto'). */
use MHW\{Support, Config, I18n};
if (!empty($anteprima) || count($snap['locales']) < 2 || isset($_GET['l'])) return;
$nomiSL = Config::get('locales');
// La lingua del telefono: la prima di Accept-Language che la guida ha; il titolo nella prima in assoluto.
$telefono = ''; $titoloSL = '';
foreach (explode(',', (string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')) as $parte) {
    $l = strtolower(substr(trim($parte), 0, 2));
    if ($l === '') continue;
    if ($titoloSL === '') $titoloSL = $l;
    if ($telefono === '' && in_array($l, $snap['locales'], true)) $telefono = $l;
}
$titoloSL = in_array($titoloSL, I18n::LOCALES, true) ? $titoloSL : $loc;
$lingueSL = $snap['locales'];
if ($telefono !== '') $lingueSL = array_values(array_unique(array_merge([$telefono], $lingueSL))); ?>
<dialog class="scegli-lingua" id="scegli-lingua" data-guida="<?= Support::e((string) $snap['property']['slug']) ?>" aria-labelledby="scegli-lingua-titolo" lang="<?= Support::e($titoloSL) ?>">
  <div class="scegli-lingua__dentro">
    <p class="scegli-lingua__casa"><?= Support::e((string) $snap['property']['name']) ?></p>
    <h2 class="scegli-lingua__titolo" id="scegli-lingua-titolo"><?= Support::e(I18n::t($titoloSL, 'choose_language')) ?></h2>
    <div class="scegli-lingua__elenco">
      <?php foreach ($lingueSL as $l): ?>
        <a class="scegli-lingua__lingua<?= $l === $telefono ? ' scegli-lingua__lingua--telefono' : '' ?>" href="<?= Support::e($base . ($coda ?? '')) ?>?l=<?= Support::e($l) ?>"
           data-lingua="<?= Support::e($l) ?>" hreflang="<?= Support::e($l) ?>" lang="<?= Support::e($l) ?>"><?= Support::e($nomiSL[$l] ?? strtoupper($l)) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
</dialog>
