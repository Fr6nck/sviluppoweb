<?php
/* =====================================================================
   PROMPT PER CLAUDE CODE — copialo così com'è in una sessione nuova (Sonnet)
   ---------------------------------------------------------------------
   Implementa SOLO la sezione «Il tempo delle risposte» della home, usando
   il file _tempo.php che ti ho dato. Non modificare nient'altro del sito.

   1. Copia _tempo.php in app/views/pub/_tempo.php senza cambiarne il
      contenuto.
   2. Apri SOLO app/views/pub/home.php. Trova il blocco che comincia con il
      commento «<?php /* Il tempo che non vedi» e finisce con il </section>
      di <section id="il-tempo" class="tempo" …>, subito prima del commento
      «<?php /* La frase grande». Sostituisci quel blocco, e solo quello,
      con questa riga:
      <?php include __DIR__ . '/_tempo.php'; ?>
   3. Non toccare altri file: niente CSS, JavaScript, rotte, traduzioni,
      database. Gli stili e lo script della sezione sono già dentro
      _tempo.php.
   4. Verifica: php -l app/views/pub/_tempo.php app/views/pub/home.php;
      poi curl della home: deve rispondere 200 e contenere «Tempo
      risparmiato». Niente browser né screenshot.
   5. Rispondi in massimo 5 righe: file toccati e risultato delle
      verifiche. Non incollare codice. Niente zip.
   ---------------------------------------------------------------------
   Cosa fa la sezione: per ogni prenotazione mostra il dato di riferimento
   (fisso) e il tempo dell'host (modificabile con un cursore) per le
   comunicazioni necessarie e per le domande evitabili con una guida; poi
   il valore in euro del tempo risparmiato, confrontato con il prezzo del
   piano più economico ($partenza, già presente in home.php). Funziona anche
   senza JavaScript con i valori di partenza.
   ===================================================================== */
