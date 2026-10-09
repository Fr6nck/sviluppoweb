<?php
/* Una sezione della guida. Si disegna dai CAMPI del catalogo: passaggi
   numerati, elenchi, indirizzi con Maps, luoghi con i loro bottoni. Ogni
   etichetta è nella lingua dell'ospite. Nessun codice d'accesso, mai. */
use MHW\{Support, Icon, Guide, Media, SectionCatalog, I18n};
$pr = $snap['property']; $def = $pr['default_locale'];
$kind = $sec['kind'];
$d = $sec['data'] ?? [];
$t = Guide::tdata($sec, $loc, $def);
$titolo = Guide::title($sec, $loc, $def);
$title = $titolo . ' — ' . $pr['name'];
$coda = '/' . (int) $sec['id'];
$notte = $kind === 'wifi';
$theme = $notte ? 'night' : 'light';
$indietro = true;
$nascondiTema = $notte;
$img = Guide::img($sec);
$pdf = !empty($sec['pdf_id']) ? Media::url((int) $sec['pdf_id']) : null;
$val = fn(string $k) => trim((string) ($t[$k] ?? ''));
$lista = fn(string $k) => array_values(array_filter((array) ($t[$k] ?? []), fn($x) => trim((string) $x) !== ''));
$maps = function (string $indirizzo, string $url): string {
    if ($url !== '') return $url;
    return $indirizzo !== '' ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($indirizzo) : '';
};

/* La sezione che viene dopo: l'invito in fondo alla pagina. */
$dopo = null; $trovata = false;
foreach ($snap['sections'] as $s) {
    if ($trovata) { $dopo = $s; break; }
    if ((int) $s['id'] === (int) $sec['id']) $trovata = true;
} ?>

<?php include __DIR__ . '/_top.php'; ?>

<div class="stack stack--sm" style="margin-top:20px">
  <?php if ($kind === 'checkin' && $lista('checkin_steps')): ?>
    <span class="kicker"><?= Support::e(I18n::t($loc, 'arrival')) ?> · <?= Support::e(I18n::t($loc, 'steps_count', count($lista('checkin_steps')))) ?></span>
  <?php elseif ($notte): ?>
    <span class="pill-quiet" style="align-self:flex-start"><?= Icon::svg('moon', 13) ?><?= Support::e(I18n::t($loc, 'night_theme')) ?></span>
  <?php endif; ?>
  <h1 class="guest-title" style="font-size:38px;line-height:38px"><?= Support::e($titolo) ?></h1>
  <?php if (in_array($kind, ['arrival', 'transport'], true)): /* fase 6C: distingue le due sezioni */ ?>
    <p class="muted" style="margin-top:6px"><?= Support::e(I18n::t($loc, 'sub.' . $kind)) ?></p>
  <?php endif; ?>
</div>

<?php if ($img): ?>
  <div class="shot shot--h186" style="margin-top:20px"><img src="<?= Support::e($img) ?>" alt="" loading="lazy" decoding="async"></div>
<?php endif; ?>

