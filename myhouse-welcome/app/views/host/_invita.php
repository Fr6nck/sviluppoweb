<?php
/* Invita un amico: il riquadro con la barra degli inviti e il link da mandare.
   Riceve $acc e, se c'è già, $inv (Inviti::stato). Si vede solo a chi può invitare:
   inviti accesi e abbonamento Stripe attivo. Lo includono «Le mie guide»,
   «La tua guida è online» e la pagina /inviti ($paginaInviti). */
use function MHW\b;
use MHW\{Support, Icon, Inviti};
if (!Inviti::puoInvitare($acc)) return;
$inv = $inv ?? Inviti::stato($acc);
$linkInvito = Inviti::link($acc);
$invN = (int) $inv['validi']; $invMax = (int) $inv['massimo'];
$invTraguardo = Inviti::MASSIMO === 50 ? 'metà prezzo' : 'il ' . Inviti::MASSIMO . '% di sconto';
if ($invN === 0) {
    $titoloInv = 'Invita un amico: uno sconto per te, uno per lui.';
    $sottoInv = 'Per ogni amico che pubblica la sua guida, il tuo prossimo rinnovo scende del ' . Inviti::PASSO . '%, fino al '
              . Inviti::MASSIMO . '%. Il tuo amico ha il ' . Inviti::AMICO . '% di sconto sul primo anno.';
} elseif ($invN < $invMax) {
    $titoloInv = "Sei al \u{2212}" . (int) $inv['percento'] . '% sul prossimo rinnovo.';
    $sottoInv = ($invN === 1 ? '1 amico ha pubblicato la sua guida.' : $invN . ' amici hanno pubblicato la loro guida.')
              . ' Ancora ' . ($invMax - $invN) . ' e arrivi a ' . $invTraguardo . '.';
} else {
    $titoloInv = (Inviti::MASSIMO === 50 ? 'Metà prezzo' : "\u{2212}" . Inviti::MASSIMO . '%') . ': hai raggiunto il massimo.';
    $sottoInv = (int) $inv['oltre'] > 0
        ? ($invN + (int) $inv['oltre']) . ' amici hanno pubblicato la loro guida: i primi ' . $invMax . ' contano per il tuo sconto. I prossimi hanno comunque il loro ' . Inviti::AMICO . '%.'
        : $invMax . ' amici hanno pubblicato la loro guida. Puoi continuare a invitare: i tuoi amici hanno sempre il loro ' . Inviti::AMICO . '%.';
}
$testoInvito = 'Uso MyHouse Welcome per la guida digitale della mia struttura. Con questo link hai il ' . Inviti::AMICO
             . '% di sconto sul primo anno: ' . $linkInvito; ?>
<section class="invito" aria-labelledby="invito-titolo">
  <div class="invito__testo">
    <span class="kicker">Invita un amico</span>
    <h2 id="invito-titolo" class="invito__titolo"><?= Support::e($titoloInv) ?></h2>
    <p class="invito__sotto"><?= Support::e($sottoInv) ?></p>
    <div>
      <div class="invito__conto"><span>Amici che hanno pubblicato</span><b><?= $invN ?> su <?= $invMax ?></b></div>
      <div class="invito__barra" aria-hidden="true"><?php for ($invI = 1; $invI <= $invMax; $invI++): ?><i<?= $invI <= $invN ? ' class="si"' : '' ?>></i><?php endfor; ?></div>
      <div class="invito__scala" aria-hidden="true"><span>0</span><span><?= "\u{2212}" . intdiv(Inviti::MASSIMO, 2) ?>%</span><span><?= "\u{2212}" . Inviti::MASSIMO ?>%</span></div>
    </div>
    <?php if ((int) $inv['percento'] > 0 && (int) $inv['prezzo'] > 0): ?>
      <p><?= $inv['rinnovo'] !== '' ? 'Il ' . Support::e(Support::date($inv['rinnovo'])) . ' paghi' : 'Al rinnovo paghi' ?>
        <b><?= Support::e(Support::money((int) $inv['scontato'], $inv['valuta'])) ?> + IVA</b> invece di <?= Support::e(Support::money((int) $inv['prezzo'], $inv['valuta'])) ?>.</p>
      <?php if (!$inv['automatico']): ?>
        <p class="small">Il rinnovo automatico è disattivato: lo sconto vale solo sul rinnovo. <a href="<?= b() ?>/account">Riattivalo</a></p>
      <?php endif; ?>
    <?php endif; ?>
  </div>
  <div class="invito__link">
    <b class="small">Il tuo link di invito</b>
    <code><?= Support::e(preg_replace('#^https?://#', '', $linkInvito)) ?></code>
    <div class="row" style="gap:8px">
      <button type="button" class="btn btn--sm" data-copia="<?= Support::e($linkInvito) ?>" data-copiato="Link copiato" hidden><?= Icon::svg('copy', 15) ?>Copia il link</button>
      <a class="btn btn--ghost btn--sm" href="https://wa.me/?text=<?= rawurlencode($testoInvito) ?>" target="_blank" rel="noopener"><?= Icon::svg('whatsapp', 15) ?>WhatsApp</a>
      <a class="btn btn--ghost btn--sm" href="mailto:?subject=<?= rawurlencode('Ti invito su MyHouse Welcome') ?>&amp;body=<?= rawurlencode($testoInvito) ?>"><?= Icon::svg('message', 15) ?>Email</a>
    </div>
    <?php $codiceInv = Inviti::codice($acc); ?>
    <div class="invito__codice">
      <span class="small">Oppure il tuo codice</span>
      <b><?= Support::e($codiceInv) ?></b>
      <button type="button" class="btn btn--ghost btn--sm" data-copia="<?= Support::e($codiceInv) ?>" data-copiato="Codice copiato" hidden><?= Icon::svg('copy', 15) ?>Copia il codice</button>
    </div>
    <p class="small muted">Chi si registra da questo link, o scrive il codice dove si inserisce il codice sconto, ha il <?= Inviti::AMICO ?>% di sconto sul primo anno.
      <?php if (empty($paginaInviti)): ?><a href="<?= b() ?>/inviti">Come funziona</a><?php endif; ?></p>
  </div>
</section>
