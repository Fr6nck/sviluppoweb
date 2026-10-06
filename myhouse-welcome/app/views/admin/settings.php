<?php
/* Amministrazione → Impostazioni: Stripe, posta e archivio delle foto.
   I segreti non si rimostrano mai; i campi impostati da variabili d'ambiente si vedono e basta. */
use function MHW\b;
use MHW\{Support, Csrf, Icon, Impostazioni, Stripe, Config};
$title = 'Impostazioni';
$pronti = [
    'stripe'   => Stripe::enabled(),
    'posta'    => Config::get('mail')['transport'] !== 'log',
    'archivio' => Config::get('storage')['driver'] === 's3',
];
$icone = ['stripe' => 'card', 'posta' => 'message', 'archivio' => 'layers'];
$intro = [
    'stripe'   => 'Senza chiave segreta e segreto del webhook nessuno può pubblicare: i clienti preparano la guida ma non possono pagarla.',
    'posta'    => 'Verifica dell\'email, recupero della password e promemoria. Finché resta «Non spedire», le email finiscono in storage/logs/mail.log.',
    'archivio' => 'Le foto e i PDF caricati dai clienti. Sul disco del server vanno bene per provare; in produzione meglio Amazon S3. Le foto già caricate restano dove sono e si vedono lo stesso.',
]; ?>
<div class="stack stack--lg" style="max-width:860px">
  <div class="saluto" style="margin-bottom:0"><div><h1>Impostazioni.</h1>
    <p>Pagamenti, posta e archivio delle foto. I valori si salvano solo su questo server, nel file <code>app/config.local.php</code>: non finiscono nel codice né nel pacchetto. Per salvare serve la tua password.</p></div></div>

  <?php if (!$scrivibile): ?>
    <p class="note note--err" role="alert">La cartella <code>app/</code> non è scrivibile dal sito: puoi vedere le impostazioni ma non salvarle. Dai i permessi di scrittura alla cartella (o al file <code>config.local.php</code>), oppure scrivi il file a mano partendo da <code>config.local.esempio.php</code>.</p>
  <?php endif; ?>

  <?php foreach (Impostazioni::GRUPPI as $g => $campi):
        $err = $gruppo === $g ? $errori : [];
        $idErr = fn(string $n) => 'imp-' . $n . '-err'; ?>
  <section class="panel stack impostazioni" id="<?= $g ?>" aria-labelledby="imp-<?= $g ?>-titolo">
    <div class="impostazioni__testa">
      <span class="impostazioni__ico"><?= Icon::svg($icone[$g], 20) ?></span>
      <h2 id="imp-<?= $g ?>-titolo"><?= Impostazioni::TITOLI[$g] ?></h2>
      <?php if ($pronti[$g]): ?><span class="badge badge--pine">Configurato</span><?php else: ?><span class="badge badge--alert">Da configurare</span><?php endif; ?>
      <?php if ($g === 'stripe' && ($modo = Impostazioni::modoStripe()) !== ''): ?>
        <span class="badge badge--<?= $modo === 'live' ? 'sea' : 'ochre' ?>"><?= $modo === 'live' ? 'Modalità reale' : 'Modalità di prova' ?></span>
      <?php endif; ?>
    </div>
    <p class="small muted"><?= Support::e($intro[$g]) ?></p>

    <?php if ($g === 'stripe'): ?>
      <div class="impostazioni__webhook">
        <p class="small"><b>Indirizzo del webhook</b> da inserire in Stripe → Sviluppatori → Webhook → «Aggiungi endpoint»:</p>
        <div class="row" style="gap:8px;flex-wrap:wrap;align-items:center"><code class="impostazioni__url"><?= Support::e($webhook) ?></code>
          <button type="button" class="btn btn--ghost btn--sm" data-copia="<?= Support::e($webhook) ?>" data-copiato="Copiato" hidden><?= Icon::svg('copy', 15) ?>Copia</button></div>
        <p class="small muted">Eventi da selezionare: checkout.session.completed, checkout.session.expired, customer.subscription.created, customer.subscription.updated, customer.subscription.deleted, invoice.paid, invoice.payment_succeeded, invoice.payment_failed. Il segreto di firma che Stripe ti mostra dopo va qui sotto.</p>
      </div>
    <?php endif; ?>

    <?php if (isset($err['_'])): ?><p class="note note--err" role="alert"><?= Support::e($err['_']) ?></p><?php endif; ?>
    <?php if ($err && !isset($err['_'])): ?><p class="note note--err" role="alert">Non ho salvato niente: correggi i campi segnati qui sotto.</p><?php endif; ?>

    <form method="post" action="<?= b() ?>/admin/impostazioni/<?= $g ?>" class="stack" style="gap:16px" autocomplete="off" novalidate><?= Csrf::field() ?>
      <div class="impostazioni__campi">
      <?php foreach ($campi as $percorso => $c):
            [$env, $etichetta, $tipo] = $c; $aiuto = $c[3] ?? ''; $opzioni = $c[4] ?? [];
            $n = Impostazioni::nome($percorso); $id = 'imp-' . $n;
            $origine = Impostazioni::origine($percorso, $locale);
            $attuale = Impostazioni::valore($percorso);
            $valore = array_key_exists($n, $valori) && $gruppo === $g ? $valori[$n] : (is_bool($attuale) ? ($attuale ? '1' : '') : (string) $attuale);
            $bloccato = $origine === 'env';
            $e = $err[$n] ?? '';
            $descr = trim(($aiuto !== '' ? $id . '-aiuto ' : '') . ($e !== '' ? $idErr($n) : ''));
            $aria = ($descr !== '' ? ' aria-describedby="' . $descr . '"' : '') . ($e !== '' ? ' aria-invalid="true"' : '');
            $largo = in_array($tipo, ['check'], true) || in_array($percorso, ['mail.transport', 'storage.driver', 'stripe.secret_key', 'stripe.webhook_secret', 'storage.s3.public_base_url', 'storage.s3.secret'], true); ?>
        <div class="field<?= $largo ? ' impostazioni__largo' : '' ?>" style="margin:0">
          <?php if ($tipo === 'check'): ?>
            <label class="scelta scelta--mini"><input type="checkbox" id="<?= $id ?>" name="<?= $n ?>" value="1" <?= $valore !== '' ? 'checked' : '' ?><?= $bloccato ? ' disabled' : '' ?><?= $aria ?>><span><?= Support::e($etichetta) ?></span></label>
          <?php else: ?>
            <label for="<?= $id ?>"><?= Support::e($etichetta) ?></label>
            <?php if ($tipo === 'choice'): ?>
              <select id="<?= $id ?>" name="<?= $n ?>"<?= $bloccato ? ' disabled' : '' ?><?= $aria ?>>
                <?php foreach ($opzioni as $k => $et): ?><option value="<?= Support::e($k) ?>" <?= (string) $valore === (string) $k ? 'selected' : '' ?>><?= Support::e($et) ?></option><?php endforeach; ?>
              </select>
            <?php elseif ($tipo === 'secret'): ?>
              <input type="password" id="<?= $id ?>" name="<?= $n ?>" autocomplete="new-password" spellcheck="false" data-lpignore="true" data-1p-ignore
                     placeholder="<?= $bloccato ? 'Impostata sul server' : ($attuale !== '' && $attuale !== null ? 'Lascia vuoto per non cambiarla' : '') ?>"<?= $bloccato ? ' disabled' : '' ?><?= $aria ?>>
            <?php else: ?>
              <input type="<?= ['email' => 'email', 'url' => 'url', 'number' => 'number'][$tipo] ?? 'text' ?>" id="<?= $id ?>" name="<?= $n ?>" value="<?= Support::e($valore) ?>" spellcheck="false" autocomplete="off" data-lpignore="true" data-1p-ignore
                     <?= $tipo === 'number' ? 'min="1" max="65535" inputmode="numeric"' : '' ?><?= $bloccato ? ' disabled' : '' ?><?= $aria ?>>
            <?php endif; ?>
          <?php endif; ?>
          <?php if ($bloccato): ?>
            <span class="small muted">Impostata sul server con la variabile d'ambiente <code><?= $env ?></code>: si cambia lì.</span>
          <?php elseif ($tipo === 'secret' && (string) $attuale !== ''): ?>
            <span class="small muted impostazioni__segreto">Impostata: <code><?= Support::e(Impostazioni::mascherato((string) $attuale)) ?></code>
              <label class="scelta scelta--mini"><input type="checkbox" name="togli[<?= $n ?>]" value="1"><span>Togli</span></label></span>
          <?php endif; ?>
          <?php if ($aiuto !== ''): ?><span class="help" id="<?= $id ?>-aiuto"><?= Support::e($aiuto) ?></span><?php endif; ?>
          <?php if ($e !== ''): ?><span class="field-error" id="<?= $idErr($n) ?>"><?= Support::e($e) ?></span><?php endif; ?>
        </div>
      <?php endforeach; ?>
      </div>
      <div class="impostazioni__conferma">
        <div class="field" style="margin:0">
          <label for="imp-<?= $g ?>-password">La tua password, per confermare</label>
          <input type="password" id="imp-<?= $g ?>-password" name="password" autocomplete="current-password" required
                 <?= isset($err['password']) ? 'aria-invalid="true" aria-describedby="imp-' . $g . '-password-err"' : '' ?>>
          <?php if (isset($err['password'])): ?><span class="field-error" id="imp-<?= $g ?>-password-err"><?= Support::e($err['password']) ?></span><?php endif; ?>
        </div>
        <button class="btn"<?= $scrivibile ? '' : ' disabled' ?>>Salva</button>
      </div>
    </form>
    <form method="post" action="<?= b() ?>/admin/impostazioni/<?= $g ?>/prova" class="impostazioni__prova"><?= Csrf::field() ?>
      <button class="btn btn--ghost btn--sm"><?= Icon::svg('pulse', 15) ?>Prova la connessione</button>
      <span class="small muted"><?= $g === 'posta' ? 'Manda un\'email di prova a ' . Support::e($emailAdmin) . '.' : ($g === 'stripe' ? 'Chiede a Stripe se la chiave è valida.' : 'Scrive, legge e cancella un piccolo file nel bucket.') ?></span>
    </form>
  </section>
  <?php endforeach; ?>
</div>
