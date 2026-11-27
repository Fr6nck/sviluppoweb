<?php
/* 10H · La scheda di un account eliminato da «Elimina account» con pagamenti veri: restano solo i dati che la legge chiede di
   conservare per 10 anni (fatturazione, ordini, abbonamenti, rimborsi, codici dei pagamenti). Niente guide, niente accesso.
   Riceve $acc, $codice, $quando, $fino, $motivo, $admin, $stripe, $cancellati, $orders, $subs, $rimborsi, $pagamenti. */
use function MHW\b;
use MHW\Support;
$title = $codice . ' — Account eliminato';
$statiO = ['paid' => 'Pagato', 'refunded' => 'Rimborsato', 'pending' => 'In attesa', 'awaiting' => 'In verifica', 'failed' => 'Non riuscito', 'canceled' => 'Annullato', 'expired' => 'Scaduto'];
$statiS = ['active' => 'Attivo', 'trialing' => 'In omaggio', 'past_due' => 'Pagamento non riuscito', 'canceled' => 'Chiuso', 'refunded' => 'Rimborsato', 'expired' => 'Scaduto'];
$fatt = array_filter([(string) $acc['billing_name'], trim(($acc['vat'] ? 'P.IVA ' . $acc['vat'] : '') . ' ' . ($acc['cf'] ? 'C.F. ' . $acc['cf'] : '')),
                      trim($acc['billing_address'] . ', ' . $acc['billing_postal'] . ' ' . $acc['billing_city'] . ($acc['billing_province'] ? ' (' . $acc['billing_province'] . ')' : ''), ' ,'),
                      $acc['sdi'] ? 'SDI ' . $acc['sdi'] : '', $acc['pec'] ? 'PEC ' . $acc['pec'] : '']); ?>
