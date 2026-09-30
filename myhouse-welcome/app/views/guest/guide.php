<?php use MHW\{Support, Icon, Guide, SectionCatalog, I18n};
$pr = $snap['property']; $def = $pr['default_locale'];
$title = $pr['name'];
$toni = ['t-terracotta', 't-sea', 't-pine', 't-ochre'];
$tel = Support::telHref((string) $pr['host_whatsapp']);
$copertina = !empty($pr['cover_id']) ? MHW\Media::url((int) $pr['cover_id']) : ($pr['cover_url'] ?? null); ?>

<?php include __DIR__ . '/_top.php'; ?>

<?php if (!empty($pr['is_demo'])): ?>
  <p class="small muted" style="margin-top:14px"><?= Support::e(I18n::t($loc, 'demo_note')) ?></p>
<?php endif; ?>

<h1 class="guest-title" style="margin-top:22px"><?= Support::e(I18n::t($loc, 'welcome_1')) ?><br><?= Support::e(I18n::t($loc, 'welcome_2', $pr['name'])) ?></h1>

<?php if ($copertina): ?>
  <div class="shot shot--h262 guest-cover" style="margin-top:22px">
    <img src="<?= Support::e($copertina) ?>" alt="" fetchpriority="high" decoding="async">
    <span class="shot-pill"><span class="dot"></span><?= Support::e(I18n::t($loc, 'checkin_from', $pr['checkin_from'])) ?></span>
  </div>
<?php else: ?>
  <div class="row" style="margin-top:22px">
    <span class="badge badge--pine"><span class="dot"></span><?= Support::e(I18n::t($loc, 'checkin_from', $pr['checkin_from'])) ?></span>
    <span class="badge badge--ochre"><?= Support::e(I18n::t($loc, 'checkout_by', $pr['checkout_by'])) ?></span>
  </div>
<?php endif; ?>

<nav class="tiles-2" style="margin-top:22px" aria-label="<?= Support::e($pr['name']) ?>">
  <?php foreach ($snap['sections'] as $i => $s): ?>
    <a class="tile <?= $toni[$i % 4] ?>" href="<?= Support::e($base) ?>/<?= (int) $s['id'] ?>?l=<?= Support::e($loc) ?>">
      <?= Icon::svg(SectionCatalog::icon($s['kind']), 22, 1.7) ?>
      <b><?= Support::e(Guide::title($s, $loc, $def)) ?></b>
    </a>
  <?php endforeach; ?>
</nav>

<?php if ($pr['city']): ?>
  <p class="small muted" style="margin-top:20px;text-align:center">
    <?= Support::e(trim($pr['city'] . ($pr['region'] ? ', ' . $pr['region'] : ''))) ?> ·
    <?= Support::e(I18n::t($loc, 'checkout_by', $pr['checkout_by'])) ?></p>
<?php endif; ?>

<p style="margin-top:14px;text-align:center">
  <a class="small muted" href="<?= Support::e($base) ?>/commiato?l=<?= Support::e($loc) ?>"><?= Support::e(I18n::t($loc, 'leaving')) ?> &rarr;</a></p>

<?php if ($pr['host_phone'] || $tel): ?>
  <div class="guest-bottom">
    <?php if ($pr['host_phone']): ?>
      <a class="btn btn--ghost" href="tel:<?= Support::e(Support::telHref($pr['host_phone'])) ?>">
        <?= Icon::svg('phone', 17) ?><?= Support::e($pr['host_name'] ? I18n::t($loc, 'call', $pr['host_name']) : I18n::t($loc, 'call_host')) ?></a>
    <?php endif; ?>
    <?php if ($tel): ?>
      <a class="btn" href="https://wa.me/<?= Support::e(ltrim($tel, '+')) ?>" rel="noopener"><?= Icon::svg('whatsapp', 17) ?><?= Support::e(I18n::t($loc, 'whatsapp')) ?></a>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if (!empty($snap['published_at'])): ?>
  <p class="tiny muted" style="margin:18px 0 28px;text-align:center">
    <?= Support::e(I18n::t($loc, 'updated_on', gmdate('d/m/Y', strtotime($snap['published_at']) ?: time()))) ?></p>
<?php endif; ?>
