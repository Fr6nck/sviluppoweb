<?php
/** Super admin · Backup: copie complete del database, automatiche e a richiesta. */
declare(strict_types=1);

require dirname(__DIR__) . '/app/platform/core.php';
require_once CM_APP . '/platform/maint.php';
pf_boot('super', '../');
require __DIR__ . '/_guard.php';

$download = get_param('scarica');
if ($download !== '') {
    $path = backup_path($download);   // solo nomi di backup validi, dentro la cartella dei backup
    if ($path === null) {
        pf_flash('error', 'Questo backup non esiste più.');
        redirect('backup.php');
    }
    pf_event(null, 'backup_scaricato', basename($path));
    audit('piattaforma_backup_scaricato', basename($path));
    super_send_file($path, basename($path), str_ends_with($path, '.gz') ? 'application/gzip' : 'application/sql');
}

if (is_post()) {
    pf_csrf_check();
    switch (post('azione')) {
        case 'crea':
            try {
                $name = backup_create('manuale');
                pf_event(null, 'backup_creato', $name);
                audit('piattaforma_backup_creato', $name);
                pf_flash('ok', 'Backup creato: ' . $name . '.');
            } catch (Throwable $e) {
                log_message('Backup manuale non riuscito: ' . $e->getMessage());
                pf_flash('error', 'Backup non riuscito: ' . $e->getMessage());
            }
            redirect('backup.php');
            // no break
        case 'elimina':
            $path = backup_path(post('nome'));
            if ($path !== null && @unlink($path)) {
                pf_event(null, 'backup_eliminato', basename($path));
                audit('piattaforma_backup_eliminato', basename($path));
                pf_flash('ok', 'Backup eliminato.');
            } else {
                pf_flash('error', 'Backup non trovato.');
            }
            redirect('backup.php');
            // no break
        case 'impostazioni':
            $keep = (int) post('keep');
            if ($keep < 1 || $keep > 60) {
                pf_flash('error', 'Puoi tenere da 1 a 60 backup automatici.');
                redirect('backup.php#impostazioni');
            }
            $auto = post('auto') === '1';
            setting_set('pf_backup_auto', $auto);
            setting_set('pf_backup_keep', $keep);
            pf_event(null, 'backup_impostazioni', ($auto ? 'automatico ogni giorno' : 'automatico spento') . ' · ne tengo ' . $keep);
            pf_flash('ok', 'Impostazioni dei backup salvate.');
            redirect('backup.php#impostazioni');
            // no break
        default:
            redirect('backup.php');
    }
}

$list = backup_list();
$auto = (bool) setting('pf_backup_auto', true);
$keep = (int) setting('pf_backup_keep', 7);
$sum = array_sum(array_column($list, 'size'));

