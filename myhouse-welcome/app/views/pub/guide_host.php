<?php
/* 9L · /guide-dei-nostri-host, «Tutte le guide»: le schede approvate di «Le guide dei nostri host» (le stesse della home,
   nell'ordine deciso in Vendite → Giudizi e vetrina), 24 per pagina. Nessun link alle guide, nessuna foto dell'host.
   Riceve $schede, $pagina, $pagine, $totale (Vetrina::tutte). */
use function MHW\b;
use MHW\{Support, TestiSito};
$title = 'Le guide dei nostri host · MyHouse Welcome'; ?>
<section class="blocco dicono tutte-guide" aria-labelledby="tutte-titolo">
  <p class="small"><a href="<?= b() ?>/#guide-host">← Torna alla home</a></p>
  <div class="stack stack--sm" style="max-width:640px">
    <span class="kicker"><?= TestiSito::html('vetrina.kicker') ?></span>
    <h1 id="tutte-titolo" class="h-sezione"><?= TestiSito::html('vetrina.titolo') ?></h1>
    <p class="muted" style="line-height:24px"><?= TestiSito::html('vetrina.sotto') ?></p>
  </div>
  <?php if ($schede): ?>
    <div class="dicono__schede" role="list">
      <?php foreach ($schede as $v): ?><div role="listitem" class="dicono__scheda"><?php include __DIR__ . '/_vetrina_scheda.php'; ?></div><?php endforeach; ?>
    </div>
    <?php if ($pagine > 1): ?>
      <nav class="tutte-guide__pagine" aria-label="Pagine">
        <?php for ($n = 1; $n <= $pagine; $n++): ?>
          <a class="btn btn--sm<?= $n === $pagina ? '' : ' btn--ghost' ?>" href="<?= b() ?>/guide-dei-nostri-host<?= $n > 1 ? '?pagina=' . $n : '' ?>"<?= $n === $pagina ? ' aria-current="page"' : '' ?>><?= $n ?></a>
        <?php endfor; ?>
      </nav>
    <?php endif; ?>
    <p class="dicono__nota"><?= TestiSito::html('vetrina.nota') ?></p>
  <?php else: ?>
    <p class="note note--quiet" style="margin-top:24px">Non ci sono ancora guide da mostrare. Torna presto.</p>
  <?php endif; ?>
</section>
