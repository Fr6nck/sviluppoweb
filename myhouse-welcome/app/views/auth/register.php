<?php use function MHW\b; use MHW\{Support, Csrf, Plans, Icon, LinguaSito};
/* 9N · In inglese da /en/register (o ?lang=en): LinguaSito::en() e $U per i testi fissi; il pannello nasce in inglese. */
$U = [LinguaSito::class, 't']; $EN = LinguaSito::en();
$title = $U('Crea il tuo account', 'Create your account') . ' | MyHouse Welcome';   // 9T · 5.9
$descrizione = $U('Crea gratis la guida digitale della tua struttura: check-in, Wi-Fi e consigli in un link o QR Code. Paghi solo quando la pubblichi.', 'Create the digital guide for your property for free: check-in, Wi-Fi and tips in one link or QR code. You only pay when you publish it.');
$pianoScelto = $pianoScelto ?? null; $quantita = $quantita ?? null; ?>
<div class="registrati__fascia"><?php include __DIR__ . '/../pub/_fascia_sconto.php'; ?></div>
<?php $daInglese = $EN; /* 9N: il pannello di chi si registra in inglese è in inglese (panel_lang) */ ?>
<?php /* 9A · Gli sconti che valgono per chi si registra (l'invito dal link o dal codice): prezzo barrato. */
      $scontoInvito = $scontoInvito ?? []; $invitoCodice = $invitoCodice ?? ''; $oltre = $oltre ?? 0; $pianoOltre = $pianoOltre ?? null; $omaggio = $omaggio ?? null;
      $contoReg = fn(array $o, int $q) => MHW\Sconti::disponibili() && $scontoInvito ? MHW\Sconti::riepilogo($o, $q, null, $scontoInvito) : null;
      $prezzoReg = function (array $o, int $q) use ($contoReg): string {
          $r = $contoReg($o, $q);
          if (!$r || $r['sconto'] <= 0) return '<span class="reg-prezzo" data-pieno="' . Plans::price($o, $q) . '" data-valuta="' . Support::e($o['currency']) . '">' . Support::e(Support::money(Plans::price($o, $q), $o['currency'])) . '</span>' . LinguaSito::t(' + IVA / anno', ' + VAT / year');
          return '<span class="reg-prezzo" data-pieno="' . $r['prezzo'] . '" data-valuta="' . Support::e($o['currency']) . '"><s>' . Support::e(Support::money($r['prezzo'], $o['currency'])) . '</s> <b>' . Support::e(Support::money($r['totale'], $o['currency'])) . '</b></span>' . LinguaSito::t(' + IVA il primo anno', ' + VAT the first year')
               . ' <span class="reg-prezzo__sconto">−' . (int) $r['percento'] . LinguaSito::t('% primo anno', '% first year') . '</span>';
      }; ?>
<?php if ($oltre && $pianoOltre): /* 9A · più strutture del massimo: il preventivo, non un piano */ ?>
  <?php $pvP = $pianoOltre; $qP = $oltre; $tornaP = LinguaSito::percorso('/registrati') . '?piano=' . (int) $piano . '&strutture=' . (int) $oltre; include __DIR__ . '/../pub/_preventivo.php'; ?>
