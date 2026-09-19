<?php
// MyHouse Welcome — configurazione.
// Copiate questo file, non serve altro: di default usa SQLite, che funziona
// su qualunque hosting senza creare database.
return [
    'app_name'  => 'MyHouse Welcome',
    'base_url'  => getenv('MHW_BASE_URL') ?: '',      // vuoto = rilevato da solo
    'db' => [
        'driver' => getenv('MHW_DB_DRIVER') ?: 'sqlite',
        'sqlite_path' => __DIR__ . '/storage/myhouse.sqlite',
        'mysql' => [
            'host' => getenv('MHW_DB_HOST') ?: 'localhost',
            'name' => getenv('MHW_DB_NAME') ?: 'myhouse',
            'user' => getenv('MHW_DB_USER') ?: 'root',
            'pass' => getenv('MHW_DB_PASS') ?: '',
        ],
    ],
    'uploads_dir' => __DIR__ . '/storage/uploads',
    'uploads_url' => '/media',
    'stripe' => [
        'secret_key'      => getenv('STRIPE_SECRET_KEY') ?: '',
        'publishable_key' => getenv('STRIPE_PUBLISHABLE_KEY') ?: '',
        'webhook_secret'  => getenv('STRIPE_WEBHOOK_SECRET') ?: '',
    ],
    'translator' => [
        'provider' => getenv('MHW_TRANSLATE_PROVIDER') ?: '',   // '' = solo manuale
        'api_key'  => getenv('MHW_TRANSLATE_KEY') ?: '',
    ],
    'locales' => ['it' => 'Italiano', 'en' => 'English', 'de' => 'Deutsch', 'fr' => 'Français'],
];
