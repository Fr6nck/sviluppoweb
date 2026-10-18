<?php
namespace MHW;

/**
 * 8J · I documenti legali: Termini e condizioni, Informativa privacy e Accordo sul trattamento dei dati (DPA,
 * Allegato A dei Termini), in italiano e in inglese. I testi stanno in lang/legale.php e si cambiano da
 * Amministrazione → Testi e foto → Documenti legali (TestiSito, chiavi termini.*, privacy.*, dpa.*).
 *
 * Le versioni sono date (config.php → legal, Impostazioni → Titolare e documenti legali): la data da cui il testo vale.
 *   - Ogni accettazione resta legata alla versione letta: users.terms_version / privacy_version / dpa_version
 *     e, una riga per documento, legal_acceptances (migrazione 042).
 *   - Versione nel futuro: i clienti con un abbonamento attivo ricevono un'email una volta sola (avvisa(), dal giro
 *     dei richiami) e nel pannello vedono una riga; a quella data compare la finestra «Accetto».
 *   - Versione arrivata e non ancora accettata: la finestra nel pannello, e finché non accetta il cliente può solo
 *     leggere (solaLettura(): niente moduli, tranne «Accetto» ed «Esci»). Mai durante l'accesso dell'assistenza.
 *   - Ogni testo in vigore si archivia da solo (legal_versions) quando la pagina si apre: il link «Versioni precedenti»
 *     mostra quelli sostituiti, così com'erano.
 */
final class Legale
{
    /** documento => [nome italiano, nome inglese, percorso italiano, percorso inglese, chiave della versione in config] */
    public const DOCUMENTI = [
        'termini' => ['Termini e condizioni', 'Terms and conditions', '/termini', '/en/terms', 'terms_version'],
        'privacy' => ['Informativa sulla privacy', 'Privacy policy', '/privacy', '/en/privacy', 'privacy_version'],
        'dpa'     => ['Accordo sul trattamento dei dati', 'Data processing agreement', '/dpa', '/en/dpa', 'dpa_version'],
    ];

    /** Le colonne di users per documento: [versione, data di accettazione]. */
    private const COLONNE = ['termini' => ['terms_version', 'terms_accepted_at'], 'privacy' => ['privacy_version', 'privacy_accepted_at'],
                             'dpa' => ['dpa_version', 'dpa_accepted_at']];

    /** I percorsi che restano aperti (POST) a chi deve ancora accettare. */
    public const APERTI = ['/legale/accetta', '/esci', '/admin/esci-da-cliente'];

    public static function disponibili(): bool { return Migrator::tableExists('legal_acceptances'); }

    /** @return array{termini:string,privacy:string,dpa:string} le versioni in uso */
    public static function versioni(): array
    {
        $l = Config::get('legal') ?? [];
        $v = [];
        foreach (self::DOCUMENTI as $d => $x) $v[$d] = (string) ($l[$x[4]] ?? '');
        return $v;
    }

