<?php
/* 9V · «La casa in foto» in home, dopo il Concierge AI: il segno «Novità» (finché vale: Impostazioni → Sito), il titolo, il
   testo e, a destra, la pagina «La casa» come la vede l'ospite, disegnata con i dati veri della guida demo pubblicata
   (la terza foto, come nel mockup, con il contatore «3 / 8», i puntini, il titolo e l'inizio della descrizione). Senza
   una demo con la galleria: le foto di serie di Casa Checco e i suoi testi (una casa di fantasia). Il link apre la pagina
   «La casa» della demo in una nuova scheda. Riceve $T, $L, $demo, $tel, $en, $U, $locSito dalla home. */
use function MHW\{a, b};
use MHW\{Support, Icon, Casa, Demo, Media, I18n};
$casaH = $tel['casa'] ?? null;
if ($casaH && $casaH['n']) {
    $nH = $casaH['n']; $iH = min(2, $nH - 1);
    $fotoH = [];
    foreach (array_slice($casaH['foto'], $iH, 2) as $k => $f) {
        $u = Media::ridotta((int) $f['id'], 800);
        if ($u) $fotoH[] = [$u, Casa::alt($f, $iH + $k + 1, $nH, (string) $tel['nome'], $locSito)];
    }
    $titoloH = $casaH['titolo']; $testoH = $casaH['descrizione'];
    $linkH = $demo ? b() . '/g/' . rawurlencode((string) $demo['slug']) . '/casa?l=' . $locSito : '';
} else {
    // Senza demo con la galleria: le foto di serie e i testi di serie (in inglese da lang/vetrina/en.php).
    $nH = count(Demo::CASA_FOTO); $iH = 2;
    $fotoH = array_map(fn($x) => [a('/assets/foto/' . $x[0]), $x[1]], array_slice(Demo::CASA_FOTO, $iH, 2));
    $trH = $en && is_file(MHW_APP . '/lang/vetrina/en.php') ? (array) require MHW_APP . '/lang/vetrina/en.php' : [];
    $titoloH = (string) ($trH[Demo::CASA_TITOLO] ?? Demo::CASA_TITOLO); $testoH = (string) ($trH[Demo::CASA_DESCRIZIONE] ?? Demo::CASA_DESCRIZIONE);
    $linkH = '';
}
if (!$fotoH) return; ?>
<section id="la-casa" class="assistente casa-home" aria-labelledby="casa-home-titolo">
  <div class="assistente__testa">
    <span class="kicker casa-home__kicker"><?= Casa::badge($en) ?><span><?= $T('casa.kicker') ?></span></span>
    <h2 id="casa-home-titolo" class="h-sezione"><?= $T('casa.titolo') ?></h2>
    <p class="muted assistente__lead"><?= $T('casa.testo') ?></p>
    <?php if ($linkH !== ''): ?>
      <p class="assistente__azioni"><a class="btn" href="<?= Support::e($linkH) ?>" target="_blank" rel="noopener"><?= $T('casa.demo', ['demo' => Support::e((string) $tel['nome'])]) ?> <?= Icon::svg('external', 15, 2) ?><span class="sr-only"> <?= Support::e($U('(si apre in una nuova scheda)', '(opens in a new tab)')) ?></span></a></p>
    <?php endif; ?>
  </div>
  <figure class="casa-home__fig">
    <div class="casa-home__pagina">
      <span class="casa-home__indietro" aria-hidden="true"><?= Icon::svg('back', 14, 2.2) ?><?= Support::e(I18n::t($locSito, 'back')) ?></span>
      <div class="casa-home__pista">
        <?php foreach ($fotoH as [$uH, $altH]): ?><img src="<?= Support::e($uH) ?>" alt="<?= Support::e($altH) ?>" width="800" height="600" loading="lazy" decoding="async"><?php endforeach; ?>
        <?php if ($nH > 1): ?><span class="casa-slide__conta" aria-hidden="true"><?= $iH + 1 ?> / <?= $nH ?></span><?php endif; ?>
      </div>
      <?php if ($nH > 1): ?><div class="casa-slide__puntini" aria-hidden="true"><?php for ($k = 0; $k < $nH; $k++): ?><i<?= $k === $iH ? ' class="on"' : '' ?>></i><?php endfor; ?></div><?php endif; ?>
      <b class="casa-home__titolo"><?= Support::e($titoloH) ?></b>
      <?php if ($testoH !== ''): ?><p class="casa-home__testo"><?= Support::e($testoH) ?></p>
        <span class="casa-home__piu" aria-hidden="true"><?= Support::e(I18n::t($locSito, 'casa_more')) ?></span><?php endif; ?>
    </div>
    <figcaption class="small muted"><?= Support::e($U('La pagina «La casa» nella guida di ' . $tel['nome'] . ': l\'ospite la apre dalla foto di copertina.', 'The “house” page in the ' . $tel['nome'] . ' guide: guests open it from the cover photo.')) ?></figcaption>
  </figure>
</section>
