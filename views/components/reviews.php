<?php
/**
 * Le recensioni, come le riportano Booking e Google.
 *
 * Compare solo quando per una piattaforma ci sono sia il punteggio sia il
 * numero delle recensioni, inseriti dall'area riservata dopo averli
 * verificati. Nessun testo di recensione, nessuna citazione: solo le cifre,
 * con il link alla fonte, così chi legge può controllare.
 *
 * Niente aggregateRating nei dati strutturati: Google non accetta le
 * recensioni che un'attività riporta su sé stessa.
 */

$piattaforme = [];
foreach (['booking' => 10, 'google' => 5] as $chiave => $scala) {
    $voto   = site('reviews.' . $chiave . '.score');
    $numero = site('reviews.' . $chiave . '.count');
    if ($voto === null || $voto === '' || empty($numero)) {
        continue;
    }
    $piattaforme[] = [
        'voto'   => number_format((float) $voto, 1, locale() === 'it' ? ',' : '.', ''),
        'scala'  => $scala,
        'numero' => (int) $numero,
        'nome'   => t('home.reviews.' . $chiave),
        'url'    => (string) (site('reviews.' . $chiave . '.url') ?? ''),
    ];
}

if ($piattaforme === []) {
    return;
}
?>
<div class="adv-recensioni">
  <h3 class="adv-titolo-md"><?= te('home.reviews.title') ?></h3>
  <ul class="adv-numeri">
    <?php foreach ($piattaforme as $p): ?>
      <li>
        <span class="adv-numeri__valore"><?= e($p['voto']) ?><span class="adv-recensioni__scala">/<?= (int) $p['scala'] ?></span></span>
        <span class="adv-numeri__nome">
          <?php if ($p['url'] !== ''): ?>
            <a href="<?= e($p['url']) ?>" rel="noopener"><?= te('home.reviews.count', ['count' => number_format($p['numero'], 0, ',', '.'), 'platform' => $p['nome']]) ?></a>
          <?php else: ?>
            <?= te('home.reviews.count', ['count' => number_format($p['numero'], 0, ',', '.'), 'platform' => $p['nome']]) ?>
          <?php endif; ?>
        </span>
      </li>
    <?php endforeach; ?>
  </ul>
</div>
