<?php use function MHW\t, MHW\tf;
/* «Hai già un sito per la tua struttura?» in «Le mie guide» (8B, mockup 2 di 3): il riquadro nero di
   MyHouse diventa la domanda. «Sì, ce l'ho» e «No, non ancora» aprono il percorso a domande della home
   (pub/_funnel_sito.php, variante compatta) in un <dialog>, già al passo giusto. Se in Impostazioni
   l'host ha già risposto (has_site), la risposta è già segnata.
   9F · Il riquadro non si chiude (niente ✕, niente cookie): resta sempre; dopo «Sì» o «No» diventa
   compatto («Hai già risposto: vuoi cambiare?»), ma c'è.
   Riceve $props (le strutture: la prima dà nome e risposta). */
use function MHW\b; use function MHW\a;
use MHW\{Icon, Csrf, Support};
$primaSito = null;
foreach ($props ?? [] as $pp) if (empty($pp['archived_at']) && (int) ($pp['is_demo'] ?? 0) < 2) { $primaSito = $pp; break; }
$haSitoBox = $primaSito && array_key_exists('has_site', $primaSito) && $primaSito['has_site'] !== null ? ((int) $primaSito['has_site'] === 1 ? 'si' : 'no') : ''; ?>
<aside class="sito-invito sito-invito--domanda<?= $haSitoBox !== '' ? ' sito-invito--compatto' : '' ?>" data-sito-invito aria-labelledby="sito-invito-titolo">
  <img class="sito-invito__logo" src="<?= Support::e(MHW\av('/assets/myhouse/myhouse-logo-bianco-breve.png')) ?>" alt="<?= MHW\Support::e(t('MyHouse')) ?>" width="140" height="32">
  <div class="sito-invito__testo">
    <?php if ($haSitoBox !== ''): ?>
      <p class="sito-invito__risposta"><?= Icon::svg('check', 14, 2.4) ?> <?= Support::e(tf('Hai già risposto: «{risposta}»', ['{risposta}' => $haSitoBox === 'si' ? t('Sì, ce l\'ho') : t('No, non ancora')])) ?></p>
      <h2 class="sito-invito__titolo" id="sito-invito-titolo"><?= t('Il sito per la tua struttura: vuoi cambiare la risposta?') ?></h2>
      <p><?= $haSitoBox === 'si' ? t('Vuoi un sito più bello, più visibile su Google o con la prenotazione diretta? Lo miglioriamo noi.') : t('Con un sito tuo gli ospiti prenotano senza commissioni. Ti aiutiamo a farlo.') ?></p>
    <?php else: ?>
      <h2 class="sito-invito__titolo" id="sito-invito-titolo"><?= t('Hai già un sito per la tua struttura?') ?></h2>
      <p><?= t('Con un sito tuo gli ospiti prenotano senza commissioni. Ti aiutiamo a farlo o a migliorarlo.') ?></p>
    <?php endif; ?>
  </div>
  <div class="sito-invito__bottoni">
    <button type="button" class="btn btn--sm sito-invito__si" data-funnel-apri="si" data-funnel-dialogo="dlg-sito-guide"<?= $haSitoBox === 'si' ? ' aria-pressed="true"' : '' ?>><?= t('Sì, ce l\'ho') ?></button>
    <button type="button" class="btn btn--sm sito-invito__no" data-funnel-apri="no" data-funnel-dialogo="dlg-sito-guide"<?= $haSitoBox === 'no' ? ' aria-pressed="true"' : '' ?>><?= t('No, non ancora') ?></button>
  </div>
  <noscript><p class="small"><a href="https://blackout.in/myhouse/?da=welcome" target="_blank" rel="noopener" style="color:#f6f0e5"><?= t('Scopri MyHouse') ?></a></p></noscript>
</aside>
<dialog class="funnel-dialogo" id="dlg-sito-guide" aria-label="<?= MHW\Support::e(t('Il sito per la tua struttura')) ?>">
  <button type="button" class="funnel-dialogo__chiudi" data-funnel-chiudi aria-label="<?= MHW\Support::e(t('Chiudi')) ?>"><?= Icon::svg('close', 18, 2) ?></button>
  <?php $funnel = ['da' => 'pannello', 'struttura' => (string) ($primaSito['name'] ?? ''), 'compatta' => true, 'id' => 'fs-guide', 'passo' => $haSitoBox];
  include __DIR__ . '/../pub/_funnel_sito.php'; ?>
</dialog>
<script src="<?= MHW\av('/assets/funnel-sito.js') ?>" defer></script>
