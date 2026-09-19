<?php use MHW\{Support, Csrf, Stripe}; $title = 'Conferma'; ?>
<h1><?= Support::e($pkg['name']) ?></h1>
<p class="muted" style="margin-top:10px"><?= Support::e($pkg['tagline']) ?></p>
<div class="card" style="margin-top:20px">
  <div class="spread"><span>Totale</span>
    <strong style="font-size:26px;font-family:Gloock,Georgia,serif"><?= Support::e(Support::money((int) $pv['price_cents'], $pv['currency'])) ?></strong></div>
  <p class="muted tiny" style="margin-top:8px">Versione <?= (int) $pv['version'] ?> del piano — è questa che resterà vostra anche se in futuro il pacchetto cambia.</p>
</div>
<?php if (!Stripe::enabled()): ?>
  <p class="note" style="margin-top:16px"><strong>Modalità prova.</strong> Stripe non è configurato,
  quindi non verrà addebitato nulla: l'ordine viene registrato e il piano attivato per farvi provare il prodotto.
  Aggiungete le chiavi in <code>config.php</code> per i pagamenti veri.</p>
<?php endif; ?>
<form method="post" style="margin-top:20px"><?= Csrf::field() ?>
  <button class="btn btn--block"><?= Stripe::enabled() ? 'Paga con Stripe' : 'Attiva in modalità prova' ?></button>
</form>
<p style="margin-top:14px"><a class="btn btn--quiet" href="/">Annulla</a></p>
