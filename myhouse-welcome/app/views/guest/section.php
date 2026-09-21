<?php use function MHW\b; use MHW\{Support, Icon};
$pr = $snap['property'];
$t = $sec['tr'][$loc] ?? $sec['tr'][$pr['default_locale']] ?? ['title' => '', 'body' => ''];
$title = $t['title'] . ' — ' . $pr['name'];
$coda = '/' . (int) $sec['id'];
$notte = $sec['kind'] === 'wifi';
$theme = $notte ? 'night' : 'light';

/* I capoversi del testo: l'host scrive normalmente, qui diventano passaggi.
   Un capoverso che comincia con "Nota:" non e' un passaggio ma un avviso, e
   finisce nel riquadro ocra in fondo: e' l'unica convenzione da imparare. */
$tutti = array_values(array_filter(array_map('trim', preg_split('/\R{2,}/', (string) $t['body']) ?: [])));
$capoversi = []; $note = [];
foreach ($tutti as $c) {
    if (preg_match('/^nota\s*:\s*/iu', $c)) {
        $testo = (string) preg_replace('/^nota\s*:\s*/iu', '', $c);
        // Tolta l'etichetta, la frase comincia da capo: anche la maiuscola.
        $note[] = mb_strtoupper(mb_substr($testo, 0, 1)) . mb_substr($testo, 1);
    }
    else $capoversi[] = $c;
}

/* La sezione che viene dopo: l'invito in fondo alla pagina. */
$dopo = null; $trovata = false;
foreach ($snap['sections'] as $s) {
    if ($trovata) { $dopo = $s; break; }
    if ((int) $s['id'] === (int) $sec['id']) $trovata = true;
}
$titoloDopo = $dopo ? ($dopo['tr'][$loc]['title'] ?? $dopo['tr'][$pr['default_locale']]['title'] ?? '') : '';
$indietro = b() . '/g/' . Support::e($slug) . '?l=' . Support::e($loc); ?>

<div class="guest-top">
  <a class="icon-btn" href="<?= $indietro ?>" aria-label="Torna alla guida"><?= Icon::svg('back', 18, 1.9) ?></a>
  <?php if ($notte): ?>
    <span class="pill-quiet"><?= Icon::svg('moon', 13) ?>Tema notte</span>
  <?php else: ?>
    <?php include __DIR__ . '/_lingue.php'; ?>
  <?php endif; ?>
</div>

<div class="stack stack--sm" style="margin-top:20px">
  <?php if ($sec['kind'] === 'checkin' && $capoversi): ?>
    <span class="kicker">Arrivo · <?= count($capoversi) + ($sec['door_code'] ? 1 : 0) ?> passaggi</span>
  <?php endif; ?>
  <h1 class="guest-title" style="font-size:38px;line-height:38px"><?= Support::e($t['title']) ?></h1>
</div>

<?php if ($sec['image']): ?>
  <div class="shot shot--h186" style="margin-top:20px">
    <img src="<?= Support::e($sec['image']) ?>" alt="<?= Support::e($sec['image_alt']) ?>"></div>
<?php endif; ?>

<?php /* ------------------------------------------------------------- Wi-Fi */ ?>
<?php if ($notte && ($sec['wifi_ssid'] !== '' || $sec['wifi_pass'] !== '')): ?>
  <div class="panel stack" style="margin-top:20px;gap:18px">
    <?php if ($sec['wifi_ssid'] !== ''): ?>
      <div class="stack stack--sm" style="gap:6px">
        <span class="kicker">Rete</span>
        <div class="copyline">
          <b data-copia="<?= Support::e($sec['wifi_ssid']) ?>"><?= Support::e($sec['wifi_ssid']) ?></b>
          <button type="button" class="icon-btn icon-btn--strong" data-copia-di="<?= Support::e($sec['wifi_ssid']) ?>"
                  aria-label="Copia il nome della rete"><?= Icon::svg('copy', 17) ?></button>
        </div>
      </div>
    <?php endif; ?>
    <?php if ($sec['wifi_ssid'] !== '' && $sec['wifi_pass'] !== ''): ?><hr class="rule"><?php endif; ?>
    <?php if ($sec['wifi_pass'] !== ''): ?>
      <div class="stack stack--sm" style="gap:6px">
        <span class="kicker">Password</span>
        <div class="copyline">
          <b class="pw"><?= Support::e($sec['wifi_pass']) ?></b>
          <button type="button" class="icon-btn icon-btn--accent" data-copia-di="<?= Support::e($sec['wifi_pass']) ?>"
                  aria-label="Copia la password"><?= Icon::svg('copy', 17, 2) ?></button>
        </div>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php /* -------------------------------------------------- arrivo: i passaggi */ ?>
