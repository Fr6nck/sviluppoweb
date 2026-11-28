<?php
/** Profilo dell'ospite: dati, società, diete e intolleranze, password, contatti del locale, uscita. */
declare(strict_types=1);

require dirname(__DIR__) . '/app/platform/core.php';
pf_boot('guest', '../');
require __DIR__ . '/_comuni.php';

$me = guest_require();
$venue = $me['venue'];
$id = (int) $me['id'];
$withCompany = feature('societa', $venue);
$dietList = array_values(array_map('strval', (array) $venue['settings']['diets']));
$company = $me['company_id'] ? (string) q_value('SELECT name FROM ' . t('companies') . ' WHERE id = ? AND venue_id = ?', [$me['company_id'], $venue['id']]) : '';
$error = '';
$form = '';

if (is_post()) {
    pf_csrf_check();
    $form = post('azione');
    if ($form === 'dati') {
        $first = clean_text(post('first_name'), 80);
        $last = clean_text(post('last_name'), 80);
        $email = mb_strtolower(post('email'));
        if ($first === '' || $last === '') {
            $error = 'Scrivi nome e cognome.';
        } elseif ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190)) {
            $error = 'L\'indirizzo email non è valido.';
        } else {
            $companyId = $me['company_id'] !== null ? (int) $me['company_id'] : null;
            if ($withCompany) {
                $name = clean_text(post('company'), 160);
                // Una società nuova scritta dall'ospite resta "da verificare" finché la reception non la controlla.
                $companyId = mb_strtolower($name) === mb_strtolower($company) ? $companyId : company_resolve((int) $venue['id'], $name, false);
            }
            q(
                'UPDATE ' . t('guests') . ' SET first_name = ?, last_name = ?, email = ?, company_id = ?, updated_at = ? WHERE id = ? AND venue_id = ?',
                [$first, $last, $email, $companyId, now(), $id, $venue['id']]
            );
            pf_flash('ok', 'Dati aggiornati.');
            redirect('profilo.php');
        }
    } elseif ($form === 'diete') {
        $diets = diets_clean($venue, g_post_diets());
        $needs = clean_text(post('needs'), 500);
        $has = $diets !== '' || $needs !== '';
        if ($has && $me['consent_at'] === null && post('consent') !== '1') {
            $error = 'Per indicare diete o intolleranze serve il tuo consenso al loro utilizzo.';
        } else {
            // Senza diete né esigenze il consenso non serve più: viene azzerato insieme ai dati.
            q(
                'UPDATE ' . t('guests') . ' SET diets = ?, needs = ?, consent_at = ?, updated_at = ? WHERE id = ? AND venue_id = ?',
                [$diets, $needs, $has ? ($me['consent_at'] ?? now()) : null, now(), $id, $venue['id']]
            );
            pf_flash('ok', $has ? 'Diete ed esigenze aggiornate: la cucina le vede accanto al tuo nome.' : 'Diete ed esigenze cancellate.');
            redirect('profilo.php');
        }
    } elseif ($form === 'password') {
        $new = (string) ($_POST['password'] ?? '');
        $hash = (string) q_value('SELECT password_hash FROM ' . t('guests') . ' WHERE id = ? AND venue_id = ?', [$id, $venue['id']]);
        if (!password_verify((string) ($_POST['current'] ?? ''), $hash)) {
            $error = 'La password attuale non è corretta.';
        } elseif ($p = guest_password_problem($new)) {
            $error = $p;
        } else {
            q('UPDATE ' . t('guests') . ' SET password_hash = ?, updated_at = ? WHERE id = ? AND venue_id = ?', [password_hash($new, PASSWORD_DEFAULT), now(), $id, $venue['id']]);
            pf_flash('ok', 'Password cambiata.');
            redirect('profilo.php');
        }
    } else {
        redirect('profilo.php');
    }
}

// Dopo un errore il modulo mostra ciò che l'ospite aveva scritto.
$v = [
    'first_name' => $form === 'dati' ? post('first_name') : (string) $me['first_name'],
    'last_name' => $form === 'dati' ? post('last_name') : (string) $me['last_name'],
    'email' => $form === 'dati' ? post('email') : (string) $me['email'],
    'company' => $form === 'dati' ? post('company') : $company,
    'diets' => $form === 'diete' ? g_post_diets() : guest_diets($me),
    'needs' => $form === 'diete' ? post('needs') : (string) $me['needs'],
];

