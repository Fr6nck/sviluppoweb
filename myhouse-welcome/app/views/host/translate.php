<?php
/* Traduzione manuale: a sinistra l'originale, a destra la tua versione.
   I campi uguali in ogni lingua (nome della rete, indirizzi, link) non si
   ripetono qui: valgono già per tutte.
   Le traduzioni suggerite ($sugg, per chiave «tipo:id:percorso», da Traduttore) stanno
   sotto il loro campo, da approvare: i bottoni mandano il modulo #sugg-f, fuori da quello
   della traduzione. */
use function MHW\b;
use MHW\{Support, Csrf, Icon, SectionCatalog};
$title = $nome . ' — ' . $prop['name'];
$pid = (int) $prop['id'];
$sugg = $sugg ?? [];
// La suggerita di un campo: Approva / Modifica / Scarta, oppure Rifai se il testo originale è cambiato.
$box = function (string $k) use ($sugg, $loc): string {
    $s = $sugg[$k] ?? null;
    if (!$s) return '';
    $id = (int) $s['id']; $testo = Support::e((string) $s['text']);
    $b = fn(string $nome, string $et, string $cl, string $extra = '') => '<button type="submit" form="sugg-f" class="' . $cl . '" name="' . $nome . '" value="' . $id . '"' . $extra . '>' . $et . '</button>';
    if ($s['da_rifare']) {
        return '<div class="suggerita suggerita--rifare"><span class="badge badge--alert">Da rifare: il testo originale è cambiato</span>'
             . '<p class="suggerita__testo" lang="' . Support::e($loc) . '">' . $testo . '</p>'
             . '<div class="suggerita__azioni">' . $b('rifai', 'Rifai', 'btn btn--ghost btn--sm') . $b('scarta', 'Scarta', 'linkbtn') . '</div></div>';
    }
    // 6M: una proposta con un codice di accesso si può approvare, ma lo si dice qui; il riquadro di conferma arriva alla pubblicazione.
    $codice = MHW\Sicurezza::codiceAccesso((string) $s['text'])
        ? '<p class="codice-avviso" role="status">' . Icon::svg('lock', 16) . '<span>Qui sembra esserci un codice di accesso. Te lo sconsigliamo: chi ha il link della guida può leggerlo. '
          . 'Comunicalo all\'ospite di persona o in privato, poco prima dell\'arrivo.</span></p>' : '';
    return '<div class="suggerita"><span class="badge badge--ochre">Suggerita: da controllare</span>'
         . '<p class="suggerita__testo" lang="' . Support::e($loc) . '">' . $testo . '</p>' . $codice
         . '<div class="suggerita__azioni">' . $b('approva', 'Approva', 'btn btn--sm')
         . $b('modifica', 'Modifica', 'btn btn--ghost btn--sm', ' data-modifica="c-' . md5($k) . '" data-testo="' . $testo . '"')
         . $b('scarta', 'Scarta', 'linkbtn') . '</div></div>';
};
$campo = function (string $name, string $tipo, $orig, $trad, string $etichetta, string $k = '') use ($box) {
    $id = $k !== '' ? 'c-' . md5($k) : 'f' . md5($name);
    if (in_array($tipo, ['steps', 'list'], true)) {
        $orig = array_values((array) $orig); $trad = array_values((array) $trad);
        $n = max(count($orig), count($trad));
        $h = '<fieldset class="fieldset" style="padding:12px"><legend class="small">' . Support::e($etichetta) . '</legend><div class="rows">';
        for ($i = 0; $i < $n; $i++) {
            // Il campo da tradurre si vede: bordo ocra ed etichetta «Da tradurre».
            $manca = trim((string) ($orig[$i] ?? '')) !== '' && trim((string) ($trad[$i] ?? '')) === '';
            $ki = $k !== '' ? "$k.$i" : '';
            $h .= '<div class="stack' . ($manca ? ' da-tradurre' : '') . '" style="gap:4px"><span class="orig">' . Support::e((string) ($orig[$i] ?? '')) . '</span>'
                . '<input type="text" name="' . $name . '[]" value="' . Support::e((string) ($trad[$i] ?? '')) . '" maxlength="600"' . ($ki !== '' ? ' id="c-' . md5($ki) . '"' : '') . ' aria-label="'
                . Support::e($etichetta) . ', voce ' . ($i + 1) . ($manca ? ', da tradurre' : '') . '">' . ($ki !== '' && $manca ? $box($ki) : '') . '</div>';
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
         . '<p class="orig" style="margin-bottom:6px">' . Support::e((string) $orig) . '</p>' . $ctrl . ($k !== '' && $manca ? $box($k) : '') . '</div>';
}; ?>
<div class="stack stack--lg" style="max-width:860px">
  <div class="stack stack--sm">
    <a class="small" href="<?= b() ?>/pannello/<?= $pid ?>/lingue"><?= Icon::svg('back', 14) ?> Lingue</a>
    <h1>Traduzione in <?= Support::e($nome) ?>.</h1>
    <p class="lead">Scrivi la tua versione sotto a ogni testo. Quello che lasci vuoto, l'ospite lo legge nella lingua principale.</p>
  </div>
  <?php if ($err): ?><p class="note note--err" role="alert"><?= Support::e($err) ?></p><?php endif; ?>

  <?php $pronte = count(array_filter($sugg, fn($x) => !$x['da_rifare'])); ?>
  <section class="panel stack suggerite" id="suggerite" aria-labelledby="suggerite-titolo">
    <h2 id="suggerite-titolo" style="font-size:20px;margin:0">Traduzioni suggerite</h2>
    <?php if (!($nelPiano ?? false)): ?>
      <p class="small" style="margin:0">Un traduttore automatico propone le traduzioni che mancano, e tu le approvi: con il piano Plus, in omaggio per un anno. <a href="<?= b() ?>/piano?passa=plus">Scopri Plus</a></p>
    <?php elseif ($perche !== ''): ?>
      <p class="small" style="margin:0"><?= Support::e($perche) ?><?php if (empty($prop['translation_suggest'])): ?> <a href="<?= b() ?>/pannello/<?= $pid ?>/lingue#suggerite">Vai a Lingue</a><?php endif; ?></p>
    <?php elseif ($daSuggerire): ?>
      <div class="actions" style="margin:0"><button type="submit" form="sugg-f" class="btn btn--sm" name="suggerisci" value="1"><?= Icon::svg('globe', 15) ?> Suggerisci le traduzioni mancanti (<?= (int) $daSuggerire ?>)</button></div>
    <?php else: ?>
      <p class="small" style="margin:0">Non manca niente da suggerire.</p>
    <?php endif; ?>
    <?php if ($pronte): ?>
      <div class="actions" style="margin:0"><span class="small"><b><?= $pronte ?></b> suggerit<?= $pronte === 1 ? 'a' : 'e' ?> da controllare, qui sotto.</span>
        <button type="submit" form="sugg-f" class="btn btn--ghost btn--sm" name="tutte" value="1">Approva tutte (<?= $pronte ?>)</button></div>
    <?php endif; ?>
    <p class="tiny muted" style="margin:0">Suggerite da un traduttore automatico, da approvare: finché non le approvi, gli ospiti non le vedono. Per i testi importanti, falli rileggere a un madrelingua.</p>
  </section>

  <form method="post" class="stack stack--lg" data-autosave><?= Csrf::field() ?>
    <?php foreach ($sections as $s): $sid = (int) $s['id'];
          $titoloOrig = $s['orig']['title'] ?: SectionCatalog::title($s['kind'], $prop['default_locale']); ?>
      <section class="panel stack">
        <div class="field" style="margin:0">
          <label for="t<?= $sid ?>"><span class="kicker">Titolo</span></label>
          <p class="orig" style="margin-bottom:6px"><?= Support::e($titoloOrig) ?></p>
          <?php $kt = "section:$sid:title"; ?>
          <input type="text" id="<?= isset($sugg[$kt]) ? 'c-' . md5($kt) : 't' . $sid ?>" name="s[<?= $sid ?>][title]" maxlength="120" value="<?= Support::e($s['trad']['title']) ?>"
                 placeholder="<?= Support::e(SectionCatalog::title($s['kind'], $loc)) ?>">
          <?= $box($kt) ?>
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
                          $parti .= $campo('s[' . $sid . '][' . $f . '][' . $i . '][' . $sn . ']', $sd[0], $r[$sn], $tr[$r['id']][$sn] ?? '', $sd[1], "section:$sid:$f.{$r['id']}.$sn");
                      }
                      if ($parti === '') continue;
                      $h .= '<div class="stack" style="gap:10px;padding-top:8px;border-top:1px solid var(--line)">'
                          . '<input type="hidden" name="s[' . $sid . '][' . $f . '][' . $i . '][id]" value="' . Support::e($r['id']) . '">' . $parti . '</div>';
                  }
                  if ($h !== '') echo '<fieldset class="fieldset" style="padding:12px"><legend class="small">' . Support::e($etichetta) . '</legend>' . $h . '</fieldset>';
                  continue;
              }
              if (!SectionCatalog::isTranslated($tipo)) continue;
              echo $campo('s[' . $sid . '][' . $f . ']', $tipo, $s['orig']['data'][$f] ?? '', $s['trad']['data'][$f] ?? '', $etichetta, "section:$sid:$f");
        endforeach; ?>
        <?php foreach ($s['places'] as $pl): $plid = (int) $pl['id']; ?>
          <div class="fieldset">
            <span class="legend"><?= Support::e($pl['name']) ?></span>
            <?php foreach (['category' => ['text', 'Categoria'], 'description' => ['textarea', 'Descrizione'], 'note' => ['textarea', 'Perché lo consigli'], 'badge' => ['text', 'Etichetta']] as $f => [$tipo, $et]):
                  // Categoria ed etichetta scelte dall'elenco (fase 6B) si traducono da sole.
                  $chiave = (string) ($pl[$f === 'category' ? 'category_key' : ($f === 'badge' ? 'badge_key' : '')] ?? '');
                  if ($chiave !== '') {
                      echo '<p class="small"><b>' . Support::e($et) . ':</b> ' . Support::e(MHW\I18n::t('it', ($f === 'category' ? 'cat.' : 'badge.') . $chiave))
                         . ' <span class="muted">— già tradotta in ogni lingua</span></p>';
                      continue;
                  }
                  echo $campo('pl[' . $plid . '][' . $f . ']', $tipo, $pl['orig'][$f] ?? '', $pl['trad'][$f] ?? '', $et, "place:$plid:$f");
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
  <?php /* Le azioni sulle suggerite. Con JavaScript, prima si salva quello che si è scritto (data-salva-prima). */ ?>
  <form method="post" action="<?= b() ?>/pannello/<?= $pid ?>/lingue/<?= Support::e($loc) ?>/suggerite" id="sugg-f" data-salva-prima hidden><?= Csrf::field() ?></form>
</div>
