<?php
/** Registro delle azioni: accessi, pubblicazioni, modifiche a prezzi e utenti. Solo Amministratore. */
declare(strict_types=1);

require dirname(__DIR__) . '/app/admin.php';

require_role('admin');

$labels = [
    'accesso' => 'Accesso', 'accesso_fallito' => 'Accesso non riuscito', 'accesso_bloccato' => 'Accesso bloccato', 'uscita' => 'Uscita',
    'recupero_richiesto' => 'Recupero password richiesto', 'password_reimpostata' => 'Password reimpostata', 'profilo_modificato' => 'Profilo modificato',
    'pubblicazione' => 'Pubblicazione', 'bozza_salvata' => 'Bozza salvata', 'ripristino_in_bozza' => 'Ripristino in bozza', 'prezzi_modificati' => 'Prezzi modificati',
    'utente_creato' => 'Utente creato', 'utente_modificato' => 'Utente modificato', 'impostazioni' => 'Impostazioni modificate',
    'richiesta_stato' => 'Stato richiesta', 'richiesta_eliminata' => 'Richiesta eliminata', 'richieste_esportate' => 'Richieste esportate',
    'richieste_eliminate_automaticamente' => 'Pulizia automatica richieste', 'media_caricato' => 'Immagine caricata', 'media_eliminato' => 'Immagine eliminata',
    'backup_scaricato' => 'Backup scaricato', 'backup_caricato' => 'Backup ricaricato', 'installazione' => 'Installazione', 'email_prova' => 'Email di prova',
];
$type = get_param('tipo');
$where = '';
$params = [];
if (isset($labels[$type])) {
    $where = ' WHERE a.action = ?';
    $params[] = $type;
}
$rows = q('SELECT a.*, u.name FROM ' . t('audit_log') . ' a LEFT JOIN ' . t('users') . ' u ON u.id = a.user_id' . $where . ' ORDER BY a.id DESC LIMIT 300', $params)->fetchAll();

admin_header('Registro azioni', 'registro.php');
?>
<form method="get" action="registro.php" class="filters">
  <?= field('tipo', 'Tipo di azione', $type, ['type' => 'select', 'options' => ['' => 'Tutte'] + $labels]) ?>
  <div class="actions-bar"><button type="submit" class="btn btn-dark">Filtra</button></div>
</form>
<p class="hint">Ultime 300 azioni. Gli indirizzi IP sono conservati solo in forma cifrata (hash).</p>
<div class="table-wrap"><table>
  <thead><tr><th scope="col">Data</th><th scope="col">Utente</th><th scope="col">Azione</th><th scope="col">Dettagli</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr><td><?= e(data_it($r['created_at'])) ?></td><td><?= e($r['name'] ?? '—') ?></td>
      <td><?= e($labels[$r['action']] ?? $r['action']) ?></td><td><?= e(str_limit((string) $r['details'], 200)) ?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php
admin_footer();
