<?php
/* La landing. Ogni blocco ha un compito solo:
     hero        che cos'è — e il prodotto si vede subito, sul telefono
     scene       come si usa, in tre immagini
     prodotto    cosa trova l'ospite
     il tempo    perché conviene al proprietario, e la frase sul valore
     guadagno    la guida che porta prenotazioni dirette e recensioni
     come        quanto è semplice cominciare (con le schermate vere del pannello)
     QR          come si condivide
     voci        le testimonianze, solo se l'amministratore ne ha inserite di vere
     domande     le FAQ, con il contatto per chi non trova la risposta
     piani       scegliere
     chiusura    cominciare, e chi c'è dietro
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

<script>
/* Prima di disegnare la pagina: se si anima, gli elementi partono già nascosti
   (niente lampo). Se landing.js non arriva entro 3 secondi, si torna alla
   pagina ferma: il contenuto non resta mai invisibile. */
(function (d) {
  if (!('IntersectionObserver' in window) || (window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches)) return;
  d.classList.add('anima');
  setTimeout(function () { if (!d.classList.contains('anima-pronta')) d.classList.remove('anima'); }, 3000);
})(document.documentElement);
</script>
<section class="hero2">
  <div class="hero2__testo">
    <span class="kicker">La reception digitale per la tua struttura ricettiva</span>
    <h1 class="display">La casa risponde<br>prima che chiedano.</h1>
    <p class="hero2__sub">La guida digitale per case vacanza, B&amp;B, affittacamere e agriturismi. Check-in, Wi-Fi,
      parcheggio, regole e consigli locali in un unico link, da condividere anche tramite QR Code.</p>
    <div class="hero2__azioni">
      <a class="btn btn--lg btn--go" href="<?= $crea ?>">Crea gratis la tua guida <span class="go"><?= Icon::svg('arrow', 19, 2) ?></span></a>
      <?php if ($demoUrl): ?>
        <?php /* La demo, e accanto le lingue in cui aprirla: un solo gruppo, non tre bottoni in fila.
                 Le lingue che la demo ha davvero, al massimo tre. */
              $lingueDemo = array_values(array_intersect(['it', 'en', 'de'], array_column(MHW\Db::all('SELECT locale FROM property_locales WHERE property_id = ?', [$demo['id']]), 'locale')));
              $nomiLingue = ['it' => 'italiano', 'en' => 'inglese', 'de' => 'tedesco']; ?>
        <span class="demo-gruppo">
          <a class="demo-gruppo__vai" href="<?= $demoUrl ?>"><?= Icon::svg('eye', 18, 1.8) ?>Guarda la demo</a>
          <?php if (count($lingueDemo) > 1): ?>
            <span class="demo-lingue" role="group" aria-label="Lingua della demo">
              <?php foreach ($lingueDemo as $l): ?><a href="<?= $demoUrl ?>?l=<?= $l ?>" hreflang="<?= $l ?>" aria-label="Demo in <?= $nomiLingue[$l] ?>"><?= strtoupper($l) ?></a><?php endforeach; ?>
            </span>
          <?php endif; ?>
        </span>
      <?php endif; ?>
    </div>
    <p class="micro micro--left hero2__garanzie"><span><?= Icon::svg('check', 15, 2.2) ?>Nessuna app</span><span><?= Icon::svg('check', 15, 2.2) ?>Anteprima gratuita</span><span><?= Icon::svg('check', 15, 2.2) ?>Paghi solo quando pubblichi</span></p>
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

<?php /* Tre scene sotto l'hero: il QR all'ingresso, l'ospite, l'host.
   Le foto si caricano in assets/foto/ con questi nomi; se una manca, la card
   mostra un riquadro colorato con un disegno, senza errori. I disegni sono
   centrati nel riquadro: si ritagliano bene anche quadrati, sul telefono. */
