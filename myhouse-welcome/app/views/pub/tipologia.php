<?php
/* 7F · Le pagine per tipologia (/case-vacanza, /bed-and-breakfast, /affittacamere, /agriturismi): stesso telaio
   della landing, contenuto proprio da lang/tipologie.php; 9N: in inglese da lang/tipologie-en.php (LinguaSito::en(), $U per i testi fissi).
   9K · Anche /piu-strutture e /property-manager, con i blocchi che le voci chiedono: 'questionario' (le quattro
   tipologie), 'sezioni', 'passi' (la partnership), 'portfolio' (prezzi progressivi e preventivo), 'partner' (il modulo).
   Riceve: $tp (i testi), $chiave, $piano (il piano consigliato da Plans::public, o null), $demo, $user, e per il questionario
   $qPiani, $qRiep, $qRisposte, $qStrutture. */
use function MHW\b; use function MHW\a;
use MHW\{Support, Icon, Plans, SectionCatalog, Partner, LinguaSito};
$U = [LinguaSito::class, 't']; $EN = LinguaSito::en();
$title = $tp['h1'] . ' · MyHouse Welcome';
$dentro = !empty($user);
$porto = !empty($tp['portfolio']) && $piano && Plans::perProperty($piano);
$crea = b() . ($dentro ? '/pannello' : LinguaSito::percorso('/registrati') . ($piano ? '?piano=' . (int) $piano['pv_id'] . ($porto ? '&amp;strutture=' . max(2, (int) $piano['min_quantity']) : '') : ''));
$ctaCrea = $dentro ? $U('Vai alle tue guide', 'Go to your guides') : ($porto ? $U('Crea gratis con ', 'Create free with ') . Support::e($piano['name']) : $U('Crea gratis la tua guida', 'Create your guide for free'));
$partner = !empty($tp['partner']);
// 9K · Le percentuali del programma Partner, se decise (Impostazioni → Sito); altrimenti le parole senza numero.
$comm = Partner::commissione(); $scP = Partner::sconto();
$sost = fn(string $t) => strtr($t, ['{commissione}' => $comm ? $U('una commissione del ', 'a commission of ') . $comm . '%' : $U('una commissione', 'a commission'),
                                   '{sconto_clienti}' => $scP ? ($EN ? $scP . '% off' : 'il ' . $scP . '% di sconto') : $U('uno sconto', 'a discount')]);
$demoUrl = $demo ? b() . '/g/' . Support::e($demo['slug']) . '/benvenuto' : null;
[$fotoFile, $fotoL, $fotoA, $fotoAlt] = $tp['foto']; ?>
<section class="tipo-hero" aria-labelledby="tipo-titolo">
  <div class="tipo-hero__testo">
    <span class="kicker"><?= Support::e($tp['kicker']) ?></span>
    <h1 id="tipo-titolo" class="display"><?= Support::e($tp['h1']) ?></h1>
    <p class="hero2__sub"><?= Support::e($tp['intro']) ?></p>
    <div class="row tipo-hero__azioni">
      <?php if ($partner): ?><a class="btn btn--lg btn--go" href="#partner"><?= $U('Diventa partner', 'Become a partner') ?> <span class="go"><?= Icon::svg('arrow', 19, 2) ?></span></a>
      <?php else: ?><a class="btn btn--lg btn--go" href="<?= $crea ?>"><?= $ctaCrea ?> <span class="go"><?= Icon::svg('arrow', 19, 2) ?></span></a><?php endif; ?>
      <?php if ($demoUrl): ?><a class="btn btn--lg btn--ghost" href="<?= $demoUrl ?>"><?= Icon::svg('eye', 18, 1.8) ?><?= $U('Guarda la demo', 'See the demo') ?></a><?php endif; ?>
    </div>
    <p class="small muted"><?= $partner ? $U('Una guida per ogni immobile, pronta in un pomeriggio. Ci conosciamo prima, senza impegno.', 'A guide for every property, ready in an afternoon. We get to know each other first, with no commitment.') : $U('La crei gratis, senza carta di credito: paghi solo quando la pubblichi.', 'Build it for free, no credit card: you only pay when you publish it.') ?></p>
  </div>
  <figure class="tipo-hero__foto">
    <?php $fotoWebp = preg_replace('/\.jpe?g$/', '.webp', $fotoFile);   // 9L: la WebP accanto al .jpg, se c'è, e il .jpg di riserva ?>
    <picture><?php if ($fotoWebp !== $fotoFile && is_file(MHW_PUBLIC . '/assets/foto/' . $fotoWebp)): ?><source srcset="<?= Support::e(a('/assets/foto/' . $fotoWebp)) ?>" type="image/webp"><?php endif; ?>
    <img src="<?= Support::e(a('/assets/foto/' . $fotoFile)) ?>" alt="<?= Support::e($fotoAlt) ?>" width="<?= (int) $fotoL ?>" height="<?= (int) $fotoA ?>" loading="eager" decoding="async"></picture>
  </figure>
