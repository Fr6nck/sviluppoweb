<?php
/* 9U · La fascia della promo in corso (Promo novembre, mockup promo-novembre, blocco 1): «−17%» grande, titolo, testo con il
   codice e la somma con il «Giudizio con sconto», conto alla rovescia (giorni, ore, minuti: con JavaScript, assets/promo.js;
   senza resta la data del titolo) e «Approfitta della promo →», che porta il codice già inserito (/registrati?codice=… o, per
   chi è dentro, /piano?codice=…). Testi in Testi e foto (promo.*). Sparisce da sola dopo l'ultimo giorno (Sconti::promoAttiva).
   Riceve $promoR (la riga del codice) e, facoltativo, $promoPiccola (in /piano e nelle pagine per tipologia). */
use function MHW\b;
use MHW\{Support, LinguaSito, TestiSito, Sconti, Pannello, Giudizi, Auth};
if (empty($promoR)) return;
$enPr = LinguaSito::en();
$piccolaPr = !empty($promoPiccola);
$gPr = !empty($promoR['somma_giudizio']) && Giudizi::attivo() ? Giudizi::percentuale() : 0;
$codicePr = (string) $promoR['code'];
$uPr = Auth::user();
$dentroPr = $uPr && ($uPr['role'] ?? '') !== 'admin';
$hrefPr = b() . ($dentroPr ? '/piano' : LinguaSito::percorso('/registrati')) . '?codice=' . rawurlencode($codicePr);
$sostPr = ['promo' => Support::e($enPr ? Pannello::tIn('en', (string) $promoR['nome_promo']) : (string) $promoR['nome_promo']), 'percento' => (int) $promoR['value'],
           'codice' => '<code class="promo__codice">' . Support::e($codicePr) . '</code>', 'data' => Support::e(Pannello::giornoMese((string) $promoR['valid_until'], $enPr)),
           'giudizio' => $gPr, 'totale' => (int) $promoR['value'] + $gPr];
$finePr = Sconti::fine($promoR); ?>
<aside class="promo<?= $piccolaPr ? ' promo--piccola' : '' ?>" aria-labelledby="promo-titolo-<?= $piccolaPr ? 'p' : 'g' ?>" data-promo>
  <span class="promo__cifra" aria-hidden="true">−<?= (int) $promoR['value'] ?>%</span>
  <div class="promo__testo">
    <h2 class="promo__titolo" id="promo-titolo-<?= $piccolaPr ? 'p' : 'g' ?>"><?= TestiSito::html('promo.titolo', $sostPr) ?></h2>
    <p><?= TestiSito::html($gPr > 0 ? 'promo.testo' : 'promo.semplice', $sostPr) ?></p>
    <div class="promo__conto" data-promo-fine="<?= $finePr * 1000 ?>" hidden role="timer" aria-label="<?= Support::e(LinguaSito::t('Tempo che resta alla fine della promo', 'Time left until the promo ends')) ?>">
      <span><b data-promo-giorni>–</b><small><?= LinguaSito::t('giorni', 'days') ?></small></span>
      <span><b data-promo-ore>–</b><small><?= LinguaSito::t('ore', 'hours') ?></small></span>
      <span><b data-promo-min>–</b><small><?= LinguaSito::t('min', 'min') ?></small></span>
    </div>
  </div>
  <a class="btn btn--go promo__vai" href="<?= Support::e($hrefPr) ?>"><?= TestiSito::html('promo.bottone') ?> <span aria-hidden="true">→</span></a>
</aside>
<script src="<?= MHW\av('/assets/promo.js') ?>" defer></script>
