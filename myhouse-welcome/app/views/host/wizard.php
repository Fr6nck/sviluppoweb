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
    <button class="linkbtn">Mandami di nuovo l'email</button>
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
    <?php if (empty($avvisoLetto)): /* 6M · una volta sola per account: «Ho capito» lo chiude per sempre */ ?>
      <section class="privacy-nota privacy-nota--prima" aria-labelledby="chi-vede">
        <?= Icon::svg('lock', 18) ?>
        <div class="stack" style="gap:8px">
          <h2 id="chi-vede" style="font-size:18px;line-height:24px;margin:0">Chi vede la tua guida</h2>
          <p style="margin:0">Solo chi ha il link o inquadra il QR: la guida non compare su Google né negli assistenti AI. Scrivi tutto quello che serve all'ospite.
            I codici di porte, cassette delle chiavi, casseforti e allarmi ti sconsigliamo di scriverli: comunicali tu, di persona o in privato.</p>
          <form method="post" action="<?= b() ?>/pannello/avviso-guida" style="margin:0"><?= Csrf::field() ?>
            <input type="hidden" name="torna" value="<?= Support::e('/pannello/' . $pid . '/procedura/struttura') ?>">
            <button class="btn btn--sm">Ho capito</button></form>
        </div>
      </section>
    <?php endif; ?>
    <?php $dopoPasso = 'arrivo'; include __DIR__ . '/_struttura_form.php'; ?>
<?php break;

case 'arrivo': $dati = json_decode((string) $core['data'], true) ?: []; ?>
      <h1>Check-in &amp; Check-out.</h1>
      <p class="lead">Come si entra, cosa serve all'arrivo e cosa fare prima di partire. Spiega dove sono le chiavi e come si apre: i codici di porte
        e cassette è meglio non scriverli qui: comunicali all'ospite in privato.</p>
      <?php include __DIR__ . '/_privacy_nota.php'; ?>
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
      <p class="lead">Colori, logo e foto di copertina. L'anteprima accanto cambia mentre scegli.</p>
    </div>
    <?php $dopoPasso = 'pubblica'; include __DIR__ . '/_aspetto_form.php'; ?>
<?php break;

