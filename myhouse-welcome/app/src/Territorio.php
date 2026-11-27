<?php
namespace MHW;

/**
 * 9F · Regione e provincia della struttura: le 20 regioni e le 107 sigle italiane (lang/regioni-province.php),
 * con il nome tradotto nelle lingue della guida dove esiste una forma (Firenze → Florence, Toscana → Tuscany).
 * All'estero la «provincia» è un testo libero (contea, distretto) e resta com'è scritta.
 */
final class Territorio
{
    private static ?array $dati = null;

    private static function dati(): array
    {
        if (self::$dati === null) { $f = MHW_APP . '/lang/regioni-province.php'; self::$dati = is_file($f) ? (array) require $f : ['regioni' => [], 'province' => []]; }
        return self::$dati;
    }

    /** @return string[] i nomi italiani delle regioni, in ordine alfabetico */
    public static function regioni(): array { return array_keys(self::dati()['regioni']); }

    /** @return array<string,string> sigla => nome italiano della provincia */
    public static function province(): array { return array_map(fn($p) => $p[0], self::dati()['province']); }

    /** La sigla, se il testo è una sigla italiana (anche minuscola, con spazi): '' altrimenti. */
    public static function sigla(string $v): string
    {
        $s = strtoupper(trim($v));
        return isset(self::dati()['province'][$s]) ? $s : '';
    }

    /** Quello che si salva in properties.province: la sigla maiuscola, oppure il testo libero (estero), al massimo 60 caratteri. */
    public static function normalizza(string $v): string
    {
        $s = self::sigla($v);
        return $s !== '' ? $s : mb_substr(trim($v), 0, 60);
    }

    /** La regione di una sigla ('' se non è una sigla italiana). */
    public static function regioneDi(string $sigla): string
    {
        $s = self::sigla($sigla);
        return $s !== '' ? (string) self::dati()['province'][$s][1] : '';
    }

    /** Il nome della provincia nella lingua della guida: dalla sigla, tradotto dove esiste una forma; un testo libero resta com'è. */
    public static function nomeProvincia(string $v, string $loc = 'it'): string
    {
        $s = self::sigla($v);
        if ($s === '') return trim($v);
        $p = self::dati()['province'][$s];
        return (string) ($p[2][$loc] ?? $p[0]);
    }

    /** Il nome della regione nella lingua della guida (tradotto solo se è una regione italiana con una forma in quella lingua). */
    public static function nomeRegione(string $v, string $loc = 'it'): string
    {
        $r = trim($v);
        $tutte = self::dati()['regioni'];
        // Si accetta anche la regione scritta in modo diverso per maiuscole/minuscole.
        foreach ($tutte as $nome => $trad) if (mb_strtolower($nome) === mb_strtolower($r)) return (string) ($trad[$loc] ?? $nome);
        return $r;
    }

    /**
     * «Provincia · Regione» nella lingua della guida (es. «Perugia · Umbria»), da un'istantanea o da una struttura.
     * Senza provincia (guide di prima) resta la sola regione; senza niente, ''.
     */
    public static function luogo(array $p, string $loc = 'it'): string
    {
        $parti = [];
        $prov = self::nomeProvincia((string) ($p['province'] ?? ''), $loc);
        $reg = self::nomeRegione((string) ($p['region'] ?? ''), $loc);
        if ($prov !== '') $parti[] = $prov;
        if ($reg !== '' && mb_strtolower($reg) !== mb_strtolower($prov)) $parti[] = $reg;
        return implode(' · ', $parti);
    }

    /** «Città · Provincia · Regione» (la città una volta sola, anche se è il capoluogo). */
    public static function dove(array $p, string $loc = 'it'): string
    {
        $citta = trim((string) ($p['city'] ?? ''));
        if ($citta === '') return self::luogo($p, $loc);
        // Il capoluogo non si ripete: «Firenze · Toscana», non «Firenze · Florence · Tuscany».
        $q = $p;
        if (mb_strtolower(self::nomeProvincia((string) ($p['province'] ?? ''), 'it')) === mb_strtolower($citta)) $q['province'] = '';
        $luogo = self::luogo($q, $loc);
        if ($luogo === '') return $citta;
        $parti = array_filter(explode(' · ', $luogo), fn($x) => mb_strtolower($x) !== mb_strtolower($citta));
        return implode(' · ', array_merge([$citta], $parti));
    }
}
