<?php
/* La landing. Ogni blocco ha un compito solo:
     hero        che cos'è — e il prodotto si vede subito, sul telefono
     scene       come si usa, in tre immagini
     prodotto    cosa trova l'ospite
     per chi     quattro modi di ospitare: a ognuno il suo uso
     il tempo    perché conviene al proprietario
     guadagno    la guida che porta prenotazioni dirette e recensioni
     come        quanto è semplice cominciare (con le schermate vere del pannello)
     QR          come si condivide
     voci        le testimonianze, solo se l'amministratore ne ha inserite di vere
     piani       scegliere (e, se attivo, «Porta un amico»)
     domande     le FAQ (le otto principali, poi «Altre domande»), con il contatto per chi non trova la risposta
     chiusura    cominciare, e chi c'è dietro
   Prezzi, nomi ed elenchi dei piani arrivano dal database (li cambia
   l'amministratore): nessun prezzo è scritto qui o nello script. Nessuna
   testimonianza e nessun numero inventato: la demo è dichiarata come demo, le
   domande sono esempi. */
use function MHW\{a, b};
use MHW\{Support, Icon, Plans, TestiSito};
$title = 'MyHouse Welcome — la guida digitale della tua struttura';
/* I testi della pagina: Amministrazione → Sistema → «Testi e foto» (TestiSito); di serie, quelli di sempre. */
$anteprimaTesti = !empty($anteprimaTesti);
$T = fn(string $k, array $sost = []) => $anteprimaTesti
    ? '<span data-testo="' . $k . '"' . ($sost ? ' data-sost="' . Support::e(json_encode($sost, JSON_UNESCAPED_UNICODE)) . '"' : '') . '>' . TestiSito::html($k, $sost) . '</span>'
    : TestiSito::html($k, $sost);
$L = fn(string $k) => $anteprimaTesti ? ' data-testo-lista="' . $k . '"' : '';   // un elenco (una voce per riga)
$F = fn(string $n) => $anteprimaTesti ? ' data-foto="' . $n . '"' : '';          // una foto
$dentro = !empty($user);
$ctaCrea = fn() => $dentro ? 'Vai alle tue guide' : $T('cta.crea');
$vai = fn(int $pv) => b() . ($dentro ? '/piano?piano=' : '/registrati?piano=') . $pv;
$crea = b() . ($dentro ? '/pannello' : '/registrati');
$demoUrl = $demo ? b() . '/g/' . Support::e($demo['slug']) . '/benvenuto' : null;
$nomeDemo = $demo['name'] ?? 'Casa Lucia';
$fotoJpg = a('/assets/foto/borgo.jpg');
$fotoSet = a('/assets/foto/borgo-1200.webp') . ' 1200w, ' . a('/assets/foto/borgo-2000.webp') . ' 2000w';
// Il telefono mostra la demo vera: con la vetrina (Casa Checco) la sua foto, altrimenti quella di Casa Lucia.
$vetrina = $demo && (int) $demo['is_demo'] === MHW\Demo::VETRINA;
$telefonoJpg = a($vetrina ? '/assets/foto/checco-telefono.jpg' : '/assets/foto/borgo-telefono.jpg');
$telefonoWebp = a($vetrina ? '/assets/foto/checco-telefono-600.webp' : '/assets/foto/borgo-telefono-600.webp');
$checkinDemo = preg_match('/^\d{2}:\d{2}$/', (string) ($demo['checkin_from'] ?? '')) ? $demo['checkin_from'] : '15:00';
// Il prezzo di partenza, dal listino: se l'amministratore lo cambia, cambia anche qui.
$partenza = null;
foreach ($offers as $of) foreach ($of['options'] as $o) {
    if ($partenza === null || (int) $o['price_cents'] < (int) $partenza['price_cents']) $partenza = $o;
}
// Tutte le sezioni del catalogo, nei tre gruppi (fase 6C): icone e titoli sono gli stessi del pannello e della guida.
$gruppiSezioni = MHW\SectionCatalog::gruppi();
$quanteSezioni = array_sum(array_map('count', $gruppiSezioni)); ?>

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
<?php include __DIR__ . '/_fascia_sconto.php'; ?>
<?php /* Chi è già registrato lo vede subito, prima di tutto il resto: il saluto, le sue
   guide con lo stato vero e il passo successivo. Niente da cercare nel menu. */
if ($dentro):
    $mie = $mie ?? [];
    $nomeU = trim((string) ($user['name'] ?? '')); $primo = $nomeU !== '' ? explode(' ', $nomeU)[0] : '';
    $admin = ($user['role'] ?? '') === 'admin'; ?>
