<?php
/** @var string $utente */
use ArcoDelVento\Support\Csrf;
?>
<p class="adm-intro">Sei entrato come <strong><?= e($utente) ?></strong>.</p>
<form method="post" action="<?= e(adminUrl('account')) ?>" class="adm-modulo adm-modulo--stretto" novalidate>
  <?= Csrf::field() ?>
  <h2>Cambia la password</h2>
  <div class="adm-campo">
    <label for="attuale">Password attuale</label>
    <input type="password" id="attuale" name="attuale" required autocomplete="current-password">
  </div>
  <div class="adm-campo">
    <label for="nuova">Nuova password</label>
    <input type="password" id="nuova" name="nuova" required minlength="10" autocomplete="new-password" aria-describedby="nuova-aiuto">
    <p class="adm-aiuto" id="nuova-aiuto">Almeno 10 caratteri.</p>
  </div>
  <div class="adm-campo">
    <label for="ripeti">Ripeti la nuova password</label>
    <input type="password" id="ripeti" name="ripeti" required minlength="10" autocomplete="new-password">
  </div>
  <p><button type="submit" class="adm-bottone">Cambia la password</button></p>
</form>
<p class="adm-nota">Dopo due ore senza attività si esce da soli; dopo dodici ore in tutto, si rientra comunque.</p>
