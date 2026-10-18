<?php
/* 9A · Le richieste di preventivo per più di 10 strutture: chi, quante, cosa ha scritto, lo stato
   (nuovo, contattato, chiuso) e il link per rispondere. Riceve $righe e $attivi. */
use function MHW\b;
use MHW\{Support, Csrf, Icon, Preventivi};
$title = 'Preventivi';
$pillola = ['nuovo' => 'ochre', 'contattato' => 'sea', 'chiuso' => 'paper'];
$conta = array_count_values(array_column($righe, 'stato')); ?>
<div class="stack stack--lg">
  <div class="stack stack--sm"><h1>Preventivi.</h1>
    <p class="muted small" style="max-width:720px">Le richieste di chi ha più strutture del massimo del Portfolio, dal riquadro «Ti facciamo una proposta su misura».
      La promessa è una risposta entro un giorno lavorativo. Si conservano 12 mesi.</p></div>
  <?php if (!$attivi): ?><p class="note" role="status"><span>La tabella dei preventivi non c'è ancora: manca la migrazione 043.</span></p><?php endif; ?>
  <div class="cifre" aria-label="In breve">
    <div class="cifra cifra--ocra"><span class="cifra__testa">Da rispondere</span><b class="cifra__valore"><?= (int) ($conta['nuovo'] ?? 0) ?></b><span class="cifra__nota">richieste nuove</span></div>
    <div class="cifra cifra--mare"><span class="cifra__testa">Contattati</span><b class="cifra__valore"><?= (int) ($conta['contattato'] ?? 0) ?></b><span class="cifra__nota">in trattativa</span></div>
    <div class="cifra cifra--carta"><span class="cifra__testa">Chiusi</span><b class="cifra__valore"><?= (int) ($conta['chiuso'] ?? 0) ?></b><span class="cifra__nota">conclusi o decaduti</span></div>
  </div>
  <?php if (!$righe): ?><p class="note note--quiet">Ancora nessuna richiesta.</p><?php else: ?>
  <div class="tablewrap"><table class="data">
    <thead><tr><th>Quando</th><th>Chi</th><th>Strutture</th><th>Messaggio</th><th>Stato</th><th><span class="sr-only">Azioni</span></th></tr></thead>
    <tbody><?php foreach ($righe as $r): $oggetto = rawurlencode('Preventivo MyHouse Welcome per ' . (int) $r['strutture'] . ' strutture'); ?>
      <tr><td class="small"><?= Support::e(Support::date($r['created_at'])) ?><?= $r['lingua'] === 'en' ? '<br><span class="tiny muted">in inglese</span>' : '' ?></td>
        <td><b><?= Support::e($r['nome']) ?></b><br><a class="small" href="mailto:<?= Support::e($r['email']) ?>?subject=<?= $oggetto ?>"><?= Support::e($r['email']) ?></a>
          <?php if ($r['telefono'] !== ''): ?><br><a class="small" href="tel:<?= Support::e(preg_replace('/[^\d+]/', '', $r['telefono'])) ?>"><?= Support::e($r['telefono']) ?></a><?php endif; ?>
          <?php if ($r['account_id']): ?><br><span class="tiny"><?= MHW\Codici::link((int) $r['account_id']) ?: '<a href="' . b() . '/admin/cliente/' . (int) $r['account_id'] . '">cliente #' . (int) $r['account_id'] . '</a>' ?></span><?php endif; ?></td>
        <td><b><?= (int) $r['strutture'] ?></b></td>
        <td class="small" style="max-width:360px;white-space:pre-line"><?= Support::e((string) $r['messaggio']) ?: '<span class="muted">—</span>' ?></td>
        <td><span class="badge badge--<?= $pillola[$r['stato']] ?? 'paper' ?>"><?= Support::e(Preventivi::STATI[$r['stato']] ?? $r['stato']) ?></span></td>
        <td><form method="post" action="<?= b() ?>/admin/preventivi/<?= (int) $r['id'] ?>/stato" class="row" style="gap:6px;flex-wrap:nowrap;align-items:center"><?= Csrf::field() ?>
          <label class="sr-only" for="stato-<?= (int) $r['id'] ?>">Stato della richiesta #<?= (int) $r['id'] ?></label>
          <select id="stato-<?= (int) $r['id'] ?>" name="stato" style="min-height:36px;min-width:132px"><?php foreach (Preventivi::STATI as $k => $et): ?><option value="<?= $k ?>"<?= $k === $r['stato'] ? ' selected' : '' ?>><?= $et ?></option><?php endforeach; ?></select>
          <button class="btn btn--ghost btn--sm">Salva</button>
          <a class="btn btn--sm" href="mailto:<?= Support::e($r['email']) ?>?subject=<?= $oggetto ?>" aria-label="Rispondi a <?= Support::e($r['nome']) ?>">Rispondi</a></form></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
</div>
