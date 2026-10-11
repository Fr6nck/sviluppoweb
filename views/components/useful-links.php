<?php
/**
 * I siti ufficiali per orari e tariffe che cambiano: il parcheggio di Piazza
 * Matteotti, gli autobus urbani, il bus dall'aeroporto. Il sito rimanda lì
 * invece di copiare numeri che invecchiano. Gli indirizzi si cambiano
 * dall'area riservata (La struttura → Link utili e mappa).
 *
 * @var list<string>|null $quali  parking | bus | airlink (tutti, se non indicato)
 */

$quali = $quali ?? ['parking', 'bus', 'airlink'];
$link  = (array) site('links', []);
$voci  = [];
foreach ($quali as $chiave) {
    $href = (string) ($link[$chiave] ?? '');
    if ($href !== '') {
        $voci[] = [$href, t('info.known.link_' . $chiave)];
    }
}
if ($voci === []) {
    return;
}
?>
<div class="adv-link-utili">
  <p class="adv-link-utili__titolo"><?= te('info.known.links_title') ?></p>
  <ul>
    <?php foreach ($voci as [$href, $testo]): ?>
      <li>
        <a class="adv-link" href="<?= e($href) ?>" target="_blank" rel="noopener">
          <?= e($testo) ?><span class="adv-visually-hidden"> — <?= te('info.known.new_tab') ?></span><?= icona('freccia-su-destra', 13) ?>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>
</div>
