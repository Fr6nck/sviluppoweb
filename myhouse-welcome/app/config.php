<?php
// MyHouse Welcome — configurazione.
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
    // I dettagli degli errori si vedono solo con debug acceso. Sempre nei log.
    'debug' => $bool('MHW_DEBUG'),

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

    // Lingue della guida, nell'ordine del listino: Essential ne pubblica due
    // (italiano e inglese), gli altri piani cinque.
    'locales' => ['it' => 'Italiano', 'en' => 'English', 'fr' => 'Français', 'de' => 'Deutsch', 'es' => 'Español'],

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
        'from'      => $env('MAIL_FROM', 'noreply@myhouse.it'),
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
        'max_image_bytes' => (int) $env('MHW_MAX_IMAGE_BYTES', (string) (8 * 1024 * 1024)),
        'max_pdf_bytes'   => (int) $env('MHW_MAX_PDF_BYTES', (string) (10 * 1024 * 1024)),
        'max_image_edge'  => 1800,
    ],

    // Le versioni dei documenti legali: cambiatele quando cambia il testo, e ogni
    // accettazione resta legata alla versione che la persona ha letto.
    'legal' => [
        'terms_version'   => $env('MHW_TERMS_VERSION', '2026-09'),
        'privacy_version' => $env('MHW_PRIVACY_VERSION', '2026-09'),
        'company'         => $env('MHW_COMPANY', 'Blackout'),
        'contact_email'   => $env('MHW_CONTACT_EMAIL', 'info@myhouse.it'),
    ],

    'uploads_dir' => __DIR__ . '/storage/uploads',
    'uploads_url' => '/media',
];

// Le sovrascritture locali, se ci sono (escluse dal repository).
if (is_file(__DIR__ . '/config.local.php')) {
    $locale = require __DIR__ . '/config.local.php';
    if (is_array($locale)) $config = array_replace_recursive($config, $locale);
}
return $config;
