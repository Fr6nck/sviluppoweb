<?php
/* La procedura guidata: sette passi, ognuno salva da sé. Avanti, indietro,
   "continua dopo": quello che è scritto resta. A destra l'anteprima vera. */
use function MHW\b;
use MHW\{Support, Csrf, Icon};
$pid = (int) $prop['id'];
$chiavi = array_keys($passi);
$i = array_search($passo, $chiavi, true);
$fatto = (int) array_search($prop['wizard_step'] ?: 'struttura', $chiavi, true);
$prec = $i > 0 ? $chiavi[$i - 1] : null;
$succ = $chiavi[$i + 1] ?? null;
$title = $passi[$passo] . ' — ' . $prop['name'];
$vai = fn(string $p) => b() . '/pannello/' . $pid . '/procedura/' . $p;
$barraIndietro = $prec ? $vai($prec) : '';
$avanti = fn(string $testo, string $verso = '') => $verso !== ''
    ? '<a class="btn btn--go" href="' . $verso . '">' . $testo . ' <span class="go">' . Icon::svg('arrow', 18, 2) . '</span></a>'
    : ''; ?>

<nav class="steps-nav" aria-label="Passi della configurazione" style="margin-bottom:28px">
  <?php foreach ($passi as $k => $nome): $j = array_search($k, $chiavi, true); ?>
    <a href="<?= $vai($k) ?>" class="<?= $k === $passo ? 'on' : ($j < $fatto || $j < $i ? 'done' : '') ?>"<?= $k === $passo ? ' aria-current="step"' : '' ?>>
      <span class="n"><?= $j < $fatto && $k !== $passo ? Icon::svg('check', 12, 2.4) : $j + 1 ?></span><?= Support::e($nome) ?></a>
  <?php endforeach; ?>
</nav>

<div class="editor">
  <div class="stack stack--lg">
    <div class="stack stack--sm">
      <span class="kicker">Passo <?= $i + 1 ?> di <?= count($passi) ?></span>

<?php switch ($passo):
case 'struttura': ?>
      <h1>La tua struttura.</h1>
      <p class="lead">Nome, orari e contatti: le informazioni che l'ospite cerca per prime.</p>
    </div>
    <?php $dopoPasso = 'checkin'; include __DIR__ . '/_struttura_form.php'; ?>
<?php break;

case 'checkin': $dati = json_decode((string) $core['data'], true) ?: []; ?>
      <h1>Check-in &amp; Check-out.</h1>
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
      <h1>Scegli le sezioni.</h1>
      <p class="lead">Aggiungi solo quello che serve davvero ai tuoi ospiti. Potrai cambiare idea quando vuoi: una sezione disattivata tiene i suoi contenuti.</p>
    </div>
    <?php $torna = 'procedura'; include __DIR__ . '/_sezioni.php'; ?>
    <?php $barraAvanti = $avanti('Salva e continua', $vai('contenuti')); include __DIR__ . '/_barra_passo.php'; ?>
<?php break;

case 'contenuti': $attiveSez = array_filter($sezioni, fn($s) => (int) $s['is_active'] === 1); ?>
      <h1>Compila i contenuti.</h1>
      <p class="lead">Apri ogni sezione e scrivi le informazioni. Si salva mentre scrivi.</p>
    </div>
    <div class="stack" style="gap:10px">
      <?php if (!$attiveSez): ?><p class="note note--quiet">Nessuna sezione aggiuntiva attiva. <a href="<?= $vai('sezioni') ?>">Scegline qualcuna</a>, oppure continua.</p><?php endif; ?>
      <?php foreach ($attiveSez as $s): ?>
        <a class="rowcard <?= $s['empty'] ? 'rowcard--flag' : '' ?>" href="<?= b() ?>/pannello/<?= $pid ?>/sezioni/<?= (int) $s['id'] ?>?da=procedura">
          <span style="color:var(--accent);display:flex"><?= Icon::svg(MHW\SectionCatalog::icon($s['kind']), 20) ?></span>
          <b class="grow"><?= Support::e($s['title']) ?></b>
          <?= $s['empty'] ? '<span class="badge badge--terracotta">Da compilare</span>' : '<span class="badge badge--pine">Pronta</span>' ?>
          <span class="small muted">Apri</span>
        </a>
      <?php endforeach; ?>
    </div>
    <?php $barraAvanti = $avanti('Salva e continua', $vai('lingue')); include __DIR__ . '/_barra_passo.php'; ?>
<?php break;

case 'lingue': ?>
      <h1>Lingue.</h1>
      <p class="lead">In quali lingue vuoi pubblicare la guida? Le traduzioni le scrivi tu, da Lingue, quando vuoi.</p>
    </div>
    <?php $dopoPasso = 'aspetto'; include __DIR__ . '/_lingue_form.php'; ?>
<?php break;

case 'aspetto': ?>
      <h1>Aspetto.</h1>
      <p class="lead">Scegli i colori e carica la copertina. L'anteprima accanto cambia mentre scegli.</p>
    </div>
    <?php $dopoPasso = 'anteprima'; include __DIR__ . '/_aspetto_form.php'; ?>
<?php break;

case 'anteprima': ?>
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
      <?php elseif ($piano): ?>
        <div class="spread spread--mid">
          <div class="stack" style="gap:4px"><span class="kicker">Il tuo piano</span>
            <b style="font-size:22px;font-weight:500"><?= Support::e($piano['name']) ?></b></div>
          <span style="font-size:26px;font-weight:500"><?= Support::e(Support::money((int) $piano['price_cents'], $piano['currency'])) ?>
            <span class="small muted">+ IVA / anno</span></span>
        </div>
        <p class="small muted">Abbonamento annuale con rinnovo automatico, che puoi disattivare quando vuoi. Il pagamento avviene su Stripe;
          la guida va online appena Stripe conferma. <a href="<?= b() ?>/piano">Cambia piano</a></p>
        <?php if (!$verificato): ?>
          <div class="note" role="status"><span>Per attivare l'abbonamento conferma prima la tua email (<?= Support::e($user['email']) ?>).</span></div>
          <form method="post" action="<?= b() ?>/verifica/invia" style="margin:0"><?= Csrf::field() ?><button class="btn btn--ghost btn--sm">Mandami di nuovo l'email</button></form>
        <?php endif; ?>
      <?php else: ?>
        <p>Scegli il piano con cui pubblicare. <a href="<?= b() ?>/piano">Vedi i piani</a></p>
      <?php endif; ?>

    </div>
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
