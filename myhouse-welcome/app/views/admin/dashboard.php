<?php
/* Il quadro dell'amministrazione: numeri veri, interrogati adesso.
   Gli abbonamenti manuali e di esempio non contano nell'incasso. */
use function MHW\b;
use MHW\{Support, Csrf, Icon};
$title = 'Amministrazione';
include __DIR__ . '/_stati.php'; ?>
<div class="stack stack--lg">
  <div class="saluto" style="margin-bottom:0"><div><h1>Quadro.</h1><p>I numeri di adesso, letti dal database in questo momento. Gli abbonamenti manuali e di esempio non contano nell'incasso.</p></div></div>

  <?php foreach ($avvisi as $avv): [$t, $d] = $avv; $vai = $avv[2] ?? ''; ?>
    <div class="note avviso" role="status"><div class="stack" style="gap:4px"><b><?= Support::e($t) ?></b><span class="small"><?= Support::e($d) ?></span></div>
      <?php if ($vai !== ''): ?><a class="btn btn--sm avviso__vai" href="<?= b() . Support::e($vai) ?>"><?= Support::e($avv[3] ?? 'Imposta ora') ?></a><?php endif; ?></div>
  <?php endforeach; ?>

  <div class="cifre cifre--4">
    <?php foreach ([['', 'people', 'Clienti', $numeri['clienti'], 'registrati', '/admin/clienti'],
                    ['cifra--pino', 'card', 'Abbonamenti', $numeri['abbonati'], 'attivi su Stripe', '/admin/abbonamenti'],
                    ['cifra--mare', 'book', 'Guide pubblicate', $numeri['pubblicate'], 'su ' . (int) $numeri['guide'] . ' in tutto', '/admin/guide'],
                    ['cifra--ocra', 'euro', 'Incassato', Support::money($numeri['incassato']), 'con Stripe, IVA esclusa', null],
                    ['cifra--carta', 'eye', 'Aperture', $numeri['aperture'], 'delle guide, ultimi 30 giorni', null],
                    ['cifra--carta', 'clock', 'In attesa', $numeri['in_attesa'], 'pagamenti da confermare', null],
                    ['cifra--carta', 'ban', 'Rinnovo disattivato', $numeri['rinnovo_off'], 'abbonamenti che non si rinnovano', null],
                    [$numeri['falliti'] ? 'cifra--rosa' : 'cifra--carta', 'warning', 'Rinnovi falliti', $numeri['falliti'], 'carte da aggiornare', '/admin/anomalie'],
                    ['cifra--pino', 'chart', 'Ricavo annuo ricorrente', Support::money($numeri['arr']), 'abbonamenti che si rinnovano da soli', '/admin/prospetti'],
                    ['cifra--mare', 'calendar', 'Rinnovi entro 30 giorni', $numeri['rinnovi30'][0], 'incasso atteso ' . Support::money($numeri['rinnovi30'][1]), '/admin/scadenze?giorni=30'],
                    [$numeri['scadono30'] ? 'cifra--ocra' : 'cifra--carta', 'clock', 'Scadono senza rinnovo', $numeri['scadono30'], 'entro 30 giorni: da avvisare', '/admin/scadenze?giorni=30'],
                    [$numeri['anomalie']['alta'] ? 'cifra--rosa' : 'cifra--carta', 'pulse', 'Anomalie', array_sum($numeri['anomalie']),
                     $numeri['anomalie']['alta'] ? $numeri['anomalie']['alta'] . ' da guardare subito' : 'nessuna urgente', '/admin/anomalie']] as [$tono, $ico, $et, $val, $nota, $href]):
          $tag = $href ? 'a' : 'div'; ?>
      <<?= $tag ?> class="cifra <?= $tono ?>"<?= $href ? ' href="' . b() . $href . '"' : '' ?>>
        <span class="cifra__testa"><span class="cifra__ico"><?= Icon::svg($ico, 17) ?></span><?= $et ?></span>
        <?php if ($href): ?><?= Icon::svg('arrow', 18, 2, 'cifra__freccia') ?><?php endif; ?>
        <b class="cifra__valore"><?= Support::e((string) $val) ?></b><span class="cifra__nota"><?= Support::e($nota) ?></span>
      </<?= $tag ?>>
    <?php endforeach; ?>
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
    <div class="spread spread--mid"><h2 style="font-size:22px">Listino</h2><a class="small" href="<?= b() ?>/admin/pacchetti">Gestisci i piani</a></div>
    <div class="tablewrap"><table class="data">
      <thead><tr><th>Piano in vendita</th><th>Versione</th><th>Prezzo</th><th>Price ID di Stripe</th><th>Clienti attivi</th></tr></thead>
      <tbody>
      <?php foreach ($piani as $p): /* il prezzo come nel listino: Portfolio «127 € + 70 € dalla 2ª» */
            $tiers = MHW\Plans::tiers($p);
            $prezzo = Support::money((int) $p['price_cents'], $p['currency'])
                . (MHW\Plans::perProperty($p) ? (count($tiers) > 1 ? ' + scaglioni dalla 2ª' : ' + ' . Support::money(MHW\Plans::unitPrice($p, 2), $p['currency']) . ' dalla 2ª') : ''); ?>
        <tr><td><?= Support::e($p['name']) ?></td><td>v<?= (int) $p['version'] ?></td>
          <td><?= Support::e($prezzo) ?> + IVA</td>
          <td><code><?= Support::e($p['stripe_price_id'] ?: '— (prezzo della versione)') ?></code></td>
          <td><?= (int) $p['clienti'] ?></td></tr>
      <?php endforeach; ?>
      </tbody></table></div>
    <a class="small" href="<?= b() ?>/admin/pacchetti">Versioni precedenti e piani nascosti</a>
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
          <td><span class="pill pill--<?= ['paid' => 'pine', 'pending' => 'ochre', 'awaiting' => 'sea', 'failed' => 'alert'][$o['status']] ?? '' ?>"><?= Support::e($statoOrdine[$o['status']] ?? $o['status']) ?></span> <span class="tiny muted"><?= Support::e($viaPagamento[$o['provider']] ?? $o['provider']) ?></span></td></tr>
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
      <p class="small">Crea tre clienti di esempio (Essential, Plus, Portfolio); la guida di Casa Lucia diventa la demo pubblica.</p>
      <form method="post" action="<?= b() ?>/admin/dati-esempio"><?= Csrf::field() ?><button class="btn btn--ghost btn--sm" name="cosa" value="crea">Crea i clienti di esempio</button></form>
    <?php endif; ?>
  </section>
</div>
