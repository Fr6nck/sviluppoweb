<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/admin.php';

$sent = false;
$error = '';
if (is_post()) {
    if (!csrf_valid()) {
        $error = 'Il modulo è scaduto. Riprova.';
    } else {
        $email = mb_strtolower(post('email'));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Inserisci un indirizzo email valido.';
        } else {
            // Limite: massimo 3 richieste ogni 15 minuti per IP, per evitare abusi.
            $recent = (int) q_value(
                'SELECT COUNT(*) FROM ' . t('audit_log') . ' WHERE action = ? AND ip_hash = ? AND created_at > ?',
                ['recupero_richiesto', ip_hash(), date('Y-m-d H:i:s', time() - 900)]
            );
            $u = q_one('SELECT id, name, email FROM ' . t('users') . ' WHERE email = ? AND active = 1', [$email]);
            if ($recent < 3) {
                audit('recupero_richiesto', $email, $u ? (int) $u['id'] : null);
                if ($u) {
                    $token = password_reset_create((int) $u['id']);
                    $link = site_url('admin/reimposta.php?token=' . rawurlencode($token));
                    $body = "Ciao {$u['name']},\n\n"
                        . "abbiamo ricevuto una richiesta per reimpostare la password del pannello ChiMangia.\n\n"
                        . "Apri questo link entro un'ora per scegliere una nuova password:\n$link\n\n"
                        . "Il link si può usare una sola volta. Se non hai chiesto tu il recupero, ignora questo messaggio: la password attuale resta valida.\n";
                    $err = send_mail([$u['email']], 'Recupero password del pannello ChiMangia', $body);
                    if ($err !== null) {
                        log_message('Email di recupero non inviata a ' . $u['email'] . ': ' . $err);
                    }
                }
            }
            // Messaggio identico in ogni caso, per non rivelare quali email sono registrate.
            $sent = true;
        }
    }
}

admin_auth_page_start('Recupera la password');
if ($sent): ?>
  <div class="notice notice-ok" role="status">Se l'indirizzo è registrato, riceverai a breve un'email con il link per scegliere una nuova password. Il link vale un'ora e si usa una sola volta.</div>
  <p class="auth-links"><a href="login.php">Torna all'accesso</a></p>
<?php else: ?>
<form method="post" action="recupero.php" class="form" novalidate>
  <?= csrf_field() ?>
  <p>Scrivi l'email con cui accedi: ti mandiamo un link per scegliere una nuova password.</p>
  <div class="field">
    <label for="email">Email</label>
    <input type="email" id="email" name="email" autocomplete="username" required<?= $error ? ' aria-invalid="true" aria-describedby="email-err"' : '' ?>>
    <?php if ($error): ?><p class="field-error" id="email-err"><?= e($error) ?></p><?php endif; ?>
  </div>
  <button type="submit" class="btn btn-dark btn-block">Invia il link</button>
  <p class="auth-links"><a href="login.php">Torna all'accesso</a></p>
</form>
<?php endif;
admin_auth_page_end();
