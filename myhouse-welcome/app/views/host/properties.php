<?php
/* Le mie guide: il saluto, tre numeri veri (online, da finire, il piano), poi
   una scheda per struttura con lo stato vero — bozza, online, offline perché
   l'abbonamento è scaduto — e le scorciatoie per le cose che si fanno spesso. */
use function MHW\b; use MHW\{Support, Media, Icon, Auth};
$title = 'Le mie guide';
$attive = array_values(array_filter($props, fn($x) => empty($x['archived_at'])));
$n = count($attive);
$bloccate = $bloccate ?? [];
$puoiAggiungere = $n < $maxProp || !empty($aggiungiPagando);
$online = count(array_filter($attive, fn($x) => $x['status'] === 'published' && $x['online'] && !in_array((int) $x['id'], $bloccate, true)));
$bozze = count(array_filter($attive, fn($x) => $x['status'] !== 'published' && !in_array((int) $x['id'], $bloccate, true)));
$nome = trim((string) (Auth::user()['name'] ?? ''));
$nome = $nome !== '' ? explode(' ', $nome)[0] : ''; ?>
<div class="saluto">
  <div>
    <h1><?= $nome !== '' ? 'Ciao, ' . Support::e($nome) . '.' : 'Le mie guide.' ?></h1>
    <p>
      <?php if ($sub && $piano): ?>
        Piano <strong><?= Support::e($piano['name']) ?></strong> attivo<?= $sub['current_period_end'] ? ' fino al ' . Support::e(Support::date($sub['current_period_end'])) : '' ?>.
      <?php elseif ($piano): ?>
        Stai configurando con il piano <strong><?= Support::e($piano['name']) ?></strong>. Paghi solo quando pubblichi.
      <?php else: ?>
        Le tue guide, in un posto solo.
      <?php endif; ?>
      <?= $maxProp > 1 && $maxProp < PHP_INT_MAX ? ' Strutture: ' . $n . ' su ' . $maxProp . '.' : '' ?>
    </p>
  </div>
  <?php if ($puoiAggiungere): ?>
    <a class="btn btn--go" href="<?= b() ?>/pannello/nuova">Aggiungi una struttura <span class="go"><?= Icon::svg('plus', 18, 2) ?></span></a>
  <?php endif; ?>
</div>

<div class="cifre" aria-label="In breve">
  <div class="cifra cifra--pino">
    <span class="cifra__testa"><span class="cifra__ico"><?= Icon::svg('globe', 17) ?></span>Guide online</span>
    <b class="cifra__valore"><?= $online ?></b>
    <span class="cifra__nota"><?= $online ? 'Gli ospiti le aprono dal link o dal QR.' : 'Ancora nessuna: pubblica la prima.' ?></span>
  </div>
  <div class="cifra cifra--ocra">
    <span class="cifra__testa"><span class="cifra__ico"><?= Icon::svg('doc', 17) ?></span>Da finire</span>
    <b class="cifra__valore"><?= $bozze ?></b>
    <span class="cifra__nota"><?= $bozze ? 'Bozze che gli ospiti non vedono ancora.' : 'Nessuna bozza in sospeso.' ?></span>
  </div>
  <a class="cifra cifra--mare" href="<?= b() ?>/account">
    <span class="cifra__testa"><span class="cifra__ico"><?= Icon::svg('card', 17) ?></span>Il tuo piano</span>
    <?= Icon::svg('arrow', 18, 2, 'cifra__freccia') ?>
    <b class="cifra__valore cifra__valore--testo"><?= Support::e($piano['name'] ?? 'Da scegliere') ?></b>
    <span class="cifra__nota"><?= $sub ? ($sub['current_period_end'] ? 'Si rinnova il ' . Support::e(Support::date($sub['current_period_end'])) . '.' : 'Attivo.') : 'Paghi solo quando pubblichi.' ?></span>
  </a>
</div>

