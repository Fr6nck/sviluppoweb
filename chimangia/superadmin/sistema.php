<?php
/**
 * Super admin · Sistema e manutenzione: stato dell'installazione, modalità manutenzione,
 * lavori periodici e cron, coda delle email, registro degli errori.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/app/platform/core.php';
require_once CM_APP . '/platform/maint.php';
pf_boot('super', '../');
require __DIR__ . '/_guard.php';

$logFile = error_log_file();

if (get_param('scarica') === 'log') {
    if (!is_file($logFile) || filesize($logFile) === 0) {
        pf_flash('info', 'Il registro degli errori è vuoto.');
        redirect('sistema.php#errori');
    }
    audit('piattaforma_log_scaricato', '');
    super_send_file($logFile, 'chimangia-errori-' . date('Ymd-Hi') . '.log', 'text/plain; charset=utf-8');
}

if (is_post()) {
    pf_csrf_check();
    switch (post('azione')) {
        case 'manutenzione':
            $on = post('on') === '1';
            setting_set('pf_maintenance', ['on' => $on, 'text' => clean_text(post('text'), 300)]);
            pf_event(null, $on ? 'manutenzione_attivata' : 'manutenzione_terminata', clean_text(post('text'), 300));
            audit('piattaforma_manutenzione', $on ? 'attivata' : 'terminata');
            pf_flash($on ? 'warn' : 'ok', $on ? 'Manutenzione attiva: locali e ospiti vedono l\'avviso. Ricordati di spegnerla.' : 'Manutenzione terminata: la piattaforma è di nuovo aperta.');
            redirect('sistema.php');
            // no break
        case 'lavori':
            pf_event(null, 'lavori_periodici', 'avviati a mano');
            pf_flash('ok', 'Lavori periodici avviati: menù chiusi all\'orario limite, email in coda, pulizie e backup del giorno.');
            // Prima la risposta, poi i lavori: pf_tick chiude la sessione e, dove può, la richiesta.
            header('Location: sistema.php#lavori', true, 303);
            pf_tick(true);
            exit;

        case 'coda_riprova':
            $n = q('UPDATE ' . t('outbox') . " SET attempts = 0, error = '' WHERE sent_at IS NULL AND attempts >= 3")->rowCount();
            pf_event(null, 'email_riprova', $n . ' messaggi rimessi in coda');
            pf_flash('ok', $n === 0 ? 'Non c\'erano email da riprovare.' : $n . ($n === 1 ? ' email rimessa in coda.' : ' email rimesse in coda.'));
            redirect('sistema.php#email');
            // no break
        case 'coda_svuota':
            $n = q('DELETE FROM ' . t('outbox') . ' WHERE sent_at IS NULL')->rowCount();
            pf_event(null, 'email_coda_svuotata', $n . ' messaggi eliminati');
            audit('piattaforma_coda_svuotata', (string) $n);
            pf_flash('ok', $n === 0 ? 'La coda era già vuota.' : 'Coda svuotata: ' . $n . ($n === 1 ? ' email eliminata.' : ' email eliminate.'));
            redirect('sistema.php#email');
            // no break
        case 'log_svuota':
            if (is_file($logFile) && is_writable($logFile)) {
                file_put_contents($logFile, '');
                audit('piattaforma_log_svuotato', '');
                pf_flash('ok', 'Registro degli errori svuotato.');
            } else {
                pf_flash('info', 'Il registro degli errori è già vuoto.');
            }
            redirect('sistema.php#errori');
            // no break
        case 'cron_chiave':
            setting_set('pf_cron_key', random_token(24));
            audit('piattaforma_cron_chiave', 'rigenerata');
            pf_flash('ok', 'Chiave rigenerata: aggiorna l\'indirizzo nel cron dell\'hosting, quello vecchio non funziona più.');
            redirect('sistema.php#lavori');
            // no break
        default:
            redirect('sistema.php');
    }
}

$cronKey = (string) setting('pf_cron_key', '');
if ($cronKey === '') {
    $cronKey = random_token(24);
    setting_set('pf_cron_key', $cronKey);
}
$cronUrl = site_url('cron.php?k=' . $cronKey);
$m = pf_maintenance();
$checks = system_checks();
$bad = count(array_filter($checks, static fn ($c) => $c[2] === 'bad'));
$warn = count(array_filter($checks, static fn ($c) => $c[2] === 'warn'));
$tick = (int) setting('pf_tick_at', 0);
$queue = q_one(
    'SELECT COALESCE(SUM(sent_at IS NULL AND attempts < 3), 0) AS pending, COALESCE(SUM(sent_at IS NULL AND attempts >= 3), 0) AS failed,
            COALESCE(SUM(sent_at IS NOT NULL AND sent_at > ?), 0) AS sent FROM ' . t('outbox'),
    [date('Y-m-d H:i:s', time() - 86400)]
);
$failed = q(
    'SELECT o.*, v.name AS venue FROM ' . t('outbox') . ' o LEFT JOIN ' . t('venues') . " v ON v.id = o.venue_id
     WHERE o.sent_at IS NULL AND o.error <> '' ORDER BY o.id DESC LIMIT 10"
)->fetchAll();
$tail = log_tail($logFile, 120);

ui_super_start('Sistema e *manutenzione*', 'sistema.php', [
    'eyebrow' => $bad > 0 ? $bad . ($bad === 1 ? ' problema da risolvere' : ' problemi da risolvere') : ($warn > 0 ? $warn . ($warn === 1 ? ' punto da controllare' : ' punti da controllare') : 'Tutto in ordine'),
]);
?>
<div class="grid-2">
  <section class="card card-white">
    <div class="card-head"><h2>Stato del sistema</h2><p>ChiMangia <?= e(CM_VERSION) ?></p></div>
    <div class="list-line">
      <?php foreach ($checks as [$label, $value, $state]): ?>
      <div class="row-between"><span><i class="status-dot<?= $state === 'ok' ? '' : ' ' . e($state) ?>" aria-hidden="true"></i><?= e($label) ?><span class="sr-only"> (<?= $state === 'ok' ? 'a posto' : ($state === 'warn' ? 'da controllare' : 'problema') ?>)</span></span><span class="small muted"><?= e($value) ?></span></div>
      <?php endforeach; ?>
    </div>
  </section>

  <div class="stack">
    <section class="card <?= $m['on'] ? 'card-zaff' : 'card-cream' ?>">
      <div class="card-head"><h2>Modalità manutenzione</h2><span><?= $m['on'] ? ui_tag('Attiva', 'diet') : ui_tag('Spenta', 'soft') ?></span></div>
      <form method="post" action="sistema.php" class="form">
        <?= csrf_field() ?>
        <input type="hidden" name="azione" value="manutenzione">
        <?= ui_switch('on', 'Piattaforma in manutenzione', $m['on'], 'Locali e ospiti vedono un avviso al posto delle pagine. Il sito pubblico e questo pannello restano aperti.') ?>
        <?= ui_field('text', 'Messaggio mostrato', $m['text'], ['type' => 'textarea', 'rows' => 3, 'attrs' => 'maxlength="300"', 'placeholder' => 'Stiamo aggiornando ChiMangia. Riprova tra qualche minuto: prenotazioni e dati sono al sicuro.', 'hint' => 'Lascia vuoto per usare il messaggio standard.']) ?>
        <div class="row"><button type="submit" class="btn btn-dark">Salva</button></div>
      </form>
    </section>

    <section class="card" id="lavori">
      <div class="card-head"><h2>Lavori periodici</h2><p><?= $tick ? 'Ultima esecuzione: ' . e(date('d/m/Y H:i', $tick)) : 'Mai eseguiti' ?></p></div>
      <p class="small muted">Chiudono i menù all'orario limite, spediscono le email in coda e una volta al giorno fanno pulizia e backup. Partono da soli a ogni visita; con un cron girano anche quando nessuno apre il pannello.</p>
      <div class="row actions-row"><?= ui_post_btn('sistema.php', ['azione' => 'lavori'], icon('refresh', 16) . 'Esegui adesso', 'btn btn-small btn-dark') ?></div>
      <p class="label">Indirizzo per il cron</p>
      <div class="link-box"><code id="cron-url"><?= e($cronUrl) ?></code><button type="button" class="btn btn-small btn-line" data-copy="#cron-url">Copia</button></div>
      <p class="label">Riga da incollare nel cron dell'hosting (ogni 5 minuti)</p>
      <code class="code-line" id="cron-line">*/5 * * * * wget -q -O /dev/null "<?= e($cronUrl) ?>"</code>
      <p class="hint">Senza wget: <code>*/5 * * * * curl -fsS "<?= e($cronUrl) ?>" &gt; /dev/null</code></p>
      <div class="row actions-row">
        <button type="button" class="btn btn-small btn-line" data-copy="#cron-line">Copia la riga</button>
        <?= ui_post_btn('sistema.php', ['azione' => 'cron_chiave'], 'Rigenera la chiave', 'btn btn-small btn-line', 'Rigenerare la chiave? L\'indirizzo attuale smetterà di funzionare: andrà aggiornato nel cron.') ?>
      </div>
    </section>
  </div>
