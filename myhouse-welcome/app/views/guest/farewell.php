<?php
/* Il congedo. Mostra SOLO quello che l'host ha scritto per la partenza: se non
   ha scritto niente, non si inventa niente — resta il saluto. */
use MHW\{Support, Icon, Guide, I18n, Media};
$pr = $snap['property']; $def = $pr['default_locale'];
$title = I18n::t($loc, 'farewell_title') . ' — ' . $pr['name'];
$core = null; foreach ($snap['sections'] as $s) if ($s['kind'] === 'checkin') { $core = $s; break; }
$t = $core ? Guide::tdata($core, $loc, $def) : [];
// La lista «Prima di partire» (dalla 009 le vecchie caselle fisse sono voci di questa lista).
$cose = array_values(array_filter(array_map(fn($x) => trim((string) $x), (array) ($t['checkout_steps'] ?? [])), fn($x) => $x !== ''));
$saluto = trim((string) ($t['checkout_notes'] ?? ''));
// WhatsApp: il primo contatto che risponde lì.
$tel = '';
foreach ($pr['contacts'] ?? [] as $c) if (!empty($c['whatsapp']) && trim((string) $c['phone']) !== '') { $tel = Support::telHref((string) $c['phone']); break; }
$copertina = !empty($pr['cover_id']) ? Media::url((int) $pr['cover_id']) : ($pr['cover_url'] ?? null); ?>

