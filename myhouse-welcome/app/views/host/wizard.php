<?php use function MHW\tf;
/* La procedura guidata: cinque passi, ognuno salva da sé. Avanti, indietro,
   "continua dopo": quello che è scritto resta. A destra l'anteprima vera.
   Le lingue in più sono in fondo all'ultimo passo, facoltative. */
use function MHW\b; use function MHW\t;
use MHW\{Support, Csrf, Icon, Plans};
$pid = (int) $prop['id'];
$chiavi = array_keys($passi);
$i = array_search($passo, $chiavi, true);
$fatto = $prop['wizard_step'] === 'fatto' ? count($chiavi) : (int) array_search($prop['wizard_step'] ?: 'struttura', $chiavi, true);
$prec = $i > 0 ? $chiavi[$i - 1] : null;
$succ = $chiavi[$i + 1] ?? null;
$title = t($passi[$passo]) . ' — ' . $prop['name'];
$vai = fn(string $p) => b() . '/pannello/' . $pid . '/procedura/' . $p;
$barraIndietro = $prec ? $vai($prec) : '';
$avanti = fn(string $testo, string $verso = '') => $verso !== ''
    ? '<a class="btn btn--go" href="' . $verso . '">' . $testo . ' <span class="go">' . Icon::svg('arrow', 18, 2) . '</span></a>'
    : ''; ?>

<?php if ($prop['status'] !== 'published' && !MHW\Auth::isVerified($user)): /* la verifica email, in una riga */ ?>
  <form method="post" action="<?= b() ?>/verifica/invia" class="verifica-riga"><?= Csrf::field() ?>
    <span><?= Icon::svg('info', 16) ?><span><?= t('Conferma la tua email') ?><span class="verifica-riga__email"> (<?= Support::e($user['email']) ?>)</span> <?= t('per poter pubblicare.') ?></span></span>
    <button class="linkbtn"><?= t('Mandami di nuovo l\'email') ?></button>
  </form>
<?php endif; ?>

<nav class="steps-nav" aria-label="<?= t('Passi della configurazione') ?>" style="margin-bottom:28px">
  <?php foreach ($passi as $k => $nome): $j = array_search($k, $chiavi, true); ?>
    <a href="<?= $vai($k) ?>" class="<?= $k === $passo ? 'on' : ($j < $fatto || $j < $i ? 'done' : '') ?>"<?= $k === $passo ? ' aria-current="step"' : '' ?>>
      <span class="n"><?= $j < $fatto && $k !== $passo ? Icon::svg('check', 12, 2.4) : $j + 1 ?></span><span class="steps-nav__nome"><?= Support::e(t($nome)) ?></span></a>
  <?php endforeach; ?>
</nav>

<div class="editor">
  <div class="stack stack--lg">
    <div class="stack stack--sm">
      <span class="kicker"><?= t('Passo') ?> <?= $i + 1 ?> <?= t('di') ?> <?= count($passi) ?></span>

<?php switch ($passo):
case 'struttura': ?>
      <h1><?= t('Struttura e contatti.') ?></h1>
      <p class="lead"><?= t('Nome, orari, contatti e la lingua in cui scrivi: le informazioni che l\'ospite cerca per prime.') ?></p>
    </div>
    <?php $dopoPasso = 'arrivo'; include __DIR__ . '/_struttura_form.php'; ?>
    <?php if (empty($avvisoLetto)): /* 6M · una volta sola per account (9N: sotto il modulo, non prima dei campi): «Ho capito» lo chiude per sempre */ ?>
      <section class="privacy-nota privacy-nota--prima" aria-labelledby="chi-vede">
        <?= Icon::svg('lock', 18) ?>
        <div class="stack" style="gap:8px">
          <h2 id="chi-vede" style="font-size:18px;line-height:24px;margin:0"><?= t('Chi vede la tua guida') ?></h2>
          <p style="margin:0"><?= t('Solo chi ha il link o inquadra il QR: la guida non compare su Google né negli assistenti AI. Scrivi tutto quello che serve all\'ospite.') ?>
            <?= t('I codici di porte, cassette delle chiavi, casseforti e allarmi ti sconsigliamo di scriverli: comunicali tu, di persona o in privato.') ?></p>
          <form method="post" action="<?= b() ?>/pannello/avviso-guida" style="margin:0"><?= Csrf::field() ?>
            <input type="hidden" name="torna" value="<?= Support::e('/pannello/' . $pid . '/procedura/struttura') ?>">
            <button class="btn btn--sm"><?= t('Ho capito') ?></button></form>
        </div>
      </section>
    <?php endif; ?>
