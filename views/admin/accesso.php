<?php
/** @var array $valori */
use ArcoDelVento\Support\Csrf;
?>
<form method="post" action="<?= e(adminUrl('accesso')) ?>" class="adm-modulo adm-modulo--stretto" novalidate>
  <?= Csrf::field() ?>
  <div class="adm-campo">
    <label for="utente">Nome utente</label>
    <input type="text" id="utente" name="utente" required autocomplete="username" autocapitalize="none"
           value="<?= e((string) ($valori['utente'] ?? '')) ?>">
  </div>
  <div class="adm-campo">
    <label for="password">Password</label>
    <input type="password" id="password" name="password" required autocomplete="current-password">
  </div>
  <p><button type="submit" class="adm-bottone">Entra</button></p>
  <p class="adm-nota">Password dimenticata? Via FTP cancella il file
     <code>storage/data/admin.json</code> e ricrea l'account con il codice di configurazione del <code>.env</code>.
     Le modifiche e le richieste non si perdono.</p>
</form>