<?php endif; ?>
<?php if ($pianoScelto): /* Il piano scelto sulla landing resta in vista: si sa cosa si sta attivando. */
      $perC = Plans::perProperty($pianoScelto); /* 9R · Portfolio: il numero resta modificabile (campo del modulo, attributo form) */
      $rC = $perC ? $contoReg($pianoScelto, (int) $quantita) : null; $scC = $rC && $rC['sconto'] > 0 && $rC['percento'] > 0 ? (int) $rC['percento'] : 0; ?>
  <p class="chip-piano"<?= $perC ? ' data-quantita data-base="' . (int) $pianoScelto['price_cents'] . '" data-extra="' . (int) $pianoScelto['extra_price_cents'] . '" data-scaglioni="' . Support::e(Plans::tiersJson($pianoScelto)) . '" data-valuta="' . Support::e($pianoScelto['currency']) . '"' . ($scC ? ' data-sconto="' . $scC . '"' : '') : '' ?>>
    <span class="chip-piano__testo"><?= Icon::svg('check', 15, 2.2) ?><span><?= $U('Piano', 'Plan') ?> <b><?= Support::e($pianoScelto['name']) ?></b>
      <?php if ($perC): ?> · <label for="chip-strutture" class="sr-only"><?= $U('Quante strutture?', 'How many properties?') ?></label><input id="chip-strutture" form="form-registrati" name="strutture" type="number" inputmode="numeric" step="1" min="<?= (int) $pianoScelto['min_quantity'] ?>" max="<?= (int) $pianoScelto['max_quantity'] ?>" value="<?= (int) $quantita ?>" class="chip-piano__quante"><?= $U(' strutture', ' properties') ?>
        · <span class="reg-prezzo" data-totale><?= Support::e(Support::money($scC ? $rC['totale'] : Plans::price($pianoScelto, (int) $quantita), $pianoScelto['currency'])) ?></span><?= $scC ? $U(' + IVA il primo anno', ' + VAT the first year') . ' <span class="reg-prezzo__sconto">−' . $scC . $U('% primo anno', '% first year') . '</span>' : $U(' + IVA / anno', ' + VAT / year') ?>
      <?php else: ?> · <?= $prezzoReg($pianoScelto, (int) $quantita) ?><?php endif; ?></span></span>
    <a href="<?= b() . ($EN ? '/en' : '/') ?>#piani"><?= $U('Cambia', 'Change') ?></a>
  </p>
<?php endif; ?>
<?php if ($omaggio): /* 9D · dal link di un codice omaggio: il banner con i mesi gratis e la scadenza del codice */ ?>
  <div class="omaggio-banner" role="status"><?php if ($EN): ?><b>You have <?= (int) $omaggio['mesi'] ?> free months.</b> The code is already entered and is valid until <?= Support::e(Support::date($omaggio['scade_il'])) ?>.
    You pay nothing today: the first charge is on <?= Support::e(Support::date(MHW\Omaggi::fineIso((int) $omaggio['mesi']))) ?>.<?php else: ?><b>Hai <?= (int) $omaggio['mesi'] ?> mesi gratis.</b> Il codice è già inserito e vale fino al <?= Support::e(Support::date($omaggio['scade_il'])) ?>.
    Oggi non paghi nulla: il primo addebito è il <?= Support::e(Support::date(MHW\Omaggi::fineIso((int) $omaggio['mesi']))) ?>.<?php endif; ?></div>
<?php endif; ?>
<h1><?= $U('Cominciamo.', 'Let\'s start.') ?></h1>
<p class="muted" style="margin-top:14px;line-height:24px"><?= $U('Configuri la guida gratis. Paghi solo quando la pubblichi.', 'Set up your guide for free. You only pay when you publish it.') ?></p>
<?php if ($err): ?><p class="note note--err" style="margin-top:20px" role="alert"><?= Support::e($err) ?></p>
  <?php $aiutoEmail = $vecchi['email']; $aiutoPagina = $U('Registrazione', 'Sign-up'); include __DIR__ . '/_aiuto.php'; ?><?php endif; ?>
