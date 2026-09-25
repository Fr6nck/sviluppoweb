<?php
/**
 * Il pulsante del tema chiaro o scuro.
 *
 * Senza JavaScript è un modulo: il server scrive la scelta in un cookie e
 * torna alla stessa pagina. Con JavaScript il cambio è immediato, senza
 * ricaricare (admin.js). Finché non si sceglie, il pannello segue il tema
 * del telefono o del computer.
 *
 * @var string|null $tema     chiaro | scuro | null
 * @var string      $ritorno  la pagina a cui tornare, sotto /admin
 * @var string      $classe
 */
use ArcoDelVento\Support\Csrf;

$scuro = $tema === 'scuro';
?>
<form method="post" action="<?= e(adminUrl('tema')) ?>" class="adm-tema <?= e($classe ?? '') ?>"
      data-tema-modulo data-tema-percorso="<?= e(adminUrl()) ?>">
  <?= Csrf::field() ?>
  <input type="hidden" name="ritorno" value="<?= e($ritorno) ?>">
  <button type="submit" name="tema" value="<?= $scuro ? 'chiaro' : 'scuro' ?>" class="adm-tema__bottone"
          aria-label="Tema scuro" aria-pressed="<?= $scuro ? 'true' : 'false' ?>" title="Tema chiaro o scuro">
    <?= icona('luna', 19, 'adm-tema__luna') ?><?= icona('sole', 19, 'adm-tema__sole') ?>
  </button>
</form>
