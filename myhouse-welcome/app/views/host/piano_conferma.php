<?php use function MHW\t, MHW\tf;
/* La conferma del cambio di piano (6H). Salire: il conto di oggi e il prezzo dal
   rinnovo, poi la pagina di Stripe. Scendere: dal rinnovo, con le scelte di cosa
   tenere (sezioni, strutture) e l'elenco di quello che non ci sarà più. */
use function MHW\b;
use MHW\{Support, Csrf, Icon, Plans, CambioPiano, SectionCatalog};
$title = tf('Passa a {piano}', ['{piano}' => $pv['name']]);
$sub = $st['sub']; $fine = (string) $sub['current_period_end']; $valuta = (string) $pv['currency'];
$soldi = fn(int $c) => Support::e(Support::money($c, $valuta));
$conQ = fn(array $v, int $n) => Support::e($v['name']) . (Plans::perProperty($v) ? ' · ' . Support::e($n === 1 ? t('1 struttura') : tf('{n} strutture', ['{n}' => $n])) : '');
$scende = $prev['tipo'] === CambioPiano::SCENDE;
$ivaCents = $iva ? (int) round($prev['conguaglio'] * 0.22) : 0; ?>
<div class="stack stack--lg" style="max-width:680px">
  <div class="stack stack--sm">
    <a class="small" href="<?= b() ?>/account/piano"><?= Icon::svg('back', 14) ?> <?= t('Cambia piano') ?></a>
    <h1><?= Support::e(tf('Passa a {piano}', ['{piano}' => $pv['name']])) ?>.</h1>
    <p class="lead"><?= tf('Da {da} a {a}.', ['{da}' => $conQ($st['pv'], $st['quantita']), '{a}' => '<b>' . $conQ($pv, $q) . '</b>']) ?></p>
  </div>
  <?php if ($err): ?><p class="note note--err" role="alert"><?= Support::e(t($err)) ?></p><?php endif; ?>

  <form method="post" action="<?= b() ?>/account/piano/conferma" class="stack"><?= Csrf::field() ?>
    <input type="hidden" name="piano" value="<?= Support::e($codice) ?>">
    <?php if (Plans::perProperty($pv)): ?><input type="hidden" name="strutture" value="<?= (int) $q ?>"><?php endif; ?>

    <?php if ($scende): ?>
      <p><?= tf('Dal {data}. Fino ad allora resti su {piano}: l\'hai già pagato. Oggi non paghi niente.', ['{data}' => '<b>' . Support::e(MHW\Pannello::data($fine)) . '</b>', '{piano}' => Support::e($st['pv']['name'])]) ?></p>
      <?php if ($cambia['sezioni'] || $cambia['daArchiviare'] > 0 || $cambia['perde']): ?>
        <section class="panel stack" aria-labelledby="cambia-titolo">
          <h2 id="cambia-titolo" style="font-size:20px;margin:0"><?= t('Cosa cambia quel giorno') ?></h2>
          <?php foreach ($cambia['sezioni'] as $b): $pid = (int) $b['struttura']['id']; ?>
            <fieldset class="fieldset" data-conta-max="<?= (int) $cambia['maxSezioni'] ?>">
              <legend><?= Support::e(tf('{nome}: scegli le {n} sezioni da tenere', ['{nome}' => $b['struttura']['name'], '{n}' => (int) $cambia['maxSezioni']])) ?></legend>
              <p class="help"><?= Support::e(tf('Ne hai {n} attive. Le altre si disattivano, ma restano salvate. Se non scegli, restano le prime in ordine.', ['{n}' => count($b['sezioni'])])) ?>
                <span data-conta aria-live="polite"></span></p>
              <div class="scelte">
                <?php foreach ($b['sezioni'] as $sz): ?>
                  <label class="scelta scelta--mini"><input type="checkbox" name="tieni[<?= $pid ?>][]" value="<?= (int) $sz['id'] ?>">
                    <span><?= Support::e(($sz['title'] ?? '') !== '' ? $sz['title'] : SectionCatalog::nome($sz['kind'])) ?></span></label>
                <?php endforeach; ?>
              </div>
            </fieldset>
          <?php endforeach; ?>
          <?php if ($cambia['daArchiviare'] > 0): ?>
            <fieldset class="fieldset">
              <legend><?= Support::e($cambia['daArchiviare'] === 1 ? t('Scegli 1 struttura da archiviare') : tf('Scegli {n} strutture da archiviare', ['{n}' => (int) $cambia['daArchiviare']])) ?></legend>
              <p class="help"><?= t('Una struttura archiviata va offline, ma contenuti, traduzioni e QR restano: la riattivi quando vuoi.') ?></p>
              <?php foreach ($cambia['strutture'] as $p): ?>
                <label class="scelta"><input type="checkbox" name="archivia[]" value="<?= (int) $p['id'] ?>">
                  <span><?= Support::e($p['name']) ?><?= $p['city'] ? ' <span class="small muted">· ' . Support::e($p['city']) . '</span>' : '' ?>
                    <?= $p['status'] === 'published' ? '<span class="badge badge--pine">' . t('Online') . '</span>' : '' ?></span></label>
              <?php endforeach; ?>
            </fieldset>
          <?php endif; ?>
          <?php if ($cambia['perde']): ?>
            <ul class="stack" style="gap:6px;margin:0;padding-left:20px">
              <?php foreach ($cambia['perde'] as $riga): ?><li><?= Support::e(t($riga)) ?></li><?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </section>
      <?php endif; ?>
      <p><?= t('Dal rinnovo:') ?> <b><?= $soldi($prev['annuoNuovo']) ?></b> <?= t('+ IVA / anno.') ?></p>
      <div class="actions"><button class="btn"><?= Support::e(tf('Conferma il passaggio a {piano}', ['{piano}' => $pv['name']])) ?></button>
        <a class="btn btn--quiet" href="<?= b() ?>/account/piano"><?= t('Annulla') ?></a></div>
      <p class="small muted"><?= t('Puoi annullare quando vuoi, fino al giorno prima.') ?></p>

    <?php elseif ($prev['conguaglio'] === 0): ?>
      <p><?= tf('Il cambio vale subito, senza pagamento. Dal rinnovo del {data}: {prezzo} + IVA / anno.', ['{data}' => Support::e(MHW\Pannello::data($fine)), '{prezzo}' => '<b>' . $soldi($prev['annuoNuovo']) . '</b>']) ?></p>
      <div class="actions"><button class="btn"><?= Support::e(tf('Conferma il passaggio a {piano}', ['{piano}' => $pv['name']])) ?></button>
        <a class="btn btn--quiet" href="<?= b() ?>/account/piano"><?= t('Annulla') ?></a></div>

    <?php else: ?>
      <section class="panel stack pianoconto" aria-labelledby="oggi-titolo">
        <h2 id="oggi-titolo" class="kicker" style="margin:0"><?= t('Oggi') ?></h2>
        <div class="spread spread--mid"><span><?= Support::e(tf('Differenza per i {n} giorni che restano', ['{n}' => (int) $prev['giorni']])) ?></span><b><?= $soldi($prev['conguaglio']) ?></b></div>
        <?php if ($iva): ?>
          <div class="spread spread--mid"><span><?= t('IVA 22%') ?></span><b><?= $soldi($ivaCents) ?></b></div>
          <div class="spread spread--mid pianoconto__totale"><span><?= t('Totale') ?></span><b><?= $soldi($prev['conguaglio'] + $ivaCents) ?></b></div>
        <?php else: ?>
          <p class="small muted" style="margin:0"><?= t('Più IVA, se dovuta: la calcola la pagina di pagamento.') ?></p>
        <?php endif; ?>
      </section>
      <section class="panel stack" aria-labelledby="rinnovo-titolo">
        <h2 id="rinnovo-titolo" class="kicker" style="margin:0"><?= Support::e(tf('Dal rinnovo del {data}', ['{data}' => MHW\Pannello::data($fine)])) ?></h2>
        <div class="spread spread--mid"><span><?= $conQ($pv, $q) ?></span><b><?= $soldi($prev['annuoNuovo']) ?> <span class="small muted"><?= t('+ IVA / anno') ?></span></b></div>
      </section>
      <?php if ((int) $sub['cancel_at_period_end'] === 1): ?>
        <p class="note"><?= Support::e(tf('Il rinnovo automatico è disattivato: il nuovo piano vale fino al {data}.', ['{data}' => MHW\Pannello::data($fine)])) ?></p>
      <?php endif; ?>
      <div class="actions"><button class="btn btn--go"><?= t('Vai al pagamento') ?> <span class="go"><?= Icon::svg('arrow', 18, 2) ?></span></button>
        <a class="btn btn--quiet" href="<?= b() ?>/account/piano"><?= t('Annulla') ?></a></div>
      <p class="small muted"><?= t('Paghi sulla pagina sicura di Stripe. Il nuovo piano si attiva appena il pagamento è confermato.') ?></p>
    <?php endif; ?>
  </form>
</div>
