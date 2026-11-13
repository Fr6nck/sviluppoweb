<?php
/**
 * L'account di chi è dentro: cambia la sua password. Il super admin vede
 * anche l'account del gestore e gli può dare una password nuova, se l'ha
 * dimenticata.
 *
 * Al gestore non si dice chi ha reimpostato la password: del super admin, per
 * lui, non c'è traccia.
 *
 * @var string     $utente
 * @var array      $mio         l'account di chi è dentro, senza l'hash
 * @var bool       $daCambiare  deve scegliere una password sua prima di fare altro
 * @var array|null $gestore     solo per il super admin: l'account del gestore ([] se non c'è)
 * @var list<array<string,mixed>> $dispositivi i dispositivi con le notifiche di questo account
 * @var bool       $pushPossibile se il server può spedire notifiche
 * @var array|null $ultimaNotifica l'ultima notifica che il sito ha provato a spedire
 */
use ArcoDelVento\Support\Csrf;

$mio = $mio ?? [];
$quando = static fn (array $a, string $k): string => !empty($a[$k]) ? dataOra((string) $a[$k]) : '—';
?>
<?php if (!empty($daCambiare)): ?>
  <div class="adm-avviso adm-avviso--attenzione" role="status">
    <?= icona('attenzione', 18, 'adm-avviso__icona') ?>
    <div>
      <p><strong>Scegli una password tua per continuare.</strong> La tua password è stata reimpostata
         il <?= e($quando($mio, 'reimpostata')) ?>: quella con cui sei entrato la conosce anche chi te l'ha data.
         Come password attuale scrivi quella.</p>
    </div>
  </div>
<?php endif; ?>

<p class="adm-intro">Sei entrato come <strong><?= e($utente) ?></strong>.
  Ultimo cambio della password: <?= e($quando($mio, 'modificato')) ?>.</p>

<?php if (is_array($gestore ?? null)): ?><div class="adm-due"><?php endif; ?>

<section class="adm-carta" aria-labelledby="a-mia">
  <div class="adm-carta__testa"><h2 id="a-mia">Cambia la password</h2></div>
  <form method="post" action="<?= e(adminUrl('account')) ?>" class="adm-modulo adm-modulo--stretto" novalidate>
    <?= Csrf::field() ?>
    <div class="adm-campo">
      <label for="attuale">Password attuale</label>
      <input type="password" id="attuale" name="attuale" required autocomplete="current-password">
    </div>
    <div class="adm-campo">
      <label for="nuova">Nuova password</label>
      <input type="password" id="nuova" name="nuova" required minlength="10" autocomplete="new-password" aria-describedby="nuova-aiuto">
      <p class="adm-aiuto" id="nuova-aiuto">Almeno 10 caratteri. Le altre sessioni aperte con la vecchia password si chiudono.</p>
    </div>
    <div class="adm-campo">
      <label for="ripeti">Ripeti la nuova password</label>
      <input type="password" id="ripeti" name="ripeti" required minlength="10" autocomplete="new-password">
    </div>
    <p><button type="submit" class="adm-bottone">Cambia la password</button></p>
  </form>
</section>

