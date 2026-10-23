<?php use function MHW\t, MHW\tf;
/* 9F · La pagina «Novità»: tutte le novità per chi ha già fatto la guida (lang/novita.php), dalla più recente.
   La fascia in cima al pannello mostra solo l'ultima; qui restano leggibili anche quelle passate.
   Riceve $novita (Novita::elenco) e $pidN (la prima guida dell'account, per i link con {pid}). */
use function MHW\b;
use MHW\{Support, Icon};
$title = t('Novità');
$data = fn(string $d) => MHW\Pannello::data($d); ?>
<div class="stack stack--lg" style="max-width:720px">
  <div class="stack stack--sm">
    <h1><?= t('Novità.') ?></h1>
    <p class="lead"><?= t('Quello che è cambiato nel pannello e nella guida, dalla più recente. La fascia in cima al pannello ti mostra solo l\'ultima.') ?></p>
  </div>
  <?php if (!$novita): ?><p class="note note--quiet"><?= t('Ancora nessuna novità.') ?></p><?php else: ?>
  <ul class="novita-elenco">
    <?php foreach ($novita as $n): $dove = str_contains((string) $n['dove'], '{pid}') ? ($pidN ? str_replace('{pid}', (string) $pidN, (string) $n['dove']) : '') : (string) $n['dove']; ?>
      <li>
        <time datetime="<?= Support::e($n['data']) ?>"><?= Support::e($data((string) $n['data'])) ?><?= !empty($n['vista']) ? ' · ' . t('vista') : '' ?></time>
        <b><?= Support::e($n['titolo']) ?></b>
        <p style="margin:4px 0 0"><?= Support::e($n['testo']) ?></p>
        <?php if ($dove !== ''): ?><p style="margin:8px 0 0"><a class="small" href="<?= b() . Support::e($dove) ?>"<?= str_ends_with($dove, '/anteprima') ? ' target="_blank" rel="noopener"' : '' ?>><?= t('Mostrami dove') ?> <?= Icon::svg('arrow', 14, 2) ?></a></p><?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
</div>
