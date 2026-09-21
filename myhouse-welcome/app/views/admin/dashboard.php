<?php use function MHW\b; use MHW\{Support, Icon}; $title = 'Quadro'; $nav = 'admin'; ?>

<div class="spread">
  <div class="stack stack--sm">
    <h1>Il quadro.</h1>
    <p class="lead">Quanti sono, quanto hanno pagato, quanto viene letto quello che scrivono.
      Numeri interrogati adesso.</p>
  </div>
  <div class="row">
    <a class="btn btn--ghost btn--sm" href="<?= b() ?>/admin/clienti"><?= Icon::svg('people', 15) ?>Clienti</a>
    <a class="btn btn--ghost btn--sm" href="<?= b() ?>/admin/pacchetti"><?= Icon::svg('book', 15) ?>Pacchetti</a>
    <a class="btn btn--ghost btn--sm" href="<?= b() ?>/admin/diagnostica"><?= Icon::svg('chart', 15) ?>Diagnostica</a>
  </div>
</div>

<?php if ($avvisi): ?>
  <div class="stack" style="margin-top:28px;gap:10px">
    <?php foreach ($avvisi as [$che, $perche]): ?>
      <p class="note"><?= Icon::svg('warning', 19) ?>
        <span><strong><?= Support::e($che) ?>.</strong> <?= Support::e($perche) ?></span></p>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="grid grid-4" style="margin-top:28px">
  <div class="stat stat--accent"><b><?= (int) $numeri['clienti'] ?></b>
    <span><?= (int) $numeri['abbonati'] ?> con un piano attivo</span></div>
  <div class="stat"><b><?= Support::e(Support::money((int) $numeri['incassato'])) ?></b>
    <span>incassato, ordini confermati</span></div>
  <div class="stat"><b><?= (int) $numeri['pubblicate'] ?></b>
    <span>guide pubblicate su <?= (int) $numeri['guide'] ?></span></div>
  <div class="stat"><b><?= (int) $numeri['aperture'] ?></b>
    <span>aperture negli ultimi 30 giorni</span></div>
</div>

<div class="sheet" style="margin-top:36px">
  <div class="stack stack--lg">

    <section class="stack" style="gap:12px">
      <div class="spread spread--mid">
        <h2 style="font-size:22px">I piani, versione per versione</h2>
        <a class="small" href="<?= b() ?>/admin/pacchetti">Modifica il listino &rarr;</a>
      </div>
      <p class="muted small">Una versione già venduta non si tocca: chi l'ha comprata ci resta,
        con quello che aveva comprato. Questa colonna dice quante persone ne dipendono.</p>
      <div class="stack" style="gap:8px">
        <?php foreach ($piani as $pl): ?>
          <div class="rowcard">
            <b class="grow"><?= Support::e($pl['name']) ?>
              <span class="muted" style="font-weight:400">· versione <?= (int) $pl['version'] ?></span></b>
            <span class="small muted"><?= Support::e(Support::money((int) $pl['price_cents'], $pl['currency'])) ?> / anno</span>
            <span class="badge badge--<?= $pl['is_current'] ? 'pine' : 'ochre' ?>">
              <?= $pl['is_current'] ? 'In vendita' : 'Storica' ?></span>
            <span class="small" style="min-width:92px;text-align:right">
              <strong><?= (int) $pl['clienti'] ?></strong> client<?= (int) $pl['clienti'] === 1 ? 'e' : 'i' ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="stack" style="gap:12px">
      <h2 style="font-size:22px">Gli ultimi ordini</h2>
      <?php if ($numeri['in_attesa']): ?>
        <p class="note note--quiet"><?= (int) $numeri['in_attesa'] ?> ordini sono ancora in attesa:
          restano così finché Stripe non conferma l'incasso con una notifica firmata.</p>
      <?php endif; ?>
      <table>
        <thead><tr><th>Quando</th><th>Cliente</th><th>Piano</th><th>Importo</th><th>Stato</th></tr></thead>
        <tbody>
        <?php foreach ($ordini as $o): ?>
          <tr>
            <td class="muted"><?= Support::e(substr($o['created_at'], 0, 10)) ?></td>
            <td><a href="<?= b() ?>/admin/cliente/<?= (int) $o['account_id'] ?>">
              <?= Support::e($o['cliente'] ?: $o['email']) ?></a></td>
            <td><?= Support::e($o['package']) ?></td>
            <td><?= Support::e(Support::money((int) $o['amount_cents'], $o['currency'])) ?></td>
            <td><span class="badge badge--<?= $o['status'] === 'paid' ? 'pine' : ($o['status'] === 'failed' ? 'alert' : 'ochre') ?>">
              <?= ['paid' => 'pagato', 'pending' => 'in attesa', 'failed' => 'fallito'][$o['status']] ?? Support::e($o['status']) ?></span></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$ordini): ?><tr><td colspan="5" class="muted">Nessun ordine ancora.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </section>
  </div>

  <div class="stack" style="gap:14px">
    <span class="kicker">Le guide più lette · 30 giorni</span>
    <div class="stack" style="gap:8px">
      <?php foreach ($lette as $g): ?>
        <a class="rowcard" href="<?= b() ?>/g/<?= Support::e($g['slug']) ?>">
          <b class="grow" style="font-size:15px"><?= Support::e($g['name']) ?></b>
          <span class="small muted"><?= (int) $g['aperture'] ?></span>
        </a>
      <?php endforeach; ?>
      <?php if (!$lette): ?>
        <p class="note note--quiet">Nessuna guida pubblicata: quando qualcuno pubblicherà,
          le aperture compariranno qui.</p>
      <?php endif; ?>
    </div>

    <span class="kicker" style="margin-top:14px">Il QR</span>
    <div class="stat"><b><?= (int) $numeri['scansioni'] ?></b><span>scansioni da sempre</span></div>

    <?php if ($esempi): ?>
      <p class="note note--quiet" style="margin-top:6px">I clienti di esempio sono ancora dentro.
        Si tolgono dalla pagina <a href="<?= b() ?>/admin/clienti">Clienti</a>.</p>
    <?php endif; ?>
  </div>
</div>