use MHW\Support;
$t2 = ['nec' => 16, 'evit' => 19, 'pren' => 6, 'euro' => 20];
$t2Ore = fn(float $h): string => $h >= 10 ? (string) round($h) : str_replace('.', ',', (string) round($h, 1));
$t2Tot = $t2['nec'] + $t2['evit'];
$t2Gu = $t2['pren'] * ($t2['evit'] / 2) * 12 / 60;
$t2Gg = round($t2Gu / 8 * 2) / 2;
?>
<section id="il-tempo" class="t2" aria-labelledby="t2-titolo">
  <div class="t2__head">
    <span class="t2__kicker">Il tempo delle risposte</span>
    <h2 id="t2-titolo" class="t2__titolo">L'accoglienza è tua. Le stesse domande, ogni volta, no.</h2>
    <p class="t2__lead">Il contatto con l'ospite è la parte più bella di questo lavoro, e una parte resta tua. L'altra è fatta di domande a cui hai già risposto cento volte. Su una prenotazione tipo, i conti sono questi.</p>
  </div>

  <div class="t2__totale">
    <div class="t2__totrow"><span class="t2__tot">Una prenotazione: <span data-o="tot"><?= $t2Tot ?></span> minuti di messaggi</span>
      <span class="t2__nota">gestiti a mano, dalla conferma alla partenza</span></div>
    <div class="t2__barra" aria-hidden="true">
      <span class="t2__barra-nec" data-bar="nec" style="flex-grow:<?= $t2['nec'] ?>"></span>
      <span class="t2__barra-evit" data-bar="evit" style="flex-grow:<?= $t2['evit'] ?>"></span>
    </div>
  </div>

  <div class="t2__colonne">
    <div class="t2__col">
      <div class="t2__testa"><span class="t2__etichetta">Comunicazioni importanti e necessarie</span>
        <span class="t2__rif"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>Riferimento: <b>16 min</b> a prenotazione</span></div>
      <label class="t2__cursore"><span>Il tuo tempo <b class="t2__min"><span data-o="nec"><?= $t2['nec'] ?></span> min</b></span>
        <input type="range" min="5" max="40" step="1" value="<?= $t2['nec'] ?>" data-t2="nec" aria-label="Il tuo tempo per le comunicazioni necessarie, in minuti per prenotazione"></label>
      <p class="t2__desc">Restano tue: è qui che si fa l'accoglienza.</p>
      <ul class="t2__lista">
        <li>Accordi prima dell'arrivo</li><li>Imprevisti al check-in</li>
        <li>Richieste particolari durante il soggiorno</li><li>Accordi per la partenza</li>
      </ul>
    </div>
    <div class="t2__col">
      <div class="t2__testa"><span class="t2__etichetta">Domande evitabili con una guida</span>
        <span class="t2__rif"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>Riferimento: <b>19 min</b> a prenotazione</span></div>
      <label class="t2__cursore"><span>Il tuo tempo <b class="t2__min t2__min--acc"><span data-o="evit"><?= $t2['evit'] ?></span> min</b></span>
        <input type="range" min="5" max="60" step="1" value="<?= $t2['evit'] ?>" data-t2="evit" aria-label="Il tuo tempo per le domande evitabili, in minuti per prenotazione"></label>
      <p class="t2__desc">Servono all'ospite, ma non richiedono il tuo tempo se le trova scritte.</p>
      <ul class="t2__lista">
        <li>Accesso, parcheggio, Wi-Fi</li><li>La casa e gli elettrodomestici</li>
        <li>Ristoranti, trasporti, cosa vedere</li><li>Orari e istruzioni per la partenza</li>
      </ul>
    </div>
  </div>

  <div class="t2__conto">
    <div class="t2__input">
      <span class="t2__conto-titolo">Fai il conto sulla tua struttura</span>
      <label class="t2__cursore t2__cursore--chiaro"><span>Prenotazioni al mese <b data-o="pren"><?= $t2['pren'] ?></b></span>
        <input type="range" min="1" max="30" step="1" value="<?= $t2['pren'] ?>" data-t2="pren"></label>
      <label class="t2__cursore t2__cursore--chiaro"><span>Quanto vale un'ora del tuo tempo <b><span data-o="euro"><?= $t2['euro'] ?></span> €</b></span>
        <input type="range" min="10" max="50" step="1" value="<?= $t2['euro'] ?>" data-t2="euro"></label>
      <p class="t2__contesto">Oggi, in un anno: <b><span data-o="oreAnno"><?= $t2Ore($t2['pren'] * $t2Tot * 12 / 60) ?></span> ore</b> di messaggi, di cui <span data-o="oreEvit"><?= $t2Ore($t2['pren'] * $t2['evit'] * 12 / 60) ?></span> di domande evitabili.</p>
    </div>
    <div class="t2__risultato" aria-live="polite">
      <span class="t2__kicker t2__kicker--chiaro">Tempo risparmiato in un anno</span>
      <span class="t2__euro"><span data-o="valore"><?= number_format($t2Gu * $t2['euro'], 0, ',', '.') ?></span> €</span>
      <span class="t2__sotto"><span data-o="oreG"><?= $t2Ore($t2Gu) ?></span> ore, cioè <span data-o="giornate"><?= str_replace('.', ',', (string) $t2Gg) ?> <?= $t2Gg == 1 ? 'giornata' : 'giornate' ?></span> di lavoro, a <span data-o="euro2"><?= $t2['euro'] ?></span> € l'ora</span>
      <?php if (!empty($partenza)): $t2Prezzo = (int) round((int) $partenza['price_cents'] / 100); $t2Diff = (int) round($t2Gu * $t2['euro']) - $t2Prezzo; ?>
      <dl class="t2__bilancio" data-prezzo="<?= $t2Prezzo ?>">
        <div><dt>Valore del tempo risparmiato</dt><dd><span data-o="valore"><?= number_format($t2Gu * $t2['euro'], 0, ',', '.') ?></span> €</dd></div>
        <div><dt>Abbonamento <?= Support::e($partenza['name'] ?? '') ?>, IVA esclusa</dt><dd>− <?= $t2Prezzo ?> €</dd></div>
        <div class="t2__diff"><dt>Differenza a tuo favore</dt><dd><span data-o="diff"><?= ($t2Diff < 0 ? '− ' : '+ ') . number_format(abs($t2Diff), 0, ',', '.') ?></span> €</dd></div>
      </dl>
      <?php endif; ?>
      <span class="t2__piccolo">Contiamo la metà dei tuoi minuti di domande evitabili: qualche ospite chiederà comunque.</span>
    </div>
  </div>

  <div class="t2__prove">
    <div><span class="t2__num">60–70%</span><span>dei messaggi ricevuti dagli host italiani chiede informazioni ripetitive.</span></div>
    <div><span class="t2__num">88,7%</span><span>degli ospiti vuole istruzioni dettagliate su accesso e parcheggio prima di arrivare.</span></div>
  </div>
  <p class="t2__fonti">35 minuti: stima di MyHouse Welcome su una prenotazione tipo, dentro le stime pubblicate nel 2026 (stime pubblicate nel 2026 da aziende del settore, tra cui Guestar su dati StayReply). I riferimenti di 16 e 19 minuti sono la nostra ripartizione di quei 35 minuti, coerente con il 60–70% di messaggi ripetitivi rilevato da Verto AI; il tempo risparmiato è un'ipotesi prudente. 60–70%: Verto AI, aprile 2026, conversazioni di gestori italiani. 88,7%: sondaggio Touch Stay tra gli ospiti, 2026. Stime di aziende del settore, non statistiche ufficiali.</p>
