<?php use MHW\{Support, Csrf}; $title = 'Clienti'; $nav = 'clienti'; ?>
<h1>Clienti</h1>
<table style="margin-top:24px">
  <thead><tr><th>Cliente</th><th>Piano</th><th>Strutture</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $row): ?>
    <tr>
      <td><a href="/admin/cliente/<?= (int) $row['account_id'] ?>"><strong><?= Support::e($row['name'] ?: '—') ?></strong></a>
        <span class="muted" style="display:block"><?= Support::e($row['email']) ?></span></td>
      <td><?= Support::e($row['plan']) ?><?= $row['plan_version'] ? ' <span class="muted">v' . (int) $row['plan_version'] . '</span>' : '' ?></td>
      <td><?= (int) $row['properties'] ?></td>
      <td style="text-align:right">
        <form method="post" action="/admin/entra/<?= (int) $row['user_id'] ?>" style="margin:0"><?= Csrf::field() ?>
          <button class="btn btn--ghost btn--sm">Entra come lui/lei</button></form></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="4" class="muted">Nessun cliente ancora.</td></tr><?php endif; ?>
  </tbody>
</table>
<p class="note" style="margin-top:24px;max-width:620px">Entrare come un cliente non richiede la sua password e non la mostra mai.
Ogni ingresso e ogni uscita restano scritti nel registro.</p>