<section class="bentornato" aria-labelledby="bentornato-titolo">
  <div class="bentornato__chi">
    <span class="bentornato__avatar" aria-hidden="true"><?= Support::e(mb_strtoupper(mb_substr($nomeU !== '' ? $nomeU : (string) $user['email'], 0, 1))) ?></span>
    <div>
      <h2 id="bentornato-titolo" class="bentornato__titolo"><?= $primo !== '' ? 'Ciao, ' . Support::e($primo) . '.' : 'Sei dentro.' ?></h2>
      <p class="small muted">Sei dentro come <b><?= Support::e($user['email']) ?></b><?= $admin ? ' · amministrazione' : '' ?></p>
    </div>
  </div>
  <?php if ($admin): ?>
    <a class="btn btn--go" href="<?= b() ?>/admin">Vai all'amministrazione <span class="go"><?= Icon::svg('arrow', 17, 2) ?></span></a>
  <?php elseif (!$mie): ?>
    <p class="bentornato__vuoto">Non hai ancora una guida. Ci vogliono pochi minuti.</p>
    <a class="btn btn--go" href="<?= b() ?>/pannello">Crea la tua prima guida <span class="go"><?= Icon::svg('arrow', 17, 2) ?></span></a>
  <?php else: ?>
    <ul class="bentornato__guide" aria-label="Le tue guide">
      <?php foreach (array_slice($mie, 0, 3) as $g):
            [$stato, $tono] = $g['online'] ? ['Online', 'pine'] : ($g['status'] === 'published' ? ['Offline', 'alert'] : ['Bozza', 'ochre']); ?>
        <li><a class="bentornato__guida" href="<?= b() ?>/pannello/<?= (int) $g['id'] ?>">
          <span class="grow stack" style="gap:2px;min-width:0"><b><?= Support::e($g['name']) ?></b><?php if (trim((string) $g['city']) !== ''): ?><span class="small muted"><?= Support::e($g['city']) ?></span><?php endif; ?></span>
          <span class="badge badge--<?= $tono ?>"><?= $stato ?></span><?= Icon::svg('chevron', 16, 2) ?></a></li>
      <?php endforeach; ?>
    </ul>
    <a class="btn btn--go" href="<?= b() ?>/pannello"><?= count($mie) > 3 ? 'Tutte le tue ' . count($mie) . ' guide' : 'Vai alle tue guide' ?> <span class="go"><?= Icon::svg('arrow', 17, 2) ?></span></a>
  <?php endif; ?>
