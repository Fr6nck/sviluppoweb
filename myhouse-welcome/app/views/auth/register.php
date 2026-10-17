<?php use function MHW\b; use MHW\{Support, Csrf, Plans, Icon}; $title = 'Crea il tuo account';
$pianoScelto = $pianoScelto ?? null; $quantita = $quantita ?? null; ?>
<div class="registrati__fascia"><?php include __DIR__ . '/../pub/_fascia_sconto.php'; ?></div>
<?php /* 8E: chi arriva dalla home inglese (?lang=en): il pannello per ora è in italiano, lo diciamo subito. */
      $daInglese = ($_GET['lang'] ?? $_POST['lang'] ?? '') === 'en';
      if ($daInglese): $mailEn = MHW\Support::contatto(); ?>
  <p class="note registra-en" lang="en" role="note">The dashboard is in Italian for now: we'll help you in English at <a href="mailto:<?= Support::e($mailEn) ?>"><?= Support::e($mailEn) ?></a>.</p>
<?php endif; ?>
<?php /* 9A · Gli sconti che valgono per chi si registra (l'invito dal link o dal codice): prezzo barrato. */
      $scontoInvito = $scontoInvito ?? []; $invitoCodice = $invitoCodice ?? ''; $oltre = $oltre ?? 0; $pianoOltre = $pianoOltre ?? null; $omaggio = $omaggio ?? null;
      $contoReg = fn(array $o, int $q) => MHW\Sconti::disponibili() && $scontoInvito ? MHW\Sconti::riepilogo($o, $q, null, $scontoInvito) : null;
      $prezzoReg = function (array $o, int $q) use ($contoReg): string {
          $r = $contoReg($o, $q);
          if (!$r || $r['sconto'] <= 0) return '<span class="reg-prezzo" data-pieno="' . Plans::price($o, $q) . '" data-valuta="' . Support::e($o['currency']) . '">' . Support::e(Support::money(Plans::price($o, $q), $o['currency'])) . '</span> + IVA / anno';
          return '<span class="reg-prezzo" data-pieno="' . $r['prezzo'] . '" data-valuta="' . Support::e($o['currency']) . '"><s>' . Support::e(Support::money($r['prezzo'], $o['currency'])) . '</s> <b>' . Support::e(Support::money($r['totale'], $o['currency'])) . '</b></span> + IVA il primo anno'
               . ' <span class="reg-prezzo__sconto">−' . (int) $r['percento'] . '% primo anno</span>';
      }; ?>
<?php if ($oltre && $pianoOltre): /* 9A · più strutture del massimo: il preventivo, non un piano */ ?>
  <?php $pvP = $pianoOltre; $qP = $oltre; $tornaP = '/registrati?piano=' . (int) $piano . '&strutture=' . (int) $oltre; include __DIR__ . '/../pub/_preventivo.php'; ?>
<?php endif; ?>
<?php if ($pianoScelto): /* Il piano scelto sulla landing resta in vista: si sa cosa si sta attivando. */ ?>
  <p class="chip-piano">
    <span class="chip-piano__testo"><?= Icon::svg('check', 15, 2.2) ?><span>Piano <b><?= Support::e($pianoScelto['name']) ?></b><?= Plans::perProperty($pianoScelto) ? ' · ' . (int) $quantita . ' strutture' : '' ?>
      · <?= $prezzoReg($pianoScelto, (int) $quantita) ?></span></span>
    <a href="<?= b() ?>/#piani">Cambia</a>
  </p>
<?php endif; ?>
<?php if ($omaggio): /* 9D · dal link di un codice omaggio: il banner con i mesi gratis e la scadenza del codice */ ?>
  <div class="omaggio-banner" role="status"><b>Hai <?= (int) $omaggio['mesi'] ?> mesi gratis.</b> Il codice è già inserito e vale fino al <?= Support::e(Support::date($omaggio['scade_il'])) ?>.
    Oggi non paghi nulla: il primo addebito è il <?= Support::e(Support::date(MHW\Omaggi::fineIso((int) $omaggio['mesi']))) ?>.</div>
