<?php use function MHW\t, MHW\tf;
/* 9R · «Le mie guide»: sotto l'elenco delle guide i due programmi, separati e cumulabili, finché il cliente non ha fatto tutto.
   1) «Invita un amico: fino al 50%» con la barra in piccolo (sparisce a 10 amici); 2) «Lasciaci un giudizio: un altro 5%»
   (Aderisci), che diventa «Ti manca il giudizio: senza, il 5% non parte» (bordo ocra, con l'importo sul suo piano) per chi ha
   aderito senza lasciarlo, e sparisce quando il giudizio c'è. Per lo staff restano, senza euro. Riceve $acc. */
use function MHW\b;
use MHW\{Support, Inviti, Giudizi, Subscriptions, Sconti, Plans, Staff, Pannello};
$rsSub = Subscriptions::active((int) $acc['id']);
$rsInviti = Inviti::puoInvitare($acc) && empty($rsSub['offerta_id']) && Inviti::validi((int) $acc['id']) < Inviti::amiciMassimi();
$rsG = Giudizi::disponibili() ? Giudizi::riquadro($acc) : ['stato' => 'nascosto'];
$rsGiudizio = in_array($rsG['stato'], ['invito', 'aderito'], true) && (Giudizi::attivo() || $rsG['stato'] === 'aderito') && empty($rsSub['offerta_id']);
if ($rsInviti || $rsGiudizio):
    $rsN = Inviti::validi((int) $acc['id']); ?>
<div class="stack riquadri-sconto" style="gap:12px;margin-top:20px">
  <?php if ($rsInviti): $barra = Inviti::barra($acc); $piccola = true; ?>
  <section class="panel riquadro-sconto" aria-labelledby="rs-inviti">
    <span class="riquadro-sconto__cifra" aria-hidden="true"><?= Inviti::MASSIMO ?>%</span>
    <div class="stack" style="gap:6px">
      <h2 id="rs-inviti" class="riquadro-sconto__titolo"><?= Support::e(tf('Invita un amico: fino al {max}% sul prossimo rinnovo', ['{max}' => Inviti::MASSIMO])) ?></h2>
      <p class="small" style="margin:0"><?= Support::e(tf('{passo}% per ogni amico che attiva la guida, lui ha il {amico}% sul primo anno.', ['{passo}' => Inviti::PASSO, '{amico}' => Inviti::AMICO])) ?>
        <?php if ($rsN > 0): ?><?= Support::e($rsN === 1 ? tf('Hai già un amico: sei al {p}%.', ['{p}' => Inviti::PASSO]) : tf('Hai già {n} amici: sei al {p}%.', ['{n}' => $rsN, '{p}' => min(Inviti::MASSIMO, $rsN * Inviti::PASSO)])) ?><?php endif; ?></p>
      <?php include __DIR__ . '/_barra_inviti.php'; ?>
    </div>
    <a class="btn btn--sm" href="<?= b() ?>/inviti"><?= t('Invita') ?></a>
  </section>
  <?php endif; ?>
  <?php if ($rsGiudizio): $gp = Giudizi::percentuale(); ?>
    <?php if ($rsG['stato'] === 'aderito'):
          $rsPv = $rsSub ? Plans::version((int) $rsSub['package_version_id']) : null;
          $rsPrezzo = $rsPv ? Plans::price($rsPv, max(1, (int) ($rsSub['quantity'] ?? 1))) : 0;
          $rsEuro = $rsPrezzo > 0 && !Staff::e($acc) ? $rsPrezzo - Sconti::netto($rsPrezzo, $gp) : 0; ?>
    <section class="panel riquadro-sconto riquadro-sconto--manca" aria-labelledby="rs-giudizio">
      <span class="riquadro-sconto__cifra" aria-hidden="true"><?= $gp ?>%</span>
      <div class="stack" style="gap:6px">
        <h2 id="rs-giudizio" class="riquadro-sconto__titolo"><?= Support::e(tf('Ti manca il giudizio: senza, il {n}% non parte', ['{n}' => $gp])) ?></h2>
        <p class="small" style="margin:0"><?= Support::e(!empty($acc['giudizio_adesione_at']) ? tf('Hai aderito il {data}.', ['{data}' => Pannello::data((string) $acc['giudizio_adesione_at'])]) : '') ?>
          <?= Support::e($rsEuro > 0 ? tf('Lascia un voto da 1 a 5 e un commento: vale {euro} in meno sul rinnovo di {piano}, in più allo sconto degli amici.', ['{euro}' => Support::money($rsEuro, (string) ($rsPv['currency'] ?? 'EUR')), '{piano}' => (string) ($rsPv['name'] ?? '')])
                                     : t('Lascia un voto da 1 a 5 e un commento: lo sconto si somma a quello degli amici.')) ?></p>
      </div>
      <a class="btn btn--sm" href="<?= b() ?>/giudizio?scrivi=1#scrivi"><?= t('Lascia il giudizio') ?></a>
    </section>
    <?php else: ?>
    <section class="panel riquadro-sconto" aria-labelledby="rs-giudizio">
      <span class="riquadro-sconto__cifra" aria-hidden="true"><?= $gp ?>%</span>
      <div class="stack" style="gap:6px">
        <h2 id="rs-giudizio" class="riquadro-sconto__titolo"><?= Support::e(tf('Lasciaci un giudizio: un altro {n}% sul prossimo rinnovo', ['{n}' => $gp])) ?></h2>
        <p class="small" style="margin:0"><?= Support::e(tf('Aderisci al programma «Giudizio con sconto», poi lasciaci un voto da 1 a 5 e un commento. Si somma allo sconto degli amici: insieme fino al {totale}%.', ['{totale}' => Sconti::massimoRinnovo()['totale']])) ?></p>
      </div>
      <a class="btn btn--sm" href="<?= b() ?>/giudizio#adesione"><?= t('Aderisci') ?></a>
    </section>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php endif; ?>