<?php break;

case 'arrivo': $dati = json_decode((string) $core['data'], true) ?: []; ?>
      <h1><?= t('Check-in &amp; Check-out.') ?></h1>
      <p class="lead"><?= t('Come si entra, cosa serve all\'arrivo e cosa fare prima di partire. Spiega dove sono le chiavi e come si apre.') ?></p><?php /* 9N · l'avviso sui codici una volta sola: è nel riquadro qui sotto */ ?>
      <?php include __DIR__ . '/_privacy_nota.php'; ?>
    </div>
    <form method="post" action="<?= b() ?>/pannello/<?= $pid ?>/sezioni/<?= (int) $core['id'] ?>" class="stack" data-autosave><?= Csrf::field() ?>
      <?php $kind = 'checkin'; $uid = 'core'; include __DIR__ . '/_campi.php'; ?>
      <?php $barraAvanti = '<button class="btn btn--go" name="dopo" value="sezioni">' . t('Salva e continua') . ' <span class="go">' . Icon::svg('arrow', 18, 2) . '</span></button>';
            include __DIR__ . '/_barra_passo.php'; ?>
    </form>
<?php break;

case 'sezioni': ?>
      <h1><?= t('Sezioni.') ?></h1>
      <p class="lead"><?= t('Aggiungi quello che serve davvero ai tuoi ospiti: la sezione si apre subito qui sotto, da compilare. Si salva mentre scrivi, e una sezione disattivata tiene i suoi contenuti.') ?></p>
    </div>
    <?php $torna = 'procedura'; include __DIR__ . '/_sezioni.php'; ?>
    <?php $barraAvanti = $avanti(t('Salva e continua'), $vai('aspetto')); include __DIR__ . '/_barra_passo.php'; ?>
<?php break;

case 'aspetto': ?>
      <h1><?= t('Aspetto.') ?></h1>
      <p class="lead"><?= t('Colori, logo e foto di copertina. L\'anteprima accanto cambia mentre scegli.') ?></p>
    </div>
    <?php $dopoPasso = 'pubblica'; include __DIR__ . '/_aspetto_form.php'; ?>
<?php break;

