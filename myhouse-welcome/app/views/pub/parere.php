<?php use MHW\{Support, Csrf, Richiami}; $title = 'Grazie'; ?>
<h1><?= $salvato ? 'Grazie, ce lo segniamo.' : 'Grazie della risposta.' ?></h1>
<?php if ($salvato): ?>
  <p class="muted" style="margin-top:14px;line-height:24px">Lo leggiamo uno per uno. Se ti va di riprovare, la tua guida è dove l'hai lasciata.</p>
  <p style="margin-top:20px"><a class="btn" href="<?= Support::e(Support::url('/accedi')) ?>">Riprendi la tua guida</a></p>
<?php else: ?>
  <p class="muted" style="margin-top:14px;line-height:24px">Ci aiuta a capire cosa migliorare. Se vuoi, aggiungi due righe: le legge una persona.</p>
  <form method="post" action="<?= Support::e(Support::url('/parere/' . $token)) ?>" style="margin-top:20px">
    <?= Csrf::field() ?>
    <fieldset class="field" style="border:0;padding:0;margin:0 0 16px">
      <legend class="label">Cosa ti ha fermato?</legend>
      <?php foreach (Richiami::MOTIVI as $k => $v): ?>
        <label style="display:flex;gap:8px;align-items:center;margin:8px 0"><input type="radio" name="motivo" value="<?= $k ?>"<?= $k === $motivo ? ' checked' : '' ?>> <?= Support::e($v) ?></label>
      <?php endforeach; ?>
    </fieldset>
    <div class="field">
      <label class="label" for="parere-testo">Raccontaci <span class="muted">(facoltativo)</span></label>
      <textarea id="parere-testo" name="testo" rows="4" maxlength="2000" placeholder="Cosa ti sarebbe servito?"><?= Support::e($testo) ?></textarea>
    </div>
    <button class="btn" style="margin-top:12px">Invia</button>
  </form>
<?php endif; ?>
