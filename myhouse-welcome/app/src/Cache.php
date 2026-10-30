<?php
namespace MHW;

/**
 * Le cache del sito e come svuotarle dall'amministrazione (Sistema → Cache).
 *
 * codice      OPcache: il codice PHP già compilato. Dopo un caricamento via FTP il server può tenersi per qualche
 *             minuto i file vecchi; svuotandola prende subito quelli nuovi.
 * immagini    le versioni ridotte delle foto (storage/cache/ridotte, Media::ridottaBytes): si rifanno da sole alla
 *             prima visita.
 * risorse     foglio di stile e script nei browser: av() aggiunge ?v= con la data del file e questo numero;
 *             alzarlo obbliga tutti i browser (e una CDN davanti al sito) a riscaricarli.
 * seo         robots.txt, sitemap.xml, llms.txt e llms-full.txt scritti di nuovo accanto a index.php (Seo::scriviFile).
 * traduzioni  la memoria delle frasi già tradotte (translation_cache): NON entra in «Svuota tutto», perché ogni frase
 *             tolta, se torna, si paga di nuovo ad Amazon. Di serie si tolgono solo le frasi mai riusate.
 *
 * Ogni pulizia va nel registro (cache.svuota) e la data dell'ultima resta in storage/cache-pulizie.json.
 */
final class Cache
{
    /** Le voci di «Svuota tutto», nell'ordine in cui si fanno. */
    public const TUTTO = ['codice', 'immagini', 'risorse', 'seo'];

    public const NOMI = [
        'codice' => 'Codice PHP (OPcache)',
        'immagini' => 'Foto ridotte',
        'risorse' => 'Foglio di stile e script nei browser',
        'seo' => 'File per motori e assistenti',
        'traduzioni' => 'Memoria delle traduzioni',
    ];

    private static function cartellaImmagini(): string { return MHW_APP . '/storage/cache/ridotte'; }

    private static function fileVersione(): string { return MHW_APP . '/storage/versione-risorse.txt'; }

    private static function filePulizie(): string { return MHW_APP . '/storage/cache-pulizie.json'; }

    /** Il numero che av() aggiunge dopo la data dei file (0 = mai alzato). */
    public static function versioneRisorse(): int
    {
        static $v = null;
        if ($v !== null) return $v;
        $f = self::fileVersione();
        return $v = is_file($f) ? max(0, (int) trim((string) @file_get_contents($f))) : 0;
    }

    /** Le date delle ultime pulizie, per voce. @return array<string,string> */
    public static function pulizie(): array
    {
        $j = is_file(self::filePulizie()) ? json_decode((string) @file_get_contents(self::filePulizie()), true) : null;
        return is_array($j) ? array_map('strval', $j) : [];
    }

