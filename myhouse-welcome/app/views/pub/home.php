<?php
/* La landing. Tutti i prezzi, i nomi e gli elenchi dei piani arrivano dal
   database: li cambia l'amministratore. Nessun numero di "riprova sociale":
   la demo è dichiarata come demo, e basta. */
use function MHW\b;
use MHW\{Support, Icon, Plans};
$title = 'MyHouse Welcome — la reception digitale della tua struttura';
$dentro = !empty($user);
$vai = fn(int $pv) => b() . ($dentro ? '/piano?piano=' : '/registrati?piano=') . $pv;
$crea = b() . ($dentro ? '/pannello' : '/registrati');
$elementi = [['home', 'Check-in & Check-out'], ['wifi', 'Wi-Fi'], ['pin', 'Come arrivare'], ['key', 'Parcheggio'],
             ['washer', 'Servizi'], ['doc', 'Regole della casa'], ['fork', 'Dove mangiare e bere'], ['globe', 'Cosa fare e vedere']]; ?>

<section class="hero">
  <h1 class="display">La casa risponde<br>prima che chiedano.</h1>
  <p>La reception digitale per case vacanza, B&amp;B, affittacamere e agriturismi. Check-in, Wi-Fi, parcheggio,
     regole e consigli locali in un unico link, sempre aggiornabile e accessibile da QR Code.</p>
  <div class="row" style="justify-content:center">
    <a class="btn btn--lg btn--go" href="<?= $crea ?>">Crea gratis la tua guida <span class="go"><?= Icon::svg('arrow', 19, 2) ?></span></a>
    <?php if ($demo): ?><a class="btn btn--lg btn--ghost" href="<?= b() ?>/g/<?= Support::e($demo['slug']) ?>/benvenuto">Guarda la demo</a><?php endif; ?>
  </div>
  <p class="micro"><span>Nessuna app</span><span>Aggiornabile quando vuoi</span><span>Paghi solo quando pubblichi</span></p>
</section>

<div class="shot shot--wide shot--h380">
  <img src="<?= Support::e($copertina) ?>" alt="Una casa in pietra, la facciata con le persiane verdi" fetchpriority="high">
  <?php if ($demo): ?>
    <span class="shot-pill"><span class="demo-tag">Demo</span><?= Support::e($demo['name']) ?><?= $demo['city'] ? ' · ' . Support::e($demo['city']) : '' ?></span>
  <?php endif; ?>
</div>

<?php /* Il tempo che non vedi: il problema riconoscibile, la svolta, i tre
   momenti del soggiorno, il valore del canone. Niente numeri inventati: le
   domande sono esempi illustrativi, non testimonianze. I link sono gli stessi
   della hero ($crea e la demo), nessuna logica nuova. */ ?>
<section id="il-tempo" class="tempo" aria-labelledby="tempo-titolo">
  <div class="tempo__intro">
    <span class="kicker">Il tempo che non vedi</span>
    <h2 id="tempo-titolo" class="tempo__titolo">Ogni ospite è nuovo.<br>Le domande sono quasi sempre le stesse.</h2>
    <p class="lead">Un messaggio per il parcheggio. Uno per il Wi-Fi. Un altro per gli orari di partenza. Sono piccole
      richieste, ma quando si ripetono a ogni soggiorno finiscono per occupare una parte del tuo tempo.</p>
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
      <h3 class="tempo__h3">Le risposte le prepari una volta. Non devi riscriverle ogni volta.</h3>
      <p>Con MyHouse Welcome raccogli le informazioni della tua struttura in una guida digitale, accessibile con un semplice
        link o QR Code.</p>
      <p>La condividi con gli ospiti, anche prima dell'arrivo. Loro possono consultarla quando ne hanno bisogno, mentre tu
        puoi aggiornarla quando cambiano le informazioni.</p>
      <p class="tempo__frase">Meno tempo a ripetere le stesse cose. Più tempo per ciò che conta davvero.</p>
    </div>
  </div>

  <div class="momenti">
    <?php foreach ([["Prima dell'arrivo", 'Ospiti più preparati.', 'Condividi in anticipo orari, indicazioni per raggiungerti e informazioni utili.'],
                    ['Durante il soggiorno', 'Le risposte sempre a disposizione.', 'Wi-Fi, servizi, regole e consigli possono essere consultati senza doverti chiedere ogni dettaglio.'],
                    ['Prima della partenza', "Indicazioni chiare fino all'ultimo momento.", 'Orari e istruzioni di check-out aiutano a ridurre dubbi ed equivoci prima di lasciare la struttura.']] as $i => [$quando, $titolo, $testo]): ?>
      <div class="momento">
        <span class="kicker"><span class="momento__n"><?= sprintf('%02d', $i + 1) ?></span><?= Support::e($quando) ?></span>
        <b><?= Support::e($titolo) ?></b>
        <p class="muted"><?= Support::e($testo) ?></p>
      </div>
    <?php endforeach; ?>
  </div>
  <p class="tempo__nota muted">Informazioni chiare fin dall'inizio aiutano a prevenire dubbi, richieste dell'ultimo minuto e
    incomprensioni durante il soggiorno e alla partenza.</p>

  <div class="tempo__valore">
    <span class="kicker">Un piccolo investimento nell'organizzazione della tua struttura</span>
    <h3 class="tempo__h3">La guida ha un costo annuale.<br>Le domande arrivano tutto l'anno.</h3>
    <p>Ogni soggiorno può portare nuove richieste e le stesse spiegazioni da ripetere.</p>
    <p>Con MyHouse Welcome organizzi le informazioni una volta, le mantieni aggiornate e le rendi disponibili agli ospiti
      ogni volta che ne hanno bisogno.</p>
    <p>Anche pochi messaggi in meno, ripetuti nel corso dell'anno, possono tradursi in tempo recuperato.</p>
    <p class="tempo__frase">Il valore non è soltanto nella guida. È nel tempo che puoi dedicare ad altro.</p>
  </div>

  <div class="tempo__cta">
    <div class="row" style="justify-content:center">
      <a class="btn btn--lg btn--go" href="<?= $crea ?>">Crea gratis la tua guida <span class="go"><?= Icon::svg('arrow', 19, 2) ?></span></a>
      <?php if ($demo): ?><a class="btn btn--lg btn--ghost" href="<?= b() ?>/g/<?= Support::e($demo['slug']) ?>/benvenuto">Guarda come funziona</a><?php endif; ?>
    </div>
    <p class="small muted">Configura la tua guida e visualizzala sullo smartphone. Paghi solo quando decidi di pubblicarla.</p>
  </div>
