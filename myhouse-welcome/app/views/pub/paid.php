<?php use function MHW\b; use MHW\Icon; $title = 'Pagamento ricevuto'; ?>
<h1>Grazie.</h1>
<p class="muted" style="margin-top:14px">Stripe ci sta confermando l'incasso con una notifica firmata:
  il piano si attiva lì, non qui. Di solito è questione di secondi.</p>
<p style="margin-top:24px"><a class="btn btn--go" href="<?= b() ?>/pannello">
  Vai alle mie guide <span class="go"><?= Icon::svg('arrow', 18, 2) ?></span></a></p>