<?php /* ---------------------------------------------------------------- Wi-Fi */ ?>
<?php if ($kind === 'wifi'):
      // Una scheda per rete: nome, password da copiare, QR per collegarsi senza scrivere.
      $reti = array_values(array_filter(Guide::rows($sec, 'networks', $loc, $def), fn($r) => trim((string) $r['ssid']) !== '' || trim((string) $r['password']) !== ''));
      // Con il QR di una camera, prima il Wi-Fi della camera; poi gli altri della casa.
      if (!empty($variante) && trim((string) $variante['wifi_ssid']) !== '') {
          if ($reti && count($reti) === 1 && trim((string) $reti[0]['zone']) === '') $reti[0]['zone'] = I18n::t($loc, 'room_other_wifi');
          array_unshift($reti, ['ssid' => $variante['wifi_ssid'], 'password' => $variante['wifi_password'], 'zone' => I18n::t($loc, 'room_wifi') . ' · ' . $variante['name']]);
      }
      foreach ($reti as $n => $rete): $ssid = trim((string) $rete['ssid']); $pw = trim((string) $rete['password']);
        $zona = trim((string) $rete['zone']) ?: (count($reti) > 1 ? I18n::t($loc, 'network_n', $n + 1) : ''); ?>
    <div class="panel stack rete" style="margin-top:20px;gap:18px">
      <?php if ($zona !== ''): ?><h2 class="rete__zona"><?= Support::e($zona) ?></h2><?php endif; ?>
      <?php if ($ssid !== ''): ?>
        <div class="stack" style="gap:6px">
          <span class="kicker"><?= Support::e(I18n::t($loc, 'network')) ?></span>
          <div class="copyline">
            <b><?= Support::e($ssid) ?></b>
            <button type="button" class="icon-btn icon-btn--strong" data-copia-di="<?= Support::e($ssid) ?>"
                    aria-label="<?= Support::e(I18n::t($loc, 'copy_network')) ?>"><?= Icon::svg('copy', 17) ?></button>
          </div>
        </div>
      <?php endif; ?>
      <?php if ($ssid !== '' && $pw !== ''): ?><hr class="rule"><?php endif; ?>
      <?php if ($pw !== ''): ?>
        <div class="stack" style="gap:6px">
          <span class="kicker"><?= Support::e(I18n::t($loc, 'password')) ?></span>
          <div class="copyline">
            <b class="pw"><?= Support::e($pw) ?></b>
            <button type="button" class="icon-btn icon-btn--accent" data-copia-di="<?= Support::e($pw) ?>"
                    aria-label="<?= Support::e(I18n::t($loc, 'copy_password')) ?>"><?= Icon::svg('copy', 17, 2) ?></button>
          </div>
        </div>
      <?php endif; ?>
      <?php if ($ssid !== ''): /* il QR Wi-Fi: dentro la pagina, nessuna richiesta in più */ ?>
        <div class="rete__qr">
          <img src="data:image/png;base64,<?= base64_encode(MHW\Qr::png(MHW\Conversione::wifiQr($ssid, $pw), 6, 3)) ?>"
               width="168" height="168" alt="<?= Support::e(I18n::t($loc, 'wifi_qr_alt', $ssid)) ?>">
          <p class="small"><?= Support::e(I18n::t($loc, 'scan_wifi')) ?></p>
        </div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  <?php if ($val('router_location') !== ''): ?>
    <p class="small muted" style="margin-top:14px"><strong><?= Support::e(I18n::t($loc, 'router')) ?>:</strong> <?= Support::e($val('router_location')) ?></p>
  <?php endif; ?>
  <?php if ($val('instructions') !== ''): ?><p style="margin-top:14px;font-size:16px;line-height:24px;white-space:pre-line"><?= Support::e($val('instructions')) ?></p><?php endif; ?>

<?php /* ------------------------------------------------ arrivo e partenza */ ?>
<?php elseif ($kind === 'checkin'):
      $modo = (string) ($d['arrival_mode'] ?? '');
      $imposta = trim((string) ($d['tax_amount'] ?? '')); $notti = trim((string) ($d['tax_max_nights'] ?? '')); ?>
  <?php if ($modo !== '' && I18n::has($loc, 'mode_' . $modo)): ?>
    <p style="margin-top:16px"><span class="badge badge--sea"><?= Support::e(I18n::t($loc, 'mode_' . $modo)) ?></span></p>
  <?php endif; ?>
  <?php if (!empty($variante) && ($accesso = MHW\Varianti::testo($variante, 'access', $loc, $def)) !== ''): /* la camera del QR */ ?>
    <div class="camera" style="margin-top:20px">
      <span class="camera__chi"><?= Icon::svg('key', 16) ?><?= Support::e(I18n::t($loc, 'room_access')) ?></span>
      <b class="camera__nome"><?= Support::e($variante['name']) ?></b>
      <p class="camera__nota" style="white-space:pre-line"><?= Support::e($accesso) ?></p>
    </div>
  <?php endif; ?>
  <div class="stack" style="margin-top:20px;gap:12px">
    <?php foreach ($lista('checkin_steps') as $i => $passo): ?>
      <div class="step"><span class="n"><?= $i + 1 ?></span><p style="white-space:pre-line"><?= Support::e($passo) ?></p></div>
    <?php endforeach; ?>
  </div>
  <?php if ($val('checkin_note') !== ''): ?>
    <p class="note" style="margin-top:14px"><?= Icon::svg('info', 19) ?><span><?= Support::e($val('checkin_note')) ?></span></p>
  <?php endif; ?>
  <?php foreach (['late_arrival' => 'clock', 'documents' => 'doc'] as $k => $ico): if ($val($k) === '') continue; ?>
    <div class="stack" style="margin-top:20px;gap:6px">
      <span class="kicker"><?= Icon::svg($ico, 13) ?> <?= Support::e(I18n::t($loc, $k)) ?></span>
      <p style="white-space:pre-line;line-height:24px"><?= Support::e($val($k)) ?></p>
    </div>
  <?php endforeach; ?>
  <?php if ($imposta !== '' || $val('tax_notes') !== ''): ?>
    <div class="panel stack" style="margin-top:20px;gap:6px">
      <span class="kicker"><?= Icon::svg('euro', 13) ?> <?= Support::e(I18n::t($loc, 'tourist_tax')) ?></span>
      <?php if ($imposta !== ''): ?><b style="font-size:20px;font-weight:500"><?= Support::e(I18n::t($loc, 'tax_per_night', $imposta)) ?></b><?php endif; ?>
      <?php if ($notti !== ''): ?><p class="small"><?= Support::e(I18n::t($loc, 'tax_max', $notti)) ?></p><?php endif; ?>
      <?php if ($val('tax_notes') !== ''): ?><p class="small muted" style="white-space:pre-line"><?= Support::e($val('tax_notes')) ?></p><?php endif; ?>
    </div>
  <?php endif; ?>
  <div class="stack" style="margin-top:28px;gap:10px">
    <span class="kicker"><?= Support::e(I18n::t($loc, 'departure')) ?> · <?= Support::e(I18n::t($loc, 'checkout_by', $pr['checkout_by'])) ?></span>
    <?php if ($lista('checkout_steps')): ?>
      <ul class="lined">
        <?php foreach ($lista('checkout_steps') as $voce): ?><li><?= Support::e($voce) ?></li><?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <?php if ($val('checkout_notes') !== ''): ?><p style="white-space:pre-line;line-height:24px"><?= Support::e($val('checkout_notes')) ?></p><?php endif; ?>
  </div>

