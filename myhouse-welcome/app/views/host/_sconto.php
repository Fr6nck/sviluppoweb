<?php
/* Il codice sconto del primo anno (6E), nel riquadro del piano. Riceve $acc, $pv (il piano,
   può mancare), $quantita, $torna ('/piano' o il passo «Pubblica») e, se c'è già, $sc (Sconti::applicato).
   Codice applicato: il messaggio e «Togli». Altrimenti «Hai un codice sconto?» apre il campo;
   un codice non valido torna qui sotto il campo, con il motivo, e il campo resta compilato. */
use function MHW\b;
use MHW\{Support, Csrf, Sconti, Plans};
if (!Sconti::disponibili()) return;
$sc = $sc ?? ($pv ? Sconti::applicato($acc, $pv, $quantita ?? 1) : null);
$errSconto = $_SESSION['sconto_errore'] ?? null; unset($_SESSION['sconto_errore']);
$idS = 'sconto-' . substr(md5($torna), 0, 6); ?>
<?php if ($sc): ?>
  <div class="sconto-ok" role="status">
    <p class="sconto-ok__prezzo" style="margin:0"><s><?= Support::e(Support::money($sc['prezzo'], $pv['currency'] ?? 'EUR')) ?></s>
      <b><?= Support::e(Support::money($sc['scontato'], $pv['currency'] ?? 'EUR')) ?></b> + IVA il primo anno</p>
    <p style="margin:0">Codice <b><?= Support::e($sc['riga']['code']) ?></b> applicato: <?= Support::e(Sconti::etichetta($sc['riga'])) ?> sul primo anno.
      Dal secondo anno <?= Support::e(Support::money($sc['prezzo'], $pv['currency'] ?? 'EUR')) ?> + IVA.</p>
    <form method="post" action="<?= b() ?>/sconto/togli" style="margin:0"><?= Csrf::field() ?><input type="hidden" name="torna" value="<?= Support::e($torna) ?>">
      <button class="linkbtn">Togli</button></form>
  </div>
<?php elseif (!$pv && !$errSconto && !empty($acc['intended_discount_code_id']) && ($rigaSc = Sconti::riga((int) $acc['intended_discount_code_id']))): /* piano non ancora scelto */ ?>
  <div class="sconto-ok" role="status">
    <p style="margin:0">Codice <b><?= Support::e($rigaSc['code']) ?></b> applicato: <?= Support::e(Sconti::etichetta($rigaSc)) ?> sul primo anno, sul piano che scegli.</p>
    <form method="post" action="<?= b() ?>/sconto/togli" style="margin:0"><?= Csrf::field() ?><input type="hidden" name="torna" value="<?= Support::e($torna) ?>">
      <button class="linkbtn">Togli</button></form>
  </div>
<?php endif; ?>
<?php if (!$sc || $errSconto): /* il campo: senza codice applicato, o per mostrare l'errore di un altro codice */ ?>
  <details class="sconto"<?= $errSconto ? ' open' : '' ?>>
    <summary class="linkbtn">Hai un codice sconto?</summary>
    <form method="post" action="<?= b() ?>/sconto/applica" class="sconto__form"><?= Csrf::field() ?>
      <input type="hidden" name="torna" value="<?= Support::e($torna) ?>">
      <div class="field" style="margin:0">
        <label for="<?= $idS ?>">Codice sconto</label>
        <input id="<?= $idS ?>" name="codice" type="text" maxlength="24" autocomplete="off" spellcheck="false" style="text-transform:uppercase"
               value="<?= Support::e($errSconto['codice'] ?? '') ?>"<?= $errSconto ? ' aria-invalid="true" aria-describedby="' . $idS . '-err"' : '' ?>>
      </div>
      <button class="btn btn--ghost btn--sm">Applica</button>
      <?php if ($errSconto): ?><p class="field-error" id="<?= $idS ?>-err" role="alert" style="flex-basis:100%;margin:0"><?= Support::e($errSconto['msg']) ?></p><?php endif; ?>
    </form>
  </details>
<?php endif; ?>
