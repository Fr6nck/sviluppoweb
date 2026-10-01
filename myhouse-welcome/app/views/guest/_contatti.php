<?php
/* La barra in fondo alla guida: un solo bottone «Contatta {nome}» che apre un
   foglio con tutti i contatti (Chiama / WhatsApp). È un <details>: si apre
   anche senza JavaScript, e la guida resta senza cookie.
   Riceve: $pr (proprietà dell'istantanea), $loc. */
use MHW\{Support, Icon, I18n};
$contatti = array_values(array_filter($pr['contacts'] ?? [], fn($c) => trim((string) ($c['phone'] ?? '')) !== ''));
if ($contatti):
    $primo = trim((string) $contatti[0]['name']);
    $nomeBreve = $primo !== '' ? explode(' ', $primo)[0] : '';
    $etichetta = $nomeBreve !== '' ? I18n::t($loc, 'contact', $nomeBreve) : I18n::t($loc, 'contacts'); ?>
  <div class="guest-bottom">
    <details class="contatti-foglio">
      <summary class="btn btn--block"><?= Icon::svg('phone', 17) ?><?= Support::e($etichetta) ?></summary>
      <div class="contatti-foglio__pannello">
        <b class="contatti-foglio__titolo"><?= Support::e(I18n::t($loc, 'contacts')) ?></b>
        <?php foreach ($contatti as $c): $num = Support::telHref((string) $c['phone']); ?>
          <div class="contatto">
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
