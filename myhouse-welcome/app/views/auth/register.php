<?php use function MHW\b; use MHW\{Support, Csrf}; $title = 'Create la vostra guida'; ?>
<h1>Cominciamo.</h1>
<p class="muted" style="margin-top:14px">Un account, poi sei domande. Si paga solo quando decidete
  di pubblicare.</p>
<?php if ($err): ?><p class="note note--err" style="margin-top:20px"><?= Support::e($err) ?></p><?php endif; ?>
<form method="post" class="panel" style="margin-top:24px"><?= Csrf::field() ?>
  <input type="hidden" name="piano" value="<?= (int) $piano ?>">
  <div class="field"><label for="name">Come vi chiamate</label>
    <input id="name" name="name" type="text" required autocomplete="name" autofocus></div>
  <div class="field"><label for="email">Email</label>
    <input id="email" name="email" type="email" required autocomplete="email"></div>
  <div class="field"><label for="password">Password <span class="muted">— almeno 8 caratteri</span></label>
    <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password"></div>
  <button class="btn btn--block">Create l'account</button>
</form>
<p class="muted small" style="margin-top:20px">Ne avete già uno? <a href="<?= b() ?>/accedi">Accedete</a>.</p>
