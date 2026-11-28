<?php
/**
 * Accesso comune: lo staff dei locali entra con l'email (pannello del locale),
 * gli ospiti con il cellulare (area ospiti del loro locale, senza bisogno del codice).
 */
declare(strict_types=1);

require dirname(__DIR__) . '/app/platform/core.php';
pf_boot('staff', '../');

if (staff_current()) {
    redirect('../gestione/');
}

/** Destinazione interna al pannello dopo l'accesso. */
function after_login_target(string $next): string
{
    return preg_match('#^[a-z0-9_-]+\.php(\?[A-Za-z0-9_=&%.-]*)?$#', $next) ? $next : 'index.php';
}

/** Collegamento monouso che apre l'area ospiti per quell'ospite (due minuti). */
function guest_entry_href(int $guestId): string
{
    return '../ospiti/entra.php?t=' . rawurlencode(pf_token_create('guest_entry', $guestId, 120));
}

$error = '';
$login = '';
$choices = [];
if (is_post()) {
    $login = post('email');
    $password = (string) ($_POST['password'] ?? '');
    if (!login_token_valid(post('_lt'), 'staff')) {
        $error = 'Il modulo è scaduto. Ricarica la pagina e riprova.';
    } elseif ($login === '' || $password === '') {
        $error = 'Scrivi email (o cellulare) e password.';
    } elseif (!str_contains($login, '@') && phone_normalize($login) !== '') {
        // Un numero di cellulare: è un ospite.
        [$found, $err] = guest_find_by_login($login, $password);
        if ($err !== null) {
            $error = $err;
        } elseif (count($found) === 1) {
            redirect(guest_entry_href($found[0]['guest_id']));
        } else {
            $choices = $found;
        }
    } else {
        $next = (string) ($_SESSION['after_login'] ?? '');
        $error = (string) staff_login($login, $password);
        if ($error === '') {
            redirect('../gestione/' . after_login_target($next));
        }
    }
}

if ($choices) {
    ui_auth_start('In quale *locale*?', ['eyebrow' => 'Area ospiti', 'lead' => 'Hai un accesso in più locali con questo cellulare. Scegli dove entrare.', 'photo' => 'sala']);
    echo '<div class="stack-s">';
    foreach ($choices as $c) {
        echo '<a class="btn btn-line btn-big btn-block" href="' . e(guest_entry_href($c['guest_id'])) . '">' . e((string) $c['venue']['name']) . icon('arrow', 18) . '</a>';
    }
    echo '</div><p class="auth-links"><a href="./">Torna all\'accesso</a></p>';
    ui_auth_end();
    exit;
}

ui_auth_start('Accedi a *ChiMangia*', ['eyebrow' => 'Staff dei locali e ospiti', 'lead' => 'Titolare, reception e cucina entrano con l\'email. Gli ospiti con il cellulare.', 'photo' => 'brigata']);
if ($error !== '') {
    echo '<div class="notice notice-error" role="alert" id="login-error">' . e($error) . '</div>';
}
?>
<form method="post" action="./" class="form" novalidate>
  <?= login_token_field('staff') ?>
  <?= ui_field('email', 'Email o cellulare', $login, ['required' => true, 'autocomplete' => 'username', 'attrs' => 'autofocus autocapitalize="none" spellcheck="false" maxlength="190"', 'hint' => 'Lo staff del locale usa l\'email, gli ospiti il numero di cellulare.']) ?>
  <?= ui_field('password', 'Password', '', ['type' => 'password', 'required' => true, 'autocomplete' => 'current-password']) ?>
  <button type="submit" class="btn btn-dark btn-big btn-block">Accedi<?= icon('arrow', 20) ?></button>
  <p class="auth-links">Password dimenticata? <a href="recupero.php">Staff</a> · <a href="../ospiti/recupero.php">Ospiti</a></p>
  <p class="auth-links">Hai un locale e non sei ancora cliente? <a href="../registrati/">Prova gratis <?= (int) setting('pf_trial_days', PF_TRIAL_DAYS) ?> giorni</a></p>
</form>
<form method="get" action="../ospiti/" class="card card-cream stack-s">
  <h2>Prima volta come ospite?</h2>
  <p class="small muted">Registrati dal collegamento che ti ha dato il locale, oppure scrivi qui il suo codice.</p>
  <div class="row">
    <label class="sr-only" for="codice">Codice del locale</label>
    <input class="grow" type="text" id="codice" name="l" placeholder="es. trattoria-da-mario" autocapitalize="none" autocomplete="off" required pattern="[a-zA-Z0-9-]{2,60}">
    <button type="submit" class="btn btn-line">Vai</button>
  </div>
</form>
<?php
ui_auth_end();
