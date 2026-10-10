<?php
/* «Cambia piano» per chi ha già un abbonamento (6H): una scheda per piano in vendita.
   Il piano attuale ha il bordo verde; per gli altri si vede subito cosa succede:
   salire costa oggi la differenza fino al rinnovo, scendere vale dal rinnovo.
   Il numero di strutture del Portfolio si cambia con − e +, anche senza JavaScript. */
use function MHW\b;
use MHW\{Support, Icon, Plans, CambioPiano};
$title = 'Cambia piano';
$sub = $st['sub']; $fine = (string) $sub['current_period_end'];
$programmato = !empty($sub['next_package_version_id']) ? Plans::version((int) $sub['next_package_version_id']) : null; ?>
<div class="stack stack--lg" style="max-width:1080px">
  <div class="stack stack--sm">
    <a class="small" href="<?= b() ?>/account"><?= Icon::svg('back', 14) ?> Account &amp; Fatturazione</a>
    <h1>Cambia piano.</h1>
    <p class="lead">Se sali, paghi oggi solo la differenza per i giorni che restano fino al rinnovo. Se scendi, non paghi niente: il cambio parte dal rinnovo del <?= Support::e(Support::date($fine)) ?>.</p>
  </div>
  <?php if ($st['motivo'] !== ''): ?><p class="note note--err" role="alert"><?= Support::e($st['motivo']) ?></p><?php endif; ?>
  <?php if ($programmato): ?>
    <p class="note" role="status">Dal <?= Support::e(Support::date($fine)) ?> passi a <b><?= Support::e($programmato['name']) ?></b>. Lo puoi annullare da
      <a href="<?= b() ?>/account">Account &amp; Fatturazione</a>. Se sali di piano adesso, il passaggio programmato si annulla.</p>
  <?php endif; ?>

  <div class="grid grid-3 piani-cambio">
    <?php foreach ($piani as $x): $pv = $x['pv']; $p = $x['p']; $q = $x['q']; $prev = $x['prev'];
          $perStruttura = Plans::perProperty($pv);
          $eAttuale = $x['attuale'] && $q === (int) $sub['quantity'];
          $vai = b() . '/account/piano/conferma?piano=' . rawurlencode((string) $pv['code']) . ($perStruttura ? '&strutture=' . $q : ''); ?>
      <section class="plan pianocambio<?= $eAttuale ? ' pianocambio--attuale' : '' ?>" aria-labelledby="pc-<?= Support::e((string) $pv['code']) ?>">
        <div class="spread spread--mid" style="gap:10px">
          <h2 class="plan__nome" id="pc-<?= Support::e((string) $pv['code']) ?>"><?= Support::e($pv['name']) ?></h2>
          <?php if ($x['attuale']): ?><span class="badge badge--pine">Il tuo piano</span><?php endif; ?>
        </div>
        <span class="price"><?= Support::e(Support::money(Plans::price($pv, $q), (string) $pv['currency'])) ?><small> + IVA / anno</small></span>
        <?php if (trim((string) ($p['headline'] ?? '')) !== ''): ?><p class="muted" style="margin:0;font-size:15px;line-height:22px"><?= Support::e($p['headline']) ?></p><?php endif; ?>
        <?php if ($perStruttura): ?>
          <form method="get" action="<?= b() ?>/account/piano" class="pianocambio__quantita" aria-label="Strutture del <?= Support::e($pv['name']) ?>">
            <span class="small">Strutture</span>
            <button class="icon-btn" name="strutture" value="<?= max((int) $pv['min_quantity'], $q - 1) ?>" aria-label="Una struttura in meno"<?= $q <= (int) $pv['min_quantity'] ? ' disabled' : '' ?>>−</button>
            <b aria-live="polite"><?= $q ?></b>
            <button class="icon-btn" name="strutture" value="<?= min((int) $pv['max_quantity'], $q + 1) ?>" aria-label="Una struttura in più"<?= $q >= (int) $pv['max_quantity'] ? ' disabled' : '' ?>>+</button>
          </form>
        <?php endif; ?>
        <?php $voci = $p['bullet_list']; include dirname(__DIR__) . '/pub/_voci_piano.php'; ?>
        <div class="pianocambio__azione">
          <?php if ($eAttuale): ?>
            <p class="small" style="margin:0"><?= Support::e(Support::money(Plans::price($pv, $q), (string) $pv['currency'])) ?> + IVA / anno · si rinnova il <?= Support::e(Support::date($fine)) ?></p>
          <?php elseif ($st['motivo'] !== ''): ?>
            <p class="small muted" style="margin:0">Non disponibile adesso.</p>
          <?php elseif ($prev['tipo'] === CambioPiano::SCENDE): ?>
            <p class="small" style="margin:0">Dal <?= Support::e(Support::date($fine)) ?>: oggi non paghi niente.</p>
            <a class="btn btn--ghost" href="<?= Support::e($vai) ?>">Passa a <?= Support::e($pv['name']) ?> dal rinnovo</a>
          <?php else: ?>
            <p class="small" style="margin:0"><?= $prev['conguaglio'] > 0 ? 'Oggi <b>' . Support::e(Support::money($prev['conguaglio'], (string) $pv['currency'])) . '</b> + IVA, per i ' . (int) $prev['giorni'] . ' giorni fino al rinnovo.' : 'Vale subito, senza pagamento.' ?></p>
            <a class="btn" href="<?= Support::e($vai) ?>">Passa a <?= Support::e($pv['name']) ?><?= $perStruttura && $x['attuale'] ? ' con ' . $q . ' strutture' : '' ?></a>
          <?php endif; ?>
        </div>
      </section>
    <?php endforeach; ?>
  </div>
  <p class="small muted">Prezzi IVA esclusa. Niente si cancella: se scendi, quello che il piano nuovo non comprende resta salvato e torna se risali.</p>
</div>
