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
  <section class="panel stack">
    <span class="kicker">Foto della landing</span>
    <p class="small">Hai sostituito una foto della landing in <code>assets/foto/</code> (<code>scena-qr.jpg</code>, <code>scena-ospite.jpg</code>, <code>scena-host.jpg</code>,
      <code>borgo.jpg</code>, <code>borgo-telefono.jpg</code>)? Rigenera le versioni WebP, più leggere: la landing usa quelle. Si può fare anche da riga di comando: <code>php app/tools/foto.php</code>.</p>
    <form method="post" action="<?= MHW\b() ?>/admin/diagnostica/foto" style="margin:0"><?= MHW\Csrf::field() ?><button class="btn btn--ghost btn--sm">Rigenera le foto WebP</button></form>
  </section>
</div>
