<?php
/* Account & Fatturazione: piano, prezzo, stato, rinnovo, scadenza. */
use function MHW\b;
use MHW\{Support, Csrf, Auth, Plans};
$title = 'Account & Fatturazione';
$stripe = $sub && $sub['provider'] === 'stripe' && $sub['provider_subscription_id'] !== '';
$fine = $sub['current_period_end'] ?? ($ultimo['current_period_end'] ?? '');
$statoOrdine = ['pending' => 'In attesa', 'awaiting' => 'In verifica', 'paid' => 'Pagato', 'failed' => 'Non riuscito', 'canceled' => 'Annullato', 'expired' => 'Scaduto']; ?>
<div class="stack stack--lg" style="max-width:820px">
  <h1>Account &amp; Fatturazione.</h1>

  <section class="panel stack">
    <span class="kicker">Abbonamento</span>
    <?php if ($sub): ?>
      <?php $perStruttura = $piano && Plans::perProperty($piano); $q = (int) ($sub['quantity'] ?? 1); ?>
      <div class="spread spread--mid">
        <b style="font-size:24px;font-weight:500"><?= Support::e($piano['name'] ?? '') ?><?= $q > 1 ? ' · ' . $q . ' strutture' : '' ?></b>
        <span style="font-size:20px;font-weight:500"><?= Support::e(Support::money($piano ? Plans::price($piano, $q) : 0, $piano['currency'] ?? 'EUR')) ?>
          <span class="small muted">+ IVA / anno</span></span>
      </div>
      <?php if ($perStruttura && $q > 1): /* Portfolio: quanto costa ognuna delle strutture, nell'ordine in cui sono state create */
            $bloccateA = MHW\Entitlements::lockedIds((int) $acc['id']);
            $mie = array_values(array_filter(MHW\Db::all('SELECT id, name FROM properties WHERE account_id = ? AND is_demo = 0 ORDER BY id', [$acc['id']]),
                                             fn($x) => !in_array((int) $x['id'], $bloccateA, true))); ?>
        <div class="scaglioni">
          <p class="scaglioni__titolo">Quanto paghi per ogni struttura, all'anno</p>
          <ul class="scaglioni__elenco">
            <?php for ($n = 1; $n <= $q; $n++): ?>
              <li><span><?= $n ?>ª · <?= isset($mie[$n - 1]) ? Support::e($mie[$n - 1]['name']) : '<span class="muted">ancora da creare</span>' ?></span>
                <b><?= Support::e(Support::money(Plans::unitPrice($piano, $n), $piano['currency'])) ?></b></li>
            <?php endfor; ?>
          </ul>
          <p class="scaglioni__media">In media <b><?= Support::e(Support::money((int) round(Plans::price($piano, $q) / $q), $piano['currency'])) ?></b> a struttura + IVA.
            <?php if ($q < (int) $piano['max_quantity']): ?>La <?= $q + 1 ?>ª costerebbe <?= Support::e(Support::money(Plans::unitPrice($piano, $q + 1), $piano['currency'])) ?>.<?php endif; ?></p>
        </div>
      <?php endif; ?>
      <?php if (($nVar = MHW\Varianti::contaAccount((int) $acc['id'])) > 0): ?>
        <p class="small">Varianti camera: <b><?= $nVar ?> × <?= Support::e(Support::money(MHW\Varianti::prezzo())) ?></b> + IVA / anno<?= $stripe ? ', con l\'abbonamento' : '' ?>.</p>
      <?php endif; ?>
      <?php if ($sub['status'] === 'past_due'): ?>
        <p class="note note--err">L'ultimo rinnovo non è andato a buon fine. Aggiorna il metodo di pagamento per non andare offline.</p>
      <?php elseif ((int) $sub['cancel_at_period_end'] === 1): ?>
        <p class="note">Rinnovo automatico disattivato. La guida resta online fino al <b><?= Support::e(Support::date($fine)) ?></b>, poi va offline: i contenuti restano salvati.</p>
      <?php else: ?>
        <p><span class="badge badge--pine"><span class="dot"></span>Attivo</span>
          <?php if ($fine): ?><span class="small muted"> · si rinnova il <?= Support::e(Support::date($fine)) ?></span><?php endif; ?></p>
      <?php endif; ?>
      <?php if (($avuto = MHW\Sconti::avuto((int) $acc['id']))): /* lo sconto del primo anno (6E) */ ?>
        <p class="small">Sconto del primo anno: <b><?= "\u{2212}" . Support::e(Support::money((int) $avuto['discount_cents'])) ?></b> (<?= Support::e($avuto['code']) ?>).
          <?php if ($fine): ?>Rinnovo a prezzo pieno il <?= Support::e(Support::date($fine)) ?>.<?php endif; ?></p>
      <?php endif; ?>
      <?php if ($stripe && MHW\Inviti::puoInvitare($acc) && ($invAcc = MHW\Inviti::stato($acc)) && $invAcc['percento'] > 0): /* lo sconto degli inviti sul prossimo rinnovo */ ?>
        <div class="sconto-ok" role="status">
          <p class="sconto-ok__prezzo" style="margin:0"><s><?= Support::e(Support::money((int) $invAcc['prezzo'], $invAcc['valuta'])) ?></s>
            <b><?= Support::e(Support::money((int) $invAcc['scontato'], $invAcc['valuta'])) ?></b> + IVA al prossimo rinnovo</p>
          <p style="margin:0">Sconto inviti <b><?= "\u{2212}" . (int) $invAcc['percento'] ?>%</b>:
            <?= (int) $invAcc['validi'] === 1 ? '1 amico ha pubblicato la sua guida' : (int) $invAcc['validi'] . ' amici hanno pubblicato la loro guida' ?>.
            Dal rinnovo successivo torni a <?= Support::e(Support::money((int) $invAcc['prezzo'], $invAcc['valuta'])) ?> + IVA.
            <a href="<?= b() ?>/inviti">Invita altri amici</a></p>
          <?php if (!$invAcc['automatico']): ?><p class="small" style="margin:0">Il rinnovo automatico è disattivato: lo sconto vale solo sul rinnovo.</p><?php endif; ?>
        </div>
      <?php endif; ?>
      <?php /* Un passaggio a un piano più economico, programmato per il rinnovo (6H). */
            if ($stripe && !empty($sub['next_package_version_id']) && ($prossimo = Plans::version((int) $sub['next_package_version_id']))):
              $qn = max(1, (int) ($sub['next_quantity'] ?? 1)); ?>
        <div class="note stack" style="display:block" role="status">
          <p style="margin:0">Dal <?= Support::e(Support::date($fine)) ?> passi a <b><?= Support::e($prossimo['name']) ?><?= Plans::perProperty($prossimo) ? ' · ' . $qn . ' strutture' : '' ?></b>:
            <?= Support::e(Support::money(Plans::price($prossimo, $qn), $prossimo['currency'])) ?> + IVA / anno.</p>
          <form method="post" action="<?= b() ?>/account/piano/annulla" class="row" style="gap:10px;align-items:center;margin-top:8px"><?= Csrf::field() ?>
            <button class="btn btn--ghost btn--sm">Annulla il cambio</button>
            <span class="small muted">Resti su <?= Support::e($piano['name'] ?? '') ?> anche dopo il rinnovo.</span></form>
        </div>
      <?php endif; ?>
      <?php if (!$stripe): ?><p class="small muted">Abbonamento attivato dal nostro staff<?= $fine ? ', valido fino al ' . Support::e(Support::date($fine)) : '' ?>.</p><?php endif; ?>
      <div class="actions">
        <?php if ($stripe && $sub['status'] !== 'past_due'): ?><a class="btn" href="<?= b() ?>/account/piano">Cambia piano</a><?php endif; ?>
        <?php if ($stripe): ?>
          <form method="post" action="<?= b() ?>/account/rinnovo" style="margin:0"><?= Csrf::field() ?>
            <?php if ((int) $sub['cancel_at_period_end'] === 1): ?>
              <button class="btn" name="rinnovo" value="si">Riattiva il rinnovo automatico</button>
            <?php else: ?>
              <button class="btn btn--ghost" name="rinnovo" value="no">Disattiva il rinnovo automatico</button>
            <?php endif; ?>
          </form>
        <?php endif; ?>
        <?php if ($portale): ?>
          <form method="post" action="<?= b() ?>/account/portale" style="margin:0"><?= Csrf::field() ?>
            <button class="btn btn--ghost">Fatture e metodo di pagamento</button></form>
        <?php endif; ?>
      </div>
    <?php elseif ($ultimo): ?>
      <p><span class="badge badge--alert"><span class="dot"></span>Non attivo</span>
        <?php if ($ultimo['current_period_end']): ?><span class="small muted"> · scaduto il <?= Support::e(Support::date($ultimo['current_period_end'])) ?></span><?php endif; ?></p>
      <p>Le tue guide sono offline, ma niente è stato cancellato. Per rimetterle online ripubblicale: ti chiederemo di riattivare l'abbonamento.</p>
      <div class="actions"><a class="btn" href="<?= b() ?>/pannello">Vai alle mie guide</a>
        <?php if ($portale): ?><form method="post" action="<?= b() ?>/account/portale" style="margin:0"><?= Csrf::field() ?><button class="btn btn--ghost">Fatture precedenti</button></form><?php endif; ?></div>
    <?php else: ?>
      <p>Nessun abbonamento attivo. <?= $piano ? 'Stai configurando con il piano <b>' . Support::e($piano['name']) . '</b>: paghi solo quando pubblichi.' : '' ?></p>
      <div class="actions"><a class="btn btn--ghost" href="<?= b() ?>/piano"><?= $piano ? 'Cambia piano' : 'Scegli un piano' ?></a></div>
    <?php endif; ?>
  </section>

  <?php /* Dati di fatturazione: obbligatori prima del primo pagamento. Vanno a Stripe con il cliente. */
        $fatt = $fatt ?? $acc; $erroriFatt = $erroriFatt ?? []; $torna = $torna ?? '';
        $fv = fn(string $k) => Support::e((string) ($fatt[$k] ?? ''));
        $completi = MHW\Fatturazione::completa($acc);
        // Persona fisica o azienda: alcuni campi valgono solo per un tipo (data-per). Il tipo scelto decide
        // cosa si vede subito; con JavaScript cambia al volo. Senza un tipo scelto, si vede tutto.
        $tipoF = (string) ($fatt['billing_type'] ?? '');
        $per = fn(string $t) => ' data-per="' . $t . '"' . ($tipoF !== '' && $t !== $tipoF ? ' hidden' : '');
        $etichetta = fn(array $per) => implode('', array_map(fn($t, $et) => '<span data-per-etichetta="' . $t . '"'
            . (($tipoF === '' ? $t !== '' : $t !== $tipoF) ? ' hidden' : '') . '>' . $et . '</span>', array_keys($per), $per));
        $campoF = function (string $k, string $et, string $tipo = 'text', string $extra = '', string $aiuto = '', string $solo = '') use ($fv, $erroriFatt, $per): string {
            $err = $erroriFatt[$k] ?? '';
            $desc = trim(($aiuto !== '' ? 'f-' . $k . '-aiuto ' : '') . ($err !== '' ? 'f-' . $k . '-err' : ''));
            return '<div class="field" style="margin:0"' . ($solo !== '' ? $per($solo) : '') . '><label for="f-' . $k . '">' . $et . '</label>'
                . ($aiuto !== '' ? '<p class="help" id="f-' . $k . '-aiuto" style="margin:0 0 6px">' . $aiuto . '</p>' : '')
                . '<input id="f-' . $k . '" name="' . $k . '" type="' . $tipo . '" value="' . $fv($k) . '" ' . $extra
                . ($desc !== '' ? ' aria-describedby="' . $desc . '"' : '') . ($err !== '' ? ' aria-invalid="true"' : '') . '>'
                . ($err !== '' ? '<p class="campo-errore" id="f-' . $k . '-err">' . Support::e($err) . '</p>' : '') . '</div>';
        }; ?>
  <section class="panel stack" id="fatturazione">
    <div class="spread spread--mid">
      <span class="kicker">Dati di fatturazione</span>
      <?php if ($completi && ($acc['billing_type'] ?? '') !== ''): ?><span class="badge badge--pine">Completi</span>
      <?php else: ?><span class="badge badge--ochre">Servono prima del pagamento</span><?php endif; ?>
    </div>
    <?php if ($erroriFatt): ?><p class="note note--err" role="alert">Controlla i campi segnati qui sotto.</p><?php endif; ?>
    <details class="altri-dettagli" style="border:0;padding:0" <?= $erroriFatt || !empty($apriFatt) || $torna !== '' || !$completi ? 'open' : '' ?>>
      <summary><?= ($acc['billing_name'] ?? '') !== '' ? Support::e($acc['billing_name']) . ' · ' . Support::e(($acc['vat'] ?? '') ?: ($acc['cf'] ?? '')) : 'Compila i dati per la fattura' ?></summary>
      <form method="post" action="<?= b() ?>/account/fatturazione" class="stack" style="margin-top:14px" novalidate data-tipo-fatt><?= Csrf::field() ?>
        <?php if ($torna !== ''): ?><input type="hidden" name="torna" value="<?= Support::e($torna) ?>"><?php endif; ?>
        <fieldset class="fieldset" style="margin:0">
          <legend>A chi intestiamo la fattura</legend>
          <div class="scelte scelte--riga">
            <?php foreach (MHW\Fatturazione::TIPI as $k => $et): ?>
              <label class="scelta"><input type="radio" name="billing_type" value="<?= $k ?>" <?= ($fatt['billing_type'] ?? '') === $k ? 'checked' : '' ?>
                <?= isset($erroriFatt['billing_type']) ? 'aria-invalid="true" aria-describedby="f-billing_type-err"' : '' ?>><span class="scelta__testo"><?= $et ?></span></label>
            <?php endforeach; ?>
          </div>
          <?php if (isset($erroriFatt['billing_type'])): ?><p class="campo-errore" id="f-billing_type-err"><?= Support::e($erroriFatt['billing_type']) ?></p><?php endif; ?>
        </fieldset>
        <?= $campoF('billing_name', $etichetta(['' => 'Intestatario', 'privato' => 'Nome e cognome', 'azienda' => 'Ragione sociale']), 'text', 'maxlength="160" autocomplete="name"') ?>
        <div class="grid grid-2 campi-allineati">
          <?= $campoF('vat', 'Partita IVA', 'text', 'maxlength="13" inputmode="numeric" autocomplete="off"', '11 cifre.', 'azienda') ?>
          <?= $campoF('cf', 'Codice fiscale' . $etichetta(['' => '', 'privato' => '', 'azienda' => ' <span class="muted">(facoltativo)</span>']), 'text', 'maxlength="16" autocomplete="off" style="text-transform:uppercase" autocapitalize="characters"', $etichetta(['' => '16 caratteri. Per una società, anche le 11 cifre della partita IVA.', 'privato' => '16 caratteri, come sulla tessera sanitaria.', 'azienda' => '16 caratteri. Per una società, anche le 11 cifre della partita IVA.'])) ?>
          <?= $campoF('sdi', 'Codice destinatario SDI', 'text', 'maxlength="7" autocomplete="off" style="text-transform:uppercase" autocapitalize="characters"', '7 caratteri. Serve questo oppure la PEC.', 'azienda') ?>
          <?= $campoF('pec', 'PEC', 'email', 'maxlength="190" autocomplete="off"', 'Se non hai il codice destinatario.', 'azienda') ?>
        </div>
        <p class="small muted"<?= $per('privato') ?>>Per una persona fisica bastano nome, codice fiscale e indirizzo: la fattura elettronica arriva anche nel tuo cassetto fiscale dell'Agenzia delle Entrate. Hai la partita IVA? Scegli «Azienda o professionista».</p>
        <?= $campoF('billing_address', 'Indirizzo', 'text', 'maxlength="255" autocomplete="street-address"', 'Via e numero civico.') ?>
        <div class="grid grid-3">
          <?= $campoF('billing_postal', 'CAP', 'text', 'maxlength="5" inputmode="numeric" autocomplete="postal-code"') ?>
          <?= $campoF('billing_city', 'Città', 'text', 'maxlength="120" autocomplete="address-level2"') ?>
          <?= $campoF('billing_province', 'Provincia', 'text', 'maxlength="2" autocomplete="address-level1" style="text-transform:uppercase" autocapitalize="characters"', 'Sigla, per esempio PG.') ?>
        </div>
        <p class="small muted">Con questi dati facciamo la fattura elettronica e la mandiamo allo SDI. Non li usiamo per nient'altro.</p>
        <div class="actions"><button class="btn"><?= $torna !== '' ? 'Salva e torna alla pubblicazione' : 'Salva i dati di fatturazione' ?></button></div>
      </form>
    </details>
  </section>

  <?php /* Il tuo account: nome, password, email. Errori sotto il campo, valori conservati. */
        $eP = $erroriProfilo ?? []; $ePw = $erroriPassword ?? []; $eE = $erroriEmail ?? []; $apri = $apri ?? '';
        $err = fn(array $e, string $k, string $id) => isset($e[$k]) ? '<p class="campo-errore" id="' . $id . '-err">' . Support::e($e[$k]) . '</p>' : '';
        $inv = fn(array $e, string $k, string $id) => isset($e[$k]) ? ' aria-invalid="true" aria-describedby="' . $id . '-err"' : ''; ?>
  <section class="panel stack" id="profilo">
    <span class="kicker">Il tuo account</span>
    <p><?= Support::e($user['email']) ?>
      <?= Auth::isVerified($user) ? '<span class="badge badge--pine">Email confermata</span>' : '<span class="badge badge--ochre">Email da confermare</span>' ?></p>
    <?php if (!empty($user['pending_email'])): ?>
      <p class="small muted">In attesa di conferma: <b><?= Support::e($user['pending_email']) ?></b>. Apri il link che ti abbiamo mandato a quell'indirizzo.</p>
    <?php endif; ?>
    <form method="post" action="<?= b() ?>/account/profilo" class="row" style="gap:12px;align-items:flex-end;flex-wrap:wrap"><?= Csrf::field() ?>
      <div class="field" style="margin:0;flex:1 1 220px"><label for="pr-nome">Nome</label>
        <input id="pr-nome" name="name" type="text" required maxlength="120" autocomplete="name" value="<?= Support::e((string) ($_POST['name'] ?? $user['name'])) ?>"<?= $inv($eP, 'name', 'pr-nome') ?>>
        <?= $err($eP, 'name', 'pr-nome') ?></div>
      <button class="btn btn--ghost">Salva il nome</button>
    </form>
    <details class="fieldset"<?= $apri === 'password' ? ' open' : '' ?>>
      <summary class="legend">Cambia la password</summary>
      <form method="post" action="<?= b() ?>/account/password" class="stack" style="gap:12px;margin-top:12px"><?= Csrf::field() ?>
        <input type="text" name="username" value="<?= Support::e($user['email']) ?>" autocomplete="username" hidden>
        <div class="field" style="margin:0"><label for="pw-attuale">Password attuale</label>
          <input id="pw-attuale" name="attuale" type="password" required autocomplete="current-password"<?= $inv($ePw, 'attuale', 'pw-attuale') ?>><?= $err($ePw, 'attuale', 'pw-attuale') ?></div>
        <div class="field" style="margin:0"><label for="pw-nuova">Password nuova</label>
          <p class="help" id="pw-nuova-aiuto" style="margin:0 0 6px">Almeno 8 caratteri.</p>
          <input id="pw-nuova" name="nuova" type="password" required minlength="8" maxlength="72" autocomplete="new-password" aria-describedby="pw-nuova-aiuto"<?= $inv($ePw, 'nuova', 'pw-nuova') ?>><?= $err($ePw, 'nuova', 'pw-nuova') ?></div>
        <div class="field" style="margin:0"><label for="pw-nuova2">Ripeti la password nuova</label>
          <input id="pw-nuova2" name="nuova2" type="password" required minlength="8" maxlength="72" autocomplete="new-password"<?= $inv($ePw, 'nuova2', 'pw-nuova2') ?>><?= $err($ePw, 'nuova2', 'pw-nuova2') ?></div>
        <div class="actions"><button class="btn">Cambia la password</button></div>
        <p class="small muted">Non ricordi quella attuale? <a href="<?= b() ?>/password/dimenticata">Chiedi un link di recupero</a>.</p>
      </form>
    </details>
    <details class="fieldset"<?= $apri === 'email' ? ' open' : '' ?>>
      <summary class="legend">Cambia l'email</summary>
      <form method="post" action="<?= b() ?>/account/email" class="stack" style="gap:12px;margin-top:12px"><?= Csrf::field() ?>
        <div class="field" style="margin:0"><label for="em-nuova">Email nuova</label>
          <p class="help" id="em-nuova-aiuto" style="margin:0 0 6px">Ti mandiamo un link: finché non lo apri, resta valida l'email di adesso.</p>
          <input id="em-nuova" name="email" type="email" required maxlength="190" autocomplete="email" value="<?= Support::e((string) ($emailNuova ?? '')) ?>" aria-describedby="em-nuova-aiuto<?= isset($eE['email']) ? ' em-nuova-err' : '' ?>"<?= isset($eE['email']) ? ' aria-invalid="true"' : '' ?>><?= $err($eE, 'email', 'em-nuova') ?></div>
        <div class="field" style="margin:0"><label for="em-attuale">Password attuale</label>
          <input id="em-attuale" name="attuale" type="password" required autocomplete="current-password"<?= $inv($eE, 'attuale', 'em-attuale') ?>><?= $err($eE, 'attuale', 'em-attuale') ?></div>
        <div class="actions"><button class="btn">Mandami il link di conferma</button></div>
      </form>
    </details>
  </section>

  <?php if ($ordini): ?>
  <section class="stack" style="gap:10px">
    <h2 style="font-size:22px">Pagamenti</h2>
    <?php foreach ($ordini as $o): ?>
      <div class="rowcard"><b class="grow"><?= Support::e($o['package']) ?></b>
        <span class="small muted"><?= Support::e(Support::date($o['created_at'])) ?></span>
        <span><?= Support::e(Support::money((int) $o['amount_cents'], $o['currency'])) ?> <span class="small muted">+ IVA</span></span>
        <span class="badge badge--<?= $o['status'] === 'paid' ? 'pine' : ($o['status'] === 'failed' ? 'alert' : 'paper') ?>"><?= Support::e($statoOrdine[$o['status']] ?? $o['status']) ?></span>
      </div>
    <?php endforeach; ?>
    <p class="tiny muted">Le ricevute dei pagamenti le trovi in «Fatture e metodo di pagamento»; la fattura elettronica arriva nel tuo cassetto fiscale, o alla PEC o al codice destinatario che hai indicato.</p>
  </section>
  <?php endif; ?>
</div>
