<?php use function MHW\t, MHW\tf;
/* 9M · «Porta le modifiche alle gemelle» (blocco 2 del mockup): una riga per sezione cambiata nella campione (modificata,
   aggiunta, eliminata), una colonna per gemella; in ogni cella la casella e lo stato (uguale alla campione e manca:
   spuntate; personalizzata, indirizzo diverso, presente da togliere: no). Sotto, l'anteprima delle differenze della riga
   scelta (?riga=…): il testo tolto barrato, quello aggiunto evidenziato. Le rimozioni chiedono conferma.
   Riceve $d (Campione::differenze), $riga, $conferma (null, o le scelte e le rimozioni da confermare). */
use function MHW\b;
use MHW\{Support, Csrf, Campione, SectionCatalog};
$title = t('Porta le modifiche alle gemelle');
$camp = $d['campione']; $gemelle = $d['gemelle']; $righe = $d['righe'];
$scelta = null; foreach ($righe as $r) if ($r['chiave'] === $riga) $scelta = $r;
$scelta ??= $righe[0] ?? null;
$data = fn(string $iso) => $iso !== '' ? MHW\Pannello::data($iso) : '';
$spuntate = 0; foreach ($righe as $r) foreach ($r['celle'] as $c) if (Campione::STATI[$c['stato']][1] ?? false) $spuntate++; ?>
<div class="stack stack--lg gemelle" style="max-width:1100px">
  <p class="small"><a href="<?= b() ?>/pannello"><?= t('← Le mie guide') ?></a></p>
  <div class="stack stack--sm">
    <h1><?= t('Porta le modifiche alle gemelle.') ?></h1>
    <p class="lead"><?= Support::e(tf('Da {nome} (campione). Spunta cosa aggiornare. Le sezioni che hai personalizzato in una gemella sono segnate: se le aggiorni, il tuo testo viene sostituito (ne teniamo una copia per 30 giorni).', ['{nome}' => $camp['name'] ?? ''])) ?></p>
  </div>

  <?php if ($conferma): ?>
    <section class="panel stack gemelle-conferma" role="alertdialog" aria-labelledby="conferma-titolo" style="gap:12px">
      <?php foreach ($conferma['togliere'] as [$tit, $n]): ?>
        <h2 id="conferma-titolo" style="font-size:22px;margin:0"><?= Support::e($n === 1 ? tf('Vuoi togliere «{sezione}» da 1 guida?', ['{sezione}' => $tit]) : tf('Vuoi togliere «{sezione}» da {n} guide?', ['{sezione}' => $tit, '{n}' => (int) $n])) ?></h2>
      <?php endforeach; ?>
      <p class="muted" style="margin:0"><?= t('Il testo resta nello storico per 30 giorni: puoi rimetterlo.') ?></p>
      <form method="post" action="<?= b() ?>/campione/gemelle" class="row" style="gap:10px"><?= Csrf::field() ?>
        <?php foreach ($conferma['scelte'] as $k => $per) foreach ((array) $per as $gid => $v): ?><input type="hidden" name="sel[<?= Support::e((string) $k) ?>][<?= (int) $gid ?>]" value="1"><?php endforeach; ?>
        <input type="hidden" name="conferma" value="1">
        <?php $nTog = (int) array_sum(array_column($conferma['togliere'], 1)); ?><button class="btn"><?= Support::e($nTog === 1 ? t('Sì, togli da 1 guida') : tf('Sì, togli da {n} guide', ['{n}' => $nTog])) ?></button>
        <a class="btn btn--ghost" href="<?= b() ?>/campione/gemelle"><?= t('Annulla') ?></a>
      </form>
    </section>
  <?php endif; ?>

  <?php if (!$righe): ?>
    <p class="note note--quiet"><?= t('Le gemelle sono allineate alla campione: niente da aggiornare.') ?></p>
  <?php else: ?>
  <form method="post" action="<?= b() ?>/campione/gemelle" class="stack" style="gap:16px" data-gemelle><?= Csrf::field() ?>
    <div class="tablewrap"><table class="data gemelle-tabella">
      <thead><tr><th><?= t('Sezione cambiata') ?></th><?php foreach ($gemelle as $g): ?><th><?= Support::e($g['name']) ?></th><?php endforeach; ?></tr></thead>
      <tbody><?php foreach ($righe as $r): ?>
        <tr<?= $scelta && $scelta['chiave'] === $r['chiave'] ? ' class="gemelle-tabella__scelta"' : '' ?>>
          <th scope="row"><a href="<?= b() ?>/campione/gemelle?riga=<?= Support::e($r['chiave']) ?>#anteprima"><?= Support::e($r['titolo']) ?></a>
            <br><span class="tiny muted"><?= Support::e(tf(['modificata' => 'modificata il {data}', 'aggiunta' => 'aggiunta il {data}', 'eliminata' => 'eliminata il {data}'][$r['tipo']], ['{data}' => $data($r['quando'])])) . ($r['tipo'] === 'aggiunta' ? ' · ' . t('sezione nuova') : '') ?></span></th>
          <?php foreach ($gemelle as $g): $c = $r['celle'][(int) $g['id']] ?? ['stato' => 'allineata']; [$et, $si] = Campione::STATI[$c['stato']]; $et = t($et); $idc = 'sel-' . $r['chiave'] . '-' . (int) $g['id']; ?>
            <td><?php if ($c['stato'] === 'allineata'): ?><span class="pillola pillola--carta"><?= t('allineata') ?></span>
              <?php else: ?><label class="gemelle-cella" for="<?= $idc ?>"><input type="checkbox" id="<?= $idc ?>" name="sel[<?= Support::e($r['chiave']) ?>][<?= (int) $g['id'] ?>]" value="1"<?= $si ? ' checked' : '' ?><?= $c['stato'] === 'assente' ? ' disabled' : '' ?> data-gemelle-casella>
                <span class="pillola <?= ['uguale' => 'pillola--pino', 'manca' => 'pillola--ocra', 'personalizzata' => 'pillola--terracotta', 'indirizzo' => 'pillola--terracotta', 'presente' => 'pillola--ocra', 'assente' => 'pillola--carta'][$c['stato']] ?? 'pillola--carta' ?>"><?= Support::e($et) ?></span>
                <span class="sr-only"> · <?= Support::e(tf('{sezione} in {nome}', ['{sezione}' => $r['titolo'], '{nome}' => $g['name']])) ?></span></label><?php endif; ?></td>
          <?php endforeach; ?>
        </tr>
      <?php endforeach; ?></tbody>
    </table></div>

    <?php if ($scelta):
          $prima = ''; $dopo = '';
          $locP = (string) ($camp['default_locale'] ?? 'it');
          foreach ($scelta['celle'] as $c) if ($c['sezione'] && $prima === '') $prima = Campione::testo($c['sezione'], $locP);
          if ($scelta['campione']) $dopo = Campione::testo($scelta['campione'], $locP); ?>
      <section class="panel gemelle-anteprima" id="anteprima" aria-label="<?= MHW\Support::e(t('Anteprima delle differenze')) ?>">
        <b><?= t('Anteprima') ?> · <?= Support::e($scelta['titolo']) ?></b>
        <?php $diffG = Campione::diff($prima, $dopo); if (!$diffG): ?><p class="small muted" style="margin:6px 0 0"><?= t('La sezione non ha ancora testo.') ?></p><?php endif; ?>
        <p style="margin:6px 0 0"><?php foreach ($diffG as [$t, $w]): ?><?= $t === '-' ? '<del>' . Support::e($w) . '</del> ' : ($t === '+' ? '<ins>' . Support::e($w) . '</ins> ' : Support::e($w) . ' ') ?><?php endforeach; ?></p>
      </section>
    <?php endif; ?>

    <div class="row" style="gap:10px;flex-wrap:wrap">
      <button class="btn" name="applica" value="1"><span><?= t('Applica alle guide spuntate (') ?><span data-gemelle-n data-una="<?= t('sezione') ?>" data-piu="<?= t('sezioni') ?>"><?= $spuntate ?>&nbsp;<?= $spuntate === 1 ? t('sezione') : t('sezioni') ?></span>)</span></button>
      <button class="btn btn--ghost" name="non_ora" value="1" formnovalidate><?= t('Non ora') ?></button>
    </div>
    <p class="small muted" style="margin:0"><?= t('Dopo l\'aggiornamento le traduzioni delle gemelle si rifanno dalla cache della campione: non consumano il tetto annuale. Le lingue che la campione non ha restano da tradurre come sempre.') ?></p>
  </form>
  <script>
  (function () {   /* il numero sul bottone segue le caselle spuntate */
    var f = document.querySelector('[data-gemelle]'), n = f && f.querySelector('[data-gemelle-n]'); if (!f || !n) return;
    f.addEventListener('change', function () { var k = f.querySelectorAll('[data-gemelle-casella]:checked').length; n.textContent = k + '\u00a0' + (k === 1 ? n.dataset.una : n.dataset.piu); });
  })();
  </script>
  <?php endif; ?>
</div>
