<?php
/* 9B · Amministrazione → Clienti → Giudizi: gli interruttori del programma (Impostazioni → Giudizi con sconto),
   la tabella dei giudizi dei clienti con «In home» (la nostra decisione), l'anteprima della scheda come in home.
   Riceve $righe (giudiziClienti con immagini), $filtro, $inHome, $aderiti. */
use function MHW\b;
use MHW\{Support, Csrf, Icon, Giudizi, Testimonianze};
$title = 'Giudizi';
$filtri = ['tutti' => 'Tutti', 'da_valutare' => 'Da valutare', 'in_home' => 'In home', 'ritirati' => 'Ritirati'];
$pct = Giudizi::percentuale(); ?>
<div class="stack stack--lg">
  <div class="stack stack--sm">
    <h1>Giudizi.</h1>
    <p class="muted" style="max-width:760px">«Ti piace MyHouse Welcome?»: chi aderisce lascia un voto con due righe e accetta che possa comparire in home, con nome, struttura, città
      e le immagini che sceglie, senza link alla guida. In cambio ha il <?= $pct ?>%, qualunque voto dia. Quali giudizi vanno in home lo decidi qui.</p>
  </div>

  <?php if (!Giudizi::disponibili()): ?><p class="note note--err" role="status">Il programma non è ancora attivo: manca la migrazione 044.</p><?php endif; ?>
  <div class="cifre" aria-label="In breve">
    <div class="cifra <?= Giudizi::attivo() ? 'cifra--pino' : 'cifra--carta' ?>"><span class="cifra__testa">Sconto per i nuovi clienti</span><b class="cifra__valore cifra__valore--testo"><?= Giudizi::attivo() ? 'Acceso · ' . $pct . '%' : 'Spento' ?></b>
      <span class="cifra__nota"><a href="<?= b() ?>/admin/impostazioni#giudizi">Impostazioni → Giudizi con sconto</a></span></div>
    <div class="cifra cifra--ocra"><span class="cifra__testa">Hanno aderito</span><b class="cifra__valore"><?= (int) $aderiti ?></b><span class="cifra__nota">clienti, al pagamento o dal pannello</span></div>
    <div class="cifra <?= Giudizi::homeAccesa() ? 'cifra--mare' : 'cifra--carta' ?>"><span class="cifra__testa">Sezione in home</span><b class="cifra__valore"><?= (int) $inHome ?></b>
      <span class="cifra__nota"><?= Giudizi::homeAccesa() ? 'giudizi mostrati in «Cosa dicono di noi»' : 'sezione spenta (Impostazioni)' ?></span></div>
  </div>

  <?php /* 9B · Gli interruttori del programma, qui (le stesse voci di Impostazioni → Giudizi con sconto): acceso, percentuale, quando si applica, sezione in home. */
        $qG = Giudizi::quando(); ?>
  <form method="post" action="<?= b() ?>/admin/giudizi/impostazioni" class="panel giudizi-programma" id="programma" aria-labelledby="programma-titolo"><?= Csrf::field() ?>
    <h2 id="programma-titolo" class="sr-only">Il programma</h2>
    <label class="scelta scelta--mini"><input type="checkbox" name="giudizi__sconto_attivo" value="1"<?= Giudizi::attivo() ? ' checked' : '' ?>><span>Sconto del <?= $pct ?>% per i nuovi clienti <b><?= Giudizi::attivo() ? 'attivo' : 'spento' ?></b></span></label>
    <div class="field giudizi-programma__campo" style="margin:0"><label for="gp-percentuale">Percentuale</label><input id="gp-percentuale" name="giudizi__percentuale" type="text" inputmode="numeric" value="<?= $pct ?>" style="width:5em"></div>
    <div class="field giudizi-programma__campo" style="margin:0"><label for="gp-quando">Sconto applicato</label>
      <select id="gp-quando" name="giudizi__sconto_quando" aria-describedby="gp-quando-aiuto">
        <option value="adesione"<?= $qG === 'adesione' ? ' selected' : '' ?>>all'adesione (anche se il giudizio arriva dopo)</option>
        <option value="giudizio"<?= $qG === 'giudizio' ? ' selected' : '' ?>>solo dopo il giudizio</option>
      </select></div>
    <label class="scelta scelta--mini"><input type="checkbox" name="giudizi__home" value="1"<?= Giudizi::homeAccesa() ? ' checked' : '' ?>><span>Sezione in home <b><?= Giudizi::homeAccesa() ? 'visibile' : 'spenta' ?></b> (<?= (int) $inHome ?> pubblicati)</span></label>
    <div class="field giudizi-programma__campo" style="margin:0"><label for="gp-password">La tua password, per confermare</label><input id="gp-password" name="password" type="password" autocomplete="current-password" required style="width:12em"></div>
    <button class="btn btn--sm">Salva</button>
    <p class="help" id="gp-quando-aiuto" style="flex-basis:100%;margin:0">«All'adesione»: chi spunta la casella al pagamento ha lo sconto sul primo addebito, anche se il giudizio arriva dopo. «Solo dopo il giudizio»: lo sconto parte quando il giudizio è stato inviato, sul primo addebito se arriva prima (per esempio durante i mesi di un codice omaggio), altrimenti sul rinnovo. Vale per chi aderisce da adesso in poi.</p>
  </form>

  <nav class="row" style="gap:6px;flex-wrap:wrap" aria-label="Filtri">
    <?php foreach ($filtri as $k => $et): ?><a class="btn btn--sm <?= $filtro === $k ? '' : 'btn--ghost' ?>" href="<?= b() ?>/admin/giudizi<?= $k === 'tutti' ? '' : '?filtro=' . $k ?>" <?= $filtro === $k ? 'aria-current="page"' : '' ?>><?= $et ?></a><?php endforeach; ?>
  </nav>

  <?php if (!$righe): ?><p class="note note--quiet">Nessun giudizio<?= $filtro !== 'tutti' ? ' con questo filtro' : ' ancora' ?>.</p><?php else: ?>
  <div class="tablewrap"><table class="data giudizi-tabella">
    <thead><tr><th>Cliente e struttura</th><th>Voto</th><th>Giudizio</th><th>Adesione</th><th>Immagini</th><th>Sconto</th><th>In home</th><th><span class="sr-only">Azioni</span></th></tr></thead>
    <tbody><?php foreach ($righe as $g): $ritirato = !empty($g['withdrawn_at']); $home = !empty($g['home_visible']) && !$ritirato; $puo = !empty($g['consent_at']) && !$ritirato;
                 $scelte = array_keys(array_filter(['Copertina' => !empty($g['show_cover']), 'Foto' => !empty($g['show_photo']), 'Logo' => !empty($g['show_logo'])])); ?>
      <tr>
        <td><b><?= Support::e($g['name']) ?></b><br><span class="small muted"><?= Support::e(trim($g['property_name'] . ($g['city'] !== '' ? ' · ' . $g['city'] : ''), ' ·')) ?></span>
          <br><a class="tiny" href="<?= b() ?>/admin/cliente/<?= (int) $g['account_id'] ?>"><?= Support::e((string) $g['email']) ?></a> <span class="tiny"><?= MHW\Codici::link((int) $g['account_id']) ?></span></td>
        <td><span class="stelline" aria-label="<?= (int) $g['rating'] ?> su 5"><?= str_repeat('★', (int) $g['rating']) . str_repeat('☆', 5 - (int) $g['rating']) ?></span></td>
        <td class="small" style="max-width:320px"><details><summary style="cursor:pointer"><?= Support::e(mb_strimwidth((string) $g['body'], 0, 70, '…')) ?></summary><p style="margin:6px 0 0">«<?= Support::e($g['body']) ?>»</p>
            <p class="tiny muted" style="margin:6px 0 0"><?= Support::e(Support::date($g['created_at'])) ?><?= $ritirato ? ' · ritirato il ' . Support::e(Support::date($g['withdrawn_at'])) : '' ?></p></details></td>
        <td class="small"><?php if (!empty($g['giudizio_adesione'])): ?><?= Support::e(Giudizi::ADESIONI[$g['giudizio_adesione']] ?? $g['giudizio_adesione']) ?><br><span class="tiny muted"><?= Support::e(Support::date($g['giudizio_adesione_at'] ?? $g['consent_at'])) ?></span>
          <?php elseif (!empty($g['consent_at'])): ?>Consenso (7D)<?php else: ?><span class="muted">Nessuna</span><?php endif; ?></td>
        <td class="small"><?= $scelte ? Support::e(implode(', ', $scelte)) : '<span class="muted">Nessuna</span>' ?></td>
        <td class="small"><?= Support::e(Giudizi::STATI[$g['giudizio_sconto_stato'] ?? 'nessuno'] ?? 'Nessuno') ?></td>
        <td><form method="post" action="<?= b() ?>/admin/giudizi/<?= (int) $g['id'] ?>/home" style="margin:0"><?= Csrf::field() ?><input type="hidden" name="filtro" value="<?= Support::e($filtro) ?>">
          <?php if ($puo): ?><input type="hidden" name="si" value="<?= $home ? '0' : '1' ?>">
            <button class="int-home<?= $home ? ' int-home--on' : '' ?>" role="switch" aria-checked="<?= $home ? 'true' : 'false' ?>" aria-label="In home: <?= Support::e($g['name']) ?>"><span class="int-home__pallina"></span><span class="int-home__testo"><?= $home ? 'Sì' : 'No' ?></span></button>
          <?php else: ?><span class="badge badge--<?= $ritirato ? 'alert' : 'paper' ?>"><?= $ritirato ? 'Ritirato' : 'Senza adesione' ?></span><?php endif; ?></form></td>
        <td><form method="post" action="<?= b() ?>/admin/giudizi/<?= (int) $g['id'] ?>/elimina" style="margin:0" onsubmit="return confirm('Eliminare questo giudizio?')"><?= Csrf::field() ?><button class="linkbtn" aria-label="Elimina il giudizio di <?= Support::e($g['name']) ?>">Elimina</button></form></td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>

  <?php $anteprime = array_values(array_filter($righe, fn($g) => !empty($g['consent_at']) && empty($g['withdrawn_at'])));
        if ($anteprime): $g = $anteprime[0]; $im = $g['immagini']; ?>
  <section class="stack" aria-labelledby="anteprima-titolo" style="gap:10px;max-width:420px">
    <h2 id="anteprima-titolo" style="font-size:20px">Anteprima della scheda<?= count($anteprime) > 1 ? ' (la prima dell\'elenco)' : '' ?></h2>
    <p class="small muted" style="margin:0">Come appare in «Cosa dicono di noi»: nessun link, le sole immagini scelte dal cliente.</p>
    <?php include dirname(__DIR__) . '/pub/_dicono_scheda.php'; ?>
  </section>
  <?php endif; ?>
</div>
