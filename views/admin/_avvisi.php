<?php
/**
 * L'esito dell'ultima azione e gli errori da correggere.
 *
 * @var array|null   $flash
 * @var list<string> $errori
 * @var string       $vista
 */
?>
<?php if (!empty($flash)): ?>
  <p class="adm-avviso adm-avviso--<?= $flash['tipo'] === 'ok' ? 'ok' : 'errore' ?>" role="status">
    <?= icona($flash['tipo'] === 'ok' ? 'spunta' : 'attenzione', 18, 'adm-avviso__icona') ?>
    <span><?= e($flash['testo']) ?></span>
  </p>
<?php endif; ?>

<?php if (!empty($errori)): ?>
  <div class="adm-avviso adm-avviso--errore" role="alert">
    <?= icona('attenzione', 18, 'adm-avviso__icona') ?>
    <div>
      <?php if (in_array($vista, ['struttura', 'camera', 'testi-sezione', 'domande'], true)): ?>
        <p><strong>Non è stato salvato niente.</strong> Da correggere:</p>
      <?php endif; ?>
      <ul>
        <?php foreach ($errori as $errore): ?>
          <li><?= e($errore) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
<?php endif; ?>
