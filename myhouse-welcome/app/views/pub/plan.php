<?php
/* La scelta del piano, dopo la registrazione. Non si paga niente qui: il piano
   scelto decide cosa si può configurare, e si paga solo alla pubblicazione. */
use function MHW\{a, b}; use MHW\{Support, Csrf, Icon, Plans}; $title = 'Scegli il piano'; ?>
<div class="stack stack--sm" style="max-width:720px">
  <h1>Scegli il piano.</h1>
  <p class="lead">Adesso non paghi niente: configuri la guida con le funzioni del piano scelto, e paghi solo quando
    decidi di pubblicarla. Puoi cambiare idea fino a quel momento.</p>
</div>

<?php if ($attivo): ?>
  <p class="note" style="margin-top:24px"><?= Icon::svg('info', 19) ?><span>Hai già un abbonamento attivo.
    Per cambiarlo vai in <a href="<?= b() ?>/account/piano">Cambia piano</a>.</span></p>
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
          $q = $perStruttura ? (Plans::quantity($o, $strutture ?: null) ?? (int) $o['min_quantity']) : 1;
          // 9N · oltre il massimo la scheda non torna a 2 strutture: resta il numero scelto, con il preventivo al posto del prezzo
          $oltreQ = $perStruttura && $strutture > (int) $o['max_quantity']; ?>
      <div class="plan pianocard<?= $o['badge'] !== '' ? ' pianocard--evidenza' : '' ?>">
        <input class="pianocard__radio" type="radio" name="pv" value="<?= $id ?>" id="pv-<?= $id ?>" <?= $sel ? 'checked' : '' ?> required>
        <label class="pianocard__velo" for="pv-<?= $id ?>"><span class="sr-only"><?= Support::e($o['name']) ?></span></label>
        <div class="plan__testa">
          <div class="spread spread--mid" style="gap:12px;padding-right:34px">
            <span class="plan__nome"><?= Support::e($o['name']) ?></span>
            <?php if ($o['badge'] !== ''): ?><span class="badge badge--ochre-strong"><?= Support::e($o['badge']) ?></span><?php endif; ?>
          </div>
          <?php if (trim((string) $o['tagline']) !== ''): ?>
            <p class="plan__per"><b>Ideale per</b> <?= Support::e(preg_replace('/^Per /u', '', trim((string) $o['tagline']))) ?></p>
          <?php endif; ?>
          <p class="muted" style="font-size:15px;line-height:22px;margin:0"><?= Support::e($o['headline']) ?> <?= Support::e($o['description']) ?></p>
        </div>
        <?php $voci = $o['bullet_list']; include dirname(__DIR__) . '/pub/_voci_piano.php'; ?>
        <?php /* Come nella home: quantità e prezzo in fondo, alla stessa altezza nelle tre schede. */ ?>
        <?php /* 9A · Gli sconti che valgono per l'account (invito, giudizio, codice): prezzo barrato e primo anno. */
              $riepO = MHW\Sconti::disponibili() ? MHW\Sconti::riepilogo($o, $q, $acc) : null; $scO = $riepO && $riepO['sconto'] > 0 ? $riepO : null;
              $attrSconto = $scO ? ($scO['percento'] > 0 ? ' data-sconto="' . (int) $scO['percento'] . '"' : ' data-sconto-fisso="' . (int) $scO['sconto'] . '"') : ''; ?>
        <?php if ($perStruttura): ?>
          <div class="quantita pianocard__sopra plan__piede" data-quantita data-base="<?= (int) $o['price_cents'] ?>" data-extra="<?= (int) $o['extra_price_cents'] ?>" data-scaglioni="<?= Support::e(Plans::tiersJson($o)) ?>" data-valuta="<?= Support::e($o['currency']) ?>"<?= $attrSconto ?>>
            <div class="plan__quante">
              <?php $pvS = $o; $qS = $q; include __DIR__ . '/_scaglioni.php'; ?>
              <label for="strutture-<?= $id ?>" class="plan__label">Quante strutture vuoi gestire?</label>
              <input id="strutture-<?= $id ?>" name="strutture" type="number" inputmode="numeric" step="1" data-sceglie="pv-<?= $id ?>"
                     min="<?= (int) $o['min_quantity'] ?>" max="999" data-max-prezzo="<?= (int) $o['max_quantity'] ?>" value="<?= $oltreQ ? (int) $strutture : $q ?>">
              <div class="plan__oltre" data-oltre<?= $oltreQ ? '' : ' hidden' ?>><p>Da <?= (int) $o['max_quantity'] + 1 ?> strutture in su: preventivo. Ti facciamo una proposta su misura.</p>
                <a class="btn btn--sm" href="#preventivo">Chiedi il preventivo</a></div>
            </div>
            <div class="plan__prezzo">
              <?php if ($scO): ?><span class="price price--barrato"><s data-pieno><?= Support::e(Support::money($scO['prezzo'], $o['currency'])) ?></s></span><?php endif; ?>
              <span class="price"><span data-totale><?= Support::e(Support::money($scO ? $scO['totale'] : Plans::price($o, $q), $o['currency'])) ?></span><small> + IVA<?= $scO ? ' il primo anno' : ' / anno' ?></small></span>
              <?php if ($scO): ?><span class="plan__sconto-riga">−<?= $scO['percento'] > 0 ? (int) $scO['percento'] . '%' : Support::e(Support::money($scO['sconto'], $o['currency'])) ?> primo anno · poi <span data-pieno-poi><?= Support::e(Support::money($scO['prezzo'], $o['currency'])) ?></span></span><?php endif; ?>
              <span class="plan__mese">circa <span data-mensile><?= Support::e(Support::money(Plans::monthly(Plans::price($o, $q)), $o['currency'])) ?></span> al mese</span>
            </div>
          </div>
        <?php else: ?>
          <div class="plan__piede">
            <div class="plan__quante"></div>
            <div class="plan__prezzo">
              <?php if ($scO): ?><span class="price price--barrato"><s><?= Support::e(Support::money($scO['prezzo'], $o['currency'])) ?></s></span><?php endif; ?>
              <span class="price"><?= Support::e(Support::money($scO ? $scO['totale'] : (int) $o['price_cents'], $o['currency'])) ?><small> + IVA<?= $scO ? ' il primo anno' : ' / anno' ?></small></span>
              <?php if ($scO): ?><span class="plan__sconto-riga">−<?= $scO['percento'] > 0 ? (int) $scO['percento'] . '%' : Support::e(Support::money($scO['sconto'], $o['currency'])) ?> primo anno · poi <?= Support::e(Support::money($scO['prezzo'], $o['currency'])) ?></span><?php endif; ?>
              <span class="plan__mese">circa <?= Support::e(Support::money(Plans::monthly((int) $o['price_cents']), $o['currency'])) ?> al mese</span>
            </div>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; endforeach; ?>
  </div>
  <div class="actions" style="margin-top:24px">
    <button class="btn btn--go">Continua <span class="go"><?= Icon::svg('arrow', 18, 2) ?></span></button>
    <span class="small muted">Rinnovo annuale automatico, disattivabile quando vuoi. Prezzi IVA esclusa.</span>
  </div>