<div class="full" id="congedo">
  <?php if ($copertina): ?><img class="full__photo" src="<?= Support::e($copertina) ?>" alt="" style="transform-origin:50% 40%"><?php endif; ?>
  <div class="full__scrim" style="background:linear-gradient(180deg,rgba(23,19,13,.70) 0%,rgba(23,19,13,.42) 24%,rgba(23,19,13,.82) 58%,rgba(23,19,13,.97) 100%)"></div>
  <div class="full__stage">
    <div class="rise" style="display:flex;align-items:center;justify-content:space-between;gap:12px;animation-delay:.1s">
      <span style="font-size:14px;font-weight:500;letter-spacing:-.2px;color:#f6f0e5"><?= Support::e($pr['name']) ?></span>
      <span class="pill-quiet" style="border-color:rgba(246,240,229,.32);color:#f6f0e5"><?= Support::e(I18n::t($loc, 'checkout_by', $pr['checkout_by'])) ?></span>
    </div>
    <div class="stack" style="gap:24px">
      <div class="stack" style="gap:12px">
        <span class="kicker rise" style="animation-delay:.3s"><?= Support::e(I18n::t($loc, 'before_leaving')) ?></span>
        <h1 class="rise" style="animation-delay:.42s;font-size:clamp(40px,12vw,50px)"><?= Support::e(I18n::t($loc, 'farewell_title')) ?></h1>
        <p class="rise" style="animation-delay:.58s"><?= Support::e(I18n::t($loc, $cose ? 'farewell_lead' : 'farewell_empty')) ?></p>
      </div>
      <?php if ($cose): ?>
        <ul class="checklist">
          <?php foreach ($cose as $i => $testo): ?>
            <li class="rise" style="animation-delay:<?= number_format(.70 + $i * .1, 2, '.', '') ?>s;align-items:flex-start">
              <span style="flex:none;color:#6fc48c;margin-top:2px"><?= Icon::svg('check', 18, 2.4) ?></span>
              <span><?= Support::e($testo) ?></span></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <?php if ($saluto !== ''): ?><p class="rise" style="animation-delay:1s;white-space:pre-line"><?= Support::e($saluto) ?></p><?php endif; ?>
      <?php /* Dopo il soggiorno: compaiono solo se l'host li ha compilati. */
            $recensioni = $pr['reviews'] ?? []; $diretta = $pr['direct'] ?? ['url' => '', 'code' => ''];
            $piattaforme = ['google' => 'Google', 'booking' => 'Booking.com', 'airbnb' => 'Airbnb', 'other' => I18n::t($loc, 'review_other')]; ?>
      <?php /* 7C · Com'è andato il soggiorno? Cinque stelle; dopo il tocco i bottoni delle recensioni, uguali per ogni voto
               (filtrare chi recensisce in base al voto è vietato da Google e Booking). Da 1 a 3, in più: «Scrivigli».
               Il voto è solo un conteggio anonimo per struttura (rating_N). Senza JavaScript le stelle ricaricano la pagina. */
            $voto = (int) ($voto ?? 0) ?: min(5, max(0, (int) ($_GET['voto'] ?? 0)));
            $contattiF = array_values(array_filter($pr['contacts'] ?? [], fn($c) => trim((string) ($c['phone'] ?? '')) !== ''));
            $nomeF = trim((string) ($contattiF[0]['name'] ?? '')) ?: trim((string) ($pr['host_name'] ?? ''));
            $nomeF = $nomeF !== '' ? explode(' ', $nomeF)[0] : $pr['name'];
            $scriviF = $contattiF ? (!empty($contattiF[0]['whatsapp']) ? 'https://wa.me/' . ltrim(Support::telHref((string) $contattiF[0]['phone']), '+') : 'tel:' . Support::telHref((string) $contattiF[0]['phone'])) : ''; ?>
      <div class="stack congedo-extra rise" style="gap:10px;animation-delay:1s" data-voto-box>
        <h2 style="font-size:22px;color:#f6f0e5"><?= Support::e(I18n::t($loc, 'rating_q')) ?></h2>
        <form method="get" action="<?= Support::e($base) ?>/commiato#voto" class="stelle" id="voto" data-voto-url="<?= empty($anteprima) ? Support::e($base) . '/voto/' : '' ?>">
          <input type="hidden" name="l" value="<?= Support::e($loc) ?>">
          <?php for ($n = 1; $n <= 5; $n++): ?>
            <button class="stella<?= $voto >= $n ? ' stella--su' : '' ?>" name="voto" value="<?= $n ?>" aria-label="<?= Support::e(I18n::t($loc, 'rating_star', (string) $n)) ?>"<?= $voto === $n ? ' aria-pressed="true"' : '' ?>>
              <svg width="36" height="36" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.8l2.8 5.9 6.4.8-4.7 4.4 1.2 6.4L12 17.2l-5.7 3.1 1.2-6.4L2.8 9.5l6.4-.8z"/></svg></button>
          <?php endfor; ?>
        </form>
        <div class="stack" style="gap:10px" data-voto-dopo<?= $voto ? '' : ' hidden' ?>>
          <?php $conRec = array_filter((array) $recensioni, fn($u) => trim((string) $u) !== ''); /* senza link per le recensioni, solo il grazie */ ?>
          <p class="small" style="color:#e8dcc8"><?= Support::e(I18n::t($loc, $conRec ? 'rating_thanks' : 'rating_thanks_solo')) ?></p>
          <?php if ($conRec): ?>
            <div class="ctas"><?php foreach ($piattaforme as $k => $nome): if (empty($recensioni[$k])) continue; ?>
              <a href="<?= Support::e($recensioni[$k]) ?>" target="_blank" rel="noopener"><?= Icon::svg('message', 15) ?><?= Support::e($nome) ?></a><?php endforeach; ?></div>
          <?php endif; ?>
          <?php if ($scriviF !== ''): ?>
            <p class="small" data-voto-basso<?= $voto >= 1 && $voto <= 3 ? '' : ' hidden' ?>><a href="<?= Support::e($scriviF) ?>" rel="noopener" style="color:#f6f0e5"><?= Support::e(I18n::t($loc, 'rating_host', $nomeF)) ?></a></p>
          <?php endif; ?>
        </div>
      </div>
      <?php if (trim((string) $diretta['url']) !== '' || trim((string) $diretta['code']) !== ''):
            $scontoF = trim((string) ($diretta['discount'] ?? '')); $codiceF = trim((string) $diretta['code']); ?>
        <div class="stack congedo-extra rise" style="gap:10px;animation-delay:1.02s">
          <h2 style="font-size:22px;color:#f6f0e5"><?= Support::e(I18n::t($loc, 'direct_title')) ?></h2>
          <p class="small" style="color:#e8dcc8"><?= Support::e(I18n::t($loc, 'direct_lead')) ?></p>
          <?php if ($codiceF !== ''): /* 7C: «−10% con il codice BENTORNATI», «Copia il codice» */ ?>
            <p class="codice-offerta"><?= $scontoF !== '' ? Support::e(I18n::t($loc, 'direct_offer', "\u{2212}" . ltrim($scontoF, "-\u{2212} "), '')) : Support::e(I18n::t($loc, 'direct_code', '')) ?><b><?= Support::e($codiceF) ?></b></p>
            <div class="ctas">
              <button type="button" data-copia-di="<?= Support::e($codiceF) ?>" data-copiato="<?= Support::e(I18n::t($loc, 'copied')) ?>" hidden><?= Icon::svg('copy', 15) ?><?= Support::e(I18n::t($loc, 'copy_code')) ?></button>
              <?php if (trim((string) $diretta['url']) !== ''): ?><a href="<?= Support::e($diretta['url']) ?>" target="_blank" rel="noopener"><?= Icon::svg('globe', 15) ?><?= Support::e(I18n::t($loc, 'direct_book')) ?></a><?php endif; ?>
            </div>
          <?php elseif (trim((string) $diretta['url']) !== ''): ?>
            <div class="ctas"><a href="<?= Support::e($diretta['url']) ?>" target="_blank" rel="noopener"><?= Icon::svg('globe', 15) ?><?= Support::e(I18n::t($loc, 'direct_book')) ?></a></div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
      <div class="stack" style="gap:10px">
        <?php if ($tel): ?>
          <a class="btn btn--lg btn--go rise" style="animation-delay:1.05s" href="https://wa.me/<?= Support::e(ltrim($tel, '+')) ?>" rel="noopener">
            <?= Support::e(I18n::t($loc, 'whatsapp')) ?> <span class="go"><?= Icon::svg('whatsapp', 19, 2) ?></span></a>
        <?php endif; ?>
        <a class="rise" style="display:flex;align-items:center;justify-content:center;min-height:48px;border-radius:999px;color:#e8dcc8;font-size:15px;font-weight:500;animation-delay:1.1s"
           href="<?= Support::e($base) ?>?l=<?= Support::e($loc) ?>"><?= Support::e(I18n::t($loc, 'farewell_back')) ?></a>
      </div>
    </div>
  </div>
</div>
<script>
/* Copiare il codice sconto: il bottone compare solo se il browser sa copiare. */
(function () {
  if (!navigator.clipboard) return;
  document.querySelectorAll('[data-copia-di]').forEach(function (b) {
    b.hidden = false;
    b.addEventListener('click', function () { navigator.clipboard.writeText(b.getAttribute('data-copia-di')); b.setAttribute('aria-label', <?= json_encode(I18n::t($loc, 'copied')) ?>); });
  });
})();
</script>
