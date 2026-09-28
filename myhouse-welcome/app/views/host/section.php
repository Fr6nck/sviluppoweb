<?php
/* L'editor di una sezione: campi veri, uno per informazione. Il testo si
   salva mentre scrivi (se il browser lo permette) e comunque col bottone.
   Le immagini e i PDF compaiono solo se il piano li comprende. */
use function MHW\b;
use MHW\{Support, Csrf, Icon, Media, SectionCatalog, Entitlements};
$pid = (int) $prop['id']; $sid = (int) $s['id']; $aid = (int) $acc['id'];
$nome = SectionCatalog::title($s['kind'], $prop['default_locale']);
$salvato = $title !== '' ? $title : $nome;
$title = $salvato . ' — ' . $prop['name'];
$titoloSalvato = Support::e($salvato);
$core = (int) $s['is_core'] === 1;
$foto = Entitlements::can($aid, 'photos');
$pdf = Entitlements::can($aid, 'pdf');
$fotoUrl = $s['media_id'] ? Media::url((int) $s['media_id']) : null;
$pdfRow = $s['pdf_media_id'] ? Media::row((int) $s['pdf_media_id']) : null;
$qui_url = b() . '/pannello/' . $pid . '/sezioni/' . $sid;
$src = '/pannello/' . $pid . '/anteprima/' . $sid;
$intro = SectionCatalog::get($s['kind'])['intro'] ?? '';
$inModifica = null;
foreach ($places as $pl) if ((int) $pl['id'] === (int) $modifica) $inModifica = $pl; ?>

