<?php use function MHW\b; use MHW\{Support, Csrf, Plans, Icon}; $title = 'Crea il tuo account';
$pianoScelto = $pianoScelto ?? null; $quantita = $quantita ?? null; ?>
<?php if ($pianoScelto): /* Il piano scelto sulla landing resta in vista: si sa cosa si sta attivando. */ ?>
  <p class="chip-piano">
    <span class="chip-piano__testo"><?= Icon::svg('check', 15, 2.2) ?><span>Piano <b><?= Support::e($pianoScelto['name']) ?></b><?= Plans::perProperty($pianoScelto) ? ' · ' . (int) $quantita . ' strutture' : '' ?>
      · <?= Support::e(Support::money(Plans::price($pianoScelto, (int) $quantita), $pianoScelto['currency'])) ?> + IVA/anno</span></span>
    <a href="<?= b() ?>/#piani">Cambia</a>
  </p>
<?php endif; ?>
<h1>Cominciamo.</h1>
<p class="muted" style="margin-top:14px;line-height:24px">Configuri la guida gratis. Paghi solo quando la pubblichi.</p>
<?php if ($err): ?><p class="note note--err" style="margin-top:20px" role="alert"><?= Support::e($err) ?></p><?php endif; ?>
<form method="post" class="panel stack" style="margin-top:24px" novalidate><?= Csrf::field() ?>
  <input type="hidden" name="piano" value="<?= (int) $piano ?>">
  <?php if (!empty($strutture)): ?><input type="hidden" name="strutture" value="<?= (int) $strutture ?>"><?php endif; ?>
  <div class="field" style="margin:0"><label for="name">Nome e cognome</label>
    <input id="name" name="name" type="text" required maxlength="120" autocomplete="name" value="<?= Support::e($vecchi['name']) ?>" autofocus></div>
  <div class="field" style="margin:0"><label for="email">Email</label>
    <input id="email" name="email" type="email" required maxlength="190" autocomplete="email" value="<?= Support::e($vecchi['email']) ?>"></div>
  <div class="field" style="margin:0"><label for="password">Password <span class="muted">— almeno 8 caratteri</span></label>
    <div class="pw">
      <input id="password" name="password" type="password" required minlength="8" maxlength="72" autocomplete="new-password" aria-describedby="pw-forza">
      <button type="button" class="pw__mostra" data-mostra-pw aria-controls="password" aria-pressed="false" hidden>Mostra</button>
    </div>
    <div class="forza" data-forza hidden><i><b></b></i><span id="pw-forza" class="small muted" aria-live="polite"></span></div>
  </div>
  <label class="check" style="align-items:flex-start"><input type="checkbox" name="termini" value="1" required style="margin-top:1px">
    <span>Accetto i <a href="<?= b() ?>/termini" target="_blank" rel="noopener">Termini e condizioni</a></span></label>
  <button class="btn btn--block btn--go">Crea l'account e inizia <span class="go"><?= Icon::svg('arrow', 18, 2) ?></span></button>
  <?php /* Formulazione da far verificare al consulente privacy. */ ?>
  <p class="small muted" style="margin-top:-4px">Creando l'account dichiari di aver letto l'<a href="<?= b() ?>/privacy" target="_blank" rel="noopener">informativa privacy</a>.</p>
</form>
<p class="muted small" style="margin-top:20px">Hai già un account? <a href="<?= b() ?>/accedi">Accedi</a>.</p>
<script>
/* Mostra la password e una stima della robustezza. Niente regole imposte oltre agli
   8 caratteri: la barra consiglia, non blocca. Senza JavaScript il modulo va lo stesso. */
(function () {
  var pw = document.getElementById('password'), bt = document.querySelector('[data-mostra-pw]'), fz = document.querySelector('[data-forza]');
  if (!pw) return;
  bt.hidden = false; fz.hidden = false;
  bt.addEventListener('click', function () {
    var vedi = pw.type === 'password';
    pw.type = vedi ? 'text' : 'password';
    bt.textContent = vedi ? 'Nascondi' : 'Mostra';
    bt.setAttribute('aria-pressed', vedi ? 'true' : 'false');
    pw.focus();
  });
  var barra = fz.querySelector('b'), testo = fz.querySelector('span');
  function stima() {
    var v = pw.value, p = 0;
    if (v.length >= 8) p++;
    if (v.length >= 12) p++;
    if (/[a-z]/.test(v) && /[A-Z]/.test(v)) p++;
    if (/\d/.test(v)) p++;
    if (/[^A-Za-z0-9]/.test(v)) p++;
    if (v.length < 8) p = Math.min(p, 1);
    var livelli = [['', 0], ['Troppo corta', 1], ['Debole', 2], ['Discreta', 3], ['Buona', 4], ['Ottima', 5]];
    var l = v ? livelli[Math.max(1, Math.min(5, p))] : livelli[0];
    barra.style.width = (l[1] * 20) + '%';
    fz.setAttribute('data-livello', String(l[1]));
    testo.textContent = l[0] ? 'Password: ' + l[0].toLowerCase() : '';
  }
  pw.addEventListener('input', stima); stima();
})();
</script>
