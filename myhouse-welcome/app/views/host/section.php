<?php use function MHW\b; use MHW\{Support, Csrf, Media}; $title = 'Sezione'; $nav = 'pannello'; ?>
<p class="small"><a href="<?= b() ?>/pannello/<?= (int) $p['id'] ?>">&larr; <?= Support::e($p['name']) ?></a></p>
<h1 style="margin-top:10px"><?= Support::e($tr['title'] ?: 'Sezione') ?></h1>
<?php if ($err): ?><p class="note note--err" style="margin-top:16px"><?= Support::e($err) ?></p><?php endif; ?>

<form method="post" enctype="multipart/form-data" class="card" style="margin-top:20px;max-width:680px"><?= Csrf::field() ?>
  <input type="hidden" name="azione" value="salva">
  <div class="field"><label for="title">Titolo</label>
    <input id="title" name="title" type="text" value="<?= Support::e($tr['title']) ?>" required></div>
  <div class="field"><label for="body">Testo</label>
    <textarea id="body" name="body" placeholder="Scrivete come parlereste a un ospite."><?= Support::e($tr['body']) ?></textarea></div>
  <div class="grid grid-2">
    <div class="field"><label for="kind">Tipo</label>
      <select id="kind" name="kind">
        <?php foreach (['text' => 'Testo semplice', 'wifi' => 'Wi-Fi', 'checkin' => 'Entrare in casa', 'places' => 'Consigli sul posto'] as $k => $l): ?>
          <option value="<?= $k ?>" <?= $s['kind'] === $k ? 'selected' : '' ?>><?= $l ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="field"><label for="color">Colore</label>
      <select id="color" name="color">
        <?php foreach (['terracotta', 'sea', 'pine', 'ochre', 'alert'] as $c): ?>
          <option value="<?= $c ?>" <?= $s['color'] === $c ? 'selected' : '' ?>><?= $c ?></option>
        <?php endforeach; ?>
      </select></div>
  </div>
  <div class="grid grid-2">
    <div class="field"><label for="wifi_ssid">Nome della rete Wi-Fi</label>
      <input id="wifi_ssid" name="wifi_ssid" type="text" value="<?= Support::e($s['wifi_ssid']) ?>"></div>
    <div class="field"><label for="wifi_pass">Password Wi-Fi</label>
      <input id="wifi_pass" name="wifi_pass" type="text" value="<?= Support::e($s['wifi_pass']) ?>"></div>
  </div>
  <div class="field"><label for="door_code">Codice della cassetta</label>
    <input id="door_code" name="door_code" type="text" value="<?= Support::e($s['door_code']) ?>"></div>
  <div class="field"><label for="foto">Foto</label>
    <?php if ($ent['photos']['value'] === '0'): ?>
      <p class="note">Le foto sono comprese dal piano Plus in su.</p>
    <?php else: ?>
      <?php if ($u = Media::url($s['media_id'] ? (int) $s['media_id'] : null)): ?>
        <div class="cover" style="max-width:240px;margin-bottom:10px"><img src="<?= Support::e($u) ?>" alt=""></div>
      <?php endif; ?>
      <input id="foto" name="foto" type="file" accept="image/jpeg,image/png,image/webp">
    <?php endif; ?>
  </div>
  <button class="btn">Salva la sezione</button>
</form>

<?php if ($s['kind'] === 'places'): ?>
<section style="margin-top:32px;max-width:680px">
  <h2>I luoghi</h2>
  <?php if ($ent['places']['value'] === '0'): ?>
    <p class="note" style="margin-top:12px">I consigli sul posto sono compresi dal piano Plus in su.</p>
  <?php else: ?>
    <div class="list" style="margin-top:12px">
      <?php foreach ($places as $pl): ?>
        <div class="list-item">
          <?php if ($u = Media::url($pl['media_id'] ? (int) $pl['media_id'] : null)): ?>
            <span class="thumb"><img src="<?= Support::e($u) ?>" alt=""></span>
          <?php endif; ?>
          <span class="grow"><strong><?= Support::e($pl['name']) ?></strong>
            <span class="muted small" style="display:block"><?= Support::e(trim($pl['category'] . ' · ' . $pl['distance'], ' ·')) ?></span></span>
          <form method="post"><?= Csrf::field() ?>
            <input type="hidden" name="azione" value="elimina-luogo">
            <input type="hidden" name="place_id" value="<?= (int) $pl['id'] ?>">
            <button class="btn btn--quiet btn--sm">Togli</button></form>
        </div>
      <?php endforeach; ?>
      <?php if (!$places): ?><p class="muted small">Nessun luogo ancora.</p><?php endif; ?>
    </div>
    <form method="post" enctype="multipart/form-data" class="card" style="margin-top:16px"><?= Csrf::field() ?>
      <input type="hidden" name="azione" value="luogo">
      <div class="grid grid-2">
        <div class="field"><label for="nome">Nome</label><input id="nome" name="nome" type="text" required></div>
        <div class="field"><label for="categoria">Categoria</label><input id="categoria" name="categoria" type="text" placeholder="Trattoria"></div>
      </div>
      <div class="grid grid-2">
        <div class="field"><label for="distanza">Distanza</label><input id="distanza" name="distanza" type="text" placeholder="450 m · a piedi"></div>
        <div class="field"><label for="badge">Etichetta</label><input id="badge" name="badge" type="text" placeholder="Aperto fino alle 23"></div>
      </div>
      <div class="field"><label for="nota">Nota</label><input id="nota" name="nota" type="text" placeholder="Si prenota solo per telefono."></div>
      <div class="field"><label for="tono">Tono dell'etichetta</label>
        <select id="tono" name="tono">
          <?php foreach (['pine' => 'verde', 'sea' => 'blu', 'ochre' => 'ocra', 'terracotta' => 'terracotta', 'alert' => 'rosso'] as $k => $l): ?>
            <option value="<?= $k ?>"><?= $l ?></option><?php endforeach; ?>
        </select></div>
      <div class="field"><label for="fotoluogo">Foto</label>
        <input id="fotoluogo" name="foto" type="file" accept="image/jpeg,image/png,image/webp"></div>
      <button class="btn btn--ghost">Aggiungi il luogo</button>
    </form>
  <?php endif; ?>
</section>
<?php endif; ?>

<form method="post" style="margin-top:32px"
      onsubmit="return confirm('Eliminare questa sezione? Non si torna indietro.')"><?= Csrf::field() ?>
  <input type="hidden" name="azione" value="elimina">
  <button class="btn btn--danger btn--sm">Elimina la sezione</button>
</form>
