<?php use function MHW\t, MHW\tf;
/* Portfolio: conferma del nuovo numero di strutture. Se si scende sotto le
   strutture che esistono, si scelgono qui quelle da archiviare. */
use function MHW\b;
use MHW\{Support, Csrf};
$title = t('Numero di strutture');
$aumento = $n > $attuale; ?>
<div class="stack stack--lg" style="max-width:680px">
  <div class="stack stack--sm">
    <h1><?= $aumento ? t('Aggiungi strutture.') : t('Riduci le strutture.') ?></h1>
    <p class="lead"><?= Support::e(tf('Da {da} a {a} strutture:', ['{da}' => (int) $attuale, '{a}' => (int) $n])) ?>
      <?= Support::e(Support::money($vecchio, $pv['currency'])) ?> → <b><?= Support::e(Support::money($nuovo, $pv['currency'])) ?></b> <?= t('+ IVA / anno.') ?></p>
    <p class="small muted"><?= $aumento
      ? t('Stripe ti addebita subito la differenza per i mesi che restano fino al rinnovo. Le nuove strutture si attivano appena il pagamento è confermato.')
      : t('La differenza per i mesi che restano ti viene accreditata sulla prossima fattura.') ?></p>
  </div>
  <form method="post" action="<?= b() ?>/account/strutture" class="stack"><?= Csrf::field() ?>
    <input type="hidden" name="strutture" value="<?= (int) $n ?>"><input type="hidden" name="conferma" value="1">
    <?php if ($daTogliere > 0): ?>
      <fieldset class="fieldset">
        <legend><?= Support::e($daTogliere === 1 ? t('Scegli 1 struttura da archiviare') : tf('Scegli {n} strutture da archiviare', ['{n}' => (int) $daTogliere])) ?></legend>
        <p class="help"><?= t('Una struttura archiviata va offline, ma contenuti, traduzioni e QR restano: la riattivi quando vuoi.') ?></p>
        <?php foreach ($attive as $p): ?>
          <label class="scelta"><input type="checkbox" name="archivia[]" value="<?= (int) $p['id'] ?>">
            <span><?= Support::e($p['name']) ?><?= $p['city'] ? ' <span class="small muted">· ' . Support::e($p['city']) . '</span>' : '' ?>
              <?= $p['status'] === 'published' ? '<span class="badge badge--pine">' . t('Online') . '</span>' : '' ?></span></label>
        <?php endforeach; ?>
      </fieldset>
    <?php endif; ?>
    <div class="actions">
      <button class="btn"><?= $aumento ? t('Conferma e paga la differenza') : t('Conferma la riduzione') ?></button>
      <a class="btn btn--quiet" href="<?= b() ?>/account"><?= t('Annulla') ?></a>
    </div>
  </form>
</div>
