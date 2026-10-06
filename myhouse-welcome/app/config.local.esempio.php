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

    // Invita un amico: acceso solo dopo le prove in modalità test di Stripe.
    // 'inviti' => ['attivi' => true],

    // Dati aziendali del piè di pagina e dei documenti legali (un valore vuoto nasconde la riga).
    // 'legal' => [
    //     'company'          => 'Blackout Agency',
    //     'company_vat'      => '02945910541',
    //     'company_city'     => 'Via Ariodante Fabretti 17, Perugia',
    //     'contact_email'    => 'info@myhousewelcome.it',
    //     'contact_phone'    => '+39 392 006 1600',
    //     'contact_whatsapp' => '393920061600',
    // ],
];
