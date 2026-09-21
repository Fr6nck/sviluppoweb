<?php use function MHW\b; use MHW\{Support, Icon};
$pr = $snap['property']; $title = $pr['name'];
$tono = ['terracotta' => 't-terracotta', 'sea' => 't-sea', 'pine' => 't-pine',
         'ochre' => 't-ochre', 'alert' => 't-alert'];
$icona = ['checkin' => 'home', 'wifi' => 'wifi', 'places' => 'fork', 'text' => 'pin'];
$iniziali = mb_strtoupper(mb_substr($pr['name'], 0, 1)
          . (($sp = mb_strpos($pr['name'], ' ')) !== false ? mb_substr($pr['name'], $sp + 1, 1) : ''));
$tel = preg_replace('/\D/', '', (string) $pr['host_whatsapp']); ?>

<div class="guest-top">
  <div class="row" style="gap:9px">
    <span class="avatar avatar--sm"><?= Support::e($iniziali) ?></span>
    <span class="guest-name"><?= Support::e($pr['name']) ?></span>
  </div>
  <div class="row" style="gap:8px">
    <?php include __DIR__ . '/../layout/_tema-bottone.php'; ?>
    <?php include __DIR__ . '/_lingue.php'; ?>
  </div>
</div>

<h1 class="guest-title" style="margin-top:22px">Benvenuti<br>a <?= Support::e($pr['name']) ?>.</h1>

<?php if ($pr['cover']): ?>
  <div class="shot shot--h262" style="margin-top:22px">
    <img src="<?= Support::e($pr['cover']) ?>" alt="<?= Support::e($pr['cover_alt']) ?>">
    <span class="shot-pill"><span class="dot"></span>Check-in dalle <?= Support::e($pr['checkin_from']) ?></span>
  </div>
<?php else: ?>
  <div class="row" style="margin-top:22px">
    <span class="badge badge--pine"><span class="dot"></span>Arrivo dalle <?= Support::e($pr['checkin_from']) ?></span>
    <span class="badge badge--ochre">Partenza entro <?= Support::e($pr['checkout_by']) ?></span>
  </div>
<?php endif; ?>

<div class="tiles-2" style="margin-top:22px">
  <?php foreach ($snap['sections'] as $s):
    $t = $s['tr'][$loc] ?? $s['tr'][$pr['default_locale']] ?? ['title' => '', 'body' => '']; ?>
    <a class="tile <?= $tono[$s['color']] ?? 't-terracotta' ?>"
       href="<?= b() ?>/g/<?= Support::e($slug) ?>/<?= (int) $s['id'] ?>?l=<?= Support::e($loc) ?>">
      <?= Icon::svg($icona[$s['kind']] ?? 'pin', 22, 1.7) ?>
      <b><?= Support::e($t['title'] ?: '—') ?></b>
    </a>
  <?php endforeach; ?>
</div>

<?php if ($pr['city']): ?>
  <p class="small muted" style="margin-top:20px;text-align:center">
    <?= Support::e(trim($pr['city'] . ($pr['region'] ? ', ' . $pr['region'] : ''))) ?>
    · partenza entro le <?= Support::e($pr['checkout_by']) ?></p>
<?php endif; ?>

<p style="margin-top:14px;text-align:center">
  <a class="small muted" href="<?= b() ?>/g/<?= Support::e($slug) ?>/commiato?l=<?= Support::e($loc) ?>">
    Stiamo per partire &rarr;</a></p>

<?php if ($pr['host_phone'] || $tel): ?>
  <div class="guest-bottom">
    <?php if ($pr['host_phone']): ?>
      <a class="btn btn--ghost" href="tel:<?= Support::e($pr['host_phone']) ?>">
        <?= Icon::svg('phone', 17) ?>Chiama <?= Support::e($pr['host_name'] ?: 'chi ospita') ?></a>
    <?php endif; ?>
    <?php if ($tel): ?>
      <a class="btn" href="https://wa.me/<?= Support::e($tel) ?>"><?= Icon::svg('whatsapp', 17) ?>WhatsApp</a>
    <?php endif; ?>
  </div>
<?php endif; ?>

<p class="tiny muted" style="margin:18px 0 28px;text-align:center">
  Guida aggiornata al <?= Support::e(substr($snap['published_at'], 0, 10)) ?></p>