ui_guest_start('Il tuo *profilo*', 'profilo.php', ['eyebrow' => g_hello($me)]);
if ($error !== '') {
    echo '<div class="notice notice-error" role="alert">' . e($error) . '</div>';
}
?>
<form method="post" action="profilo.php" class="card stack-s" novalidate>
  <?= csrf_field() ?><input type="hidden" name="azione" value="dati">
  <h2>I tuoi dati</h2>
  <div class="form-grid g-two">
    <?= ui_field('first_name', 'Nome', $v['first_name'], ['required' => true, 'autocomplete' => 'given-name', 'attrs' => 'maxlength="80"']) ?>
    <?= ui_field('last_name', 'Cognome', $v['last_name'], ['required' => true, 'autocomplete' => 'family-name', 'attrs' => 'maxlength="80"']) ?>
  </div>
  <?= ui_field('phone_show', 'Cellulare', phone_show((string) $me['phone']), ['type' => 'tel', 'attrs' => 'readonly', 'hint' => 'È il numero con cui entri. Per cambiarlo chiedi alla reception.']) ?>
  <?= ui_field('email', 'Email', $v['email'], ['type' => 'email', 'autocomplete' => 'email', 'placeholder' => 'nome@email.it', 'hint' => 'Facoltativa: serve per recuperare la password' . (feature('email', $venue) ? ' e per ricevere gli avvisi.' : '.')]) ?>
  <?= $withCompany ? g_company_field($venue, $v['company']) : '' ?>
  <button type="submit" class="btn btn-dark btn-block">Salva i dati</button>
</form>

<form method="post" action="profilo.php" class="card stack-s" novalidate>
  <?= csrf_field() ?><input type="hidden" name="azione" value="diete">
  <h2>Diete e intolleranze</h2>
  <?= $dietList ? ui_chips('diets[]', array_combine($dietList, $dietList), $v['diets']) : '' ?>
  <?= ui_field('needs', 'Altre esigenze (facoltativo)', $v['needs'], ['type' => 'textarea', 'rows' => 2, 'placeholder' => 'Es. allergia alla frutta secca', 'attrs' => 'maxlength="500"']) ?>
  <?php if ($me['consent_at'] === null): ?>
  <?= ui_check('consent', 'Acconsento che ' . e((string) $venue['name']) . ' usi i dati su diete e intolleranze solo per preparare i miei pasti. <a href="../privacy/" target="_blank" rel="noopener">Informativa privacy</a>', post('consent') === '1') ?>
  <?php else: ?>
  <p class="hint">Hai dato il consenso a usare questi dati solo per preparare i tuoi pasti. Togliendo tutte le voci, vengono cancellati.</p>
  <?php endif; ?>
  <button type="submit" class="btn btn-dark btn-block">Salva diete ed esigenze</button>
</form>

<form method="post" action="profilo.php" class="card stack-s" novalidate>
  <?= csrf_field() ?><input type="hidden" name="azione" value="password">
  <h2>Password</h2>
  <?= ui_field('current', 'Password attuale', '', ['type' => 'password', 'required' => true, 'autocomplete' => 'current-password']) ?>
  <?= ui_field('password', 'Nuova password', '', ['type' => 'password', 'required' => true, 'autocomplete' => 'new-password', 'hint' => 'Almeno 6 caratteri.']) ?>
  <button type="submit" class="btn btn-line btn-block">Cambia la password</button>
</form>

<section class="card card-cream stack-s">
  <h2><?= e((string) $venue['name']) ?></h2>
  <?php if ((string) $venue['address'] !== ''): ?><p class="small"><?= e((string) $venue['address']) ?></p><?php endif; ?>
  <?php if ((string) $venue['phone'] !== ''): ?>
  <a class="btn btn-line btn-block" href="tel:<?= e((string) $venue['phone']) ?>"><?= icon('phone', 18) ?>Chiama la reception: <?= e(phone_show((string) $venue['phone'])) ?></a>
  <?php else: ?>
  <p class="small muted">Per modifiche fuori orario o per il cellulare rivolgiti alla reception.</p>
  <?php endif; ?>
</section>

<form method="post" action="esci.php">
  <?= csrf_field() ?>
  <button type="submit" class="btn btn-line btn-danger btn-block"><?= icon('logout', 18) ?>Esci</button>
</form>
<?php
ui_guest_end();
