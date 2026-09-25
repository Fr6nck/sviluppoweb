<?php
/**
 * L'elenco numerato: ordinale terracotta, voce, nota, freccia.
 *
 * Fa da elenco dei luoghi di Assisi, delle altre camere, delle strade per
 * chi si è perso. Gli ordinali sono scritti nel markup e non generati in CSS,
 * così restano selezionabili; agli screen reader li dice già la lista.
 *
 * @var array $voci   [['etichetta' => …, 'nota' => …|null, 'nota_html' => …|null,
 *                      'href' => …|null, 'esterno' => bool]]
 */
?>
<ol class="adv-tappe">
  <?php $n = 0; foreach ($voci as $voce): $n++; ?>
    <?php
    $href    = $voce['href'] ?? null;
    $esterno = !empty($voce['esterno']);
    $tag     = $href ? 'a' : 'div';
    ?>
    <li>
      <<?= $tag ?> class="adv-tappe__voce"<?= $href ? ' href="' . e($href) . '"' : '' ?><?= $esterno ? ' target="_blank" rel="noopener"' : '' ?>>
        <span class="adv-tappe__numero" aria-hidden="true"><?= sprintf('%02d', $n) ?></span>
        <span class="adv-tappe__corpo">
          <?= e($voce['etichetta']) ?>
          <?php if (!empty($voce['nota']) || !empty($voce['nota_html'])): ?>
            <span class="adv-tappe__nota"><?= $voce['nota_html'] ?? e($voce['nota']) ?></span>
          <?php endif; ?>
          <?php if ($esterno): ?>
            <span class="adv-visually-hidden">— <?= te('home.position.new_tab') ?></span>
          <?php endif; ?>
        </span>
        <?php if ($href): ?><?= icona('freccia-su-destra', 15, 'adv-tappe__freccia') ?><?php endif; ?>
      </<?= $tag ?>>
    </li>
  <?php endforeach; ?>
</ol>
