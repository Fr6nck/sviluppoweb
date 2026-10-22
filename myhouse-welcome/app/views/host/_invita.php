<?php use function MHW\t, MHW\tf;
/* Invita un amico: il riquadro con la barra degli inviti e il link da mandare.
   Riceve $acc e, se c'è già, $inv (Inviti::stato). Si vede solo a chi può invitare:
   inviti accesi e un abbonamento attivo, di qualsiasi tipo (7D). Lo includono «Le mie guide»,
   «La tua guida è online» e la pagina /inviti ($paginaInviti). */
use function MHW\b;
use MHW\{Support, Icon, Inviti};
if (!Inviti::puoInvitare($acc)) return;
$inv = $inv ?? Inviti::stato($acc);
$linkInvito = Inviti::link($acc);
$invN = (int) $inv['validi']; $invMax = (int) $inv['massimo'];
// 9N: il traguardo è «al 55% degli inviti», non «metà prezzo». Frasi intere, nella lingua del pannello.
$numInv = ['{passo}' => Inviti::PASSO, '{max}' => Inviti::MASSIMO, '{amico}' => Inviti::AMICO, '{n}' => $invN, '{tetto}' => $invMax];
if ($invN === 0) {
    $titoloInv = t('Invita un amico: uno sconto per te, uno per lui.');
    $sottoInv = tf('Per ogni amico che pubblica la sua guida, il tuo prossimo rinnovo scende del {passo}%, fino al {max}%. Il tuo amico ha il {amico}% di sconto sul primo anno.', $numInv);
} elseif ($invN < $invMax) {
    $titoloInv = tf('Sei al {p}% sul prossimo rinnovo.', ['{p}' => "\u{2212}" . (int) $inv['percento']]);
    $sottoInv = ($invN === 1 ? t('1 amico ha pubblicato la sua guida.') : tf('{n} amici hanno pubblicato la loro guida.', $numInv))
              . ' ' . tf('Ancora {r} e arrivi al {max}% degli inviti.', $numInv + ['{r}' => $invMax - $invN]);
} else {
    $titoloInv = (Inviti::MASSIMO === 50 ? t('Metà prezzo') : "\u{2212}" . Inviti::MASSIMO . '%') . t(': hai raggiunto il massimo.');
    $sottoInv = (int) $inv['oltre'] > 0
        ? tf('{tot} amici hanno pubblicato la loro guida: i primi {tetto} contano per il tuo sconto. I prossimi hanno comunque il loro {amico}%.', $numInv + ['{tot}' => $invN + (int) $inv['oltre']])
        : tf('{tetto} amici hanno pubblicato la loro guida. Puoi continuare a invitare: i tuoi amici hanno sempre il loro {amico}%.', $numInv);
}
$testoInvito = Inviti::messaggio($acc);   /* 7D: lo stesso messaggio pronto della pagina «Invita un amico» */ ?>
<section class="invito" aria-labelledby="invito-titolo">
  <div class="invito__testo">
    <span class="kicker"><?= t('Invita un amico') ?></span>
    <h2 id="invito-titolo" class="invito__titolo"><?= Support::e($titoloInv) ?></h2>
    <p class="invito__sotto"><?= Support::e($sottoInv) ?></p>
    <?php $barra = Inviti::barra($acc); $piccola = false; include __DIR__ . '/_barra_inviti.php'; /* 9R · la barra che si accende */ ?>
    <?php if ((int) $inv['percento'] > 0 && (int) $inv['prezzo'] > 0): ?>
      <p><?= tf($inv['rinnovo'] !== '' ? 'Il {data} paghi {prezzo} invece di {pieno}.' : 'Al rinnovo paghi {prezzo} invece di {pieno}.', ['{data}' => Support::e(MHW\Pannello::data($inv['rinnovo'])),
          '{prezzo}' => '<b>' . Support::e(Support::money((int) $inv['scontato'], $inv['valuta'])) . ' ' . t('+ IVA') . '</b>', '{pieno}' => Support::e(Support::money((int) $inv['prezzo'], $inv['valuta']))]) ?></p>
      <?php if (!empty($inv['a_mano'])): ?>
        <p class="small"><?= t('Il tuo abbonamento è stato attivato dal nostro staff: lo sconto lo applichiamo noi al rinnovo.') ?></p>
      <?php elseif (!$inv['automatico']): ?>
        <p class="small"><?= t('Il rinnovo automatico è disattivato: lo sconto vale solo sul rinnovo.') ?> <a href="<?= b() ?>/account"><?= t('Riattivalo') ?></a></p>
      <?php endif; ?>
    <?php endif; ?>
  </div>
  <div class="invito__link">
    <b class="small"><?= t('Il tuo link di invito') ?></b>
    <code><?= Support::e(preg_replace('#^https?://#', '', $linkInvito)) ?></code>
    <div class="row" style="gap:8px">
      <button type="button" class="btn btn--sm" data-copia="<?= Support::e($linkInvito) ?>" data-copiato="<?= t('Link copiato') ?>" hidden><?= Icon::svg('copy', 15) ?><?= t('Copia il link') ?></button>
      <?php if (empty($paginaInviti)): /* nella pagina «Invita un amico» ci sono già, sotto il messaggio pronto */ ?>
      <a class="btn btn--ghost btn--sm" href="https://wa.me/?text=<?= rawurlencode($testoInvito) ?>" target="_blank" rel="noopener"><?= Icon::svg('whatsapp', 15) ?>WhatsApp</a>
      <a class="btn btn--ghost btn--sm" href="mailto:?subject=<?= rawurlencode(t('Ti invito su MyHouse Welcome')) ?>&amp;body=<?= rawurlencode($testoInvito) ?>"><?= Icon::svg('message', 15) ?>Email</a>
      <?php endif; ?>
    </div>
    <?php $codiceInv = Inviti::codice($acc); ?>
    <div class="invito__codice">
      <span class="small"><?= t('Oppure il tuo codice') ?></span>
      <b><?= Support::e($codiceInv) ?></b>
      <button type="button" class="btn btn--ghost btn--sm" data-copia="<?= Support::e($codiceInv) ?>" data-copiato="<?= t('Codice copiato') ?>" hidden><?= Icon::svg('copy', 15) ?><?= t('Copia il codice') ?></button>
    </div>
    <p class="small muted"><?= Support::e(tf('Chi si registra da questo link, o scrive il codice dove si inserisce il codice sconto, ha il {amico}% di sconto sul primo anno.', $numInv)) ?>
      <?php if (empty($paginaInviti)): ?><a href="<?= b() ?>/inviti"><?= t('Come funziona') ?></a><?php endif; ?></p>
  </div>
</section>
