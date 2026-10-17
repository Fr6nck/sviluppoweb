<?php
/* «Come funziona se non scrivi in italiano»: cosa vuol dire la lingua principale, con le regole del piano
   (Entitlements::regolaLingue), le traduzioni suggerite e i bottoni della guida.
   Riceve $prop (account_id, default_locale) e, facoltativo, $comeGenerico (true = la versione per «la tua lingua»,
   quando la lingua principale è ancora l'italiano). */
use MHW\{Support, I18n, Entitlements, Traduttore};
$aidC = (int) $prop['account_id'];
$locC = (string) ($prop['default_locale'] ?: 'it');
$genC = !empty($comeGenerico) || $locC === 'it';
$nomeC = I18n::nomeIt($locC);
$vocale = fn(string $n) => (bool) preg_match('/^[aeiouh]/i', $n);
$ilC = $genC ? 'la tua lingua' : ($vocale($nomeC) ? "l'" : 'il ') . $nomeC;          // «il tedesco», «l'olandese»
$dalC = $genC ? 'dalla tua lingua' : ($vocale($nomeC) ? "dall'" : 'dal ') . $nomeC;   // «dal tedesco», «dall'inglese»
$inC = $genC ? 'nella tua lingua' : 'in ' . $nomeC;
$nC = Entitlements::limit($aidC, 'locales', 1); $xC = Entitlements::limit($aidC, 'lingua_extra', 0);
$tradC = Traduttore::nelPiano($aidC);
$nExtra = count(I18n::EXTRA);
$conBottoni = array_values(array_filter(I18n::EXTRA, [I18n::class, 'interfaccia']));
$elenco = fn(array $l) => count($l) > 1 ? implode(', ', array_slice($l, 0, -1)) . ' e ' . end($l) : (string) ($l[0] ?? '');
$titoloC = $genC ? 'Scrivi in un\'altra lingua? Ecco come funziona' : 'Scrivi la guida ' . $inC . ': ecco come funziona'; ?>
<?php if ($genC): ?><details class="lingua-come"><summary><?= Support::e($titoloC) ?></summary><?php else: ?>
<section class="lingua-come lingua-come--aperta" aria-labelledby="lingua-come-titolo"><h2 id="lingua-come-titolo" class="lingua-come__titolo"><?= Support::e($titoloC) ?></h2><?php endif; ?>
  <ul class="lingua-come__voci">
    <li><b>I tuoi testi</b> restano <?= Support::e($inC) ?>, così come li scrivi: è la lingua che legge chi la sceglie, ed è quella da cui partono le traduzioni.</li>
    <li><b>Le altre lingue.</b>
      <?php if ($xC <= 0): ?>
        Il tuo piano comprende <?= Support::e($ilC) ?> e una seconda lingua, quella che vuoi<?= $genC || $locC !== 'it' ? ', anche l\'italiano' : '' ?>. Con Plus le lingue diventano sei.
      <?php elseif ($genC): ?>
        Se è una delle lingue di base (inglese, francese, tedesco, spagnolo) hai anche le altre e una lingua in più a scelta tra <?= $nExtra ?>; se è un'altra lingua, per esempio il russo o il portoghese del Brasile, occupa il posto della lingua in più e hai tutte e cinque le lingue di base.
      <?php elseif (I18n::base($locC)): ?>
        <?= Support::e(ucfirst($ilC)) ?> è una delle cinque lingue di base: hai anche le altre quattro e una lingua in più a scelta tra <?= $nExtra ?>.
      <?php else: ?>
        <?= Support::e(ucfirst($ilC)) ?> occupa il posto della lingua in più: in più hai le cinque lingue di base, cioè italiano, inglese, francese, tedesco e spagnolo.
      <?php endif; ?></li>
    <li><b>Le traduzioni.</b>
      <?php if ($tradC): ?>
        Te le suggerisce il traduttore automatico partendo <?= Support::e($dalC) ?>: nella pagina di ogni lingua tocchi «Traduci» e approvi le proposte una per una. Gli ospiti vedono solo quelle che approvi.
      <?php else: ?>
        Le scrivi tu, nella pagina di ogni lingua, accanto al testo <?= Support::e($inC) ?>. Con Plus te le suggerisce il traduttore automatico, partendo <?= Support::e($dalC) ?>.
      <?php endif; ?></li>
    <li><b>I bottoni e i titoli della guida</b>
      <?php if ($genC): ?>
        sono tradotti nelle cinque lingue di base e in <?= Support::e($elenco(array_map([I18n::class, 'nomeIt'], $conBottoni))) ?>; per le altre lingue l'ospite li legge in inglese.
      <?php elseif (I18n::interfaccia($locC)): ?>
        sono già <?= Support::e($inC) ?>: l'ospite non trova parole in italiano.
      <?php else: ?>
        <?= Support::e($inC) ?> non sono tradotti: l'ospite li legge in inglese, mentre i tuoi testi restano <?= Support::e($inC) ?>.
      <?php endif; ?></li>
    <li><b>Il pannello</b> resta in italiano. Se cambi la lingua principale, i testi già scritti restano nella lingua in cui li hai scritti e diventano una traduzione.</li>
  </ul>
<?php if ($genC): ?></details><?php else: ?></section><?php endif; ?>
