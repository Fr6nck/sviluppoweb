<?php
/* La scheda di un cliente: chi è, cosa ha comprato (versione compresa),
   cosa può fare e perché, le sue guide, i pagamenti, il registro. */
use function MHW\b;
use MHW\{Support, Csrf, Db};
$title = ($acc['user_name'] ?: $acc['email']) . ' — Cliente';
$etichette = array_column(Db::all('SELECT code, label FROM features ORDER BY id'), 'label', 'code');
$fonte = ['package' => 'piano pagato', 'intended' => 'piano scelto', 'override' => 'eccezione', 'default' => 'predefinito']; ?>
<div class="stack stack--lg">
  <div class="spread">
    <div class="stack stack--sm">
      <a class="small" href="<?= b() ?>/admin/clienti">← Clienti</a>
      <h1><?= Support::e($acc['user_name'] ?: $acc['email']) ?>.</h1>
      <p class="small muted"><?= Support::e($acc['email']) ?> · registrato il <?= Support::e(Support::date($acc['registrato'])) ?> ·
        <?= $acc['email_verified_at'] ? 'email verificata' : '<b>email non verificata</b>' ?></p>
    </div>
    <form method="post" action="<?= b() ?>/admin/entra/<?= (int) $acc['user_id'] ?>" style="margin:0"><?= Csrf::field() ?>
      <button class="btn btn--ghost btn--sm">Entra come cliente</button></form>
  </div>

  <div class="grid grid-2" style="align-items:start">
    <section class="panel stack" style="gap:8px">
      <span class="kicker">Consensi</span>
      <p class="small">Termini: versione <?= Support::e($acc['terms_version'] ?: '—') ?>, accettati il <?= Support::e(Support::date($acc['terms_accepted_at'] ?? '')) ?: '—' ?></p>
      <p class="small">Privacy: versione <?= Support::e($acc['privacy_version'] ?: '—') ?>, presa visione il <?= Support::e(Support::date($acc['privacy_accepted_at'] ?? '')) ?: '—' ?></p>
    </section>
    <section class="panel stack" style="gap:8px">
      <span class="kicker">Stripe</span>
      <p class="small">Customer: <code><?= Support::e($acc['stripe_customer_id'] ?: '—') ?></code></p>
      <p class="small">Piano scelto: <?= $acc['intended_package_version_id'] ? 'versione #' . (int) $acc['intended_package_version_id'] : '—' ?></p>
    </section>
  </div>

  <?php /* La guida vetrina: una demo completa dentro questo account (nomi e dati di fantasia). */
        $vetrina = null; foreach ($props as $p) if ((int) $p['is_demo'] === MHW\Demo::VETRINA) $vetrina = $p;
        $conPiano = (bool) MHW\Subscriptions::active((int) $acc['id']); ?>
  <section class="panel stack" style="gap:10px" aria-labelledby="vetrina-titolo">
    <h2 id="vetrina-titolo" style="font-size:22px">Guida vetrina</h2>
    <?php if ($vetrina): ?>
      <p class="small">«<?= Support::e($vetrina['name']) ?>» è la guida vetrina di questo account: è online come demo, non conta nel limite di strutture ed è la demo della landing.
        <a href="<?= b() ?>/g/<?= Support::e($vetrina['slug']) ?>" target="_blank" rel="noopener">Apri la guida</a></p>
      <form method="post" action="<?= b() ?>/admin/cliente/<?= (int) $acc['id'] ?>/vetrina" class="stack" style="gap:8px"><?= Csrf::field() ?>
        <input type="hidden" name="rifai" value="1">
        <p class="small muted">«Rifai la vetrina» la toglie e ne crea una nuova con i dati aggiornati (eventi con le prossime date, foto, luoghi). Le modifiche fatte a mano sulla vetrina si perdono; le altre strutture dell'account non si toccano.</p>
        <button class="btn btn--ghost btn--sm" style="align-self:flex-start">Rifai la vetrina</button>
      </form>
    <?php else: ?>
      <p class="small">Una guida dimostrativa completa sulla falsariga di Casa Lucia, con nomi, numeri, indicazioni e foto diversi, tutti di fantasia: «Casa Checco», ad Assisi, vicino a Piazza Matteotti. Tutte le sezioni sono compilate, eventi compresi. Si pubblica subito come demo, non occupa il posto di una struttura e diventa la demo della landing. Il cliente la modifica dal suo pannello.</p>
      <form method="post" action="<?= b() ?>/admin/cliente/<?= (int) $acc['id'] ?>/vetrina" class="stack" style="gap:10px"><?= Csrf::field() ?>
        <?php if (!$conPiano): ?>
          <label class="check"><input type="checkbox" name="plus" value="1" checked> <span>Concedi Plus dimostrativo per 12 mesi: senza un piano la guida esce senza foto, senza inglese e senza luoghi.</span></label>
        <?php endif; ?>
        <button class="btn btn--sm" style="align-self:flex-start">Crea la guida vetrina</button>
      </form>
    <?php endif; ?>
  </section>

  <section class="stack" style="gap:10px">
    <h2 style="font-size:22px">Strutture e guide</h2>
    <?php if (!$props): ?><p class="small muted">Nessuna struttura.</p><?php else: ?>
    <div class="tablewrap"><table class="data">
      <thead><tr><th>Struttura</th><th>Guida</th><th>Lingue</th><th>QR</th><th>Pubblicata</th></tr></thead>
      <tbody><?php foreach ($props as $p): ?>
        <tr><td><b><?= Support::e($p['name']) ?></b><br><span class="small muted"><?= Support::e($p['city']) ?></span></td>
          <td><?= $p['status'] === 'published' ? ($p['online'] ? '<span class="badge badge--pine">Online</span>' : '<span class="badge badge--alert">Offline</span>') : '<span class="badge badge--ochre">Bozza</span>' ?>
            <br><a class="small" href="<?= b() ?>/g/<?= Support::e($p['slug']) ?>" target="_blank" rel="noopener">/g/<?= Support::e($p['slug']) ?></a></td>
          <td><?= Support::e($p['lingue']) ?></td>
          <td><code><?= Support::e($p['qr']['token'] ?? '—') ?></code></td>
          <td class="small"><?= Support::e(Support::date($p['published_at'] ?? '')) ?: '—' ?></td></tr>
      <?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
  </section>

  <section class="stack" style="gap:10px">
    <h2 style="font-size:22px">Abbonamenti</h2>
    <?php if (!$subs): ?><p class="small muted">Nessun abbonamento.</p><?php else: ?>
    <div class="tablewrap"><table class="data">
      <thead><tr><th>Piano</th><th>Stato</th><th>Periodo</th><th>Rinnovo</th><th>Stripe</th></tr></thead>
      <tbody><?php foreach ($subs as $s): ?>
        <tr><td><?= Support::e($s['package']) ?> <span class="tiny muted">v<?= (int) $s['version'] ?></span><?= (int) ($s['quantity'] ?? 1) > 1 ? ' · ' . (int) $s['quantity'] . ' strutture' : '' ?><br><span class="tiny muted"><?= Support::e($s['provider']) ?></span></td>
          <td><?= Support::e($s['status']) ?><?= $s['payment_status'] ? '<br><span class="tiny muted">' . Support::e($s['payment_status']) . '</span>' : '' ?></td>
          <td class="small"><?= Support::e(Support::date($s['current_period_start'])) ?> → <?= Support::e(Support::date($s['current_period_end'])) ?></td>
          <td class="small"><?= (int) $s['cancel_at_period_end'] ? 'disattivato' : 'automatico' ?></td>
          <td><code><?= Support::e($s['provider_subscription_id'] ?: '—') ?></code><br><code><?= Support::e($s['provider_price_id'] ?: '') ?></code></td></tr>
      <?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
    <details class="fieldset">
      <summary class="legend" style="cursor:pointer;min-height:32px">Attiva un abbonamento manuale</summary>
      <form method="post" action="<?= b() ?>/admin/cliente/<?= (int) $acc['id'] ?>/abbonamento" class="stack" style="margin-top:12px"><?= Csrf::field() ?>
        <p class="small muted">Per omaggi o pagamenti arrivati per altre vie. Non passa da Stripe, non conta nell'incasso e resta nel registro.</p>
        <div class="grid grid-4">
          <div class="field" style="margin:0"><label for="pv">Piano</label><select id="pv" name="pv">
            <?php foreach ($versioni as $v): ?><option value="<?= (int) $v['id'] ?>"><?= Support::e($v['name']) ?> v<?= (int) $v['version'] ?></option><?php endforeach; ?></select></div>
          <div class="field" style="margin:0"><label for="mesi">Mesi</label><input id="mesi" name="mesi" type="number" min="1" max="36" value="12"></div>
          <div class="field" style="margin:0"><label for="strutture">Strutture <span class="muted">(Portfolio)</span></label><input id="strutture" name="strutture" type="number" min="1" max="500" value="2"></div>
          <div class="field" style="margin:0"><label for="nota">Motivo</label><input type="text" id="nota" name="nota" required maxlength="200"></div>
        </div>
        <div class="actions"><button class="btn btn--sm">Attiva</button></div>
      </form>
    </details>
  </section>

  <section class="stack" style="gap:10px">
    <h2 style="font-size:22px">Cosa può fare</h2>
    <p class="small muted">Valore effettivo e da dove arriva. Un'eccezione vale sopra il piano; lasciala vuota per toglierla. 0/1 per no/sì, un numero per i limiti, unlimited per illimitato.</p>
    <div class="tablewrap"><table class="data">
      <thead><tr><th>Funzione</th><th>Valore</th><th>Fonte</th><th>Eccezione</th></tr></thead>
      <tbody><?php foreach ($ent as $code => $e): ?>
        <tr><td><?= Support::e($etichette[$code] ?? $code) ?><br><code class="tiny muted"><?= Support::e($code) ?></code></td>
          <td><b><?= Support::e($e['value']) ?></b></td><td class="small"><?= Support::e($fonte[$e['source']] ?? $e['source']) ?></td>
          <td><form method="post" action="<?= b() ?>/admin/cliente/<?= (int) $acc['id'] ?>/override" class="row" style="gap:6px;flex-wrap:nowrap"><?= Csrf::field() ?>
            <input type="hidden" name="feature" value="<?= Support::e($code) ?>">
            <input type="text" name="valore" value="<?= $e['source'] === 'override' ? Support::e($e['value']) : '' ?>" style="width:110px" aria-label="Eccezione per <?= Support::e($code) ?>">
            <input type="text" name="nota" placeholder="motivo" style="width:140px" aria-label="Motivo">
            <button class="btn btn--ghost btn--sm">Salva</button></form></td></tr>
      <?php endforeach; ?></tbody></table></div>
  </section>

  <section class="stack" style="gap:10px">
    <h2 style="font-size:22px">Ordini</h2>
    <?php if (!$orders): ?><p class="small muted">Nessun ordine.</p><?php else: ?>
    <div class="tablewrap"><table class="data">
      <thead><tr><th>Data</th><th>Piano</th><th>Importo</th><th>Stato</th><th>Sessione</th></tr></thead>
      <tbody><?php foreach ($orders as $o): ?>
        <tr><td class="small"><?= Support::e(Support::date($o['created_at'])) ?></td><td><?= Support::e($o['package']) ?></td>
          <td><?= Support::e(Support::money((int) $o['amount_cents'], $o['currency'])) ?></td><td><?= Support::e($o['status']) ?></td>
          <td><code><?= Support::e($o['provider_session_id'] ?: '—') ?></code></td></tr>
      <?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
  </section>

  <section class="stack" style="gap:10px">
    <h2 style="font-size:22px">Registro</h2>
    <?php if (!$audit): ?><p class="small muted">Niente da segnalare.</p><?php else: ?>
    <div class="tablewrap"><table class="data"><tbody><?php foreach ($audit as $l): ?>
      <tr><td class="small" style="white-space:nowrap"><?= Support::e(str_replace(['T', 'Z'], [' ', ''], $l['created_at'])) ?></td><td><code><?= Support::e($l['action']) ?></code></td>
        <td class="small muted"><?= Support::e($l['meta']) ?></td></tr>
    <?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
  </section>
</div>
