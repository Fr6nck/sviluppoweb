<?php use MHW\{Support, Csrf}; $title = 'Installazione'; ?>
<h1>Mettiamola in piedi.</h1>
<p class="muted" style="margin-top:12px">Questo passaggio crea il database e il primo account di amministrazione. Si fa una volta sola.</p>
<?php if ($err): ?><p class="note note--err"><?= Support::e($err) ?></p><?php endif; ?>
<form method="post" class="card" style="margin-top:20px">
  <?= Csrf::field() ?>
  <div class="field"><label for="email">La vostra email</label>
    <input id="email" name="email" type="email" required autocomplete="email"></div>
  <div class="field"><label for="password">Una password (almeno 8 caratteri)</label>
    <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password"></div>
  <button class="btn btn--block">Installa</button>
</form>
