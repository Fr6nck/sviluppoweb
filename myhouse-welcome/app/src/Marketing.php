<?php
namespace MHW;

/**
 * Le statistiche di marketing (7F): Amministrazione → Vendite → Marketing. Solo lettura.
 *
 * Il periodo (30, 90, 365 giorni o tutto) vale così:
 *   - guide (tipologia, piano, regione, città) e uso (sezioni, lingue): le guide CREATE nel periodo;
 *   - percorso: chi si è REGISTRATO nel periodo, seguito passo per passo fino a oggi;
 *   - le guide più visitate: aperture e scansioni del QR NEL periodo;
 *   - inviti: mandati, amici registrati e amici che hanno pagato NEL periodo.
 * Fuori sempre: clienti di esempio (Demo::DOMINIO), clienti di prova (7F), guide vetrina e di esempio.
 *
 * Ogni tabella: ['titolo', 'nota', 'colonne', 'righe' => list<list>, 'barra' => indice della colonna
 * con il numero da disegnare, 'totale' => il cento per cento delle barre]. La stessa struttura
 * diventa la pagina e il CSV.
 */
final class Marketing
{
    public const PERIODI = ['30' => '30 giorni', '90' => '90 giorni', '365' => '365 giorni', 'tutto' => 'Tutto'];

    public const LINGUE = ['it' => 'Italiano', 'en' => 'Inglese', 'fr' => 'Francese', 'de' => 'Tedesco', 'es' => 'Spagnolo',
                           'nl' => 'Olandese', 'pt' => 'Portoghese (Portogallo)', 'pt-BR' => 'Portoghese (Brasile)', 'pl' => 'Polacco', 'ru' => 'Russo', 'zh' => 'Cinese',
                           'ja' => 'Giapponese', 'sv' => 'Svedese', 'da' => 'Danese', 'cs' => 'Ceco'];

    /** Le prime due cifre del CAP → la regione (la provincia si deduce dal CAP, la regione dalla provincia). */
    private const CAP_REGIONE = [
        '00' => 'Lazio', '01' => 'Lazio', '02' => 'Lazio', '03' => 'Lazio', '04' => 'Lazio',
        '05' => 'Umbria', '06' => 'Umbria',
        '07' => 'Sardegna', '08' => 'Sardegna', '09' => 'Sardegna',
        '10' => 'Piemonte', '12' => 'Piemonte', '13' => 'Piemonte', '14' => 'Piemonte', '15' => 'Piemonte', '28' => 'Piemonte',
        '11' => 'Valle d\'Aosta',
        '16' => 'Liguria', '17' => 'Liguria', '18' => 'Liguria', '19' => 'Liguria',
        '20' => 'Lombardia', '21' => 'Lombardia', '22' => 'Lombardia', '23' => 'Lombardia', '24' => 'Lombardia', '25' => 'Lombardia',
        '26' => 'Lombardia', '27' => 'Lombardia', '46' => 'Lombardia',
        '29' => 'Emilia-Romagna', '40' => 'Emilia-Romagna', '41' => 'Emilia-Romagna', '42' => 'Emilia-Romagna', '43' => 'Emilia-Romagna',
        '44' => 'Emilia-Romagna', '47' => 'Emilia-Romagna', '48' => 'Emilia-Romagna',
        '30' => 'Veneto', '31' => 'Veneto', '32' => 'Veneto', '35' => 'Veneto', '36' => 'Veneto', '37' => 'Veneto', '45' => 'Veneto',
        '33' => 'Friuli-Venezia Giulia', '34' => 'Friuli-Venezia Giulia',
        '38' => 'Trentino-Alto Adige', '39' => 'Trentino-Alto Adige',
        '50' => 'Toscana', '51' => 'Toscana', '52' => 'Toscana', '53' => 'Toscana', '54' => 'Toscana', '55' => 'Toscana',
        '56' => 'Toscana', '57' => 'Toscana', '58' => 'Toscana', '59' => 'Toscana',
        '60' => 'Marche', '61' => 'Marche', '62' => 'Marche', '63' => 'Marche',
        '64' => 'Abruzzo', '65' => 'Abruzzo', '66' => 'Abruzzo', '67' => 'Abruzzo',
        '86' => 'Molise',
        '70' => 'Puglia', '71' => 'Puglia', '72' => 'Puglia', '73' => 'Puglia', '74' => 'Puglia', '76' => 'Puglia',
        '75' => 'Basilicata', '85' => 'Basilicata',
        '80' => 'Campania', '81' => 'Campania', '82' => 'Campania', '83' => 'Campania', '84' => 'Campania',
        '87' => 'Calabria', '88' => 'Calabria', '89' => 'Calabria',
        '90' => 'Sicilia', '91' => 'Sicilia', '92' => 'Sicilia', '93' => 'Sicilia', '94' => 'Sicilia', '95' => 'Sicilia',
        '96' => 'Sicilia', '97' => 'Sicilia', '98' => 'Sicilia',
    ];

