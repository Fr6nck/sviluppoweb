<?php
/**
 * Il sito chiuso da un codice d'accesso: acceso o spento, e quale codice.
 *
 * @var bool   $attivo
 * @var string $codice
 * @var bool   $spentoDalFile
 */
use ArcoDelVento\Support\Csrf;
?>
<p class="adm-intro">Finché il sito è in anteprima, chi lo apre vede solo una pagina con il campo del codice, e dietro il
   sito sfocato. Scritto il codice giusto, il sito si apre e su quel browser resta aperto trenta giorni.
   L'area riservata non è mai chiusa: da qui lo riapri quando vuoi.</p>

<?php if ($spentoDalFile): ?>
  <div class="adm-avviso adm-avviso--attenzione">
    <?= icona('attenzione', 18, 'adm-avviso__icona') ?>
    <p>Nel file <code>.env</code> c'è <code>SITE_ACCESS_CODE=off</code>: il sito è aperto a tutti, qualunque cosa
       scegli qui. Per usare il codice, togli quella riga.</p>
  </div>
<?php endif; ?>

<section class="adm-carta">
  <div class="adm-carta__testa">
    <h2>Adesso</h2>
    <span class="adm-pagamento adm-pagamento--<?= $attivo && !$spentoDalFile ? 'avviso' : 'ok' ?>"><?= $attivo && !$spentoDalFile ? 'Chiuso da codice' : 'Aperto a tutti' ?></span>
  </div>
  <form method="post" action="<?= e(adminUrl('accesso-sito')) ?>" class="adm-modulo adm-modulo--stretto" novalidate>
    <?= Csrf::field() ?>
    <div class="adm-campo adm-campo--spunta">
      <input type="hidden" name="attivo" value="0">
      <input type="checkbox" id="attivo" name="attivo" value="1"<?= $attivo ? ' checked' : '' ?>>
      <label for="attivo">Il sito si apre solo con il codice</label>
      <p class="adm-aiuto">Togli la spunta il giorno in cui il sito apre a tutti.</p>
    </div>
    <div class="adm-campo">
      <label for="codice">Codice d'accesso</label>
      <input type="text" id="codice" name="codice" value="<?= e($codice) ?>" autocomplete="off" spellcheck="false" aria-describedby="codice-aiuto">
      <p class="adm-aiuto" id="codice-aiuto">Da 4 a 60 caratteri. Maiuscole e minuscole non contano. Se lo cambi, chi era già
         entrato deve scrivere quello nuovo.</p>
    </div>
    <p><button type="submit" class="adm-bottone">Salva l'accesso</button></p>
  </form>
</section>

<p class="adm-nota">Per vedere che cosa vede un visitatore, apri il sito in una finestra anonima: qui, entrato
   nell'area riservata, il sito per te è sempre aperto.</p>
