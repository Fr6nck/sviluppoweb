<?php
use function MHW\b;
use MHW\{Support, Csrf};
$title = 'Clienti'; ?>
<div class="stack stack--lg">
  <div class="spread spread--mid">
    <h1>Clienti.</h1>
    <form method="get" class="search" role="search" style="min-width:280px">
      <input name="q" value="<?= Support::e($cerca) ?>" placeholder="Nome, email o struttura" aria-label="Cerca clienti"></form>
  </div>
  <?php if (!$rows): ?><p class="muted">Nessun cliente<?= $cerca !== '' ? ' per questa ricerca' : '' ?>.</p><?php else: ?>
  <div class="tablewrap"><table class="data">
    <thead><tr><th>Cliente</th><th>Struttura</th><th>Piano</th><th>Stato</th><th>Registrato</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><a href="<?= b() ?>/admin/cliente/<?= (int) $r['account_id'] ?>"><b><?= Support::e($r['name'] ?: '—') ?></b></a><br>
          <span class="small muted"><?= Support::e($r['email']) ?></span><?= $r['email_verified_at'] ? '' : ' <span class="badge badge--ochre">non verificata</span>' ?></td>
        <td><?= Support::e($r['struttura'] ?: '—') ?><?= $r['citta'] ? '<br><span class="small muted">' . Support::e($r['citta']) . '</span>' : '' ?>
          <?= $r['strutture'] > 1 ? '<br><span class="small muted">+' . ($r['strutture'] - 1) . ' altre</span>' : '' ?></td>
        <td><?= Support::e($r['plan']) ?><?= $r['plan_version'] ? ' <span class="tiny muted">v' . (int) $r['plan_version'] . '</span>' : '' ?></td>
        <td><span class="badge badge--<?= $r['stato'][1] ?>"><?= Support::e($r['stato'][0]) ?></span></td>
        <td class="small"><?= Support::e(Support::date($r['created_at'])) ?></td>
        <td><form method="post" action="<?= b() ?>/admin/entra/<?= (int) $r['user_id'] ?>"><?= Csrf::field() ?>
          <button class="btn btn--ghost btn--sm" title="Apre l'app con l'account del cliente, tracciato nel registro">Entra come cliente</button></form></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</div>
