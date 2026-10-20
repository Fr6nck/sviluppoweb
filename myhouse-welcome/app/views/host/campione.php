<?php
/* 9M · «La scheda campione» (Portfolio): lo spiegone (campione.* in «Testi e foto»), quale guida è la campione, le gemelle
   con il loro stato e la scelta della campione. Prima del pagamento: «Disponibile dopo l'attivazione», scelta spenta.
   Riceve $guide (le guide dell'account), $disponibile, $d (Campione::differenze, o null). */
use function MHW\b;
use MHW\{Support, Csrf};
$title = 'La scheda campione';
$camp = $d['campione'] ?? null; ?>
<div class="stack stack--lg" style="max-width:980px">
  <p class="small"><a href="<?= b() ?>/pannello">← Le mie guide</a></p>
  <?php $csTitolo = 'h1'; include MHW_APP . '/views/pub/_campione_spiegone.php'; ?>
  <section class="panel stack" style="gap:12px" aria-labelledby="campione-scelta">
    <h2 id="campione-scelta" style="font-size:22px;margin:0">La tua campione</h2>
    <?php if (!$disponibile): ?>
      <?php include __DIR__ . '/_attivazione.php'; ?>
      <button class="btn btn--sm" disabled>Rendi campione</button>
    <?php else: ?>
      <p class="small muted" style="margin:0"><?= $camp ? '<b>' . Support::e($camp['name']) . '</b> è la tua scheda campione' . ($d['gemelle'] ? ': ' . count($d['gemelle']) . (count($d['gemelle']) === 1 ? ' guida gemella.' : ' guide gemelle.') : '. Nessuna guida gemella ancora: copia le sezioni dalla campione in «Contenuti → Copia sezioni da…».') : 'Nessuna scheda campione: scegline una, oppure lascia tutto come oggi.' ?></p>
      <form method="post" action="<?= b() ?>/campione/scegli" class="row" style="gap:10px;align-items:flex-end;flex-wrap:wrap"><?= Csrf::field() ?>
        <div class="field" style="margin:0;flex:1 1 260px"><label for="campione-guida">Guida campione</label>
          <select id="campione-guida" name="guida">
            <option value="0">Nessuna</option>
            <?php foreach ($guide as $g): ?><option value="<?= (int) $g['id'] ?>"<?= (int) $g['campione'] === 1 ? ' selected' : '' ?>><?= Support::e($g['name']) ?><?= $g['status'] !== 'published' ? ' (bozza)' : '' ?></option><?php endforeach; ?>
          </select></div>
        <button class="btn btn--sm">Salva</button>
      </form>
      <p class="help" style="margin:0">Cambiare campione non scollega niente: le sezioni già copiate restano collegate alla guida da cui vengono; le nuove copie si collegano alla nuova campione.</p>
      <?php if ($d && $d['righe']): ?><a class="btn btn--go btn--sm" style="align-self:flex-start" href="<?= b() ?>/campione/gemelle">Aggiorna le gemelle →</a><?php endif; ?>
    <?php endif; ?>
  </section>
</div>
