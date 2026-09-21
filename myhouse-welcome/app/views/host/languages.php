<?php use function MHW\b; use MHW\{Support, Csrf, View, Icon};
$title = 'Lingue — ' . $p['name'];
$topnav = View::render('host/_propnav', ['p' => $p, 'qui' => 'lingue'], null);

/* Quante voci mancano per ogni lingua: il numero che conta davvero. */
$totale = count($sections);
$conteggio = [];
foreach ($active as $l) {
    $fatte = 0; $riviste = 0;
    foreach ($sections as $s) {
        $t = $s['tr'][$l] ?? null;
        if ($t && trim((string) $t['title']) !== '') { $fatte++; if ($t['state'] === 'reviewed') $riviste++; }
    }
    $conteggio[$l] = ['fatte' => $fatte, 'riviste' => $riviste];
} ?>

<div class="spread">
  <div class="stack stack--sm">
    <h1><?= count($active) ?> lingu<?= count($active) === 1 ? 'a' : 'e' ?>,<br>una sola guida.</h1>
    <p class="lead" style="max-width:520px">Scrivete in <?= Support::e(mb_strtolower($all[$p['default_locale']] ?? 'italiano')) ?>.
      Le altre lingue si compilano qui — e restano vostre finché non le rivedete.</p>
  </div>
</div>

<?php if ($err): ?><p class="note note--err" style="margin-top:24px"><?= Support::e($err) ?></p><?php endif; ?>

<div class="grid grid-4" style="margin-top:32px">
  <?php foreach ($all as $code => $label):
    $attiva = in_array($code, $active, true);
    $compresa = in_array($code, $allowed, true);
    $c = $conteggio[$code] ?? ['fatte' => 0, 'riviste' => 0];
    $originale = $code === $p['default_locale']; ?>
    <div class="panel stack <?= $originale ? 'panel--dark' : '' ?>" style="gap:14px<?= $attiva ? '' : ';opacity:.6' ?>">
      <span style="font-size:26px;font-weight:500;letter-spacing:-.9px"><?= Support::e($label) ?></span>
      <span class="small muted">
        <?php if ($originale): ?>La lingua in cui scrivete
        <?php elseif (!$compresa): ?>Non compresa nel piano
        <?php elseif (!$attiva): ?>Non pubblicata
        <?php else: ?><?= (int) $c['fatte'] ?> testi su <?= (int) $totale ?><?php endif; ?>
      </span>
      <span class="badge badge--<?= $originale ? 'paper' : ($c['riviste'] >= $totale && $attiva ? 'pine'
              : ($attiva ? 'sea' : 'ochre')) ?>" style="margin-top:auto;align-self:flex-start">
        <?php if ($originale): ?>Originale
        <?php elseif (!$compresa): ?>Nel piano superiore
        <?php elseif (!$attiva): ?>Spenta
        <?php elseif ($c['riviste'] >= $totale): ?>Riviste da voi
        <?php else: ?><?= max(0, $totale - $c['riviste']) ?> da rivedere<?php endif; ?>
      </span>
    </div>
  <?php endforeach; ?>
</div>

<form method="post" class="panel" style="margin-top:24px"><?= Csrf::field() ?>
  <input type="hidden" name="azione" value="attiva">
  <span class="kicker">Quali lingue pubblicare</span>
  <div class="row" style="margin-top:12px;gap:18px">
    <?php foreach ($all as $code => $label): $ok = in_array($code, $allowed, true); ?>
      <label class="check">
        <input type="checkbox" name="locali[]" value="<?= $code ?>"
          <?= in_array($code, $active, true) ? 'checked' : '' ?> <?= $ok ? '' : 'disabled' ?>>
        <span><?= Support::e($label) ?><?= $ok ? '' : ' <span class="muted">(non nel piano)</span>' ?></span>
      </label>
    <?php endforeach; ?>
  </div>
  <div class="row" style="margin-top:20px">
    <button class="btn btn--ghost btn--sm">Aggiorna le lingue</button>
    <button class="btn btn--sm" form="traduci" <?= $translator ? '' : 'disabled' ?>>Traduci quello che manca</button>
  </div>
  <?php if (!$translator): ?>
    <p class="tiny muted" style="margin-top:12px">Nessun servizio di traduzione configurato: le lingue si
      compilano a mano, e tutto il resto funziona lo stesso. Per attivarlo aggiungete provider e chiave in
      <code>config.php</code>.</p>
  <?php endif; ?>
</form>
<form method="post" id="traduci" style="display:none"><?= Csrf::field() ?>
  <input type="hidden" name="azione" value="traduci"></form>

<h2 style="margin-top:44px">Le traduzioni</h2>
<p class="muted small" style="margin-top:10px">Una traduzione che confermate diventa <strong>vostra</strong>:
  da quel momento nessuna macchina la sovrascrive più.</p>

<?php foreach ($sections as $s):
  $base = $s['tr'][$p['default_locale']] ?? null; if (!$base) continue;
  foreach ($active as $loc): if ($loc === $p['default_locale']) continue;
    $t = $s['tr'][$loc] ?? ['title' => '', 'body' => '', 'state' => 'missing']; ?>
    <div class="trrow" style="margin-top:16px">
      <div class="trrow__head">
        <span style="font-size:15px;font-weight:500"><?= Support::e($base['title']) ?>
          · <?= Support::e($all[$p['default_locale']] ?? $p['default_locale']) ?>
          &rarr; <?= Support::e($all[$loc] ?? $loc) ?></span>
        <span class="badge badge--<?= $t['state'] === 'reviewed' ? 'pine' : ($t['state'] === 'machine' ? 'sea' : 'ochre') ?>">
          <?= ['missing' => 'Da fare', 'machine' => 'Automatica, da rivedere',
               'reviewed' => 'Confermata da voi'][$t['state']] ?? Support::e($t['state']) ?></span>
      </div>
      <div class="trrow__a">
        <span class="kicker"><?= Support::e($all[$p['default_locale']] ?? '') ?> · originale</span>
        <p><?= Support::e($base['title']) ?></p>
        <p class="muted" style="white-space:pre-line"><?= Support::e($base['body']) ?></p>
      </div>
      <form method="post" class="trrow__b"><?= Csrf::field() ?>
        <input type="hidden" name="azione" value="salva-traduzione">
        <input type="hidden" name="section_id" value="<?= (int) $s['id'] ?>">
        <input type="hidden" name="locale" value="<?= Support::e($loc) ?>">
        <span class="kicker"><?= Support::e($all[$loc] ?? $loc) ?></span>
        <div class="field" style="margin:0"><input name="title" type="text"
          value="<?= Support::e($t['title']) ?>" placeholder="Titolo"></div>
        <div class="field" style="margin:0"><textarea name="body" placeholder="Testo"
          style="min-height:120px"><?= Support::e($t['body']) ?></textarea></div>
        <div class="row" style="margin-top:auto">
          <button class="btn btn--pine btn--sm">Va bene così</button>
        </div>
      </form>
    </div>
  <?php endforeach; ?>
<?php endforeach; ?>

<?php if (count($active) <= 1): ?>
  <p class="note" style="margin-top:16px"><?= Icon::svg('info', 19) ?>
    <span>Una sola lingua attiva: non c'è ancora niente da tradurre.</span></p>
<?php endif; ?>