</div>

<section class="card card-white" id="email">
  <div class="card-head"><h2>Email in coda</h2><p><?= (int) $queue['pending'] ?> da inviare · <?= (int) $queue['failed'] ?> non riuscite · <?= (int) $queue['sent'] ?> inviate nelle ultime 24 ore</p></div>
  <?php if (!$failed): ?>
    <?= ui_empty('Nessuna email con errori.', 'check') ?>
  <?php else: ?>
  <div class="table-wrap">
    <table class="table stack-sm">
      <thead><tr><th scope="col">Quando</th><th scope="col">Destinatario</th><th scope="col">Oggetto</th><th scope="col" class="num">Tentativi</th><th scope="col">Errore</th></tr></thead>
      <tbody>
      <?php foreach ($failed as $o): ?>
        <tr>
          <td data-th="Quando" class="nowrap"><?= e(data_it((string) $o['created_at'])) ?></td>
          <td data-th="Destinatario"><?= e((string) $o['to_email']) ?><span class="sub"><?= e((string) ($o['venue'] ?? 'Piattaforma')) ?></span></td>
          <td data-th="Oggetto"><?= e((string) $o['subject']) ?></td>
          <td data-th="Tentativi" class="num"><?= (int) $o['attempts'] >= 3 ? ui_tag((int) $o['attempts'] . ' su 3', 'err') : (int) $o['attempts'] . ' su 3' ?></td>
          <td data-th="Errore"><?= e((string) $o['error']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
  <div class="row actions-row">
    <?= ui_post_btn('sistema.php', ['azione' => 'coda_riprova'], icon('refresh', 16) . 'Riprova le non riuscite') ?>
    <?= ui_post_btn('sistema.php', ['azione' => 'coda_svuota'], icon('trash', 16) . 'Svuota la coda', 'btn btn-small btn-danger', 'Eliminare tutte le email non ancora inviate? Non verranno più spedite.') ?>
    <a class="btn btn-small btn-line" href="../admin/impostazioni.php"><?= icon('settings', 16) ?>Configura l'invio</a>
  </div>
</section>

<section class="card card-white" id="errori">
  <div class="card-head"><h2>Registro degli errori</h2><p><?= is_file($logFile) ? e(bytes_it((int) filesize($logFile))) . ' · ultime 120 righe' : 'File non ancora creato' ?></p></div>
  <?php if ($tail === ''): ?>
    <?= ui_empty('Nessun errore registrato.', 'check') ?>
  <?php else: ?>
    <pre class="log" tabindex="0" aria-label="Ultime righe del registro degli errori"><?= e($tail) ?></pre>
    <div class="row actions-row">
      <a class="btn btn-small btn-line" href="sistema.php?scarica=log"><?= icon('download', 16) ?>Scarica il file</a>
      <?= ui_post_btn('sistema.php', ['azione' => 'log_svuota'], icon('trash', 16) . 'Svuota il registro', 'btn btn-small btn-danger', 'Svuotare il registro degli errori?') ?>
    </div>
  <?php endif; ?>
</section>
<?php
ui_shell_end();