<div class="guide-griglia">
  <?php foreach ($props as $pr): $cop = Media::url($pr['cover_media_id'] ? (int) $pr['cover_media_id'] : null);
        if (in_array((int) $pr['id'], $bloccate, true)): /* bloccata: si vede, non si apre */ ?>
    <div class="panel stack bloccata" style="padding:20px;gap:8px">
      <span class="bloccata__ico" aria-hidden="true"><?= Icon::svg('lock', 22) ?></span>
      <b class="scheda-guida__nome"><?= Support::e($pr['name']) ?></b>
      <span class="badge badge--ochre" style="align-self:flex-start"><span class="dot"></span>Si attiva dopo il pagamento</span>
      <span class="small muted"><?= $sub ? 'Aspetta la conferma di Stripe per la struttura in più.' : 'Prima del pagamento si configura una struttura alla volta: completa e pubblica la prima.' ?></span>
      <details class="small">
        <summary style="cursor:pointer;min-height:32px">Elimina</summary>
        <form method="post" action="<?= b() ?>/pannello/<?= (int) $pr['id'] ?>/elimina" class="stack" style="gap:8px;margin-top:6px"><?= MHW\Csrf::field() ?>
          <div class="field" style="margin:0"><label for="conferma-<?= (int) $pr['id'] ?>">Per confermare scrivi <b><?= Support::e($pr['name']) ?></b></label>
            <input type="text" id="conferma-<?= (int) $pr['id'] ?>" name="conferma" required autocomplete="off"></div>
          <button class="btn btn--danger btn--sm" style="align-self:flex-start">Elimina</button>
        </form>
      </details>
    </div>
  <?php continue; endif;
        $pid = (int) $pr['id'];
        $stato = !empty($pr['archived_at']) ? ['paper', 'Archiviata'] : ($pr['status'] !== 'published' ? ['ochre', 'Bozza'] : ($pr['online'] ? ['pine', 'Online'] : ['alert', 'Offline'])); ?>
    <article class="scheda-guida">
      <a class="scheda-guida__foto" href="<?= b() ?>/pannello/<?= $pid ?>" tabindex="-1" aria-hidden="true">
        <?php if ($cop): ?><img src="<?= Support::e($cop) ?>" alt="" loading="lazy"><?php else: ?><span class="scheda-guida__vuota"><?= Icon::brand(40) ?></span><?php endif; ?>
        <span class="badge badge--<?= $stato[0] ?>"><span class="dot"></span><?= $stato[1] ?></span>
      </a>
      <a class="scheda-guida__corpo" href="<?= b() ?>/pannello/<?= $pid ?>">
        <b class="scheda-guida__nome"><?= Support::e($pr['name']) ?></b>
        <span class="small muted"><?= Support::e($pr['city'] ?: 'Città da indicare') ?></span>
      </a>
      <?php if (!empty($pr['archived_at'])): ?>
        <form method="post" action="<?= b() ?>/pannello/<?= $pid ?>/riattiva" class="scheda-guida__azioni"><?= MHW\Csrf::field() ?>
          <button class="btn btn--ghost btn--sm">Riattiva <?= Support::e($pr['name']) ?></button></form>
      <?php else: ?>
        <div class="scheda-guida__azioni">
          <a href="<?= b() ?>/pannello/<?= $pid ?>"><?= Icon::svg('doc', 15) ?>Contenuti</a>
          <a href="<?= b() ?>/pannello/<?= $pid ?>/qr"><?= Icon::svg('qr', 15) ?>QR</a>
          <a href="<?= b() ?>/pannello/<?= $pid ?>/anteprima" target="_blank" rel="noopener"><?= Icon::svg('eye', 15) ?>Anteprima</a>
        </div>
      <?php endif; ?>
    </article>
  <?php endforeach; ?>
  <?php if ($puoiAggiungere): ?>
    <a class="scheda-nuova" href="<?= b() ?>/pannello/nuova"><span class="scheda-nuova__piu"><?= Icon::svg('plus', 24, 2) ?></span>Aggiungi una struttura
      <span class="small">Una guida e un QR in più, con i suoi contenuti.</span></a>
  <?php endif; ?>
</div>

<?php if (!$puoiAggiungere && $maxProp < PHP_INT_MAX): ?>
  <div class="limit" style="margin-top:28px">
    <?php if ($maxProp === 1): ?>
      <p style="max-width:620px">Il tuo piano comprende una struttura. Con Portfolio gestisci più strutture dallo stesso account,
        ognuna con la sua guida e il suo QR.</p>
      <a class="btn btn--ghost btn--sm" href="<?= b() ?>/piano">Scopri Portfolio</a>
    <?php else: ?>
      <?php if ($sub): ?>
        <p style="max-width:620px">Il tuo piano comprende <?= (int) $maxProp ?> strutture. Puoi aggiungerne altre da Account &amp; Fatturazione.</p>
        <a class="btn btn--ghost btn--sm" href="<?= b() ?>/account">Account &amp; Fatturazione</a>
      <?php else: ?>
        <p style="max-width:620px">Hai scelto un Portfolio per <?= (int) $maxProp ?> strutture. Il cifra lo cambi prima di pagare, dalla scelta del piano.</p>
        <a class="btn btn--ghost btn--sm" href="<?= b() ?>/piano">Cambia il cifra di strutture</a>
      <?php endif; ?>
    <?php endif; ?>
  </div>
<?php endif; ?>
