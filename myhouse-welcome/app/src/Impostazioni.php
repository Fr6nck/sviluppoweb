<?php
namespace MHW;

/**
 * Amministrazione → Impostazioni: Stripe, posta in uscita e archivio delle foto
 * impostati dal pannello invece che a mano.
 *
 * I valori finiscono in app/config.local.php, il file che config.php legge già
 * dopo le variabili d'ambiente: è lo stesso posto dove andrebbero scritti a mano.
 * Regole:
 *  - un campo impostato da una variabile d'ambiente (e non da config.local.php)
 *    qui si vede ma non si cambia: comanda il server;
 *  - i segreti non si rimostrano mai: si vede solo che ci sono e come finiscono;
 *    lasciare il campo vuoto li conserva;
 *  - per salvare serve la password dell'amministratore;
 *  - il file si riscrive intero con var_export (i commenti scritti a mano non si
 *    conservano: la versione precedente resta in config.local.bak.php).
 */
final class Impostazioni
{
    /**
     * I campi, per gruppo. [percorso => [variabile d'ambiente, etichetta, tipo, aiuto, opzioni]]
     * Tipi: text, secret, email, url, number, choice, check; cifra (intero), decimale, data (AAAA-MM-GG).
     */
    public const GRUPPI = [
        // 7A: l'indirizzo del sito, gli indirizzi senza index.php e l'indirizzo unico dell'assistenza.
        'sito' => [
            'base_url'       => ['MHW_BASE_URL', 'Indirizzo del sito', 'url', 'Per esempio https://welcomemyhouse.it, senza barra finale. È quello che finisce nei QR, nelle email e nei motori di ricerca.'],
            'pretty_urls'    => ['MHW_PRETTY_URLS', 'Indirizzi puliti, senza index.php', 'check', 'Si accende solo se la prova riesce: il sito chiede a sé stesso una pagina senza index.php. Spento, tutto resta come prima.'],
            'contatti.email' => ['MHW_CONTACT_EMAIL', 'Indirizzo per assistenza, suggerimenti e preventivi', 'email', 'Quello dei bottoni «Scrivici» del sito e del pannello, e del piede delle email. Di serie assistenza@welcomemyhouse.it. È anche l\'indirizzo per i diritti privacy.'],
        ],
        'stripe' => [
            'stripe.secret_key'     => ['STRIPE_SECRET_KEY', 'Chiave segreta', 'secret', 'Stripe → Sviluppatori → Chiavi API. Comincia con sk_live_ (o sk_test_ per le prove).'],
            'stripe.webhook_secret' => ['STRIPE_WEBHOOK_SECRET', 'Segreto del webhook', 'secret', 'Stripe → Sviluppatori → Webhook → il tuo endpoint → «Segreto di firma». Comincia con whsec_.'],
            'stripe.automatic_tax'  => ['STRIPE_AUTOMATIC_TAX', 'Usa Stripe Tax per calcolare l\'IVA', 'check', 'Solo se Stripe Tax è già attivo nel pannello di Stripe.'],
        ],
        'posta' => [
            'mail.transport'  => ['MAIL_TRANSPORT', 'Come parte la posta', 'choice', '', ['smtp' => 'Server SMTP (consigliato)', 'mail' => 'Funzione mail() del server', 'log' => 'Non spedire: scrivi nei registri della posta (storage/logs, 7 giorni)']],
            'mail.host'       => ['MAIL_HOST', 'Server SMTP', 'text', 'Per esempio smtp.tuodominio.it: lo trovi nel cPanel, alla voce «Account email».'],
            'mail.port'       => ['MAIL_PORT', 'Porta', 'number', '587 con STARTTLS, 465 con SSL.'],
            'mail.encryption' => ['MAIL_ENCRYPTION', 'Cifratura', 'choice', '', ['tls' => 'STARTTLS (porta 587)', 'ssl' => 'SSL (porta 465)', 'none' => 'Nessuna (sconsigliato)']],
            'mail.user'       => ['MAIL_USER', 'Utente', 'text', 'Di solito l\'indirizzo email completo.'],
            'mail.pass'       => ['MAIL_PASS', 'Password della casella', 'secret', ''],
            'mail.from'       => ['MAIL_FROM', 'Mittente', 'email', 'L\'indirizzo che vedono i clienti. Meglio se è la stessa casella dell\'utente.'],
            'mail.from_name'  => ['MAIL_FROM_NAME', 'Nome del mittente', 'text', ''],
        ],
        'archivio' => [
            'storage.driver'             => ['MHW_STORAGE', 'Dove si salvano i file nuovi', 'choice', '', ['s3' => 'Amazon S3 (consigliato in produzione)', 'local' => 'Sul disco del server']],
            'storage.s3.region'          => ['AWS_REGION', 'Regione', 'text', 'Per esempio eu-south-1 (Milano).'],
            'storage.s3.bucket'          => ['AWS_S3_BUCKET', 'Bucket', 'text', ''],
            'storage.s3.key'             => ['AWS_ACCESS_KEY_ID', 'Access key ID', 'text', 'Di un utente IAM con s3:PutObject, s3:GetObject e s3:DeleteObject su questo bucket.'],
            'storage.s3.secret'          => ['AWS_SECRET_ACCESS_KEY', 'Secret access key', 'secret', ''],
            'storage.s3.public_base_url' => ['AWS_S3_PUBLIC_URL', 'Indirizzo pubblico (CDN)', 'url', 'Facoltativo: solo con CloudFront o un bucket pubblico in lettura. Vuoto = indirizzi firmati a tempo.'],
        ],
        // Traduzioni suggerite (Traduttore): Amazon Translate, chiamato solo da Plus e Portfolio.
        'traduzioni' => [
            'translate.region'                => ['MHW_TRANSLATE_REGION', 'Regione di Amazon Translate', 'text', 'Predefinita eu-west-1 (Irlanda). Non tutte le regioni hanno il servizio.'],
            'translate.key'                   => ['MHW_TRANSLATE_KEY', 'Access key ID', 'text', 'Di un utente IAM con il solo permesso translate:TranslateText. Vuoto = si usano le chiavi dell\'archivio S3.'],
            'translate.secret'                => ['MHW_TRANSLATE_SECRET', 'Secret access key', 'secret', ''],
            'translate.price_usd_per_million' => ['MHW_TRANSLATE_PRICE', 'Prezzo, in dollari per milione di caratteri', 'decimale', 'Il listino di Amazon Translate: 15.'],
            'translate.usd_eur'               => ['MHW_TRANSLATE_USD_EUR', 'Cambio: quanti euro vale un dollaro', 'decimale', 'Per le stime in euro, per esempio 0,86.'],
            'translate.free_tier_until'       => ['MHW_TRANSLATE_FREE_UNTIL', 'Fine del piano gratuito di AWS', 'data', '2 milioni di caratteri al mese per 12 mesi dalla prima traduzione. Vuoto = nessun piano gratuito.'],
            'translate.cap_account'           => ['MHW_TRANSLATE_CAP_ACCOUNT', 'Tetto per account, caratteri al mese', 'cifra', 'Oltre, quel cliente non riceve altre suggerite fino al mese dopo. Predefinito 150.000.'],
            'translate.extra'                 => ['MHW_TRANSLATE_EXTRA', 'Traduci anche la lingua aggiuntiva', 'check', 'Le traduzioni suggerite anche verso la lingua in più di Plus e Portfolio (olandese, portoghese, giapponese…). Spento, quella lingua si scrive a mano.'],
            'translate.cap_global'            => ['MHW_TRANSLATE_CAP_GLOBAL', 'Tetto per tutto il sito, caratteri al mese', 'cifra', 'Predefinito 1.900.000, sotto i 2 milioni del piano gratuito.'],
        ],
        // 8B: i link dei portali che la guida mostra a chi dà 5 stelle (Canali). Google cerca nome e città.
        'portali' => [
            'portali.google'  => ['MHW_PORTALE_GOOGLE', 'Google (cerca su Maps)', 'url', 'In fondo si aggiungono nome e città della struttura. Di serie https://www.google.com/maps/search/?api=1&query='],
            'portali.booking' => ['MHW_PORTALE_BOOKING', 'Booking.com', 'url', 'Di serie https://www.booking.com/'],
            'portali.airbnb'  => ['MHW_PORTALE_AIRBNB', 'Airbnb', 'url', 'Di serie https://www.airbnb.it/'],
            'portali.vrbo'    => ['MHW_PORTALE_VRBO', 'VRBO', 'url', 'Di serie https://www.vrbo.com/'],
            'portali.expedia' => ['MHW_PORTALE_EXPEDIA', 'Expedia', 'url', 'Di serie https://www.expedia.it/'],
        ],
        // 8I: i registri (log) sul server e la copia notturna su un bucket S3 a parte (solo scrittura).
        'registri' => [
            'registri.giorni'  => ['MHW_REGISTRI_GIORNI', 'Conserva i registri tecnici per … giorni', 'cifra', 'Di serie 90; da 30 a 180. I registri della posta di prova restano 7 giorni.'],
            'registri.bucket'  => ['MHW_REGISTRI_BUCKET', 'Bucket dei registri', 'text', 'Un bucket a parte, per esempio welcomemyhouse-registri, con Object Lock (vedi DA-CONFIGURARE). Vuoto = nessuna copia.'],
            'registri.regione' => ['MHW_REGISTRI_REGIONE', 'Regione', 'text', 'Di serie eu-south-1 (Milano).'],
            'registri.key'     => ['MHW_REGISTRI_KEY', 'Access key ID', 'text', 'Di un utente IAM a parte (welcomemyhouse-registri) con i SOLI permessi s3:PutObject e s3:PutObjectRetention su questo bucket. Non quelle delle foto.'],
            'registri.secret'  => ['MHW_REGISTRI_SECRET', 'Secret access key', 'secret', ''],
        ],
        // 8J: il titolare del trattamento (piè di pagina, documenti legali, email, dati strutturati) e le versioni dei documenti.
        'legale' => [
            'legal.company'         => ['MHW_COMPANY', 'Ragione sociale', 'text', 'Di serie City Up S.r.l. È il titolare del trattamento e il fornitore del servizio.'],
            'legal.company_legal'   => ['MHW_COMPANY_LEGAL', 'Riga completa per i documenti', 'text', 'Ragione sociale, P.IVA e C.F., REA: compare in Termini, Privacy e DPA.'],
            'legal.company_vat'     => ['MHW_COMPANY_VAT', 'Partita IVA', 'text', '11 cifre, senza IT.'],
            'legal.company_city'    => ['MHW_COMPANY_CITY', 'Sede legale', 'text', 'Per esempio Via Ariodante Fabretti 15, 06123 Perugia (PG).'],
            'legal.company_pec'     => ['MHW_COMPANY_PEC', 'PEC', 'email', ''],
            'legal.company_site'    => ['MHW_COMPANY_SITE', 'Sito della società', 'url', 'Di serie https://www.cityup.eu'],
            'legal.contact_phone'   => ['MHW_CONTACT_PHONE', 'Telefono', 'text', 'Come si legge, per esempio +39 392 006 1600.'],
            'legal.contact_whatsapp' => ['MHW_CONTACT_WHATSAPP', 'WhatsApp', 'text', 'Solo cifre, con il prefisso senza +, per esempio 393920061600.'],
            'legal.terms_version'   => ['MHW_TERMS_VERSION', 'Versione dei Termini (data da cui valgono)', 'data', 'Cambiala quando cambi il testo dei Termini in Testi e foto → Documenti legali. Una data futura (almeno 30 giorni) manda l\'avviso ai clienti; a quella data compare la finestra «Accetto».'],
            'legal.privacy_version' => ['MHW_PRIVACY_VERSION', 'Versione dell\'Informativa privacy', 'data', 'Come sopra, per l\'informativa.'],
            'legal.dpa_version'     => ['MHW_DPA_VERSION', 'Versione dell\'Accordo sul trattamento dei dati (DPA)', 'data', 'Come sopra, per il DPA (Allegato A dei Termini).'],
        ],
        // Invita un amico (Inviti): acceso da qui, senza toccare config.local.php a mano.
        'inviti' => [
            'inviti.attivi' => ['MHW_INVITI', 'Accendi «Invita un amico»', 'check', 'I clienti con un abbonamento attivo vedono il loro link e il codice, e possono mandare l\'invito per email. Prima di accenderlo con le chiavi live, fai una prova in modalità test di Stripe.'],
        ],
        // 9B · Giudizi con sconto: l'interruttore per i nuovi clienti, la percentuale e la sezione in home.
        'giudizi' => [
            'giudizi.sconto_attivo' => ['MHW_GIUDIZI_SCONTO', 'Sconto per i nuovi clienti che lasciano un giudizio', 'check', 'Acceso: la casella «Sì, voglio il 5% in più» prima del pagamento e «Partecipo» nel pannello. Spento: spariscono per chi non ha ancora aderito; chi ha già aderito conserva lo sconto promesso.'],
            'giudizi.percentuale'   => ['MHW_GIUDIZI_PERCENTO', 'Percentuale dello sconto', 'text', 'Da 1 a 20. Di serie 5. Vale per chi aderisce da adesso in poi.'],
            'giudizi.home'          => ['MHW_GIUDIZI_HOME', 'Sezione «Cosa dicono di noi» in home', 'check', 'La sezione compare solo se c\'è almeno un giudizio messo in home (Clienti → Giudizi).'],
        ],
    ];

