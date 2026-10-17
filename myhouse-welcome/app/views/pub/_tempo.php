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
   il valore in euro del tempo risparmiato in un anno (7B: senza il confronto
   con il prezzo del piano). Funziona anche senza JavaScript con i valori di partenza.
   ===================================================================== */
use MHW\{Support, TestiSito};
$T = $T ?? fn(string $k, array $sost = []) => TestiSito::html($k, $sost);
$L = $L ?? fn(string $k) => '';
/* 8A: anche le frasi del cursore e del riquadro arancione stanno in «Testi e foto» (tempo2.*); quelle che
   cambia lo script gli arrivano come attributi data- (la 8E le traduce). */
$t2 = ['nec' => 16, 'evit' => 19, 'pren' => 6, 'euro' => 20];
$t2Fmt = trim(TestiSito::testo('tempo2.formato')) ?: 'it-IT';
$t2En = str_starts_with(strtolower($t2Fmt), 'en');
$t2Ore = fn(float $h): string => $h >= 10 ? (string) round($h) : ($t2En ? (string) round($h, 1) : str_replace('.', ',', (string) round($h, 1)));
$t2Eur = fn(float $n): string => $t2En ? '€' . number_format($n, 0, '.', ',') : number_format($n, 0, ',', '.') . ' €';
$t2Tot = $t2['nec'] + $t2['evit'];
$t2Gu = $t2['pren'] * ($t2['evit'] / 2) * 12 / 60;
$t2Gg = round($t2Gu / 8 * 2) / 2;
$t2GgTesto = $t2Gg < 1 ? TestiSito::testo('tempo2.meno') : TestiSito::testo($t2Gg == 1 ? 'tempo2.giornata' : 'tempo2.giornate', ['n' => $t2En ? (string) $t2Gg : str_replace('.', ',', (string) $t2Gg)]);
$lucchetto = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>';
$colonna = function (string $k, int $rif, int $max) use ($T, $L, $t2, $lucchetto): void { ?>
    <div class="t2__col">
      <span class="t2__etichetta"><?= $T("tempo.$k.titolo") ?></span>
      <p class="t2__desc"><?= $T("tempo.$k.desc") ?></p>
      <ul class="t2__lista"<?= $L("tempo.$k.lista") ?>><?php foreach (TestiSito::lista("tempo.$k.lista") as $v): ?><li><?= $v ?></li><?php endforeach; ?></ul>
      <span class="t2__rif"><?= $lucchetto ?><?= $T('tempo2.rif', ['min' => (string) $rif]) ?></span>
      <label class="t2__cursore"><span><?= $T('tempo2.tuo') ?> <b class="t2__min<?= $k === 'evit' ? ' t2__min--acc' : '' ?>"><?= $T('tempo2.minuti', ['n' => '<span data-o="' . $k . '">' . $t2[$k] . '</span>']) ?></b></span>
        <input type="range" min="5" max="<?= $max ?>" step="1" value="<?= $t2[$k] ?>" data-t2="<?= $k ?>" aria-label="<?= TestiSito::testo("tempo2.aria.$k") ?>"></label>
    </div>
<?php };
?>
<section id="il-tempo" class="t2" aria-labelledby="t2-titolo" data-formato="<?= Support::e($t2Fmt) ?>"
         data-giornate="<?= TestiSito::testo('tempo2.giornate') ?>" data-giornata="<?= TestiSito::testo('tempo2.giornata') ?>" data-meno="<?= TestiSito::testo('tempo2.meno') ?>">
  <div class="t2__head">
    <span class="t2__kicker"><?= $T('tempo.kicker') ?></span>
    <h2 id="t2-titolo" class="t2__titolo"><?= $T('tempo.titolo') ?></h2>
    <p class="t2__lead"><?= $T('tempo.lead') ?></p>
  </div>

  <div class="t2__totale">
    <div class="t2__totrow"><span class="t2__tot"><?= $T('tempo.totale', ['tot' => '<span data-o="tot">' . $t2Tot . '</span>']) ?></span>
      <span class="t2__nota"><?= $T('tempo.nota') ?></span></div>
    <div class="t2__barra" aria-hidden="true">
      <span class="t2__barra-nec" data-bar="nec" style="flex-grow:<?= $t2['nec'] ?>"></span>
      <span class="t2__barra-evit" data-bar="evit" style="flex-grow:<?= $t2['evit'] ?>"></span>
    </div>
  </div>

  <?php /* 8A: in ogni colonna titolo → frase → i 4 esempi → riferimento → il tuo tempo (subgrid di 5 righe). */ ?>
  <div class="t2__colonne">
    <?php $colonna('nec', 16, 60); $colonna('evit', 19, 60); ?>
  </div>

  <div class="t2__conto">
    <div class="t2__input">
      <span class="t2__conto-titolo"><?= $T('tempo.conto.titolo') ?></span>
      <label class="t2__cursore t2__cursore--chiaro"><span><?= $T('tempo.conto.pren') ?> <b data-o="pren"><?= $t2['pren'] ?></b></span>
        <input type="range" min="1" max="30" step="1" value="<?= $t2['pren'] ?>" data-t2="pren"></label>
      <label class="t2__cursore t2__cursore--chiaro"><span><?= $T('tempo.conto.ora') ?> <b data-o="euroOra"><?= $t2Eur($t2['euro']) ?></b></span>
        <input type="range" min="10" max="50" step="1" value="<?= $t2['euro'] ?>" data-t2="euro"></label>
      <p class="t2__contesto"><?= $T('tempo2.contesto', ['ore' => '<span data-o="oreAnno">' . $t2Ore($t2['pren'] * $t2Tot * 12 / 60) . '</span>', 'evit' => '<span data-o="oreEvit">' . $t2Ore($t2['pren'] * $t2['evit'] * 12 / 60) . '</span>']) ?></p>
    </div>
    <?php /* 8A: in grande il tempo, sotto le giornate; gli euro in grassetto alla grandezza del testo. */ ?>
    <div class="t2__risultato" aria-live="polite">
      <span class="t2__kicker t2__kicker--chiaro"><?= $T('tempo.risultato.kicker') ?></span>
      <span class="t2__ore"><?= $T('tempo2.ore', ['ore' => '<span data-o="oreG">' . $t2Ore($t2Gu) . '</span>']) ?></span>
      <span class="t2__giornate" data-o="giornate"><?= $t2GgTesto ?></span>
      <b class="t2__valgono"><?= $T('tempo2.valgono', ['euro' => '<span data-o="valore">' . $t2Eur($t2Gu * $t2['euro']) . '</span>', 'ora' => '<span data-o="euro2">' . $t2Eur($t2['euro']) . '</span>']) ?></b>
      <span class="t2__piccolo"><?= $T('tempo.risultato.piccolo') ?></span>
    </div>
  </div>

  <div class="t2__prove">
    <div><span class="t2__num"><?= $T('tempo.prova1.num') ?></span><span><?= $T('tempo.prova1.testo') ?></span></div>
    <div><span class="t2__num"><?= $T('tempo.prova2.num') ?></span><span><?= $T('tempo.prova2.testo') ?></span></div>
  </div>
  <p class="t2__fonti"><?= $T('tempo.fonti') ?></p>
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
.t2__col{display:grid;grid-row:span 5;grid-template-rows:subgrid;gap:12px;border-top:1px solid rgba(250,245,236,.18);padding-top:18px;align-items:start}
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
.t2 input[data-t2="nec"]{accent-color:var(--inverse-fg,#faf5ec)}   /* come la barra in alto: bianco = resta tuo, arancione = evitabile */
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
.t2__ore{font-family:Gloock,Georgia,serif;font-size:clamp(56px,7vw,80px);line-height:1;font-variant-numeric:tabular-nums}
.t2__giornate{font-size:15px;opacity:.9}
.t2__valgono{display:block;margin-top:12px;font-size:16px;line-height:24px;font-weight:700}
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
  var fmt = s.getAttribute('data-formato') || 'it-IT';
  var num = function (n, dec) { return n.toLocaleString(fmt, { maximumFractionDigits: dec }); };
  var ore = function (h) { return h >= 10 ? num(Math.round(h), 0) : num(Math.round(h * 10) / 10, 1); };
  var eur = function (n) { return n.toLocaleString(fmt, { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }); };
  var out = function (k, t) { s.querySelectorAll('[data-o="' + k + '"]').forEach(function (e) { e.textContent = t; }); };
  function ricalcola() {
    var tot = v.nec + v.evit, g = v.pren * (v.evit / 2) * 12 / 60, gg = Math.round(g / 8 * 2) / 2;
    out('nec', v.nec); out('evit', v.evit); out('tot', tot); out('pren', v.pren);
    out('euroOra', eur(v.euro)); out('euro2', eur(v.euro));
    out('oreAnno', ore(v.pren * tot * 12 / 60)); out('oreEvit', ore(v.pren * v.evit * 12 / 60)); out('oreG', ore(g));
    out('giornate', gg < 1 ? s.getAttribute('data-meno') : s.getAttribute(gg === 1 ? 'data-giornata' : 'data-giornate').split('{n}').join(num(gg, 1)));
    out('valore', eur(Math.round(g * v.euro)));
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
