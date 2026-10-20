<?php use function MHW\t, MHW\tf;
/* Invita un amico: il riquadro con la barra, il link e il codice, l'invito per email, come funziona,
   gli amici invitati (nome breve e stato, niente altro) e le regole in breve.
   Riceve $acc, $puo, $inv, $entrati, $perEmail, $codice, $sub, $pubblica, $messaggio, $anteprima.
   7D: in alto «Come funziona», poi il messaggio pronto (modificabile) con Copia, WhatsApp ed Email,
   la casella dell'anteprima, la barra e «Chi è entrato» con quanto hai risparmiato.
   Chi non ha ancora un abbonamento attivo ($puo falso) vede come funziona e il bottone verso «Pubblica». */
use function MHW\b;
use MHW\{Support, Inviti, Icon};
$title = t('Invita un amico');
/* 9N: il traguardo è «al 55% degli inviti», non «metà prezzo» (accanto al 55% confondeva). Frasi intere, nella lingua del pannello. */
$nI = ['{amico}' => Inviti::AMICO, '{passo}' => Inviti::PASSO, '{max}' => Inviti::MASSIMO, '{amici}' => Inviti::amiciMassimi(), '{n}' => Inviti::EMAIL_PER_INVIO]; ?>
<div class="stack stack--lg" style="max-width:980px">
  <div class="stack stack--sm">
    <h1><?= t('Invita un amico.') ?></h1>
    <p class="lead" style="max-width:680px"><?= Support::e(tf('Conosci un altro host? Mandagli il tuo link. Lui ha il {amico}% di sconto sul primo anno, tu il {passo}% in meno sul prossimo rinnovo per ogni amico che pubblica, fino al {max}% degli inviti.', $nI)) ?></p>
  </div>

  <section class="stack" style="gap:12px" aria-labelledby="inviti-come">
    <h2 id="inviti-come" style="font-size:24px"><?= t('Come funziona') ?></h2>
    <ol class="invito__passi" role="list">
      <li class="invito__passo"><i aria-hidden="true">1</i><span><?= t('Mandi il messaggio su WhatsApp (o per email) con il tuo link.') ?></span></li>
      <li class="invito__passo"><i aria-hidden="true">2</i><span><?= Support::e(tf('Il tuo amico apre il link: vede la tua guida in anteprima e lo sconto del {amico}% è già inserito.', $nI)) ?></span></li>
      <li class="invito__passo"><i aria-hidden="true">3</i><span><?= Support::e(tf('Quando pubblica la sua guida, il tuo prossimo rinnovo scende del {passo}%, fino al {max}%.', $nI)) ?></span></li>
    </ol>
    <?php $rCome = b() . '/domande#{id}'; $rEtichetta = t('Come funziona'); $rT = fn($k, $s) => MHW\TestiSito::formatta(MHW\TestiSito::get($k, MHW\Pannello::en() ? 'en' : null), $s); include __DIR__ . '/../pub/_risparmio.php'; /* 9L · fino al 55% con il programma «Giudizio con sconto» */ ?>
  </section>

  <?php if (!$puo): ?>
    <section class="panel stack" style="gap:12px" aria-labelledby="inviti-quando">
      <h2 id="inviti-quando" style="font-size:22px;margin:0"><?= t('Il tuo link arriva quando pubblichi la tua guida.') ?></h2>
      <p style="margin:0"><?= t('Con la guida online trovi qui il messaggio pronto da mandare, il tuo link e il tuo codice. Puoi invitare anche per email, direttamente da questa pagina.') ?></p>
      <div class="row"><a class="btn" href="<?= b() . Support::e($pubblica) ?>"><?= Icon::svg('globe', 16) ?><?= t('Pubblica la tua guida') ?></a></div>
    </section>
  <?php else: ?>

  <section class="panel stack invito-messaggio" aria-labelledby="inv-msg-titolo">
    <h2 id="inv-msg-titolo" style="font-size:22px;margin:0"><?= t('Il messaggio pronto') ?></h2>
    <div class="field" style="margin:0">
      <label for="inv-messaggio"><?= t('Il testo da mandare') ?> <span class="muted"><?= t('(puoi cambiarlo)') ?></span></label>
      <textarea id="inv-messaggio" rows="5" data-benvenuto data-invito-msg aria-describedby="inv-msg-aiuto"><?= Support::e($messaggio) ?></textarea>
      <p class="help" id="inv-msg-aiuto"><?= t('Su WhatsApp il link mostra l\'anteprima con la foto di copertina e il nome della tua struttura. Il codice è scritto nel messaggio e si inserisce da solo quando il tuo amico si registra dal link.') ?></p>
    </div>
    <div class="row" style="gap:8px">
      <button type="button" class="btn btn--sm" data-copia-da="inv-messaggio" data-copiato="<?= t('Messaggio copiato') ?>" hidden><?= Icon::svg('copy', 15) ?><?= t('Copia') ?></button>
      <a class="btn btn--ghost btn--sm" href="https://wa.me/?text=<?= rawurlencode($messaggio) ?>" target="_blank" rel="noopener" data-wa-da="inv-messaggio"><?= Icon::svg('whatsapp', 15) ?><?= t('Manda su WhatsApp') ?></a>
      <a class="btn btn--ghost btn--sm" href="mailto:?subject=<?= rawurlencode(t('Ti invito su MyHouse Welcome')) ?>&amp;body=<?= rawurlencode($messaggio) ?>" data-mail-da="inv-messaggio"><?= Icon::svg('message', 15) ?>Email</a>
    </div>
    <form method="post" action="<?= b() ?>/inviti/anteprima" class="invito-anteprima"><?= MHW\Csrf::field() ?>
      <input type="hidden" name="mostra" value="0">
      <label class="check"><input type="checkbox" name="mostra" value="1" <?= $anteprima ? 'checked' : '' ?> data-invia-subito>
        <span><?= t('Mostra la copertina e il nome della mia struttura nell\'anteprima dell\'invito') ?></span></label>
      <noscript><button class="btn btn--ghost btn--sm"><?= t('Salva') ?></button></noscript>
      <p class="help" style="margin:0"><?= t('Spenta, chi apre il tuo link vede un\'anteprima generica di MyHouse Welcome. Indirizzo, Wi-Fi e contatti non si vedono mai.') ?></p>
    </form>
  </section>

  <?php $paginaInviti = true; include __DIR__ . '/_invita.php'; ?>

  <section class="stack" style="gap:10px" aria-labelledby="inviti-elenco">
    <h2 id="inviti-elenco" style="font-size:24px"><?= t('Chi è entrato') ?></h2>
    <?php if ((int) $inv['sconto'] > 0): ?>
      <p class="invito-risparmio"><?= Icon::svg('check', 17, 2.2) ?><?= t('Finora hai risparmiato') ?> <b><?= Support::e(Support::money((int) $inv['sconto'], $inv['valuta'])) ?></b> <?= t('sul prossimo rinnovo.') ?></p>
    <?php endif; ?>
    <?php if (!$entrati): ?>
      <p class="muted"><?= Support::e(tf('Ancora nessuno. Il primo amico che pubblica vale il {passo}% in meno sul tuo rinnovo.', $nI)) ?></p>
    <?php else: ?>
      <ul class="invito-entrati" role="list">
      <?php foreach ($entrati as $am): ?>
        <li class="rowcard">
          <span class="grow stack" style="gap:2px"><b><?= Support::e($am['nome']) ?></b>
            <?php if ($am['struttura'] !== ''): ?><span class="small muted"><?= Support::e($am['struttura']) ?></span><?php endif; ?></span>
          <span class="small muted"><?= Support::e(MHW\Pannello::data($am['data'])) ?></span>
          <span class="badge badge--<?= $am['pubblicato'] ? 'pine' : 'ochre' ?>"><?= Support::e($am['stato']) ?></span>
          <?php if ($am['risparmio'] > 0): ?>
            <span class="small invito-entrati__euro"><?= t('Ti ha fatto risparmiare') ?> <b><?= Support::e(Support::money((int) $am['risparmio'], $inv['valuta'])) ?></b></span>
          <?php elseif ($am['status'] === 'oltre'): ?><span class="small muted"><?= t('Oltre il massimo') ?></span>
          <?php elseif ($am['status'] === 'usato'): ?><span class="small muted"><?= t('Già scontato a un rinnovo') ?></span><?php endif; ?>
        </li>
      <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <?php $bozza = $_SESSION['invito_email'] ?? null; unset($_SESSION['invito_email']); ?>
  <section class="panel stack invito-email" id="per-email" aria-labelledby="per-email-titolo">
    <h2 id="per-email-titolo" style="font-size:22px;margin:0"><?= t('Invita per email') ?></h2>
    <p class="small muted" style="margin:0"><?= t('Scrivi gli indirizzi dei tuoi amici: mandiamo noi l\'invito, a nome tuo, con il tuo link e il tuo codice. Non conserviamo gli indirizzi.') ?></p>
    <form method="post" action="<?= b() ?>/inviti/email" class="stack" style="gap:14px"><?= MHW\Csrf::field() ?>
      <div class="field" style="margin:0">
        <label for="inv-email"><?= t('Email dei tuoi amici') ?></label>
        <p class="help" id="inv-email-aiuto" style="margin:0 0 6px"><?= Support::e(tf('Fino a {n}, separati da una virgola o uno per riga.', $nI)) ?></p>
        <textarea id="inv-email" name="email" rows="3" required aria-describedby="inv-email-aiuto" autocomplete="off" spellcheck="false" inputmode="email"
                  placeholder="mario@esempio.it, giulia@esempio.it"><?= Support::e((string) ($bozza['email'] ?? '')) ?></textarea>
      </div>
      <div class="field" style="margin:0">
        <label for="inv-msg"><?= t('Un messaggio') ?> <span class="muted"><?= t('(facoltativo)') ?></span></label>
        <?php /* 9F · non parte vuoto: il testo di partenza (Testi e foto), modificabile */ ?>
        <textarea id="inv-msg" name="messaggio" rows="4" maxlength="400"><?= Support::e((string) ($bozza['messaggio'] ?? Inviti::testoPartenza())) ?></textarea>
      </div>
      <div class="actions"><button class="btn"><?= Icon::svg('message', 16) ?><?= t('Manda l\'invito') ?></button></div>
    </form>
    <?php if ($perEmail): ?>
      <div class="stack" style="gap:8px">
        <b class="small"><?= t('Inviti mandati per email') ?></b>
        <?php foreach ($perEmail as $pe):
              [$tono, $et] = match ($pe['stato']) {
                  'valido', 'usato', 'oltre' => ['pine', t('Ha pubblicato')],
                  'registrato' => ['ochre', t('Si è registrato')],
                  'annullato' => ['paper', t('Non vale')],
                  default => ['paper', t('Invitato')],
              }; ?>
          <div class="rowcard" style="flex-wrap:wrap"><b class="grow"><?= Support::e($pe['email_mask']) ?></b>
            <span class="small muted"><?= Support::e(tf('Il {data}', ['{data}' => MHW\Pannello::data($pe['sent_at'])])) ?></span>
            <span class="badge badge--<?= $tono ?>"><?= Support::e($et) ?></span></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <section class="invito__regole" aria-labelledby="inviti-regole">
    <b id="inviti-regole"><?= t('Le regole, in breve') ?></b>
    <ul>
      <li><?= t('Un invito conta quando il tuo amico paga il suo primo abbonamento.') ?></li>
      <li><?= t('Lo sconto vale sul tuo prossimo rinnovo, poi il conto riparte da zero. Con un abbonamento attivato dal nostro staff lo applichiamo noi al rinnovo.') ?></li>
      <li><?= Support::e(tf('Il massimo è il {max}%: oltre {amici} amici, ognuno ha comunque il suo {amico}%.', $nI)) ?></li>
      <li><?= t('Non contano account tuoi o con i tuoi stessi dati di fatturazione.') ?></li>
      <li><?= t('Per il tuo amico l\'invito non si somma ad altri codici sconto.') ?></li>
    </ul>
    <a class="small" href="<?= b() ?>/termini#inviti"><?= t('Leggi il regolamento completo') ?></a>
  </section>
</div>
