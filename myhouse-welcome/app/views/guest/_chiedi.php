<?php
/* 10 · «Chiedi alla guida»: il bottone nella barra in fondo e la finestra della chat (un <dialog>, come «Chi ti
   accoglie»). Con JavaScript le domande partono da qui (assets/guida.js, POST …/chiedi in JSON); senza, il bottone
   è un link alla pagina …/chiedi?l=… con lo stesso modulo. Niente cookie, niente dati salvati nel telefono: gli
   scambi restano nella pagina. Riceve: $chiedi (Assistente::datiGuida), $pr, $loc, $base. Da _contatti.php. */
use MHW\{Support, Icon, I18n};
if (empty($chiedi)) return;
$urlChiedi = (string) $chiedi['url'];
$nomeHost = (string) ($chiedi['host'] ?? '');
$testi = ['thinking' => I18n::t($loc, 'ask_thinking'), 'source' => I18n::t($loc, 'ask_source', '%s'), 'open' => I18n::t($loc, 'ask_open_section'),
          'contact' => $nomeHost !== '' ? I18n::t($loc, 'ask_contact', $nomeHost) : I18n::t($loc, 'ask_contact_plain'), 'error' => I18n::t($loc, 'ask_error'),
          'you' => I18n::t($loc, 'ask_you'), 'answer' => I18n::t($loc, 'ask_answer'), 'base' => $base, 'lingua' => $loc]; ?>
<a class="btn btn--ghost chiedi-apri" href="<?= Support::e($urlChiedi) ?>?l=<?= Support::e($loc) ?>" data-chiedi-apri="chiedi-alla-guida" aria-haspopup="dialog"><?= Icon::svg('message', 17) ?><?= Support::e(I18n::t($loc, 'ask_guide')) ?></a>
<dialog class="chiedi" id="chiedi-alla-guida" aria-labelledby="chiedi-titolo" data-chiedi data-url="<?= Support::e($urlChiedi) ?>"
        data-contatti-href="<?= Support::e((string) ($chiedi['contatto_href'] ?? '')) ?>" data-testi="<?= Support::e(json_encode($testi, JSON_UNESCAPED_UNICODE)) ?>">
  <div class="chiedi__dentro">
    <div class="chiedi__testa">
      <span class="chiedi__ico" aria-hidden="true"><?= Icon::svg('message', 20, 1.7) ?></span>
      <div class="chiedi__titoli"><h2 class="chiedi__titolo" id="chiedi-titolo"><?= Support::e(I18n::t($loc, 'ask_guide')) ?></h2>
        <p class="chiedi__intro"><?= Support::e(I18n::t($loc, 'ask_intro')) ?></p></div>
      <button type="button" class="chiedi__chiudi" data-chiedi-chiudi aria-label="<?= Support::e(I18n::t($loc, 'close')) ?>"><?= Icon::svg('close', 18, 2) ?></button>
    </div>
    <div class="chiedi__msgs" role="log" aria-live="polite" aria-relevant="additions" data-chiedi-msgs></div>
    <?php if (!empty($chiedi['esempi'])): ?>
      <div class="chiedi__esempi" data-chiedi-esempi>
        <span class="small muted"><?= Support::e(I18n::t($loc, 'ask_examples')) ?></span>
        <?php foreach ($chiedi['esempi'] as $k): ?><button type="button" class="chiedi__chip" data-esempio><?= Support::e(I18n::t($loc, $k)) ?></button><?php endforeach; ?>
      </div>
    <?php endif; ?>
    <form class="chiedi__form" method="get" action="<?= Support::e($urlChiedi) ?>" data-chiedi-form>
      <input type="hidden" name="l" value="<?= Support::e($loc) ?>">
      <label class="sr-only" for="chiedi-q"><?= Support::e(I18n::t($loc, 'ask_placeholder')) ?></label>
      <input id="chiedi-q" name="q" type="text" maxlength="<?= MHW\Assistente::DOMANDA_MAX ?>" required autocomplete="off" enterkeyhint="send" placeholder="<?= Support::e(I18n::t($loc, 'ask_placeholder')) ?>">
      <button class="btn chiedi__invia" aria-label="<?= Support::e(I18n::t($loc, 'ask_send')) ?>"><?= Icon::svg('arrow', 20, 2) ?></button>
    </form>
    <p class="chiedi__nota"><?= Support::e(I18n::t($loc, 'ask_note')) ?></p>
  </div>
</dialog>
