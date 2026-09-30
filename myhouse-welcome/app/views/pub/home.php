<?php
/* La landing. Ogni blocco ha un compito solo:
     hero        che cos'è — e il prodotto si vede subito, sul telefono
     prodotto    cosa trova l'ospite
     il tempo    perché conviene al proprietario
     chi         chi c'è dietro
     come        quanto è semplice cominciare
     QR          come si condivide
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
$elementi = [['home', 'Check-in & Check-out'], ['wifi', 'Wi-Fi'], ['pin', 'Come arrivare'], ['car', 'Parcheggio'],
             ['washer', 'Servizi'], ['doc', 'Regole della casa'], ['fork', 'Dove mangiare'], ['bin', 'Rifiuti e raccolta differenziata']]; ?>

<section class="hero2">
  <div class="hero2__testo">
    <span class="kicker">La reception digitale per la tua struttura ricettiva</span>
    <h1 class="display">La casa risponde<br>prima che chiedano.</h1>
    <p class="hero2__sub">La guida digitale per case vacanza, B&amp;B, affittacamere e agriturismi. Check-in, Wi-Fi,
      parcheggio, regole e consigli locali in un unico link, da condividere anche tramite QR Code.</p>
    <div class="row">
      <a class="btn btn--lg btn--go" href="<?= $crea ?>">Crea gratis la tua guida <span class="go"><?= Icon::svg('arrow', 19, 2) ?></span></a>
      <?php if ($demoUrl): ?><a class="btn btn--lg btn--ghost" href="<?= $demoUrl ?>">Guarda la demo</a><?php endif; ?>
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
          <span class="device__top"><span class="device__avatar">CL</span><span><?= Support::e($nomeDemo) ?></span>
            <?php if ($demo): ?><span class="demo-tag">Demo</span><?php endif; ?></span>
          <span class="device__title">Benvenuti<br>a <?= Support::e($nomeDemo) ?>.</span>
          <span class="device__shot"><picture><source srcset="<?= Support::e($telefonoWebp) ?>" type="image/webp">
            <img src="<?= Support::e($telefonoJpg) ?>" alt="" width="600" height="422" loading="eager" decoding="async"></picture>
            <span class="device__pill"><i></i>Check-in dalle 15:00</span></span>
          <span class="device__tiles">
            <span class="t-terracotta"><?= Icon::svg('home', 16, 1.8) ?>Check-in &amp; Check-out</span>
            <span class="t-sea"><?= Icon::svg('wifi', 16, 1.8) ?>Wi-Fi</span>
            <span class="t-pine"><?= Icon::svg('fork', 16, 1.8) ?>Dove mangiare</span>
            <span class="t-ochre"><?= Icon::svg('car', 16, 1.8) ?>Parcheggio</span>
          </span>
        </span>
        <span class="device__home"></span>
      </span>
    </span>
    <?php if ($demoUrl): ?><span class="device-link__invito">Scopri come la vedranno i tuoi ospiti <?= Icon::svg('arrow', 15, 2) ?></span><?php endif; ?>
  </<?= $tag ?>>
</section>

<div class="stage">
  <picture>
    <source type="image/webp" srcset="<?= Support::e($fotoSet) ?>" sizes="(max-width: 1240px) 100vw, 1160px">
    <img src="<?= Support::e($fotoJpg) ?>" width="2000" height="924" fetchpriority="high" decoding="async"
         alt="Facciata in pietra in un borgo medievale, con una scalinata e gerani alle finestre">
  </picture>
  <?php if ($demo): ?>
    <span class="shot-pill"><span class="demo-tag">Demo</span>— <?= Support::e($demo['name']) ?></span>
  <?php endif; ?>
</div>

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
    <div class="step-card"><span class="big">01</span><b>Crea la tua guida.</b>
      <p class="muted">Inserisci le informazioni della struttura e scegli cosa condividere con gli ospiti.</p></div>
    <div class="step-card"><span class="big">02</span><b>Personalizza e guarda l'anteprima.</b>
      <p class="muted">Vedi come apparirà la guida sullo smartphone, prima di pubblicarla.</p></div>
    <div class="step-card"><span class="big">03</span><b>Pubblica e condividi.</b>
      <p class="muted">Attiva il piano e condividi la guida tramite link o QR Code.</p></div>
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

        <?php if ($famiglia): /* Portfolio: un modulo vero. Senza JavaScript il bottone manda
                  comunque la scelta; lo script aggiorna solo il prezzo, preso dal listino. */ ?>
          <form class="plan__scelta" method="get" action="<?= b() . ($dentro ? '/piano' : '/registrati') ?>" data-portfolio>
            <label for="<?= $idSel ?>" class="plan__label">Quante strutture vuoi gestire?</label>
            <select id="<?= $idSel ?>" name="piano">
              <?php foreach ($of['options'] as $o): ?>
                <option value="<?= (int) $o['pv_id'] ?>" data-prezzo="<?= Support::e(Support::money((int) $o['price_cents'], $o['currency'])) ?>"><?= Support::e($o['tagline'] ?: $o['name']) ?></option>
              <?php endforeach; ?>
            </select>
            <span class="price" aria-live="polite"><span data-prezzo-mostrato><?= Support::e(Support::money((int) $primo['price_cents'], $primo['currency'])) ?></span><small> + IVA / anno</small></span>
            <ul class="plan__lista">
              <?php foreach ($p['bullet_list'] as $bl): ?><li><?= Icon::svg('check', 16, 2) ?><span><?= Support::e($bl) ?></span></li><?php endforeach; ?>
            </ul>
            <button class="btn <?= $scuro ? '' : 'btn--ghost' ?>"><?= Support::e($p['cta_label'] ?: 'Scegli ' . $nome) ?></button>
          </form>
        <?php else: ?>
          <span class="price"><?= Support::e(Support::money((int) $primo['price_cents'], $primo['currency'])) ?><small> + IVA / anno</small></span>
          <ul class="plan__lista">
            <?php foreach ($p['bullet_list'] as $bl): ?><li><?= Icon::svg('check', 16, 2) ?><span><?= Support::e($bl) ?></span></li><?php endforeach; ?>
          </ul>
          <a class="btn <?= $scuro ? '' : 'btn--ghost' ?>" href="<?= $vai((int) $p['pv_id']) ?>"><?= Support::e($p['cta_label'] ?: 'Scegli ' . $nome) ?></a>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
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

<script>
/* Portfolio: il prezzo mostrato segue la scelta. I prezzi arrivano dal listino
   (attributo data-prezzo di ogni opzione), qui non ce n'è nessuno scritto. */
document.querySelectorAll('[data-portfolio]').forEach(function (form) {
  var sel = form.querySelector('select'), out = form.querySelector('[data-prezzo-mostrato]');
  function aggiorna() { var o = sel.options[sel.selectedIndex]; if (o) out.textContent = o.getAttribute('data-prezzo'); }
  sel.addEventListener('change', aggiorna);
  aggiorna();
});
</script>