<?php if (is_array($gestore ?? null)): ?>
  <section class="adm-carta" id="gestore" aria-labelledby="a-gestore">
    <div class="adm-carta__testa"><h2 id="a-gestore">La password del gestore</h2></div>
    <?php if ($gestore === []): ?>
      <p>Il gestore non ha ancora un account: lo crea la prima volta su <code>/admin</code>, con il codice
         <code>ADMIN_SETUP_TOKEN</code> del file <code>.env</code>.</p>
    <?php else: ?>
      <dl class="adm-dati adm-dati--piatta">
        <div><dt>Account</dt><dd><?= e((string) $gestore['utente']) ?></dd></div>
        <div><dt>Ultimo accesso</dt><dd><?= e($quando($gestore, 'ultimo_accesso')) ?></dd></div>
        <div><dt>Password cambiata</dt><dd><?= e($quando($gestore, 'modificato')) ?><?= !empty($gestore['da_cambiare']) ? ' · reimpostata: al prossimo accesso ne sceglie una sua' : (!empty($gestore['reimpostata']) ? ' · reimpostata da qui' : '') ?></dd></div>
      </dl>
      <p>Se <?= e((string) $gestore['utente']) ?> ha dimenticato la password, gliene dai una nuova da qui. Le sue
         sessioni aperte si chiudono subito. La password la comunichi tu, a voce o al telefono: il sito non la
         manda a nessuno.</p>
      <form method="post" action="<?= e(adminUrl('account')) ?>" class="adm-modulo adm-modulo--stretto" novalidate>
        <?= Csrf::field() ?>
        <input type="hidden" name="azione" value="reimposta">
        <div class="adm-campo">
          <label for="nuova-gestore">Nuova password per <?= e((string) $gestore['utente']) ?></label>
          <input type="password" id="nuova-gestore" name="nuova_gestore" required minlength="10" autocomplete="new-password" aria-describedby="nuova-gestore-aiuto">
          <p class="adm-aiuto" id="nuova-gestore-aiuto">Almeno 10 caratteri.</p>
        </div>
        <div class="adm-campo">
          <label for="ripeti-gestore">Ripeti la nuova password</label>
          <input type="password" id="ripeti-gestore" name="ripeti_gestore" required minlength="10" autocomplete="new-password">
        </div>
        <div class="adm-campo adm-campo--spunta">
          <input type="checkbox" id="da-cambiare" name="da_cambiare" value="1" checked aria-describedby="da-cambiare-aiuto">
          <label for="da-cambiare">Al prossimo accesso deve sceglierne una sua</label>
          <p class="adm-aiuto" id="da-cambiare-aiuto">Così la password che conosci tu vale per un accesso solo.</p>
        </div>
        <div class="adm-campo">
          <label for="conferma-reset">La tua password, per conferma</label>
          <input type="password" id="conferma-reset" name="conferma" required autocomplete="current-password">
        </div>
        <p><button type="submit" class="adm-bottone">Reimposta la password di <?= e((string) $gestore['utente']) ?></button></p>
      </form>
    <?php endif; ?>
  </section>
  </div>
<?php endif; ?>

<p class="adm-nota">Dopo due ore senza attività si esce da soli; dopo dodici ore in tutto, si rientra comunque.</p>

