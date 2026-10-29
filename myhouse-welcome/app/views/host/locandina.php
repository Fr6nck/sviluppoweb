<?php
/* 10 · La locandina A6 Concierge AI (pagina a sé, senza telaio): il QR della guida, i tre passi, le domande
   d'esempio, il contatto per le urgenze. Nella lingua principale della guida e, se è un'altra, anche in inglese.
   Riceve: $prop, $qrPng, $primo (contatto), $kinds, $attiva. */
use MHW\{Support, I18n};
$loc = (string) $prop['default_locale'];
$seconda = $loc !== 'en' ? 'en' : null;
$t = fn(string $k) => I18n::t($loc, $k);
$t2 = fn(string $k) => $seconda ? I18n::t($seconda, $k) : '';
$esempi = [];
foreach (['wifi' => 'ask_eg_wifi', 'checkin' => 'ask_eg_checkout', 'parking' => 'ask_eg_parking', 'eat' => 'ask_eg_eat'] as $k => $chiave) if (in_array($k, $kinds, true)) $esempi[] = $chiave;
$esempi = array_slice($esempi ?: ['ask_eg_checkout'], 0, 3);
$nomeHost = $primo ? trim((string) $primo['name']) : '';
$tel = $primo ? trim((string) $primo['phone']) : ''; ?>
<!doctype html>
<html lang="<?= Support::e($loc) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= Support::e($t('ask_guide')) ?> — <?= Support::e($prop['name']) ?></title>
<link rel="preload" href="<?= MHW\a('/assets/fonts/onest-latin.woff2') ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= MHW\av('/assets/app.css') ?>">
<style>
  @page { size: A6; margin: 0 }
  body { margin: 0; background: #e9e2d4 }
  .foglio { box-sizing: border-box; width: 105mm; min-height: 148mm; margin: 24px auto; padding: 9mm 8mm 7mm; background: #faf5ec; color: #231b12; display: flex; flex-direction: column; gap: 5mm; border: 1px solid #e2d7c4 }
  .foglio__casa { font-size: 8.5pt; letter-spacing: .12em; text-transform: uppercase; color: #6a5b48; margin: 0 }
  .foglio h1 { margin: 1mm 0 0; font-family: Gloock, Georgia, serif; font-weight: 400; font-size: 19pt; line-height: 1.12; letter-spacing: -.3pt }
  .foglio__sotto { margin: 0; font-size: 9pt; line-height: 1.4; color: #6a5b48 }
  .foglio__qr { display: grid; grid-template-columns: 34mm 1fr; gap: 4mm; align-items: center; padding: 4mm; border-radius: 5mm; background: #fff; border: 1px solid #e2d7c4 }
  .foglio__qr img { display: block; width: 34mm; height: 34mm }
  .foglio ol { margin: 0; padding: 0; list-style: none; display: flex; flex-direction: column; gap: 2.5mm; font-size: 8.5pt; line-height: 1.35 }
  .foglio ol li { display: flex; gap: 2mm; align-items: flex-start }
  .foglio ol b { flex: none; width: 5mm; height: 5mm; border-radius: 50%; background: #231b12; color: #faf5ec; display: inline-grid; place-items: center; font-size: 7.5pt; font-weight: 600 }
  .foglio__chip { display: flex; flex-wrap: wrap; gap: 1.5mm }
  .foglio__chip span { display: inline-block; padding: 1.2mm 3mm; border-radius: 99px; background: #fff; border: 1px solid #e2d7c4; font-size: 8pt }
  .foglio__piede { margin-top: auto; padding-top: 3mm; border-top: 1px solid #e2d7c4; display: flex; flex-direction: column; gap: 1.5mm; font-size: 8pt; line-height: 1.4 }
  .foglio__piede .tiny { font-size: 6.8pt; color: #6a5b48; display: flex; justify-content: space-between; gap: 3mm }
  .stampa { display: flex; justify-content: center; gap: 8px; margin: 16px auto }
  @media print { body { background: #fff } .foglio { margin: 0; border: 0; min-height: 148mm } .stampa { display: none } }
</style>
</head>
<body>
<div class="stampa">
  <button type="button" class="btn btn--sm" onclick="window.print()"><?= Support::e(MHW\t('Stampa la locandina')) ?></button>
  <?php if (!$attiva): ?><span class="note note--err" role="status" style="padding:8px 14px"><?= Support::e(MHW\t('Il Concierge AI non è ancora acceso su questa guida: la locandina serve quando lo sarà.')) ?></span><?php endif; ?>
</div>
<div class="foglio">
  <p class="foglio__casa"><?= Support::e($prop['name']) ?></p>
  <h1><?= Support::e($t('ask_poster')) ?><?php if ($seconda): ?><br><span style="color:#6a5b48"><?= Support::e($t2('ask_poster')) ?></span><?php endif; ?></h1>
  <p class="foglio__sotto"><?= Support::e($t('ask_intro')) ?><?php if ($seconda): ?><br><?= Support::e($t2('ask_intro')) ?><?php endif; ?></p>
  <div class="foglio__qr">
    <img src="data:image/png;base64,<?= base64_encode($qrPng) ?>" alt="QR">
    <ol>
      <?php foreach (['ask_step_scan', 'ask_step_tap', 'ask_step_write'] as $i => $k): ?>
      <li><b><?= $i + 1 ?></b><span><?= Support::e($t($k)) ?><?php if ($seconda): ?><br><span style="color:#6a5b48"><?= Support::e($t2($k)) ?></span><?php endif; ?></span></li>
      <?php endforeach; ?>
    </ol>
  </div>
  <div class="foglio__chip"><?php foreach ($esempi as $k): ?><span>«<?= Support::e($t($k)) ?>»</span><?php endforeach; ?><?php if ($seconda): ?><span>«<?= Support::e($t2($esempi[0])) ?>»</span><?php endif; ?></div>
  <div class="foglio__piede">
    <?php if ($tel !== ''): ?><p style="margin:0"><?= Support::e($nomeHost !== '' ? I18n::t($loc, 'ask_contact', $nomeHost) : $t('ask_contact_plain')) ?>: <b><?= Support::e($tel) ?></b><?= !empty($primo['whatsapp']) ? ' · WhatsApp' : '' ?></p><?php endif; ?>
    <p class="tiny"><span><?= Support::e($t('ask_note')) ?></span><span style="flex:none;font-weight:600;color:#231b12">MyHouse Welcome</span></p>
  </div>
</div>
</body>
</html>
