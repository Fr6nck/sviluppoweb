<?php
/**
 * Super admin · Funzioni e permessi: valori iniziali per i locali nuovi,
 * durata della prova, apertura delle registrazioni e avviso a tutti i locali.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/app/platform/core.php';
require_once CM_APP . '/platform/maint.php';
pf_boot('super', '../');
require __DIR__ . '/_guard.php';

if (is_post()) {
    pf_csrf_check();
    switch (post('azione')) {
        case 'generali':
            $days = (int) post('trial_days');
            if ($days < 1 || $days > 60) {
                pf_flash('error', 'La prova può durare da 1 a 60 giorni.');
                redirect('funzioni.php');
            }
            $open = post('signup_open') === '1';
            setting_set('pf_trial_days', $days);
            setting_set('pf_signup_open', $open);
            pf_event(null, 'impostazioni_piattaforma', 'prova di ' . $days . ' giorni · registrazioni ' . ($open ? 'aperte' : 'chiuse'));
            audit('piattaforma_impostazioni', 'prova ' . $days . ' giorni, registrazioni ' . ($open ? 'aperte' : 'chiuse'));
            pf_flash('ok', 'Impostazioni salvate.');
            redirect('funzioni.php');
            // no break
        case 'avviso':
            $text = clean_text(post('text'), 300);
            $until = post('until');
            if ($until !== '' && !valid_day($until)) {
                pf_flash('error', 'La data di fine dell\'avviso non è valida.');
                redirect('funzioni.php#avviso');
            }
            setting_set('pf_announcement', ['text' => $text, 'until' => $text !== '' ? $until : '']);
            pf_event(null, 'avviso_ai_locali', $text !== '' ? $text . ($until !== '' ? ' · fino al ' . data_it($until, false) : '') : 'avviso tolto');
            pf_flash('ok', $text !== '' ? 'Avviso pubblicato in tutti i pannelli dei locali.' : 'Avviso tolto.');
            redirect('funzioni.php#avviso');
            // no break
        case 'funzioni':
            $features = super_read_features();
            setting_set('pf_feature_defaults', $features);
            pf_event(null, 'funzioni_predefinite', implode(', ', array_keys(array_filter($features))));
            pf_flash('ok', 'Funzioni iniziali salvate: valgono per i locali creati da ora in poi.');
            redirect('funzioni.php#funzioni');
            // no break
        case 'permessi':
            setting_set('pf_perm_defaults', super_read_perms());
            pf_event(null, 'permessi_predefiniti', 'matrice dei ruoli');
            pf_flash('ok', 'Permessi iniziali salvati: valgono per i locali creati da ora in poi.');
            redirect('funzioni.php#permessi');
            // no break
        default:
            redirect('funzioni.php');
    }
}

$features = pf_feature_defaults();
$notice = setting('pf_announcement', []);
$notice = ['text' => (string) ($notice['text'] ?? ''), 'until' => (string) ($notice['until'] ?? '')];
$noticeLive = $notice['text'] !== '' && ($notice['until'] === '' || $notice['until'] >= today());

ui_super_start('Funzioni e *permessi*', 'funzioni.php', [
    'eyebrow' => 'Valori iniziali della piattaforma',
    'lead' => 'Quello che scegli qui vale per i locali nuovi. Per cambiare un locale già esistente apri la sua scheda da Clienti.',
]);
?>
<div class="grid-even">
  <section class="card card-white">
    <div class="card-head"><h2>Prova e registrazioni</h2></div>
    <form method="post" action="funzioni.php" class="form" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="azione" value="generali">
      <?= ui_field('trial_days', 'Durata della prova gratuita (giorni)', (string) (int) setting('pf_trial_days', PF_TRIAL_DAYS), ['type' => 'number', 'required' => true, 'attrs' => 'min="1" max="60" step="1" inputmode="numeric"', 'hint' => 'Da 1 a 60. Vale per le registrazioni da ora in poi.']) ?>
      <?= ui_switch('signup_open', 'Registrazioni aperte', (bool) setting('pf_signup_open', true), 'Se le chiudi, la pagina «Registrati» invita a scriverci e i locali li crei tu da Clienti.') ?>
      <div class="row"><button type="submit" class="btn btn-dark">Salva</button></div>
    </form>
  </section>

  <section class="card card-cream" id="avviso">
    <div class="card-head"><h2>Avviso a tutti i locali</h2><span><?= $noticeLive ? ui_tag('In mostra', 'zaff') : ui_tag('Nessun avviso', 'soft') ?></span></div>
    <form method="post" action="funzioni.php" class="form" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="azione" value="avviso">
      <?= ui_field('text', 'Testo dell\'avviso', $notice['text'], ['type' => 'textarea', 'rows' => 3, 'attrs' => 'maxlength="300"', 'hint' => 'Compare in alto in ogni pannello dei locali. Lascia vuoto per toglierlo.']) ?>
      <?= ui_field('until', 'Mostralo fino al', $notice['until'], ['type' => 'date', 'hint' => 'Facoltativo: senza data resta finché non lo togli.']) ?>
      <div class="row"><button type="submit" class="btn btn-dark">Pubblica l'avviso</button></div>
    </form>
  </section>
</div>

<section class="card card-white" id="funzioni">
  <div class="card-head"><h2>Funzioni accese nei locali nuovi</h2><p><?= count(array_filter($features)) ?> su <?= count($features) ?> accese</p></div>
  <form method="post" action="funzioni.php" class="form">
    <?= csrf_field() ?>
    <input type="hidden" name="azione" value="funzioni">
    <?= super_feature_switches($features) ?>
    <p class="hint">Il modulo Hotel si accende comunque quando chi si registra dichiara di avere le camere.</p>
    <div class="row"><button type="submit" class="btn btn-dark">Salva le funzioni iniziali</button></div>
  </form>
</section>

<section class="card card-white" id="permessi">
  <div class="card-head"><h2>Permessi iniziali dei ruoli</h2><p>Spunta una casella per dare il permesso</p></div>
  <form method="post" action="funzioni.php" class="form">
    <?= csrf_field() ?>
    <input type="hidden" name="azione" value="permessi">
    <?= super_perm_matrix(pf_default_perms()) ?>
    <p class="hint">Il titolare ha sempre tutti i permessi. Qui decidi da dove partono Amministrazione, Reception e Cucina.</p>
    <div class="row"><button type="submit" class="btn btn-dark">Salva i permessi iniziali</button></div>
  </form>
</section>
<?php
ui_shell_end();
