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
      <?php if ($perStruttura && $stripe && ($sub['provider_extra_item_id'] ?? '') !== ''): ?>
        <form method="post" action="<?= b() ?>/account/strutture" class="row" style="gap:10px;align-items:flex-end"><?= Csrf::field() ?>
          <div class="field" style="margin:0"><label for="strutture">Numero di strutture</label>
            <input id="strutture" name="strutture" type="number" inputmode="numeric" step="1" required style="max-width:120px"
                   min="<?= (int) $piano['min_quantity'] ?>" max="<?= (int) $piano['max_quantity'] ?>" value="<?= $q ?>"></div>
          <button class="btn btn--ghost btn--sm">Cambia</button>
          <span class="small muted">Prima struttura <?= Support::e(Support::money((int) $piano['price_cents'], $piano['currency'])) ?>, ogni struttura aggiuntiva
            +<?= Support::e(Support::money((int) $piano['extra_price_cents'], $piano['currency'])) ?> / anno.</span>
        </form>
      <?php endif; ?>
      <?php if (!$stripe): ?><p class="small muted">Abbonamento attivato dal nostro staff<?= $fine ? ', valido fino al ' . Support::e(Support::date($fine)) : '' ?>.</p><?php endif; ?>
      <div class="actions">
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
        <?php if ($portale): ?><form method="post" action="<?= b() ?>/account/portale" style="margin:0"><?= Csrf::field() ?><button class="btn btn--ghost">Vecchie fatture</button></form><?php endif; ?></div>
    <?php else: ?>
      <p>Nessun abbonamento attivo. <?= $piano ? 'Stai configurando con il piano <b>' . Support::e($piano['name']) . '</b>: paghi solo quando pubblichi.' : '' ?></p>
      <div class="actions"><a class="btn btn--ghost" href="<?= b() ?>/piano"><?= $piano ? 'Cambia piano' : 'Scegli un piano' ?></a></div>
    <?php endif; ?>
  </section>

  <?php /* Dati di fatturazione: obbligatori prima del primo pagamento. Vanno a Stripe con il cliente. */
        $fatt = $fatt ?? $acc; $erroriFatt = $erroriFatt ?? []; $torna = $torna ?? '';
        $fv = fn(string $k) => Support::e((string) ($fatt[$k] ?? ''));
        $completi = MHW\Fatturazione::completa($acc);
        $campoF = function (string $k, string $et, string $tipo = 'text', string $extra = '', string $aiuto = '') use ($fv, $erroriFatt): string {
            $err = $erroriFatt[$k] ?? '';
            $desc = trim(($aiuto !== '' ? 'f-' . $k . '-aiuto ' : '') . ($err !== '' ? 'f-' . $k . '-err' : ''));
            return '<div class="field" style="margin:0"><label for="f-' . $k . '">' . $et . '</label>'
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
      <form method="post" action="<?= b() ?>/account/fatturazione" class="stack" style="margin-top:14px" novalidate><?= Csrf::field() ?>
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
        <?= $campoF('billing_name', 'Intestatario', 'text', 'maxlength="160" autocomplete="organization"', 'Ragione sociale, oppure nome e cognome.') ?>
        <div class="grid grid-2">
          <?= $campoF('vat', 'Partita IVA', 'text', 'maxlength="13" inputmode="numeric" autocomplete="off"', 'Obbligatoria per aziende e professionisti. 11 cifre.') ?>
          <?= $campoF('cf', 'Codice fiscale', 'text', 'maxlength="16" autocomplete="off" style="text-transform:uppercase"', 'Obbligatorio per i privati. 16 caratteri, o 11 cifre per le società.') ?>
          <?= $campoF('sdi', 'Codice destinatario SDI', 'text', 'maxlength="7" autocomplete="off" style="text-transform:uppercase"', '7 caratteri. Per aziende e professionisti: questo oppure la PEC.') ?>
          <?= $campoF('pec', 'PEC', 'email', 'maxlength="190" autocomplete="off"') ?>
        </div>
        <?= $campoF('billing_address', 'Indirizzo', 'text', 'maxlength="255" autocomplete="street-address"', 'Via e numero civico.') ?>
        <div class="grid grid-3">
          <?= $campoF('billing_postal', 'CAP', 'text', 'maxlength="5" inputmode="numeric" autocomplete="postal-code"') ?>
          <?= $campoF('billing_city', 'Città', 'text', 'maxlength="120" autocomplete="address-level2"') ?>
          <?= $campoF('billing_province', 'Provincia', 'text', 'maxlength="2" autocomplete="address-level1" style="text-transform:uppercase"', 'Sigla, per esempio PG.') ?>
        </div>
        <p class="small muted">Le fatture le emette Stripe con questi dati. Non li usiamo per nient'altro.</p>
        <div class="actions"><button class="btn"><?= $torna !== '' ? 'Salva e torna alla pubblicazione' : 'Salva i dati di fatturazione' ?></button></div>
      </form>
    </details>
  </section>

  <section class="panel stack">
    <span class="kicker">Il tuo account</span>
    <p><?= Support::e($user['name']) ?> · <?= Support::e($user['email']) ?>
      <?= Auth::isVerified($user) ? '<span class="badge badge--pine">Email confermata</span>' : '<span class="badge badge--ochre">Email da confermare</span>' ?></p>
    <p class="small muted">Per cambiare la password usa <a href="<?= b() ?>/password/dimenticata">Password dimenticata</a>: ti mandiamo un link.</p>
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
    <p class="tiny muted">Le fatture le emette Stripe e le trovi in "Fatture e metodo di pagamento".</p>
  </section>
  <?php endif; ?>
</div>
