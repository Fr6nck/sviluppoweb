<?php
/** Porta al pannello del fornitore (clienti, funzioni, manutenzione, backup). Solo Amministratore. */
declare(strict_types=1);

require dirname(__DIR__) . '/app/admin.php';
require_role('admin');
redirect('../superadmin/');