<?php endif; ?>
<h1>Cominciamo.</h1>
<p class="muted" style="margin-top:14px;line-height:24px">Configuri la guida gratis. Paghi solo quando la pubblichi.</p>
<?php if ($err): ?><p class="note note--err" style="margin-top:20px" role="alert"><?= Support::e($err) ?></p>
  <?php $aiutoEmail = $vecchi['email']; $aiutoPagina = 'Registrazione'; include __DIR__ . '/_aiuto.php'; ?><?php endif; ?>
<form method="post" class="panel stack" style="margin-top:24px" novalidate data-base="<?= b() ?>"><?= Csrf::field() ?>
  <?php if ($daInglese): ?><input type="hidden" name="lang" value="en"><?php endif; ?>
  <?php if ($pianoScelto): ?>
  <input type="hidden" name="piano" value="<?= (int) $piano ?>">
  <?php else: /* 7B: senza un piano dalla landing, si sceglie qui (Plus già scelto); si salva come intended_package_version_id. */
        $sceglibili = Plans::public(); $giaScelto = (int) $piano;
        if (!$giaScelto) foreach ($sceglibili as $o) if ($o['code'] === 'plus') $giaScelto = (int) $o['pv_id']; ?>
  <fieldset class="scegli-piano">
    <legend class="scegli-piano__titolo">Scegli il tuo piano</legend>
    <p class="small muted" style="margin:0 0 12px">Non paghi niente finché non decidi di pubblicare. Intanto prepari la guida e la vedi in anteprima, come la vedranno i tuoi ospiti.</p>
    <div class="scegli-piano__schede">
      <?php foreach ($sceglibili as $o): $qMin = Plans::quantity($o, null) ?? 1;
            $voci = array_slice(array_values(array_filter($o['bullet_list'], fn($v) => !str_ends_with($v, ':'))), 0, 3); ?>
        <label class="scegli-piano__scheda">
          <input type="radio" name="piano" value="<?= (int) $o['pv_id'] ?>"<?= (int) $o['pv_id'] === $giaScelto ? ' checked' : '' ?> required>
          <span class="scegli-piano__testa"><b><?= Support::e($o['name']) ?></b>
            <span><?= $prezzoReg($o, $qMin) ?><?= Plans::perProperty($o) ? ' · ' . $qMin . ' strutture' : '' ?></span></span>
          <ul><?php foreach ($voci as $v): ?><li><?= Support::e(str_replace('{sezioni}', (string) MHW\SectionCatalog::quante(), $v)) ?></li><?php endforeach; ?></ul>
        </label>
      <?php endforeach; ?>
    </div>
  </fieldset>
  <?php endif; ?>
  <?php if (!empty($strutture) && !$oltre): ?><input type="hidden" name="strutture" value="<?= (int) $strutture ?>"><?php endif; ?>
  <?php $omaggiOk = MHW\Omaggi::disponibili(); if ($omaggio): /* 9D · il codice omaggio dal link: già scritto, in sola lettura */ ?>
    <div class="field invito-campo" style="margin:0"><label for="codice_invito">Codice di invito o omaggio</label>
      <input id="codice_invito" name="codice_invito" type="text" value="<?= Support::e($omaggio['codice']) ?>" readonly aria-describedby="invito-ok">
      <p class="invito-ok" id="invito-ok" role="status"><?= Icon::svg('check', 16, 2.4) ?><span><?= (int) $omaggio['mesi'] ?> mesi gratis · primo addebito il <?= Support::e(Support::date(MHW\Omaggi::fineIso((int) $omaggio['mesi']))) ?></span></p></div>
  <?php elseif (MHW\Inviti::disponibili() || $omaggiOk): /* 9A · il codice di invito: dal link (sola lettura) o da scrivere, con «Applica»; 9D: nello stesso campo anche un codice omaggio */ ?>
    <?php if ($invitoCodice !== ''): ?>
      <div class="field invito-campo" style="margin:0"><label for="codice_invito">Codice di invito</label>
        <input id="codice_invito" name="codice_invito" type="text" value="<?= Support::e($invitoCodice) ?>" readonly aria-describedby="invito-ok">
        <p class="invito-ok" id="invito-ok" role="status"><?= Icon::svg('check', 16, 2.4) ?><span>Codice di <?= Support::e($invitoDi) ?>: <?= MHW\Inviti::AMICO ?>% sul primo anno</span></p></div>
    <?php else: ?>
      <details class="sconto invito-campo" data-invito<?= $vecchi['codice_invito'] !== '' ? ' open' : '' ?>>
        <summary class="linkbtn"><?= $omaggiOk && MHW\Inviti::disponibili() ? 'Hai un codice di invito o omaggio?' : ($omaggiOk ? 'Hai un codice omaggio?' : 'Hai un codice di invito?') ?></summary>
        <div class="sconto__form">
          <div class="field" style="margin:0"><label for="codice_invito"><?= $omaggiOk && MHW\Inviti::disponibili() ? 'Codice di invito o omaggio' : ($omaggiOk ? 'Codice omaggio' : 'Codice di invito') ?></label>
            <input id="codice_invito" name="codice_invito" type="text" maxlength="14" autocomplete="off" spellcheck="false" style="text-transform:uppercase"
                   value="<?= Support::e($vecchi['codice_invito']) ?>" aria-describedby="invito-err invito-ok"></div>
          <button type="button" class="btn btn--ghost btn--sm" data-invito-applica>Applica</button>
          <p class="field-error" id="invito-err" role="alert" style="flex-basis:100%;margin:0" hidden></p>
          <p class="invito-ok" id="invito-ok" role="status" style="flex-basis:100%" <?= $scontoInvito ? '' : 'hidden' ?>><?= Icon::svg('check', 16, 2.4) ?><span><?= $scontoInvito ? 'Codice di ' . Support::e($invitoDi ?: $scontoInvito[0]['nome']) . ': ' . MHW\Inviti::AMICO . '% sul primo anno' : '' ?></span></p>
        </div>
      </details>
    <?php endif; ?>
  <?php endif; ?>
  <div class="field" style="margin:0"><label for="name">Nome e cognome</label>
    <input id="name" name="name" type="text" required maxlength="120" autocomplete="name" value="<?= Support::e($vecchi['name']) ?>" autofocus></div>
  <div class="field" style="margin:0"><label for="email">Email</label>
    <input id="email" name="email" type="email" required maxlength="190" autocomplete="email" value="<?= Support::e($vecchi['email']) ?>"></div>
  <div class="field" style="margin:0"><label for="email2">Ripeti l'email</label>
    <input id="email2" name="email2" type="email" required maxlength="190" autocomplete="email" value="<?= Support::e($vecchi['email2'] ?? '') ?>" data-ripeti="email" aria-describedby="email2-diversa">
    <p class="field-error" id="email2-diversa" hidden>Le due email non sono uguali.</p></div>
  <?php $pwId = 'password'; $pwNome = 'password'; $pwEtichetta = 'Password'; $pwAuto = 'new-password'; $pwNuova = true; include __DIR__ . '/_pw.php'; ?>
  <?php /* 8J: una sola casella, non spuntata: Termini, Accordo sul trattamento dei dati (Allegato A) e presa visione dell'Informativa. Niente consenso marketing. */ ?>
  <label class="check" style="align-items:flex-start"><input type="checkbox" name="termini" value="1" required style="margin-top:1px">
    <span>Accetto i <a href="<?= b() ?>/termini" target="_blank" rel="noopener">Termini</a> e l'<a href="<?= b() ?>/dpa" target="_blank" rel="noopener">Accordo sul trattamento dei dati</a>
      e ho letto l'<a href="<?= b() ?>/privacy" target="_blank" rel="noopener">Informativa privacy</a></span></label>
  <button class="btn btn--block btn--go">Crea l'account e inizia <span class="go"><?= Icon::svg('arrow', 18, 2) ?></span></button>
  <p class="small muted registrati__riservata"><?= Icon::svg('lock', 14) ?><span>La guida che crei non compare sui motori di ricerca: la vede solo chi ha il link o il QR.</span></p>