</section>

<section style="margin-top:72px" class="split">
  <div class="stack stack--lg">
    <div class="stack stack--sm">
      <h2 style="font-size:clamp(30px,4vw,44px);line-height:1.02">Tutto quello che serve ai tuoi ospiti.<br>In un unico posto.</h2>
      <p class="lead">Check-in, Wi-Fi, parcheggio, regole, servizi e consigli locali sempre disponibili sul loro smartphone.</p>
    </div>
    <div class="features8">
      <?php foreach ($elementi as [$ico, $nome]): ?>
        <div class="feat"><span class="ico"><?= Icon::svg($ico, 22, 1.7) ?></span><b><?= Support::e($nome) ?></b></div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="mockphone" aria-hidden="true">
    <div class="mockphone__screen">
      <div class="mockphone__shot"><img src="<?= Support::e($copertina) ?>" alt=""></div>
      <div class="mockphone__body">
        <span style="font-family:Gloock,Georgia,serif;font-size:26px;line-height:1.05">Benvenuti<br>a <?= Support::e($demo['name'] ?? 'Casa Lucia') ?>.</span>
        <div class="mockphone__grid">
          <span class="t-terracotta">Check-in &amp; Check-out</span><span class="t-sea">Wi-Fi</span>
          <span class="t-pine">Dove mangiare</span><span class="t-ochre">Parcheggio</span>
        </div>
      </div>
    </div>
  </div>
</section>

<section id="come-funziona" style="margin-top:88px;scroll-margin-top:96px">
  <div class="stack stack--sm" style="margin-bottom:28px">
    <span class="kicker">Come funziona</span>
    <h2 style="font-size:clamp(30px,4vw,44px);line-height:1.02">Tre passaggi, pochi minuti.</h2>
  </div>
  <div class="howto">
    <div class="step-card"><span class="big">01</span><b style="font-size:20px;font-weight:500">Rispondi a poche domande</b>
      <p class="muted">Inserisci le informazioni principali della tua struttura. Bastano pochi minuti per iniziare.</p></div>
    <div class="step-card"><span class="big">02</span><b style="font-size:20px;font-weight:500">Guarda subito l'anteprima</b>
      <p class="muted">Vedi in tempo reale come apparirà la guida sullo smartphone dei tuoi ospiti.</p></div>
    <div class="step-card"><span class="big">03</span><b style="font-size:20px;font-weight:500">Pubblica e condividi</b>
      <p class="muted">Attiva il piano, scarica il QR Code e condividi la guida con i tuoi ospiti.</p></div>
  </div>
  <p style="margin-top:28px;text-align:center;font-family:Gloock,Georgia,serif;font-size:clamp(26px,3vw,34px)">Smetti di ripeterti.</p>
</section>

