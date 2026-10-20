<?php use function MHW\b; use MHW\Support; $title = 'Abbonamenti'; include __DIR__ . '/_stati.php'; ?>
<div class="stack stack--lg">
  <div class="spread spread--mid"><div class="stack stack--sm"><h1>Abbonamenti.</h1>
    <p class="muted small">Un abbonamento vale se Stripe lo dà per attivo e il periodo pagato non è finito. Quelli manuali e di esempio sono indicati come tali.</p></div>
    <a class="btn btn--ghost btn--sm" href="<?= b() ?>/admin/abbonamenti?<?= !empty($gratuiti) ? 'filtro=gratuiti&amp;' : '' ?>formato=csv">Esporta CSV</a></div>
  <nav class="row filtri-abbonamenti" style="gap:8px" aria-label="Filtro degli abbonamenti">
    <a class="btn btn--sm<?= empty($gratuiti) ? '' : ' btn--ghost' ?>" href="<?= b() ?>/admin/abbonamenti"<?= empty($gratuiti) ? ' aria-current="page"' : '' ?>>Tutti</a>
    <a class="btn btn--sm<?= !empty($gratuiti) ? '' : ' btn--ghost' ?>" href="<?= b() ?>/admin/abbonamenti?filtro=gratuiti"<?= !empty($gratuiti) ? ' aria-current="page"' : '' ?>>Gratuiti</a>
  </nav>
  <?php if (!$subs): ?><p class="muted"><?= !empty($gratuiti) ? 'Nessun abbonamento gratuito.' : 'Nessun abbonamento.' ?></p><?php else: ?>
  <div class="tablewrap"><table class="data">
    <thead><tr><th>Cliente</th><th>Piano</th><th>Stato</th><th>Periodo</th><th>Rinnovo</th><th>Riferimenti Stripe</th></tr></thead>
    <tbody><?php foreach ($subs as $s): ?>
      <tr><td><a href="<?= b() ?>/admin/cliente/<?= (int) $s['account_id'] ?>"><?= Support::e($s['cliente'] ?: $s['email']) ?></a><?= !empty($s['is_test']) ? ' <span class="badge badge--sea">Prova</span>' : '' ?><br><span class="tiny muted"><?= Support::e($s['email']) ?></span></td>
        <td><?= Support::e($s['package']) ?> v<?= (int) $s['version'] ?><?= (int) ($s['quantity'] ?? 1) > 1 ? ' · ' . (int) $s['quantity'] . ' strutture' : '' ?><br><span class="tiny muted"><?= Support::e(Support::money((int) $s['price_cents'], $s['currency'])) ?> · <?= Support::e($viaPagamento[$s['provider']] ?? $s['provider']) ?></span></td>
        <td><span class="badge badge--<?= $s['valido'] ? 'pine' : 'alert' ?>"><?= $s['valido'] ? 'Valido' : 'Non valido' ?></span><br><span class="tiny muted"><?= Support::e($statoAbbonamento[$s['status']] ?? $s['status']) ?><?= $s['payment_status'] ? ' · ' . Support::e($statoPagamento[$s['payment_status']] ?? $s['payment_status']) : '' ?></span></td>
        <td class="small"><?= Support::e(Support::date($s['current_period_start'])) ?> → <?= substr((string) $s['current_period_end'], 0, 4) >= '2100' ? 'per sempre' : Support::e(Support::date($s['current_period_end'])) ?></td>
        <td class="small"><?= $s['provider'] !== 'stripe' ? 'staff' : ((int) $s['cancel_at_period_end'] ? 'disattivato' : 'automatico') ?></td>
        <td><code><?= Support::e($s['provider_customer_id'] ?: '—') ?></code><br><code><?= Support::e($s['provider_subscription_id'] ?: '') ?></code><br><code><?= Support::e($s['provider_price_id'] ?: '') ?></code></td></tr>
    <?php endforeach; ?></tbody></table></div>
  <?php endif; ?>
</div>
