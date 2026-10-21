<?php use function MHW\t, MHW\tf;
/* Le mie guide: il saluto, tre numeri veri (online, da finire, il piano), poi
   una scheda per struttura con lo stato vero — bozza, online, offline perché
   l'abbonamento è scaduto — e le scorciatoie per le cose che si fanno spesso. */
use function MHW\b; use MHW\{Support, Media, Icon, Auth};
$title = t('Le mie guide');
$attive = array_values(array_filter($props, fn($x) => empty($x['archived_at'])));
$n = count(array_filter($attive, fn($x) => (int) $x['is_demo'] < 2));   // la vetrina non conta nel limite
$bloccate = $bloccate ?? [];
$puoiAggiungere = $n < $maxProp || !empty($aggiungiPagando);
$online = count(array_filter($attive, fn($x) => $x['status'] === 'published' && $x['online'] && !in_array((int) $x['id'], $bloccate, true)));
$bozze = count(array_filter($attive, fn($x) => $x['status'] !== 'published' && !in_array((int) $x['id'], $bloccate, true)));
$nome = trim((string) (Auth::user()['name'] ?? ''));
$nome = $nome !== '' ? explode(' ', $nome)[0] : '';
// 9M · La scheda campione (solo Portfolio pagato): badge, contatori delle gemelle e il banner «Aggiorna le gemelle».
$accC = Auth::account(); $aidC = $accC ? (int) $accC['id'] : 0;
$campDisp = $aidC && MHW\Campione::disponibile($aidC); $campArrivo = !$campDisp && $aidC && MHW\Campione::inArrivo($aidC);
$campD = $campDisp ? MHW\Campione::differenze($aidC) : null;
$campId = (int) ($campD['campione']['id'] ?? 0);
$gemIds = $campD ? array_map(fn($g) => (int) $g['id'], $campD['gemelle']) : []; ?>
<div class="saluto">
  <div>
    <h1><?= $nome !== '' ? Support::e(tf('Ciao, {nome}.', ['{nome}' => $nome])) : t('Le mie guide.') ?></h1>
    <p>
      <?php if ($sub && $piano): ?>
        <?= $sub['current_period_end'] ? tf('Piano {piano} attivo fino al {data}.', ['{piano}' => '<strong>' . Support::e($piano['name']) . '</strong>', '{data}' => Support::e(MHW\Pannello::data($sub['current_period_end']))])
                                       : tf('Piano {piano} attivo.', ['{piano}' => '<strong>' . Support::e($piano['name']) . '</strong>']) ?>
      <?php elseif ($piano): ?>
        <?= tf('Stai configurando con il piano {piano}. Paghi solo quando pubblichi.', ['{piano}' => '<strong>' . Support::e($piano['name']) . '</strong>']) ?>
      <?php else: ?>
        <?= t('Le tue guide, in un posto solo.') ?>
      <?php endif; ?>
      <?= $maxProp > 1 && $maxProp < PHP_INT_MAX ? ' ' . tf('Strutture: {n} su {max}.', ['{n}' => $n, '{max}' => $maxProp]) : '' ?>
    </p>
    <?php if ($campId): ?><p class="small muted" style="margin:4px 0 0"><?= Support::e(tf('{nome} è la tua', ['{nome}' => $campD['campione']['name']])) ?> <a href="<?= b() ?>/campione"><?= t('scheda campione') ?></a><?= t(': quando la cambi, ti proponiamo di portare le modifiche anche nelle gemelle.') ?></p><?php endif; ?>
  </div>
  <?php if ($puoiAggiungere): ?>
    <a class="btn btn--go" href="<?= b() ?>/pannello/nuova"><?= t('Aggiungi una struttura') ?> <span class="go"><?= Icon::svg('plus', 18, 2) ?></span></a>
  <?php elseif ($maxProp === 1): /* 8B: senza più strutture nel piano il bottone resta e apre «Più strutture con Portfolio» */ ?>
    <a class="btn btn--go btn--lucchetto" href="<?= b() ?>/piano?passa=portfolio" data-sblocca="strutture"><?= t('Aggiungi una struttura') ?> <span class="go"><?= Icon::svg('lock', 18, 2) ?></span></a>
  <?php endif; ?>
</div>

