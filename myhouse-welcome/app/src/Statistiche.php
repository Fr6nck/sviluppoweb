<?php
namespace MHW;

/**
 * 8H · Le statistiche delle visite del sito pubblico. Tutto si configura da Amministrazione → SEO e GEO
 * (campi vuoti = spento), nessun codice nel codice:
 *   - Matomo, sul nostro server e senza cookie: conta tutte le visite e non chiede il consenso;
 *   - Google Analytics 4 e Microsoft Clarity: partono SOLO dopo il consenso del visitatore (banner, assets/statistiche.js).
 * Solo sulle pagine pubbliche elencate in PAGINE: mai nelle guide degli ospiti, nel pannello, in Amministrazione,
 * né nelle pagine con un codice nell'indirizzo (reimposta password, conferma email, inviti, link delle email).
 * Gli obiettivi hanno un nome fisso (OBIETTIVI): si configurano in Matomo e in GA4 con quel nome.
 */
final class Statistiche
{
    /** Le viste del sito pubblico che contano: home e /en, domande, tipologie, prezzi, pagamento, legali, registrazione e accesso. */
    public const PAGINE = ['pub/home', 'pub/domande', 'pub/tipologia', 'pub/plan', 'pub/paid', 'pub/legal',
                           'auth/register', 'auth/login', 'auth/check_email', 'auth/forgot'];

    /** I nomi degli obiettivi (evento: categoria «obiettivo», azione = nome). */
    public const OBIETTIVI = ['registrazione' => 'account creato', 'prova' => 'guida di prova creata', 'pagamento' => 'pagamento completato (nome = piano)',
                              'contatto' => 'clic su WhatsApp, email o telefono (nome = canale)', 'sito-myhouse' => '«Sì» nel percorso «Anche il tuo sito»',
                              'vedi-esempio' => 'apertura della guida di esempio',
                              'piano_test' => 'esito del questionario «Qual è il piano giusto per te?» (nome = piano · origine: home, tipologia, piano)',
                              // 9P/9Q · I clic dalle email ai clienti, contati dal server (nome = la strada o il bottone)
                              'bozza7_click' => 'clic su «Riprendi da dove eri» nell\'email del giorno 7 sulla bozza (nome = passo)',
                              'bozza21_click' => 'clic su una strada dell\'email del giorno 21 sulla bozza (nome = chiamata, non-ora)',
                              'email_sconti_click' => 'clic su un bottone dell\'email «Fino al 55%» (nome = inviti, giudizio)'];

    /** Il nome del cookie tecnico con la scelta del visitatore (6 mesi): «s1a0.20261015», s = statistiche (GA4), a = analisi (Clarity). */
    public const COOKIE = 'mhw_consenso';
    public const DURATA = 15552000;   // 180 giorni

    /** @return array{matomo_url:string, matomo_id:string, ga4:string, clarity:string} */
    public static function config(): array
    {
        $ok = Seo::disponibili();
        $g = fn(string $k) => $ok ? trim(Seo::get("stat.$k")) : '';
        $url = $g('matomo_url');
        return ['matomo_url' => $url !== '' ? rtrim($url, '/') . '/' : '', 'matomo_id' => $g('matomo_id'), 'ga4' => $g('ga4'), 'clarity' => $g('clarity')];
    }

    public static function matomo(): bool { $c = self::config(); return $c['matomo_url'] !== '' && $c['matomo_id'] !== ''; }

    /** Il banner serve solo con GA4 o Clarity: con il solo Matomo (senza cookie) non c'è niente da chiedere. */
    public static function consensoServe(): bool { $c = self::config(); return $c['ga4'] !== '' || $c['clarity'] !== ''; }

    public static function attive(): bool { return self::matomo() || self::consensoServe(); }

    /** Questa pagina conta? Solo le viste pubbliche di PAGINE, e solo se c'è almeno uno strumento. */
    public static function qui(): bool { return in_array(View::vista(), self::PAGINE, true) && self::attive(); }

