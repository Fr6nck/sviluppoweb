<?php
/* I campi a righe e a spunte di una sezione: emergenze, rifiuti, dotazioni e
   istruzioni, regole, parcheggi, come arrivare. Si include da section.php dentro
   il giro dei campi: ci sono $campo, $tipo, $sec, $d, $loc, $def, $maps. */
use MHW\{Support, Icon, Guide, Media, I18n};
$righe = $tipo === 'repeater' ? Guide::rows($sec, $campo, $loc, $def) : [];
$pieno = fn(array $r, array $chiavi) => (bool) array_filter($chiavi, fn($k) => trim((string) ($r[$k] ?? '')) !== '');
$giorni = fn(array $gg) => implode(', ', array_map(fn($g) => I18n::t($loc, 'day_' . (int) $g), $gg));

/* ----------------------------------------------------------- emergenze */
if ($campo === 'contacts'):
    $righe = array_filter($righe, fn($r) => $pieno($r, ['name', 'phone'])); if ($righe): ?>
  <div class="stack" style="margin-top:16px;gap:10px">
    <?php foreach ($righe as $r): $nome = trim((string) $r['name']); $tel = trim((string) $r['phone']); ?>
      <div class="panel numero">
        <span class="grow stack" style="gap:3px">
          <b><?= Support::e($nome !== '' ? $nome : $tel) ?></b>
          <?php if ($tel !== '' && $nome !== ''): ?><span class="numero__tel"><?= Support::e($tel) ?></span><?php endif; ?>
          <?php if (trim((string) $r['note']) !== ''): ?><span class="small muted"><?= Support::e($r['note']) ?></span><?php endif; ?>
        </span>
        <?php if ($tel !== ''): ?>
          <a class="btn btn--sm numero__chiama" href="tel:<?= Support::e(Support::telHref($tel)) ?>"
             aria-label="<?= Support::e(I18n::t($loc, 'call', $nome !== '' ? $nome : $tel)) ?>"><?= Icon::svg('phone', 15) ?><?= Support::e(I18n::t($loc, 'call_host')) ?></a>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif;

