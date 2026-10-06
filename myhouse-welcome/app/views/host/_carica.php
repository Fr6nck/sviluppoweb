<?php
/* La zona di caricamento: al posto del «Choose File» del browser, che parla la
   lingua del sistema e non quella del sito. L'input vero resta (nascosto alla
   vista, non alla tastiera): senza JavaScript funziona lo stesso.
   Riceve: $carica = [
     'id', 'name', 'accept', 'aiuto',
     'cosa'   => 'il logo' (per «Trascina qui il logo»),
     'url'    => immagine attuale o null,
     'file'   => nome del file attuale (PDF) o '',
     'togli'  => valore del bottone azione per rimuovere quello salvato, o '',
     'togliNome', 'togliVerso' => nome del bottone (predefinito «azione») e,
                  se serve, l'indirizzo a cui mandarlo (formaction),
     'logo'   => true per non ritagliare l'anteprima,
   ] */
use MHW\{Support, Icon};
$c = $carica + ['url' => null, 'file' => '', 'togli' => '', 'togliNome' => 'azione', 'togliVerso' => '', 'logo' => false, 'cosa' => 'il file', 'aiuto' => ''];
$pieno = $c['url'] || $c['file'] !== '';
$id = Support::e($c['id']); ?>
<div class="drop<?= $pieno ? ' drop--pieno' : '' ?><?= $c['logo'] ? ' drop--logo' : '' ?>" data-drop>
  <input class="drop__input" type="file" id="<?= $id ?>" name="<?= Support::e($c['name']) ?>" accept="<?= Support::e($c['accept']) ?>"
         aria-describedby="<?= $id ?>-aiuto">
  <label class="drop__vuoto" for="<?= $id ?>">
    <span class="drop__piu" aria-hidden="true"><?= Icon::svg('plus', 20, 2) ?></span>
    <span><b>Trascina qui <?= Support::e($c['cosa']) ?> o <span class="drop__scegli">scegli un file</span></b>
      <span class="help" id="<?= $id ?>-aiuto"><?= Support::e($c['aiuto']) ?></span></span>
  </label>
  <div class="drop__pieno">
    <span class="drop__mini" data-drop-mini><?php if ($c['url']): ?><img src="<?= Support::e($c['url']) ?>" alt=""><?php else: ?><?= Icon::svg('doc', 26) ?><?php endif; ?></span>
    <span class="drop__info"><b data-drop-nome><?= Support::e($c['file'] !== '' ? $c['file'] : 'Immagine attuale') ?></b>
      <span class="help" data-drop-peso><?= $c['url'] ? 'Già salvata' : 'Già allegato' ?></span></span>
    <span class="drop__azioni">
      <label class="btn btn--ghost btn--sm" for="<?= $id ?>">Sostituisci</label>
      <?php if ($c['togli'] !== ''): ?>
        <button class="btn btn--quiet btn--sm" name="<?= Support::e($c['togliNome']) ?>" value="<?= Support::e($c['togli']) ?>" formnovalidate data-drop-rimuovi
                <?= $c['togliVerso'] !== '' ? 'formaction="' . Support::e($c['togliVerso']) . '"' : '' ?>>Togli</button>
      <?php endif; ?>
      <button type="button" class="btn btn--quiet btn--sm" data-drop-annulla>Togli</button>
    </span>
  </div>
</div>