<div class="editor">
  <div class="stack stack--lg">
    <div class="stack stack--sm">
      <a class="small" href="<?= b() ?>/pannello/<?= $pid ?><?= $procedura ? '/procedura/contenuti' : '' ?>"><?= Icon::svg('back', 14) ?> <?= $procedura ? 'Torna alla procedura' : 'Tutte le sezioni' ?></a>
      <div class="row" style="gap:10px">
        <h1 style="font-size:clamp(28px,3.4vw,38px)"><?= $titoloSalvato ?></h1>
        <?php if ($core): ?><span class="badge badge--sea">Sempre inclusa</span><?php endif; ?>
        <?php if (!$core && (int) $s['is_active'] === 0): ?><span class="badge badge--paper">Disattivata</span><?php endif; ?>
      </div>
      <?php if ($intro !== ''): ?><p class="muted"><?= Support::e($intro) ?></p><?php endif; ?>
    </div>

    <?php if ($err): ?><p class="note note--err" role="alert"><?= Support::e($err) ?></p><?php endif; ?>

    <form method="post" action="<?= $qui_url ?>" enctype="multipart/form-data" class="stack" data-autosave><?= Csrf::field() ?>
      <div class="field" style="margin:0">
        <label for="title">Titolo nella guida</label>
        <input id="title" name="title" type="text" maxlength="120" value="<?= $titoloSalvato ?>" placeholder="<?= Support::e($nome) ?>">
        <p class="help" style="margin-top:6px">Se lo lasci com'è, nelle altre lingue compare il titolo già tradotto.</p>
      </div>

      <?php $kind = $s['kind']; $uid = 's' . $sid; include __DIR__ . '/_campi.php'; ?>

      <?php if ($foto || $fotoUrl): ?>
        <fieldset class="fieldset">
          <legend>Immagine della sezione</legend>
          <div class="mediabox">
            <span class="mediabox__img"><?php if ($fotoUrl): ?><img src="<?= Support::e($fotoUrl) ?>" alt=""><?php endif; ?></span>
            <div class="stack" style="gap:8px">
              <?php if ($foto): ?>
                <input type="file" name="foto" accept="image/jpeg,image/png,image/webp" aria-label="Scegli un'immagine">
                <p class="help">JPG, PNG o WebP, fino a 8 MB.</p>
              <?php else: ?>
                <p class="help">Il tuo piano non comprende le immagini nelle sezioni: toglila prima di pubblicare.</p>
              <?php endif; ?>
              <?php if ($fotoUrl): ?><button class="linkbtn" name="azione" value="togli-foto" formnovalidate>Togli l'immagine</button><?php endif; ?>
            </div>
          </div>
        </fieldset>
      <?php endif; ?>

      <?php if ($pdf || $pdfRow): ?>
        <fieldset class="fieldset">
          <legend>PDF allegato</legend>
          <?php if ($pdfRow): ?><p class="small"><?= Icon::svg('doc', 15) ?> <?= Support::e($pdfRow['original_name'] ?: 'Documento') ?></p><?php endif; ?>
          <?php if ($pdf): ?>
            <input type="file" name="pdf" accept="application/pdf" aria-label="Scegli un PDF">
            <p class="help">Per esempio il manuale della caldaia o la mappa del paese. Solo PDF, fino a 10 MB.</p>
          <?php else: ?>
            <p class="help">Il tuo piano non comprende i PDF: toglilo prima di pubblicare.</p>
          <?php endif; ?>
          <?php if ($pdfRow): ?><button class="linkbtn" name="azione" value="togli-pdf" formnovalidate>Togli il PDF</button><?php endif; ?>
        </fieldset>
      <?php endif; ?>

      <?php if (!$foto && !$core): ?>
        <p class="tiny muted">Immagini e PDF nelle sezioni sono compresi dal piano Plus. <a href="<?= b() ?>/piano">Scopri Plus</a></p>
      <?php endif; ?>

      <div class="actions">
        <button class="btn" name="azione" value="salva">Salva</button>
        <?php if ($procedura): ?><button class="btn btn--ghost" name="dopo" value="contenuti">Salva e torna alla procedura</button><?php endif; ?>
        <span class="small muted" data-stato-salvataggio aria-live="polite"></span>
      </div>
    </form>

    <?php if (SectionCatalog::hasPlaces($s['kind'])): ?>
      <div class="stack" style="gap:12px">
        <h2 style="font-size:22px">I luoghi che consigli</h2>
        <?php if (!$places): ?><p class="note note--quiet">Ancora nessun luogo. Aggiungi il primo qui sotto: nome, indirizzo e due righe su perché ti piace.</p><?php endif; ?>
        <?php foreach ($places as $i => $pl): ?>
          <div class="rowcard" style="flex-wrap:wrap">
            <span class="grow stack" style="gap:2px"><b><?= Support::e($pl['name']) ?></b>
              <span class="small muted"><?= Support::e(implode(' · ', array_filter([$pl['tr']['category'], $pl['address']]))) ?></span></span>
            <?php if ($pl['tr']['badge'] !== ''): ?><span class="badge badge--<?= Support::e($pl['badge_tone'] === 'ochre' ? 'ochre' : $pl['badge_tone']) ?>"><?= Support::e($pl['tr']['badge']) ?></span><?php endif; ?>
            <span class="row" style="gap:2px">
              <a class="btn btn--quiet btn--sm" href="<?= $qui_url ?>?luogo=<?= (int) $pl['id'] ?><?= $procedura ? '&amp;da=procedura' : '' ?>#luogo">Modifica</a>
              <?php foreach (array_filter(['su' => $i > 0 ? 'Sposta su' : '', 'giu' => $i < count($places) - 1 ? 'Sposta giù' : '']) as $fai => $et): ?>
                <form method="post" action="<?= $qui_url ?>/luogo/<?= (int) $pl['id'] ?>/azione" style="margin:0"><?= Csrf::field() ?>
                  <button class="btn btn--quiet btn--sm" name="fai" value="<?= $fai ?>"><?= $et ?></button></form>
              <?php endforeach; ?>
              <details class="langpick"><summary class="btn btn--quiet btn--sm">Altro</summary>
                <div class="langpick__menu" style="min-width:220px;padding:12px">
                  <form method="post" action="<?= $qui_url ?>/luogo/<?= (int) $pl['id'] ?>/azione" style="margin:0"><?= Csrf::field() ?>
                    <button class="btn btn--danger btn--sm btn--block" name="fai" value="elimina">Elimina il luogo</button></form>
                </div></details>
            </span>
          </div>
        <?php endforeach; ?>

        <?php $v = $inModifica ?? ['id' => 0, 'name' => '', 'address' => '', 'maps_url' => '', 'phone' => '', 'website' => '', 'booking_url' => '',
                                   'walk_minutes' => 0, 'drive_minutes' => 0, 'badge_tone' => 'pine', 'media_id' => null,
                                   'tr' => ['category' => '', 'description' => '', 'note' => '', 'badge' => '']];
              $vFoto = $v['media_id'] ? Media::url((int) $v['media_id']) : null; ?>
        <details id="luogo" class="fieldset" <?= $inModifica || !$places ? 'open' : '' ?>>
          <summary class="legend" style="cursor:pointer;min-height:32px"><?= $inModifica ? 'Modifica: ' . Support::e($v['name']) : '+ Aggiungi un luogo' ?></summary>
          <form method="post" action="<?= $qui_url ?>/luogo" enctype="multipart/form-data" class="stack" style="margin-top:12px"><?= Csrf::field() ?>
            <input type="hidden" name="place_id" value="<?= (int) $v['id'] ?>">
            <div class="grid grid-2">
              <div class="field" style="margin:0"><label for="pl-name">Nome</label>
                <input id="pl-name" name="name" required maxlength="160" value="<?= Support::e($v['name']) ?>"></div>
              <div class="field" style="margin:0"><label for="pl-cat">Categoria</label>
                <input id="pl-cat" name="category" maxlength="80" list="categorie" value="<?= Support::e($v['tr']['category']) ?>" placeholder="Trattoria, Bar, Spiaggia…"></div>
            </div>
            <div class="field" style="margin:0"><label for="pl-desc">Descrizione</label>
              <textarea id="pl-desc" name="description" rows="2" maxlength="600"><?= Support::e($v['tr']['description']) ?></textarea></div>
            <div class="grid grid-2">
              <div class="field" style="margin:0"><label for="pl-addr">Indirizzo</label>
                <input id="pl-addr" name="address" maxlength="255" value="<?= Support::e($v['address']) ?>"></div>
              <div class="field" style="margin:0"><label for="pl-maps">Link a Google Maps <span class="muted">(facoltativo)</span></label>
                <input id="pl-maps" name="maps_url" type="url" maxlength="500" value="<?= Support::e($v['maps_url']) ?>" placeholder="https://"></div>
              <div class="field" style="margin:0"><label for="pl-walk">A piedi (minuti)</label>
                <input id="pl-walk" name="walk_minutes" type="number" min="0" max="600" inputmode="numeric" value="<?= (int) $v['walk_minutes'] ?: '' ?>"></div>
              <div class="field" style="margin:0"><label for="pl-drive">In auto (minuti)</label>
                <input id="pl-drive" name="drive_minutes" type="number" min="0" max="600" inputmode="numeric" value="<?= (int) $v['drive_minutes'] ?: '' ?>"></div>
              <div class="field" style="margin:0"><label for="pl-tel">Telefono</label>
                <input id="pl-tel" name="phone" type="tel" maxlength="40" value="<?= Support::e($v['phone']) ?>"></div>
              <div class="field" style="margin:0"><label for="pl-web">Sito web</label>
                <input id="pl-web" name="website" type="url" maxlength="500" value="<?= Support::e($v['website']) ?>" placeholder="https://"></div>
              <div class="field" style="margin:0"><label for="pl-book">Link per prenotare</label>
                <input id="pl-book" name="booking_url" type="url" maxlength="500" value="<?= Support::e($v['booking_url']) ?>" placeholder="https://"></div>
              <div class="field" style="margin:0"><label for="pl-badge">Etichetta</label>
                <input id="pl-badge" name="badge" maxlength="80" list="etichette" value="<?= Support::e($v['tr']['badge']) ?>" placeholder="Consigliato dall'host"></div>
            </div>
            <div class="field" style="margin:0"><label for="pl-note">Il tuo consiglio</label>
              <textarea id="pl-note" name="note" rows="2" maxlength="400" placeholder="Prenota il tavolo in terrazza, al tramonto."><?= Support::e($v['tr']['note']) ?></textarea></div>
            <fieldset class="tones" style="border:0;padding:0;margin:0"><legend class="small" style="margin-bottom:6px">Colore dell'etichetta</legend>
              <?php foreach (['pine' => 'Verde', 'sea' => 'Blu', 'ochre' => 'Ocra', 'terracotta' => 'Terracotta'] as $k => $et): ?>
                <label class="tone"><input type="radio" name="badge_tone" value="<?= $k ?>" <?= $v['badge_tone'] === $k ? 'checked' : '' ?>><span class="badge badge--<?= $k ?>"><?= $et ?></span></label>
              <?php endforeach; ?>
            </fieldset>
            <?php if ($foto): ?>
              <div class="mediabox">
                <?php if ($vFoto): ?><span class="mediabox__img"><img src="<?= Support::e($vFoto) ?>" alt=""></span><?php endif; ?>
                <div class="field" style="margin:0"><label for="pl-foto">Foto del luogo <span class="muted">(facoltativa)</span></label>
                  <input id="pl-foto" type="file" name="foto" accept="image/jpeg,image/png,image/webp"></div>
              </div>
            <?php endif; ?>
            <div class="actions">
              <button class="btn"><?= $inModifica ? 'Salva il luogo' : 'Aggiungi il luogo' ?></button>
              <?php if ($inModifica): ?><a class="btn btn--quiet" href="<?= $qui_url ?>">Annulla</a><?php endif; ?>
            </div>
          </form>
          <?php if ($inModifica && $vFoto): ?>
            <form method="post" action="<?= $qui_url ?>/luogo/<?= (int) $v['id'] ?>/azione" style="margin:0"><?= Csrf::field() ?>
              <button class="linkbtn" name="fai" value="togli-foto">Togli la foto</button></form>
          <?php endif; ?>
        </details>
        <datalist id="categorie"><?php foreach (['Ristorante', 'Trattoria', 'Pizzeria', 'Bar', 'Colazione', 'Enoteca', 'Gelateria', 'Spiaggia', 'Museo', 'Borgo', 'Sentiero', 'Mercato'] as $c): ?><option value="<?= $c ?>"><?php endforeach; ?></datalist>
        <datalist id="etichette"><?php foreach (["Consigliato dall'host", 'Perfetto per cena', 'Ideale per colazione', 'Da non perdere', 'Per famiglie', 'Vista mare'] as $c): ?><option value="<?= Support::e($c) ?>"><?php endforeach; ?></datalist>
      </div>
    <?php endif; ?>

    <a class="btn btn--ghost phonebtn" href="<?= Support::e(Support::url($src)) ?>" target="_blank" rel="noopener"><?= Icon::svg('eye', 16) ?>Guarda l'anteprima</a>
  </div>
  <?php include __DIR__ . '/_telefono.php'; ?>
</div>
