<?php
/* 7F · Le pagine per tipologia (/case-vacanza, /bed-and-breakfast, /affittacamere, /agriturismi): stesso telaio
   della landing, contenuto proprio da lang/tipologie.php. Solo in italiano: niente interruttore IT/EN.
   9K · Anche /piu-strutture e /property-manager, con i blocchi che le voci chiedono: 'questionario' (le quattro
   tipologie), 'sezioni', 'passi' (la partnership), 'portfolio' (prezzi progressivi e preventivo), 'partner' (il modulo).
   Riceve: $tp (i testi), $chiave, $piano (il piano consigliato da Plans::public, o null), $demo, $user, e per il questionario
   $qPiani, $qRiep, $qRisposte, $qStrutture. */
use function MHW\b; use function MHW\a;
use MHW\{Support, Icon, Plans, SectionCatalog, Partner};
$title = $tp['h1'] . ' · MyHouse Welcome';
$dentro = !empty($user);
$porto = !empty($tp['portfolio']) && $piano && Plans::perProperty($piano);
$crea = b() . ($dentro ? '/pannello' : '/registrati' . ($piano ? '?piano=' . (int) $piano['pv_id'] . ($porto ? '&amp;strutture=' . max(2, (int) $piano['min_quantity']) : '') : ''));
$ctaCrea = $dentro ? 'Vai alle tue guide' : ($porto ? 'Crea gratis con ' . Support::e($piano['name']) : 'Crea gratis la tua guida');
$partner = !empty($tp['partner']);
// 9K · Le percentuali del programma Partner, se decise (Impostazioni → Sito); altrimenti le parole senza numero.
$comm = Partner::commissione(); $scP = Partner::sconto();
$sost = fn(string $t) => strtr($t, ['{commissione}' => $comm ? 'una commissione del ' . $comm . '%' : 'una commissione',
                                   '{sconto_clienti}' => $scP ? 'il ' . $scP . '% di sconto' : 'uno sconto']);
$demoUrl = $demo ? b() . '/g/' . Support::e($demo['slug']) . '/benvenuto' : null;
[$fotoFile, $fotoL, $fotoA, $fotoAlt] = $tp['foto']; ?>
<section class="tipo-hero" aria-labelledby="tipo-titolo">
  <div class="tipo-hero__testo">
    <span class="kicker"><?= Support::e($tp['kicker']) ?></span>
    <h1 id="tipo-titolo" class="display"><?= Support::e($tp['h1']) ?></h1>
    <p class="hero2__sub"><?= Support::e($tp['intro']) ?></p>
    <div class="row tipo-hero__azioni">
      <?php if ($partner): ?><a class="btn btn--lg btn--go" href="#partner">Diventa partner <span class="go"><?= Icon::svg('arrow', 19, 2) ?></span></a>
      <?php else: ?><a class="btn btn--lg btn--go" href="<?= $crea ?>"><?= $ctaCrea ?> <span class="go"><?= Icon::svg('arrow', 19, 2) ?></span></a><?php endif; ?>
      <?php if ($demoUrl): ?><a class="btn btn--lg btn--ghost" href="<?= $demoUrl ?>"><?= Icon::svg('eye', 18, 1.8) ?>Guarda la demo</a><?php endif; ?>
    </div>
    <p class="small muted"><?= $partner ? 'Una guida per ogni immobile, pronta in un pomeriggio. Ci conosciamo prima, senza impegno.' : 'La crei gratis, senza carta di credito: paghi solo quando la pubblichi.' ?></p>
  </div>
  <figure class="tipo-hero__foto">
    <?php $fotoWebp = preg_replace('/\.jpe?g$/', '.webp', $fotoFile);   // 9L: la WebP accanto al .jpg, se c'è, e il .jpg di riserva ?>
    <picture><?php if ($fotoWebp !== $fotoFile && is_file(MHW_PUBLIC . '/assets/foto/' . $fotoWebp)): ?><source srcset="<?= Support::e(a('/assets/foto/' . $fotoWebp)) ?>" type="image/webp"><?php endif; ?>
    <img src="<?= Support::e(a('/assets/foto/' . $fotoFile)) ?>" alt="<?= Support::e($fotoAlt) ?>" width="<?= (int) $fotoL ?>" height="<?= (int) $fotoA ?>" loading="eager" decoding="async"></picture>
  </figure>
</section>

