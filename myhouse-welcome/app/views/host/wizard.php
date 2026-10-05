<?php
/* La procedura guidata: cinque passi, ognuno salva da sé. Avanti, indietro,
   "continua dopo": quello che è scritto resta. A destra l'anteprima vera.
   Le lingue in più sono in fondo all'ultimo passo, facoltative. */
use function MHW\b;
use MHW\{Support, Csrf, Icon, Plans};
$pid = (int) $prop['id'];
$chiavi = array_keys($passi);
$i = array_search($passo, $chiavi, true);
$fatto = $prop['wizard_step'] === 'fatto' ? count($chiavi) : (int) array_search($prop['wizard_step'] ?: 'struttura', $chiavi, true);
$prec = $i > 0 ? $chiavi[$i - 1] : null;
$succ = $chiavi[$i + 1] ?? null;
$title = $passi[$passo] . ' — ' . $prop['name'];
$vai = fn(string $p) => b() . '/pannello/' . $pid . '/procedura/' . $p;
$barraIndietro = $prec ? $vai($prec) : '';
$avanti = fn(string $testo, string $verso = '') => $verso !== ''
    ? '<a class="btn btn--go" href="' . $verso . '">' . $testo . ' <span class="go">' . Icon::svg('arrow', 18, 2) . '</span></a>'
    : ''; ?>

<?php if ($prop['status'] !== 'published' && !MHW\Auth::isVerified($user)): /* la verifica email, in una riga */ ?>
  <form method="post" action="<?= b() ?>/verifica/invia" class="verifica-riga"><?= Csrf::field() ?>
    <span><?= Icon::svg('info', 16) ?><span>Conferma la tua email<span class="verifica-riga__email"> (<?= Support::e($user['email']) ?>)</span> per poter pubblicare.</span></span>
    <button class="linkbtn">Mandamela di nuovo</button>
  </form>
<?php endif; ?>

<nav class="steps-nav" aria-label="Passi della configurazione" style="margin-bottom:28px">
  <?php foreach ($passi as $k => $nome): $j = array_search($k, $chiavi, true); ?>
    <a href="<?= $vai($k) ?>" class="<?= $k === $passo ? 'on' : ($j < $fatto || $j < $i ? 'done' : '') ?>"<?= $k === $passo ? ' aria-current="step"' : '' ?>>
      <span class="n"><?= $j < $fatto && $k !== $passo ? Icon::svg('check', 12, 2.4) : $j + 1 ?></span><span class="steps-nav__nome"><?= Support::e($nome) ?></span></a>
  <?php endforeach; ?>
</nav>

<div class="editor">
  <div class="stack stack--lg">
    <div class="stack stack--sm">
      <span class="kicker">Passo <?= $i + 1 ?> di <?= count($passi) ?></span>

<?php switch ($passo):
case 'struttura': ?>
      <h1>Struttura e contatti.</h1>
      <p class="lead">Nome, orari, contatti e la lingua in cui scrivi: le informazioni che l'ospite cerca per prime.</p>
    </div>
    <?php $dopoPasso = 'arrivo'; include __DIR__ . '/_struttura_form.php'; ?>
<?php break;

case 'arrivo': $dati = json_decode((string) $core['data'], true) ?: []; ?>
      <h1>Arrivo e partenza.</h1>
      <p class="lead">Come si entra e cosa fare prima di partire. È il cuore della guida ed è sempre incluso, in ogni piano.
        Non scrivere qui codici di porte o cassette: mandali all'ospite in privato.</p>
    </div>
    <form method="post" action="<?= b() ?>/pannello/<?= $pid ?>/sezioni/<?= (int) $core['id'] ?>" class="stack" data-autosave><?= Csrf::field() ?>
      <?php $kind = 'checkin'; $uid = 'core'; include __DIR__ . '/_campi.php'; ?>
      <?php $barraAvanti = '<button class="btn btn--go" name="dopo" value="sezioni">Salva e continua <span class="go">' . Icon::svg('arrow', 18, 2) . '</span></button>';
            include __DIR__ . '/_barra_passo.php'; ?>
    </form>
<?php break;

case 'sezioni': ?>
      <h1>Sezioni.</h1>
      <p class="lead">Aggiungi quello che serve davvero ai tuoi ospiti: la sezione si apre subito qui sotto, da compilare.
        Si salva mentre scrivi, e una sezione disattivata tiene i suoi contenuti.</p>
    </div>
    <?php $torna = 'procedura'; include __DIR__ . '/_sezioni.php'; ?>
    <?php $barraAvanti = $avanti('Salva e continua', $vai('aspetto')); include __DIR__ . '/_barra_passo.php'; ?>
<?php break;

case 'aspetto': ?>
      <h1>Aspetto.</h1>
      <p class="lead">Scegli i colori e carica la copertina. L'anteprima accanto cambia mentre scegli.</p>
    </div>
    <?php $dopoPasso = 'pubblica'; include __DIR__ . '/_aspetto_form.php'; ?>
<?php break;

