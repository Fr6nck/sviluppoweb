<?php
/* 9K · «Qual è il piano giusto per te?»: tre domande sì/no, una alla volta, e il piano che basta (pagine per tipologia).
   Essential se sì/sì/sì; Plus se una delle prime due è no e la terza sì; Portfolio se la terza è no (con il selettore
   delle strutture e il prezzo progressivo). Senza JavaScript le domande sono un modulo GET: la pagina torna con l'esito.
   Con JavaScript (questionario.js) si va avanti da soli, si torna indietro, si rifà; i tre esiti sono già qui, nascosti.
   Riceve: $qPiani (codice => piano di Plans::public), $qRiep (codice => Sconti::riepilogo o null), $qRisposte (d1..d3 =>
   'si'|'no'), $qStrutture (Portfolio), $dentro. I testi: «Testi e foto» → «Pagine per tipologia: …».
   9L · Lo stesso partial (e lo stesso JS) per la finestra della home: $qModo = 'finestra' (senza la sezione e il riquadro
   intorno, tema scuro dato dalla finestra; senza JavaScript tutto porta a /piano) e $qOrigine (home | tipologia | piano)
   per l'evento delle statistiche «piano_test».
   In inglese sulla home /en e su /en/plan (LinguaSito): i testi di «Testi e foto» dalla linguetta EN, quelli fissi qui con $qU. */
use function MHW\b;
use MHW\{Support, Icon, Plans, TestiSito, SectionCatalog, Inviti, Giudizi, LinguaSito};
$qU = [LinguaSito::class, 't']; $qEn = LinguaSito::en();
// I numeri dei testi, dal listino: le sezioni di Essential, tutte le sezioni, le lingue di Plus (base + lingua in più).
$fq = fn(string $c, string $f) => (string) ($qPiani[$c]['features'][$f] ?? '');
$qSost = ['sezioni' => SectionCatalog::quante(), 'sezioni_essential' => ctype_digit($fq('essential', 'sections')) ? $fq('essential', 'sections') : '6',
          'lingue' => ctype_digit($fq('plus', 'locales')) ? (int) $fq('plus', 'locales') + (int) $fq('plus', 'lingua_extra') : 6];