</section>

<section class="blocco" aria-labelledby="tipo-problemi">
  <div class="come__testa">
    <span class="kicker"><?= Support::e($tp['problemi_kicker'] ?? $U('I problemi di tutti i giorni', 'Everyday problems')) ?></span>
    <h2 id="tipo-problemi" class="h-sezione"><?= Support::e($tp['problemi_titolo'] ?? $U('Tre cose che la guida risolve', 'Three things the guide solves')) ?></h2>
  </div>
  <div class="vantaggi tipo-problemi">
    <?php foreach ($tp['problemi'] as [$tit, $problema, $risposta]): ?>
      <div class="vantaggio">
        <b><?= Support::e($tit) ?></b>
        <p><?= Support::e($sost($problema)) ?></p>
        <?php if ($risposta !== ''): ?><p class="tipo-risposta"><?= Icon::svg('check', 16, 2.2) ?><span><?= Support::e($risposta) ?></span></p><?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<?php if (!empty($tp['campione'])): /* 9M · «Il lavoro lo fai una volta sola»: lo spiegone della scheda campione (le stesse chiavi del pannello e delle FAQ) */ ?>
<section class="blocco" aria-labelledby="tipo-campione">
  <div class="come__testa">
    <span class="kicker"><?= $U('Con Portfolio', 'With Portfolio') ?></span>
    <h2 id="tipo-campione" class="h-sezione"><?= $U('Il lavoro lo fai una volta sola', 'You do the work only once') ?></h2>
  </div>
  <?php $csTitolo = 'h3'; include __DIR__ . '/_campione_spiegone.php'; ?>
</section>
<?php endif; ?>

<?php if (!empty($tp['passi'])): /* 9K · Come funziona la partnership: quattro passi in ordine */ ?>
<section class="blocco" aria-labelledby="tipo-passi">
  <div class="come__testa">
    <span class="kicker"><?= $U('Passo dopo passo', 'Step by step') ?></span>
    <h2 id="tipo-passi" class="h-sezione"><?= Support::e($tp['passi_titolo'] ?? $U('Come funziona', 'How it works')) ?></h2>
  </div>
  <ol class="tipo-passi">
    <?php foreach ($tp['passi'] as $i => [$tit, $testo]): ?>
      <li><span class="tipo-passi__n" aria-hidden="true"><?= $i + 1 ?></span><span><b><?= Support::e($tit) ?></b><span class="small muted"><?= Support::e($sost($testo)) ?></span></span></li>
    <?php endforeach; ?>
  </ol>
</section>
<?php endif; ?>

<?php if (!empty($tp['sezioni'])): ?>
<section class="blocco" aria-labelledby="tipo-sezioni">
  <div class="come__testa">
    <span class="kicker"><?= $U('Le sezioni', 'The sections') ?></span>
    <h2 id="tipo-sezioni" class="h-sezione"><?= $U('Le sezioni più utili per ', 'The most useful sections for ') . Support::e(mb_strtolower($tp['nome'])) ?></h2>
    <p class="muted"><?= $U('Sono già pronte, con i campi giusti: scegli quelle che ti servono e le compili in pochi minuti. In tutto sono ', 'They are ready-made, with the right fields: pick the ones you need and fill them in in a few minutes. There are ') . SectionCatalog::quante() . $U('.', ' in all.') ?></p>
  </div>
  <ul class="tipo-sezioni">
    <?php foreach ($tp['sezioni'] as $kind => $perche): ?>
      <li><span class="vantaggio__ico" aria-hidden="true"><?= Icon::svg(SectionCatalog::icon($kind), 20, 1.7) ?></span>
        <span><b><?= Support::e($EN ? (SectionCatalog::title($kind, 'en')) : SectionCatalog::nome($kind)) ?></b><span class="small muted"><?= Support::e($perche) ?></span></span></li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<?php /* 9K · Al posto del piano consigliato fisso: «Qual è il piano giusto per te?», tre domande e l'esito. */
      if (!empty($tp['questionario'])) include __DIR__ . '/_questionario.php'; ?>