<section class="adm-carta" id="notifiche" aria-labelledby="a-notifiche" tabindex="-1">
  <div class="adm-carta__testa"><h2 id="a-notifiche">Notifiche sul telefono</h2></div>
  <p class="adm-nota">Una notifica per ogni nuova richiesta di prenotazione, prenotazione pagata e messaggio dal sito, su ogni
     dispositivo dove le attivi, anche con l'area riservata chiusa. Toccandola si apre la richiesta. Arrivano a tutti e due
     gli account, ognuno sui suoi dispositivi.</p>
  <?php if (empty($pushPossibile)): ?>
    <p class="adm-avviso adm-avviso--attenzione">Questo server non può spedire notifiche: servono le estensioni OpenSSL e cURL di PHP.</p>
  <?php else: ?>
    <?php /* Lo stato di questo dispositivo lo sa solo il browser: lo scrive admin-app.js. */ ?>
    <div class="adm-notifiche-qui" data-notifiche-qui hidden>
      <p class="adm-notifiche-qui__stato"><?= icona('campanella', 18) ?><span data-notifiche-stato>Controllo questo dispositivo…</span></p>
      <p class="adm-notifiche-qui__azioni">
        <button type="button" class="adm-bottone" data-notifiche-attiva hidden>Attiva su questo dispositivo</button>
        <button type="button" class="adm-bottone adm-bottone--piatto" data-notifiche-spegni hidden>Disattiva su questo dispositivo</button>
      </p>
      <p class="adm-nota" data-notifiche-esito role="status"></p>
    </div>
    <noscript><p class="adm-nota">Per attivare le notifiche serve JavaScript.</p></noscript>

    <h3 class="adm-sottotitolo-carta">I tuoi dispositivi</h3>
    <?php if (empty($dispositivi)): ?>
      <p class="adm-tenue">Nessun dispositivo con le notifiche, per ora.</p>
    <?php else: ?>
      <ul class="adm-dispositivi">
        <?php foreach ($dispositivi as $d): ?>
          <li data-dispositivo="<?= e((string) $d['id']) ?>">
            <div>
              <strong><?= e((string) $d['dispositivo']) ?></strong>
              <span class="adm-tenue">· attivate il <?= e(dataOra((string) $d['creata'])) ?><?= !empty($d['usata']) ? ' · ultima notifica arrivata al servizio il ' . e(dataOra((string) $d['usata'])) : ' · nessuna notifica ancora' ?></span>
              <?php if (!empty($d['errore'])): ?>
                <p class="adm-dispositivi__errore" data-errore-dispositivo><?= icona('attenzione', 14) ?>Ultimo tentativo, il <?= e(dataOra((string) $d['tentata'])) ?>, non riuscito: <?= e((string) $d['errore']) ?></p>
              <?php endif; ?>
            </div>
            <form method="post" action="<?= e(adminUrl('notifiche/togli')) ?>">
              <?= Csrf::field() ?>
              <input type="hidden" name="id" value="<?= e((string) $d['id']) ?>">
              <button type="submit" class="adm-bottone adm-bottone--piatto">Togli<span class="adm-visually-hidden"> le notifiche da <?= e((string) $d['dispositivo']) ?></span></button>
            </form>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php if (!empty($ultimaNotifica)): $u = $ultimaNotifica; ?>
        <p class="adm-nota adm-notifiche-ultima" data-ultima-notifica>Ultima notifica del sito: «<?= e((string) $u['titolo']) ?>», il <?= e(dataOra((string) $u['quando'])) ?> —
          <?php if ((int) $u['dispositivi'] === 0): ?>nessun dispositivo aveva le notifiche attive.
          <?php else: ?>arrivata al servizio di push per <?= (int) $u['spedite'] ?> <?= (int) $u['spedite'] === 1 ? 'dispositivo' : 'dispositivi' ?> su <?= (int) $u['dispositivi'] ?>.<?php endif; ?></p>
      <?php endif; ?>
      <form method="post" action="<?= e(adminUrl('notifiche/prova')) ?>" class="adm-notifiche-prova">
        <?= Csrf::field() ?>
        <button type="submit" class="adm-bottone adm-bottone--piatto"><?= icona('campanella', 16) ?>Manda una notifica di prova</button>
      </form>
    <?php endif; ?>

    <details class="adm-notifiche-come">
      <summary>Se le notifiche non arrivano</summary>
      <div class="adm-notifiche-come__testo">
        <ol>
          <li>Il telefono deve comparire qui sopra, fra i tuoi dispositivi. Se non c'è, apri l'app dall'icona, vieni in questa pagina e tocca
              «Attiva su questo dispositivo».</li>
          <li>Tocca «Manda una notifica di prova». Se l'avviso dice che è partita ma sul telefono non arriva niente, il blocco è sul telefono:
            <ul>
              <li><strong>Android:</strong> Impostazioni → App → Arco del Vento → Notifiche: attive; e il risparmio energetico non deve
                  limitare l'app.</li>
              <li><strong>iPhone:</strong> Impostazioni → Notifiche → Arco del Vento: «Consenti notifiche» attivo, anche «Badge» per il numero
                  sull'icona; e nessuna «Full immersion» (Focus) che le nasconda.</li>
            </ul>
          </li>
          <li>Se l'avviso dice che non è partita, il motivo è scritto lì e accanto al dispositivo.</li>
          <li>Dopo una prenotazione, «Ultima notifica del sito» qui sopra dice se il sito ci ha provato e a quanti dispositivi è arrivata.</li>
        </ol>
        <p>Il numero sull'icona sono le richieste ancora da leggere: sull'iPhone e sul computer lo mette l'app; su Android il pallino
           lo mette il telefono quando c'è una notifica non letta.</p>
      </div>
    </details>

    <details class="adm-notifiche-come">
      <summary>Come si installa l'area riservata sul telefono</summary>
      <div class="adm-notifiche-come__testo">
        <p><strong>Android</strong> (Chrome): apri l'area riservata, tocca «Installa» nell'invito in fondo alla pagina,
           oppure ⋮ → «Installa app». Poi apri l'app e attiva le notifiche.</p>
        <p><strong>iPhone e iPad</strong> (Safari, iOS 16.4 o più recente): tocca <?= icona('condividi', 15) ?> Condividi →
           <?= icona('aggiungi', 15) ?> «Aggiungi alla schermata Home». Le notifiche si attivano dall'app, aperta dall'icona:
           in Safari l'iPhone non le permette. L'app ha un accesso suo: la prima volta si entra con nome utente e password.</p>
      </div>
    </details>
  <?php endif; ?>
</section>
