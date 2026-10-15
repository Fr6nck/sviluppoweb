<?php
/* «Ti sta piacendo MyHouse Welcome?» (7D): il giudizio del cliente sulla piattaforma.
   5 stelle (obbligatorie), «Cosa diresti a un altro host?» (20–400 caratteri), la foto da mostrare
   e il consenso, che serve solo per la pubblicazione. Riceve $err, $fatto (dopo l'invio), $vecchi,
   $p (la struttura di cui parla), $gia (un giudizio già dato). */
use function MHW\b;
use MHW\{Support, Icon, Csrf, Media, Testimonianze};
$title = 'Il tuo giudizio';
$voto = (int) ($vecchi['rating'] ?? 0);
$fotoScelta = (string) ($vecchi['photo_source'] ?? 'nessuna');
$anteprime = [
    'profilo' => $p && $p['profile_media_id'] ? Media::url((int) $p['profile_media_id']) : null,
    'copertina' => $p && $p['cover_media_id'] ? Media::url((int) $p['cover_media_id']) : null,
]; ?>
<div class="stack stack--lg" style="max-width:720px">
  <?php if ($fatto): ?>
    <section class="panel stack giudizio-grazie" role="status">
      <span class="giudizio-grazie__ico" aria-hidden="true"><?= Icon::svg('star', 26, 1.8) ?></span>
      <?php if ($fatto['rating'] <= 3): ?>
        <h1 style="font-size:30px">Grazie.</h1>
        <p class="lead">Ti scriviamo per capire cosa migliorare.</p>
      <?php else: ?>
        <h1 style="font-size:30px">Grazie del tuo giudizio.</h1>
        <p class="lead"><?= $fatto['consenso'] ? 'Lo leggiamo e lo pubblichiamo sul sito dopo un controllo. Puoi ritirare il consenso quando vuoi da Account &amp; Fatturazione.'
                                                 : 'Resta tra noi: senza il tuo consenso non lo pubblichiamo.' ?></p>
      <?php endif; ?>
      <div class="row"><a class="btn" href="<?= b() ?>/pannello">Torna alle tue guide</a></div>
    </section>
  <?php else: ?>
  <div class="stack stack--sm">
    <h1>Ti sta piacendo MyHouse Welcome?</h1>
    <p class="lead">Due righe per un altro host che sta decidendo se provarla. Ci aiuti anche a migliorare.</p>
  </div>
  <?php if ($gia && $gia['status'] === 'pubblicata' && $gia['withdrawn_at'] === null): ?>
    <p class="note" role="status"><span>Il tuo giudizio è già pubblicato sul sito. Grazie! Se vuoi toglierlo, lo trovi in <a href="<?= b() ?>/account#giudizio">Account &amp; Fatturazione</a>.</span></p>
  <?php else: ?>
  <?php if ($err): ?><p class="note note--err" role="alert"><span><?= Support::e($err) ?></span></p><?php endif; ?>
  <form method="post" action="<?= b() ?>/giudizio" class="panel stack giudizio" novalidate><?= Csrf::field() ?>
    <fieldset class="stelle-scelta">
      <legend>Il tuo voto <span class="muted">(obbligatorio)</span></legend>
      <div class="stelle-scelta__fila">
        <?php for ($i = 5; $i >= 1; $i--): ?>
          <input type="radio" class="sr-only" name="rating" id="voto-<?= $i ?>" value="<?= $i ?>" <?= $voto === $i ? 'checked' : '' ?> required>
          <label for="voto-<?= $i ?>" title="<?= $i ?> su 5"><?= Icon::svg('star', 34, 1.6) ?><span class="sr-only"><?= $i ?> stell<?= $i === 1 ? 'a' : 'e' ?></span></label>
        <?php endfor; ?>
      </div>
    </fieldset>
    <div class="field" style="margin:0">
      <label for="g-testo">Cosa diresti a un altro host?</label>
      <textarea id="g-testo" name="body" rows="4" minlength="20" maxlength="400" required aria-describedby="g-testo-aiuto"><?= Support::e((string) ($vecchi['body'] ?? '')) ?></textarea>
      <p class="help" id="g-testo-aiuto">Da 20 a 400 caratteri. Per esempio: cosa ti fa risparmiare, cosa dicono gli ospiti.</p>
    </div>
    <fieldset class="giudizio-foto">
      <legend>La foto accanto al giudizio</legend>
      <div class="giudizio-foto__scelte">
        <?php foreach (Testimonianze::FOTO as $k => $et): if ($k !== 'nessuna' && !$anteprime[$k]) continue; ?>
          <label class="giudizio-foto__scelta">
            <input type="radio" name="photo_source" value="<?= $k ?>" <?= $fotoScelta === $k ? 'checked' : '' ?>>
            <span class="giudizio-foto__img giudizio-foto__img--<?= $k ?>"><?php if ($k !== 'nessuna'): ?><img src="<?= Support::e($anteprime[$k]) ?>" alt="" width="64" height="64" loading="lazy"><?php else: ?><?= Icon::svg('close', 22, 1.8) ?><?php endif; ?></span>
            <span class="small"><?= Support::e($et) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </fieldset>
    <label class="check giudizio-consenso"><input type="checkbox" name="consenso" value="1" <?= !empty($vecchi['consenso']) ? 'checked' : '' ?>>
      <span><?= Support::e(Testimonianze::CONSENSO) ?></span></label>
    <p class="help" style="margin:0">Il consenso serve solo per la pubblicazione: senza, il giudizio arriva soltanto a noi.</p>
    <div class="actions"><button class="btn"><?= Icon::svg('message', 16) ?>Manda il giudizio</button></div>
  </form>
  <?php endif; ?>
  <?php endif; ?>
</div>