</section>

<style>
.t2{margin:72px auto 0;max-width:1180px;background:var(--inverse-bg,#231b12);color:var(--inverse-fg,#faf5ec);border-radius:var(--r-2xl,34px);padding:clamp(28px,5vw,64px) clamp(20px,4vw,56px);display:flex;flex-direction:column;gap:36px}
.t2__head{display:flex;flex-direction:column;gap:14px;max-width:44em}
.t2__kicker{font-size:11px;font-weight:600;letter-spacing:.14em;text-transform:uppercase;color:#ee7a4a}
.t2__kicker--chiaro{color:inherit;opacity:.85}
.t2__titolo{font-size:clamp(32px,4.6vw,52px);line-height:1.05;color:inherit;text-wrap:balance}
.t2__lead{margin:0;font-size:18px;line-height:1.5;color:var(--inverse-muted,#b6a891)}
.t2__totale{display:flex;flex-direction:column;gap:12px}
.t2__totrow{display:flex;flex-wrap:wrap;justify-content:space-between;align-items:baseline;gap:8px 16px}
.t2__tot{font-family:Gloock,Georgia,serif;font-size:24px}
.t2__nota{font-size:14px;color:var(--inverse-muted,#b6a891)}
.t2__barra{display:flex;gap:3px;height:16px;border-radius:999px;overflow:hidden}
.t2__barra span{flex-basis:0;transition:flex-grow .2s ease}
.t2__barra-nec{background:var(--inverse-fg,#faf5ec)}
.t2__barra-evit{background:#ee7a4a}
.t2__colonne{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,320px),1fr));column-gap:40px;row-gap:12px}
.t2__col{display:grid;grid-row:span 4;grid-template-rows:subgrid;gap:12px;border-top:1px solid rgba(250,245,236,.18);padding-top:18px}
.t2__testa{display:flex;flex-direction:column;gap:8px}
.t2__rif{display:inline-flex;align-items:center;gap:6px;align-self:flex-start;padding:5px 10px;border-radius:8px;background:rgba(250,245,236,.08);color:var(--inverse-muted,#b6a891);font-size:13px}
.t2__rif b{color:var(--inverse-fg,#faf5ec);font-weight:600}
.t2__min{font-family:Gloock,Georgia,serif;font-weight:400;font-size:40px;line-height:1;color:var(--inverse-fg,#faf5ec);font-variant-numeric:tabular-nums}
.t2__min--acc{color:#ee7a4a}
.t2__etichetta{font-weight:600;font-size:17px}
.t2__cursore{display:flex;flex-direction:column;gap:4px;font-size:14px;color:var(--inverse-muted,#b6a891)}
.t2__cursore>span{display:flex;justify-content:space-between;align-items:baseline;gap:12px}
.t2__cursore b{white-space:nowrap}
.t2__cursore--chiaro b{color:var(--ink,#231b12);font-size:18px;font-variant-numeric:tabular-nums}
.t2__cursore>span{align-items:flex-end}
.t2__cursore--chiaro{color:var(--muted,#6a5b48)}
.t2 input[type=range]{width:100%;height:30px;margin:0;accent-color:#ee7a4a;cursor:pointer}
.t2 input[type=range]:focus-visible{outline:2px solid #ee7a4a;outline-offset:3px;border-radius:8px}
.t2__desc{margin:0;color:var(--inverse-muted,#b6a891)}
.t2__lista{margin:0;padding:0;list-style:none;display:grid;gap:0}
.t2__lista li{padding:9px 0;border-bottom:1px solid rgba(250,245,236,.12)}
.t2__lista li:last-child{border-bottom:0}
.t2__conto{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,320px),1fr));gap:12px;background:var(--paper,#faf5ec);color:var(--ink,#231b12);border-radius:var(--r-xl,26px);padding:12px}
.t2__input{display:flex;flex-direction:column;gap:18px;padding:16px 16px 12px}
.t2__conto-titolo{font-family:Gloock,Georgia,serif;font-size:26px;line-height:1.1}
.t2__contesto{margin:0;font-size:14px;color:var(--muted,#6a5b48)}
.t2__contesto b{color:var(--ink,#231b12)}
.t2__risultato{background:var(--tile-terracotta,#b4451f);color:var(--tile-ink,#fff8f2);border-radius:20px;padding:28px;display:flex;flex-direction:column;gap:6px;justify-content:center}
.t2__euro{font-family:Gloock,Georgia,serif;font-size:clamp(56px,7vw,80px);line-height:1;font-variant-numeric:tabular-nums}
.t2__bilancio{margin:14px 0 0;display:grid;gap:0;border-top:1px solid rgba(255,248,242,.35)}
.t2__bilancio div{display:flex;justify-content:space-between;gap:12px;padding:8px 0;border-bottom:1px solid rgba(255,248,242,.2);font-size:15px}
.t2__bilancio dt{opacity:.9}
.t2__bilancio dd{margin:0;white-space:nowrap;font-variant-numeric:tabular-nums}
.t2__diff{font-size:18px!important;font-weight:600;border-bottom:0!important}
.t2__sotto,.t2__valore{font-size:18px}
.t2__valore{margin-top:8px}
.t2__piccolo{margin-top:6px;font-size:13px;opacity:.85}
.t2__prove{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,300px),1fr));gap:20px 40px;border-top:1px solid rgba(250,245,236,.18);padding-top:24px}
.t2__prove div{display:flex;flex-direction:column;gap:8px}
.t2__num{font-family:Gloock,Georgia,serif;font-size:34px;line-height:1;white-space:nowrap}
.t2__prove span:last-child{color:var(--inverse-muted,#b6a891);font-size:15px}
.t2__fonti{margin:-12px 0 0;font-size:12px;line-height:1.6;color:#9a8b73}
@media (max-width:760px){.t2{margin-inline:12px}}
@media (prefers-reduced-motion:reduce){.t2__barra span{transition:none}}
</style>

<script>
(function () {
  var s = document.getElementById('il-tempo'); if (!s) return;
  var v = { nec: 16, evit: 19, pren: 6, euro: 20 };
  var ore = function (h) { return h >= 10 ? String(Math.round(h)) : String(Math.round(h * 10) / 10).replace('.', ','); };
  var out = function (k, t) { s.querySelectorAll('[data-o="' + k + '"]').forEach(function (e) { e.textContent = t; }); };
  function ricalcola() {
    var tot = v.nec + v.evit, g = v.pren * (v.evit / 2) * 12 / 60, gg = Math.round(g / 8 * 2) / 2;
    out('nec', v.nec); out('evit', v.evit); out('tot', tot); out('pren', v.pren); out('euro', v.euro); out('euro2', v.euro);
    out('oreAnno', ore(v.pren * tot * 12 / 60)); out('oreEvit', ore(v.pren * v.evit * 12 / 60)); out('oreG', ore(g));
    out('giornate', gg < 1 ? 'meno di una giornata' : String(gg).replace('.', ',') + (gg === 1 ? ' giornata' : ' giornate'));
    out('valore', Math.round(g * v.euro).toLocaleString('it-IT'));
    var bil = s.querySelector('[data-prezzo]');
    if (bil) { var d = Math.round(g * v.euro) - Number(bil.getAttribute('data-prezzo')); out('diff', (d < 0 ? '\u2212 ' : '+ ') + Math.abs(d).toLocaleString('it-IT')); }
    s.querySelector('[data-bar="nec"]').style.flexGrow = v.nec;
    s.querySelector('[data-bar="evit"]').style.flexGrow = v.evit;
  }
  s.querySelectorAll('[data-t2]').forEach(function (i) {
    v[i.getAttribute('data-t2')] = Number(i.value);
    i.addEventListener('input', function () { v[i.getAttribute('data-t2')] = Number(i.value); ricalcola(); });
  });
  ricalcola();
})();
</script>
