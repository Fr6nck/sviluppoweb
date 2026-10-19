<?php
/* 9L · Amministrazione → Vendite → «Giudizi e vetrina»: chi ha aderito al programma «Giudizio con sconto» e cosa mostriamo
   in home («Le guide dei nostri host»). In alto i filtri con i conteggi, quattro numeri e «Esporta CSV»; poi la tabella
   (struttura, adesione, giudizio, immagini, interruttore «In home», azioni) e gli interruttori del programma.
   Regole: al massimo 6 schede accese (prima quelle con giudizio); una scheda nuova entra «da approvare» e si accende a
   mano; l'interruttore si spegne da solo (guida archiviata, abbonamento scaduto, account chiuso, copertina mancante).
   Riceve $righe, $conta, $numeri (Vetrina::elenco), $filtro. Su telefono la colonna Immagini si nasconde. */
use function MHW\b;
use MHW\{Support, Csrf, Giudizi, Vetrina, Media};
$title = 'Giudizi e vetrina';
$pct = Giudizi::percentuale();
$pillola = ['in_attesa' => 'pillola--ocra', 'da_approvare' => 'pillola--terracotta', 'pubblicato' => 'pillola--pino', 'ritirato' => 'pillola--carta', 'sospeso' => 'pillola--carta']; ?>
<div class="stack stack--lg giudizi-vetrina">
  <div class="row" style="justify-content:space-between;align-items:flex-end;gap:16px;flex-wrap:wrap">
    <div class="stack stack--sm">
      <h1>Giudizi e vetrina.</h1>
      <p class="muted" style="margin:0">Chi ha aderito al programma, cosa mostriamo in home.</p>
    </div>
    <a class="btn btn--ghost btn--sm" href="<?= b() ?>/admin/giudizi?formato=csv">Esporta CSV</a>
  </div>

  <?php if (!Vetrina::disponibili()): ?><p class="note note--err" role="status">Il programma non è ancora attivo: manca la migrazione 050.</p><?php endif; ?>

  <nav class="row" style="gap:6px;flex-wrap:wrap" aria-label="Filtri">
    <?php foreach (Vetrina::FILTRI as $k => $et): ?><a class="btn btn--sm <?= $filtro === $k ? '' : 'btn--ghost' ?>" href="<?= b() ?>/admin/giudizi<?= $k === 'tutti' ? '' : '?filtro=' . $k ?>" <?= $filtro === $k ? 'aria-current="page"' : '' ?>><?= $et ?> · <?= (int) ($conta[$k] ?? 0) ?></a><?php endforeach; ?>
  </nav>

  <div class="cifre" aria-label="In breve">
    <div class="cifra cifra--ocra"><span class="cifra__testa">Aderenti</span><b class="cifra__valore"><?= (int) ($numeri['aderenti'] ?? 0) ?></b><span class="cifra__nota">su <?= (int) ($numeri['attivi'] ?? 0) ?> clienti attivi</span></div>
    <div class="cifra cifra--mare"><span class="cifra__testa">In home</span><b class="cifra__valore"><?= (int) ($numeri['in_home'] ?? 0) ?> / <?= Vetrina::MASSIMO ?></b><span class="cifra__nota"><?= (int) ($numeri['in_home'] ?? 0) >= Vetrina::MASSIMO ? 'posti pieni' : 'posti liberi: ' . (Vetrina::MASSIMO - (int) ($numeri['in_home'] ?? 0)) ?></span></div>
    <div class="cifra cifra--pino"><span class="cifra__testa">Giudizi arrivati</span><b class="cifra__valore"><?= (int) ($numeri['giudizi'] ?? 0) ?></b><span class="cifra__nota"><?= ($numeri['media'] ?? null) !== null ? 'media ' . str_replace('.', ',', (string) $numeri['media']) : 'nessuno ancora' ?></span></div>
    <div class="cifra cifra--rosa"><span class="cifra__testa">Da approvare</span><b class="cifra__valore"><?= (int) ($numeri['da_approvare'] ?? 0) ?></b><span class="cifra__nota"><?= (int) ($numeri['nuovi_oggi'] ?? 0) ?> nuovi oggi</span></div>
  </div>

  <?php if (!$righe): ?><p class="note note--quiet">Nessuna scheda<?= $filtro !== 'tutti' ? ' con questo filtro' : ': nessuno ha ancora aderito' ?>.</p><?php else: ?>
  <div class="tablewrap"><table class="data giudizi-tabella">
    <thead><tr><th>Struttura</th><th>Adesione</th><th>Giudizio</th><th class="giudizi-tabella__img">Immagini</th><th>In home</th><th><span class="sr-only">Azioni</span></th></tr></thead>
    <tbody><?php foreach ($righe as $r): $p = $r['p']; $g = $r['g']; $ritirato = !empty($r['ritirato_at']); $home = (int) $r['scheda_home'] === 1 && $r['motivo'] === '' && !$ritirato;
                 $sc = $r['scelte']; $haCop = $p && !empty($p['cover_media_id']); $haLogo = $p && !empty($p['logo_media_id']); ?>
      <tr>
        <td><div class="giudizi-struttura">
            <?php if ($haCop): ?><img src="<?= Support::e(Media::ridotta((int) $p['cover_media_id'], 120)) ?>" alt="" width="48" height="36" loading="lazy"><?php else: ?><span class="giudizi-struttura__vuota" aria-hidden="true"></span><?php endif; ?>
            <span><b><?= Support::e($p['name'] ?? 'Guida non pubblicata') ?></b><br><span class="small muted"><?= Support::e($r['host'] . ($p ? ' · ' . Vetrina::dove($p) : '')) ?></span>
              <br><span class="tiny"><?= MHW\Codici::link((int) $r['account_id']) ?: '<a href="' . b() . '/admin/cliente/' . (int) $r['account_id'] . '">cliente ' . (int) $r['account_id'] . '</a>' ?><?= $r['piano'] !== '' ? ' · ' . Support::e($r['piano']) : '' ?></span></span></div></td>
        <td class="small"><?= Support::e(Support::date((string) $r['giudizio_adesione_at'])) ?><br><span class="tiny muted"><?= Support::e(mb_strtolower(Giudizi::ADESIONI[$r['giudizio_adesione']] ?? '')) ?> · <?= Support::e(Giudizi::STATI[$r['giudizio_sconto_stato']] ?? '') ?></span></td>
        <td class="small" style="max-width:300px">
          <?php if ($g && Vetrina::statoGiudizio($g) !== 'ritirato'): ?>
            <span class="stelline" aria-label="<?= (int) $g['rating'] ?> su 5"><?= str_repeat('★', (int) $g['rating']) . str_repeat('☆', 5 - (int) $g['rating']) ?></span>
            <span class="pillola <?= $pillola[Vetrina::statoGiudizio($g) === 'pubblicato' ? 'pubblicato' : 'da_approvare'] ?>"><?= Vetrina::statoGiudizio($g) === 'pubblicato' ? 'Pubblicato' : 'Da approvare' ?></span>
            <?php if ($r['segnalato']): ?><span class="pillola pillola--terracotta">Segnalato</span><?php endif; ?>
            <br><span class="muted">«<?= Support::e(mb_strimwidth((string) $g['body'], 0, 60, '…')) ?>»<?= (int) $g['rating'] <= 2 ? ' · ' . (int) $g['rating'] . ' stell' . ((int) $g['rating'] === 1 ? 'a' : 'e') . ': lo sconto resta' : '' ?></span>
          <?php else: ?>
            <span class="pillola <?= $pillola[$r['stato']] ?? 'pillola--ocra' ?>"><?= $ritirato ? 'Ritirato il ' . Support::e(Support::date((string) $r['ritirato_at'])) : ($r['stato'] === 'sospeso' ? 'Sospesa: ' . Support::e(Vetrina::SOSPESA[$r['motivo']] ?? '') : ($r['stato'] === 'da_approvare' ? 'Scheda da approvare' : 'In attesa')) ?></span>
            <br><span class="tiny muted"><?= $ritirato ? 'sconto promesso decaduto' : ($r['richiesta'] ? 'giudizio richiesto il ' . Support::e(Support::date($r['richiesta'])) : 'guida non ancora pubblicata') ?></span>
          <?php endif; ?></td>
        <td class="small giudizi-tabella__img"><?= $haCop ? 'Copertina ' . ($sc['copertina'] ? '✓' : '—') : 'Copertina: manca' ?><br><?= $haLogo ? 'Logo ' . ($sc['logo'] ? '✓' : '—') : 'Logo —' ?></td>
        <td><?php if (!$ritirato): ?><form method="post" action="<?= b() ?>/admin/giudizi/<?= (int) $r['id'] ?>/home" style="margin:0"><?= Csrf::field() ?><input type="hidden" name="filtro" value="<?= Support::e($filtro) ?>"><input type="hidden" name="si" value="<?= $home ? '0' : '1' ?>">
            <button class="int-home<?= $home ? ' int-home--on' : '' ?>" role="switch" aria-checked="<?= $home ? 'true' : 'false' ?>" aria-label="In home: <?= Support::e($p['name'] ?? $r['host']) ?>"<?= !$home && $r['motivo'] !== '' ? ' disabled title="' . Support::e(Vetrina::SOSPESA[$r['motivo']] ?? '') . '"' : '' ?>><span class="int-home__pallina"></span><span class="int-home__testo"><?= $home ? 'Sì' : 'No' ?></span></button></form>
          <?php else: ?><span class="muted">—</span><?php endif; ?></td>
        <td class="small"><a href="<?= b() ?>/admin/giudizi/<?= (int) $r['id'] ?>">Anteprima</a>
          <?php if ($g && Vetrina::statoGiudizio($g) !== 'ritirato'): ?> · <a href="<?= b() ?>/admin/giudizi/<?= (int) $r['id'] ?>#giudizio"><?= Vetrina::statoGiudizio($g) === 'pubblicato' ? 'Modifica' : 'Leggi' ?></a> · <a href="<?= b() ?>/admin/giudizi/<?= (int) $r['id'] ?>#rispondi">Rispondi</a><?php endif; ?>
          · <a href="<?= b() ?>/admin/giudizi/<?= (int) $r['id'] ?>#nota">Nota</a> · <a href="<?= b() ?>/admin/giudizi/<?= (int) $r['id'] ?>#storico">Storico</a></td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
  <p class="small muted" style="max-width:860px;margin:0">Massimo <?= Vetrina::MASSIMO ?> schede in home (prima quelle con il giudizio, poi le altre, le più recenti prima). Una scheda nuova entra «da approvare» e va accesa a mano;
    l'interruttore si spegne da solo se la guida è archiviata, l'abbonamento scade, l'host si ritira o manca la copertina. Ogni azione finisce nel registro con il codice cliente.
    «Segnalati»: 1–2 stelle o parole da controllare (Impostazioni → Giudizi con sconto), solo per attirare l'attenzione: lo sconto resta.</p>

  <?php /* Gli interruttori del programma (le stesse voci di Impostazioni → Giudizi con sconto). */ $qG = Giudizi::quando(); ?>
  <details class="panel" id="programma"><summary class="legend" style="cursor:pointer">Il programma: sconto, percentuale, sezione in home</summary>
  <form method="post" action="<?= b() ?>/admin/giudizi/impostazioni" class="giudizi-programma" style="margin-top:12px"><?= Csrf::field() ?>
    <label class="scelta scelta--mini"><input type="checkbox" name="giudizi__sconto_attivo" value="1"<?= Giudizi::attivo() ? ' checked' : '' ?>><span>Programma aperto ai nuovi clienti (<?= $pct ?>%) <b><?= Giudizi::attivo() ? 'attivo' : 'spento' ?></b></span></label>
    <div class="field giudizi-programma__campo" style="margin:0"><label for="gp-percentuale">Percentuale</label><input id="gp-percentuale" name="giudizi__percentuale" type="text" inputmode="numeric" value="<?= $pct ?>" style="width:5em"></div>
    <div class="field giudizi-programma__campo" style="margin:0"><label for="gp-quando">Sconto applicato</label>
      <select id="gp-quando" name="giudizi__sconto_quando">
        <option value="adesione"<?= $qG === 'adesione' ? ' selected' : '' ?>>all'adesione (anche se il giudizio arriva dopo)</option>
        <option value="giudizio"<?= $qG === 'giudizio' ? ' selected' : '' ?>>solo dopo il giudizio</option>
      </select></div>
    <label class="scelta scelta--mini"><input type="checkbox" name="giudizi__home" value="1"<?= Giudizi::homeAccesa() ? ' checked' : '' ?>><span>Sezione «Le guide dei nostri host» <b><?= Giudizi::homeAccesa() ? 'visibile' : 'spenta' ?></b></span></label>
    <div class="field giudizi-programma__campo" style="margin:0;flex-basis:100%"><label for="gp-parole">Parole da controllare (separate da virgole)</label><input id="gp-parole" name="giudizi__parole" type="text" value="<?= Support::e((string) ((MHW\Config::get('giudizi') ?? [])['parole'] ?? '')) ?>"></div>
    <div class="field giudizi-programma__campo" style="margin:0"><label for="gp-password">La tua password, per confermare</label><input id="gp-password" name="password" type="password" autocomplete="current-password" required style="width:12em"></div>
    <button class="btn btn--sm">Salva</button>
  </form></details>
</div>
