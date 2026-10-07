<?php
/* La conferma del cambio di piano (6H). Salire: il conto di oggi e il prezzo dal
   rinnovo, poi la pagina di Stripe. Scendere: dal rinnovo, con le scelte di cosa
   tenere (sezioni, strutture) e l'elenco di quello che non ci sarà più. */
use function MHW\b;
use MHW\{Support, Csrf, Icon, Plans, CambioPiano, SectionCatalog};
$title = 'Passa a ' . $pv['name'];
$sub = $st['sub']; $fine = (string) $sub['current_period_end']; $valuta = (string) $pv['currency'];
$soldi = fn(int $c) => Support::e(Support::money($c, $valuta));
$conQ = fn(array $v, int $n) => Support::e($v['name']) . (Plans::perProperty($v) ? ' · ' . $n . ' struttur' . ($n === 1 ? 'a' : 'e') : '');
$scende = $prev['tipo'] === CambioPiano::SCENDE;
$ivaCents = $iva ? (int) round($prev['conguaglio'] * 0.22) : 0; ?>
<div class="stack stack--lg" style="max-width:680px">
  <div class="stack stack--sm">
    <a class="small" href="<?= b() ?>/account/piano"><?= Icon::svg('back', 14) ?> Cambia piano</a>
    <h1>Passa a <?= Support::e($pv['name']) ?>.</h1>
    <p class="lead">Da <?= $conQ($st['pv'], $st['quantita']) ?> a <b><?= $conQ($pv, $q) ?></b>.</p>
  </div>
  <?php if ($err): ?><p class="note note--err" role="alert"><?= Support::e($err) ?></p><?php endif; ?>

  <form method="post" action="<?= b() ?>/account/piano/conferma" class="stack"><?= Csrf::field() ?>
    <input type="hidden" name="piano" value="<?= Support::e($codice) ?>">
    <?php if (Plans::perProperty($pv)): ?><input type="hidden" name="strutture" value="<?= (int) $q ?>"><?php endif; ?>

    <?php if ($scende): ?>
      <p>Dal <b><?= Support::e(Support::date($fine)) ?></b>. Fino ad allora resti su <?= Support::e($st['pv']['name']) ?>: l'hai già pagato. Oggi non paghi niente.</p>
      <?php if ($cambia['sezioni'] || $cambia['daArchiviare'] > 0 || $cambia['perde']): ?>
        <section class="panel stack" aria-labelledby="cambia-titolo">
          <h2 id="cambia-titolo" style="font-size:20px;margin:0">Cosa cambia quel giorno</h2>
          <?php foreach ($cambia['sezioni'] as $b): $pid = (int) $b['struttura']['id']; ?>
            <fieldset class="fieldset" data-conta-max="<?= (int) $cambia['maxSezioni'] ?>">
              <legend><?= Support::e($b['struttura']['name']) ?>: scegli le <?= (int) $cambia['maxSezioni'] ?> sezioni da tenere</legend>
              <p class="help">Ne hai <?= count($b['sezioni']) ?> attive. Le altre si disattivano, ma restano salvate. Se non scegli, restano le prime in ordine.
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
              <legend>Scegli <?= (int) $cambia['daArchiviare'] ?> struttur<?= $cambia['daArchiviare'] === 1 ? 'a' : 'e' ?> da archiviare</legend>
              <p class="help">Una struttura archiviata va offline, ma contenuti, traduzioni e QR restano: la riattivi quando vuoi.</p>
              <?php foreach ($cambia['strutture'] as $p): ?>
                <label class="scelta"><input type="checkbox" name="archivia[]" value="<?= (int) $p['id'] ?>">
                  <span><?= Support::e($p['name']) ?><?= $p['city'] ? ' <span class="small muted">· ' . Support::e($p['city']) . '</span>' : '' ?>
                    <?= $p['status'] === 'published' ? '<span class="badge badge--pine">Online</span>' : '' ?></span></label>
              <?php endforeach; ?>
            </fieldset>
          <?php endif; ?>
          <?php if ($cambia['perde']): ?>
            <ul class="stack" style="gap:6px;margin:0;padding-left:20px">
              <?php foreach ($cambia['perde'] as $riga): ?><li><?= Support::e($riga) ?></li><?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </section>
      <?php endif; ?>
      <p>Dal rinnovo: <b><?= $soldi($prev['annuoNuovo']) ?></b> + IVA / anno.</p>
      <div class="actions"><button class="btn">Conferma il passaggio a <?= Support::e($pv['name']) ?></button>
        <a class="btn btn--quiet" href="<?= b() ?>/account/piano">Annulla</a></div>
      <p class="small muted">Puoi annullare quando vuoi, fino al giorno prima.</p>

    <?php elseif ($prev['conguaglio'] === 0): ?>
      <p>Il cambio vale subito, senza pagamento. Dal rinnovo del <?= Support::e(Support::date($fine)) ?>: <b><?= $soldi($prev['annuoNuovo']) ?></b> + IVA / anno.</p>
      <div class="actions"><button class="btn">Conferma il passaggio a <?= Support::e($pv['name']) ?></button>
        <a class="btn btn--quiet" href="<?= b() ?>/account/piano">Annulla</a></div>

    <?php else: ?>
      <section class="panel stack pianoconto" aria-labelledby="oggi-titolo">
        <h2 id="oggi-titolo" class="kicker" style="margin:0">Oggi</h2>
        <div class="spread spread--mid"><span>Differenza per i <?= (int) $prev['giorni'] ?> giorni che restano</span><b><?= $soldi($prev['conguaglio']) ?></b></div>
        <?php if ($iva): ?>
          <div class="spread spread--mid"><span>IVA 22%</span><b><?= $soldi($ivaCents) ?></b></div>
          <div class="spread spread--mid pianoconto__totale"><span>Totale</span><b><?= $soldi($prev['conguaglio'] + $ivaCents) ?></b></div>
        <?php else: ?>
          <p class="small muted" style="margin:0">Più IVA, se dovuta: la calcola la pagina di pagamento.</p>
        <?php endif; ?>
      </section>
      <section class="panel stack" aria-labelledby="rinnovo-titolo">
        <h2 id="rinnovo-titolo" class="kicker" style="margin:0">Dal rinnovo del <?= Support::e(Support::date($fine)) ?></h2>
        <div class="spread spread--mid"><span><?= $conQ($pv, $q) ?></span><b><?= $soldi($prev['annuoNuovo']) ?> <span class="small muted">+ IVA / anno</span></b></div>
      </section>
      <?php if ((int) $sub['cancel_at_period_end'] === 1): ?>
        <p class="note">Il rinnovo automatico è disattivato: il nuovo piano vale fino al <?= Support::e(Support::date($fine)) ?>.</p>
      <?php endif; ?>
      <div class="actions"><button class="btn btn--go">Vai al pagamento <span class="go"><?= Icon::svg('arrow', 18, 2) ?></span></button>
        <a class="btn btn--quiet" href="<?= b() ?>/account/piano">Annulla</a></div>
      <p class="small muted">Paghi sulla pagina sicura di Stripe. Il nuovo piano si attiva appena il pagamento è confermato.</p>
    <?php endif; ?>
  </form>
</div>
