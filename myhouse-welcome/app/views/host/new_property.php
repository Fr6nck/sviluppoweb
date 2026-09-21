<?php use MHW\{Support, Csrf, Icon}; $title = 'Nuova struttura'; $nav = 'pannello'; ?>
<div class="wizard">
  <div class="wizard__side">
    <div class="wizard__step on"><span class="n">1</span>La struttura</div>
    <div class="wizard__step"><span class="n">2</span>Arrivo e partenza</div>
    <div class="wizard__step"><span class="n">3</span>Wi-Fi e servizi</div>
    <div class="wizard__step"><span class="n">4</span>Consigli sul posto</div>
    <div class="wizard__step"><span class="n">5</span>Lingue</div>
    <div class="wizard__step"><span class="n">6</span>QR e pubblicazione</div>
    <div class="note note--quiet" style="margin-top:auto">Potete fermarvi quando volete.
      Quello che scrivete resta salvato.</div>
  </div>

  <div class="wizard__main">
    <div class="stack stack--lg" style="max-width:440px">
      <div class="stack stack--sm">
        <h1>Come si chiama la casa?</h1>
        <p class="lead">Bastano il nome e la città. Tutto il resto — Wi-Fi, chiavi, orari, i posti giusti —
          si aggiunge dopo, una cosa per volta.</p>
      </div>

      <?php if ($err): ?><p class="note note--err"><?= Support::e($err) ?></p><?php endif; ?>

      <form method="post" class="stack"><?= Csrf::field() ?>
        <div class="field" style="margin:0"><label for="name">Nome della struttura</label>
          <input id="name" name="name" type="text" required placeholder="Casa Lucia" autofocus></div>
        <div class="field" style="margin:0"><label for="city">Città</label>
          <input id="city" name="city" type="text" placeholder="Montepulciano"></div>
        <div class="row" style="margin-top:4px">
          <button class="btn btn--go">Continua <span class="go"><?= Icon::svg('arrow', 18, 2) ?></span></button>
        </div>
      </form>

      <p class="tiny muted">Usate <?= (int) $have ?> di
        <?= $max > 99 ? 'illimitate' : (int) $max ?> strutture comprese nel piano.</p>
    </div>
  </div>
</div>
