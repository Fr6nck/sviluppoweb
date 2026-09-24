<?php
/**
 * La prima volta: si crea l'account, con il codice che sta nel .env.
 *
 * @var bool  $disponibile
 * @var array $valori
 */
use ArcoDelVento\Support\Csrf;
?>
<?php if (!$disponibile): ?>
  <div class="adm-riquadro">
    <p>L'area riservata non ha ancora un account, e per crearlo serve un codice
       che non è impostato.</p>
    <ol class="adm-passi">
      <li>Apri il file <code>.env</code> nella cartella del sito (via FTP).</li>
      <li>Alla riga <code>ADMIN_SETUP_TOKEN=</code> scrivi una parola lunga e
          casuale, di almeno 16 caratteri.</li>
      <li>Salva, ricarica il file e torna su questa pagina.</li>
    </ol>
    <p class="adm-nota">Serve perché solo chi ha accesso ai file del sito possa creare
       l'account: nessuno può arrivare per primo e prendersi il pannello.</p>
  </div>
<?php else: ?>
  <p class="adm-intro">Crea l'account dell'area riservata. Serve una volta sola.</p>
  <form method="post" action="<?= e(adminUrl('configura')) ?>" class="adm-modulo adm-modulo--stretto" novalidate>
    <?= Csrf::field() ?>
    <div class="adm-campo">
      <label for="gettone">Codice di configurazione</label>
      <input type="password" id="gettone" name="gettone" required autocomplete="off" aria-describedby="gettone-aiuto">
      <p class="adm-aiuto" id="gettone-aiuto">È il valore della riga <code>ADMIN_SETUP_TOKEN</code> nel file
         <code>.env</code> (o in <code>env-prova.txt</code>, se l'hai caricato da lì).</p>
    </div>
    <div class="adm-campo">
      <label for="utente">Nome utente</label>
      <input type="text" id="utente" name="utente" required autocomplete="username"
             value="<?= e((string) ($valori['utente'] ?? 'daniele')) ?>" aria-describedby="utente-aiuto">
      <p class="adm-aiuto" id="utente-aiuto">Lettere minuscole, cifre, punto o trattino.</p>
    </div>
    <div class="adm-campo">
      <label for="password">Password</label>
      <input type="password" id="password" name="password" required minlength="10" autocomplete="new-password" aria-describedby="password-aiuto">
      <p class="adm-aiuto" id="password-aiuto">Almeno 10 caratteri. Una frase che ricordi va meglio di una parola complicata.</p>
    </div>
    <div class="adm-campo">
      <label for="ripeti">Ripeti la password</label>
      <input type="password" id="ripeti" name="ripeti" required minlength="10" autocomplete="new-password">
    </div>
    <p><button type="submit" class="adm-bottone">Crea l'account</button></p>
  </form>
<?php endif; ?>
