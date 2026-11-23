<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/admin.php';

if (is_post() && csrf_valid()) {
    $u = current_user();
    if ($u) {
        audit('uscita', $u['email'], (int) $u['id']);
    }
    logout_user();
    flash('ok', 'Sei uscito dal pannello.');
}
redirect('login.php');
