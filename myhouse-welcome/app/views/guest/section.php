<?php use MHW\Support; $pr = $snap['property'];
$t = $sec['tr'][$loc] ?? $sec['tr'][$pr['default_locale']] ?? ['title' => '', 'body' => ''];
$title = $t['title'] . ' — ' . $pr['name']; ?>

<p class="small"><a href="/g/<?= Support::e($slug) ?>?l=<?= Support::e($loc) ?>">&larr; <?= Support::e($pr['name']) ?></a></p>
<h1 style="margin-top:12px"><?= Support::e($t['title']) ?></h1>

<?php if ($sec['image']): ?>
  <div class="cover" style="margin-top:18px"><img src="<?= Support::e($sec['image']) ?>" alt="<?= Support::e($sec['image_alt']) ?>"></div>
<?php endif; ?>

<?php if (trim((string) $t['body']) !== ''): ?>
  <div style="margin-top:18px;white-space:pre-line;font-size:17px;line-height:1.6"><?= Support::e($t['body']) ?></div>
<?php endif; ?>

<?php if ($sec['kind'] === 'wifi' && ($sec['wifi_ssid'] || $sec['wifi_pass'])): ?>
  <div class="card" style="margin-top:20px">
    <?php if ($sec['wifi_ssid']): ?>
      <p class="kicker">Rete</p>
      <p style="font-size:22px;margin:6px 0 0"><?= Support::e($sec['wifi_ssid']) ?></p>
    <?php endif; ?>
    <?php if ($sec['wifi_pass']): ?>
      <p class="kicker" style="margin-top:16px">Password</p>
      <p style="font-size:22px;margin:6px 0 0;letter-spacing:.5px"><?= Support::e($sec['wifi_pass']) ?></p>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if ($sec['kind'] === 'checkin' && $sec['door_code']): ?>
  <div class="card" style="margin-top:20px">
    <p class="kicker">Codice della cassetta</p>
    <p style="font-size:34px;margin:8px 0 0;letter-spacing:4px;font-family:Gloock,Georgia,serif"><?= Support::e($sec['door_code']) ?></p>
  </div>
<?php endif; ?>

<?php if ($sec['places']): ?>
  <div class="list" style="margin-top:20px">
    <?php foreach ($sec['places'] as $pl): ?>
      <div class="list-item">
        <?php if ($pl['image']): ?><span class="thumb"><img src="<?= Support::e($pl['image']) ?>" alt="<?= Support::e($pl['image_alt']) ?>"></span><?php endif; ?>
        <span class="grow">
          <strong style="font-size:18px"><?= Support::e($pl['name']) ?></strong>
          <span class="muted small" style="display:block"><?= Support::e(trim($pl['category'] . ' · ' . $pl['distance'], ' ·')) ?></span>
          <?php if ($pl['note']): ?><span class="small" style="display:block;margin-top:4px"><?= Support::e($pl['note']) ?></span><?php endif; ?>
          <?php if ($pl['badge']): ?><span class="badge badge--<?= Support::e($pl['badge_tone']) ?>" style="margin-top:6px"><?= Support::e($pl['badge']) ?></span><?php endif; ?>
        </span>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
