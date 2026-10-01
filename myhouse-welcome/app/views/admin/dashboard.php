<?php
/* Il quadro dell'amministrazione: numeri veri, interrogati adesso.
   Gli abbonamenti manuali e di esempio non contano nell'incasso. */
use function MHW\b;
use MHW\{Support, Csrf};
$title = 'Amministrazione'; ?>
<div class="stack stack--lg">
  <h1>Quadro.</h1>

  <?php foreach ($avvisi as [$t, $d]): ?>
    <div class="note" role="status"><div class="stack" style="gap:4px"><b><?= Support::e($t) ?></b><span class="small"><?= Support::e($d) ?></span></div></div>
  <?php endforeach; ?>

  <div class="grid grid-4">
    <div class="stat"><b><?= (int) $numeri['clienti'] ?></b><span>clienti registrati</span></div>
    <div class="stat"><b><?= (int) $numeri['abbonati'] ?></b><span>abbonamenti Stripe attivi</span></div>
    <div class="stat"><b><?= (int) $numeri['pubblicate'] ?></b><span>guide pubblicate su <?= (int) $numeri['guide'] ?></span></div>
    <div class="stat"><b><?= Support::e(Support::money($numeri['incassato'])) ?></b><span>incassato con Stripe (IVA esclusa)</span></div>
    <div class="stat"><b><?= (int) $numeri['aperture'] ?></b><span>aperture delle guide (30 giorni)</span></div>
    <div class="stat"><b><?= (int) $numeri['in_attesa'] ?></b><span>pagamenti in attesa</span></div>
    <div class="stat"><b><?= (int) $numeri['rinnovo_off'] ?></b><span>con rinnovo disattivato</span></div>
    <div class="stat"><b><?= (int) $numeri['falliti'] ?></b><span>rinnovi non riusciti</span></div>
  </div>

  <section class="stack" style="gap:10px" aria-labelledby="funnel-titolo">
    <h2 id="funnel-titolo" style="font-size:22px">Dalla landing alla guida pubblicata <span class="small muted">· ultimi 30 giorni</span></h2>
    <ol class="funnel">
      <?php foreach ($funnel as $f): ?>
        <li class="stat"><b><?= (int) $f['n'] ?></b><span><?= Support::e($f['label']) ?></span>
          <?php if ($f['perc'] !== null): ?><span class="funnel__perc"><?= (int) $f['perc'] ?>% dal passo prima</span><?php elseif ($f['kind'] !== 'landing_view'): ?><span class="funnel__perc">—</span><?php endif; ?></li>
      <?php endforeach; ?>
    </ol>
    <p class="tiny muted">Eventi anonimi senza cookie: nessun IP, nessun identificativo, i robot non contano. Una persona che ricarica la landing conta due volte.</p>
  </section>

  <section class="stack" style="gap:10px">
    <div class="spread spread--mid"><h2 style="font-size:22px">Listino</h2><a class="small" href="<?= b() ?>/admin/pacchetti">Gestisci pacchetti</a></div>
    <div class="tablewrap"><table class="data">
      <thead><tr><th>Pacchetto</th><th>Versione</th><th>Prezzo</th><th>Stripe Price</th><th>Clienti attivi</th><th>Stato</th></tr></thead>
      <tbody>
      <?php foreach ($piani as $p): ?>
        <tr><td><?= Support::e($p['name']) ?></td><td>v<?= (int) $p['version'] ?><?= $p['is_current'] ? ' · in vendita' : '' ?></td>
          <td><?= Support::e(Support::money((int) $p['price_cents'], $p['currency'])) ?> + IVA</td>
          <td><code><?= Support::e($p['stripe_price_id'] ?: '— (prezzo inline)') ?></code></td>
          <td><?= (int) $p['clienti'] ?></td>
          <td><?= !$p['active'] ? 'Disattivato' : ($p['public'] ? 'Pubblico' : 'Nascosto') ?></td></tr>
      <?php endforeach; ?>
      </tbody></table></div>
  </section>

  <section class="stack" style="gap:10px">
    <h2 style="font-size:22px">Ultimi ordini</h2>
    <?php if (!$ordini): ?><p class="muted small">Nessun ordine.</p><?php else: ?>
    <div class="tablewrap"><table class="data">
      <thead><tr><th>Data</th><th>Cliente</th><th>Piano</th><th>Importo</th><th>Stato</th></tr></thead>
      <tbody>
      <?php foreach ($ordini as $o): ?>
        <tr><td><?= Support::e(Support::date($o['created_at'])) ?></td>
          <td><a href="<?= b() ?>/admin/cliente/<?= (int) $o['account_id'] ?>"><?= Support::e($o['cliente'] ?: $o['email']) ?></a></td>
          <td><?= Support::e($o['package']) ?></td><td><?= Support::e(Support::money((int) $o['amount_cents'], $o['currency'])) ?></td>
          <td><?= Support::e($o['status']) ?> <span class="tiny muted"><?= Support::e($o['provider']) ?></span></td></tr>
      <?php endforeach; ?>
      </tbody></table></div>
    <?php endif; ?>
  </section>

  <section class="panel stack">
    <span class="kicker">Clienti di esempio</span>
    <?php if ($esempi): ?>
      <p class="small">Tre account dimostrativi (dominio esempio.it) con password nota. Servono per provare il prodotto: toglili prima di aprire al pubblico.</p>
      <form method="post" action="<?= b() ?>/admin/dati-esempio"><?= Csrf::field() ?><button class="btn btn--danger btn--sm" name="cosa" value="elimina">Elimina i clienti di esempio</button></form>
    <?php else: ?>
      <p class="small">Crea tre clienti di prova (Essential, Plus, Portfolio) con la guida di Casa Lucia usata come demo pubblica.</p>
      <form method="post" action="<?= b() ?>/admin/dati-esempio"><?= Csrf::field() ?><button class="btn btn--ghost btn--sm" name="cosa" value="crea">Crea i clienti di esempio</button></form>
    <?php endif; ?>
  </section>
</div>