case 'pubblica': $quantita = (int) ($acc['intended_quantity'] ?? 1); ?>
      <h1><?= $prop['status'] === 'published' && $online ? 'Pubblica le modifiche.' : 'Pubblica la guida.' ?></h1>
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
      <?php elseif ($piano): $qSc = Plans::perProperty($piano) ? $quantita : 1;
            /* 9A · Il conto del primo anno: sconti di sistema sommati (invito, giudizio) o il codice scritto a mano. */
            $riep = MHW\Sconti::disponibili() ? MHW\Sconti::riepilogo($piano, $qSc, $acc) : null; $sc = $riep && $riep['sconto'] > 0 ? $riep : null;
            $om = $riep['omaggio'] ?? null; $dataOm = $om ? Support::date(gmdate('Y-m-d\TH:i:s\Z', $om['fine'])) : ''; ?>
        <div class="spread spread--mid">
          <div class="stack" style="gap:4px"><span class="kicker">Il tuo piano</span>
            <b style="font-size:22px;font-weight:500">Hai scelto <?= Support::e($piano['name']) ?><?= Plans::perProperty($piano) ? ' · ' . $quantita . ' strutture' : '' ?></b></div>
          <span style="font-size:26px;font-weight:500"><?php if ($om): /* 9D · con il codice omaggio oggi si paga 0 € */ ?>0&nbsp;€<span class="small muted"> oggi</span>
            <?php elseif ($sc): ?><s class="muted" style="font-size:18px"><?= Support::e(Support::money($sc['prezzo'], $piano['currency'])) ?></s>
            <?= Support::e(Support::money($sc['totale'], $piano['currency'])) ?><span class="small muted"> + IVA il primo anno</span>
            <?php else: ?><?= Support::e(Support::money(Plans::price($piano, $qSc), $piano['currency'])) ?>
            <span class="small muted">+ IVA / anno</span><?php endif; ?></span>
        </div>
        <?php if ($sc && !$om): ?><p class="plan__sconto-riga" style="margin:0">−<?= $sc['percento'] > 0 ? (int) $sc['percento'] . '%' : Support::e(Support::money($sc['sconto'], $piano['currency'])) ?> primo anno</p><?php endif; ?>
        <?php if ($om): ?>
          <div class="spiega omaggio-spiega" role="status"><b>Oggi paghi 0 €.</b> I tuoi primi <b><?= (int) $om['mesi'] ?> mesi sono gratis</b>: il primo addebito di <b><?= Support::e(Support::money($riep['totale'], $piano['currency'])) ?> + IVA</b> sarà il <b><?= Support::e($dataOm) ?></b>.
            Prima ti avvisiamo due volte, per email e nel pannello, 30 e 7 giorni prima. Puoi disattivare il rinnovo quando vuoi da Account &amp; Fatturazione: fino al <?= Support::e($dataOm) ?> la guida resta online e non paghi nulla; senza disattivazione l'abbonamento parte da solo.</div>
        <?php endif; ?>
        <?php if ($riep): $pv = $piano; $quantita = $qSc; include __DIR__ . '/_riepilogo.php'; endif; ?>
        <?php $pv = $piano; $quantita = $qSc; $torna = '/pannello/' . $pid . '/procedura/pubblica'; $soloCampo = true; include __DIR__ . '/_sconto.php'; ?>
        <p class="small muted">Abbonamento annuale con rinnovo automatico, che puoi disattivare quando vuoi. Il pagamento avviene su Stripe;
          la guida va online appena Stripe conferma.</p>
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
                      array_map(fn($x) => '«' . $x['struttura']['name'] . '» ha ' . count($x['sezioni']) . ' sezioni attive: con ' . $o['name'] . ' ne restano ' . $cambia['maxSezioni'] . ', e prima di pubblicare scegli quali.', $cambia['sezioni']),
                      $cambia['daArchiviare'] ? [$o['name'] . ' comprende ' . $cambia['maxStrutture'] . ' struttur' . ($cambia['maxStrutture'] === 1 ? 'a' : 'e') . ': le altre restano in bozza.'] : []) : [];
                  $etichettaO = 'Passa a ' . $o['name'] . ' · ' . Support::money($prezzoO, $o['currency']) . ' + IVA / anno';
                  $modulo = '<form method="post" action="' . b() . '/piano" style="margin:0">' . Csrf::field() . '<input type="hidden" name="pv" value="' . (int) $o['pv_id'] . '">'
                          . (Plans::perProperty($o) ? '<input type="hidden" name="strutture" value="' . $qO . '">' : '') . '<input type="hidden" name="torna" value="' . Support::e($torna) . '">'; ?>
              <?php if ($perdite): ?>
                <details class="passa-piano__conferma">
                  <summary class="btn btn--ghost btn--sm"><?= Support::e($etichettaO) ?></summary>
                  <div class="stack" style="gap:8px;margin-top:10px">
                    <p class="small" style="margin:0"><b>Con <?= Support::e($o['name']) ?> la guida non mostrerà:</b></p>
                    <ul class="small" style="margin:0;padding-left:18px"><?php foreach ($perdite as $x): ?><li><?= Support::e($x) ?></li><?php endforeach; ?></ul>
                    <?= $modulo ?><button class="btn btn--sm">Conferma: passa a <?= Support::e($o['name']) ?></button></form>
                  </div>
                </details>
              <?php else: ?>
                <?= $modulo ?><button class="btn btn--ghost btn--sm"><?= Support::e($etichettaO) ?></button></form>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <?php if (!$verificato): ?>
          <div class="note" role="status"><span>Per attivare l'abbonamento conferma prima la tua email (<?= Support::e($user['email']) ?>).</span></div>
          <form method="post" action="<?= b() ?>/verifica/invia" style="margin:0"><?= Csrf::field() ?><button class="btn btn--ghost btn--sm">Mandami di nuovo l'email</button></form>
        <?php endif; ?>
        <?php if (!MHW\Fatturazione::completa($acc)): /* 7B: lo stesso modulo di Account & Fatturazione, qui, sopra «Conferma e paga». */ ?>
          <section class="stack fatt-pubblica" id="fatturazione" style="gap:12px" aria-labelledby="fatt-pubblica-titolo">
            <h2 id="fatt-pubblica-titolo" style="font-size:20px;margin:0">I dati per la fattura</h2>
            <p class="small muted" style="margin:0">Servono per la fattura o la ricevuta. Potrai cambiarli quando vuoi in Account &amp; Fatturazione.</p>
            <?php $torna = '/pannello/' . $pid . '/procedura/pubblica'; include __DIR__ . '/_fatturazione_form.php'; ?>
          </section>
        <?php endif; ?>
      <?php else: ?>
        <p>Scegli il piano con cui pubblicare. <a href="<?= b() ?>/piano">Vedi i piani</a></p>
      <?php endif; ?>

    </div>

    <?php /* 7C: le lingue con campi da tradurre (non ferma la pubblicazione) */
          $incomplete = array_filter(array_map(fn($per) => array_sum($per), MHW\Traduttore::mancanti($prop)));
          if ($incomplete): $nomiLI = MHW\Config::get('locales'); ?>
      <div class="note" role="status"><div class="stack" style="gap:4px"><b>Lingue da completare</b>
        <?php foreach ($incomplete as $lm => $quanti): ?>
          <span><a href="<?= b() ?>/pannello/<?= $pid ?>/lingue/<?= Support::e($lm) ?>"><?= Support::e($nomiLI[$lm] ?? $lm) ?></a>: <?= (int) $quanti ?> camp<?= $quanti === 1 ? 'o' : 'i' ?> da tradurre. Dove manca, l'ospite legge la lingua principale.</span>
        <?php endforeach; ?></div></div>
    <?php endif; ?>
    <?php /* Facoltativo: le lingue in più. Le traduzioni non fermano la pubblicazione:
             dove mancano, l'ospite legge la lingua principale. */
          $altreLingue = array_diff_key($tutte, [$prop['default_locale'] => 1]); if ($altreLingue): ?>
      <form method="post" action="<?= b() ?>/pannello/<?= $pid ?>/lingue" class="fieldset stack" style="gap:12px"><?= Csrf::field() ?>
        <span class="legend">Vuoi la guida anche in altre lingue? <span class="small muted" style="font-weight:400">Facoltativo</span></span>
        <p class="help">Puoi pubblicare anche solo in <?= Support::e($tutte[$prop['default_locale']] ?? $prop['default_locale']) ?>. Le traduzioni le scrivi
          da <a href="<?= b() ?>/pannello/<?= $pid ?>/lingue">Lingue</a>, quando vuoi: dove mancano, l'ospite legge la lingua principale.</p>
        <?php include __DIR__ . '/_lingue_scelta.php'; ?>
        <div><button class="btn btn--sm" name="dopo" value="pubblica">Salva le lingue</button></div>
      </form>
    <?php endif; ?>

    <?php if (!empty($codici)): /* 6M: codici di accesso, conferma a proprio rischio (vale solo per questa pubblicazione) */ ?>
      <section class="codici-conferma" id="codici" role="alertdialog" aria-labelledby="codici-titolo" aria-describedby="codici-testo" tabindex="-1">
        <h2 id="codici-titolo"><?= Icon::svg('lock', 20) ?>Nella guida ci sono codici di accesso</h2>
        <ul class="codici-conferma__campi">
          <?php foreach ($codici as $c): $dove = $c['lingua'] === $prop['default_locale'] ? '/pannello/' . $pid . '/sezioni/' . $c['sid'] : '/pannello/' . $pid . '/lingue/' . $c['lingua']; ?>
            <li><span><b><?= Support::e($c['sezione']) ?></b> · <?= Support::e($c['campo']) ?><?= $c['lingua'] !== $prop['default_locale'] ? ' <span class="small muted">(' . Support::e(strtoupper($c['lingua'])) . ')</span>' : '' ?></span>
              <a href="<?= b() . $dove ?>">Correggi</a></li>
          <?php endforeach; ?>
        </ul>
        <p id="codici-testo">Chi ha il link o il QR della guida può leggerli, anche dopo la partenza, e il link si può inoltrare. Ti consigliamo di toglierli e comunicarli
          all'ospite in privato. Se pubblichi lo stesso, lo fai a tuo rischio: MyHouse Welcome non risponde dell'uso di questi codici.</p>
        <form method="post" action="<?= b() ?>/pannello/<?= $pid ?>/pubblica" class="stack" style="gap:16px;margin:0" data-codici-conferma><?= Csrf::field() ?>
          <label class="check"><input type="checkbox" name="codici_ok" value="1" required data-codici-ok> <span>Ho capito, pubblico a mio rischio</span></label>
          <div class="row" style="gap:12px">
            <a class="btn" href="<?= b() ?>/pannello/<?= $pid ?>/<?= $codici[0]['lingua'] === $prop['default_locale'] ? 'sezioni/' . $codici[0]['sid'] : 'lingue/' . $codici[0]['lingua'] ?>">Torna a correggere</a>
            <button class="btn btn--ghost" data-codici-vai disabled>Pubblica lo stesso</button>
            <?php /* senza JavaScript il bottone resta attivo: la casella è obbligatoria e il server la controlla */ ?>
            <noscript><style>[data-codici-vai]{display:none}</style><button class="btn btn--ghost">Pubblica lo stesso</button></noscript>
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

    <p class="small muted pubblica-riservata"><?= Icon::svg('lock', 14) ?><span>Dopo la pubblicazione la guida è online per chi ha il link o il QR. Non compare nei motori di ricerca.</span></p>
    <form method="post" action="<?= b() ?>/pannello/<?= $pid ?>/pubblica" style="margin:0" data-base="<?= b() ?>"><?= Csrf::field() ?>
      <?php if (!$sub && $piano): /* 8J · chi acquista, prima del primo pagamento: impresa o privato (con la richiesta di iniziare subito) */
            $acqS = (array) ($_SESSION['acquisto'] ?? []); ?>
        <fieldset class="fieldset stack acquisto" id="acquisto" style="gap:10px;margin:0 0 16px">
          <legend class="legend">Chi acquista?</legend>
          <label class="check"><input type="radio" name="acquirente" value="impresa" required data-acquirente<?= ($acqS['acquirente'] ?? '') === 'impresa' ? ' checked' : '' ?>>
            <span>Acquisto per la mia attività ricettiva (impresa o professionista)</span></label>
          <label class="check"><input type="radio" name="acquirente" value="consumatore" required data-acquirente<?= ($acqS['acquirente'] ?? '') === 'consumatore' ? ' checked' : '' ?>>
            <span>Acquisto come privato</span></label>
          <div class="acquisto__privato stack" style="gap:6px" data-acquisto-privato>
            <label class="check" style="align-items:flex-start"><input type="checkbox" name="recesso_rinuncia" value="1" data-recesso style="margin-top:1px"<?= !empty($acqS['rinuncia']) ? ' checked' : '' ?>>
              <span>Chiedo che il servizio inizi subito e so che, pubblicata la guida, perdo il diritto di recesso per il periodo già goduto</span></label>
            <p class="help" style="margin:0">Solo per i privati, ed è obbligatoria per pubblicare subito. Come privato hai 14 giorni per recedere: <a href="<?= b() ?>/termini#recesso" target="_blank" rel="noopener">come funziona</a>.</p>
          </div>
        </fieldset>
        <?php /* 9B · «Ti piace MyHouse Welcome?»: una sola casella, mai pre-spuntata, solo con l'interruttore acceso e se non ha mai aderito
                 (o ha aderito al pagamento e non ha ancora pagato: la si può togliere). Lo spiegone sotto, sempre visibile. */
              $giudizioPuo = MHW\Giudizi::attivo() && (MHW\Giudizi::puoAderire($acc) || (($acc['giudizio_adesione'] ?? '') === 'pagamento' && ($acc['giudizio_sconto_stato'] ?? '') === 'promesso'));
              if ($giudizioPuo): $giudizioSi = ($acc['giudizio_adesione'] ?? '') === 'pagamento'; ?>
          <fieldset class="fieldset stack giudizio-sconto" id="giudizio-sconto" style="gap:8px;margin:0 0 16px" data-giudizio-sconto data-torna="/pannello/<?= $pid ?>/procedura/pubblica">
            <legend class="legend">Ti piace MyHouse Welcome? <span class="badge badge--pine">−<?= MHW\Giudizi::percentuale() ?>%</span></legend>
            <input type="hidden" name="giudizio_casella" value="1">
            <label class="check" style="align-items:flex-start"><input type="checkbox" name="giudizio_adesione" value="1" data-giudizio-casella style="margin-top:1px"<?= $giudizioSi ? ' checked' : '' ?>>
              <span><b>Sì, voglio il <?= MHW\Giudizi::percentuale() ?>% in più: lascerò un giudizio.</b> <?= Support::e(preg_replace('/^Sì, voglio il \d+% in più: lascerò un giudizio\. /', '', MHW\Giudizi::casella())) ?></span></label>
            <p class="help giudizio-sconto__spiega" style="margin:0"><?= Support::e(trim(MHW\Giudizi::spiegaQuando() . ' ' . MHW\Giudizi::spiegone())) ?> Regole complete nei <a href="<?= b() ?>/termini#giudizio-sconto" target="_blank" rel="noopener">Termini</a>, punto «Giudizio con sconto».</p>
            <noscript><button class="btn btn--ghost btn--sm" formaction="<?= b() ?>/giudizio/adesione" formmethod="post" name="torna" value="/pannello/<?= $pid ?>/procedura/pubblica">Aggiorna il riepilogo</button></noscript>
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
            $etichettaB = $sub ? 'Pubblica ora' : ($omB ? 'Inserisci la carta e pubblica <small class="btn__sotto">0 € oggi · ' . Support::e(Support::money($omB['totale'], $piano['currency'])) . ' + IVA il ' . Support::e(Support::date(gmdate('Y-m-d\TH:i:s\Z', $omB['omaggio']['fine']))) . '</small>' : 'Conferma e paga');
            $barraAvanti = '<button class="btn btn--go' . ($omB ? ' btn--due-righe' : '') . '" ' . ($problemi || (!$sub && (!$verificato || !$piano || !MHW\Fatturazione::completa($acc))) ? 'disabled' : '') . '>'
                         . $etichettaB . ' <span class="go">' . Icon::svg('arrow', 18, 2) . '</span></button>';
            include __DIR__ . '/_barra_passo.php'; ?>
    </form>
<?php break;
endswitch; ?>

  </div>
  <?php include __DIR__ . '/_telefono.php'; ?>
</div>
