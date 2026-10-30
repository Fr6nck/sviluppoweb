<?php
/* Amministrazione → Impostazioni: Stripe, posta e archivio di foto e PDF.
   I segreti non si rimostrano mai; i campi impostati da variabili d'ambiente si vedono e basta. */
use function MHW\b;
use MHW\{Support, Csrf, Icon, Impostazioni, Stripe, Config, Traduttore};
$title = 'Impostazioni';
$pronti = [
    'sito'     => (string) Config::get('base_url') !== '' && Config::get('pretty_urls') === true,
    'indirizzi' => MHW\Support::contatto() !== '',
    'stripe'   => Stripe::enabled(),
    'posta'    => Config::get('mail')['transport'] !== 'log',
    'archivio' => Config::get('storage')['driver'] === 's3',
    'traduzioni' => Traduttore::configurato(),
    'inviti' => MHW\Inviti::disponibili(),
    'giudizi' => MHW\Giudizi::disponibili(),
    'portali' => true,   // hanno sempre gli indirizzi di serie
    'registri' => MHW\Registri::s3Configurato(),
    'legale' => (string) (Config::get('legal')['company'] ?? '') !== '' && (string) (Config::get('legal')['company_vat'] ?? '') !== '',
    'assistente' => MHW\Assistente::configurato(),
];
$icone = ['sito' => 'globe', 'indirizzi' => 'message', 'stripe' => 'card', 'posta' => 'message', 'archivio' => 'layers', 'traduzioni' => 'globe', 'portali' => 'star', 'registri' => 'doc', 'legale' => 'doc', 'inviti' => 'people', 'giudizi' => 'star', 'assistente' => 'message'];
$intro = [
    'sito'     => 'L\'indirizzo pubblico del sito e gli indirizzi senza index.php: valgono per link, email, QR, canonical e sitemap. «Prova» chiede al sito una sua pagina senza index.php: se risponde, il server legge .htaccess e gli indirizzi puliti si possono accendere.',
    'indirizzi' => 'Quattro indirizzi, ognuno al suo posto (9H). Tutte le email del sito partono dal mittente di Posta; qui decidi a chi arrivano le risposte e chi riceve gli avvisi. Commerciale, newsletter e amministrazione possono essere alias che inoltrano all\'assistenza.',
    'stripe'   => 'Senza chiave segreta e segreto del webhook nessuno può pubblicare: i clienti preparano la guida ma non possono pagarla.',
    'posta'    => 'Conferma dell\'email, recupero della password e promemoria. Finché resta «Non spedire», le email finiscono nei registri della posta (storage/logs/mail-AAAA-MM-GG.log, tenuti 7 giorni).',
    'archivio' => 'Le foto e i PDF caricati dai clienti. Sul disco del server vanno bene per provare; in produzione meglio Amazon S3. I file già caricati restano dove sono e si vedono lo stesso.',
    'inviti' => 'Ogni cliente ha un link e un codice da girare agli amici: l\'amico ha il ' . MHW\Inviti::AMICO . '% sul primo anno, chi invita il ' . MHW\Inviti::PASSO . '% in meno sul rinnovo per ogni amico che paga, fino al ' . MHW\Inviti::MASSIMO . '%.',
    'giudizi' => 'Chi aderisce, con una sola casella, si impegna a lasciare un voto con due righe dopo la pubblicazione e accetta che il giudizio possa comparire in home (nome, struttura, città, immagini scelte, nessun link): in cambio ha lo sconto, sul primo anno se aderisce al pagamento, sul rinnovo se aderisce dopo. Lo sconto non dipende dal voto. Quali giudizi vanno in home lo decidi tu in Clienti → Giudizi.',
    'portali' => 'I link che la guida mostra a chi dà 5 stelle, per i portali che l\'host ha spuntato (8B). Google cerca su Maps il nome e la città della struttura; gli altri aprono il sito del portale, dove la recensione si lascia dall\'app o dall\'email che mandano dopo il soggiorno.',
    'registri' => 'I registri tecnici, della posta di prova e degli accessi degli amministratori, in app/storage/logs (fuori dal web), con email, IP e telefoni abbreviati. Ogni giorno si tolgono quelli più vecchi e, se c\'è il bucket, la copia di quelli di ieri va su S3 (Milano), dove gli accessi degli amministratori non si possono cancellare per 6 mesi.',
    'legale' => 'Chi fornisce il servizio ed è titolare del trattamento: compare nel piè di pagina del sito e delle email, nei dati strutturati, nei Termini, nell\'Informativa e nel DPA. Le versioni sono la data da cui un testo vale: cambiale quando cambi i testi in Testi e foto → Documenti legali. Una data futura (almeno 30 giorni) manda subito l\'avviso ai clienti; a quella data, nel pannello, la finestra «Accetto».',
    'assistente' => 'Concierge AI (10, 9T): l\'assistente che risponde agli ospiti con il testo della guida, compreso in Plus e Portfolio. Le domande vanno all\'API di Anthropic (Claude) con la chiave scritta qui; il limite per struttura e il tetto di spesa fermano le richieste; prezzi e cambio servono solo per le stime di Amministrazione → Concierge AI.',
    'traduzioni' => 'Le traduzioni suggerite di Plus e Portfolio, con Amazon Translate. Prezzo, cambio e piano gratuito servono solo per le stime di Amministrazione → Traduzioni; i tetti fermano le richieste.',
]; ?>
<div class="stack stack--lg" style="max-width:860px">
  <div class="saluto" style="margin-bottom:0"><div><h1>Impostazioni.</h1>
    <p>Pagamenti, posta e archivio di foto e PDF. I valori si salvano solo su questo server, nel file <code>app/config.local.php</code>: non finiscono nel codice né nel pacchetto. Per salvare serve la tua password.</p></div></div>

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
        <p class="small muted">Eventi da selezionare: checkout.session.completed, checkout.session.expired, checkout.session.async_payment_succeeded, checkout.session.async_payment_failed, invoice.paid, invoice.payment_failed, customer.subscription.updated, customer.subscription.deleted, customer.subscription.trial_will_end, charge.refunded, charge.refund.updated (9O: i rimborsi fatti anche da Stripe o da Adamo). Il segreto di firma che Stripe ti mostra dopo va qui sotto.</p>
      </div>
    <?php endif; ?>

    <?php if ($g === 'registri'): $infoR = MHW\Registri::info(); $statoR = $infoR['stato']; ?>
      <div class="impostazioni__webhook registri-info">
        <p class="small"><b><?= $infoR['byte'] >= 1048576 ? Support::e(number_format($infoR['byte'] / 1048576, 1, ',', '.')) . ' MB' : Support::e(number_format(max(1, (int) ceil($infoR['byte'] / 1024)), 0, ',', '.')) . ' KB' ?></b> in <?= (int) $infoR['file'] ?> file<?= $infoR['vecchio'] ? ' · il più vecchio è del ' . Support::e(Support::date($infoR['vecchio'] . 'T00:00:00Z')) : '' ?>.
          Restano <?= (int) MHW\Registri::config()['giorni'] ?> giorni (la posta di prova <?= MHW\Registri::GIORNI_POSTA ?>).</p>
        <p class="small"><?= $statoR ? ($statoR['ok'] ? 'Ultima copia su S3: ' : 'Ultima copia su S3 NON riuscita: ') . Support::e(Support::date((string) $statoR['quando'])) . ' · ' . Support::e((string) $statoR['msg'])
                                     : (MHW\Registri::s3Configurato() ? 'Nessuna copia su S3 ancora: parte dopo mezzanotte, con il primo giro dei richiami.' : 'Nessun bucket: i registri restano solo sul server.') ?></p>
        <div class="row" style="gap:8px;flex-wrap:wrap;align-items:flex-end">
          <form method="post" action="<?= b() ?>/admin/registri/scarica"><?= Csrf::field() ?><button class="btn btn--ghost btn--sm"><?= Icon::svg('doc', 15) ?>Scarica gli ultimi 7 giorni</button></form>
          <form method="post" action="<?= b() ?>/admin/registri/svuota" class="row" style="gap:8px;align-items:flex-end"><?= Csrf::field() ?>
            <div class="field" style="margin:0"><label for="registri-conferma" class="small">Per svuotare, scrivi SVUOTA</label>
              <input id="registri-conferma" name="conferma" type="text" autocomplete="off" spellcheck="false" style="width:9em"></div>
            <button class="btn btn--ghost btn--sm"><?= Icon::svg('bin', 15) ?>Svuota i registri</button></form>
        </div>
        <p class="small muted">«Svuota» toglie i registri tecnici e della posta; il registro degli accessi degli amministratori resta.</p>
      </div>
    <?php endif; ?>

    <?php if (isset($err['_'])): ?><p class="note note--err" role="alert"><?= Support::e($err['_']) ?></p><?php endif; ?>
    <?php if ($err && !isset($err['_'])): ?><p class="note note--err" role="alert">Niente è stato salvato: correggi i campi segnati qui sotto.</p><?php endif; ?>

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
            $largo = in_array($tipo, ['check'], true) || in_array($percorso, ['mail.transport', 'storage.driver', 'stripe.secret_key', 'stripe.webhook_secret', 'storage.s3.public_base_url', 'storage.s3.secret', 'translate.secret', 'assistente.api_key'], true); ?>
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
              <input type="<?= ['email' => 'email', 'url' => 'url', 'number' => 'number', 'data' => 'date'][$tipo] ?? 'text' ?>" id="<?= $id ?>" name="<?= $n ?>" value="<?= Support::e($valore) ?>" spellcheck="false" autocomplete="off" data-lpignore="true" data-1p-ignore
                     <?= $tipo === 'number' ? 'min="1" max="65535" inputmode="numeric"' : ($tipo === 'percento' ? 'inputmode="numeric" maxlength="3"' : ($tipo === 'cifra' ? 'inputmode="numeric"' : ($tipo === 'decimale' ? 'inputmode="decimal"' : ''))) ?><?= $bloccato ? ' disabled' : '' ?><?= $aria ?>>
            <?php endif; ?>
          <?php endif; ?>
          <?php if ($bloccato): ?>
            <span class="small muted">Impostata sul server con la variabile d'ambiente <code><?= $env ?></code>: si cambia lì.</span>
          <?php elseif ($tipo === 'secret' && (string) $attuale !== ''): ?>
            <span class="small muted impostazioni__segreto">Impostata: <code><?= Support::e(Impostazioni::mascherato((string) $attuale)) ?></code>
              <label class="scelta scelta--mini"><input type="checkbox" name="togli[<?= $n ?>]" value="1"><span>Togli</span></label></span>
          <?php endif; ?>
          <?php if ($aiuto !== ''): ?><span class="help" id="<?= $id ?>-aiuto"><?= Support::e($aiuto) ?></span><?php endif; ?>
          <?php if ($percorso === 'storage.s3.public_base_url' && trim((string) $valore) !== ''): /* 6M: i file non passerebbero da /media/ */ ?>
            <span class="note note--err" role="status"><?= Support::e(MHW\Gestione::AVVISO_ARCHIVIO) ?></span>
          <?php endif; ?>
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
        <div class="row" style="gap:8px">
          <button class="btn"<?= $scrivibile ? '' : ' disabled' ?>>Salva</button>
          <button class="btn btn--ghost" name="con_dati" value="1" formaction="<?= b() ?>/admin/impostazioni/<?= $g ?>/prova"
                  title="Usa i dati scritti qui sopra, senza salvarli"><?= Icon::svg('pulse', 15) ?>Prova con questi dati</button>
        </div>
      </div>
    </form>
    <form method="post" action="<?= b() ?>/admin/impostazioni/<?= $g ?>/prova" class="impostazioni__prova"><?= Csrf::field() ?>
      <button class="btn btn--ghost btn--sm"><?= Icon::svg('pulse', 15) ?>Prova i dati salvati</button>
      <span class="small muted"><?= $g === 'posta' ? 'Manda un\'email di prova a ' . Support::e($emailAdmin) . '.' : ($g === 'stripe' ? 'Chiede a Stripe se la chiave è valida.' : ($g === 'assistente' ? 'Manda una domanda minima ad Anthropic con il modello scelto.' : ($g === 'traduzioni' ? 'Fa tradurre una parola: 9 caratteri.' : ($g === 'inviti' ? 'Dice se è acceso e quanti clienti possono invitare.' : ($g === 'portali' ? 'Mostra i link per una struttura d\'esempio.' : ($g === 'registri' ? 'Carica un file di prova nel bucket e controlla che la cancellazione sia rifiutata.' : ($g === 'legale' ? 'Mostra la riga del titolare, le versioni in vigore e quanti clienti devono ancora accettarle.' : ($g === 'giudizi' ? 'Dice se lo sconto è acceso, quanti hanno aderito e quanti giudizi sono in home.' : ($g === 'indirizzi' ? 'Mostra dove va ciascun indirizzo adesso.' : 'Scrive, legge e cancella un piccolo file nel bucket.'))))))))) ?></span>
    </form>
  </section>
  <?php endforeach; ?>
</div>
