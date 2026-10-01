<?php
/* La landing. Ogni blocco ha un compito solo:
     hero        che cos'è — e il prodotto si vede subito, sul telefono
     prodotto    cosa trova l'ospite
     il tempo    perché conviene al proprietario
     chi         chi c'è dietro
     guadagno    la guida che porta prenotazioni dirette e recensioni
     come        quanto è semplice cominciare (con le schermate vere del pannello)
     QR          come si condivide
     voci        le testimonianze, solo se l'amministratore ne ha inserite di vere
     domande     le FAQ
     piani       scegliere
     chiusura    cominciare
   Prezzi, nomi ed elenchi dei piani arrivano dal database (li cambia
   l'amministratore): nessun prezzo è scritto qui o nello script. Nessuna
   testimonianza e nessun numero inventato: la demo è dichiarata come demo, le
   domande sono esempi. */
use function MHW\{a, b};
use MHW\{Support, Icon, Plans};
$title = 'MyHouse Welcome — la guida digitale della tua struttura';
$dentro = !empty($user);
$vai = fn(int $pv) => b() . ($dentro ? '/piano?piano=' : '/registrati?piano=') . $pv;
$crea = b() . ($dentro ? '/pannello' : '/registrati');
$demoUrl = $demo ? b() . '/g/' . Support::e($demo['slug']) . '/benvenuto' : null;
$nomeDemo = $demo['name'] ?? 'Casa Lucia';
$fotoJpg = a('/assets/foto/borgo.jpg');
$fotoSet = a('/assets/foto/borgo-1200.webp') . ' 1200w, ' . a('/assets/foto/borgo-2000.webp') . ' 2000w';
$telefonoJpg = a('/assets/foto/borgo-telefono.jpg');
$telefonoWebp = a('/assets/foto/borgo-telefono-600.webp');
// Il prezzo di partenza, dal listino: se l'amministratore lo cambia, cambia anche qui.
$partenza = null;
foreach ($offers as $of) foreach ($of['options'] as $o) {
    if ($partenza === null || (int) $o['price_cents'] < (int) $partenza['price_cents']) $partenza = $o;
}
// Le icone vengono dal catalogo delle sezioni: le stesse del pannello e della guida.
$elementi = array_map(fn($x) => [MHW\SectionCatalog::icon($x[0]), $x[1]],
    [['checkin', 'Check-in & Check-out'], ['wifi', 'Wi-Fi'], ['arrival', 'Come arrivare'], ['parking', 'Parcheggio'],
     ['services', 'Servizi'], ['rules', 'Regole della casa'], ['eat', 'Dove mangiare'], ['waste', 'Rifiuti e raccolta differenziata']]); ?>