<section id="qr" class="band" style="margin-top:72px;background:var(--sunk);scroll-margin-top:96px">
  <div class="stack" style="gap:14px;max-width:560px">
    <h2 style="font-size:clamp(28px,3.6vw,40px);line-height:1.02">Un QR. Tutta la struttura.</h2>
    <ul class="lined">
      <li>Il QR resta sempre lo stesso: lo stampi una volta.</li>
      <li>La guida la aggiorni quando vuoi, e gli ospiti vedono subito la versione nuova.</li>
      <li>Non devi ristampare niente dopo ogni modifica.</li>
      <li>Puoi condividerla anche come link, via WhatsApp o email, prima dell'arrivo.</li>
    </ul>
  </div>
  <div class="qr-sheet" style="width:220px">
    <?php /* Se c'è la demo, il QR la apre davvero: provalo col telefono. */ ?>
    <?= preg_replace('/width="\d+" height="\d+"/', 'width="160" height="160"',
                     MHW\QrExport::svg($demo ? Support::baseUrl() . '/g/' . $demo['slug'] . '/benvenuto' : Support::baseUrl())) ?>
    <p class="small" style="margin-top:10px;color:#231b12"><?= $demo ? 'Inquadra: si apre la demo' : 'Inquadra per la guida' ?></p>
  </div>
</section>

<section id="piani" style="margin-top:88px;scroll-margin-top:96px">
  <div class="spread">
    <div class="stack stack--sm">
      <span class="kicker">Piani</span>
      <h2 style="font-size:clamp(30px,4.4vw,46px);line-height:1">Crei gratis.<br>Paghi quando pubblichi.</h2>
    </div>
    <p class="muted" style="max-width:360px;line-height:24px">Abbonamento annuale con rinnovo automatico, che puoi
      disattivare quando vuoi. Prezzi IVA esclusa.</p>
  </div>

  <div class="grid grid-3" style="margin-top:28px;align-items:stretch">
    <?php foreach ($offers as $of): $p = $of['main']; $famiglia = count($of['options']) > 1;
          $scuro = $p['badge'] !== '';
          $min = min(array_map(fn($o) => (int) $o['price_cents'], $of['options'])); ?>
      <div class="plan <?= $scuro ? 'plan--dark' : '' ?>">
        <div class="spread spread--mid" style="gap:12px">
          <span class="kicker" style="<?= $scuro ? 'color:var(--inverse-muted)' : '' ?>"><?= Support::e($famiglia ? preg_replace('/\s*\d+$/', '', $p['name']) : $p['name']) ?></span>
          <?php if ($p['badge'] !== ''): ?><span class="badge badge--ochre-strong"><?= Support::e($p['badge']) ?></span><?php endif; ?>
        </div>
        <div class="stack" style="gap:8px">
          <span class="name" style="font-family:Gloock,Georgia,serif;font-weight:400;letter-spacing:-.3px;font-size:26px;line-height:1.1"><?= Support::e($p['headline']) ?></span>
          <span class="muted" style="font-size:15px;line-height:22px"><?= Support::e($p['description']) ?></span>
        </div>
        <span class="price"><?= $famiglia ? '<small style="font-size:16px">da </small>' : '' ?><?= Support::e(Support::money($min, $p['currency'])) ?><small> + IVA / anno</small></span>
        <?php if ($famiglia): ?>
          <div class="options">
            <?php foreach ($of['options'] as $o): ?>
              <a class="option" href="<?= $vai((int) $o['pv_id']) ?>" style="color:inherit"><span><?= Support::e($o['tagline']) ?></span>
                <strong><?= Support::e(Support::money((int) $o['price_cents'], $o['currency'])) ?></strong></a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <ul><?php foreach ($p['bullet_list'] as $bl): ?><li><?= Support::e($bl) ?></li><?php endforeach; ?></ul>
        <p class="small <?= $scuro ? '' : 'muted' ?>" style="<?= $scuro ? 'color:var(--inverse-muted)' : '' ?>"><?= Support::e($p['tagline'] !== '' && !$famiglia ? $p['tagline'] : '') ?></p>
        <a class="btn <?= $scuro ? '' : 'btn--ghost' ?>" href="<?= $vai((int) $p['pv_id']) ?>"><?= Support::e($p['cta_label'] ?: 'Scegli ' . $p['name']) ?></a>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section class="band" style="margin-top:72px">
  <div class="stack" style="gap:12px">
    <h2 style="font-size:clamp(30px,4vw,42px);line-height:1">Pochi minuti oggi,<br>una stagione tranquilla.</h2>
    <p class="muted" style="font-size:17px;line-height:26px;max-width:480px">Crea la tua guida gratis e guarda l'anteprima.
      Paghi solo quando decidi di pubblicarla.</p>
  </div>
  <a class="btn btn--lg btn--go" href="<?= $crea ?>">Crea gratis la tua guida <span class="go"><?= Icon::svg('arrow', 19, 2) ?></span></a>
</section>
