<?php
/* 9N · 10 bis · La pagina dell'offerta su misura (/offerta/{token}): strutture, prezzo + IVA, «stesso prezzo anche al
   rinnovo», scadenza, «Accetta e paga». Senza account: prima la registrazione (o l'accesso), poi si torna qui.
   Riceve $o (l'offerta), $utente (l'host dentro, o null), $acc, $fatturazioneOk. Niente indicizzazione (nessuna $seoPagina). */
use function MHW\b;
use MHW\{Support, Csrf};
$en = $o['lingua'] === 'en';
$U = fn(string $it, string $inglese) => $en ? $inglese : $it;
$title = $U('La tua offerta su misura', 'Your tailored offer');
$n = (int) $o['strutture'];
$data = $en ? gmdate('j F Y', (int) strtotime($o['valida_fino'])) : Support::date($o['valida_fino']);
$aperta = $o['stato'] === 'inviata'; ?>
<div class="stack stack--lg" style="max-width:860px;margin:0 auto">
  <div class="grid grid-2" style="align-items:start">
    <section class="panel stack" aria-labelledby="off-titolo">
      <span class="kicker" id="off-titolo"><?= $U('La tua offerta Portfolio su misura', 'Your tailored Portfolio offer') ?></span>
      <p style="margin:0"><b style="font-size:48px;line-height:1"><?= Support::e(Support::money((int) $o['prezzo_cents'])) ?></b>
        <span class="muted"><?= $U('+ IVA / anno per', '+ VAT / year for') ?> <?= $n ?> <?= $U('strutture', 'properties') ?></span></p>
      <ul class="stack small" style="gap:6px;margin:0;padding-left:20px">
        <li><?= $U('Una guida e un QR per ogni struttura', 'One guide and one QR code per property') ?></li>
        <li><?= $U('Tutto Portfolio: traduzioni comprese, scheda campione, statistiche', 'All of Portfolio: translations included, master guide, statistics') ?></li>
        <li><?= $o['modo'] === 'nuovo' ? $U('Stesso prezzo anche al rinnovo', 'Same price at renewal') : ($o['modo'] === 'subito' ? $U('Sostituisce subito l\'offerta di adesso: paghi la differenza fino al rinnovo', 'Replaces your current offer now: you pay the difference until renewal') : $U('Vale dal prossimo rinnovo', 'Applies from your next renewal')) ?></li>
      </ul>
      <?php if (trim((string) $o['note']) !== ''): ?><p class="small" style="margin:0;white-space:pre-line"><?= Support::e((string) $o['note']) ?></p><?php endif; ?>
      <?php if ($aperta): ?>
        <p class="small muted" style="margin:0"><?= $U('Offerta valida fino al', 'Offer valid until') ?> <?= Support::e($data) ?>. <?= $U('Rinnovo automatico annuale, disattivabile quando vuoi.', 'Annual automatic renewal, which you can switch off whenever you like.') ?></p>
        <?php if (!$utente): ?>
          <div class="actions">
            <a class="btn" href="<?= b() ?>/registrati?offerta=<?= Support::e($o['token']) ?><?= $en ? '&amp;lang=en' : '' ?>"><?= $U('Crea l\'account e accetta', 'Create your account and accept') ?> →</a>
            <a class="btn btn--ghost" href="<?= b() ?>/accedi?offerta=<?= Support::e($o['token']) ?><?= $en ? '&amp;lang=en' : '' ?>"><?= $U('Ho già un account', 'I already have an account') ?></a>
          </div>
        <?php elseif (!$fatturazioneOk): ?>
          <p class="note" role="status"><?= $U('Prima del pagamento servono i dati di fatturazione: li compili una volta sola.', 'Before paying we need your billing details: you only fill them in once.') ?></p>
          <div class="actions"><a class="btn" href="<?= b() ?>/account#fatturazione"><?= $U('Compila i dati di fatturazione', 'Fill in your billing details') ?></a></div>
        <?php else: ?>
          <form method="post" action="<?= b() ?>/offerta/<?= Support::e($o['token']) ?>/accetta" class="actions"><?= Csrf::field() ?>
            <button class="btn"><?= $U('Accetta e paga', 'Accept and pay') ?> →</button></form>
        <?php endif; ?>
      <?php else: ?>
        <p class="note" role="status"><?= match ($o['stato']) {
            'accettata' => $U('Hai già accettato questa offerta.', 'You have already accepted this offer.') . ' <a href="' . b() . '/account">' . $U('Vai al tuo account', 'Go to your account') . '</a>',
            'scaduta' => $U('Questa offerta è scaduta il', 'This offer expired on') . ' ' . Support::e($data) . '. ' . $U('Scrivici e te ne prepariamo una nuova:', 'Write to us and we will prepare a new one:') . ' <a href="mailto:' . Support::e(Support::commerciale()) . '">' . Support::e(Support::commerciale()) . '</a>',
            default => $U('Questa offerta non è più valida. Scrivici:', 'This offer is no longer valid. Write to us:') . ' <a href="mailto:' . Support::e(Support::commerciale()) . '">' . Support::e(Support::commerciale()) . '</a>',
        } ?></p>
      <?php endif; ?>
    </section>
    <section class="panel stack" aria-labelledby="off-come">
      <h2 id="off-come" style="font-size:24px;margin:0"><?= $U('Cosa succede', 'What happens') ?></h2>
      <ul class="stack small" style="gap:8px;margin:0;padding-left:20px">
        <li><?= $U('Paghi su Stripe come per gli altri piani (con l\'IVA).', 'You pay on Stripe, like our other plans (VAT added).') ?></li>
        <li><?= $U('Il prezzo è solo per te: non compare nel listino.', 'The price is just for you: it does not appear in the price list.') ?></li>
        <li><?= $U('Subito dopo il pagamento hai Portfolio con', 'Right after payment you have Portfolio with') ?> <?= $n ?> <?= $U('strutture: crei e pubblichi le guide dal pannello.', 'properties: you create and publish the guides from your dashboard.') ?></li>
        <li><?= $U('Al rinnovo, ogni anno, lo stesso prezzo. Gli sconti del programma inviti e «Giudizio con sconto» non si applicano da soli alle offerte su misura: ne parliamo noi con te.', 'Every year at renewal, the same price. The discounts of the referral programme and «Review with a discount» do not apply automatically to tailored offers: we discuss them with you.') ?></li>
      </ul>
      <p class="small muted" style="margin:0"><?= $U('Domande? Scrivi a', 'Questions? Write to') ?> <a href="mailto:<?= Support::e(Support::commerciale()) ?>"><?= Support::e(Support::commerciale()) ?></a>.</p>
    </section>
  </div>
</div>
