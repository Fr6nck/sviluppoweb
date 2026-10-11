<?php
/* «Controlla la tua email» (7A): la guida si comincia dopo la conferma. Rimanda (dopo 60 secondi,
   al massimo 5 all'ora), cambia l'indirizzo se è sbagliato, l'assistenza. */
use function MHW\b; use MHW\{Support, Csrf, Icon};
$title = 'Controlla la tua email'; ?>
<h1>Controlla la tua email.</h1>
<p class="muted" style="margin-top:14px;line-height:24px">Ti abbiamo scritto a <b style="color:var(--ink)"><?= Support::e($email) ?></b>.
  Apri il messaggio e tocca «Conferma la mia email»: ti riporta qui dentro, pronto per preparare la guida.</p>
<?php if (!empty($err)): ?><p class="note note--err" style="margin-top:20px" role="alert"><?= Support::e($err) ?></p><?php endif; ?>
<div class="panel stack" style="margin-top:24px">
  <p class="small" style="margin:0"><?= Icon::svg('info', 16) ?> Non la trovi? Guarda in <b>Spam</b> o <b>Promozioni</b> e segnala che non è spam: le prossime arriveranno nella posta in arrivo.</p>
  <form method="post" action="<?= b() ?>/verifica/invia" style="margin:0" data-attendi="<?= (int) $attendi ?>"><?= Csrf::field() ?>
    <input type="hidden" name="torna" value="conferma">
    <button class="btn btn--block" data-rimanda<?= $attendi > 0 ? ' disabled' : '' ?>>Rimanda l'email</button>
    <p class="small muted" data-attesa style="margin:8px 0 0"<?= $attendi > 0 ? '' : ' hidden' ?>>Puoi rimandarla tra <span data-secondi><?= (int) $attendi ?></span> secondi.</p>
  </form>
  <details>
    <summary class="linkbtn" style="cursor:pointer">Ho sbagliato indirizzo</summary>
    <form method="post" action="<?= b() ?>/conferma-email/cambia" class="stack" style="margin-top:12px;gap:12px"><?= Csrf::field() ?>
      <div class="field" style="margin:0"><label for="nuova-email">L'email giusta</label>
        <input id="nuova-email" name="email" type="email" required maxlength="190" autocomplete="email"></div>
      <button class="btn btn--ghost">Usa questa email e rimanda</button>
    </form>
  </details>
</div>
<?php if (Support::contatto() !== ''): ?>
  <p class="muted small" style="margin-top:20px">Non arriva niente? <a href="<?= Support::e(Support::mailto('Non arriva l\'email di conferma', "Ciao, non mi arriva l'email di conferma.\n\nEmail usata: " . $email . "\n")) ?>">Scrivici a <?= Support::e(Support::contatto()) ?></a>.</p>
<?php endif; ?>
<form method="post" action="<?= b() ?>/esci" style="margin-top:12px"><?= Csrf::field() ?><button class="linkbtn small">Esci</button></form>
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
