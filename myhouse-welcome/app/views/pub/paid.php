<?php
/* Il ritorno da Stripe. Il browser non prova niente: questa pagina aspetta il
   webhook firmato e se ne accorge da sola, senza ricaricare. */
use function MHW\b; use MHW\{Support, Icon}; $title = 'Stiamo attivando la tua guida'; ?>
<div class="stack stack--lg" style="max-width:640px" id="attesa" data-stato="<?= Support::e(b()) ?>/pagamento/stato?order=<?= (int) $order['id'] ?>">
  <div class="stack stack--sm">
    <h1 id="titolo">Grazie. Stiamo attivando la tua guida.</h1>
    <p class="lead" id="testo">Stripe ci sta confermando il pagamento con una notifica firmata: di solito bastano pochi secondi.
      Puoi restare qui, la pagina si aggiorna da sola.</p>
  </div>
  <div class="actions" id="fatto" hidden>
    <a class="btn btn--go" id="vai" href="<?= b() ?>/pannello">Apri la guida <span class="go"><?= Icon::svg('arrow', 18, 2) ?></span></a>
    <a class="btn btn--ghost" href="<?= b() ?>/pannello/<?= (int) $order['property_id'] ?>/qr">Scarica il QR</a>
  </div>
  <p class="small muted" id="lento" hidden>Ci sta mettendo più del solito. Il pagamento non si perde: appena Stripe
    conferma, la guida va online da sola. Puoi tornare più tardi in <a href="<?= b() ?>/pannello">Le mie guide</a>.</p>
</div>
<script>
(function () {
  var box = document.getElementById('attesa'), url = box.getAttribute('data-stato'), giri = 0;
  function chiedi() {
    fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d.stato === 'paid' && d.pubblicata) {
          document.getElementById('titolo').textContent = 'La tua guida è online.';
          document.getElementById('testo').textContent = 'Abbonamento attivo. Da adesso il link e il QR funzionano.';
          if (d.guida) document.getElementById('vai').href = d.guida;
          document.getElementById('fatto').hidden = false; document.getElementById('lento').hidden = true;
          return;
        }
        if (++giri === 15) document.getElementById('lento').hidden = false;
        setTimeout(chiedi, giri < 15 ? 2000 : 6000);
      }).catch(function () { setTimeout(chiedi, 5000); });
  }
  chiedi();
})();
</script>
