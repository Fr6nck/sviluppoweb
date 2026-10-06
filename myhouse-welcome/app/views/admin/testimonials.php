<?php
/* Testimonianze per la landing: solo vere, con il consenso di chi parla. */
use function MHW\b;
use MHW\{Support, Csrf, Testimonianze};
$title = 'Testimonianze';
$modulo = function (array $t) {
    $id = (int) ($t['id'] ?? 0); $p = 't' . $id; ob_start(); ?>
  <form method="post" action="<?= b() ?>/admin/testimonianze" enctype="multipart/form-data" class="stack"><?= Csrf::field() ?>
    <input type="hidden" name="id" value="<?= $id ?>">
    <div class="grid grid-2">
      <div class="field" style="margin:0"><label for="<?= $p ?>-nome">Nome</label>
        <input id="<?= $p ?>-nome" name="name" type="text" required maxlength="120" value="<?= Support::e($t['name'] ?? '') ?>"></div>
      <div class="field" style="margin:0"><label for="<?= $p ?>-str">Struttura</label>
        <input id="<?= $p ?>-str" name="property_name" type="text" maxlength="160" value="<?= Support::e($t['property_name'] ?? '') ?>"></div>
    </div>
    <div class="field" style="margin:0"><label for="<?= $p ?>-testo">Testo</label>
      <textarea id="<?= $p ?>-testo" name="body" rows="3" required maxlength="800"><?= Support::e($t['body'] ?? '') ?></textarea></div>
    <div class="grid grid-2">
      <div class="field" style="margin:0"><label for="<?= $p ?>-foto">Foto <span class="muted">(facoltativa, JPG, PNG o WebP)</span></label>
        <input id="<?= $p ?>-foto" name="foto" type="file" accept="image/jpeg,image/png,image/webp"></div>
      <div class="field" style="margin:0"><label for="<?= $p ?>-pos">Ordine</label>
        <input id="<?= $p ?>-pos" name="position" type="number" min="0" max="999" value="<?= (int) ($t['position'] ?? 0) ?>" style="max-width:8rem"></div>
    </div>
    <div class="row" style="gap:16px">
      <label class="check"><input type="checkbox" name="visible" value="1" <?= !empty($t['visible']) ? 'checked' : '' ?>> <span>Visibile sulla landing</span></label>
      <?php if (!empty($t['photo_key'])): ?><label class="check"><input type="checkbox" name="togli_foto" value="1"> <span>Togli la foto</span></label><?php endif; ?>
    </div>
    <div class="actions"><button class="btn btn--sm"><?= $id ? 'Salva' : 'Aggiungi la testimonianza' ?></button></div>
  </form>
<?php return (string) ob_get_clean(); }; ?>
<div class="stack stack--lg" style="max-width:820px">
  <div class="stack stack--sm">
    <h1>Testimonianze.</h1>
    <p class="muted">Sulla landing compaiono solo quelle segnate come visibili; se non ce n'è nessuna, il blocco non compare.
      Scrivi solo parole vere, con il permesso di chi le ha dette (anche per la foto).</p>
  </div>
  <?php if (!$righe): ?><p class="note note--quiet">Ancora nessuna testimonianza.</p><?php endif; ?>
  <?php foreach ($righe as $t): $foto = Testimonianze::foto($t); ?>
    <details class="fieldset">
      <summary class="legend" style="cursor:pointer;min-height:32px"><?= Support::e($t['name']) ?><?= $t['property_name'] !== '' ? ' · ' . Support::e($t['property_name']) : '' ?>
        <span class="badge badge--<?= (int) $t['visible'] ? 'pine' : 'paper' ?>"><?= (int) $t['visible'] ? 'Visibile' : 'Nascosta' ?></span></summary>
      <div class="stack" style="margin-top:12px">
        <?php if ($foto): ?><img src="<?= Support::e($foto) ?>" alt="" width="72" height="72" style="border-radius:50%;object-fit:cover"><?php endif; ?>
        <?= $modulo($t) ?>
        <form method="post" action="<?= b() ?>/admin/testimonianze/<?= (int) $t['id'] ?>/elimina" style="margin:0"><?= Csrf::field() ?>
          <button class="linkbtn">Elimina</button></form>
      </div>
    </details>
  <?php endforeach; ?>
  <section class="panel stack"><span class="kicker">Nuova testimonianza</span><?= $modulo([]) ?></section>
</div>