<?php /* --------------------------------------------------- luoghi consigliati */ ?>
<?php elseif (SectionCatalog::hasPlaces($kind)): ?>
  <?php if ($val('intro') !== ''): ?><p class="muted" style="margin-top:14px;line-height:23px"><?= Support::e($val('intro')) ?></p><?php endif; ?>
  <?php $luoghi = $sec['places'] ?? [];
        $categorie = array_values(array_unique(array_filter(array_map(fn($pl) => trim((string) Guide::ptr($pl, $loc, $def)['category']), $luoghi)))); ?>
  <?php if (count($categorie) > 1): ?>
    <div class="chips" style="margin-top:18px">
      <button type="button" class="on" data-filtro=""><?= Support::e(I18n::t($loc, 'all')) ?></button>
      <?php foreach ($categorie as $c): ?><button type="button" data-filtro="<?= Support::e($c) ?>"><?= Support::e($c) ?></button><?php endforeach; ?>
    </div>
  <?php endif; ?>
  <div class="stack" style="margin-top:18px;gap:12px">
    <?php foreach ($luoghi as $pl): $ptr = Guide::ptr($pl, $loc, $def); $foto = Guide::img($pl);
          $meta = array_filter([$ptr['category'],
              $pl['walk_minutes'] ? I18n::t($loc, 'walk_min', (int) $pl['walk_minutes']) : '',
              $pl['drive_minutes'] ? I18n::t($loc, 'drive_min', (int) $pl['drive_minutes']) : '',
              $pl['distance'] ?? '']);
          $mapsUrl = $maps((string) $pl['address'], (string) $pl['maps_url']); ?>
      <article class="place" data-categoria="<?= Support::e($ptr['category']) ?>" style="flex-direction:column;align-items:stretch;gap:12px">
        <div class="row" style="gap:14px;flex-wrap:nowrap;align-items:center">
          <?php if ($foto): ?><span class="thumb"><img src="<?= Support::e($foto) ?>" alt="" loading="lazy" decoding="async"></span><?php endif; ?>
          <span class="grow stack" style="gap:5px">
            <b><?= Support::e($pl['name']) ?></b>
            <?php if ($meta): ?><span class="meta"><?= Support::e(implode(' · ', $meta)) ?></span><?php endif; ?>
            <?php if (trim($ptr['badge']) !== ''): ?>
              <span class="badge badge--<?= $pl['badge_tone'] === 'ochre' ? 'ochre-strong' : Support::e($pl['badge_tone'] ?: 'pine') ?>" style="align-self:flex-start"><?= Support::e($ptr['badge']) ?></span>
            <?php endif; ?>
          </span>
        </div>
        <?php if (trim($ptr['description']) !== ''): ?><p class="small" style="line-height:21px"><?= Support::e($ptr['description']) ?></p><?php endif; ?>
        <?php if (trim((string) $pl['address']) !== ''): ?><p class="small muted"><?= Support::e($pl['address']) ?></p><?php endif; ?>
        <?php if (trim($ptr['note']) !== ''): ?><p class="small" style="line-height:21px"><em><?= Support::e($ptr['note']) ?></em></p><?php endif; ?>
        <?php if ($mapsUrl || $pl['phone'] || $pl['website'] || $pl['booking_url']): ?>
          <div class="ctas">
            <?php if ($mapsUrl): ?><a href="<?= Support::e($mapsUrl) ?>" target="_blank" rel="noopener"><?= Icon::svg('pin', 15) ?><?= Support::e(I18n::t($loc, 'open_maps')) ?></a><?php endif; ?>
            <?php if ($pl['phone']): ?><a href="tel:<?= Support::e(Support::telHref($pl['phone'])) ?>"><?= Icon::svg('phone', 15) ?><?= Support::e(I18n::t($loc, 'call_place')) ?></a><?php endif; ?>
            <?php if ($pl['website']): ?><a href="<?= Support::e($pl['website']) ?>" target="_blank" rel="noopener"><?= Icon::svg('globe', 15) ?><?= Support::e(I18n::t($loc, 'visit_site')) ?></a><?php endif; ?>
            <?php if ($pl['booking_url']): ?><a href="<?= Support::e($pl['booking_url']) ?>" target="_blank" rel="noopener"><?= Icon::svg('check', 15) ?><?= Support::e(I18n::t($loc, 'book')) ?></a><?php endif; ?>
          </div>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
  </div>
  <?php if ($val('host_note') !== ''): ?>
    <div class="saying" style="margin-top:18px">
      <p><?= Support::e($val('host_note')) ?></p>
      <cite><?= Support::e(I18n::t($loc, 'host_signature', $pr['host_name'] ?: $pr['name'])) ?></cite>
    </div>
  <?php endif; ?>