<section class="hero2">
  <div class="hero2__testo">
    <span class="kicker">La reception digitale per la tua struttura ricettiva</span>
    <h1 class="display">La casa risponde<br>prima che chiedano.</h1>
    <p class="hero2__sub">La guida digitale per case vacanza, B&amp;B, affittacamere e agriturismi. Check-in, Wi-Fi,
      parcheggio, regole e consigli locali in un unico link, da condividere anche tramite QR Code.</p>
    <div class="row">
      <a class="btn btn--lg btn--go" href="<?= $crea ?>">Crea gratis la tua guida <span class="go"><?= Icon::svg('arrow', 19, 2) ?></span></a>
      <?php if ($demoUrl): ?><a class="btn btn--lg btn--ghost" href="<?= $demoUrl ?>">Guarda la demo</a>
        <?php /* La demo nella lingua dell'ospite: le lingue che la demo ha davvero, al massimo tre. */
              $lingueDemo = array_values(array_intersect(['it', 'en', 'de'], array_column(MHW\Db::all('SELECT locale FROM property_locales WHERE property_id = ?', [$demo['id']]), 'locale')));
              $nomiLingue = ['it' => 'italiano', 'en' => 'inglese', 'de' => 'tedesco']; ?>
        <?php if (count($lingueDemo) > 1): ?>
          <span class="demo-lingue" role="group" aria-label="Lingua della demo">
            <?php foreach ($lingueDemo as $l): ?><a href="<?= $demoUrl ?>?l=<?= $l ?>" hreflang="<?= $l ?>" aria-label="Demo in <?= $nomiLingue[$l] ?>"><?= strtoupper($l) ?></a><?php endforeach; ?>
          </span>
        <?php endif; ?>
      <?php endif; ?>
    </div>
    <p class="micro micro--left"><span>Nessuna app</span><span>Anteprima gratuita</span><span>Paghi solo quando pubblichi</span></p>
  </div>

  <?php /* Il telefono: una schermata della guida disegnata in HTML, sempre nel
     tema chiaro della guida. Se c'è la demo, tutto il telefono la apre. */
  $tag = $demoUrl ? 'a' : 'div'; ?>
  <<?= $tag ?> class="device-link"<?= $demoUrl ? ' href="' . $demoUrl . '" aria-label="Apri la demo di ' . Support::e($nomeDemo) . ': scopri come la vedranno i tuoi ospiti"' : '' ?>>
    <span class="device"<?= $demoUrl ? '' : ' role="img" aria-label="La guida di ' . Support::e($nomeDemo) . ' sullo schermo di uno smartphone"' ?>>
      <span class="device__screen" aria-hidden="true">
        <span class="device__status"><span>9:41</span><span class="device__island"></span>
          <span class="device__icons"><i class="sig"></i><i class="bat"></i></span></span>
        <span class="device__app">
          <span class="device__top"><?= Icon::brand(24) ?><span><?= Support::e($nomeDemo) ?></span>
            <?php if ($demo): ?><span class="demo-tag">Demo</span><?php endif; ?></span>
          <span class="device__title">Benvenuti<br>a <?= Support::e($nomeDemo) ?>.</span>
          <span class="device__shot"><picture><source srcset="<?= Support::e($telefonoWebp) ?>" type="image/webp">
            <img src="<?= Support::e($telefonoJpg) ?>" alt="" width="600" height="422" loading="eager" decoding="async"></picture>
            <span class="device__pill"><i></i>Check-in dalle 15:00</span></span>
          <span class="device__tiles">
            <span class="t-terracotta"><?= Icon::svg('home', 16, 1.8) ?>Check-in &amp; Check-out</span>
            <span class="t-sea"><?= Icon::svg('wifi', 16, 1.8) ?>Wi-Fi</span>
            <span class="t-pine"><?= Icon::svg('fork', 16, 1.8) ?>Dove mangiare</span>
            <span class="t-ochre"><?= Icon::svg(MHW\SectionCatalog::icon('parking'), 16, 1.8) ?>Parcheggio</span>
          </span>
        </span>
        <span class="device__home"></span>
      </span>
    </span>
    <?php if ($demoUrl): ?><span class="device-link__invito">Scopri come la vedranno i tuoi ospiti <?= Icon::svg('arrow', 15, 2) ?></span><?php endif; ?>
  </<?= $tag ?>>
</section>

<?php /* Tre scene al posto della foto grande: il QR all'ingresso, l'ospite, l'host.
   Le foto si caricano in assets/foto/ con questi nomi; se una manca, la card
   mostra un riquadro colorato con l'icona, senza errori. */
$cartellaFoto = (defined('MHW_PUBLIC') ? MHW_PUBLIC : dirname(__DIR__, 2) . '/public') . '/assets/foto/'; ?>
<section class="scene" aria-label="Come si usa">
  <?php foreach ([['scena-qr.jpg', 'qr', 't-terracotta', 'Il QR all\'ingresso', 'Lo stampi una volta: l\'ospite lo inquadra e la guida si apre.'],
                  ['scena-ospite.jpg', 'phone', 't-sea', 'L\'ospite trova tutto', 'Wi-Fi, check-in, consigli: nella sua lingua, sul suo telefono.'],
                  ['scena-host.jpg', 'home', 't-pine', 'Tu aggiorni quando vuoi', 'Cambi un orario dal pannello e pubblichi: il QR resta lo stesso.']] as [$file, $ico, $tono, $tit, $txt]):
        $cie = is_file($cartellaFoto . $file); ?>
    <figure class="scena">
      <?php if ($cie): ?><img src="<?= Support::e(a('/assets/foto/' . $file)) ?>" alt="" loading="lazy" decoding="async" width="800" height="600">
      <?php else: ?><span class="scena__vuota <?= $tono ?>" aria-hidden="true"><?= Icon::svg($ico, 40, 1.6) ?></span><?php endif; ?>
      <figcaption><b><?= $tit ?></b><span><?= $txt ?></span></figcaption>
    </figure>
  <?php endforeach; ?>
</section>

