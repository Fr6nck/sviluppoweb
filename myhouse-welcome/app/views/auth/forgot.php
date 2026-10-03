<?php use function MHW\b; use MHW\{Support, Csrf}; $title = 'Recupera la password'; ?>
<h1>Nuova password.</h1>
<?php if ($inviata): ?>
  <p class="note note--ok" style="margin-top:20px" role="status">Se l'indirizzo corrisponde a un account, ti abbiamo scritto
    un link per scegliere una nuova password. Vale un'ora. Controlla anche la posta indesiderata.</p>
<?php else: ?>
  <p class="muted" style="margin-top:14px">Scrivi l'email con cui ti sei registrato: ti mandiamo un link.</p>
  <?php if ($err): ?><p class="note note--err" style="margin-top:20px" role="alert"><?= Support::e($err) ?></p><?php endif; ?>
  <form method="post" class="panel stack" style="margin-top:24px"><?= Csrf::field() ?>
    <div class="field" style="margin:0"><label for="email">Email</label>
      <input id="email" name="email" type="email" required autocomplete="email" autofocus></div>
    <button class="btn btn--block">Mandami il link</button>
  </form>
<?php endif; ?>
<p class="muted small" style="margin-top:20px"><a href="<?= b() ?>/accedi">Torna all'accesso</a></p>
