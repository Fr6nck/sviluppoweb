<?php
/* Impostazioni della struttura e, in fondo, l'eliminazione. */
use function MHW\b;
use MHW\{Support, Csrf};
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
      <p class="help">«Ti è piaciuto il soggiorno?» con un pulsante per ogni piattaforma. Incolla il link diretto alla pagina delle recensioni.</p>
      <div class="grid grid-2">
        <?php foreach (['review_google' => 'Google', 'review_booking' => 'Booking.com', 'review_airbnb' => 'Airbnb', 'review_other' => 'Altro sito'] as $k => $et): ?>
          <div class="field" style="margin:0"><label for="<?= $k ?>"><?= $et ?></label>
            <input id="<?= $k ?>" name="<?= $k ?>" type="url" maxlength="500" placeholder="https://" value="<?= Support::e((string) $prop[$k]) ?>"></div>
        <?php endforeach; ?>
      </div>
    </fieldset>
    <fieldset class="fieldset">
      <legend>Prenotazione diretta</legend>
      <p class="help">«La prossima volta prenota da noi»: il link al tuo sito e, se vuoi, un codice sconto per chi torna.</p>
      <div class="grid grid-2">
        <div class="field" style="margin:0"><label for="direct_url">Link al sito per prenotare</label>
          <input id="direct_url" name="direct_url" type="url" maxlength="500" placeholder="https://" value="<?= Support::e((string) $prop['direct_url']) ?>"></div>
        <div class="field" style="margin:0"><label for="direct_code">Codice sconto <span class="muted">(facoltativo)</span></label>
          <input id="direct_code" name="direct_code" type="text" maxlength="60" autocomplete="off" value="<?= Support::e((string) $prop['direct_code']) ?>"></div>
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
