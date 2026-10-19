<?php
/* 9K · Le richieste del programma Partner (/property-manager): chi, la società, quante strutture e dove, il messaggio,
   lo stato (nuovo, contattato, attivo, chiuso) con una nota, e il link per rispondere. Riceve $righe e $attivi. */
use function MHW\b;
use MHW\{Support, Csrf, Partner};
$title = 'Partner';
$pillola = ['nuovo' => 'ochre', 'contattato' => 'sea', 'attivo' => 'pine', 'chiuso' => 'paper'];
$conta = array_count_values(array_column($righe, 'stato'));
$comm = Partner::commissione(); $sc = Partner::sconto(); ?>
<div class="stack stack--lg">
  <div class="stack stack--sm"><h1>Partner.</h1>
    <p class="muted small" style="max-width:760px">Le richieste del modulo «Diventa partner» della pagina <a href="<?= b() ?>/property-manager" target="_blank" rel="noopener">/property-manager</a>
      (property manager e agenzie). Arrivano anche all'email commerciale. Si conservano 12 mesi. Commissione <?= $comm ? $comm . '%' : 'non decisa' ?>,
      sconto per i clienti del partner <?= $sc ? $sc . '%' : 'non deciso' ?> (<a href="<?= b() ?>/admin/impostazioni#sito">Impostazioni → Sito</a>). I codici partner e il conteggio delle commissioni arrivano in una fase successiva.</p></div>
  <?php if (!$attivi): ?><p class="note" role="status"><span>La tabella delle richieste non c'è ancora: manca la migrazione 049.</span></p><?php endif; ?>
  <div class="cifre cifre--4" aria-label="In breve">
    <div class="cifra cifra--ocra"><span class="cifra__testa">Da rispondere</span><b class="cifra__valore"><?= (int) ($conta['nuovo'] ?? 0) ?></b><span class="cifra__nota">richieste nuove</span></div>
    <div class="cifra cifra--mare"><span class="cifra__testa">Contattati</span><b class="cifra__valore"><?= (int) ($conta['contattato'] ?? 0) ?></b><span class="cifra__nota">in conversazione</span></div>
    <div class="cifra cifra--pino"><span class="cifra__testa">Attivi</span><b class="cifra__valore"><?= (int) ($conta['attivo'] ?? 0) ?></b><span class="cifra__nota">partner al lavoro</span></div>
    <div class="cifra cifra--carta"><span class="cifra__testa">Chiusi</span><b class="cifra__valore"><?= (int) ($conta['chiuso'] ?? 0) ?></b><span class="cifra__nota">conclusi o decaduti</span></div>
  </div>
  <?php if (!$righe): ?><p class="note note--quiet">Ancora nessuna richiesta.</p><?php else: ?>
  <div class="tablewrap"><table class="data">
    <thead><tr><th>Quando</th><th>Chi</th><th>Strutture e zone</th><th>Messaggio</th><th>Stato e nota</th></tr></thead>
    <tbody><?php foreach ($righe as $r): $oggetto = rawurlencode('Programma Partner MyHouse Welcome'); ?>
      <tr><td class="small"><?= Support::e(Support::date($r['created_at'])) ?></td>
        <td><b><?= Support::e($r['nome']) ?></b><?= $r['societa'] !== '' ? '<br><span class="small">' . Support::e($r['societa']) . '</span>' : '' ?>
          <br><a class="small" href="mailto:<?= Support::e($r['email']) ?>?subject=<?= $oggetto ?>"><?= Support::e($r['email']) ?></a>
          <?php if ($r['telefono'] !== ''): ?><br><a class="small" href="tel:<?= Support::e(preg_replace('/[^\d+]/', '', $r['telefono'])) ?>"><?= Support::e($r['telefono']) ?></a><?php endif; ?></td>
        <td class="small"><?= $r['strutture'] !== '' ? '<b>' . Support::e($r['strutture']) . '</b> strutture' : '<span class="muted">—</span>' ?><?= $r['zone'] !== '' ? '<br>' . Support::e($r['zone']) : '' ?></td>
        <td class="small" style="max-width:340px;white-space:pre-line"><?= Support::e((string) $r['messaggio']) ?: '<span class="muted">—</span>' ?></td>
        <td><form method="post" action="<?= b() ?>/admin/partner/<?= (int) $r['id'] ?>/stato" class="stack" style="gap:6px;min-width:220px"><?= Csrf::field() ?>
          <span class="badge badge--<?= $pillola[$r['stato']] ?? 'paper' ?>" style="align-self:flex-start"><?= Support::e(Partner::STATI[$r['stato']] ?? $r['stato']) ?></span>
          <label class="sr-only" for="pstato-<?= (int) $r['id'] ?>">Stato della richiesta #<?= (int) $r['id'] ?></label>
          <select id="pstato-<?= (int) $r['id'] ?>" name="stato" style="min-height:36px"><?php foreach (Partner::STATI as $k => $et): ?><option value="<?= $k ?>"<?= $k === $r['stato'] ? ' selected' : '' ?>><?= $et ?></option><?php endforeach; ?></select>
          <label class="sr-only" for="pnota-<?= (int) $r['id'] ?>">Nota interna</label>
          <input id="pnota-<?= (int) $r['id'] ?>" name="nota" type="text" maxlength="255" value="<?= Support::e((string) $r['nota']) ?>" placeholder="Nota interna">
          <span class="row" style="gap:6px"><button class="btn btn--ghost btn--sm">Salva</button>
            <a class="btn btn--sm" href="mailto:<?= Support::e($r['email']) ?>?subject=<?= $oggetto ?>" aria-label="Rispondi a <?= Support::e($r['nome']) ?>">Rispondi</a></span></form></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
</div>
