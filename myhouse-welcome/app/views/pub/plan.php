<?php
/* La scelta del piano, dopo la registrazione. Non si paga niente qui: il piano
   scelto decide cosa si può configurare, e si paga solo alla pubblicazione. */
use function MHW\{a, b}; use MHW\{Support, Csrf, Icon, Plans}; $title = 'Scegli il piano'; ?>
<div class="stack stack--sm" style="max-width:720px">
  <h1>Scegli la soluzione ideale per te.</h1>
  <p class="lead">Adesso non paghi niente: configuri la guida con le funzioni del piano scelto, e paghi solo quando
    decidi di pubblicarla. Puoi cambiare idea fino a quel momento.</p>
</div>

<?php if ($attivo): ?>
  <p class="note" style="margin-top:24px"><?= Icon::svg('info', 19) ?><span>Hai già un abbonamento attivo.
    Per cambiarlo vai in <a href="<?= b() ?>/account">Account &amp; Fatturazione</a>.</span></p>
<?php else: ?>
<form method="post" style="margin-top:28px"><?= Csrf::field() ?>
  <div class="grid grid-3" style="align-items:stretch">
    <?php foreach ($offers as $of): $p = $of['main']; $famiglia = count($of['options']) > 1; ?>
      <fieldset class="plan" style="margin:0">
        <legend class="sr-only"><?= Support::e($p['name']) ?></legend>
        <div class="spread spread--mid" style="gap:12px">
          <span class="name"><?= Support::e($famiglia ? preg_replace('/\s*\d+$/', '', $p['name']) : $p['name']) ?></span>
          <?php if ($p['badge'] !== ''): ?><span class="badge badge--ochre-strong"><?= Support::e($p['badge']) ?></span><?php endif; ?>
        </div>
        <p class="muted" style="font-size:15px;line-height:22px"><?= Support::e($p['headline']) ?> <?= Support::e($p['description']) ?></p>
        <ul><?php foreach ($p['bullet_list'] as $bl): ?><li><?= Support::e($bl) ?></li><?php endforeach; ?></ul>
        <div class="options" style="margin-top:auto">
          <?php foreach ($of['options'] as $o): $id = (int) $o['pv_id'];
                $sel = $preselezione ? $preselezione === $id : $scelto === $id; ?>
            <label class="swatch" style="flex-direction:row;align-items:center;justify-content:space-between">
              <input type="radio" name="pv" value="<?= $id ?>" <?= $sel ? 'checked' : '' ?> required>
              <span><?= Support::e($famiglia ? $o['tagline'] : $o['name']) ?></span>
              <?php if (!Plans::perProperty($o)): ?>
                <strong><?= Support::e(Support::money((int) $o['price_cents'], $o['currency'])) ?> <span class="small muted" style="font-weight:400">+ IVA / anno</span></strong>
              <?php endif; ?>
            </label>
            <?php if (Plans::perProperty($o)):
                  $q = Plans::quantity($o, $strutture ?: null) ?? (int) $o['min_quantity']; ?>
              <div class="quantita" data-quantita data-base="<?= (int) $o['price_cents'] ?>" data-extra="<?= (int) $o['extra_price_cents'] ?>" data-valuta="<?= Support::e($o['currency']) ?>">
                <label for="strutture-<?= $id ?>" class="plan__label">Quante strutture vuoi gestire?</label>
                <input id="strutture-<?= $id ?>" name="strutture" type="number" inputmode="numeric" step="1"
                       min="<?= (int) $o['min_quantity'] ?>" max="<?= (int) $o['max_quantity'] ?>" value="<?= $q ?>">
                <p class="small muted">Prima struttura <?= Support::e(Support::money((int) $o['price_cents'], $o['currency'])) ?>/anno.
                  Ogni struttura aggiuntiva +<?= Support::e(Support::money((int) $o['extra_price_cents'], $o['currency'])) ?>/anno.</p>
                <strong><span data-totale><?= Support::e(Support::money(Plans::price($o, $q), $o['currency'])) ?></span>
                  <span class="small muted" style="font-weight:400">+ IVA / anno</span></strong>
              </div>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      </fieldset>
    <?php endforeach; ?>
  </div>
  <div class="actions" style="margin-top:24px">
    <button class="btn btn--go">Continua <span class="go"><?= Icon::svg('arrow', 18, 2) ?></span></button>
    <span class="small muted">Rinnovo annuale automatico, disattivabile quando vuoi. Prezzi IVA esclusa.</span>
  </div>
</form>
<?php endif; ?>
<script src="<?= a() ?>/assets/prezzi.js" defer></script>
