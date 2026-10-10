<?php
/* Tutte le domande frequenti (Faq::tutte), aperte, con il link diretto a ognuna: /domande#id.
   Le stesse della landing e dei dati strutturati (FAQPage) e di llms.txt. */
use function MHW\b;
use MHW\{Support, Icon, Config};
$title = 'Domande frequenti · MyHouse Welcome';
$legale = Config::get('legal') ?? [];
$waFaq = preg_replace('/\D/', '', (string) ($legale['contact_whatsapp'] ?? ''));
$crea = b() . (!empty(MHW\Auth::user()) ? '/pannello' : '/registrati'); ?>
<section class="blocco faq domande" aria-labelledby="faq-titolo">
  <div class="faq__testa">
    <span class="kicker">Domande</span>
    <h1 id="faq-titolo" class="h-sezione">Domande frequenti</h1>
    <?php if (($legale['contact_email'] ?? '') !== '' || $waFaq !== ''): ?>
      <p class="muted">Non trovi la risposta? Scrivici, ti rispondiamo volentieri.</p>
      <div class="faq__contatti">
        <?php if ($waFaq !== ''): ?><a class="btn btn--ghost" href="https://wa.me/<?= Support::e($waFaq) ?>" rel="noopener"><?= Icon::svg('whatsapp', 18, 1.8) ?>WhatsApp</a><?php endif; ?>
        <?php if (($legale['contact_email'] ?? '') !== ''): ?><a class="btn btn--ghost" href="mailto:<?= Support::e($legale['contact_email']) ?>"><?= Icon::svg('message', 18, 1.8) ?>Email</a><?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
  <div class="faq__lista">
    <?php foreach ($faq as $q): ?>
      <details class="faq__voce" id="<?= Support::e($q['id']) ?>" open>
        <summary><?= Support::e($q['d']) ?><?= Icon::svg('plus', 18, 2, 'faq__segno') ?></summary>
        <p><?= Support::e($q['r']) ?></p>
      </details>
    <?php endforeach; ?>
    <div class="row domande__azioni">
      <a class="btn" href="<?= $crea ?>">Crea gratis la tua guida</a>
      <a class="btn btn--ghost" href="<?= b() ?>/#piani">Vedi i prezzi</a>
    </div>
  </div>
</section>
