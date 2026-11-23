<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/admin.php';

$user = require_login();

$hasLeads = function_exists('leads_count_new');
$newLeads = $hasLeads ? leads_count_new() : 0;
$lastLeads = $hasLeads ? leads_latest(5) : [];
$lastPublish = (string) setting('last_publish_at', '');
$lastPublisher = q_one(
    'SELECT u.name FROM ' . t('versions') . ' v LEFT JOIN ' . t('users') . ' u ON u.id = v.user_id ORDER BY v.id DESC LIMIT 1'
);
$pending = content_unpublished_areas();
$areas = content_areas();

admin_header('Cruscotto', 'index.php');
?>
<div class="cards">
  <section class="stat" aria-labelledby="st-leads">
    <h2 id="st-leads">Richieste nuove</h2>
    <p class="stat-num"><?= (int) $newLeads ?></p>
    <?php if (is_file(__DIR__ . '/richieste.php')): ?><a href="richieste.php?stato=nuova">Vedi le richieste nuove</a><?php endif; ?>
  </section>
  <section class="stat" aria-labelledby="st-pub">
    <h2 id="st-pub">Ultima pubblicazione</h2>
    <p class="stat-text"><?= e(data_it($lastPublish)) ?></p>
    <?php if (!empty($lastPublisher['name'])): ?><p class="hint">di <?= e($lastPublisher['name']) ?></p><?php endif; ?>
  </section>
  <section class="stat" aria-labelledby="st-site">
    <h2 id="st-site">Il sito</h2>
    <p><a class="btn btn-line btn-small" href="../" target="_blank" rel="noopener">Apri il sito<span class="sr-only"> (nuova scheda)</span></a></p>
    <?php if (setting('maintenance', false)): ?><p class="notice notice-warn">Modalità manutenzione attiva: il pubblico vede la pagina d'attesa.</p><?php endif; ?>
  </section>
</div>

<section class="panel" aria-labelledby="h-draft">
  <h2 id="h-draft">Bozze non pubblicate</h2>
  <?php if (!$pending): ?>
    <p>Nessuna bozza in sospeso: il sito mostra la versione più recente.</p>
  <?php else: ?>
    <p>Queste parti hanno modifiche salvate in bozza ma non ancora visibili sul sito:</p>
    <ul class="list-inline">
      <?php foreach ($pending as $a): ?>
        <li><a href="<?= e($areas[$a]['page'] ?? 'index.php') ?>"><?= e($areas[$a]['label'] ?? $a) ?></a></li>
      <?php endforeach; ?>
    </ul>
    <form method="post" action="pubblica.php" class="actions-bar">
      <?= csrf_field() ?>
      <a class="btn btn-line" href="../anteprima.php" target="_blank" rel="noopener">Anteprima della bozza<span class="sr-only"> (nuova scheda)</span></a>
      <button type="submit" class="btn btn-dark">Pubblica tutto<span class="arr" aria-hidden="true">→</span></button>
    </form>
  <?php endif; ?>
</section>

<?php if ($hasLeads): ?>
<section class="panel" aria-labelledby="h-leads">
  <h2 id="h-leads">Ultime richieste</h2>
  <?php if (!$lastLeads): ?>
    <p>Non sono ancora arrivate richieste dal modulo contatti.</p>
  <?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th scope="col">Data</th><th scope="col">Nome</th><th scope="col">Attività</th><th scope="col">Stato</th></tr></thead>
      <tbody>
      <?php foreach ($lastLeads as $l): ?>
        <tr>
          <td><?= e(data_it($l['created_at'])) ?></td>
          <td><a href="richiesta.php?id=<?= (int) $l['id'] ?>"><?= e($l['name']) ?></a></td>
          <td><?= e($l['business']) ?></td>
          <td><span class="status status-<?= e($l['status']) ?>"><?= e(lead_statuses()[$l['status']] ?? $l['status']) ?></span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</section>
<?php endif; ?>

<?php
$company = content_get('azienda', 'published');
if (str_contains((string) $company['email'], '[')): ?>
  <div class="notice notice-warn" role="status">L'email del sito è ancora un segnaposto (<?= e($company['email']) ?>). Aggiornala in <?= is_file(__DIR__ . '/azienda.php') ? '<a href="azienda.php">Dati aziendali</a>' : 'Dati aziendali' ?>.</div>
<?php endif;
admin_footer();