</section>
<?php endif; ?>
<section class="hero2">
  <div class="hero2__testo">
    <span class="kicker"><?= $T('hero.kicker') ?></span>
    <h1 class="display"><?= $T('hero.titolo') ?></h1>
    <p class="hero2__sub"><?= $T('hero.sotto') ?></p>
    <div class="hero2__azioni">
      <a class="btn btn--lg btn--go" href="<?= $crea ?>"><?= $ctaCrea() ?> <span class="go"><?= Icon::svg('arrow', 19, 2) ?></span></a>
      <?php if ($demoUrl): ?>
        <?php /* La demo, e accanto le lingue in cui aprirla: un solo gruppo, non tre bottoni in fila.
                 Le lingue che la demo ha davvero, al massimo tre. */
              $lingueDemo = array_values(array_intersect(['it', 'en', 'fr'], array_column(MHW\Db::all('SELECT locale FROM property_locales WHERE property_id = ?', [$demo['id']]), 'locale')));
              $nomiLingue = ['it' => 'italiano', 'en' => 'inglese', 'fr' => 'francese']; ?>
        <span class="demo-gruppo">
          <a class="demo-gruppo__vai" href="<?= $demoUrl ?>"><?= Icon::svg('eye', 18, 1.8) ?><?= $T('cta.demo') ?></a>
          <?php if (count($lingueDemo) > 1): ?>
            <span class="demo-lingue" role="group" aria-label="Lingua della demo">
              <?php foreach ($lingueDemo as $l): ?><a href="<?= $demoUrl ?>?l=<?= $l ?>" hreflang="<?= $l ?>" aria-label="Demo in <?= $nomiLingue[$l] ?>"><?= strtoupper($l) ?></a><?php endforeach; ?>
            </span>
          <?php endif; ?>
        </span>
      <?php endif; ?>
    </div>
    <p class="micro micro--left hero2__garanzie"<?= $L('hero.garanzie') ?>><?php foreach (TestiSito::lista('hero.garanzie') as $g): ?><span><?= Icon::svg('check', 15, 2.2) ?><?= $g ?></span><?php endforeach; ?></p>
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
          <span class="device__shot"><?php $telPers = TestiSito::immagine('telefono'); if ($telPers['caricata']): ?>
            <img src="<?= Support::e($telPers['url']) ?>" alt="" width="600" height="422" loading="eager" decoding="async"<?= $F('telefono') ?>>
            <?php else: ?><picture><source srcset="<?= Support::e($telefonoWebp) ?>" type="image/webp">
            <img src="<?= Support::e($telefonoJpg) ?>" alt="" width="600" height="422" loading="eager" decoding="async"<?= $F('telefono') ?>></picture><?php endif; ?>
            <span class="device__pill"><i></i>Check-in dalle <?= Support::e($checkinDemo) ?></span></span>
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
    <?php if ($demoUrl): ?><span class="device-link__invito"><?= $T('hero.invito') ?> <?= Icon::svg('arrow', 15, 2) ?></span><?php endif; ?>
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
  foreach ([['scena-qr.jpg', 'qr', 't-terracotta', $T('scena.qr.titolo'), $T('scena.qr.testo'), '#qr', TestiSito::testo('scena.qr.alt'), '62% 50%'],
                  ['scena-ospite.jpg', 'ospite', 't-sea', $T('scena.ospite.titolo'), $T('scena.ospite.testo'), $demoUrl ?? '#prodotto-titolo', TestiSito::testo('scena.ospite.alt'), '50% 50%'],
                  ['scena-host.jpg', 'host', 't-pine', $T('scena.host.titolo'), $T('scena.host.testo'), '#come-funziona', TestiSito::testo('scena.host.alt'), '52% 50%']] as [$file, $dis, $tono, $tit, $txt, $dove, $alt, $centro]):
        $pers = TestiSito::immagine(substr($file, 0, -4));   // la foto caricata dall'amministrazione, se c'è
        $cie = is_file($cartellaFoto . $file);
        // Le versioni WebP (da tools/foto.php) valgono solo se non sono più vecchie del .jpg:
        // chi carica un .jpg nuovo lo vede subito, anche prima di rigenerarle.
        $base = substr($file, 0, -4);
        $webp = $cie && is_file($cartellaFoto . "$base-600.webp") && is_file($cartellaFoto . "$base-1200.webp")
             && filemtime($cartellaFoto . "$base-1200.webp") >= filemtime($cartellaFoto . $file); ?>
    <figure class="scena">
      <?php if ($pers['caricata']): ?><picture><img src="<?= Support::e($pers['url']) ?>" alt="<?= $alt ?>" loading="lazy" decoding="async" width="1200" height="750"<?= $F(substr($file, 0, -4)) ?>></picture>
      <?php elseif ($cie): ?><picture>
        <?php if ($webp): ?><source type="image/webp" sizes="(max-width: 760px) 92px, 380px"
          srcset="<?= Support::e(a("/assets/foto/$base-600.webp")) ?> 600w, <?= Support::e(a("/assets/foto/$base-1200.webp")) ?> 1200w"><?php endif; ?>
        <img src="<?= Support::e(a('/assets/foto/' . $file)) ?>" alt="<?= $alt ?>" loading="lazy" decoding="async" width="1200" height="750" style="object-position:<?= $centro ?>"<?= $F(substr($file, 0, -4)) ?>>
      </picture>
      <?php else: ?><span class="scena__vuota <?= $tono ?>" aria-hidden="true"><svg viewBox="0 0 160 100" preserveAspectRatio="xMidYMid slice"><?= $disegni[$dis] ?></svg></span><?php endif; ?>
      <figcaption><a class="scena__link" href="<?= $dove ?>"><?= $tit ?> <?= Icon::svg('arrow', 15, 2) ?></a><span><?= $txt ?></span></figcaption>
    </figure>
  <?php endforeach; ?>
</section>

