<?php
/* 7F · Le pagine per tipologia (/case-vacanza, /bed-and-breakfast, /affittacamere, /agriturismi): stesso telaio
   della landing, contenuto proprio da lang/tipologie.php. Solo in italiano: niente interruttore IT/EN.
   Riceve: $tp (i testi), $chiave, $piano (il piano consigliato da Plans::public, o null), $demo, $user. */
use function MHW\b; use function MHW\a;
use MHW\{Support, Icon, Plans, SectionCatalog};
$title = $tp['h1'] . ' · MyHouse Welcome';
$dentro = !empty($user);
$crea = b() . ($dentro ? '/pannello' : '/registrati' . ($piano ? '?piano=' . (int) $piano['pv_id'] : ''));
$ctaCrea = $dentro ? 'Vai alle tue guide' : 'Crea gratis la tua guida';
$demoUrl = $demo ? b() . '/g/' . Support::e($demo['slug']) . '/benvenuto' : null;
[$fotoFile, $fotoL, $fotoA, $fotoAlt] = $tp['foto']; ?>
<section class="tipo-hero" aria-labelledby="tipo-titolo">
  <div class="tipo-hero__testo">
    <span class="kicker"><?= Support::e($tp['kicker']) ?></span>
    <h1 id="tipo-titolo" class="display"><?= Support::e($tp['h1']) ?></h1>
    <p class="hero2__sub"><?= Support::e($tp['intro']) ?></p>
    <div class="row tipo-hero__azioni">
      <a class="btn btn--lg btn--go" href="<?= $crea ?>"><?= $ctaCrea ?> <span class="go"><?= Icon::svg('arrow', 19, 2) ?></span></a>
      <?php if ($demoUrl): ?><a class="btn btn--lg btn--ghost" href="<?= $demoUrl ?>"><?= Icon::svg('eye', 18, 1.8) ?>Guarda la demo</a><?php endif; ?>
    </div>
    <p class="small muted">La crei gratis, senza carta di credito: paghi solo quando la pubblichi.</p>
  </div>
  <figure class="tipo-hero__foto">
    <img src="<?= Support::e(a('/assets/foto/' . $fotoFile)) ?>" alt="<?= Support::e($fotoAlt) ?>" width="<?= (int) $fotoL ?>" height="<?= (int) $fotoA ?>" loading="eager" decoding="async">
  </figure>
</section>

<section class="blocco" aria-labelledby="tipo-problemi">
  <div class="come__testa">
    <span class="kicker">I problemi di tutti i giorni</span>
    <h2 id="tipo-problemi" class="h-sezione">Tre cose che la guida risolve</h2>
  </div>
  <div class="vantaggi tipo-problemi">
    <?php foreach ($tp['problemi'] as [$tit, $problema, $risposta]): ?>
      <div class="vantaggio">
        <b><?= Support::e($tit) ?></b>
        <p><?= Support::e($problema) ?></p>
        <p class="tipo-risposta"><?= Icon::svg('check', 16, 2.2) ?><span><?= Support::e($risposta) ?></span></p>
      </div>
    <?php endforeach; ?>
  </div>
</section>

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

<?php if ($piano): $perStruttura = Plans::perProperty($piano); ?>
<section class="blocco" aria-labelledby="tipo-piano">
  <div class="tipo-piano panel">
    <div class="stack" style="gap:10px">
      <span class="kicker">Il piano che ti consigliamo</span>
      <h2 id="tipo-piano" class="h-sezione"><?= Support::e($piano['name']) ?></h2>
      <p><?= Support::e($tp['piano_perche']) ?></p>
      <a class="small" href="<?= b() ?>/#piani">Confronta tutti i piani</a>
    </div>
    <div class="tipo-piano__prezzo">
      <span class="tipo-piano__cifra"><?= $perStruttura ? 'da ' : '' ?><?= Support::e(Support::money((int) $piano['price_cents'], $piano['currency'])) ?></span>
      <span class="small muted">+ IVA all'anno<?= $perStruttura ? ', la prima struttura' : '' ?></span>
      <a class="btn btn--go" href="<?= $dentro ? b() . '/piano?piano=' . (int) $piano['pv_id'] : $crea ?>"><?= $ctaCrea ?> <span class="go"><?= Icon::svg('arrow', 17, 2) ?></span></a>
    </div>
  </div>
</section>
<?php endif; ?>

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

<section class="blocco tipo-fine" aria-labelledby="tipo-fine">
  <h2 id="tipo-fine" class="h-sezione">Prova adesso, è gratis</h2>
  <p class="muted">Prepari la guida e la guardi come la vedranno i tuoi ospiti. Paghi solo quando la pubblichi.</p>
  <div class="row tipo-hero__azioni">
    <a class="btn btn--lg btn--go" href="<?= $crea ?>"><?= $ctaCrea ?> <span class="go"><?= Icon::svg('arrow', 19, 2) ?></span></a>
    <?php if ($demoUrl): ?><a class="btn btn--lg btn--ghost" href="<?= $demoUrl ?>"><?= Icon::svg('eye', 18, 1.8) ?>Guarda la demo</a><?php endif; ?>
  </div>
</section>
