<?php
/* Il dettaglio di un codice sconto (6E): i dati, lo stato su Stripe, chi l'ha usato. */
use function MHW\b;
use MHW\{Support, Csrf, Sconti};
$title = 'Codice ' . $c['code'];
$giorno = fn(string $d) => Support::e(Support::date($d));
$pillola = ['attivo' => ['Attivo', 'pine'], 'programmato' => ['Programmato', 'sea'], 'scaduto' => ['Scaduto', 'paper'], 'esaurito' => ['Esaurito', 'ochre'],
            'disattivato' => ['Disattivato', 'paper'], 'da_sincronizzare' => ['Da sincronizzare', 'alert']][$c['stato']];
$scelti = Sconti::pacchetti($c); ?>
<div class="stack stack--lg" style="max-width:900px">
  <div class="stack stack--sm">
    <a class="small" href="<?= b() ?>/admin/sconti">← Codici sconto</a>
    <h1><?= Support::e($c['code']) ?> <span class="badge badge--<?= $pillola[1] ?>"><?= $pillola[0] ?></span></h1>
    <p class="muted"><?= Support::e(Sconti::etichetta($c)) ?> sul primo anno · dal <?= $giorno($c['valid_from']) ?> al <?= $giorno($c['valid_until']) ?>
      · <?= (int) $c['max_uses'] > 0 ? 'al massimo ' . (int) $c['max_uses'] . ' utilizzi' : 'senza limite di utilizzi' ?></p>
  </div>
  <section class="panel stack" style="gap:8px">
    <h2 style="font-size:20px">Su Stripe</h2>
    <?php if ($c['stripe_coupon_id'] !== ''): ?>
      <p class="small">Coupon <code><?= Support::e($c['stripe_coupon_id']) ?></code>, durata «una volta» (solo la prima fattura)<?= $c['stripe_synced_at'] ? ', creato il ' . Support::e(Support::date($c['stripe_synced_at'])) : '' ?>.</p>
    <?php else: ?>
      <p class="small">Non ancora creato su Stripe: il codice non si può usare.</p>
      <form method="post" action="<?= b() ?>/admin/sconti/<?= (int) $c['id'] ?>/sincronizza" style="margin:0"><?= Csrf::field() ?><input type="hidden" name="torna" value="dettaglio">
        <button class="btn btn--sm">Riprova la sincronizzazione</button></form>
    <?php endif; ?>
  </section>
  <form method="post" action="<?= b() ?>/admin/sconti/<?= (int) $c['id'] ?>/modifica" class="panel stack"><?= Csrf::field() ?><input type="hidden" name="torna" value="dettaglio">
    <h2 style="font-size:20px">Nota e piani</h2>
    <div class="field" style="margin:0"><label for="sc-note">Nota interna</label><input id="sc-note" name="note" type="text" maxlength="255" value="<?= Support::e($c['note']) ?>"></div>
    <fieldset class="fieldset" style="margin:0"><legend>Piani</legend>
      <div class="scelte scelte--riga">
        <label class="scelta scelta--mini"><input type="radio" name="piani" value="tutti" <?= !$scelti ? 'checked' : '' ?>><span>Tutti i piani</span></label>
        <label class="scelta scelta--mini"><input type="radio" name="piani" value="scelti" <?= $scelti ? 'checked' : '' ?>><span>Solo questi:</span></label>
        <?php foreach ($piani as $p): ?>
          <label class="scelta scelta--mini"><input type="checkbox" name="packages[]" value="<?= Support::e($p['code']) ?>" <?= in_array($p['code'], $scelti, true) ? 'checked' : '' ?>><span><?= Support::e($p['name']) ?></span></label>
        <?php endforeach; ?>
      </div></fieldset>
    <div class="row" style="gap:12px"><button class="btn btn--sm">Salva</button></div>
  </form>
  <?php if ((int) $c['active']): ?>
    <form method="post" action="<?= b() ?>/admin/sconti/<?= (int) $c['id'] ?>/disattiva" style="margin:0"><?= Csrf::field() ?><input type="hidden" name="torna" value="dettaglio">
      <button class="btn btn--ghost btn--sm">Disattiva il codice</button></form>
  <?php endif; ?>
  <section class="stack" style="gap:10px">
    <h2 style="font-size:22px">Utilizzi</h2>
    <?php if (!$usi): ?><p class="small muted">Nessuno ancora.</p><?php else: ?>
    <div class="tablewrap"><table class="data">
      <thead><tr><th>Cliente</th><th>Piano</th><th>Data</th><th>Sconto</th></tr></thead>
      <tbody><?php foreach ($usi as $x): ?>
        <tr><td><a href="<?= b() ?>/admin/cliente/<?= (int) $x['account_id'] ?>"><?= Support::e($x['email']) ?></a></td><td><?= Support::e($x['piano']) ?></td>
          <td class="small"><?= Support::e(Support::date($x['created_at'])) ?></td><td><?= "\u{2212}" . Support::e(Support::money((int) $x['discount_cents'])) ?></td></tr>
      <?php endforeach; ?></tbody>
      <tfoot><tr><th colspan="3">Totale</th><th><?= "\u{2212}" . Support::e(Support::money((int) array_sum(array_column($usi, 'discount_cents')))) ?></th></tr></tfoot>
    </table></div>
    <?php endif; ?>
  </section>
</div>