<section class="prodotto" aria-labelledby="prodotto-titolo">
  <div class="prodotto__testa">
    <span class="kicker"><?= $T('prodotto.kicker') ?></span>
    <h2 id="prodotto-titolo" class="h-sezione"><?= $T('prodotto.titolo') ?></h2>
    <p class="muted"><?= $T('prodotto.testo', ['sezioni' => $quanteSezioni]) ?></p>
    <?php if ($demoUrl): ?><a class="link-freccia" href="<?= $demoUrl ?>"><?= $T('prodotto.link', ['demo' => Support::e($nomeDemo)]) ?> <?= Icon::svg('arrow', 16, 2) ?></a><?php endif; ?>
  </div>
  <div class="gruppi-sez">
    <?php $toni = ['casa' => 'terracotta', 'arrivo' => 'sea', 'territorio' => 'pine'];
    foreach ($gruppiSezioni as $g => $tipi): if (!$tipi) continue; ?>
      <div class="gruppo-sez">
        <h3 class="gruppo-sez__titolo" id="gruppo-<?= $g ?>"><?= Support::e(MHW\SectionCatalog::GRUPPI[$g]) ?></h3>
        <ul class="features8 features8--compatte" aria-labelledby="gruppo-<?= $g ?>">
          <?php foreach ($tipi as $k): ?>
            <li class="feat feat--<?= $toni[$g] ?>"><span class="ico"><?= Icon::svg(MHW\SectionCatalog::icon($k), 18, 1.8) ?></span><b><?= Support::e(MHW\SectionCatalog::title($k, 'it')) ?></b></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<?php /* Per chi è: quattro modi di ospitare, a ognuno il suo uso della guida. Niente numeri e
   niente promesse: solo quello che il prodotto fa davvero. */ ?>
<section id="per-chi" class="blocco" aria-labelledby="perchi-titolo">
  <div class="come__testa">
    <span class="kicker"><?= $T('perchi.kicker') ?></span>
    <h2 id="perchi-titolo" class="h-sezione"><?= $T('perchi.titolo') ?></h2>
  </div>
  <div class="vantaggi vantaggi--4">
    <?php foreach (['home', 'coffee', 'sun', 'layers'] as $i => $ico): $tit = $T('perchi.' . ($i + 1) . '.titolo'); $txt = $T('perchi.' . ($i + 1) . '.testo'); ?>
      <div class="vantaggio">
        <span class="vantaggio__ico"><?= Icon::svg($ico, 20, 1.7) ?></span>
        <b><?= $tit ?></b>
        <p><?= $txt ?></p>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<?php include __DIR__ . '/_tempo.php'; ?>

<?php /* La guida che fa guadagnare: recensioni, prenotazione diretta, servizi extra.
   A destra il commiato, disegnato con le stesse etichette della guida vera. */ ?>
<section id="guadagno" class="guadagno" aria-labelledby="guadagno-titolo">
  <div class="guadagno__testo">
    <span class="kicker"><?= $T('guadagno.kicker') ?></span>
    <h2 id="guadagno-titolo" class="h-sezione"><?= $T('guadagno.titolo') ?></h2>
    <p class="guadagno__lead"><?= $T('guadagno.lead') ?></p>
    <ul class="guadagno__voci">
      <?php foreach (['message', 'globe', 'euro'] as $i => $ico): ?>
      <li><span class="guadagno__ico"><?= Icon::svg($ico, 18, 1.8) ?></span><span><b><?= $T('guadagno.' . ($i + 1) . '.titolo') ?></b>
        <?= $T('guadagno.' . ($i + 1) . '.testo') ?></span></li>
      <?php endforeach; ?>
    </ul>
    <?php /* 7B: alla fine del blocco scuro, lo stesso bottone dell'hero. */ ?>
    <a class="btn btn--lg btn--go guadagno__cta" href="<?= $crea ?>"><?= $ctaCrea() ?> <span class="go"><?= Icon::svg('arrow', 19, 2) ?></span></a>
  </div>
  <?php /* 8A: la scheda è la pagina di saluto nuova (8C): il voto con 5 stelle piene, il grazie e i portali. */ ?>
  <div class="congedo-mock" aria-hidden="true">
    <span class="congedo-mock__kicker"><?= Support::e(MHW\I18n::t('it', 'before_leaving')) ?></span>
    <span class="congedo-mock__titolo"><?= Support::e(MHW\I18n::t('it', 'farewell_title')) ?></span>
    <span class="congedo-mock__blocco">
      <b><?= Support::e(MHW\I18n::t('it', 'rating_q')) ?></b>
      <span class="congedo-mock__stelle"><?php for ($i = 0; $i < 5; $i++) echo Icon::svg('star', 26, 1.6); ?></span>
      <span class="congedo-mock__grazie"><b><?= Support::e(MHW\I18n::t('it', 'rating_happy')) ?></b><?= Support::e(MHW\I18n::t('it', 'rating_favor')) ?></span>
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
    <span class="kicker"><?= $T('come.kicker') ?></span>
    <h2 id="come-titolo" class="h-sezione"><?= $T('come.titolo') ?></h2>
  </div>
  <?php /* Tre passi e le schermate vere del pannello (assets/foto/pannello-1…3.webp).
     Senza JavaScript i passi sono link alle schermate, tutte visibili; con
     landing.js diventano schede: una schermata grande alla volta. Se le
     schermate mancano, restano i passi numerati. */
  $passi = [];
  foreach (['01', '02', '03'] as $i => $n) $passi[] = [$n, $T('come.' . ($i + 1) . '.titolo'), $T('come.' . ($i + 1) . '.testo'), TestiSito::testo('come.' . ($i + 1) . '.alt')];
  // Le schermate: quelle caricate dall'amministrazione, altrimenti pannello-N.webp se c'è.
  $schermate = array_filter(array_map(fn($i) => TestiSito::immagine('pannello-' . ($i + 1))['url'], array_keys($passi))); ?>
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
            <img src="<?= Support::e($schermate[$i]) ?>" alt="<?= $alt ?>"<?= $F('pannello-' . ($i + 1)) ?>
                 loading="<?= $i === 0 ? 'eager' : 'lazy' ?>" decoding="async" width="1200" height="750">
          </figure>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<section id="qr" class="band qrband" aria-labelledby="qr-titolo">
  <div class="stack" style="gap:20px;max-width:540px">
    <h2 id="qr-titolo" class="h-sezione"><?= $T('qr.titolo') ?></h2>
    <ul class="spunte">
      <?php foreach ([1, 2, 3] as $i): ?>
      <li><?= Icon::svg('check', 18, 2) ?><span><b><?= $T("qr.$i.titolo") ?></b><?= $T("qr.$i.testo") ?></span></li>
      <?php endforeach; ?>
    </ul>
  </div>
  <div class="qr-sheet qrband__foglio">
    <?php /* Se c'è la demo, il QR la apre davvero: provalo col telefono. */ ?>
    <?php /* Dentro la pagina l'SVG va senza la dichiarazione XML (7A): alcuni browser la mostrano come testo. */ ?>
    <?= preg_replace(['/^<\?xml[^>]*\?>\s*/', '/width="\d+" height="\d+"/'], ['', 'width="168" height="168"'],
                     MHW\QrExport::svg($demo ? Support::baseUrl() . '/g/' . $demo['slug'] . '/benvenuto' : Support::baseUrl())) ?>
    <p class="small"><?= $demo ? 'Inquadra e prova la demo.' : 'Inquadra per la guida' ?></p>
  </div>
