<?php
/**
 * @var array $valori
 * @var bool  $portaSuper  aperta da /admin/super: dopo l'accesso si va a Configurazione
 */
use ArcoDelVento\Support\Csrf;
?>
<form method="post" action="<?= e(adminUrl('accesso')) ?>" class="adm-modulo adm-modulo--stretto" novalidate>
  <?= Csrf::field() ?>
  <?php if (!empty($portaSuper)): ?><input type="hidden" name="super" value="1"><?php endif; ?>
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
  <?php /* La procedura di ripristino sta in docs/DEPLOY-HOSTINGER.md: su una
           pagina pubblica direbbe a chiunque dove stanno i file dell'account. */ ?>
  <p class="adm-nota">Password dimenticata? Scrivi a chi gestisce il sito: la reimposta senza perdere
     modifiche e richieste.</p>
</form>
