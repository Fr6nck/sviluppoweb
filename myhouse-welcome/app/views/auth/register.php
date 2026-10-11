<?php use function MHW\b; use MHW\{Support, Csrf, Plans, Icon}; $title = 'Crea il tuo account';
$pianoScelto = $pianoScelto ?? null; $quantita = $quantita ?? null; ?>
<div class="registrati__fascia"><?php include __DIR__ . '/../pub/_fascia_sconto.php'; ?></div>
<?php if ($pianoScelto): /* Il piano scelto sulla landing resta in vista: si sa cosa si sta attivando. */ ?>
  <p class="chip-piano">
    <span class="chip-piano__testo"><?= Icon::svg('check', 15, 2.2) ?><span>Piano <b><?= Support::e($pianoScelto['name']) ?></b><?= Plans::perProperty($pianoScelto) ? ' · ' . (int) $quantita . ' strutture' : '' ?>
      · <?= Support::e(Support::money(Plans::price($pianoScelto, (int) $quantita), $pianoScelto['currency'])) ?> + IVA/anno</span></span>
    <a href="<?= b() ?>/#piani">Cambia</a>
  </p>
<?php endif; ?>
<h1>Cominciamo.</h1>
<p class="muted" style="margin-top:14px;line-height:24px">Configuri la guida gratis. Paghi solo quando la pubblichi.</p>
<?php if ($err): ?><p class="note note--err" style="margin-top:20px" role="alert"><?= Support::e($err) ?></p>
  <?php $aiutoEmail = $vecchi['email']; $aiutoPagina = 'Registrazione'; include __DIR__ . '/_aiuto.php'; ?><?php endif; ?>
<form method="post" class="panel stack" style="margin-top:24px" novalidate><?= Csrf::field() ?>
  <input type="hidden" name="piano" value="<?= (int) $piano ?>">
  <?php if (!empty($strutture)): ?><input type="hidden" name="strutture" value="<?= (int) $strutture ?>"><?php endif; ?>
  <div class="field" style="margin:0"><label for="name">Nome e cognome</label>
    <input id="name" name="name" type="text" required maxlength="120" autocomplete="name" value="<?= Support::e($vecchi['name']) ?>" autofocus></div>
  <div class="field" style="margin:0"><label for="email">Email</label>
    <input id="email" name="email" type="email" required maxlength="190" autocomplete="email" value="<?= Support::e($vecchi['email']) ?>"></div>
  <div class="field" style="margin:0"><label for="email2">Ripeti l'email</label>
    <input id="email2" name="email2" type="email" required maxlength="190" autocomplete="email" value="<?= Support::e($vecchi['email2'] ?? '') ?>" data-ripeti="email" aria-describedby="email2-diversa">
    <p class="field-error" id="email2-diversa" hidden>Le due email non sono uguali.</p></div>
  <?php $pwId = 'password'; $pwNome = 'password'; $pwEtichetta = 'Password'; $pwAuto = 'new-password'; $pwNuova = true; include __DIR__ . '/_pw.php'; ?>
  <label class="check" style="align-items:flex-start"><input type="checkbox" name="termini" value="1" required style="margin-top:1px">
    <span>Accetto i <a href="<?= b() ?>/termini" target="_blank" rel="noopener">Termini e condizioni</a></span></label>
  <button class="btn btn--block btn--go">Crea l'account e inizia <span class="go"><?= Icon::svg('arrow', 18, 2) ?></span></button>
  <p class="small muted registrati__riservata"><?= Icon::svg('lock', 14) ?><span>La guida che crei non compare sui motori di ricerca: la vede solo chi ha il link o il QR.</span></p>
  <?php /* Formulazione da far verificare al consulente privacy. */ ?>
  <p class="small muted" style="margin-top:-4px">Creando l'account dichiari di aver letto l'<a href="<?= b() ?>/privacy" target="_blank" rel="noopener">informativa privacy</a>.</p>
</form>
<p class="muted small" style="margin-top:20px">Hai già un account? <a href="<?= b() ?>/accedi">Accedi</a>.</p>
<script src="<?= MHW\av('/assets/password.js') ?>" defer></script>
