<?php use function MHW\b; use MHW\{Support, Csrf, LinguaSito};
/* L'accesso. 9N: anche in inglese (/en/login): $U per i testi fissi, i link nella stessa lingua. */
$U = [LinguaSito::class, 't']; $q = LinguaSito::en() ? '?lang=en' : '';
$title = $U('Accedi', 'Log in') . ' | MyHouse Welcome';   // 9T · 5.9: titolo e descrizione anche qui (la pagina resta noindex)
$descrizione = $U('Accedi a MyHouse Welcome per preparare e aggiornare la guida digitale della tua struttura.', 'Log in to MyHouse Welcome to build and update the digital guide for your property.'); ?>
<h1><?= $U('Rieccoti.', 'Welcome back.') ?></h1>
<?php if ($err): ?><p class="note note--err" style="margin-top:20px" role="alert"><?= Support::e($err) ?></p>
  <?php $aiutoEmail = $email; $aiutoPagina = $U('Accedi', 'Log in'); include __DIR__ . '/_aiuto.php'; ?><?php endif; ?>
<form method="post" class="panel stack" style="margin-top:24px"><?= Csrf::field() ?>
  <div class="field" style="margin:0"><label for="email">Email</label>
    <input id="email" name="email" type="email" required autocomplete="email" value="<?= Support::e($email) ?>" autofocus></div>
  <?php $pwId = 'password'; $pwNome = 'password'; $pwEtichetta = 'Password'; $pwAuto = 'current-password'; include __DIR__ . '/_pw.php'; ?>
  <button class="btn btn--block"><?= $U('Accedi', 'Log in') ?></button>
</form>
<p class="muted small" style="margin-top:20px"><a href="<?= b() ?>/password/dimenticata<?= $q ?>"><?= $U('Hai dimenticato la password?', 'Forgot your password?') ?></a></p>
<p class="muted small" style="margin-top:8px"><?= $U('Non hai ancora un account?', 'No account yet?') ?> <a href="<?= b() . LinguaSito::percorso('/registrati') ?>"><?= $U('Crea la tua guida gratis', 'Create your guide for free') ?></a>.</p>
<script src="<?= MHW\av('/assets/password.js') ?>" defer></script>
