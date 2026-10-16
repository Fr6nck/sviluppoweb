<?php
/* «Ti piace MyHouse Welcome?» (7D, 9B): il giudizio del cliente sulla piattaforma.
   5 stelle (obbligatorie), «Cosa diresti a un altro host?» (20–400 caratteri), le immagini da mostrare se va in home
   (chip: copertina, la mia foto, logo; solo quelle che esistono, pre-selezionate). Nessuna casella di consenso: la
   pubblicazione fa parte dell'adesione al programma; chi non ha ancora aderito trova qui la stessa unica casella
   («Partecipo»). Riceve $err, $fatto (dopo l'invio), $vecchi, $p (la struttura), $gia (un giudizio già dato), $riquadro. */
use function MHW\b;
use MHW\{Support, Icon, Csrf, Media, Testimonianze, Giudizi};
$title = 'Il tuo giudizio';
$programma = Giudizi::disponibili();
$voto = (int) ($vecchi['rating'] ?? 0);
$pct = $programma ? Giudizi::percentuale() : 5;
$ha = $p ? MHW\Db::one('SELECT profile_media_id, cover_media_id, logo_media_id FROM properties WHERE id = ?', [(int) $p['id']]) : null;
$chip = ['show_cover' => [$ha['cover_media_id'] ?? null, 'Copertina'], 'show_photo' => [$ha['profile_media_id'] ?? null, 'La mia foto'], 'show_logo' => [$ha['logo_media_id'] ?? null, 'Logo della struttura']];
$modifica = !empty($vecchi['modifica']);
$pubblicata = $programma ? Testimonianze::primaPubblicazione((int) $acc['id']) !== null : true;
$aderito = !empty($acc['giudizio_adesione']);
$puoAderire = $programma && Giudizi::puoAderire($acc);
$inHome = $gia && !empty($gia['home_visible']) && $gia['withdrawn_at'] === null; ?>
<div class="stack stack--lg" style="max-width:720px">
  <?php if ($fatto): ?>
    <section class="panel stack giudizio-grazie" role="status">
      <span class="giudizio-grazie__ico" aria-hidden="true"><?= Icon::svg('star', 26, 1.8) ?></span>
      <?php if ($fatto['rating'] <= 3): ?>
        <h1 style="font-size:30px">Grazie.</h1>
        <p class="lead">Ti scriviamo per capire cosa migliorare.<?= $programma && !empty($fatto['aderito']) ? ' Lo sconto non dipende dal voto: resta tuo.' : '' ?></p>
      <?php else: ?>
        <h1 style="font-size:30px">Grazie! Giudizio ricevuto.</h1>
        <p class="lead"><?php if ($programma && !empty($fatto['aderito'])): ?><?= Support::e(Giudizi::scontoTesto($acc)) ?>. Il tuo giudizio <b>non è ancora sulla nostra home</b>: decidiamo noi quali mostrare. Puoi modificarlo o ritirarlo da Account &amp; Fatturazione.
          <?php elseif ($programma): ?>Resta tra noi: senza l'adesione al programma non lo pubblichiamo.
          <?php else: ?><?= $fatto['consenso'] ? 'Lo leggiamo e lo pubblichiamo sul sito dopo un controllo. Puoi ritirare il consenso quando vuoi da Account &amp; Fatturazione.' : 'Resta tra noi: senza il tuo consenso non lo pubblichiamo.' ?><?php endif; ?></p>
      <?php endif; ?>
      <div class="row"><a class="btn" href="<?= b() ?>/pannello">Torna alle tue guide</a><a class="btn btn--ghost" href="<?= b() ?>/account#giudizio">Il tuo giudizio</a></div>
    </section>
  <?php else: ?>
  <div class="stack stack--sm">
    <h1><?= $modifica ? 'Modifica il tuo giudizio.' : 'Ti piace MyHouse Welcome?' ?></h1>
    <p class="lead"><?= $aderito && $programma ? Support::e(Giudizi::scontoTesto($acc)) . '. ' : '' ?>Un voto e due righe per un altro host che sta decidendo se provarla. Ci aiuti anche a migliorare.</p>
  </div>
  <?php if ($inHome): ?>
    <p class="note" role="status"><span>Il tuo giudizio è nella nostra home. Grazie! Se vuoi toglierlo, lo trovi in <a href="<?= b() ?>/account#giudizio">Account &amp; Fatturazione</a>.</span></p>
  <?php elseif ($programma && !$pubblicata): ?>
    <p class="note" role="status"><span>Il giudizio si lascia dopo aver pubblicato la guida: torna qui appena è online. <?= $puoAderire ? 'Intanto puoi aderire al programma dal riquadro in «Le mie guide».' : '' ?></span></p>
  <?php else: ?>
  <?php if ($err): ?><p class="note note--err" role="alert"><span><?= Support::e($err) ?></span></p><?php endif; ?>
  <form method="post" action="<?= b() ?>/giudizio" class="panel stack giudizio" novalidate><?= Csrf::field() ?>
    <?php if ($puoAderire): /* 9B · una sola casella: la stessa del pagamento, in breve */ ?>
      <label class="check giudizio-partecipo" style="align-items:flex-start"><input type="checkbox" name="partecipo" value="1" style="margin-top:1px"<?= !empty($vecchi['partecipo']) ? ' checked' : '' ?>>
        <span><b>Partecipo: lascerò un giudizio</b> e accetto che possa essere mostrato in home con nome, copertina, foto e logo, senza link alla mia guida. In cambio ho il <?= $pct ?>% sul prossimo rinnovo, qualunque voto io dia.</span></label>
    <?php endif; ?>
    <fieldset class="stelle-scelta">
      <legend>Il tuo voto <span class="muted">(obbligatorio)</span></legend>
      <div class="stelle-scelta__fila">
        <?php for ($i = 5; $i >= 1; $i--): ?>
          <input type="radio" class="sr-only" name="rating" id="voto-<?= $i ?>" value="<?= $i ?>" <?= $voto === $i ? 'checked' : '' ?> required>
          <label for="voto-<?= $i ?>" title="<?= $i ?> su 5"><?= Icon::svg('star', 34, 1.6) ?><span class="sr-only"><?= $i ?> stell<?= $i === 1 ? 'a' : 'e' ?></span></label>
        <?php endfor; ?>
      </div>
    </fieldset>
    <div class="field" style="margin:0">
      <label for="g-testo">Cosa diresti a un altro host?</label>
      <textarea id="g-testo" name="body" rows="4" minlength="20" maxlength="400" required aria-describedby="g-testo-aiuto" data-conta><?= Support::e((string) ($vecchi['body'] ?? '')) ?></textarea>
      <p class="help" id="g-testo-aiuto">Da 20 a 400 caratteri. Per esempio: cosa ti fa risparmiare, cosa dicono gli ospiti. <span class="muted" data-conta-per="g-testo"></span></p>
    </div>
    <?php if ($programma): $esistenti = array_filter($chip, fn($c) => !empty($c[0])); ?>
      <fieldset class="giudizio-foto">
        <legend>Cosa mostriamo, se il tuo giudizio va in home <span class="muted">(togli quello che non vuoi)</span></legend>
        <?php if ($esistenti): ?>
          <div class="chips">
            <?php foreach ($esistenti as $k => [$mid, $et]): $si = $modifica || array_key_exists('rating', $vecchi) ? !empty($vecchi[$k]) : true; ?>
              <label class="chip"><input type="checkbox" name="<?= $k ?>" value="1"<?= $si ? ' checked' : '' ?>><img src="<?= Support::e(Media::ridotta((int) $mid, 120)) ?>" alt="" width="28" height="28" loading="lazy"><span><?= $et ?></span></label>
            <?php endforeach; ?>
          </div>
        <?php else: ?><p class="small muted" style="margin:0">Nella guida non ci sono ancora copertina, foto o logo: se li aggiungi, potrai sceglierli qui.</p><?php endif; ?>
        <p class="help" style="margin:8px 0 0">Nome senza cognome, struttura e città compaiono sempre. Nessun link alla tua guida. Decidiamo noi quali giudizi mostrare.</p>
      </fieldset>
    <?php else: $fotoScelta = (string) ($vecchi['photo_source'] ?? 'nessuna');
          $anteprime = ['profilo' => $p && $p['profile_media_id'] ? Media::url((int) $p['profile_media_id']) : null, 'copertina' => $p && $p['cover_media_id'] ? Media::url((int) $p['cover_media_id']) : null]; ?>
      <fieldset class="giudizio-foto">
        <legend>La foto accanto al giudizio</legend>
        <div class="giudizio-foto__scelte">
          <?php foreach (Testimonianze::FOTO as $k => $et): if ($k !== 'nessuna' && !$anteprime[$k]) continue; ?>
            <label class="giudizio-foto__scelta">
              <input type="radio" name="photo_source" value="<?= $k ?>" <?= $fotoScelta === $k ? 'checked' : '' ?>>
              <span class="giudizio-foto__img giudizio-foto__img--<?= $k ?>"><?php if ($k !== 'nessuna'): ?><img src="<?= Support::e($anteprime[$k]) ?>" alt="" width="64" height="64" loading="lazy"><?php else: ?><?= Icon::svg('close', 22, 1.8) ?><?php endif; ?></span>
              <span class="small"><?= Support::e($et) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </fieldset>
      <label class="check giudizio-consenso"><input type="checkbox" name="consenso" value="1" <?= !empty($vecchi['consenso']) ? 'checked' : '' ?>>
        <span><?= Support::e(Testimonianze::CONSENSO) ?></span></label>
      <p class="help" style="margin:0">Il consenso serve solo per la pubblicazione: senza, il giudizio arriva soltanto a noi.</p>
    <?php endif; ?>
    <div class="actions"><button class="btn"><?= Icon::svg('message', 16) ?><?= $modifica ? 'Salva il giudizio' : 'Invia il giudizio' ?></button>
      <?php if ($modifica): ?><a class="btn btn--ghost" href="<?= b() ?>/account#giudizio">Annulla</a><?php endif; ?></div>
  </form>
  <script>
  (function () { var t = document.querySelector('[data-conta]'), c = document.querySelector('[data-conta-per]'); if (!t || !c) return;
    var agg = function () { c.textContent = t.value.length + ' / 400'; }; t.addEventListener('input', agg); agg(); })();
  </script>
  <?php endif; ?>
  <?php endif; ?>
</div>
