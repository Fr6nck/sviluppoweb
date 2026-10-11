<?php
/* Impostazioni della struttura e, in fondo, l'eliminazione. */
use function MHW\b;
use MHW\{Support, Csrf, Icon};
$title = 'Impostazioni — ' . $prop['name']; ?>
<div class="stack stack--lg" style="max-width:720px">
  <div class="stack stack--sm">
    <h1>Impostazioni.</h1>
    <p class="muted small">Link della guida: <?= Support::e(Support::baseUrl()) ?>/g/<?= Support::e($prop['slug']) ?> — resta sempre lo stesso, anche se cambi il nome.</p>
  </div>
  <?php if ($err): ?><p class="note note--err" role="alert"><?= Support::e($err) ?></p><?php endif; ?>
  <?php $dopoPasso = ''; include __DIR__ . '/_struttura_form.php'; ?>

  <?php if (array_key_exists('review_google', $prop)): /* Dopo il soggiorno: recensioni, prenotazione diretta, firma */
        $firma = MHW\Entitlements::can((int) $acc['id'], 'hide_branding'); ?>
  <form method="post" action="<?= b() ?>/pannello/<?= (int) $prop['id'] ?>/dopo-il-soggiorno" class="stack" id="dopo-il-soggiorno" style="margin-top:24px"><?= Csrf::field() ?>
    <div class="stack stack--sm">
      <h2 style="font-size:24px">Dopo il soggiorno.</h2>
      <p class="muted small">Facoltativo. Nella pagina di saluto, che l'ospite apre da «Stiamo per partire», compare solo quello che compili.</p>
    </div>
    <fieldset class="fieldset">
      <legend>Recensioni</legend>
      <p class="help">Gli ospiti vedono un bottone per ogni link che compili, alla fine del soggiorno.</p>
      <?php /* 7C: una scheda per piattaforma, con «Prova il link» e dove trovarlo. */
            $piattaforme = [
              'review_google'  => ['Google', 'search', ['Apri il tuo Profilo dell\'attività su Google (cerca il nome della struttura da loggato).', 'Tocca «Chiedi recensioni» e copia il link che ti dà.']],
              'review_booking' => ['Booking.com', 'calendar', ['Entra nella Extranet di Booking.com.', 'Vai in «Recensioni ospiti» e copia il link della pagina delle tue recensioni.']],
              'review_airbnb'  => ['Airbnb', 'home', ['Apri il tuo annuncio su Airbnb.', 'Copia il link dell\'annuncio: le recensioni sono lì.']],
              'review_other'   => ['Altro sito', 'globe', ['Il sito dove vuoi le recensioni (TripAdvisor, il tuo sito…).', 'Copia il link della pagina dove si scrive la recensione.']],
            ]; ?>
      <div class="recensioni-schede">
        <?php foreach ($piattaforme as $k => [$et, $ico, $dove]): $val = (string) $prop[$k]; ?>
          <div class="recensione-scheda">
            <div class="recensione-scheda__testa"><span class="recensione-scheda__ico"><?= Icon::svg($ico, 18) ?></span><label for="<?= $k ?>"><?= $et ?></label></div>
            <input id="<?= $k ?>" name="<?= $k ?>" type="url" maxlength="500" placeholder="https://" value="<?= Support::e($val) ?>" data-prova-campo>
            <div class="recensione-scheda__piede">
              <a class="small" href="<?= Support::e($val !== '' ? $val : '#') ?>" target="_blank" rel="noopener" data-prova-link<?= $val === '' ? ' hidden' : '' ?>>Prova il link</a>
              <details class="recensione-scheda__dove"><summary class="small">Dove trovo il link?</summary>
                <ol class="small"><?php foreach ($dove as $riga): ?><li><?= Support::e($riga) ?></li><?php endforeach; ?></ol></details>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </fieldset>
    <fieldset class="fieldset">
      <legend>Prenotazione diretta</legend>
      <p class="help">«La prossima volta prenota da noi»: il link al tuo sito e, se vuoi, un codice sconto per chi torna.</p>
      <?php $dUrl = (string) $prop['direct_url']; $dCod = (string) $prop['direct_code']; $dSconto = (string) ($prop['direct_discount'] ?? ''); ?>
      <div class="diretta">
        <div class="stack" style="gap:12px">
          <div class="field" style="margin:0"><label for="direct_url">Link al sito per prenotare</label>
            <input id="direct_url" name="direct_url" type="url" maxlength="500" placeholder="https://" value="<?= Support::e($dUrl) ?>" data-diretta="url"></div>
          <div class="grid grid-2">
            <div class="field" style="margin:0"><label for="direct_code">Codice sconto per chi torna <span class="muted">(facoltativo)</span></label>
              <input id="direct_code" aria-describedby="direct-code-aiuto" name="direct_code" type="text" maxlength="60" autocomplete="off" value="<?= Support::e($dCod) ?>" data-diretta="codice">
              <p class="help" id="direct-code-aiuto" style="margin:6px 0 0">Il codice lo crei tu sul tuo sito o sul tuo motore di prenotazione; qui lo scrivi perché l'ospite lo veda quando parte.</p></div>
            <div class="field" style="margin:0"><label for="direct_discount">Sconto <span class="muted">(facoltativo)</span></label>
              <input id="direct_discount" name="direct_discount" type="text" maxlength="40" autocomplete="off" placeholder="Per esempio 10% o 15 €" value="<?= Support::e($dSconto) ?>" data-diretta="sconto"></div>
          </div>
          <p class="note" role="status" data-diretta-avviso<?= $dCod !== '' && $dUrl === '' ? '' : ' hidden' ?>>Senza il link al tuo sito l'ospite non sa dove usarlo.</p>
        </div>
        <?php /* L'anteprima della scheda che vede l'ospite nel commiato (in italiano). */ ?>
        <div class="diretta__anteprima" aria-label="Anteprima: come la vede l'ospite">
          <span class="tiny muted">Anteprima</span>
          <b>La prossima volta prenota da noi</b>
          <span data-diretta-offerta<?= $dCod === '' ? ' hidden' : '' ?>><?= Support::e(($dSconto !== '' ? '−' . ltrim($dSconto, '-−') . ' con il codice ' : 'Con il codice ')) ?><b data-diretta-codice><?= Support::e($dCod) ?></b></span>
          <span class="diretta__bottoni"><span class="diretta__finto" data-diretta-copia<?= $dCod === '' ? ' hidden' : '' ?>>Copia il codice</span><span class="diretta__finto diretta__finto--pieno">Prenota sul nostro sito</span></span>
        </div>
      </div>
    </fieldset>
    <fieldset class="fieldset">
      <legend>Firma nella guida</legend>
      <p class="help">In fondo alla guida: «Guida creata con MyHouse Welcome · Crea la tua».</p>
      <?php if ($firma): ?>
        <label class="check"><input type="checkbox" name="hide_branding" value="1" <?= (int) $prop['hide_branding'] === 1 ? 'checked' : '' ?>> <span>Nascondi la firma</span></label>
      <?php else: ?>
        <p class="small">La firma resta visibile con il tuo piano. Con Plus e Portfolio puoi nasconderla. <a href="<?= b() ?>/piano?passa=plus">Vedi i piani</a></p>
      <?php endif; ?>
    </fieldset>
    <div class="actions"><button class="btn">Salva</button></div>
  </form>
  <?php endif; ?>

  <details class="fieldset" style="margin-top:24px" id="link-guida">
    <summary class="legend" style="cursor:pointer;min-height:32px">Cambia il link della guida</summary>
    <form method="post" action="<?= b() ?>/pannello/<?= (int) $prop['id'] ?>/link" class="stack" style="margin-top:12px"><?= Csrf::field() ?>
      <p class="small">Il link di adesso è <b><?= Support::e(Support::baseUrl() . '/g/' . $prop['slug']) ?></b>. Se lo hai mandato a chi non doveva averlo,
        crea un link nuovo: si forma dal nome della struttura e da quattro caratteri casuali.</p>
      <p class="note" role="note">Il link vecchio smette di funzionare. Il QR stampato no: continua ad aprire la guida.</p>
      <label class="check"><input type="checkbox" name="conferma" value="1" required> <span>Ho capito: il link vecchio non funzionerà più</span></label>
      <div class="actions"><button class="btn btn--ghost">Crea un link nuovo</button></div>
    </form>
  </details>

  <details class="fieldset" style="margin-top:24px">
    <summary class="legend" style="cursor:pointer;min-height:32px">Elimina la struttura</summary>
    <form method="post" action="<?= b() ?>/pannello/<?= (int) $prop['id'] ?>/elimina" class="stack" style="margin-top:12px"><?= Csrf::field() ?>
      <p class="small">Si cancellano la guida, le traduzioni, le foto, i PDF e il QR. Chi ha già stampato il QR troverà una pagina vuota.
        Non si può annullare. L'abbonamento non si disdice da qui: lo gestisci in <a href="<?= b() ?>/account">Account &amp; Fatturazione</a>.</p>
      <div class="field" style="margin:0"><label for="conferma">Per confermare scrivi <b><?= Support::e($prop['name']) ?></b></label>
        <input type="text" id="conferma" name="conferma" required autocomplete="off" autocapitalize="none" spellcheck="false"></div>
      <div class="actions"><button class="btn btn--danger">Elimina per sempre</button></div>
    </form>
  </details>
</div>