<section class="prodotto" aria-labelledby="prodotto-titolo">
  <div class="prodotto__testa">
    <span class="kicker">La guida</span>
    <h2 id="prodotto-titolo" class="h-sezione">Cosa trova l'ospite.</h2>
    <p class="muted">Le informazioni del soggiorno, in ordine e sempre sul telefono. Le sezioni le scegli tu, in base al piano.</p>
  </div>
  <div class="features8">
    <?php foreach ($elementi as [$ico, $nome]): ?>
      <div class="feat"><span class="ico"><?= Icon::svg($ico, 22, 1.7) ?></span><b><?= Support::e($nome) ?></b></div>
    <?php endforeach; ?>
  </div>
</section>

<?php /* Il tempo che non vedi: il problema, le domande, la soluzione, due
   vantaggi, il valore dell'anno. Le domande sono esempi; nessun numero. */ ?>
<section id="il-tempo" class="tempo" aria-labelledby="tempo-titolo">
  <div class="tempo__intro">
    <span class="kicker">Il tempo che non vedi</span>
    <h2 id="tempo-titolo" class="tempo__titolo">Ogni ospite è nuovo.<br>Le domande sono quasi sempre le stesse.</h2>
    <p class="lead">Parcheggio, Wi-Fi, orari, regole della casa. Ogni richiesta richiede poco tempo, ma prova a pensare
      quante volte ripeti le stesse informazioni durante una stagione.</p>
  </div>

  <div class="tempo__grid">
    <figure class="tempo__msgs">
      <ul class="msgs" aria-label="Domande che arrivano spesso">
        <?php foreach ([['car', 'Dove possiamo parcheggiare?'], ['wifi', "Qual è la password del Wi\u{2011}Fi?"],
                        ['clock', 'A che ora dobbiamo lasciare la camera?']] as [$ico, $testo]): ?>
          <li class="msg">
            <span class="msg__ico"><?= Icon::svg($ico, 18, 1.6) ?></span>
            <span class="msg__corpo"><span class="msg__chi">Ospite</span><?= Support::e($testo) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
      <figcaption class="tiny muted">Esempi di domande ricorrenti.</figcaption>
    </figure>

    <div class="tempo__svolta">
      <h3 class="tempo__h3">Le risposte sono già nella tua guida.</h3>
      <p>Con MyHouse Welcome raccogli le informazioni della tua struttura in un unico posto. Le condividi prima
        dell'arrivo e gli ospiti possono consultarle durante il soggiorno, quando ne hanno bisogno.</p>
    </div>
  </div>

  <div class="vantaggi">
    <div class="vantaggio">
      <span class="vantaggio__ico"><?= Icon::svg('clock', 20, 1.7) ?></span>
      <b>Più tempo per te.</b>
      <p>Meno spiegazioni da ripetere a ogni nuovo soggiorno.</p>
    </div>
    <div class="vantaggio">
      <span class="vantaggio__ico"><?= Icon::svg('check', 20, 2) ?></span>
      <b>Meno incomprensioni.</b>
      <p>Indicazioni chiare su orari, regole e informazioni utili possono aiutare a prevenire dubbi e fraintendimenti.</p>
    </div>
  </div>

  <div class="chiarezza">
    <p class="chiarezza__frase">Meno dubbi all'arrivo.<br>Meno equivoci alla partenza.</p>
    <p class="muted">Quando le informazioni sono chiare e consultabili, diventa più semplice gestire orari, regole e
      indicazioni per il soggiorno.</p>
  </div>

  <div class="tempo__valore">
    <span class="kicker">Il valore dell'abbonamento</span>
    <h3 class="tempo__h3">Un piccolo investimento annuale, utile soggiorno dopo soggiorno.</h3>
    <p>Organizzi le risposte una volta, le aggiorni quando serve e le rendi disponibili a ogni nuovo ospite. Anche pochi
      minuti recuperati a ogni soggiorno, nel corso dell'anno, possono fare la differenza.</p>
    <p class="tempo__frase">Il valore non è soltanto nella guida. È nel tempo che puoi dedicare ad altro.</p>
    <?php if ($partenza): ?>
      <p class="tempo__prezzo">Da <?= Support::e(Support::money((int) $partenza['price_cents'], $partenza['currency'])) ?> + IVA all'anno.
        <a href="#piani">Vedi i piani</a></p>
    <?php endif; ?>
  </div>
</section>

