<?php
/* La barra in fondo alla guida. 9U (mockup barra-guida): due bottoni affiancati della stessa larghezza, a sinistra
   «Chiedi» con il badge AI (Concierge AI, _chiedi.php), a destra «Contatta» pieno, con il logo di WhatsApp se almeno un
   contatto ce l'ha (altrimenti il telefono in un tondo bianco). Niente nome né foto nel bottone: il nome completo e la
   foto sono nel foglio che si apre («Ciao, sono {nome}», poi WhatsApp, telefono ed email). Se WhatsApp è l'unico modo di
   contattare l'host (un solo contatto, con WhatsApp, e nessuna email), il bottone apre subito wa.me. Senza Concierge
   «Contatta» occupa tutta la riga, con il nome se è corto («Contatta Francesco»). Il foglio è un <details>: si apre anche
   senza JavaScript, e la guida resta senza cookie.
   Riceve: $pr (proprietà dell'istantanea), $loc, $chiedi (Assistente::datiGuida, o null). Con $barraFinta = true (il
   telefono disegnato in home) solo l'aspetto, senza link né bottoni: il telefono intero è già il link alla demo. */
use MHW\{Support, Icon, I18n, Media, Demo};
$contatti = array_values(array_filter($pr['contacts'] ?? [], fn($c) => trim((string) ($c['phone'] ?? '')) !== ''));
$chiedi = $chiedi ?? null;
$finta = !empty($barraFinta);
$conChiedi = $finta ? !empty($barraChiedi) : !empty($chiedi);
if (!$contatti && !$conChiedi) return;
$vetrinaC = (int) ($pr['is_demo'] ?? 0) === Demo::VETRINA;
$emailC = trim((string) ($pr['guest_email'] ?? ''));
$primoC = $contatti[0] ?? ['name' => '', 'role' => 'altro', 'phone' => '', 'whatsapp' => 0];
$nomeC = trim((string) $primoC['name']);
$nomeBreve = $nomeC !== '' ? explode(' ', $nomeC)[0] : '';
$conWa = (bool) array_filter($contatti, fn($c) => !empty($c['whatsapp']));
$numPrimo = Support::telHref((string) $primoC['phone']);
$soloWa = !$finta && !$vetrinaC && count($contatti) === 1 && !empty($primoC['whatsapp']) && $emailC === '';
// Senza Concierge il nome entra nel bottone se è corto (fino a 11 lettere: «Francesco» sì, «Massimiliano» no).
$etichetta = !$conChiedi && $nomeBreve !== '' && mb_strlen($nomeBreve) < 12 ? I18n::t($loc, 'contact', $nomeBreve) : I18n::t($loc, 'contact_short');
$etichettaLunga = $nomeBreve !== '' ? I18n::t($loc, 'contact', $nomeBreve) : I18n::t($loc, 'contacts');
$iconaC = $conWa ? Icon::svg('whatsapp-logo', 26) : '<span class="contatta__tel">' . Icon::svg('phone', 15, 2) . '</span>';
$fotoHost = !empty($pr['profile_id']) ? Media::url((int) $pr['profile_id']) : null;
$classeBarra = 'guest-bottom barra-guida' . ($conChiedi && $contatti ? '' : ' barra-guida--sola');

if ($finta): ?>
  <span class="barra-guida barra-guida--finta<?= $conChiedi && $contatti ? '' : ' barra-guida--sola' ?>">
    <?php if ($conChiedi): ?><span class="btn btn--ghost chiedi-apri"><?= Icon::svg('message', 15) ?><span class="chiedi-apri__testo"><?= Support::e(I18n::t($loc, 'ask_short')) ?></span><span class="badge-ai">AI</span></span><?php endif; ?>
    <?php if ($contatti): ?><span class="btn contatta"><?= $iconaC ?><span class="contatta__testo"><?= Support::e($etichetta) ?></span></span><?php endif; ?>
  </span>
