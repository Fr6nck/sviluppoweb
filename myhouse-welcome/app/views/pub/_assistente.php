<?php
/* 10 · 9T · Il Concierge AI in home (mockup concierge-come, blocco 1): il nome con il badge «AI», cosa fa, tre punti, la
   nota del piano, «Provalo sulla demo» (Casa Checco in una nuova scheda, con la finestra del Concierge già aperta: solo
   se sulla vetrina è acceso) e una finta chat con il check-out della guida vetrina, se c'è (nessun dato inventato).
   9U · La chat è nella lingua della pagina (Testi e foto) e poi passa da sola alle altre lingue della guida (tedesco,
   francese, spagnolo e l'altra lingua del sito: assets/chat-lingue.js), con le frasi della guida ospite e il titolo della
   sezione tradotto; senza JavaScript o con «riduci il movimento» resta nella lingua della pagina.
   Riceve $T, $L, $demo, $en, $U dalla home. */
use function MHW\b;
use MHW\{Support, Icon, Assistente, TestiSito};
$oraDemo = preg_match('/^\d{1,2}:\d{2}$/', (string) ($demo['checkout_by'] ?? '')) ? (string) $demo['checkout_by'] : '';
$sostA = ['domande' => number_format(Assistente::limite(0), 0, $en ? '.' : ',', $en ? ',' : '.')];
$punti = TestiSito::lista('assistente.punti', [], $en ? 'en' : 'it');
// Le altre lingue: la domanda e «Dalla sezione…» della guida ospite (lang/*.php), la risposta con l'orario della vetrina.
$risposteA = ['it' => 'Entro le %s.', 'en' => 'By %s.', 'de' => 'Bis %s Uhr.', 'fr' => 'Avant %s.', 'es' => 'Antes de las %s.'];
$snapA = $demo && $oraDemo !== '' ? MHW\Guide::published((int) $demo['id']) : null;
$checkinA = null; foreach ((array) ($snapA['sections'] ?? []) as $sA) if (($sA['kind'] ?? '') === 'checkin') { $checkinA = $sA; break; }
$variantiA = [];
foreach (array_values(array_diff(['de', 'fr', 'es', 'en', 'it'], [$en ? 'en' : 'it'])) as $lA) {
    $titA = $checkinA ? MHW\Guide::title($checkinA, $lA, (string) ($snapA['property']['default_locale'] ?? 'it')) : 'Check-in & Check-out';
    $variantiA[] = ['l' => $lA, 'd' => MHW\I18n::t($lA, 'ask_eg_checkout'), 'r' => sprintf($risposteA[$lA], $oraDemo), 'f' => MHW\I18n::t($lA, 'ask_source', $titA)];
}
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
  <figure class="assistente__chat" role="img" data-chat-lingue="<?= Support::e(json_encode($variantiA, JSON_UNESCAPED_UNICODE)) ?>" aria-label="<?= Support::e($U('Esempio: un ospite chiede l\'ora del check-out nella sua lingua e il Concierge AI risponde nella stessa lingua con l\'orario scritto dall\'host', 'Example: a guest asks the check-out time in their own language and Concierge AI answers in the same language with the time the host wrote')) ?>">
    <div class="assistente__fumetti" aria-hidden="true" lang="<?= $en ? 'en' : 'it' ?>">
      <div class="chiedi__msg chiedi__msg--tu" data-chat-d><?= $T('assistente.esempio.d') ?></div>
      <div class="chiedi__msg chiedi__msg--guida" data-chat-r><?= $T('assistente.esempio.r', ['ora' => Support::e($oraDemo)]) ?></div>
      <span class="chiedi__fonte"><?= Icon::svg('book', 14) ?><span data-chat-f><?= $T('assistente.esempio.fonte') ?></span></span>
    </div>
    <figcaption class="small muted"><?= $T('assistente.esempio.nota') ?></figcaption>
  </figure>
  <script src="<?= MHW\av('/assets/chat-lingue.js') ?>" defer></script>
  <?php endif; ?>
</section>
