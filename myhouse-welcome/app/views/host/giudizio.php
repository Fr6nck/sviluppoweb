<?php use function MHW\t, MHW\tf;
/* 9L · Impostazioni → «Giudizio con sconto»: una pagina sola per il programma (fonde la pagina «Giudizio» della 9B).
   Chi ha aderito vede: la data e dove vale lo sconto; «La tua scheda su welcomemyhouse.it» con lo stato della scheda e,
   separato, quello del giudizio (in attesa di approvazione / in home / non in home al momento) e l'Anteprima; «Immagini mostrate» (Copertina / Logo, solo quelli che esistono nella guida); «Il tuo
   giudizio» con la data in cui lo chiediamo e «Lascia il giudizio» (anche prima dei 30 giorni); «Ritirarsi dal programma»
   con la conferma. Chi non ha aderito: la stessa unica casella del pagamento e «Aderisco». Mai la foto dell'host.
   Il modulo del giudizio (5 stelle, «Cosa diresti a un altro host?» 20–400 caratteri) si apre con ?scrivi=1.
   Riceve $acc, $err, $fatto, $vecchi, $p (la struttura), $gia (il giudizio), $scheda, $scrivi. */
use function MHW\b;
use MHW\{Support, Icon, Csrf, Media, Testimonianze, Giudizi, Vetrina};
$title = t('Giudizio con sconto');
$programma = Giudizi::disponibili() && Vetrina::disponibili();
$pct = $programma ? Giudizi::percentuale() : 5;
$aderito = !empty($acc['giudizio_adesione']);
$ritirato = $scheda && !empty($scheda['ritirato_at']);
$pubblicata = Testimonianze::primaPubblicazione((int) $acc['id']) !== null;
$voto = (int) ($vecchi['rating'] ?? 0);
$statoG = $programma && $scheda ? Vetrina::statoGiudizio($scheda, $gia, Vetrina::motivoSospesa($scheda, $p)) : ($gia ? (!empty($gia['withdrawn_at']) ? 'ritirato' : 'da_approvare') : 'in_attesa');
$data = fn(?string $d) => $d ? MHW\Pannello::data($d) : ''; ?>
<div class="stack stack--lg giudizio-pagina" style="max-width:820px">
  <div class="stack stack--sm">
    <h1><?= t('Giudizio con sconto.') ?></h1>
    <?php if ($escluso = Giudizi::motivoEscluso($acc)): /* 9N · mai una pagina vuota: il motivo, e cosa si può fare */ ?>
      <section class="panel stack" role="status" style="gap:8px">
        <p class="lead" style="margin:0"><?= Support::e(tf('Il programma «Giudizio con sconto» non è disponibile per questo account perché {motivo}.', ['{motivo}' => $escluso])) ?></p>
        <p class="small muted" style="margin:0"><?= t('Senza adesione non pubblichiamo nulla: né la scheda della tua guida né un giudizio. Se pensi che sia un errore, scrivici a') ?>
          <a href="mailto:<?= Support::e(Support::contatto()) ?>"><?= Support::e(Support::contatto()) ?></a>.</p>
        <div class="row"><a class="btn btn--ghost btn--sm" href="<?= b() ?>/pannello"><?= t('Torna alle tue guide') ?></a></div>
      </section>
    <?php elseif ($programma && $aderito): ?>
      <p class="lead"><?= Support::e(tf('Hai aderito il {data}', ['{data}' => $data((string) $acc['giudizio_adesione_at'])])) ?><?= ($s = Giudizi::scontoTesto($acc)) !== '' ? ' · ' . Support::e($s) : '' ?>.</p>
    <?php elseif ($programma && Giudizi::attivo()): ?>
      <p class="lead"><?= Support::e(tf('Il {n}% di sconto, e la tua guida tra «Le guide dei nostri host» su welcomemyhouse.it.', ['{n}' => $pct])) ?></p>
    <?php else: ?>
      <p class="lead"><?= t('Ti piace MyHouse Welcome? Raccontacelo: un voto e due righe ci aiutano a migliorare.') ?></p>
    <?php endif; ?>
  </div>

  <?php if ($fatto): ?>
    <section class="panel stack giudizio-grazie" role="status">
      <span class="giudizio-grazie__ico" aria-hidden="true"><?= Icon::svg('star', 26, 1.8) ?></span>
      <h2 style="font-size:26px;margin:0"><?= $fatto['rating'] <= 3 ? t('Grazie.') : t('Grazie! Giudizio ricevuto.') ?></h2>
      <p class="lead" style="margin:0"><?= $fatto['rating'] <= 3 ? t('Ti scriviamo per capire cosa migliorare.') . ' ' : '' ?><?= $programma && !empty($fatto['aderito']) ? t('Lo sconto non dipende dal voto: resta tuo. Se lo pubblichiamo, compare in «Cosa dicono di noi».') : t('Resta tra noi: lo leggiamo per migliorare.') ?></p>
      <div class="row"><a class="btn" href="<?= b() ?>/giudizio"><?= t('Torna a Giudizio con sconto') ?></a><a class="btn btn--ghost" href="<?= b() ?>/pannello"><?= t('Le tue guide') ?></a></div>
    </section>
  <?php endif; ?>

  <?php if ($programma && $aderito && $scheda && !$ritirato):
        $card = Vetrina::perScheda($scheda, $p); $cardG = $gia && $statoG !== 'ritirato' ? Vetrina::perScheda($scheda, $p, $gia, true) : null;
        $motivo = Vetrina::motivoSospesa($scheda, $p); $sc = Vetrina::scelte($scheda);
        $statoS = Vetrina::statoScheda($scheda, $motivo, Vetrina::posizione((int) $scheda['id']));
        $etS = match ($statoS) {
            'in_home' => tf('Scheda approvata il {data} · in home', ['{data}' => $data((string) $scheda['scheda_approvata_at'])]),
            'in_vetrina' => tf('Scheda approvata il {data} · in «Tutte le guide»', ['{data}' => $data((string) $scheda['scheda_approvata_at'])]),
            'da_approvare' => t('Scheda in attesa di approvazione'), default => t('Scheda: non in home al momento') };
        $etG = match ($statoG) { 'pubblicato' => t('Giudizio: in home'), 'da_approvare' => t('Giudizio: in attesa di approvazione'), 'in_attesa' => t('Giudizio: in attesa'), default => t('Giudizio: non in home al momento') };
        $richiesta = Vetrina::dataRichiesta($scheda); ?>
    <section class="panel giudizio-blocchi" aria-label="<?= MHW\Support::e(t('Il programma')) ?>">
      <div class="giudizio-blocco">
        <div class="stack" style="gap:4px">
          <h2 class="giudizio-blocco__titolo"><?= t('La tua scheda su welcomemyhouse.it') ?></h2>
          <p class="small muted" style="margin:0"><?= Support::e($p ? trim($p['name'] . ' · ' . Vetrina::nomeHost((int) $acc['id']) . ($p['city'] !== '' ? ' · ' . Vetrina::dove($p) : '')) : t('La guida non è ancora pubblicata')) ?>. <?= t('Senza link, senza la tua foto.') ?></p>
          <span class="row" style="gap:6px;flex-wrap:wrap"><span class="pillola <?= in_array($statoS, ['in_home', 'in_vetrina'], true) ? 'pillola--pino' : 'pillola--ocra' ?>"><?= Support::e($etS) ?></span>
            <span class="pillola <?= $statoG === 'pubblicato' ? 'pillola--pino' : 'pillola--ocra' ?>"><?= Support::e($etG) ?></span></span>
        </div>
        <?php if ($card): ?>
          <details class="giudizio-anteprima"><summary class="btn btn--ghost btn--sm"><?= t('Anteprima') ?></summary>
            <div class="giudizio-anteprima__scheda dicono"><?php $v = $card; include MHW_APP . '/views/pub/_vetrina_scheda.php'; ?></div>
            <?php if ($cardG): ?><div class="giudizio-anteprima__scheda dicono" style="margin-top:12px"><?php $g = $cardG; include MHW_APP . '/views/pub/_dicono_scheda.php'; ?></div><?php endif; ?></details>
        <?php endif; ?>
      </div>

      <div class="giudizio-blocco">
        <div class="stack" style="gap:6px">
          <h2 class="giudizio-blocco__titolo"><?= t('Immagini mostrate') ?></h2>
          <p class="small muted" style="margin:0"><?= t('Solo queste due. Se le cambi nella guida, cambiano anche lì. Se mancano, al loro posto c\'è il segnaposto «Welcome My House».') ?></p>
          <?php $haCop = $p && !empty($p['cover_media_id']); $haLogo = $p && !empty($p['logo_media_id']); ?>
          <?php if ($haCop || $haLogo): ?>
            <form method="post" action="<?= b() ?>/giudizio/immagini" class="row" style="gap:8px;flex-wrap:wrap;align-items:center"><?= Csrf::field() ?>
              <input type="hidden" name="invia" value="1">
              <?php if ($haCop): ?><label class="chip"><input type="checkbox" name="copertina" value="1"<?= $sc['copertina'] ? ' checked' : '' ?>><img src="<?= Support::e(Media::ridotta((int) $p['cover_media_id'], 120)) ?>" alt="" width="28" height="28" loading="lazy"><span><?= t('Copertina') ?></span></label><?php endif; ?>
              <?php if ($haLogo): ?><label class="chip"><input type="checkbox" name="logo" value="1"<?= $sc['logo'] ? ' checked' : '' ?>><img src="<?= Support::e(Media::ridotta((int) $p['logo_media_id'], 120)) ?>" alt="" width="28" height="28" loading="lazy"><span><?= t('Logo') ?></span></label><?php endif; ?>
              <button class="btn btn--ghost btn--sm"><?= t('Salva') ?></button>
            </form>
          <?php else: ?><p class="small" style="margin:0"><?= t('Nella guida non ci sono ancora copertina e logo: aggiungili in Aspetto.') ?></p><?php endif; ?>
        </div>
      </div>

      <div class="giudizio-blocco">
        <div class="stack" style="gap:4px">
          <h2 class="giudizio-blocco__titolo"><?= t('Il tuo giudizio') ?></h2>
          <?php if ($gia && $statoG !== 'ritirato'): ?>
            <p style="margin:0"><span class="stelline" aria-label="<?= Support::e(tf('{n} su 5', ['{n}' => (int) $gia['rating']])) ?>"><?= str_repeat('★', (int) $gia['rating']) . str_repeat('☆', 5 - (int) $gia['rating']) ?></span> «<?= Support::e($gia['body']) ?>»</p>
            <span class="pillola <?= $statoG === 'pubblicato' ? 'pillola--pino' : 'pillola--ocra' ?>"><?= $statoG === 'pubblicato' ? t('In home, in «Cosa dicono di noi»') : ($statoG === 'da_approvare' ? t('In attesa di approvazione') : t('Non in home al momento')) ?></span>
          <?php else: ?>
            <p class="small muted" style="margin:0"><?= $richiesta ? Support::e(tf('Te lo chiediamo 30 giorni dopo la pubblicazione (il {data}). Puoi lasciarlo anche prima.', ['{data}' => $data($richiesta)])) : t('Lo lasci dopo aver pubblicato la guida: te lo chiediamo 30 giorni dopo.') ?></p>
            <span class="pillola pillola--ocra"><?= t('In attesa') ?></span>
          <?php endif; ?>
        </div>
        <?php if ($pubblicata && !$scrivi): ?><a class="btn btn--sm" href="<?= b() ?>/giudizio?scrivi=1#scrivi"><?= $gia && $statoG !== 'ritirato' ? t('Modifica il giudizio') : t('Lascia il giudizio') ?></a><?php endif; ?>
      </div>

      <div class="giudizio-blocco">
        <div class="stack" style="gap:4px">
          <h2 class="giudizio-blocco__titolo"><?= t('Ritirarsi dal programma') ?></h2>
          <p class="small muted" style="margin:0"><?= t('La scheda e il giudizio spariscono subito. Lo sconto già avuto resta; quello promesso sul rinnovo decade.') ?></p>
        </div>
        <details class="giudizio-ritiro"><summary class="btn btn--ghost btn--sm"><?= t('Ritirati') ?></summary>
          <form method="post" action="<?= b() ?>/giudizio/ritira" class="stack" style="gap:8px;margin-top:10px"><?= Csrf::field() ?>
            <label class="check"><input type="checkbox" name="conferma" value="1" required><span><?= t('Sì, mi ritiro dal programma «Giudizio con sconto»') ?></span></label>
            <button class="btn btn--sm"><?= t('Conferma il ritiro') ?></button></form></details>
      </div>
    </section>
  <?php elseif ($programma && $aderito && $ritirato): ?>
    <p class="note" role="status"><span><?= Support::e(tf('Ti sei ritirato dal programma il {data}: la scheda e il giudizio non si vedono sul sito. Lo sconto già avuto resta.', ['{data}' => $data((string) $scheda['ritirato_at'])])) ?></span></p>
  <?php elseif ($programma && Giudizi::puoAderire($acc)): ?>
    <form method="post" action="<?= b() ?>/giudizio/partecipo" class="panel stack" style="gap:12px"><?= Csrf::field() ?>
      <label class="check giudizio-casella" style="align-items:flex-start"><input type="checkbox" name="partecipo" value="1" required style="margin-top:2px">
        <span><?= Giudizi::casellaHtml() ?></span></label>
      <p class="small muted" style="margin:0"><?= Support::e(tf('Aderendo adesso, il {n}% vale sul prossimo rinnovo.', ['{n}' => $pct])) ?> <?= t('Regole complete nei') ?> <a href="<?= b() ?>/termini#giudizio-sconto" target="_blank" rel="noopener"><?= t('Termini') ?></a><?= t(', punto «Giudizio con sconto».') ?></p>
      <div class="actions"><button class="btn"><?= t('Aderisco') ?></button></div>
    </form>
  <?php endif; ?>

  <?php if ($scrivi || $err): ?>
    <section class="stack" id="scrivi" style="gap:12px">
      <h2 style="font-size:24px;margin:0"><?= $gia && $statoG !== 'ritirato' ? t('Modifica il tuo giudizio') : t('Il tuo giudizio') ?></h2>
      <?php if (!$pubblicata): ?>
        <p class="note" role="status"><span><?= t('Il giudizio si lascia dopo aver pubblicato la guida: torna qui appena è online.') ?></span></p>
      <?php elseif ($ritirato): ?>
        <p class="note" role="status"><span><?= t('Ti sei ritirato dal programma: il giudizio non si può più lasciare.') ?></span></p>
      <?php else: ?>
        <?php if ($err): ?><p class="note note--err" role="alert"><span><?= Support::e(t($err)) ?></span></p><?php endif; ?>
        <form method="post" action="<?= b() ?>/giudizio" class="panel stack giudizio" novalidate><?= Csrf::field() ?>
          <fieldset class="stelle-scelta">
            <legend><?= t('Il tuo voto') ?> <span class="muted"><?= t('(obbligatorio)') ?></span></legend>
            <div class="stelle-scelta__fila">
              <?php for ($i = 5; $i >= 1; $i--): ?>
                <input type="radio" class="sr-only" name="rating" id="voto-<?= $i ?>" value="<?= $i ?>" <?= $voto === $i ? 'checked' : '' ?> required>
                <label for="voto-<?= $i ?>" title="<?= Support::e(tf('{n} su 5', ['{n}' => $i])) ?>"><?= Icon::svg('star', 34, 1.6) ?><span class="sr-only"><?= Support::e($i === 1 ? t('1 stella') : tf('{n} stelle', ['{n}' => $i])) ?></span></label>
              <?php endfor; ?>
            </div>
          </fieldset>
          <div class="field" style="margin:0">
            <label for="g-testo"><?= t('Cosa diresti a un altro host?') ?></label>
            <textarea id="g-testo" name="body" rows="4" minlength="20" maxlength="400" required aria-describedby="g-testo-aiuto" data-conta><?= Support::e((string) ($vecchi['body'] ?? '')) ?></textarea>
            <p class="help" id="g-testo-aiuto"><?= t('Da 20 a 400 caratteri. Per esempio: cosa ti fa risparmiare, cosa dicono gli ospiti.') ?> <span class="muted" data-conta-per="g-testo"></span></p>
          </div>
          <p class="help" style="margin:0"><?= $programma && $aderito ? t('Può comparire in «Cosa dicono di noi» su welcomemyhouse.it, con il nome della struttura, la città e il tuo nome senza cognome, se decidiamo di pubblicarlo. Nessun link alla tua guida, nessuna tua foto. Lo sconto non dipende dal voto né dalla pubblicazione.') : t('Resta tra noi: lo leggiamo per migliorare.') ?></p>
          <div class="actions"><button class="btn"><?= Icon::svg('message', 16) ?><?= $gia && $statoG !== 'ritirato' ? t('Salva il giudizio') : t('Invia il giudizio') ?></button>
            <a class="btn btn--ghost" href="<?= b() ?>/giudizio"><?= t('Annulla') ?></a></div>
        </form>
        <script>
        (function () { var t = document.querySelector('[data-conta]'), c = document.querySelector('[data-conta-per]'); if (!t || !c) return;
          var agg = function () { c.textContent = t.value.length + ' / 400'; }; t.addEventListener('input', agg); agg(); })();
        </script>
      <?php endif; ?>
    </section>
  <?php elseif (!$programma || (!$aderito && !Giudizi::puoAderire($acc))): ?>
    <?php if ($pubblicata): ?><div class="actions"><a class="btn" href="<?= b() ?>/giudizio?scrivi=1#scrivi"><?= t('Lascia il tuo giudizio') ?></a></div><?php endif; ?>
  <?php endif; ?>
</div>
