<?php
/* L'invito di un amico (/i/CODICE, 7D): chi invita, l'anteprima sicura della sua guida (copertina,
   nome, città e i nomi delle sezioni a riquadri, mai i contenuti) e i due bottoni. Pagina noindex:
   non ha $seoPagina. Riceve $nome, $codice, $p (null se la casella dell'anteprima è spenta o la
   guida non è pubblicata), $sezioni, $copertina, $esempio. */
use function MHW\b;
use MHW\{Support, Icon, Inviti, Palette, SectionCatalog};
$title = $p ? $p['name'] . ' usa MyHouse Welcome' : $nome . ' ti invita su MyHouse Welcome';
$pal = Palette::get((string) ($p['palette'] ?? ''));
$tiles = $pal['tiles']; $inchiostri = $pal['tile_ink']; ?>
<section class="blocco invito-pagina" aria-labelledby="invito-titolo">
  <div class="invito-pagina__testo stack">
    <span class="kicker">Un invito di <?= Support::e($nome) ?></span>
    <h1 id="invito-titolo" class="h-sezione">
      <?php if ($p): ?><?= Support::e($nome) ?> usa MyHouse Welcome per <?= Support::e($p['name']) ?><?= trim((string) $p['city']) !== '' ? ', a ' . Support::e($p['city']) : '' ?>.
      <?php else: ?><?= Support::e($nome) ?> usa MyHouse Welcome e ti invita a provarla.<?php endif; ?>
    </h1>
    <p class="lead">La guida digitale della casa: check-in, Wi-Fi e consigli in un link e un QR, nella lingua di ogni ospite.
      Con questo invito hai il <b><?= Inviti::AMICO ?>% di sconto</b> sul primo anno.</p>
    <div class="row invito-pagina__bottoni">
      <a class="btn btn--go" href="<?= b() ?>/registrati">Crea la tua guida con il <?= Inviti::AMICO ?>% di sconto <span class="go"><?= Icon::svg('arrow', 18, 2) ?></span></a>
      <?php if ($esempio): ?><a class="btn btn--ghost" href="<?= Support::e(Support::url($esempio)) ?>" target="_blank" rel="noopener">Guarda una guida completa <?= Icon::svg('external', 15, 2) ?></a><?php endif; ?>
    </div>
    <p class="small muted">Lo sconto è già inserito: dopo la registrazione lo trovi applicato. Il codice di <?= Support::e($nome) ?> è <b><?= Support::e($codice) ?></b>.
      Crei la guida gratis e paghi solo quando la pubblichi.</p>
  </div>
  <?php if ($p): ?>
  <figure class="invito-pagina__scheda" aria-label="Anteprima della guida di <?= Support::e($p['name']) ?>">
    <div class="invito-pagina__foto">
      <?php if ($copertina): ?><img src="<?= Support::e($copertina) ?>" alt="" width="560" height="294" loading="eager" decoding="async">
      <?php else: ?><span class="invito-pagina__vuota" style="background:<?= Support::e($pal['scuro']['accent']) ?>"><?= Icon::brand(48) ?></span><?php endif; ?>
    </div>
    <figcaption class="invito-pagina__nome"><b><?= Support::e($p['name']) ?></b><?php if (trim((string) $p['city']) !== ''): ?><span class="small muted"><?= Support::e($p['city']) ?></span><?php endif; ?></figcaption>
    <?php if ($sezioni): ?>
      <ul class="invito-pagina__riquadri" aria-label="Le sezioni della guida">
        <?php foreach ($sezioni as $i => $s): ?>
          <li style="background:<?= Support::e($tiles[$i % 4]) ?>;color:<?= Support::e($inchiostri[$i % 4]) ?>"><?= Icon::svg(SectionCatalog::icon($s['kind']), 20, 1.8) ?><span><?= Support::e($s['titolo']) ?></span></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <p class="tiny muted invito-pagina__nota"><?= Icon::svg('lock', 13, 2) ?>Si vedono solo i titoli: indirizzo, Wi-Fi e contatti restano per gli ospiti.</p>
  </figure>
  <?php endif; ?>
</section>
