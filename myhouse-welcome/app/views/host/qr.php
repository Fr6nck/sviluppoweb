<?php use MHW\Support; $title = 'QR & Link — ' . $prop['name']; ?>
<div class="stack stack--lg" style="max-width:900px">
  <div class="stack stack--sm">
    <h1>QR &amp; Link.</h1>
    <?php if ($prop['status'] !== 'published'): ?>
      <p class="note">Puoi già scaricare e stampare il QR: funzionerà appena pubblichi la guida.</p>
    <?php elseif (!$online): ?>
      <p class="note note--err">La guida è offline perché l'abbonamento non è attivo. Il QR resta valido: tornerà a funzionare quando lo rinnovi.</p>
    <?php else: ?>
      <p class="lead">Stampa il QR e mettilo dove l'ospite lo vede entrando: sul tavolo, vicino alla porta, sul frigo.</p>
    <?php endif; ?>
  </div>
  <?php include __DIR__ . '/_qr_box.php'; ?>
</div>
