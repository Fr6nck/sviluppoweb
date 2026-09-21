<?php use MHW\{Support, Csrf, Config, Stripe, Icon}; $title = 'Piano ' . $pkg['name']; ?>
<h1><?= Support::e($pkg['name']) ?>.</h1>
<p class="muted" style="margin-top:14px"><?= Support::e($pkg['tagline']) ?></p>
<div class="panel stack" style="margin-top:24px">
  <div class="spread spread--mid">
    <span class="muted">Piano <?= Support::e($pkg['name']) ?>, versione <?= (int) $pv['version'] ?></span>
    <span class="price" style="font-size:34px;font-weight:500;letter-spacing:-1.2px">
      <?= Support::e(Support::money((int) $pv['price_cents'], $pv['currency'])) ?></span>
  </div>
  <hr class="rule">
  <?php if (Stripe::enabled()): ?>
    <p class="small muted">Il pagamento avviene su Stripe. Il piano si attiva quando Stripe ci conferma
      l'incasso con una notifica firmata: il ritorno dal browser da solo non attiva niente.</p>
  <?php else: ?>
    <p class="note"><?= Icon::svg('warning', 19) ?>
      <span>Stripe non è configurato su questo server: l'acquisto resta in <strong>modalità prova</strong>
      e non viene addebitato nulla. Per i pagamenti veri servono le chiavi in <code>config.php</code>.</span></p>
  <?php endif; ?>
  <form method="post"><?= Csrf::field() ?>
    <button class="btn btn--block btn--go">
      <?= Stripe::enabled() ? 'Vai al pagamento' : 'Attiva in modalità prova' ?>
      <span class="go"><?= Icon::svg('arrow', 18, 2) ?></span></button>
  </form>
</div>
