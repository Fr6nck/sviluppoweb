<?php use MHW\{Support, Csrf, Demo}; $title = 'Installazione'; ?>
<h1>Mettiamola in piedi.</h1>
<p class="muted" style="margin-top:14px">Questo passaggio crea il database e il primo account di
  amministrazione. Si fa una volta sola.</p>
<?php if ($err): ?><p class="note note--err" style="margin-top:20px"><?= Support::e($err) ?></p><?php endif; ?>
<form method="post" class="panel" style="margin-top:24px">
  <?= Csrf::field() ?>
  <div class="field"><label for="email">La vostra email</label>
    <input id="email" name="email" type="email" required autocomplete="email"></div>
  <div class="field"><label for="password">Una password (almeno 8 caratteri)</label>
    <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password"></div>

  <div class="note note--quiet" style="margin-bottom:18px;display:block">
    <label class="check" style="margin:0">
      <input type="checkbox" name="esempi" value="1" checked>
      <span>Riempi con tre clienti di esempio</span>
    </label>
    <p class="tiny muted" style="margin-top:8px">Tre host finti con guide vere, foto e statistiche, per
      vedere il prodotto pieno invece che vuoto. Entrano tutti con la password
      <strong><?= Support::e(Demo::PASSWORD) ?></strong>. Si cancellano dalla pagina Clienti:
      <strong>fatelo prima di aprire il sito al pubblico</strong>.</p>
  </div>

  <button class="btn btn--block">Installa</button>
</form>