    public const TITOLI = ['sito' => 'Sito', 'stripe' => 'Stripe', 'posta' => 'Posta in uscita', 'archivio' => 'Archivio di foto e PDF', 'traduzioni' => 'Traduzioni', 'portali' => 'Portali', 'registri' => 'Registri', 'legale' => 'Titolare e documenti legali', 'inviti' => 'Invita un amico', 'giudizi' => 'Giudizi con sconto'];

    public static function file(): string { return MHW_APP . '/config.local.php'; }

    /** Il contenuto attuale di config.local.php (vuoto se non c'è). */
    public static function locale(): array
    {
        $f = self::file();
        if (!is_file($f)) return [];
        $v = (static fn() => require $f)();
        return is_array($v) ? $v : [];
    }

    private static function prendi(array $a, string $percorso): mixed
    {
        foreach (explode('.', $percorso) as $k) {
            if (!is_array($a) || !array_key_exists($k, $a)) return null;
            $a = $a[$k];
        }
        return $a;
    }

    private static function metti(array &$a, string $percorso, mixed $v): void
    {
        $chiavi = explode('.', $percorso);
        $ultima = array_pop($chiavi);
        $p = &$a;
        foreach ($chiavi as $k) {
            if (!isset($p[$k]) || !is_array($p[$k])) $p[$k] = [];
            $p = &$p[$k];
        }
        $p[$ultima] = $v;
    }

