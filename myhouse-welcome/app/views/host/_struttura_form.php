<?php
/* Nome, luogo, orari, contatti dell'host. Riceve: $prop, $dopoPasso. */
use function MHW\b;
use MHW\{Support, Csrf, Icon};
$dopoPasso = $dopoPasso ?? '';
$c = fn(string $k) => Support::e((string) $prop[$k]); ?>
<form method="post" action="<?= b() ?>/pannello/<?= (int) $prop['id'] ?>/impostazioni" class="stack"><?= Csrf::field() ?>
  <fieldset class="fieldset">
    <legend>La struttura</legend>
    <div class="field" style="margin:0"><label for="name">Nome</label><input id="name" name="name" required maxlength="120" value="<?= $c('name') ?>"></div>
    <div class="grid grid-2">
      <div class="field" style="margin:0"><label for="city">Città</label><input id="city" name="city" maxlength="120" value="<?= $c('city') ?>"></div>
      <div class="field" style="margin:0"><label for="region">Zona o regione</label><input id="region" name="region" maxlength="120" value="<?= $c('region') ?>"></div>
    </div>
  </fieldset>
  <fieldset class="fieldset">
    <legend>Orari</legend>
    <div class="grid grid-2">
      <div class="field" style="margin:0"><label for="checkin_from">Check-in dalle</label><input id="checkin_from" name="checkin_from" type="time" value="<?= $c('checkin_from') ?>"></div>
      <div class="field" style="margin:0"><label for="checkout_by">Check-out entro le</label><input id="checkout_by" name="checkout_by" type="time" value="<?= $c('checkout_by') ?>"></div>
    </div>
  </fieldset>
  <fieldset class="fieldset">
    <legend>Contatti dell'host</legend>
    <p class="help">Compaiono nella guida, così l'ospite ti chiama o ti scrive con un tocco.</p>
    <div class="field" style="margin:0"><label for="host_name">Il tuo nome</label><input id="host_name" name="host_name" maxlength="120" value="<?= $c('host_name') ?>"></div>
    <div class="grid grid-2">
      <div class="field" style="margin:0"><label for="host_phone">Telefono</label><input id="host_phone" name="host_phone" type="tel" maxlength="40" value="<?= $c('host_phone') ?>"></div>
      <div class="field" style="margin:0"><label for="host_whatsapp">WhatsApp</label><input id="host_whatsapp" name="host_whatsapp" type="tel" maxlength="40" value="<?= $c('host_whatsapp') ?>" placeholder="+39 …"></div>
    </div>
  </fieldset>
  <div class="actions">
    <?php if ($dopoPasso !== ''): ?>
      <button class="btn btn--go" name="dopo" value="<?= Support::e($dopoPasso) ?>">Salva e continua <span class="go"><?= Icon::svg('arrow', 18, 2) ?></span></button>
    <?php else: ?>
      <button class="btn">Salva</button>
    <?php endif; ?>
  </div>
</form>
