<?php
/** Backup JSON di contenuti e impostazioni (senza password) e ricaricamento. Solo Amministratore. */
declare(strict_types=1);

require dirname(__DIR__) . '/app/admin.php';

$me = require_role('admin');

/** Impostazioni escluse dal backup: segreti e dati di servizio. */
const BACKUP_SKIP_SETTINGS = ['smtp_pass', 'preview_token', 'db_version', 'retention_last_run', 'last_publish_at'];

if (get_param('scarica') === '1') {
    $settings = [];
    foreach (q('SELECT name, value FROM ' . t('settings'))->fetchAll() as $r) {
        if (!in_array($r['name'], BACKUP_SKIP_SETTINGS, true)) {
            $settings[$r['name']] = json_decode((string) $r['value'], true);
        }
    }
    $media = q('SELECT id, file, webp, alt, width, height FROM ' . t('media') . ' ORDER BY id')->fetchAll();
    $backup = [
        'format' => 'chimangia-backup',
        'version' => CM_VERSION,
        'created_at' => date('c'),
        'site_url' => site_url(),
        'content' => ['published' => content_all('published'), 'draft' => content_all('draft')],
        'settings' => $settings,
        'media' => $media,
    ];
    audit('backup_scaricato', count($settings) . ' impostazioni');
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="chimangia-backup-' . date('Y-m-d-Hi') . '.json"');
    echo json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$error = '';
if (is_post()) {
    csrf_check();
    $f = $_FILES['backup'] ?? null;
    $raw = ($f && (int) $f['error'] === UPLOAD_ERR_OK && is_uploaded_file((string) $f['tmp_name']) && (int) $f['size'] < 10 * 1024 * 1024)
        ? (string) file_get_contents((string) $f['tmp_name']) : '';
    $data = $raw !== '' ? json_decode($raw, true) : null;
    if (!is_array($data) || ($data['format'] ?? '') !== 'chimangia-backup' || !is_array($data['content']['published'] ?? null)) {
        $error = 'Il file non è un backup valido di ChiMangia.';
    } else {
        $defaults = default_content();
        // Copia di sicurezza dello stato attuale nello storico.
        q('INSERT INTO ' . t('versions') . ' (user_id, note, data, created_at) VALUES (?, ?, ?, ?)',
            [$me['id'], 'Copia automatica prima di ricaricare un backup', content_encode(content_all('published')), now()]);
        $pdo = db();
        $pdo->beginTransaction();
        foreach ($defaults as $area => $def) {
            $pub = is_array($data['content']['published'][$area] ?? null) ? array_replace($def, $data['content']['published'][$area]) : null;
            $dr = is_array($data['content']['draft'][$area] ?? null) ? array_replace($def, $data['content']['draft'][$area]) : $pub;
            if ($pub === null) {
                continue;
            }
            q('UPDATE ' . t('content') . ' SET draft = ?, published = ?, draft_updated_at = ?, draft_updated_by = ?, published_at = ?, published_by = ? WHERE area = ?',
                [content_encode($dr), content_encode($pub), now(), $me['id'], now(), $me['id'], $area]);
        }
        $known = array_keys(default_settings());
        foreach ((array) ($data['settings'] ?? []) as $name => $value) {
            if (in_array($name, $known, true) && !in_array($name, BACKUP_SKIP_SETTINGS, true) && (is_scalar($value) || $value === null)) {
                setting_set((string) $name, $value);
            }
        }
        $pdo->commit();
        q('INSERT INTO ' . t('versions') . ' (user_id, note, data, created_at) VALUES (?, ?, ?, ?)',
            [$me['id'], 'Backup ricaricato (' . clean_text((string) ($data['created_at'] ?? ''), 40) . ')', content_encode(content_all('published')), now()]);
        setting_set('last_publish_at', now());
        if (setting('maintenance', false)) {
            write_file_atomic(maintenance_flag(), (string) setting('maintenance_message', ''));
        } elseif (is_file(maintenance_flag())) {
            @unlink(maintenance_flag());
        }
        site_rebuild();
        audit('backup_caricato', 'Backup del ' . clean_text((string) ($data['created_at'] ?? ''), 40));
        $missing = 0;
        foreach ((array) ($data['media'] ?? []) as $m) {
            if (!media_get((int) ($m['id'] ?? 0))) {
                $missing++;
            }
        }
        flash('ok', 'Backup ricaricato e pubblicato. Lo stato precedente è salvato nello storico.'
            . ($missing ? ' Attenzione: ' . $missing . ' immagini citate nel backup non sono presenti in questa installazione (vanno ricaricate dalla pagina Media).' : ''));
        redirect('backup.php');
    }
}

admin_header('Backup', 'backup.php');
if ($error): ?><div class="notice notice-error" role="alert"><?= e($error) ?></div><?php endif; ?>
<section class="panel" aria-labelledby="h-down">
  <h2 id="h-down">Scarica un backup</h2>
  <p>Un file JSON con tutti i contenuti (versione pubblicata e bozza) e le impostazioni. Sono escluse le password (utenti e SMTP) e il link di anteprima. Le immagini caricate non sono nel file: per salvarle copia la cartella <code>uploads/</code> via FTP.</p>
  <p><a class="btn btn-dark" href="backup.php?scarica=1">Scarica il backup</a></p>
</section>
<form method="post" action="backup.php" enctype="multipart/form-data" class="form panel" novalidate>
  <?= csrf_field() ?>
  <h2>Ricarica un backup</h2>
  <p>Sostituisce contenuti e impostazioni con quelli del file e pubblica subito. Prima viene salvata una copia dello stato attuale nello storico, così puoi tornare indietro.</p>
  <div class="field">
    <label for="f-backup">File di backup (.json)</label>
    <input type="file" id="f-backup" name="backup" accept="application/json,.json" required>
  </div>
  <div class="actions-bar"><button type="submit" class="btn btn-danger" data-confirm="Sostituire contenuti e impostazioni con quelli del backup?">Ricarica il backup</button></div>
</form>
<?php
admin_footer();
