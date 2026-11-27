<?php
/* 9A · «N strutture? Ti facciamo una proposta su misura.» Il riquadro del preventivo per più strutture del
   massimo del Portfolio: nella home, in /piano e in registrazione. Sta fuori dal modulo del piano e si apre
   quando il numero scelto supera il massimo (prezzi.js) o subito, se la pagina arriva con un numero oltre
   il massimo, un esito o un errore. Funziona anche senza JavaScript (POST /preventivo).
   Riceve: $pvP (la versione del Portfolio), $qP (le strutture scelte), $tornaP (dove tornare). */
use function MHW\b;
use MHW\{Support, Csrf, Icon, LinguaSito, Preventivi};
if (!Preventivi::disponibili()) return;
$enP = LinguaSito::en();
$maxP = (int) $pvP['max_quantity'];
$okP = $_SESSION['preventivo_ok'] ?? null; unset($_SESSION['preventivo_ok']);
$errP = $_SESSION['preventivo_errore'] ?? null; unset($_SESSION['preventivo_errore']);
$apertoP = $qP > $maxP || $okP || $errP;
$nP = (int) ($errP['strutture'] ?? ($okP ?: max((int) $qP, $maxP + 1)));
// 9N · chi ha già l'account trova nome ed email già scritti
$utP = MHW\Auth::user();
$vP = fn(string $k) => Support::e((string) ($errP[$k] ?? ($errP ? '' : ($utP ? ['nome' => $utP['name'] ?? '', 'email' => $utP['email'] ?? ''][$k] ?? '' : ''))));
$uP = fn(string $it, string $en) => $enP ? $en : $it; ?>
<div class="preventivo" id="preventivo" data-preventivo data-min="<?= $maxP + 1 ?>"<?= $apertoP ? '' : ' hidden' ?>>
  <?php if ($okP): ?>
    <p class="preventivo__ok" role="status"><?= Icon::svg('check', 20, 2.2) ?><span><?= $uP('Abbiamo ricevuto la richiesta per <b>' . (int) $okP . ' strutture</b>: ti rispondiamo entro un giorno lavorativo. Ti abbiamo mandato una conferma per email.',
                                                                                                'We received your request for <b>' . (int) $okP . ' properties</b>: we will get back to you within one working day. We sent you a confirmation by email.') ?></span></p>
  <?php else: ?>
    <h3 class="preventivo__titolo"><?= $enP ? '<span data-preventivo-n>' . $nP . '</span> properties? We will make you a tailored offer.' : '<span data-preventivo-n>' . $nP . '</span> strutture? Ti facciamo una proposta su misura.' ?></h3>
    <p class="preventivo__lead"><?= $uP('Lasciaci i tuoi contatti: ti rispondiamo entro un giorno lavorativo.', 'Leave us your details: we will get back to you within one working day.') ?></p>
    <form method="post" action="<?= b() ?>/preventivo" class="preventivo__form"><?= Csrf::field() ?>
      <input type="hidden" name="torna" value="<?= Support::e($tornaP) ?>">
      <div class="field" style="margin:0"><label for="prev-nome"><?= $uP('Nome e cognome', 'Full name') ?></label>
        <input id="prev-nome" name="nome" type="text" required maxlength="120" autocomplete="name" value="<?= $vP('nome') ?>"></div>
      <div class="field" style="margin:0"><label for="prev-email">Email</label>
        <input id="prev-email" name="email" type="email" required maxlength="190" autocomplete="email" value="<?= $vP('email') ?>"></div>
      <div class="field" style="margin:0"><label for="prev-telefono"><?= $uP('Telefono', 'Phone') ?> <span class="muted">(<?= $uP('facoltativo', 'optional') ?>)</span></label>
        <input id="prev-telefono" name="telefono" type="tel" maxlength="40" autocomplete="tel" value="<?= $vP('telefono') ?>"></div>
      <div class="field" style="margin:0"><label for="prev-strutture"><?= $uP('Quante strutture', 'How many properties') ?></label>
        <input id="prev-strutture" name="strutture" type="number" inputmode="numeric" required min="<?= $maxP + 1 ?>" max="999" step="1" value="<?= $nP ?>" data-preventivo-strutture></div>
      <div class="field preventivo__messaggio" style="margin:0"><label for="prev-messaggio"><?= $uP('Qualcosa da aggiungere?', 'Anything to add?') ?> <span class="muted">(<?= $uP('facoltativo', 'optional') ?>)</span></label>
        <textarea id="prev-messaggio" name="messaggio" rows="3" maxlength="2000"><?= $vP('messaggio') ?></textarea></div>
      <?php if ($errP): ?><p class="field-error preventivo__errore" role="alert"><?= Support::e((string) $errP['msg']) ?></p><?php endif; ?>
      <div class="preventivo__azioni"><button class="btn"><?= $uP('Chiedi il preventivo', 'Ask for a quote') ?></button>
        <span class="small muted"><?= $uP('Nessun impegno: il Portfolio fino a ' . $maxP . ' strutture si attiva da solo.', 'No commitment: Portfolio up to ' . $maxP . ' properties activates on its own.') ?></span></div>
    </form>
  <?php endif; ?>
</div>
