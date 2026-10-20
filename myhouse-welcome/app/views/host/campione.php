<?php use function MHW\t, MHW\tf;
/* 9M · «La scheda campione» (Portfolio): lo spiegone (campione.* in «Testi e foto»), quale guida è la campione, le gemelle
   con il loro stato e la scelta della campione. Prima del pagamento: «Disponibile dopo l'attivazione», scelta spenta.
   Riceve $guide (le guide dell'account), $disponibile, $d (Campione::differenze, o null). */
use function MHW\b;
use MHW\{Support, Csrf};
$title = t('La scheda campione');
$camp = $d['campione'] ?? null; ?>
<div class="stack stack--lg" style="max-width:980px">
  <p class="small"><a href="<?= b() ?>/pannello"><?= t('← Le mie guide') ?></a></p>
  <?php $csTitolo = 'h1'; include MHW_APP . '/views/pub/_campione_spiegone.php'; ?>
  <section class="panel stack" style="gap:12px" aria-labelledby="campione-scelta">
    <h2 id="campione-scelta" style="font-size:22px;margin:0"><?= t('La tua campione') ?></h2>
    <?php if (!$disponibile): ?>
      <?php include __DIR__ . '/_attivazione.php'; ?>
      <button class="btn btn--sm" disabled><?= t('Rendi campione') ?></button>
    <?php else: ?>
      <p class="small muted" style="margin:0"><?= $camp ? ($d['gemelle'] ? (count($d['gemelle']) === 1 ? tf('{nome} è la tua scheda campione: 1 guida gemella.', ['{nome}' => '<b>' . Support::e($camp['name']) . '</b>']) : tf('{nome} è la tua scheda campione: {n} guide gemelle.', ['{nome}' => '<b>' . Support::e($camp['name']) . '</b>', '{n}' => count($d['gemelle'])]))
          : tf('{nome} è la tua scheda campione. Nessuna guida gemella ancora: copia le sezioni dalla campione in «Contenuti → Copia sezioni da…».', ['{nome}' => '<b>' . Support::e($camp['name']) . '</b>'])) : t('Nessuna scheda campione: scegline una, oppure lascia tutto come oggi.') ?></p>
      <form method="post" action="<?= b() ?>/campione/scegli" class="row" style="gap:10px;align-items:flex-end;flex-wrap:wrap"><?= Csrf::field() ?>
        <div class="field" style="margin:0;flex:1 1 260px"><label for="campione-guida"><?= t('Guida campione') ?></label>
          <select id="campione-guida" name="guida">
            <option value="0"><?= t('Nessuna') ?></option>
            <?php foreach ($guide as $g): ?><option value="<?= (int) $g['id'] ?>"<?= (int) $g['campione'] === 1 ? ' selected' : '' ?>><?= Support::e($g['name']) ?><?= $g['status'] !== 'published' ? ' (bozza)' : '' ?></option><?php endforeach; ?>
          </select></div>
        <button class="btn btn--sm"><?= t('Salva') ?></button>
      </form>
      <p class="help" style="margin:0"><?= t('Cambiare campione non scollega niente: le sezioni già copiate restano collegate alla guida da cui vengono; le nuove copie si collegano alla nuova campione.') ?></p>
      <?php if ($d && $d['righe']): ?><a class="btn btn--go btn--sm" style="align-self:flex-start" href="<?= b() ?>/campione/gemelle"><?= t('Aggiorna le gemelle →') ?></a><?php endif; ?>
    <?php endif; ?>
  </section>
</div>