    private static function togli(array &$a, string $percorso): void
    {
        $chiavi = explode('.', $percorso);
        $ultima = array_pop($chiavi);
        $p = &$a;
        foreach ($chiavi as $k) {
            if (!isset($p[$k]) || !is_array($p[$k])) return;
            $p = &$p[$k];
        }
        unset($p[$ultima]);
    }

    /** Il valore in uso adesso (variabili d'ambiente + config.local.php). */
    public static function valore(string $percorso): mixed
    {
        $parti = explode('.', $percorso);
        return self::prendi([$parti[0] => Config::get($parti[0])], $percorso);
    }

    /** Da dove viene il valore: file (config.local.php), env (variabile d'ambiente, bloccato qui) o predefinito. */
    public static function origine(string $percorso, array $locale): string
    {
        if (self::prendi($locale, $percorso) !== null) return 'file';
        $env = self::GRUPPI[self::gruppoDi($percorso)][$percorso][0];
        $v = getenv($env);
        return $v !== false && $v !== '' ? 'env' : 'predefinito';
    }

    public static function gruppoDi(string $percorso): string
    {
        foreach (self::GRUPPI as $g => $campi) if (isset($campi[$percorso])) return $g;
        throw new \InvalidArgumentException('Campo sconosciuto: ' . $percorso);
    }