case 'pubblica': $quantita = (int) ($acc['intended_quantity'] ?? 1); ?>
      <h1><?= $prop['status'] === 'published' && $online ? 'Pubblica le modifiche.' : 'Anteprima e pubblicazione.' ?></h1>
      <p class="lead">Guarda la guida come la vedranno gli ospiti. Quando ti convince, pubblicala.</p>
    </div>
    <a class="btn btn--ghost phonebtn" href="<?= Support::e(Support::url('/pannello/' . $pid . '/anteprima')) ?>" target="_blank" rel="noopener"><?= Icon::svg('eye', 16) ?>Apri l'anteprima</a>

    <?php if ($problemi): ?>
      <div class="note note--err" role="alert"><div class="stack" style="gap:6px"><b>Prima di pubblicare</b>
        <?php foreach ($problemi as $pr): ?><span><?= Support::e($pr) ?></span><?php endforeach; ?></div></div>
    <?php endif; ?>

    <div class="panel stack">
      <?php if ($sub): ?>
        <p>Il tuo abbonamento <b><?= Support::e($piano['name'] ?? '') ?></b> è attivo<?= $sub['current_period_end'] ? ' fino al ' . Support::e(Support::date($sub['current_period_end'])) : '' ?>:
          la guida si pubblica subito, senza nuovi pagamenti.</p>
      <?php elseif ($piano): $qSc = Plans::perProperty($piano) ? $quantita : 1; $sc = MHW\Sconti::disponibili() ? MHW\Sconti::applicato($acc, $piano, $qSc) : null; ?>
        <div class="spread spread--mid">
          <div class="stack" style="gap:4px"><span class="kicker">Il tuo piano</span>
            <b style="font-size:22px;font-weight:500"><?= Support::e($piano['name']) ?><?= Plans::perProperty($piano) ? ' · ' . $quantita . ' strutture' : '' ?></b></div>
          <span style="font-size:26px;font-weight:500"><?php if ($sc): ?><s class="muted" style="font-size:18px"><?= Support::e(Support::money($sc['prezzo'], $piano['currency'])) ?></s>
            <?= Support::e(Support::money($sc['scontato'], $piano['currency'])) ?><span class="small muted"> + IVA il primo anno</span>
            <?php else: ?><?= Support::e(Support::money(Plans::price($piano, $qSc), $piano['currency'])) ?>
            <span class="small muted">+ IVA / anno</span><?php endif; ?></span>
        </div>
        <?php $pv = $piano; $quantita = $qSc; $torna = '/pannello/' . $pid . '/procedura/pubblica'; include __DIR__ . '/_sconto.php'; ?>
        <p class="small muted">Abbonamento annuale con rinnovo automatico, che puoi disattivare quando vuoi. Il pagamento avviene su Stripe;
          la guida va online appena Stripe conferma. <a href="<?= b() ?>/piano">Cambia piano</a></p>
        <?php if (!$verificato): ?>
          <div class="note" role="status"><span>Per attivare l'abbonamento conferma prima la tua email (<?= Support::e($user['email']) ?>).</span></div>
          <form method="post" action="<?= b() ?>/verifica/invia" style="margin:0"><?= Csrf::field() ?><button class="btn btn--ghost btn--sm">Mandami di nuovo l'email</button></form>
        <?php endif; ?>
        <?php if (!MHW\Fatturazione::completa($acc)): ?>
          <div class="note" role="status"><span>Prima del pagamento servono i dati di fatturazione (P.IVA o codice fiscale, SDI o PEC).
            <a href="<?= b() ?>/account?torna=<?= rawurlencode('/pannello/' . $pid . '/procedura/pubblica') ?>#fatturazione">Compilali ora</a>.</span></div>
        <?php endif; ?>
      <?php else: ?>
        <p>Scegli il piano con cui pubblicare. <a href="<?= b() ?>/piano">Vedi i piani</a></p>
      <?php endif; ?>

    </div>

    <?php /* Facoltativo: le lingue in più. Le traduzioni non fermano la pubblicazione:
             dove mancano, l'ospite legge la lingua principale. */
          $altreLingue = array_diff_key($tutte, [$prop['default_locale'] => 1]); if ($altreLingue): ?>
      <form method="post" action="<?= b() ?>/pannello/<?= $pid ?>/lingue" class="fieldset stack" style="gap:12px"><?= Csrf::field() ?>
        <span class="legend">Vuoi la guida anche in altre lingue? <span class="small muted" style="font-weight:400">Facoltativo</span></span>
        <p class="help">Puoi pubblicare anche solo in <?= Support::e($tutte[$prop['default_locale']] ?? $prop['default_locale']) ?>. Le traduzioni le scrivi
          da <a href="<?= b() ?>/pannello/<?= $pid ?>/lingue">Lingue</a>, quando vuoi: dove mancano, l'ospite legge la lingua principale.</p>
        <input type="hidden" name="locali[]" value="<?= Support::e($prop['default_locale']) ?>">
        <div class="scelte">
          <?php foreach ($altreLingue as $code => $nomeL): $ok = in_array($code, $consentite, true); ?>
            <label class="scelta"><input type="checkbox" name="locali[]" value="<?= Support::e($code) ?>" <?= in_array($code, $lingueAttive, true) && $ok ? 'checked' : '' ?> <?= $ok ? '' : 'disabled' ?>>
              <span class="scelta__testo"><?= Support::e($nomeL) ?><?php if (!$ok): ?> <span class="small muted">compresa nel piano Plus</span><?php endif; ?></span></label>
          <?php endforeach; ?>
        </div>
        <div><button class="btn btn--ghost btn--sm" name="dopo" value="pubblica">Salva le lingue</button></div>
      </form>
    <?php endif; ?>

    <form method="post" action="<?= b() ?>/pannello/<?= $pid ?>/pubblica" style="margin:0"><?= Csrf::field() ?>
      <?php $barraAvanti = '<button class="btn btn--go" ' . ($problemi || (!$sub && (!$verificato || !$piano)) ? 'disabled' : '') . '>'
                         . ($sub ? 'Pubblica ora' : 'Attiva e pubblica') . ' <span class="go">' . Icon::svg('arrow', 18, 2) . '</span></button>';
            include __DIR__ . '/_barra_passo.php'; ?>
    </form>
<?php break;
endswitch; ?>

  </div>
  <?php include __DIR__ . '/_telefono.php'; ?>
</div>