ui_super_start('Backup del *database*', 'backup.php', [
    'eyebrow' => $list ? 'Ultimo: ' . date('d/m/Y H:i', $list[0]['time']) : 'Ancora nessun backup',
    'lead' => 'Ogni backup contiene tutto: sito, locali, ospiti, menù e prenotazioni. I file restano nella cartella dati privata, mai raggiungibile dal web.',
    'actions' => ui_post_btn('backup.php', ['azione' => 'crea'], icon('database', 18) . 'Crea un backup adesso', 'btn btn-dark'),
]);
?>
<div class="grid-2">
  <section class="card card-white">
    <div class="card-head"><h2>Backup disponibili</h2><p><?= count($list) ?> <?= count($list) === 1 ? 'file' : 'file' ?> · <?= e(bytes_it((int) $sum)) ?> · database ora <?= e(bytes_it(db_size())) ?></p></div>
    <?php if (!$list): ?>
      <?= ui_empty('Nessun backup. Creane uno adesso: ci vogliono pochi secondi.', 'database') ?>
    <?php else: ?>
    <div class="table-wrap">
      <table class="table stack-sm">
        <thead><tr><th scope="col">Data</th><th scope="col">Tipo</th><th scope="col" class="num">Dimensione</th><th scope="col"><span class="sr-only">Azioni</span></th></tr></thead>
        <tbody>
        <?php foreach ($list as $b): ?>
          <tr>
            <td data-th="Data"><strong><?= e(date('d/m/Y H:i', $b['time'])) ?></strong><span class="sub"><?= e($b['name']) ?></span></td>
            <td data-th="Tipo"><?= $b['auto'] ? ui_tag('Automatico', 'soft') : ui_tag('Manuale', 'zaff') ?></td>
            <td data-th="Dimensione" class="num"><?= e(bytes_it($b['size'])) ?></td>
            <td class="actions">
              <a class="btn btn-small btn-line" href="backup.php?scarica=<?= e(rawurlencode($b['name'])) ?>"><?= icon('download', 16) ?>Scarica</a>
              <?= ui_post_btn('backup.php', ['azione' => 'elimina', 'nome' => $b['name']], icon('trash', 16) . '<span class="sr-only">Elimina il backup del ' . e(date('d/m/Y H:i', $b['time'])) . '</span>', 'btn btn-small btn-danger', 'Eliminare il backup del ' . date('d/m/Y H:i', $b['time']) . '?') ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </section>

  <div class="stack">
    <section class="card card-cream" id="impostazioni">
      <div class="card-head"><h2>Backup automatico</h2><span><?= $auto ? ui_tag('Ogni giorno', 'diet') : ui_tag('Spento', 'soft') ?></span></div>
      <form method="post" action="backup.php" class="form" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="azione" value="impostazioni">
        <?= ui_switch('auto', 'Un backup al giorno', $auto, 'Parte con la prima visita della giornata, oppure dal cron.') ?>
        <?= ui_field('keep', 'Quanti backup automatici tenere', (string) $keep, ['type' => 'number', 'required' => true, 'attrs' => 'min="1" max="60" step="1" inputmode="numeric"', 'hint' => 'I più vecchi vengono cancellati. Quelli manuali restano finché non li elimini tu.']) ?>
        <div class="row"><button type="submit" class="btn btn-dark">Salva</button></div>
      </form>
    </section>
    <section class="card">
      <div class="card-head"><h2>Solo i contenuti del sito?</h2></div>
      <p class="small muted">Testi, prezzi e domande frequenti del sito pubblico hanno il loro backup in formato JSON, con il ripristino dal pannello.</p>
      <div class="row actions-row"><a class="btn btn-small btn-line" href="../admin/backup.php"><?= icon('globe', 16) ?>Backup dei contenuti</a></div>
    </section>
  </div>
</div>

<section class="card card-cream">
  <div class="card-head"><h2>Come si ripristina</h2><p>Serve solo dopo un guasto o un errore grave</p></div>
  <div class="steps">
    <div class="step"><span class="step-n" aria-hidden="true">1</span><h3>Scarica il file</h3><p>Da questa pagina scarica il backup che ti serve e, se finisce con .gz, lascialo così: phpMyAdmin lo legge compresso.</p></div>
    <div class="step"><span class="step-n" aria-hidden="true">2</span><h3>Metti in manutenzione</h3><p>Accendi la modalità manutenzione in «Sistema e manutenzione», così nessuno scrive dati mentre lavori.</p></div>
    <div class="step"><span class="step-n" aria-hidden="true">3</span><h3>Importa da phpMyAdmin</h3><p>Nel pannello dell'hosting apri phpMyAdmin, scegli il database del sito, poi «Importa» e carica il file. Le tabelle vengono ricreate com'erano.</p></div>
    <div class="step"><span class="step-n" aria-hidden="true">4</span><h3>Riapri</h3><p>Controlla che il pannello si apra, poi spegni la manutenzione. Le foto in uploads/ non sono nel backup: copiale a parte via FTP.</p></div>
  </div>
</section>
<?php
ui_shell_end();
