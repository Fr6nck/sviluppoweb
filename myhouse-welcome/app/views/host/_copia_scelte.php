<?php use function MHW\t, MHW\tf;
/* Cosa copiare da un'altra struttura: le sezioni (già spuntate), l'aspetto e i contatti.
   Quello che è solo di una casa non compare mai: indirizzo, CIN, reti Wi-Fi, passaggi
   di arrivo, foto di copertina. 9M: il parcheggio sì, segnato «da controllare».
   Riceve, facoltativi: $sezioniOrigine (da Copia::proposta, con 'esiste'), $nomeDestinazione. */
use MHW\{Support, SectionCatalog, Copia};
$sezioniOrigine = $sezioniOrigine ?? null;
$elenco = $sezioniOrigine ?? array_map(fn($k) => ['kind' => $k, 'titolo' => SectionCatalog::title($k, 'it'), 'esiste' => false], Copia::SEZIONI); ?>
<fieldset class="fieldset" style="margin:0">
  <legend><?= t('Cosa copiare') ?></legend>
  <p class="help"><?= !empty($da['campione'])
      ? t('Ogni sezione si copia con le sue traduzioni, i luoghi, le foto e i PDF. Dalla scheda campione le sezioni copiate restano collegate: quando cambi la campione ti proponiamo di aggiornarle, e niente cambia da solo.')
      : t('Ogni sezione si copia con le sue traduzioni, i luoghi, le foto e i PDF. Le due guide restano indipendenti: quello che cambi in una non cambia nell\'altra.') ?>
    <?= $sezioniOrigine === null ? t('Le sezioni che la struttura di origine non ha vengono saltate.') : '' ?></p>
  <div class="stack" style="gap:8px">
    <?php foreach ($elenco as $x): $id = 'copia-' . $x['kind']; ?>
      <div class="copia__voce">
        <label class="check" for="<?= $id ?>"><input type="checkbox" id="<?= $id ?>" name="copia[]" value="<?= Support::e($x['kind']) ?>" checked>
          <span><?= Support::e($x['titolo']) ?><?php if (in_array($x['kind'], Copia::DA_CONTROLLARE, true)): ?> <span class="small muted"><?= t('· copiata e segnata «da controllare»') ?></span><?php endif; ?></span></label>
        <?php if ($x['esiste']): ?>
          <fieldset class="copia__esiste">
            <legend class="small"><?= Support::e(tf('C\'è già in {nome}:', ['{nome}' => $nomeDestinazione ?? t('questa struttura')])) ?></legend>
            <div class="scelte scelte--riga">
              <label class="scelta scelta--mini"><input type="radio" name="esistenti[<?= Support::e($x['kind']) ?>]" value="salta" checked><span><?= t('Saltala') ?></span></label>
              <label class="scelta scelta--mini"><input type="radio" name="esistenti[<?= Support::e($x['kind']) ?>]" value="sostituisci"><span><?= t('Sostituiscila') ?></span></label>
            </div>
          </fieldset>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    <label class="check"><input type="checkbox" name="copia_aspetto" value="1" checked> <span><?= t('Aspetto: palette, tema e logo') ?></span></label>
    <label class="check"><input type="checkbox" name="copia_contatti" value="1" checked> <span><?= t('Contatti') ?></span></label>
    <?php /* 9V · «La casa»: la galleria e la descrizione. Con la proposta ($casaOrigine): solo se l'origine ne ha una; se la struttura di arrivo ha già la sua non si tocca. */
          $casaOrigine = $casaOrigine ?? null; $casaEsiste = $casaEsiste ?? false;
          if ($sezioniOrigine === null || $casaOrigine !== null): ?>
      <label class="check"><input type="checkbox" name="copia_casa" value="1" <?= $casaEsiste ? 'disabled' : 'checked' ?>>
        <span><?= t('La casa: foto e descrizione') ?><?php if ($casaOrigine): ?> <span class="small muted">· <?= Support::e($casaOrigine === 1 ? t('1 foto') : tf('{n} foto', ['{n}' => (int) $casaOrigine])) ?></span><?php endif; ?>
          <?php if ($casaEsiste): ?><span class="small muted">· <?= Support::e(tf('{nome} ha già la sua: non si tocca', ['{nome}' => $nomeDestinazione ?? t('questa struttura')])) ?></span><?php endif; ?></span></label>
    <?php endif; ?>
  </div>
  <p class="small muted" style="margin-top:10px"><?= t('Non si copiano mai, perché sono solo di una struttura: indirizzo, CIN, reti Wi-Fi, passaggi per entrare, foto di copertina.') ?></p>
</fieldset>