</form>
<?php /* 9M · «Disponibile dopo l'attivazione»: la traduzione automatica e la scheda campione si accendono dopo il pagamento. */ ?>
<div style="max-width:720px;margin-top:16px"><?php include dirname(__DIR__) . '/host/_attivazione.php'; ?></div>
<?php /* 9A · Il riquadro del preventivo, oltre il massimo del Portfolio: fuori dal modulo del piano. */
      foreach ($offers as $of) foreach ($of['options'] as $oP) if (Plans::perProperty($oP)) { $pvP = $oP; $qP = $strutture; $tornaP = '/piano' . ($strutture ? '?piano=' . (int) $oP['pv_id'] . '&strutture=' . (int) $strutture : ''); include __DIR__ . '/_preventivo.php'; break 2; } ?>
<?php /* Il codice sconto del primo anno (6E): fuori dal modulo del piano. */
      if (isset($acc)) { $pv = $pvScelto ?? null; $quantita = (int) ($acc['intended_quantity'] ?? 1) ?: 1; $torna = '/piano'; ?>
  <div class="panel stack" style="margin-top:20px;gap:10px;max-width:720px"><?php include dirname(__DIR__) . '/host/_sconto.php'; ?></div>
<?php } ?>
<?php endif; ?>
<script src="<?= MHW\av('/assets/prezzi.js') ?>" defer></script>
<script>
/* Scrivere il numero di strutture sceglie anche il Portfolio. */
document.addEventListener('focusin', function (e) {
  var id = e.target.getAttribute && e.target.getAttribute('data-sceglie');
  if (id) document.getElementById(id).checked = true;
});
</script>