$cartellaFoto = (defined('MHW_PUBLIC') ? MHW_PUBLIC : dirname(__DIR__, 2) . '/public') . '/assets/foto/';
$disegni = [
  // La targa col QR accanto alla porta ad arco.
  'qr' => '<path d="M50 104V46a30 30 0 0 1 60 0v58" fill="none" stroke="currentColor" stroke-opacity=".3" stroke-width="2"/>'
        . '<rect x="61" y="27" width="38" height="50" rx="6" fill="#fff8f2"/>'
        . '<g fill="#231b12"><path d="M66 32h9v9h-9zM85 32h9v9h-9zM66 51h9v9h-9z"/>'
        . '<path d="M78 32h3v3h-3zM78 38h3v6h-3zM85 44h3v3h-3zM91 44h3v3h-3zM78 47h6v3h-6zM88 50h3v6h-3zM78 53h3v7h-3zM84 56h3v4h-3zM91 57h3v3h-3zM69 44h3v3h-3z"/></g>'
        . '<g fill="#fff8f2"><path d="M68 34h5v5h-5zM87 34h5v5h-5zM68 53h5v5h-5z"/></g>'
        . '<g fill="#231b12"><path d="M69.5 35.5h2v2h-2zM88.5 35.5h2v2h-2zM69.5 54.5h2v2h-2z"/></g>'
        . '<rect x="68" y="66" width="24" height="3" rx="1.5" fill="#b4451f" fill-opacity=".55"/>',
  // Il telefono dell'ospite con le quattro sezioni.
  'ospite' => '<rect x="58" y="14" width="44" height="96" rx="9" fill="#231b12"/>'
        . '<rect x="61.5" y="17.5" width="37" height="89" rx="6.5" fill="#faf5ec"/>'
        . '<rect x="73" y="20" width="14" height="3.5" rx="1.75" fill="#231b12"/>'
        . '<rect x="66" y="29" width="20" height="3.5" rx="1.75" fill="#231b12"/><rect x="66" y="35" width="14" height="3.5" rx="1.75" fill="#231b12"/>'
        . '<rect x="66" y="43" width="28" height="16" rx="4" fill="#e2d7c4"/>'
        . '<rect x="66" y="62" width="13.5" height="13" rx="3.5" fill="#b4451f"/><rect x="80.5" y="62" width="13.5" height="13" rx="3.5" fill="#1c5a78"/>'
        . '<rect x="66" y="77" width="13.5" height="13" rx="3.5" fill="#1f6b3f"/><rect x="80.5" y="77" width="13.5" height="13" rx="3.5" fill="#b07d0c"/>',
  // Il pannello dell'host, con il bottone «Pubblica».
  'host' => '<g transform="translate(80 53) scale(.92) translate(-80 -53)"><rect x="26" y="20" width="108" height="66" rx="7" fill="#faf5ec"/>'
        . '<path d="M26 27a7 7 0 0 1 7-7h94a7 7 0 0 1 7 7v4H26z" fill="#e2d7c4"/>'
        . '<circle cx="33" cy="25.5" r="1.6" fill="#94825f"/><circle cx="38.5" cy="25.5" r="1.6" fill="#94825f"/><circle cx="44" cy="25.5" r="1.6" fill="#94825f"/>'
        . '<rect x="33" y="38" width="22" height="3" rx="1.5" fill="#231b12"/><rect x="33" y="46" width="18" height="3" rx="1.5" fill="#94825f"/>'
        . '<rect x="33" y="53" width="20" height="3" rx="1.5" fill="#94825f"/><rect x="33" y="60" width="16" height="3" rx="1.5" fill="#94825f"/>'
        . '<rect x="64" y="37" width="62" height="11" rx="3" fill="#fff" stroke="#e2d7c4"/><rect x="68" y="41" width="30" height="3" rx="1.5" fill="#6a5b48"/>'
        . '<rect x="64" y="52" width="62" height="11" rx="3" fill="#fff" stroke="#e2d7c4"/><rect x="68" y="56" width="22" height="3" rx="1.5" fill="#6a5b48"/>'
        . '<rect x="113" y="54.5" width="9" height="6" rx="3" fill="#1f6b3f"/><circle cx="119" cy="57.5" r="2" fill="#fff"/>'
        . '<rect x="98" y="70" width="28" height="9" rx="4.5" fill="#b4451f"/><rect x="104" y="73.5" width="16" height="2" rx="1" fill="#fff8f2"/></g>',
]; ?>
<section class="scene" aria-label="Come si usa">
  <?php /* Ogni scena porta dove se ne parla: il QR, la demo, i passi nel pannello. */
  foreach ([['scena-qr.jpg', 'qr', 't-terracotta', 'Il QR all\'ingresso', 'Lo stampi una volta: l\'ospite lo inquadra e la guida si apre.', '#qr',
                   'Un ospite inquadra con il telefono il QR in cornice accanto alla porta d\'ingresso', '62% 50%'],
                  ['scena-ospite.jpg', 'ospite', 't-sea', 'L\'ospite trova tutto', 'Wi-Fi, check-in, consigli: nella sua lingua, sul suo telefono.', $demoUrl ?? '#prodotto-titolo',
                   'Un\'ospite al tavolo della casa sfoglia la guida sul telefono: Wi-Fi, check-in, parcheggio, dove mangiare', '50% 50%'],
                  ['scena-host.jpg', 'host', 't-pine', 'Tu aggiorni quando vuoi', 'Cambi un orario dal pannello e pubblichi: il QR resta lo stesso.', '#come-funziona',
                   'L\'host aggiorna la guida dal portatile, con l\'anteprima sul telefono accanto', '52% 50%']] as [$file, $dis, $tono, $tit, $txt, $dove, $alt, $centro]):
        $cie = is_file($cartellaFoto . $file);
        // Le versioni WebP (da tools/foto.php) valgono solo se non sono più vecchie del .jpg:
        // chi carica un .jpg nuovo lo vede subito, anche prima di rigenerarle.
        $base = substr($file, 0, -4);
        $webp = $cie && is_file($cartellaFoto . "$base-600.webp") && is_file($cartellaFoto . "$base-1200.webp")
             && filemtime($cartellaFoto . "$base-1200.webp") >= filemtime($cartellaFoto . $file); ?>
    <figure class="scena">
      <?php if ($cie): ?><picture>
        <?php if ($webp): ?><source type="image/webp" sizes="(max-width: 760px) 92px, 380px"
          srcset="<?= Support::e(a("/assets/foto/$base-600.webp")) ?> 600w, <?= Support::e(a("/assets/foto/$base-1200.webp")) ?> 1200w"><?php endif; ?>
        <img src="<?= Support::e(a('/assets/foto/' . $file)) ?>" alt="<?= Support::e($alt) ?>" loading="lazy" decoding="async" width="1200" height="750" style="object-position:<?= $centro ?>">
      </picture>
      <?php else: ?><span class="scena__vuota <?= $tono ?>" aria-hidden="true"><svg viewBox="0 0 160 100" preserveAspectRatio="xMidYMid slice"><?= $disegni[$dis] ?></svg></span><?php endif; ?>
      <figcaption><a class="scena__link" href="<?= $dove ?>"><?= $tit ?> <?= Icon::svg('arrow', 15, 2) ?></a><span><?= $txt ?></span></figcaption>
    </figure>
  <?php endforeach; ?>