</section>

<?php if (!empty($testimonianze)): /* solo testimonianze vere, inserite dall'amministratore */ ?>
<section class="blocco voci" aria-labelledby="voci-titolo">
  <div class="stack stack--sm"><span class="kicker"><?= $T('voci.kicker') ?></span><h2 id="voci-titolo" class="h-sezione"><?= $T('voci.titolo') ?></h2>
    <?php /* 7D: la media solo da 5 giudizi pubblicati in su, e niente aggregateRating nei dati strutturati. */
    if (($mediaVoci = MHW\Testimonianze::media())): ?>
      <p class="voci__media"><span class="stelline" aria-hidden="true">★</span><b><?= Support::e(str_replace('.', ',', (string) $mediaVoci['media'])) ?> su 5</b> da <?= (int) $mediaVoci['n'] ?> host</p>
    <?php endif; ?></div>
  <div class="grid grid-3" style="margin-top:24px">
    <?php foreach ($testimonianze as $t): ?>
      <figure class="panel voce">
        <?php if ((int) $t['rating'] > 0): ?><span class="stelline voce__stelle" role="img" aria-label="<?= (int) $t['rating'] ?> su 5"><?= str_repeat('★', (int) $t['rating']) . str_repeat('☆', 5 - (int) $t['rating']) ?></span><?php endif; ?>
        <blockquote><p><?= Support::e($t['body']) ?></p></blockquote>
        <figcaption>
          <?php if ($t['foto']): ?><img src="<?= Support::e($t['foto']) ?>" alt="" width="44" height="44" loading="lazy"><?php endif; ?>
          <span><b><?= Support::e($t['name']) ?></b><?php if ($t['property_name'] !== ''): ?><span class="small muted"><?= Support::e($t['property_name'] . ($t['city'] !== '' ? ', ' . $t['city'] : '')) ?></span><?php endif; ?></span>
        </figcaption>
      </figure>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php $legale = MHW\Config::get('legal');
