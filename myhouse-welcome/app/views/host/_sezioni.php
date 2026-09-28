<?php
/* L'elenco delle sezioni aggiuntive e il catalogo da cui aggiungerne.
   Si ordinano con "Sposta su / giù": niente trascinamento, niente maniglie
   che promettono un gesto che non esiste.
   Riceve: $prop, $sezioni (solo le aggiuntive), $limite, $attive, $torna ('' | 'procedura'). */
use function MHW\b;
use MHW\{Support, Csrf, Icon, SectionCatalog};
$pid = (int) $prop['id'];
$torna = $torna ?? '';
$illimitate = $limite >= PHP_INT_MAX;
$pieno = !$illimitate && $attive >= $limite;
$presenti = array_column($sezioni, 'kind');
$catalogo = array_values(array_diff(SectionCatalog::selectable(), $presenti));
$azione = function (array $s, string $fai, string $etichetta, string $classe = 'btn btn--quiet btn--sm') use ($pid, $torna): string {
    return '<form method="post" action="' . b() . '/pannello/' . $pid . '/sezioni/' . (int) $s['id'] . '/azione" style="margin:0">'
         . Csrf::field() . '<input type="hidden" name="fai" value="' . $fai . '"><input type="hidden" name="torna" value="' . Support::e($torna) . '">'
         . '<button class="' . $classe . '">' . $etichetta . '</button></form>';
};
$n = count($sezioni); ?>
<div class="stack" style="gap:10px">
  <div class="spread spread--mid">
    <h2 style="font-size:22px">Sezioni aggiuntive</h2>
    <?php if (!$illimitate): ?>
      <span class="meter"><i><b style="width:<?= min(100, (int) round($attive / max(1, $limite) * 100)) ?>%"></b></i>
        <?= (int) $attive ?> sezion<?= $attive === 1 ? 'e' : 'i' ?> su <?= (int) $limite ?> utilizzat<?= $attive === 1 ? 'a' : 'e' ?></span>
    <?php else: ?>
      <span class="small muted">Sezioni illimitate con il tuo piano</span>
    <?php endif; ?>
  </div>
  <?php if (!$sezioni): ?>
    <p class="note note--quiet">Nessuna sezione aggiuntiva, per ora. Scegline qualcuna qui sotto: Wi-Fi e Regole della casa sono le più lette.</p>
  <?php endif; ?>
  <?php foreach ($sezioni as $i => $s): $on = (int) $s['is_active'] === 1; ?>
    <div class="rowcard <?= $on ? ($s['empty'] ? 'rowcard--flag' : '') : 'rowcard--locked' ?>" style="flex-wrap:wrap">
      <span style="color:var(--accent);display:flex"><?= Icon::svg(SectionCatalog::icon($s['kind']), 20) ?></span>
      <a class="grow" href="<?= b() ?>/pannello/<?= $pid ?>/sezioni/<?= (int) $s['id'] ?><?= $torna === 'procedura' ? '?da=procedura' : '' ?>" style="color:var(--ink)">
        <b><?= Support::e($s['title']) ?></b></a>
      <?php if (!$on): ?><span class="badge badge--paper">Disattivata</span>
      <?php elseif ($s['empty']): ?><span class="badge badge--terracotta">Da compilare</span>
      <?php else: ?><span class="badge badge--pine">Pronta</span><?php endif; ?>
      <span class="row" style="gap:2px">
        <?= $i > 0 ? $azione($s, 'su', '<span class="sr-only">' . Support::e($s['title']) . ': </span>Sposta su') : '' ?>
        <?= $i < $n - 1 ? $azione($s, 'giu', '<span class="sr-only">' . Support::e($s['title']) . ': </span>Sposta giù') : '' ?>
        <?= $on ? $azione($s, 'disattiva', 'Disattiva') : $azione($s, 'attiva', 'Attiva') ?>
        <details class="langpick">
          <summary class="btn btn--quiet btn--sm" aria-label="Altro per <?= Support::e($s['title']) ?>">Altro</summary>
          <div class="langpick__menu" style="min-width:240px;padding:12px">
            <p class="small muted" style="margin-bottom:8px">Eliminare cancella anche i contenuti e le traduzioni. Per toglierla solo dalla guida, disattivala.</p>
            <?= $azione($s, 'elimina', 'Elimina la sezione', 'btn btn--danger btn--sm btn--block') ?>
          </div>
        </details>
      </span>
    </div>
  <?php endforeach; ?>
</div>

<?php if ($catalogo): ?>
<div class="stack" style="gap:12px;margin-top:8px">
  <h3 style="font-size:18px">Aggiungi una sezione</h3>
  <?php if ($pieno): ?>
    <div class="limit" role="status">
      <p style="max-width:620px">Hai utilizzato tutte le <?= (int) $limite ?> sezioni incluse nel tuo piano. Passa a Plus per aggiungere
        tutte le sezioni che vuoi, oppure disattivane una per liberare un posto.</p>
      <a class="btn btn--sm" href="<?= b() ?>/piano">Scopri Plus</a>
    </div>
  <?php endif; ?>
  <div class="kinds">
    <?php foreach ($catalogo as $k): ?>
      <div class="kind <?= $pieno ? 'kind--off' : '' ?>">
        <span class="ico"><?= Icon::svg(SectionCatalog::icon($k), 22) ?></span>
        <b><?= Support::e(SectionCatalog::title($k, 'it')) ?></b>
        <?php if (!$pieno): ?>
          <form method="post" action="<?= b() ?>/pannello/<?= $pid ?>/sezioni"><?= Csrf::field() ?>
            <input type="hidden" name="kind" value="<?= Support::e($k) ?>"><input type="hidden" name="torna" value="<?= Support::e($torna) ?>">
            <button class="btn btn--ghost btn--sm"><?= Icon::svg('plus', 15, 2) ?>Aggiungi<span class="sr-only"> <?= Support::e(SectionCatalog::title($k, 'it')) ?></span></button>
          </form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>
