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
          <?php if ($foto):
                $carica = ['id' => 'carica-foto', 'name' => 'foto', 'accept' => 'image/jpeg,image/png,image/webp', 'cosa' => "l'immagine",
                           'aiuto' => 'JPG, PNG o WebP, fino a 8 MB.', 'url' => $fotoUrl, 'togli' => 'togli-foto'];
                include __DIR__ . '/_carica.php';
              else: ?>
            <div class="mediabox">
              <span class="mediabox__img"><img src="<?= Support::e($fotoUrl) ?>" alt=""></span>
              <div class="stack" style="gap:8px">
                <p class="help">Il tuo piano non comprende le immagini nelle sezioni: toglila prima di pubblicare.</p>
                <button class="linkbtn" name="azione" value="togli-foto" formnovalidate>Togli l'immagine</button>
              </div>
            </div>
          <?php endif; ?>
        </fieldset>
      <?php endif; ?>

      <?php if ($pdf || $pdfRow): ?>
        <fieldset class="fieldset">
          <legend>PDF allegato</legend>
          <?php if ($pdf):
                $carica = ['id' => 'carica-pdf', 'name' => 'pdf', 'accept' => 'application/pdf', 'cosa' => 'il PDF',
                           'aiuto' => 'Per esempio il manuale della caldaia o la mappa del paese. Solo PDF, fino a 10 MB.',
                           'file' => $pdfRow ? ($pdfRow['original_name'] ?: 'Documento') : '', 'togli' => $pdfRow ? 'togli-pdf' : ''];
                include __DIR__ . '/_carica.php';
              else: ?>
            <?php if ($pdfRow): ?><p class="small"><?= Icon::svg('doc', 15) ?> <?= Support::e($pdfRow['original_name'] ?: 'Documento') ?></p><?php endif; ?>
            <p class="help">Il tuo piano non comprende i PDF: toglilo prima di pubblicare.</p>
            <?php if ($pdfRow): ?><button class="linkbtn" name="azione" value="togli-pdf" formnovalidate>Togli il PDF</button><?php endif; ?>
          <?php endif; ?>
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
        <?php if ($places): ?><div class="righe" data-ordina><?php endif; ?>
        <?php foreach ($places as $i => $pl): $azioneLuogo = $qui_url . '/luogo/' . (int) $pl['id'] . '/azione';
              $modificaLuogo = $qui_url . '?luogo=' . (int) $pl['id'] . ($procedura ? '&amp;da=procedura' : '') . '#luogo'; ?>
          <div class="riga" data-riga data-azione="<?= $azioneLuogo ?>">
            <span class="riga__maniglia" aria-hidden="true" title="Trascina per cambiare l'ordine"><?= Icon::svg('grip', 18, 2.6) ?></span>
            <a class="riga__nome" href="<?= $modificaLuogo ?>"><b><?= Support::e($pl['name']) ?></b>
              <span class="small muted"><?= Support::e(implode(' · ', array_filter([$pl['tr']['category'], $pl['address']]))) ?></span></a>
            <?php if ($pl['tr']['badge'] !== ''): ?><span class="badge badge--<?= Support::e($pl['badge_tone']) ?> riga__etichetta"><?= Support::e($pl['tr']['badge']) ?></span><?php endif; ?>
            <details class="menu-riga">
              <summary class="icon-btn" aria-label="Azioni per <?= Support::e($pl['name']) ?>"><span aria-hidden="true">⋯</span></summary>
              <div class="menu-riga__lista">
                <a class="menu-riga__voce" href="<?= $modificaLuogo ?>">Modifica</a>
                <?php foreach (array_filter(['su' => $i > 0 ? 'Sposta su' : '', 'giu' => $i < count($places) - 1 ? 'Sposta giù' : '']) as $fai => $et): ?>
                  <form method="post" action="<?= $azioneLuogo ?>" style="margin:0"><?= Csrf::field() ?>
                    <button class="menu-riga__voce" name="fai" value="<?= $fai ?>"><?= $et ?></button></form>
                <?php endforeach; ?>
                <hr class="rule">
                <form method="post" action="<?= $azioneLuogo ?>" style="margin:0"><?= Csrf::field() ?>
                  <button class="menu-riga__voce menu-riga__voce--danger" name="fai" value="elimina">Elimina</button></form>
              </div>
            </details>
          </div>
        <?php endforeach; ?>
        <?php if ($places): ?></div><?php endif; ?>

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
                <input type="text" id="pl-name" name="name" required maxlength="160" value="<?= Support::e($v['name']) ?>"></div>
              <div class="field" style="margin:0"><label for="pl-cat">Categoria</label>
                <input type="text" id="pl-cat" name="category" maxlength="80" list="categorie" value="<?= Support::e($v['tr']['category']) ?>" placeholder="Trattoria, Bar, Spiaggia…"></div>
            </div>
            <div class="field" style="margin:0"><label for="pl-desc">Descrizione</label>
              <textarea id="pl-desc" name="description" rows="2" maxlength="600"><?= Support::e($v['tr']['description']) ?></textarea></div>
            <div class="grid grid-2">
              <div class="field" style="margin:0"><label for="pl-addr">Indirizzo</label>
                <input type="text" id="pl-addr" name="address" maxlength="255" value="<?= Support::e($v['address']) ?>"></div>
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
                <input type="text" id="pl-badge" name="badge" maxlength="80" list="etichette" value="<?= Support::e($v['tr']['badge']) ?>" placeholder="Consigliato dall'host"></div>
            </div>
            <div class="field" style="margin:0"><label for="pl-note">Il tuo consiglio</label>
              <textarea id="pl-note" name="note" rows="2" maxlength="400" placeholder="Prenota il tavolo in terrazza, al tramonto."><?= Support::e($v['tr']['note']) ?></textarea></div>
            <fieldset class="tones scelte scelte--riga" style="border:0;padding:0;margin:0"><legend class="small" style="margin-bottom:6px">Colore dell'etichetta</legend>
              <?php foreach (['pine' => 'Verde', 'sea' => 'Blu', 'ochre' => 'Ocra', 'terracotta' => 'Terracotta'] as $k => $et): ?>
                <label class="tone scelta"><input type="radio" name="badge_tone" value="<?= $k ?>" <?= $v['badge_tone'] === $k ? 'checked' : '' ?>><span class="badge badge--<?= $k ?>"><?= $et ?></span></label>
              <?php endforeach; ?>
            </fieldset>
            <?php if ($foto): ?>
              <div class="field" style="margin:0"><span class="label">Foto del luogo <span class="muted">(facoltativa)</span></span>
                <?php $carica = ['id' => 'pl-foto', 'name' => 'foto', 'accept' => 'image/jpeg,image/png,image/webp', 'cosa' => 'la foto',
                                 'aiuto' => 'JPG, PNG o WebP, fino a 8 MB.', 'url' => $vFoto ?: null,
                                 'togli' => $vFoto ? 'togli-foto' : '', 'togliNome' => 'fai', 'togliVerso' => $qui_url . '/luogo/' . (int) $v['id'] . '/azione'];
                      include __DIR__ . '/_carica.php'; ?></div>
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
