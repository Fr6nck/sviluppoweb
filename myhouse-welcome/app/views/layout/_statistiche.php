<?php
/* 8H · Statistiche delle visite e banner del consenso, in fondo alle pagine pubbliche che contano
   (Statistiche::qui(): mai nelle guide, nel pannello e in Amministrazione). Lo script è assets/statistiche.js:
   Matomo parte subito (senza cookie); Google Analytics e Clarity solo dopo il consenso. Il banner c'è solo se
   GA4 o Clarity sono configurati; i testi sono in Testi e foto → «Cookie e consenso». */
use function MHW\b;
use MHW\{Statistiche, TestiSito, Support, LinguaSito};
$cS = Statistiche::config();
$datiS = ['matomo' => Statistiche::matomo() ? ['url' => $cS['matomo_url'], 'id' => $cS['matomo_id']] : null,
          'ga4' => $cS['ga4'] !== '' ? $cS['ga4'] : null, 'clarity' => $cS['clarity'] !== '' ? $cS['clarity'] : null,
          'eventi' => Statistiche::prendiEventi(), 'cookie' => Statistiche::COOKIE, 'durata' => Statistiche::DURATA,
          'percorso' => Support::baseDir() . '/']; ?>
<script type="application/json" id="mhw-statistiche"><?= json_encode($datiS, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) ?></script>
<?php if (Statistiche::consensoServe()): ?>
<section class="consenso" id="consenso" aria-labelledby="consenso-testo" hidden>
  <div class="consenso__dentro">
    <p class="consenso__testo" id="consenso-testo"><?= TestiSito::html('consenso.testo', ['finalita' => Support::e(Statistiche::finalita(LinguaSito::en())), 'strumenti' => Support::e(Statistiche::strumenti(LinguaSito::en()))]) ?>
      <a href="<?= b() ?>/privacy#cookie"><?= TestiSito::html('consenso.link') ?></a></p>
    <div class="consenso__scelte" id="consenso-scelte" hidden>
      <?php if ($cS['ga4'] !== ''): ?>
        <label class="interruttore-check"><input type="checkbox" role="switch" data-consenso="s">
          <span class="interruttore-check__testo"><b><?= TestiSito::html('consenso.statistiche') ?></b> <span class="small muted"><?= TestiSito::html('consenso.statistiche.nota') ?></span></span></label>
      <?php endif; ?>
      <?php if ($cS['clarity'] !== ''): ?>
        <label class="interruttore-check"><input type="checkbox" role="switch" data-consenso="a">
          <span class="interruttore-check__testo"><b><?= TestiSito::html('consenso.analisi') ?></b> <span class="small muted"><?= TestiSito::html('consenso.analisi.nota') ?></span></span></label>
      <?php endif; ?>
    </div>
    <div class="consenso__bottoni">
      <?php /* «Accetta» e «Rifiuta» con lo stesso peso: stesso stile, stessa misura. */ ?>
      <button type="button" class="btn btn--sm consenso__pari" data-consenso-azione="rifiuta"><?= TestiSito::html('consenso.rifiuta') ?></button>
      <button type="button" class="btn btn--sm consenso__pari" data-consenso-azione="accetta"><?= TestiSito::html('consenso.accetta') ?></button>
      <button type="button" class="btn btn--ghost btn--sm" data-consenso-azione="scegli" aria-expanded="false" aria-controls="consenso-scelte"><?= TestiSito::html('consenso.scegli') ?></button>
      <button type="button" class="btn btn--sm consenso__pari" data-consenso-azione="salva" hidden><?= TestiSito::html('consenso.salva') ?></button>
    </div>
    <button type="button" class="consenso__x" data-consenso-azione="chiudi" aria-label="<?= TestiSito::testo('consenso.chiudi') ?>">×</button>
  </div>
</section>
<?php endif; ?>
<script src="<?= MHW\av('/assets/statistiche.js') ?>" defer></script>
