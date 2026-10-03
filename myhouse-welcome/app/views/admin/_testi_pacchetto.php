<?php use MHW\Support; $pp = $p; ?>
<div class="grid grid-2">
  <div class="field" style="margin:0"><label>Titolo</label><input type="text" name="headline" maxlength="160" value="<?= Support::e($pp['headline']) ?>"></div>
  <div class="field" style="margin:0"><label>Sottotitolo breve</label><input type="text" name="tagline" maxlength="200" value="<?= Support::e($pp['tagline']) ?>"></div>
</div>
<div class="field" style="margin:0"><label>Descrizione</label><textarea name="description" rows="2" maxlength="600"><?= Support::e($pp['description']) ?></textarea></div>
<div class="field" style="margin:0"><label>Elenco puntato (una voce per riga)</label><textarea name="bullets" rows="5"><?= Support::e($pp['bullets']) ?></textarea></div>
<div class="grid grid-2">
  <div class="field" style="margin:0"><label>Etichetta (es. Più scelto)</label><input type="text" name="badge" maxlength="40" value="<?= Support::e($pp['badge']) ?>"></div>
  <div class="field" style="margin:0"><label>Testo del bottone</label><input type="text" name="cta_label" maxlength="60" value="<?= Support::e($pp['cta_label']) ?>"></div>
</div>
<div class="row"><label class="check"><input type="checkbox" name="public" value="1" <?= $pp['public'] ? 'checked' : '' ?>> Visibile sulla landing</label>
  <label class="check"><input type="checkbox" name="active" value="1" <?= $pp['active'] ? 'checked' : '' ?>> In vendita</label></div>
