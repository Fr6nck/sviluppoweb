<?php
/* Tutte le domande frequenti (Faq::tutte), divise per tema con l'indice in cima (9N), chiuse: si apre quella del link
   (/domande#id, guida.js/landing.js) o quella toccata.
   Le stesse della landing e dei dati strutturati (FAQPage) e di llms.txt. */
use function MHW\b;
use MHW\{Support, Icon, Config, LinguaSito};
$enD = LinguaSito::en();
$title = $enD ? 'Frequently asked questions · MyHouse Welcome' : 'Domande frequenti · MyHouse Welcome';
$legale = Config::get('legal') ?? [];
$waFaq = preg_replace('/\D/', '', (string) ($legale['contact_whatsapp'] ?? ''));
$crea = b() . (!empty(MHW\Auth::user()) ? '/pannello' : '/registrati' . ($enD ? '?lang=en' : '')); ?>
<section class="blocco faq domande" aria-labelledby="faq-titolo">
  <div class="faq__testa">
    <span class="kicker"><?= $enD ? 'Questions' : 'Domande' ?></span>
    <h1 id="faq-titolo" class="h-sezione"><?= MHW\TestiSito::html('domande.titolo') ?></h1>
    <?php if (($legale['contact_email'] ?? '') !== '' || $waFaq !== ''): ?>
      <p class="muted"><?= MHW\TestiSito::html('faq.aiuto') ?></p>
      <div class="faq__contatti">
        <?php if ($waFaq !== ''): ?><a class="btn btn--ghost" href="https://wa.me/<?= Support::e($waFaq) ?>" rel="noopener"><?= Icon::svg('whatsapp', 18, 1.8) ?>WhatsApp</a><?php endif; ?>
        <?php if (($legale['contact_email'] ?? '') !== ''): ?><a class="btn btn--ghost" href="mailto:<?= Support::e($legale['contact_email']) ?>"><?= Icon::svg('mail', 18, 1.8) ?>Email</a><?php endif; ?>
      </div>
      <?php if (($legale['contact_email'] ?? '') !== ''): /* chi non ha un programma di posta sul computer lo copia da qui */ ?>
        <p class="small muted faq__indirizzo"><?= MHW\TestiSito::html('faq.oppure') ?> <b><?= Support::e($legale['contact_email']) ?></b></p>
      <?php endif; ?>
    <?php endif; ?>
  </div>
  <?php $temi = MHW\Faq::perTema($faq, $enD); ?>
  <nav class="faq__indice" aria-label="<?= $enD ? 'Topics' : 'Temi' ?>">
    <?php foreach ($temi as [$idT, $nomeT, $qT]): ?><a href="#tema-<?= Support::e($idT) ?>"><?= Support::e($nomeT) ?> <span class="muted">(<?= count($qT) ?>)</span></a><?php endforeach; ?>
  </nav>
  <div class="faq__lista">
    <?php foreach ($temi as [$idT, $nomeT, $qT]): ?>
      <h2 class="faq__tema" id="tema-<?= Support::e($idT) ?>"><?= Support::e($nomeT) ?></h2>
      <?php foreach ($qT as $q): ?>
        <details class="faq__voce" id="<?= Support::e($q['id']) ?>">
          <summary><?= Support::e($q['d']) ?><?= Icon::svg('plus', 18, 2, 'faq__segno') ?></summary>
          <p><?= Support::e($q['r']) ?></p>
        </details>
      <?php endforeach; ?>
    <?php endforeach; ?>
    <script>
    /* 9N · La domanda del link (#id) si apre da sola, anche cambiando ancora nella stessa pagina. */
    (function () { var apri = function () { var d = location.hash && document.getElementById(location.hash.slice(1)); if (d && d.tagName === 'DETAILS') d.open = true; };
      apri(); window.addEventListener('hashchange', apri); })();
    </script>
    <div class="row domande__azioni">
      <a class="btn" href="<?= $crea ?>"><?= MHW\TestiSito::html('cta.crea') ?></a>
      <a class="btn btn--ghost" href="<?= b() . ($enD ? '/en' : '/') ?>#piani"><?= $enD ? 'See pricing' : 'Vedi i prezzi' ?></a>
    </div>
  </div>
</section>
