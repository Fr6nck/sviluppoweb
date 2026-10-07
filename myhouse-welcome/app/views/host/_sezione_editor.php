<?php
/* L'editor di una sezione: campi, immagine, PDF, luoghi. Lo usano la pagina
   della sezione e il passo «Sezioni» della procedura, dove si apre sotto la card.
   Riceve: $prop, $acc, $s, $titoloSezione (titolo nella lingua principale),
   $dati, $tdati, $places, $modifica, $err, $inProcedura,
   $suggLuoghi e $suggRighe («Già in <struttura>», da Suggerimenti). */
use function MHW\b;
use MHW\{Support, Csrf, Icon, Media, SectionCatalog, Entitlements, Tassonomie, I18n};
$pid = (int) $prop['id']; $sid = (int) $s['id']; $aid = (int) $acc['id'];
$nome = SectionCatalog::title($s['kind'], $prop['default_locale']);
$titoloSalvato = Support::e($titoloSezione !== '' ? $titoloSezione : $nome);
$core = (int) $s['is_core'] === 1;
$foto = Entitlements::can($aid, 'photos');
$pdf = Entitlements::can($aid, 'pdf');
$fotoUrl = $s['media_id'] ? Media::url((int) $s['media_id']) : null;
$pdfRow = $s['pdf_media_id'] ? Media::row((int) $s['pdf_media_id']) : null;
$qui_url = b() . '/pannello/' . $pid . '/sezioni/' . $sid;
$inProcedura = $inProcedura ?? false;
// Dove porta «Modifica» di un luogo: nella procedura resta nella procedura.
$modificaLuogoUrl = $inProcedura ? b() . '/pannello/' . $pid . '/procedura/sezioni?apri=' . $sid . '&amp;luogo=' : $qui_url . '?luogo=';
$procedura = $procedura ?? false;
$inModifica = null;
foreach ($places as $pl) if ((int) $pl['id'] === (int) $modifica) $inModifica = $pl;
$suggLuoghi = $suggLuoghi ?? []; $suggRighe = $suggRighe ?? []; ?>
    <?php if ($err): ?><p class="note note--err" role="alert"><?= Support::e($err) ?></p><?php endif; ?>
    <?php /* Nella procedura l'editor si apre sotto la card: il riquadro con l'introduzione sta qui (nella pagina della sezione è sotto il titolo). */
          $introSezione = SectionCatalog::get($s['kind'])['intro'] ?? '';
          if ($inProcedura && $introSezione !== ''): ?><p class="note note--quiet"><?= Support::e($introSezione) ?></p><?php endif; ?>

    <form method="post" action="<?= $qui_url ?>" enctype="multipart/form-data" class="stack" data-autosave><?= Csrf::field() ?>
      <?php if ($inProcedura): ?><input type="hidden" name="da" value="procedura"><?php endif; ?>
      <div class="field" style="margin:0">
        <label for="title">Titolo nella guida</label>
        <input id="title" name="title" type="text" maxlength="120" value="<?= $titoloSalvato ?>" placeholder="<?= Support::e($nome) ?>">
        <p class="help" style="margin-top:6px">Se lo lasci com'è, nelle altre lingue compare il titolo già tradotto.</p>
      </div>

      <?php $kind = $s['kind']; $uid = 's' . $sid; include __DIR__ . '/_campi.php'; ?>

      <?php if ($foto || $fotoUrl): ?>
        <fieldset class="fieldset">
          <legend>Foto della sezione</legend>
          <?php if ($foto):
                $carica = ['id' => 'carica-foto', 'name' => 'foto', 'accept' => 'image/jpeg,image/png,image/webp', 'cosa' => 'la foto',
                           'aiuto' => 'JPG, PNG o WebP, fino a 8 MB.', 'url' => $fotoUrl, 'togli' => 'togli-foto'];
                include __DIR__ . '/_carica.php';
              else: ?>
            <div class="mediabox">
              <span class="mediabox__img"><img src="<?= Support::e($fotoUrl) ?>" alt=""></span>
              <div class="stack" style="gap:8px">
                <p class="help">Il tuo piano non comprende le foto nelle sezioni: toglila prima di pubblicare.</p>
                <button class="linkbtn" name="azione" value="togli-foto" formnovalidate>Togli la foto</button>
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
                           'aiuto' => 'Per esempio: il manuale della caldaia o la mappa del paese. Solo PDF, fino a 10 MB.',
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
        <p class="tiny muted">Foto e PDF nelle sezioni sono disponibili con il piano Plus. <a href="<?= b() ?>/piano?passa=plus">Scopri Plus</a></p>
      <?php endif; ?>

      <div class="actions">
        <button class="btn" name="azione" value="salva">Salva</button>
        <?php if ($procedura): ?><button class="btn btn--ghost" name="dopo" value="sezioni">Salva e torna alla configurazione</button><?php endif; ?>
        <span class="small muted" data-stato-salvataggio aria-live="polite"></span>
      </div>
    </form>

    <?php if ($suggLuoghi || $suggRighe): /* «Già in <struttura>»: i bottoni stanno nell'editor, il modulo qui fuori.
             Con JavaScript prima si salva quello che si è scritto (data-salva-prima). */ ?>
      <form method="post" action="<?= $qui_url ?>/da-altra" id="da-altra-<?= $sid ?>" data-salva-prima hidden><?= Csrf::field() ?>
        <?php if ($inProcedura): ?><input type="hidden" name="da" value="procedura"><?php endif; ?></form>
    <?php endif; ?>

    <?php if (SectionCatalog::hasPlaces($s['kind'])): ?>
      <div class="stack" style="gap:12px">
        <h2 style="font-size:22px">I luoghi che consigli</h2>
        <?php if (!$places): ?><p class="note note--quiet">Ancora nessun luogo. Aggiungi il primo qui sotto: nome, indirizzo e due righe su perché ti piace.</p><?php endif; ?>
        <?php if ($places): ?><div class="righe" data-ordina><?php endif; ?>
        <?php foreach ($places as $i => $pl): $azioneLuogo = $qui_url . '/luogo/' . (int) $pl['id'] . '/azione';
              $modificaLuogo = $modificaLuogoUrl . (int) $pl['id'] . ($procedura ? '&amp;da=procedura' : '') . '#luogo'; ?>
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
                  <form method="post" action="<?= $azioneLuogo ?>" style="margin:0"><?= Csrf::field() ?><?= $inProcedura ? '<input type="hidden" name="da" value="procedura">' : '' ?>
                    <button class="menu-riga__voce" name="fai" value="<?= $fai ?>"><?= $et ?></button></form>
                <?php endforeach; ?>
                <hr class="rule">
                <form method="post" action="<?= $azioneLuogo ?>" style="margin:0"><?= Csrf::field() ?><?= $inProcedura ? '<input type="hidden" name="da" value="procedura">' : '' ?>
                  <button class="menu-riga__voce menu-riga__voce--danger" name="fai" value="elimina">Elimina</button></form>
              </div>
            </details>
          </div>
        <?php endforeach; ?>
        <?php if ($places): ?></div><?php endif; ?>

        <?php if ($suggLuoghi && !$inModifica): $gruppi = [];
              foreach ($suggLuoghi as $x) $gruppi[$x['struttura']][] = $x; ?>
          <div class="gia-altrove" data-gia-altrove>
            <?php if (count($suggLuoghi) > 12): ?>
              <div class="field" style="margin:0"><label for="gia-cerca-<?= $sid ?>">Cerca tra i luoghi delle tue altre strutture</label>
                <input type="search" id="gia-cerca-<?= $sid ?>" data-gia-cerca autocomplete="off" data-no-autosave></div>
            <?php endif; ?>
            <?php foreach ($gruppi as $struttura => $voci): ?>
              <div class="suggerimenti">
                <span class="small muted">Già in <?= Support::e($struttura) ?>:</span>
                <?php foreach ($voci as $x): ?>
                  <button type="submit" class="chip-sugg" form="da-altra-<?= $sid ?>" name="luogo" value="<?= (int) $x['id'] ?>" data-nome="<?= Support::e(mb_strtolower($x['name'])) ?>"
                          aria-label="Aggiungi <?= Support::e($x['name']) ?>, già in <?= Support::e($struttura) ?>">+ <?= Support::e($x['name']) ?><?php if ($x['categoria'] !== ''): ?><span class="muted gia-altrove__cat">· <?= Support::e($x['categoria']) ?></span><?php endif; ?></button>
                <?php endforeach; ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?php $v = $inModifica ?? ['id' => 0, 'name' => '', 'address' => '', 'maps_url' => '', 'phone' => '', 'website' => '', 'booking_url' => '',
                                   'walk_minutes' => 0, 'drive_minutes' => 0, 'badge_tone' => 'pine', 'media_id' => null,
                                   'category_key' => '', 'badge_key' => '',
                                   'tr' => ['category' => '', 'description' => '', 'note' => '', 'badge' => '']];
              // Il salvataggio non è riuscito: il modulo torna con quello che era stato scritto e l'errore accanto al campo.
              $bozza = null;
              if (($_SESSION['luogo_bozza']['sid'] ?? 0) === (int) $s['id']) { $bozza = $_SESSION['luogo_bozza']; unset($_SESSION['luogo_bozza']); }
              if ($bozza && (int) $bozza['place_id'] === (int) $v['id']) {
                  $b = $bozza['in'];
                  foreach (['name', 'address', 'maps_url', 'phone', 'website', 'booking_url', 'badge_tone'] as $k) if (isset($b[$k])) $v[$k] = $b[$k];
                  foreach (['walk_minutes', 'drive_minutes'] as $k) if (isset($b[$k])) $v[$k] = (int) $b[$k];
                  foreach (['description', 'note', 'category', 'badge'] as $k) if (isset($b[$k])) $v['tr'][$k] = $b[$k];
                  if (isset($b['category_choice'])) $v['category_key'] = in_array($b['category_choice'], ['__altro', ''], true) ? '' : $b['category_choice'];
                  if (isset($b['badge_choice'])) $v['badge_key'] = in_array($b['badge_choice'], ['__altra', ''], true) ? '' : $b['badge_choice'];
              } else $bozza = null;
              $erroreNome = $bozza && $bozza['campo'] === 'name';
              $vFoto = $v['media_id'] ? Media::url((int) $v['media_id']) : null; ?>
        <details id="luogo" class="fieldset" <?= $inModifica || !$places || $bozza ? 'open' : '' ?>>
          <summary class="legend" style="cursor:pointer;min-height:32px"><?= $inModifica ? 'Modifica: ' . Support::e($v['name']) : '+ Aggiungi un luogo' ?></summary>
          <?php /* Un luogo già salvato si salva anche mentre si scrive; uno nuovo solo col bottone (non nascono doppioni). */ ?>
          <form method="post" action="<?= $qui_url ?>/luogo" enctype="multipart/form-data" class="stack" style="margin-top:12px"<?= (int) $v['id'] ? ' data-autosave' : '' ?>><?= Csrf::field() ?>
            <?php if ($inProcedura): ?><input type="hidden" name="da" value="procedura"><?php endif; ?>
            <input type="hidden" name="place_id" value="<?= (int) $v['id'] ?>">
            <?php if ($bozza && !$erroreNome): ?><p class="note note--err" role="alert"><?= Support::e($bozza['errore']) ?></p><?php endif; ?>
            <?php /* Prima il link di Maps: da lì nome, coordinate e minuti a piedi. Poi solo l'essenziale;
                     il resto in «Altri dettagli», chiuso. */ ?>
            <div class="field" style="margin:0"><label for="pl-maps">Incolla il link di Google Maps</label>
              <p class="help" id="pl-maps-aiuto" style="margin:0 0 6px">Su Google Maps: Condividi → Copia link. Se lasci vuoto il nome, lo prendiamo dal link.</p>
              <input id="pl-maps" name="maps_url" type="url" maxlength="500" value="<?= Support::e($v['maps_url']) ?>" placeholder="https://maps.app.goo.gl/…"
                     aria-describedby="pl-maps-aiuto pl-maps-stato" data-mappe="<?= b() ?>/pannello/<?= $pid ?>/mappe">
              <p class="help" id="pl-maps-stato" data-mappe-stato aria-live="polite"></p></div>
            <div class="field" style="margin:0"><label for="pl-name">Nome</label>
              <p class="help" id="pl-name-aiuto" style="margin:0 0 6px">Serve sempre: se il link di Maps non lo dà, scrivilo tu.</p>
              <input type="text" id="pl-name" name="name" maxlength="160" autocomplete="off" value="<?= Support::e($v['name']) ?>"
                     aria-describedby="pl-name-aiuto<?= $erroreNome ? ' pl-name-err' : '' ?>"<?= $erroreNome ? ' aria-invalid="true" autofocus' : '' ?>>
              <?php if ($erroreNome): ?><p class="campo-errore" id="pl-name-err"><?= Support::e($bozza['errore']) ?></p><?php endif; ?></div>
            <?php /* Categoria ed etichetta (fase 6B): pillole con le voci di questa sezione, tradotte da sole
                     nella guida; «Altro…» e «Personalizzata…» aprono il testo libero. */
            $cats = Tassonomie::categorie($s['kind']); $tags = Tassonomie::etichette($s['kind']);
            $ck = (string) ($v['category_key'] ?? ''); $bk = (string) ($v['badge_key'] ?? '');
            $cTesto = (string) $v['tr']['category']; $bTesto = (string) $v['tr']['badge']; ?>
            <?php if ($cats): ?>
              <fieldset class="fieldset scelta-luogo">
                <legend>Categoria</legend>
                <div class="scelte scelte--riga">
                  <?php foreach ($cats as $k): ?>
                    <label class="scelta scelta--mini"><input type="radio" name="category_choice" value="<?= Support::e($k) ?>" <?= $ck === $k ? 'checked' : '' ?>><span><?= Support::e(I18n::t('it', 'cat.' . $k)) ?></span></label>
                  <?php endforeach; ?>
                  <label class="scelta scelta--mini"><input type="radio" name="category_choice" value="__altro" data-apre="pl-cat-box" <?= $ck === '' && $cTesto !== '' ? 'checked' : '' ?>><span>Altro…</span></label>
                </div>
                <div class="field" id="pl-cat-box" style="margin:10px 0 0"><label for="pl-cat">Scrivi la categoria</label>
                  <input type="text" id="pl-cat" name="category" maxlength="80" value="<?= Support::e($cTesto) ?>"></div>
              </fieldset>
            <?php else: ?>
              <div class="field" style="margin:0"><label for="pl-cat">Categoria</label>
                <input type="text" id="pl-cat" name="category" maxlength="80" value="<?= Support::e($cTesto) ?>"></div>
            <?php endif; ?>
            <div class="field" style="margin:0"><label for="pl-note">Perché lo consigli</label>
              <textarea id="pl-note" name="note" rows="2" maxlength="400" placeholder="<?= Support::e(Tassonomie::SEGNAPOSTO[$s['kind']] ?? 'Prenota il tavolo in terrazza, al tramonto.') ?>"><?= Support::e($v['tr']['note']) ?></textarea></div>
            <?php if ($tags): ?>
              <fieldset class="fieldset scelta-luogo">
                <legend>Etichetta <span class="muted">(facoltativa)</span></legend>
                <p class="help">Compare sulla scheda del luogo, in un bollino colorato.</p>
                <div class="scelte scelte--riga">
                  <label class="scelta scelta--mini"><input type="radio" name="badge_choice" value="" <?= $bk === '' && $bTesto === '' ? 'checked' : '' ?>><span>Nessuna</span></label>
                  <?php foreach ($tags as $k): ?>
                    <label class="scelta scelta--mini"><input type="radio" name="badge_choice" value="<?= Support::e($k) ?>" <?= $bk === $k ? 'checked' : '' ?>><span><?= Support::e(I18n::t('it', 'badge.' . $k)) ?></span></label>
                  <?php endforeach; ?>
                  <label class="scelta scelta--mini"><input type="radio" name="badge_choice" value="__altra" data-apre="pl-badge-box" <?= $bk === '' && $bTesto !== '' ? 'checked' : '' ?>><span>Personalizzata…</span></label>
                </div>
                <div class="field" id="pl-badge-box" style="margin:10px 0 0"><label for="pl-badge">Scrivi l'etichetta</label>
                  <input type="text" id="pl-badge" name="badge" maxlength="80" value="<?= Support::e($bTesto) ?>"></div>
              </fieldset>
            <?php else: ?>
              <div class="field" style="margin:0"><label for="pl-badge">Etichetta <span class="muted">(facoltativa)</span></label>
                <input type="text" id="pl-badge" name="badge" maxlength="80" value="<?= Support::e($bTesto) ?>" aria-describedby="pl-badge-aiuto">
                <p class="help" id="pl-badge-aiuto">Compare sulla scheda del luogo, in un bollino colorato.</p></div>
            <?php endif; ?>
            <details class="altri-dettagli">
              <summary>Altri dettagli <span class="small muted">— descrizione, indirizzo, minuti, contatti, foto</span></summary>
              <div class="stack" style="margin-top:14px">
            <div class="field" style="margin:0"><label for="pl-desc">Descrizione</label>
              <textarea id="pl-desc" name="description" rows="2" maxlength="600"><?= Support::e($v['tr']['description']) ?></textarea></div>
            <div class="grid grid-2">
              <div class="field" style="margin:0"><label for="pl-addr">Indirizzo</label>
                <input type="text" id="pl-addr" name="address" maxlength="255" value="<?= Support::e($v['address']) ?>"></div>
              <div class="field" style="margin:0"><label for="pl-walk">Minuti a piedi</label>
                <input id="pl-walk" name="walk_minutes" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="3" value="<?= (int) $v['walk_minutes'] ?: '' ?>" aria-describedby="pl-walk-aiuto">
                <p class="help" id="pl-walk-aiuto">Dalla struttura. È una stima che puoi correggere: la calcoliamo dal link di Maps del luogo e da quello della struttura, in «Come arrivare».</p></div>
              <div class="field" style="margin:0"><label for="pl-drive">Minuti in auto</label>
                <input id="pl-drive" name="drive_minutes" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="3" value="<?= (int) $v['drive_minutes'] ?: '' ?>"></div>
              <div class="field" style="margin:0"><label for="pl-tel">Telefono</label>
                <input id="pl-tel" name="phone" type="tel" maxlength="40" data-prefisso="+39 " placeholder="+39 075 123 4567" autocomplete="off" value="<?= Support::e($v['phone']) ?>"></div>
              <div class="field" style="margin:0"><label for="pl-web">Sito web</label>
                <input id="pl-web" name="website" type="url" maxlength="500" value="<?= Support::e($v['website']) ?>" placeholder="https://"></div>
              <div class="field" style="margin:0"><label for="pl-book">Link per prenotare</label>
                <input id="pl-book" name="booking_url" type="url" maxlength="500" value="<?= Support::e($v['booking_url']) ?>" placeholder="https://"></div>
            </div>
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
              </div>
            </details>
            <div class="actions">
              <button class="btn"><?= $inModifica ? 'Salva il luogo' : 'Aggiungi il luogo' ?></button>
              <?php if ((int) $v['id']): ?><span class="small muted" data-stato-salvataggio aria-live="polite"></span><?php endif; ?>
              <?php if ($inModifica): ?><a class="btn btn--quiet" href="<?= $inProcedura ? b() . '/pannello/' . $pid . '/procedura/sezioni?apri=' . $sid . '#sez-' . $sid : $qui_url ?>">Annulla</a><?php endif; ?>
            </div>
          </form>
          <?php if ($inModifica && $vFoto): ?>
            <form method="post" action="<?= $qui_url ?>/luogo/<?= (int) $v['id'] ?>/azione" style="margin:0"><?= Csrf::field() ?>
              <?= $inProcedura ? '<input type="hidden" name="da" value="procedura">' : '' ?>
              <button class="linkbtn" name="fai" value="togli-foto">Togli la foto</button></form>
          <?php endif; ?>
        </details>
      </div>
    <?php endif; ?>

