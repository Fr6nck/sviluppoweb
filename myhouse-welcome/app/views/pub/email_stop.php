<?php use function MHW\b; use MHW\{Support, Csrf}; $title = 'Email'; ?>
<h1><?= $fatto ? 'Fatto.' : 'Non ricevere più questa email?' ?></h1>
<?php if ($fatto): ?>
  <p class="muted" style="margin-top:14px;line-height:24px">Non ti manderemo più il <?= Support::e($tipo) ?>. Le email che servono al tuo account
    (conferma dell'indirizzo, password, pagamenti) continuano ad arrivare.</p>
  <p style="margin-top:20px"><a class="btn btn--ghost" href="<?= b() ?>/">Torna al sito</a></p>
<?php else: ?>
  <p class="muted" style="margin-top:14px;line-height:24px">Smetteremo di mandarti il <?= Support::e($tipo) ?>. Le altre email non cambiano.</p>
  <form method="post" style="margin-top:20px"><?= Csrf::field() ?><button class="btn">Sì, non mandarmelo più</button></form>
<?php endif; ?>
