<?php
/* La scelta delle lingue. Quelle fuori dal piano si vedono, spente, con il
   piano che le comprende: niente sorprese dopo.
   Riceve: $prop, $lingueAttive, $consentite, $tutte, $dopoPasso ('' fuori dalla procedura). */
use function MHW\b;
use MHW\{Support, Csrf};
$def = $prop['default_locale']; $dopoPasso = $dopoPasso ?? ''; ?>
<form method="post" action="<?= b() ?>/pannello/<?= (int) $prop['id'] ?>/lingue" class="stack"<?= $dopoPasso !== '' ? ' data-autosave' : '' ?>><?= Csrf::field() ?>
  <fieldset class="fieldset">
    <legend>Lingue della guida</legend>
    <p class="help">La guida si apre nella lingua del telefono dell'ospite, se è tra queste. Le traduzioni le scrivi tu: nessuna traduzione automatica.</p>
    <div class="scelte">
    <?php foreach ($tutte as $code => $nome): $ok = in_array($code, $consentite, true); $on = in_array($code, $lingueAttive, true); ?>
      <label class="scelta<?= $code === $def ? ' scelta--fissa' : '' ?>">
        <?php if ($code === $def): ?>
          <input type="checkbox" checked disabled><input type="hidden" name="locali[]" value="<?= Support::e($code) ?>">
          <span class="scelta__testo"><?= Support::e($nome) ?> <span class="small muted">lingua principale</span></span>
        <?php else: ?>
          <input type="checkbox" name="locali[]" value="<?= Support::e($code) ?>" <?= $on && $ok ? 'checked' : '' ?> <?= $ok ? '' : 'disabled' ?>>
          <span class="scelta__testo"><?= Support::e($nome) ?><?php if (!$ok): ?> <span class="small muted">con il piano Plus</span><?php endif; ?>
            <?php if ($on && !$ok): ?> <span class="badge badge--alert">da togliere</span><?php endif; ?></span>
        <?php endif; ?>
      </label>
    <?php endforeach; ?>
    </div>
  </fieldset>
  <?php if (count($consentite) < count($tutte)): ?><p class="small"><a href="<?= b() ?>/piano">Scopri Plus: la guida in 5 lingue (italiano, inglese, francese, tedesco, spagnolo)</a></p><?php endif; ?>
  <?php if ($dopoPasso !== ''):
        $barraAvanti = '<button class="btn btn--go" name="dopo" value="' . Support::e($dopoPasso) . '">Salva e continua <span class="go">' . MHW\Icon::svg('arrow', 18, 2) . '</span></button>';
        include __DIR__ . '/_barra_passo.php';
      else: ?>
  <div class="actions"><button class="btn">Salva le lingue</button></div>
  <?php endif; ?>
</form>
