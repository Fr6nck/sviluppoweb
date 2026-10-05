<?php use MHW\{Support, Icon, Guide, SectionCatalog, I18n, Eventi};
$pr = $snap['property']; $def = $pr['default_locale'];
$title = $pr['name'];
$toni = ['t-terracotta', 't-sea', 't-pine', 't-ochre'];
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

<?php /* Eventi (6G): la guida è un'istantanea, le date si guardano adesso. La casella si vede solo
         se c'è qualcosa nei prossimi due mesi; se c'è un evento oggi, una fascia scura in cima. */
$eventiDi = [];
foreach ($snap['sections'] as $s) if (($s['kind'] ?? '') === 'events') $eventiDi[(int) $s['id']] = Guide::rows($s, 'events', $loc, $def);
$oggiEv = Eventi::oggi();
foreach ($eventiDi as $sidEv => $righeEv):
    $diOggi = Eventi::diOggi($righeEv, $oggiEv); if (!$diOggi) continue; $primo = $diOggi[0];
    $sotto = array_filter([Eventi::orario($primo, $loc), trim((string) ($primo['place'] ?? '')), Eventi::distanza($primo, $loc)]); ?>
  <a class="oggi-eventi" href="<?= Support::e($base) ?>/<?= $sidEv ?>?l=<?= Support::e($loc) ?>">
    <?= Icon::svg('calendar', 22, 1.7) ?>
    <span class="grow"><span class="oggi-eventi__chi"><?= Support::e(I18n::t($loc, 'ev.today')) ?></span>
      <b style="display:block"><?= Support::e(trim((string) $primo['name'])) ?></b>
      <?php if ($sotto): ?><small><?= Support::e(implode(' · ', $sotto)) ?></small><?php endif; ?></span>
    <?php if (count($diOggi) > 1): ?><b>+<?= count($diOggi) - 1 ?></b><?php endif; ?>
  </a>
<?php break; endforeach; ?>

<nav class="tiles-2" style="margin-top:22px" aria-label="<?= Support::e($pr['name']) ?>">
  <?php $i = 0; foreach ($snap['sections'] as $s):
        if (isset($eventiDi[(int) $s['id']]) && !Eventi::daMostrare($eventiDi[(int) $s['id']], $oggiEv)) continue;
        $quanti = isset($eventiDi[(int) $s['id']]) ? Eventi::conta($eventiDi[(int) $s['id']], $oggiEv) : 0; ?>
    <a class="tile <?= $toni[$i++ % 4] ?>" href="<?= Support::e($base) ?>/<?= (int) $s['id'] ?>?l=<?= Support::e($loc) ?>">
      <?= Icon::svg(SectionCatalog::iconaDi($s['kind'], $s['data'] ?? []), 22, 1.7) ?>
      <b><?= Support::e(Guide::title($s, $loc, $def)) ?><?php if ($quanti > 0): ?><span class="tile__conta"><?= Support::e(I18n::t($loc, 'ev.count', $quanti)) ?></span><?php endif; ?></b>
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

<?php include __DIR__ . '/_contatti.php'; ?>

<?php $cin = trim(preg_replace('/^CIN\s*/i', '', (string) ($pr['cin'] ?? '')));
      if (!empty($snap['published_at']) || $cin !== ''): /* piè di pagina: aggiornamento e CIN, in piccolo */ ?>
  <p class="tiny muted" style="margin:18px 0 28px;text-align:center">
    <?php if (!empty($snap['published_at'])): ?><?= Support::e(I18n::t($loc, 'updated_on', gmdate('d/m/Y', strtotime($snap['published_at']) ?: time()))) ?><?php endif; ?>
    <?php if ($cin !== ''): ?><?= !empty($snap['published_at']) ? '<br>' : '' ?>CIN <?= Support::e($cin) ?><?php endif; ?></p>
<?php endif; ?>
<?php if ($pr['branding'] ?? true): /* la firma: un link alla landing, senza cookie; nascondibile con Plus e Portfolio */ ?>
  <p class="tiny firma"><?= Support::e(I18n::t($loc, 'made_with')) ?> ·
    <a href="<?= Support::e(Support::baseUrl()) ?>/?ref=guida" rel="noopener" target="_blank"><?= Support::e(I18n::t($loc, 'create_yours')) ?></a></p>
<?php endif; ?>