<div class="cifre" aria-label="<?= MHW\Support::e(t('In breve')) ?>">
  <div class="cifra cifra--pino">
    <span class="cifra__testa"><span class="cifra__ico"><?= Icon::svg('globe', 17) ?></span><?= t('Guide online') ?></span>
    <b class="cifra__valore"><?= $online ?></b>
    <span class="cifra__nota"><?= $online ? t('Gli ospiti le aprono dal link o dal QR.') : t('Ancora nessuna: pubblica la prima.') ?></span>
  </div>
  <div class="cifra cifra--ocra">
    <span class="cifra__testa"><span class="cifra__ico"><?= Icon::svg('doc', 17) ?></span><?= t('Da finire') ?></span>
    <b class="cifra__valore"><?= $bozze ?></b>
    <span class="cifra__nota"><?= $bozze ? t('Bozze che gli ospiti non vedono ancora.') : t('Nessuna bozza in sospeso.') ?></span>
  </div>
  <a class="cifra cifra--mare" href="<?= b() ?>/account">
    <span class="cifra__testa"><span class="cifra__ico"><?= Icon::svg('card', 17) ?></span><?= t('Il tuo piano') ?></span>
    <?= Icon::svg('arrow', 18, 2, 'cifra__freccia') ?>
    <b class="cifra__valore cifra__valore--testo"><?= Support::e($piano['name'] ?? t('Da scegliere')) ?></b>
    <span class="cifra__nota"><?= $sub ? ($sub['current_period_end'] ? Support::e(tf('Si rinnova il {data}.', ['{data}' => MHW\Pannello::data($sub['current_period_end'])])) : t('Attivo.')) : t('Paghi solo quando pubblichi.') ?></span>
  </a>
</div>

<?php include __DIR__ . '/_giudizio_invito.php'; /* 7D: «Ti sta piacendo MyHouse Welcome?» */ ?>

