<?php
use function MHW\b;
use MHW\{Support, Csrf};
$title = 'Clienti'; ?>
<div class="stack stack--lg">
  <div class="spread spread--mid">
    <h1>Clienti.</h1>
    <?php $q = http_build_query(array_filter(['q' => $cerca, 'stato' => $filtroStato, 'piano' => $filtroPiano], fn($v) => $v !== '')); ?>
    <a class="btn btn--ghost btn--sm" href="<?= b() ?>/admin/clienti?<?= Support::e($q . ($q !== '' ? '&' : '') . 'formato=csv') ?>">Esporta CSV</a>
  </div>
  <form method="get" class="filtri-clienti" role="search" aria-label="Cerca e filtra i clienti">
    <div class="field" style="margin:0"><label for="c-q">Cerca</label>
      <input id="c-q" type="search" name="q" value="<?= Support::e($cerca) ?>" placeholder="Nome, email o struttura"></div>
    <div class="field" style="margin:0"><label for="c-stato">Stato</label>
      <select id="c-stato" name="stato"><option value="">Tutti</option>
        <?php foreach (['attivo' => 'Attivo', 'pagato' => 'Pagato, non pubblicato', 'fallito' => 'Pagamento non riuscito', 'scaduto' => 'Scaduto (offline)',
                        'bozza' => 'In bozza, senza piano pagato', 'vuoto' => 'Nessuna struttura'] as $k => $et): ?>
          <option value="<?= $k ?>"<?= $filtroStato === $k ? ' selected' : '' ?>><?= Support::e($et) ?></option><?php endforeach; ?></select></div>
    <div class="field" style="margin:0"><label for="c-piano">Piano</label>
      <select id="c-piano" name="piano"><option value="">Tutti</option>
        <?php foreach ($piani as $p): ?><option<?= $filtroPiano === $p ? ' selected' : '' ?>><?= Support::e($p) ?></option><?php endforeach; ?></select></div>
    <div class="actions" style="align-self:end"><button class="btn btn--sm">Filtra</button>
      <?php if ($q !== ''): ?><a class="btn btn--quiet btn--sm" href="<?= b() ?>/admin/clienti">Togli i filtri</a><?php endif; ?></div>
  </form>
  <?php if ($rows): ?><p class="small muted" style="margin:0"><?= count($rows) ?> client<?= count($rows) === 1 ? 'e' : 'i' ?></p><?php endif; ?>
  <?php if (!$rows): ?><p class="muted">Nessun cliente<?= $cerca !== '' ? ' per questa ricerca' : '' ?>.</p><?php else: ?>
  <div class="tablewrap"><table class="data">
    <thead><tr><th>Cliente</th><th>Struttura</th><th>Piano</th><th>Stato</th><th>Scadenza</th><th>Registrato</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><a href="<?= b() ?>/admin/cliente/<?= (int) $r['account_id'] ?>"><b><?= Support::e($r['name'] ?: '—') ?></b></a><br>
          <span class="small muted"><?= Support::e($r['email']) ?></span><?= $r['email_verified_at'] ? '' : ' <span class="badge badge--ochre">non confermata</span>' ?></td>
        <td><?= Support::e($r['struttura'] ?: '—') ?><?= $r['citta'] ? '<br><span class="small muted">' . Support::e($r['citta']) . '</span>' : '' ?>
          <?= $r['strutture'] > 1 ? '<br><span class="small muted">+' . ($r['strutture'] - 1) . ((int) $r['strutture'] === 2 ? ' altra' : ' altre') . '</span>' : '' ?></td>
        <td><?= Support::e($r['plan']) ?><?= $r['plan_version'] ? ' <span class="tiny muted">v' . (int) $r['plan_version'] . '</span>' : '' ?></td>
        <td><span class="badge badge--<?= $r['stato'][1] ?>"><?= Support::e($r['stato'][0]) ?></span></td>
        <td class="small"><?= $r['scadenza'] !== '' ? Support::e(Support::date($r['scadenza'])) . '<br><span class="tiny muted">' . Support::e(['automatico' => 'si rinnova', 'disattivato' => 'non si rinnova', 'staff' => 'dallo staff'][$r['rinnovo']] ?? '') . '</span>' : '—' ?></td>
        <td class="small"><?= Support::e(Support::date($r['created_at'])) ?></td>
        <td><form method="post" action="<?= b() ?>/admin/entra/<?= (int) $r['user_id'] ?>"><?= Csrf::field() ?>
          <button class="btn btn--ghost btn--sm" title="Apre il pannello con l'account del cliente: l'accesso resta nel registro">Entra come cliente</button></form></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</div>
