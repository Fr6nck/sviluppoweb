<?php
/**
 * 003 — Il listino dell'MVP e le sezioni strutturate.
 *
 * Tocca DATI, per questo è PHP. Vale sia per un database nuovo sia per uno
 * che ha già clienti: nel secondo caso non toglie niente a nessuno.
 *
 *  1. Feature: le nuove, e quelle future già dichiarate ma spente.
 *  2. Versioni già vendute: ricevono le feature nuove con valori che non le
 *     peggiorano mai. Una versione venduta non perde capacità.
 *  3. Listino nuovo: Essential 87, Plus 117, Portfolio 2 177, Portfolio 3 237
 *     (centesimi, IVA esclusa). Nuove versioni, le vecchie restano a chi le ha.
 *     Pro esce dal listino pubblico; chi l'ha comprato lo tiene.
 *  4. Sezioni: ogni struttura ha il suo Check-in & Check-out come nucleo; i
 *     tipi vecchi diventano tipi del catalogo, i testi diventano campi.
 *  5. Codice cassetta: svuotato, e tolto anche dalle istantanee già pubblicate.
 *  6. Demo: segnata come tale, e senza statistiche finte.
 */

use MHW\{Db, Support};

return function (\PDO $pdo): void {
    $ora = Support::now();

    // ------------------------------------------------------------ 1. feature
    $feature = [
        // codice,          etichetta,                                  tipo,   predefinito
        ['sections',        'Sezioni aggiuntive attive',                'int',  '4'],
        ['locales',         'Lingue pubblicabili',                      'int',  '2'],
        ['photos',          'Immagini nelle sezioni',                   'bool', '0'],
        ['pdf',             'PDF nelle sezioni',                        'bool', '0'],
        ['logo',            'Logo della struttura',                     'bool', '1'],
        ['cover',           'Foto di copertina',                        'bool', '1'],
        ['profile_image',   'Immagine profilo',                         'bool', '0'],
        ['palette',         'Personalizzazione colori',                 'bool', '1'],
        ['places',          'Consigli sul posto',                       'bool', '1'],
        ['properties',      'Strutture sullo stesso account',           'int',  '1'],
        ['analytics',       'Statistiche di lettura',                   'bool', '0'],
        ['branding',        'Marchio personalizzato (non più in vendita)', 'bool', '0'],
        // Dichiarate adesso, spente per tutti: il giorno che servono c'è già il posto.
        ['custom_sections',    'Sezioni personalizzate',                'bool', '0'],
        ['auto_translation',   'Traduzione automatica',                 'bool', '0'],
        ['protected_content',  'Contenuti protetti',                    'bool', '0'],
        ['advanced_analytics', 'Statistiche avanzate',                  'bool', '0'],
        ['video',              'Video',                                 'bool', '0'],
        ['upselling',          'Vendita di servizi',                    'bool', '0'],
        ['custom_domain',      'Dominio personalizzato',                'bool', '0'],
        ['ai_concierge',       'Concierge con intelligenza artificiale','bool', '0'],
    ];
    foreach ($feature as [$code, $label, $kind, $def]) {
        $f = Db::one('SELECT id FROM features WHERE code = ?', [$code]);
        if ($f) Db::update('features', ['label' => $label, 'kind' => $kind, 'default_value' => $def], 'id = :fid', ['fid' => $f['id']]);
        else Db::insert('features', ['code' => $code, 'label' => $label, 'kind' => $kind, 'default_value' => $def]);
    }
    $fid = fn(string $c) => (int) Db::val('SELECT id FROM features WHERE code = ?', [$c]);
    $valore = fn(int $pv, string $c) => Db::val(
        'SELECT pf.value FROM package_features pf JOIN features f ON f.id = pf.feature_id
         WHERE pf.package_version_id = ? AND f.code = ?', [$pv, $c]);

    // ------------------------------------ 2. le versioni già vendute non peggiorano
    foreach (Db::all('SELECT id FROM package_versions') as $v) {
        $pv = (int) $v['id'];
        $foto = (string) ($valore($pv, 'photos') ?? '0');
        $riempi = [
            // Il check-in ora non conta fra le sezioni: lo stesso numero vale di più.
            'sections' => (string) ($valore($pv, 'sections') ?? '8'),
            'locales' => (string) ($valore($pv, 'locales') ?? '1'),
            'photos' => $foto,
            'pdf' => '0',
            // Logo, copertina e colori ora sono per tutti: a chi li aveva già non cambia
            // nulla, a chi non li aveva si aggiungono. Nessuno perde.
            'logo' => '1', 'cover' => '1', 'palette' => '1',
            'profile_image' => $foto,
            'places' => (string) ($valore($pv, 'places') ?? '0'),
            'properties' => (string) ($valore($pv, 'properties') ?? '1'),
            'analytics' => (string) ($valore($pv, 'analytics') ?? '0'),
            'branding' => (string) ($valore($pv, 'branding') ?? '0'),
            'custom_sections' => '0', 'auto_translation' => '0', 'protected_content' => '0',
            'advanced_analytics' => '0', 'video' => '0', 'upselling' => '0',
            'custom_domain' => '0', 'ai_concierge' => '0',
        ];
        foreach ($riempi as $code => $val) {
            // Solo quello che manca: una riga già presente è il contratto venduto.
            if ($valore($pv, $code) === null) {
                Db::insert('package_features', ['package_version_id' => $pv, 'feature_id' => $fid($code), 'value' => $val]);
            }
        }
    }

    // ------------------------------------------------------ 3. il listino nuovo
    $base = ['photos' => '0', 'pdf' => '0', 'logo' => '1', 'cover' => '1', 'profile_image' => '0',
             'palette' => '1', 'places' => '1', 'analytics' => '0', 'branding' => '0',
             'custom_sections' => '0', 'auto_translation' => '0', 'protected_content' => '0',
             'advanced_analytics' => '0', 'video' => '0', 'upselling' => '0',
             'custom_domain' => '0', 'ai_concierge' => '0'];
    $plus = ['sections' => 'unlimited', 'locales' => '5', 'photos' => '1', 'pdf' => '1',
             'profile_image' => '1', 'analytics' => '1'] + $base;

    $listino = [
        'essential' => [
            'name' => 'Essential', 'sort' => 0, 'family' => '', 'price' => 8700,
            'tagline' => 'Meno domande ripetitive, più tempo per accogliere.',
            'headline' => 'Tutto ciò che serve per il soggiorno.',
            'description' => 'Una guida semplice e professionale con le informazioni più importanti della tua struttura.',
            'badge' => '', 'cta_label' => 'Crea gratis',
            'bullets' => "1 struttura\nCheck-in & Check-out incluso\n4 sezioni a scelta\nItaliano + Inglese\nLogo\nFoto copertina\nPersonalizzazione colori\nQR permanente\nLink personale\nCMS",
            'features' => ['sections' => '4', 'locales' => '2', 'properties' => '1'] + $base,
        ],
        'plus' => [
            'name' => 'Plus', 'sort' => 1, 'family' => '', 'price' => 11700,
            'tagline' => 'Trasforma la guida in un vero concierge digitale.',
            'headline' => 'Dalla struttura al territorio.',
            'description' => "Una guida completa e multilingua per accompagnare l'ospite durante tutto il soggiorno.",
            'badge' => 'Più scelto', 'cta_label' => 'Crea gratis con Plus',
            'bullets' => "1 struttura\nSezioni predefinite illimitate\n5 lingue pubblicabili\nLogo\nFoto copertina\nImmagine profilo\nImmagini nelle sezioni\nPDF\nStatistiche di lettura\nPersonalizzazione colori\nQR permanente\nCMS",
            'features' => ['properties' => '1'] + $plus,
        ],
        'portfolio2' => [
            'name' => 'Portfolio 2', 'sort' => 2, 'family' => 'portfolio', 'price' => 17700,
            'tagline' => '2 strutture',
            'headline' => 'Più strutture. Un solo account.',
            'description' => 'Per proprietari e gestori che vogliono amministrare più Welcome Guide dallo stesso pannello.',
            'badge' => '', 'cta_label' => 'Scegli Portfolio',
            'bullets' => "Funzionalità Plus per ogni struttura\nUna guida e un QR per ogni struttura\nStatistiche distinte\nTutto dallo stesso account",
            'features' => ['properties' => '2'] + $plus,
        ],
        'portfolio3' => [
            'name' => 'Portfolio 3', 'sort' => 3, 'family' => 'portfolio', 'price' => 23700,
            'tagline' => '3 strutture',
            'headline' => 'Più strutture. Un solo account.',
            'description' => 'Per proprietari e gestori che vogliono amministrare più Welcome Guide dallo stesso pannello.',
            'badge' => '', 'cta_label' => 'Scegli Portfolio',
            'bullets' => "Funzionalità Plus per ogni struttura\nUna guida e un QR per ogni struttura\nStatistiche distinte\nTutto dallo stesso account",
            'features' => ['properties' => '3'] + $plus,
        ],
    ];

    foreach ($listino as $code => $p) {
        $copy = ['name' => $p['name'], 'sort' => $p['sort'], 'family' => $p['family'],
                 'tagline' => $p['tagline'], 'headline' => $p['headline'], 'description' => $p['description'],
                 'badge' => $p['badge'], 'cta_label' => $p['cta_label'], 'bullets' => $p['bullets'],
                 'active' => 1, 'public' => 1];
        $pk = Db::one('SELECT * FROM packages WHERE code = ?', [$code]);
        $pid = $pk ? (int) $pk['id'] : Db::insert('packages', ['code' => $code] + $copy);
        if ($pk) Db::update('packages', $copy, 'id = :pid', ['pid' => $pid]);

        // Una versione nuova, sempre: quelle già vendute restano come sono.
        $prossima = (int) Db::val('SELECT COALESCE(MAX(version),0)+1 FROM package_versions WHERE package_id = ?', [$pid], 1);
        Db::run('UPDATE package_versions SET is_current = 0 WHERE package_id = ?', [$pid]);
        $vid = Db::insert('package_versions', [
            'package_id' => $pid, 'version' => $prossima, 'price_cents' => $p['price'], 'currency' => 'EUR',
            'interval_unit' => 'year', 'is_current' => 1, 'sold_count' => 0, 'created_at' => $ora,
        ]);
        foreach ($p['features'] as $fc => $val) {
            Db::insert('package_features', ['package_version_id' => $vid, 'feature_id' => $fid($fc), 'value' => $val]);
        }
    }
    // Pro non esiste più come offerta pubblica. Chi l'ha comprato lo tiene: la
    // versione e l'abbonamento non si toccano.
    Db::run("UPDATE packages SET active = 0, public = 0 WHERE code = 'pro'");

    // Chi aveva scelto un piano senza pagarlo passa alla versione corrente dello stesso.
    // (Nessuno ancora: la colonna nasce adesso. Il passaggio resta per chiarezza.)

    // --------------------------------------------------------- 4. le sezioni
    $mappa = ['places' => 'eat', 'text' => 'info'];
    $paragrafi = fn(string $t) => array_values(array_filter(array_map('trim', preg_split('/\R{2,}/', $t) ?: [])));

    foreach (Db::all('SELECT * FROM properties') as $prop) {
        $pid = (int) $prop['id'];
        $sezioni = Db::all('SELECT * FROM sections WHERE property_id = ? ORDER BY position, id', [$pid]);

        $nucleo = null;
        foreach ($sezioni as $s) if ($s['kind'] === 'checkin' && !$nucleo) $nucleo = $s;

        foreach ($sezioni as $s) {
            $kind = $s['kind'];
            $dati = [];
            if ($kind === 'wifi') $dati = ['network' => $s['wifi_ssid'], 'password' => $s['wifi_pass']];
            $nuovo = $mappa[$kind] ?? $kind;
            // Una seconda sezione "arrivo" oltre al nucleo diventa informazioni utili.
            if ($kind === 'checkin' && $nucleo && (int) $nucleo['id'] !== (int) $s['id']) $nuovo = 'info';

            Db::update('sections', [
                'kind' => $nuovo, 'icon' => $nuovo,
                'is_core' => ($nucleo && (int) $nucleo['id'] === (int) $s['id']) ? 1 : 0,
                'is_active' => 1,
                'data' => json_encode($dati, JSON_UNESCAPED_UNICODE),
                'door_code' => '',
            ], 'id = :sid', ['sid' => $s['id']]);

            foreach (Db::all('SELECT * FROM section_translations WHERE section_id = ?', [$s['id']]) as $t) {
                $p = $paragrafi((string) $t['body']);
                $tdati = match ($nuovo) {
                    'checkin' => ['checkin_steps' => $p],
                    'wifi'    => ['instructions' => implode("\n\n", $p)],
                    'eat'     => ['intro' => $p[0] ?? '', 'host_note' => implode("\n\n", array_slice($p, 1))],
                    default   => ['items' => $p],
                };
                Db::update('section_translations', ['data' => json_encode($tdati, JSON_UNESCAPED_UNICODE)],
                           'id = :tid', ['tid' => $t['id']]);
            }
        }

        if (!$nucleo) {
            // Il nucleo non c'era: lo si crea vuoto, in testa. Non conta nel limite.
            Db::run('UPDATE sections SET position = position + 1 WHERE property_id = ?', [$pid]);
            $sid = Db::insert('sections', [
                'property_id' => $pid, 'kind' => 'checkin', 'icon' => 'checkin', 'color' => 'terracotta',
                'position' => 0, 'is_core' => 1, 'is_active' => 1, 'data' => '{}', 'created_at' => $ora,
            ]);
            Db::insert('section_translations', [
                'section_id' => $sid, 'locale' => $prop['default_locale'], 'title' => 'Check-in & Check-out',
                'body' => '', 'data' => '{}', 'state' => 'reviewed', 'updated_at' => $ora,
            ]);
        } else {
            Db::run("UPDATE section_translations SET title = 'Check-in & Check-out'
                     WHERE section_id = ? AND title IN ('Entrare in casa', 'Arrivo e chiavi', 'Getting in', 'Arrival and keys', 'Ins Haus kommen')",
                    [$nucleo['id']]);
        }

        // I luoghi: quello che si traduce va nella lingua della struttura.
        foreach (Db::all('SELECT pl.* FROM places pl JOIN sections s ON s.id = pl.section_id WHERE s.property_id = ?', [$pid]) as $pl) {
            if (Db::one('SELECT id FROM place_translations WHERE place_id = ? AND locale = ?', [$pl['id'], $prop['default_locale']])) continue;
            Db::insert('place_translations', [
                'place_id' => $pl['id'], 'locale' => $prop['default_locale'],
                'category' => $pl['category'], 'description' => '', 'note' => $pl['note'], 'badge' => $pl['badge'],
            ]);
        }

        // Una guida già pubblicata prende i campi nuovi anche nel formato: la copertina
        // prima era riservata a Plus, ora è di tutti. I media restano dove sono.
        if ($prop['cover_media_id']) {
            Db::run('UPDATE media SET property_id = ? WHERE id = ? AND property_id IS NULL', [$pid, $prop['cover_media_id']]);
        }
        Db::update('properties', ['published_at' => $prop['status'] === 'published' ? $ora : ''], 'id = :pid', ['pid' => $pid]);
    }

    // ------------------------------------------- 5. niente codici, da nessuna parte
    Db::run("UPDATE sections SET door_code = ''");
    foreach (Db::all('SELECT id, snapshot FROM guide_versions') as $gv) {
        $snap = json_decode((string) $gv['snapshot'], true);
        if (!is_array($snap)) continue;
        $toccata = false;
        foreach (($snap['sections'] ?? []) as $i => $s) {
            if (array_key_exists('door_code', $s)) { unset($snap['sections'][$i]['door_code']); $toccata = true; }
        }
        if ($toccata) Db::update('guide_versions', ['snapshot' => json_encode($snap, JSON_UNESCAPED_UNICODE)], 'id = :gid', ['gid' => $gv['id']]);
    }

    // ------------------------------------------- 6. la demo è una demo, e basta
    Db::run("UPDATE properties SET is_demo = 1 WHERE account_id IN (
               SELECT a.id FROM accounts a JOIN users u ON u.id = a.user_id WHERE u.email LIKE '%@esempio.it')");
    Db::run('DELETE FROM analytics_events WHERE property_id IN (SELECT id FROM properties WHERE is_demo = 1)');
    Db::run('UPDATE qr_tokens SET scans = 0 WHERE property_id IN (SELECT id FROM properties WHERE is_demo = 1)');

    // Gli utenti che esistevano prima avevano già un account funzionante: la loro
    // email si considera verificata, per non bloccarli fuori da quello che hanno.
    Db::run('UPDATE users SET email_verified_at = ? WHERE email_verified_at IS NULL', [$ora]);
};
