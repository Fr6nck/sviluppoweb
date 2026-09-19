<?php use MHW\Support; $title = 'Diagnostica'; $nav = 'clienti';
$bad = array_filter($checks, fn($c) => !$c[1]); ?>
<h1>Diagnostica</h1>
<p class="muted" style="margin-top:10px">Queste voci non sono promesse: il server interroga davvero sé stesso.</p>
<?php if ($bad): ?>
  <p class="note note--err" style="margin-top:16px"><?= count($bad) ?> voce/i da sistemare.</p>
<?php else: ?>
  <p class="note note--ok" style="margin-top:16px">Tutto a posto.</p>
<?php endif; ?>
<table style="margin-top:20px">
  <thead><tr><th>Controllo</th><th>Esito</th><th>Dettaglio</th></tr></thead>
  <tbody>
  <?php foreach ($checks as [$label, $good, $detail]): ?>
    <tr>
      <td><?= Support::e($label) ?></td>
      <td><span class="badge badge--<?= $good ? 'pine' : 'alert' ?>"><?= $good ? 'ok' : 'da vedere' ?></span></td>
      <td class="muted"><?= Support::e($detail) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
