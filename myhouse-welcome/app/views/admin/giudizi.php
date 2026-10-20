<?php
/* 9L · Amministrazione → Vendite → «Giudizi e vetrina»: le due pubblicazioni del programma «Giudizio con sconto», approvate
   separatamente. Tre schede in alto (Vetrina · Giudizi · Ritirati) con i conteggi, quattro numeri, «Esporta CSV»; la tabella
   con l'ordine (numero, frecce ▲▼ e trascinamento, separato per vetrina e giudizi), la struttura, la scheda (interruttore,
   stato, «incompleta»), il giudizio (stelle, interruttore, inizio del commento, «anche in vetrina»), le immagini e le azioni.
   Riceve $righe, $conta, $numeri (Vetrina::elenco), $scheda (vetrina|giudizi|ritirati), $stelle ('5' | 'tutti').
   Su telefono la colonna Immagini si nasconde. */
use function MHW\b;
use MHW\{Support, Csrf, Giudizi, Vetrina, Media};
$title = 'Giudizi e vetrina';
$pct = Giudizi::percentuale();
$pillola = ['in_home' => 'pillola--pino', 'in_vetrina' => 'pillola--mare', 'da_approvare' => 'pillola--terracotta', 'ritirata_admin' => 'pillola--carta', 'sospesa' => 'pillola--carta',
            'ritirato' => 'pillola--carta', 'pubblicato' => 'pillola--pino', 'non_pubblicato' => 'pillola--carta', 'sospeso' => 'pillola--carta', 'in_attesa' => 'pillola--ocra'];
