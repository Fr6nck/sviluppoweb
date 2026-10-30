<?php
/* 9U · Le caselle della promo di un codice sconto (mockup promo-novembre, blocco 3): con che cosa si somma e se va in home.
   Oggi un codice scritto a mano non si somma a niente («vale il più alto»): con una di queste caselle entra tra gli sconti
   del primo anno e si somma in una sola percentuale, con un solo coupon su Stripe (mhw-primo-anno-N). Solo a percentuale.
   Riceve $vP (somma_giudizio, somma_invito, in_home, nome_promo). Nel modulo del codice nuovo e in quello della scheda. */
use MHW\Support;
if (!MHW\Migrator::columnExists('discount_codes', 'in_home')) return; ?>
<fieldset class="fieldset" style="margin:0"><legend>Promo (solo a percentuale)</legend>
  <div class="scelte scelte--riga">
    <label class="scelta scelta--mini"><input type="checkbox" name="somma_giudizio" value="1" <?= !empty($vP['somma_giudizio']) ? 'checked' : '' ?>><span>Si somma al «Giudizio con sconto»</span></label>
    <label class="scelta scelta--mini"><input type="checkbox" name="somma_invito" value="1" <?= !empty($vP['somma_invito']) ? 'checked' : '' ?>><span>Si somma all'invito di un amico</span></label>
    <label class="scelta scelta--mini"><input type="checkbox" name="in_home" value="1" <?= !empty($vP['in_home']) ? 'checked' : '' ?>><span>Mostra in home (badge, prezzi barrati, fascia)</span></label>
  </div>
  <div class="field" style="margin:8px 0 0"><label for="sc-promo-nome">Nome della promo</label>
    <input id="sc-promo-nome" name="nome_promo" type="text" maxlength="80" value="<?= Support::e((string) ($vP['nome_promo'] ?? '')) ?>" placeholder="Promo novembre" aria-describedby="sc-promo-aiuto">
    <p class="help" id="sc-promo-aiuto">Nella fascia e nel riepilogo («Promo novembre NOVEMBRE17 · −17%»). In home la promo compare dal primo all'ultimo giorno di validità e sparisce da sola; solo per chi non ha ancora pagato (come tutti i codici). Si somma sempre al codice di un partner (la commissione del partner resta sull'imponibile pagato); mai ai codici omaggio. I testi della fascia sono in Testi e foto → Promo.</p></div>
</fieldset>
