<?php
/* «Anche il tuo sito» a domande (8A): la scheda chiara con i tre passi, riutilizzata nel pannello (8B).
     1  Hai già un sito? → Sì / No
     2  Sì: cosa migliorare (scelte multiple, «Altro» con due parole) · No: cosa facciamo
     3  Come preferisci sentirci? Il riepilogo del messaggio e tre bottoni: Scopri MyHouse, WhatsApp, Email.
   Al clic su uno dei tre bottoni assets/funnel-sito.js manda un segnale a /sito/interesse
   (sendBeacon, con il token CSRF della pagina): la risposta resta in Amministrazione → Prospetti.
   Senza JavaScript si vedono solo i tre bottoni finali, con un messaggio di serie.

   Riceve $funnel: ['da' => home|pannello|impostazioni, 'struttura' => nome (nel pannello),
   'passo' => ''|si|no (parte già risposto), 'compatta' => bool, 'id' => prefisso degli id]. */
use function MHW\b;
use MHW\{Support, Icon, Csrf, Config, TestiSito};
$fn = ($funnel ?? []) + ['da' => 'home', 'struttura' => '', 'passo' => '', 'compatta' => false, 'id' => 'fs'];
$Tf = $T ?? fn(string $k, array $sost = []) => TestiSito::html($k, $sost);
$tx = fn(string $k, array $sost = []) => TestiSito::testo($k, $sost);
$email = Support::contatto();
$waNum = preg_replace('/\D/', '', (string) ((Config::get('legal') ?? [])['contact_whatsapp'] ?? ''));
$telVisto = (string) ((Config::get('legal') ?? [])['contact_phone'] ?? '');
$inizio = $fn['struttura'] !== '' ? $tx('sito2.msg.pannello', ['struttura' => Support::e($fn['struttura'])]) : $tx('sito2.msg.home');
$bisogni = ['aspetto' => 'sito2.b.aspetto', 'google' => 'sito2.b.google', 'dirette' => 'sito2.b.dirette', 'altro' => 'sito2.b.altro'];
$id = preg_replace('/[^a-z0-9-]/', '', (string) $fn['id']) ?: 'fs';
// Il messaggio di serie (senza JavaScript): la prima frase e la domanda finale.
$msgSerie = html_entity_decode($inizio . ' ' . $tx('sito2.msg.fine'), ENT_QUOTES, 'UTF-8');
$scopri = 'https://blackout.in/myhouse/?da=welcome';
// 8J: le risposte vanno a Blackout Agency: lo dice la riga sotto i bottoni, con il link all'informativa.
$linkPrivacy = '<a href="' . b() . MHW\LinguaSito::percorso('/privacy') . '#trattamento-8">' . (MHW\LinguaSito::en() ? 'Privacy policy' : 'Informativa privacy') . '</a>'; ?>
<div class="funnel funnel--nojs<?= $fn['compatta'] ? ' funnel--compatta' : '' ?>" data-funnel data-da="<?= Support::e($fn['da']) ?>"
     data-passo-iniziale="<?= in_array($fn['passo'], ['si', 'no'], true) ? $fn['passo'] : '' ?>"
     data-url="<?= b() ?>/sito/interesse" data-csrf="<?= Support::e(Csrf::token()) ?>" data-scopri="<?= Support::e($scopri) ?>"
     data-wa="<?= Support::e($waNum) ?>" data-email="<?= Support::e($email) ?>" data-oggetto="<?= $tx('sito2.oggetto') ?>"
     data-msg-inizio="<?= $inizio ?>" data-msg-si="<?= $tx('sito2.msg.si') ?>" data-msg-no="<?= $tx('sito2.msg.no') ?>" data-msg-fine="<?= $tx('sito2.msg.fine') ?>">

  <div class="funnel__passo" data-passo="1">
    <span class="funnel__conta"><?= $Tf('sito2.passo1') ?></span>
    <h3 class="funnel__domanda" id="<?= $id ?>-d1"><?= $Tf('sito2.domanda1') ?></h3>
    <div class="funnel__scelte" role="group" aria-labelledby="<?= $id ?>-d1">
      <button type="button" class="funnel__scelta" data-ha="si"><b><?= $Tf('sito2.si.titolo') ?></b><span><?= $Tf('sito2.si.sotto') ?></span></button>
      <button type="button" class="funnel__scelta" data-ha="no"><b><?= $Tf('sito2.no.titolo') ?></b><span><?= $Tf('sito2.no.sotto') ?></span></button>
    </div>
    <p class="funnel__subito"><?= $Tf('sito2.subito') ?>
      <?php if ($email !== ''): ?><a href="mailto:<?= Support::e($email) ?>?subject=<?= rawurlencode(html_entity_decode($tx('sito2.oggetto'), ENT_QUOTES, 'UTF-8')) ?>"><?= Support::e($email) ?></a><?php endif; ?>
      <?php if ($waNum !== ''): ?> · <a href="https://wa.me/<?= Support::e($waNum) ?>" target="_blank" rel="noopener">WhatsApp <?= Support::e($telVisto) ?></a><?php endif; ?></p>
  </div>

  <div class="funnel__passo" data-passo="si" hidden>
    <span class="funnel__conta"><?= $Tf('sito2.passo2') ?></span>
    <h3 class="funnel__domanda" id="<?= $id ?>-d2"><?= $Tf('sito2.domanda2si') ?></h3>
    <div class="funnel__pillole" role="group" aria-labelledby="<?= $id ?>-d2">
      <?php foreach ($bisogni as $cod => $chiave): ?>
        <label class="funnel__pillola"><input type="checkbox" value="<?= $cod ?>" data-bisogno><span><?= $Tf($chiave) ?></span></label>
      <?php endforeach; ?>
    </div>
    <div class="funnel__altro" data-altro hidden>
      <label for="<?= $id ?>-altro"><?= $Tf('sito2.altro') ?></label>
      <input type="text" id="<?= $id ?>-altro" maxlength="200" autocomplete="off">
    </div>
    <div class="funnel__azioni">
      <button type="button" class="funnel__indietro" data-vai="1"><?= $Tf('sito2.indietro') ?></button>
      <button type="button" class="btn funnel__avanti" data-vai="3"><?= $Tf('sito2.continua') ?></button>
    </div>
  </div>

  <div class="funnel__passo" data-passo="no" hidden>
    <span class="funnel__conta"><?= $Tf('sito2.passo2') ?></span>
    <h3 class="funnel__domanda"><?= $Tf('sito2.domanda2no') ?></h3>
    <ul class="funnel__punti"<?= isset($L) ? $L('sito2.punti') : '' ?>>
      <?php foreach (TestiSito::lista('sito2.punti') as $v): ?><li><?= Icon::svg('check', 16, 2.2) ?><?= $v ?></li><?php endforeach; ?>
    </ul>
    <div class="funnel__azioni">
      <button type="button" class="funnel__indietro" data-vai="1"><?= $Tf('sito2.indietro') ?></button>
      <button type="button" class="btn funnel__avanti" data-vai="3"><?= $Tf('sito2.proposta') ?></button>
    </div>
  </div>

  <div class="funnel__passo" data-passo="3" hidden>
    <span class="funnel__conta"><?= $Tf('sito2.passo3') ?></span>
    <h3 class="funnel__domanda" tabindex="-1"><?= $Tf('sito2.domanda3') ?></h3>
    <blockquote class="funnel__messaggio" data-messaggio></blockquote>
    <div class="funnel__contatti">
      <a class="btn funnel__scopri" href="<?= Support::e($scopri) ?>" target="_blank" rel="noopener" data-canale="scopri"><?= $Tf('sito2.scopri') ?> <?= Icon::svg('external', 15, 2) ?></a>
      <?php if ($waNum !== ''): ?><a class="btn funnel__wa" href="https://wa.me/<?= Support::e($waNum) ?>" target="_blank" rel="noopener" data-canale="whatsapp"><?= Icon::svg('whatsapp', 17) ?><?= $Tf('sito2.whatsapp') ?></a><?php endif; ?>
      <?php if ($email !== ''): ?><a class="btn btn--ghost funnel__email" href="mailto:<?= Support::e($email) ?>" data-canale="email"><?= Icon::svg('message', 17) ?><?= $Tf('sito2.email') ?></a><?php endif; ?>
    </div>
    <p class="funnel__nota"><?= $Tf('sito2.risposta') ?></p>
    <p class="funnel__nota funnel__privacy small"><?= $Tf('sito2.privacy', ['informativa' => $linkPrivacy]) ?></p>
    <button type="button" class="funnel__indietro" data-vai="2"><?= $Tf('sito2.indietro') ?></button>
  </div>

  <?php /* Senza JavaScript: i tre bottoni con il messaggio di serie. */ ?>
  <div class="funnel__senza-js">
    <h3 class="funnel__domanda"><?= $Tf('sito2.domanda3') ?></h3>
    <div class="funnel__contatti">
      <a class="btn funnel__scopri" href="<?= Support::e($scopri) ?>" target="_blank" rel="noopener"><?= $Tf('sito2.scopri') ?> <?= Icon::svg('external', 15, 2) ?></a>
      <?php if ($waNum !== ''): ?><a class="btn funnel__wa" href="https://wa.me/<?= Support::e($waNum) ?>?text=<?= rawurlencode($msgSerie) ?>" target="_blank" rel="noopener"><?= Icon::svg('whatsapp', 17) ?><?= $Tf('sito2.whatsapp') ?></a><?php endif; ?>
      <?php if ($email !== ''): ?><a class="btn btn--ghost funnel__email" href="mailto:<?= Support::e($email) ?>?subject=<?= rawurlencode(html_entity_decode($tx('sito2.oggetto'), ENT_QUOTES, 'UTF-8')) ?>&amp;body=<?= rawurlencode($msgSerie) ?>"><?= Icon::svg('message', 17) ?><?= $Tf('sito2.email') ?></a><?php endif; ?>
    </div>
    <p class="funnel__nota"><?= $Tf('sito2.risposta') ?></p>
    <p class="funnel__nota funnel__privacy small"><?= $Tf('sito2.privacy', ['informativa' => $linkPrivacy]) ?></p>
  </div>
</div>
