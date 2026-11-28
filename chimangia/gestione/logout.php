<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/platform/core.php';
pf_boot('staff', '../');

if (is_post() && csrf_valid()) {
    $super = !empty($_SESSION['impersonated_by']);
    staff_logout();
    if ($super) {
        redirect('../superadmin/');
    }
    pf_flash('info', 'Sei uscito dal pannello.');
}
redirect('../accedi/');
