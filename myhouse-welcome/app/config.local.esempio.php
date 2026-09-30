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

    // Dati aziendali del piè di pagina e dei documenti legali (un valore vuoto nasconde la riga).
    // 'legal' => [
    //     'company'          => 'Blackout',
    //     'company_vat'      => '02945910541',
    //     'company_city'     => '',
    //     'contact_email'    => 'info@tuodominio.it',
    //     'contact_phone'    => '+39 392 006 1600',
    //     'contact_whatsapp' => '393920061600',
    // ],
];
