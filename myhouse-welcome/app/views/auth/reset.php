<?php use function MHW\b; use MHW\{Support, Csrf, LinguaSito};
$U = [LinguaSito::class, 't'];   // 9N: nella lingua del pannello dell'account
$title = $U('Scegli una nuova password', 'Choose a new password'); ?>
<?php if (!$valido): ?>
  <h1><?= $U('Link scaduto.', 'Link expired.') ?></h1>
  <p class="muted" style="margin-top:14px"><?= $U('Questo link non vale più: è già stato usato, oppure è passata più di un\'ora.', 'This link is no longer valid: it has already been used, or more than an hour has passed.') ?></p>
  <p style="margin-top:20px"><a class="btn" href="<?= b() ?>/password/dimenticata<?= LinguaSito::en() ? '?lang=en' : '' ?>"><?= $U('Chiedine uno nuovo', 'Ask for a new one') ?></a></p>
<?php else: ?>
  <h1><?= $U('Scegli una nuova password.', 'Choose a new password.') ?></h1>
  <?php if ($err): ?><p class="note note--err" style="margin-top:20px" role="alert"><?= Support::e($err) ?></p>
    <?php $aiutoEmail = ''; $aiutoPagina = $U('Nuova password', 'New password'); include __DIR__ . '/_aiuto.php'; ?><?php endif; ?>
  <form method="post" class="panel stack" style="margin-top:24px"><?= Csrf::field() ?>
    <?php $pwId = 'password'; $pwNome = 'password'; $pwEtichetta = $U('Nuova password', 'New password'); $pwAuto = 'new-password'; $pwNuova = true; $pwFuoco = true; include __DIR__ . '/_pw.php'; ?>
    <?php $pwId = 'password2'; $pwNome = 'password2'; $pwEtichetta = $U('Ripetila', 'Repeat it'); $pwAuto = 'new-password'; include __DIR__ . '/_pw.php'; ?>
    <button class="btn btn--block"><?= $U('Salva la password', 'Save the password') ?></button>
  </form>
<?php endif; ?>
<script src="<?= MHW\av('/assets/password.js') ?>" defer></script>
