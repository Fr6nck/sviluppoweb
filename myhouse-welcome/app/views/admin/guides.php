<?php use function MHW\b; use MHW\Support; $title = 'Guide'; ?>
<div class="stack stack--lg">
  <h1>Guide.</h1>
  <?php if (!$guide): ?><p class="muted">Nessuna guida.</p><?php else: ?>
  <div class="tablewrap"><table class="data">
    <thead><tr><th>Struttura</th><th>Cliente</th><th>Stato</th><th>Versione</th><th>Lingue</th><th>QR</th></tr></thead>
    <tbody><?php foreach ($guide as $g): ?>
      <tr><td><b><?= Support::e($g['name']) ?></b><?= (int) $g['is_demo'] ? ' <span class="demo-tag">Demo</span>' : '' ?><br><a class="small" href="<?= b() ?>/g/<?= Support::e($g['slug']) ?>" target="_blank" rel="noopener">/g/<?= Support::e($g['slug']) ?></a></td>
        <td><a href="<?= b() ?>/admin/cliente/<?= (int) $g['account_id'] ?>"><?= Support::e($g['cliente'] ?: $g['email']) ?></a></td>
        <td><?= $g['status'] === 'published' ? ($g['online'] ? '<span class="badge badge--pine">Online</span>' : '<span class="badge badge--alert">Offline</span>') : '<span class="badge badge--ochre">Bozza</span>' ?></td>
        <td><?= $g['versione'] ? 'v' . (int) $g['versione'] : '—' ?><br><span class="tiny muted"><?= Support::e(Support::date($g['published_at'] ?? '')) ?></span></td>
        <td><?= Support::e($g['lingue']) ?></td>
        <td><code><?= Support::e($g['qr_token'] ?? '—') ?></code></td></tr>
    <?php endforeach; ?></tbody></table></div>
  <?php endif; ?>
</div>