    /** Un segreto, senza mostrarlo: «sk_live_ … 4f2a». */
    public static function mascherato(string $v): string
    {
        if ($v === '') return '';
        $prefisso = preg_match('/^(sk_live_|sk_test_|whsec_)/', $v, $m) ? $m[1] : '';
        return $prefisso . '… ' . (mb_strlen($v) > 10 ? mb_substr($v, -4) : '');
    }

    /** Stripe in modalità reale o di prova, dalla chiave. */
    public static function modoStripe(): string
    {
        $k = (string) (Config::get('stripe')['secret_key'] ?? '');
        return str_starts_with($k, 'sk_live_') ? 'live' : (str_starts_with($k, 'sk_test_') ? 'test' : '');
    }

    /**
     * Salva un gruppo. $in: i campi del modulo (nome = percorso con i punti
     * cambiati in due trattini bassi), più 'togli' per i segreti da cancellare.
     * @return array<string,string> errori per campo; vuoto = salvato
     */
    public static function salva(string $gruppo, array $in): array
    {
        [$nuovo, , $errori] = self::prepara($gruppo, $in);
        if ($errori) return $errori;
        self::scrivi($nuovo);
        return [];
    }

    /**
     * Prova la connessione con i dati scritti nel modulo, senza salvarli: un segreto lasciato vuoto
     * vale quello salvato. Valgono solo per questa richiesta.
     * @return array{0:bool,1:string,2:array<string,string>} [riuscita, messaggio, errori per campo]
     */
    public static function provaCon(string $gruppo, array $in, string $emailAdmin): array
    {
        [, $effettivi, $errori] = self::prepara($gruppo, $in);
        if ($errori) return [false, '', $errori];
        $prima = [];
        foreach ($effettivi as $percorso => $v) {
            $cima = explode('.', $percorso)[0];
            $prima[$cima] ??= Config::get($cima);
            $tutto = [$cima => Config::get($cima)];
            self::metti($tutto, $percorso, $v);
            Config::sovrascrivi($cima, $tutto[$cima]);
        }
        try { [$ok, $msg] = self::prova($gruppo, $emailAdmin); }
        finally { foreach ($prima as $cima => $v) Config::sovrascrivi($cima, $v); }
        return [$ok, $msg, []];
    }

