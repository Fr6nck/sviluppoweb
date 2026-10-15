<?php
/* «Hai già un sito per la tua struttura?» in «Le mie guide» (8B, mockup 2 di 3): il riquadro nero di
   MyHouse diventa la domanda. «Sì, ce l'ho» e «No, non ancora» aprono il percorso a domande della home
   (pub/_funnel_sito.php, variante compatta) in un <dialog>, già al passo giusto. Se in Impostazioni
   l'host ha già risposto (has_site), la risposta è già segnata. La ✕ («Non ora») lo nasconde per 30
   giorni in questo browser; «Non mostrare più» lascia il cookie tecnico mhw_no_sito per 180 giorni.
   Riceve $props (le strutture: la prima dà nome e risposta). */
use function MHW\b; use function MHW\a;
use MHW\{Icon, Csrf, Support};
if (!empty($_COOKIE['mhw_no_sito'])) return;
$primaSito = null;
foreach ($props ?? [] as $pp) if (empty($pp['archived_at']) && (int) ($pp['is_demo'] ?? 0) < 2) { $primaSito = $pp; break; }
$haSitoBox = $primaSito && array_key_exists('has_site', $primaSito) && $primaSito['has_site'] !== null ? ((int) $primaSito['has_site'] === 1 ? 'si' : 'no') : ''; ?>
<aside class="sito-invito sito-invito--domanda" data-sito-invito aria-labelledby="sito-invito-titolo">
  <button type="button" class="sito-invito__chiudi" data-sito-chiudi aria-label="Non ora: nascondi per 30 giorni" hidden><?= Icon::svg('close', 18, 2) ?></button>
  <img class="sito-invito__logo" src="<?= Support::e(a('/assets/myhouse/myhouse-logo-bianco.png')) ?>" alt="MyHouse" width="98" height="32">
  <div class="sito-invito__testo">
    <h2 class="sito-invito__titolo" id="sito-invito-titolo">Hai già un sito per la tua struttura?</h2>
    <p>Con un sito tuo gli ospiti prenotano senza commissioni. Ti aiutiamo a farlo o a migliorarlo.</p>
  </div>
  <div class="sito-invito__bottoni">
    <button type="button" class="btn btn--sm sito-invito__si" data-funnel-apri="si" data-funnel-dialogo="dlg-sito-guide"<?= $haSitoBox === 'si' ? ' aria-pressed="true"' : '' ?>>Sì, ce l'ho</button>
    <button type="button" class="btn btn--sm sito-invito__no" data-funnel-apri="no" data-funnel-dialogo="dlg-sito-guide"<?= $haSitoBox === 'no' ? ' aria-pressed="true"' : '' ?>>No, non ancora</button>
  </div>
  <form method="post" action="<?= b() ?>/pannello/sito/nascondi" class="sito-invito__mai"><?= Csrf::field() ?>
    <button class="linkbtn">Non mostrare più</button></form>
  <noscript><p class="small"><a href="https://blackout.in/myhouse/?da=welcome" target="_blank" rel="noopener" style="color:#f6f0e5">Scopri MyHouse</a></p></noscript>
</aside>
<dialog class="funnel-dialogo" id="dlg-sito-guide" aria-label="Il sito per la tua struttura">
  <button type="button" class="funnel-dialogo__chiudi" data-funnel-chiudi aria-label="Chiudi"><?= Icon::svg('close', 18, 2) ?></button>
  <?php $funnel = ['da' => 'pannello', 'struttura' => (string) ($primaSito['name'] ?? ''), 'compatta' => true, 'id' => 'fs-guide', 'passo' => $haSitoBox];
  include __DIR__ . '/../pub/_funnel_sito.php'; ?>
</dialog>
<script src="<?= MHW\av('/assets/funnel-sito.js') ?>" defer></script>