$inviti = MHW\Inviti::disponibili(); /* «Porta un amico» compare (qui e nelle FAQ) solo se è acceso */
$waFaq = preg_replace('/\D/', '', (string) ($legale['contact_whatsapp'] ?? '')); ?>
<section id="piani" class="blocco" aria-labelledby="piani-titolo">
  <div class="spread">
    <div class="stack stack--sm">
      <span class="kicker"><?= $T('piani.kicker') ?></span>
      <h2 id="piani-titolo" class="h-sezione"><?= $T('piani.titolo') ?></h2>
    </div>
    <p class="muted" style="max-width:400px;line-height:24px"><?= $T('piani.lead') ?></p>
  </div>

  <?php $vociPiano = fn(array $p) => $p['bullet_list']; ?>
  <div class="grid grid-3 piani" style="margin-top:28px">
    <?php foreach ($offers as $of): $p = $of['main']; $famiglia = count($of['options']) > 1;
          $scuro = $p['badge'] !== '';
          $nome = $famiglia ? preg_replace('/\s*\d+$/', '', $p['name']) : $p['name'];
          $primo = $of['options'][0];
          $idSel = 'strutture-' . Support::slug($nome); ?>
      <div class="plan <?= $scuro ? 'plan--dark' : '' ?>">
        <?php /* Le righe della scheda (testa, voci, quantità, prezzo, bottone) sono le stesse nelle tre schede:
                 con la subgrid di .piani prezzi e bottoni stanno in fondo, alla stessa altezza. */ ?>
        <div class="plan__testa">
          <div class="spread spread--mid" style="gap:12px;align-items:center">
            <span class="plan__nome"><?= Support::e($nome) ?></span>
            <?php if ($p['badge'] !== ''): ?><span class="badge badge--ochre-strong"><?= Support::e($p['badge']) ?></span><?php endif; ?>
          </div>
          <?php if (trim((string) $p['tagline']) !== ''): /* il posizionamento: per chi è indicato */ ?>
            <p class="plan__per"><b>Ideale per</b> <?= Support::e(preg_replace('/^Per /u', '', trim((string) $p['tagline']))) ?></p>
          <?php endif; ?>
        </div>

        <?php if (Plans::perProperty($primo)): /* Portfolio a quantità: un modulo vero. Senza JavaScript il
                  bottone manda comunque la scelta; lo script calcola solo il totale mostrato,
                  con base e costo aggiuntivo presi dal listino. */
              $minimo = (int) $primo['min_quantity']; ?>
          <form class="plan__scelta" method="get" action="<?= b() . ($dentro ? '/piano' : '/registrati') ?>" data-portfolio
                data-quantita data-base="<?= (int) $primo['price_cents'] ?>" data-extra="<?= (int) $primo['extra_price_cents'] ?>" data-scaglioni="<?= Support::e(Plans::tiersJson($primo)) ?>" data-valuta="<?= Support::e($primo['currency']) ?>">
            <input type="hidden" name="piano" value="<?= (int) $primo['pv_id'] ?>">
            <?php $voci = $vociPiano($p); include __DIR__ . '/_voci_piano.php'; ?>
            <div class="plan__quante">
              <?php $pvS = $primo; $qS = $minimo; include __DIR__ . '/_scaglioni.php'; ?>
              <label for="<?= $idSel ?>" class="plan__label">Quante strutture vuoi gestire?</label>
              <input id="<?= $idSel ?>" name="strutture" type="number" inputmode="numeric" step="1" required
                     min="<?= $minimo ?>" max="<?= (int) $primo['max_quantity'] ?>" value="<?= $minimo ?>">
              <div class="plan__oltre" data-oltre hidden><p>Più di <?= (int) $primo['max_quantity'] ?> strutture? Ti facciamo una proposta.</p>
                <a class="btn btn--sm" href="<?= Support::e(Support::mailtoPreventivo()) ?>">Chiedi un preventivo</a></div>
            </div>
            <div class="plan__prezzo">
              <span class="price" aria-live="polite"><span data-totale><?= Support::e(Support::money(Plans::price($primo, $minimo), $primo['currency'])) ?></span><small> + IVA / anno</small></span>
              <span class="plan__mese">circa <span data-mensile><?= Support::e(Support::money(Plans::monthly(Plans::price($primo, $minimo)), $primo['currency'])) ?></span> al mese</span>
            </div>
            <button class="btn"><?= Support::e($p['cta_label'] ?: 'Scegli ' . $nome) ?></button>
          </form>
        <?php else: ?>
          <?php $voci = $vociPiano($p); include __DIR__ . '/_voci_piano.php'; ?>
          <div class="plan__quante"></div>
          <div class="plan__prezzo">
            <span class="price"><?= Support::e(Support::money((int) $primo['price_cents'], $primo['currency'])) ?><small> + IVA / anno</small></span>
            <span class="plan__mese">circa <?= Support::e(Support::money(Plans::monthly((int) $primo['price_cents']), $primo['currency'])) ?> al mese</span>
          </div>
          <a class="btn" href="<?= $vai((int) $p['pv_id']) ?>"><?= Support::e($p['cta_label'] ?: 'Scegli ' . $nome) ?></a>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
  <?php include __DIR__ . '/_confronto.php'; ?>
  <p class="piani__nota"><?= $T('piani.nota') ?></p>
