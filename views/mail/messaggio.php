<?php
/**
 * Un'e-mail con la grafica del sito: crema e avorio per le superfici, il
 * mattone per il gesto (pulsanti, occhiello, firma), il bruno per il testo;
 * Prata per i titoli e Sora per il testo, con i caratteri di sistema come
 * riserva, perché molti programmi di posta non scaricano i caratteri.
 *
 * Le regole della posta non sono quelle del web: tabelle per l'impaginazione,
 * stili scritti su ogni elemento (Gmail e Outlook ignorano molto del resto),
 * immagini con un indirizzo completo. Il testo semplice dice le stesse cose:
 * Contenuto::testo().
 *
 * @var \ArcoDelVento\Mail\Contenuto $c
 * @var string   $logo  l'indirizzo completo del logotipo in PNG
 * @var callable $font  nome del file → indirizzo completo del carattere
 * @var string   $sito  l'indirizzo completo della home nella lingua dell'e-mail
 */

$crema = '#f8f1e3'; $avorio = '#fffdf8'; $bruno = '#4b2c1f'; $mattone = '#813131';
$muto = '#6b6257'; $filo = '#e4d8c3';
$sans = "'Sora', -apple-system, 'Segoe UI', Helvetica, Arial, sans-serif";
$serif = "'Prata', Georgia, 'Times New Roman', serif";
$firmaFont = "'Allura', 'Snell Roundhand', 'Brush Script MT', cursive";
$avvisi = [
    'attenzione' => ['#f5e7cc', '#7a4f0c', '#e7cf9f'],
    'prova'      => ['#f5e7cc', '#7a4f0c', '#e7cf9f'],
    'ok'         => ['#e6ecdc', '#2f5b3c', '#c9d6b8'],
];
// Le funzioni delle pagine ($e()) qui non ci sono: un'e-mail può partire anche da uno script.
$e = static fn (?string $s): string => htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
$testo = static fn (string $s): string => nl2br($e($s), false);
?>
<!DOCTYPE html>
<html lang="<?= $e($c->lingua) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<title><?= $e($c->titolo) ?></title>
<style>
  @font-face { font-family: 'Prata'; font-style: normal; font-weight: 400; src: url('<?= $e($font('prata-latin.woff2')) ?>') format('woff2'); }
  @font-face { font-family: 'Sora'; font-style: normal; font-weight: 300 700; src: url('<?= $e($font('sora-latin.woff2')) ?>') format('woff2'); }
  @font-face { font-family: 'Allura'; font-style: normal; font-weight: 400; src: url('<?= $e($font('allura-latin.woff2')) ?>') format('woff2'); }
  body { margin: 0; padding: 0; background: <?= $crema ?>; }
  a { color: <?= $mattone ?>; }
  @media (max-width: 620px) {
    .adv-carta { border-radius: 0 !important; border-left: 0 !important; border-right: 0 !important; }
    .adv-dentro { padding-left: 22px !important; padding-right: 22px !important; }
    .adv-titolo { font-size: 26px !important; }
    .adv-pulsanti { width: 100% !important; }
    .adv-pulsante { display: block !important; width: 100% !important; padding-right: 0 !important; }
    .adv-pulsante a { display: block !important; }
    .adv-riga td { display: block !important; width: auto !important; padding: 2px 0 !important; border: 0 !important; }
    .adv-riga { border-bottom: 1px solid <?= $filo ?>; }
    .adv-riga td.adv-etichetta { padding-top: 10px !important; }
    .adv-riga td.adv-valore { padding-bottom: 10px !important; }
  }
