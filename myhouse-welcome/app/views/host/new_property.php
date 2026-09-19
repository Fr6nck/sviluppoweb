<?php use MHW\{Support, Csrf}; $title = 'Nuova struttura'; $nav = 'pannello'; ?>
<h1>Come si chiama la casa?</h1>
<p class="muted" style="margin-top:10px">Bastano il nome e la città. Il resto si aggiunge dopo.</p>
<?php if ($err): ?><p class="note note--err" style="margin-top:16px"><?= Support::e($err) ?></p><?php endif; ?>
<form method="post" class="card" style="margin-top:20px;max-width:520px"><?= Csrf::field() ?>
  <div class="field"><label for="name">Nome della struttura</label>
    <input id="name" name="name" type="text" required placeholder="Casa Lucia"></div>
  <div class="field"><label for="city">Città</label>
    <input id="city" name="city" type="text" placeholder="Montepulciano"></div>
  <button class="btn btn--block">Crea la guida</button>
  <p class="muted tiny" style="margin-top:12px">Usate <?= (int) $have ?> di <?= $max > 99 ? 'illimitate' : (int) $max ?> strutture comprese nel piano.</p>
</form>