<?php /* ------------------------------- riscaldamento e aria condizionata */ ?>
<?php elseif ($kind === 'clima'): include __DIR__ . '/_clima.php'; ?>

<?php /* ------------------------------------------------ tutte le altre sezioni */ ?>
<?php else: ?>
  <?php foreach (SectionCatalog::fields($kind) as $campo => [$tipo]): ?>
    <?php if (in_array($tipo, ['repeater', 'checks', 'toggles', 'time'], true)): include __DIR__ . '/_righe.php'; ?>
    <?php elseif ($tipo === 'steps' && $lista($campo)): ?>
      <div class="stack" style="margin-top:18px;gap:12px">
        <?php foreach ($lista($campo) as $i => $passo): ?>
          <div class="step"><span class="n"><?= $i + 1 ?></span><p style="white-space:pre-line"><?= Support::e($passo) ?></p></div>
        <?php endforeach; ?>
      </div>
    <?php elseif ($kind === 'services' && $campo === 'items'): /* già nell'elenco delle dotazioni */ ?>
    <?php elseif ($tipo === 'list' && $lista($campo)):
          // In Servizi e Regole la lista viene dopo le dotazioni o le regole principali: ha il suo titolo.
          $altro = ['services' => !empty($d['amenities']) || !empty($d['manuals']) ? 'other_amenities' : '',
                    'rules' => !empty($d['flags']) ? 'other_rules' : ''][$kind] ?? ''; ?>
      <?php if ($altro !== ''): ?><span class="kicker" style="display:block;margin-top:22px"><?= Support::e(I18n::t($loc, $altro)) ?></span><?php endif; ?>
      <ul class="lined" style="margin-top:14px">
        <?php foreach ($lista($campo) as $voce): ?><li><?= Support::e($voce) ?></li><?php endforeach; ?>
      </ul>
    <?php elseif ($tipo === 'text' && $val($campo) !== ''): ?>
      <p style="margin-top:14px"><?php if (I18n::has($loc, $campo) || I18n::has('en', $campo)): ?><span class="kicker"><?= Support::e(I18n::t($loc, $campo)) ?></span><?php endif; ?>
        <span style="font-size:17px"><?= Support::e($val($campo)) ?></span></p>
    <?php elseif ($tipo === 'textarea' && $val($campo) !== ''): ?>
      <?php if ($campo === 'ztl'): ?>
        <div class="panel stack" style="margin-top:16px;gap:6px">
          <span class="kicker"><?= Icon::svg('warning', 13) ?> <?= Support::e(I18n::t($loc, 'ztl')) ?></span>
          <p style="white-space:pre-line;line-height:23px"><?= Support::e($val($campo)) ?></p>
        </div>
      <?php elseif ($campo === 'note'): ?>
        <p class="note" style="margin-top:16px"><?= Icon::svg('info', 19) ?><span style="white-space:pre-line"><?= Support::e($val($campo)) ?></span></p>
      <?php else: ?>
        <p style="margin-top:16px;font-size:16px;line-height:24px;white-space:pre-line"><?= Support::e($val($campo)) ?></p>
      <?php endif; ?>
    <?php elseif ($tipo === 'plain' && trim((string) ($d[$campo] ?? '')) !== ''): ?>
      <?php if ($campo === 'emergency_number'): ?>
        <a class="panel" href="tel:<?= Support::e(Support::telHref($d[$campo])) ?>" style="display:flex;align-items:center;justify-content:space-between;margin-top:16px;color:var(--ink)">
          <span class="stack" style="gap:4px"><span class="kicker"><?= Support::e(I18n::t($loc, 'emergency_number')) ?></span>
            <b style="font-size:30px;letter-spacing:1px"><?= Support::e($d[$campo]) ?></b></span>
          <span class="icon-btn icon-btn--accent"><?= Icon::svg('phone', 18) ?></span></a>
      <?php else: ?>
        <div class="stack" style="margin-top:16px;gap:8px">
          <span class="kicker"><?= Support::e(I18n::t($loc, 'address')) ?></span>
          <p style="font-size:17px"><?= Support::e($d[$campo]) ?></p>
          <?php $u = $maps((string) $d[$campo], (string) ($d['maps_url'] ?? '')); if ($u): ?>
            <div class="ctas"><a href="<?= Support::e($u) ?>" target="_blank" rel="noopener"><?= Icon::svg('pin', 15) ?><?= Support::e(I18n::t($loc, 'open_maps')) ?></a></div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  <?php endforeach; ?>