/* ------------------------------------------------------------- rifiuti */
elseif ($campo === 'bins'):
    $righe = array_values(array_filter($righe, fn($r) => ($r['type'] ?? 'altro') !== 'altro' || $pieno($r, ['label', 'where']) || !empty($r['days'])));
    if ($righe):
        // «Oggi si butta»: il giorno di oggi in Italia, non quello del telefono dell'ospite.
        $oggi = (int) (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Rome')))->format('N');
        $nomeDi = fn(array $r) => ($r['type'] ?? 'altro') !== 'altro' ? I18n::t($loc, 'waste_' . $r['type'])
                                  : (trim((string) $r['label']) !== '' ? trim((string) $r['label']) : I18n::t($loc, 'waste_altro'));
        $conGiorni = array_filter($righe, fn($r) => !empty($r['days']));
        $oggiSi = array_map($nomeDi, array_filter($conGiorni, fn($r) => in_array($oggi, array_map('intval', (array) $r['days']), true))); ?>
  <?php if ($conGiorni): ?>
    <p class="oggi-rifiuti<?= $oggiSi ? ' oggi-rifiuti--si' : '' ?>" style="margin-top:16px"><?= Icon::svg('bin', 19) ?>
      <span><?= Support::e($oggiSi ? I18n::t($loc, 'waste_today', implode(', ', $oggiSi)) : I18n::t($loc, 'waste_today_none')) ?></span></p>
  <?php endif; ?>
  <div class="stack" style="margin-top:14px;gap:10px">
    <?php foreach ($righe as $r): $colore = (string) ($r['color'] ?? ''); $label = trim((string) $r['label']);
          $dett = ($r['type'] ?? 'altro') !== 'altro' ? $label : ''; ?>
      <div class="panel rifiuto">
        <?php if ($colore !== ''): ?>
          <span class="rifiuto__colore rifiuto__colore--<?= Support::e($colore) ?>" role="img" aria-label="<?= Support::e(I18n::t($loc, 'bin_color', I18n::t($loc, 'color_' . $colore))) ?>"></span>
        <?php else: ?><span class="rifiuto__colore" aria-hidden="true"><?= Icon::svg('bin', 18) ?></span><?php endif; ?>
        <span class="stack" style="gap:3px">
          <b><?= Support::e($nomeDi($r)) ?></b>
          <?php if ($dett !== ''): ?><span class="small"><?= Support::e($dett) ?></span><?php endif; ?>
          <?php if (!empty($r['days'])): ?><span class="small"><?= Icon::svg('clock', 13) ?> <?= Support::e($giorni((array) $r['days'])) ?></span><?php endif; ?>
          <?php if (trim((string) $r['where']) !== ''): ?><span class="small muted"><?= Icon::svg('pin', 13) ?> <?= Support::e($r['where']) ?></span><?php endif; ?>
        </span>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif;

/* -------------------------------------------------- servizi: dotazioni */
elseif ($tipo === 'checks' && !empty($d[$campo])): ?>
  <div class="stack" style="margin-top:18px;gap:10px">
    <span class="kicker"><?= Support::e(I18n::t($loc, 'amenities')) ?></span>
    <ul class="dotazioni-ospite">
      <?php foreach ((array) $d[$campo] as $k): ?>
        <li><?= Icon::svg(Icon::amenita((string) $k), 20) ?><span><?= Support::e(I18n::t($loc, 'amen_' . $k)) ?></span></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php

/* ------------------------------------------------- servizi: istruzioni */
elseif ($campo === 'manuals'):
    $righe = array_filter($righe, fn($r) => $pieno($r, ['title', 'steps', 'photo', 'pdf'])); if ($righe): ?>
  <div class="stack" style="margin-top:22px;gap:10px">
    <span class="kicker"><?= Support::e(I18n::t($loc, 'manuals')) ?></span>
    <?php foreach ($righe as $r):
          $passi = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $r['steps']) ?: []), fn($x) => $x !== ''));
          $foto = (int) $r['photo'] ? Media::url((int) $r['photo']) : null; $doc = (int) $r['pdf'] ? Media::url((int) $r['pdf']) : null; ?>
      <details class="panel manuale">
        <summary><b><?= Support::e(trim((string) $r['title']) !== '' ? $r['title'] : I18n::t($loc, 'manuals')) ?></b><?= Icon::svg('chevron', 16, 2, 'manuale__freccia') ?></summary>
        <div class="stack" style="gap:12px;margin-top:12px">
          <?php if ($foto): ?><div class="shot shot--h186"><img src="<?= Support::e($foto) ?>" alt="" loading="lazy" decoding="async"></div><?php endif; ?>
          <?php foreach ($passi as $i => $passo): ?>
            <div class="step"><span class="n"><?= $i + 1 ?></span><p><?= Support::e($passo) ?></p></div>
          <?php endforeach; ?>
          <?php if ($doc): ?><div class="ctas"><a href="<?= Support::e($doc) ?>" target="_blank" rel="noopener"><?= Icon::svg('doc', 15) ?><?= Support::e(I18n::t($loc, 'open_pdf')) ?></a></div><?php endif; ?>
        </div>
      </details>
    <?php endforeach; ?>
  </div>
<?php endif;

/* ----------------------------------------------------- regole: sì / no */
elseif ($tipo === 'toggles' && !empty($d[$campo])):
    $icone = ['smoking' => 'smoke', 'pets' => 'paw', 'parties' => 'music', 'visitors' => 'people']; ?>
  <ul class="regole-ospite" style="margin-top:18px">
    <?php foreach ((array) $d[$campo] as $k => $v): if (!in_array($v, ['si', 'no'], true) || !isset($icone[$k])) continue; ?>
      <li class="regola regola--<?= $v ?>"><span class="regola__icona"><?= Icon::svg($icone[$k], 19) ?><?php if ($v === 'no'): ?><?= Icon::svg('ban', 26, 1.6, 'regola__divieto') ?><?php endif; ?></span>
        <span><?= Support::e(I18n::t($loc, 'rule_' . $k . '_' . $v)) ?></span></li>
    <?php endforeach; ?>
  </ul>
<?php

