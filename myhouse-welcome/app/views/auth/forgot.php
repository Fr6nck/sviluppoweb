<?php use function MHW\b; use MHW\{Support, Csrf, LinguaSito};
$U = [LinguaSito::class, 't']; $q = LinguaSito::en() ? '?lang=en' : '';   // 9N: anche in inglese, da /en/login
$title = $U('Recupera la password', 'Recover your password'); ?>
<h1><?= $U('Nuova password.', 'New password.') ?></h1>
<?php if ($inviata): ?>
  <p class="note note--ok" style="margin-top:20px" role="status"><?= $U('Se l\'indirizzo corrisponde a un account, ti abbiamo scritto
    un link per scegliere una nuova password. Vale un\'ora. Controlla anche la posta indesiderata.', 'If the address matches an account, we have sent you a link to choose a new password. It is valid for one hour. Check your spam folder too.') ?></p>
<?php else: ?>
  <p class="muted" style="margin-top:14px"><?= $U('Scrivi l\'email del tuo account: ti mandiamo un link per sceglierne una nuova.', 'Write your account email: we will send you a link to choose a new one.') ?></p>
  <?php if ($err): ?><p class="note note--err" style="margin-top:20px" role="alert"><?= Support::e($err) ?></p>
    <?php $aiutoEmail = (string) ($_POST['email'] ?? ''); $aiutoPagina = $U('Password dimenticata', 'Forgotten password'); include __DIR__ . '/_aiuto.php'; ?><?php endif; ?>
  <form method="post" class="panel stack" style="margin-top:24px"><?= Csrf::field() ?>
    <div class="field" style="margin:0"><label for="email">Email</label>
      <input id="email" name="email" type="email" required autocomplete="email" autofocus></div>
    <button class="btn btn--block"><?= $U('Mandami il link', 'Send me the link') ?></button>
  </form>
<?php endif; ?>
<p class="muted small" style="margin-top:20px"><a href="<?= b() . LinguaSito::percorso('/accedi') ?>"><?= $U('Torna all\'accesso', 'Back to log in') ?></a></p>