<?php return; endif; ?>
<div class="<?= $classeBarra ?>">
  <?php if ($conChiedi) include __DIR__ . '/_chiedi.php'; ?>
  <?php if ($soloWa): ?>
    <a class="btn contatta" href="https://wa.me/<?= Support::e(ltrim($numPrimo, '+')) ?>" rel="noopener" aria-label="<?= Support::e($etichettaLunga) ?> (WhatsApp)"><?= $iconaC ?><span class="contatta__testo"><?= Support::e($etichetta) ?></span></a>
  <?php elseif ($contatti): ?>
    <details class="contatti-foglio">
      <summary class="btn contatta" aria-label="<?= Support::e($etichettaLunga) ?>"><?= $iconaC ?><span class="contatta__testo"><?= Support::e($etichetta) ?></span></summary>
      <div class="contatti-foglio__pannello">
        <div class="contatti-foglio__testa">
          <?php if ($fotoHost): ?><img class="contatto__foto" src="<?= Support::e($fotoHost) ?>" alt="" width="44" height="44"><?php endif; ?>
          <span class="contatto__chi"><b class="contatti-foglio__ciao"><?= Support::e($nomeC !== '' ? I18n::t($loc, 'host_ciao', $nomeC) : I18n::t($loc, 'contacts')) ?></b>
            <span class="small muted"><?= Support::e(I18n::t($loc, 'role_' . ($primoC['role'] ?: 'altro'))) ?></span></span>
        </div>
        <?php if ($vetrinaC): /* 9T · 5.4: nella vetrina (la demo della home) i numeri sono di fantasia: niente WhatsApp, Chiama né email */ ?>
          <div class="demo-contatto__pannello demo-contatto__pannello--foglio" role="note"><p><?= Support::e(I18n::t($loc, 'demo_contact_note')) ?></p>
            <a class="btn btn--sm" href="<?= Support::e(Support::baseUrl()) ?>/registrati"><?= Support::e(I18n::t($loc, 'demo_create')) ?></a></div>
        <?php else: ?>
          <div class="contatti-foglio__azioni">
            <?php if (!empty($primoC['whatsapp'])): ?><a class="btn btn--block btn--wa" href="https://wa.me/<?= Support::e(ltrim($numPrimo, '+')) ?>" rel="noopener"><?= Icon::svg('whatsapp-glifo', 18) ?><?= Support::e(I18n::t($loc, 'whatsapp')) ?></a><?php endif; ?>
            <a class="btn btn--block btn--ghost" href="tel:<?= Support::e($numPrimo) ?>"><?= Icon::svg('phone', 16) ?><?= Support::e(I18n::t($loc, 'call_host')) ?></a>
            <?php if ($emailC !== ''): ?><a class="btn btn--block btn--ghost" href="mailto:<?= Support::e($emailC) ?>"><?= Icon::svg('mail', 16) ?><?= Support::e(I18n::t($loc, 'email_host')) ?></a><?php endif; ?>
          </div>
        <?php endif; ?>
        <?php foreach (array_slice($contatti, 1) as $c): $num = Support::telHref((string) $c['phone']); /* gli altri contatti (co-host, pulizie…) */ ?>
          <div class="contatto">
            <span class="contatto__chi"><b><?= Support::e($c['name'] ?: I18n::t($loc, 'role_altro')) ?></b>
              <span class="small muted"><?= Support::e(I18n::t($loc, 'role_' . ($c['role'] ?: 'altro'))) ?></span></span>
            <?php if (!$vetrinaC): ?>
            <span class="contatto__azioni">
              <?php if (!empty($c['whatsapp'])): ?><a class="btn btn--sm btn--wa" href="https://wa.me/<?= Support::e(ltrim($num, '+')) ?>" rel="noopener"><?= Icon::svg('whatsapp-glifo', 15) ?><?= Support::e(I18n::t($loc, 'whatsapp')) ?></a><?php endif; ?>
              <a class="btn btn--ghost btn--sm" href="tel:<?= Support::e($num) ?>"><?= Icon::svg('phone', 15) ?><?= Support::e(I18n::t($loc, 'call_host')) ?></a>
            </span>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </details>
  <?php endif; ?>
</div>
