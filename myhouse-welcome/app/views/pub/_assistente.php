<?php
/* 10 · «Chiedi alla guida» in home: cosa fa, tre punti, e una finta chat con la domanda e la risposta d'esempio
   (il check-out della guida vetrina, se c'è: nessun dato inventato). Riceve $T, $L, $demo, $en, $U dalla home. */
use MHW\{Support, Icon, Assistente, TestiSito};
$oraDemo = preg_match('/^\d{1,2}:\d{2}$/', (string) ($demo['checkout_by'] ?? '')) ? (string) $demo['checkout_by'] : '';
$sostA = ['domande' => number_format(Assistente::limite(0), 0, $en ? '.' : ',', $en ? ',' : '.')];
$punti = TestiSito::lista('assistente.punti', [], $en ? 'en' : 'it'); ?>
<section id="chiedi-alla-guida" class="assistente" aria-labelledby="assistente-titolo">
  <div class="assistente__testa">
    <span class="kicker"><?= $T('assistente.kicker') ?></span>
    <h2 id="assistente-titolo" class="h-sezione"><?= $T('assistente.titolo') ?></h2>
    <p class="muted"><?= $T('assistente.testo') ?></p>
    <ul class="spunte"<?= $L('assistente.punti') ?>>
      <?php foreach ($punti as $riga): ?>
        <li><?= Icon::svg('check', 18, 2) ?><span><?= $riga ?></span></li>
      <?php endforeach; ?>
    </ul>
    <p class="small muted assistente__nota"><?= $T('assistente.nota', $sostA) ?></p>
  </div>
  <?php if ($oraDemo !== ''): ?>
  <figure class="assistente__chat" role="img" aria-label="<?= Support::e($U('Esempio: un ospite chiede l\'ora del check-out e la guida risponde con l\'orario scritto dall\'host', 'Example: a guest asks the check-out time and the guide answers with the time the host wrote')) ?>">
    <div class="assistente__fumetti" aria-hidden="true">
      <div class="chiedi__msg chiedi__msg--tu"><?= $T('assistente.esempio.d') ?></div>
      <div class="chiedi__msg chiedi__msg--guida"><?= $T('assistente.esempio.r', ['ora' => Support::e($oraDemo)]) ?></div>
      <span class="chiedi__fonte"><?= Icon::svg('book', 14) ?><?= $T('assistente.esempio.fonte') ?></span>
    </div>
    <figcaption class="small muted"><?= Support::e($U('Risposte automatiche, dalle informazioni scritte dall\'host.', 'Automatic answers, from the information written by the host.')) ?></figcaption>
  </figure>
  <?php endif; ?>
</section>
