<?php use function MHW\t, MHW\tf;
/* Lingue: quali pubblicare e quanto è tradotto ciascuna. */
use function MHW\b;
use MHW\{Support, Icon, Csrf};
$title = t('Lingue — ') . $prop['name'];
$def = $prop['default_locale']; ?>
<div class="stack stack--lg" style="max-width:760px">
  <div class="stack stack--sm">
    <h1><?= t('Lingue.') ?></h1>
    <p class="lead"><?= t('Scegli in quali lingue pubblicare la guida e scrivi le traduzioni, sezione per sezione, accanto al testo originale.') ?></p>
  </div>
  <?php if ($err): ?><p class="note note--err" role="alert"><?= Support::e($err) ?></p><?php endif; ?>
  <?php /* 9F · «Una lingua in più, oltre alle cinque di base»: sopra la scelta, per tutti. */
        include __DIR__ . '/_lingua_come.php'; ?>

  <?php $dopoPasso = ''; include __DIR__ . '/_lingue_form.php'; ?>

  <?php /* Traduzioni suggerite (9G): comprese in Plus e Portfolio entro un limite annuale di caratteri; il contatore dell'anno. Niente prezzi. */ ?>
  <section class="panel stack" id="suggerite" aria-labelledby="suggerite-titolo">
    <div class="row" style="gap:10px;align-items:center;flex-wrap:wrap">
      <h2 id="suggerite-titolo" style="font-size:22px;margin:0"><?= t('Traduzioni suggerite') ?></h2>
      <?php if (!$trad['piano']): ?><span class="badge badge--paper"><?= t('con il piano Plus') ?></span>
      <?php elseif ($trad['anno'] && $trad['anno']['finito']): ?><span class="badge badge--ochre"><?= t('Limite dell\'anno raggiunto') ?></span>
      <?php elseif ($trad['acceso']): ?><span class="badge badge--pine"><?= t('Accese') ?></span><?php endif; ?>
    </div>
    <p class="help" style="margin:0"><?= t('Un traduttore automatico propone le traduzioni dei testi che mancano. Tu le controlli e le approvi: finché non le approvi, gli ospiti non le vedono.') ?></p>
    <?php if ($trad['spiegazione']): ?>
      <div class="note note--ok" role="status" style="display:block">
        <p style="margin:0"><?= t('Le trovi nella pagina di ogni lingua, qui sotto: tocca «Suggerisci le traduzioni mancanti», poi approvale una per una.') ?></p>
        <p style="margin:6px 0 0"><?= t('Per i testi importanti, falli rileggere a un madrelingua.') ?></p>
      </div>
    <?php endif; ?>
    <?php if (!$trad['piano']): ?>
      <label class="scelta scelta--fissa"><input type="checkbox" disabled><span class="scelta__testo"><?= t('Accendi le traduzioni suggerite') ?> <span class="small muted"><?= t('con il piano Plus') ?></span></span></label>
      <p class="small" style="margin:0"><a href="<?= b() ?>/piano?passa=plus"><?= t('Scopri Plus: traduzioni suggerite comprese nel piano') ?></a></p>
    <?php elseif (!MHW\Traduttore::attiva((int) $prop['account_id'])): /* 9M · prima del pagamento: il bottone c'è, spento, con lo spiegone */ ?>
      <?php include __DIR__ . '/_attivazione.php'; ?>
      <div class="actions" style="margin:0"><button type="button" class="btn btn--sm" disabled><?= t('Accendi le traduzioni suggerite') ?></button></div>
    <?php else: ?>
      <?php $annoT = $trad['anno']; include __DIR__ . '/_traduzioni_contatore.php'; ?>
      <form method="post" action="<?= b() ?>/pannello/<?= (int) $prop['id'] ?>/lingue/suggerite" class="actions" style="margin:0"><?= Csrf::field() ?>
        <?php if ($trad['acceso']): ?>
          <input type="hidden" name="acceso" value="0"><button class="btn btn--ghost btn--sm"><?= t('Spegni per questa struttura') ?></button>
        <?php else: ?>
          <input type="hidden" name="acceso" value="1"><button class="btn btn--sm"><?= t('Accendi le traduzioni suggerite') ?></button>
        <?php endif; ?>
      </form>
    <?php endif; ?>
  </section>

  <?php $altre = array_values(array_filter($lingueAttive, fn($l) => $l !== $def)); if ($altre): ?>
    <div class="stack" style="gap:10px">
      <h2 style="font-size:22px"><?= t('Traduzioni') ?></h2>
      <?php foreach ($altre as $l): [$fatte, $tot] = $copertura[$l] ?? [0, 0]; $ok = in_array($l, $consentite, true);
            $perc = $tot ? (int) floor($fatte / $tot * 100) : 100; ?>
        <a class="rowcard <?= $ok ? '' : 'rowcard--locked' ?>" href="<?= $ok ? b() . '/pannello/' . (int) $prop['id'] . '/lingue/' . Support::e($l) : '#' ?>">
          <span style="color:var(--accent);display:flex"><?= Icon::svg('globe', 20) ?></span>
          <b class="grow"><?= Support::e($tutte[$l] ?? $l) ?> <span class="perc"><?= $perc ?>%</span></b>
          <span class="meter"><i><b style="width:<?= $perc ?>%"></b></i><?= Support::e($tot - $fatte ? ($tot - $fatte === 1 ? t('1 campo da tradurre') : tf('{n} campi da tradurre', ['{n}' => $tot - $fatte])) : t('Tutto tradotto')) ?></span>
          <?php if ($ok && ($n = $trad['daControllare'][$l] ?? 0)): ?><span class="badge badge--ochre"><?= Support::e($n === 1 ? t('1 suggerita da controllare') : tf('{n} suggerite da controllare', ['{n}' => $n])) ?></span><?php endif; ?>
          <span class="small muted"><?= $ok ? t('Traduci') : t('Non compresa nel piano') ?></span>
        </a>
      <?php endforeach; ?>
      <p class="tiny muted"><?= t('Le traduzioni non servono per pubblicare: dove mancano, l\'ospite legge il testo nella lingua principale.') ?></p>
    </div>
  <?php endif; ?>
</div>
