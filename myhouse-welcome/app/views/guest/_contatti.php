<?php
/* La barra in fondo alla guida: un solo bottone «Contatta {nome}» che apre un
   foglio con tutti i contatti (Chiama / WhatsApp). È un <details>: si apre
   anche senza JavaScript, e la guida resta senza cookie.
   La foto profilo (Plus), se c'è, sta nel bottone e accanto al primo contatto: è chi risponde.
   Riceve: $pr (proprietà dell'istantanea), $loc. */
use MHW\{Support, Icon, I18n, Media};
$contatti = array_values(array_filter($pr['contacts'] ?? [], fn($c) => trim((string) ($c['phone'] ?? '')) !== ''));
if ($contatti):
    $primo = trim((string) $contatti[0]['name']);
    $nomeBreve = $primo !== '' ? explode(' ', $primo)[0] : '';
    $etichetta = $nomeBreve !== '' ? I18n::t($loc, 'contact', $nomeBreve) : I18n::t($loc, 'contacts');
    $fotoHost = !empty($pr['profile_id']) ? Media::url((int) $pr['profile_id']) : null; ?>
  <div class="guest-bottom">
    <details class="contatti-foglio">
      <summary class="btn btn--block"><?php if ($fotoHost): ?><img class="contatto__foto contatto__foto--bottone" src="<?= Support::e($fotoHost) ?>" alt="" width="26" height="26"><?php else: ?><?= Icon::svg('phone', 17) ?><?php endif; ?><?= Support::e($etichetta) ?></summary>
      <div class="contatti-foglio__pannello">
        <b class="contatti-foglio__titolo"><?= Support::e(I18n::t($loc, 'contacts')) ?></b>
        <?php foreach ($contatti as $i => $c): $num = Support::telHref((string) $c['phone']); ?>
          <div class="contatto">
            <?php if ($i === 0 && $fotoHost): ?><img class="contatto__foto" src="<?= Support::e($fotoHost) ?>" alt="" width="44" height="44"><?php endif; ?>
            <span class="contatto__chi"><b><?= Support::e($c['name'] ?: I18n::t($loc, 'role_altro')) ?></b>
              <span class="small muted"><?= Support::e(I18n::t($loc, 'role_' . ($c['role'] ?: 'altro'))) ?></span></span>
            <span class="contatto__azioni">
              <a class="btn btn--ghost btn--sm" href="tel:<?= Support::e($num) ?>"><?= Icon::svg('phone', 15) ?><?= Support::e(I18n::t($loc, 'call_host')) ?></a>
              <?php if (!empty($c['whatsapp'])): ?>
                <a class="btn btn--sm" href="https://wa.me/<?= Support::e(ltrim($num, '+')) ?>" rel="noopener"><?= Icon::svg('whatsapp', 15) ?><?= Support::e(I18n::t($loc, 'whatsapp')) ?></a>
              <?php endif; ?>
            </span>
          </div>
        <?php endforeach; ?>
      </div>
    </details>
  </div>
<?php endif; ?>
