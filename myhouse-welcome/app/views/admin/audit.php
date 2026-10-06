<?php use MHW\Support; $title = 'Registro'; ?>
<div class="stack stack--lg">
  <div class="stack stack--sm"><h1>Registro.</h1>
    <p class="muted small">Le azioni che contano: accessi come cliente, eccezioni, abbonamenti manuali, pubblicazioni, cambi di listino. Ultime 300.</p></div>
  <?php if (!$righe): ?><p class="muted">Ancora nessuna azione registrata.</p><?php else: ?>
  <div class="tablewrap"><table class="data">
    <thead><tr><th>Quando (UTC)</th><th>Chi</th><th>Azione</th><th>Su</th><th>Dettagli</th></tr></thead>
    <tbody><?php foreach ($righe as $l): ?>
      <tr><td class="small" style="white-space:nowrap"><?= Support::e(str_replace(['T', 'Z'], [' ', ''], $l['created_at'])) ?></td>
        <td class="small"><?= Support::e($l['attore'] ?: 'sistema') ?></td><td><code><?= Support::e($l['action']) ?></code></td>
        <td class="small"><?= Support::e($l['bersaglio'] ?: '—') ?></td><td class="small muted"><code><?= Support::e($l['meta']) ?></code></td></tr>
    <?php endforeach; ?></tbody></table></div>
  <?php endif; ?>
</div>
