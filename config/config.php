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
        'debug'   => Env::bool('APP_DEBUG', true),
        'url'     => rtrim((string) Env::get('APP_URL', ''), '/'),
        'name'    => 'Arco del Vento',
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
        'min_nights'  => (int) Env::get('BOOKING_MIN_NIGHTS', 2),
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
