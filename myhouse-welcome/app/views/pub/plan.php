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
  <?php /* Ogni opzione è una card intera che si preme: il pallino in alto a destra è
           il radio vero (tastiera e lettori di schermo), il velo trasparente sopra la
           card è la sua etichetta. Il numero di strutture del Portfolio sta dentro la
           card, sopra il velo, così si scrive senza perdere la scelta. */ ?>
  <div class="grid grid-3 piani-scelta" style="align-items:stretch">
    <?php foreach ($offers as $of): foreach ($of['options'] as $o): $id = (int) $o['pv_id'];
          $sel = $preselezione ? $preselezione === $id : $scelto === $id;
          $perStruttura = Plans::perProperty($o);
          $q = $perStruttura ? (Plans::quantity($o, $strutture ?: null) ?? (int) $o['min_quantity']) : 1; ?>
      <div class="plan pianocard<?= $o['badge'] !== '' ? ' pianocard--evidenza' : '' ?>">
        <input class="pianocard__radio" type="radio" name="pv" value="<?= $id ?>" id="pv-<?= $id ?>" <?= $sel ? 'checked' : '' ?> required>
        <label class="pianocard__velo" for="pv-<?= $id ?>"><span class="sr-only"><?= Support::e($o['name']) ?></span></label>
        <div class="spread spread--mid" style="gap:12px;padding-right:34px">
          <span class="plan__nome"><?= Support::e($o['name']) ?></span>
          <?php if ($o['badge'] !== ''): ?><span class="badge badge--ochre-strong"><?= Support::e($o['badge']) ?></span><?php endif; ?>
        </div>
        <?php if ($perStruttura): ?>
          <div class="quantita pianocard__sopra" data-quantita data-base="<?= (int) $o['price_cents'] ?>" data-extra="<?= (int) $o['extra_price_cents'] ?>" data-valuta="<?= Support::e($o['currency']) ?>">
            <span class="price"><span data-totale><?= Support::e(Support::money(Plans::price($o, $q), $o['currency'])) ?></span><small> + IVA / anno</small></span>
            <label for="strutture-<?= $id ?>" class="plan__label" style="margin:6px 0 0">Quante strutture vuoi gestire?</label>
            <input id="strutture-<?= $id ?>" name="strutture" type="number" inputmode="numeric" step="1" data-sceglie="pv-<?= $id ?>"
                   min="<?= (int) $o['min_quantity'] ?>" max="<?= (int) $o['max_quantity'] ?>" value="<?= $q ?>">
            <p class="small muted">Prima struttura <?= Support::e(Support::money((int) $o['price_cents'], $o['currency'])) ?>/anno.
              Ogni struttura aggiuntiva +<?= Support::e(Support::money((int) $o['extra_price_cents'], $o['currency'])) ?>/anno.</p>
          </div>
        <?php else: ?>
          <span class="price"><?= Support::e(Support::money((int) $o['price_cents'], $o['currency'])) ?><small> + IVA / anno</small></span>
        <?php endif; ?>
        <p class="muted" style="font-size:15px;line-height:22px"><?= Support::e($o['headline']) ?> <?= Support::e($o['description']) ?></p>
        <ul class="plan__lista"><?php foreach ($o['bullet_list'] as $bl): ?><li><?= Icon::svg('check', 16, 2) ?><span><?= Support::e($bl) ?></span></li><?php endforeach; ?></ul>
      </div>
    <?php endforeach; endforeach; ?>
  </div>
  <div class="actions" style="margin-top:24px">
    <button class="btn btn--go">Continua <span class="go"><?= Icon::svg('arrow', 18, 2) ?></span></button>
    <span class="small muted">Rinnovo annuale automatico, disattivabile quando vuoi. Prezzi IVA esclusa.</span>
  </div>
</form>
<?php endif; ?>
<script src="<?= a() ?>/assets/prezzi.js" defer></script>
<script>
/* Scrivere il numero di strutture sceglie anche il Portfolio. */
document.addEventListener('focusin', function (e) {
  var id = e.target.getAttribute && e.target.getAttribute('data-sceglie');
  if (id) document.getElementById(id).checked = true;
});
</script>