<section class="blocco" aria-labelledby="tipo-problemi">
  <div class="come__testa">
    <span class="kicker"><?= Support::e($tp['problemi_kicker'] ?? 'I problemi di tutti i giorni') ?></span>
    <h2 id="tipo-problemi" class="h-sezione"><?= Support::e($tp['problemi_titolo'] ?? 'Tre cose che la guida risolve') ?></h2>
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
    <span class="kicker">Con Portfolio</span>
    <h2 id="tipo-campione" class="h-sezione">Il lavoro lo fai una volta sola</h2>
  </div>
  <?php $csTitolo = 'h3'; include __DIR__ . '/_campione_spiegone.php'; ?>
</section>
<?php endif; ?>

<?php if (!empty($tp['passi'])): /* 9K · Come funziona la partnership: quattro passi in ordine */ ?>
<section class="blocco" aria-labelledby="tipo-passi">
  <div class="come__testa">
    <span class="kicker">Passo dopo passo</span>
    <h2 id="tipo-passi" class="h-sezione"><?= Support::e($tp['passi_titolo'] ?? 'Come funziona') ?></h2>
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
    <span class="kicker">Le sezioni</span>
    <h2 id="tipo-sezioni" class="h-sezione">Le sezioni più utili per <?= Support::e(mb_strtolower($tp['nome'])) ?></h2>
    <p class="muted">Sono già pronte, con i campi giusti: scegli quelle che ti servono e le compili in pochi minuti. In tutto sono <?= SectionCatalog::quante() ?>.</p>
  </div>
  <ul class="tipo-sezioni">
    <?php foreach ($tp['sezioni'] as $kind => $perche): ?>
      <li><span class="vantaggio__ico" aria-hidden="true"><?= Icon::svg(SectionCatalog::icon($kind), 20, 1.7) ?></span>
        <span><b><?= Support::e(SectionCatalog::nome($kind)) ?></b><span class="small muted"><?= Support::e($perche) ?></span></span></li>
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
      <span class="kicker">Il piano per più strutture</span>
      <h2 id="tipo-portfolio" class="h-sezione"><?= Support::e($piano['name']) ?>: più strutture aggiungi, meno costa ognuna</h2>
      <p>Tutto quello che c'è in Plus, per ogni struttura: tutte le sezioni, foto e PDF, la traduzione automatica, le statistiche di ognuna. Un solo account e un solo abbonamento.</p>
      <?php $pvS = $piano; $qS = $minP; include __DIR__ . '/_scaglioni.php'; ?>
    </div>
    <div class="tipo-piano__prezzo">
      <span class="tipo-piano__cifra price" data-totale><?= Support::e(Support::money($prezzoP, $piano['currency'])) ?></span>
      <span class="small muted">+ IVA all'anno per <span data-quante><?= $minP ?></span> strutture<?= $maxP > 0 ? ', fino a ' . $maxP : '' ?></span>
      <div class="field" style="margin:0"><label for="pf-strutture">Quante strutture?</label>
        <input id="pf-strutture" type="number" inputmode="numeric" min="<?= $minP ?>" max="<?= $maxP > 0 ? $maxP + 1 : 999 ?>" data-max-prezzo="<?= $maxP > 0 ? $maxP : 999 ?>" value="<?= $minP ?>"></div>
      <a class="btn btn--go" href="<?= $crea ?>" data-crea data-href="<?= Support::e(b() . ($dentro ? '/pannello' : '/registrati?piano=' . (int) $piano['pv_id'])) ?>"><?= $ctaCrea ?> <span class="go"><?= Icon::svg('arrow', 17, 2) ?></span></a>
    </div>
  </div>
  <?php /* 9M · Sotto i prezzi e il selettore, solo la parte finale del questionario: il 55% e la scala del rinnovo, legati al selettore. */
        $rnPieno = $prezzoP; $rnCampo = 'pf-strutture'; ?>
  <div class="tipo-rinnovo"><?php include __DIR__ . '/_rinnovo.php'; ?></div>
  <script src="<?= MHW\av('/assets/prezzi.js') ?>" defer></script>
  <?php if ($maxP > 0 && MHW\Preventivi::disponibili()): /* oltre il massimo: il modulo del preventivo (9A), aperto da qui */ ?>
    <details class="tipo-preventivo panel"<?= isset($_SESSION['preventivo_ok']) || isset($_SESSION['preventivo_errore']) ? ' open' : '' ?>>
      <summary><b>Più di <?= $maxP ?> strutture?</b> Ti facciamo una proposta su misura <?= Icon::svg('plus', 18, 2, 'faq__segno') ?></summary>
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
      <span class="kicker">Programma Partner</span>
      <h2 id="partner-titolo" class="h-sezione">Diventa partner</h2>
      <p class="muted">Raccontaci chi sei e quante case gestisci: ti contattiamo entro un giorno lavorativo per conoscerci. Le risposte arrivano da <?= Support::e(Support::commerciale()) ?>.</p>
    </div>
    <?php if ($okPa): ?>
      <p class="note note--ok" role="status">Grazie: abbiamo ricevuto la tua richiesta e ti abbiamo mandato una conferma per email. Ti contattiamo entro un giorno lavorativo.</p>
    <?php else: ?>
      <?php if ($errPa): ?><p class="note note--err" role="alert"><?= Support::e($errPa['msg']) ?></p><?php endif; ?>
      <form method="post" action="<?= b() ?>/property-manager/partner" class="stack tipo-partner__modulo" style="gap:14px"><?= MHW\Csrf::field() ?>
        <div class="grid grid-2">
          <div class="field" style="margin:0"><label for="pa-nome">Nome e cognome</label><input id="pa-nome" name="nome" type="text" required maxlength="120" autocomplete="name" value="<?= $vPa('nome') ?>"></div>
          <div class="field" style="margin:0"><label for="pa-societa">Società o agenzia</label><input id="pa-societa" name="societa" type="text" maxlength="160" autocomplete="organization" value="<?= $vPa('societa') ?>"></div>
          <div class="field" style="margin:0"><label for="pa-email">Email</label><input id="pa-email" name="email" type="email" required maxlength="190" autocomplete="email" value="<?= $vPa('email') ?>"></div>
          <div class="field" style="margin:0"><label for="pa-tel">Telefono</label><input id="pa-tel" name="telefono" type="tel" maxlength="40" autocomplete="tel" value="<?= $vPa('telefono') ?>"></div>
          <div class="field" style="margin:0"><label for="pa-strutture">Quante strutture gestisci?</label><input id="pa-strutture" name="strutture" type="number" min="1" max="9999" inputmode="numeric" value="<?= $vPa('strutture') ?>"></div>
          <div class="field" style="margin:0"><label for="pa-zone">Città o zone</label><input id="pa-zone" name="zone" type="text" maxlength="255" placeholder="Per esempio Perugia, Assisi, Lago Trasimeno" value="<?= $vPa('zone') ?>"></div>
        </div>
        <div class="field" style="margin:0"><label for="pa-msg">Messaggio <span class="muted small">(facoltativo)</span></label><textarea id="pa-msg" name="messaggio" rows="4" maxlength="2000"><?= $vPa('messaggio') ?></textarea></div>
        <label class="check" style="align-items:flex-start"><input type="checkbox" name="consenso" value="1" required style="margin-top:1px">
          <span>Acconsento al trattamento dei miei dati per ricevere una risposta, come spiegato nell'<a href="<?= b() ?>/privacy#trattamento-14" target="_blank" rel="noopener">Informativa privacy</a> (li conserviamo 12 mesi).</span></label>
        <div><button class="btn btn--go">Diventa partner <span class="go"><?= Icon::svg('arrow', 17, 2) ?></span></button></div>
      </form>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

