<?php
/* 9L · Lo sconto massimo sul rinnovo con il numero grande («55%» in serif, colore d'accento) e il testo di «Testi e foto»
   (risparmio.testo, o la variante con un solo programma acceso). «Come funziona» porta alla domanda degli inviti, che spiega
   anche il programma «Giudizio con sconto». Riceve facoltativi: $rT (il formattatore della pagina, per l'anteprima dei testi),
   $rCome (l'indirizzo di «Come funziona»), $rClasse (una classe in più). Non mostra niente se inviti e programma sono spenti. */
use function MHW\b;
use MHW\{Sconti, TestiSito};
$rM = Sconti::massimoRinnovo();
if ($rM['chiave'] !== null):
    $rSost = ['totale' => $rM['totale'] . '%', 'max' => $rM['max'] . '%', 'passo' => $rM['passo'] . '%', 'amici' => $rM['amici'], 'giudizio' => $rM['giudizio'] . '%'];
    $rTesto = isset($rT) ? $rT($rM['chiave'], $rSost) : TestiSito::html($rM['chiave'], $rSost); ?>
  <div class="risparmio<?= isset($rClasse) ? ' ' . $rClasse : '' ?>">
    <span class="risparmio__cifra" aria-hidden="true"><?= (int) $rM['totale'] ?>%</span>
    <p class="risparmio__testo"><?= $rTesto ?> <a href="<?= $rCome ?? b() . '/domande#porta-amico' ?>"><?= MHW\LinguaSito::en() ? 'How it works' : 'Come funziona' ?></a></p>
  </div>
<?php endif; unset($rT, $rCome, $rClasse); ?>
