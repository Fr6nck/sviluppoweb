<?php
/** Storico delle pubblicazioni: confronto essenziale e ripristino come nuova bozza. */
declare(strict_types=1);

require dirname(__DIR__) . '/app/admin.php';

$user = require_role('editor');

if (is_post()) {
    csrf_check();
    $vid = (int) post('id');
    if (post('azione') === 'ripristina' && content_restore_to_draft($vid, (int) $user['id'])) {
        audit('ripristino_in_bozza', 'Versione #' . $vid);
        flash('ok', 'La versione #' . $vid . ' è ora la bozza. Controllala con l\'anteprima e premi Pubblica quando sei pronto: il sito non è cambiato.');
        redirect('index.php');
    }
    flash('error', 'Versione non trovata.');
    redirect('storico.php');
}

/** Elenco piatto "percorso => testo" per il confronto. */
function flatten_content(array $data, string $prefix = ''): array
{
    $out = [];
    foreach ($data as $k => $v) {
        if ($k === 'id') {
            continue;
        }
        $path = $prefix === '' ? (string) $k : $prefix . ' › ' . (is_int($k) ? '#' . ($k + 1) : $k);
        if (is_array($v)) {
            $out += flatten_content($v, $path);
        } else {
            $out[$path] = is_bool($v) ? ($v ? 'sì' : 'no') : trim(strip_tags((string) $v));
        }
    }
    return $out;
}

function version_data(int $id): ?array
{
    $raw = q_value('SELECT data FROM ' . t('versions') . ' WHERE id = ?', [$id]);
    $d = is_string($raw) ? json_decode($raw, true) : null;
    return is_array($d) ? $d : null;
}

$compare = (int) get_param('confronta');
admin_header('Storico e ripristino', 'storico.php');

if ($compare > 0 && ($v = version_data($compare))):
    $current = content_all('published');
    $areas = content_areas();
    $a = flatten_content($v);
    $b = flatten_content($current);
    $changes = [];
    foreach (array_unique(array_merge(array_keys($a), array_keys($b))) as $k) {
        if (($a[$k] ?? null) !== ($b[$k] ?? null)) {
            $changes[$k] = [$a[$k] ?? '', $b[$k] ?? ''];
        }
    }
    ?>
  <p><a href="storico.php">← Torna allo storico</a></p>
  <h2>Versione #<?= $compare ?> confrontata con il sito attuale</h2>
  <?php if (!$changes): ?>
    <p>Nessuna differenza: questa versione coincide con quella pubblicata ora.</p>
  <?php else: ?>
    <p><?= count($changes) ?> campi diversi. <span class="old">Barrato</span>: testo della versione #<?= $compare ?>; <span class="new">evidenziato</span>: testo pubblicato ora.</p>
    <ul class="diff">
      <?php foreach (array_slice($changes, 0, 200, true) as $k => [$old, $new]):
          $first = strtok($k, ' ');
          $label = $areas[$first]['label'] ?? $first; ?>
        <li><strong><?= e($label . substr($k, strlen($first))) ?></strong><br>
          <span class="old"><?= e(str_limit($old, 300)) ?: '(vuoto)' ?></span><br>
          <span class="new"><?= e(str_limit($new, 300)) ?: '(vuoto)' ?></span></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
  <form method="post" action="storico.php" class="actions-bar">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $compare ?>">
    <button type="submit" name="azione" value="ripristina" class="btn btn-dark" data-confirm="Ripristinare questa versione come bozza? Le bozze attuali non pubblicate verranno sostituite.">Ripristina come bozza</button>
  </form>
<?php else:
    $rows = q('SELECT v.id, v.note, v.created_at, u.name FROM ' . t('versions') . ' v LEFT JOIN ' . t('users') . ' u ON u.id = v.user_id ORDER BY v.id DESC LIMIT 100')->fetchAll();
    ?>
  <p>Ogni pubblicazione salva una copia completa di tutti i contenuti. <strong>Ripristina</strong> trasforma la versione scelta in bozza: il sito cambia solo quando premi Pubblica.</p>
  <div class="table-wrap"><table>
    <thead><tr><th scope="col">Versione</th><th scope="col">Data</th><th scope="col">Autore</th><th scope="col">Nota</th><th scope="col">Azioni</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $i => $r): ?>
      <tr>
        <td>#<?= (int) $r['id'] ?><?= $i === 0 ? ' <span class="status status-cliente">online</span>' : '' ?></td>
        <td><?= e(data_it($r['created_at'])) ?></td>
        <td><?= e($r['name'] ?? '—') ?></td>
        <td><?= e($r['note']) ?></td>
        <td>
          <a class="btn btn-line btn-small" href="storico.php?confronta=<?= (int) $r['id'] ?>">Confronta</a>
          <form method="post" action="storico.php" class="inline-form">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
            <button type="submit" name="azione" value="ripristina" class="btn btn-line btn-small" data-confirm="Ripristinare la versione #<?= (int) $r['id'] ?> come bozza?">Ripristina</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
<?php endif;
admin_footer();