<?php /* La guida che fa guadagnare: recensioni, prenotazione diretta, servizi extra. */ ?>
<section id="guadagno" class="blocco guadagno" aria-labelledby="guadagno-titolo">
  <div class="stack stack--sm" style="max-width:640px">
    <span class="kicker">La guida che lavora per te</span>
    <h2 id="guadagno-titolo" class="h-sezione">Una prenotazione diretta in più all'anno paga l'abbonamento.</h2>
    <p class="lead">Alla fine del soggiorno la guida saluta l'ospite e gli lascia due inviti: una recensione dove preferisci, e la
      prossima volta la prenotazione sul tuo sito, con il tuo codice sconto. Senza commissioni.</p>
  </div>
  <div class="grid grid-3 guadagno__voci">
    <div class="vantaggio"><span class="vantaggio__ico"><?= Icon::svg('message', 20, 1.7) ?></span><b>Più recensioni.</b>
      <p>Google, Booking, Airbnb: un pulsante per ognuno, quando il ricordo è fresco.</p></div>
    <div class="vantaggio"><span class="vantaggio__ico"><?= Icon::svg('globe', 20, 1.7) ?></span><b>Prenotazioni dirette.</b>
      <p>«La prossima volta prenota da noi», con il link al tuo sito e il codice sconto.</p></div>
    <div class="vantaggio"><span class="vantaggio__ico"><?= Icon::svg('euro', 20, 1.7) ?></span><b>Servizi extra.</b>
      <p>Transfer, colazione, late check-out: l'ospite li chiede con un tocco su WhatsApp.</p></div>
  </div>
</section>

<aside class="chi" aria-label="Chi c'è dietro MyHouse Welcome">
  <p class="chi__titolo">Pensata per chi ospita. Sviluppata da chi lavora nel digitale e nell'ospitalità.</p>
  <p class="muted">MyHouse Welcome fa parte delle soluzioni MyHouse di
    <a href="https://blackout.in" rel="noopener" target="_blank">Blackout Agency</a>, dedicate alle esigenze digitali delle strutture ricettive.</p>
</aside>

<section id="come-funziona" class="blocco" aria-labelledby="come-titolo">
  <div class="stack stack--sm" style="margin-bottom:28px">
    <span class="kicker">Come funziona</span>
    <h2 id="come-titolo" class="h-sezione">Inizia in pochi minuti.</h2>
  </div>
  <div class="howto">
    <?php /* Le schermate vere del pannello (assets/foto/pannello-1…3.webp); se mancano, resta il numero. */
    foreach ([['01', 'Crea la tua guida.', 'Inserisci le informazioni della struttura e scegli cosa condividere con gli ospiti.', 'Il pannello: i passi della procedura e il modulo della struttura'],
              ['02', 'Personalizza e guarda l\'anteprima.', 'Vedi come apparirà la guida sullo smartphone, prima di pubblicarla.', 'Il pannello: le sezioni della guida con l\'anteprima'],
              ['03', 'Pubblica e condividi.', 'Attiva il piano e condividi la guida tramite link o QR Code.', 'Il pannello: il QR Code da stampare e il link da condividere']] as $i => [$n, $tit, $txt, $alt]):
          $shot = 'pannello-' . ($i + 1) . '.webp'; ?>
      <div class="step-card">
        <?php if (is_file($cartellaFoto . $shot)): ?>
          <img class="step-card__shot" src="<?= Support::e(a('/assets/foto/' . $shot)) ?>" alt="<?= Support::e($alt) ?>" loading="lazy" decoding="async" width="720" height="450">
          <span class="step-card__n"><?= $n ?></span>
        <?php else: ?><span class="big"><?= $n ?></span><?php endif; ?>
        <b><?= $tit ?></b>
        <p class="muted"><?= $txt ?></p></div>
    <?php endforeach; ?>
  </div>
</section>

<section id="qr" class="band qrband" aria-labelledby="qr-titolo">
  <div class="stack" style="gap:20px;max-width:540px">
    <h2 id="qr-titolo" class="h-sezione">Un QR. Tutta la struttura.</h2>
    <ul class="spunte">
      <li><?= Icon::svg('check', 18, 2) ?><span><b>Un solo QR Code, sempre valido.</b>Lo stampi una volta e continui a utilizzarlo.</span></li>
      <li><?= Icon::svg('check', 18, 2) ?><span><b>Informazioni sempre aggiornabili.</b>Modifichi la guida e pubblichi la nuova versione senza cambiare il QR.</span></li>
      <li><?= Icon::svg('check', 18, 2) ?><span><b>Condividi anche prima dell'arrivo.</b>Invia il link via WhatsApp o email.</span></li>
    </ul>
  </div>
  <div class="qr-sheet" style="width:220px">
    <?php /* Se c'è la demo, il QR la apre davvero: provalo col telefono. */ ?>
    <?= preg_replace('/width="\d+" height="\d+"/', 'width="160" height="160"',
                     MHW\QrExport::svg($demo ? Support::baseUrl() . '/g/' . $demo['slug'] . '/benvenuto' : Support::baseUrl())) ?>
    <p class="small" style="margin-top:10px;color:#231b12"><?= $demo ? 'Inquadra e prova la demo.' : 'Inquadra per la guida' ?></p>
  </div>
