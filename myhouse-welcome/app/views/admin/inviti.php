<?php
/* Inviti: chi ha invitato chi, in che stato è ogni invito, e «Annulla» per quelli
   che non devono contare (un rimborso, un abuso). Riceve $righe e $attivi. */
use function MHW\b;
use MHW\{Support, Csrf, Inviti};
$title = 'Inviti';
$stati = ['registrato' => ['In preparazione', 'ochre'], 'valido' => ['Valido', 'pine'], 'oltre' => ['Oltre il massimo', 'paper'],
          'usato' => ['Già scontato', 'paper'], 'annullato' => ['Annullato', 'alert']];
$conta = array_count_values(array_column($righe, 'status')); ?>
<div class="stack stack--lg">
  <div class="stack stack--sm"><h1>Inviti.</h1>
    <p class="muted small" style="max-width:720px">Invita un amico: <?= Inviti::AMICO ?>% all'amico sul primo anno, <?= Inviti::PASSO ?>% a chi invita sul prossimo rinnovo
      per ogni amico che paga, fino al <?= Inviti::MASSIMO ?>%. Lo sconto di chi invita è un coupon Stripe «una volta» sul suo abbonamento.</p></div>
  <?php if (!$attivi): ?>
    <p class="note" role="status"><span>Gli inviti sono spenti. Si accendono da <a href="<?= b() ?>/admin/impostazioni#inviti">Impostazioni → Invita un amico</a>,
      dopo un giro di prova in modalità test di Stripe.</span></p>
  <?php endif; ?>
  <div class="cifre" aria-label="In breve">
    <div class="cifra cifra--pino"><span class="cifra__testa">Validi</span><b class="cifra__valore"><?= (int) ($conta['valido'] ?? 0) ?></b><span class="cifra__nota">in attesa del prossimo rinnovo</span></div>
    <div class="cifra cifra--carta"><span class="cifra__testa">Inviti per email</span><b class="cifra__valore"><?= (int) $perEmail ?></b><span class="cifra__nota">mandati dal sito, ultimi 30 giorni</span></div>
    <div class="cifra cifra--ocra"><span class="cifra__testa">In preparazione</span><b class="cifra__valore"><?= (int) ($conta['registrato'] ?? 0) ?></b><span class="cifra__nota">registrati, non ancora paganti</span></div>
    <div class="cifra cifra--mare"><span class="cifra__testa">Già scontati</span><b class="cifra__valore"><?= (int) ($conta['usato'] ?? 0) ?></b><span class="cifra__nota">usati a un rinnovo</span></div>
  </div>
  <?php if (!$righe): ?><p class="muted">Nessun invito.</p><?php else: ?>
  <div class="tablewrap"><table class="data">
    <thead><tr><th>Chi invita</th><th>Amico</th><th>Stato</th><th>Registrato</th><th>Ha pagato</th><th></th></tr></thead>
    <tbody><?php foreach ($righe as $r): [$et, $tono] = $stati[$r['status']] ?? [$r['status'], 'paper']; ?>
      <tr><td><a href="<?= b() ?>/admin/cliente/<?= (int) $r['chi_account'] ?>"><?= Support::e($r['chi'] ?: $r['chi_email']) ?></a><br><span class="tiny muted"><?= Support::e($r['chi_email']) ?></span></td>
        <td><a href="<?= b() ?>/admin/cliente/<?= (int) $r['amico_account'] ?>"><?= Support::e($r['amico'] ?: $r['amico_email']) ?></a><br><span class="tiny muted"><?= Support::e($r['amico_email']) ?></span></td>
        <td><span class="badge badge--<?= $tono ?>"><?= Support::e($et) ?></span></td>
        <td class="small"><?= Support::e(Support::date($r['created_at'])) ?></td>
        <td class="small"><?= Support::e(Support::date($r['qualified_at'])) ?></td>
        <td><?php if (in_array($r['status'], ['registrato', 'valido', 'oltre'], true)): ?>
          <form method="post" action="<?= b() ?>/admin/inviti/<?= (int) $r['id'] ?>/annulla" style="margin:0"><?= Csrf::field() ?><button class="linkbtn">Annulla</button></form>
        <?php endif; ?></td></tr>
    <?php endforeach; ?></tbody></table></div>
  <?php endif; ?>
</div>
