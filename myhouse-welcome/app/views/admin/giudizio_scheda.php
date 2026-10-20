<?php
/* 9L · Una riga di «Giudizi e vetrina»: le due anteprime (la scheda com'è in «Le guide dei nostri host», il giudizio com'è
   in «Cosa dicono di noi»), le due decisioni separate (approva o ritira la scheda con un motivo interno; pubblica o no il
   giudizio), la risposta pubblica (facoltativa, sotto il giudizio), la nota interna e lo storico (audit_log).
   Riceve $r (Vetrina::rigaElenco), $card (la scheda), $cardG (il giudizio, se c'è), $storico. */
use function MHW\b;
use MHW\{Support, Csrf, Giudizi, Vetrina};
$title = 'Scheda · ' . ($r['p']['name'] ?? $r['host']);
$g = $r['g']; $ss = $r['stato_scheda']; $sg = $r['stato_giudizio']; $ritirato = !empty($r['ritirato_at']); ?>
<div class="stack stack--lg" style="max-width:980px">
  <p class="small"><a href="<?= b() ?>/admin/giudizi">← Giudizi e vetrina</a></p>
  <div class="stack stack--sm">
    <h1><?= Support::e($r['p']['name'] ?? 'Guida non pubblicata') ?>.</h1>
    <p class="muted" style="margin:0"><?= Support::e($r['host']) ?> · <?= Support::e($r['codice']) ?> · scheda: <?= Support::e(Vetrina::STATI_SCHEDA[$ss] ?? $ss) ?><?= $r['motivo'] !== '' ? ' (' . Support::e(Vetrina::SOSPESA[$r['motivo']] ?? '') . ')' : '' ?>
      · giudizio: <?= Support::e(Vetrina::STATI_GIUDIZIO[$sg] ?? $sg) ?>
      · aderito il <?= Support::e(Support::date((string) $r['giudizio_adesione_at'])) ?> <?= Support::e(mb_strtolower(Giudizi::ADESIONI[$r['giudizio_adesione']] ?? '')) ?> · <?= Support::e(Giudizi::STATI[$r['giudizio_sconto_stato']] ?? '') ?></p>
  </div>
  <div class="giudizio-dettaglio">
    <section class="stack" aria-labelledby="anteprima-titolo" style="gap:10px">
      <h2 id="anteprima-titolo" style="font-size:20px;margin:0">Anteprima</h2>
      <p class="small muted" style="margin:0">Com'è in «Le guide dei nostri host» e, se pubblicato, in «Cosa dicono di noi»: nessun link, nessuna foto dell'host.</p>
      <?php if ($card): ?><div class="dicono"><?php $v = $card; include dirname(__DIR__) . '/pub/_vetrina_scheda.php'; ?></div>
        <?php if ($cardG): ?><div class="dicono" style="margin-top:12px"><?php $g0 = $g; $g = $cardG; include dirname(__DIR__) . '/pub/_dicono_scheda.php'; $g = $g0; ?></div><?php endif; ?>
      <?php else: ?><p class="note note--quiet">La guida non è ancora pubblicata.</p><?php endif; ?>
    </section>
    <div class="stack" style="gap:18px">
      <section class="panel stack" id="scheda" style="gap:10px">
        <h2 style="font-size:20px;margin:0">La scheda</h2>
        <p class="small" style="margin:0"><span class="pillola <?= in_array($ss, ['in_home', 'in_vetrina'], true) ? 'pillola--pino' : ($ss === 'da_approvare' ? 'pillola--terracotta' : 'pillola--carta') ?>"><?= Support::e(Vetrina::STATI_SCHEDA[$ss] ?? $ss) ?></span>
          <?= $r['posizione'] ? ' · posizione ' . (int) $r['posizione'] . ($r['posizione'] <= Vetrina::postiSchede() ? ' (in home)' : ' (in «Tutte le guide»)') : '' ?>
          <?= !empty($r['scheda_approvata_at']) && in_array($ss, ['in_home', 'in_vetrina'], true) ? ' · approvata il ' . Support::e(Support::date((string) $r['scheda_approvata_at'])) : '' ?></p>
        <?php if ($r['mancano'] && !$ritirato): ?><p class="help" style="margin:0">Incompleta: manca <?= Support::e(implode(' e ', $r['mancano'])) ?> (al suo posto il segnaposto «Welcome My House»). <?= $ss === 'da_approvare' ? 'Consiglio: aspetta che l\'host completi prima di approvarla.' : 'Se l\'host la aggiunge, compare da sola.' ?></p><?php endif; ?>
        <?php if ($ss === 'ritirata_admin'): ?><p class="small muted" style="margin:0">Ritirata il <?= Support::e(Support::date((string) $r['scheda_ritirata_at'])) ?><?= $r['scheda_motivo'] ? ': «' . Support::e((string) $r['scheda_motivo']) . '»' : '' ?>.</p><?php endif; ?>
        <?php if (in_array($ss, ['da_approvare', 'ritirata_admin'], true)): ?>
          <form method="post" action="<?= b() ?>/admin/giudizi/<?= (int) $r['id'] ?>/home" style="margin:0"><?= Csrf::field() ?><input type="hidden" name="si" value="1">
            <button class="btn btn--sm"><?= $ss === 'ritirata_admin' ? 'Rimetti in vetrina' : 'Approva la scheda' ?></button></form>
        <?php elseif (in_array($ss, ['in_home', 'in_vetrina'], true)): ?>
          <form method="post" action="<?= b() ?>/admin/giudizi/<?= (int) $r['id'] ?>/home" class="stack" id="ritira" style="gap:8px;margin:0"><?= Csrf::field() ?><input type="hidden" name="si" value="0">
            <label for="motivo-scheda" class="small">Motivo del ritiro <span class="muted">(interno: il cliente vede solo «non in home al momento»)</span></label>
            <input id="motivo-scheda" name="motivo" type="text" maxlength="255">
            <div class="actions"><button class="btn btn--sm btn--ghost">Ritira la scheda</button></div></form>
        <?php else: ?><p class="small muted" style="margin:0"><?= $ritirato ? 'L\'host si è ritirato dal programma: scheda e giudizio non si vedono.' : 'Si spegne da sola: ' . Support::e(Vetrina::SOSPESA[$r['motivo']] ?? '') . '.' ?></p><?php endif; ?>
      </section>
      <section class="panel stack" id="giudizio" style="gap:10px">
        <h2 style="font-size:20px;margin:0">Il giudizio</h2>
        <?php if ($g && empty($g['withdrawn_at']) && !$ritirato): ?>
          <p style="margin:0"><span class="stelline" aria-label="<?= (int) $g['rating'] ?> su 5"><?= str_repeat('★', (int) $g['rating']) . str_repeat('☆', 5 - (int) $g['rating']) ?></span> · <?= Support::e(Support::date((string) $g['created_at'])) ?><?= $r['segnalato'] ? ' · <b>segnalato</b>' : '' ?>
            · <span class="pillola <?= $sg === 'pubblicato' ? 'pillola--pino' : ($sg === 'da_approvare' ? 'pillola--terracotta' : 'pillola--carta') ?>"><?= Support::e(Vetrina::STATI_GIUDIZIO[$sg] ?? $sg) ?></span><?= $r['doppione'] ? ' · anche in vetrina' : '' ?></p>
          <blockquote style="margin:0">«<?= Support::e((string) $g['body']) ?>»</blockquote>
          <?php if ($sg !== 'sospeso'): ?>
          <form method="post" action="<?= b() ?>/admin/giudizi/<?= (int) $r['id'] ?>/giudizio" class="stack" style="gap:8px;margin:0"><?= Csrf::field() ?>
            <input type="hidden" name="si" value="<?= $sg === 'pubblicato' ? '0' : '1' ?>">
            <?php if ($sg === 'pubblicato'): ?><label for="motivo-giudizio" class="small">Motivo <span class="muted">(interno, facoltativo)</span></label><input id="motivo-giudizio" name="motivo" type="text" maxlength="255"><?php endif; ?>
            <div class="actions"><button class="btn btn--sm<?= $sg === 'pubblicato' ? ' btn--ghost' : '' ?>"><?= $sg === 'pubblicato' ? 'Togli da «Cosa dicono di noi»' : 'Pubblica in «Cosa dicono di noi»' ?></button></div></form>
          <?php endif; ?>
          <?php if ($sg === 'non_pubblicato' && $r['giudizio_motivo']): ?><p class="small muted" style="margin:0">Non pubblicato: «<?= Support::e((string) $r['giudizio_motivo']) ?>».</p><?php endif; ?>
          <p class="help" style="margin:0">Lo sconto non dipende dal voto né dalla pubblicazione. Di norma pubblichiamo i 5 stelle.</p>
        <?php else: ?><p class="small muted" style="margin:0"><?= $ritirato ? 'L\'host si è ritirato: giudizio e scheda non si vedono.' : 'Nessun giudizio ancora' . ($r['richiesta'] ? ': lo chiediamo il ' . Support::e(Support::date($r['richiesta'])) . '.' : '.') ?></p><?php endif; ?>
      </section>
      <?php if ($g && empty($g['withdrawn_at']) && !$ritirato): ?>
      <form method="post" action="<?= b() ?>/admin/giudizi/<?= (int) $r['id'] ?>/risposta" class="panel stack" id="rispondi" style="gap:8px"><?= Csrf::field() ?>
        <label for="risposta" style="font-weight:600">Risposta pubblica <span class="muted">(facoltativa, sotto il giudizio)</span></label>
        <textarea id="risposta" name="testo" rows="3" maxlength="600"><?= Support::e((string) ($r['risposta_pubblica'] ?? '')) ?></textarea>
        <div class="actions"><button class="btn btn--sm btn--ghost">Salva la risposta</button></div>
      </form>
      <?php endif; ?>
      <form method="post" action="<?= b() ?>/admin/giudizi/<?= (int) $r['id'] ?>/nota" class="panel stack" id="nota" style="gap:8px"><?= Csrf::field() ?>
        <label for="nota-testo" style="font-weight:600">Nota interna <span class="muted">(solo per noi)</span></label>
        <textarea id="nota-testo" name="testo" rows="2" maxlength="1000"><?= Support::e((string) ($r['nota_admin'] ?? '')) ?></textarea>
        <div class="actions"><button class="btn btn--sm btn--ghost">Salva la nota</button></div>
      </form>
    </div>
  </div>
  <section class="stack" id="storico" style="gap:8px">
    <h2 style="font-size:20px;margin:0">Storico</h2>
    <?php if (!$storico): ?><p class="small muted">Niente ancora.</p><?php else: ?>
      <ul class="small" style="margin:0;padding-left:18px">
        <?php foreach ($storico as $h): ?><li><?= Support::e(Support::date((string) $h['created_at'])) ?> · <?= Support::e($h['action']) ?><?php $d = array_diff_key($h['dati'], array_flip(['account_id', 'codice'])); echo $d ? ' · ' . Support::e(json_encode($d, JSON_UNESCAPED_UNICODE)) : ''; ?></li><?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
</div>