<div class="guide-griglia">
  <?php foreach ($props as $pr): $cop = Media::url($pr['cover_media_id'] ? (int) $pr['cover_media_id'] : null);
        if (in_array((int) $pr['id'], $bloccate, true)): /* bloccata: si vede, non si apre */ ?>
    <div class="panel stack bloccata" style="padding:20px;gap:8px">
      <span class="bloccata__ico" aria-hidden="true"><?= Icon::svg('lock', 22) ?></span>
      <b class="scheda-guida__nome"><?= Support::e($pr['name']) ?></b>
      <span class="badge badge--ochre" style="align-self:flex-start"><span class="dot"></span><?= t('Si attiva dopo il pagamento') ?></span>
      <span class="small muted"><?= $sub ? t('Aspetta la conferma di Stripe per la struttura in più.') : t('Prima del pagamento si configura una struttura alla volta: completa e pubblica la prima.') ?></span>
      <details class="small">
        <summary style="cursor:pointer;min-height:32px"><?= t('Elimina') ?></summary>
        <form method="post" action="<?= b() ?>/pannello/<?= (int) $pr['id'] ?>/elimina" class="stack" style="gap:8px;margin-top:6px"><?= MHW\Csrf::field() ?>
          <div class="field" style="margin:0"><label for="conferma-<?= (int) $pr['id'] ?>"><?= t('Per confermare scrivi') ?> <b><?= Support::e($pr['name']) ?></b></label>
            <input type="text" id="conferma-<?= (int) $pr['id'] ?>" name="conferma" required autocomplete="off"></div>
          <button class="btn btn--danger btn--sm" style="align-self:flex-start"><?= t('Elimina') ?></button>
        </form>
      </details>
    </div>
  <?php continue; endif;
        $pid = (int) $pr['id'];
        $stato = !empty($pr['archived_at']) ? ['paper', t('Archiviata')] : ($pr['status'] !== 'published' ? ['ochre', t('Bozza')] : ($pr['online'] ? ['pine', t('Online')] : ['alert', t('Offline')])); ?>
    <article class="scheda-guida">
      <a class="scheda-guida__foto" href="<?= b() ?>/pannello/<?= $pid ?>" tabindex="-1" aria-hidden="true">
        <?php if ($cop): ?><img src="<?= Support::e($cop) ?>" alt="" loading="lazy"><?php else: ?><span class="scheda-guida__vuota"><?= Icon::brand(40) ?></span><?php endif; ?>
        <span class="badge badge--<?= $stato[0] ?>"><span class="dot"></span><?= $stato[1] ?></span>
      </a>
      <a class="scheda-guida__corpo" href="<?= b() ?>/pannello/<?= $pid ?>">
        <b class="scheda-guida__nome"><?= Support::e($pr['name']) ?><?php if ($pid === $campId): ?> <span class="badge-campione"><?= t('Campione') ?></span><?php elseif (in_array($pid, $gemIds, true)): ?> <span class="badge-campione badge-campione--gemella"><?= t('Gemella') ?></span><?php endif; ?></b>
        <span class="small muted"><?= Support::e($pr['city'] ?: t('Città da indicare')) ?></span>
        <?php if ($pid === $campId): ?><span class="small"><?= count($gemIds) === 1 ? t('1 guida gemella') : Support::e(tf('{n} guide gemelle', ['{n}' => count($gemIds)])) ?></span>
        <?php elseif (in_array($pid, $gemIds, true)): $nG = (int) ($campD['contatori'][$pid] ?? 0); ?><span class="small <?= $nG ? 'gemella-da-aggiornare' : 'gemella-allineata' ?>"><?= $nG ? ($nG === 1 ? t('1 sezione da aggiornare') : Support::e(tf('{n} sezioni da aggiornare', ['{n}' => $nG]))) : t('Allineata') ?></span><?php endif; ?>
      </a>
      <?php /* 9N · La checklist delle bozze: cosa manca per pubblicare (e cosa conviene), con il link a dove si completa. */
            if (empty($pr['archived_at']) && $pr['status'] !== 'published' && (int) $pr['is_demo'] === 0):
              $passi = array_map(fn($v) => [$v['etichetta'], $v['fatto'], MHW\Bozze::dove($pr, $v)], MHW\Bozze::checklist($pr, null, true));   // 9Q · lo stesso calcolo dell'email del giorno 7
              $fatti = count(array_filter($passi, fn($x) => $x[1])); ?>
        <details class="checklist-bozza"<?= $fatti < count($passi) ? ' open' : '' ?>>
          <summary class="small"><?= Support::e(tf('Per pubblicare: {fatti} su {tot}', ['{fatti}' => $fatti, '{tot}' => count($passi)])) ?></summary>
          <ul class="checklist-bozza__elenco">
            <?php foreach ($passi as [$etP, $okP, $doveP]): ?>
              <li class="<?= $okP ? 'fatto' : '' ?>"><?= Icon::svg($okP ? 'check' : 'plus', 14, 2.2) ?>
                <?php if ($okP): ?><span><?= Support::e($etP) ?></span><?php else: ?><a href="<?= b() ?><?= Support::e($doveP) ?>"><?= Support::e($etP) ?></a><?php endif; ?></li>
            <?php endforeach; ?>
          </ul>
        </details>
      <?php endif; ?>
      <?php if (!empty($pr['archived_at'])): ?>
        <form method="post" action="<?= b() ?>/pannello/<?= $pid ?>/riattiva" class="scheda-guida__azioni"><?= MHW\Csrf::field() ?>
          <button class="btn btn--ghost btn--sm"><?= Support::e(tf('Riattiva {nome}', ['{nome}' => $pr['name']])) ?></button></form>
      <?php else: ?>
        <div class="scheda-guida__azioni">
          <a href="<?= b() ?>/pannello/<?= $pid ?>"><?= Icon::svg('doc', 15) ?><?= t('Contenuti') ?></a>
          <a href="<?= b() ?>/pannello/<?= $pid ?>/qr"><?= Icon::svg('qr', 15) ?>QR</a>
          <a href="<?= b() ?>/pannello/<?= $pid ?>/anteprima" target="_blank" rel="noopener"><?= Icon::svg('eye', 15) ?><?= t('Anteprima') ?></a>
          <?php if ($campDisp && $pid !== $campId && (int) $pr['is_demo'] === 0): ?>
            <?php /* 9N · con la conferma: la campione è il modello delle altre guide (una bozza vuota non serve a niente) */ ?>
            <details class="rendi-campione"><summary class="linkbtn"><?= Icon::svg('star', 15) ?><?= t('Rendi campione') ?></summary>
              <form method="post" action="<?= b() ?>/pannello/<?= $pid ?>/campione" class="rendi-campione__conferma"><?= MHW\Csrf::field() ?>
                <p class="small" style="margin:0"><?= Support::e(tf('«{nome}» diventa il modello delle altre guide: da qui copi le sezioni, e quando la cambi ti proponiamo di aggiornare le gemelle.', ['{nome}' => $pr['name']])) ?>
                  <?php if ($pr['status'] !== 'published'): ?><?= t('È ancora una bozza: conviene sceglierne una già completa.') ?><?php endif; ?></p>
                <span class="row" style="gap:8px;align-items:center"><button class="btn btn--sm"><?= t('Sì, rendila campione') ?></button> <a class="aiuto-q" href="<?= b() ?>/campione" target="_blank" rel="noopener" aria-label="<?= MHW\Support::e(MHW\t('Cos\'è la scheda campione')) ?>" title="<?= MHW\Support::e(MHW\t('Cos\'è la scheda campione')) ?>">?</a></span></form></details>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </article>
  <?php endforeach; ?>
  <?php if ($puoiAggiungere): ?>
    <a class="scheda-nuova" href="<?= b() ?>/pannello/nuova"><span class="scheda-nuova__piu"><?= Icon::svg('plus', 24, 2) ?></span><?= t('Aggiungi una struttura') ?>
      <span class="small"><?= t('Una guida e un QR in più, con i suoi contenuti.') ?></span></a>
  <?php endif; ?>
