<?php
/* Il Portfolio struttura per struttura: la prima, poi gli scaglioni, e quanto costa in media
   ciascuna per il numero scelto (lo aggiorna prezzi.js). Riceve: $pvS (versione), $qS (quantità). */
use MHW\{Support, Plans, LinguaSito, Iva};
$enS = LinguaSito::en();
$ordS = fn(int $n) => $n . (in_array($n % 100, [11, 12, 13], true) ? 'th' : ([1 => 'st', 2 => 'nd', 3 => 'rd'][$n % 10] ?? 'th'));
$maxS = (int) ($pvS['max_quantity'] ?? 0);   // 9A: l'ultimo scaglione arriva al massimo del listino
$etS = function (array $t) use ($enS, $ordS, $maxS): string {
    if ($t['a'] === null && $maxS > $t['da']) $t['a'] = $maxS;
    if (!$enS) return ucfirst(Plans::tierLabel($t, $maxS));
    return $t['a'] === null ? ($t['da'] === 2 ? 'From the 2nd on' : 'Beyond the ' . $ordS($t['da'] - 1)) : ($t['a'] === $t['da'] ? ucfirst($ordS($t['da'])) : 'From the ' . $ordS($t['da']) . ' to the ' . $ordS($t['a']));
}; ?>
<div class="scaglioni">
  <?php $minS = max(2, (int) ($pvS['min_quantity'] ?? 2)); /* 9N · accanto a Plus: si vede subito che Portfolio parte da 2 strutture */ ?>
  <p class="scaglioni__titolo"><?= $enS ? 'What each property costs, per year · from ' . $minS . ' properties' : 'Quanto costa ogni struttura, all\'anno · da ' . $minS . ' strutture' ?></p>
  <ul class="scaglioni__elenco">
    <li><span><?= $enS ? '1st' : '1ª' ?></span><?= Iva::cifra((int) $pvS['price_cents'], $pvS['currency'], '', 'b') ?></li>
    <?php foreach (Plans::tiers($pvS) as $t): ?>
      <li><span><?= Support::e($etS($t)) ?></span><b>+<?= Iva::cifra($t['cents'], $pvS['currency']) ?><?php if ($t['a'] !== $t['da']): ?> <small><?= $enS ? 'each' : 'l\'una' ?></small><?php endif; ?></b></li>
    <?php endforeach; ?>
  </ul>
  <p class="scaglioni__media"><?= $enS ? 'With' : 'Con' ?> <span data-quante><?= (int) $qS ?></span> <?= ($enS ? 'properties: on average' : 'strutture: in media') . "\n" ?>    <?= Iva::cifra((int) round(Plans::price($pvS, $qS) / max(1, $qS)), $pvS['currency'], 'data-media', 'b') ?> <?= $enS ? Iva::nota('per property + VAT.', 'per property incl. VAT.') : Iva::nota('a struttura + IVA.', 'a struttura IVA inclusa.') ?></p>
</div>
