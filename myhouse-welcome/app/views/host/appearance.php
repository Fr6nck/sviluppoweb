<?php
/* Aspetto: la palette si prova dal vivo nel telefono accanto. */
use MHW\{Support, Icon};
$title = 'Aspetto — ' . $prop['name']; ?>
<div class="editor">
  <div class="stack stack--lg">
    <div class="stack stack--sm">
      <h1>Aspetto.</h1>
      <p class="lead">Colori, logo e foto di copertina. Scegli una palette: l'anteprima cambia subito. Per confermare premi «Salva l'aspetto».</p>
    </div>
    <?php if ($err): ?><p class="note note--err" role="alert"><?= Support::e($err) ?></p><?php endif; ?>
    <?php $dopoPasso = ''; include __DIR__ . '/_aspetto_form.php'; ?>
    <a class="btn btn--ghost phonebtn" href="<?= Support::e(Support::url('/pannello/' . (int) $prop['id'] . '/anteprima')) ?>" target="_blank" rel="noopener"><?= Icon::svg('eye', 16) ?>Apri l'anteprima</a>
  </div>
  <?php include __DIR__ . '/_telefono.php'; ?>
</div>