</div>

<?php if ($campD && $campD['righe']): /* 9M · il banner: niente si propaga da solo */
      $nomiC = array_slice($campD['cambiate'], 0, 3); $nGem = count(array_filter($campD['contatori'])); ?>
  <div class="campione-banner" role="status">
    <span class="campione-banner__ico" aria-hidden="true">!</span>
    <div><p style="margin:0"><?= tf(count($campD['cambiate']) > 3 ? 'Hai modificato {sezioni} e altre nella scheda campione.' : 'Hai modificato {sezioni} nella scheda campione.', ['{sezioni}' => implode(t(' e '), array_map(fn($x) => '<b>«' . Support::e($x) . '»</b>', $nomiC))]) ?></p>
      <p class="small muted" style="margin:2px 0 0"><?= Support::e($nGem === 1 ? t('1 guida gemella ha ancora la versione precedente. Le modifiche non si copiano da sole: decidi tu.') : tf('{n} guide gemelle hanno ancora la versione precedente. Le modifiche non si copiano da sole: decidi tu.', ['{n}' => $nGem])) ?></p></div>
    <a class="btn btn--go btn--sm" href="<?= b() ?>/campione/gemelle"><?= t('Aggiorna le gemelle →') ?></a>
  </div>
<?php elseif ($campArrivo): /* 9M · Portfolio scelto e non ancora pagato: la voce spenta, con lo spiegone */ ?>
  <div class="panel stack campione-spento" style="gap:10px;margin-top:20px">
    <div class="row" style="justify-content:space-between;gap:10px"><span><b><?= t('Rendi campione') ?></b><br><span class="small muted"><?= t('Scheda campione e traduzione automatica') ?></span></span>
      <a class="badge-campione badge-campione--spento" href="<?= b() ?>/campione"><?= t('Campione') ?></a></div>
    <?php include __DIR__ . '/_attivazione.php'; ?>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/_fino55.php'; /* 9P · «Fino al 55%»: i due bottoni dell'email, finché non ha 10 inviti e il giudizio */ ?>

<?php include __DIR__ . '/_invita.php'; /* Invita un amico: la barra e il link, per chi ha un abbonamento attivo */ ?>

<?php include __DIR__ . '/_sito_invito.php'; /* 9N · «Hai già un sito?» sotto le guide, non sopra */ ?>

<?php if (!$puoiAggiungere && $maxProp < PHP_INT_MAX): ?>
  <div class="limit" style="margin-top:28px">
    <?php if ($maxProp === 1): ?>
      <p style="max-width:620px"><?= t('Il tuo piano comprende una struttura. Con Portfolio gestisci più strutture dallo stesso account, ognuna con la sua guida e il suo QR.') ?></p>
      <div class="row">
        <a class="btn btn--sm" href="<?= b() ?>/piano?passa=portfolio"><?= t('Scopri Portfolio') ?></a>
        <?php $pianoAdesso = (string) ($piano['name'] ?? ''); $chiInfo = (string) ($user['email'] ?? ''); ?>
        <a class="btn btn--ghost btn--sm" href="<?= Support::e(Support::mailto(t('Informazioni su Portfolio'), tf("Ciao, vorrei sapere di più su Portfolio.\n\nEmail dell'account: {email}\nPiano attuale: {piano}\nQuante strutture vorrei gestire:", ['{email}' => $chiInfo, '{piano}' => $pianoAdesso ?: t('nessuno')]))) ?>"><?= t('Chiedi informazioni') ?></a>
      </div>
    <?php else: ?>
      <?php if ($sub): ?>
        <p style="max-width:620px"><?= Support::e(tf('Il tuo piano comprende {n} strutture. Puoi aggiungerne altre da Account & Fatturazione.', ['{n}' => (int) $maxProp])) ?></p>
        <a class="btn btn--ghost btn--sm" href="<?= b() ?>/account"><?= t('Account &amp; Fatturazione') ?></a>
      <?php else: ?>
        <p style="max-width:620px"><?= Support::e(tf('Hai scelto Portfolio per {n} strutture. Il numero lo cambi prima di pagare, dalla scelta del piano.', ['{n}' => (int) $maxProp])) ?></p>
        <a class="btn btn--ghost btn--sm" href="<?= b() ?>/piano"><?= t('Cambia il numero di strutture') ?></a>
      <?php endif; ?>
    <?php endif; ?>
  </div>
<?php endif; ?>
