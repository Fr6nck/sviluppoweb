<?php use function MHW\b; use MHW\{Support, Csrf}; $title = 'Scegli una nuova password'; ?>
<?php if (!$valido): ?>
  <h1>Link scaduto.</h1>
  <p class="muted" style="margin-top:14px">Questo link non vale più: è già stato usato, oppure è passata più di un'ora.</p>
  <p style="margin-top:20px"><a class="btn" href="<?= b() ?>/password/dimenticata">Chiedine uno nuovo</a></p>
<?php else: ?>
  <h1>Scegli una nuova password.</h1>
  <?php if ($err): ?><p class="note note--err" style="margin-top:20px" role="alert"><?= Support::e($err) ?></p>
    <?php $aiutoEmail = ''; $aiutoPagina = 'Nuova password'; include __DIR__ . '/_aiuto.php'; ?><?php endif; ?>
  <form method="post" class="panel stack" style="margin-top:24px"><?= Csrf::field() ?>
    <?php $pwId = 'password'; $pwNome = 'password'; $pwEtichetta = 'Nuova password'; $pwAuto = 'new-password'; $pwNuova = true; $pwFuoco = true; include __DIR__ . '/_pw.php'; ?>
    <?php $pwId = 'password2'; $pwNome = 'password2'; $pwEtichetta = 'Ripetila'; $pwAuto = 'new-password'; include __DIR__ . '/_pw.php'; ?>
    <button class="btn btn--block">Salva la password</button>
  </form>
<?php endif; ?>
<script src="<?= MHW\av('/assets/password.js') ?>" defer></script>
