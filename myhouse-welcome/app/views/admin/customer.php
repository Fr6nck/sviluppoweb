<?php use function MHW\b; use MHW\{Support, Csrf, Icon}; $title = 'Cliente'; $nav = 'clienti'; ?>
<p class="small"><a href="<?= b() ?>/admin/clienti">&larr; Clienti</a></p>

<div class="spread" style="margin-top:12px">
  <div class="stack stack--sm">
    <h1><?= Support::e($acc['user_name'] ?: $acc['email']) ?>.</h1>
    <p class="muted"><?= Support::e($acc['email']) ?></p>
  </div>
  <form method="post" action="<?= b() ?>/admin/entra/<?= (int) $acc['user_id'] ?>"><?= Csrf::field() ?>
    <button class="btn btn--ghost"><?= Icon::svg('eye', 15) ?>Entra come lui/lei</button></form>
</div>

<div class="sheet" style="margin-top:36px">
  <div class="stack stack--lg">

    <section class="stack" style="gap:12px">
      <h2 style="font-size:22px">Cosa può fare</h2>
      <p class="muted small">L'ordine è sempre lo stesso: eccezione per questo cliente &rarr;
        versione di pacchetto comprata &rarr; valore predefinito. Un'eccezione vuota torna al piano.</p>
      <table>
        <thead><tr><th>Funzione</th><th>Valore</th><th>Da dove viene</th><th>Eccezione</th></tr></thead>
        <tbody>
        <?php foreach ($ent as $code => $e): ?>
          <tr>
            <td><?= Support::e($code) ?></td>
            <td><strong><?= Support::e($e['value']) ?></strong></td>
            <td><span class="badge badge--<?= $e['source'] === 'override' ? 'terracotta' : ($e['source'] === 'package' ? 'pine' : 'ochre') ?>">
              <?= ['default' => 'predefinito', 'package' => 'dal piano', 'override' => 'eccezione'][$e['source']] ?></span></td>
            <td>
              <form method="post" action="<?= b() ?>/admin/cliente/<?= (int) $acc['id'] ?>/override"
                    class="row" style="gap:6px;margin:0"><?= Csrf::field() ?>
                <input type="hidden" name="feature" value="<?= Support::e($code) ?>">
                <input name="valore" type="text" value="<?= $e['source'] === 'override' ? Support::e($e['value']) : '' ?>"
                       placeholder="vuoto = nessuna" style="height:40px;width:130px;padding:0 12px">
                <button class="btn btn--ghost btn--sm">Applica</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </section>

    <section class="stack" style="gap:12px">
      <h2 style="font-size:22px">Ordini</h2>
      <table>
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
    </section>

    <section class="stack" style="gap:12px">
      <h2 style="font-size:22px">Registro accessi</h2>
      <table>
        <tbody>
        <?php foreach ($audit as $l): ?>
          <tr><td class="muted"><?= Support::e($l['created_at']) ?></td><td><?= Support::e($l['action']) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$audit): ?><tr><td class="muted">Nessun accesso registrato.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </section>
  </div>

  <div class="stack" style="gap:14px">
    <span class="kicker">Strutture</span>
    <div class="stack" style="gap:8px">
      <?php foreach ($props as $pr): ?>
        <a class="rowcard" href="<?= b() ?>/g/<?= Support::e($pr['slug']) ?>">
          <span class="grow stack" style="gap:3px">
            <b style="font-size:15px"><?= Support::e($pr['name']) ?></b>
            <span class="tiny muted">/g/<?= Support::e($pr['slug']) ?></span></span>
          <span class="badge badge--<?= $pr['status'] === 'published' ? 'pine' : 'ochre' ?>">
            <?= $pr['status'] === 'published' ? 'Pubblicata' : 'Bozza' ?></span>
        </a>
      <?php endforeach; ?>
      <?php if (!$props): ?><p class="note note--quiet">Nessuna struttura.</p><?php endif; ?>
    </div>
  </div>
</div>
