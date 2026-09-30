<?php
/* La landing. Ogni blocco ha un compito solo:
     hero        che cos'è — e il prodotto si vede subito, sul telefono
     prodotto    cosa trova l'ospite
     il tempo    perché conviene: domande ripetute, chiarezza, valore del canone
     come        quanto è semplice
     QR          come si condivide
     piani       scegliere
     chiusura    cominciare
   Prezzi, nomi ed elenchi dei piani arrivano dal database (li cambia
   l'amministratore). Nessuna testimonianza e nessun numero inventato: la demo
   è dichiarata come demo, le domande sono esempi. */
use function MHW\{a, b};
use MHW\{Support, Icon, Plans};
$title = 'MyHouse Welcome — la guida digitale della tua struttura';
$dentro = !empty($user);
$vai = fn(int $pv) => b() . ($dentro ? '/piano?piano=' : '/registrati?piano=') . $pv;
$crea = b() . ($dentro ? '/pannello' : '/registrati');
$demoUrl = $demo ? b() . '/g/' . Support::e($demo['slug']) . '/benvenuto' : null;
$foto = a('/assets/foto/borgo.jpg');
$fotoTelefono = a('/assets/foto/borgo-telefono.jpg');
$nomeDemo = $demo['name'] ?? 'Casa Lucia';
$elementi = [['home', 'Check-in & Check-out'], ['wifi', 'Wi-Fi'], ['pin', 'Come arrivare'], ['car', 'Parcheggio'],
             ['washer', 'Servizi'], ['doc', 'Regole della casa'], ['fork', 'Dove mangiare e bere'], ['globe', 'Cosa fare e vedere']]; ?>

<section class="hero2">
  <div class="hero2__testo">
    <h1 class="display">La casa risponde<br>prima che chiedano.</h1>
    <p class="hero2__sub">La guida digitale per case vacanza, B&amp;B e agriturismi. Check-in, Wi-Fi, regole e consigli
      locali in un unico link, da aprire anche con un QR Code.</p>
    <div class="row">
      <a class="btn btn--lg btn--go" href="<?= $crea ?>">Crea gratis la tua guida <span class="go"><?= Icon::svg('arrow', 19, 2) ?></span></a>
      <?php if ($demoUrl): ?><a class="btn btn--lg btn--ghost" href="<?= $demoUrl ?>">Guarda la demo</a><?php endif; ?>
    </div>
    <p class="micro micro--left"><span>Nessuna app</span><span>Anteprima gratuita</span><span>Paghi solo quando pubblichi</span></p>
  </div>

  <?php /* Il telefono: una schermata della guida disegnata in HTML, sempre nel
     tema chiaro della guida, qualunque sia il tema del sito. */ ?>
  <div class="device" role="img" aria-label="La guida di <?= Support::e($nomeDemo) ?> sullo schermo di uno smartphone">
    <div class="device__screen" aria-hidden="true">
      <div class="device__status"><span>9:41</span><span class="device__island"></span>
        <span class="device__icons"><i class="sig"></i><i class="bat"></i></span></div>
      <div class="device__app">
        <div class="device__top"><span class="device__avatar">CL</span><span><?= Support::e($nomeDemo) ?></span>
          <?php if ($demo): ?><span class="demo-tag">Demo</span><?php endif; ?></div>
        <span class="device__title">Benvenuti<br>a <?= Support::e($nomeDemo) ?>.</span>
        <div class="device__shot"><img src="<?= Support::e($fotoTelefono) ?>" alt="" loading="eager">
          <span class="device__pill"><i></i>Check-in dalle 15:00</span></div>
        <div class="device__tiles">
          <span class="t-terracotta"><?= Icon::svg('home', 16, 1.8) ?>Check-in &amp; Check-out</span>
          <span class="t-sea"><?= Icon::svg('wifi', 16, 1.8) ?>Wi-Fi</span>
          <span class="t-pine"><?= Icon::svg('fork', 16, 1.8) ?>Dove mangiare</span>
          <span class="t-ochre"><?= Icon::svg('car', 16, 1.8) ?>Parcheggio</span>
        </div>
      </div>
      <span class="device__home"></span>
    </div>
  </div>
</section>

