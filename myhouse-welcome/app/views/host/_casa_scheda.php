<?php use function MHW\t, MHW\tf;
/* 9V · La scheda «La casa: foto e descrizione» in Contenuti (e nel passo «Sezioni» della procedura): porta alla
   pagina con la galleria e i testi. Dice a che punto è: quante foto, se manca tutto, se è nascosta agli ospiti.
   Inclusa in tutti i piani: non conta nel limite delle sezioni. Riceve: $prop. */
use function MHW\b;
use MHW\{Support, Icon, Casa};
if (!Casa::disponibile()) return;
$nC = Casa::quante((int) $prop['id']);
$descrC = trim((string) ($prop['casa_descrizione'] ?? '')) !== '';
$spentaC = (int) ($prop['casa_attiva'] ?? 1) !== 1; ?>
<div class="stack" style="gap:10px">
  <a class="rowcard" href="<?= b() ?>/pannello/<?= (int) $prop['id'] ?>/casa" data-casa-scheda>
    <span style="color:var(--accent);display:flex"><?= Icon::svg('photo', 20) ?></span>
    <b class="grow"><?= t('La casa: foto e descrizione') ?> <?= Casa::badge(MHW\Pannello::en()) ?></b>
    <?php if (!$nC && !$descrC): ?><span class="badge badge--ochre"><?= t('Da aggiungere') ?></span>
    <?php elseif ($spentaC): ?><span class="badge badge--paper"><?= t('Nascosta agli ospiti') ?></span>
    <?php else: ?><span class="badge badge--pine"><?= Support::e($nC ? tf('{n} di {max} foto', ['{n}' => $nC, '{max}' => Casa::MAX_FOTO]) : t('Solo la descrizione')) ?></span><?php endif; ?>
    <span class="small muted"><?= t('Modifica') ?></span>
  </a>
  <p class="tiny muted"><?= t('L\'ospite la apre toccando la foto di copertina: fino a 10 foto e una breve descrizione. Non conta nel limite delle sezioni del piano.') ?></p>
</div>