</style>
</head>
<body style="margin:0;padding:0;background:<?= $crema ?>;color:<?= $bruno ?>;-webkit-text-size-adjust:100%;">
<?php if ($c->anteprima !== ''): ?>
<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;font-size:1px;line-height:1px;"><?= $e($c->anteprima) ?>&#8199;&#847;&#8199;&#847;&#8199;&#847;&#8199;&#847;&#8199;&#847;&#8199;&#847;</div>
<?php endif; ?>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:<?= $crema ?>;">
  <tr>
    <td align="center" style="padding:28px 12px 40px;">
      <table role="presentation" class="adv-carta" width="100%" cellpadding="0" cellspacing="0" border="0"
             style="max-width:600px;background:<?= $avorio ?>;border:1px solid <?= $filo ?>;border-radius:25px;">
        <tr>
          <td align="center" style="padding:34px 32px 6px;">
            <a href="<?= $e($sito) ?>" style="text-decoration:none;"><img src="<?= $e($logo) ?>" width="168" alt="Arco del Vento · Assisi"
               style="display:block;width:168px;max-width:60%;height:auto;border:0;outline:none;color:<?= $mattone ?>;font-family:<?= $serif ?>;font-size:24px;"></a>
          </td>
        </tr>
        <tr>
          <td align="center" style="padding:14px 32px 0;">
            <div style="width:44px;height:2px;background:<?= $mattone ?>;line-height:2px;font-size:2px;">&nbsp;</div>
          </td>
        </tr>
        <tr>
          <td class="adv-dentro" style="padding:26px 40px 8px;">
            <?php if ($c->occhiello !== ''): ?>
              <p style="margin:0 0 10px;font-family:<?= $sans ?>;font-size:12px;line-height:1.4;letter-spacing:0.16em;text-transform:uppercase;color:<?= $mattone ?>;font-weight:600;text-align:center;"><?= $e($c->occhiello) ?></p>
            <?php endif; ?>
            <?php if ($c->titolo !== ''): ?>
              <h1 class="adv-titolo" style="margin:0 0 22px;font-family:<?= $serif ?>;font-weight:400;font-size:30px;line-height:1.2;color:<?= $bruno ?>;text-align:center;"><?= $e($c->titolo) ?></h1>
            <?php endif; ?>
            <?php if ($c->saluto !== ''): ?>
              <p style="margin:0 0 14px;font-family:<?= $sans ?>;font-size:16px;line-height:1.6;color:<?= $bruno ?>;"><?= $e($c->saluto) ?></p>
            <?php endif; ?>

            <?php foreach ($c->blocchi as $b): ?>
              <?php if ($b['tipo'] === 'avviso'): [$fondo, $colore, $bordo] = $avvisi[$b['livello']] ?? $avvisi['attenzione']; ?>
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 18px;">
                  <tr><td style="background:<?= $fondo ?>;border:1px solid <?= $bordo ?>;border-radius:14px;padding:14px 16px;font-family:<?= $sans ?>;font-size:14px;line-height:1.55;color:<?= $colore ?>;font-weight:600;"><?= $testo($b['testo']) ?></td></tr>
                </table>

              <?php elseif ($b['tipo'] === 'paragrafo'): ?>
                <p style="margin:0 0 16px;font-family:<?= $sans ?>;font-size:16px;line-height:1.6;color:<?= $bruno ?>;"><?= $testo($b['testo']) ?></p>

              <?php elseif ($b['tipo'] === 'dati'): ?>
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:6px 0 22px;background:<?= $crema ?>;border-radius:18px;">
                  <tr><td style="padding:18px 20px 8px;">
                    <?php if ($b['titolo'] !== ''): ?>
                      <p style="margin:0 0 6px;font-family:<?= $sans ?>;font-size:12px;letter-spacing:0.14em;text-transform:uppercase;color:<?= $muto ?>;font-weight:600;"><?= $e($b['titolo']) ?></p>
                    <?php endif; ?>
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                      <?php foreach ($b['righe'] as $i => [$etichetta, $valore]): ?>
                        <tr class="adv-riga">
                          <td class="adv-etichetta" width="38%" valign="top" style="padding:10px 12px 10px 0;<?= $i > 0 ? 'border-top:1px solid ' . $filo . ';' : '' ?>font-family:<?= $sans ?>;font-size:14px;line-height:1.45;color:<?= $muto ?>;"><?= $e($etichetta) ?></td>
                          <td class="adv-valore" valign="top" style="padding:10px 0;<?= $i > 0 ? 'border-top:1px solid ' . $filo . ';' : '' ?>font-family:<?= $sans ?>;font-size:15px;line-height:1.45;color:<?= $bruno ?>;font-weight:600;"><?= $testo((string) $valore) ?></td>
                        </tr>
                      <?php endforeach; ?>
                    </table>
                  </td></tr>
                </table>

              <?php elseif ($b['tipo'] === 'pulsanti'): ?>
                <?php if ($b['titolo'] !== ''): ?>
                  <p style="margin:4px 0 12px;font-family:<?= $sans ?>;font-size:15px;line-height:1.5;color:<?= $bruno ?>;font-weight:600;"><?= $e($b['titolo']) ?></p>
                <?php endif; ?>
                <table role="presentation" class="adv-pulsanti" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 20px;">
                  <tr>
                    <?php foreach ($b['pulsanti'] as $p): $primario = !empty($p[2]); ?>
                      <td class="adv-pulsante" style="padding:0 10px 10px 0;">
                        <a href="<?= $e($p[1]) ?>" style="display:inline-block;padding:13px 22px;border-radius:999px;font-family:<?= $sans ?>;font-size:15px;line-height:1.2;font-weight:600;text-decoration:none;text-align:center;<?= $primario
                            ? 'background:' . $mattone . ';color:' . $avorio . ';border:1px solid ' . $mattone . ';'
                            : 'background:' . $avorio . ';color:' . $bruno . ';border:1px solid ' . $filo . ';' ?>"><?= $e($p[0]) ?></a>
                      </td>
                    <?php endforeach; ?>
                  </tr>
                </table>

              <?php elseif ($b['tipo'] === 'nota'): ?>
                <p style="margin:0 0 14px;font-family:<?= $sans ?>;font-size:13px;line-height:1.55;color:<?= $muto ?>;"><?= $testo($b['testo']) ?></p>
              <?php endif; ?>
            <?php endforeach; ?>

            <?php if ($c->firma !== ''): ?>
              <p style="margin:18px 0 0;font-family:<?= $firmaFont ?>;font-size:30px;line-height:1.1;color:<?= $mattone ?>;"><?= $e($c->firma) ?></p>
            <?php endif; ?>
          </td>
        </tr>
        <tr><td style="padding:0 0 30px;">&nbsp;</td></tr>
      </table>

      <?php if ($c->piede !== []): ?>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;">
          <tr>
            <td align="center" style="padding:22px 24px 0;font-family:<?= $sans ?>;font-size:12px;line-height:1.7;color:<?= $muto ?>;">
              <?php foreach ($c->piede as $riga): ?><?= $e($riga) ?><br><?php endforeach; ?>
              <a href="<?= $e($sito) ?>" style="color:<?= $mattone ?>;text-decoration:underline;"><?= $e((string) parse_url($sito, PHP_URL_HOST)) ?></a>
            </td>
          </tr>
        </table>
      <?php endif; ?>
    </td>
  </tr>
</table>
</body>
</html>
