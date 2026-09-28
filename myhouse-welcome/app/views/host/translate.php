<?php
/* Traduzione manuale: a sinistra l'originale, a destra la tua versione.
   I campi uguali in ogni lingua (nome della rete, indirizzi, link) non si
   ripetono qui: valgono già per tutte. */
use function MHW\b;
use MHW\{Support, Csrf, Icon, SectionCatalog};
$title = $nome . ' — ' . $prop['name'];
$pid = (int) $prop['id'];
$campo = function (string $name, string $tipo, $orig, $trad, string $etichetta) {
    $id = 'f' . md5($name);
    if (in_array($tipo, ['steps', 'list'], true)) {
        $orig = array_values((array) $orig); $trad = array_values((array) $trad);
        $n = max(count($orig), count($trad));
        $h = '<fieldset class="fieldset" style="padding:12px"><legend class="small">' . Support::e($etichetta) . '</legend><div class="rows">';
        for ($i = 0; $i < $n; $i++) {
            $h .= '<div class="stack" style="gap:4px"><span class="orig">' . Support::e((string) ($orig[$i] ?? '')) . '</span>'
                . '<input type="text" name="' . $name . '[]" value="' . Support::e((string) ($trad[$i] ?? '')) . '" maxlength="600" aria-label="'
                . Support::e($etichetta) . ', voce ' . ($i + 1) . '"></div>';
        }
        return $h . '</div></fieldset>';
    }
    if (trim((string) $orig) === '' && trim((string) $trad) === '') return '';
    $ctrl = $tipo === 'textarea'
        ? '<textarea id="' . $id . '" name="' . $name . '" rows="3" maxlength="2000">' . Support::e((string) $trad) . '</textarea>'
        : '<input id="' . $id . '" type="text" name="' . $name . '" value="' . Support::e((string) $trad) . '" maxlength="300">';
    return '<div class="field" style="margin:0"><label for="' . $id . '">' . Support::e($etichetta) . '</label>'
         . '<p class="orig" style="margin-bottom:6px">' . Support::e((string) $orig) . '</p>' . $ctrl . '</div>';
}; ?>
<div class="stack stack--lg" style="max-width:860px">
  <div class="stack stack--sm">
    <a class="small" href="<?= b() ?>/pannello/<?= $pid ?>/lingue"><?= Icon::svg('back', 14) ?> Lingue</a>
    <h1>Traduzione in <?= Support::e($nome) ?>.</h1>
    <p class="lead">Scrivi la tua versione sotto a ogni testo. Quello che lasci vuoto, l'ospite lo legge nella lingua principale.</p>
  </div>
  <?php if ($err): ?><p class="note note--err" role="alert"><?= Support::e($err) ?></p><?php endif; ?>

  <form method="post" class="stack stack--lg" data-autosave><?= Csrf::field() ?>
    <?php foreach ($sections as $s): $sid = (int) $s['id'];
          $titoloOrig = $s['orig']['title'] ?: SectionCatalog::title($s['kind'], $prop['default_locale']); ?>
      <section class="panel stack">
        <div class="field" style="margin:0">
          <label for="t<?= $sid ?>"><span class="kicker">Titolo</span></label>
          <p class="orig" style="margin-bottom:6px"><?= Support::e($titoloOrig) ?></p>
          <input id="t<?= $sid ?>" name="s[<?= $sid ?>][title]" maxlength="120" value="<?= Support::e($s['trad']['title']) ?>"
                 placeholder="<?= Support::e(SectionCatalog::title($s['kind'], $loc)) ?>">
        </div>
        <?php foreach (SectionCatalog::fields($s['kind']) as $f => [$tipo, $etichetta]):
              if (!SectionCatalog::isTranslated($tipo)) continue;
              echo $campo('s[' . $sid . '][' . $f . ']', $tipo, $s['orig']['data'][$f] ?? '', $s['trad']['data'][$f] ?? '', $etichetta);
        endforeach; ?>
        <?php foreach ($s['places'] as $pl): $plid = (int) $pl['id']; ?>
          <div class="fieldset">
            <span class="legend"><?= Support::e($pl['name']) ?></span>
            <?php foreach (['category' => ['text', 'Categoria'], 'description' => ['textarea', 'Descrizione'], 'note' => ['textarea', 'Il tuo consiglio'], 'badge' => ['text', 'Etichetta']] as $f => [$tipo, $et]):
                  echo $campo('pl[' . $plid . '][' . $f . ']', $tipo, $pl['orig'][$f] ?? '', $pl['trad'][$f] ?? '', $et);
            endforeach; ?>
          </div>
        <?php endforeach; ?>
      </section>
    <?php endforeach; ?>
    <div class="actions" style="position:sticky;bottom:0;padding:12px 0;background:var(--paper)">
      <button class="btn">Salva la traduzione</button>
      <span class="small muted" data-stato-salvataggio aria-live="polite"></span>
    </div>
  </form>
</div>
