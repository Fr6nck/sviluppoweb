<?php
/**
 * L'indice numerato: ordinale, voce, nota. Da quattro a sette voci.
 *
 * Qui fa da elenco dei luoghi che si raggiungono a piedi. Gli ordinali sono
 * scritti nel markup e non generati in CSS, così restano selezionabili e
 * copiabili — e gli screen reader leggono già la numerazione della lista.
 *
 * @var array  $voci   [['etichetta' => …, 'nota' => …|null, 'href' => …|null]]
 */
?>
<ol class="adv-indice">
  <?php $n = 0; foreach ($voci as $voce): $n++; ?>
    <li class="adv-indice__voce">
      <?php $tag = !empty($voce['href']) ? 'a' : 'div'; ?>
      <<?= $tag ?> class="adv-indice__link"<?= !empty($voce['href']) ? ' href="' . e($voce['href']) . '"' : '' ?>>
        <span class="adv-indice__numero"><?= sprintf('%02d', $n) ?></span>
        <span class="adv-indice__etichetta"><?= e($voce['etichetta']) ?></span>
        <?php if (!empty($voce['nota']) || !empty($voce['nota_html'])): ?>
          <span class="adv-indice__nota"><?= $voce['nota_html'] ?? e($voce['nota']) ?></span>
        <?php endif; ?>
      </<?= $tag ?>>
    </li>
  <?php endforeach; ?>
</ol>
