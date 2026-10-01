<?php
/* Il congedo. Mostra SOLO quello che l'host ha scritto per la partenza: se non
   ha scritto niente, non si inventa niente — resta il saluto. */
use MHW\{Support, Icon, Guide, I18n, Media};
$pr = $snap['property']; $def = $pr['default_locale'];
$title = I18n::t($loc, 'farewell_title') . ' — ' . $pr['name'];
$core = null; foreach ($snap['sections'] as $s) if ($s['kind'] === 'checkin') { $core = $s; break; }
$t = $core ? Guide::tdata($core, $loc, $def) : [];
// La lista «Prima di partire» (dalla 009 le vecchie caselle fisse sono voci di questa lista).
$cose = array_values(array_filter(array_map(fn($x) => trim((string) $x), (array) ($t['checkout_steps'] ?? [])), fn($x) => $x !== ''));
$saluto = trim((string) ($t['checkout_notes'] ?? ''));
// WhatsApp: il primo contatto che risponde lì.
$tel = '';
foreach ($pr['contacts'] ?? [] as $c) if (!empty($c['whatsapp']) && trim((string) $c['phone']) !== '') { $tel = Support::telHref((string) $c['phone']); break; }
$copertina = !empty($pr['cover_id']) ? Media::url((int) $pr['cover_id']) : ($pr['cover_url'] ?? null); ?>

<div class="full" id="congedo">
  <?php if ($copertina): ?><img class="full__photo" src="<?= Support::e($copertina) ?>" alt="" style="transform-origin:50% 40%"><?php endif; ?>
  <div class="full__scrim" style="background:linear-gradient(180deg,rgba(23,19,13,.70) 0%,rgba(23,19,13,.42) 24%,rgba(23,19,13,.82) 58%,rgba(23,19,13,.97) 100%)"></div>
  <div class="full__stage">
    <div class="rise" style="display:flex;align-items:center;justify-content:space-between;gap:12px;animation-delay:.1s">
      <span style="font-size:14px;font-weight:500;letter-spacing:-.2px;color:#f6f0e5"><?= Support::e($pr['name']) ?></span>
      <span class="pill-quiet" style="border-color:rgba(246,240,229,.32);color:#f6f0e5"><?= Support::e(I18n::t($loc, 'checkout_by', $pr['checkout_by'])) ?></span>
    </div>
    <div class="stack" style="gap:24px">
      <div class="stack" style="gap:12px">
        <span class="kicker rise" style="animation-delay:.3s"><?= Support::e(I18n::t($loc, 'before_leaving')) ?></span>
        <h1 class="rise" style="animation-delay:.42s;font-size:clamp(40px,12vw,50px)"><?= Support::e(I18n::t($loc, 'farewell_title')) ?></h1>
        <p class="rise" style="animation-delay:.58s"><?= Support::e(I18n::t($loc, $cose ? 'farewell_lead' : 'farewell_empty')) ?></p>
      </div>
      <?php if ($cose): ?>
        <ul class="checklist">
          <?php foreach ($cose as $i => $testo): ?>
            <li class="rise" style="animation-delay:<?= number_format(.70 + $i * .1, 2, '.', '') ?>s;align-items:flex-start">
              <span style="flex:none;color:#6fc48c;margin-top:2px"><?= Icon::svg('check', 18, 2.4) ?></span>
              <span><?= Support::e($testo) ?></span></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <?php if ($saluto !== ''): ?><p class="rise" style="animation-delay:1s;white-space:pre-line"><?= Support::e($saluto) ?></p><?php endif; ?>
      <div class="stack" style="gap:10px">
        <?php if ($tel): ?>
          <a class="btn btn--lg btn--go rise" style="animation-delay:1.05s" href="https://wa.me/<?= Support::e(ltrim($tel, '+')) ?>" rel="noopener">
            <?= Support::e(I18n::t($loc, 'whatsapp')) ?> <span class="go"><?= Icon::svg('whatsapp', 19, 2) ?></span></a>
        <?php endif; ?>
        <a class="rise" style="display:flex;align-items:center;justify-content:center;min-height:48px;border-radius:999px;color:#e8dcc8;font-size:15px;font-weight:500;animation-delay:1.1s"
           href="<?= Support::e($base) ?>?l=<?= Support::e($loc) ?>"><?= Support::e(I18n::t($loc, 'farewell_back')) ?></a>
      </div>
    </div>
  </div>
</div>