    /** I valori di un gruppo dal modulo: [config.local.php nuovo, valori in uso per ogni campo, errori]. */
    private static function prepara(string $gruppo, array $in): array
    {
        if (!isset(self::GRUPPI[$gruppo])) return [[], [], ['_' => 'Gruppo sconosciuto.']];
        $locale = self::locale();
        $nuovo = $locale;
        $togli = (array) ($in['togli'] ?? []);
        $errori = [];
        $effettivi = [];
        foreach (self::GRUPPI[$gruppo] as $percorso => $c) {
            [$env, $etichetta, $tipo] = $c;
            $nome = self::nome($percorso);
            if (self::origine($percorso, $locale) === 'env') { $effettivi[$percorso] = self::valore($percorso); continue; }
            $grezzo = preg_replace('/[\x00-\x1F\x7F]/', '', trim((string) ($in[$nome] ?? ''))) ?? '';
            if ($percorso === 'pretty_urls') {
                // Spento = come prima: il sito decide da solo (null), non «mai».
                if (empty($in[$nome])) { self::togli($nuovo, $percorso); $effettivi[$percorso] = null; continue; }
                self::metti($nuovo, $percorso, true);
                $effettivi[$percorso] = true;
                continue;
            }
            if ($tipo === 'check') {
                $v = !empty($in[$nome]);
                self::metti($nuovo, $percorso, $v);
                $effettivi[$percorso] = $v;
                continue;
            }
            if ($tipo === 'secret') {
                if (!empty($togli[$nome])) { self::togli($nuovo, $percorso); $effettivi[$percorso] = ''; continue; }
                if ($grezzo === '') { $effettivi[$percorso] = (string) self::valore($percorso); continue; }   // vuoto = resta com'è
            }
            $errore = match (true) {
                $grezzo === '' => '',
                $percorso === 'stripe.secret_key' && !preg_match('/^sk_(live|test)_[A-Za-z0-9_]{10,}$/', $grezzo) => 'La chiave segreta comincia con sk_live_ (o sk_test_). Non usare la chiave pubblicabile pk_.',
                $percorso === 'stripe.webhook_secret' && !preg_match('/^whsec_[A-Za-z0-9_+\/=]{10,}$/', $grezzo) => 'Il segreto del webhook comincia con whsec_.',
                $tipo === 'choice' && !isset($c[4][$grezzo]) => 'Scegli una delle opzioni.',
                $tipo === 'number' && (!ctype_digit($grezzo) || (int) $grezzo < 1 || (int) $grezzo > 65535) => 'Una porta è un numero tra 1 e 65535.',
                $tipo === 'email' && !filter_var($grezzo, FILTER_VALIDATE_EMAIL) => 'Questo indirizzo email non sembra valido.',
                $tipo === 'url' && !preg_match('#^https://[^\s/]+#', $grezzo) => 'Scrivi l\'indirizzo completo, con https://.',
                $percorso === 'storage.s3.region' && !preg_match('/^[a-z]{2}(-[a-z]+)+-\d$/', $grezzo) => 'La regione si scrive come eu-south-1.',
                $percorso === 'storage.s3.bucket' && !preg_match('/^[a-z0-9][a-z0-9.\-]{1,61}[a-z0-9]$/', $grezzo) => 'Il nome del bucket ha solo lettere minuscole, numeri, punti e trattini.',
                $percorso === 'storage.s3.key' && !preg_match('/^[A-Z0-9]{16,128}$/', $grezzo) => 'L\'access key ID è fatto di lettere maiuscole e numeri, per esempio AKIA….',
                $percorso === 'mail.host' && !preg_match('/^[A-Za-z0-9.\-]+$/', $grezzo) => 'Scrivi solo il nome del server, senza http:// né porta.',
                $percorso === 'translate.region' && !preg_match('/^[a-z]{2}(-[a-z]+)+-\d$/', $grezzo) => 'La regione si scrive come eu-west-1.',
                $percorso === 'translate.key' && !preg_match('/^[A-Z0-9]{16,128}$/', $grezzo) => 'L\'access key ID è fatto di lettere maiuscole e numeri, per esempio AKIA….',
                $percorso === 'registri.giorni' && (!ctype_digit($grezzo) || (int) $grezzo < 30 || (int) $grezzo > 180) => 'Scrivi un numero di giorni tra 30 e 180.',
                $percorso === 'giudizi.percentuale' && (!ctype_digit($grezzo) || (int) $grezzo < 1 || (int) $grezzo > 20) => 'La percentuale va da 1 a 20.',
                $percorso === 'registri.regione' && !preg_match('/^[a-z]{2}(-[a-z]+)+-\d$/', $grezzo) => 'La regione si scrive come eu-south-1.',
                $percorso === 'registri.bucket' && !preg_match('/^[a-z0-9][a-z0-9.\-]{1,61}[a-z0-9]$/', $grezzo) => 'Il nome del bucket ha solo lettere minuscole, numeri, punti e trattini.',
                $percorso === 'registri.key' && !preg_match('/^[A-Z0-9]{16,128}$/', $grezzo) => 'L\'access key ID è fatto di lettere maiuscole e numeri, per esempio AKIA….',
                $percorso === 'legal.company_vat' && !preg_match('/^\d{11}$/', $grezzo) => 'La partita IVA italiana è fatta di 11 cifre (senza IT).',
                $percorso === 'legal.contact_whatsapp' && !preg_match('/^\d{8,15}$/', $grezzo) => 'Scrivi solo le cifre, con il prefisso del paese e senza +, per esempio 393920061600.',
                $tipo === 'cifra' && (!ctype_digit(str_replace('.', '', $grezzo)) || (int) str_replace('.', '', $grezzo) < 1 || (int) str_replace('.', '', $grezzo) > 100000000) => 'Scrivi un numero intero, per esempio 150000.',
                $tipo === 'decimale' && (!preg_match('/^\d{1,6}([.,]\d{1,6})?$/', $grezzo) || (float) str_replace(',', '.', $grezzo) <= 0) => 'Scrivi un numero, per esempio 0,86.',
                $tipo === 'data' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $grezzo) || !checkdate((int) substr($grezzo, 5, 2), (int) substr($grezzo, 8, 2), (int) substr($grezzo, 0, 4))) => 'Scrivi la data come 2027-10-31.',
                default => '',
            };
            if ($errore !== '') { $errori[$nome] = $errore; continue; }
            $v = match ($tipo) {
                'number' => (int) $grezzo,
                'cifra' => (int) str_replace('.', '', $grezzo),
                'decimale' => str_replace(',', '.', $grezzo),
                default => $percorso === 'base_url' ? rtrim($grezzo, '/') : $grezzo,
            };
            if ($grezzo === '') self::togli($nuovo, $percorso); else self::metti($nuovo, $percorso, $v);
            $effettivi[$percorso] = $grezzo === '' ? '' : $v;
        }
        // Le regole che tengono insieme i campi: niente SMTP senza server, niente S3 senza credenziali.
        if (!$errori && $gruppo === 'posta' && ($effettivi['mail.transport'] ?? '') === 'smtp') {
            foreach (['mail.host' => 'Serve il server SMTP.', 'mail.from' => 'Serve l\'indirizzo del mittente.'] as $p => $msg) {
                if ((string) ($effettivi[$p] ?? '') === '') $errori[self::nome($p)] = $msg;
            }
        }
        if (!$errori && $gruppo === 'archivio' && ($effettivi['storage.driver'] ?? '') === 's3') {
            foreach (['storage.s3.region' => 'Serve la regione.', 'storage.s3.bucket' => 'Serve il bucket.', 'storage.s3.key' => 'Serve l\'access key ID.', 'storage.s3.secret' => 'Serve la secret access key.'] as $p => $msg) {
                if ((string) ($effettivi[$p] ?? '') === '') $errori[self::nome($p)] = $msg;
            }
        }
        if (!$errori && $gruppo === 'traduzioni' && (($effettivi['translate.key'] ?? '') === '') !== (($effettivi['translate.secret'] ?? '') === '')) {
            $errori[self::nome(($effettivi['translate.key'] ?? '') === '' ? 'translate.key' : 'translate.secret')] = 'Servono tutte e due le chiavi (o nessuna, per usare quelle dell\'archivio S3).';
        }
        if (!$errori && $gruppo === 'registri' && ($effettivi['registri.bucket'] ?? '') !== '') {
            foreach (['registri.key' => 'Serve l\'access key ID.', 'registri.secret' => 'Serve la secret access key.'] as $p => $msg) {
                if ((string) ($effettivi[$p] ?? '') === '') $errori[self::nome($p)] = $msg;
            }
            if (!$errori && ($effettivi['registri.key'] ?? '') === (string) (Config::get('storage')['s3']['key'] ?? '')) {
                $errori[self::nome('registri.key')] = 'Usa chiavi a parte, che possono solo scrivere nel bucket dei registri: non quelle dell\'archivio delle foto.';
            }
        }
        if (!$errori && $gruppo === 'sito' && ($effettivi['pretty_urls'] ?? null) === true) {
            [$ok, $msg] = self::provaIndirizzi(rtrim((string) ($effettivi['base_url'] ?? ''), '/'));
            if (!$ok) $errori[self::nome('pretty_urls')] = $msg;
        }
        if (!$errori && $gruppo === 'stripe' && ($effettivi['stripe.secret_key'] ?? '') !== '' && ($effettivi['stripe.webhook_secret'] ?? '') === '') {
            $errori[self::nome('stripe.webhook_secret')] = 'Serve anche il segreto del webhook: senza, i pagamenti non attivano le guide.';
        }
        return [$nuovo, $effettivi, $errori];
    }

    /** Il nome del campo nel modulo: i punti diventano due trattini bassi. */
    public static function nome(string $percorso): string { return str_replace('.', '__', $percorso); }

    private static function scrivi(array $dati): void
    {
        $f = self::file();
        $dir = dirname($f);
        if (!is_writable($dir) && !(is_file($f) && is_writable($f))) {
            throw new \RuntimeException('Non si riesce a scrivere app/config.local.php: dai i permessi di scrittura alla cartella app/ (o al file), oppure crealo a mano dal modello config.local.esempio.php.');
        }
        $testo = "<?php\n// Impostazioni di questo server: segreti compresi. Escluso dal repository e dal pacchetto.\n"
               . "// Lo riscrive anche Amministrazione → Impostazioni: i commenti scritti a mano non si conservano\n"
               . "// (la versione precedente resta in config.local.bak.php).\n"
               . 'return ' . var_export($dati, true) . ";\n";
        if (is_file($f)) @copy($f, $dir . '/config.local.bak.php');
        $tmp = $dir . '/.config.local.' . bin2hex(random_bytes(6)) . '.php';
        if (@file_put_contents($tmp, $testo, LOCK_EX) === false) throw new \RuntimeException('Non si riesce a scrivere nella cartella app/: controlla i permessi.');
        @chmod($tmp, 0640);
        if (!@rename($tmp, $f)) {
            if (@file_put_contents($f, $testo, LOCK_EX) === false) { @unlink($tmp); throw new \RuntimeException('Non si riesce a sostituire app/config.local.php: controlla i permessi.'); }
            @unlink($tmp);
        }
        if (function_exists('opcache_invalidate')) @opcache_invalidate($f, true);
    }

    /** Dalla risposta del server di posta, cosa controllare: in parole semplici, con la risposta originale in fondo. */
    public static function consiglioPosta(string $errore): string
    {
        $e = mb_strtolower($errore);
        $consiglio = match (true) {
            $errore === '' => 'Controlla server, porta, cifratura, utente e password.',
            str_contains($e, 'manca la password') => 'Manca la password della casella: scrivila nel campo «Password della casella» e premi «Prova con questi dati».',
            str_contains($e, 'accesso rifiutato') || preg_match('/\b(535|534|530)\b/', $e) === 1
                => 'Il server ha rifiutato utente o password. Controlla, tra parentesi qui sotto, l\'utente usato: dev\'essere l\'indirizzo completo della casella (per esempio info@tuodominio.it). '
                 . 'La password è quella della casella, la stessa della webmail, non quella del pannello: riscrivila e premi «Prova con questi dati» (prova senza salvare), poi «Salva». '
                 . 'Se la webmail entra con la stessa password e qui no, chiedi al fornitore della posta se l\'invio SMTP è attivo per quella casella o se serve una «password per le app».',
            str_contains($e, 'connessione a') => 'Il sito non raggiunge il server di posta: controlla nome del server e porta (587 con STARTTLS, 465 con SSL). Se la porta è giusta, l\'hosting potrebbe bloccarla: prova l\'altra.',
            str_contains($e, 'starttls') || str_contains($e, 'ssl') || str_contains($e, 'crypto') => 'La connessione cifrata non è riuscita: prova SSL sulla porta 465 invece di STARTTLS sulla 587.',
            preg_match('/\b(550|551|553|554)\b/', $e) === 1 => 'Il server ha rifiutato il mittente o il destinatario: il mittente deve essere la stessa casella dell\'utente (o un suo alias).',
            preg_match('/\b(421|450|451|452)\b/', $e) === 1 => 'Il server è momentaneamente occupato o ha limitato gli invii: riprova tra qualche minuto.',
            default => 'Controlla server, porta, cifratura, utente e password.',
        };
        return $consiglio . ($errore !== '' ? ' Risposta del server: «' . mb_substr($errore, 0, 300) . '».' : '');
    }

    public const AVVISO_HTACCESS = 'Il server non legge .htaccess: caricalo nella cartella principale, è un file nascosto, e controlla che il piano di hosting lo permetta';

    /**
     * Gli indirizzi puliti funzionano? Il sito chiede a sé stesso $base/indirizzi-puliti (senza index.php):
     * risponde 200 solo se il server riscrive gli indirizzi. $base vuoto = l'indirizzo di adesso.
     * 7F: non più robots.txt, che ora può essere un file vero (Seo::scriviFile) e risponderebbe anche senza riscrittura.
     * @return array{0:bool,1:string}
     */
    public static function provaIndirizzi(string $base = ''): array
    {
        if ($base === '') $base = (string) preg_replace('#/index\.php$#', '', Support::baseUrl());
        $url = rtrim($base, '/') . '/indirizzi-puliti';
        // Il server di prova di PHP (php -S) serve una richiesta per volta: chiedere a sé stesso lo bloccherebbe.
        if (PHP_SAPI === 'cli-server' && parse_url($url, PHP_URL_HOST) . ':' . parse_url($url, PHP_URL_PORT) === preg_replace('/^\[?([^\]]*)\]?$/', '$1', (string) ($_SERVER['SERVER_NAME'] ?? '')) . ':' . ($_SERVER['SERVER_PORT'] ?? '')) {
            return [false, 'Con il server di prova di PHP la prova non si può fare: si fa sull\'hosting.'];
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 5, CURLOPT_FOLLOWLOCATION => false, CURLOPT_NOBODY => false]);
        $corpo = (string) curl_exec($ch);
        $codice = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errore = curl_error($ch);
        curl_close($ch);
        if ($codice === 200 && str_contains($corpo, Seo::SONDA)) return [true, 'Gli indirizzi puliti funzionano: ' . $url . ' risponde.'];
        return [false, self::AVVISO_HTACCESS . ' (' . $url . ' → ' . ($codice ?: 'nessuna risposta' . ($errore !== '' ? ': ' . $errore : '')) . ').'];
    }

    /**
     * Prova la connessione con la configurazione in uso.
     * @return array{0:bool,1:string} [riuscita, messaggio per l'amministratore]
     */
    public static function prova(string $gruppo, string $emailAdmin): array
    {
        try {
            switch ($gruppo) {
                case 'sito':
                    return self::provaIndirizzi(rtrim((string) Config::get('base_url'), '/'));
                case 'giudizi':
                    $inHome = Giudizi::disponibili() ? (int) Db::val('SELECT COUNT(*) FROM testimonials WHERE account_id IS NOT NULL AND home_visible = 1 AND withdrawn_at IS NULL', [], 0) : 0;
                    $aderiti = Giudizi::disponibili() ? (int) Db::val('SELECT COUNT(*) FROM accounts WHERE giudizio_adesione IS NOT NULL', [], 0) : 0;
                    return [true, (Giudizi::attivo() ? 'Sconto acceso: ' . Giudizi::percentuale() . '%.' : 'Sconto spento per i nuovi clienti.') . ' ' . $aderiti . ' clienti hanno aderito; '
                                  . $inHome . ' giudizi in home' . (Giudizi::homeAccesa() ? '.' : ' (sezione spenta).')];
                case 'stripe':
                    if (!Stripe::enabled()) return [false, 'Stripe non è ancora configurato: servono la chiave segreta e il segreto del webhook.'];
                    Stripe::call('GET', 'balance');
                    return [true, 'Stripe risponde: la chiave è valida (' . (self::modoStripe() === 'live' ? 'modalità reale' : 'modalità di prova') . '). Il segreto del webhook si verifica al primo pagamento: controlla in Stripe che gli eventi arrivino con esito 200.'];
                case 'posta':
                    $t = (string) Config::get('mail')['transport'];
                    $ok = Mailer::manda($emailAdmin, 'Prova della posta', 'La posta funziona',
                        ['Se leggi questa email, la posta in uscita funziona.', 'Inviata da Amministrazione → Impostazioni il ' . gmdate('d/m/Y H:i') . ' (UTC).']);
                    if (!$ok) return [false, 'L\'email di prova non è partita. ' . self::consiglioPosta(Mailer::ultimoErrore())];
                    return [true, $t === 'log' ? 'La posta è in modalità prova: l\'email è finita in storage/logs/mail-' . gmdate('Y-m-d') . '.log, non nella casella.' : 'Email di prova spedita a ' . $emailAdmin . ': controlla la casella (anche lo spam).'];
                case 'archivio':
                    $cfg = Config::get('storage');
                    if (($cfg['driver'] ?? '') !== 's3') return [true, 'Foto e PDF si salvano sul disco del server: non c\'è niente da collegare.'];
                    $s3 = new S3Storage($cfg['s3']);
                    $chiave = '_prova/' . bin2hex(random_bytes(8)) . '.txt';
                    try {
                        $s3->put($chiave, 'prova', 'text/plain');
                        $letto = $s3->get($chiave);
                        $s3->delete($chiave);
                    } catch (\Throwable $e) {   // la risposta esatta di S3, e cosa fare (ai clienti resta «non disponibile al momento»)
                        Log::exception($e, 'impostazioni: prova archivio');
                        $dett = S3Storage::$ultimoErrore ?: $e->getMessage();
                        $cosa = S3Storage::consiglio($dett);
                        return [false, 'Il bucket non risponde: «' . $dett . '».' . ($cosa !== '' ? ' ' . $cosa : '')];
                    }
                    return $letto === 'prova' ? [true, 'Il bucket risponde: scrittura, lettura e cancellazione riuscite.'] : [false, 'Il bucket ha accettato il file ma ne ha restituito un altro contenuto.'];
                case 'registri':   // 8I: un file di prova nel bucket; e la cancellazione deve essere rifiutata
                    if (!Registri::s3Configurato()) return [true, 'Nessun bucket per la copia dei registri: restano solo sul server (' . Registri::config()['giorni'] . ' giorni).'];
                    $c = Registri::config();
                    $s3 = new S3Storage(['bucket' => $c['bucket'], 'region' => $c['regione'], 'key' => $c['key'], 'secret' => $c['secret']]);
                    $chiave = 'tecnici/_prova-' . gmdate('Ymd-His') . '.txt';
                    try { $s3->putRegistro($chiave, 'Prova da Amministrazione → Impostazioni → Registri.'); }
                    catch (\Throwable $e) {
                        $dett = S3Storage::$ultimoErrore ?: $e->getMessage();
                        return [false, 'Il bucket dei registri non accetta il file: «' . $dett . '».' . (($cosa = S3Storage::consiglio($dett)) !== '' ? ' ' . $cosa : '')];
                    }
                    try { $s3->delete($chiave); $cancella = true; } catch (\Throwable) { $cancella = false; }
                    return $cancella
                        ? [false, 'Il file di prova si è caricato, ma le chiavi possono anche CANCELLARE: togli all\'utente IAM ogni permesso tranne s3:PutObject e s3:PutObjectRetention.']
                        : [true, 'Il bucket accetta i registri e rifiuta la cancellazione, come deve. Il file di prova (' . $chiave . ') lo toglie il ciclo di vita del bucket.'];
                case 'inviti':
                    if (!Inviti::disponibili()) return [false, 'Spento: i clienti non vedono «Invita un amico». Spunta la casella e salva.'];
                    if (!Stripe::enabled()) return [false, 'Acceso, ma Stripe non è configurato: gli sconti degli inviti passano da Stripe.'];
                    $n = 0;   // 7D: ogni abbonamento attivo, anche quelli attivati dallo staff (lo sconto si applica a mano)
                    foreach (Db::all("SELECT DISTINCT account_id FROM subscriptions WHERE status IN ('active','trialing')") as $x) {
                        if (Inviti::puoInvitare(['id' => (int) $x['account_id']])) $n++;
                    }
                    return [true, 'Acceso: ' . $n . ($n === 1 ? ' cliente può' : ' clienti possono') . ' invitare. Chi non ha ancora un abbonamento attivo vede la pagina e il motivo. '
                                . 'Per gli abbonamenti attivati dallo staff lo sconto si applica a mano: lo trovi in Inviti → «Da applicare a mano al rinnovo».'];
                case 'legale':   // 8J: come si presenta il titolare, e le versioni in vigore
                    $l = Config::get('legal');
                    $versioni = Legale::versioni();
                    $stato = [];
                    foreach (Legale::DOCUMENTI as $d => [$nome]) $stato[] = $nome . ' ' . Support::date($versioni[$d]) . (Legale::futura($versioni[$d]) ? ' (dal ' . Support::date($versioni[$d]) . ': avviso in corso)' : '');
                    return [true, 'Piè di pagina: «' . Legale::rigaTitolare() . '». Versioni: ' . implode(' · ', $stato) . '. Da accettare di nuovo: ' . Legale::daRiaccettare() . ' clienti.'];
                case 'portali':   // 8B: i link che la guida prepara, con una struttura d'esempio
                    $p = ['name' => 'Casa Esempio', 'city' => 'Assisi'];
                    $righe = [];
                    foreach (Canali::NOTI as $k => $nome) {
                        $u = Canali::link($k, $p);
                        if (!preg_match('#^https://#', $u)) return [false, $nome . ': l\'indirizzo deve cominciare con https:// («' . $u . '»).'];
                        $righe[] = $nome . ' → ' . $u;
                    }
                    return [true, 'I link dei portali, per una struttura «Casa Esempio» ad Assisi: ' . implode(' · ', $righe)];
                case 'traduzioni':
                    if (!Traduttore::configurato()) return [false, 'Mancano le chiavi: scrivile qui, oppure configura l\'archivio S3 (si usano le sue). Finché mancano, nel pannello i clienti leggono «Le traduzioni non sono disponibili in questo momento».'];
                    try { $t = Traduttore::prova(); }
                    catch (\Throwable $e) {   // 7C: il messaggio esatto di AWS, e cosa fare
                        Log::exception($e, 'impostazioni: prova traduzioni');
                        $cosa = Traduttore::consiglio($e->getMessage());
                        return [false, 'La prova non è riuscita. Amazon risponde: «' . $e->getMessage() . '».' . ($cosa !== '' ? ' ' . $cosa : '')];
                    }
                    return [true, 'Amazon Translate risponde: «Buongiorno» → «' . $t . '» (10 caratteri, nel registro).' . (!empty(Traduttore::config()['chiavi_s3']) ? ' Sta usando le chiavi dell\'archivio S3.' : '')];
            }
        } catch (\Throwable $e) {
            Log::exception($e, 'impostazioni: prova ' . $gruppo);
            return [false, 'La prova non è riuscita: ' . $e->getMessage()];
        }
        return [false, 'Gruppo sconosciuto.'];
    }
}