</section>

<section class="prodotto" aria-labelledby="prodotto-titolo">
  <div class="prodotto__testa">
    <span class="kicker">La guida</span>
    <h2 id="prodotto-titolo" class="h-sezione">Cosa trova l'ospite.</h2>
    <p class="muted">Le informazioni del soggiorno, in ordine e sempre sul telefono. Le sezioni le scegli tu, in base al piano.</p>
    <?php if ($demoUrl): ?><a class="link-freccia" href="<?= $demoUrl ?>">Sfoglia la guida di <?= Support::e($nomeDemo) ?> <?= Icon::svg('arrow', 16, 2) ?></a><?php endif; ?>
  </div>
  <ul class="features8">
    <?php $toni = ['terracotta', 'sea', 'pine', 'ochre'];
    foreach ($elementi as $i => [$ico, $nome]): ?>
      <li class="feat feat--<?= $toni[$i % 4] ?>"><span class="ico"><?= Icon::svg($ico, 20, 1.8) ?></span><b><?= Support::e($nome) ?></b></li>
    <?php endforeach; ?>
  </ul>
</section>

<?php /* Il tempo che non vedi: a sinistra il problema e la soluzione, a destra
   le domande che arrivano e la guida che risponde; sotto, due vantaggi e il
   valore dell'anno. Le domande sono esempi; nessun numero. */ ?>
