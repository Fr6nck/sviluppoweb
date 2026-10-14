<?php
/**
 * La prima volta del super admin: l'account si crea con il codice
 * SUPERADMIN_SETUP_TOKEN del file .env. Questa pagina esiste solo finché il
 * super admin non c'è e il codice è scritto; dopo, l'indirizzo è il suo accesso.
 *
 * @var array $valori
 */
use ArcoDelVento\Support\Csrf;
?>
<p class="adm-intro">Crea l'account <strong>super admin</strong>: è il solo che vede il pannello
   <strong>Configurazione</strong>, cioè il file <code>.env</code> del sito. Il gestore della casa non lo vede.
   Serve una volta sola.</p>
<form method="post" action="<?= e(adminUrl('super')) ?>" class="adm-modulo adm-modulo--stretto" novalidate>
  <?= Csrf::field() ?>
  <div class="adm-campo">
    <label for="gettone">Codice del super admin</label>
    <input type="password" id="gettone" name="gettone" required autocomplete="off" aria-describedby="gettone-aiuto">
    <p class="adm-aiuto" id="gettone-aiuto">È il valore della riga <code>SUPERADMIN_SETUP_TOKEN</code> nel file
       <code>.env</code>.</p>
  </div>
  <div class="adm-campo">
    <label for="utente">Nome utente</label>
    <input type="text" id="utente" name="utente" required autocomplete="username" autocapitalize="none"
           value="<?= e((string) ($valori['utente'] ?? '')) ?>" aria-describedby="utente-aiuto">
    <p class="adm-aiuto" id="utente-aiuto">Lettere minuscole, cifre, punto o trattino. Diverso da quello del gestore.</p>
  </div>
  <div class="adm-campo">
    <label for="password">Password</label>
    <input type="password" id="password" name="password" required minlength="10" autocomplete="new-password" aria-describedby="password-aiuto">
    <p class="adm-aiuto" id="password-aiuto">Almeno 10 caratteri. Questo account può cambiare la configurazione
       del sito: usa una password che non usi altrove.</p>
  </div>
  <div class="adm-campo">
    <label for="ripeti">Ripeti la password</label>
    <input type="password" id="ripeti" name="ripeti" required minlength="10" autocomplete="new-password">
  </div>
  <p><button type="submit" class="adm-bottone">Crea il super admin</button></p>
</form>