/* ------------------------------------------------- regole: il silenzio */
elseif ($campo === 'quiet_from'):
    $da = (string) ($d['quiet_from'] ?? ''); $a = (string) ($d['quiet_to'] ?? '');
    if ($da !== '' || $a !== ''): ?>
  <p class="note" style="margin-top:14px"><?= Icon::svg('moon', 19) ?>
    <span><?= Support::e($da !== '' && $a !== '' ? I18n::t($loc, 'quiet_hours', $da, $a) : ($da !== '' ? I18n::t($loc, 'quiet_from', $da) : I18n::t($loc, 'quiet_to', $a))) ?></span></p>
<?php endif;

/* --------------------------------------------------------- parcheggio */
elseif ($campo === 'options'):
    $righe = array_filter($righe, fn($r) => $pieno($r, ['type', 'name', 'address', 'maps_url', 'cost', 'instructions', 'photo'])); if ($righe): ?>
  <div class="stack" style="margin-top:18px;gap:12px">
    <?php foreach ($righe as $r): $u = $maps(trim((string) $r['address']), trim((string) $r['maps_url']));
          $foto = (int) $r['photo'] ? Media::url((int) $r['photo']) : null; ?>
      <div class="panel stack" style="gap:8px">
        <?php if ($r['type'] !== ''): ?><span class="badge badge--sea" style="align-self:flex-start"><?= Support::e(I18n::t($loc, 'park_' . $r['type'])) ?></span><?php endif; ?>
        <?php if (trim((string) $r['name']) !== ''): ?><b style="font-size:18px;font-weight:500"><?= Support::e($r['name']) ?></b><?php endif; ?>
        <?php if (trim((string) $r['address']) !== ''): ?><p class="small muted"><?= Icon::svg('pin', 13) ?> <?= Support::e($r['address']) ?></p><?php endif; ?>
        <?php if (trim((string) $r['cost']) !== ''): ?><p class="small"><span class="kicker"><?= Support::e(I18n::t($loc, 'cost')) ?></span> <?= Support::e($r['cost']) ?></p><?php endif; ?>
        <?php if (trim((string) $r['instructions']) !== ''): ?><p style="white-space:pre-line;line-height:23px"><?= Support::e($r['instructions']) ?></p><?php endif; ?>
        <?php if ($foto): ?><div class="shot shot--h186"><img src="<?= Support::e($foto) ?>" alt="" loading="lazy" decoding="async"></div><?php endif; ?>
        <?php if ($u): ?><div class="ctas"><a href="<?= Support::e($u) ?>" target="_blank" rel="noopener"><?= Icon::svg('pin', 15) ?><?= Support::e(I18n::t($loc, 'open_maps')) ?></a></div><?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif;

/* ---------------------------------------------------- come arrivare */
elseif ($campo === 'routes'):
    $righe = array_values(array_filter($righe, fn($r) => $pieno($r, ['steps']) || ($r['mode'] ?? '') !== ''));
    $icone = ['auto' => 'car', 'treno' => 'train', 'aereo' => 'plane', 'autobus' => 'bus', '' => 'compass']; ?>
  <div class="stack" style="margin-top:18px;gap:10px">
    <?php foreach ($righe as $r): $modo = (string) ($r['mode'] ?? '');
          $passi = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $r['steps']) ?: []), fn($x) => $x !== '')); ?>
      <details class="panel manuale" <?= count($righe) === 1 ? 'open' : '' ?>>
        <summary><span class="row" style="gap:10px;flex-wrap:nowrap"><?= Icon::svg($icone[$modo] ?? 'compass', 19) ?>
          <b><?= Support::e(I18n::t($loc, $modo !== '' ? 'route_' . $modo : 'directions')) ?></b></span><?= Icon::svg('chevron', 16, 2, 'manuale__freccia') ?></summary>
        <div class="stack" style="gap:12px;margin-top:12px">
          <?php foreach ($passi as $i => $passo): ?>
            <div class="step"><span class="n"><?= $i + 1 ?></span><p><?= Support::e($passo) ?></p></div>
          <?php endforeach; ?>
        </div>
      </details>
    <?php endforeach; ?>
  </div>
<?php endif;
