<?php
/** Uscita dall'area ospiti. */
declare(strict_types=1);

require dirname(__DIR__) . '/app/platform/core.php';
pf_boot('guest', '../');

$slug = (string) ($_SESSION['venue_slug'] ?? '');
if (is_post() && csrf_valid()) {
    guest_logout();
    // "poi": il locale da aprire dopo l'uscita (quando si passa da un locale all'altro).
    $next = venue_by_slug(mb_strtolower(post('poi')));
    if ($next) {
        $slug = (string) $next['slug'];
        $_SESSION['venue_slug'] = $slug;
    } else {
        pf_flash('info', 'Sei uscito. A presto!');
    }
}
redirect('index.php' . ($slug !== '' ? '?l=' . rawurlencode($slug) : ''));
