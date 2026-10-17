<?php /* 9E · Dopo una password provvisoria impostata dall'assistenza: si sceglie la password nuova, poi si entra. */
use function MHW\b; use MHW\{Support, Csrf}; $title = 'Scegli la tua password'; ?>
<h1>Scegli la tua password.</h1>
<p class="muted" style="margin-top:14px;line-height:24px">Sei entrato con una password provvisoria: vale 48 ore. Scegline una tua adesso, poi vai alle tue guide.</p>
<?php if ($err): ?><p class="note note--err" style="margin-top:20px" role="alert"><?= Support::e($err) ?></p><?php endif; ?>
<form method="post" class="panel stack" style="margin-top:24px"><?= Csrf::field() ?>
  <input type="text" name="username" value="<?= Support::e($user['email']) ?>" autocomplete="username" hidden>
  <?php $pwId = 'password'; $pwNome = 'password'; $pwEtichetta = 'Password nuova'; $pwAuto = 'new-password'; $pwNuova = true; $pwFuoco = true; include __DIR__ . '/_pw.php'; ?>
  <?php $pwId = 'password2'; $pwNome = 'password2'; $pwEtichetta = 'Ripetila'; $pwAuto = 'new-password'; include __DIR__ . '/_pw.php'; ?>
  <button class="btn btn--block">Salva e vai alle mie guide</button>
</form>
<form method="post" action="<?= b() ?>/esci" style="margin-top:16px"><?= Csrf::field() ?><button class="linkbtn">Esci</button></form>
<script src="<?= MHW\av('/assets/password.js') ?>" defer></script>