<div class="stage">
  <img src="<?= Support::e($foto) ?>" alt="Facciata in pietra in un borgo medievale, con una scalinata e gerani alle finestre" fetchpriority="high">
  <?php if ($demo): ?>
    <span class="shot-pill"><span class="demo-tag">Demo</span><?= Support::e($demo['name']) ?><?= $demo['city'] ? ' · ' . Support::e($demo['city']) : '' ?></span>
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

<?php /* Il tempo che non vedi: il problema, la risposta, la chiarezza, il valore
   del canone. Le domande sono esempi, non testimonianze; nessun numero. */ ?>
<section id="il-tempo" class="tempo" aria-labelledby="tempo-titolo">
  <div class="tempo__intro">
    <span class="kicker">Il tempo che non vedi</span>
    <h2 id="tempo-titolo" class="tempo__titolo">Ogni ospite è nuovo.<br>Le domande sono quasi sempre le stesse.</h2>
    <p class="tempo__firma">MyHouse Welcome nasce da chi conosce da vicino il lavoro di chi ospita: sappiamo quali domande
      arrivano a ogni soggiorno.</p>
  </div>

  <div class="tempo__grid">
    <figure class="tempo__msgs">
      <ul class="msgs" aria-label="Domande che arrivano spesso">
        <?php foreach ([['car', 'Ciao! Dove possiamo parcheggiare?'], ['wifi', 'Buongiorno, qual è la password del Wi-Fi?'],
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
      <h3 class="tempo__h3">Le risposte le prepari una volta.</h3>
      <p>Raccogli le informazioni della struttura in una guida da aprire con un link o un QR Code. Gli ospiti la
        consultano quando serve, anche prima dell'arrivo; tu la aggiorni quando cambia qualcosa.</p>
      <p class="tempo__frase">Meno tempo a ripetere. Più tempo per ciò che conta davvero.</p>
    </div>
  </div>

  <div class="chiaro">
    <div class="chiaro__testa">
      <h3 class="tempo__h3">Meno dubbi all'arrivo.<br>Meno equivoci alla partenza.</h3>
      <p class="muted">Regole, orari e indicazioni scritti con chiarezza e sempre consultabili aiutano a prevenire
        incomprensioni, per l'ospite e per te.</p>
    </div>
    <ol class="chiaro__tappe">
      <li><span class="kicker">Prima dell'arrivo</span>Orari e indicazioni per raggiungerti.</li>
      <li><span class="kicker">Durante il soggiorno</span>Wi-Fi, servizi, regole e consigli.</li>
      <li><span class="kicker">Alla partenza</span>Orario e istruzioni di check-out.</li>
    </ol>
  </div>

  <div class="tempo__valore">
    <span class="kicker">Il valore del canone</span>
    <h3 class="tempo__h3">La guida si paga una volta l'anno.<br>Le domande arrivano tutto l'anno.</h3>
    <p>Non paghi una pagina con un QR: paghi uno strumento che mette le risposte a disposizione degli ospiti, a ogni
      soggiorno, mentre tu fai altro. Anche pochi minuti in meno per ospite, ripetuti durante l'anno, possono tradursi
      in tempo prezioso.</p>
    <p class="tempo__frase">Il valore non è soltanto nella guida, ma nel tempo che ti aiuta a recuperare.</p>
  </div>

  <div class="tempo__cta">
    <a class="btn btn--lg btn--go" href="<?= $crea ?>">Crea gratis la tua guida <span class="go"><?= Icon::svg('arrow', 19, 2) ?></span></a>
    <?php if ($demoUrl): ?><a class="btn btn--lg btn--ghost" href="<?= $demoUrl ?>">Guarda la demo</a><?php endif; ?>
  </div>
</section>

<section id="come-funziona" class="blocco" aria-labelledby="come-titolo">
  <div class="stack stack--sm" style="margin-bottom:28px">
    <span class="kicker">Come funziona</span>
    <h2 id="come-titolo" class="h-sezione">Pronta in pochi minuti.</h2>
  </div>
  <div class="howto">
    <div class="step-card"><span class="big">01</span><b>Inserisci le informazioni</b>
      <p class="muted">Una procedura guidata ti chiede solo quello che serve: check-in, Wi-Fi, regole, consigli.</p></div>
    <div class="step-card"><span class="big">02</span><b>Guarda l'anteprima</b>
      <p class="muted">Vedi la guida come la vedranno gli ospiti sul telefono, prima di pagare.</p></div>
    <div class="step-card"><span class="big">03</span><b>Pubblica e condividi</b>
      <p class="muted">Attivi il piano, scarichi il QR Code e mandi il link agli ospiti.</p></div>
  </div>
</section>

<section id="qr" class="band qrband" aria-labelledby="qr-titolo">
  <div class="stack" style="gap:18px;max-width:520px">
    <h2 id="qr-titolo" class="h-sezione">Un QR. Tutta la struttura.</h2>
    <ul class="spunte">
      <li><?= Icon::svg('check', 18, 2) ?>Un solo QR, sempre valido: lo stampi una volta.</li>
      <li><?= Icon::svg('check', 18, 2) ?>Aggiorni la guida quando vuoi e pubblichi le modifiche con un clic.</li>
      <li><?= Icon::svg('check', 18, 2) ?>Non devi ristampare niente a ogni modifica.</li>
      <li><?= Icon::svg('check', 18, 2) ?>Puoi mandarla anche come link, prima dell'arrivo.</li>
    </ul>
  </div>
  <div class="qr-sheet" style="width:220px">
    <?php /* Se c'è la demo, il QR la apre davvero: provalo col telefono. */ ?>
    <?= preg_replace('/width="\d+" height="\d+"/', 'width="160" height="160"',
                     MHW\QrExport::svg($demo ? Support::baseUrl() . '/g/' . $demo['slug'] . '/benvenuto' : Support::baseUrl())) ?>
    <p class="small" style="margin-top:10px;color:#231b12"><?= $demo ? 'Inquadra: si apre la demo' : 'Inquadra per la guida' ?></p>
  </div>
</section>

<section id="piani" class="blocco" aria-labelledby="piani-titolo">
  <div class="spread">
    <div class="stack stack--sm">
      <span class="kicker">Piani</span>
      <h2 id="piani-titolo" class="h-sezione">Scegli il piano.</h2>
    </div>
    <p class="muted" style="max-width:380px;line-height:24px">Crei e provi la guida gratis. Paghi solo alla pubblicazione:
      abbonamento annuale, IVA esclusa, rinnovo disattivabile quando vuoi.</p>
  </div>

  <div class="grid grid-3 piani" style="margin-top:28px">
    <?php foreach ($offers as $of): $p = $of['main']; $famiglia = count($of['options']) > 1;
          $scuro = $p['badge'] !== '';
          $min = min(array_map(fn($o) => (int) $o['price_cents'], $of['options'])); ?>
      <div class="plan <?= $scuro ? 'plan--dark' : '' ?>">
        <div class="spread spread--mid" style="gap:12px;align-items:center">
          <span class="plan__nome"><?= Support::e($famiglia ? preg_replace('/\s*\d+$/', '', $p['name']) : $p['name']) ?></span>
          <?php if ($p['badge'] !== ''): ?><span class="badge badge--ochre-strong"><?= Support::e($p['badge']) ?></span><?php endif; ?>
        </div>
        <span class="plan__headline"><?= Support::e($p['headline']) ?></span>
        <span class="price"><?= $famiglia ? '<small class="plan__da">da </small>' : '' ?><?= Support::e(Support::money($min, $p['currency'])) ?><small> + IVA / anno</small></span>
        <ul class="plan__lista">
          <?php foreach ($p['bullet_list'] as $bl): ?><li><?= Icon::svg('check', 16, 2) ?><span><?= Support::e($bl) ?></span></li><?php endforeach; ?>
        </ul>
        <a class="btn <?= $scuro ? '' : 'btn--ghost' ?>" href="<?= $vai((int) $p['pv_id']) ?>"><?= Support::e($p['cta_label'] ?: 'Scegli ' . $p['name']) ?></a>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section class="band" style="margin-top:72px">
  <div class="stack" style="gap:12px">
    <h2 style="font-size:clamp(30px,4vw,42px);line-height:1">Pochi minuti oggi,<br>una stagione più tranquilla.</h2>
    <p class="muted" style="font-size:17px;line-height:26px;max-width:480px">Crea la guida, guardala sul telefono e decidi
      dopo se pubblicarla.</p>
  </div>
  <div class="row">
    <a class="btn btn--lg btn--go" href="<?= $crea ?>">Crea gratis la tua guida <span class="go"><?= Icon::svg('arrow', 19, 2) ?></span></a>
    <?php if ($demoUrl): ?><a class="btn btn--lg btn--ghost" href="<?= $demoUrl ?>">Guarda la demo</a><?php endif; ?>
  </div>
</section>
