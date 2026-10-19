<?php
/* Impostazioni della struttura e, in fondo, l'eliminazione. */
use function MHW\b;
use MHW\{Support, Csrf, Icon};
$title = 'Impostazioni — ' . $prop['name']; ?>
<div class="stack stack--lg" style="max-width:720px">
  <div class="stack stack--sm">
    <h1>Impostazioni.</h1>
    <p class="muted small">Link della guida: <?= Support::e(Support::baseUrl()) ?>/g/<?= Support::e($prop['slug']) ?> — resta sempre lo stesso, anche se cambi il nome.</p>
  </div>
  <?php if ($err): ?><p class="note note--err" role="alert"><?= Support::e($err) ?></p><?php endif; ?>
  <?php $dopoPasso = ''; include __DIR__ . '/_struttura_form.php'; ?>

  <?php if (array_key_exists('review_google', $prop)): /* Dopo il soggiorno: dove si trova (8B), prenotazione diretta, firma */
        $firma = MHW\Entitlements::can((int) $acc['id'], 'hide_branding'); ?>
  <form method="post" action="<?= b() ?>/pannello/<?= (int) $prop['id'] ?>/dopo-il-soggiorno" class="stack" id="dopo-il-soggiorno" style="margin-top:24px"><?= Csrf::field() ?>
    <div class="stack stack--sm">
      <h2 style="font-size:24px">Dopo il soggiorno.</h2>
      <p class="muted small">Facoltativo. Nella pagina di saluto, che l'ospite apre da «Stiamo per partire», e in quella del voto compare solo quello che compili.</p>
    </div>
    <?php /* 8B: dove si trova la struttura. L'host spunta i portali; i link li prepara il sistema (Canali). */
          $canali = MHW\Canali::righe((int) $prop['id']);
          $spuntati = array_values(array_filter(array_column($canali, 'kind'), fn($k) => isset(MHW\Canali::NOTI[$k])));
          $ordine = array_merge($spuntati, array_values(array_diff(array_keys(MHW\Canali::NOTI), $spuntati)));
          $altri = array_values(array_filter($canali, fn($c) => isset(MHW\Canali::ALTRI[$c['kind']])));
          $social = array_column(array_filter($canali, fn($c) => MHW\Canali::social($c['kind'])), 'url', 'kind');
          $icoPortale = ['google' => 'search', 'booking' => 'calendar', 'airbnb' => 'home', 'vrbo' => 'home', 'expedia' => 'plane']; ?>
    <fieldset class="fieldset" id="piattaforme">
      <legend>Su quali piattaforme si trova la tua struttura?</legend>
      <p class="help">Spunta i portali dove gli ospiti ti trovano e prenotano. A fine soggiorno, chi ti dà 5 stelle trova i bottoni per lasciarti la recensione: i link li prepariamo noi.</p>
      <h3 class="canali__titolo">Portali</h3>
      <ul class="canali" data-canali role="list">
        <?php foreach ($ordine as $k): $si = in_array($k, $spuntati, true); ?>
          <li class="canale" data-canale-riga>
            <label class="canale__scelta"><input type="checkbox" name="portali[]" value="<?= $k ?>" <?= $si ? 'checked' : '' ?>>
              <span class="canale__ico"><?= Icon::svg($icoPortale[$k], 18) ?></span><span><?= MHW\Canali::NOTI[$k] ?></span></label>
            <span class="canale__sposta" data-solo-js hidden>
              <button type="button" class="canale__freccia" data-sposta="su" aria-label="Sposta <?= MHW\Canali::NOTI[$k] ?> più su"><?= Icon::svg('chevron', 16, 2, 'ruota-su') ?></button>
              <button type="button" class="canale__freccia" data-sposta="giu" aria-label="Sposta <?= MHW\Canali::NOTI[$k] ?> più giù"><?= Icon::svg('chevron', 16, 2, 'ruota-giu') ?></button>
            </span>
          </li>
        <?php endforeach; ?>
      </ul>
      <p class="help" style="margin:0">L'ordine è quello dei bottoni che vede l'ospite. Google apre la tua scheda su Maps cercando il nome e la città della struttura.</p>
      <div class="canali-altri" data-canali-altri>
        <?php $righeAltri = array_merge($altri, [['kind' => '', 'label' => '', 'url' => '']]);
        foreach ($righeAltri as $i => $a): $vuota = $a['kind'] === ''; ?>
          <div class="canale-altro" data-canale-altro<?= $vuota ? ' data-modello' : '' ?>>
            <div class="field" style="margin:0"><label for="altri-<?= $i ?>-kind">Portale</label>
              <select id="altri-<?= $i ?>-kind" name="altri[<?= $i ?>][kind]" data-altro-kind>
                <option value="">Scegli…</option>
                <?php foreach (MHW\Canali::ALTRI as $ak => $an): ?><option value="<?= $ak ?>"<?= $a['kind'] === $ak ? ' selected' : '' ?>><?= Support::e($an) ?></option><?php endforeach; ?>
              </select></div>
            <div class="field" style="margin:0" data-solo-altro><label for="altri-<?= $i ?>-label">Nome del portale</label>
              <input id="altri-<?= $i ?>-label" name="altri[<?= $i ?>][label]" type="text" maxlength="80" value="<?= Support::e($a['label']) ?>"></div>
            <div class="field" style="margin:0"><label for="altri-<?= $i ?>-url">Indirizzo della tua scheda</label>
              <input id="altri-<?= $i ?>-url" name="altri[<?= $i ?>][url]" type="url" maxlength="500" placeholder="https://" value="<?= Support::e($a['url']) ?>"></div>
            <button type="button" class="linkbtn canale-altro__togli" data-togli-altro data-solo-js hidden>Togli</button>
          </div>
        <?php endforeach; ?>
        <button type="button" class="btn btn--ghost btn--sm" data-aggiungi-altro data-solo-js hidden><?= Icon::svg('plus', 15, 2) ?>Aggiungi un altro portale</button>
        <p class="help" style="margin:0">Agoda, Hotels.com, Tripadvisor, Holidu, HomeToGo o un altro: qui l'indirizzo della tua scheda lo scrivi tu (comincia con https://).</p>
      </div>
      <h3 class="canali__titolo">Social <span class="muted">(facoltativi)</span></h3>
      <div class="grid grid-3 canali-social">
        <?php foreach (MHW\Canali::SOCIAL as $sk => $sn): ?>
          <div class="field" style="margin:0"><label for="social-<?= $sk ?>"><?= Icon::svg($sk, 15) ?> <?= $sn ?></label>
            <input id="social-<?= $sk ?>" name="social[<?= $sk ?>]" type="text" maxlength="500" placeholder="@nome o https://…" autocomplete="off" value="<?= Support::e((string) ($social[$sk] ?? '')) ?>"></div>
        <?php endforeach; ?>
      </div>
      <p class="help" style="margin:0">Nella pagina di saluto della guida compaiono come «Seguici».</p>
    </fieldset>
    <fieldset class="fieldset" id="prenotazione-diretta">
      <legend>Prenotazione diretta</legend>
      <p class="help">«Tornerai da queste parti?»: compare all'ospite nella pagina di saluto, con il codice sconto e il link al tuo sito.</p>
      <?php /* 8B: prima la domanda sul sito, poi i campi giusti (mockup 3 di 3). */
            $dUrl = (string) $prop['direct_url']; $dCod = (string) $prop['direct_code']; $dSconto = (string) ($prop['direct_discount'] ?? '');
            $haSito = array_key_exists('has_site', $prop) && $prop['has_site'] !== null ? (int) $prop['has_site'] : ($dUrl !== '' ? 1 : null); ?>
      <div class="field" style="margin:0">
        <span class="label" id="ha-sito-domanda">Hai un sito dove gli ospiti possono prenotare direttamente?</span>
        <div class="pillole-scelta" role="radiogroup" aria-labelledby="ha-sito-domanda">
          <label class="pillola-scelta"><input type="radio" name="has_site" value="1" <?= $haSito === 1 ? 'checked' : '' ?> data-ha-sito> <span>Sì</span></label>
          <label class="pillola-scelta"><input type="radio" name="has_site" value="0" <?= $haSito === 0 ? 'checked' : '' ?> data-ha-sito> <span>No</span></label>
        </div>
      </div>
      <div class="stack" style="gap:12px" data-sito-si<?= $haSito === 0 ? ' hidden' : '' ?>>
      <?php /* 9F · da 900 px in su due colonne 6/6 allineate in alto, campi a tutta larghezza; «Codice sconto» e «Sconto» sulla
               stessa riga (3/2) con l'aiuto del codice sotto tutti e due; l'anteprima alta quanto i campi. Su telefono in colonna. */ ?>
      <div class="diretta">
        <div class="stack diretta__campi" style="gap:12px">
          <div class="field" style="margin:0"><label for="direct_url">Link al sito per prenotare</label>
            <input id="direct_url" name="direct_url" type="url" maxlength="500" placeholder="https://" value="<?= Support::e($dUrl) ?>" data-diretta="url"></div>
          <div class="diretta__codice">
            <div class="field" style="margin:0"><label for="direct_code">Codice sconto <span class="muted">(facoltativo)</span></label>
              <input id="direct_code" aria-describedby="direct-code-aiuto" name="direct_code" type="text" maxlength="60" autocomplete="off" value="<?= Support::e($dCod) ?>" data-diretta="codice"></div>
            <div class="field" style="margin:0"><label for="direct_discount">Sconto <span class="muted">(facoltativo)</span></label>
              <input id="direct_discount" name="direct_discount" type="text" maxlength="40" autocomplete="off" placeholder="10% o 15 €" value="<?= Support::e($dSconto) ?>" data-diretta="sconto"></div>
          </div>
          <p class="help" id="direct-code-aiuto" style="margin:-4px 0 0">Il codice per chi torna lo crei tu sul tuo sito o sul tuo motore di prenotazione; qui lo scrivi perché l'ospite lo veda quando parte.</p>
          <p class="note" role="status" data-diretta-avviso<?= $dCod !== '' && $dUrl === '' ? '' : ' hidden' ?>>Senza il link al tuo sito l'ospite non sa dove usarlo.</p>
        </div>
        <?php /* L'anteprima della scheda che vede l'ospite nel commiato (in italiano). */ ?>
        <div class="diretta__anteprima" aria-label="Anteprima: come la vede l'ospite">
          <span class="tiny muted">Anteprima</span>
          <b><?= Support::e(MHW\I18n::t('it', 'direct_title')) ?></b>
          <span class="small"><?= Support::e(MHW\I18n::t('it', 'direct_lead')) ?></span>
          <span data-diretta-offerta<?= $dCod === '' ? ' hidden' : '' ?>><?= Support::e(($dSconto !== '' ? '−' . ltrim($dSconto, '-−') . ' con il codice ' : 'Con il codice ')) ?><b data-diretta-codice><?= Support::e($dCod) ?></b></span>
          <span class="diretta__bottoni"><span class="diretta__finto" data-diretta-copia<?= $dCod === '' ? ' hidden' : '' ?>>Copia il codice</span><span class="diretta__finto diretta__finto--pieno">Prenota sul nostro sito</span></span>
        </div>
      </div>
      <div class="striscia-myhouse">
        <img src="<?= Support::e(MHW\av('/assets/myhouse/myhouse-logo-nero-breve.png')) ?>" alt="MyHouse" width="114" height="26">
        <span>Vuoi un sito più bello, più visibile su Google o con la prenotazione diretta? Lo miglioriamo noi.</span>
        <button type="button" class="linkbtn striscia-myhouse__vai" data-funnel-apri="si" data-funnel-dialogo="dlg-sito">Scopri come <?= Icon::svg('external', 14, 2) ?></button>
      </div>
      </div>
      <div class="striscia-myhouse striscia-myhouse--crea" data-sito-no<?= $haSito === 0 ? '' : ' hidden' ?>>
        <img src="<?= Support::e(MHW\av('/assets/myhouse/myhouse-logo-nero-breve.png')) ?>" alt="MyHouse" width="114" height="26">
        <span><b>Crealo con noi.</b> Un sito tuo, con la prenotazione diretta: meno commissioni ai portali e ospiti che tornano da te.</span>
        <?php $waNumero = preg_replace('/\D/', '', (string) ((MHW\Config::get('legal') ?? [])['contact_whatsapp'] ?? ''));
              $msgSito = 'Ciao, ho una guida MyHouse Welcome per ' . $prop['name'] . '. Non ho ancora un sito per la mia struttura e vorrei crearlo. Mi date una proposta?'; ?>
        <span class="row striscia-myhouse__bottoni">
          <button type="button" class="btn btn--sm" data-funnel-apri="no" data-funnel-dialogo="dlg-sito">Voglio una proposta</button>
          <?php if ($waNumero !== ''): ?><a class="btn btn--ghost btn--sm" href="https://wa.me/<?= Support::e($waNumero) ?>?text=<?= rawurlencode($msgSito) ?>" target="_blank" rel="noopener"><?= Icon::svg('whatsapp', 15) ?>WhatsApp</a><?php endif; ?>
          <?php if (Support::contatto() !== ''): ?><a class="btn btn--ghost btn--sm" href="<?= Support::e(Support::mailto('Il sito per la mia struttura', $msgSito)) ?>"><?= Icon::svg('message', 15) ?>Email</a><?php endif; ?>
        </span>
      </div>
    </fieldset>
    <fieldset class="fieldset">
      <legend>Firma nella guida</legend>
      <p class="help">In fondo alla guida: «Guida creata con MyHouse Welcome · Crea la tua».</p>
      <?php if ($firma): ?>
        <label class="check"><input type="checkbox" name="hide_branding" value="1" <?= (int) $prop['hide_branding'] === 1 ? 'checked' : '' ?>> <span>Nascondi la firma</span></label>
      <?php else: ?>
        <?php /* 8B: la casella c'è, con il lucchetto: apre «Nascondere la firma è compreso in Plus» */ ?>
        <label class="check"><input type="checkbox" value="1" data-sblocca="firma" aria-describedby="firma-plus"> <span>Nascondi la firma <?= Icon::svg('lock', 13, 2) ?></span></label>
        <p class="small" id="firma-plus" style="margin:0">La firma resta visibile con il tuo piano. Con Plus e Portfolio puoi nasconderla. <a href="<?= b() ?>/piano?passa=plus" data-sblocca="firma">Vedi i piani</a></p>
      <?php endif; ?>
    </fieldset>
    <div class="actions"><button class="btn">Salva</button></div>
  </form>
  <?php /* 8B: il percorso «Anche il tuo sito» in una finestra, già al passo giusto. */ ?>
  <dialog class="funnel-dialogo" id="dlg-sito" aria-label="Il sito per la tua struttura">
    <button type="button" class="funnel-dialogo__chiudi" data-funnel-chiudi aria-label="Chiudi"><?= Icon::svg('close', 18, 2) ?></button>
    <?php $funnel = ['da' => 'impostazioni', 'struttura' => (string) $prop['name'], 'compatta' => true, 'id' => 'fs-imp']; include __DIR__ . '/../pub/_funnel_sito.php'; ?>
  </dialog>
  <script src="<?= MHW\av('/assets/funnel-sito.js') ?>" defer></script>
  <?php endif; ?>

  <?php /* 9L · «Giudizio con sconto»: la voce del programma (una per account), con lo stato in una riga. */
        $accG = MHW\Auth::account();
        if ($accG && MHW\Vetrina::disponibili() && !MHW\Giudizi::escluso($accG) && (!empty($accG['giudizio_adesione']) || MHW\Giudizi::attivo())):
          $schG = MHW\Vetrina::scheda((int) $accG['id']); ?>
  <section class="fieldset row" style="margin-top:24px;justify-content:space-between;align-items:center;gap:12px" id="giudizio-con-sconto">
    <div class="stack" style="gap:2px"><b>Giudizio con sconto</b>
      <span class="small muted"><?= empty($accG['giudizio_adesione']) ? 'Il ' . MHW\Giudizi::percentuale() . '% di sconto e la tua guida tra «Le guide dei nostri host».'
          : ($schG && !empty($schG['ritirato_at']) ? 'Ti sei ritirato dal programma.' : 'Hai aderito il ' . Support::e(Support::date((string) $accG['giudizio_adesione_at'])) . '.') ?></span></div>
    <a class="btn btn--ghost btn--sm" href="<?= b() ?>/giudizio">Apri</a>
  </section>
  <?php endif; ?>

  <details class="fieldset" style="margin-top:24px" id="link-guida">
    <summary class="legend" style="cursor:pointer;min-height:32px">Cambia il link della guida</summary>
    <form method="post" action="<?= b() ?>/pannello/<?= (int) $prop['id'] ?>/link" class="stack" style="margin-top:12px"><?= Csrf::field() ?>
      <p class="small">Il link di adesso è <b><?= Support::e(Support::baseUrl() . '/g/' . $prop['slug']) ?></b>. Se lo hai mandato a chi non doveva averlo,
        crea un link nuovo: si forma dal nome della struttura e da quattro caratteri casuali.</p>
      <p class="note" role="note">Il link vecchio smette di funzionare. Il QR stampato no: continua ad aprire la guida.</p>
      <label class="check"><input type="checkbox" name="conferma" value="1" required> <span>Ho capito: il link vecchio non funzionerà più</span></label>
      <div class="actions"><button class="btn btn--ghost">Crea un link nuovo</button></div>
    </form>
  </details>

  <details class="fieldset" style="margin-top:24px">
    <summary class="legend" style="cursor:pointer;min-height:32px">Elimina la struttura</summary>
    <form method="post" action="<?= b() ?>/pannello/<?= (int) $prop['id'] ?>/elimina" class="stack" style="margin-top:12px"><?= Csrf::field() ?>
      <p class="small">Si cancellano la guida, le traduzioni, le foto, i PDF e il QR. Chi ha già stampato il QR troverà una pagina vuota.
        Non si può annullare. L'abbonamento non si disdice da qui: lo gestisci in <a href="<?= b() ?>/account">Account &amp; Fatturazione</a>.</p>
      <div class="field" style="margin:0"><label for="conferma">Per confermare scrivi <b><?= Support::e($prop['name']) ?></b></label>
        <input type="text" id="conferma" name="conferma" required autocomplete="off" autocapitalize="none" spellcheck="false"></div>
      <div class="actions"><button class="btn btn--danger">Elimina per sempre</button></div>
    </form>
  </details>
</div>
