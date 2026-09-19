<?php use MHW\{Support, Csrf, Media}; $title = 'Impostazioni'; $nav = 'pannello'; ?>
<h1>Impostazioni</h1>
<nav class="nav" style="margin-top:20px;display:inline-flex">
  <a href="/pannello/<?= (int) $p['id'] ?>">Sezioni</a>
  <a href="/pannello/<?= (int) $p['id'] ?>/lingue">Lingue</a>
  <a href="/pannello/<?= (int) $p['id'] ?>/qr">QR</a>
  <a class="on" href="/pannello/<?= (int) $p['id'] ?>/impostazioni">Impostazioni</a>
</nav>
<?php if ($err): ?><p class="note note--err" style="margin-top:16px"><?= Support::e($err) ?></p><?php endif; ?>
<form method="post" enctype="multipart/form-data" class="card" style="margin-top:20px;max-width:620px"><?= Csrf::field() ?>
  <div class="field"><label for="name">Nome</label>
    <input id="name" name="name" type="text" value="<?= Support::e($p['name']) ?>" required></div>
  <div class="field"><label for="city">Città</label>
    <input id="city" name="city" type="text" value="<?= Support::e($p['city']) ?>"></div>
  <div class="grid grid-2">
    <div class="field"><label for="checkin_from">Arrivo dalle</label>
      <input id="checkin_from" name="checkin_from" type="text" value="<?= Support::e($p['checkin_from']) ?>"></div>
    <div class="field"><label for="checkout_by">Partenza entro</label>
      <input id="checkout_by" name="checkout_by" type="text" value="<?= Support::e($p['checkout_by']) ?>"></div>
  </div>
  <div class="field"><label for="host_name">Chi ospita</label>
    <input id="host_name" name="host_name" type="text" value="<?= Support::e($p['host_name']) ?>"></div>
  <div class="grid grid-2">
    <div class="field"><label for="host_phone">Telefono</label>
      <input id="host_phone" name="host_phone" type="tel" value="<?= Support::e($p['host_phone']) ?>"></div>
    <div class="field"><label for="host_whatsapp">WhatsApp</label>
      <input id="host_whatsapp" name="host_whatsapp" type="tel" value="<?= Support::e($p['host_whatsapp']) ?>"></div>
  </div>
  <div class="field">
    <label for="cover">Foto di copertina</label>
    <?php if ($ent['photos']['value'] === '0'): ?>
      <p class="note">Le foto sono comprese dal piano Plus in su.</p>
    <?php else: ?>
      <?php if ($u = Media::url($p['cover_media_id'] ? (int) $p['cover_media_id'] : null)): ?>
        <div class="cover" style="max-width:280px;margin-bottom:10px"><img src="<?= Support::e($u) ?>" alt=""></div>
      <?php endif; ?>
      <input id="cover" name="cover" type="file" accept="image/jpeg,image/png,image/webp">
    <?php endif; ?>
  </div>
  <button class="btn">Salva</button>
</form>
