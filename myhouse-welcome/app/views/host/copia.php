<?php
/* «Copia sezioni da…» su una struttura che esiste già: si sceglie da dove, poi cosa.
   Per le sezioni che ci sono già si sceglie se sostituirle o saltarle. */
use function MHW\b;
use MHW\{Support, Csrf, Icon};
$title = 'Copia sezioni — ' . $prop['name']; $pid = (int) $prop['id']; ?>
<div class="stack stack--lg" style="max-width:640px">
  <div class="stack stack--sm">
    <h1>Copia sezioni da un'altra struttura.</h1>
    <p class="lead">Rifiuti, ristoranti, cose da vedere, regole: quello che vale anche qui lo scrivi una volta sola.</p>
  </div>
  <?php if ($err): ?><p class="note note--err" role="alert"><?= Support::e($err) ?></p><?php endif; ?>
  <form method="get" action="<?= b() ?>/pannello/<?= $pid ?>/copia" class="row" style="gap:10px;align-items:flex-end">
    <div class="field" style="margin:0;flex:1 1 240px"><label for="da">Copia da</label>
      <select id="da" name="da">
        <?php foreach ($origini as $o): ?><option value="<?= (int) $o['id'] ?>" <?= (int) $o['id'] === (int) ($da['id'] ?? 0) ? 'selected' : '' ?>><?= Support::e($o['name']) ?></option><?php endforeach; ?>
      </select></div>
    <button class="btn btn--ghost btn--sm">Mostra cosa c'è</button>
  </form>
  <?php if ($da): ?>
    <?php if (!$proposta['sezioni'] && !$proposta['logo'] && !$proposta['contatti']): ?>
      <p class="note note--quiet"><?= Support::e($da['name']) ?> non ha sezioni da copiare.</p>
    <?php else: ?>
      <form method="post" action="<?= b() ?>/pannello/<?= $pid ?>/copia" class="stack"><?= Csrf::field() ?>
        <input type="hidden" name="da" value="<?= (int) $da['id'] ?>">
        <?php $sezioniOrigine = $proposta['sezioni']; $nomeDestinazione = $prop['name']; include __DIR__ . '/_copia_scelte.php'; ?>
        <div class="actions"><button class="btn"><?= Icon::svg('copy', 16) ?> Copia in <?= Support::e($prop['name']) ?></button>
          <a class="btn btn--quiet" href="<?= b() ?>/pannello/<?= $pid ?>">Annulla</a></div>
      </form>
    <?php endif; ?>
  <?php endif; ?>
</div>