case 'pubblica': $quantita = (int) ($acc['intended_quantity'] ?? 1); ?>
      <h1><?= t($prop['status'] === 'published' && $online ? 'Pubblica le modifiche.' : 'Pubblica la guida.') ?></h1>
      <p class="lead"><?= t('Guarda la guida come la vedranno gli ospiti. Quando ti convince, pubblicala.') ?></p>
    </div>
    <a class="btn btn--ghost phonebtn" href="<?= Support::e(Support::url('/pannello/' . $pid . '/anteprima')) ?>" target="_blank" rel="noopener"><?= Icon::svg('eye', 16) ?><?= t('Apri l\'anteprima') ?></a>

    <?php if ($problemi): ?>
      <div class="note note--err" role="alert"><div class="stack" style="gap:6px"><b><?= t('Prima di pubblicare') ?></b>
        <?php foreach ($problemi as $pr): ?><span><?= Support::e(t($pr)) ?></span><?php endforeach; ?></div></div>
    <?php endif; ?>

    <div class="panel stack">
      <?php if ($sub): ?>
        <p><?= $sub['current_period_end'] ? tf('Il tuo abbonamento {piano} è attivo fino al {data}: la guida si pubblica subito, senza nuovi pagamenti.', ['{piano}' => '<b>' . Support::e($piano['name'] ?? '') . '</b>', '{data}' => Support::e(MHW\Pannello::data($sub['current_period_end']))])
                                         : tf('Il tuo abbonamento {piano} è attivo: la guida si pubblica subito, senza nuovi pagamenti.', ['{piano}' => '<b>' . Support::e($piano['name'] ?? '') . '</b>']) ?></p>
      <?php elseif ($piano): $qSc = Plans::perProperty($piano) ? $quantita : 1;
            /* 9A · Il conto del primo anno: sconti di sistema sommati (invito, giudizio) o il codice scritto a mano. */
            $riep = MHW\Sconti::disponibili() ? MHW\Sconti::riepilogo($piano, $qSc, $acc) : null; $sc = $riep && $riep['sconto'] > 0 ? $riep : null;
            $om = $riep['omaggio'] ?? null; $dataOm = $om ? MHW\Pannello::data(gmdate('Y-m-d\TH:i:s\Z', $om['fine'])) : ''; ?>
        <div class="spread spread--mid">
          <div class="stack" style="gap:4px"><span class="kicker"><?= t('Il tuo piano') ?></span>
            <b style="font-size:22px;font-weight:500"><?= Support::e(tf('Hai scelto {piano}', ['{piano}' => $piano['name']])) ?><?= Plans::perProperty($piano) ? ' · ' . Support::e(tf('{n} strutture', ['{n}' => $quantita])) : '' ?></b>
            <?php if (Plans::perProperty($piano)): $minQ = Plans::minimoPer($piano, (int) $acc['id']); $maxQ = (int) $piano['max_quantity'];
                  /* 9R · Il numero di strutture si cambia qui: −/+ (prezzi.js) e «Aggiorna» ricalcola il riepilogo; mai sotto le strutture create. */ ?>
              <form method="post" action="<?= b() ?>/piano" class="quante-pubblica" data-quantita data-base="<?= (int) $piano['price_cents'] ?>" data-extra="<?= (int) $piano['extra_price_cents'] ?>" data-scaglioni="<?= Support::e(Plans::tiersJson($piano)) ?>" data-valuta="<?= Support::e($piano['currency']) ?>"><?= Csrf::field() ?>
                <input type="hidden" name="pv" value="<?= (int) $piano['id'] ?>"><input type="hidden" name="torna" value="/pannello/<?= (int) $pid ?>/procedura/pubblica">
                <label for="pub-strutture" class="small"><?= t('Strutture') ?></label>
                <input id="pub-strutture" name="strutture" type="number" inputmode="numeric" step="1" min="<?= $minQ ?>" max="<?= $maxQ ?>" value="<?= max($minQ, (int) $qSc) ?>" onchange="this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()">
                <span class="small muted"><span data-totale><?= Support::e(Support::money(Plans::price($piano, max($minQ, (int) $qSc)), $piano['currency'])) ?></span> <?= t('+ IVA / anno') ?></span>
                <button class="btn btn--ghost btn--sm"><?= t('Aggiorna') ?></button>
              </form>
              <p class="small muted" style="margin:0"><?= Support::e(Plans::scomposizione($piano, (int) $qSc, MHW\Pannello::en())) ?></p>
              <?php if ($minQ > (int) $piano['min_quantity']): ?><p class="small muted" style="margin:0"><?= Support::e(tf('Hai già {n} strutture: il minimo è {n}; per ridurre, archivia una struttura.', ['{n}' => $minQ])) ?></p><?php endif; ?>
              <p class="small" style="margin:0"><a href="<?= b() ?>/piano?piano=<?= (int) $piano['id'] ?>&amp;strutture=<?= $maxQ + 1 ?>#preventivo"><?= Support::e(tf('Più di {n} strutture? Chiedi il preventivo', ['{n}' => $maxQ])) ?></a></p>
              <script src="<?= MHW\av('/assets/prezzi.js') ?>" defer></script>
            <?php endif; ?></div>
          <span style="font-size:26px;font-weight:500"><?php if ($om): /* 9D · con il codice omaggio oggi si paga 0 € */ ?>0&nbsp;€<span class="small muted"> <?= t('oggi') ?></span>
            <?php elseif ($sc): ?><s class="muted" style="font-size:18px"><?= Support::e(Support::money($sc['prezzo'], $piano['currency'])) ?></s>
            <?= Support::e(Support::money($sc['totale'], $piano['currency'])) ?><span class="small muted"> <?= t('+ IVA il primo anno') ?></span>
            <?php else: ?><?= Support::e(Support::money(Plans::price($piano, $qSc), $piano['currency'])) ?>
            <span class="small muted"><?= t('+ IVA / anno') ?></span><?php endif; ?></span>
        </div>
        <?php if ($sc && !$om): ?><p class="plan__sconto-riga" style="margin:0">−<?= $sc['percento'] > 0 ? (int) $sc['percento'] . '%' : Support::e(Support::money($sc['sconto'], $piano['currency'])) ?> <?= t('primo anno') ?></p><?php endif; ?>
        <?php if ($om): ?>
          <div class="spiega omaggio-spiega" role="status"><b><?= t('Oggi paghi 0 €.') ?></b> <?= tf('I tuoi primi {n} mesi sono gratis: il primo addebito di {prezzo} sarà il {data}.', ['{n}' => '<b>' . (int) $om['mesi'] . '</b>', '{prezzo}' => '<b>' . Support::e(Support::money($riep['totale'], $piano['currency'])) . ' ' . t('+ IVA') . '</b>', '{data}' => '<b>' . Support::e($dataOm) . '</b>']) ?>
            <?= Support::e(tf('Prima ti avvisiamo due volte, per email e nel pannello, 30 e 7 giorni prima. Puoi disattivare il rinnovo quando vuoi da Account & Fatturazione: fino al {data} la guida resta online e non paghi nulla; senza disattivazione l\'abbonamento parte da solo.', ['{data}' => $dataOm])) ?></div>
        <?php endif; ?>
        <?php if ($riep): $pv = $piano; $quantita = $qSc; include __DIR__ . '/_riepilogo.php'; endif; ?>
        <?php $pv = $piano; $quantita = $qSc; $torna = '/pannello/' . $pid . '/procedura/pubblica'; $soloCampo = true; include __DIR__ . '/_sconto.php'; ?>
        <p class="small muted"><?= t('Abbonamento annuale con rinnovo automatico, che puoi disattivare quando vuoi. Il pagamento avviene su Stripe; la guida va online appena Stripe conferma.') ?></p>
        <?php /* 7B: prima di pagare si può passare a un altro piano, anche più economico; se perde qualcosa che la guida usa, lo si dice prima. */
              $altriPiani = array_values(array_filter(Plans::public(), fn($o) => (int) $o['id'] !== (int) $piano['package_id']));
              $nStrutture = (int) MHW\Db::val('SELECT COUNT(*) FROM properties WHERE account_id = ? AND archived_at IS NULL AND is_demo < 2', [$acc['id']], 1);
              $torna = '/pannello/' . $pid . '/procedura/pubblica'; ?>
        <?php if ($altriPiani): ?>
          <div class="passa-piano">
            <?php foreach ($altriPiani as $o): $qO = Plans::perProperty($o) ? (Plans::quantity($o, (string) max((int) $o['min_quantity'], $nStrutture)) ?? (int) $o['min_quantity']) : 1;
                  $prezzoO = Plans::price($o, $qO); $menoCaro = $prezzoO < Plans::price($piano, $qSc);
                  $cambia = $menoCaro ? MHW\CambioAbbonamento::cosaCambia((int) $acc['id'], ['id' => (int) $o['pv_id']] + $o, $qO) : null;
                  $perdite = $cambia ? array_merge($cambia['perde'],
                      array_map(fn($x) => tf('«{nome}» ha {n} sezioni attive: con {piano} ne restano {max}, e prima di pubblicare scegli quali.', ['{nome}' => $x['struttura']['name'], '{n}' => count($x['sezioni']), '{piano}' => $o['name'], '{max}' => $cambia['maxSezioni']]), $cambia['sezioni']),
                      $cambia['daArchiviare'] ? [$cambia['maxStrutture'] === 1 ? tf('{piano} comprende 1 struttura: le altre restano in bozza.', ['{piano}' => $o['name']]) : tf('{piano} comprende {n} strutture: le altre restano in bozza.', ['{piano}' => $o['name'], '{n}' => $cambia['maxStrutture']])] : []) : [];
                  $etichettaO = tf('Passa a {piano} · {prezzo} + IVA / anno', ['{piano}' => $o['name'], '{prezzo}' => Support::money($prezzoO, $o['currency'])]);
                  $modulo = '<form method="post" action="' . b() . '/piano" style="margin:0">' . Csrf::field() . '<input type="hidden" name="pv" value="' . (int) $o['pv_id'] . '">'
                          . (Plans::perProperty($o) ? '<input type="hidden" name="strutture" value="' . $qO . '">' : '') . '<input type="hidden" name="torna" value="' . Support::e($torna) . '">'; ?>
              <?php if ($perdite): ?>
                <details class="passa-piano__conferma">
                  <summary class="btn btn--ghost btn--sm"><?= Support::e($etichettaO) ?></summary>
                  <div class="stack" style="gap:8px;margin-top:10px">
                    <p class="small" style="margin:0"><b><?= Support::e(tf('Con {piano} la guida non mostrerà:', ['{piano}' => $o['name']])) ?></b></p>
                    <ul class="small" style="margin:0;padding-left:18px"><?php foreach ($perdite as $x): ?><li><?= Support::e(t($x)) ?></li><?php endforeach; ?></ul>
                    <?= $modulo ?><button class="btn btn--sm"><?= Support::e(tf('Conferma: passa a {piano}', ['{piano}' => $o['name']])) ?></button></form>
                  </div>
                </details>
              <?php else: ?>
                <?= $modulo ?><button class="btn btn--ghost btn--sm"><?= Support::e($etichettaO) ?></button></form>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <?php if (!$verificato): ?>
          <div class="note" role="status"><span><?= Support::e(tf('Per attivare l\'abbonamento conferma prima la tua email ({email}).', ['{email}' => $user['email']])) ?></span></div>
          <form method="post" action="<?= b() ?>/verifica/invia" style="margin:0"><?= Csrf::field() ?><button class="btn btn--ghost btn--sm"><?= t('Mandami di nuovo l\'email') ?></button></form>
        <?php endif; ?>
        <?php if (!MHW\Fatturazione::completa($acc)): /* 7B: lo stesso modulo di Account & Fatturazione, qui, sopra «Conferma e paga». */ ?>
          <section class="stack fatt-pubblica" id="fatturazione" style="gap:12px" aria-labelledby="fatt-pubblica-titolo">
            <h2 id="fatt-pubblica-titolo" style="font-size:20px;margin:0"><?= t('I dati per la fattura') ?></h2>
            <p class="small muted" style="margin:0"><?= t('Servono per la fattura o la ricevuta. Potrai cambiarli quando vuoi in Account &amp; Fatturazione.') ?></p>
            <?php $torna = '/pannello/' . $pid . '/procedura/pubblica'; include __DIR__ . '/_fatturazione_form.php'; ?>
          </section>
        <?php endif; ?>
      <?php else: ?>
        <p><?= t('Scegli il piano con cui pubblicare.') ?> <a href="<?= b() ?>/piano"><?= t('Vedi i piani') ?></a></p>
      <?php endif; ?>

    </div>

    <?php /* 7C: le lingue con campi da tradurre (non ferma la pubblicazione) */
          $incomplete = array_filter(array_map(fn($per) => array_sum($per), MHW\Traduttore::mancanti($prop)));
          if ($incomplete): $nomiLI = MHW\Config::get('locales'); ?>
      <div class="note" role="status"><div class="stack" style="gap:4px"><b><?= t('Lingue da completare') ?></b>
        <?php foreach ($incomplete as $lm => $quanti): ?>
          <span><a href="<?= b() ?>/pannello/<?= $pid ?>/lingue/<?= Support::e($lm) ?>"><?= Support::e($nomiLI[$lm] ?? $lm) ?></a>: <?= Support::e($quanti === 1 ? t('1 campo da tradurre. Dove manca, l\'ospite legge la lingua principale.') : tf('{n} campi da tradurre. Dove manca, l\'ospite legge la lingua principale.', ['{n}' => (int) $quanti])) ?></span>
        <?php endforeach; ?></div></div>
            <?php /* 9F · «Traduci tutta la guida»: una lingua per volta, con la barra (solo con JavaScript e con le suggerite nel piano) */
              if (MHW\Traduttore::perche((int) $prop['account_id'], $prop) === ''): $lingueW = array_map(fn($lm) => ['loc' => $lm, 'nome' => ucfirst(MHW\I18n::nomeIt($lm)),
                  'url' => b() . '/pannello/' . $pid . '/lingue/' . $lm . '/suggerite.json', 'pagina' => b() . '/pannello/' . $pid . '/lingue/' . $lm], array_keys($incomplete)); ?>
          <div class="traduci-sezione" data-traduci-sezione="0" data-lingue="<?= Support::e(json_encode($lingueW, JSON_UNESCAPED_UNICODE)) ?>" data-csrf="<?= Support::e(Csrf::token()) ?>" hidden>
            <button type="button" class="btn btn--sm" data-traduci-avvia><?= Icon::svg('globe', 15) ?> <span data-traduci-etichetta><?= Support::e(count($incomplete) === 1 ? t('Traduci tutta la guida in 1 lingua') : tf('Traduci tutta la guida in {n} lingue', ['{n}' => count($incomplete)])) ?></span></button>
            <div class="traduci-stato" data-traduci-stato hidden role="status" aria-live="polite"><span class="traduci-stato__barra"><i></i></span><p class="small" data-traduci-testo style="margin:0"></p></div>
            <div class="traduci-esito" data-traduci-esito></div>
          </div>
        <?php endif; ?>
        <?php endif; ?>
    <?php /* Facoltativo: le lingue in più. Le traduzioni non fermano la pubblicazione:
             dove mancano, l'ospite legge la lingua principale. */
          $altreLingue = array_diff_key($tutte, [$prop['default_locale'] => 1]); if ($altreLingue): ?>
      <form method="post" action="<?= b() ?>/pannello/<?= $pid ?>/lingue" class="fieldset stack" style="gap:12px"><?= Csrf::field() ?>
        <span class="legend"><?= t('Vuoi la guida anche in altre lingue?') ?> <span class="small muted" style="font-weight:400"><?= t('Facoltativo') ?></span></span>
        <p class="help"><?= t('Puoi pubblicare anche solo in') ?> <?= Support::e($tutte[$prop['default_locale']] ?? $prop['default_locale']) ?>. <?= t('Le traduzioni le scrivi') ?>
          <?= t('da') ?> <a href="<?= b() ?>/pannello/<?= $pid ?>/lingue"><?= t('Lingue') ?></a>, <?= t('quando vuoi: dove mancano, l\'ospite legge la lingua principale.') ?></p>
        <?php include __DIR__ . '/_lingue_scelta.php'; ?>
        <div><button class="btn btn--sm" name="dopo" value="pubblica"><?= t('Salva le lingue') ?></button></div>
      </form>
    <?php endif; ?>

    <?php if (!empty($codici)): /* 6M: codici di accesso, conferma a proprio rischio (vale solo per questa pubblicazione) */ ?>
      <section class="codici-conferma" id="codici" role="alertdialog" aria-labelledby="codici-titolo" aria-describedby="codici-testo" tabindex="-1">
        <h2 id="codici-titolo"><?= Icon::svg('lock', 20) ?><?= t('Nella guida ci sono codici di accesso') ?></h2>
        <ul class="codici-conferma__campi">
          <?php foreach ($codici as $c): $dove = $c['lingua'] === $prop['default_locale'] ? '/pannello/' . $pid . '/sezioni/' . $c['sid'] : '/pannello/' . $pid . '/lingue/' . $c['lingua']; ?>
            <li><span><b><?= Support::e($c['sezione']) ?></b> · <?= Support::e($c['campo']) ?><?= $c['lingua'] !== $prop['default_locale'] ? ' <span class="small muted">(' . Support::e(strtoupper($c['lingua'])) . ')</span>' : '' ?></span>
              <a href="<?= b() . $dove ?>"><?= t('Correggi') ?></a></li>
          <?php endforeach; ?>
        </ul>
        <p id="codici-testo"><?= t('Chi ha il link o il QR della guida può leggerli, anche dopo la partenza, e il link si può inoltrare. Ti consigliamo di toglierli e comunicarli all\'ospite in privato. Se pubblichi lo stesso, lo fai a tuo rischio: MyHouse Welcome non risponde dell\'uso di questi codici.') ?></p>
        <form method="post" action="<?= b() ?>/pannello/<?= $pid ?>/pubblica" class="stack" style="gap:16px;margin:0" data-codici-conferma><?= Csrf::field() ?>
          <label class="check"><input type="checkbox" name="codici_ok" value="1" required data-codici-ok> <span><?= t('Ho capito, pubblico a mio rischio') ?></span></label>
          <div class="row" style="gap:12px">
            <a class="btn" href="<?= b() ?>/pannello/<?= $pid ?>/<?= $codici[0]['lingua'] === $prop['default_locale'] ? 'sezioni/' . $codici[0]['sid'] : 'lingue/' . $codici[0]['lingua'] ?>"><?= t('Torna a correggere') ?></a>
            <button class="btn btn--ghost" data-codici-vai disabled><?= t('Pubblica lo stesso') ?></button>
            <?php /* senza JavaScript il bottone resta attivo: la casella è obbligatoria e il server la controlla */ ?>
            <noscript><style>[data-codici-vai]{display:none}</style><button class="btn btn--ghost"><?= t('Pubblica lo stesso') ?></button></noscript>
          </div>
        </form>
        <script>
        (function () {
          var f = document.querySelector('[data-codici-conferma]'); if (!f) return;
          var ok = f.querySelector('[data-codici-ok]'), vai = f.querySelector('[data-codici-vai]');
          var aggiorna = function () { vai.disabled = !ok.checked; };
          ok.addEventListener('change', aggiorna); aggiorna();
          document.getElementById('codici').focus();
        })();
        </script>
      </section>
    <?php endif; ?>

    <p class="small muted pubblica-riservata"><?= Icon::svg('lock', 14) ?><span><?= t('Dopo la pubblicazione la guida è online per chi ha il link o il QR. Non compare nei motori di ricerca.') ?></span></p>
    <form method="post" action="<?= b() ?>/pannello/<?= $pid ?>/pubblica" style="margin:0" data-base="<?= b() ?>"><?= Csrf::field() ?>
      <?php if (!$sub && $piano): /* 8J · chi acquista, prima del primo pagamento: impresa o privato (con la richiesta di iniziare subito) */
            $acqS = (array) ($_SESSION['acquisto'] ?? []); ?>
        <fieldset class="fieldset stack acquisto" id="acquisto" style="gap:10px;margin:0 0 16px">
          <legend class="legend"><?= t('Chi acquista?') ?></legend>
          <label class="check"><input type="radio" name="acquirente" value="impresa" required data-acquirente<?= ($acqS['acquirente'] ?? '') === 'impresa' ? ' checked' : '' ?>>
            <span><?= t('Acquisto per la mia attività ricettiva (impresa o professionista)') ?></span></label>
          <label class="check"><input type="radio" name="acquirente" value="consumatore" required data-acquirente<?= ($acqS['acquirente'] ?? '') === 'consumatore' ? ' checked' : '' ?>>
            <span><?= t('Acquisto come privato') ?></span></label>
          <div class="acquisto__privato stack" style="gap:6px" data-acquisto-privato>
            <label class="check" style="align-items:flex-start"><input type="checkbox" name="recesso_rinuncia" value="1" data-recesso style="margin-top:1px"<?= !empty($acqS['rinuncia']) ? ' checked' : '' ?>>
              <span><?= t('Chiedo che il servizio inizi subito e so che, pubblicata la guida, perdo il diritto di recesso per il periodo già goduto') ?></span></label>
            <p class="help" style="margin:0"><?= t('Solo per i privati, ed è obbligatoria per pubblicare subito. Come privato hai 14 giorni per recedere:') ?> <a href="<?= b() ?>/termini#recesso" target="_blank" rel="noopener"><?= t('come funziona') ?></a>.</p>
          </div>
        </fieldset>
        <?php /* 9B/9L · «Giudizio con sconto»: una sola casella, mai pre-spuntata, solo con l'interruttore acceso e se non ha mai aderito
                 (o ha aderito al pagamento e non ha ancora pagato: la si può togliere). Lo spiegone sotto, sempre visibile. */
              // 9L · Con un codice omaggio (9D) la casella non compare.
              $giudizioPuo = MHW\Giudizi::attivo() && !(MHW\Omaggi::disponibili() && MHW\Omaggi::daUsare($acc)) && (MHW\Giudizi::puoAderire($acc) || (($acc['giudizio_adesione'] ?? '') === 'pagamento' && ($acc['giudizio_sconto_stato'] ?? '') === 'promesso'));
              if ($giudizioPuo): $giudizioSi = ($acc['giudizio_adesione'] ?? '') === 'pagamento'; ?>
          <fieldset class="fieldset stack giudizio-sconto" id="giudizio-sconto" style="gap:8px;margin:0 0 16px" data-giudizio-sconto data-torna="/pannello/<?= $pid ?>/procedura/pubblica">
            <legend class="legend"><?= t('Giudizio con sconto') ?> <span class="badge badge--pine">−<?= MHW\Giudizi::percentuale() ?>%</span></legend>
            <input type="hidden" name="giudizio_casella" value="1">
            <label class="check" style="align-items:flex-start"><input type="checkbox" name="giudizio_adesione" value="1" data-giudizio-casella style="margin-top:1px"<?= $giudizioSi ? ' checked' : '' ?>>
              <span><?= MHW\Giudizi::casellaHtml() /* 9L · il testo unico di «Testi e foto» (giudizio.casella) */ ?></span></label>
            <p class="help giudizio-sconto__spiega" style="margin:0"><?= MHW\Giudizi::casellaNota(true) /* 9R · la riga corta, «sul primo anno» */ ?> <?= t('Regole complete nei') ?> <a href="<?= b() ?>/termini#giudizio-sconto" target="_blank" rel="noopener"><?= t('Termini') ?></a><?= t(', punto «Giudizio con sconto».') ?></p>
            <?php if (($spQ = MHW\Giudizi::spiegaQuando()) !== ''): /* 9B-bis · lo sconto solo dopo il giudizio */ ?><p class="help" style="margin:0"><?= Support::e(t($spQ)) ?></p><?php endif; ?>
            <noscript><button class="btn btn--ghost btn--sm" formaction="<?= b() ?>/giudizio/adesione" formmethod="post" name="torna" value="/pannello/<?= $pid ?>/procedura/pubblica"><?= t('Aggiorna il riepilogo') ?></button></noscript>
          </fieldset>
          <script>
          /* Spuntata o tolta: si salva subito e la pagina si ricarica con il riepilogo aggiornato (senza JavaScript: «Aggiorna il riepilogo», o all'invio). */
          (function () {
            var f = document.querySelector('[data-giudizio-sconto]'), c = f && f.querySelector('[data-giudizio-casella]'); if (!c) return;
            c.addEventListener('change', function () {
              var fd = new FormData(); fd.append('_csrf', f.closest('form').querySelector('input[name=_csrf]').value); fd.append('torna', f.getAttribute('data-torna'));
              if (c.checked) fd.append('giudizio_adesione', '1');
              c.disabled = true;
              fetch(f.closest('form').getAttribute('data-base') + '/giudizio/adesione', { method: 'POST', body: fd, headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                .then(function () { location.reload(); })
                .catch(function () { c.disabled = false; });
            });
          })();
          </script>
        <?php endif; ?>
        <script>
        (function () {
          var f = document.getElementById('acquisto'); if (!f) return;
          var priv = f.querySelector('[data-acquisto-privato]'), c = f.querySelector('[data-recesso]');
          var aggiorna = function () {
            var x = f.querySelector('[data-acquirente]:checked'), p = !!x && x.value === 'consumatore';
            priv.hidden = !p; c.required = p; if (!p) c.checked = false;
          };
          f.addEventListener('change', aggiorna); aggiorna();
        })();
        </script>
      <?php endif; ?>
      <?php /* 9D · con l'omaggio l'obbligo di pagare sta sul bottone (art. 65 Codice del Consumo): «0 € oggi, X € + IVA il …» */
            $omB = !$sub && $piano && !empty($riep['omaggio']) ? $riep : null;
            $etichettaB = $sub ? t('Pubblica ora') : ($omB ? t('Inserisci la carta e pubblica') . ' <small class="btn__sotto">' . Support::e(tf('0 € oggi · {prezzo} + IVA il {data}', ['{prezzo}' => Support::money($omB['totale'], $piano['currency']), '{data}' => MHW\Pannello::data(gmdate('Y-m-d\TH:i:s\Z', $omB['omaggio']['fine']))])) . '</small>' : t('Conferma e paga'));
            $spentoB = $problemi || (!$sub && (!$verificato || !$piano || !MHW\Fatturazione::completa($acc)));
            $barraAvanti = '<button class="btn btn--go' . ($omB ? ' btn--due-righe' : '') . '" ' . ($spentoB ? 'disabled' : '') . '>'
                         . $etichettaB . ' <span class="go">' . Icon::svg('arrow', 18, 2) . '</span></button>';
            /* Con la chiave pubblicabile di Stripe: il bottone apre il modulo della carta nel pannello; la pagina di Stripe resta a un clic. */
            if (!$sub && $piano && MHW\Stripe::incorporato()): ?>
      <p class="small muted pagamento-modi"><?= Icon::svg('lock', 14) ?><span><?= t('Paghi qui con la carta di credito o di debito, nel modulo sicuro di Stripe: i dati della carta non passano dal nostro sito.') ?>
        <button class="linkbtn" name="modo" value="stripe"<?= $spentoB ? ' disabled' : '' ?>><?= t('Preferisci pagare sulla pagina di Stripe?') ?></button></span></p>
<?php       endif;
            include __DIR__ . '/_barra_passo.php';
            /* 9N · Il bottone spento dice perché, proprio sotto: con il link a dove si sistema (il CIN e il nome stanno al passo 1). */
            if ($spentoB):
              $cause = [];
              foreach ($problemi as $pr) $cause[] = [t($pr), preg_match('/\bCIN\b|nome della struttura/u', $pr) ? '/pannello/' . $pid . '/procedura/struttura#cin' : (str_contains($pr, 'Check-in') ? '/pannello/' . $pid . '/procedura/arrivo' : '')];
              if (!$sub && !$verificato) $cause[] = [t('Conferma la tua email: il link è nella posta che ti abbiamo mandato.'), ''];
              if (!$sub && !$piano) $cause[] = [t('Scegli il piano con cui pubblicare.'), '/piano'];
              if (!$sub && $piano && !MHW\Fatturazione::completa($acc)) $cause[] = [t('Compila i dati per la fattura, qui sopra.'), '#fatturazione']; ?>
      <div class="note note--err barra-passo__perche" role="status"><div class="stack" style="gap:4px">
        <b><?= t('Il bottone si attiva quando:') ?></b>
        <?php foreach ($cause as [$testoC, $doveC]): ?><span><?= Support::e($testoC) ?><?php if ($doveC !== ''): ?> <a href="<?= Support::e(str_starts_with($doveC, '#') ? $doveC : b() . $doveC) ?>"><?= t('Sistema') ?></a><?php endif; ?></span><?php endforeach; ?>
      </div></div>
<?php       endif; ?>
    </form>
<?php break;
endswitch; ?>

  </div>
  <?php include __DIR__ . '/_telefono.php'; ?>
</div>