<section id="il-tempo" class="tempo" aria-labelledby="tempo-titolo">
  <div class="tempo__grid">
    <div class="tempo__intro">
      <span class="kicker">Il tempo che non vedi</span>
      <h2 id="tempo-titolo" class="tempo__titolo">Ogni ospite è nuovo.<br>Le domande sono quasi sempre le stesse.</h2>
      <p class="lead">Parcheggio, Wi-Fi, orari, regole della casa. Ogni richiesta richiede poco tempo, ma prova a pensare
        quante volte ripeti le stesse informazioni durante una stagione.</p>
      <div class="tempo__svolta">
        <h3 class="tempo__h3">Le risposte sono già nella tua guida.</h3>
        <p>Con MyHouse Welcome raccogli le informazioni della tua struttura in un unico posto. Le condividi prima
          dell'arrivo e gli ospiti possono consultarle durante il soggiorno, quando ne hanno bisogno.</p>
      </div>
    </div>

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
      <p class="risposta"><span class="risposta__chi"><?= Icon::brand(16) ?>La tua guida</span>Parcheggio, Wi-Fi e orari di partenza: è tutto qui, in ogni momento.</p>
      <figcaption class="tiny muted">Esempi di domande ricorrenti.</figcaption>
    </figure>
  </div>

  <div class="vantaggi">
    <div class="vantaggio">
      <span class="vantaggio__ico"><?= Icon::svg('clock', 20, 1.7) ?></span>
      <b>Più tempo per te.</b>
      <p>Meno spiegazioni da ripetere a ogni nuovo soggiorno.</p>
    </div>
    <div class="vantaggio">
      <span class="vantaggio__ico"><?= Icon::svg('check', 20, 2) ?></span>
      <b>Meno dubbi all'arrivo.<br>Meno equivoci alla partenza.</b>
      <p>Indicazioni chiare su orari, regole e informazioni utili aiutano a prevenire dubbi e fraintendimenti.</p>
    </div>
    <div class="tempo__valore">
      <span class="kicker">Il valore dell'abbonamento</span>
      <h3 class="tempo__h3">Un piccolo investimento annuale, utile soggiorno dopo soggiorno.</h3>
      <?php if ($partenza): ?>
        <p class="tempo__prezzo">Da <?= Support::e(Support::money((int) $partenza['price_cents'], $partenza['currency'])) ?> + IVA all'anno.
          <a href="#piani">Vedi i piani</a></p>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php /* La frase grande, da sola: il motivo per cui l'abbonamento vale. */ ?>
<section class="frase" aria-label="Il valore">
  <p class="frase__testo">Il valore non è soltanto nella guida.<br><span>È nel tempo che puoi dedicare ad altro.</span></p>
  <p class="frase__sotto">Organizzi le risposte una volta, le aggiorni quando serve e le rendi disponibili a ogni nuovo ospite.
    Anche pochi minuti recuperati a ogni soggiorno, nel corso dell'anno, possono fare la differenza.</p>
</section>

<?php /* La guida che fa guadagnare: recensioni, prenotazione diretta, servizi extra.
   A destra il commiato, disegnato con le stesse etichette della guida vera. */ ?>
