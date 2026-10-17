<?php
/* «Com'è andato il soggiorno?» (8C, decisione del titolare): cinque stelle; il voto si conta in modo
   anonimo per struttura (rating_N). Lo usano la pagina delle recensioni (/g/{slug}/recensione) e quella
   di saluto. Le stelle sono bottoni di un modulo GET: la pagina si ricarica con ?voto=N, anche senza
   JavaScript, e mostra SOLO il blocco giusto:
     5 stelle   — il grazie, la richiesta e i bottoni dei portali spuntati dall'host (link del sistema);
     da 1 a 4   — nessun link ai portali: «Dicci cosa possiamo migliorare», WhatsApp ed email all'host.
   Riceve $snap, $loc, $pr, $voto, $azione (dove manda il modulo), $chiaro (testo chiaro su fondo scuro). */
use MHW\{Support, Icon, I18n, Canali};
// Nell'anteprima del pannello il voto arriva solo dall'indirizzo e non si conta.
$voto = (int) ($voto ?? 0) ?: min(5, max(0, (int) ($_GET['voto'] ?? 0)));
$portaliV = array_values(array_filter((array) ($pr['channels'] ?? []), fn($c) => empty($c['social']) && trim((string) ($c['url'] ?? '')) !== ''));
$dallApp = (bool) array_filter($portaliV, fn($c) => in_array($c['kind'], Canali::DALL_APP, true));
$contattiV = array_values(array_filter($pr['contacts'] ?? [], fn($c) => trim((string) ($c['phone'] ?? '')) !== ''));
$waV = null; foreach ($contattiV as $c) if (!empty($c['whatsapp'])) { $waV = $c; break; }
$nomeHost = trim((string) ($waV['name'] ?? '')) ?: trim((string) ($pr['host_name'] ?? ''));
$nomeHost = $nomeHost !== '' ? explode(' ', $nomeHost)[0] : (string) $pr['name'];
$emailOspiti = trim((string) ($pr['guest_email'] ?? ''));
$col = !empty($chiaro) ? '#f6f0e5' : 'var(--ink)';
$colTenue = !empty($chiaro) ? '#e8dcc8' : 'var(--muted)'; ?>
<div class="stack voto-box<?= empty($chiaro) ? ' voto-box--carta' : '' ?>" style="gap:12px" id="voto">
  <h2 class="voto-box__titolo" style="color:<?= $col ?>"><?= Support::e(I18n::t($loc, 'rating_q')) ?></h2>
  <form method="get" action="<?= Support::e($azione) ?>#voto" class="stelle">
    <input type="hidden" name="l" value="<?= Support::e($loc) ?>">
    <?php for ($n = 1; $n <= 5; $n++): ?>
      <button class="stella<?= $voto >= $n ? ' stella--su' : '' ?>" name="voto" value="<?= $n ?>" aria-label="<?= Support::e(I18n::t($loc, 'rating_star', (string) $n)) ?>"<?= $voto === $n ? ' aria-pressed="true"' : '' ?>>
        <svg width="40" height="40" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.8l2.8 5.9 6.4.8-4.7 4.4 1.2 6.4L12 17.2l-5.7 3.1 1.2-6.4L2.8 9.5l6.4-.8z"/></svg></button>
    <?php endfor; ?>
  </form>
  <?php if ($voto === 5): ?>
    <div class="stack voto-box__dopo" style="gap:10px" data-voto="5">
      <p style="margin:0;color:<?= $col ?>"><b><?= Support::e(I18n::t($loc, 'rating_happy')) ?></b></p>
      <?php if ($portaliV): ?>
        <p class="small" style="margin:0;color:<?= $colTenue ?>"><?= Support::e(I18n::t($loc, 'rating_favor')) ?></p>
        <div class="ctas voto-box__portali"><?php foreach ($portaliV as $c): ?>
          <a href="<?= Support::e($c['url']) ?>" target="_blank" rel="noopener" data-portale="<?= Support::e($c['kind']) ?>"><?= Icon::svg('star', 15) ?><?= Support::e($c['kind'] === 'altro' && ($c['label'] ?? '') === 'Altro sito' ? I18n::t($loc, 'review_other') : $c['label']) ?></a><?php endforeach; ?></div>
        <?php if ($dallApp): ?><p class="tiny" style="margin:0;color:<?= $colTenue ?>"><?= Support::e(I18n::t($loc, 'rating_app_note')) ?></p><?php endif; ?>
      <?php endif; ?>
    </div>
  <?php elseif ($voto >= 1): ?>
    <div class="stack voto-box__dopo" style="gap:10px" data-voto="basso">
      <p style="margin:0;color:<?= $col ?>"><b><?= Support::e(I18n::t($loc, 'rating_honest')) ?></b></p>
      <p class="small" style="margin:0;color:<?= $colTenue ?>"><?= Support::e(I18n::t($loc, 'rating_improve')) ?></p>
      <?php if ($waV || $emailOspiti !== ''): ?>
        <div class="ctas">
          <?php if ($waV): ?><a href="https://wa.me/<?= Support::e(ltrim(Support::telHref((string) $waV['phone']), '+')) ?>?text=<?= rawurlencode(I18n::t($loc, 'rating_wa_text', $nomeHost)) ?>" rel="noopener"><?= Icon::svg('whatsapp', 15) ?><?= Support::e(I18n::t($loc, 'rating_whatsapp')) ?></a><?php endif; ?>
          <?php if ($emailOspiti !== ''): ?><a href="mailto:<?= Support::e($emailOspiti) ?>?subject=<?= rawurlencode(I18n::t($loc, 'rating_email_subject', (string) $pr['name'])) ?>"><?= Icon::svg('message', 15) ?><?= Support::e(I18n::t($loc, 'rating_email')) ?></a><?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>
