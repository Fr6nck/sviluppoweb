<?php
/* La barra in fondo alla guida: un solo bottone «Contatta {nome}» che apre un
   foglio con tutti i contatti (Chiama / WhatsApp). È un <details>: si apre
   anche senza JavaScript, e la guida resta senza cookie.
   La foto profilo (Plus), se c'è, sta nel bottone e accanto al primo contatto: è chi risponde.
   Riceve: $pr (proprietà dell'istantanea), $loc. */
use MHW\{Support, Icon, I18n, Media};
$contatti = array_values(array_filter($pr['contacts'] ?? [], fn($c) => trim((string) ($c['phone'] ?? '')) !== ''));
// 10 · Concierge AI sta nella stessa barra, prima dei contatti (anche senza contatti, se è acceso).
$chiedi = $chiedi ?? null;
if (!$contatti && !empty($chiedi)): ?>
  <div class="guest-bottom"><?php include __DIR__ . '/_chiedi.php'; ?></div>
<?php endif;
if ($contatti):
    $primo = trim((string) $contatti[0]['name']);
    $nomeBreve = $primo !== '' ? explode(' ', $primo)[0] : '';
    $etichetta = $nomeBreve !== '' ? I18n::t($loc, 'contact', $nomeBreve) : I18n::t($loc, 'contacts');
    $fotoHost = !empty($pr['profile_id']) ? Media::url((int) $pr['profile_id']) : null; ?>
  <div class="guest-bottom">
    <?php if (!empty($chiedi)) include __DIR__ . '/_chiedi.php'; ?>
    <details class="contatti-foglio">
      <summary class="btn btn--block" title="<?= Support::e($etichetta) ?>"><?php if ($fotoHost): ?><img class="contatto__foto contatto__foto--bottone" src="<?= Support::e($fotoHost) ?>" alt="" width="26" height="26"><?php else: ?><?= Icon::svg('phone', 17) ?><?php endif; ?><span class="contatti-foglio__lungo"><?= Support::e($etichetta) ?></span><?php if ($nomeBreve !== ''): /* 9T · su telefono, accanto a «Chiedi al Concierge», solo il nome */ ?><span class="contatti-foglio__corto" aria-hidden="true"><?= Support::e($nomeBreve) ?></span><?php endif; ?></summary>
      <div class="contatti-foglio__pannello">
        <b class="contatti-foglio__titolo"><?= Support::e(I18n::t($loc, 'contacts')) ?></b>
        <?php foreach ($contatti as $i => $c): $num = Support::telHref((string) $c['phone']); ?>
          <div class="contatto">
            <?php if ($i === 0 && $fotoHost): ?><img class="contatto__foto" src="<?= Support::e($fotoHost) ?>" alt="" width="44" height="44"><?php endif; ?>
            <span class="contatto__chi"><b><?= Support::e($c['name'] ?: I18n::t($loc, 'role_altro')) ?></b>
              <span class="small muted"><?= Support::e(I18n::t($loc, 'role_' . ($c['role'] ?: 'altro'))) ?></span></span>
            <?php if ((int) ($pr['is_demo'] ?? 0) !== MHW\Demo::VETRINA): ?>
            <span class="contatto__azioni">
              <a class="btn btn--ghost btn--sm" href="tel:<?= Support::e($num) ?>"><?= Icon::svg('phone', 15) ?><?= Support::e(I18n::t($loc, 'call_host')) ?></a>
              <?php if (!empty($c['whatsapp'])): ?>
                <a class="btn btn--sm" href="https://wa.me/<?= Support::e(ltrim($num, '+')) ?>" rel="noopener"><?= Icon::svg('whatsapp', 15) ?><?= Support::e(I18n::t($loc, 'whatsapp')) ?></a>
              <?php endif; ?>
            </span>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
        <?php if ((int) ($pr['is_demo'] ?? 0) === MHW\Demo::VETRINA): /* 9T · 5.4: nella vetrina (la demo della home) i numeri sono di fantasia: niente Chiama né WhatsApp */ ?>
          <div class="demo-contatto__pannello demo-contatto__pannello--foglio" role="note"><p><?= Support::e(I18n::t($loc, 'demo_contact_note')) ?></p>
            <a class="btn btn--sm" href="<?= Support::e(Support::baseUrl()) ?>/registrati"><?= Support::e(I18n::t($loc, 'demo_create')) ?></a></div>
        <?php endif; ?>
      </div>
    </details>
  </div>
<?php endif; ?>
