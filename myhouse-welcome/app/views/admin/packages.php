<?php use function MHW\b; use MHW\{Support, Csrf}; $title = 'Pacchetti'; $nav = 'pacchetti'; ?>
<h1>Pacchetti</h1>
<p class="note" style="margin-top:16px;max-width:680px">Modificare un pacchetto non tocca chi l'ha già comprato:
si crea una <strong>versione nuova</strong>, e gli abbonamenti già venduti restano agganciati alla loro.</p>

<?php foreach ($packages as $pk): $cur = $pk['versions'][0] ?? null; ?>
  <section class="card" style="margin-top:24px">
    <div class="spread">
      <h2><?= Support::e($pk['name']) ?></h2>
      <span class="muted small"><?= count($pk['versions']) ?> versione/i</span>
    </div>
    <form method="post" action="<?= b() ?>/admin/pacchetti/<?= (int) $pk['id'] ?>/nuova-versione" style="margin-top:16px"><?= Csrf::field() ?>
      <div class="grid grid-2">
        <div class="field"><label for="nome<?= (int) $pk['id'] ?>">Nome</label>
          <input id="nome<?= (int) $pk['id'] ?>" name="nome" type="text" value="<?= Support::e($pk['name']) ?>" required></div>
        <div class="field"><label for="prezzo<?= (int) $pk['id'] ?>">Prezzo all'anno (euro)</label>
          <input id="prezzo<?= (int) $pk['id'] ?>" name="prezzo" type="text"
                 value="<?= $cur ? number_format((int) $cur['price_cents'] / 100, 2, ',', '') : '0' ?>" required></div>
      </div>
      <div class="field"><label for="sub<?= (int) $pk['id'] ?>">Sottotitolo</label>
        <input id="sub<?= (int) $pk['id'] ?>" name="sottotitolo" type="text" value="<?= Support::e($pk['tagline']) ?>"></div>
      <p class="kicker" style="margin-top:8px">Cosa comprende</p>
      <div class="grid grid-2" style="margin-top:10px">
        <?php foreach ($features as $f):
          $val = '0';
          foreach (($cur['features'] ?? []) as $cf) if ($cf['code'] === $f['code']) $val = $cf['value']; ?>
          <div class="field"><label for="f<?= (int) $pk['id'] . $f['code'] ?>"><?= Support::e($f['label']) ?>
            <span class="muted tiny">(<?= Support::e($f['kind']) ?>)</span></label>
            <input id="f<?= (int) $pk['id'] . $f['code'] ?>" name="f[<?= Support::e($f['code']) ?>]" type="text"
                   value="<?= Support::e($val) ?>" placeholder="0, un numero, oppure unlimited"></div>
        <?php endforeach; ?>
      </div>
      <button class="btn">Salva come versione nuova</button>
    </form>
    <details style="margin-top:16px"><summary class="small muted">Storico delle versioni</summary>
      <table style="margin-top:12px">
        <thead><tr><th>Versione</th><th>Prezzo</th><th>Venduti</th><th>Corrente</th></tr></thead>
        <tbody><?php foreach ($pk['versions'] as $v): ?>
          <tr><td>v<?= (int) $v['version'] ?></td>
            <td><?= Support::e(Support::money((int) $v['price_cents'], $v['currency'])) ?></td>
            <td><?= (int) $v['sold_count'] ?></td>
            <td><?= $v['is_current'] ? 'sì' : '' ?></td></tr>
        <?php endforeach; ?></tbody>
      </table>
    </details>
  </section>
<?php endforeach; ?>
