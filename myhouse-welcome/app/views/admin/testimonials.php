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
  <?php $giudizi = $giudizi ?? []; $attesa = array_filter($giudizi, fn($g) => $g['status'] === 'in_attesa'); ?>
  <?php if (MHW\Giudizi::disponibili()): /* 9B: i giudizi dei clienti stanno in Clienti → Giudizi */ ?>
    <p class="note" role="status"><span>I giudizi dei clienti (con lo sconto) sono in <a href="<?= b() ?>/admin/giudizi">Clienti → Giudizi</a>: lì decidi quali mettere in home.</span></p>
  <?php else: ?>
  <section class="stack" aria-labelledby="giudizi-titolo" style="gap:12px">
    <h2 id="giudizi-titolo" style="font-size:22px">Giudizi dei clienti <?php if ($attesa): ?><span class="badge badge--ochre"><?= count($attesa) ?> in attesa</span><?php endif; ?></h2>
    <p class="small muted" style="margin:0">Arrivano dal pannello («Ti sta piacendo MyHouse Welcome?»). Si pubblicano solo quelli con il consenso;
      se il cliente lo ritira, il giudizio sparisce subito dalla home. In home la media compare da <?= Testimonianze::MEDIA_DA ?> giudizi pubblicati.</p>
    <?php if (!$giudizi): ?><p class="note note--quiet">Ancora nessun giudizio.</p><?php endif; ?>
    <?php foreach ($giudizi as $g):
          $ritirato = !empty($g['withdrawn_at']); $consenso = !empty($g['consent_at']) && !$ritirato;
          [$tono, $et] = $ritirato ? ['alert', 'Consenso ritirato'] : match ($g['status']) {
              'pubblicata' => ['pine', 'Pubblicato'], 'rifiutata' => ['paper', 'Rifiutato'], default => ['ochre', 'In attesa'] }; ?>
      <article class="panel stack giudizio-admin" style="gap:8px">
        <div class="row" style="gap:10px;flex-wrap:wrap">
          <span class="stelline" aria-label="<?= (int) $g['rating'] ?> su 5"><?= str_repeat('★', (int) $g['rating']) . str_repeat('☆', 5 - (int) $g['rating']) ?></span>
          <b><?= Support::e($g['name']) ?></b><span class="small muted"><?= Support::e(trim($g['property_name'] . ($g['city'] !== '' ? ', ' . $g['city'] : ''), ', ')) ?></span>
          <span class="badge badge--<?= $tono ?>"><?= $et ?></span>
          <span class="tiny muted"><?= Support::e(Support::date($g['created_at'])) ?> · <a href="<?= b() ?>/admin/cliente/<?= (int) $g['account_id'] ?>"><?= Support::e((string) $g['email']) ?></a></span>
        </div>
        <p style="margin:0">«<?= Support::e($g['body']) ?>»</p>
        <p class="tiny muted" style="margin:0">Foto: <?= Support::e(Testimonianze::FOTO[$g['photo_source']] ?? 'Nessuna foto') ?>.
          <?= $consenso ? 'Consenso del ' . Support::e(Support::date($g['consent_at'])) . ': «' . Support::e((string) $g['consent_text']) . '»' : ($ritirato ? '' : 'Senza consenso: non si può pubblicare.') ?></p>
        <?php if ($g['status'] !== 'pubblicata' && !$ritirato): ?>
          <div class="row" style="gap:8px">
            <?php if ($consenso): ?><form method="post" action="<?= b() ?>/admin/testimonianze/<?= (int) $g['id'] ?>/pubblica" style="margin:0"><?= Csrf::field() ?><button class="btn btn--sm">Pubblica</button></form><?php endif; ?>
            <?php if ($g['status'] !== 'rifiutata'): ?><form method="post" action="<?= b() ?>/admin/testimonianze/<?= (int) $g['id'] ?>/rifiuta" style="margin:0"><?= Csrf::field() ?><button class="btn btn--ghost btn--sm">Rifiuta</button></form><?php endif; ?>
          </div>
        <?php elseif ($g['status'] === 'pubblicata' && !$ritirato): ?>
          <form method="post" action="<?= b() ?>/admin/testimonianze/<?= (int) $g['id'] ?>/rifiuta" style="margin:0"><?= Csrf::field() ?><button class="linkbtn">Togli dalla home</button></form>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
  </section>
  <?php endif; ?>
  <h2 style="font-size:22px">Scritte da noi</h2>
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