$qT = fn(string $k, array $s = []) => TestiSito::html('questionario.' . $k, $s + $qSost);
$qEsito = null;
if (count(array_filter($qRisposte, fn($v) => in_array($v, ['si', 'no'], true))) === 3) {
    $qEsito = $qRisposte['d3'] === 'no' ? 'portfolio' : ($qRisposte['d1'] === 'no' || $qRisposte['d2'] === 'no' ? 'plus' : 'essential');
}
// Lo sconto sul rinnovo: inviti (fino a MASSIMO) e giudizio, solo quelli accesi.
$qInviti = Inviti::disponibili(); $qGiudizio = Giudizi::attivo();
$qTotSconto = ($qInviti ? Inviti::MASSIMO : 0) + ($qGiudizio ? Giudizi::percentuale() : 0);
$qScontoSost = ['totale' => $qTotSconto, 'amici' => (int) (Inviti::MASSIMO / max(1, Inviti::PASSO)), 'passo' => Inviti::PASSO, 'max' => Inviti::MASSIMO, 'giudizio' => Giudizi::percentuale()];
$qRighe = [];   // la tabella del rinnovo: [etichetta, percento]
if ($qInviti) foreach ([2, 5, (int) (Inviti::MASSIMO / max(1, Inviti::PASSO))] as $n) $qRighe[] = [$n . $qU(' amici · ', ' friends · ') . min($n * Inviti::PASSO, Inviti::MASSIMO) . '%', min($n * Inviti::PASSO, Inviti::MASSIMO)];
if ($qGiudizio) $qRighe[] = [($qInviti ? $qScontoSost['amici'] . $qU(' amici + giudizio', ' friends + review') : $qU('Giudizio', 'Review')) . ' · ' . $qTotSconto . '%', $qTotSconto];
$qFinestra = ($qModo ?? 'pagina') === 'finestra';
$qOrigine ??= $qFinestra ? 'home' : 'tipologia';
// La pagina stessa, senza le risposte; nella finestra della home, /piano (senza JavaScript la finestra non si apre).
$qBase = $qFinestra ? b() . LinguaSito::percorso('/piano') : strtok((string) ($_SERVER['REQUEST_URI'] ?? '/'), '?');
?>
<?php if (!$qFinestra): ?><section class="blocco" aria-labelledby="piano-giusto-titolo" id="piano-giusto"><?php endif; ?>
  <div class="questionario<?= $qFinestra ? ' questionario--finestra' : ' panel' ?>" data-questionario data-origine="<?= Support::e($qOrigine) ?>" data-si="<?= $qU('sì', 'yes') ?>"<?= $qEsito ? ' data-esito-iniziale="' . $qEsito . '"' : '' ?>>
    <span class="kicker questionario__kick"><?= $qT('kicker') ?></span>
    <<?= $qOrigine === 'piano' ? 'h1' : 'h2' ?> id="piano-giusto-titolo" class="h-sezione"><?= $qT('titolo') ?></<?= $qOrigine === 'piano' ? 'h1' : 'h2' ?>>
    <p class="muted questionario__sub"><?= $qT('sub') ?></p>

    <form method="get" action="<?= Support::e($qBase) ?>#piano-giusto" class="questionario__modulo" data-q-modulo<?= $qEsito ? ' hidden' : '' ?>>
      <div class="questionario__avanzamento" aria-hidden="true"><i class="on"></i><i></i><i></i></div>
      <?php foreach ([1, 2, 3] as $n): ?>
        <fieldset class="questionario__passo" data-q-passo="<?= $n ?>">
          <legend class="questionario__domanda"><?= $qT('d' . $n) ?></legend>
          <p class="questionario__aiuto"><?= $qT('d' . $n . '.aiuto') ?></p>
          <div class="questionario__scelte">
            <?php foreach (['si', 'no'] as $r): ?>
              <label class="questionario__scelta"><input type="radio" name="d<?= $n ?>" value="<?= $r ?>" required<?= ($qRisposte['d' . $n] ?? '') === $r ? ' checked' : '' ?>>
                <span><b><?= $qT('d' . $n . '.' . $r) ?></b><span><?= $qT('d' . $n . '.' . $r . '.aiuto') ?></span></span></label>
            <?php endforeach; ?>
          </div>
          <?php if ($n > 1): ?><button type="button" class="questionario__indietro" data-q-indietro hidden>← <?= $qU('Indietro', 'Back') ?></button><?php endif; ?>
        </fieldset>
      <?php endforeach; ?>
      <div class="questionario__invia" data-q-invia><button class="btn btn--go"><?= $qT('vedi') ?> <span class="go"><?= Icon::svg('arrow', 17, 2) ?></span></button></div>
    </form>

    <?php foreach (['essential', 'plus', 'portfolio'] as $cod): $pv = $qPiani[$cod] ?? null; if (!$pv) continue;
          $porto = $cod === 'portfolio'; $q = $porto ? $qStrutture : 1; $ri = $qRiep[$cod] ?? null; $sc = $ri && $ri['sconto'] > 0 ? $ri : null;
          $pieno = Plans::price($pv, $q); $tiers = Plans::tiers($pv); ?>
      <div class="questionario__esito" data-q-esito="<?= $cod ?>"<?= $qEsito === $cod ? '' : ' hidden' ?>
           data-base="<?= (int) $pv['price_cents'] ?>" data-scaglioni="<?= Support::e(Plans::tiersJson($pv)) ?>"
           <?= $sc ? ($sc['percento'] > 0 ? 'data-sconto="' . (int) $sc['percento'] . '"' : 'data-sconto-fisso="' . (int) $sc['sconto'] . '"') : '' ?>>
        <span class="badge questionario__badge"><?= $qT('badge') ?></span>
        <div class="questionario__piano">
          <div class="stack" style="gap:8px">
            <h3 class="questionario__nome"><?= Support::e($pv['name']) ?></h3>
            <p class="questionario__prezzo">
              <?php if ($sc): ?><s class="muted" data-q-pieno><?= Support::e(Support::money($pieno)) ?></s> <?php endif; ?>
              <b data-q-totale><?= Support::e(Support::money($sc ? $sc['totale'] : $pieno)) ?></b>
              <span class="muted"> <?= $qU('+ IVA', '+ VAT') ?> <?= $sc ? $qU('il primo anno', 'the first year') : $qU('/ anno', '/ year') ?><?= $porto ? $qU(' per ', ' for ') . '<span data-q-n>' . $q . '</span>' . $qU(' strutture', ' properties') : '' ?></span>
            </p>
            <?php if ($porto): /* il prezzo struttura per struttura, poi il selettore (2 … massimo del listino) */
                  $parti = array_map(fn($t) => Support::money($t['cents']), $tiers); ?>
              <p class="tiny muted" style="margin:0"><?= Support::e(Support::money((int) $pv['price_cents'])) ?> <?= $qU('la prima, poi', 'for the first, then') ?> <?= Support::e(implode(' · ', array_map(fn($x) => preg_replace('/\s*€$/u', '', $x), $parti))) ?> € <?= $qU('l\'una', 'each') ?><?= (int) $pv['max_quantity'] > 0 ? $qU(' · oltre ', ' · over ') . (int) $pv['max_quantity'] . $qU(': preventivo', ': quote') : '' ?></p>
              <div class="questionario__quante">
                <label for="q-strutture" class="small"><?= $qU('Quante strutture?', 'How many properties?') ?></label>
                <span class="questionario__selettore">
                  <button type="button" data-q-meno aria-label="<?= $qU('Una struttura in meno', 'One property fewer') ?>" hidden>−</button>
                  <input id="q-strutture" type="number" name="strutture" form="q-quante" inputmode="numeric" min="<?= max(2, (int) $pv['min_quantity']) ?>" max="<?= max(2, (int) $pv['max_quantity']) ?>" value="<?= $q ?>" data-q-quante>
                  <button type="button" data-q-piu aria-label="<?= $qU('Una struttura in più', 'One property more') ?>" hidden>+</button>
                </span>
              </div>
            <?php endif; ?>
            <ul class="questionario__punti">
              <?php foreach (TestiSito::lista('questionario.' . $cod . '.punti', $qSost) as $p): ?><li><?= Icon::svg('check', 15, 2.2) ?><span><?= $p ?></span></li><?php endforeach; ?>
            </ul>
          </div>
          <div class="questionario__perche">
            <b><?= $qT('perche') ?></b>
            <p><?= $qT($cod . '.perche') ?></p>
            <ul class="questionario__riep" data-q-riep>
              <?php foreach ([1, 2, 3] as $n): $v = $qRisposte['d' . $n] ?? ''; ?>
                <li><?= $qT('d' . $n . '.breve') ?>: <span data-q-r="<?= $n ?>"><?= $v === 'si' ? $qU('sì', 'yes') : ($v === 'no' ? 'no' : '') ?></span></li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>
        <div class="row questionario__azioni">
          <?php $qLang = $qEn && !$dentro ? '&lang=en' : ''; /* come le schede dei piani di /en */ ?>
          <a class="btn btn--go" data-q-crea data-href="<?= Support::e(b() . ($dentro ? '/piano?piano=' : '/registrati?piano=') . (int) $pv['pv_id'] . $qLang) ?>"
             href="<?= Support::e(b() . ($dentro ? '/piano?piano=' : '/registrati?piano=') . (int) $pv['pv_id'] . $qLang . ($porto ? '&strutture=' . $q : '')) ?>"><?= $qT('crea', ['piano' => Support::e($pv['name'])]) ?> <span class="go"><?= Icon::svg('arrow', 17, 2) ?></span></a>
          <a class="btn btn--ghost" href="<?= Support::e($qBase) ?>#piano-giusto" data-q-rifai><?= $qT('rifai') ?></a>
        </div>
        <?php if ($qTotSconto > 0): $rClasse = 'questionario__sconto'; $rCome = b() . LinguaSito::percorso('/domande') . '#porta-amico'; include __DIR__ . '/_risparmio.php'; /* 9L: lo stesso riquadro della home */ ?>
          <div class="questionario__rinnovo" data-q-rinnovo>
            <span><?= $qU('Primo anno', 'First year') ?> <b data-q-anno><?= Support::e(Support::money($pieno)) ?></b>, <?= $qEn ? 'from the renewal with ' . (int) $qTotSconto . '% off' : 'dal rinnovo con il ' . (int) $qTotSconto . '%' ?>:<br><s class="muted" data-q-anno-s><?= Support::e(Support::money($pieno)) ?></s></span>
            <b class="questionario__rinnovo-v" data-q-min><?= Support::e(Support::money((int) round($pieno * (100 - $qTotSconto) / 100))) ?></b>
            <table><tbody>
              <tr><td><?= $qU('Nessuno sconto', 'No discount') ?></td><td data-q-perc="0"><?= Support::e(Support::money($pieno)) ?></td></tr>
              <?php foreach ($qRighe as [$et, $perc]): ?><tr><td><?= Support::e($et) ?></td><td data-q-perc="<?= (int) $perc ?>"><?= Support::e(Support::money((int) round($pieno * (100 - $perc) / 100))) ?></td></tr><?php endforeach; ?>
            </tbody></table>
            <small><?= $qInviti ? $qT('rinnovo.nota') : $qU('Prezzi + IVA, all\'anno.', 'Prices + VAT, per year.') ?></small>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    <?php if (isset($qPiani['portfolio'])): /* senza JavaScript il numero di strutture si cambia ricaricando, con le stesse risposte */ ?>
      <form id="q-quante" method="get" action="<?= Support::e($qBase) ?>#piano-giusto" class="questionario__ricalcola" data-q-ricalcola>
        <?php foreach ($qRisposte as $k => $v): ?><input type="hidden" name="<?= Support::e($k) ?>" value="<?= Support::e($v) ?>"><?php endforeach; ?>
        <?php if ($qEsito === 'portfolio'): ?><button class="btn btn--sm btn--ghost"><?= $qU('Ricalcola', 'Recalculate') ?></button><?php endif; ?>
      </form>
    <?php endif; ?>
  </div>
<?php if (!$qFinestra): ?></section><?php endif; ?>
<script src="<?= MHW\av('/assets/questionario.js') ?>" defer></script>