</section>

<?php if ($inviti): /* Porta un amico: le percentuali arrivano da Inviti, nessun numero scritto qui.
   Sta dopo i piani: chi ha appena scelto vede subito come pagare meno l'anno dopo. */
      $passi = MHW\Inviti::amiciMassimi(); ?>
<section id="amico" class="blocco amico" aria-labelledby="amico-titolo">
  <div class="amico__testo">
    <?php /* Le percentuali arrivano dal programma; nel titolo {max} è in evidenza. */
          $pct = ['amico' => MHW\Inviti::AMICO . '%', 'passo' => MHW\Inviti::PASSO . '%', 'max' => MHW\Inviti::MASSIMO . '%']; ?>
    <span class="kicker"><?= $T('amico.kicker') ?></span>
    <h2 id="amico-titolo" class="h-sezione"><?= $T('amico.titolo', ['max' => '<span class="amico__cifra">' . MHW\Inviti::MASSIMO . '%</span>'] + $pct) ?></h2>
    <p class="amico__lead"><?= $T('amico.lead', $pct) ?></p>
    <div class="row">
      <a class="btn" href="<?= b() . ($dentro ? '/inviti' : '/registrati') ?>"><?= $dentro ? 'Invita un amico' : $T('cta.crea') ?></a>
      <a class="btn btn--ghost" href="#faq-porta-amico"><?= $T('amico.bottone') ?></a>
    </div>
  </div>
  <div class="amico__conto">
    <ol class="amico__passi">
      <?php foreach ([1, 2, 3] as $i): ?><li><b><?= $T("amico.$i.titolo", $pct) ?></b><?= $T("amico.$i.testo", $pct) ?></li><?php endforeach; ?>
    </ol>
    <div class="amico__barra" role="img" aria-label="<?= $passi ?> amici: <?= MHW\Inviti::MASSIMO ?>% di sconto sul rinnovo">
      <?php for ($n = 1; $n <= $passi; $n++): ?><span class="amico__tacca" style="opacity:<?= round(.3 + .7 * $n / $passi, 2) ?>"></span><?php endfor; ?>
    </div>
    <div class="amico__scala" aria-hidden="true"><span>1 amico · −<?= MHW\Inviti::PASSO ?>%</span><span><?= $passi ?> amici · −<?= MHW\Inviti::MASSIMO ?>%</span></div>
  </div>
</section>
<?php endif; ?>

<section id="domande" class="blocco faq" aria-labelledby="faq-titolo">
  <div class="faq__testa">
    <span class="kicker"><?= $T('faq.kicker') ?></span>
    <h2 id="faq-titolo" class="h-sezione"><?= $T('faq.titolo') ?></h2>
    <?php if (($legale['contact_email'] ?? '') !== '' || $waFaq !== ''): ?>
      <p class="muted"><?= $T('faq.aiuto') ?></p>
      <div class="faq__contatti">
        <?php if ($waFaq !== ''): ?><a class="btn btn--ghost" href="https://wa.me/<?= Support::e($waFaq) ?>" rel="noopener"><?= Icon::svg('whatsapp', 18, 1.8) ?>WhatsApp</a><?php endif; ?>
        <?php if (($legale['contact_email'] ?? '') !== ''): ?><a class="btn btn--ghost" href="mailto:<?= Support::e($legale['contact_email']) ?>"><?= Icon::svg('message', 18, 1.8) ?>Email</a><?php endif; ?>
      </div>
      <?php if (($legale['contact_email'] ?? '') !== ''): /* chi non ha un programma di posta sul computer lo copia da qui */ ?>
        <p class="small muted faq__indirizzo"><?= $T('faq.oppure') ?> <b><?= Support::e($legale['contact_email']) ?></b></p>
      <?php endif; ?>
    <?php endif; ?>
  </div>
  <div class="faq__lista">
    <?php [$faqVista, $faqAltre] = MHW\Faq::perLanding();
    foreach ($faqVista as $i => $q): ?>
      <details class="faq__voce" id="faq-<?= Support::e($q['id']) ?>"<?= $i === 0 ? ' open' : '' ?>>
        <summary><?= Support::e($q['d']) ?><?= Icon::svg('plus', 18, 2, 'faq__segno') ?></summary>
        <p><?= Support::e($q['r']) ?></p>
      </details>
    <?php endforeach; ?>
    <?php if ($faqAltre): /* le altre, tutte insieme: si aprono con un tocco */ ?>
      <details class="faq__voce faq__altre">
        <summary><?= $T('faq.altre') ?> (<?= count($faqAltre) ?>)<?= Icon::svg('plus', 18, 2, 'faq__segno') ?></summary>
        <div class="faq__altre-lista">
          <?php foreach ($faqAltre as $q): ?>
            <details class="faq__voce" id="faq-<?= Support::e($q['id']) ?>">
              <summary><?= Support::e($q['d']) ?><?= Icon::svg('plus', 18, 2, 'faq__segno') ?></summary>
              <p><?= Support::e($q['r']) ?></p>
            </details>
          <?php endforeach; ?>
        </div>
      </details>
    <?php endif; ?>
    <p class="faq__tutte"><a href="<?= b() ?>/domande"><?= $T('faq.tutte') ?></a></p>
  </div>
