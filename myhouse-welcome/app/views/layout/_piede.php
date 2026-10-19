<?php /* Il piè di pagina del sito: chi c'è dietro a sinistra, come raggiungerci a
         destra. I dati vengono da config.php → legal; un valore vuoto non compare. */
use function MHW\b; use MHW\{Support, Config, LinguaSito};
$en = LinguaSito::en();
$l = Config::get('legal');
$tel = ($l['contact_phone'] ?? '') !== '' ? Support::telHref($l['contact_phone']) : '';
$wa = preg_replace('/\D/', '', (string) ($l['contact_whatsapp'] ?? ''));
// 8H: «Preferenze cookie» riapre il banner del consenso; c'è solo dove c'è il banner (Google Analytics o Clarity).
$prefCookie = MHW\Statistiche::qui() && MHW\Statistiche::consensoServe() ? ' · <a href="#" data-consenso-apri>' . MHW\TestiSito::html('consenso.preferenze') . '</a>' : ''; ?>
<footer class="wrap piede">
  <hr class="rule">
  <div class="piede__colonne">
    <div class="piede__chi">
      <span class="piede__marchio"><?= MHW\Icon::brand(22) ?>MyHouse Welcome</span>
      <?php /* 8J: chi fornisce il servizio (art. 2250 c.c., D.Lgs. 70/2003): ragione sociale, sede, P.IVA, REA, capitale sociale (9K: niente PEC). */ ?>
      <span class="piede__titolare"><?= Support::e(MHW\Legale::rigaTitolare($en)) ?></span>
    </div>
    <?php /* 7F: le pagine per tipologia, solo in italiano. */ if (!$en): ?>
    <nav class="piede__perchi" aria-labelledby="piede-perchi">
      <span id="piede-perchi" class="piede__titolo">Per chi è</span>
      <?php foreach (MHW\Seo::tipologie() as $tpP): ?><a href="<?= b() . Support::e($tpP['percorso']) ?>"><?= Support::e($tpP['nome']) ?></a><?php endforeach; ?>
    </nav>
    <?php endif; ?>
    <div class="piede__contatti">
      <?php if (($l['contact_email'] ?? '') !== ''): ?><a href="mailto:<?= Support::e($l['contact_email']) ?>"><?= Support::e($l['contact_email']) ?></a><?php endif; ?>
      <?php if ($tel !== ''): ?><a href="tel:<?= Support::e($tel) ?>"><?= $en ? 'Phone' : 'Tel.' ?> <?= Support::e($l['contact_phone']) ?></a><?php endif; ?>
      <?php if ($wa !== ''): ?><a href="https://wa.me/<?= Support::e($wa) ?>" rel="noopener">WhatsApp</a><?php endif; ?>
      <a href="<?= b() . ($en ? '/en' : '/') ?>#sito"><?= $en ? 'A website for your property' : 'Il sito per la tua struttura' ?></a>
      <?php /* 8J: Termini, Privacy e Accordo sul trattamento dei dati, nella lingua della pagina */ ?>
      <span><a href="<?= b() . LinguaSito::percorso('/termini') ?>"><?= $en ? 'Terms' : 'Termini' ?></a> · <a href="<?= b() . LinguaSito::percorso('/privacy') ?>">Privacy</a> · <a href="<?= b() . LinguaSito::percorso('/dpa') ?>"><?= $en ? 'DPA' : 'Accordo dati (DPA)' ?></a><?= $prefCookie ?></span>
    </div>
  </div>
</footer>
