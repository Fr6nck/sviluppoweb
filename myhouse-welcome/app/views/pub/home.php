<?php use function MHW\b; use function MHW\a; use MHW\{Support, Icon, Translator};
$title = 'MyHouse Welcome — la guida della vostra casa';

/** Una funzione del piano, detta come la direbbe una persona. */
$riga = function (array $f): array {
    $v = $f['value'];
    $spento = ($v === '0' || $v === '');
    $testo = match ($f['code']) {
        'sections'   => $v === 'unlimited' ? 'Sezioni illimitate' : 'Fino a ' . (int) $v . ' sezioni',
        // Senza una chiave di traduzione la promessa "tradotte da sole" sarebbe falsa.
        'locales'    => (int) $v <= 1 ? 'Una lingua'
                        : (int) $v . (Translator::enabled() ? ' lingue, tradotte da sole' : ' lingue pubblicabili'),
        'photos'     => 'Foto nelle sezioni',
        'places'     => 'Consigli sul posto',
        'properties' => (int) $v <= 1 ? 'Una struttura' : 'Fino a ' . (int) $v . ' strutture',
        'branding'   => 'Colori e logo vostri',
        'analytics'  => 'Statistiche di lettura',
        default      => $f['label'],
    };
    return [$testo, $spento];
}; ?>

<section class="hero">
  <h1 class="display">La casa risponde<br>prima che chiedano.</h1>
  <p>Una guida digitale per la vostra casa vacanze. Wi-Fi, chiavi, orari, i posti giusti —
     in quattro lingue, dietro un QR sul frigo.</p>
  <div class="row" style="justify-content:center">
    <a class="btn btn--lg btn--go" href="<?= b() ?>/registrati">
      Create la vostra guida <span class="go"><?= Icon::svg('arrow', 19, 2) ?></span></a>
    <?php if ($vetrina): ?>
      <a class="btn btn--lg btn--ghost" href="<?= b() ?>/g/<?= Support::e($vetrina['slug']) ?>/benvenuto">Guardane una vera</a>
    <?php endif; ?>
  </div>
</section>

<div class="shot shot--wide shot--h380">
  <img src="<?= Support::e($copertina) ?>" alt="Una casa in pietra fra gli ulivi, all'ora del tramonto">
  <?php if ($vetrina): ?>
    <span class="shot-pill"><span class="dot"></span>
      <?= Support::e($vetrina['name']) ?><?= $vetrina['city'] ? ' · ' . Support::e($vetrina['city']) : '' ?>
      <?= $vetrina['aperture'] ? ' · ' . (int) $vetrina['aperture'] . ' aperture questo mese' : '' ?></span>
  <?php endif; ?>
</div>

<section class="grid grid-3" style="margin-top:56px">
  <div class="tile tile--big t-sea">
    <span class="kicker">Passaggio 1</span>
    <div class="stack" style="gap:10px"><b>Rispondete<br>a sei domande</b><p>Venti minuti, una volta sola.</p></div>
  </div>
  <div class="tile tile--big t-pine">
    <span class="kicker">Passaggio 2</span>
    <div class="stack" style="gap:10px"><b>Stampate<br>il QR</b><p>L'indirizzo non cambia mai più.</p></div>
  </div>
  <div class="tile tile--big t-ochre">
    <span class="kicker">Passaggio 3</span>
    <div class="stack" style="gap:10px"><b>Smettete<br>di rispondere</b><p>Alle stesse domande, ogni settimana.</p></div>
  </div>
</section>

<section style="margin-top:56px">
  <div class="spread">
    <h2 style="font-size:clamp(32px,4.4vw,46px);line-height:1">Tre piani. Si cambia<br>quando volete.</h2>
    <p class="muted" style="max-width:340px;line-height:24px">Se scendete di piano non perdete niente di quello che
      avete già scritto: resta lì, in attesa.</p>
  </div>

  <div class="grid grid-3" style="margin-top:28px">
    <?php foreach ($packages as $pk): $scuro = $pk['code'] === 'plus'; ?>
      <div class="plan <?= $scuro ? 'plan--dark' : '' ?>">
        <div class="spread spread--mid" style="gap:12px">
          <div class="stack" style="gap:8px">
            <span class="name"><?= Support::e($pk['name']) ?></span>
            <span class="muted" style="font-size:15px"><?= Support::e($pk['tagline']) ?></span>
          </div>
          <?php if ($scuro): ?><span class="badge badge--ochre-strong">Il più scelto</span><?php endif; ?>
        </div>
        <span class="price"><?= Support::e(Support::money((int) $pk['price_cents'], $pk['currency'])) ?><small> / anno</small></span>
        <ul>
          <li>Guida completa e QR permanente</li>
          <?php foreach ($pk['features'] as $f): [$testo, $spento] = $riga($f);
            if ($f['code'] === 'sections' || $spento) continue; ?>
            <li><?= Support::e($testo) ?></li>
          <?php endforeach; ?>
          <?php foreach ($pk['features'] as $f): [$testo, $spento] = $riga($f);
            if ($f['code'] !== 'sections') continue; ?>
            <li><?= Support::e($testo) ?></li>
          <?php endforeach; ?>
        </ul>
        <a class="btn <?= $scuro ? '' : 'btn--ghost' ?>" href="<?= b() ?>/registrati?piano=<?= (int) $pk['pv_id'] ?>">
          Scegli <?= Support::e($pk['name']) ?></a>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section class="band" style="margin-top:56px">
  <div class="stack" style="gap:12px">
    <h2 style="font-size:clamp(30px,4vw,42px);line-height:1">Venti minuti oggi,<br>una stagione tranquilla.</h2>
    <p class="muted" style="font-size:17px;line-height:26px;max-width:480px">Provate a crearne una.
      Si paga solo quando decidete di pubblicarla.</p>
  </div>
  <a class="btn btn--lg btn--go" href="<?= b() ?>/registrati">
    Comincia <span class="go"><?= Icon::svg('arrow', 19, 2) ?></span></a>
</section>
