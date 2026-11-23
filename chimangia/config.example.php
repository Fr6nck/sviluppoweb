<?php
/**
 * Modello di configurazione.
 *
 * La procedura guidata (install/) crea config.php partendo da questo file.
 * Se preferisci configurare a mano: copia questo file in config.php e
 * sostituisci i valori tra doppi trattini bassi.
 * config.php non va mai incluso nello zip né condiviso.
 */
return [
    'db' => [
        'host'   => '__DB_HOST__',
        'port'   => '__DB_PORT__',
        'name'   => '__DB_NAME__',
        'user'   => '__DB_USER__',
        'pass'   => '__DB_PASS__',
        'prefix' => '__DB_PREFIX__',
    ],

    // Indirizzo completo del sito, senza barra finale (es. https://www.chimangia.it).
    'site_url' => '__SITE_URL__',

    // Chiave segreta casuale: firma i token e cifra la password SMTP. Non cambiarla dopo l'installazione.
    'app_secret' => '__APP_SECRET__',

    // Cartella dati privata (cache HTML, log, file di blocco). Meglio fuori dalla web root.
    'storage_path' => '__STORAGE_PATH__',

    'timezone' => 'Europe/Rome',

    // true solo in sviluppo: mostra gli errori a video.
    'debug' => false,
];