<?php if ($sec['kind'] === 'checkin'): ?>
  <div class="stack" style="margin-top:20px;gap:12px">
    <?php foreach ($capoversi as $i => $c): ?>
      <div class="step">
        <span class="n"><?= $i + 1 ?></span>
        <p style="white-space:pre-line"><?= Support::e($c) ?></p>
      </div>
    <?php endforeach; ?>
    <?php if ($sec['door_code'] !== ''): ?>
      <div class="step">
        <span class="n"><?= count($capoversi) + 1 ?></span>
        <div class="stack stack--sm" style="gap:10px;min-width:0">
          <h2>Il codice</h2>
          <div class="row" style="gap:10px">
            <span class="bigcode"><?= Support::e(trim(chunk_split($sec['door_code'], 1, ' '))) ?></span>
            <button type="button" class="icon-btn icon-btn--strong" data-copia-di="<?= Support::e($sec['door_code']) ?>"
                    aria-label="Copia il codice"><?= Icon::svg('copy', 17) ?></button>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>

<?php /* --------------------------------------------------- i luoghi consigliati */ ?>
<?php elseif ($sec['places']): ?>
  <?php if ($capoversi): ?>
    <p class="muted" style="margin-top:14px;max-width:300px;line-height:23px"><?= Support::e($capoversi[0]) ?></p>
  <?php endif; ?>
  <?php $categorie = array_values(array_unique(array_filter(array_column($sec['places'], 'category')))); ?>
  <?php if (count($categorie) > 1): ?>
    <div class="chips" style="margin-top:18px">
      <button type="button" class="on" data-filtro="">Tutti</button>
      <?php foreach ($categorie as $c): ?>
        <button type="button" data-filtro="<?= Support::e($c) ?>"><?= Support::e($c) ?></button>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <div class="stack" style="margin-top:18px;gap:12px">
    <?php foreach ($sec['places'] as $pl): ?>
      <div class="place" data-categoria="<?= Support::e($pl['category']) ?>">
        <?php if ($pl['image']): ?>
          <span class="thumb"><img src="<?= Support::e($pl['image']) ?>" alt="<?= Support::e($pl['image_alt']) ?>"></span>
        <?php endif; ?>
        <span class="grow stack" style="gap:5px">
          <b><?= Support::e($pl['name']) ?></b>
          <span class="meta"><?= Support::e(trim($pl['category'] . ' · ' . $pl['distance'], ' ·')) ?></span>
          <?php if ($pl['badge']): ?>
            <span class="badge badge--<?= $pl['badge_tone'] === 'ochre' ? 'ochre-strong' : Support::e($pl['badge_tone']) ?>"
                  style="align-self:flex-start"><?= Support::e($pl['badge']) ?></span>
          <?php endif; ?>
        </span>
      </div>
    <?php endforeach; ?>
  </div>
  <?php if (count($capoversi) > 1): ?>
    <div class="saying" style="margin-top:18px">
      <p><?= Support::e(implode("\n\n", array_slice($capoversi, 1))) ?></p>
      <cite><?= Support::e($pr['host_name'] ?: 'chi ospita') ?>, la vostra host</cite>
    </div>
  <?php endif; ?>

<?php /* ------------------------------------------------ tutto il resto: testo */ ?>
<?php else: ?>
  <?php foreach ($capoversi as $c): ?>
    <p style="margin-top:16px;font-size:17px;line-height:26px;white-space:pre-line"><?= Support::e($c) ?></p>
  <?php endforeach; ?>
<?php endif; ?>

<?php foreach ($note as $n): ?>
  <p class="note" style="margin-top:16px"><?= Icon::svg('info', 19) ?><span><?= Support::e($n) ?></span></p>
<?php endforeach; ?>

<?php if ($dopo): ?>
  <div class="guest-bottom">
    <a class="btn btn--go grow" href="<?= b() ?>/g/<?= Support::e($slug) ?>/<?= (int) $dopo['id'] ?>?l=<?= Support::e($loc) ?>">
      <?= Support::e($titoloDopo ?: 'Continua') ?>
      <span class="go"><?= Icon::svg('arrow', 18, 2) ?></span>
    </a>
  </div>
<?php else: ?>
  <div class="guest-bottom">
    <a class="btn btn--ghost grow" href="<?= $indietro ?>"><?= Icon::svg('back', 17) ?>Torna alla guida</a>
  </div>
<?php endif; ?>

<script>
/* Copiare il codice o la password: se il browser non lo permette, il bottone
   sparisce invece di mentire. Il valore resta comunque scritto a schermo. */
(function () {
  var bottoni = document.querySelectorAll('[data-copia-di]');
  if (!navigator.clipboard) { bottoni.forEach(function (b) { b.remove(); }); return; }
  bottoni.forEach(function (b) {
    b.addEventListener('click', function () {
      navigator.clipboard.writeText(b.getAttribute('data-copia-di')).then(function () {
        var prima = b.innerHTML;
        b.innerHTML = '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 12.5l5 5 10-11"/></svg>';
        setTimeout(function () { b.innerHTML = prima; }, 1400);
      });
    });
  });
  var chips = document.querySelectorAll('[data-filtro]');
  chips.forEach(function (c) {
    c.addEventListener('click', function () {
      var q = c.getAttribute('data-filtro');
      chips.forEach(function (x) { x.classList.toggle('on', x === c); });
      document.querySelectorAll('[data-categoria]').forEach(function (p) {
        p.style.display = (q === '' || p.getAttribute('data-categoria') === q) ? '' : 'none';
      });
    });
  });
})();
</script>
