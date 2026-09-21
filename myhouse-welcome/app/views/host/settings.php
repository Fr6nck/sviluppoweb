<?php use function MHW\b; use MHW\{Support, Csrf, Media, View, Icon};
$title = 'Impostazioni — ' . $p['name'];
$topnav = View::render('host/_propnav', ['p' => $p, 'qui' => 'impostazioni'], null);
$cop = Media::url($p['cover_media_id'] ? (int) $p['cover_media_id'] : null); ?>

<div class="stack stack--sm" style="margin-bottom:28px">
  <h1>Impostazioni.</h1>
  <p class="lead" style="max-width:520px">Le cose che gli ospiti vedono in cima alla guida, e i modi
    per raggiungervi quando qualcosa non torna.</p>
</div>

<?php if ($err): ?><p class="note note--err" style="margin-bottom:20px"><?= Support::e($err) ?></p><?php endif; ?>

<form method="post" enctype="multipart/form-data" class="sheet">
  <?= Csrf::field() ?>
  <div class="panel stack">
    <div class="field" style="margin:0"><label for="name">Nome della casa</label>
      <input id="name" name="name" type="text" value="<?= Support::e($p['name']) ?>" required></div>
    <div class="field" style="margin:0"><label for="city">Città</label>
      <input id="city" name="city" type="text" value="<?= Support::e($p['city']) ?>"></div>
    <div class="grid grid-2">
      <div class="field" style="margin:0"><label for="checkin_from">Arrivo dalle</label>
        <input id="checkin_from" name="checkin_from" type="text" value="<?= Support::e($p['checkin_from']) ?>"></div>
      <div class="field" style="margin:0"><label for="checkout_by">Partenza entro</label>
        <input id="checkout_by" name="checkout_by" type="text" value="<?= Support::e($p['checkout_by']) ?>"></div>
    </div>
    <hr class="rule">
    <div class="field" style="margin:0"><label for="host_name">Chi ospita</label>
      <input id="host_name" name="host_name" type="text" value="<?= Support::e($p['host_name']) ?>"></div>
    <div class="grid grid-2">
      <div class="field" style="margin:0"><label for="host_phone">Telefono</label>
        <input id="host_phone" name="host_phone" type="tel" value="<?= Support::e($p['host_phone']) ?>"></div>
      <div class="field" style="margin:0"><label for="host_whatsapp">WhatsApp</label>
        <input id="host_whatsapp" name="host_whatsapp" type="tel" value="<?= Support::e($p['host_whatsapp']) ?>"></div>
    </div>
    <div class="row" style="margin-top:4px"><button class="btn">Salva</button></div>
  </div>

  <div class="stack" style="gap:14px">
    <span class="kicker">Foto di copertina</span>
    <?php if ($ent['photos']['value'] === '0'): ?>
      <p class="note"><?= Icon::svg('info', 19) ?><span>Le foto sono comprese dal piano Plus in su.</span></p>
    <?php else: ?>
      <div class="shot" style="aspect-ratio:16/10">
        <?php if ($cop): ?><img src="<?= Support::e($cop) ?>" alt=""><?php endif; ?>
      </div>
      <label for="cover" class="small">Sostituisci la foto</label>
      <input id="cover" name="cover" type="file" accept="image/jpeg,image/png,image/webp">
      <p class="tiny muted">Viene riscritta in JPEG e ridotta a 1600 px sul lato lungo.
        Quello che c'era dentro il file originale non sopravvive.</p>
    <?php endif; ?>
  </div>
</form>
