<?php use function MHW\b; use MHW\{Support, Csrf}; $title = 'Accedi'; ?>
<h1>Bentornati.</h1>
<?php if ($err): ?><p class="note note--err" style="margin-top:20px"><?= Support::e($err) ?></p><?php endif; ?>
<form method="post" class="panel" style="margin-top:24px"><?= Csrf::field() ?>
  <div class="field"><label for="email">Email</label>
    <input id="email" name="email" type="email" required autocomplete="email" autofocus></div>
  <div class="field"><label for="password">Password</label>
    <input id="password" name="password" type="password" required autocomplete="current-password"></div>
  <button class="btn btn--block">Entra</button>
</form>
<p class="muted small" style="margin-top:20px">Non avete ancora un account?
  <a href="<?= b() ?>/registrati">Createne uno</a>.</p>
