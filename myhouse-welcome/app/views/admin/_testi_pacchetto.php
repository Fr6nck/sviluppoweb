<?php use MHW\Support; $pp = $p; ?>
<div class="grid grid-2">
  <div class="field" style="margin:0"><label>Titolo</label><input type="text" name="headline" maxlength="160" value="<?= Support::e($pp['headline']) ?>"></div>
  <div class="field" style="margin:0"><label>Ideale per <span class="muted">(sulla landing sotto il nome; comincia con «Per chi…» o «Per B&amp;B…»)</span></label><input type="text" name="tagline" maxlength="200" value="<?= Support::e($pp['tagline']) ?>"></div>
</div>
<div class="field" style="margin:0"><label>Descrizione</label><textarea name="description" rows="2" maxlength="600"><?= Support::e($pp['description']) ?></textarea></div>
<div class="field" style="margin:0"><label>Elenco puntato <span class="muted">(una voce per riga; una riga che finisce con i due punti diventa un titoletto)</span></label><textarea name="bullets" rows="5" aria-describedby="bullets-aiuto"><?= Support::e($pp['bullets']) ?></textarea>
  <p class="tiny muted" id="bullets-aiuto" style="margin:4px 0 0">Scrivi <code>{sezioni}</code> e nelle schede compare il numero di sezioni pronte del catalogo (oggi <?= MHW\SectionCatalog::quante() ?>): per esempio «Tutte le {sezioni} sezioni». Si aggiorna da solo quando arriva una sezione nuova.</p></div>
<div class="grid grid-2">
  <div class="field" style="margin:0"><label>Etichetta in evidenza <span class="muted">(per esempio: Consigliato)</span></label><input type="text" name="badge" maxlength="40" value="<?= Support::e($pp['badge']) ?>"></div>
  <div class="field" style="margin:0"><label>Testo del bottone</label><input type="text" name="cta_label" maxlength="60" value="<?= Support::e($pp['cta_label']) ?>"></div>
</div>
<?php if (array_key_exists('tagline_en', $pp)): /* 8E: la scheda in inglese per /en; vuoto = si mostra l'italiano */ ?>
<details class="pacchetto-en">
  <summary>English <span class="small muted">· la scheda sulla home inglese (/en); un campo vuoto mostra l'italiano</span></summary>
  <div class="stack" style="gap:10px;margin-top:10px" lang="en">
    <div class="grid grid-2">
      <div class="field" style="margin:0"><label>Title (English)</label><input type="text" name="headline_en" maxlength="160" value="<?= Support::e((string) $pp['headline_en']) ?>"></div>
      <div class="field" style="margin:0"><label>Ideal for (English) <span class="muted">(starts with «For…»)</span></label><input type="text" name="tagline_en" maxlength="250" value="<?= Support::e((string) $pp['tagline_en']) ?>"></div>
    </div>
    <div class="field" style="margin:0"><label>Description (English)</label><textarea name="description_en" rows="2" maxlength="600"><?= Support::e((string) $pp['description_en']) ?></textarea></div>
    <div class="field" style="margin:0"><label>Bullet list (English) <span class="muted">(one per line; {sezioni} works here too)</span></label><textarea name="bullets_en" rows="5"><?= Support::e((string) $pp['bullets_en']) ?></textarea></div>
    <div class="grid grid-2">
      <div class="field" style="margin:0"><label>Badge (English)</label><input type="text" name="badge_en" maxlength="40" value="<?= Support::e((string) $pp['badge_en']) ?>"></div>
      <div class="field" style="margin:0"><label>Button text (English)</label><input type="text" name="cta_label_en" maxlength="60" value="<?= Support::e((string) $pp['cta_label_en']) ?>"></div>
    </div>
  </div>
</details>
<?php endif; ?>
<div class="row"><label class="check"><input type="checkbox" name="public" value="1" <?= $pp['public'] ? 'checked' : '' ?>> Visibile sulla landing</label>
  <label class="check"><input type="checkbox" name="active" value="1" <?= $pp['active'] ? 'checked' : '' ?>> In vendita</label></div>