<?php if ($porto): /* 9K · Solo Portfolio: quanto costa ogni struttura, il preventivo oltre il massimo, lo sconto sul rinnovo */
      $maxP = (int) $piano['max_quantity']; $minP = max(2, (int) $piano['min_quantity']); ?>
<section class="blocco" aria-labelledby="tipo-portfolio">
  <?php $prezzoP = Plans::price($piano, $minP); ?>
  <div class="tipo-piano panel" data-quantita data-base="<?= (int) $piano['price_cents'] ?>" data-extra="<?= (int) $piano['extra_price_cents'] ?>" data-scaglioni="<?= Support::e(Plans::tiersJson($piano)) ?>" data-valuta="<?= Support::e($piano['currency']) ?>">
    <div class="stack" style="gap:10px">
      <span class="kicker"><?= $U('Il piano per più strutture', 'The plan for several properties') ?></span>
      <h2 id="tipo-portfolio" class="h-sezione"><?= Support::e($piano['name']) ?><?= $U(': più strutture aggiungi, meno costa ognuna', ': the more properties you add, the less each one costs') ?></h2>
      <p><?= $U('Tutto quello che c\'è in Plus, per ogni struttura: tutte le sezioni, foto e PDF, le traduzioni suggerite dal traduttore automatico (le approvi tu, comprese entro un limite annuale), le statistiche di ognuna. Un solo account e un solo abbonamento.', 'Everything in Plus, for every property: all the sections, photos and PDFs, translations suggested by the automatic translator (you approve them, included within a yearly limit), statistics for each. One account and one subscription.') ?></p>
      <?php $pvS = $piano; $qS = $minP; include __DIR__ . '/_scaglioni.php'; ?>
    </div>
    <div class="tipo-piano__prezzo">
      <span class="tipo-piano__cifra price" data-totale><?= Support::e(Support::money($prezzoP, $piano['currency'])) ?></span>
      <span class="small muted"><?= $U('+ IVA all\'anno per ', '+ VAT per year for ') ?><span data-quante><?= $minP ?></span><?= $U(' strutture', ' properties') ?><?= $maxP > 0 ? $U(', fino a ', ', up to ') . $maxP : '' ?></span>
      <div class="field" style="margin:0"><label for="pf-strutture"><?= $U('Quante strutture?', 'How many properties?') ?></label>
        <input id="pf-strutture" type="number" inputmode="numeric" min="<?= $minP ?>" max="<?= $maxP > 0 ? $maxP + 1 : 999 ?>" data-max-prezzo="<?= $maxP > 0 ? $maxP : 999 ?>" value="<?= $minP ?>"></div>
      <a class="btn btn--go" href="<?= $crea ?>" data-crea data-href="<?= Support::e(b() . ($dentro ? '/pannello' : LinguaSito::percorso('/registrati') . '?piano=' . (int) $piano['pv_id'])) ?>"><?= $ctaCrea ?> <span class="go"><?= Icon::svg('arrow', 17, 2) ?></span></a>
    </div>
  </div>
  <?php /* 9M · Sotto i prezzi e il selettore, solo la parte finale del questionario: il 55% e la scala del rinnovo, legati al selettore. */
        $rnPieno = $prezzoP; $rnCampo = 'pf-strutture'; ?>
  <div class="tipo-rinnovo"><?php include __DIR__ . '/_rinnovo.php'; ?></div>
  <script src="<?= MHW\av('/assets/prezzi.js') ?>" defer></script>
  <?php if ($maxP > 0 && MHW\Preventivi::disponibili()): /* oltre il massimo: il modulo del preventivo (9A), aperto da qui */ ?>
    <details class="tipo-preventivo panel"<?= isset($_SESSION['preventivo_ok']) || isset($_SESSION['preventivo_errore']) ? ' open' : '' ?>>
      <summary><b><?= $U('Più di ', 'More than ') . $maxP . $U(' strutture?', ' properties?') ?></b> <?= $U('Ti facciamo una proposta su misura', 'We will make you a tailored offer') ?> <?= Icon::svg('plus', 18, 2, 'faq__segno') ?></summary>
      <?php $pvP = $piano; $qP = $maxP + 1; $tornaP = $tp['percorso']; include __DIR__ . '/_preventivo.php'; ?>
    </details>
  <?php endif; ?>