    /** Controlli dei campi di SEO e GEO: [valore pulito, errore o '']. */
    public static function pulisci(array $in): array
    {
        $url = trim((string) ($in['matomo_url'] ?? ''));
        $id = trim((string) ($in['matomo_id'] ?? ''));
        $ga = strtoupper(trim((string) ($in['ga4'] ?? '')));
        $cl = strtolower(trim((string) ($in['clarity'] ?? '')));
        if ($url !== '' && !preg_match('~^https://[a-z0-9.-]+(:\d+)?(/[^\s?#]*)?$~i', $url)) return [null, 'L\'indirizzo di Matomo va scritto per intero, con https://, per esempio https://statistiche.welcomemyhouse.it/.'];
        if ($id !== '' && !preg_match('/^\d{1,6}$/', $id)) return [null, 'L\'ID del sito in Matomo è un numero (di solito 1).'];
        if (($url === '') !== ($id === '')) return [null, 'Per Matomo servono sia l\'indirizzo sia l\'ID del sito (o lasciali vuoti tutti e due).'];
        if ($ga !== '' && !preg_match('/^G-[A-Z0-9]{4,20}$/', $ga)) return [null, 'L\'ID di misurazione di Google Analytics 4 comincia con G-, per esempio G-AB12CD34EF.'];
        if ($cl !== '' && !preg_match('/^[a-z0-9]{6,20}$/', $cl)) return [null, 'L\'ID del progetto di Microsoft Clarity è fatto di lettere e numeri (lo trovi in Impostazioni → Configurazione).'];
        return [['stat.matomo_url' => $url !== '' ? rtrim($url, '/') . '/' : '', 'stat.matomo_id' => $id, 'stat.ga4' => $ga, 'stat.clarity' => $cl], ''];
    }

    /**
     * Un obiettivo raggiunto su una pagina che non conta (o prima di un redirect): si mette da parte nella sessione
     * e parte dalla prossima pagina pubblica che conta, insieme alla visita (Matomo e, con il consenso, GA4).
     */
    public static function evento(string $nome, string $dettaglio = ''): void
    {
        if (!isset(self::OBIETTIVI[$nome]) || !self::attive() || session_status() !== PHP_SESSION_ACTIVE) return;
        $coda = (array) ($_SESSION['mhw_stat'] ?? []);
        if (count($coda) < 10) $coda[] = [$nome, mb_substr($dettaglio, 0, 60)];
        $_SESSION['mhw_stat'] = $coda;
    }

    /** Gli eventi da mandare da questa pagina (e la coda si svuota). @return list<array{0:string,1:string}> */
    public static function prendiEventi(): array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) return [];
        $coda = array_values((array) ($_SESSION['mhw_stat'] ?? []));
        unset($_SESSION['mhw_stat']);
        return $coda;
    }

    /**
     * Un obiettivo che succede nel pannello, dove le statistiche non entrano (la guida di prova): solo a Matomo,
     * dal server, senza cookie né identificativi e senza aspettare (al massimo 2 secondi). GA4 qui no: non c'è consenso.
     */
    public static function matomoDalServer(string $nome, string $dettaglio = ''): void
    {
        if (!isset(self::OBIETTIVI[$nome]) || !self::matomo() || !function_exists('curl_init')) return;
        $c = self::config();
        $q = http_build_query(['idsite' => $c['matomo_id'], 'rec' => 1, 'apiv' => 1, 'send_image' => 0, 'rand' => bin2hex(random_bytes(4)),
                               'url' => Support::baseUrl() . '/', 'action_name' => 'MyHouse Welcome',
                               'e_c' => 'obiettivo', 'e_a' => $nome, 'e_n' => $dettaglio]);
        try {
            $ch = curl_init($c['matomo_url'] . 'matomo.php?' . $q);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 2, CURLOPT_TIMEOUT => 2,
                                    CURLOPT_USERAGENT => 'MyHouse Welcome (statistiche dal server)']);
            curl_exec($ch);
            curl_close($ch);
        } catch (\Throwable $e) { Log::exception($e, 'Statistiche::matomoDalServer'); }
    }

    /**
     * Il pezzo variabile del testo del banner: le finalità, senza i nomi dei fornitori (primo livello dell'informativa,
     * linee guida del Garante 2021); i nomi stanno in «Scegli» e nella pagina /privacy.
     */
    public static function finalita(bool $en): string
    {
        $c = self::config();
        $s = $c['ga4'] !== ''; $a = $c['clarity'] !== '';
        if ($en) return $s && $a ? 'statistics and usage analysis cookies' : ($s ? 'statistics cookies' : 'usage analysis cookies');
        return $s && $a ? 'cookie di statistica e di analisi dell\'uso' : ($s ? 'cookie di statistica' : 'cookie di analisi dell\'uso');
    }

    /** Come finalita(), ma con i nomi degli strumenti (per chi li vuole anche nel banner: {strumenti}). */
    public static function strumenti(bool $en): string
    {
        $c = self::config(); $v = [];
        if ($c['ga4'] !== '') $v[] = $en ? 'statistics tools (Google Analytics)' : 'strumenti di statistica (Google Analytics)';
        if ($c['clarity'] !== '') $v[] = $en ? 'usage analysis tools (Microsoft Clarity)' : ($v ? 'di analisi dell\'uso (Microsoft Clarity)' : 'strumenti di analisi dell\'uso (Microsoft Clarity)');
        return implode($en ? ' and ' : ' e ', $v);
    }
}
