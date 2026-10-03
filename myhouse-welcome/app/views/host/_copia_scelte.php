<?php
/* Cosa copiare da un'altra struttura: le sezioni (già spuntate), l'aspetto e i contatti.
   Quello che è solo di una casa non compare mai: indirizzo, CIN, reti Wi-Fi, passaggi
   di arrivo, foto di copertina, parcheggio.
   Riceve, facoltativi: $sezioniOrigine (da Copia::proposta, con 'esiste'), $nomeDestinazione. */
use MHW\{Support, SectionCatalog, Copia};
$sezioniOrigine = $sezioniOrigine ?? null;
$elenco = $sezioniOrigine ?? array_map(fn($k) => ['kind' => $k, 'titolo' => SectionCatalog::title($k, 'it'), 'esiste' => false], Copia::SEZIONI); ?>
<fieldset class="fieldset" style="margin:0">
  <legend>Cosa copiare</legend>
  <p class="help">Con le traduzioni, i luoghi, le foto e i PDF (duplicati: le due guide restano indipendenti).
    <?= $sezioniOrigine === null ? 'Le sezioni che la struttura di origine non ha si ignorano.' : '' ?></p>
  <div class="stack" style="gap:8px">
    <?php foreach ($elenco as $x): $id = 'copia-' . $x['kind']; ?>
      <div class="copia__voce">
        <label class="check" for="<?= $id ?>"><input type="checkbox" id="<?= $id ?>" name="copia[]" value="<?= Support::e($x['kind']) ?>" checked>
          <span><?= Support::e($x['titolo']) ?></span></label>
        <?php if ($x['esiste']): ?>
          <fieldset class="copia__esiste">
            <legend class="small">C'è già in <?= Support::e($nomeDestinazione ?? 'questa struttura') ?>:</legend>
            <div class="scelte scelte--riga">
              <label class="scelta scelta--mini"><input type="radio" name="esistenti[<?= Support::e($x['kind']) ?>]" value="salta" checked><span>Saltala</span></label>
              <label class="scelta scelta--mini"><input type="radio" name="esistenti[<?= Support::e($x['kind']) ?>]" value="sostituisci"><span>Sostituiscila</span></label>
            </div>
          </fieldset>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    <label class="check"><input type="checkbox" name="copia_aspetto" value="1" checked> <span>Aspetto: palette, logo e tono del testo</span></label>
    <label class="check"><input type="checkbox" name="copia_contatti" value="1" checked> <span>Contatti</span></label>
  </div>
  <p class="small muted" style="margin-top:10px">Non si copiano mai: indirizzo, CIN, reti Wi-Fi, passaggi di arrivo, foto di copertina, parcheggio.</p>
</fieldset>
