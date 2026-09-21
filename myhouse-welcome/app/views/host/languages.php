<?php use function MHW\b; use MHW\{Support, Csrf}; $title = 'Lingue'; $nav = 'pannello'; ?>
<h1>Lingue e traduzioni</h1>
<nav class="nav" style="margin-top:20px;display:inline-flex">
  <a href="<?= b() ?>/pannello/<?= (int) $p['id'] ?>">Sezioni</a>
  <a class="on" href="<?= b() ?>/pannello/<?= (int) $p['id'] ?>/lingue">Lingue</a>
  <a href="<?= b() ?>/pannello/<?= (int) $p['id'] ?>/qr">QR</a>
  <a href="<?= b() ?>/pannello/<?= (int) $p['id'] ?>/impostazioni">Impostazioni</a>
</nav>
<?php if ($err): ?><p class="note note--err" style="margin-top:16px"><?= Support::e($err) ?></p><?php endif; ?>

<form method="post" class="card" style="margin-top:20px;max-width:620px"><?= Csrf::field() ?>
  <input type="hidden" name="azione" value="attiva">
  <p class="kicker">Quali lingue pubblicare</p>
  <div class="row" style="margin-top:12px">
    <?php foreach ($all as $code => $label): $ok = in_array($code, $allowed, true); ?>
      <label class="row small" style="gap:8px;margin:0">
        <input type="checkbox" name="locali[]" value="<?= $code ?>" style="width:auto;min-height:0"
          <?= in_array($code, $active, true) ? 'checked' : '' ?> <?= $ok ? '' : 'disabled' ?>>
        <?= Support::e($label) ?><?= $ok ? '' : ' (non nel piano)' ?>
      </label>
    <?php endforeach; ?>
  </div>
  <button class="btn btn--ghost btn--sm" style="margin-top:16px">Aggiorna le lingue</button>
</form>

<form method="post" style="margin-top:16px"><?= Csrf::field() ?>
  <input type="hidden" name="azione" value="traduci">
  <button class="btn btn--ghost btn--sm" <?= $translator ? '' : 'disabled' ?>>Traduci quello che manca</button>
  <?php if (!$translator): ?>
    <p class="muted tiny" style="margin-top:8px">Nessun servizio di traduzione configurato: le lingue si compilano a mano.
    Per attivarlo aggiungete provider e chiave in <code>config.php</code>.</p>
  <?php endif; ?>
</form>

<h2 style="margin-top:32px">Le traduzioni</h2>
<p class="muted small" style="margin-top:8px">Una traduzione che confermate diventa <strong>vostra</strong>: nessuna macchina la sovrascrive più.</p>
<?php foreach ($sections as $s):
  $base = $s['tr'][$p['default_locale']] ?? null; if (!$base) continue; ?>
  <div class="card" style="margin-top:16px">
    <p class="kicker"><?= Support::e($base['title']) ?></p>
    <?php foreach ($active as $loc): if ($loc === $p['default_locale']) continue;
      $t = $s['tr'][$loc] ?? ['title' => '', 'body' => '', 'state' => 'missing']; ?>
      <form method="post" style="margin-top:16px;padding-top:16px;border-top:1px solid var(--line)"><?= Csrf::field() ?>
        <input type="hidden" name="azione" value="salva-traduzione">
        <input type="hidden" name="section_id" value="<?= (int) $s['id'] ?>">
        <input type="hidden" name="locale" value="<?= Support::e($loc) ?>">
        <div class="spread"><span class="kicker"><?= Support::e($all[$loc] ?? $loc) ?></span>
          <span class="badge badge--<?= $t['state'] === 'reviewed' ? 'pine' : ($t['state'] === 'machine' ? 'sea' : 'ochre') ?>">
            <?= ['missing' => 'da fare', 'machine' => 'automatica', 'reviewed' => 'confermata da voi'][$t['state']] ?? $t['state'] ?></span></div>
        <div class="field" style="margin-top:10px"><input name="title" type="text" value="<?= Support::e($t['title']) ?>" placeholder="Titolo"></div>
        <div class="field"><textarea name="body" placeholder="Testo" style="min-height:90px"><?= Support::e($t['body']) ?></textarea></div>
        <button class="btn btn--ghost btn--sm">Conferma questa traduzione</button>
      </form>
    <?php endforeach; ?>
    <?php if (count($active) <= 1): ?><p class="muted small" style="margin-top:10px">Una sola lingua attiva.</p><?php endif; ?>
  </div>
<?php endforeach; ?>
