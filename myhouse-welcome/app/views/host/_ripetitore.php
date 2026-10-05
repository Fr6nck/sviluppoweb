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
     'item'    => il nome di una riga («Parcheggio»): l'intestazione dice «Parcheggio 1», «Parcheggio 2»…
     'presets' => [etichetta => valori] righe pronte da aggiungere con un tocco (emergenze),
     'foto', 'pdf' => se il piano comprende foto e PDF nelle righe,
   ]
   Foto e PDF di una riga: l'id del file salvato viaggia nel campo nascosto, il
   file nuovo in rip_file[campo][riga][sottocampo], «Togli» in rip_togli[…]. */
use MHW\{Support, Icon, Media, Eventi};
$r = $rip + ['help' => '', 'rows' => [], 'add' => 'Aggiungi', 'item' => 'Voce', 'max' => 30, 'presets' => [], 'foto' => true, 'pdf' => true, 'eventi' => false];
$nome = $r['name'];
$domId = 'rip-' . preg_replace('/[^a-z0-9]+/i', '-', $nome);
$giorni = [1 => 'Lun', 2 => 'Mar', 3 => 'Mer', 4 => 'Gio', 5 => 'Ven', 6 => 'Sab', 7 => 'Dom'];

/* Una riga: $k è la chiave nel modulo (l'ordine lo decide la posizione nella pagina).
   In alto maniglia, nome e numero («Parcheggio 1»), e su / giù / togli; sotto i campi,
   su una griglia di 12 colonne (la larghezza di ogni sottocampo la dice il catalogo).
   Una riga già compilata ($chiusa) con JavaScript si mostra compressa, con una linea di
   riepilogo («Piazza Matteotti · A pagamento · 18 €»): si apre con un clic. Senza JavaScript
   resta aperta. La riga nuova nasce aperta. */