    /** Una versione che vale da una data futura (annunciata, non ancora in vigore). */
    public static function futura(string $v): bool { return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) === 1 && $v > gmdate('Y-m-d'); }

    /** La firma delle tre versioni insieme: per l'avviso email, una volta sola per combinazione. */
    public static function firma(): string { return implode('|', self::versioni()); }

    // ------------------------------------------------------------------ il titolare

    /** La riga del titolare per il piè di pagina e le email (art. 2250 c.c., D.Lgs. 70/2003). */
    public static function rigaTitolare(bool $en = false): string
    {
        $l = Config::get('legal') ?? [];
        return implode(' · ', array_filter([
            (string) ($l['company'] ?? ''),
            ($l['company_vat'] ?? '') !== '' ? ($en ? 'VAT no. ' : 'P.IVA ') . $l['company_vat'] : '',
            (string) ($l['company_city'] ?? ''),
            ($l['company_pec'] ?? '') !== '' ? 'PEC ' . $l['company_pec'] : '',
        ]));
    }

    /**
     * I segnaposto dei testi legali, già sicuri per l'HTML (TestiSito::html li mette dopo aver escapato il resto).
     * @return array<string,string>
     */
    public static function segnaposto(bool $en = false): array
    {
        $l = Config::get('legal') ?? [];
        $e = fn($s) => Support::e((string) $s);
        $email = (string) ($l['contact_email'] ?? '');
        $sito = (string) ($l['company_site'] ?? '');
        $pec = (string) ($l['company_pec'] ?? '');
        $b = Support::base();
        $mail = Config::get('mail') ?? [];
        // Il fornitore della posta, dall'impostazione: il dominio del server SMTP (per esempio smtp.hostinger.com → Hostinger).
        $posta = ($mail['transport'] ?? '') === 'smtp' && ($mail['host'] ?? '') !== '' ? self::fornitorePosta((string) $mail['host'])
               : (($mail['transport'] ?? '') === 'mail' ? ($en ? 'the hosting provider\'s mail server' : 'il server di posta dell\'hosting') : ($en ? 'the provider set in the email settings' : 'il fornitore configurato nelle impostazioni della posta'));
        return [
            'titolare' => $e($l['company'] ?? ''),
            'titolare_completo' => $e(($l['company_legal'] ?? '') !== '' ? $l['company_legal'] : ($l['company'] ?? '')),
            'piva' => $e($l['company_vat'] ?? ''),
            'sede' => $e($l['company_city'] ?? ''),
            'pec' => $pec !== '' ? '<a href="mailto:' . $e($pec) . '">' . $e($pec) . '</a>' : '',
            'email' => $email !== '' ? '<a href="mailto:' . $e($email) . '">' . $e($email) . '</a>' : '',
            'email_fatture' => ($f = Support::amministrazione()) !== '' ? '<a href="mailto:' . $e($f) . '">' . $e($f) . '</a>' : '',   // 9H
            'telefono' => $e($l['contact_phone'] ?? ''),
            'sito_titolare' => $sito !== '' ? '<a href="' . $e($sito) . '" rel="noopener" target="_blank">' . $e(preg_replace('#^https?://#', '', $sito)) . '</a>' : '',
            'link_termini' => '<a href="' . $b . ($en ? '/en/terms' : '/termini') . '">' . ($en ? 'Terms and conditions' : 'Termini e condizioni') . '</a>',
            'link_privacy' => '<a href="' . $b . ($en ? '/en/privacy' : '/privacy') . '">' . ($en ? 'Privacy policy' : 'Informativa sulla privacy') . '</a>',
            'link_dpa' => '<a href="' . $b . ($en ? '/en/dpa' : '/dpa') . '">' . ($en ? 'Data processing agreement' : 'Accordo sul trattamento dei dati') . '</a>',
            'link_modulo' => '<a href="' . $b . ($en ? '/en/terms' : '/termini') . '#modulo-recesso">' . ($en ? 'model withdrawal form' : 'modulo tipo di recesso') . '</a>',
            'amico' => (string) Inviti::AMICO, 'passo' => (string) Inviti::PASSO, 'max' => (string) Inviti::MASSIMO, 'amici' => (string) Inviti::amiciMassimi(),
            'giudizio' => (string) Giudizi::percentuale(),   // 9B
            'regione_traduzioni' => $e((string) (Traduttore::config()['region'] ?? 'eu-west-1')),
            'giorni_registri' => (string) Registri::config()['giorni'],
            'posta' => $e($posta),
        ];
    }

    /** smtp.hostinger.com → Hostinger; mail.example.it → example.it. */
    public static function fornitorePosta(string $host): string
    {
        $noti = ['hostinger' => 'Hostinger', 'gmail' => 'Google (Gmail)', 'google' => 'Google', 'office365' => 'Microsoft (Office 365)', 'outlook' => 'Microsoft (Outlook)',
                 'aruba' => 'Aruba', 'register' => 'Register.it', 'amazonaws' => 'Amazon SES', 'brevo' => 'Brevo', 'sendinblue' => 'Brevo', 'mailgun' => 'Mailgun', 'sendgrid' => 'SendGrid'];
        foreach ($noti as $k => $n) if (stripos($host, $k) !== false) return $n;
        $p = explode('.', strtolower($host));
        return implode('.', array_slice($p, -2));
    }

    // ------------------------------------------------------------------ accettazioni

    /** I documenti che questo utente deve (ri)accettare adesso: versione in vigore diversa da quella accettata. @return string[] */
    public static function daAccettare(?array $u): array
    {
        if (!$u || ($u['role'] ?? '') !== 'host' || !self::disponibili()) return [];
        $out = [];
        foreach (self::versioni() as $d => $v) {
            if ($v === '' || self::futura($v)) continue;
            if ((string) ($u[self::COLONNE[$d][0]] ?? '') !== $v) $out[] = $d;
        }
        return $out;
    }

    /** I documenti annunciati (versione futura) che l'utente non ha ancora accettato. @return string[] */
    public static function annunciati(?array $u): array
    {
        if (!$u || ($u['role'] ?? '') !== 'host' || !self::disponibili()) return [];
        $out = [];
        foreach (self::versioni() as $d => $v) if (self::futura($v) && (string) ($u[self::COLONNE[$d][0]] ?? '') !== $v) $out[] = $d;
        return $out;
    }

    /** Quanti clienti devono ancora accettare la versione in vigore di almeno un documento. */
    public static function daRiaccettare(): int
    {
        if (!self::disponibili()) return 0;
        $cond = []; $args = [];
        foreach (self::versioni() as $d => $v) {
            if ($v === '' || self::futura($v)) continue;
            $c = self::COLONNE[$d][0];
            $cond[] = "($c IS NULL OR $c <> ?)"; $args[] = $v;
        }
        if (!$cond) return 0;
        return (int) Db::val("SELECT COUNT(*) FROM users WHERE role = 'host' AND (" . implode(' OR ', $cond) . ')', $args, 0);
    }

    /**
     * Registra l'accettazione delle versioni in uso (anche quelle annunciate: chi accetta prima, ha accettato quelle).
     * $modo: registrazione, riaccettazione.
     */
    public static function accetta(int $userId, string $modo): void
    {
        $ora = Support::now();
        $riga = [];
        foreach (self::versioni() as $d => $v) {
            [$cv, $ca] = self::COLONNE[$d];
            if (!Migrator::columnExists('users', $cv)) continue;
            $riga[$cv] = $v; $riga[$ca] = $ora;
        }
        if ($riga) Db::update('users', $riga, 'id = :uid', ['uid' => $userId]);
        if (!self::disponibili()) return;
        foreach (self::versioni() as $d => $v) {
            Db::insert('legal_acceptances', ['user_id' => $userId, 'doc' => $d, 'version' => $v, 'modo' => $modo, 'created_at' => $ora]);
        }
    }

    /**
     * Il pannello in sola lettura per chi deve ancora accettare: ogni POST (salvare, pubblicare, pagare…) torna
     * indietro con un messaggio, tranne «Accetto» ed «Esci». Mai per l'amministrazione né durante l'accesso dell'assistenza.
     */
    public static function solaLettura(string $route): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || Auth::isImpersonating() || in_array($route, self::APERTI, true)) return;
        $u = Auth::user();
        if (!$u || !self::daAccettare($u)) return;
        Support::flash('Prima di continuare leggi e accetta i Termini e l\'Informativa aggiornati: finché non li accetti puoi solo consultare il pannello.', 'err');
        $torna = parse_url((string) ($_SERVER['HTTP_REFERER'] ?? ''), PHP_URL_PATH) ?: '';
        $torna = Support::routePath($torna);
        Support::redirect(preg_match('#^/(pannello|account|piano)#', $torna) ? $torna : '/pannello');
    }

    // ------------------------------------------------------------------ avviso ai clienti

    /**
     * L'email «Abbiamo aggiornato i Termini e l'Informativa», una volta sola per combinazione di versioni,
     * ai clienti con un abbonamento attivo che non le hanno ancora accettate. Gira con i richiami (e /cron).
     * Non si può disattivare: è un avviso contrattuale. Mai ai clienti di prova (7F) né agli account dimostrativi.
     * @return int quante email
     */
    public static function avvisa(int $max = 200): int
    {
        if (!self::disponibili() || !Richiami::disponibili() || !Migrator::columnExists('users', 'dpa_version')) return 0;
        $v = self::versioni();
        $firma = self::firma();
        $mandate = 0;
        $righe = Db::all("SELECT DISTINCT u.id, u.email, u.name, u.terms_version, u.privacy_version, u.dpa_version, a.id AS acc_id
                          FROM users u JOIN accounts a ON a.user_id = u.id JOIN subscriptions s ON s.account_id = a.id
                          WHERE u.role = 'host' AND s.status IN ('active', 'trialing') AND s.provider NOT IN ('dimostrazione')
                            AND u.email NOT LIKE ? AND (u.terms_version IS NULL OR u.terms_version <> ? OR u.privacy_version IS NULL OR u.privacy_version <> ?
                            OR u.dpa_version IS NULL OR u.dpa_version <> ?)", ['%@' . Demo::DOMINIO, $v['termini'], $v['privacy'], $v['dpa']]);
        foreach ($righe as $r) {
            if ($mandate >= $max) break;
            $acc = (int) $r['acc_id'];
            if (Richiami::diProva($acc) || Db::one('SELECT id FROM email_log WHERE account_id = ? AND kind = ? AND ref = ?', [$acc, 'legale', $firma])) continue;
            try { Db::insert('email_log', ['account_id' => $acc, 'kind' => 'legale', 'ref' => $firma, 'token' => bin2hex(random_bytes(16)), 'sent_at' => Support::now()]); }
            catch (\Throwable) { continue; }
            if (self::emailAvviso($r)) $mandate++;
            else Db::run('DELETE FROM email_log WHERE account_id = ? AND kind = ? AND ref = ?', [$acc, 'legale', $firma]);
        }
        return $mandate;
    }

    private static function emailAvviso(array $u): bool
    {
        $v = self::versioni();
        $quando = max($v);
        $nome = trim(explode(' ', trim((string) $u['name']))[0] ?? '');
        $futura = self::futura($quando);
        $paragrafi = ['Ciao' . ($nome !== '' ? ' ' . $nome : '') . ', ' . ($futura
            ? 'dal ' . Support::date($quando) . ' cambiano i Termini e condizioni, l\'Informativa sulla privacy e l\'Accordo sul trattamento dei dati di MyHouse Welcome. Ecco le novità principali:'
            : 'abbiamo aggiornato i Termini e condizioni, l\'Informativa sulla privacy e l\'Accordo sul trattamento dei dati di MyHouse Welcome. Ecco le novità principali:')];
        foreach (TestiSito::lista('legale.novita') as $riga) $paragrafi[] = '• ' . html_entity_decode(strip_tags($riga), ENT_QUOTES, 'UTF-8');
        $paragrafi[] = $futura
            ? 'Puoi leggerli già adesso. Se non li accetti puoi disdire l\'abbonamento senza penali fino a quella data; dopo, continuando a usare il servizio, li accetti. Le condizioni economiche del periodo che hai già pagato non cambiano.'
            : 'Al prossimo accesso al pannello ti chiederemo di accettarli. Le condizioni economiche del periodo che hai già pagato non cambiano.';
        $b = Support::baseUrl();
        return Mailer::manda((string) $u['email'], 'Aggiornamento dei Termini e dell\'Informativa', 'Abbiamo aggiornato i documenti legali', $paragrafi,
            ['Leggi i Termini', $b . '/termini'], 'Informativa: ' . $b . '/privacy · Accordo sul trattamento dei dati: ' . $b . '/dpa');
    }

    // ------------------------------------------------------------------ archivio delle versioni

    /** Salva il testo di questa versione così come si legge adesso (una riga per documento, lingua e versione). */
    public static function archivia(string $doc, string $loc, string $versione, string $html): void
    {
        if (!Migrator::tableExists('legal_versions') || $versione === '' || !isset(self::DOCUMENTI[$doc])) return;
        $hash = hash('sha256', $html);
        $r = Db::one('SELECT id, hash FROM legal_versions WHERE doc = ? AND locale = ? AND version = ?', [$doc, $loc, $versione]);
        try {
            if (!$r) Db::insert('legal_versions', ['doc' => $doc, 'locale' => $loc, 'version' => $versione, 'html' => $html, 'hash' => $hash,
                                                   'created_at' => Support::now(), 'updated_at' => Support::now()]);
            elseif ($r['hash'] !== $hash) Db::update('legal_versions', ['html' => $html, 'hash' => $hash, 'updated_at' => Support::now()], 'id = :id', ['id' => $r['id']]);
        } catch (\Throwable $e) { Log::exception($e, 'Legale::archivia'); }
    }

    /** Le versioni archiviate diverse da quella in uso, dalla più recente. @return list<array{version:string,locale:string,updated_at:string}> */
    public static function precedenti(string $doc, ?string $loc = null): array
    {
        if (!Migrator::tableExists('legal_versions')) return [];
        $attuale = self::versioni()[$doc] ?? '';
        $sql = 'SELECT version, locale, updated_at FROM legal_versions WHERE doc = ? AND version <> ?' . ($loc ? ' AND locale = ?' : '') . ' ORDER BY version DESC, locale';
        return Db::all($sql, $loc ? [$doc, $attuale, $loc] : [$doc, $attuale]);
    }

    /** Il testo archiviato di una versione (in quella lingua, altrimenti in italiano), o null. */
    public static function archiviata(string $doc, string $versione, string $loc): ?array
    {
        if (!Migrator::tableExists('legal_versions')) return null;
        return Db::one('SELECT * FROM legal_versions WHERE doc = ? AND version = ? AND locale = ?', [$doc, $versione, $loc])
            ?? Db::one('SELECT * FROM legal_versions WHERE doc = ? AND version = ? AND locale = ?', [$doc, $versione, 'it']);
    }

    // ------------------------------------------------------------------ testi

    /**
     * Un testo legale in HTML: paragrafi separati da una riga vuota; righe che cominciano con «- » → elenco;
     * righe che cominciano con «| » → tabella a due colonne («| Dati | testo»). Dentro: **grassetto**, [link](…),
     * i segnaposto di segnaposto(). Neutralizzato come tutti i testi del sito (TestiSito::formatta).
     */
    public static function blocchi(string $k, bool $en): string
    {
        $sost = self::segnaposto($en);
        $testo = str_replace("\r", '', TestiSito::get($k, $en ? 'en' : 'it'));
        $out = '';
        foreach (preg_split('/\n\s*\n/', trim($testo)) ?: [] as $blocco) {
            $righe = array_values(array_filter(array_map('trim', explode("\n", $blocco)), fn($r) => $r !== ''));
            if (!$righe) continue;
            if (count(array_filter($righe, fn($r) => str_starts_with($r, '- '))) === count($righe)) {
                $out .= '<ul class="legale__elenco">';
                foreach ($righe as $r) $out .= '<li>' . TestiSito::formatta(substr($r, 2), $sost) . '</li>';
                $out .= "</ul>\n";
            } elseif (str_starts_with($righe[0], '| ')) {
                $out .= '<div class="legale__tabella"><table>';
                foreach ($righe as $r) {
                    $celle = array_map('trim', explode('|', trim($r, "| \t")));
                    $out .= '<tr><th scope="row">' . TestiSito::formatta($celle[0] ?? '', $sost) . '</th><td>' . TestiSito::formatta(implode(' | ', array_slice($celle, 1)), $sost) . '</td></tr>';
                }
                $out .= "</table></div>\n";
            } else {
                $out .= '<p>' . TestiSito::formatta(implode("\n", $righe), $sost) . "</p>\n";
            }
        }
        return $out;
    }
}