<form method="post" class="panel stack" id="form-registrati" style="margin-top:24px" novalidate data-base="<?= b() ?>"><?= Csrf::field() ?>
  <?php if ($daInglese): ?><input type="hidden" name="lang" value="en"><?php endif; ?>
  <?php if ($pianoScelto): ?>
  <input type="hidden" name="piano" value="<?= (int) $piano ?>">
  <?php else: /* 7B: senza un piano dalla landing, si sceglie qui (Plus già scelto); si salva come intended_package_version_id. */
        $sceglibili = Plans::public(); $giaScelto = (int) $piano;
        if (!$giaScelto) foreach ($sceglibili as $o) if ($o['code'] === 'plus') $giaScelto = (int) $o['pv_id']; ?>
  <fieldset class="scegli-piano">
    <legend class="scegli-piano__titolo"><?= $U('Scegli il tuo piano', 'Choose your plan') ?></legend>
    <p class="small muted" style="margin:0 0 12px"><?= $U('Non paghi niente finché non decidi di pubblicare. Intanto prepari la guida e la vedi in anteprima, come la vedranno i tuoi ospiti.', 'You pay nothing until you decide to publish. Meanwhile you prepare the guide and preview it as your guests will see it.') ?></p>
    <div class="scegli-piano__schede">
      <?php foreach ($sceglibili as $o): $qMin = Plans::quantity($o, null) ?? 1; if ($EN) $o = Plans::inLingua($o, 'en');
            $voci = array_slice(array_values(array_filter($o['bullet_list'], fn($v) => !str_ends_with($v, ':'))), 0, 3);
            /* 9R · Portfolio: il numero di strutture si sceglie qui (2–10, da ?strutture= se arriva da home o questionario). */
            $perS = Plans::perProperty($o); $qReg = $perS ? (Plans::quantity($o, (string) ($strutture ?: '')) ?? $qMin) : 1; ?>
        <label class="scegli-piano__scheda">
          <input type="radio" name="piano" value="<?= (int) $o['pv_id'] ?>"<?= (int) $o['pv_id'] === $giaScelto ? ' checked' : '' ?> required>
          <span class="scegli-piano__testa"><b><?= Support::e($o['name']) ?></b>
            <span><?= $prezzoReg($o, $qReg) ?><?= $perS ? ' · ' . $qReg . $U(' strutture', ' properties') : '' ?></span></span>
          <?php if ($perS): $rS = $contoReg($o, $qReg); ?><span class="reg-strutture" data-quantita data-base="<?= (int) $o['price_cents'] ?>" data-extra="<?= (int) $o['extra_price_cents'] ?>" data-scaglioni="<?= Support::e(Plans::tiersJson($o)) ?>" data-valuta="<?= Support::e($o['currency']) ?>"<?= $rS && $rS['sconto'] > 0 && $rS['percento'] > 0 ? ' data-sconto="' . (int) $rS['percento'] . '"' : '' ?>>
            <span id="reg-strutture-et"><?= $U('Quante strutture?', 'How many properties?') ?></span>
            <input id="reg-strutture" name="strutture" aria-labelledby="reg-strutture-et" type="number" inputmode="numeric" step="1" min="<?= (int) $o['min_quantity'] ?>" max="<?= (int) $o['max_quantity'] ?>" value="<?= $qReg ?>">
            <span class="small"><span data-totale><?= Support::e(Support::money($rS && $rS['sconto'] > 0 ? $rS['totale'] : Plans::price($o, $qReg), $o['currency'])) ?></span><?= $U(' + IVA', ' + VAT') ?> · <span data-quante><?= $qReg ?></span><?= $U(' strutture', ' properties') ?></span></span>
            <span class="small muted reg-scaglioni"><?= Support::e(Plans::scomposizione($o, (int) $o['max_quantity'], $EN)) ?></span><?php endif; ?>
          <ul><?php foreach ($voci as $v): ?><li><?= Support::e(str_replace('{sezioni}', (string) MHW\SectionCatalog::quante(), $v)) ?></li><?php endforeach; ?></ul>
        </label>
      <?php endforeach; ?>
    </div>
  </fieldset>
  <?php endif; ?>
  <?php if ($pianoScelto && !Plans::perProperty($pianoScelto) && !empty($strutture) && !$oltre): ?><input type="hidden" name="strutture" value="<?= (int) $strutture ?>"><?php endif; ?>
  <?php $omaggiOk = MHW\Omaggi::disponibili(); if ($omaggio): /* 9D · il codice omaggio dal link: già scritto, in sola lettura */ ?>
    <div class="field invito-campo" style="margin:0"><label for="codice_invito"><?= $U('Codice di invito o omaggio', 'Invitation or gift code') ?></label>
      <input id="codice_invito" name="codice_invito" type="text" value="<?= Support::e($omaggio['codice']) ?>" readonly aria-describedby="invito-ok">
      <p class="invito-ok" id="invito-ok" role="status"><?= Icon::svg('check', 16, 2.4) ?><span><?= (int) $omaggio['mesi'] ?><?= $U(' mesi gratis · primo addebito il ', ' free months · first charge on ') ?><?= Support::e(Support::date(MHW\Omaggi::fineIso((int) $omaggio['mesi']))) ?></span></p></div>
  <?php elseif (MHW\Inviti::disponibili() || $omaggiOk): /* 9A · il codice di invito: dal link (sola lettura) o da scrivere, con «Applica»; 9D: nello stesso campo anche un codice omaggio */ ?>
    <?php if ($invitoCodice !== ''): ?>
      <div class="field invito-campo" style="margin:0"><label for="codice_invito"><?= $U('Codice di invito', 'Invitation code') ?></label>
        <input id="codice_invito" name="codice_invito" type="text" value="<?= Support::e($invitoCodice) ?>" readonly aria-describedby="invito-ok">
        <p class="invito-ok" id="invito-ok" role="status"><?= Icon::svg('check', 16, 2.4) ?><span><?= $U('Codice di ', 'Code from ') . Support::e($invitoDi) ?>: <?= MHW\Inviti::AMICO ?><?= $U('% sul primo anno', '% off the first year') ?></span></p></div>
    <?php else: ?>
      <details class="sconto invito-campo" data-invito<?= $vecchi['codice_invito'] !== '' ? ' open' : '' ?>>
        <summary class="linkbtn"><?= $omaggiOk && MHW\Inviti::disponibili() ? $U('Hai un codice di invito o omaggio?', 'Do you have an invitation or gift code?') : ($omaggiOk ? $U('Hai un codice omaggio?', 'Do you have a gift code?') : $U('Hai un codice di invito?', 'Do you have an invitation code?')) ?></summary>
        <div class="sconto__form">
          <div class="field" style="margin:0"><label for="codice_invito"><?= $omaggiOk && MHW\Inviti::disponibili() ? $U('Codice di invito o omaggio', 'Invitation or gift code') : ($omaggiOk ? $U('Codice omaggio', 'Gift code') : $U('Codice di invito', 'Invitation code')) ?></label>
            <input id="codice_invito" name="codice_invito" type="text" maxlength="14" autocomplete="off" spellcheck="false" style="text-transform:uppercase"
                   value="<?= Support::e($vecchi['codice_invito']) ?>" aria-describedby="invito-err invito-ok"></div>
          <button type="button" class="btn btn--ghost btn--sm" data-invito-applica><?= $U('Applica', 'Apply') ?></button>
          <p class="field-error" id="invito-err" role="alert" style="flex-basis:100%;margin:0" hidden></p>
          <p class="invito-ok" id="invito-ok" role="status" style="flex-basis:100%" <?= $scontoInvito ? '' : 'hidden' ?>><?= Icon::svg('check', 16, 2.4) ?><span><?= $scontoInvito ? $U('Codice di ', 'Code from ') . Support::e($invitoDi ?: $scontoInvito[0]['nome']) . ': ' . MHW\Inviti::AMICO . $U('% sul primo anno', '% off the first year') : '' ?></span></p>
        </div>
      </details>
    <?php endif; ?>
  <?php endif; ?>
  <div class="field" style="margin:0"><label for="name"><?= $U('Nome e cognome', 'Full name') ?></label>
    <input id="name" name="name" type="text" required maxlength="120" autocomplete="name" value="<?= Support::e($vecchi['name']) ?>" autofocus></div>
  <div class="field" style="margin:0"><label for="email">Email</label>
    <input id="email" name="email" type="email" required maxlength="190" autocomplete="email" value="<?= Support::e($vecchi['email']) ?>"></div>
  <?php $pwId = 'password'; $pwNome = 'password'; $pwEtichetta = 'Password'; $pwAuto = 'new-password'; $pwNuova = true; include __DIR__ . '/_pw.php'; ?>
  <?php /* 8J: una sola casella, non spuntata: Termini, Accordo sul trattamento dei dati (Allegato A) e presa visione dell'Informativa. Niente consenso marketing. */ ?>
  <label class="check" style="align-items:flex-start"><input type="checkbox" name="termini" value="1" required style="margin-top:1px">
    <?php if ($EN): ?><span>I accept the <a href="<?= b() ?>/en/terms" target="_blank" rel="noopener">Terms</a> and the <a href="<?= b() ?>/en/dpa" target="_blank" rel="noopener">Data processing agreement</a>
      and I have read the <a href="<?= b() ?>/en/privacy" target="_blank" rel="noopener">Privacy policy</a></span>
    <?php else: ?><span>Accetto i <a href="<?= b() ?>/termini" target="_blank" rel="noopener">Termini</a> e l'<a href="<?= b() ?>/dpa" target="_blank" rel="noopener">Accordo sul trattamento dei dati</a>
      e ho letto l'<a href="<?= b() ?>/privacy" target="_blank" rel="noopener">Informativa privacy</a></span><?php endif; ?></label>
  <button class="btn btn--block btn--go"><?= $U('Crea l\'account e inizia', 'Create the account and start') ?> <span class="go"><?= Icon::svg('arrow', 18, 2) ?></span></button>
  <p class="small muted registrati__riservata"><?= Icon::svg('lock', 14) ?><span><?= $U('La guida che crei non compare sui motori di ricerca: la vede solo chi ha il link o il QR.', 'The guide you create does not appear on search engines: only those with the link or the QR code can see it.') ?></span></p>
