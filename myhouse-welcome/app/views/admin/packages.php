<?php
/* Pacchetti e versioni. Cambiare prezzo o funzioni crea una versione nuova:
   chi ha già comprato resta sulla sua. I testi commerciali si cambiano sul
   pacchetto e compaiono subito sulla landing. */
use function MHW\b;
use MHW\{Support, Csrf};
$title = 'Pacchetti'; ?>
<div class="stack stack--lg">
  <div class="stack stack--sm">
    <h1>Pacchetti.</h1>
    <p class="muted small">Prezzi IVA esclusa, annuali. Una versione venduta non si modifica mai: si crea la successiva. Il Price ID di Stripe è facoltativo:
      se manca, il checkout usa il prezzo della versione.</p>
  </div>

  <?php foreach ($packages as $p): $cur = null; foreach ($p['versions'] as $v) if ($v['is_current']) $cur = $v; $cur ??= $p['versions'][0] ?? null; ?>
    <section class="panel stack" id="pk-<?= (int) $p['id'] ?>">
      <div class="spread spread--mid">
        <div class="stack" style="gap:4px"><b style="font-size:22px;font-weight:500"><?= Support::e($p['name']) ?></b>
          <span class="small muted"><code><?= Support::e($p['code']) ?></code><?= $p['family'] ? ' · famiglia ' . Support::e($p['family']) : '' ?>
            · <?= !$p['active'] ? 'disattivato' : ($p['public'] ? 'pubblico' : 'nascosto') ?></span></div>
        <?php if ($cur): ?><span style="font-size:22px;font-weight:500"><?= Support::e(Support::money((int) $cur['price_cents'], $cur['currency'])) ?> <span class="small muted">+ IVA / anno · v<?= (int) $cur['version'] ?></span></span><?php endif; ?>
      </div>

      <div class="tablewrap"><table class="data">
        <thead><tr><th>Versione</th><th>Prezzo</th><th>Price ID</th><th>Funzioni</th><th>Attivi</th><th>Venduti</th></tr></thead>
        <tbody><?php foreach ($p['versions'] as $v): ?>
          <tr><td>v<?= (int) $v['version'] ?><?= $v['is_current'] ? ' <span class="badge badge--pine">in vendita</span>' : '' ?><br><span class="tiny muted"><?= Support::e(Support::date($v['created_at'])) ?></span></td>
            <td><?= Support::e(Support::money((int) $v['price_cents'], $v['currency'])) ?></td>
            <td><code><?= Support::e($v['stripe_price_id'] ?: '—') ?></code></td>
            <td class="small"><?= Support::e(implode(' · ', array_map(fn($k, $x) => "$k=$x", array_keys(array_filter($v['features'], fn($x) => $x !== '0')), array_filter($v['features'], fn($x) => $x !== '0')))) ?></td>
            <td><?= (int) $v['clienti'] ?></td><td><?= (int) $v['sold_count'] ?></td></tr>
        <?php endforeach; ?></tbody></table></div>

      <details class="fieldset">
        <summary class="legend" style="cursor:pointer;min-height:32px">Testi della landing</summary>
        <form method="post" action="<?= b() ?>/admin/pacchetti/<?= (int) $p['id'] ?>/testo" class="stack" style="margin-top:12px"><?= Csrf::field() ?>
          <?php include __DIR__ . '/_testi_pacchetto.php'; ?>
          <div class="actions"><button class="btn btn--sm">Salva i testi</button></div>
        </form>
      </details>

      <details class="fieldset">
        <summary class="legend" style="cursor:pointer;min-height:32px">Nuova versione (prezzo o funzioni)</summary>
        <form method="post" action="<?= b() ?>/admin/pacchetti/<?= (int) $p['id'] ?>/nuova-versione" class="stack" style="margin-top:12px"><?= Csrf::field() ?>
          <div class="grid grid-3">
            <div class="field" style="margin:0"><label>Nome</label><input name="nome" value="<?= Support::e($p['name']) ?>" maxlength="80"></div>
            <div class="field" style="margin:0"><label>Prezzo annuale (€, IVA esclusa)</label>
              <input name="prezzo" inputmode="decimal" required value="<?= $cur ? Support::e(number_format($cur['price_cents'] / 100, 2, ',', '')) : '' ?>"></div>
            <div class="field" style="margin:0"><label>Stripe Price ID <span class="muted">(facoltativo)</span></label>
              <input name="stripe_price_id" placeholder="price_…" pattern="price_[A-Za-z0-9]+" value="<?= Support::e($cur['stripe_price_id'] ?? '') ?>"></div>
          </div>
          <div class="grid grid-4">
            <?php foreach ($features as $f): ?>
              <div class="field" style="margin:0"><label class="small" for="f-<?= (int) $p['id'] ?>-<?= Support::e($f['code']) ?>"><?= Support::e($f['label']) ?></label>
                <input id="f-<?= (int) $p['id'] ?>-<?= Support::e($f['code']) ?>" name="f[<?= Support::e($f['code']) ?>]" value="<?= Support::e($cur['features'][$f['code']] ?? $f['default_value']) ?>"
                       pattern="\d{1,4}|unlimited" title="Un numero, 0/1, oppure unlimited"></div>
            <?php endforeach; ?>
          </div>
          <?php include __DIR__ . '/_testi_pacchetto.php'; ?>
          <p class="small muted">Gli abbonamenti già attivi restano sulla loro versione. Se usi un Price ID, deve essere un prezzo annuale ricorrente creato in Stripe con lo stesso importo.</p>
          <div class="actions"><button class="btn btn--sm">Crea la versione <?= $cur ? (int) $cur['version'] + 1 : 1 ?></button></div>
        </form>
      </details>
    </section>
  <?php endforeach; ?>
</div>
