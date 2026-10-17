<?php
/**
 * 9A — Verifica dei prezzi su Stripe, da riga di comando e SOLO con la chiave di prova (sk_test_).
 *
 *   php app/tools/verifica-stripe.php
 *
 * Per Essential, Plus e Portfolio da 2 a 10 strutture, senza sconto e con lo sconto dell'invito; 9D: con un codice omaggio
 * da 1, 6 e 12 mesi, con e senza lo sconto del giudizio (sessione a 0 € oggi, trial_end)
 * (Inviti::AMICO %): crea una sessione di Checkout senza cliente, fa i due controlli del sito
 * (Stripe::verificaPrezzi prima, Stripe::controllaSessione dopo), la fa scadere e stampa una tabella
 * atteso/Stripe. Da lanciare prima di andare online e dopo ogni cambio di listino. Con la chiave
 * viva (sk_live_) si rifiuta: non si creano sessioni vere per una prova.
 */

declare(strict_types=1);

use MHW\{Config, Db, Plans, Stripe, Inviti, Sconti, Support, Giudizi};

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('MHW_APP', dirname(__DIR__));
spl_autoload_register(function (string $c): void {
    if (str_starts_with($c, 'MHW\\') && is_file($f = MHW_APP . '/src/' . substr($c, 4) . '.php')) require $f;
});
Config::load(MHW_APP . '/config.php');

$chiave = (string) ((Config::get('stripe') ?? [])['secret_key'] ?? '');
if ($chiave === '') { fwrite(STDERR, "Stripe non è configurato (chiave segreta): niente da verificare.\n"); exit(1); }
if (!str_starts_with($chiave, 'sk_test_')) { fwrite(STDERR, "Questo strumento lavora solo con la chiave di prova (sk_test_): con quella viva si fermerebbe qui.\n"); exit(1); }

$righe = [];
$sconti = [0, Inviti::AMICO];
$giudizio = Giudizi::disponibili() ? Giudizi::percentuale() : 5;
foreach (Plans::public() as $o) {
    $pv = Plans::currentVersion((int) $o['pv_id']);
    $pkg = Db::one('SELECT * FROM packages WHERE id = ?', [$pv['package_id']]);
    $quantita = Plans::perProperty($pv) ? range((int) $pv['min_quantity'], (int) $pv['max_quantity']) : [1];
    foreach ($quantita as $q) foreach ($sconti as $pct) {
        $atteso = Plans::price($pv, $q);
        $attesoNetto = $atteso - ($pct > 0 ? (int) round($atteso * $pct / 100) : 0);
        $esito = Stripe::sessioneDiProva($pv, $pkg, $q, $pct);
        $righe[] = [$o['name'] . (Plans::perProperty($pv) ? " · $q strutture" : ''), $pct > 0 ? "−$pct%" : '—',
                    Support::money($atteso), Support::money($attesoNetto), $esito['ok'] ? 'ok' : 'NO', $esito['errore'], $esito['sessione']];
    }
    // 9D · Codice omaggio: 1, 6 e 12 mesi, senza sconto e con quello del giudizio (l'unico ammesso); oggi la sessione deve dire 0 €.
    // «Invito + omaggio»: su Stripe va solo l'omaggio (il sito non manda il coupon dell'invito): qui la riga «solo omaggio» lo rappresenta.
    $qMin = Plans::perProperty($pv) ? (int) $pv['min_quantity'] : 1;
    foreach ([1, 6, 12] as $mesi) foreach ([0, $giudizio] as $pct) {
        $atteso = Plans::price($pv, $qMin);
        $attesoNetto = $atteso - ($pct > 0 ? (int) round($atteso * $pct / 100) : 0);
        $esito = Stripe::sessioneDiProva($pv, $pkg, $qMin, $pct, $mesi);
        $righe[] = [$o['name'] . (Plans::perProperty($pv) ? " · $qMin strutture" : '') . " · omaggio $mesi m.", $pct > 0 ? "−$pct% giudizio" : 'solo omaggio',
                    '0 € oggi', Support::money($attesoNetto), $esito['ok'] ? 'ok' : 'NO', $esito['errore'], $esito['sessione']];
    }
}

$col = fn(string $s, int $n) => str_pad($s, $n + strlen($s) - mb_strlen($s));   // a colonne anche con «€» e spazi stretti
echo $col('Piano', 34) . $col('Sconto', 14) . $col('Listino', 12) . $col('Primo addebito', 15) . $col('Esito', 7) . "Sessione / errore\n";
echo str_repeat('-', 100) . "\n";
$ko = 0;
foreach ($righe as $r) {
    if ($r[4] !== 'ok') $ko++;
    echo $col($r[0], 34) . $col($r[1], 14) . $col($r[2], 12) . $col($r[3], 15) . $col($r[4], 7) . ($r[4] === 'ok' ? $r[6] . ' (scaduta)' : $r[5]) . "\n";
}
echo str_repeat('-', 100) . "\n";
echo $ko === 0 ? "Tutte le " . count($righe) . " righe tornano: i Price su Stripe e gli importi del Checkout corrispondono al listino.\n"
               : "$ko righe su " . count($righe) . " NON tornano: controlla i Price su Stripe (o svuota i Price ID nella versione: il sito li ricrea).\n";
exit($ko === 0 ? 0 : 2);
