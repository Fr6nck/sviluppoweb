<?php
// MyHouse Welcome — configurazione.

// Aperto direttamente dal web (non da index.php): non risponde niente.
if (PHP_SAPI !== 'cli' && isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    http_response_code(404);
    exit;
}
//
// Nessun segreto va scritto qui dentro: chiavi Stripe, credenziali AWS e
// password di posta si leggono dalle variabili d'ambiente. Su un hosting che
// non permette di impostarle, create accanto a questo file un config.local.php
// (è escluso dal repository) che restituisce solo le voci da sovrascrivere.

$env = fn(string $k, string $d = '') => (($v = getenv($k)) !== false && $v !== '') ? $v : $d;
$bool = fn(string $k, bool $d = false) => in_array(strtolower($env($k, $d ? '1' : '0')), ['1', 'true', 'yes', 'on'], true);

$config = [
    'app_name' => 'MyHouse Welcome',
    // L'indirizzo pubblico, senza barra finale: https://welcome.myhouse.it oppure
    // https://blackout.in/welcomebook. Vuoto = si ricava dalla richiesta.
    // È quello che finisce nei QR e nelle email: conviene impostarlo.
    'base_url'  => $env('MHW_BASE_URL'),
    // Sottocartella (es. 'welcomebook'). Vuoto = si ricava da sola.
    'base_path' => $env('MHW_BASE_PATH'),
    // Indirizzi puliti: null = si decide da solo; true = sempre; false = mai.
    'pretty_urls' => null,
    // GEO · dove si mandano gli avvisi IndexNow (vuoto = api.indexnow.org; le prove lo puntano a un servizio finto).
    'indexnow_endpoint' => $env('MHW_INDEXNOW_ENDPOINT'),
    // I dettagli degli errori si vedono solo con debug acceso. Sempre nei log.
    'debug' => $bool('MHW_DEBUG'),
    // Il token dell'indirizzo /cron/{token}, da collegare a un cron di cPanel
    // (facoltativo: senza, le email di richiamo partono lo stesso, controllando
    // al massimo ogni 15 minuti mentre qualcuno usa il sito). Vuoto = /cron spento.
    'cron_token' => $env('MHW_CRON_TOKEN'),
    // L'account in cui la guida vetrina «Casa Checco» si crea da sola, una volta, al primo
    // aggiornamento (Demo::vetrinaAutomatica). Vuoto = mai: la si crea dall'amministrazione.
    'vetrina_email' => $env('MHW_VETRINA_EMAIL', 'vetrina@welcomemyhouse.it'),   // 9H: non riceve email (esclusa dai Richiami)

    'db' => [
        'driver' => $env('MHW_DB_DRIVER', 'sqlite'),
        'sqlite_path' => __DIR__ . '/storage/myhouse.sqlite',
        'mysql' => [
            'host' => $env('MHW_DB_HOST', 'localhost'),
            'name' => $env('MHW_DB_NAME', 'myhouse'),
            'user' => $env('MHW_DB_USER', 'root'),
            'pass' => $env('MHW_DB_PASS'),
        ],
    ],

    // Lingue della guida, col nome nativo: prima le cinque di base (I18n::BASE), poi le aggiuntive
    // (8C). Le regole per piano sono in Entitlements::regolaLingue: Essential la principale e una a
    // scelta, Plus e Portfolio la principale, le altre di base e una aggiuntiva.
    'locales' => ['it' => 'Italiano', 'en' => 'English', 'fr' => 'Français', 'de' => 'Deutsch', 'es' => 'Español',
                  'nl' => 'Nederlands', 'pt' => 'Português (Portugal)', 'pt-BR' => 'Português (Brasil)', 'pl' => 'Polski', 'ru' => 'Русский', 'zh' => '中文',
                  'ja' => '日本語', 'sv' => 'Svenska', 'da' => 'Dansk', 'cs' => 'Čeština'],

    'stripe' => [
        'secret_key'      => $env('STRIPE_SECRET_KEY'),
        'publishable_key' => $env('STRIPE_PUBLISHABLE_KEY'),
        'webhook_secret'  => $env('STRIPE_WEBHOOK_SECRET'),
        // Stripe Tax: accendetelo solo dopo averlo configurato nel pannello Stripe.
        'automatic_tax'   => $bool('STRIPE_AUTOMATIC_TAX'),
        // Il portale clienti di Stripe (cambio carta, fatture, disdetta).
        'customer_portal' => $bool('STRIPE_CUSTOMER_PORTAL', true),
        // Solo per le prove automatiche: un finto Stripe in locale.
        'api_base'        => $env('STRIPE_API_BASE', 'https://api.stripe.com'),
    ],

    'billing' => [
        // Giorni in cui una guida resta online dopo un rinnovo non riuscito,
        // mentre Stripe ritenta l'addebito. Zero = offline alla scadenza.
        'grace_days' => (int) $env('MHW_GRACE_DAYS', '0'),
    ],

    // Invita un amico: spento finché non lo accendi (MHW_INVITI=1), quando il
    // regolamento è pronto e hai fatto un giro in modalità test di Stripe.
    'inviti' => [
        'attivi' => $bool('MHW_INVITI'),
    ],
    // 9B · «Ti piace MyHouse Welcome?»: lo sconto per chi lascia un giudizio (di serie acceso, 5%) e la sezione in home.
    'giudizi' => [
        'sconto_attivo' => $bool('MHW_GIUDIZI_SCONTO', true),
        'percentuale'   => (int) $env('MHW_GIUDIZI_PERCENTO', '5'),
        'home'          => $bool('MHW_GIUDIZI_HOME', true),
        // Quando si applica: «adesione» (al primo addebito se ha spuntato la casella, anche se il giudizio arriva
        // dopo) oppure «giudizio» (solo dopo il giudizio inviato: al primo addebito se arriva prima, altrimenti al rinnovo).
        'sconto_quando' => $env('MHW_GIUDIZI_QUANDO', 'adesione') === 'giudizio' ? 'giudizio' : 'adesione',
        // 9L · Le parole che fanno finire un giudizio tra i «Segnalati» (con le 1–2 stelle), separate da virgole.
        'parole'        => $env('MHW_GIUDIZI_PAROLE', 'truffa, truffatori, vergogna, schifo, pessimo, denuncia, avvocato, rimborso'),
        // 9L (revisione) · I posti in home: schede di «Le guide dei nostri host» (le altre in «Tutte le guide») e giudizi di «Cosa dicono di noi».
        'posti_schede'  => (int) $env('MHW_VETRINA_POSTI', '4'),
        'posti_giudizi' => (int) $env('MHW_GIUDIZI_POSTI', '6'),
    ],

    // 8I: i registri (log). Quanti giorni restano sul server i registri tecnici (30–180, di serie 90; la posta
    // di prova 7) e il bucket S3 a parte dove ogni notte va la copia di quelli del giorno prima, con chiavi
    // che possono solo scrivere. Si cambia da Amministrazione → Impostazioni → Registri.
    'registri' => [
        'giorni'  => (int) $env('MHW_REGISTRI_GIORNI', '90'),
        'bucket'  => $env('MHW_REGISTRI_BUCKET', ''),
        'regione' => $env('MHW_REGISTRI_REGIONE', 'eu-south-1'),
        'key'     => $env('MHW_REGISTRI_KEY', ''),
        'secret'  => $env('MHW_REGISTRI_SECRET', ''),
    ],

    'mail' => [
        // smtp | mail | log. "log" scrive le email in storage/logs/mail.log:
        // per provare senza spedire niente.
        'transport' => $env('MAIL_TRANSPORT', 'log'),
        'host'      => $env('MAIL_HOST'),
        'port'      => (int) $env('MAIL_PORT', '587'),
        'user'      => $env('MAIL_USER'),
        'pass'      => $env('MAIL_PASS'),
        // tls = STARTTLS sulla 587; ssl = connessione cifrata sulla 465; none = in chiaro (solo prove)
        'encryption'=> $env('MAIL_ENCRYPTION', 'tls'),
        // 9H · Per ora un solo indirizzo per tutto (decisione del titolare): assistenza@welcomemyhouse.it, sul dominio del sito.
        'from'      => $env('MAIL_FROM', 'assistenza@welcomemyhouse.it'),
        'from_name' => $env('MAIL_FROM_NAME', 'MyHouse Welcome'),
    ],

    'storage' => [
        // local | s3. In produzione s3; local resta per lo sviluppo.
        'driver' => $env('MHW_STORAGE', 'local'),
        'local_dir' => __DIR__ . '/storage/uploads',
        's3' => [
            'region'   => $env('AWS_REGION', 'eu-south-1'),
            'bucket'   => $env('AWS_S3_BUCKET'),
            'key'      => $env('AWS_ACCESS_KEY_ID'),
            'secret'   => $env('AWS_SECRET_ACCESS_KEY'),
            'token'    => $env('AWS_SESSION_TOKEN'),
            // Se c'è un CDN (CloudFront) o il bucket è pubblico in lettura:
            // gli URL si costruiscono da qui. Vuoto = URL firmati a tempo.
            'public_base_url' => $env('AWS_S3_PUBLIC_URL'),
            // Solo per le prove automatiche o per servizi compatibili S3.
            'endpoint' => $env('AWS_S3_ENDPOINT'),
            'url_ttl'  => (int) $env('AWS_S3_URL_TTL', '3600'),
        ],
        // Limiti di caricamento, in byte.
        'max_image_bytes' => (int) $env('MHW_MAX_IMAGE_BYTES', (string) (20 * 1024 * 1024)),   // 9K: le foto arrivano già compresse dal browser; 20 MB serve senza JavaScript
        'max_pdf_bytes'   => (int) $env('MHW_MAX_PDF_BYTES', (string) (10 * 1024 * 1024)),
        'max_image_edge'  => 1800,
    ],

    // Traduzioni suggerite (Amazon Translate, TranslateText). Chiavi di un utente IAM con
    // translate:TranslateText; vuote = si usano quelle dell'archivio S3. Tetti in caratteri al mese.
    'translate' => [
        'region'   => $env('MHW_TRANSLATE_REGION', 'eu-west-1'),
        'key'      => $env('MHW_TRANSLATE_KEY'),
        'secret'   => $env('MHW_TRANSLATE_SECRET'),
        // Solo per le prove automatiche (il servizio finto).
        'endpoint' => $env('MHW_TRANSLATE_ENDPOINT'),
        'price_usd_per_million' => $env('MHW_TRANSLATE_PRICE', '15'),
        'usd_eur'        => $env('MHW_TRANSLATE_USD_EUR', '0.86'),
        // Fine del piano gratuito AWS (2 milioni di caratteri al mese per 12 mesi dalla prima chiamata).
        'free_tier_until' => $env('MHW_TRANSLATE_FREE_UNTIL'),
        'cap_account'    => (int) $env('MHW_TRANSLATE_CAP_ACCOUNT', '150000'),
        'cap_global'     => (int) $env('MHW_TRANSLATE_CAP_GLOBAL', '1900000'),
        // 8C: suggerire anche la lingua aggiuntiva (nl, pt, ja…). Spento, quella lingua si scrive a mano.
        'extra'          => $bool('MHW_TRANSLATE_EXTRA', true),
    ],

    // Le versioni dei documenti legali: cambiatele quando cambia il testo, e ogni
    // accettazione resta legata alla versione che la persona ha letto (8J: anche il DPA).
    // La versione è una data (AAAA-MM-GG): quella da cui il testo vale. Se è nel futuro, i clienti
    // ricevono l'avviso (email una volta sola e riga nel pannello) e la finestra «Accetto» arriva a quella data.
    // Si cambiano da Amministrazione → Impostazioni → Titolare e documenti legali.
    'legal' => [
        'terms_version'   => $env('MHW_TERMS_VERSION', '2026-10-20'),   // 9L (revisione): «Giudizio con sconto», due pubblicazioni decise da noi
        'privacy_version' => $env('MHW_PRIVACY_VERSION', '2026-10-20'),   // 9L (revisione): scheda e giudizio pubblicati a nostra scelta; 9L: «Giudizio con sconto e vetrina»; 9K: partner
        'dpa_version'     => $env('MHW_DPA_VERSION', '2026-10-15'),
        // 8J: il titolare del trattamento e fornitore del servizio.
        'company'         => $env('MHW_COMPANY', 'City Up S.r.l.'),
        'company_legal'   => $env('MHW_COMPANY_LEGAL', 'City Up S.r.l. — P.IVA e C.F. 03793880547 — REA PG-352001'),
        // Di serie è l'indirizzo unico dell'assistenza (contatti.email, qui sotto): vedi in fondo al file.
        // È anche l'indirizzo per i diritti privacy.
        'contact_email'   => '',
        // Dati aziendali mostrati nel piè di pagina e nei documenti legali.
        // Un valore vuoto nasconde la sua riga.
        'company_vat'      => $env('MHW_COMPANY_VAT', '03793880547'),
        'contact_phone'    => $env('MHW_CONTACT_PHONE', '+39 392 006 1600'),
        'contact_whatsapp' => $env('MHW_CONTACT_WHATSAPP', '393920061600'),
        'company_city'     => $env('MHW_COMPANY_CITY', 'Via Ariodante Fabretti 15, 06123 Perugia (PG)'),
        // 9K · La PEC resta come promemoria interno: vuota di serie e mai mostrata sul sito né nelle email.
        'company_pec'      => $env('MHW_COMPANY_PEC', ''),
        // 9K · I dati obbligatori delle S.r.l. (art. 2250 c.c.) nel piè di pagina e nel blocco del titolare.
        // Il REA si mostra sempre; capitale sociale e «società unipersonale» solo se compilati.
        'company_rea'          => $env('MHW_COMPANY_REA', 'PG-352001'),
        'company_capitale'     => $env('MHW_COMPANY_CAPITALE', ''),
        'company_unipersonale' => $bool('MHW_COMPANY_UNIPERSONALE'),
        'company_site'     => $env('MHW_COMPANY_SITE', 'https://www.cityup.eu'),
    ],

    // 9K · Il programma Partner (/property-manager): le percentuali, se decise. Vuote, la pagina parla di
    // «una commissione» e «uno sconto» senza numeri. Si cambiano da Amministrazione → Impostazioni → Sito.
    'partner' => [
        'commissione' => $env('MHW_PARTNER_COMMISSIONE', ''),
        'sconto'      => $env('MHW_PARTNER_SCONTO', ''),
    ],

    // Gli indirizzi email del sito (7A, 9H). Si cambiano da Amministrazione → Impostazioni → Indirizzi email.
    //  - email: l'assistenza (clienti, privacy, avvisi del sito, piede delle email, Reply-To di serie). Unica casella vera.
    //  - commerciale: MyHouse siti («Anche il tuo sito»), preventivi, convenzioni e codici omaggio.
    //  - newsletter: mittente di newsletter e campagne (Brevo). Il sito non manda email da qui.
    //  - amministrazione: fatture, ricevute, pagamenti, rinnovi e rimborsi (Reply-To delle email di pagamento).
    //  Gli ultimi tre, vuoti, valgono l'email assistenza (Support::commerciale(), newsletter(), amministrazione()).
    'contatti' => [
        'email'           => $env('MHW_CONTACT_EMAIL', 'assistenza@welcomemyhouse.it'),
        'commerciale'     => $env('MHW_COMMERCIAL_EMAIL', 'commerciale@welcomemyhouse.it'),
        'newsletter'      => $env('MHW_NEWSLETTER_EMAIL', 'news@welcomemyhouse.it'),
        'amministrazione' => $env('MHW_BILLING_EMAIL', 'amministrazione@welcomemyhouse.it'),
    ],

    // 8B: gli indirizzi di partenza dei portali per le recensioni. Google cerca nome e città su Maps;
    // gli altri aprono il sito (lì la recensione si lascia dall'app o dall'email del portale).
    // Si cambiano da Amministrazione → Impostazioni → Portali.
    'portali' => [
        'google'  => $env('MHW_PORTALE_GOOGLE', 'https://www.google.com/maps/search/?api=1&query='),
        'booking' => $env('MHW_PORTALE_BOOKING', 'https://www.booking.com/'),
        'airbnb'  => $env('MHW_PORTALE_AIRBNB', 'https://www.airbnb.it/'),
        'vrbo'    => $env('MHW_PORTALE_VRBO', 'https://www.vrbo.com/'),
        'expedia' => $env('MHW_PORTALE_EXPEDIA', 'https://www.expedia.it/'),
    ],

    'uploads_dir' => __DIR__ . '/storage/uploads',
    'uploads_url' => '/media',
];

// Le sovrascritture locali, se ci sono (escluse dal repository).
if (is_file(__DIR__ . '/config.local.php')) {
    $locale = require __DIR__ . '/config.local.php';
    if (is_array($locale)) $config = array_replace_recursive($config, $locale);
}
// Un indirizzo solo: il contatto dei documenti legali è quello dell'assistenza, se non è scritto a parte.
if (($config['legal']['contact_email'] ?? '') === '') $config['legal']['contact_email'] = $config['contatti']['email'];
return $config;
