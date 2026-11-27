<?php
/* «Controlla la tua email» (7A): la guida si comincia dopo la conferma. Rimanda (dopo 60 secondi,
   al massimo 5 all'ora), cambia l'indirizzo se è sbagliato, l'assistenza. */
use function MHW\b; use MHW\{Support, Csrf, Icon, LinguaSito};
$U = [LinguaSito::class, 't'];   // 9N: nella lingua del pannello (inglese per chi si è registrato in inglese)
$title = $U('Controlla la tua email', 'Check your email'); ?>
<h1><?= $U('Controlla la tua email.', 'Check your email.') ?></h1>
<p class="muted" style="margin-top:14px;line-height:24px"><?= $U('Ti abbiamo scritto a', 'We have written to') ?> <b style="color:var(--ink)"><?= Support::e($email) ?></b>.
  <?= $U('Apri il messaggio e tocca «Conferma la mia email»: ti riporta qui dentro, pronto per preparare la guida.', 'Open the message and tap "Confirm my email": it brings you back here, ready to prepare your guide.') ?></p>
<?php if (!empty($err)): ?><p class="note note--err" style="margin-top:20px" role="alert"><?= Support::e($err) ?></p><?php endif; ?>
<div class="panel stack" style="margin-top:24px">
  <p class="small" style="margin:0"><?= Icon::svg('info', 16) ?> <?= $U('Non la trovi? Guarda in <b>Spam</b> o <b>Promozioni</b> e segnala che non è spam: le prossime arriveranno nella posta in arrivo.', 'Can\'t find it? Look in <b>Spam</b> or <b>Promotions</b> and mark it as not spam: the next ones will arrive in your inbox.') ?></p>
  <form method="post" action="<?= b() ?>/verifica/invia" style="margin:0" data-attendi="<?= (int) $attendi ?>"><?= Csrf::field() ?>
    <input type="hidden" name="torna" value="conferma">
    <button class="btn btn--block" data-rimanda<?= $attendi > 0 ? ' disabled' : '' ?>><?= $U('Rimanda l\'email', 'Send the email again') ?></button>
    <p class="small muted" data-attesa style="margin:8px 0 0"<?= $attendi > 0 ? '' : ' hidden' ?>><?= $U('Puoi rimandarla tra', 'You can send it again in') ?> <span data-secondi><?= (int) $attendi ?></span> <?= $U('secondi', 'seconds') ?>.</p>
  </form>
  <details>
    <summary class="linkbtn" style="cursor:pointer"><?= $U('Ho sbagliato indirizzo', 'I typed the wrong address') ?></summary>
    <form method="post" action="<?= b() ?>/conferma-email/cambia" class="stack" style="margin-top:12px;gap:12px"><?= Csrf::field() ?>
      <div class="field" style="margin:0"><label for="nuova-email"><?= $U('L\'email giusta', 'The right email') ?></label>
        <input id="nuova-email" name="email" type="email" required maxlength="190" autocomplete="email"></div>
      <button class="btn btn--ghost"><?= $U('Usa questa email e rimanda', 'Use this email and resend') ?></button>
    </form>
  </details>
</div>
<?php if (Support::contatto() !== ''): ?>
  <p class="muted small" style="margin-top:20px"><?= $U('Non arriva niente?', 'Nothing arriving?') ?> <a href="<?= Support::e(LinguaSito::en() ? Support::mailto('The confirmation email does not arrive', "Hello, the confirmation email does not reach me.\n\nEmail used: " . $email . "\n") : Support::mailto('Non arriva l\'email di conferma', "Ciao, non mi arriva l'email di conferma.\n\nEmail usata: " . $email . "\n")) ?>"><?= $U('Scrivici a', 'Write to us at') ?> <?= Support::e(Support::contatto()) ?></a>.</p>
<?php endif; ?>
<form method="post" action="<?= b() ?>/esci" style="margin-top:12px"><?= Csrf::field() ?><button class="linkbtn small"><?= $U('Esci', 'Log out') ?></button></form>
<script>
(function () {
  var f = document.querySelector('[data-attendi]'); if (!f) return;
  var resto = +f.getAttribute('data-attendi'), bt = f.querySelector('[data-rimanda]'), riga = f.querySelector('[data-attesa]'), sec = f.querySelector('[data-secondi]');
  if (resto <= 0) return;
  var t = setInterval(function () {
    resto--; sec.textContent = String(resto);
    if (resto <= 0) { clearInterval(t); bt.disabled = false; riga.hidden = true; }
  }, 1000);
})();
</script>
