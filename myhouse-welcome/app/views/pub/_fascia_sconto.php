<?php
/* La fascia in cima a landing e registrazione: un codice sconto arrivato da un link (6E)
   o l'invito di un amico. Riceve $codiceSconto (riga di Sconti::valida) e $invitoDi (nome). */
use MHW\{Support, Icon, LinguaSito};
$enF = LinguaSito::en(); ?>
<?php if (!empty($codiceSconto)): /* arrivato da un link con il codice sconto (6E) */ ?>
  <p class="sconto-fascia" role="status"><?= Icon::svg('check', 18, 2) ?><span><?= $enF ? 'Code' : 'Codice' ?> <b><?= Support::e($codiceSconto['code']) ?></b>:
    <?= Support::e(MHW\Sconti::etichetta($codiceSconto)) ?> <?= $enF ? 'on the first year, until' : 'sul primo anno, fino al' ?> <?= Support::e(Support::date($codiceSconto['valid_until'])) ?>.</span></p>
<?php endif; ?>
<?php if (!empty($invitoDi)): /* arrivato dal link di un amico (Invita un amico) */ ?>
  <p class="sconto-fascia" role="status"><?= Icon::svg('check', 18, 2) ?><?php if ($enF): ?><span><b><?= Support::e($invitoDi) ?> invited you</b>:
    you get <?= MHW\Inviti::AMICO ?>% off your first year. It is applied automatically when you create your account.</span>
  <?php else: ?><span><b><?= Support::e($invitoDi) ?> ti ha invitato</b>:
    hai il <?= MHW\Inviti::AMICO ?>% di sconto sul primo anno. Si applica da solo quando crei l'account.</span><?php endif; ?></p>
<?php endif; ?>