</section>


<section class="chiusura" aria-labelledby="chiusura-titolo">
  <div class="chiusura__testo">
    <h2 id="chiusura-titolo" class="chiusura__titolo"><?= $T('chiusura.titolo') ?></h2>
    <p><?= $T('chiusura.testo') ?></p>
    <div class="row">
      <a class="btn btn--lg btn--go" href="<?= $crea ?>"><?= $ctaCrea() ?> <span class="go"><?= Icon::svg('arrow', 19, 2) ?></span></a>
      <?php if ($demoUrl): ?><a class="btn btn--lg btn--ghost" href="<?= $demoUrl ?>"><?= $T('cta.demo') ?></a><?php endif; ?>
    </div>
  </div>
  <aside class="chi" aria-label="Chi c'è dietro MyHouse Welcome">
    <span class="chi__marchio"><?= Icon::brand(28) ?></span>
    <p class="chi__titolo"><?= $T('chi.titolo') ?></p>
    <p><?= $T('chi.testo') ?></p>
  </aside>
</section>

<?php /* 8A: «Anche il tuo sito» a domande. Fondo nero con una grana leggera e la foto di sempre molto
   scurita dietro; a sinistra MyHouse (un servizio a parte), a destra il percorso (pub/_funnel_sito.php):
   due domande e il contatto, con le risposte nel link, nel messaggio e in Amministrazione → Prospetti. */ ?>
<section id="sito" class="sito2" aria-labelledby="sito-titolo">
  <?php $sitoPers = TestiSito::immagine('sito'); ?>
  <picture class="sito2__foto" aria-hidden="true">
    <?php if (!$sitoPers['caricata']): ?><source type="image/webp" srcset="<?= Support::e(a('/assets/foto/sito-800.webp')) ?> 800w, <?= Support::e(a('/assets/foto/sito-1600.webp')) ?> 1600w" sizes="100vw"><?php endif; ?>
    <img src="<?= Support::e($sitoPers['caricata'] ? $sitoPers['url'] : a('/assets/foto/sito.jpg')) ?>" alt="" loading="lazy" decoding="async" width="1600" height="1067"<?= $F('sito') ?>>
  </picture>
  <div class="sito2__dentro">
    <div class="sito2__testo">
      <img class="sito2__logo" src="<?= Support::e(a('/assets/myhouse/myhouse-logo-bianco.png')) ?>" alt="<?= TestiSito::testo('sito2.logo') ?>" width="160" height="52" loading="lazy">
      <span class="sito2__kicker"><?= $T('sito2.kicker') ?></span>
      <h2 id="sito-titolo" class="sito2__titolo"><?= $T('sito2.titolo') ?><br><span class="sito2__accento"><?= $T('sito2.accento') ?></span></h2>
      <p class="sito2__lead"><?= $T('sito2.lead') ?></p>
      <p class="sito2__avviso"><?= Icon::svg('info', 16, 2) ?><span><?= $T('sito2.avviso') ?></span></p>
    </div>
    <div class="sito2__scheda">
      <?php $funnel = ['da' => 'home', 'id' => 'fs-home']; include __DIR__ . '/_funnel_sito.php'; ?>
    </div>
  </div>
</section>
<script src="<?= MHW\av('/assets/funnel-sito.js') ?>" defer></script>

<script src="<?= MHW\av('/assets/prezzi.js') ?>" defer></script>
<script src="<?= MHW\av('/assets/landing.js') ?>" defer></script>
<?php if ($anteprimaTesti): /* dentro l'iframe di Amministrazione → Testi e foto: riceve i testi mentre si scrivono */ ?>
<script src="<?= MHW\av('/assets/anteprima-testi.js') ?>" defer></script>
<?php endif; ?>