$qui = '/admin/giudizi' . ($scheda !== 'vetrina' ? '?scheda=' . $scheda . ($scheda === 'giudizi' && $stelle !== '5' ? '&stelle=tutti' : '') : '');
$ordina = $scheda === 'giudizi' ? 'giudizio' : ($scheda === 'vetrina' ? 'scheda' : '');
$int = function (string $azione, int $id, bool $on, string $etichetta, bool $spento = false, string $perche = '') use ($qui): string {
    return '<form method="post" action="' . b() . '/admin/giudizi/' . $id . '/' . $azione . '" style="margin:0;display:inline-block">' . Csrf::field()
         . '<input type="hidden" name="torna" value="' . Support::e($qui) . '"><input type="hidden" name="si" value="' . ($on ? '0' : '1') . '">'
         . '<button class="int-home' . ($on ? ' int-home--on' : '') . '" role="switch" aria-checked="' . ($on ? 'true' : 'false') . '" aria-label="' . Support::e($etichetta) . '"'
         . ($spento ? ' disabled title="' . Support::e($perche) . '"' : '') . '><span class="int-home__pallina"></span><span class="int-home__testo">' . ($on ? 'Sì' : 'No') . '</span></button></form>';
}; ?>
<div class="stack stack--lg giudizi-vetrina">
  <div class="row" style="justify-content:space-between;align-items:flex-end;gap:16px;flex-wrap:wrap">
    <div class="stack stack--sm">
      <h1>Giudizi e vetrina.</h1>
      <p class="muted" style="margin:0"><?= (int) ($numeri['aderenti'] ?? 0) ?> aderenti al programma · <?= (int) ($numeri['attivi'] ?? 0) ?> clienti attivi</p>
    </div>
    <a class="btn btn--ghost btn--sm" href="<?= b() ?>/admin/giudizi?formato=csv">Esporta CSV</a>
  </div>

  <?php if (!Vetrina::disponibili()): ?><p class="note note--err" role="status">Il programma non è ancora attivo: mancano le migrazioni 050 e 051.</p><?php endif; ?>

  <nav class="row" style="gap:6px;flex-wrap:wrap" aria-label="Schede">
    <?php foreach (Vetrina::SCHEDE as $k => $et): ?><a class="btn btn--sm <?= $scheda === $k ? '' : 'btn--ghost' ?>" href="<?= b() ?>/admin/giudizi<?= $k === 'vetrina' ? '' : '?scheda=' . $k ?>" <?= $scheda === $k ? 'aria-current="page"' : '' ?>><?= $et ?> · <?= (int) ($conta[$k] ?? 0) ?></a><?php endforeach; ?>
  </nav>

  <div class="cifre" aria-label="In breve">
    <div class="cifra cifra--mare"><span class="cifra__testa">In vetrina</span><b class="cifra__valore"><?= (int) ($numeri['in_vetrina'] ?? 0) ?></b><span class="cifra__nota">in home: <?= (int) ($numeri['in_home'] ?? 0) ?> + «Vedi tutte»</span></div>
    <div class="cifra cifra--rosa"><span class="cifra__testa">Da approvare</span><b class="cifra__valore"><?= (int) ($numeri['da_approvare'] ?? 0) ?></b><span class="cifra__nota"><?= (int) ($numeri['schede_da_approvare'] ?? 0) ?> schede · <?= (int) ($numeri['giudizi_da_approvare'] ?? 0) ?> giudizi · <?= (int) ($numeri['nuovi_oggi'] ?? 0) ?> nuovi oggi</span></div>
    <div class="cifra cifra--pino"><span class="cifra__testa">Giudizi pubblicati</span><b class="cifra__valore"><?= (int) ($numeri['giudizi_pubblicati'] ?? 0) ?> / <?= (int) ($numeri['giudizi_arrivati'] ?? 0) ?></b><span class="cifra__nota">mostriamo i 5 stelle · massimo <?= (int) ($numeri['posti_giudizi'] ?? 6) ?></span></div>
    <div class="cifra cifra--ocra"><span class="cifra__testa">Doppioni</span><b class="cifra__valore"><?= (int) ($numeri['doppioni'] ?? 0) ?></b><span class="cifra__nota">in vetrina e nei giudizi</span></div>
  </div>

  <?php if ($scheda === 'giudizi'): ?>
    <p class="row small" style="gap:6px;margin:0"><span class="muted">Voti:</span>
      <a class="btn btn--sm <?= $stelle === '5' ? '' : 'btn--ghost' ?>" href="<?= b() ?>/admin/giudizi?scheda=giudizi"<?= $stelle === '5' ? ' aria-current="true"' : '' ?>>5 stelle</a>
      <a class="btn btn--sm <?= $stelle !== '5' ? '' : 'btn--ghost' ?>" href="<?= b() ?>/admin/giudizi?scheda=giudizi&amp;stelle=tutti"<?= $stelle !== '5' ? ' aria-current="true"' : '' ?>>Tutti i voti</a></p>
  <?php endif; ?>

  <?php if (!$righe): ?><p class="note note--quiet"><?= ['vetrina' => 'Nessuna scheda: nessuno ha ancora aderito.', 'giudizi' => 'Nessun giudizio' . ($stelle === '5' ? ' a 5 stelle' : '') . ' arrivato.', 'ritirati' => 'Nessuno si è ritirato dal programma.'][$scheda] ?></p><?php else: ?>
  <div class="tablewrap"><table class="data giudizi-tabella">
    <thead><tr><th>Ordine</th><th>Struttura</th><th>Scheda (vetrina)</th><th>Giudizio</th><th class="giudizi-tabella__img">Immagini</th><th><span class="sr-only">Azioni</span></th></tr></thead>
    <tbody<?= $ordina !== '' ? ' data-ordina="' . $ordina . '"' : '' ?>><?php foreach ($righe as $r): $p = $r['p']; $g = $r['g']; $id = (int) $r['id']; $ss = $r['stato_scheda']; $sg = $r['stato_giudizio'];
                 $pos = $ordina === 'giudizio' ? (int) $r['posizione_giudizio'] : ($ordina === 'scheda' ? (int) $r['posizione'] : 0);
                 $haCop = $p && !empty($p['cover_media_id']); $haLogo = $p && !empty($p['logo_media_id']); $sc = $r['scelte']; $nome = (string) ($p['name'] ?? $r['host']); ?>
      <tr<?= $pos ? ' draggable="true" data-id="' . $id . '"' : '' ?>>
        <td class="small giudizi-ordine"><?php if ($pos): ?><b><?= $pos ?></b>
            <?php foreach ([-1 => '▲', 1 => '▼'] as $verso => $freccia): ?><form method="post" action="<?= b() ?>/admin/giudizi/<?= $id ?>/sposta" style="display:inline;margin:0"><?= Csrf::field() ?>
              <input type="hidden" name="tipo" value="<?= $ordina ?>"><input type="hidden" name="verso" value="<?= $verso ?>"><input type="hidden" name="torna" value="<?= Support::e($qui) ?>">
              <button class="giudizi-ordine__freccia" aria-label="<?= $verso < 0 ? 'Sposta su' : 'Sposta giù' ?>: <?= Support::e($nome) ?>"><?= $freccia ?></button></form><?php endforeach; ?>
          <?php else: ?><span class="muted">—</span><?php endif; ?></td>
        <td><div class="giudizi-struttura">
            <?php if ($haCop): ?><img src="<?= Support::e(Media::ridotta((int) $p['cover_media_id'], 120)) ?>" alt="" width="48" height="36" loading="lazy"><?php else: ?><span class="giudizi-struttura__vuota" aria-hidden="true"></span><?php endif; ?>
            <span><b><?= Support::e($p['name'] ?? 'Guida non pubblicata') ?></b><br><span class="small muted"><?= Support::e($r['host'] . ($p ? ' · ' . Vetrina::dove($p) : '')) ?></span>
              <br><span class="tiny"><?= MHW\Codici::link((int) $r['account_id']) ?: '<a href="' . b() . '/admin/cliente/' . (int) $r['account_id'] . '">cliente ' . (int) $r['account_id'] . '</a>' ?><?= $r['piano'] !== '' ? ' · ' . Support::e($r['piano']) : '' ?> · aderito il <?= Support::e(Support::date((string) $r['giudizio_adesione_at'])) ?></span></span></div></td>
        <td class="small" style="max-width:260px">
          <?php if (!in_array($ss, ['ritirato', 'sospesa'], true)): ?><?= $int('home', $id, (int) $r['scheda_home'] === 1, 'Scheda in vetrina: ' . $nome) ?><?php endif; ?>
          <span class="pillola <?= $pillola[$ss] ?? 'pillola--ocra' ?>"><?= Support::e(Vetrina::STATI_SCHEDA[$ss] ?? $ss) ?></span>
          <br><span class="tiny muted"><?php
            if ($ss === 'in_home') echo 'approvata il ' . Support::e(Support::date((string) $r['scheda_approvata_at']));
            elseif ($ss === 'in_vetrina') echo 'non in home: oltre i ' . Vetrina::postiSchede() . ' posti';
            elseif ($ss === 'ritirata_admin') echo ($r['scheda_motivo'] ? '«' . Support::e((string) $r['scheda_motivo']) . '» · ' : '') . 'il ' . Support::e(Support::date((string) $r['scheda_ritirata_at']));
            elseif ($ss === 'sospesa') echo Support::e(Vetrina::SOSPESA[$r['motivo']] ?? '');
            elseif ($ss === 'ritirato') echo 'ritirato il ' . Support::e(Support::date((string) $r['ritirato_at'])) . ' · sconto promesso decaduto';
            elseif ($r['mancano']) echo 'incompleta: manca ' . Support::e(implode('/', $r['mancano'])) . ' · consiglio: aspetta che completi';
            else echo 'scheda completa'; ?></span></td>
        <td class="small" style="max-width:280px">
          <?php if ($g && empty($g['withdrawn_at'])): ?>
            <span class="stelline" aria-label="<?= (int) $g['rating'] ?> su 5"><?= str_repeat('★', (int) $g['rating']) . str_repeat('☆', 5 - (int) $g['rating']) ?></span>
            <?php if (!in_array($sg, ['ritirato', 'sospeso'], true)): ?><?= $int('giudizio', $id, $sg === 'pubblicato', 'Giudizio in home: ' . $nome) ?><?php endif; ?>
            <span class="pillola <?= $pillola[$sg] ?? 'pillola--ocra' ?>"><?= Support::e(Vetrina::STATI_GIUDIZIO[$sg] ?? $sg) ?></span>
            <?php if ($r['segnalato']): ?><span class="pillola pillola--terracotta">Segnalato</span><?php endif; ?>
            <br><span class="muted">«<?= Support::e(mb_strimwidth((string) $g['body'], 0, 60, '…')) ?>»<?= $r['doppione'] ? ' · anche in vetrina' : '' ?><?= (int) $g['rating'] <= 2 ? ' · sconto confermato' : '' ?></span>
          <?php else: ?>
            <span class="pillola <?= $pillola[$sg] ?? 'pillola--ocra' ?>"><?= Support::e(Vetrina::STATI_GIUDIZIO[$sg] ?? $sg) ?></span>
            <br><span class="tiny muted"><?= $r['richiesta'] ? 'richiesta il ' . Support::e(Support::date($r['richiesta'])) : 'guida non ancora pubblicata' ?></span>
          <?php endif; ?></td>
        <td class="small giudizi-tabella__img"><?= $haCop ? 'Copertina ' . ($sc['copertina'] ? '✓' : '—') : 'Copertina —' ?><br><?= $haLogo ? 'Logo ' . ($sc['logo'] ? '✓' : '—') : 'Logo —' ?></td>
        <td class="small giudizi-azioni"><a href="<?= b() ?>/admin/giudizi/<?= $id ?>">Anteprima</a>
          <?php if ($ss === 'da_approvare'): ?> · <form method="post" action="<?= b() ?>/admin/giudizi/<?= $id ?>/home" style="display:inline;margin:0"><?= Csrf::field() ?><input type="hidden" name="si" value="1"><input type="hidden" name="torna" value="<?= Support::e($qui) ?>"><button class="linkbtn">Approva</button></form><?php endif; ?>
          <?php if ($ss === 'ritirata_admin'): ?> · <form method="post" action="<?= b() ?>/admin/giudizi/<?= $id ?>/home" style="display:inline;margin:0"><?= Csrf::field() ?><input type="hidden" name="si" value="1"><input type="hidden" name="torna" value="<?= Support::e($qui) ?>"><button class="linkbtn">Rimetti</button></form><?php endif; ?>
          <?php if (in_array($ss, ['in_home', 'in_vetrina'], true)): ?> · <a href="<?= b() ?>/admin/giudizi/<?= $id ?>#ritira">Ritira</a><?php endif; ?>
          <?php if ($g && empty($g['withdrawn_at'])): ?> · <a href="<?= b() ?>/admin/giudizi/<?= $id ?>#giudizio"><?= $sg === 'pubblicato' ? 'Modifica' : 'Leggi' ?></a> · <a href="<?= b() ?>/admin/giudizi/<?= $id ?>#rispondi">Rispondi</a><?php endif; ?>
          · <a href="<?= b() ?>/admin/giudizi/<?= $id ?>#nota">Nota</a> · <a href="<?= b() ?>/admin/giudizi/<?= $id ?>#storico">Storico</a></td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php if ($ordina !== ''): ?>
    <form method="post" action="<?= b() ?>/admin/giudizi/ordine" id="ordine-trascinato" hidden><?= Csrf::field() ?><input type="hidden" name="tipo" value="<?= $ordina ?>"><input type="hidden" name="torna" value="<?= Support::e($qui) ?>"></form>
    <script>
    /* L'ordine trascinando le righe numerate (con il mouse); da tastiera ci sono le frecce ▲▼. */
    (function () {
      var corpo = document.querySelector('tbody[data-ordina]'), modulo = document.getElementById('ordine-trascinato'), presa = null;
      if (!corpo || !modulo) return;
      corpo.addEventListener('dragstart', function (e) { presa = e.target.closest('tr[data-id]'); if (presa) { presa.classList.add('giudizi-trascina'); e.dataTransfer.effectAllowed = 'move'; } });
      corpo.addEventListener('dragover', function (e) {
        var su = e.target.closest('tr[data-id]'); if (!presa || !su || su === presa) return; e.preventDefault();
        var r = su.getBoundingClientRect(); corpo.insertBefore(presa, e.clientY < r.top + r.height / 2 ? su : su.nextSibling);
      });
      corpo.addEventListener('dragend', function () {
        if (!presa) return; presa.classList.remove('giudizi-trascina'); presa = null;
        corpo.querySelectorAll('tr[data-id]').forEach(function (tr) { var i = document.createElement('input'); i.type = 'hidden'; i.name = 'ids[]'; i.value = tr.getAttribute('data-id'); modulo.appendChild(i); });
        modulo.submit();
      });
    })();
    </script>
  <?php endif; ?>
  <?php endif; ?>
  <p class="small muted" style="max-width:860px;margin:0"><b>Regole.</b> Scheda e giudizio si approvano separatamente, ognuno con il suo interruttore e la sua data.
    Vetrina: di norma si approvano tutte; in home ne stanno <?= Vetrina::postiSchede() ?> (Impostazioni → Sito) nell'ordine scelto qui, le altre nella pagina «Tutte le guide»;
    ritirarne una chiede un motivo interno (il cliente vede solo «non in home al momento»). Giudizi: mai in automatico, di norma solo 5 stelle, al massimo <?= Vetrina::postiGiudizi() ?> in home.
    Una struttura può stare in tutte e due le sezioni («Doppioni»). L'ordine si cambia con le frecce o trascinando, per ognuna delle due sezioni.
    Si spengono da soli: guida archiviata, abbonamento scaduto, account chiuso, ritiro dell'host; copertina o logo tolti dopo l'approvazione: la scheda torna da approvare.
    «Segnalato»: 1–2 stelle o parole da controllare, solo per attirare l'attenzione: lo sconto resta. Ogni azione finisce nel registro con il codice cliente.</p>

  <?php /* Gli interruttori del programma (le stesse voci di Impostazioni → Giudizi con sconto). */ $qG = Giudizi::quando(); ?>
  <details class="panel" id="programma"><summary class="legend" style="cursor:pointer">Il programma: sconto, percentuale, sezioni in home</summary>
  <form method="post" action="<?= b() ?>/admin/giudizi/impostazioni" class="giudizi-programma" style="margin-top:12px"><?= Csrf::field() ?>
    <label class="scelta scelta--mini"><input type="checkbox" name="giudizi__sconto_attivo" value="1"<?= Giudizi::attivo() ? ' checked' : '' ?>><span>Programma aperto ai nuovi clienti (<?= $pct ?>%) <b><?= Giudizi::attivo() ? 'attivo' : 'spento' ?></b></span></label>
    <div class="field giudizi-programma__campo" style="margin:0"><label for="gp-percentuale">Percentuale</label><input id="gp-percentuale" name="giudizi__percentuale" type="text" inputmode="numeric" value="<?= $pct ?>" style="width:5em"></div>
    <div class="field giudizi-programma__campo" style="margin:0"><label for="gp-quando">Sconto applicato</label>
      <select id="gp-quando" name="giudizi__sconto_quando">
        <option value="adesione"<?= $qG === 'adesione' ? ' selected' : '' ?>>all'adesione (anche se il giudizio arriva dopo)</option>
        <option value="giudizio"<?= $qG === 'giudizio' ? ' selected' : '' ?>>solo dopo il giudizio</option>
      </select></div>
    <label class="scelta scelta--mini"><input type="checkbox" name="giudizi__home" value="1"<?= Giudizi::homeAccesa() ? ' checked' : '' ?>><span>Sezioni «Le guide dei nostri host» e «Cosa dicono di noi» <b><?= Giudizi::homeAccesa() ? 'visibili' : 'spente' ?></b></span></label>
    <div class="field giudizi-programma__campo" style="margin:0;flex-basis:100%"><label for="gp-parole">Parole da controllare (separate da virgole)</label><input id="gp-parole" name="giudizi__parole" type="text" value="<?= Support::e((string) ((MHW\Config::get('giudizi') ?? [])['parole'] ?? '')) ?>"></div>
    <div class="field giudizi-programma__campo" style="margin:0"><label for="gp-password">La tua password, per confermare</label><input id="gp-password" name="password" type="password" autocomplete="current-password" required style="width:12em"></div>
    <button class="btn btn--sm">Salva</button>
  </form></details>
</div>