<section id="guadagno" class="guadagno" aria-labelledby="guadagno-titolo">
  <div class="guadagno__testo">
    <span class="kicker">La guida che lavora per te</span>
    <h2 id="guadagno-titolo" class="h-sezione">Una prenotazione diretta in più all'anno paga l'abbonamento.</h2>
    <p class="guadagno__lead">Alla fine del soggiorno la guida saluta l'ospite e gli lascia due inviti: una recensione dove preferisci, e la
      prossima volta la prenotazione sul tuo sito, con il tuo codice sconto. Senza commissioni.</p>
    <ul class="guadagno__voci">
      <li><span class="guadagno__ico"><?= Icon::svg('message', 18, 1.8) ?></span><span><b>Più recensioni.</b>
        Google, Booking, Airbnb: un pulsante per ognuno, quando il ricordo è fresco.</span></li>
      <li><span class="guadagno__ico"><?= Icon::svg('globe', 18, 1.8) ?></span><span><b>Prenotazioni dirette.</b>
        «La prossima volta prenota da noi», con il link al tuo sito e il codice sconto.</span></li>
      <li><span class="guadagno__ico"><?= Icon::svg('euro', 18, 1.8) ?></span><span><b>Servizi extra.</b>
        Transfer, colazione, late check-out: l'ospite li chiede con un tocco su WhatsApp.</span></li>
    </ul>
  </div>
  <div class="congedo-mock" aria-hidden="true">
    <span class="congedo-mock__kicker"><?= Support::e(MHW\I18n::t('it', 'before_leaving')) ?></span>
    <span class="congedo-mock__titolo"><?= Support::e(MHW\I18n::t('it', 'farewell_title')) ?></span>
    <span class="congedo-mock__blocco">
      <b><?= Support::e(MHW\I18n::t('it', 'review_title')) ?></b>
      <span class="congedo-mock__bottoni"><span><?= Icon::svg('message', 13) ?>Google</span><span><?= Icon::svg('message', 13) ?>Booking.com</span><span><?= Icon::svg('message', 13) ?>Airbnb</span></span>
    </span>
    <span class="congedo-mock__blocco">
      <b><?= Support::e(MHW\I18n::t('it', 'direct_title')) ?></b>
      <span class="congedo-mock__codice"><?= Support::e(MHW\I18n::t('it', 'direct_code', 'BENTORNATI')) ?></span>
    </span>
  </div>
</section>

<section id="come-funziona" class="blocco" aria-labelledby="come-titolo">
  <div class="come__testa">
    <span class="kicker">Come funziona</span>
    <h2 id="come-titolo" class="h-sezione">Inizia in pochi minuti.</h2>
  </div>
  <?php /* Tre passi e le schermate vere del pannello (assets/foto/pannello-1…3.webp).
     Senza JavaScript i passi sono link alle schermate, tutte visibili; con
     landing.js diventano schede: una schermata grande alla volta. Se le
     schermate mancano, restano i passi numerati. */
  $passi = [['01', 'Crea la tua guida.', 'Inserisci le informazioni della struttura e scegli cosa condividere con gli ospiti.', 'Il pannello: i contenuti della guida, con le sezioni e le statistiche'],
            ['02', 'Personalizza e guarda l\'anteprima.', 'Vedi come apparirà la guida sullo smartphone, prima di pubblicarla.', 'Il pannello: l\'aspetto della guida, con palette, testo e copertina'],
            ['03', 'Pubblica e condividi.', 'Attiva il piano e condividi la guida tramite link o QR Code.', 'Il pannello: il QR Code da stampare e il link da condividere']];
  $schermate = array_filter(array_map(fn($i) => is_file($cartellaFoto . 'pannello-' . ($i + 1) . '.webp') ? 'pannello-' . ($i + 1) . '.webp' : null, array_keys($passi))); ?>
  <div class="passi<?= count($schermate) === 3 ? '' : ' passi--senza' ?>"<?= count($schermate) === 3 ? ' data-passi' : '' ?>>
    <ol class="passi__lista">
      <?php foreach ($passi as $i => [$n, $tit, $txt]): ?>
        <li><a class="passo" id="passo-<?= $i + 1 ?>" href="#schermata-<?= $i + 1 ?>"<?= count($schermate) === 3 ? '' : ' tabindex="-1"' ?>>
          <span class="passo__n"><?= $n ?></span>
          <span class="passo__testo"><b><?= $tit ?></b><span><?= $txt ?></span></span></a></li>
      <?php endforeach; ?>
    </ol>
    <?php if (count($schermate) === 3): ?>
      <div class="passi__schermate">
        <?php foreach ($passi as $i => [$n, , , $alt]): ?>
          <figure class="schermata" id="schermata-<?= $i + 1 ?>">
            <span class="schermata__barra" aria-hidden="true"><i></i><i></i><i></i><span>myhousewelcome.it</span></span>
            <img src="<?= Support::e(a('/assets/foto/pannello-' . ($i + 1) . '.webp')) ?>" alt="<?= Support::e($alt) ?>"
                 loading="<?= $i === 0 ? 'eager' : 'lazy' ?>" decoding="async" width="1200" height="750">
          </figure>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
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
  <div class="qr-sheet qrband__foglio">
    <?php /* Se c'è la demo, il QR la apre davvero: provalo col telefono. */ ?>
    <?= preg_replace('/width="\d+" height="\d+"/', 'width="168" height="168"',
                     MHW\QrExport::svg($demo ? Support::baseUrl() . '/g/' . $demo['slug'] . '/benvenuto' : Support::baseUrl())) ?>
    <p class="small"><?= $demo ? 'Inquadra e prova la demo.' : 'Inquadra per la guida' ?></p>
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