$riga = function (string $k, array $v, int $num = 0, bool $chiusa = false) use ($r, $nome, $domId, $giorni): string {
    $no = ' autocomplete="off" data-lpignore="true" data-1p-ignore';   // niente compilazione automatica dei gestori di password
    $campiId = $domId . '-' . $k . '-campi';
    $h = '<div class="rip__riga" data-rip-riga' . ($chiusa ? ' data-rip-chiusa' : '') . '>'
       . '<div class="rip__testa">'
       . '<span class="riga__maniglia rip__maniglia" aria-hidden="true" title="Trascina per cambiare l\'ordine">' . Icon::svg('grip', 18, 2.6) . '</span>'
       . '<span class="rip__nome">' . Support::e($r['item']) . ' <span data-rip-num>' . ($num ?: '') . '</span></span>';
    // Gli eventi (6G): lo stato di ogni evento salvato, e «Ripeti nel 2027» per quelli passati che tornano ogni anno.
    if ($r['eventi'] && $v && trim((string) ($v['name'] ?? '')) !== '') {
        [$st, $gg] = Eventi::stato($v, Eventi::oggi());
        [$testo, $tono] = ['in_corso' => ['In corso', 'pine'], 'tra' => [$gg === 1 ? 'Domani' : 'Tra ' . $gg . ' giorni', 'sea'], 'ricorrente' => ['Ricorrente', 'paper'],
                           'passato' => ['Passato: nascosto', 'ochre'], 'senza_data' => ['Manca la data', 'alert']][$st] ?? ['', 'paper'];
        $h .= '<span class="badge badge--' . $tono . ' rip__stato">' . $testo . '</span>';
        if ($st === 'passato' && !empty($v['yearly'])) {
            $h .= '<button type="submit" class="btn btn--ghost btn--sm rip__ripeti" name="ripeti" value="' . Support::e((string) ($v['id'] ?? '')) . '">Ripeti nel ' . Eventi::annoDopo($v) . '</button>';
        }
    }
    $h .= '<button type="button" class="rip__apri" data-rip-apri aria-expanded="true" aria-controls="' . $campiId . '" hidden>'
       . '<span class="rip__riassunto" data-rip-riassunto></span><span class="sr-only" data-rip-azione>Comprimi</span>' . Icon::svg('chevron', 16, 2, 'rip__freccia') . '</button>'
       . '<div class="rip__azioni">'
       . '<button type="button" class="icon-btn" data-rip-su aria-label="Sposta su">' . Icon::svg('chevron', 16, 2, 'rip__su') . '</button>'
       . '<button type="button" class="icon-btn" data-rip-giu aria-label="Sposta giù">' . Icon::svg('chevron', 16, 2, 'rip__giu') . '</button>'
       . '<button type="button" class="icon-btn" data-rip-togli aria-label="Togli questa riga">&times;</button>'
       . '</div></div>'
       . '<input type="hidden" name="' . Support::e($nome) . '[' . $k . '][id]" value="' . Support::e((string) ($v['id'] ?? '')) . '">'
       . '<div class="rip__campi" id="' . $campiId . '">';
    foreach ($r['sub'] as $sn => $sd) {
        [$tipo, $et] = $sd; $aiuto = $sd[2] ?? '';
        $n = Support::e($nome) . '[' . $k . '][' . $sn . ']';
        $id = $domId . '-' . $k . '-' . $sn;
        if (!empty($sd['nascosto'])) continue;   // lo disegna il suo compagno (il PDF della locandina)
        // La riga nuova parte dal valore predefinito del catalogo (gli eventi: «Un giorno»).
        $val = $v[$sn] ?? ($tipo === 'days' ? [] : (!$v && isset($sd['default']) ? $sd['default'] : ''));
        $w = MHW\SectionCatalog::larghezza($sd);
        // 'nascosto_con': il campo sparisce quando l'altro campo della riga ha uno di quei valori (i costi di un posto privato).
        $nasc = isset($sd['nascosto_con']) ? ' data-nascosto-con="' . Support::e($sd['nascosto_con'][0]) . '" data-nascosto-valori="' . Support::e(implode(',', $sd['nascosto_con'][1])) . '"' : '';
        if (isset($sd['solo_con'])) $nasc .= ' data-solo-con="' . Support::e($sd['solo_con'][0]) . '" data-solo-valori="' . Support::e(implode(',', $sd['solo_con'][1])) . '"';
        if (isset($sd['etichetta_se'])) $nasc .= ' data-etichetta-con="' . Support::e($sd['etichetta_se'][0]) . '" data-etichette="' . Support::e(json_encode($sd['etichetta_se'][1], JSON_UNESCAPED_UNICODE)) . '"';
        $c = '<div class="rip__c rip__c--w' . $w . '"' . (!empty($sd['solo_piu']) ? ' data-rip-solo-piu' : '') . $nasc . '>';
        $help = $aiuto !== '' ? '<p class="help rip__aiuto" id="' . $id . '-aiuto">' . Support::e($aiuto) . '</p>' : '';
        if (isset($sd['aiuto_con'])) $help .= '<p class="help rip__aiuto" data-solo-con="' . Support::e($sd['aiuto_con'][0]) . '" data-solo-valori="' . Support::e(implode(',', $sd['aiuto_con'][1])) . '">' . Support::e($sd['aiuto_con'][2]) . '</p>';
        $desc = $aiuto !== '' ? ' aria-describedby="' . $id . '-aiuto"' : '';
        if ($tipo === 'image' && !empty($sd['locandina'])) {
            // La locandina: una zona sola, immagine o PDF. Il server mette il file nel sottocampo giusto.
            $pn = $sd['locandina'];
            $mid = (int) $val; $pdfId = (int) ($v[$pn] ?? 0); $puoi = $r['foto'] || $r['pdf'];
            $sub = '[' . Support::e($nome) . '][' . $k . '][' . $sn . ']';
            $h .= $c . '<div class="field rip__media" style="margin:0"><span class="label">' . Support::e($et) . '</span>'
                . '<input type="hidden" name="' . $n . '" value="' . ($mid ?: '') . '">'
                . '<input type="hidden" name="' . Support::e($nome) . '[' . $k . '][' . Support::e($pn) . ']" value="' . ($pdfId ?: '') . '">'
                . '<div class="rip__file">';
            if ($mid && ($url = Media::url($mid))) $h .= '<img src="' . Support::e($url) . '" alt="">';
            elseif ($pdfId) $h .= '<span class="rip__pdf">' . Icon::svg('doc', 22) . '<span class="small">' . Support::e((Media::row($pdfId)['original_name'] ?? '') ?: 'Locandina') . '</span></span>';
            if ($puoi) {
                $h .= '<span class="rip__carica"><input class="drop__input" id="' . $id . '" type="file" name="rip_file' . $sub . '" accept="image/jpeg,image/png,image/webp,application/pdf" aria-describedby="' . $id . '-aiuto ' . $id . '-nome">'
                    . '<label class="btn btn--ghost btn--sm" for="' . $id . '">' . ($mid || $pdfId ? 'Sostituisci' : 'Scegli la locandina') . '</label></span>';
            }
            if ($mid || $pdfId) $h .= '<label class="check rip__togli"><input type="checkbox" name="rip_togli' . $sub . '" value="1"> <span>Togli</span></label>';
            if ($puoi) $h .= '<span class="small muted" id="' . $id . '-nome" data-rip-file-nome aria-live="polite">' . ($mid || $pdfId ? '' : 'Nessun file scelto') . '</span>';
            $h .= '</div>';
            $h .= $puoi ? '<p class="help rip__aiuto" id="' . $id . '-aiuto">' . Support::e(trim($aiuto . ' Si carica col bottone Salva.')) . '</p>'
                        : '<p class="help">Il tuo piano non comprende foto e PDF nelle sezioni.</p>';
            $h .= '</div></div>';
        } elseif ($tipo === 'image' || $tipo === 'pdf') {
            $mid = (int) $val; $puoi = $tipo === 'image' ? $r['foto'] : $r['pdf'];
            if (!$mid && !$puoi) continue;
            $sub = '[' . Support::e($nome) . '][' . $k . '][' . $sn . ']';
            $h .= $c . '<div class="field rip__media" style="margin:0"><span class="label">' . Support::e($et) . '</span>'
                . '<input type="hidden" name="' . $n . '" value="' . ($mid ?: '') . '">'
                . '<div class="rip__file">';
            if ($mid) {
                $url = $tipo === 'image' ? Media::url($mid) : null;
                $file = $tipo === 'pdf' ? ((Media::row($mid)['original_name'] ?? '') ?: 'Documento') : '';
                $h .= ($url ? '<img src="' . Support::e($url) . '" alt="">' : '<span class="rip__pdf">' . Icon::svg('doc', 22) . '<span class="small">' . Support::e($file) . '</span></span>');
            }
            // Il selettore del browser parla la lingua del sistema: l'input vero resta (nascosto
            // alla vista, non alla tastiera) e si mostra un bottone in italiano col nome scelto.
            if ($puoi) {
                $h .= '<span class="rip__carica"><input class="drop__input" id="' . $id . '" type="file" name="rip_file' . $sub . '" accept="'
                    . ($tipo === 'image' ? 'image/jpeg,image/png,image/webp' : 'application/pdf') . '" aria-describedby="' . $id . '-aiuto ' . $id . '-nome">'
                    . '<label class="btn btn--ghost btn--sm" for="' . $id . '">' . ($mid ? 'Sostituisci' : 'Scegli ' . ($tipo === 'image' ? 'una foto' : 'un PDF')) . '</label></span>';
            }
            if ($mid) $h .= '<label class="check rip__togli"><input type="checkbox" name="rip_togli' . $sub . '" value="1"> <span>Togli</span></label>';
            if ($puoi) $h .= '<span class="small muted" id="' . $id . '-nome" data-rip-file-nome aria-live="polite">' . ($mid ? '' : 'Nessun file scelto') . '</span>';
            $h .= '</div>';
            if (!$puoi) $h .= '<p class="help">Il tuo piano non comprende ' . ($tipo === 'image' ? 'le foto' : 'i PDF') . ' nelle sezioni: toglilo prima di pubblicare.</p>';
            else $h .= '<p class="help rip__aiuto" id="' . $id . '-aiuto">' . Support::e(trim($aiuto . ' ' . ($tipo === 'image' ? 'JPG, PNG o WebP.' : 'Solo PDF.') . ' Si carica col bottone Salva.')) . '</p>';
            $h .= '</div></div>';
        } elseif ($tipo === 'check') {
            $h .= $c . '<label class="check rip__check"><input type="checkbox" name="' . $n . '" value="1"' . ($val ? ' checked' : '') . '> <span>' . Support::e($et) . '</span></label></div>';
        } elseif ($tipo === 'choice' && !empty($sd['pillole'])) {
            // Una scelta a pillole: radio veri, usabili da tastiera con le frecce.
            $h .= $c . '<fieldset class="rip__pillole"><legend class="small">' . Support::e($et) . '</legend><div class="scelte scelte--riga">';
            foreach ($sd['options'] as $ok => $ol) {
                $h .= '<label class="scelta scelta--mini"><input type="radio" name="' . $n . '" value="' . Support::e((string) $ok) . '"' . ((string) $val === (string) $ok ? ' checked' : '') . '><span>' . Support::e($ol) . '</span></label>';
            }
            $h .= '</div></fieldset></div>';
        } elseif ($tipo === 'money') {
            // Un importo in euro: il simbolo sta fisso a destra, si scrive solo il numero.
            $h .= $c . '<div class="field" style="margin:0"><label for="' . $id . '">' . Support::e($et) . '<span class="sr-only"> (euro)</span></label>'
                . '<div class="soldi"><input id="' . $id . '" type="text" inputmode="decimal" name="' . $n . '" value="' . Support::e((string) $val) . '" maxlength="9" pattern="[0-9]{1,6}([.,][0-9]{1,2})?" title="Solo il numero, per esempio 1,50"' . $desc . $no . '>'
                . '<span class="soldi__euro" aria-hidden="true">€</span></div>' . $help . '</div></div>';
        } elseif ($tipo === 'choice') {
            $h .= $c . '<div class="field" style="margin:0"><label for="' . $id . '">' . Support::e($et) . '</label><select id="' . $id . '" name="' . $n . '"' . $desc . $no . '>';
            foreach ($sd['options'] as $ok => $ol) $h .= '<option value="' . Support::e($ok) . '"' . ((string) $val === (string) $ok ? ' selected' : '') . '>' . Support::e($ol) . '</option>';
            $h .= '</select>' . $help . '</div></div>';
        } elseif ($tipo === 'days') {
            $h .= $c . '<fieldset class="rip__giorni"><legend class="small">' . Support::e($et) . '</legend><div class="scelte scelte--riga">';
            foreach ($giorni as $gn => $gl) {
                $h .= '<label class="scelta scelta--mini"><input type="checkbox" name="' . $n . '[]" value="' . $gn . '"' . (in_array($gn, (array) $val, false) ? ' checked' : '') . '><span>' . $gl . '</span></label>';
            }
            $h .= '</div></fieldset></div>';
        } elseif ($tipo === 'textarea') {
            $h .= $c . '<div class="field" style="margin:0"><label for="' . $id . '">' . Support::e($et) . '</label>'
                . '<textarea id="' . $id . '" name="' . $n . '" rows="' . (!empty($sd['lines']) ? 4 : 2) . '" maxlength="2000"' . $desc . $no . '>' . Support::e((string) $val) . '</textarea>' . $help . '</div></div>';
        } else {
            $t = ['url' => 'url', 'tel' => 'tel', 'time' => 'time', 'date' => 'date'][$tipo] ?? 'text';
            $extra = $tipo === 'secret' ? ' spellcheck="false" data-segreto' : ($tipo === 'url' ? ' placeholder="https://"' : '');
            if (!empty($sd['cifre'])) $extra .= ' inputmode="numeric" pattern="[0-9]*"';
            // La password si legge con «Mostra»: senza JavaScript resta in chiaro, che è più comodo da scrivere.
            $mostra = $tipo === 'secret' ? '<button type="button" class="linkbtn rip__mostra" data-mostra-segreto hidden>Mostra</button>' : '';
            $h .= $c . '<div class="field" style="margin:0"><label for="' . $id . '">' . Support::e($et) . '</label>'
                . ($mostra ? '<div class="rip__segreto">' : '')
                . '<input id="' . $id . '" type="' . $t . '" name="' . $n . '" value="' . Support::e((string) $val) . '" maxlength="' . ($tipo === 'url' ? 500 : (!empty($sd['cifre']) ? 3 : 300)) . '"' . $extra . $desc . $no . '>'
                . ($mostra ? $mostra . '</div>' : '') . $help . '</div></div>';
        }
    }
    $h .= '</div></div>';
    return $h;
}; ?>
<fieldset class="fieldset rip" id="<?= $domId ?>" data-rip data-rip-max="<?= (int) $r['max'] ?>">
  <legend><?= Support::e($r['legend']) ?></legend>
  <?php if ($r['help'] !== ''): ?><p class="help"><?= Support::e($r['help']) ?></p><?php endif; ?>
  <div class="rip__righe" data-rip-righe>
    <?php foreach (array_values($r['rows']) as $i => $v) echo $riga((string) $i, $v, $i + 1, true); ?>
    <?php /* Senza JavaScript serve una riga vuota già pronta; con JavaScript si toglie se ce n'è già un'altra. */
          echo str_replace('data-rip-riga', 'data-rip-riga data-rip-vuota', $riga((string) count($r['rows']), [], count($r['rows']) + 1)); ?>
  </div>
  <template data-rip-modello><?= $riga('__K__', []) ?></template>
  <button type="button" class="linkbtn" data-rip-aggiungi hidden><?= Icon::svg('plus', 15, 2) ?> <?= Support::e($r['add']) ?></button>
  <?php if ($r['presets']): /* righe pronte: si aggiungono già compilate, poi si correggono */ ?>
    <div class="suggerimenti" data-solo-js hidden>
      <span class="small muted">Aggiungi al volo:</span>
      <?php foreach ($r['presets'] as $et => $valori): ?>
        <button type="button" class="chip-sugg" data-rip-preset="<?= Support::e(json_encode($valori, JSON_UNESCAPED_UNICODE)) ?>">+ <?= Support::e($et) ?></button>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</fieldset>
