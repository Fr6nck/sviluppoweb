<?php
/* Il campo password con «Mostra» (7A): registrazione, accesso, nuova password, account.
   $pwId, $pwNome, $pwEtichetta, $pwAuto (autocomplete); $pwNuova = true aggiunge minimo e barra di robustezza.
   Il comportamento è in assets/password.js: senza JavaScript resta un campo password normale. */
use MHW\{Support, Auth};
$pwNuova = $pwNuova ?? false; $pwDescr = $pwNuova ? ' aria-describedby="' . Support::e($pwId) . '-forza"' : ''; ?>
<div class="field" style="margin:0"><label for="<?= Support::e($pwId) ?>"><?= Support::e($pwEtichetta) ?><?php if ($pwNuova): ?> <span class="muted">— almeno <?= Auth::PASSWORD_MIN ?> caratteri</span><?php endif; ?></label>
  <div class="pw">
    <input id="<?= Support::e($pwId) ?>" name="<?= Support::e($pwNome) ?>" type="password" required<?= $pwNuova ? ' minlength="' . Auth::PASSWORD_MIN . '" maxlength="72"' : '' ?>
           autocomplete="<?= Support::e($pwAuto) ?>"<?= $pwDescr ?><?= !empty($pwFuoco) ? ' autofocus' : '' ?>>
    <button type="button" class="pw__mostra" data-mostra-pw aria-controls="<?= Support::e($pwId) ?>" aria-pressed="false" hidden>Mostra</button>
  </div>
  <?php if ($pwNuova): ?><div class="forza" data-forza="<?= Support::e($pwId) ?>" hidden><i><b></b></i><span id="<?= Support::e($pwId) ?>-forza" class="small muted" aria-live="polite"></span></div><?php endif; ?>
</div>
<?php $pwFuoco = false; $pwNuova = false;
