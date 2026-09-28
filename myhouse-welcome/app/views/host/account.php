<?php
/* Account & Fatturazione: piano, prezzo, stato, rinnovo, scadenza. */
use function MHW\b;
use MHW\{Support, Csrf, Auth};
$title = 'Account & Fatturazione';
$stripe = $sub && $sub['provider'] === 'stripe' && $sub['provider_subscription_id'] !== '';
$fine = $sub['current_period_end'] ?? ($ultimo['current_period_end'] ?? '');
$statoOrdine = ['pending' => 'In attesa', 'awaiting' => 'In verifica', 'paid' => 'Pagato', 'failed' => 'Non riuscito', 'canceled' => 'Annullato', 'expired' => 'Scaduto']; ?>
<div class="stack stack--lg" style="max-width:820px">
  <h1>Account &amp; Fatturazione.</h1>

  <section class="panel stack">
    <span class="kicker">Abbonamento</span>
    <?php if ($sub): ?>
      <div class="spread spread--mid">
        <b style="font-size:24px;font-weight:500"><?= Support::e($piano['name'] ?? '') ?></b>
        <span style="font-size:20px;font-weight:500"><?= Support::e(Support::money((int) ($piano['price_cents'] ?? 0), $piano['currency'] ?? 'EUR')) ?>
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
