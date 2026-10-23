<?php use function MHW\tf;
/* Account & Fatturazione: piano, prezzo, stato, rinnovo, scadenza. */
use function MHW\b; use function MHW\t;
use MHW\{Support, Csrf, Auth, Plans};
$title = t('Account & Fatturazione');
$stripe = $sub && $sub['provider'] === 'stripe' && $sub['provider_subscription_id'] !== '';
$fine = $sub['current_period_end'] ?? ($ultimo['current_period_end'] ?? '');
$statoOrdine = array_map('MHW\\t', ['pending' => 'In attesa', 'awaiting' => 'In verifica', 'paid' => 'Pagato', 'failed' => 'Non riuscito', 'canceled' => 'Annullato', 'expired' => 'Scaduto']); ?>
<div class="stack stack--lg" style="max-width:820px">
  <h1><?= t('Account &amp; Fatturazione.') ?></h1>

  <?php /* 9I · Il codice cliente: da tenere a portata di mano quando si scrive all'assistenza. */
        if (($codCli = MHW\Codici::cliente((int) $acc['id'])) !== ''): ?>
    <section class="panel codice-cliente" aria-labelledby="codice-cliente-titolo">
      <p style="margin:0" id="codice-cliente-titolo"><?= t('Il tuo codice cliente') ?> <b class="codice-cliente__codice" data-codice-cliente><?= Support::e($codCli) ?></b>
        <button type="button" class="btn btn--ghost btn--sm" data-copia="<?= Support::e($codCli) ?>" data-copiato="<?= t('Copiato') ?>" hidden><?= MHW\Icon::svg('copy', 15) ?><?= t('Copia') ?></button></p>
      <p class="small muted" style="margin:0"><?= t('Tienilo a portata di mano quando scrivi all\'assistenza.') ?></p>
    </section>
  <?php endif; ?>

  <section class="panel stack">
    <span class="kicker"><?= t('Abbonamento') ?></span>
    <?php if ($sub): ?>
      <?php $perStruttura = $piano && Plans::perProperty($piano); $q = (int) ($sub['quantity'] ?? 1); ?>
      <div class="spread spread--mid">
        <b style="font-size:24px;font-weight:500"><?= Support::e($piano['name'] ?? '') ?><?= $q > 1 ? ' · ' . tf('{n} strutture', ['{n}' => $q]) : '' ?></b>
        <span style="font-size:20px;font-weight:500"><?= Support::e(Support::money($piano ? Plans::price($piano, $q) : 0, $piano['currency'] ?? 'EUR')) ?>
          <span class="small muted"><?= t('+ IVA / anno') ?></span></span>
      </div>
      <?php if ($perStruttura && $q > 1): /* Portfolio: quanto costa ognuna delle strutture, nell'ordine in cui sono state create */
            $bloccateA = MHW\Entitlements::lockedIds((int) $acc['id']);
            $mie = array_values(array_filter(MHW\Db::all('SELECT id, name FROM properties WHERE account_id = ? AND is_demo = 0 ORDER BY id', [$acc['id']]),
                                             fn($x) => !in_array((int) $x['id'], $bloccateA, true))); ?>
        <div class="scaglioni">
          <p class="scaglioni__titolo"><?= t('Quanto paghi per ogni struttura, all\'anno') ?></p>
          <ul class="scaglioni__elenco">
            <?php for ($n = 1; $n <= $q; $n++): ?>
              <li><span><?= $n ?>ª · <?= isset($mie[$n - 1]) ? Support::e($mie[$n - 1]['name']) : '<span class="muted">' . t('ancora da creare') . '</span>' ?></span>
                <b><?= Support::e(Support::money(Plans::unitPrice($piano, $n), $piano['currency'])) ?></b></li>
            <?php endfor; ?>
          </ul>
          <p class="scaglioni__media"><?= tf('In media {prezzo} a struttura + IVA.', ['{prezzo}' => '<b>' . Support::e(Support::money((int) round(Plans::price($piano, $q) / $q), $piano['currency'])) . '</b>']) ?>
            <?php if ($q < (int) $piano['max_quantity']): ?><?= Support::e(tf('La {n}ª costerebbe {prezzo}.', ['{n}' => $q + 1, '{prezzo}' => Support::money(Plans::unitPrice($piano, $q + 1), $piano['currency'])])) ?><?php endif; ?></p>
        </div>
      <?php endif; ?>
      <?php $inOmaggio = $sub['status'] === 'trialing' && !empty($sub['omaggio_fino']); /* 9D · i mesi gratis di un codice omaggio */ ?>
      <?php if ($sub['status'] === 'past_due'): ?>
        <p class="note note--err"><?= t('L\'ultimo rinnovo non è andato a buon fine. Aggiorna il metodo di pagamento per non andare offline.') ?></p>
      <?php elseif ((int) $sub['cancel_at_period_end'] === 1): ?>
        <p class="note"><?= tf($inOmaggio ? 'Rinnovo automatico disattivato. La guida resta online fino al {data}, poi va offline e non paghi nulla: i contenuti restano salvati.' : 'Rinnovo automatico disattivato. La guida resta online fino al {data}, poi va offline: i contenuti restano salvati.', ['{data}' => '<b>' . Support::e(MHW\Pannello::data($fine)) . '</b>']) ?></p>
      <?php elseif ($inOmaggio): $addOm = MHW\Richiami::prossimoAddebito($sub); ?>
        <p><span class="badge badge--pine"><span class="dot"></span><?= t('In omaggio') ?></span>
          <span class="small"> <?= tf('· Omaggio fino al {data} · poi {prezzo} + IVA l\'anno', ['{data}' => '<b>' . Support::e(MHW\Pannello::data($fine)) . '</b>', '{prezzo}' => Support::e(Support::money($addOm['netto'], $addOm['valuta']))]) ?><?= $addOm['sconto'] > 0 ? ' ' . tf('(il primo addebito con il {n}% di sconto)', ['{n}' => (int) $addOm['percento']]) : '' ?><?= ($sub['card_last4'] ?? '') !== '' ? tf(', sulla carta •••• {n}', ['{n}' => Support::e($sub['card_last4'])]) : '' ?>.</span></p>
        <?php /* 9N · «Il tuo codice omaggio»: le agevolazioni dal primo rinnovo dopo il primo pagamento. */ if (MHW\Inviti::disponibili() || MHW\Giudizi::attivo()): $rOm = MHW\Sconti::rinnovo((int) $acc['id'], $sub); ?>
        <div class="sconto-ok" role="note"><p style="margin:0"><b><?= t('Il tuo codice omaggio') ?></b>: <?= Support::e(tf('Dal primo rinnovo dopo l\'attivazione hai tutti gli sconti, fino al {n}%.', ['{n}' => MHW\Sconti::massimoRinnovo()['totale']])) ?>
          <?= $rOm['percento'] > 0 ? Support::e($rOm['giudizio'] ? tf('Oggi ne hai già il {n}% (inviti {i}%, giudizio {g}%).', ['{n}' => $rOm['percento'], '{i}' => $rOm['inviti'], '{g}' => $rOm['giudizio']]) : tf('Oggi ne hai già il {n}% (inviti {i}%).', ['{n}' => $rOm['percento'], '{i}' => $rOm['inviti']])) : '' ?></p></div>
        <?php endif; ?>
      <?php else: ?>
        <p><span class="badge badge--pine"><span class="dot"></span><?= t('Attivo') ?></span>
          <?php if ($fine): ?><span class="small muted"> · <?= Support::e(tf('si rinnova il {data}', ['{data}' => MHW\Pannello::data($fine)])) ?></span><?php endif; ?></p>
      <?php endif; ?>
      <?php if (($avuto = MHW\Sconti::avuto((int) $acc['id']))): /* lo sconto del primo anno (6E) */ ?>
        <p class="small"><?= t('Sconto del primo anno:') ?> <b><?= "\u{2212}" . Support::e(Support::money((int) $avuto['discount_cents'])) ?></b> (<?= Support::e($avuto['code']) ?>).
          <?php if ($fine): ?><?= Support::e(tf('Rinnovo a prezzo pieno il {data}.', ['{data}' => MHW\Pannello::data($fine)])) ?><?php endif; ?></p>
      <?php endif; ?>
      <?php if ($stripe && MHW\Inviti::puoInvitare($acc) && ($invAcc = MHW\Inviti::stato($acc)) && $invAcc['percento'] > 0): /* lo sconto degli inviti sul prossimo rinnovo */ ?>
        <div class="sconto-ok" role="status">
          <p class="sconto-ok__prezzo" style="margin:0"><s><?= Support::e(Support::money((int) $invAcc['prezzo'], $invAcc['valuta'])) ?></s>
            <b><?= Support::e(Support::money((int) $invAcc['scontato'], $invAcc['valuta'])) ?></b> <?= t('+ IVA al prossimo rinnovo') ?></p>
          <p style="margin:0"><?= t('Sconto inviti') ?> <b><?= "\u{2212}" . (int) $invAcc['percento'] ?>%</b>:
            <?= (int) $invAcc['validi'] === 1 ? t('1 amico ha pubblicato la sua guida') : tf('{n} amici hanno pubblicato la loro guida', ['{n}' => (int) $invAcc['validi']]) ?>.
            <?= Support::e(tf('Dal rinnovo successivo torni a {prezzo} + IVA.', ['{prezzo}' => Support::money((int) $invAcc['prezzo'], $invAcc['valuta'])])) ?>
            <a href="<?= b() ?>/inviti"><?= t('Invita altri amici') ?></a></p>
          <?php if (!$invAcc['automatico']): ?><p class="small" style="margin:0"><?= t('Il rinnovo automatico è disattivato: lo sconto vale solo sul rinnovo.') ?></p><?php endif; ?>
        </div>
      <?php endif; ?>
      <?php /* Un passaggio a un piano più economico, programmato per il rinnovo (6H). */
            if ($stripe && !empty($sub['next_package_version_id']) && ($prossimo = Plans::version((int) $sub['next_package_version_id']))):
              $qn = max(1, (int) ($sub['next_quantity'] ?? 1)); ?>
        <div class="note stack" style="display:block" role="status">
          <p style="margin:0"><?= tf('Dal {data} passi a {piano}: {prezzo} + IVA / anno.', ['{data}' => Support::e(MHW\Pannello::data($fine)), '{piano}' => '<b>' . Support::e($prossimo['name']) . (Plans::perProperty($prossimo) ? ' · ' . tf('{n} strutture', ['{n}' => $qn]) : '') . '</b>', '{prezzo}' => Support::e(Support::money(Plans::price($prossimo, $qn), $prossimo['currency']))]) ?></p>
          <form method="post" action="<?= b() ?>/account/piano/annulla" class="row" style="gap:10px;align-items:center;margin-top:8px"><?= Csrf::field() ?>
            <button class="btn btn--ghost btn--sm"><?= t('Annulla il cambio') ?></button>
            <span class="small muted"><?= Support::e(tf('Resti su {piano} anche dopo il rinnovo.', ['{piano}' => $piano['name'] ?? ''])) ?></span></form>
        </div>
      <?php endif; ?>
      <?php if (!$stripe): ?><p class="small muted"><?= $fine ? Support::e(tf('Abbonamento attivato dal nostro staff, valido fino al {data}.', ['{data}' => MHW\Pannello::data($fine)])) : t('Abbonamento attivato dal nostro staff.') ?></p><?php endif; ?>
      <?php /* 7B: «Passa a …» per ogni altro piano in vendita (su e giù), dentro il flusso del cambio di piano. */
            $altri = array_values(array_filter(Plans::public(), fn($o) => $o['code'] !== ($piano['code'] ?? ''))); ?>
      <?php if ($stripe && $sub['status'] !== 'past_due' && $altri): ?>
        <div class="row" style="gap:8px">
          <?php foreach ($altri as $o): $qO = Plans::perProperty($o) ? max((int) $o['min_quantity'], $q) : 1; ?>
            <a class="btn btn--ghost btn--sm" href="<?= b() ?>/account/piano/conferma?piano=<?= rawurlencode((string) $o['code']) ?><?= Plans::perProperty($o) ? '&amp;strutture=' . min((int) $o['max_quantity'], $qO) : '' ?>"><?= Support::e(tf('Passa a {piano}', ['{piano}' => $o['name']])) ?></a>
          <?php endforeach; ?>
        </div>
      <?php elseif (!$stripe): ?>
        <div class="row" style="gap:8px">
          <a class="btn btn--ghost btn--sm" href="<?= Support::e(Support::mailto('Cambio piano — ' . ($user['email'] ?? ''), "Ciao, vorrei cambiare piano.\n\nPiano attuale: " . ($piano['name'] ?? '') . ($q > 1 ? " ($q strutture)" : '') . "\nPiano che vorrei:\n")) ?>"><?= t('Chiedi il cambio di piano') ?></a>
        </div>
      <?php endif; ?>
      <div class="actions">
        <?php if ($stripe && $sub['status'] !== 'past_due'): ?><a class="btn" href="<?= b() ?>/account/piano"><?= t('Cambia piano') ?></a><?php endif; ?>
        <?php if ($stripe): ?>
          <form method="post" action="<?= b() ?>/account/rinnovo" style="margin:0"><?= Csrf::field() ?>
            <?php if ((int) $sub['cancel_at_period_end'] === 1): ?>
              <button class="btn" name="rinnovo" value="si"><?= t('Riattiva il rinnovo automatico') ?></button>
            <?php else: ?>
              <button class="btn btn--ghost" name="rinnovo" value="no"><?= t('Disattiva il rinnovo') ?></button>
              <?php if ($inOmaggio): ?><span class="small muted" style="align-self:center"><?= Support::e(tf('Fino al {data} la guida resta online e non paghi nulla.', ['{data}' => MHW\Pannello::data($fine)])) ?></span><?php endif; ?>
            <?php endif; ?>
          </form>
        <?php endif; ?>
        <?php if ($portale): /* 9O · il portale di Stripe solo per la carta: le fatture le manda Adamo (sotto, «Pagamenti e fatture») */ ?>
          <form method="post" action="<?= b() ?>/account/portale" style="margin:0"><?= Csrf::field() ?>
            <button class="btn btn--ghost"><?= t('Cambia il metodo di pagamento') ?></button></form>
        <?php endif; ?>
      </div>
    <?php elseif ($ultimo): ?>
      <p><span class="badge badge--alert"><span class="dot"></span><?= t('Non attivo') ?></span>
        <?php if ($ultimo['current_period_end']): ?><span class="small muted"> · <?= Support::e(tf('scaduto il {data}', ['{data}' => MHW\Pannello::data($ultimo['current_period_end'])])) ?></span><?php endif; ?></p>
      <p><?= t('Le tue guide sono offline, ma niente è stato cancellato. Per rimetterle online ripubblicale: ti chiederemo di riattivare l\'abbonamento.') ?></p>
      <div class="actions"><a class="btn" href="<?= b() ?>/pannello"><?= t('Vai alle mie guide') ?></a></div>
    <?php else: ?>
      <p><?= t('Nessun abbonamento attivo.') ?> <?= $piano ? tf('Stai configurando con il piano {piano}: paghi solo quando pubblichi.', ['{piano}' => '<b>' . Support::e($piano['name']) . '</b>']) : '' ?></p>
      <?php /* 9A · finché non si è pagato il primo abbonamento: gli sconti che valgono e il campo per un codice sconto o di invito. */
            if (!MHW\Db::one("SELECT id FROM orders WHERE account_id = ? AND status = 'paid'", [(int) $acc['id']])) {
                $pv = $piano; $quantita = (int) ($acc['intended_quantity'] ?? 1) ?: 1; $torna = '/account'; include __DIR__ . '/_sconto.php';
            } ?>
      <div class="actions"><a class="btn btn--ghost" href="<?= b() ?>/piano"><?= $piano ? t('Cambia piano') : t('Scegli un piano') ?></a></div>
    <?php endif; ?>
  </section>

  <?php /* Dati di fatturazione: obbligatori prima del primo pagamento. Vanno a Stripe con il cliente. */
        $torna = $torna ?? '';
        ob_start(); include __DIR__ . '/_fatturazione_form.php'; $moduloFatt = ob_get_clean(); ?>
  <?php /* 9G · Traduzioni suggerite: il contatore dell'anno, solo con il piano che le comprende */
        if (MHW\Migrator::tableExists('translation_usage') && MHW\Traduttore::nelPiano((int) $acc['id'])): ?>
  <section class="panel stack" id="traduzioni" style="gap:10px">
    <span class="kicker"><?= t('Traduzioni suggerite') ?></span>
    <?php $annoT = MHW\Traduttore::contatore((int) $acc['id']); include __DIR__ . '/_traduzioni_contatore.php'; ?>
    <p class="small muted" style="margin:0"><?= t('Comprese nel tuo piano entro un limite annuale di caratteri, per tutte le tue guide. Le chiedi dalla pagina di ogni lingua.') ?></p>
  </section>
  <?php endif; ?>
  <section class="panel stack" id="fatturazione">
    <div class="spread spread--mid">
      <span class="kicker"><?= t('Dati di fatturazione') ?></span>
      <?php if ($completi && ($acc['billing_type'] ?? '') !== ''): ?><span class="badge badge--pine"><?= t('Completi') ?></span>
      <?php else: ?><span class="badge badge--ochre"><?= t('Servono prima del pagamento') ?></span><?php endif; ?>
    </div>
    <?php if ($erroriFatt): ?><p class="note note--err" role="alert"><?= t('Controlla i campi segnati qui sotto.') ?></p><?php endif; ?>
    <details class="altri-dettagli" style="border:0;padding:0" <?= $erroriFatt || !empty($apriFatt) || $torna !== '' || !$completi ? 'open' : '' ?>>
      <summary><?= ($acc['billing_name'] ?? '') !== '' ? Support::e($acc['billing_name']) . ' · ' . Support::e(($acc['vat'] ?? '') ?: ($acc['cf'] ?? '')) : t('Compila i dati per la fattura') ?></summary>
      <?= $moduloFatt ?>
    </details>
    <?php /* 9H · Le domande su fatture e pagamenti vanno all'email amministrazione (9I: con il codice cliente nell'oggetto). */
          $ammin = Support::amministrazione(); if ($ammin !== ''): $codFatt = (string) ($acc['code'] ?? ''); ?>
      <p class="small muted" style="margin:0" id="fatture-domande"><?= t('Domande su fatture e pagamenti? Scrivi a') ?>
        <a href="mailto:<?= Support::e($ammin) ?>?subject=<?= rawurlencode(($codFatt !== '' ? '[' . $codFatt . '] ' : '') . 'Fatturazione') ?>"><?= Support::e($ammin) ?></a>.</p>
    <?php endif; ?>
  </section>

  <?php /* Il tuo account: nome, password, email. Errori sotto il campo, valori conservati. */
        $eP = $erroriProfilo ?? []; $ePw = $erroriPassword ?? []; $eE = $erroriEmail ?? []; $apri = $apri ?? '';
        $err = fn(array $e, string $k, string $id) => isset($e[$k]) ? '<p class="campo-errore" id="' . $id . '-err">' . Support::e(t($e[$k])) . '</p>' : '';
        $inv = fn(array $e, string $k, string $id) => isset($e[$k]) ? ' aria-invalid="true" aria-describedby="' . $id . '-err"' : ''; ?>
  <section class="panel stack" id="profilo">
    <span class="kicker"><?= t('Il tuo account') ?></span>
    <p><?= Support::e($user['email']) ?>
      <?= Auth::isVerified($user) ? '<span class="badge badge--pine">' . t('Email confermata') . '</span>' : '<span class="badge badge--ochre">' . t('Email da confermare') . '</span>' ?></p>
    <?php if (!empty($user['pending_email'])): ?>
      <p class="small muted"><?= t('In attesa di conferma:') ?> <b><?= Support::e($user['pending_email']) ?></b><?= t('. Apri il link che ti abbiamo mandato a quell\'indirizzo.') ?></p>
    <?php endif; ?>
    <form method="post" action="<?= b() ?>/account/profilo" class="row" style="gap:12px;align-items:flex-end;flex-wrap:wrap"><?= Csrf::field() ?>
      <div class="field" style="margin:0;flex:1 1 220px"><label for="pr-nome"><?= t('Nome') ?></label>
        <input id="pr-nome" name="name" type="text" required maxlength="120" autocomplete="name" value="<?= Support::e((string) ($_POST['name'] ?? $user['name'])) ?>"<?= $inv($eP, 'name', 'pr-nome') ?>>
        <?= $err($eP, 'name', 'pr-nome') ?></div>
      <button class="btn"><?= t('Salva il nome') ?></button>
    </form>
    <details class="fieldset" id="password"<?= $apri === 'password' ? ' open' : '' ?>>
      <summary class="legend"><?= t('Cambia la password') ?></summary>
      <form method="post" action="<?= b() ?>/account/password" class="stack" style="gap:12px;margin-top:12px"><?= Csrf::field() ?>
        <input type="text" name="username" value="<?= Support::e($user['email']) ?>" autocomplete="username" hidden>
        <div class="field" style="margin:0"><label for="pw-attuale"><?= t('Password attuale') ?></label>
          <input id="pw-attuale" name="attuale" type="password" required autocomplete="current-password"<?= $inv($ePw, 'attuale', 'pw-attuale') ?>><?= $err($ePw, 'attuale', 'pw-attuale') ?></div>
        <div class="field" style="margin:0"><label for="pw-nuova"><?= t('Password nuova') ?></label>
          <p class="help" id="pw-nuova-aiuto" style="margin:0 0 6px"><?= t('Almeno 8 caratteri.') ?></p>
          <input id="pw-nuova" name="nuova" type="password" required minlength="<?= MHW\Auth::PASSWORD_MIN ?>" maxlength="72" autocomplete="new-password" aria-describedby="pw-nuova-aiuto"<?= $inv($ePw, 'nuova', 'pw-nuova') ?>><?= $err($ePw, 'nuova', 'pw-nuova') ?></div>
        <div class="field" style="margin:0"><label for="pw-nuova2"><?= t('Ripeti la password nuova') ?></label>
          <input id="pw-nuova2" name="nuova2" type="password" required minlength="<?= MHW\Auth::PASSWORD_MIN ?>" maxlength="72" autocomplete="new-password"<?= $inv($ePw, 'nuova2', 'pw-nuova2') ?>><?= $err($ePw, 'nuova2', 'pw-nuova2') ?></div>
        <div class="actions"><button class="btn"><?= t('Cambia la password') ?></button></div>
        <p class="small muted"><?= t('Non ricordi quella attuale?') ?> <a href="<?= b() ?>/password/dimenticata"><?= t('Chiedi un link di recupero') ?></a>.</p>
      </form>
    </details>
    <details class="fieldset"<?= $apri === 'email' ? ' open' : '' ?>>
      <summary class="legend"><?= t('Cambia l\'email') ?></summary>
      <form method="post" action="<?= b() ?>/account/email" class="stack" style="gap:12px;margin-top:12px"><?= Csrf::field() ?>
        <div class="field" style="margin:0"><label for="em-nuova"><?= t('Email nuova') ?></label>
          <p class="help" id="em-nuova-aiuto" style="margin:0 0 6px"><?= t('Ti mandiamo un link: finché non lo apri, resta valida l\'email di adesso.') ?></p>
          <input id="em-nuova" name="email" type="email" required maxlength="190" autocomplete="email" value="<?= Support::e((string) ($emailNuova ?? '')) ?>" aria-describedby="em-nuova-aiuto<?= isset($eE['email']) ? ' em-nuova-err' : '' ?>"<?= isset($eE['email']) ? ' aria-invalid="true"' : '' ?>><?= $err($eE, 'email', 'em-nuova') ?></div>
        <div class="field" style="margin:0"><label for="em-attuale"><?= t('Password attuale') ?></label>
          <input id="em-attuale" name="attuale" type="password" required autocomplete="current-password"<?= $inv($eE, 'attuale', 'em-attuale') ?>><?= $err($eE, 'attuale', 'em-attuale') ?></div>
        <div class="actions"><button class="btn"><?= t('Mandami il link di conferma') ?></button></div>
      </form>
    </details>
    <?php /* 8J · i documenti accettati e gli accessi dell'assistenza al tuo account (ognuno registrato: data e chi) */
          $accessiA = MHW\Db::all("SELECT l.created_at, u.name FROM audit_log l LEFT JOIN users u ON u.id = l.actor_user_id
                                   WHERE l.action = 'impersonate.start' AND l.target_user_id = ? ORDER BY l.id DESC LIMIT 20", [(int) $user['id']]); ?>
    <?php /* 9F·11 · «Pannello in: Italiano / English»: di serie dalla lingua principale della guida (users.panel_lang) */
          $pl = (string) ($user['panel_lang'] ?? ''); ?>
    <form method="post" action="<?= b() ?>/account/pannello-lingua" class="field" style="margin:0" id="pannello-lingua"><?= Csrf::field() ?>
      <span class="label" id="pannello-lingua-et"><?= t('Lingua del pannello') ?></span>
      <div class="scelte scelte--riga" role="radiogroup" aria-labelledby="pannello-lingua-et">
        <label class="scelta"><input type="radio" name="pannello_lingua" value="it" <?= $pl === 'it' ? 'checked' : '' ?>><span class="scelta__testo"><?= t('Italiano') ?></span></label>
        <label class="scelta"><input type="radio" name="pannello_lingua" value="en" <?= $pl === 'en' ? 'checked' : '' ?>><span class="scelta__testo"><?= t('English') ?></span></label>
        <label class="scelta"><input type="radio" name="pannello_lingua" value="" <?= $pl === '' ? 'checked' : '' ?>><span class="scelta__testo"><?= t('Di serie') ?></span></label>
      </div>
      <p class="help" style="margin:6px 0 0"><?= t('Di serie: quella della lingua principale della guida (inglese se non scrivi in italiano).') ?></p>
      <div class="actions" style="margin-top:8px"><button class="btn btn--ghost btn--sm"><?= t('Salva') ?></button></div>
    </form>
    <details class="fieldset" id="privacy-account">
      <summary class="legend"><?= t('Documenti accettati e accessi dell\'assistenza') ?></summary>
      <div class="stack" style="gap:8px;margin-top:12px">
        <p class="small"><?= tf('Hai accettato i {termini} (versione {vt}), l\'{dpa} (versione {vd}) e letto l\'{privacy} (versione {vp}).', [
              '{termini}' => '<a href="' . b() . '/termini">' . t('Termini') . '</a>', '{vt}' => Support::e(($user['terms_version'] ?? '') ?: '—'),
              '{dpa}' => '<a href="' . b() . '/dpa">' . t('Accordo sul trattamento dei dati') . '</a>', '{vd}' => Support::e(($user['dpa_version'] ?? '') ?: '—'),
              '{privacy}' => '<a href="' . b() . '/privacy">' . t('Informativa privacy') . '</a>', '{vp}' => Support::e(($user['privacy_version'] ?? '') ?: '—')]) ?>
          <?= tf('Per una copia dei tuoi dati o per chiudere l\'account scrivi a {email}.', ['{email}' => '<a href="mailto:' . Support::e(Support::contatto()) . '">' . Support::e(Support::contatto()) . '</a>']) ?></p>
        <?php if ($accessiA): ?>
          <p class="small" style="margin:0"><b><?= t('Accessi dell\'assistenza al tuo account') ?></b></p>
          <ul class="small" style="margin:0">
            <?php foreach ($accessiA as $x): ?><li><?= Support::e(MHW\Pannello::data($x['created_at'])) ?>, <?= Support::e(tf('ore {ora} UTC', ['{ora}' => gmdate('H:i', (int) strtotime((string) $x['created_at']))])) ?> · <?= Support::e(trim(explode(' ', trim((string) $x['name']))[0] ?? '') ?: t('amministratore')) ?></li><?php endforeach; ?>
          </ul>
        <?php else: ?><p class="small muted" style="margin:0"><?= t('L\'assistenza non è mai entrata nel tuo account. Se succede, lo trovi scritto qui.') ?></p><?php endif; ?>
      </div>
    </details>
  </section>

  <?php $rq = $giudizioRiquadro ?? null; /* 9L: il programma ha la sua pagina (Giudizio con sconto); qui lo stato in breve e il link */
        if ($rq && $rq['stato'] !== 'nascosto'): $pctG = MHW\Giudizi::percentuale(); $schA = MHW\Vetrina::scheda((int) $acc['id']); ?>
  <section class="panel stack" id="giudizio" aria-labelledby="giudizio-titolo" style="gap:10px">
    <h2 id="giudizio-titolo" style="font-size:22px;margin:0"><?= t('Giudizio con sconto') ?></h2>
    <?php if ($rq['sconto'] !== ''): ?><span class="giudizio-invito__stato"><?= Support::e($rq['sconto']) ?></span><?php endif; ?>
    <p class="small" style="margin:0"><?php if ($schA && !empty($schA['ritirato_at'])): ?><?= Support::e(tf('Ti sei ritirato dal programma il {data}: la scheda e il giudizio non si vedono sul sito.', ['{data}' => MHW\Pannello::data((string) $schA['ritirato_at'])])) ?>
      <?php elseif ($rq['aderito']): ?><?= t('Hai aderito: la tua scheda tra «Le guide dei nostri host», le immagini mostrate, il giudizio e il ritiro sono nella pagina del programma.') ?>
      <?php elseif ($rq['stato'] === 'invito'): ?><?= Support::e(tf('Il {n}% sul prossimo rinnovo e la tua guida tra «Le guide dei nostri host». Lo sconto non dipende dal voto.', ['{n}' => $pctG])) ?>
      <?php else: ?><?= t('Ti piace MyHouse Welcome? Raccontacelo: un voto e due righe.') ?><?php endif; ?></p>
    <a class="btn btn--ghost btn--sm" style="align-self:flex-start" href="<?= b() ?>/giudizio"><?= t('Apri Giudizio con sconto') ?></a>
  </section>
  <?php elseif (!empty($giudizio)): /* 7D: il giudizio sulla piattaforma e il consenso, ritirabile */ ?>
  <section class="panel stack" id="giudizio" aria-labelledby="giudizio-titolo" style="gap:10px">
    <h2 id="giudizio-titolo" style="font-size:22px;margin:0"><?= t('Il tuo giudizio su MyHouse Welcome') ?></h2>
    <p style="margin:0"><span class="stelline" aria-label="<?= Support::e(tf('{n} su 5', ['{n}' => (int) $giudizio['rating']])) ?>"><?= str_repeat('★', (int) $giudizio['rating']) . str_repeat('☆', 5 - (int) $giudizio['rating']) ?></span>
      «<?= Support::e($giudizio['body']) ?>»</p>
    <?php if (!empty($giudizio['withdrawn_at'])): ?>
      <p class="small muted" style="margin:0"><?= Support::e(tf('Hai ritirato il consenso il {data}: il giudizio non si vede sul sito.', ['{data}' => MHW\Pannello::data($giudizio['withdrawn_at'])])) ?></p>
    <?php elseif (!empty($giudizio['consent_at'])): ?>
      <p class="small muted" style="margin:0"><?= $giudizio['status'] === 'pubblicata' ? t('È pubblicato sul sito.') : t('Ci hai dato il consenso a pubblicarlo.') ?>
        <?= Support::e(tf('Consenso dato il {data}.', ['{data}' => MHW\Pannello::data($giudizio['consent_at'])])) ?></p>
      <form method="post" action="<?= b() ?>/account/giudizio/ritira" style="margin:0"><?= MHW\Csrf::field() ?>
        <button class="btn btn--ghost btn--sm"><?= t('Ritira il consenso') ?></button></form>
    <?php else: ?>
      <p class="small muted" style="margin:0"><?= t('Senza il consenso resta tra noi: non lo pubblichiamo.') ?></p>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <?php /* 9O · «Pagamenti e fatture»: i pagamenti con codice, piano, imponibile, IVA, totale, stato e metodo. Le fatture le manda Adamo. */
        $pagamenti = MHW\Pagamenti::elenco((int) $acc['id']);
        if ($pagamenti): $statiP = ['pagato' => ['Pagato', 'pine'], 'in verifica' => ['In verifica', 'sea'], 'non riuscito' => ['Non riuscito', 'alert'], 'rimborsato' => ['Rimborsato', 'ochre'], 'rimborso in corso' => ['Rimborso in corso', 'ochre']];
              $tipiP = ['nuovo' => 'Attivazione', 'cambio' => 'Cambio di piano', 'rinnovo' => 'Rinnovo', 'rimborso' => 'Rimborso'];
              $metodiP = ['carta' => 'Carta', 'sepa' => 'Addebito SEPA', 'bonifico' => 'Bonifico', '' => '—']; ?>
  <section class="panel stack" id="pagamenti" aria-labelledby="pagamenti-titolo" style="gap:10px">
    <h2 id="pagamenti-titolo" style="font-size:22px;margin:0"><?= t('Pagamenti e fatture') ?></h2>
    <p class="small muted" style="margin:0"><?= tf('La fattura elettronica ti arriva per email da Adamo con la copia di cortesia in PDF; l\'originale è nel tuo cassetto fiscale o al tuo codice destinatario/PEC. Per richieste sulle fatture: {email}.', ['{email}' => '<a href="mailto:' . Support::e(Support::amministrazione()) . '">' . Support::e(Support::amministrazione()) . '</a>']) ?></p>
    <div class="tablewrap"><table class="data">
      <thead><tr><th><?= t('Data') ?></th><th><?= t('Codice') ?></th><th><?= t('Cosa') ?></th><th><?= t('Imponibile') ?></th><th><?= t('IVA') ?></th><th><?= t('Totale') ?></th><th><?= t('Stato') ?></th><th><?= t('Metodo') ?></th></tr></thead>
      <tbody><?php foreach ($pagamenti as $pg): [$stEt, $stCol] = $statiP[$pg['stato']] ?? [$pg['stato'], 'paper']; ?>
        <tr><td class="small"><?= Support::e(MHW\Pannello::data($pg['data'])) ?></td>
          <td><?php if ($pg['codice'] !== ''): ?><code class="codice-mw"><?= Support::e($pg['codice']) ?></code><?php endif; ?></td>
          <td class="small"><?= Support::e(t($tipiP[$pg['tipo']] ?? $pg['tipo'])) ?><?= $pg['piano'] !== '' ? ' · ' . Support::e($pg['piano']) : '' ?></td>
          <td class="small"><?= $pg['imponibile'] !== null ? Support::e(Support::money((int) $pg['imponibile'])) : '—' ?></td>
          <td class="small"><?= $pg['iva'] !== null ? Support::e(Support::money((int) $pg['iva'])) : '—' ?></td>
          <td><?= $pg['totale'] !== null ? '<b>' . Support::e(Support::money((int) $pg['totale'])) . '</b>' : '—' ?></td>
          <td><span class="badge badge--<?= $stCol ?>"><?= Support::e(t($stEt)) ?></span></td>
          <td class="small"><?= Support::e(t($metodiP[$pg['metodo']] ?? $pg['metodo'])) ?></td></tr>
      <?php endforeach; ?></tbody></table></div>
  </section>
  <?php endif; ?>
</div>
