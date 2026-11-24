<?php
/** Dettaglio di una richiesta: dati, stato, note interne, cronologia, eliminazione. */
declare(strict_types=1);

require dirname(__DIR__) . '/app/admin.php';

$user = require_role('editor');
$id = (int) (is_post() ? post('id') : get_param('id'));
$lead = lead_get($id);
if (!$lead) {
    http_response_code(404);
    admin_message_page('Richiesta non trovata', 'La richiesta non esiste o è stata eliminata.');
}

if (is_post()) {
    csrf_check();
    $action = post('azione');
    if ($action === 'elimina') {
        lead_delete($id);
        audit('richiesta_eliminata', '#' . $id . ' ' . $lead['name']);
        flash('ok', 'Richiesta eliminata.');
        redirect('richieste.php');
    }
    if ($action === 'salva') {
        $status = post('status');
        $notes = clean_lines(post('notes'), 5000);
        $changed = isset(lead_statuses()[$status]) && lead_set_status($id, $status, (int) $user['id'], clean_text(post('status_note'), 500));
        q('UPDATE ' . t('leads') . ' SET notes = ?, updated_at = ? WHERE id = ?', [$notes, now(), $id]);
        if ($changed) {
            audit('richiesta_stato', '#' . $id . ': ' . (lead_statuses()[$lead['status']] ?? '') . ' → ' . lead_statuses()[$status]);
        }
        flash('ok', $changed ? 'Stato e note aggiornati.' : 'Note salvate.');
        redirect('richiesta.php?id=' . $id);
    }
}

$history = lead_history($id);
admin_header('Richiesta di ' . $lead['name'], 'richieste.php');
?>
<p><a href="richieste.php">← Torna alle richieste</a></p>
<section class="panel" aria-labelledby="h-dati">
  <h2 id="h-dati">Dati inviati</h2>
  <div class="table-wrap"><table>
    <tbody>
      <tr><th scope="row">Ricevuta il</th><td><?= e(data_it($lead['created_at'])) ?></td></tr>
      <tr><th scope="row">Nome</th><td><?= e($lead['name']) ?></td></tr>
      <tr><th scope="row">Attività</th><td><?= e($lead['business'] ?: '—') ?></td></tr>
      <tr><th scope="row">Email</th><td><a href="mailto:<?= e($lead['email']) ?>"><?= e($lead['email']) ?></a></td></tr>
      <tr><th scope="row">Telefono</th><td><?= $lead['phone'] !== '' ? '<a href="tel:' . e(preg_replace('/[^0-9+]/', '', $lead['phone'])) . '">' . e($lead['phone']) . '</a>' : '—' ?></td></tr>
      <tr><th scope="row">Clienti (indicativo)</th><td><?= e($lead['clients'] ?: '—') ?></td></tr>
      <tr><th scope="row">Messaggio</th><td><?= nl2br(e($lead['message'] ?: '—')) ?></td></tr>
      <tr><th scope="row">Consenso privacy</th><td>Dato il <?= e(data_it($lead['consent_at'])) ?></td></tr>
    </tbody>
  </table></div>
</section>

<form method="post" action="richiesta.php" class="form panel" novalidate data-dirty-check>
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $id ?>">
  <input type="hidden" name="azione" value="salva">
  <h2>Gestione</h2>
  <div class="grid-2">
    <?= field('status', 'Stato', $lead['status'], ['type' => 'select', 'options' => lead_statuses()]) ?>
    <?= field('status_note', 'Nota sul cambio di stato (facoltativa)', '', ['hint' => 'Finisce nella cronologia, es. "Chiamato, richiamare lunedì".']) ?>
  </div>
  <?= field('notes', 'Note interne', $lead['notes'], ['type' => 'textarea', 'rows' => 5, 'hint' => 'Visibili solo nel pannello.']) ?>
  <div class="actions-bar"><button type="submit" class="btn btn-dark">Salva</button></div>
</form>

<section class="panel" aria-labelledby="h-crono">
  <h2 id="h-crono">Cronologia degli stati</h2>
  <?php if (!$history): ?>
    <p>Nessun cambio di stato.</p>
  <?php else: ?>
    <ol class="timeline">
      <?php foreach ($history as $h): ?>
        <li><strong><?= e(data_it($h['created_at'])) ?></strong> —
          <?= $h['from_status'] !== '' ? e(lead_statuses()[$h['from_status']] ?? $h['from_status']) . ' → ' : '' ?><?= e(lead_statuses()[$h['to_status']] ?? $h['to_status']) ?>
          <?= $h['user_name'] ? ' · ' . e($h['user_name']) : '' ?>
          <?= $h['note'] !== '' ? '<br><span class="hint">' . e($h['note']) . '</span>' : '' ?></li>
      <?php endforeach; ?>
    </ol>
  <?php endif; ?>
</section>

<form method="post" action="richiesta.php" class="panel">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $id ?>">
  <h2>Elimina</h2>
  <p>Cancella definitivamente la richiesta e la sua cronologia (ad esempio su domanda dell'interessato).</p>
  <button type="submit" name="azione" value="elimina" class="btn btn-danger" data-confirm="Eliminare definitivamente questa richiesta?">Elimina la richiesta</button>
</form>
<?php
admin_footer();
