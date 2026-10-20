<?php
/* 9M · «Porta le modifiche alle gemelle» (blocco 2 del mockup): una riga per sezione cambiata nella campione (modificata,
   aggiunta, eliminata), una colonna per gemella; in ogni cella la casella e lo stato (uguale alla campione e manca:
   spuntate; personalizzata, indirizzo diverso, presente da togliere: no). Sotto, l'anteprima delle differenze della riga
   scelta (?riga=…): il testo tolto barrato, quello aggiunto evidenziato. Le rimozioni chiedono conferma.
   Riceve $d (Campione::differenze), $riga, $conferma (null, o le scelte e le rimozioni da confermare). */
use function MHW\b;
use MHW\{Support, Csrf, Campione, SectionCatalog};
$title = 'Porta le modifiche alle gemelle';
$camp = $d['campione']; $gemelle = $d['gemelle']; $righe = $d['righe'];
$scelta = null; foreach ($righe as $r) if ($r['chiave'] === $riga) $scelta = $r;
$scelta ??= $righe[0] ?? null;
$data = fn(string $iso) => $iso !== '' ? Support::date($iso) : '';
$spuntate = 0; foreach ($righe as $r) foreach ($r['celle'] as $c) if (Campione::STATI[$c['stato']][1] ?? false) $spuntate++; ?>
<div class="stack stack--lg gemelle" style="max-width:1100px">
  <p class="small"><a href="<?= b() ?>/pannello">← Le mie guide</a></p>
  <div class="stack stack--sm">
    <h1>Porta le modifiche alle gemelle.</h1>
    <p class="lead">Da <?= Support::e($camp['name'] ?? '') ?> (campione). Spunta cosa aggiornare. Le sezioni che hai personalizzato in una gemella sono segnate: se le aggiorni, il tuo testo viene sostituito (ne teniamo una copia per 30 giorni).</p>
  </div>

  <?php if ($conferma): ?>
    <section class="panel stack gemelle-conferma" role="alertdialog" aria-labelledby="conferma-titolo" style="gap:12px">
      <?php foreach ($conferma['togliere'] as [$tit, $n]): ?>
        <h2 id="conferma-titolo" style="font-size:22px;margin:0">Vuoi togliere «<?= Support::e($tit) ?>» da <?= (int) $n ?> <?= $n === 1 ? 'guida' : 'guide' ?>?</h2>
      <?php endforeach; ?>
      <p class="muted" style="margin:0">Il testo resta nello storico per 30 giorni: puoi rimetterlo.</p>
      <form method="post" action="<?= b() ?>/campione/gemelle" class="row" style="gap:10px"><?= Csrf::field() ?>
        <?php foreach ($conferma['scelte'] as $k => $per) foreach ((array) $per as $gid => $v): ?><input type="hidden" name="sel[<?= Support::e((string) $k) ?>][<?= (int) $gid ?>]" value="1"><?php endforeach; ?>
        <input type="hidden" name="conferma" value="1">
        <button class="btn">Sì, togli da <?= (int) array_sum(array_column($conferma['togliere'], 1)) ?> <?= array_sum(array_column($conferma['togliere'], 1)) === 1 ? 'guida' : 'guide' ?></button>
        <a class="btn btn--ghost" href="<?= b() ?>/campione/gemelle">Annulla</a>
      </form>
    </section>
  <?php endif; ?>

  <?php if (!$righe): ?>
    <p class="note note--quiet">Le gemelle sono allineate alla campione: niente da aggiornare.</p>
  <?php else: ?>
  <form method="post" action="<?= b() ?>/campione/gemelle" class="stack" style="gap:16px" data-gemelle><?= Csrf::field() ?>
    <div class="tablewrap"><table class="data gemelle-tabella">
      <thead><tr><th>Sezione cambiata</th><?php foreach ($gemelle as $g): ?><th><?= Support::e($g['name']) ?></th><?php endforeach; ?></tr></thead>
      <tbody><?php foreach ($righe as $r): ?>
        <tr<?= $scelta && $scelta['chiave'] === $r['chiave'] ? ' class="gemelle-tabella__scelta"' : '' ?>>
          <th scope="row"><a href="<?= b() ?>/campione/gemelle?riga=<?= Support::e($r['chiave']) ?>#anteprima"><?= Support::e($r['titolo']) ?></a>
            <br><span class="tiny muted"><?= ['modificata' => 'modificata il ', 'aggiunta' => 'aggiunta il ', 'eliminata' => 'eliminata il '][$r['tipo']] . Support::e($data($r['quando'])) . ($r['tipo'] === 'aggiunta' ? ' · sezione nuova' : '') ?></span></th>
          <?php foreach ($gemelle as $g): $c = $r['celle'][(int) $g['id']] ?? ['stato' => 'allineata']; [$et, $si] = Campione::STATI[$c['stato']]; $idc = 'sel-' . $r['chiave'] . '-' . (int) $g['id']; ?>
            <td><?php if ($c['stato'] === 'allineata'): ?><span class="pillola pillola--carta">allineata</span>
              <?php else: ?><label class="gemelle-cella" for="<?= $idc ?>"><input type="checkbox" id="<?= $idc ?>" name="sel[<?= Support::e($r['chiave']) ?>][<?= (int) $g['id'] ?>]" value="1"<?= $si ? ' checked' : '' ?><?= $c['stato'] === 'assente' ? ' disabled' : '' ?> data-gemelle-casella>
                <span class="pillola <?= ['uguale' => 'pillola--pino', 'manca' => 'pillola--ocra', 'personalizzata' => 'pillola--terracotta', 'indirizzo' => 'pillola--terracotta', 'presente' => 'pillola--ocra', 'assente' => 'pillola--carta'][$c['stato']] ?? 'pillola--carta' ?>"><?= Support::e($et) ?></span>
                <span class="sr-only"> · <?= Support::e($r['titolo'] . ' in ' . $g['name']) ?></span></label><?php endif; ?></td>
          <?php endforeach; ?>
        </tr>
      <?php endforeach; ?></tbody>
    </table></div>

    <?php if ($scelta):
          $prima = ''; $dopo = '';
          $locP = (string) ($camp['default_locale'] ?? 'it');
          foreach ($scelta['celle'] as $c) if ($c['sezione'] && $prima === '') $prima = Campione::testo($c['sezione'], $locP);
          if ($scelta['campione']) $dopo = Campione::testo($scelta['campione'], $locP); ?>
      <section class="panel gemelle-anteprima" id="anteprima" aria-label="Anteprima delle differenze">
        <b>Anteprima · <?= Support::e($scelta['titolo']) ?></b>
        <?php $diffG = Campione::diff($prima, $dopo); if (!$diffG): ?><p class="small muted" style="margin:6px 0 0">La sezione non ha ancora testo.</p><?php endif; ?>
        <p style="margin:6px 0 0"><?php foreach ($diffG as [$t, $w]): ?><?= $t === '-' ? '<del>' . Support::e($w) . '</del> ' : ($t === '+' ? '<ins>' . Support::e($w) . '</ins> ' : Support::e($w) . ' ') ?><?php endforeach; ?></p>
      </section>
    <?php endif; ?>

    <div class="row" style="gap:10px;flex-wrap:wrap">
      <button class="btn" name="applica" value="1"><span>Applica alle guide spuntate (<span data-gemelle-n><?= $spuntate ?>&nbsp;<?= $spuntate === 1 ? 'sezione' : 'sezioni' ?></span>)</span></button>
      <button class="btn btn--ghost" name="non_ora" value="1" formnovalidate>Non ora</button>
    </div>
    <p class="small muted" style="margin:0">Dopo l'aggiornamento le traduzioni delle gemelle si rifanno dalla cache della campione: non consumano il tetto annuale. Le lingue che la campione non ha restano da tradurre come sempre.</p>
  </form>
  <script>
  (function () {   /* il numero sul bottone segue le caselle spuntate */
    var f = document.querySelector('[data-gemelle]'), n = f && f.querySelector('[data-gemelle-n]'); if (!f || !n) return;
    f.addEventListener('change', function () { var k = f.querySelectorAll('[data-gemelle-casella]:checked').length; n.textContent = k + '\u00a0' + (k === 1 ? 'sezione' : 'sezioni'); });
  })();
  </script>
  <?php endif; ?>
</div>
