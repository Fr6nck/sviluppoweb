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
            // Il campo da tradurre si vede: bordo ocra ed etichetta «Da tradurre».
            $manca = trim((string) ($orig[$i] ?? '')) !== '' && trim((string) ($trad[$i] ?? '')) === '';
            $h .= '<div class="stack' . ($manca ? ' da-tradurre' : '') . '" style="gap:4px"><span class="orig">' . Support::e((string) ($orig[$i] ?? '')) . '</span>'
                . '<input type="text" name="' . $name . '[]" value="' . Support::e((string) ($trad[$i] ?? '')) . '" maxlength="600" aria-label="'
                . Support::e($etichetta) . ', voce ' . ($i + 1) . ($manca ? ', da tradurre' : '') . '"></div>';
        }
        return $h . '</div></fieldset>';
    }
    if (trim((string) $orig) === '' && trim((string) $trad) === '') return '';
    $manca = trim((string) $orig) !== '' && trim((string) $trad) === '';
    $ctrl = $tipo === 'textarea'
        ? '<textarea id="' . $id . '" name="' . $name . '" rows="3" maxlength="2000">' . Support::e((string) $trad) . '</textarea>'
        : '<input id="' . $id . '" type="text" name="' . $name . '" value="' . Support::e((string) $trad) . '" maxlength="300">';
    return '<div class="field' . ($manca ? ' da-tradurre' : '') . '" style="margin:0"><label for="' . $id . '">' . Support::e($etichetta)
         . ($manca ? ' <span class="badge badge--ochre">Da tradurre</span>' : '') . '</label>'
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
          <input type="text" id="t<?= $sid ?>" name="s[<?= $sid ?>][title]" maxlength="120" value="<?= Support::e($s['trad']['title']) ?>"
                 placeholder="<?= Support::e(SectionCatalog::title($s['kind'], $loc)) ?>">
        </div>
        <?php foreach (SectionCatalog::fields($s['kind']) as $f => $defC): [$tipo, $etichetta] = $defC;
              if ($tipo === 'repeater') {
                  // Riga per riga: l'id nascosto lega la traduzione alla sua riga anche se l'host la sposta.
                  $comuni = json_decode((string) $s['data'], true)[$f] ?? [];
                  $righe = SectionCatalog::rows($defC, $comuni, $s['orig']['data'][$f] ?? []);
                  $tr = [];
                  foreach ((array) ($s['trad']['data'][$f] ?? []) as $x) if (is_array($x) && isset($x['id'])) $tr[$x['id']] = $x;
                  $testi = array_filter($defC['sub'], fn($sd) => SectionCatalog::isTranslated($sd[0]));
                  $h = '';
                  foreach ($righe as $i => $r) {
                      $parti = '';
                      foreach ($testi as $sn => $sd) {
                          if (trim((string) $r[$sn]) === '' && trim((string) ($tr[$r['id']][$sn] ?? '')) === '') continue;
                          $parti .= $campo('s[' . $sid . '][' . $f . '][' . $i . '][' . $sn . ']', $sd[0], $r[$sn], $tr[$r['id']][$sn] ?? '', $sd[1]);
                      }
                      if ($parti === '') continue;
                      $h .= '<div class="stack" style="gap:10px;padding-top:8px;border-top:1px solid var(--line)">'
                          . '<input type="hidden" name="s[' . $sid . '][' . $f . '][' . $i . '][id]" value="' . Support::e($r['id']) . '">' . $parti . '</div>';
                  }
                  if ($h !== '') echo '<fieldset class="fieldset" style="padding:12px"><legend class="small">' . Support::e($etichetta) . '</legend>' . $h . '</fieldset>';
                  continue;
              }
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
