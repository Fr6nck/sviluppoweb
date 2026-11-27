<?php use MHW\Support; $title = 'Diagnostica'; ?>
<div class="stack stack--lg" style="max-width:900px">
  <div class="stack stack--sm">
    <h1>Diagnostica.</h1>
    <p class="muted small">Controlli fatti adesso dal server su <?= Support::e($base) ?>. Nessun segreto viene mostrato.</p>
  </div>
  <div class="tablewrap"><table class="data"><tbody>
    <?php foreach ($checks as [$nome, $ok, $dett]): ?>
      <tr><td style="width:36px"><span class="badge badge--<?= $ok ? 'pine' : 'alert' ?>"><?= $ok ? 'OK' : 'NO' ?></span></td>
        <td><b><?= Support::e($nome) ?></b><br><span class="small muted"><?= Support::e((string) $dett) ?></span></td></tr>
    <?php endforeach; ?>
  </tbody></table></div>
  <?php if (!empty($stripe['voci'])): /* 9O · la configurazione di Stripe letta con la chiave del sito */
        $statiS = ['ok' => ['OK', 'pine'], 'sistemare' => ['Da sistemare', 'alert'], 'manuale' => ['Da controllare a mano', 'ochre']]; ?>
  <section class="stack" id="stripe" aria-labelledby="diag-stripe" style="gap:10px">
    <div class="spread" style="align-items:center;gap:12px;flex-wrap:wrap">
      <h2 id="diag-stripe" style="font-size:22px;margin:0">Stripe</h2>
      <form method="post" action="<?= MHW\b() ?>/admin/diagnostica/stripe" class="row" style="gap:8px;align-items:center;margin:0"><?= MHW\Csrf::field() ?>
        <span class="small muted">Controllato il <?= Support::e(Support::date($stripe['quando'])) ?> alle <?= Support::e(gmdate('H:i', (int) strtotime($stripe['quando']))) ?> (UTC) · vale un'ora</span>
        <button class="btn btn--ghost btn--sm">Ricontrolla</button></form>
    </div>
    <div class="tablewrap"><table class="data"><tbody>
      <?php foreach ($stripe['voci'] as $v): [$etS, $colS] = $statiS[$v['stato']] ?? [$v['stato'], 'paper']; ?>
        <tr><td style="width:150px"><span class="badge badge--<?= $colS ?>"><?= Support::e($etS) ?></span></td>
          <td><b><?= Support::e($v['titolo']) ?></b><br><span class="small muted"><?= Support::e($v['dettaglio']) ?></span></td>
          <td class="small" style="white-space:nowrap"><a href="<?= Support::e($v['link']) ?>" target="_blank" rel="noopener">Apri su Stripe</a>
            <?php if (!empty($v['conferma'])): ?>
              <form method="post" action="<?= MHW\b() ?>/admin/diagnostica/stripe" style="margin:6px 0 0"><?= MHW\Csrf::field() ?><button class="btn btn--quiet btn--sm" name="conferma" value="<?= Support::e($v['conferma']) ?>">Confermo</button></form>
            <?php endif; ?></td></tr>
      <?php endforeach; ?>
    </tbody></table></div>
  </section>
  <?php endif; ?>
  <section class="panel stack">
    <span class="kicker">Foto della landing</span>
    <p class="small">Hai sostituito una foto della landing in <code>assets/foto/</code> (<code>scena-qr.jpg</code>, <code>scena-ospite.jpg</code>, <code>scena-host.jpg</code>,
      <code>checco-telefono.jpg</code>, <code>checco-orizzontale.jpg</code>)? Rigenera le versioni WebP, più leggere: la landing usa quelle. Si può fare anche da riga di comando: <code>php app/tools/foto.php</code>.</p>
    <form method="post" action="<?= MHW\b() ?>/admin/diagnostica/foto" style="margin:0"><?= MHW\Csrf::field() ?><button class="btn btn--ghost btn--sm">Rigenera le foto WebP</button></form>
  </section>
</div>