</section>

<?php if (!empty($testimonianze)): /* solo testimonianze vere, inserite dall'amministratore */ ?>
<section class="blocco voci" aria-labelledby="voci-titolo">
  <div class="stack stack--sm"><span class="kicker">Chi la usa</span><h2 id="voci-titolo" class="h-sezione">Le parole di chi ospita.</h2></div>
  <div class="grid grid-3" style="margin-top:24px">
    <?php foreach ($testimonianze as $t): ?>
      <figure class="panel voce">
        <blockquote><p><?= Support::e($t['body']) ?></p></blockquote>
        <figcaption>
          <?php if ($t['foto']): ?><img src="<?= Support::e($t['foto']) ?>" alt="" width="44" height="44" loading="lazy"><?php endif; ?>
          <span><b><?= Support::e($t['name']) ?></b><?php if ($t['property_name'] !== ''): ?><span class="small muted"><?= Support::e($t['property_name']) ?></span><?php endif; ?></span>
        </figcaption>
      </figure>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<section id="domande" class="blocco faq" aria-labelledby="faq-titolo">
  <div class="stack stack--sm"><span class="kicker">Domande</span><h2 id="faq-titolo" class="h-sezione">Prima di cominciare.</h2></div>
  <div class="faq__lista">
    <?php foreach ([
        ['Serve un\'app?', 'No. La guida si apre nel browser del telefono, da un link o dal QR Code. Gli ospiti non scaricano niente, e nemmeno tu: il pannello funziona dal computer e dal telefono.'],
        ['Cosa succede se non rinnovo?', 'Alla fine del periodo pagato la guida va offline da sola. Niente si cancella: testi, foto e QR restano salvati, e il QR stampato torna a funzionare appena rinnovi.'],
        ['Posso cambiare i testi dopo aver stampato il QR?', 'Sì, quando vuoi. Il QR Code è permanente: modifichi la guida, pubblichi la nuova versione e chi inquadra il QR stampato vede già quella.'],
        ['Ricevo fattura?', 'Sì. Prima del primo pagamento inserisci una volta i dati di fatturazione (partita IVA o codice fiscale, codice destinatario SDI o PEC). I documenti di pagamento li trovi in Account & Fatturazione.'],
        ['Posso disdire?', 'Sì. Disattivi il rinnovo automatico da Account & Fatturazione quando vuoi: la guida resta online fino alla fine del periodo già pagato, poi si ferma. Nessun vincolo.'],
        ['Gli ospiti vengono tracciati?', 'No. La guida non usa cookie e non compare nei motori di ricerca. Le statistiche di lettura contano solo aperture anonime: nessun indirizzo IP, nessun profilo.'],
    ] as $i => [$d, $r]): ?>
      <details class="faq__voce"<?= $i === 0 ? ' open' : '' ?>>
        <summary><?= Support::e($d) ?><?= Icon::svg('plus', 18, 2, 'faq__segno') ?></summary>
        <p><?= Support::e($r) ?></p>
      </details>
    <?php endforeach; ?>
  </div>
</section>

