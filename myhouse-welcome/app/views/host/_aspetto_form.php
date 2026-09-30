<?php
/* Palette, testo chiaro o scuro, logo, copertina, immagine profilo.
   I campioni sono i colori veri; la variante che non passa il controllo di
   contrasto non si può scegliere.
   Riceve: $prop, $palette, $acc, $dopoPasso. */
use function MHW\b;
use MHW\{Support, Csrf, Media, Entitlements, Icon};
$aid = (int) $acc['id']; $pid = (int) $prop['id']; $dopoPasso = $dopoPasso ?? '';
$puoPalette = Entitlements::can($aid, 'palette');
$scelta = $prop['palette'] ?: 'terracotta';
$tono = $prop['text_tone'] ?: 'scuro';
$media = [
    'cover'   => ['cover_media_id', 'cover', 'Foto di copertina', 'La prima cosa che vedono: la facciata, il cortile, la vista.', false, 'la foto'],
    'logo'    => ['logo_media_id', 'logo', 'Logo', 'PNG con fondo trasparente, se ce l\'hai.', true, 'il logo'],
    'profile' => ['profile_media_id', 'profile_image', 'Immagine profilo', 'Una tua foto o quella della struttura, piccola e rotonda.', false, 'l\'immagine'],
]; ?>
<form method="post" action="<?= b() ?>/pannello/<?= $pid ?>/aspetto" enctype="multipart/form-data" class="stack stack--lg" data-palette-scelta<?= $dopoPasso !== '' ? ' data-autosave' : '' ?>><?= Csrf::field() ?>
  <fieldset class="fieldset">
    <legend>Palette</legend>
    <?php if (!$puoPalette): ?><p class="help">Il tuo piano usa la palette Terracotta.</p><?php endif; ?>
    <div class="swatches">
      <?php foreach ($palette as $code => $p): $v = $p['dati']['scuro']; ?>
        <label class="swatch">
          <input type="radio" name="palette" value="<?= Support::e($code) ?>" <?= $code === $scelta ? 'checked' : '' ?> <?= $puoPalette || $code === 'terracotta' ? '' : 'disabled' ?>
                 data-css="<?= Support::e($p['css']) ?>" data-toni="<?= Support::e(implode(',', $p['toni'])) ?>">
          <span class="swatch__mini" style="background:<?= Support::e($v['bg']) ?>" aria-hidden="true">
            <?php foreach ($p['dati']['tiles'] as $c): ?><i style="background:<?= Support::e($c) ?>"></i><?php endforeach; ?>
          </span>
          <span><?= Support::e($p['nome']) ?></span>
        </label>
      <?php endforeach; ?>
    </div>
  </fieldset>

  <fieldset class="fieldset">
    <legend>Testo</legend>
    <p class="help">Testo scuro su fondo chiaro, oppure testo chiaro su fondo scuro. L'ospite può comunque passare all'altro tema dal suo telefono.</p>
    <div class="tones scelte scelte--riga">
      <?php $toniOk = $palette[$scelta]['toni'] ?? ['scuro', 'chiaro'];
            foreach (['scuro' => 'Testo scuro', 'chiaro' => 'Testo chiaro'] as $k => $et): $ok = in_array($k, $toniOk, true); ?>
        <label class="tone scelta <?= $ok ? '' : 'tone--off' ?>"><input type="radio" name="text_tone" value="<?= $k ?>" <?= $k === $tono && $ok ? 'checked' : '' ?> <?= $ok ? '' : 'disabled' ?>>
          <span class="scelta__testo"><?= $et ?></span></label>
      <?php endforeach; ?>
    </div>
  </fieldset>

  <?php foreach ($media as $input => [$col, $feature, $nome, $aiuto, $isLogo, $cosa]):
        $ok = Entitlements::can($aid, $feature); $url = $prop[$col] ? Media::url((int) $prop[$col]) : null;
        if (!$ok && !$url) { ?>
          <p class="tiny muted"><?= Support::e($nome) ?>: compresa nel piano Plus. <a href="<?= b() ?>/piano">Scopri Plus</a></p>
        <?php continue; } ?>
    <fieldset class="fieldset">
      <legend><?= Support::e($nome) ?></legend>
      <?php if ($ok):
            $carica = ['id' => 'carica-' . $input, 'name' => $input, 'accept' => 'image/jpeg,image/png,image/webp', 'cosa' => $cosa,
                       'aiuto' => $aiuto . ' JPG, PNG o WebP, fino a 8 MB.', 'url' => $url, 'togli' => 'togli-' . $input, 'logo' => $isLogo];
            include __DIR__ . '/_carica.php';
          else: ?>
        <div class="mediabox">
          <span class="mediabox__img <?= $isLogo ? 'logo' : '' ?>"><img src="<?= Support::e($url) ?>" alt=""></span>
          <div class="stack" style="gap:8px">
            <p class="help">Non è compresa nel tuo piano: toglila prima di pubblicare.</p>
            <button class="linkbtn" name="azione" value="togli-<?= $input ?>">Togli</button>
          </div>
        </div>
      <?php endif; ?>
    </fieldset>
  <?php endforeach; ?>

  <?php if ($dopoPasso !== ''):
        $barraAvanti = '<button class="btn btn--go" name="dopo" value="' . Support::e($dopoPasso) . '">Salva e continua <span class="go">' . Icon::svg('arrow', 18, 2) . '</span></button>';
        include __DIR__ . '/_barra_passo.php';
      else: ?>
  <div class="actions">
      <button class="btn" name="azione" value="salva">Salva l'aspetto</button>
  </div>
  <?php endif; ?>
</form>
