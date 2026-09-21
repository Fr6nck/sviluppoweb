<?php use function MHW\b; use MHW\{Support, Csrf, Icon}; $title = 'Pacchetti'; $nav = 'pacchetti'; ?>

<div class="stack stack--sm">
  <h1>Il listino.</h1>
  <p class="lead" style="max-width:620px">I piani non sono scritti nel codice: nome, prezzo e
    contenuto si cambiano da qui.</p>
</div>

<p class="note" style="margin-top:24px;max-width:720px"><?= Icon::svg('info', 19) ?>
  <span>Modificare un piano non tocca chi l'ha già comprato: nasce una <strong>versione nuova</strong>,
    e gli abbonamenti già venduti restano agganciati alla loro. Nessuno perde quello che ha pagato.</span></p>

<div class="grid grid-3" style="margin-top:28px;gap:16px;align-items:start">
  <?php foreach ($packages as $pk): $cur = $pk['versions'][0] ?? null;
    $venduti = array_sum(array_map(fn($v) => (int) $v['sold_count'], $pk['versions'])); ?>
    <form method="post" action="<?= b() ?>/admin/pacchetti/<?= (int) $pk['id'] ?>/nuova-versione"
          class="panel stack"><?= Csrf::field() ?>
      <span class="kicker">Piano <?= Support::e($pk['name']) ?> · versione <?= $cur ? (int) $cur['version'] : 1 ?></span>

      <div class="field" style="margin:0"><label for="nome<?= (int) $pk['id'] ?>">Come si chiama</label>
        <input id="nome<?= (int) $pk['id'] ?>" name="nome" type="text" value="<?= Support::e($pk['name']) ?>" required></div>

      <div class="field" style="margin:0"><label for="prezzo<?= (int) $pk['id'] ?>">Prezzo all'anno (euro)</label>
        <input id="prezzo<?= (int) $pk['id'] ?>" name="prezzo" type="text"
               value="<?= $cur ? number_format((int) $cur['price_cents'] / 100, 2, ',', '') : '0' ?>" required></div>

      <div class="field" style="margin:0"><label for="sub<?= (int) $pk['id'] ?>">Sottotitolo</label>
        <input id="sub<?= (int) $pk['id'] ?>" name="sottotitolo" type="text" value="<?= Support::e($pk['tagline']) ?>"></div>

      <hr class="rule">
      <span class="kicker">Cosa comprende</span>

      <?php foreach ($features as $f):
        $val = '0';
        foreach (($cur['features'] ?? []) as $cf) if ($cf['code'] === $f['code']) $val = $cf['value'];
        $id = 'f' . (int) $pk['id'] . $f['code']; ?>
        <?php if ($f['kind'] === 'bool'): ?>
          <div class="spread spread--mid" style="gap:12px">
            <label for="<?= $id ?>" style="margin:0;font-weight:400;font-size:15px"><?= Support::e($f['label']) ?></label>
            <input type="hidden" name="f[<?= Support::e($f['code']) ?>]" value="0">
            <input id="<?= $id ?>" type="checkbox" name="f[<?= Support::e($f['code']) ?>]" value="1"
                   <?= $val !== '0' ? 'checked' : '' ?> style="width:auto;height:auto;min-height:0">
          </div>
        <?php else: ?>
          <div class="field" style="margin:0"><label for="<?= $id ?>"><?= Support::e($f['label']) ?>
            <span class="muted">— un numero, oppure <code>unlimited</code></span></label>
            <input id="<?= $id ?>" name="f[<?= Support::e($f['code']) ?>]" type="text" value="<?= Support::e($val) ?>"></div>
        <?php endif; ?>
      <?php endforeach; ?>

      <div class="note note--quiet">Salvando nasce la versione <?= $cur ? (int) $cur['version'] + 1 : 1 ?>.
        <?= $venduti ?> abbonamenti già venduti restano dove sono.</div>

      <button class="btn">Salva come versione nuova</button>

      <details style="margin-top:4px"><summary class="small muted" style="cursor:pointer">Storico delle versioni</summary>
        <table style="margin-top:12px">
          <thead><tr><th>V.</th><th>Prezzo</th><th>Venduti</th><th></th></tr></thead>
          <tbody><?php foreach ($pk['versions'] as $v): ?>
            <tr><td>v<?= (int) $v['version'] ?></td>
              <td><?= Support::e(Support::money((int) $v['price_cents'], $v['currency'])) ?></td>
              <td><?= (int) $v['sold_count'] ?></td>
              <td><?= $v['is_current'] ? '<span class="badge badge--pine">in vendita</span>' : '' ?></td></tr>
          <?php endforeach; ?></tbody>
        </table>
      </details>
    </form>
  <?php endforeach; ?>
</div>
