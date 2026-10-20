<?php
/* 9M · Il riquadro dello sconto sul rinnovo, componente a sé (questionario e /piu-strutture): la riga del 55% («Testi e foto»,
   risparmio.*, con «Come funziona») e, sotto, «Primo anno {prezzo}, dal rinnovo con il 55%:» con il prezzo barrato, il prezzo
   finale grande e la scala (Nessuno sconto / 2, 5, 10 amici / 10 amici + giudizio) con la nota. Solo gli sconti accesi:
   inviti (fino a Inviti::MASSIMO) e «Giudizio con sconto». Niente se sono spenti tutti e due.
   Riceve: $rnPieno (il prezzo pieno in centesimi), facoltativo $rnCampo (l'id del campo «Quante strutture?» che lo aggiorna:
   prezzi.js; nel questionario lo aggiorna questionario.js). */
use function MHW\b;
use MHW\{Support, TestiSito, Inviti, Giudizi, LinguaSito};
$rnU = [LinguaSito::class, 't']; $rnEn = LinguaSito::en();
$rnInviti = Inviti::disponibili(); $rnGiudizio = Giudizi::attivo();
$rnTot = ($rnInviti ? Inviti::MASSIMO : 0) + ($rnGiudizio ? Giudizi::percentuale() : 0);
if ($rnTot > 0):
    $rnAmici = (int) (Inviti::MASSIMO / max(1, Inviti::PASSO));
    $rnRighe = [];   // [etichetta, percento]
    if ($rnInviti) foreach ([2, 5, $rnAmici] as $n) $rnRighe[] = [$n . $rnU(' amici · ', ' friends · ') . min($n * Inviti::PASSO, Inviti::MASSIMO) . '%', min($n * Inviti::PASSO, Inviti::MASSIMO)];
    if ($rnGiudizio) $rnRighe[] = [($rnInviti ? $rnAmici . $rnU(' amici + giudizio', ' friends + review') : $rnU('Giudizio', 'Review')) . ' · ' . $rnTot . '%', $rnTot];
    $rClasse = 'questionario__sconto'; $rCome = b() . LinguaSito::percorso('/domande') . '#porta-amico'; include __DIR__ . '/_risparmio.php'; /* 9L: lo stesso riquadro della home */ ?>
  <div class="questionario__rinnovo" data-q-rinnovo<?= !empty($rnCampo) ? ' data-rinnovo data-campo="' . Support::e($rnCampo) . '"' : '' ?>>
    <span><?= $rnU('Primo anno', 'First year') ?> <b data-q-anno><?= Support::e(Support::money($rnPieno)) ?></b>, <?= $rnEn ? 'from the renewal with ' . (int) $rnTot . '% off' : 'dal rinnovo con il ' . (int) $rnTot . '%' ?>:<br><s class="muted" data-q-anno-s><?= Support::e(Support::money($rnPieno)) ?></s></span>
    <b class="questionario__rinnovo-v" data-q-min><?= Support::e(Support::money((int) round($rnPieno * (100 - $rnTot) / 100))) ?></b>
    <table><tbody>
      <tr><td><?= $rnU('Nessuno sconto', 'No discount') ?></td><td data-q-perc="0"><?= Support::e(Support::money($rnPieno)) ?></td></tr>
      <?php foreach ($rnRighe as [$et, $perc]): ?><tr><td><?= Support::e($et) ?></td><td data-q-perc="<?= (int) $perc ?>"><?= Support::e(Support::money((int) round($rnPieno * (100 - $perc) / 100))) ?></td></tr><?php endforeach; ?>
    </tbody></table>
    <small><?= $rnInviti ? TestiSito::html('questionario.rinnovo.nota') : $rnU('Prezzi + IVA, all\'anno.', 'Prices + VAT, per year.') ?></small>
  </div>
<?php endif; unset($rnCampo); ?>