</form>
<p class="muted small" style="margin-top:20px">Hai già un account? <a href="<?= b() ?>/accedi">Accedi</a>.</p>
<script src="<?= MHW\av('/assets/password.js') ?>" defer></script>
<?php if (MHW\Inviti::disponibili() && $invitoCodice === ''): /* 9A · «Applica»: controlla il codice senza ricaricare e barra i prezzi delle schede */ ?>
<script>
(function () {
  var box = document.querySelector('[data-invito]'); if (!box) return;
  var campo = box.querySelector('input[name=codice_invito]'), btn = box.querySelector('[data-invito-applica]');
  var err = box.querySelector('#invito-err'), ok = box.querySelector('#invito-ok'), form = campo.form;
  function soldi(cents, valuta) {
    var intero = cents % 100 === 0, n = (cents / 100).toFixed(intero ? 0 : 2).split('.');
    n[0] = n[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    return n.join(',') + '\u00a0' + (valuta === 'EUR' ? '€' : valuta);
  }
  function barra(pct) {
    var prezzi = document.querySelectorAll('.reg-prezzo');
    for (var i = 0; i < prezzi.length; i++) {
      var pieno = parseInt(prezzi[i].getAttribute('data-pieno'), 10), v = prezzi[i].getAttribute('data-valuta') || 'EUR';
      var sconto = Math.min(Math.round(pieno * pct / 100), pieno - 100);
      prezzi[i].innerHTML = '<s>' + soldi(pieno, v) + '</s> <b>' + soldi(pieno - sconto, v) + '</b>';
      var dopo = prezzi[i].nextSibling;
      if (dopo && dopo.nodeType === 3) dopo.textContent = ' + IVA il primo anno ';
      if (!prezzi[i].parentNode.querySelector('.reg-prezzo__sconto')) {
        var r = document.createElement('span'); r.className = 'reg-prezzo__sconto'; r.textContent = '−' + pct + '% primo anno';
        prezzi[i].parentNode.insertBefore(r, prezzi[i].nextSibling ? prezzi[i].nextSibling.nextSibling : null);
      }
    }
  }
  btn.addEventListener('click', function () {
    var codice = campo.value.trim().toUpperCase(); campo.value = codice;
    err.hidden = true; ok.hidden = true;
    if (codice === '') { err.textContent = 'Scrivi il codice.'; err.hidden = false; return; }
    var fd = new FormData(); fd.append('codice', codice); fd.append('_csrf', form.querySelector('input[name=_csrf]').value);
    btn.disabled = true;
    fetch(form.getAttribute('data-base') + '/invito/verifica', { method: 'POST', body: fd, headers: { Accept: 'application/json' }, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (j.ok && j.tipo === 'omaggio') { ok.querySelector('span').textContent = j.mesi + ' mesi gratis · primo addebito il ' + j.primo_addebito; ok.hidden = false; }
        else if (j.ok) { ok.querySelector('span').textContent = 'Codice di ' + j.nome + ': ' + j.percento + '% sul primo anno'; ok.hidden = false; barra(j.percento); }
        else { err.textContent = j.errore || 'Questo codice non esiste o non è più attivo. Controlla con chi te l\'ha dato.'; err.hidden = false; }
      })
      .catch(function () { err.textContent = 'Non riesco a controllare il codice adesso: lo verifichiamo quando crei l\'account.'; err.hidden = false; })
      .then(function () { btn.disabled = false; });
  });
  campo.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); btn.click(); } });
})();
</script>
<?php endif; ?>
