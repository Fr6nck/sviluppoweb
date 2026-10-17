<?php
/* Il codice sconto del primo anno (6E) e, dalla 9A, tutti gli sconti che valgono per l'account: nel riquadro
   del piano (/piano e passo «Pubblica»). Riceve $acc, $pv (il piano, può mancare), $quantita, $torna e,
   se la pagina l'ha già calcolato, $riep (Sconti::riepilogo). Con $soloCampo il conto lo mostra la pagina
   (il riepilogo del passo «Pubblica») e qui resta solo il campo.
   Gli sconti di sistema (invito di un amico, giudizio) non si tolgono: un codice scritto a mano sì. Un codice
   non valido torna qui sotto il campo, con il motivo, e il campo resta compilato. */
use function MHW\b;
use MHW\{Support, Csrf, Sconti, Plans};
if (!Sconti::disponibili()) return;
$riep = $riep ?? ($pv ? Sconti::riepilogo($pv, (int) ($quantita ?? 1), $acc) : null);
$soloCampo = $soloCampo ?? false;
$errSconto = $_SESSION['sconto_errore'] ?? null; unset($_SESSION['sconto_errore']);
$idS = 'sconto-' . substr(md5($torna), 0, 6);
$val = (string) ($pv['currency'] ?? 'EUR');
$conInviti = MHW\Inviti::disponibili();
// Il codice scritto a mano che vale (o varrebbe) e si può togliere; gli sconti di sistema senza piano scelto.
$manuale = $riep['manuale'] ?? (!$pv && !empty($acc['intended_discount_code_id']) ? Sconti::riga((int) $acc['intended_discount_code_id']) : null);
if ($manuale && !empty($manuale['sistema'])) $manuale = null;
$sistema = $riep['sistema'] ?? Sconti::sistema($acc);
$togli = fn() => '<form method="post" action="' . b() . '/sconto/togli" style="margin:0">' . Csrf::field() . '<input type="hidden" name="torna" value="' . Support::e($torna) . '"><button class="linkbtn">Togli</button></form>';
$omaggioS = MHW\Omaggi::daUsare($acc);   // 9D · il codice omaggio prenotato: mesi gratis e primo addebito ?>
<?php if ($omaggioS && !$soloCampo): ?>
  <div class="sconto-ok" role="status">
    <p style="margin:0"><b>Codice omaggio <?= Support::e($omaggioS['codice']) ?></b>: <?= (int) $omaggioS['mesi'] ?> mesi gratis, primo addebito il <?= Support::e(Support::date(MHW\Omaggi::fineIso((int) $omaggioS['mesi']))) ?>. Un codice omaggio e un invito non si sommano: vale l'omaggio.</p>
  </div>
<?php elseif ($omaggioS): ?>
  <p class="small" style="margin:0">Codice omaggio <b><?= Support::e($omaggioS['codice']) ?></b> applicato: <?= (int) $omaggioS['mesi'] ?> mesi gratis.</p>
<?php endif; ?>
<?php if (!$soloCampo && $riep && $riep['sconto'] > 0): ?>
  <div class="sconto-ok" role="status">
    <p class="sconto-ok__prezzo" style="margin:0"><s><?= Support::e(Support::money($riep['prezzo'], $val)) ?></s>
      <b><?= Support::e(Support::money($riep['totale'], $val)) ?></b> + IVA il primo anno</p>
    <?php foreach ($riep['righe'] as $rr): ?>
      <p style="margin:0"><b><?= Support::e($rr['nome']) ?></b>: <?= $rr['percento'] > 0 ? '−' . (int) $rr['percento'] . '%' : '−' . Support::e(Support::money((int) $rr['cents'], $val)) ?> sul primo anno.</p>
    <?php endforeach; ?>
    <p style="margin:0">Dal secondo anno <?= Support::e(Support::money($riep['prezzo'], $val)) ?> + IVA.</p>
    <?php if ($riep['nota'] !== ''): ?><p class="small" style="margin:0"><?= Support::e($riep['nota']) ?></p><?php endif; ?>
    <?php if ($manuale): echo $togli(); endif; ?>
  </div>
<?php elseif (!$soloCampo && !$pv && ($manuale || $sistema)): /* piano non ancora scelto */ ?>
  <div class="sconto-ok" role="status">
    <?php foreach ($sistema as $s): ?><p style="margin:0"><b><?= Support::e($s['nome']) ?></b>: −<?= (int) $s['percento'] ?>% sul primo anno, sul piano che scegli.</p><?php endforeach; ?>
    <?php if ($manuale): ?><p style="margin:0">Codice <b><?= Support::e($manuale['code']) ?></b> applicato: <?= Support::e(Sconti::etichetta($manuale)) ?> sul primo anno, sul piano che scegli.<?= $sistema ? ' Non si somma agli altri sconti: vale il più alto.' : '' ?></p><?= $togli() ?><?php endif; ?>
  </div>
<?php elseif ($soloCampo && $manuale): ?>
  <div class="row" style="gap:10px;align-items:center"><span class="small">Codice <b><?= Support::e($manuale['code']) ?></b> applicato.</span><?= $togli() ?></div>
<?php endif; ?>
<?php if (!$manuale || $errSconto): /* il campo: senza codice scritto a mano, o per mostrare l'errore di un altro codice */ ?>
  <details class="sconto"<?= $errSconto ? ' open' : '' ?>>
    <summary class="linkbtn"><?= $conInviti ? 'Hai un codice sconto, un invito o un codice omaggio?' : 'Hai un codice sconto o un codice omaggio?' ?></summary>
    <form method="post" action="<?= b() ?>/sconto/applica" class="sconto__form"><?= Csrf::field() ?>
      <input type="hidden" name="torna" value="<?= Support::e($torna) ?>">
      <div class="field" style="margin:0">
        <label for="<?= $idS ?>"><?= $conInviti ? 'Codice sconto, di invito o omaggio' : 'Codice sconto o omaggio' ?></label>
        <input id="<?= $idS ?>" name="codice" type="text" maxlength="24" autocomplete="off" spellcheck="false" style="text-transform:uppercase"
               value="<?= Support::e($errSconto['codice'] ?? '') ?>"<?= $errSconto ? ' aria-invalid="true" aria-describedby="' . $idS . '-err"' : '' ?>>
      </div>
      <button class="btn btn--ghost btn--sm">Applica</button>
      <?php if ($errSconto): ?><p class="field-error" id="<?= $idS ?>-err" role="alert" style="flex-basis:100%;margin:0"><?= Support::e($errSconto['msg']) ?></p><?php endif; ?>
    </form>
  </details>
<?php endif; ?>
