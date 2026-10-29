<?php
/* 10 · 9T · Il Concierge AI in home (mockup concierge-come, blocco 1): il nome con il badge «AI», cosa fa, tre punti, la
   nota del piano, «Provalo sulla demo» (Casa Checco in una nuova scheda, con la finestra del Concierge già aperta: solo
   se sulla vetrina è acceso) e una finta chat in tedesco con il check-out della guida vetrina, se c'è (nessun dato
   inventato). Riceve $T, $L, $demo, $en, $U dalla home. */
use function MHW\b;
use MHW\{Support, Icon, Assistente, TestiSito};
$oraDemo = preg_match('/^\d{1,2}:\d{2}$/', (string) ($demo['checkout_by'] ?? '')) ? (string) $demo['checkout_by'] : '';
$sostA = ['domande' => number_format(Assistente::limite(0), 0, $en ? '.' : ',', $en ? ',' : '.')];
$punti = TestiSito::lista('assistente.punti', [], $en ? 'en' : 'it');
$provaDemo = $demo && Assistente::perGuida($demo) ? b() . '/g/' . rawurlencode((string) $demo['slug']) . '?l=' . ($en ? 'en' : 'it') . '#concierge' : ''; ?>
<section id="concierge-ai" class="assistente" aria-labelledby="assistente-titolo">
  <div class="assistente__testa">
    <span class="kicker kicker--ai"><?= $T('assistente.kicker') ?> <span class="badge-ai">AI</span></span>
    <h2 id="assistente-titolo" class="h-sezione"><?= $T('assistente.titolo') ?></h2>
    <p class="muted assistente__lead"><?= $T('assistente.testo') ?></p>
    <ul class="spunte"<?= $L('assistente.punti') ?>>
      <?php foreach ($punti as $riga): ?>
        <li><?= Icon::svg('check', 18, 2) ?><span><?= $riga ?></span></li>
      <?php endforeach; ?>
    </ul>
    <p class="small muted assistente__nota"><?= $T('assistente.nota', $sostA) ?></p>
    <?php if ($provaDemo !== ''): ?>
      <p class="assistente__azioni"><a class="btn" href="<?= Support::e($provaDemo) ?>" target="_blank" rel="noopener"><?= $T('assistente.demo') ?> <?= Icon::svg('external', 15, 2) ?><span class="sr-only"> <?= Support::e($U('(si apre in una nuova scheda)', '(opens in a new tab)')) ?></span></a></p>
    <?php endif; ?>
  </div>
  <?php if ($oraDemo !== ''): ?>
  <figure class="assistente__chat" role="img" aria-label="<?= Support::e($U('Esempio: un ospite chiede in tedesco l\'ora del check-out e il Concierge AI risponde in tedesco con l\'orario scritto dall\'host', 'Example: a guest asks the check-out time in German and Concierge AI answers in German with the time the host wrote')) ?>">
    <div class="assistente__fumetti" aria-hidden="true">
      <div class="chiedi__msg chiedi__msg--tu"><?= $T('assistente.esempio.d') ?></div>
      <div class="chiedi__msg chiedi__msg--guida"><?= $T('assistente.esempio.r', ['ora' => Support::e($oraDemo)]) ?></div>
      <span class="chiedi__fonte"><?= Icon::svg('book', 14) ?><?= $T('assistente.esempio.fonte') ?></span>
    </div>
    <figcaption class="small muted"><?= $T('assistente.esempio.nota') ?></figcaption>
  </figure>
  <?php endif; ?>
</section>
