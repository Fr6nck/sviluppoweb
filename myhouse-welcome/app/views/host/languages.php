<?php
/* Lingue: quali pubblicare e quanto è tradotto ciascuna. */
use function MHW\b;
use MHW\{Support, Icon, Csrf};
$title = 'Lingue — ' . $prop['name'];
$def = $prop['default_locale']; ?>
<div class="stack stack--lg" style="max-width:760px">
  <div class="stack stack--sm">
    <h1>Lingue.</h1>
    <p class="lead">Scegli in quali lingue pubblicare la guida e scrivi le traduzioni, sezione per sezione, accanto al testo originale.</p>
  </div>
  <?php if ($err): ?><p class="note note--err" role="alert"><?= Support::e($err) ?></p><?php endif; ?>

  <?php $dopoPasso = ''; include __DIR__ . '/_lingue_form.php'; ?>

  <?php /* Traduzioni suggerite: l'interruttore della struttura, l'anno in omaggio. Niente prezzi. */ ?>
  <section class="panel stack" id="suggerite" aria-labelledby="suggerite-titolo">
    <div class="row" style="gap:10px;align-items:center;flex-wrap:wrap">
      <h2 id="suggerite-titolo" style="font-size:22px;margin:0">Traduzioni suggerite</h2>
      <?php if (!$trad['piano']): ?><span class="badge badge--paper">con il piano Plus</span>
      <?php elseif ($trad['acceso'] && !$trad['finito']): ?><span class="badge badge--pine">Accese</span>
      <?php elseif ($trad['acceso']): ?><span class="badge badge--ochre">Omaggio finito</span><?php endif; ?>
    </div>
    <p class="help" style="margin:0">Un traduttore automatico propone le traduzioni dei testi che mancano. Tu le controlli e le approvi: finché non le approvi, gli ospiti non le vedono.</p>
    <?php if ($trad['spiegazione']): ?>
      <div class="note note--ok" role="status" style="display:block">
        <p style="margin:0">Le trovi nella pagina di ogni lingua, qui sotto: tocca «Suggerisci le traduzioni mancanti», poi approvale una per una.</p>
        <p style="margin:6px 0 0">Per i testi importanti, falli rileggere a un madrelingua.</p>
      </div>
    <?php endif; ?>
    <?php if (!$trad['piano']): ?>
      <label class="scelta scelta--fissa"><input type="checkbox" disabled><span class="scelta__testo">Accendi le traduzioni suggerite <span class="small muted">con il piano Plus</span></span></label>
      <p class="small" style="margin:0"><a href="<?= b() ?>/piano?passa=plus">Scopri Plus: traduzioni suggerite in omaggio per un anno</a></p>
    <?php else: ?>
      <?php if ($trad['finito']): ?>
        <p class="small" style="margin:0">L'anno in omaggio è finito il <?= Support::e(Support::date((string) $trad['fino'])) ?>: le traduzioni che hai approvato restano, ma non se ne possono chiedere di nuove.</p>
      <?php elseif ($trad['fino']): ?>
        <p class="small" style="margin:0">In omaggio fino al <?= Support::e(Support::date((string) $trad['fino'])) ?>, per tutte le strutture del tuo account.</p>
      <?php else: ?>
        <p class="small" style="margin:0">In omaggio per un anno dalla prima accensione.</p>
      <?php endif; ?>
      <form method="post" action="<?= b() ?>/pannello/<?= (int) $prop['id'] ?>/lingue/suggerite" class="actions" style="margin:0"><?= Csrf::field() ?>
        <?php if ($trad['acceso']): ?>
          <input type="hidden" name="acceso" value="0"><button class="btn btn--ghost btn--sm">Spegni per questa struttura</button>
        <?php else: ?>
          <input type="hidden" name="acceso" value="1"><button class="btn btn--sm">Accendi le traduzioni suggerite</button>
        <?php endif; ?>
      </form>
    <?php endif; ?>
  </section>

  <?php $altre = array_values(array_filter($lingueAttive, fn($l) => $l !== $def)); if ($altre): ?>
    <div class="stack" style="gap:10px">
      <h2 style="font-size:22px">Traduzioni</h2>
      <?php foreach ($altre as $l): [$fatte, $tot] = $copertura[$l] ?? [0, 0]; $ok = in_array($l, $consentite, true);
            $perc = $tot ? (int) floor($fatte / $tot * 100) : 100; ?>
        <a class="rowcard <?= $ok ? '' : 'rowcard--locked' ?>" href="<?= $ok ? b() . '/pannello/' . (int) $prop['id'] . '/lingue/' . Support::e($l) : '#' ?>">
          <span style="color:var(--accent);display:flex"><?= Icon::svg('globe', 20) ?></span>
          <b class="grow"><?= Support::e($tutte[$l] ?? $l) ?> <span class="perc"><?= $perc ?>%</span></b>
          <span class="meter"><i><b style="width:<?= $perc ?>%"></b></i><?= $tot - $fatte ? ($tot - $fatte) . ' camp' . ($tot - $fatte === 1 ? 'o' : 'i') . ' da tradurre' : 'Tutto tradotto' ?></span>
          <?php if ($ok && ($n = $trad['daControllare'][$l] ?? 0)): ?><span class="badge badge--ochre"><?= $n ?> suggerit<?= $n === 1 ? 'a' : 'e' ?> da controllare</span><?php endif; ?>
          <span class="small muted"><?= $ok ? 'Traduci' : 'Non compresa nel piano' ?></span>
        </a>
      <?php endforeach; ?>
      <p class="tiny muted">Le traduzioni non servono per pubblicare: dove mancano, l'ospite legge il testo nella lingua principale.</p>
    </div>
  <?php endif; ?>
</div>