    public static function periodo(string $p): string { return isset(self::PERIODI[$p]) ? $p : '90'; }

    /** Da quando (ISO), o '' per «tutto». */
    private static function da(string $periodo): string
    {
        return $periodo === 'tutto' ? '' : gmdate('Y-m-d\TH:i:s\Z', time() - (int) $periodo * 86400);
    }

    /** La regione di una struttura: quella scritta, altrimenti dalla provincia (9F), altrimenti dal CAP. */
    public static function regione(array $p): string
    {
        $r = trim((string) ($p['region'] ?? ''));
        if ($r !== '') return $r;
        if (($rp = Territorio::regioneDi((string) ($p['province'] ?? ''))) !== '') return $rp;
        $cap = preg_replace('/\D/', '', (string) ($p['postal_code'] ?? ''));
        return strlen($cap) === 5 ? (self::CAP_REGIONE[substr($cap, 0, 2)] ?? 'Non indicata') : 'Non indicata';
    }

    /** Le condizioni sulle strutture «vere»: niente esempi, vetrine, clienti di esempio o di prova. */
    private static function vere(string $p = 'p', string $u = 'u'): string
    {
        return "$p.is_demo = 0 AND $u.email NOT LIKE '%@" . Demo::DOMINIO . "'" . Gestione::senzaProva("$p.account_id");
    }

    private static function perc(int $n, int $tot): string
    {
        return $tot > 0 ? number_format($n * 100 / $tot, $n * 100 % $tot === 0 ? 0 : 1, ',', '') . '%' : '—';
    }

    /** Una tabella «etichetta, quante, %» da un conteggio, dalla più grande. */
    private static function distribuzione(string $titolo, string $nota, string $voce, array $conta, int $totale, int $limite = 0): array
    {
        arsort($conta);
        if ($limite > 0) $conta = array_slice($conta, 0, $limite, true);
        $righe = [];
        foreach ($conta as $k => $n) $righe[] = [(string) $k, (int) $n, self::perc((int) $n, $totale)];
        return ['titolo' => $titolo, 'nota' => $nota, 'colonne' => [$voce, 'Guide', '%'], 'righe' => $righe, 'barra' => 1, 'totale' => max(1, $totale)];
    }