<?php if (!empty($tp['faq'])): ?>
<section class="blocco faq" aria-labelledby="tipo-faq">
  <div class="faq__testa">
    <span class="kicker">Domande</span>
    <h2 id="tipo-faq" class="h-sezione">Le domande di chi gestisce <?= Support::e(mb_strtolower($tp['nome'])) ?></h2>
    <p class="muted">Le altre risposte sono nelle <a href="<?= b() ?>/domande">domande frequenti</a>.</p>
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
  <h2 id="tipo-fine" class="h-sezione"><?= $partner ? 'Parliamone' : 'Prova adesso, è gratis' ?></h2>
  <p class="muted"><?= $partner ? 'Ti richiamiamo, ti mostriamo la guida e decidiamo insieme se il programma Partner fa per te.' : 'Prepari la guida e la guardi come la vedranno i tuoi ospiti. Paghi solo quando la pubblichi.' ?></p>
  <div class="row tipo-hero__azioni">
    <?php if ($partner): ?><a class="btn btn--lg btn--go" href="#partner">Diventa partner <span class="go"><?= Icon::svg('arrow', 19, 2) ?></span></a>
    <?php else: ?><a class="btn btn--lg btn--go" href="<?= $crea ?>"><?= $ctaCrea ?> <span class="go"><?= Icon::svg('arrow', 19, 2) ?></span></a><?php endif; ?>
    <?php if ($demoUrl): ?><a class="btn btn--lg btn--ghost" href="<?= $demoUrl ?>"><?= Icon::svg('eye', 18, 1.8) ?>Guarda la demo</a><?php endif; ?>
  </div>
</section>
