<?php
/**
 * Configurazione dell'applicazione.
 *
 * Legge le variabili da .env (se c'è) e dall'ambiente del server, che su
 * Hostinger è il modo consigliato di tenere le credenziali fuori dai file.
 * Nessun valore segreto è scritto qui dentro.
 */

declare(strict_types=1);

use ArcoDelVento\Support\Env;

$root = dirname(__DIR__);

Env::load($root . '/.env');

return [
    'root'    => $root,
    'storage' => $root . '/storage',
    'views'   => $root . '/views',
    'content' => $root . '/content',

    'app' => [
        'env'     => Env::get('APP_ENV', 'development'),
        // Senza .env il debug è SPENTO: un sito caricato senza il suo .env non
        // deve mostrare agli ospiti gli errori di PHP con i percorsi del server.
        // In sviluppo lo accende il .env, con APP_DEBUG=true.
        'debug'   => Env::bool('APP_DEBUG', false),
        'url'     => rtrim((string) Env::get('APP_URL', ''), '/'),
        'name'    => 'Arco del Vento',

        // La cartella in cui vive il sito, ricavata da APP_URL: «» sul dominio
        // vero, «/assisiapartment» su https://blackout.in/assisiapartment.
        // È la sola cosa che cambia fra la prova e la pubblicazione, e sta in
        // un posto solo: ogni link, ogni reindirizzamento e ogni file statico
        // la prende da qui.
        'base'    => rtrim((string) (parse_url((string) Env::get('APP_URL', ''), PHP_URL_PATH) ?? ''), '/'),

        // «https://blackout.in»: davanti ai percorsi, che la cartella la
        // contengono già, per gli indirizzi assoluti — canonical, hreflang,
        // sitemap, Open Graph.
        'origin'  => (static function (): string {
            $parti = parse_url((string) Env::get('APP_URL', ''));
            if (!is_array($parti) || empty($parti['host'])) {
                return '';
            }

            return ($parti['scheme'] ?? 'https') . '://' . $parti['host']
                . (isset($parti['port']) ? ':' . $parti['port'] : '');
        })(),

        // In prova il sito non va nei motori di ricerca: finirebbe indicizzato
        // su un dominio che non è il suo, e il giorno della pubblicazione
        // Google avrebbe due copie dello stesso sito.
        'noindex' => Env::bool('APP_NOINDEX', false),
    ],

    'i18n' => [
        'default'   => Env::get('APP_DEFAULT_LOCALE', 'it'),
        // «es» è già previsto dall'architettura: si attiva aggiungendolo qui
        // e completando content/lang/es.php.
        'available' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) Env::get('APP_LOCALES', 'it,en'))
        ))),
    ],

    'database' => [
        'dsn'      => (string) Env::get('DB_DSN', ''),
        'user'     => (string) Env::get('DB_USER', ''),
        'password' => (string) Env::get('DB_PASSWORD', ''),
    ],

    'booking' => [
        'provider'    => Env::get('BOOKING_PROVIDER', 'demo'),
        'api_base'    => (string) Env::get('BOOKING_API_BASE_URL', ''),
        'api_key'     => (string) Env::get('BOOKING_API_KEY', ''),
        'property_id' => (string) Env::get('BOOKING_PROPERTY_ID', ''),
        // Il minimo è un dato della casa e sta in content/settings.php;
        // questa variabile serve solo a un gestionale che ne imponga un
        // altro. Lasciata vuota, comanda il file dei contenuti — mai un
        // numero scritto qui, che sarebbe una regola inventata.
        'min_nights'  => Env::get('BOOKING_MIN_NIGHTS', '') === ''
            ? null
            : (int) Env::get('BOOKING_MIN_NIGHTS'),
        'currency'    => Env::get('BOOKING_CURRENCY', 'EUR'),
    ],

    'mail' => [
        'transport' => Env::get('MAIL_TRANSPORT', 'log'),
        'from'      => [
            'address' => (string) Env::get('MAIL_FROM_ADDRESS', 'no-reply@example.test'),
            'name'    => (string) Env::get('MAIL_FROM_NAME', 'Arco del Vento'),
        ],
        'to'   => (string) Env::get('MAIL_TO_ADDRESS', 'no-reply@example.test'),
        'smtp' => [
            'host'       => (string) Env::get('MAIL_SMTP_HOST', ''),
            'port'       => (int) Env::get('MAIL_SMTP_PORT', 587),
            'user'       => (string) Env::get('MAIL_SMTP_USER', ''),
            'password'   => (string) Env::get('MAIL_SMTP_PASSWORD', ''),
            'encryption' => (string) Env::get('MAIL_SMTP_ENCRYPTION', 'tls'),
        ],
    ],
];
