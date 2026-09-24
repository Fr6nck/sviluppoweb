<?php
/** @var list<array{chiave:string,nome:string,voci:int,modificate:int}> $sezioni */
?>
<p class="adm-intro">Tutti i testi del sito, in italiano e in inglese, divisi come le pagine.
   Ogni testo modificato resta accanto all'originale, e tornare all'originale vuol dire svuotare il campo.</p>

<ul class="adm-sezioni">
  <?php foreach ($sezioni as $s): ?>
    <li>
      <a href="<?= e(adminUrl('testi/' . $s['chiave'])) ?>"><?= e($s['nome']) ?></a>
      <span class="adm-tenue"><?= (int) $s['voci'] ?> testi<?= $s['modificate'] > 0 ? ' · ' . (int) $s['modificate'] . ' modificati' : '' ?></span>
    </li>
  <?php endforeach; ?>
</ul>
