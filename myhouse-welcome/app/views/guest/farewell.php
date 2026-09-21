<?php use function MHW\b; use MHW\{Support, Icon};
$pr = $snap['property']; $title = 'Buon viaggio — ' . $pr['name'];
$tel = preg_replace('/\D/', '', (string) $pr['host_whatsapp']); ?>

<div class="full" id="congedo">
  <?php if ($pr['cover']): ?>
    <img class="full__photo" src="<?= Support::e($pr['cover']) ?>" alt="<?= Support::e($pr['cover_alt']) ?>"
         style="transform-origin:50% 40%">
  <?php endif; ?>
  <div class="full__scrim" style="background:linear-gradient(180deg,rgba(23,19,13,.70) 0%,rgba(23,19,13,.42) 24%,rgba(23,19,13,.80) 58%,rgba(23,19,13,.97) 100%)"></div>

  <div class="full__stage">
    <div class="rise" style="display:flex;align-items:center;justify-content:space-between;gap:12px;animation-delay:.1s">
      <span style="font-size:14px;font-weight:500;letter-spacing:-.2px;color:#f6f0e5"><?= Support::e($pr['name']) ?></span>
      <span class="pill-quiet" style="border-color:rgba(246,240,229,.32);color:#f6f0e5">
        Partenza entro le <?= Support::e($pr['checkout_by']) ?></span>
    </div>

    <div class="stack" style="gap:24px">
      <div class="stack" style="gap:12px">
        <span class="kicker rise" style="animation-delay:.3s">Prima di andare</span>
        <h1 class="rise" style="animation-delay:.42s;font-size:clamp(40px,12vw,50px)">Buon viaggio.</h1>
        <p class="rise" style="animation-delay:.58s"><?= count($cose) ?> cose sole, poi la porta si chiude da sé.</p>
      </div>

      <ul class="checklist">
        <?php foreach ($cose as $i => $c): ?>
          <li class="rise" style="animation-delay:<?= number_format(.70 + $i * .1, 2, '.', '') ?>s">
            <span style="flex:none;color:#6fc48c"><?= Icon::svg('check', 18, 2.4) ?></span>
            <?= Support::e($c) ?></li>
        <?php endforeach; ?>
      </ul>

      <div class="stack" style="gap:10px">
        <?php if ($tel): ?>
          <a class="btn btn--lg btn--go rise" style="animation-delay:1.02s" href="https://wa.me/<?= Support::e($tel) ?>">
            Ho chiuso, arrivederci <span class="go"><?= Icon::svg('arrow', 19, 2) ?></span></a>
        <?php endif; ?>
        <a class="rise" style="display:flex;align-items:center;justify-content:center;height:48px;
                  border-radius:999px;color:#e8dcc8;font-size:15px;font-weight:500;animation-delay:1.1s"
           href="<?= b() ?>/g/<?= Support::e($slug) ?>?l=<?= Support::e($loc) ?>">Torna alla guida</a>
      </div>
    </div>
  </div>
</div>
