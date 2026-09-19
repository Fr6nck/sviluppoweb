<?php use MHW\Support; $pr = $snap['property']; $title = $pr['name'];
$tone = ['terracotta' => 't-terracotta', 'sea' => 't-sea', 'pine' => 't-pine', 'ochre' => 't-ochre', 'alert' => 't-alert']; ?>

<?php if (count($snap['locales']) > 1): ?>
  <nav class="nav" style="margin-bottom:18px">
    <?php foreach ($snap['locales'] as $l): ?>
      <a href="/g/<?= Support::e($slug) ?>?l=<?= Support::e($l) ?>" class="<?= $l === $loc ? 'on' : '' ?>"
         hreflang="<?= Support::e($l) ?>"><?= Support::e(strtoupper($l)) ?></a>
    <?php endforeach; ?>
  </nav>
<?php endif; ?>

<h1><?= Support::e($pr['name']) ?></h1>
<?php if ($pr['city']): ?><p class="muted" style="margin-top:8px"><?= Support::e($pr['city']) ?></p><?php endif; ?>

<?php if ($pr['cover']): ?>
  <div class="cover" style="margin-top:18px"><img src="<?= Support::e($pr['cover']) ?>" alt="<?= Support::e($pr['cover_alt']) ?>"></div>
<?php endif; ?>

<p class="row small" style="margin-top:16px">
  <span class="badge badge--pine">Arrivo dalle <?= Support::e($pr['checkin_from']) ?></span>
  <span class="badge badge--ochre">Partenza entro <?= Support::e($pr['checkout_by']) ?></span>
</p>

<div class="grid grid-2" style="margin-top:18px">
  <?php foreach ($snap['sections'] as $s):
    $t = $s['tr'][$loc] ?? $s['tr'][$pr['default_locale']] ?? ['title' => '', 'body' => '']; ?>
    <a class="tile <?= $tone[$s['color']] ?? 't-terracotta' ?>"
       href="/g/<?= Support::e($slug) ?>/<?= (int) $s['id'] ?>?l=<?= Support::e($loc) ?>">
      <span class="kicker" style="color:inherit;opacity:.85"><?= Support::e($s['kind']) ?></span>
      <h3><?= Support::e($t['title']) ?></h3>
    </a>
  <?php endforeach; ?>
</div>

<?php if ($pr['host_phone'] || $pr['host_whatsapp']): ?>
  <div class="row" style="margin-top:22px;gap:10px">
    <?php if ($pr['host_phone']): ?>
      <a class="btn btn--ghost grow" href="tel:<?= Support::e($pr['host_phone']) ?>">Chiama <?= Support::e($pr['host_name'] ?: 'chi ospita') ?></a>
    <?php endif; ?>
    <?php if ($pr['host_whatsapp']): ?>
      <a class="btn grow" href="https://wa.me/<?= Support::e(preg_replace('/\D/', '', $pr['host_whatsapp'])) ?>">WhatsApp</a>
    <?php endif; ?>
  </div>
<?php endif; ?>

<p class="tiny muted" style="margin-top:28px;text-align:center">Guida aggiornata al <?= Support::e(substr($snap['published_at'], 0, 10)) ?></p>
