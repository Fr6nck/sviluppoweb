<?php use function MHW\b; use MHW\{Support, Csrf}; $title = 'Registrazione'; ?>
<h1>Cominciamo.</h1>
<?php if ($err): ?><p class="note note--err" style="margin-top:16px"><?= Support::e($err) ?></p><?php endif; ?>
<form method="post" class="card" style="margin-top:20px"><?= Csrf::field() ?>
  <input type="hidden" name="piano" value="<?= (int) $piano ?>">
  <div class="field"><label for="name">Come vi chiamate</label>
    <input id="name" name="name" type="text" required autocomplete="name"></div>
  <div class="field"><label for="email">Email</label>
    <input id="email" name="email" type="email" required autocomplete="email"></div>
  <div class="field"><label for="password">Password (almeno 8 caratteri)</label>
    <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password"></div>
  <button class="btn btn--block">Crea l'account</button>
</form>
<p class="small muted" style="margin-top:16px">Avete già un account? <a href="<?= b() ?>/accedi">Accedete</a>.</p>
