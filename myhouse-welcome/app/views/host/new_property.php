<?php use MHW\{Support, Csrf, Icon}; $title = 'Nuova struttura'; ?>
<div class="stack stack--lg" style="max-width:520px">
  <div class="stack stack--sm">
    <span class="kicker">Passo 1 di 7 · La tua struttura</span>
    <h1>Come si chiama la tua struttura?</h1>
    <p class="lead">Bastano il nome e la città. Check-in, Wi-Fi, regole e consigli li aggiungi dopo, un passo per volta.
      Puoi fermarti quando vuoi: quello che scrivi resta salvato.</p>
  </div>
  <?php if ($err): ?><p class="note note--err" role="alert"><?= Support::e($err) ?></p><?php endif; ?>
  <?php if ($have >= $max): ?>
    <div class="limit"><p>Hai già <?= (int) $have ?> struttur<?= $have === 1 ? 'a' : 'e' ?>, il massimo del tuo piano.</p>
      <a class="btn btn--sm" href="<?= MHW\b() ?>/piano">Scopri Portfolio</a></div>
  <?php else: ?>
    <form method="post" class="stack"><?= Csrf::field() ?>
      <div class="field" style="margin:0"><label for="name">Nome della struttura</label>
        <input id="name" name="name" type="text" required maxlength="120" placeholder="Per esempio: Casa Lucia" autofocus></div>
      <div class="field" style="margin:0"><label for="city">Città</label>
        <input id="city" name="city" type="text" maxlength="120" placeholder="Per esempio: Montepulciano"></div>
      <div class="actions"><button class="btn btn--go">Continua <span class="go"><?= Icon::svg('arrow', 18, 2) ?></span></button></div>
    </form>
  <?php endif; ?>
</div>