<div class="stack stack--lg">
  <div class="stack stack--sm">
    <a class="small" href="<?= b() ?>/admin/clienti">← Clienti</a>
    <h1>Account eliminato. <span class="badge badge--alert" style="vertical-align:middle"><?= Support::e($codice) ?></span></h1>
    <p class="small muted" style="max-width:760px">Eliminato il <b><?= Support::e(Support::date($quando)) ?></b><?= $admin !== '' ? ' da ' . Support::e($admin) : '' ?><?= $motivo !== '' ? ' · motivo: «' . Support::e($motivo) . '»' : '' ?>.
      Guide, foto, accesso e dati personali sono cancellati; l'email è di nuovo libera. Restano, per legge, solo i dati qui sotto: da conservare almeno fino al <b><?= Support::e(Support::date($fino)) ?></b> (10 anni, art. 2220 c.c.).</p>
  </div>

  <div class="grid grid-2" style="align-items:start">
    <section class="panel stack" style="gap:8px" aria-labelledby="ee-fatt">
      <h2 id="ee-fatt" style="font-size:19px;margin:0">Dati di fatturazione</h2>
      <?php if (!$fatt): ?><p class="small muted" style="margin:0">Nessun dato di fatturazione.</p>
      <?php else: ?><p class="small" style="margin:0"><?= implode('<br>', array_map(fn($x) => Support::e($x), $fatt)) ?></p><?php endif; ?>
      <?php if ((string) $acc['stripe_customer_id'] !== ''): ?><p class="small muted" style="margin:0">Cliente Stripe <code><?= Support::e($acc['stripe_customer_id']) ?></code> (le fatture sono lì e in Adamo).</p><?php endif; ?>
    </section>
    <section class="panel stack" style="gap:8px" aria-labelledby="ee-canc">
      <h2 id="ee-canc" style="font-size:19px;margin:0">Cosa si è cancellato</h2>
      <?php if (!$cancellati): ?><p class="small muted" style="margin:0">Nel Registro azioni non c'è il dettaglio.</p>
      <?php else: ?><ul class="small" style="margin:0;padding-left:18px"><?php foreach ($cancellati as $c): ?><li><?= Support::e((string) $c) ?></li><?php endforeach; ?></ul><?php endif; ?>
      <?php if ($stripe): ?><p class="small muted" style="margin:0">Abbonamenti chiusi su Stripe: <?= Support::e(implode(', ', array_map('strval', $stripe))) ?>.</p><?php endif; ?>
    </section>
  </div>

  <section class="stack" style="gap:10px" aria-labelledby="ee-ord">
    <h2 id="ee-ord" style="font-size:22px">Ordini</h2>
    <?php if (!$orders): ?><p class="small muted">Nessun ordine.</p><?php else: ?>
    <div class="tablewrap"><table class="data resp">
      <thead><tr><th>Data</th><th>Codice</th><th>Piano</th><th>Importo</th><th>Pagamento</th><th>Stato</th></tr></thead>
      <tbody><?php foreach ($orders as $o): ?>
        <tr><td data-l="Data" class="small"><?= Support::e(Support::date((string) $o['created_at'])) ?></td>
          <td data-l="Codice"><code><?= Support::e((string) ($o['code'] ?? '')) ?></code></td>
          <td data-l="Piano"><?= Support::e((string) ($o['package'] ?? '—')) ?></td>
          <td data-l="Importo"><?= Support::e(Support::money((int) $o['amount_cents'], (string) $o['currency'])) ?></td>
          <td data-l="Pagamento" class="small"><?= Support::e((string) $o['provider']) ?><?= (string) $o['provider_session_id'] !== '' ? '<br><span class="tiny muted">' . Support::e((string) $o['provider_session_id']) . '</span>' : '' ?></td>
          <td data-l="Stato" class="small"><?= Support::e($statiO[$o['status']] ?? (string) $o['status']) ?></td></tr>
      <?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
  </section>

  <div class="grid grid-2" style="align-items:start">
    <section class="panel stack" style="gap:8px" aria-labelledby="ee-abb">
      <h2 id="ee-abb" style="font-size:19px;margin:0">Abbonamenti</h2>
      <?php if (!$subs): ?><p class="small muted" style="margin:0">Nessun abbonamento.</p><?php else: ?>
        <ul class="small" style="margin:0;padding-left:18px"><?php foreach ($subs as $s): ?>
          <li><?= Support::e((string) ($s['package'] ?? '—')) ?> · <?= Support::e($statiS[$s['status']] ?? (string) $s['status']) ?>
            <?= (string) $s['current_period_start'] !== '' ? ' · dal ' . Support::e(Support::date((string) $s['current_period_start'])) : '' ?><?= (string) $s['current_period_end'] !== '' ? ' al ' . Support::e(Support::date((string) $s['current_period_end'])) : '' ?>
            <?= (string) $s['provider_subscription_id'] !== '' ? '<br><span class="tiny muted">' . Support::e((string) $s['provider_subscription_id']) . '</span>' : '' ?></li>
        <?php endforeach; ?></ul>
      <?php endif; ?>
    </section>
    <section class="panel stack" style="gap:8px" aria-labelledby="ee-rimb">
      <h2 id="ee-rimb" style="font-size:19px;margin:0">Rimborsi e codici dei pagamenti</h2>
      <?php if (!$rimborsi): ?><p class="small muted" style="margin:0">Nessun rimborso.</p><?php else: ?>
        <ul class="small" style="margin:0;padding-left:18px"><?php foreach ($rimborsi as $rb): ?>
          <li><?= Support::e(Support::date((string) $rb['created_at'])) ?> · <?= Support::e(Support::money((int) $rb['amount_cents'])) ?> con IVA · <?= Support::e((string) $rb['stato']) ?></li>
        <?php endforeach; ?></ul>
      <?php endif; ?>
      <?php if ($pagamenti): ?>
        <p class="small" style="margin:0"><?= implode(' · ', array_map(fn($p) => '<code>' . Support::e((string) $p['codice']) . '</code>', $pagamenti)) ?></p>
      <?php endif; ?>
    </section>
  </div>
</div>
