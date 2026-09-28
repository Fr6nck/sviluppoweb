<?php use function MHW\b; use MHW\{Support, Csrf}; $title = 'Scegli una nuova password'; ?>
<?php if (!$valido): ?>
  <h1>Link scaduto.</h1>
  <p class="muted" style="margin-top:14px">Questo link non vale più: è già stato usato, oppure è passata più di un'ora.</p>
  <p style="margin-top:20px"><a class="btn" href="<?= b() ?>/password/dimenticata">Chiedine uno nuovo</a></p>
<?php else: ?>
  <h1>Scegli una nuova password.</h1>
  <?php if ($err): ?><p class="note note--err" style="margin-top:20px" role="alert"><?= Support::e($err) ?></p><?php endif; ?>
  <form method="post" class="panel stack" style="margin-top:24px"><?= Csrf::field() ?>
    <div class="field" style="margin:0"><label for="password">Nuova password <span class="muted">— almeno 8 caratteri</span></label>
      <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password" autofocus></div>
    <div class="field" style="margin:0"><label for="password2">Ripetila</label>
      <input id="password2" name="password2" type="password" required minlength="8" autocomplete="new-password"></div>
    <button class="btn btn--block">Salva la password</button>
  </form>
<?php endif; ?>
