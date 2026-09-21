<?php use function MHW\b; use MHW\Support; $title = 'MyHouse Welcome'; ?>
<section style="text-align:center;padding:28px 0 40px">
  <h1 style="max-width:760px;margin:0 auto">La casa risponde prima che chiedano.</h1>
  <p class="muted" style="max-width:520px;margin:20px auto 0;font-size:19px">
    Una guida digitale per la vostra casa vacanze. Wi-Fi, chiavi, orari, i posti giusti —
    in quattro lingue, dietro un QR sul frigo.</p>
  <p style="margin-top:26px"><a class="btn" href="<?= b() ?>/registrati">Create la vostra guida</a></p>
</section>

<section class="grid grid-3" style="margin-bottom:48px">
  <div class="tile t-sea"><span class="kicker" style="color:inherit;opacity:.85">Passaggio 1</span>
    <h3>Rispondete a sei domande</h3></div>
  <div class="tile t-pine"><span class="kicker" style="color:inherit;opacity:.85">Passaggio 2</span>
    <h3>Stampate il QR</h3></div>
  <div class="tile t-ochre"><span class="kicker" style="color:inherit;opacity:.8">Passaggio 3</span>
    <h3>Smettete di rispondere</h3></div>
</section>

<section>
  <h2>Tre piani. Si cambia quando volete.</h2>
  <p class="muted" style="margin-top:10px;max-width:560px">Se scendete di piano non perdete niente di quello che avete già scritto: resta lì, in attesa.</p>
  <div class="grid grid-3" style="margin-top:24px">
    <?php foreach ($packages as $pk): ?>
      <div class="card stack">
        <div>
          <h3 style="font-family:Gloock,Georgia,serif;font-size:26px"><?= Support::e($pk['name']) ?></h3>
          <p class="muted small" style="margin:6px 0 0"><?= Support::e($pk['tagline']) ?></p>
        </div>
        <p style="font-size:34px;margin:0;font-family:Gloock,Georgia,serif">
          <?= Support::e(Support::money((int) $pk['price_cents'], $pk['currency'])) ?>
          <span class="muted" style="font-size:15px;font-family:Onest,sans-serif"> / anno</span></p>
        <ul style="margin:0;padding-left:18px" class="small">
          <?php foreach ($pk['features'] as $f): if ($f['value'] === '0') continue; ?>
            <li><?= Support::e($f['label']) ?>:
              <strong><?= $f['value'] === 'unlimited' ? 'senza limite' : ($f['kind'] === 'bool' ? 'sì' : Support::e($f['value'])) ?></strong></li>
          <?php endforeach; ?>
        </ul>
        <a class="btn btn--block" href="<?= b() ?>/registrati?piano=<?= (int) $pk['pv_id'] ?>">Scegli <?= Support::e($pk['name']) ?></a>
      </div>
    <?php endforeach; ?>
  </div>
</section>
