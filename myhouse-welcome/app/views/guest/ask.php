<?php
/* 10 · La pagina del Concierge AI («Chiedi al Concierge», 9T) per chi naviga senza JavaScript (e per chi arriva dal link): la domanda nel
   modulo, la risposta sotto, con la sezione da cui viene o i contatti. Riceve: $snap, $loc, $base, $domanda,
   $esito (null | ['testo', 'trovata', 'sezione' => ?array, 'contatti' => bool]), $errore, $chiedi. */
use MHW\{Support, Icon, I18n};
$pr = $snap['property']; $def = $pr['default_locale'];
$title = I18n::t($loc, 'ask_guide') . ' — ' . $pr['name'];
$indietro = true; $coda = '/chiedi';
$nomeHost = (string) ($chiedi['host'] ?? ''); ?>
<?php include __DIR__ . '/_top.php'; ?>
<div class="stack stack--sm" style="margin-top:20px">
  <h1 class="guest-title" style="font-size:38px;line-height:38px"><?= Support::e(I18n::t($loc, 'ask_guide')) ?> <span class="badge-ai">AI</span></h1>
  <p class="muted" style="margin-top:6px"><?= Support::e(I18n::t($loc, 'ask_intro')) ?></p>
</div>
<form class="chiedi__form chiedi__form--pagina" method="get" action="<?= Support::e($chiedi['url']) ?>" style="margin-top:20px">
  <input type="hidden" name="l" value="<?= Support::e($loc) ?>">
  <label class="sr-only" for="chiedi-q"><?= Support::e(I18n::t($loc, 'ask_placeholder')) ?></label>
  <input id="chiedi-q" name="q" type="text" maxlength="<?= MHW\Assistente::DOMANDA_MAX ?>" required autocomplete="off" aria-describedby="chiedi-hint" value="<?= Support::e($domanda) ?>" placeholder="<?= Support::e(I18n::t($loc, 'ask_placeholder')) ?>">
  <button class="btn chiedi__invia" aria-label="<?= Support::e(I18n::t($loc, 'ask_send')) ?>"><?= Icon::svg('arrow', 20, 2) ?></button>
</form>
<?php if ($domanda !== '' && !empty($chiedi['esempi']) && $esito === null && $errore === ''): ?><?php endif; ?>
<?php if ($errore !== ''): ?>
  <p class="note note--err" role="status" style="margin-top:16px"><?= Support::e($errore) ?></p>
<?php elseif ($esito !== null): ?>
  <div class="chiedi__msgs chiedi__msgs--pagina" style="margin-top:20px">
    <div class="chiedi__msg chiedi__msg--tu"><span class="sr-only"><?= Support::e(I18n::t($loc, 'ask_you')) ?>:</span><?= Support::e($domanda) ?></div>
    <div class="chiedi__msg chiedi__msg--guida"><span class="sr-only"><?= Support::e(I18n::t($loc, 'ask_answer')) ?>:</span><?= Support::e($esito['testo']) ?></div>
    <?php if (!empty($esito['sezione'])): ?>
      <a class="chiedi__fonte" href="<?= Support::e($esito['sezione']['url']) ?>"><?= Icon::svg('book', 14) ?><?= Support::e(I18n::t($loc, 'ask_source', $esito['sezione']['titolo'])) ?> · <b><?= Support::e(I18n::t($loc, 'ask_open_section')) ?></b></a>
    <?php elseif (!empty($esito['contatti']) && !empty($chiedi['contatto_href'])): ?>
      <a class="btn btn--sm chiedi__contatto" href="<?= Support::e($chiedi['contatto_href']) ?>" rel="noopener"><?= Icon::svg(str_starts_with((string) $chiedi['contatto_href'], 'tel:') ? 'phone' : 'whatsapp', 15) ?><?= Support::e($nomeHost !== '' ? I18n::t($loc, 'ask_contact', $nomeHost) : I18n::t($loc, 'ask_contact_plain')) ?></a>
    <?php endif; ?>
  </div>
<?php elseif (!empty($chiedi['esempi'])): ?>
  <div class="chiedi__esempi" style="margin-top:16px">
    <span class="small muted"><?= Support::e(I18n::t($loc, 'ask_examples')) ?></span>
    <?php foreach ($chiedi['esempi'] as $k): ?><a class="chiedi__chip" href="<?= Support::e($chiedi['url']) ?>?l=<?= Support::e($loc) ?>&amp;q=<?= rawurlencode(I18n::t($loc, $k)) ?>"><?= Support::e(I18n::t($loc, $k)) ?></a><?php endforeach; ?>
  </div>
<?php endif; ?>
<p class="chiedi__hint" id="chiedi-hint" style="margin-top:8px"><?= Support::e(I18n::t($loc, 'ask_hint')) ?></p>
<p class="chiedi__nota" style="margin-top:20px"><?= Support::e(I18n::t($loc, 'ask_note')) ?></p>
<div class="guest-bottom">
  <a class="btn btn--ghost grow" href="<?= Support::e($base) ?>?l=<?= Support::e($loc) ?>"><?= Icon::svg('back', 17) ?><?= Support::e(I18n::t($loc, 'back')) ?></a>
</div>
