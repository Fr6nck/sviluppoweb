<?php
/* 9L · Una scheda di «Giudizi e vetrina»: l'anteprima com'è in home, il giudizio da leggere e approvare, la risposta
   pubblica (facoltativa, sotto il giudizio), la nota interna e lo storico (audit_log). Riceve $r (una riga di
   Vetrina::elenco), $card (Vetrina::perScheda, con il giudizio solo se approvato), $storico. */
use function MHW\b;
use MHW\{Support, Csrf, Giudizi, Vetrina};
$title = 'Scheda · ' . ($r['p']['name'] ?? $r['host']);
$g = $r['g']; $sg = Vetrina::statoGiudizio($g); $ritirato = !empty($r['ritirato_at']); ?>
<div class="stack stack--lg" style="max-width:980px">
  <p class="small"><a href="<?= b() ?>/admin/giudizi">← Giudizi e vetrina</a></p>
  <div class="stack stack--sm">
    <h1><?= Support::e($r['p']['name'] ?? 'Guida non pubblicata') ?>.</h1>
    <p class="muted" style="margin:0"><?= Support::e($r['host']) ?> · <?= Support::e($r['codice']) ?> · <?= Support::e(Vetrina::STATI[$r['stato']] ?? $r['stato']) ?><?= $r['motivo'] !== '' ? ' (' . Support::e(Vetrina::SOSPESA[$r['motivo']]) . ')' : '' ?>
      · aderito il <?= Support::e(Support::date((string) $r['giudizio_adesione_at'])) ?> <?= Support::e(mb_strtolower(Giudizi::ADESIONI[$r['giudizio_adesione']] ?? '')) ?> · <?= Support::e(Giudizi::STATI[$r['giudizio_sconto_stato']] ?? '') ?></p>
  </div>
  <div class="giudizio-dettaglio">
    <section class="stack" aria-labelledby="anteprima-titolo" style="gap:10px">
      <h2 id="anteprima-titolo" style="font-size:20px;margin:0">Anteprima</h2>
      <p class="small muted" style="margin:0">Com'è in «Le guide dei nostri host»: nessun link, nessuna foto dell'host.</p>
      <?php if ($card): ?><div class="dicono"><?php $g0 = $g; $g = $card; include dirname(__DIR__) . '/pub/_dicono_scheda.php'; $g = $g0; ?></div>
      <?php else: ?><p class="note note--quiet">La guida non è ancora pubblicata.</p><?php endif; ?>
    </section>
    <div class="stack" style="gap:18px">
      <section class="panel stack" id="giudizio" style="gap:10px">
        <h2 style="font-size:20px;margin:0">Il giudizio</h2>
        <?php if ($g && $sg !== 'ritirato'): ?>
          <p style="margin:0"><span class="stelline" aria-label="<?= (int) $g['rating'] ?> su 5"><?= str_repeat('★', (int) $g['rating']) . str_repeat('☆', 5 - (int) $g['rating']) ?></span> · <?= Support::e(Support::date((string) $g['created_at'])) ?><?= $r['segnalato'] ? ' · <b>segnalato</b>' : '' ?></p>
          <blockquote style="margin:0">«<?= Support::e((string) $g['body']) ?>»</blockquote>
          <form method="post" action="<?= b() ?>/admin/giudizi/<?= (int) $r['id'] ?>/giudizio" class="row" style="gap:8px"><?= Csrf::field() ?>
            <input type="hidden" name="approva" value="<?= $sg === 'pubblicato' ? '0' : '1' ?>">
            <button class="btn btn--sm<?= $sg === 'pubblicato' ? ' btn--ghost' : '' ?>"><?= $sg === 'pubblicato' ? 'Togli il giudizio dalla scheda' : 'Approva: sulla scheda' ?></button></form>
          <p class="help" style="margin:0">Lo sconto non dipende dal voto né dall'approvazione.</p>
        <?php else: ?><p class="small muted" style="margin:0"><?= $ritirato ? 'L\'host si è ritirato: giudizio e scheda non si vedono.' : 'Nessun giudizio ancora' . ($r['richiesta'] ? ': lo chiediamo il ' . Support::e(Support::date($r['richiesta'])) . '.' : '.') ?></p><?php endif; ?>
      </section>
      <?php if ($g && $sg !== 'ritirato'): ?>
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