</section>
<?php endif; ?>

<?php if ($partner): /* 9K · «Diventa partner»: va all'email commerciale e in Amministrazione → Partner */
      $okPa = !empty($_SESSION['partner_ok']); unset($_SESSION['partner_ok']);
      $errPa = $_SESSION['partner_errore'] ?? null; unset($_SESSION['partner_errore']);
      $vPa = fn(string $k) => Support::e((string) ($errPa[$k] ?? '')); ?>
<section class="blocco" aria-labelledby="partner-titolo" id="partner">
  <div class="tipo-partner panel">
    <div class="stack" style="gap:10px">
      <span class="kicker"><?= $U('Programma Partner', 'Partner programme') ?></span>
      <h2 id="partner-titolo" class="h-sezione"><?= $U('Diventa partner', 'Become a partner') ?></h2>
      <p class="muted"><?= $U('Raccontaci chi sei e quante case gestisci: ti contattiamo entro un giorno lavorativo per conoscerci. Le risposte arrivano da ', 'Tell us who you are and how many homes you manage: we will contact you within one working day. Replies come from ') . Support::e(Support::commerciale()) ?>.</p>
    </div>
    <?php if ($okPa): ?>
      <p class="note note--ok" role="status"><?= $U('Grazie: abbiamo ricevuto la tua richiesta e ti abbiamo mandato una conferma per email. Ti contattiamo entro un giorno lavorativo.', 'Thank you: we have received your request and sent you a confirmation by email. We will contact you within one working day.') ?></p>
    <?php else: ?>
      <?php if ($errPa): ?><p class="note note--err" role="alert"><?= Support::e($errPa['msg']) ?></p><?php endif; ?>
      <form method="post" action="<?= b() ?>/property-manager/partner" class="stack tipo-partner__modulo" style="gap:14px"><?= MHW\Csrf::field() ?><?= $EN ? '<input type="hidden" name="lingua" value="en">' : '' ?>
        <div class="grid grid-2">
          <div class="field" style="margin:0"><label for="pa-nome"><?= $U('Nome e cognome', 'Full name') ?></label><input id="pa-nome" name="nome" type="text" required maxlength="120" autocomplete="name" value="<?= $vPa('nome') ?>"></div>
          <div class="field" style="margin:0"><label for="pa-societa"><?= $U('Società o agenzia', 'Company or agency') ?></label><input id="pa-societa" name="societa" type="text" maxlength="160" autocomplete="organization" value="<?= $vPa('societa') ?>"></div>
          <div class="field" style="margin:0"><label for="pa-email">Email</label><input id="pa-email" name="email" type="email" required maxlength="190" autocomplete="email" value="<?= $vPa('email') ?>"></div>
          <div class="field" style="margin:0"><label for="pa-tel"><?= $U('Telefono', 'Phone') ?></label><input id="pa-tel" name="telefono" type="tel" maxlength="40" autocomplete="tel" value="<?= $vPa('telefono') ?>"></div>
          <div class="field" style="margin:0"><label for="pa-strutture"><?= $U('Quante strutture gestisci?', 'How many properties do you manage?') ?></label><input id="pa-strutture" name="strutture" type="number" min="1" max="9999" inputmode="numeric" value="<?= $vPa('strutture') ?>"></div>
          <div class="field" style="margin:0"><label for="pa-zone"><?= $U('Città o zone', 'Towns or areas') ?></label><input id="pa-zone" name="zone" type="text" maxlength="255" placeholder="<?= $U('Per esempio Perugia, Assisi, Lago Trasimeno', 'For example Perugia, Assisi, Lake Trasimeno') ?>" value="<?= $vPa('zone') ?>"></div>
        </div>
        <div class="field" style="margin:0"><label for="pa-msg"><?= $U('Messaggio', 'Message') ?> <span class="muted small">(<?= $U('facoltativo', 'optional') ?>)</span></label><textarea id="pa-msg" name="messaggio" rows="4" maxlength="2000"><?= $vPa('messaggio') ?></textarea></div>
        <label class="check" style="align-items:flex-start"><input type="checkbox" name="consenso" value="1" required style="margin-top:1px">
          <span><?= $U('Acconsento al trattamento dei miei dati per ricevere una risposta, come spiegato nell\'', 'I agree to the processing of my data to receive a reply, as explained in the ') ?><a href="<?= b() . LinguaSito::percorso('/privacy') ?>#trattamento-14" target="_blank" rel="noopener"><?= $U('Informativa privacy', 'Privacy policy') ?></a><?= $U(' (li conserviamo 12 mesi).', ' (we keep them for 12 months).') ?></span></label>
        <div><button class="btn btn--go"><?= $U('Diventa partner', 'Become a partner') ?> <span class="go"><?= Icon::svg('arrow', 17, 2) ?></span></button></div>
      </form>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

