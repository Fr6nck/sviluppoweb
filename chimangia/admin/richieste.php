<?php
/** Elenco delle richieste dal modulo contatti, con ricerca, filtri ed esportazione CSV. */
declare(strict_types=1);

require dirname(__DIR__) . '/app/admin.php';

$user = require_role('editor');
$filters = ['q' => mb_substr(get_param('q'), 0, 100), 'stato' => get_param('stato'), 'dal' => get_param('dal'), 'al' => get_param('al')];
[$where, $params] = leads_where($filters);

/* ---------- Esportazione CSV (separatore ;, UTF-8 con BOM per Excel) ---------- */
if (get_param('export') === 'csv') {
    $rows = q('SELECT * FROM ' . t('leads') . $where . ' ORDER BY id DESC', $params)->fetchAll();
    audit('richieste_esportate', count($rows) . ' righe');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="richieste-chimangia-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    $cell = static function (string $v): string {
        // Evita che Excel interpreti i valori come formule.
        return preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;
    };
    fputcsv($out, ['ID', 'Data', 'Stato', 'Nome', 'Attività', 'Email', 'Telefono', 'Clienti', 'Messaggio', 'Note interne', 'Consenso privacy'], ';', '"', '');
    foreach ($rows as $r) {
        fputcsv($out, array_map($cell, [
            (string) $r['id'], data_it($r['created_at']), lead_statuses()[$r['status']] ?? $r['status'], $r['name'], $r['business'],
            $r['email'], $r['phone'], $r['clients'], $r['message'], $r['notes'], data_it($r['consent_at']),
        ]), ';', '"', '');
    }
    fclose($out);
    exit;
}

$perPage = 25;
$total = (int) q_value('SELECT COUNT(*) FROM ' . t('leads') . $where, $params);
$pages = max(1, (int) ceil($total / $perPage));
$page = min($pages, max(1, (int) get_param('pagina', '1')));
$rows = q('SELECT id, name, business, email, phone, clients, status, created_at FROM ' . t('leads') . $where
    . ' ORDER BY id DESC LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage), $params)->fetchAll();
$qs = static fn (array $extra) => http_build_query(array_filter(array_merge($filters, $extra), static fn ($v) => $v !== '' && $v !== null));

admin_header('Richieste', 'richieste.php');
?>
<form method="get" action="richieste.php" class="filters panel" role="search" aria-label="Filtra le richieste">
  <?= field('q', 'Cerca', $filters['q'], ['type' => 'search', 'hint' => 'Nome, attività, email, telefono o messaggio']) ?>
  <?= field('stato', 'Stato', $filters['stato'], ['type' => 'select', 'options' => ['' => 'Tutti'] + lead_statuses()]) ?>
  <?= field('dal', 'Dal', $filters['dal'], ['type' => 'date']) ?>
  <?= field('al', 'Al', $filters['al'], ['type' => 'date']) ?>
  <div class="actions-bar">
    <button type="submit" class="btn btn-dark">Filtra</button>
    <a class="btn btn-line" href="richieste.php">Azzera</a>
    <a class="btn btn-line" href="richieste.php?<?= e($qs(['export' => 'csv'])) ?>">Esporta CSV</a>
  </div>
</form>

<p aria-live="polite"><?= $total === 1 ? '1 richiesta trovata.' : (int) $total . ' richieste trovate.' ?></p>

<?php if ($rows): ?>
<div class="table-wrap"><table>
  <caption class="sr-only">Richieste dal modulo contatti</caption>
  <thead><tr><th scope="col">Data</th><th scope="col">Nome</th><th scope="col">Attività</th><th scope="col">Contatti</th><th scope="col">Clienti</th><th scope="col">Stato</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><?= e(data_it($r['created_at'])) ?></td>
      <td><a href="richiesta.php?id=<?= (int) $r['id'] ?>"><?= e($r['name']) ?></a></td>
      <td><?= e($r['business']) ?></td>
      <td><a href="mailto:<?= e($r['email']) ?>"><?= e($r['email']) ?></a><?= $r['phone'] !== '' ? '<br>' . e($r['phone']) : '' ?></td>
      <td><?= e($r['clients']) ?></td>
      <td><span class="status status-<?= e($r['status']) ?>"><?= e(lead_statuses()[$r['status']] ?? $r['status']) ?></span></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php if ($pages > 1): ?>
  <nav class="actions-bar" aria-label="Pagine">
    <?php if ($page > 1): ?><a class="btn btn-line btn-small" href="richieste.php?<?= e($qs(['pagina' => $page - 1])) ?>">← Precedenti</a><?php endif; ?>
    <span>Pagina <?= $page ?> di <?= $pages ?></span>
    <?php if ($page < $pages): ?><a class="btn btn-line btn-small" href="richieste.php?<?= e($qs(['pagina' => $page + 1])) ?>">Successive →</a><?php endif; ?>
  </nav>
<?php endif; ?>
<?php else: ?>
  <p>Nessuna richiesta corrisponde ai filtri.</p>
<?php endif; ?>
<p class="hint">Le richieste con stato "Chiusa" vengono cancellate in automatico dopo <?= (int) setting('retention_months', 12) ?> mesi (impostabile dall'amministratore).</p>
<?php
admin_footer();