<?php $legale = MHW\Config::get('legal');
$waFaq = preg_replace('/\D/', '', (string) ($legale['contact_whatsapp'] ?? '')); ?>
<section id="domande" class="blocco faq" aria-labelledby="faq-titolo">
  <div class="faq__testa">
    <span class="kicker">Domande</span>
    <h2 id="faq-titolo" class="h-sezione">Prima di cominciare.</h2>
    <?php if (($legale['contact_email'] ?? '') !== '' || $waFaq !== ''): ?>
      <p class="muted">Non trovi la risposta? Scrivici, ti rispondiamo volentieri.</p>
      <div class="faq__contatti">
        <?php if ($waFaq !== ''): ?><a class="btn btn--ghost" href="https://wa.me/<?= Support::e($waFaq) ?>" rel="noopener"><?= Icon::svg('whatsapp', 18, 1.8) ?>WhatsApp</a><?php endif; ?>
        <?php if (($legale['contact_email'] ?? '') !== ''): ?><a class="btn btn--ghost" href="mailto:<?= Support::e($legale['contact_email']) ?>"><?= Icon::svg('message', 18, 1.8) ?>Email</a><?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
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

<section class="chiusura" aria-labelledby="chiusura-titolo">
  <div class="chiusura__testo">
    <h2 id="chiusura-titolo" class="chiusura__titolo">La tua struttura ha tanto da raccontare. Mettilo a disposizione dei tuoi ospiti.</h2>
    <p>Crea la tua guida, personalizzala e guarda il risultato. Decidi soltanto dopo se pubblicarla.</p>
    <div class="row">
      <a class="btn btn--lg btn--go" href="<?= $crea ?>">Crea gratis la tua guida <span class="go"><?= Icon::svg('arrow', 19, 2) ?></span></a>
      <?php if ($demoUrl): ?><a class="btn btn--lg btn--ghost" href="<?= $demoUrl ?>">Guarda la demo</a><?php endif; ?>
    </div>
  </div>
  <aside class="chi" aria-label="Chi c'è dietro MyHouse Welcome">
    <span class="chi__marchio"><?= Icon::brand(28) ?></span>
    <p class="chi__titolo">Pensata per chi ospita. Sviluppata da chi lavora nel digitale e nell'ospitalità.</p>
    <p>MyHouse Welcome fa parte delle soluzioni MyHouse di
      <a href="https://blackout.in" rel="noopener" target="_blank">Blackout Agency</a>, dedicate alle esigenze digitali delle strutture ricettive.</p>
  </aside>
</section>

<script src="<?= MHW\av('/assets/prezzi.js') ?>" defer></script>
<script src="<?= MHW\av('/assets/landing.js') ?>" defer></script>