<?php if (!empty($tp['faq'])): ?>
<section class="blocco faq" aria-labelledby="tipo-faq">
  <div class="faq__testa">
    <span class="kicker"><?= $U('Domande', 'Questions') ?></span>
    <h2 id="tipo-faq" class="h-sezione"><?= $EN ? 'Questions from people who run ' . Support::e(mb_strtolower($tp['nome'])) : 'Le domande di chi gestisce ' . Support::e(mb_strtolower($tp['nome'])) ?></h2>
    <p class="muted"><?= $U('Le altre risposte sono nelle ', 'The other answers are in the ') ?><a href="<?= b() . LinguaSito::percorso('/domande') ?>"><?= $U('domande frequenti', 'frequently asked questions') ?></a>.</p>
  </div>
  <div class="faq__lista">
    <?php foreach ($tp['faq'] as $i => [$d, $risp]): ?>
      <details class="faq__voce" id="domanda-<?= $i + 1 ?>"<?= $i === 0 ? ' open' : '' ?>>
        <summary><?= Support::e($d) ?><?= Icon::svg('plus', 18, 2, 'faq__segno') ?></summary>
        <p><?= Support::e($risp) ?></p>
      </details>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<section class="blocco tipo-fine" aria-labelledby="tipo-fine">
  <h2 id="tipo-fine" class="h-sezione"><?= $partner ? $U('Parliamone', 'Let\'s talk') : $U('Prova adesso, è gratis', 'Try it now, it\'s free') ?></h2>
  <p class="muted"><?= $partner ? $U('Ti richiamiamo, ti mostriamo la guida e decidiamo insieme se il programma Partner fa per te.', 'We call you back, show you the guide and decide together whether the Partner programme is right for you.') : $U('Prepari la guida e la guardi come la vedranno i tuoi ospiti. Paghi solo quando la pubblichi.', 'Prepare the guide and see it as your guests will. You only pay when you publish it.') ?></p>
  <div class="row tipo-hero__azioni">
    <?php if ($partner): ?><a class="btn btn--lg btn--go" href="#partner"><?= $U('Diventa partner', 'Become a partner') ?> <span class="go"><?= Icon::svg('arrow', 19, 2) ?></span></a>
    <?php else: ?><a class="btn btn--lg btn--go" href="<?= $crea ?>"><?= $ctaCrea ?> <span class="go"><?= Icon::svg('arrow', 19, 2) ?></span></a><?php endif; ?>
    <?php if ($demoUrl): ?><a class="btn btn--lg btn--ghost" href="<?= $demoUrl ?>"><?= Icon::svg('eye', 18, 1.8) ?><?= $U('Guarda la demo', 'See the demo') ?></a><?php endif; ?>
  </div>
</section>
