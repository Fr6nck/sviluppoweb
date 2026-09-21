<?php use function MHW\b; use MHW\{Support, Csrf}; $title = 'Cliente'; $nav = 'clienti'; ?>
<p class="small"><a href="<?= b() ?>/admin">&larr; Clienti</a></p>
<h1 style="margin-top:10px"><?= Support::e($acc['user_name'] ?: $acc['email']) ?></h1>
<p class="muted" style="margin-top:8px"><?= Support::e($acc['email']) ?></p>

<h2 style="margin-top:32px">Cosa può fare</h2>
<table style="margin-top:12px;max-width:680px">
  <thead><tr><th>Funzione</th><th>Valore</th><th>Da dove viene</th><th>Eccezione</th></tr></thead>
  <tbody>
  <?php foreach ($ent as $code => $e): ?>
    <tr>
      <td><?= Support::e($code) ?></td>
      <td><strong><?= Support::e($e['value']) ?></strong></td>
      <td><span class="badge badge--<?= $e['source'] === 'override' ? 'terracotta' : ($e['source'] === 'package' ? 'pine' : 'ochre') ?>">
        <?= ['default' => 'predefinito', 'package' => 'dal piano', 'override' => 'eccezione'][$e['source']] ?></span></td>
      <td>
        <form method="post" action="<?= b() ?>/admin/cliente/<?= (int) $acc['id'] ?>/override" class="row" style="gap:6px"><?= Csrf::field() ?>
          <input type="hidden" name="feature" value="<?= Support::e($code) ?>">
          <input name="valore" type="text" value="<?= $e['source'] === 'override' ? Support::e($e['value']) : '' ?>"
                 placeholder="vuoto = nessuna" style="min-height:38px;width:130px">
          <button class="btn btn--ghost btn--sm">Applica</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<h2 style="margin-top:32px">Strutture</h2>
<div class="list" style="margin-top:12px">
  <?php foreach ($props as $pr): ?>
    <div class="list-item"><span class="grow"><strong><?= Support::e($pr['name']) ?></strong>
      <span class="muted small" style="display:block">/g/<?= Support::e($pr['slug']) ?></span></span>
      <span class="badge badge--<?= $pr['status'] === 'published' ? 'pine' : 'ochre' ?>"><?= Support::e($pr['status']) ?></span></div>
  <?php endforeach; ?>
  <?php if (!$props): ?><p class="muted small">Nessuna struttura.</p><?php endif; ?>
</div>

<h2 style="margin-top:32px">Ordini</h2>
<table style="margin-top:12px;max-width:680px">
  <thead><tr><th>Quando</th><th>Piano</th><th>Importo</th><th>Stato</th><th>Canale</th></tr></thead>
  <tbody>
  <?php foreach ($orders as $o): ?>
    <tr><td class="muted"><?= Support::e(substr($o['created_at'], 0, 10)) ?></td>
      <td><?= Support::e($o['package']) ?></td>
      <td><?= Support::e(Support::money((int) $o['amount_cents'], $o['currency'])) ?></td>
      <td><span class="badge badge--<?= $o['status'] === 'paid' ? 'pine' : 'ochre' ?>"><?= Support::e($o['status']) ?></span></td>
      <td class="muted"><?= Support::e($o['provider']) ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$orders): ?><tr><td colspan="5" class="muted">Nessun ordine.</td></tr><?php endif; ?>
  </tbody>
</table>

<h2 style="margin-top:32px">Registro accessi</h2>
<table style="margin-top:12px;max-width:680px">
  <tbody>
  <?php foreach ($audit as $l): ?>
    <tr><td class="muted"><?= Support::e($l['created_at']) ?></td><td><?= Support::e($l['action']) ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$audit): ?><tr><td class="muted">Nessun accesso registrato.</td></tr><?php endif; ?>
  </tbody>
</table>
