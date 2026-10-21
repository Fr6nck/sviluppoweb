<?php use function MHW\t;
/* 9P · «Fino al 55%» nella home del pannello: lo sconto massimo sul rinnovo (il numero grande di _risparmio) con i due bottoni
   dell'email: «Invita un amico» e «Aderisci al giudizio con sconto». Per chi può invitare, finché non ha 10 inviti e il giudizio;
   mai con un'offerta su misura (lo sconto lo decide l'amministrazione). Riceve $acc. */
use function MHW\b;
use MHW\{Inviti, Giudizi, Subscriptions};
$f55Sub = Subscriptions::active((int) $acc['id']);
$f55Inviti = Inviti::puoInvitare($acc) && Inviti::validi((int) $acc['id']) < Inviti::amiciMassimi();
$f55Giudizio = Giudizi::puoAderire($acc);
if (($f55Inviti || $f55Giudizio) && Inviti::puoInvitare($acc) && empty($f55Sub['offerta_id'])): ?>
  <section class="panel stack fino55" aria-label="<?= MHW\Support::e(t('Sconti sul rinnovo')) ?>" style="gap:12px;margin-top:20px">
    <?php $rCome = b() . '/domande#{id}'; $rEtichetta = t('Come funziona'); $rClasse = 'risparmio--pannello';
          $rT = fn($k, $s) => MHW\TestiSito::formatta(MHW\TestiSito::get($k, MHW\Pannello::en() ? 'en' : null), $s); include __DIR__ . '/../pub/_risparmio.php'; ?>
    <div class="actions">
      <?php if ($f55Inviti): ?><a class="btn btn--sm" href="<?= b() ?>/inviti"><?= t('Invita un amico') ?></a><?php endif; ?>
      <?php if ($f55Giudizio): ?><a class="btn btn--ghost btn--sm" href="<?= b() ?>/giudizio#adesione"><?= t('Aderisci al giudizio con sconto') ?></a><?php endif; ?>
    </div>
  </section>
<?php endif; ?>