    private static function segna(string $voce): void
    {
        $p = self::pulizie();
        $p[$voce] = Support::now();
        @file_put_contents(self::filePulizie(), json_encode($p, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    /** I file delle foto ridotte (solo quelli che scrive Media::ridottaBytes). @return string[] */
    private static function fileImmagini(): array
    {
        return array_values(array_filter(glob(self::cartellaImmagini() . '/*') ?: [], fn($f) => is_file($f) && preg_match('/^\d+-\d+\.(jpg|png)$/', basename($f))));
    }

    /** OPcache: [attiva, dettaglio]. */
    private static function opcache(): array
    {
        if (!function_exists('opcache_get_status') || !filter_var(ini_get('opcache.enable'), FILTER_VALIDATE_BOOLEAN)) {
            return [false, 'OPcache non è attiva su questo server: il codice nuovo si vede subito, non c\'è niente da svuotare.'];
        }
        $s = @opcache_get_status(false);
        if (!is_array($s)) return [true, 'OPcache è attiva, ma l\'hosting non lascia leggere quanti file contiene (opcache.restrict_api).'];
        $n = (int) ($s['opcache_statistics']['num_cached_scripts'] ?? 0);
        $mb = ((int) ($s['memory_usage']['used_memory'] ?? 0)) / 1048576;
        return [true, $n . ' file PHP in memoria (' . number_format($mb, 1, ',', '.') . ' MB).'];
    }

    /** Lo stato di ogni voce per la pagina: nome, dettaglio, ultima pulizia. @return array<string,array{nome:string,dettaglio:string,ultima:?string,possibile:bool}> */
    public static function stato(): array
    {
        $p = self::pulizie();
        [$opOn, $opDett] = self::opcache();
        $img = self::fileImmagini();
        $byte = array_sum(array_map(fn($f) => (int) @filesize($f), $img));
        $esito = Seo::esitoFile();
        $mem = Migrator::tableExists('translation_cache')
            ? Db::one('SELECT COUNT(*) AS righe, COALESCE(SUM(CASE WHEN hits = 0 THEN 1 ELSE 0 END), 0) AS mai, COALESCE(SUM(hits), 0) AS riusi FROM translation_cache') : null;
        $v = self::versioneRisorse();
        $voci = [
            'codice' => [$opDett, $opOn],
            'immagini' => [count($img) . ' foto ridotte (' . number_format($byte / 1048576, 1, ',', '.') . ' MB). Si rifanno da sole alla prima visita.', true],
            'risorse' => [($v > 0 ? 'Versione ' . $v . '. ' : 'Mai forzata. ') . 'I browser tengono foglio di stile e script finché non cambiano: dopo un caricamento via FTP di solito se ne accorgono da soli, questo serve quando non succede (o con una CDN davanti al sito).', true],
            'seo' => [$esito ? 'Ultima scrittura: ' . Support::date($esito['quando']) . ($esito['ok'] ? '.' : ' — ' . $esito['msg']) : 'Mai scritti come file.', Seo::disponibili()],
            'traduzioni' => [$mem ? number_format((int) $mem['righe'], 0, ',', '.') . ' frasi in memoria, ' . number_format((int) $mem['mai'], 0, ',', '.') . ' mai riusate; ' . number_format((int) $mem['riusi'], 0, ',', '.') . ' riusi (traduzioni non pagate).'
                                   : 'La memoria delle traduzioni non c\'è ancora.', $mem !== null],
        ];
        $out = [];
        foreach ($voci as $k => [$dett, $possibile]) $out[$k] = ['nome' => self::NOMI[$k], 'dettaglio' => $dett, 'ultima' => $p[$k] ?? null, 'possibile' => $possibile];
        return $out;
    }

    /**
     * Svuota una voce. $tutte vale solo per «traduzioni»: false = solo le frasi mai riusate, true = tutta la memoria.
     * @return array{0:bool,1:string} [riuscito, messaggio]
     */
    public static function svuota(string $voce, bool $tutte = false): array
    {
        $esito = match ($voce) {
            'codice' => self::svuotaCodice(),
            'immagini' => self::svuotaImmagini(),
            'risorse' => self::alzaVersione(),
            'seo' => Seo::scriviFile(true),
            'traduzioni' => self::svuotaTraduzioni($tutte),
            default => [false, 'Voce sconosciuta.'],
        };
        if (isset(self::NOMI[$voce])) {
            if ($esito[0]) self::segna($voce);
            Auth::audit('cache.svuota', null, ['voce' => $voce, 'tutte' => $tutte, 'ok' => $esito[0], 'esito' => $esito[1]]);
        }
        return $esito;
    }

    /** «Svuota tutto»: le voci di TUTTO (non la memoria delle traduzioni). @return array<string,array{0:bool,1:string}> */
    public static function svuotaTutto(): array
    {
        $r = [];
        foreach (self::TUTTO as $v) $r[$v] = self::svuota($v);
        return $r;
    }

    private static function svuotaCodice(): array
    {
        [$on] = self::opcache();
        if (!$on) return [true, 'OPcache non è attiva: niente da svuotare.'];
        if (!function_exists('opcache_reset')) return [false, 'L\'hosting non permette di svuotare OPcache da PHP: chiedi di riavviare PHP, o aspetta qualche minuto.'];
        return @opcache_reset() ? [true, 'Codice PHP svuotato: dalla prossima pagina il server legge i file di adesso.']
                                : [false, 'L\'hosting non permette di svuotare OPcache da qui (opcache.restrict_api): chiedi di riavviare PHP, o aspetta qualche minuto.'];
    }

    private static function svuotaImmagini(): array
    {
        $n = 0; $falliti = 0;
        foreach (self::fileImmagini() as $f) { if (@unlink($f)) $n++; else $falliti++; }
        return $falliti ? [false, "Tolte $n foto ridotte, $falliti non si possono togliere: controlla i permessi di app/storage/cache."]
                        : [true, $n === 0 ? 'Nessuna foto ridotta da togliere.' : ($n === 1 ? 'Tolta 1 foto ridotta: si rifà alla prima visita.' : "Tolte $n foto ridotte: si rifanno alla prima visita.")];
    }

    private static function alzaVersione(): array
    {
        $v = self::versioneRisorse() + 1;
        if (@file_put_contents(self::fileVersione(), (string) $v, LOCK_EX) === false) return [false, 'Non riesco a scrivere in app/storage: controlla i permessi.'];
        return [true, "Foglio di stile e script alla versione $v: i browser li riscaricano alla prossima pagina."];
    }

    private static function svuotaTraduzioni(bool $tutte): array
    {
        if (!Migrator::tableExists('translation_cache')) return [true, 'La memoria delle traduzioni non c\'è ancora.'];
        $n = $tutte ? Db::run('DELETE FROM translation_cache') : Db::run('DELETE FROM translation_cache WHERE hits = 0');
        $frasi = $n === 1 ? '1 frase' : "$n frasi";
        return [true, $tutte ? "Memoria delle traduzioni svuotata ($frasi): le prossime traduzioni si pagano di nuovo."
                             : "Tolte $frasi mai riusate dalla memoria delle traduzioni."];
    }
}
