<?php use function MHW\b; use MHW\{Support, Csrf}; $title = 'Accedi'; ?>
<h1>Rieccoti.</h1>
<?php if ($err): ?><p class="note note--err" style="margin-top:20px" role="alert"><?= Support::e($err) ?></p><?php endif; ?>
<form method="post" class="panel stack" style="margin-top:24px"><?= Csrf::field() ?>
  <div class="field" style="margin:0"><label for="email">Email</label>
    <input id="email" name="email" type="email" required autocomplete="email" value="<?= Support::e($email) ?>" autofocus></div>
  <div class="field" style="margin:0"><label for="password">Password</label>
    <input id="password" name="password" type="password" required autocomplete="current-password"></div>
  <button class="btn btn--block">Accedi</button>
</form>
<p class="muted small" style="margin-top:20px"><a href="<?= b() ?>/password/dimenticata">Hai dimenticato la password?</a></p>
<p class="muted small" style="margin-top:8px">Non hai ancora un account? <a href="<?= b() ?>/registrati">Crea la tua guida gratis</a>.</p>
