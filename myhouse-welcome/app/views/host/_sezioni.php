<?php
/* L'elenco delle sezioni aggiuntive e il catalogo da cui aggiungerne.
   Si ordinano trascinando la maniglia, oppure dal menu ⋯ con «Sposta su /
   giù» (tastiera e telefono): tutte e due usano le stesse rotte.
   Riceve: $prop, $sezioni (solo le aggiuntive), $limite, $attive, $torna ('' | 'procedura'). */
use function MHW\b;
use MHW\{Support, Csrf, Icon, SectionCatalog};
$pid = (int) $prop['id'];
$torna = $torna ?? '';
$illimitate = $limite >= PHP_INT_MAX;
$pieno = !$illimitate && $attive >= $limite;
$presenti = array_column($sezioni, 'kind');
$catalogo = array_values(array_diff(SectionCatalog::selectable(), $presenti));
$azione = function (array $s, string $fai, string $etichetta, string $classe = 'menu-riga__voce') use ($pid, $torna): string {
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
  <?php if ($sezioni): ?><div class="righe" data-ordina><?php endif; ?>
  <?php foreach ($sezioni as $i => $s): $on = (int) $s['is_active'] === 1;
        $modifica = b() . '/pannello/' . $pid . '/sezioni/' . (int) $s['id'] . ($torna === 'procedura' ? '?da=procedura' : ''); ?>
    <div class="riga <?= $on ? ($s['empty'] ? 'riga--flag' : '') : 'riga--spenta' ?>" data-riga
         data-azione="<?= b() ?>/pannello/<?= $pid ?>/sezioni/<?= (int) $s['id'] ?>/azione" data-torna="<?= Support::e($torna) ?>">
      <span class="riga__maniglia" aria-hidden="true" title="Trascina per cambiare l'ordine"><?= Icon::svg('grip', 18, 2.6) ?></span>
      <span class="riga__ico"><?= Icon::svg(SectionCatalog::icon($s['kind']), 20) ?></span>
      <a class="riga__nome" href="<?= $modifica ?>"><b><?= Support::e($s['title']) ?></b></a>
      <?php if (!$on): ?><span class="badge badge--paper">Disattivata</span>
      <?php elseif ($s['empty']): ?><span class="badge badge--terracotta">Da compilare</span>
      <?php else: ?><span class="badge badge--pine">Pronta</span><?php endif; ?>
      <details class="menu-riga">
        <summary class="icon-btn" aria-label="Azioni per <?= Support::e($s['title']) ?>"><span aria-hidden="true">⋯</span></summary>
        <div class="menu-riga__lista">
          <a class="menu-riga__voce" href="<?= $modifica ?>">Modifica</a>
          <?= $i > 0 ? $azione($s, 'su', 'Sposta su') : '' ?>
          <?= $i < $n - 1 ? $azione($s, 'giu', 'Sposta giù') : '' ?>
          <?= $on ? $azione($s, 'disattiva', 'Disattiva') : $azione($s, 'attiva', 'Attiva') ?>
          <hr class="rule">
          <p class="tiny muted" style="padding:4px 12px">Eliminare cancella anche contenuti e traduzioni. Per toglierla solo dalla guida, disattivala.</p>
          <?= $azione($s, 'elimina', 'Elimina', 'menu-riga__voce menu-riga__voce--danger') ?>
        </div>
      </details>
    </div>
  <?php endforeach; ?>
  <?php if ($sezioni): ?></div><?php endif; ?>
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