    /** @return array<string,array> tutte le tabelle della pagina, per chiave (la chiave è anche il nome del CSV) */
    public static function tabelle(string $periodo): array
    {
        $periodo = self::periodo($periodo);
        $da = self::da($periodo);
        $dal = fn(string $col) => $da === '' ? '' : " AND $col >= '" . $da . "'";
        $t = [];

        // ---------------------------------------------------------------- le guide create nel periodo
        $guide = Db::all('SELECT p.id, p.account_id, p.property_type, p.property_type_other, p.city, p.region, ' . (Migrator::columnExists('properties', 'province') ? 'p.province, ' : '') . 'p.postal_code, p.status
                          FROM properties p JOIN accounts a ON a.id = p.account_id JOIN users u ON u.id = a.user_id
                          WHERE ' . self::vere() . $dal('p.created_at'));
        $n = count($guide);
        $tipi = []; $piani = []; $regioni = []; $citta = [];
        $pianoDi = [];
        foreach ($guide as $g) {
            $tipo = Properties::TIPOLOGIE[(string) $g['property_type']] ?? 'Non indicata';
            $tipi[$tipo] = ($tipi[$tipo] ?? 0) + 1;
            $aid = (int) $g['account_id'];
            if (!isset($pianoDi[$aid])) {
                $s = Subscriptions::active($aid);
                $v = $s ? Plans::version((int) $s['package_version_id']) : null;
                $pianoDi[$aid] = $v ? (string) $v['name'] : 'Senza abbonamento';
            }
            $piani[$pianoDi[$aid]] = ($piani[$pianoDi[$aid]] ?? 0) + 1;
            $reg = self::regione($g);
            $regioni[$reg] = ($regioni[$reg] ?? 0) + 1;
            $c = trim((string) $g['city']);
            if ($c !== '') { $c = mb_convert_case(mb_strtolower($c), MB_CASE_TITLE); $citta[$c] = ($citta[$c] ?? 0) + 1; }
        }
        $nota = $n === 1 ? '1 guida creata nel periodo.' : "$n guide create nel periodo.";
        $t['tipologie'] = self::distribuzione('Guide per tipologia', $nota, 'Tipologia', $tipi, $n);
        $t['piani'] = self::distribuzione('Guide per piano', $nota . ' Il piano è quello attivo adesso nell\'account.', 'Piano', $piani, $n);
        $t['regioni'] = self::distribuzione('Guide per regione', $nota . ' Regione dal CAP, se non è scritta.', 'Regione', $regioni, $n);
        $t['citta'] = self::distribuzione('Le 10 città con più guide', $nota, 'Città', $citta, $n, 10);

        // ---------------------------------------------------------------- il percorso di chi si è registrato nel periodo
        $utenti = Db::all("SELECT u.id, u.email_verified_at, a.id AS aid FROM users u JOIN accounts a ON a.user_id = u.id
                           WHERE u.role = 'host' AND u.email NOT LIKE '%@" . Demo::DOMINIO . "'" . Gestione::senzaProva('a.id') . $dal('u.created_at'));
        $passi = ['Registrati' => count($utenti), 'Email confermata' => 0, 'Guida iniziata' => 0, 'Guida pubblicata' => 0, 'Pagato' => 0];
        foreach ($utenti as $x) {
            $aid = (int) $x['aid'];
            if (!empty($x['email_verified_at'])) $passi['Email confermata']++;
            if (Db::val('SELECT 1 FROM properties WHERE account_id = ? AND is_demo = 0', [$aid])) $passi['Guida iniziata']++;
            if (Db::val("SELECT 1 FROM properties WHERE account_id = ? AND is_demo = 0 AND (status = 'published' OR published_at <> '')", [$aid])) $passi['Guida pubblicata']++;
            if (Db::val("SELECT 1 FROM orders WHERE account_id = ? AND status = 'paid' AND provider = 'stripe'", [$aid])) $passi['Pagato']++;
        }
        $righe = []; $prima = null;
        foreach ($passi as $nome => $q) {
            $righe[] = [$nome, $q, self::perc($q, $passi['Registrati']), $prima === null ? '—' : self::perc($q, $prima)];
            $prima = $q;
        }
        $t['percorso'] = ['titolo' => 'Il percorso', 'nota' => 'Chi si è registrato nel periodo, e fin dove è arrivato a oggi. «Pagato»: almeno un pagamento con Stripe (gli omaggi non contano).',
                          'colonne' => ['Passo', 'Persone', '% dei registrati', '% dal passo prima'], 'righe' => $righe, 'barra' => 1, 'totale' => max(1, $passi['Registrati'])];

        if (Migrator::tableExists('feedback')) {
            $motivi = [];
            foreach (Db::all('SELECT f.motivo, COUNT(*) AS n FROM feedback f WHERE 1 = 1' . Gestione::senzaProva('f.account_id') . $dal('f.created_at') . ' GROUP BY f.motivo') as $r) {
                $motivi[Richiami::MOTIVI[$r['motivo']] ?? (string) $r['motivo']] = (int) $r['n'];
            }
            $tm = array_sum($motivi);
            arsort($motivi);
            $t['motivi'] = ['titolo' => '«Cosa ti ha fermato?»', 'nota' => 'Le risposte all\'email mandata a chi non ha pubblicato dopo due settimane, nel periodo.',
                            'colonne' => ['Motivo', 'Risposte', '%'], 'righe' => array_map(fn($k, $q) => [$k, $q, self::perc($q, $tm)], array_keys($motivi), $motivi),
                            'barra' => 1, 'totale' => max(1, $tm)];
        }

        // ---------------------------------------------------------------- sezioni e lingue delle guide pubblicate (create nel periodo)
        $pubblicate = array_values(array_filter($guide, fn($g) => $g['status'] === 'published'));
        $np = count($pubblicate);
        $sezioni = []; $lingue = [];
        if ($np) {
            $ids = implode(',', array_map(fn($g) => (int) $g['id'], $pubblicate));
            foreach (Db::all("SELECT kind, COUNT(DISTINCT property_id) AS n FROM sections WHERE is_active = 1 AND property_id IN ($ids) GROUP BY kind") as $r) {
                $sezioni[SectionCatalog::nome((string) $r['kind'])] = (int) $r['n'];
            }
            foreach (Db::all("SELECT locale, COUNT(DISTINCT property_id) AS n FROM property_locales WHERE property_id IN ($ids) GROUP BY locale") as $r) {
                $lingue[self::LINGUE[$r['locale']] ?? (string) $r['locale']] = (int) $r['n'];
            }
        }
        $notaP = ($np === 1 ? '1 guida pubblicata' : "$np guide pubblicate") . ' tra quelle create nel periodo: la % è su queste.';
        $t['sezioni'] = self::distribuzione('Le sezioni più usate', $notaP . ' Contano le sezioni attive.', 'Sezione', $sezioni, $np);
        $t['lingue'] = self::distribuzione('Le lingue attive', $notaP, 'Lingua', $lingue, $np);

        // ---------------------------------------------------------------- le 20 guide più visitate nel periodo
        $giorno = $da === '' ? '' : " AND e.day >= '" . substr($da, 0, 10) . "'";
        $top = Db::all("SELECT p.id, p.name, p.property_type, p.account_id,
                               SUM(CASE WHEN e.kind IN ('guide_view','open') THEN 1 ELSE 0 END) AS aperture,
                               SUM(CASE WHEN e.kind IN ('qr_open','qr') THEN 1 ELSE 0 END) AS qr
                        FROM analytics_events e JOIN properties p ON p.id = e.property_id JOIN accounts a ON a.id = p.account_id JOIN users u ON u.id = a.user_id
                        WHERE e.kind IN ('guide_view','open','qr_open','qr') AND " . self::vere() . $giorno . "
                        GROUP BY p.id, p.name, p.property_type, p.account_id ORDER BY aperture DESC, qr DESC, p.id LIMIT 20");
        $righe = [];
        foreach ($top as $x) {
            if (!isset($pianoDi[(int) $x['account_id']])) {
                $s = Subscriptions::active((int) $x['account_id']);
                $v = $s ? Plans::version((int) $s['package_version_id']) : null;
                $pianoDi[(int) $x['account_id']] = $v ? (string) $v['name'] : 'Senza abbonamento';
            }
            $righe[] = [(string) $x['name'], (int) $x['aperture'], (int) $x['qr'], Properties::TIPOLOGIE[(string) $x['property_type']] ?? 'Non indicata',
                        $pianoDi[(int) $x['account_id']], (int) $x['account_id']];
        }
        $t['visitate'] = ['titolo' => 'Le 20 guide più visitate', 'nota' => 'Aperture della guida e scansioni del QR nel periodo. Le aperture dal QR contano in tutte e due.',
                          'colonne' => ['Struttura', 'Aperture', 'Scansioni del QR', 'Tipologia', 'Piano', 'Cliente'], 'righe' => $righe, 'barra' => 1,
                          'totale' => max(1, (int) ($righe[0][1] ?? 1)), 'cliente' => 5];

        // ---------------------------------------------------------------- inviti
        if (Migrator::tableExists('referrals')) {
            $mandati = Migrator::tableExists('referral_invites')
                ? (int) Db::val('SELECT COUNT(*) FROM referral_invites r WHERE 1 = 1' . Gestione::senzaProva('r.account_id') . $dal('r.sent_at'), [], 0) : 0;
            $registrati = (int) Db::val('SELECT COUNT(*) FROM referrals r WHERE 1 = 1' . Gestione::senzaProva('r.referrer_account_id') . $dal('r.created_at'), [], 0);
            $paganti = (int) Db::val("SELECT COUNT(*) FROM referrals r WHERE r.status IN ('valido','usato','oltre') AND r.qualified_at IS NOT NULL"
                                     . Gestione::senzaProva('r.referrer_account_id') . $dal('r.qualified_at'), [], 0);
            $t['inviti'] = ['titolo' => 'Inviti', 'nota' => 'Inviti mandati per email da «Invita un amico», amici registrati dal link e amici che hanno pagato il primo abbonamento, nel periodo.',
                            'colonne' => ['', 'Quanti', '%'], 'righe' => [['Inviti mandati per email', $mandati, '—'],
                                                                        ['Amici registrati', $registrati, $mandati > 0 ? self::perc($registrati, $mandati) : '—'],
                                                                        ['Amici che hanno pagato', $paganti, self::perc($paganti, $registrati)]],
                            'barra' => 1, 'totale' => max(1, $mandati, $registrati, $paganti)];
        }
        // ---------------------------------------------------------------- 9D · codici omaggio
        if (Migrator::tableExists('codici_omaggio')) {
            $creati = (int) Db::val('SELECT COUNT(*) FROM codici_omaggio c WHERE 1 = 1' . $dal('c.created_at'), [], 0);
            $usati = (int) Db::val('SELECT COUNT(*) FROM codici_omaggio c WHERE c.usato_at IS NOT NULL' . Gestione::senzaProva('c.usato_da_account_id') . $dal('c.usato_at'), [], 0);
            // Diventati paganti: i mesi gratis sono finiti e l'abbonamento è ancora attivo (il primo addebito è passato).
            $paganti = (int) Db::val("SELECT COUNT(DISTINCT s.account_id) FROM subscriptions s JOIN codici_omaggio c ON c.usato_da_account_id = s.account_id
                                      WHERE s.omaggio_fino IS NOT NULL AND s.omaggio_fino <= ? AND s.status = 'active'" . Gestione::senzaProva('s.account_id') . $dal('c.usato_at'), [Support::now()], 0);
            $t['omaggi'] = ['titolo' => 'Codici omaggio', 'nota' => 'Codici creati, codici usati (abbonamento partito con i mesi gratis) e clienti diventati paganti alla fine dell\'omaggio, nel periodo.',
                            'colonne' => ['', 'Quanti', '%'], 'righe' => [['Codici creati', $creati, '—'],
                                                                        ['Codici usati', $usati, $creati > 0 ? self::perc($usati, $creati) : '—'],
                                                                        ['Diventati paganti', $paganti, $usati > 0 ? self::perc($paganti, $usati) : '—']],
                            'barra' => 1, 'totale' => max(1, $creati, $usati, $paganti)];
        }
        return $t;
    }
}
