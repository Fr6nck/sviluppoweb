<?php
/* Portfolio: conferma del nuovo numero di strutture. Se si scende sotto le
   strutture che esistono, si scelgono qui quelle da archiviare. */
use function MHW\b;
use MHW\{Support, Csrf};
$title = 'Numero di strutture';
$aumento = $n > $attuale; ?>
<div class="stack stack--lg" style="max-width:680px">
  <div class="stack stack--sm">
    <h1><?= $aumento ? 'Aggiungi strutture.' : 'Riduci le strutture.' ?></h1>
    <p class="lead">Da <?= (int) $attuale ?> a <?= (int) $n ?> strutture:
      <?= Support::e(Support::money($vecchio, $pv['currency'])) ?> → <b><?= Support::e(Support::money($nuovo, $pv['currency'])) ?></b> + IVA / anno.</p>
    <p class="small muted"><?= $aumento
      ? 'Stripe ti addebita subito la differenza per i mesi che restano fino al rinnovo. Le nuove strutture si attivano appena il pagamento è confermato.'
      : 'La differenza per i mesi che restano ti viene accreditata sulla prossima fattura.' ?></p>
  </div>
  <form method="post" action="<?= b() ?>/account/strutture" class="stack"><?= Csrf::field() ?>
    <input type="hidden" name="strutture" value="<?= (int) $n ?>"><input type="hidden" name="conferma" value="1">
    <?php if ($daTogliere > 0): ?>
      <fieldset class="fieldset">
        <legend>Scegli <?= (int) $daTogliere ?> struttur<?= $daTogliere === 1 ? 'a' : 'e' ?> da archiviare</legend>
        <p class="help">Una struttura archiviata va offline, ma contenuti, traduzioni e QR restano: la riattivi quando vuoi.</p>
        <?php foreach ($attive as $p): ?>
          <label class="scelta"><input type="checkbox" name="archivia[]" value="<?= (int) $p['id'] ?>">
            <span><?= Support::e($p['name']) ?><?= $p['city'] ? ' <span class="small muted">· ' . Support::e($p['city']) . '</span>' : '' ?>
              <?= $p['status'] === 'published' ? '<span class="badge badge--pine">Pubblicata</span>' : '' ?></span></label>
        <?php endforeach; ?>
      </fieldset>
    <?php endif; ?>
    <div class="actions">
      <button class="btn"><?= $aumento ? 'Conferma e paga la differenza' : 'Conferma la riduzione' ?></button>
      <a class="btn btn--quiet" href="<?= b() ?>/account">Annulla</a>
    </div>
  </form>
</div>
