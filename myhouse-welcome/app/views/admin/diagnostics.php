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
</div>
