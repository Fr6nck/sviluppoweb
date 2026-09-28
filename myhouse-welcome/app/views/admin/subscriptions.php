<?php use function MHW\b; use MHW\Support; $title = 'Abbonamenti'; ?>
<div class="stack stack--lg">
  <div class="stack stack--sm"><h1>Abbonamenti.</h1>
    <p class="muted small">Un abbonamento vale se Stripe lo dà per attivo e il periodo pagato non è finito. Quelli manuali e di esempio sono indicati come tali.</p></div>
  <?php if (!$subs): ?><p class="muted">Nessun abbonamento.</p><?php else: ?>
  <div class="tablewrap"><table class="data">
    <thead><tr><th>Cliente</th><th>Piano</th><th>Stato</th><th>Periodo</th><th>Rinnovo</th><th>Riferimenti Stripe</th></tr></thead>
    <tbody><?php foreach ($subs as $s): ?>
      <tr><td><a href="<?= b() ?>/admin/cliente/<?= (int) $s['account_id'] ?>"><?= Support::e($s['cliente'] ?: $s['email']) ?></a><br><span class="tiny muted"><?= Support::e($s['email']) ?></span></td>
        <td><?= Support::e($s['package']) ?> v<?= (int) $s['version'] ?><br><span class="tiny muted"><?= Support::e(Support::money((int) $s['price_cents'], $s['currency'])) ?> · <?= Support::e($s['provider']) ?></span></td>
        <td><span class="badge badge--<?= $s['valido'] ? 'pine' : 'alert' ?>"><?= $s['valido'] ? 'Valido' : 'Non valido' ?></span><br><span class="tiny muted"><?= Support::e($s['status']) ?><?= $s['payment_status'] ? ' · ' . Support::e($s['payment_status']) : '' ?></span></td>
        <td class="small"><?= Support::e(Support::date($s['current_period_start'])) ?> → <?= Support::e(Support::date($s['current_period_end'])) ?></td>
        <td class="small"><?= (int) $s['cancel_at_period_end'] ? 'disattivato' : 'automatico' ?></td>
        <td><code><?= Support::e($s['provider_customer_id'] ?: '—') ?></code><br><code><?= Support::e($s['provider_subscription_id'] ?: '') ?></code><br><code><?= Support::e($s['provider_price_id'] ?: '') ?></code></td></tr>
    <?php endforeach; ?></tbody></table></div>
  <?php endif; ?>
</div>
