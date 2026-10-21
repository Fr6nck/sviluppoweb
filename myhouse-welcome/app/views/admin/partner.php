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
  <div class="spread spread--mid"><h1>Partner.</h1><a class="btn btn--ghost btn--sm" href="<?= b() ?>/admin/partner?formato=csv">Esporta CSV</a></div>
  <div class="stack stack--sm">
    <p class="muted small" style="max-width:760px">Le richieste del modulo «Diventa partner» della pagina <a href="<?= b() ?>/property-manager" target="_blank" rel="noopener">/property-manager</a>
      (property manager e agenzie). Arrivano anche all'email commerciale. Si conservano 12 mesi. Commissione <?= $comm ? $comm . '%' : 'non decisa' ?>,
      sconto per i clienti del partner <?= $sc ? $sc . '%' : 'non deciso' ?> di serie (<a href="<?= b() ?>/admin/impostazioni#sito">Impostazioni → Sito</a>); ogni partner poi ha le sue percentuali.
      «Approva e crea il partner» gli dà il codice (PM-…) e gli manda l'email di benvenuto; le commissioni si liquidano da <a href="<?= b() ?>/admin/cassa/partner">Cassa → Commissioni partner</a>.</p></div>
  <?php if (MHW\ProgrammaPartner::disponibili()): /* 9O · i partner approvati */ ?>
  <section class="stack" style="gap:10px" aria-labelledby="pp-attivi">
    <div class="spread spread--mid"><h2 id="pp-attivi" style="font-size:22px;margin:0">Partner · <?= count($partner) ?></h2><a class="btn btn--ghost btn--sm" href="<?= b() ?>/admin/partner/nuovo">Nuovo partner</a></div>
    <?php if (!$partner): ?><p class="note note--quiet">Ancora nessun partner approvato: «Approva e crea il partner» su una richiesta qui sotto.</p><?php else: ?>
    <div class="tablewrap"><table class="data"><thead><tr><th>Partner</th><th>Codice</th><th>Commissione</th><th>Sconto clienti</th><th>Rinnovi</th><th>Da liquidare</th><th>Stato</th></tr></thead><tbody>
      <?php foreach ($partner as $pp): ?><tr><td><a href="<?= b() ?>/admin/partner/p/<?= (int) $pp['id'] ?>"><?= Support::e($pp['ragione_sociale'] ?: $pp['referente']) ?></a><br><span class="tiny muted"><?= Support::e($pp['email']) ?></span></td>
        <td><code><?= Support::e($pp['codice']) ?></code></td><td><?= (int) $pp['commissione_pct'] ?>%</td><td><?= (int) $pp['sconto_clienti_pct'] ?>%</td><td><?= (int) $pp['rinnovi'] ? 'sì' : 'no' ?></td>
        <td><?= Support::e(Support::money((int) ($daLiquidare[(int) $pp['id']] ?? 0))) ?></td><td><?= Support::e(MHW\ProgrammaPartner::STATI[$pp['stato']] ?? $pp['stato']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
  </section>
  <?php endif; ?>
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
          <span class="row" style="gap:6px;flex-wrap:wrap"><button class="btn btn--ghost btn--sm">Salva</button>
            <?php if (MHW\ProgrammaPartner::disponibili() && !in_array((int) $r['id'], array_map(fn($x) => (int) $x['richiesta_id'], $partner), true)): ?><a class="btn btn--sm" href="<?= b() ?>/admin/partner/nuovo?richiesta=<?= (int) $r['id'] ?>">Approva e crea il partner</a><?php endif; ?>
            <a class="btn btn--sm" href="mailto:<?= Support::e($r['email']) ?>?subject=<?= $oggetto ?>" aria-label="Rispondi a <?= Support::e($r['nome']) ?>">Rispondi</a></span></form></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
</div>
