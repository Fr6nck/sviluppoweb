<?php use function MHW\t, MHW\tf;
/* Il pagamento con la carta nel pannello: il modulo di Stripe (Checkout incorporato) dentro il passo «Pubblica».
   La carta la scrive il cliente in un iframe di Stripe: il sito non la vede. Alla fine Stripe porta su /pagamento/ok,
   e la guida va online solo con il webhook firmato. Senza JavaScript, o se Stripe.js non si carica: la pagina di Stripe.
   Riceve: $prop, $ordine, $segreto (della sessione), $chiave (pubblicabile), $pianoOrdine. */
use function MHW\b;
use MHW\{Support, Csrf, Icon, Codici};
$pid = (int) $prop['id'];
$oid = (int) $ordine['id'];
$title = t('Pagamento — ') . $prop['name'];
$codiceP = Codici::pagamento($oid); ?>

<div class="stack stack--lg" style="max-width:680px">
  <div class="stack stack--sm">
    <a class="small" href="<?= b() ?>/pannello/<?= $pid ?>/procedura/pubblica"><?= Icon::svg('back', 14) ?> <?= t('Passo 5 di 5 · Pubblica') ?></a>
    <h1><?= t('Paga e pubblica.') ?></h1>
    <p class="lead"><?= t('Inserisci la carta di credito o di debito. Il modulo è di Stripe: i dati della carta non passano dal nostro sito. Appena Stripe conferma, la guida va online.') ?></p>
    <p class="small muted" style="margin:0"><?= Support::e((string) ($pianoOrdine['name'] ?? '')) ?> · <?= Support::e($prop['name']) ?><?= $codiceP !== '' ? ' · ' . t('pagamento') . ' <span style="font-family:ui-monospace,Menlo,Consolas,monospace">' . Support::e($codiceP) . '</span>' : '' ?></p>
  </div>

  <div class="pagamento-carta" id="pagamento-carta" data-chiave="<?= Support::e($chiave) ?>" data-segreto="<?= Support::e($segreto) ?>" aria-live="polite">
    <p class="pagamento-carta__attesa" data-attesa><?= t('Carico il modulo di pagamento…') ?></p>
  </div>
  <div class="note note--err" id="pagamento-guasto" role="alert" hidden>
    <?= t('Il modulo della carta non si è caricato. Puoi pagare lo stesso sulla pagina di Stripe, con il bottone qui sotto.') ?>
  </div>
  <noscript><p class="note note--err" style="margin:0"><?= t('Per pagare qui serve JavaScript. Puoi pagare sulla pagina di Stripe, con il bottone qui sotto.') ?></p></noscript>

  <div class="pagamento-altri">
    <form method="post" action="<?= b() ?>/pannello/<?= $pid ?>/pagamento/<?= $oid ?>/stripe" style="margin:0"><?= Csrf::field() ?>
      <button class="btn btn--ghost"><?= t('Paga sulla pagina di Stripe') ?></button>
    </form>
    <form method="post" action="<?= b() ?>/pannello/<?= $pid ?>/pagamento/<?= $oid ?>/annulla" style="margin:0"><?= Csrf::field() ?>
      <button class="btn btn--quiet"><?= t('Annulla e torna indietro') ?></button>
    </form>
  </div>
  <p class="small muted" style="margin:0"><?= t('Stessi prezzi e sconti in tutti e due i modi. La guida va online solo dopo la conferma di Stripe, mai prima.') ?></p>
</div>

<?php /* Stripe chiede di caricare Stripe.js sempre da js.stripe.com, senza copie né versioni fisse. */ ?>
<script src="https://js.stripe.com/v3/"></script>
<script>
(function () {
  var box = document.getElementById('pagamento-carta'), guasto = document.getElementById('pagamento-guasto');
  function nonVa() { box.hidden = true; guasto.hidden = false; }
  if (!box) return;
  if (typeof window.Stripe !== 'function') { nonVa(); return; }
  var segreto = box.getAttribute('data-segreto');
  try {
    var stripe = window.Stripe(box.getAttribute('data-chiave'), { locale: 'it' });
    // Stripe.js «Dahlia» ha rinominato initEmbeddedCheckout in createEmbeddedCheckoutPage: si usa quello che c'è.
    (stripe.createEmbeddedCheckoutPage || stripe.initEmbeddedCheckout).call(stripe, { fetchClientSecret: function () { return Promise.resolve(segreto); } })
      .then(function (modulo) {
        var attesa = box.querySelector('[data-attesa]'); if (attesa) attesa.remove();
        modulo.mount(box);
      })
      .catch(nonVa);
  } catch (e) { nonVa(); }
})();
</script>
