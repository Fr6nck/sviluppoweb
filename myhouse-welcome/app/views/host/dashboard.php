<?php use function MHW\b; use MHW\{Support, Csrf, Media, View, Icon};
$title = $p['name'];
$topnav = View::render('host/_propnav', ['p' => $p, 'qui' => 'guida'], null);
$topright = '<a class="btn btn--ghost btn--sm" href="' . b() . '/g/' . Support::e($p['slug'])
          . '/benvenuto">Vedi come un ospite</a>';
$pubblicata = $p['status'] === 'published';
$copertina = Media::url($p['cover_media_id'] ? (int) $p['cover_media_id'] : null);
$max = $ent['sections']['value'] === 'unlimited' ? PHP_INT_MAX : (int) $ent['sections']['value'];
$tono = ['terracotta' => '#b4451f', 'sea' => '#1c5a78', 'pine' => '#1f6b3f',
         'ochre' => '#b07d0c', 'alert' => '#9c2b20']; ?>

<div class="sheet">
  <div class="stack stack--lg">

    <div class="spread">
      <div class="stack stack--sm">
        <h1><?= Support::e($p['name']) ?>.</h1>
        <div class="row" style="gap:10px">
          <span class="badge badge--<?= $pubblicata ? 'pine' : 'ochre' ?>">
            <span class="dot"></span><?= $pubblicata ? 'Pubblicata' : 'Bozza' ?></span>
          <span class="small muted">
            <a href="<?= b() ?>/g/<?= Support::e($p['slug']) ?>">/g/<?= Support::e($p['slug']) ?></a>
            <?php if ($pubblicata): ?> · <?= $pending ? 'ci sono modifiche non pubblicate' : 'aggiornata' ?><?php endif; ?>
          </span>
        </div>
      </div>
      <form method="post" action="<?= b() ?>/pannello/<?= (int) $p['id'] ?>/pubblica" style="margin:0">
        <?= Csrf::field() ?>
        <button class="btn btn--go">
          <?= !$pubblicata ? 'Pubblica la guida' : ($pending ? 'Pubblica le modifiche' : 'Ripubblica') ?>
          <span class="go"><?= Icon::svg('arrow', 18, 2) ?></span></button>
      </form>
    </div>

    <div class="grid grid-3">
      <div class="stat"><b><?= (int) $stats['aperture'] ?></b><span>aperture negli ultimi 30 giorni</span></div>
      <div class="stat"><b style="font-size:clamp(20px,2.4vw,30px);letter-spacing:-.8px">
        <?= Support::e($stats['piu_letta']) ?></b><span>la sezione più letta</span></div>
      <div class="stat"><b><?= (int) $stats['lingue'] ?></b><span>lingue attive</span></div>
    </div>

    <div class="stack" style="gap:10px">
      <div class="spread spread--mid" style="margin-bottom:4px">
        <h2 style="font-size:22px">Le sezioni</h2>
        <form method="post" action="<?= b() ?>/pannello/<?= (int) $p['id'] ?>/sezioni/nuova" style="margin:0">
          <?= Csrf::field() ?>
          <button class="btn btn--ghost btn--sm"><?= Icon::svg('plus', 15, 2) ?>Aggiungi</button></form>
      </div>

      <?php foreach ($sections as $s): $vuota = trim((string) $s['tr']['body']) === ''; ?>
        <a class="rowcard <?= $vuota ? 'rowcard--flag' : '' ?>"
           href="<?= b() ?>/pannello/<?= (int) $p['id'] ?>/sezioni/<?= (int) $s['id'] ?>">
          <span style="color:var(--line-strong);display:flex"><?= Icon::svg('grip', 16) ?></span>
          <b class="grow"><?= Support::e($s['tr']['title'] ?: 'Senza titolo') ?></b>
          <span class="small muted"><?= (int) $s['locales'] ?> lingua<?= $s['locales'] == 1 ? '' : 'e' ?></span>
          <?php if ($vuota): ?>
            <span class="badge badge--terracotta">Da scrivere</span>
          <?php else: ?>
            <span class="badge badge--pine">Pronta</span>
          <?php endif; ?>
          <span style="width:10px;height:10px;border-radius:999px;flex:none;
                       background:<?= $tono[$s['color']] ?? '#b4451f' ?>"></span>
        </a>
      <?php endforeach; ?>

      <?php if (!$sections): ?>
        <p class="note"><?= Icon::svg('info', 19) ?><span>Nessuna sezione ancora. Aggiungetene una:
          il Wi-Fi è quella che gli ospiti cercano per prima.</span></p>
      <?php endif; ?>

      <?php if (count($sections) >= $max): ?>
        <div class="rowcard rowcard--locked">
          <span class="grow muted" style="font-size:17px;font-weight:500;letter-spacing:-.4px">Un'altra sezione</span>
          <span class="badge badge--ochre">Nel piano superiore</span>
        </div>
      <?php endif; ?>
    </div>

    <p class="tiny muted">Sezioni comprese nel piano:
      <?= $max === PHP_INT_MAX ? 'senza limite' : (int) $max ?>. Usate: <?= count($sections) ?>.</p>
  </div>

  <div class="stack" style="gap:14px">
    <span class="kicker">Come la vedono gli ospiti</span>
    <div class="preview">
      <?php if ($copertina): ?>
        <div class="preview__shot"><img src="<?= Support::e($copertina) ?>" alt=""></div>
      <?php endif; ?>
      <div class="preview__body">
        <span class="preview__title">Benvenuti<br>a <?= Support::e($p['name']) ?>.</span>
        <div class="preview__grid">
          <?php foreach (array_slice($sections, 0, 4) as $s): ?>
            <i style="background:<?= $tono[$s['color']] ?? '#b4451f' ?>"></i>
          <?php endforeach; ?>
        </div>
        <?php if ($qr): ?>
          <p class="tiny muted">QR scansionato <?= (int) $qr['scans'] ?> volte.
            <a href="<?= b() ?>/pannello/<?= (int) $p['id'] ?>/qr">Stampalo</a>.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
