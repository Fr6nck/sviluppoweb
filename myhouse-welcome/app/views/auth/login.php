<?php use MHW\{Support, Csrf}; $title = 'Accesso'; ?>
<h1>Bentornati.</h1>
<?php if ($err): ?><p class="note note--err" style="margin-top:16px"><?= Support::e($err) ?></p><?php endif; ?>
<form method="post" class="card" style="margin-top:20px"><?= Csrf::field() ?>
  <div class="field"><label for="email">Email</label>
    <input id="email" name="email" type="email" required autocomplete="email"></div>
  <div class="field"><label for="password">Password</label>
    <input id="password" name="password" type="password" required autocomplete="current-password"></div>
  <button class="btn btn--block">Entra</button>
</form>
<p class="small muted" style="margin-top:16px">Non avete un account? <a href="/registrati">Createne uno</a>.</p>
