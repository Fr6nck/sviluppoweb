<?php use MHW\{Support, Csrf, Demo}; $title = 'Installazione'; ?>
<h1>Mettiamola in piedi.</h1>
<p class="muted" style="margin-top:14px">Questo passaggio crea il database e il primo account di amministrazione. Si fa una volta sola.</p>
<?php if ($err): ?><p class="note note--err" style="margin-top:20px" role="alert"><?= Support::e($err) ?></p><?php endif; ?>
<form method="post" class="panel stack" style="margin-top:24px"><?= Csrf::field() ?>
  <div class="field" style="margin:0"><label for="email">La tua email</label>
    <input id="email" name="email" type="email" required autocomplete="email"></div>
  <div class="field" style="margin:0"><label for="password">Una password <span class="muted">— almeno 8 caratteri</span></label>
    <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password"></div>
  <div class="note note--quiet">
    <label class="check" style="margin:0"><input type="checkbox" name="esempi" value="1" checked> <span>Crea anche la demo e tre clienti di esempio</span></label>
    <p class="tiny muted" style="margin-top:8px">Casa Lucia diventa la demo della landing. I clienti di esempio entrano con la password
      <strong><?= Support::e(Demo::PASSWORD) ?></strong>: <strong>toglili prima di aprire al pubblico</strong> (Amministrazione → Clienti).</p>
  </div>
  <button class="btn btn--block">Installa</button>
</form>
