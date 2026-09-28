<?php
/* Le mie guide: una scheda per struttura, con lo stato vero — bozza,
   online, offline perché l'abbonamento è scaduto. */
use function MHW\b; use MHW\{Support, Media, Icon};
$title = 'Le mie guide';
$n = count($props);
$puoiAggiungere = $n < $maxProp; ?>
<div class="spread">
  <div class="stack stack--sm">
    <h1>Le mie guide.</h1>
    <p class="muted small">
      <?php if ($sub && $piano): ?>
        Piano <strong><?= Support::e($piano['name']) ?></strong> attivo<?= $sub['current_period_end'] ? ' fino al ' . Support::e(Support::date($sub['current_period_end'])) : '' ?>.
      <?php elseif ($piano): ?>
        Stai configurando con il piano <strong><?= Support::e($piano['name']) ?></strong>. Paghi solo quando pubblichi.
      <?php endif; ?>
      <?= $maxProp > 1 && $maxProp < PHP_INT_MAX ? ' Strutture: ' . $n . ' su ' . $maxProp . '.' : '' ?>
    </p>
  </div>
  <?php if ($puoiAggiungere): ?>
    <a class="btn btn--go" href="<?= b() ?>/pannello/nuova">Aggiungi una struttura <span class="go"><?= Icon::svg('arrow', 18, 2) ?></span></a>
  <?php endif; ?>
</div>

<div class="grid grid-3" style="margin-top:28px">
  <?php foreach ($props as $pr): $cop = Media::url($pr['cover_media_id'] ? (int) $pr['cover_media_id'] : null);
        $stato = $pr['status'] !== 'published' ? ['ochre', 'Bozza'] : ($pr['online'] ? ['pine', 'Online'] : ['alert', 'Offline']); ?>
    <a class="panel stack" style="padding:0;overflow:hidden;gap:0;color:var(--ink)" href="<?= b() ?>/pannello/<?= (int) $pr['id'] ?>">
      <span style="display:block;height:150px;overflow:hidden;background:var(--sunk)">
        <?php if ($cop): ?><img src="<?= Support::e($cop) ?>" alt="" style="width:100%;height:100%;object-fit:cover"><?php endif; ?>
      </span>
      <span class="stack stack--sm" style="padding:20px">
        <b style="font-size:19px;font-weight:500;letter-spacing:-.5px"><?= Support::e($pr['name']) ?></b>
        <span class="small muted"><?= Support::e($pr['city'] ?: 'Città da indicare') ?></span>
        <span class="badge badge--<?= $stato[0] ?>" style="align-self:flex-start"><span class="dot"></span><?= $stato[1] ?></span>
      </span>
    </a>
  <?php endforeach; ?>
</div>

<?php if (!$puoiAggiungere && $maxProp < 3): ?>
  <div class="limit" style="margin-top:28px">
    <p style="max-width:620px">Il tuo piano comprende <?= (int) $maxProp ?> struttur<?= $maxProp === 1 ? 'a' : 'e' ?>.
      Con Portfolio gestisci 2 o 3 strutture dallo stesso account, ognuna con la sua guida e il suo QR.</p>
    <a class="btn btn--ghost btn--sm" href="<?= b() ?>/piano">Scopri Portfolio</a>
  </div>
<?php endif; ?>
