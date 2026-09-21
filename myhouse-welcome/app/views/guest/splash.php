<?php use function MHW\b; use function MHW\a; use MHW\{Support, Icon, Config};
$pr = $snap['property']; $title = $pr['name'];
$nomi = Config::get('locales');
$dentro = b() . '/g/' . Support::e($slug) . '?l=' . Support::e($loc);
$luogo = trim($pr['city'] . ($pr['region'] ? ', ' . $pr['region'] : '')); ?>

<div class="full" id="soglia">
  <?php if ($pr['cover']): ?>
    <img class="full__photo" src="<?= Support::e($pr['cover']) ?>" alt="<?= Support::e($pr['cover_alt']) ?>">
  <?php endif; ?>
  <div class="full__scrim"></div>

  <div class="full__stage">
    <div class="rise row" style="gap:10px;animation-delay:.1s">
      <svg class="mh-arch arch" width="26" height="26" viewBox="0 0 32 32" fill="none" aria-hidden="true">
        <path d="M6 28V14a10 10 0 0 1 20 0v14" stroke="#f6f0e5" stroke-width="2.2" stroke-linecap="round"></path>
        <circle cx="20.5" cy="19" r="1.9" fill="#ee7a4a"></circle>
      </svg>
      <span style="font-size:14px;font-weight:500;letter-spacing:-.2px;color:#f6f0e5">myhouse welcome</span>
    </div>

    <div class="stack" style="gap:24px">
      <div class="stack" style="gap:12px">
        <span class="kicker rise" style="animation-delay:.34s">La guida della casa</span>
        <h1 class="rise" style="animation-delay:.46s"><?= Support::e($pr['name']) ?></h1>
        <p class="rise" style="animation-delay:.62s">
          <?= $luogo ? Support::e($luogo) . ' — ' : '' ?>tutto quello che vi serve, senza chiedere.</p>
      </div>

      <div class="stack" style="gap:12px">
        <a class="btn btn--lg btn--go rise" id="entra" href="<?= $dentro ?>" style="animation-delay:.8s">
          Entra <span class="go"><?= Icon::svg('arrow', 19, 2) ?></span></a>

        <?php if (count($snap['locales']) > 1): ?>
          <div class="langbar rise" style="animation-delay:.96s">
            <?php foreach ($snap['locales'] as $l): ?>
              <a href="<?= b() ?>/g/<?= Support::e($slug) ?>/benvenuto?l=<?= Support::e($l) ?>"
                 hreflang="<?= Support::e($l) ?>" class="<?= $l === $loc ? 'on' : '' ?>">
                <?= Support::e($nomi[$l] ?? strtoupper($l)) ?></a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div id="velo" style="position:absolute;inset:0;background:#b4451f;pointer-events:none;
                        transform:translateY(101%);z-index:5"></div>
</div>

<script>
/* Il passaggio dalla soglia alla guida è una cosa sola: il velo sale, poi si
   entra. Se il JavaScript non parte, il bottone resta un collegamento normale. */
(function () {
  var a = document.getElementById('entra'), velo = document.getElementById('velo'),
      stage = document.querySelector('.full__stage');
  if (!a || !velo) return;
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  a.addEventListener('click', function (e) {
    e.preventDefault();
    stage.style.transition = 'opacity .5s cubic-bezier(.5,0,.75,0), transform .5s cubic-bezier(.5,0,.75,0)';
    stage.style.opacity = '0'; stage.style.transform = 'translateY(-26px)';
    velo.style.transition = 'transform .62s cubic-bezier(.5,0,.2,1) .18s';
    velo.style.transform = 'translateY(0)';
    setTimeout(function () { window.location.href = a.href; }, 800);
  });
})();
</script>
