<?php
/* La scelta delle lingue. Quelle fuori dal piano si vedono, spente, con il
   piano che le comprende: niente sorprese dopo.
   Riceve: $prop, $lingueAttive, $consentite, $tutte, $dopoPasso ('' fuori dalla procedura). */
use function MHW\b;
use MHW\{Support, Csrf};
$def = $prop['default_locale']; $dopoPasso = $dopoPasso ?? ''; ?>
<form method="post" action="<?= b() ?>/pannello/<?= (int) $prop['id'] ?>/lingue" class="stack"><?= Csrf::field() ?>
  <fieldset class="fieldset">
    <legend>Lingue della guida</legend>
    <p class="help">La guida si apre nella lingua del telefono dell'ospite, se è tra queste. Le traduzioni le scrivi tu: nessuna traduzione automatica.</p>
    <?php foreach ($tutte as $code => $nome): $ok = in_array($code, $consentite, true); $on = in_array($code, $lingueAttive, true); ?>
      <label class="check" style="<?= $ok ? '' : 'opacity:.55' ?>">
        <?php if ($code === $def): ?>
          <input type="checkbox" checked disabled><input type="hidden" name="locali[]" value="<?= Support::e($code) ?>">
          <span><?= Support::e($nome) ?> <span class="small muted">· lingua principale</span></span>
        <?php else: ?>
          <input type="checkbox" name="locali[]" value="<?= Support::e($code) ?>" <?= $on && $ok ? 'checked' : '' ?> <?= $ok ? '' : 'disabled' ?>>
          <span><?= Support::e($nome) ?><?php if (!$ok): ?> <span class="small muted">· compresa nel piano Plus</span><?php endif; ?>
            <?php if ($on && !$ok): ?> <span class="badge badge--alert">da togliere</span><?php endif; ?></span>
        <?php endif; ?>
      </label>
    <?php endforeach; ?>
  </fieldset>
  <div class="actions">
    <?php if ($dopoPasso !== ''): ?>
      <button class="btn btn--go" name="dopo" value="<?= Support::e($dopoPasso) ?>">Salva e continua <span class="go"><?= MHW\Icon::svg('arrow', 18, 2) ?></span></button>
    <?php else: ?>
      <button class="btn">Salva le lingue</button>
    <?php endif; ?>
    <?php if (count($consentite) < count($tutte)): ?><a class="small" href="<?= b() ?>/piano">Con Plus: italiano, inglese, francese, tedesco e spagnolo</a><?php endif; ?>
  </div>
</form>
