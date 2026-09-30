<?php
/* Impostazioni della struttura e, in fondo, l'eliminazione. */
use function MHW\b;
use MHW\{Support, Csrf};
$title = 'Impostazioni — ' . $prop['name']; ?>
<div class="stack stack--lg" style="max-width:720px">
  <div class="stack stack--sm">
    <h1>Impostazioni.</h1>
    <p class="muted small">Indirizzo della guida: <?= Support::e(Support::baseUrl()) ?>/g/<?= Support::e($prop['slug']) ?> — resta sempre lo stesso, anche se cambi il nome.</p>
  </div>
  <?php if ($err): ?><p class="note note--err" role="alert"><?= Support::e($err) ?></p><?php endif; ?>
  <?php $dopoPasso = ''; include __DIR__ . '/_struttura_form.php'; ?>

  <details class="fieldset" style="margin-top:24px">
    <summary class="legend" style="cursor:pointer;min-height:32px">Elimina la struttura</summary>
    <form method="post" action="<?= b() ?>/pannello/<?= (int) $prop['id'] ?>/elimina" class="stack" style="margin-top:12px"><?= Csrf::field() ?>
      <p class="small">Si cancellano la guida, le traduzioni, le immagini e il QR. Chi ha già stampato il QR troverà una pagina vuota.
        Non si può annullare. L'abbonamento non si disdice da qui: lo gestisci in <a href="<?= b() ?>/account">Account &amp; Fatturazione</a>.</p>
      <div class="field" style="margin:0"><label for="conferma">Per confermare scrivi <b><?= Support::e($prop['name']) ?></b></label>
        <input type="text" id="conferma" name="conferma" required autocomplete="off"></div>
      <div class="actions"><button class="btn btn--danger">Elimina per sempre</button></div>
    </form>
  </details>
</div>
