<?php
// Modello per gli hosting che non permettono di impostare variabili d'ambiente.
// Copialo in config.local.php (stessa cartella), riempi SOLO le voci che servono
// e cancella le altre. config.local.php è escluso dal repository: i segreti
// restano sul server e non finiscono mai nel codice.
return [
    'base_url' => 'https://tuodominio.it/welcomebook',

    'stripe' => [
        'secret_key'     => 'sk_live_…',
        'webhook_secret' => 'whsec_…',
        // 'automatic_tax' => true,     // solo se Stripe Tax è attivo
    ],

    'mail' => [
        'transport'  => 'smtp',
        'host'       => 'smtp.tuodominio.it',
        'port'       => 587,
        'user'       => 'noreply@tuodominio.it',
        'pass'       => '…',
        'encryption' => 'tls',
        'from'       => 'noreply@tuodominio.it',
        'from_name'  => 'MyHouse Welcome',
    ],

    'storage' => [
        'driver' => 's3',
        's3' => [
            'region' => 'eu-south-1',
            'bucket' => 'nome-bucket',
            'key'    => 'AKIA…',
            'secret' => '…',
            // 'public_base_url' => 'https://cdn.tuodominio.it',
        ],
    ],
];
