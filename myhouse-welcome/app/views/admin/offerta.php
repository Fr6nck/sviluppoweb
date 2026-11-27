<?php
/* 9N · 10 bis · «Prepara l'offerta»: dal preventivo (o dalla scheda del cliente, per più strutture) strutture, prezzo annuo
   totale, scadenza, nota, sconto sul rinnovo deciso a mano; accanto il listino a scaglioni per confronto. «Manda l'offerta»
   la salva e la manda per email; «Anteprima email» la mostra senza mandarla. Sotto, le offerte già fatte.
   Riceve $dest (preventivo_id, account_id, email, nome, lingua, titolo, sotto), $v (i valori del modulo), $offerte, $attiva
   (l'offerta che il cliente paga adesso, o null), $azione (dove mandare il modulo), $minimo. */
use function MHW\b;
use MHW\{Support, Csrf, Offerte};
$title = 'Offerta su misura';
$listinoN = Offerte::listino((int) $v['strutture']);
$prezzoV = Offerte::euro((string) $v['prezzo']);
$diff = $listinoN > 0 && $prezzoV !== null ? (int) round(($prezzoV - $listinoN) * 100 / $listinoN) : null;
$pv = Offerte::versione(); ?>
<div class="stack stack--lg" style="max-width:980px">
  <div class="stack stack--sm">
    <h1>Offerta su misura<?= $dest['titolo'] !== '' ? ' · ' . Support::e($dest['titolo']) : '' ?>.</h1>
    <p class="muted" style="margin:0"><?= Support::e($dest['sotto']) ?></p>
  </div>

  <form method="post" action="<?= b() . Support::e($azione) ?>" class="panel stack" style="gap:14px"><?= Csrf::field() ?>
    <div class="grid grid-4">
      <div class="field" style="margin:0"><label for="of-n">Strutture</label>
        <input id="of-n" name="strutture" type="number" min="<?= (int) $minimo ?>" max="500" required value="<?= (int) $v['strutture'] ?>"></div>
      <div class="field" style="margin:0"><label for="of-p">Prezzo annuo totale (€, IVA esclusa)</label>
        <input id="of-p" name="prezzo" type="text" inputmode="decimal" required value="<?= Support::e((string) $v['prezzo']) ?>" placeholder="640,00"></div>
      <div class="field" style="margin:0"><label for="of-r">Al rinnovo</label>
        <input id="of-r" type="text" value="stesso prezzo" readonly aria-describedby="of-r-aiuto"><p class="help" id="of-r-aiuto">Il prezzo concordato vale anche ai rinnovi.</p></div>
      <div class="field" style="margin:0"><label for="of-f">Offerta valida fino al</label>
        <input id="of-f" name="valida_fino" type="date" required min="<?= gmdate('Y-m-d') ?>" value="<?= Support::e((string) $v['valida_fino']) ?>"></div>
    </div>
    <div class="grid grid-2">
      <div class="field" style="margin:0"><label for="of-s">Sconto concordato sul rinnovo (facoltativo, a mano)</label>
        <input id="of-s" name="sconto_rinnovo" type="number" min="0" max="90" value="<?= (int) $v['sconto_rinnovo'] ?>"> <span class="small muted">%</span></div>
      <div class="field" style="margin:0"><label for="of-note">Nota per il cliente</label>
        <input id="of-note" name="note" type="text" maxlength="1000" value="<?= Support::e((string) $v['note']) ?>" placeholder="Prezzo bloccato anche al rinnovo."></div>
    </div>
    <?php if ($attiva): ?>
      <fieldset class="fieldset" style="margin:0"><legend class="legend">Il cliente ha già un'offerta (<?= (int) $attiva['strutture'] ?> strutture, <?= Support::e(Support::money((int) $attiva['prezzo_cents'])) ?> + IVA): la nuova la sostituisce</legend>
        <div class="row" style="gap:16px;flex-wrap:wrap">
          <label class="check"><input type="radio" name="modo" value="rinnovo"<?= $v['modo'] !== 'subito' ? ' checked' : '' ?>> <span>Dal prossimo rinnovo</span></label>
          <label class="check"><input type="radio" name="modo" value="subito"<?= $v['modo'] === 'subito' ? ' checked' : '' ?>> <span>Subito, con il conguaglio dei giorni che restano</span></label>
        </div></fieldset>
    <?php endif; ?>
    <p class="note" style="margin:0">Listino per confronto: <?= (int) $v['strutture'] ?> strutture a scaglioni = <b><?= Support::e(Support::money($listinoN)) ?></b><?= $pv ? ' (Portfolio v' . (int) $pv['version'] . ')' : '' ?>.
      <?php if ($prezzoV !== null && $diff !== null): ?>Proposta: <b><?= Support::e(Support::money($prezzoV)) ?></b> (<?= $diff > 0 ? '+' : '' ?><?= $diff ?>%).<?php endif; ?><br>
      Inviti e «Giudizio con sconto» <b>non si applicano da soli</b> alle offerte su misura: li vedi nella scheda del cliente come promemoria e decidi tu lo sconto sul rinnovo.</p>
    <div class="actions">
      <button class="btn" name="azione" value="manda">Manda l'offerta</button>
      <button class="btn btn--ghost" name="azione" value="anteprima" formtarget="_blank">Anteprima email</button>
      <span class="small muted">A <?= Support::e($dest['email']) ?><?= $dest['lingua'] === 'en' ? ' · in inglese' : '' ?></span>
    </div>
  </form>

  <?php if ($offerte): ?>
  <section class="stack" style="gap:10px" aria-labelledby="of-elenco">
    <h2 id="of-elenco" style="font-size:22px">Offerte fatte</h2>
    <div class="tablewrap"><table class="data">
      <thead><tr><th>Del</th><th>Strutture</th><th>Prezzo</th><th>Valida fino al</th><th>Modo</th><th>Stato</th><th></th></tr></thead>
      <tbody><?php foreach ($offerte as $x): $link = Support::baseUrl() . '/offerta/' . $x['token']; ?>
        <tr><td class="small"><?= Support::e(Support::date($x['created_at'])) ?></td><td><?= (int) $x['strutture'] ?></td>
          <td><?= Support::e(Support::money((int) $x['prezzo_cents'])) ?> + IVA<?= (int) $x['sconto_rinnovo_pct'] ? '<br><span class="tiny muted">sconto rinnovo ' . (int) $x['sconto_rinnovo_pct'] . '%</span>' : '' ?></td>
          <td class="small"><?= Support::e(Support::date($x['valida_fino'])) ?></td><td class="small"><?= Support::e(Offerte::MODI[$x['modo']] ?? $x['modo']) ?></td>
          <td><span class="badge badge--<?= ['inviata' => 'sea', 'accettata' => 'pine', 'scaduta' => 'alert', 'annullata' => 'paper'][$x['stato']] ?? 'paper' ?>"><?= Support::e(Offerte::STATI[$x['stato']] ?? $x['stato']) ?></span></td>
          <td class="small"><a href="<?= Support::e($link) ?>" target="_blank" rel="noopener">Apri</a>
            <?php if ($x['stato'] === 'inviata'): ?>
              <form method="post" action="<?= b() ?>/admin/offerte/<?= (int) $x['id'] ?>/annulla" style="display:inline"><?= Csrf::field() ?><button class="linkbtn">Annulla</button></form>
            <?php endif; ?></td></tr>
      <?php endforeach; ?></tbody></table></div>
  </section>
  <?php endif; ?>
</div>
