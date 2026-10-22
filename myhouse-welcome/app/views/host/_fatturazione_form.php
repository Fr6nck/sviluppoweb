<?php use function MHW\t, MHW\tf;
/* Il modulo dei dati di fatturazione (stessi campi e stessa validazione: Fatturazione::valida), usato in
   Account & Fatturazione e, quando mancano, nel passo «Pubblica» (7B). Riceve $acc e, facoltativi, $fatt,
   $erroriFatt, $torna. Dopo l'include restano $completi e $erroriFatt. */
use function MHW\b;
use MHW\{Support, Csrf};
$fatt = $fatt ?? $acc; $erroriFatt = $erroriFatt ?? []; $torna = $torna ?? '';
$fv = fn(string $k) => Support::e((string) ($fatt[$k] ?? ''));
$completi = MHW\Fatturazione::completa($acc);
// Persona fisica o azienda: alcuni campi valgono solo per un tipo (data-per). Il tipo scelto decide
// cosa si vede subito; con JavaScript cambia al volo. Senza un tipo scelto, si vede tutto.
$tipoF = (string) ($fatt['billing_type'] ?? '');
$per = fn(string $t) => ' data-per="' . $t . '"' . ($tipoF !== '' && $t !== $tipoF ? ' hidden' : '');
$etichetta = fn(array $per) => implode('', array_map(fn($t, $et) => '<span data-per-etichetta="' . $t . '"'
    . (($tipoF === '' ? $t !== '' : $t !== $tipoF) ? ' hidden' : '') . '>' . t($et) . '</span>', array_keys($per), $per));
$campoF = function (string $k, string $et, string $tipo = 'text', string $extra = '', string $aiuto = '', string $solo = '') use ($fv, $erroriFatt, $per): string {
    $err = $erroriFatt[$k] ?? '';
    $desc = trim(($aiuto !== '' ? 'f-' . $k . '-aiuto ' : '') . ($err !== '' ? 'f-' . $k . '-err' : ''));
    // 9R · L'aiuto sotto il campo (non più tra etichetta e campo): nelle colonne i campi restano allineati in alto.
    return '<div class="field" style="margin:0"' . ($solo !== '' ? $per($solo) : '') . '><label for="f-' . $k . '">' . t($et) . '</label>'
        . '<input id="f-' . $k . '" name="' . $k . '" type="' . $tipo . '" value="' . $fv($k) . '" ' . $extra
        . ($desc !== '' ? ' aria-describedby="' . $desc . '"' : '') . ($err !== '' ? ' aria-invalid="true"' : '') . '>'
        . ($aiuto !== '' ? '<p class="help fatt-aiuto" id="f-' . $k . '-aiuto">' . t($aiuto) . '</p>' : '')
        . ($err !== '' ? '<p class="campo-errore" id="f-' . $k . '-err">' . Support::e(t($err)) . '</p>' : '') . '</div>';
}; ?>
<form method="post" action="<?= b() ?>/account/fatturazione" class="stack fatt-modulo" style="margin-top:14px" novalidate data-tipo-fatt><?= Csrf::field() ?>
  <?php if ($torna !== ''): ?><input type="hidden" name="torna" value="<?= Support::e($torna) ?>"><?php endif; ?>
  <fieldset class="fieldset" style="margin:0">
    <legend><?= t('A chi intestiamo la fattura') ?></legend>
    <div class="scelte scelte--riga">
      <?php foreach (MHW\Fatturazione::TIPI as $k => $et): ?>
        <label class="scelta"><input type="radio" name="billing_type" value="<?= $k ?>" <?= ($fatt['billing_type'] ?? '') === $k ? 'checked' : '' ?>
          <?= isset($erroriFatt['billing_type']) ? 'aria-invalid="true" aria-describedby="f-billing_type-err"' : '' ?>><span class="scelta__testo"><?= t($et) ?></span></label>
      <?php endforeach; ?>
    </div>
    <?php if (isset($erroriFatt['billing_type'])): ?><p class="campo-errore" id="f-billing_type-err"><?= Support::e(t($erroriFatt['billing_type'])) ?></p><?php endif; ?>
  </fieldset>
  <?= $campoF('billing_name', $etichetta(['' => 'Intestatario', 'privato' => 'Nome e cognome', 'azienda' => 'Ragione sociale']), 'text', 'maxlength="160" autocomplete="name"') ?>
  <div class="grid grid-2 campi-allineati">
    <?= $campoF('vat', 'Partita IVA', 'text', 'maxlength="13" inputmode="numeric" autocomplete="off"', '11 cifre.', 'azienda') ?>
    <?= $campoF('cf', 'Codice fiscale' . $etichetta(['' => '', 'privato' => '', 'azienda' => ' <span class="muted">(facoltativo)</span>']), 'text', 'maxlength="16" autocomplete="off" style="text-transform:uppercase" autocapitalize="characters"', $etichetta(['' => '16 caratteri. Per una società, anche le 11 cifre della partita IVA.', 'privato' => '16 caratteri, come sulla tessera sanitaria.', 'azienda' => '16 caratteri. Per una società, anche le 11 cifre della partita IVA.'])) ?>
    <?= $campoF('sdi', 'Codice destinatario SDI', 'text', 'maxlength="7" autocomplete="off" style="text-transform:uppercase" autocapitalize="characters"', '7 caratteri. Serve questo oppure la PEC.', 'azienda') ?>
    <?= $campoF('pec', 'PEC', 'email', 'maxlength="190" autocomplete="off"', 'Se non hai il codice destinatario.', 'azienda') ?>
  </div>
  <p class="small muted"<?= $per('privato') ?>><?= t('Per una persona fisica bastano nome, codice fiscale e indirizzo: la fattura elettronica arriva anche nel tuo cassetto fiscale dell\'Agenzia delle Entrate. Hai la partita IVA? Scegli «Azienda o professionista».') ?></p>
  <?= $campoF('billing_address', 'Indirizzo', 'text', 'maxlength="255" autocomplete="street-address"', 'Via e numero civico.') ?>
  <div class="grid grid-3 campi-allineati">
    <?= $campoF('billing_postal', 'CAP', 'text', 'maxlength="5" inputmode="numeric" autocomplete="postal-code"') ?>
    <?= $campoF('billing_city', 'Città', 'text', 'maxlength="120" autocomplete="address-level2"') ?>
    <?= $campoF('billing_province', 'Provincia', 'text', 'maxlength="2" autocomplete="address-level1" style="text-transform:uppercase" autocapitalize="characters"', 'Sigla, per esempio PG.') ?>
  </div>
  <p class="small muted"><?= t('Con questi dati facciamo la fattura elettronica e la mandiamo allo SDI. Non li usiamo per nient\'altro.') ?></p>
  <div class="actions"><button class="btn"><?= $torna !== '' ? t('Salva e torna alla pubblicazione') : t('Salva i dati di fatturazione') ?></button></div>
</form>