<section id="piani" class="blocco" aria-labelledby="piani-titolo">
  <div class="spread">
    <div class="stack stack--sm">
      <span class="kicker">Piani</span>
      <h2 id="piani-titolo" class="h-sezione">Scegli il piano.</h2>
    </div>
    <p class="muted" style="max-width:360px;line-height:24px">Crei e provi la guida gratis. Prezzi IVA esclusa.</p>
  </div>

  <div class="grid grid-3 piani" style="margin-top:28px">
    <?php foreach ($offers as $of): $p = $of['main']; $famiglia = count($of['options']) > 1;
          $scuro = $p['badge'] !== '';
          $nome = $famiglia ? preg_replace('/\s*\d+$/', '', $p['name']) : $p['name'];
          $primo = $of['options'][0];
          $idSel = 'strutture-' . Support::slug($nome); ?>
      <div class="plan <?= $scuro ? 'plan--dark' : '' ?>">
        <div class="spread spread--mid" style="gap:12px;align-items:center">
          <span class="plan__nome"><?= Support::e($nome) ?></span>
          <?php if ($p['badge'] !== ''): ?><span class="badge badge--ochre-strong"><?= Support::e($p['badge']) ?></span><?php endif; ?>
        </div>
        <span class="plan__headline"><?= Support::e($p['headline']) ?></span>
        <?php if (trim((string) $p['description']) !== ''): ?><p class="plan__desc"><?= Support::e($p['description']) ?></p><?php endif; ?>

        <?php if (Plans::perProperty($primo)): /* Portfolio a quantità: un modulo vero. Senza JavaScript il
                  bottone manda comunque la scelta; lo script calcola solo il totale mostrato,
                  con base e costo aggiuntivo presi dal listino. */
              $minimo = (int) $primo['min_quantity']; ?>
          <form class="plan__scelta" method="get" action="<?= b() . ($dentro ? '/piano' : '/registrati') ?>" data-portfolio
                data-quantita data-base="<?= (int) $primo['price_cents'] ?>" data-extra="<?= (int) $primo['extra_price_cents'] ?>" data-valuta="<?= Support::e($primo['currency']) ?>">
            <input type="hidden" name="piano" value="<?= (int) $primo['pv_id'] ?>">
            <label for="<?= $idSel ?>" class="plan__label">Quante strutture vuoi gestire?</label>
            <input id="<?= $idSel ?>" name="strutture" type="number" inputmode="numeric" step="1" required
                   min="<?= $minimo ?>" max="<?= (int) $primo['max_quantity'] ?>" value="<?= $minimo ?>">
            <p class="plan__regola">Prima struttura <?= Support::e(Support::money((int) $primo['price_cents'], $primo['currency'])) ?>/anno.
              Ogni struttura aggiuntiva +<?= Support::e(Support::money((int) $primo['extra_price_cents'], $primo['currency'])) ?>/anno.</p>
            <span class="price" aria-live="polite"><span data-totale><?= Support::e(Support::money(Plans::price($primo, $minimo), $primo['currency'])) ?></span><small> + IVA / anno</small></span>
            <span class="plan__mese">circa <span data-mensile><?= Support::e(Support::money(Plans::monthly(Plans::price($primo, $minimo)), $primo['currency'])) ?></span> al mese</span>
            <?php $voci = $p['bullet_list']; include __DIR__ . '/_voci_piano.php'; ?>
            <button class="btn <?= $scuro ? '' : 'btn--ghost' ?>"><?= Support::e($p['cta_label'] ?: 'Scegli ' . $nome) ?></button>
          </form>
        <?php else: ?>
          <span class="price"><?= Support::e(Support::money((int) $primo['price_cents'], $primo['currency'])) ?><small> + IVA / anno</small></span>
          <span class="plan__mese">circa <?= Support::e(Support::money(Plans::monthly((int) $primo['price_cents']), $primo['currency'])) ?> al mese</span>
          <?php $voci = $p['bullet_list']; include __DIR__ . '/_voci_piano.php'; ?>
          <a class="btn <?= $scuro ? '' : 'btn--ghost' ?>" href="<?= $vai((int) $p['pv_id']) ?>"><?= Support::e($p['cta_label'] ?: 'Scegli ' . $nome) ?></a>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
  <?php include __DIR__ . '/_confronto.php'; ?>
  <p class="piani__nota">Abbonamento annuale con rinnovo automatico. Puoi disattivare il rinnovo dal tuo account, mantenendo la
    guida disponibile fino alla scadenza del periodo pagato.</p>
</section>

<section class="band" style="margin-top:72px">
  <div class="stack" style="gap:12px">
    <h2 style="font-size:clamp(28px,3.6vw,40px);line-height:1.05;max-width:620px">La tua struttura ha tanto da raccontare. Mettilo a disposizione dei tuoi ospiti.</h2>
    <p class="muted" style="font-size:17px;line-height:26px;max-width:500px">Crea la tua guida, personalizzala e guarda il
      risultato. Decidi soltanto dopo se pubblicarla.</p>
  </div>
  <a class="btn btn--lg btn--go" href="<?= $crea ?>">Crea gratis la tua guida <span class="go"><?= Icon::svg('arrow', 19, 2) ?></span></a>
</section>

<script src="<?= a() ?>/assets/prezzi.js" defer></script>
