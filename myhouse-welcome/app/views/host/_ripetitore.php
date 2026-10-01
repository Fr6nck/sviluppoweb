<?php
/* Un elenco di righe con sottocampi: più reti Wi-Fi, più contatti, più
   parcheggi… Ogni riga si sposta (maniglia o «su/giù» da tastiera), si toglie,
   se ne aggiunge un'altra. L'id nascosto tiene la riga legata alle sue
   traduzioni anche quando cambia posto.
   Riceve: $rip = [
     'name'    => nome del campo nel modulo (es. 'networks', 'contacts'),
     'legend'  => titolo del riquadro, 'help' => aiuto,
     'sub'     => [nome => [tipo, etichetta, aiuto, 'options' => […]]],
     'rows'    => righe già salvate (ognuna con 'id' e i sottocampi),
     'add'     => testo del bottone «Aggiungi…», 'max' => quante righe al massimo,
   ] */
use MHW\{Support, Icon};
$r = $rip + ['help' => '', 'rows' => [], 'add' => 'Aggiungi', 'max' => 30];
$nome = $r['name'];
$domId = 'rip-' . preg_replace('/[^a-z0-9]+/i', '-', $nome);
$giorni = [1 => 'Lun', 2 => 'Mar', 3 => 'Mer', 4 => 'Gio', 5 => 'Ven', 6 => 'Sab', 7 => 'Dom'];

/* Una riga: $k è la chiave nel modulo (l'ordine lo decide la posizione nella pagina). */
$riga = function (string $k, array $v) use ($r, $nome, $domId, $giorni): string {
    $h = '<div class="rip__riga" data-rip-riga>'
       . '<span class="riga__maniglia rip__maniglia" aria-hidden="true" title="Trascina per cambiare l\'ordine">' . Icon::svg('grip', 18, 2.6) . '</span>'
       . '<input type="hidden" name="' . Support::e($nome) . '[' . $k . '][id]" value="' . Support::e((string) ($v['id'] ?? '')) . '">'
       . '<div class="rip__campi">';
    foreach ($r['sub'] as $sn => $sd) {
        [$tipo, $et] = $sd; $aiuto = $sd[2] ?? '';
        $n = Support::e($nome) . '[' . $k . '][' . $sn . ']';
        $id = $domId . '-' . $k . '-' . $sn;
        $val = $v[$sn] ?? ($tipo === 'days' ? [] : '');
        $help = $aiuto !== '' ? '<p class="help" style="margin:0 0 6px">' . Support::e($aiuto) . '</p>' : '';
        if ($tipo === 'check') {
            $h .= '<label class="check rip__check"><input type="checkbox" name="' . $n . '" value="1"' . ($val ? ' checked' : '') . '> <span>' . Support::e($et) . '</span></label>';
        } elseif ($tipo === 'choice') {
            $h .= '<div class="field" style="margin:0"><label for="' . $id . '">' . Support::e($et) . '</label>' . $help . '<select id="' . $id . '" name="' . $n . '">';
            foreach ($sd['options'] as $ok => $ol) $h .= '<option value="' . Support::e($ok) . '"' . ((string) $val === (string) $ok ? ' selected' : '') . '>' . Support::e($ol) . '</option>';
            $h .= '</select></div>';
        } elseif ($tipo === 'days') {
            $h .= '<fieldset class="rip__giorni"><legend class="small">' . Support::e($et) . '</legend><div class="scelte scelte--riga">';
            foreach ($giorni as $gn => $gl) {
                $h .= '<label class="scelta scelta--mini"><input type="checkbox" name="' . $n . '[]" value="' . $gn . '"' . (in_array($gn, (array) $val, false) ? ' checked' : '') . '><span>' . $gl . '</span></label>';
            }
            $h .= '</div></fieldset>';
        } elseif ($tipo === 'textarea') {
            $h .= '<div class="field" style="margin:0"><label for="' . $id . '">' . Support::e($et) . '</label>' . $help
                . '<textarea id="' . $id . '" name="' . $n . '" rows="2" maxlength="2000">' . Support::e((string) $val) . '</textarea></div>';
        } else {
            $t = ['url' => 'url', 'tel' => 'tel', 'time' => 'time'][$tipo] ?? 'text';
            $extra = $tipo === 'secret' ? ' autocomplete="off" spellcheck="false"' : ($tipo === 'url' ? ' placeholder="https://"' : '');
            $h .= '<div class="field" style="margin:0"><label for="' . $id . '">' . Support::e($et) . '</label>' . $help
                . '<input id="' . $id . '" type="' . $t . '" name="' . $n . '" value="' . Support::e((string) $val) . '" maxlength="' . ($tipo === 'url' ? 500 : 300) . '"' . $extra . '></div>';
        }
    }
    $h .= '</div><div class="rip__azioni">'
        . '<button type="button" class="icon-btn" data-rip-su aria-label="Sposta su">' . Icon::svg('chevron', 16, 2, 'rip__su') . '</button>'
        . '<button type="button" class="icon-btn" data-rip-giu aria-label="Sposta giù">' . Icon::svg('chevron', 16, 2, 'rip__giu') . '</button>'
        . '<button type="button" class="icon-btn" data-rip-togli aria-label="Togli questa riga">&times;</button>'
        . '</div></div>';
    return $h;
}; ?>
<fieldset class="fieldset rip" id="<?= $domId ?>" data-rip data-rip-max="<?= (int) $r['max'] ?>">
  <legend><?= Support::e($r['legend']) ?></legend>
  <?php if ($r['help'] !== ''): ?><p class="help"><?= Support::e($r['help']) ?></p><?php endif; ?>
  <div class="rip__righe" data-rip-righe>
    <?php foreach (array_values($r['rows']) as $i => $v) echo $riga((string) $i, $v); ?>
    <?php /* Senza JavaScript serve una riga vuota già pronta; con JavaScript si toglie se ce n'è già un'altra. */
          echo str_replace('data-rip-riga', 'data-rip-riga data-rip-vuota', $riga((string) count($r['rows']), [])); ?>
  </div>
  <template data-rip-modello><?= $riga('__K__', []) ?></template>
  <button type="button" class="linkbtn" data-rip-aggiungi hidden><?= Icon::svg('plus', 15, 2) ?> <?= Support::e($r['add']) ?></button>
</fieldset>