<?php endif; ?>

<?php if ($pdf): ?>
  <div class="ctas" style="margin-top:18px"><a href="<?= Support::e($pdf) ?>" target="_blank" rel="noopener"><?= Icon::svg('doc', 15) ?><?= Support::e(I18n::t($loc, 'open_pdf')) ?></a></div>
<?php endif; ?>

<div class="guest-bottom">
  <?php if ($dopo): ?>
    <a class="btn btn--go grow" href="<?= Support::e($base) ?>/<?= (int) $dopo['id'] ?>?l=<?= Support::e($loc) ?>"
       aria-label="<?= Support::e(I18n::t($loc, 'next')) ?>: <?= Support::e(Guide::title($dopo, $loc, $def)) ?>">
      <?= Support::e(Guide::title($dopo, $loc, $def)) ?><span class="go"><?= Icon::svg('arrow', 18, 2) ?></span></a>
  <?php else: ?>
    <a class="btn btn--ghost grow" href="<?= Support::e($base) ?>?l=<?= Support::e($loc) ?>"><?= Icon::svg('back', 17) ?><?= Support::e(I18n::t($loc, 'back')) ?></a>
  <?php endif; ?>
</div>

<script>
/* Copiare nome della rete e password: se il browser non lo permette il bottone
   sparisce invece di mentire; il valore resta comunque scritto a schermo. */
(function () {
  var bottoni = document.querySelectorAll('[data-copia-di]');
  if (!navigator.clipboard) { bottoni.forEach(function (b) { b.remove(); }); }
  bottoni.forEach(function (b) {
    b.addEventListener('click', function () {
      navigator.clipboard.writeText(b.getAttribute('data-copia-di')).then(function () {
        var prima = b.innerHTML, et = b.getAttribute('aria-label');
        b.innerHTML = '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4.5 12.5l5 5 10-11"/></svg>';
        b.setAttribute('aria-label', <?= json_encode(I18n::t($loc, 'copied')) ?>);
        setTimeout(function () { b.innerHTML = prima; b.setAttribute('aria-label', et); }, 1400);
      });
    });
  });
  var chips = document.querySelectorAll('[data-filtro]');
  chips.forEach(function (c) {
    c.addEventListener('click', function () {
      var q = c.getAttribute('data-filtro');
      chips.forEach(function (x) { x.classList.toggle('on', x === c); x.setAttribute('aria-pressed', x === c ? 'true' : 'false'); });
      document.querySelectorAll('[data-categoria]').forEach(function (p) {
        p.style.display = (q === '' || p.getAttribute('data-categoria') === q) ? '' : 'none';
      });
    });
  });
})();
</script>