</form>
<p class="muted small" style="margin-top:20px"><?= $U('Hai già un account?', 'Already have an account?') ?> <a href="<?= b() . LinguaSito::percorso('/accedi') ?>"><?= $U('Accedi', 'Log in') ?></a>.</p>
<script src="<?= MHW\av('/assets/password.js') ?>" defer></script>
<?php if (MHW\Inviti::disponibili() && $invitoCodice === ''): /* 9A · «Applica»: controlla il codice senza ricaricare e barra i prezzi delle schede */ ?>
<script>
(function () {
  var en = document.documentElement.lang === 'en';
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
      if (dopo && dopo.nodeType === 3) dopo.textContent = en ? ' + VAT the first year ' : ' + IVA il primo anno ';
      if (!prezzi[i].parentNode.querySelector('.reg-prezzo__sconto')) {
        var r = document.createElement('span'); r.className = 'reg-prezzo__sconto'; r.textContent = '−' + pct + (en ? '% first year' : '% primo anno');
        prezzi[i].parentNode.insertBefore(r, prezzi[i].nextSibling ? prezzi[i].nextSibling.nextSibling : null);
      }
    }
  }
  btn.addEventListener('click', function () {
    var codice = campo.value.trim().toUpperCase(); campo.value = codice;
    err.hidden = true; ok.hidden = true;
    if (codice === '') { err.textContent = en ? 'Write the code.' : 'Scrivi il codice.'; err.hidden = false; return; }
    var fd = new FormData(); fd.append('codice', codice); fd.append('_csrf', form.querySelector('input[name=_csrf]').value);
    btn.disabled = true;
    fetch(form.getAttribute('data-base') + '/invito/verifica', { method: 'POST', body: fd, headers: { Accept: 'application/json' }, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (j.ok && j.tipo === 'omaggio') { ok.querySelector('span').textContent = j.mesi + (en ? ' free months · first charge on ' : ' mesi gratis · primo addebito il ') + j.primo_addebito; ok.hidden = false; }
        else if (j.ok) { ok.querySelector('span').textContent = (en ? 'Code from ' : 'Codice di ') + j.nome + ': ' + j.percento + (en ? '% off the first year' : '% sul primo anno'); ok.hidden = false; barra(j.percento); }
        else { err.textContent = (en ? '' : j.errore) || (en ? 'This code does not exist or is no longer active. Check with whoever gave it to you.' : 'Questo codice non esiste o non è più attivo. Controlla con chi te l\'ha dato.'); err.hidden = false; }
      })
      .catch(function () { err.textContent = en ? 'I cannot check the code right now: we will verify it when you create the account.' : 'Non riesco a controllare il codice adesso: lo verifichiamo quando crei l\'account.'; err.hidden = false; })
      .then(function () { btn.disabled = false; });
  });
  campo.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); btn.click(); } });
})();
</script>
<?php endif; ?>
<script src="<?= MHW\av('/assets/prezzi.js') ?>" defer></script>
