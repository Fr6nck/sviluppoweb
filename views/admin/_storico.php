<?php
/**
 * Le versioni precedenti di un archivio, con il ripristino.
 *
 * @var list<array{id:string,quando:int,nome?:string,lingua?:string}> $storico
 * @var string $nome     l'archivio (se le voci non lo portano con sé)
 * @var string $ritorno  dove tornare dopo il ripristino
 */
use ArcoDelVento\Support\Csrf;

$storico = array_slice($storico ?? [], 0, 8);
if ($storico === []) {
    return;
}
?>
<details class="adm-storico">
  <summary>Versioni precedenti</summary>
  <p class="adm-nota">Ogni salvataggio tiene la versione di prima. Ripristinarne una non cancella quella attuale: finisce a sua volta qui.</p>
  <ul>
    <?php foreach ($storico as $versione): ?>
      <li>
        <form method="post" action="<?= e(adminUrl('ripristina')) ?>">
          <?= Csrf::field() ?>
          <input type="hidden" name="nome" value="<?= e($versione['nome'] ?? $nome) ?>">
          <input type="hidden" name="versione" value="<?= e($versione['id']) ?>">
          <input type="hidden" name="ritorno" value="<?= e($ritorno) ?>">
          <span><?= isset($versione['lingua']) ? e($versione['lingua']) . ' · ' : '' ?>salvata il <?= e(date('d/m/Y \a\l\l\e H:i', $versione['quando'])) ?></span>
          <button type="submit" class="adm-bottone adm-bottone--piatto">Ripristina questa</button>
        </form>
      </li>
    <?php endforeach; ?>
  </ul>
</details>
