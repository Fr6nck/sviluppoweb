<?php use MHW\Support; $title = 'QR & Link — ' . $prop['name']; ?>
<div class="stack stack--lg" style="max-width:900px">
  <div class="stack stack--sm">
    <h1>QR &amp; Link.</h1>
    <?php if ($prop['status'] !== 'published'): ?>
      <p class="note">Puoi già scaricare e stampare il QR: funzionerà appena pubblichi la guida.</p>
    <?php elseif (!$online): ?>
      <p class="note note--err">La guida è offline perché l'abbonamento non è attivo. Il QR resta valido: tornerà a funzionare quando lo rinnovi.</p>
    <?php else: ?>
      <p class="lead">Stampa il QR e mettilo dove l'ospite lo vede entrando: sul tavolo, vicino alla porta, sul frigo.</p>
    <?php endif; ?>
  </div>
  <?php include __DIR__ . '/_qr_box.php'; ?>

  <?php /* Il messaggio di benvenuto (6D · M2): pronto da copiare, una versione per lingua della guida,
           con il link che apre la guida già in quella lingua. Si può ritoccare prima di copiarlo. */
  $nomiLingue = MHW\Config::get('locales');
  $link = Support::baseUrl() . '/g/' . $prop['slug'];
  $firma = trim((string) ($prop['host_name'] ?? '')) !== '' ? trim((string) $prop['host_name']) : $prop['name']; ?>
  <section class="fieldset stack" style="gap:12px" aria-labelledby="benvenuto-titolo">
    <h2 id="benvenuto-titolo" class="legend" style="font-size:18px">Messaggio di benvenuto</h2>
    <p class="help" style="margin:0">Pronto da mandare prima dell'arrivo: su WhatsApp, nella chat di Airbnb e Booking o nel messaggio automatico di conferma. Puoi ritoccarlo prima di copiarlo.</p>
    <?php foreach ($lingue as $i => $l):
          $testo = MHW\I18n::t($l, 'welcome_message', $prop['name'], $link . ($l === $prop['default_locale'] ? '' : '?l=' . $l), $firma); ?>
      <details class="benvenuto-msg"<?= $i === 0 ? ' open' : '' ?>>
        <summary><?= Support::e($nomiLingue[$l] ?? strtoupper($l)) ?></summary>
        <div class="stack" style="gap:10px;margin-top:10px">
          <label class="sr-only" for="benvenuto-<?= $l ?>">Messaggio di benvenuto in <?= Support::e($nomiLingue[$l] ?? $l) ?></label>
          <textarea id="benvenuto-<?= $l ?>" rows="7" lang="<?= $l ?>" data-benvenuto><?= Support::e($testo) ?></textarea>
          <div class="row" style="gap:8px">
            <button type="button" class="btn btn--sm" data-copia-da="benvenuto-<?= $l ?>" data-copiato="Messaggio copiato" hidden><?= MHW\Icon::svg('copy', 15) ?>Copia</button>
            <a class="btn btn--ghost btn--sm" href="https://wa.me/?text=<?= rawurlencode($testo) ?>" target="_blank" rel="noopener" data-wa-da="benvenuto-<?= $l ?>"><?= MHW\Icon::svg('whatsapp', 15) ?>Apri WhatsApp</a>
          </div>
        </div>
      </details>
    <?php endforeach; ?>
  </section>
</div>
