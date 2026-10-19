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

    // Il titolare e i dati aziendali del piè di pagina e dei documenti legali (un valore vuoto nasconde la riga).
    // Si cambiano anche da Amministrazione → Impostazioni → Titolare e documenti legali.
    // 'legal' => [
    //     'company'          => 'City Up S.r.l.',
    //     'company_legal'    => 'City Up S.r.l. — P.IVA e C.F. 03793880547 — REA PG-352001',
    //     'company_vat'      => '03793880547',
    //     'company_city'     => 'Via Ariodante Fabretti 15, 06123 Perugia (PG)',
    //     'company_rea'      => 'PG-352001',
    //     'company_capitale' => '€ 10.000 i.v.',   // esempio: vuoto non compare
    //     'company_site'     => 'https://www.cityup.eu',
    //     'contact_email'    => 'assistenza@welcomemyhouse.it',
    //     'contact_phone'    => '+39 392 006 1600',
    //     'contact_whatsapp' => '393920061600',
    //     'terms_version'    => '2026-10-15',
    //     'privacy_version'  => '2026-10-15',
    //     'dpa_version'      => '2026-10-15',
    // ],
    // Traduzioni suggerite (Amazon Translate): utente IAM con il solo translate:TranslateText.
    // Si impostano anche da Amministrazione → Impostazioni → Traduzioni.
    // 'translate' => [
    //     'region' => 'eu-west-1',
    //     'key'    => 'AKIA…',
    //     'secret' => '…',
    //     'cap_account' => 150000,
    //     'cap_global'  => 1900000,
    // ],
];
