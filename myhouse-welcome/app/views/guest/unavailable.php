<?php use MHW\{Support, I18n}; ?>
<div style="padding:72px 0 48px" class="stack">
  <span class="avatar avatar--sm" aria-hidden="true">·</span>
  <h1 class="guest-title" style="font-size:34px;line-height:36px"><?= Support::e(I18n::t($loc, $esiste ? 'unavailable' : 'not_found')) ?></h1>
  <p class="muted" style="line-height:24px"><?= Support::e(I18n::t($loc, $esiste ? 'unavailable_body' : 'not_found_body')) ?></p>
</div>
