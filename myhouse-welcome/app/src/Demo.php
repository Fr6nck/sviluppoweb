<?php
namespace MHW;

/**
 * La demo: tre host di esempio, per vedere il prodotto abitato invece che vuoto.
 *
 * Sono account veri con una password nota: si tolgono prima di aprire al
 * pubblico. Le strutture sono segnate come demo e la guida lo dice agli
 * ospiti. Nessun numero inventato: niente statistiche finte, niente scansioni
 * finte, niente codici di cassette. I badge dei luoghi sono consigli
 * dell'host, non orari che nessuno controlla.
 */
final class Demo
{
    public const PASSWORD = 'dimostrazione1';
    public const DOMINIO = 'esempio.it';

    public static function presente(): bool
    {
        return (bool) Db::val('SELECT COUNT(*) FROM users WHERE email LIKE ?', ['%@' . self::DOMINIO], 0);
    }

    public static function rimuovi(): int
    {
        $utenti = Db::all('SELECT u.id, a.id AS account_id FROM users u JOIN accounts a ON a.user_id = u.id WHERE u.email LIKE ?', ['%@' . self::DOMINIO]);
        foreach ($utenti as $u) {
            foreach (Db::all('SELECT id FROM media WHERE account_id = ?', [$u['account_id']]) as $m) Media::delete((int) $m['id'], (int) $u['account_id']);
            Db::run('DELETE FROM users WHERE id = ?', [$u['id']]);
        }
        return count($utenti);
    }

    private static function foto(string $nome, int $accountId, int $propertyId, string $alt): ?int
    {
        foreach ([defined('MHW_PUBLIC') ? MHW_PUBLIC : null, dirname(__DIR__) . '/public'] as $dove) {
            if ($dove && is_file($dove . '/assets/foto/' . $nome)) return Media::importImage($dove . '/assets/foto/' . $nome, $accountId, $propertyId, $alt);
        }
        return null;
    }

    /** @param bool $pagato false = piano scelto ma non ancora pagato (Marco: Portfolio prima del pagamento) */
    private static function host(string $nome, string $email, string $pacchetto, bool $pagato = true, int $quantita = 1): array
    {
        $u = Auth::register($email, self::PASSWORD, $nome);
        Db::update('users', ['email_verified_at' => Support::now()], 'id = :uid', ['uid' => $u['user_id']]);
        Auth::recordConsent((int) $u['user_id']);
        $pv = (int) Db::val('SELECT pv.id FROM package_versions pv JOIN packages p ON p.id = pv.package_id
                             WHERE p.code = ? AND pv.is_current = 1', [$pacchetto]);
        // Un abbonamento dimostrativo: nessun pagamento, e l'incasso del quadro non lo conta.
        if ($pagato) Db::insert('subscriptions', [
            'account_id' => $u['account_id'], 'package_version_id' => $pv, 'status' => 'active',
            'provider' => 'dimostrazione', 'payment_status' => 'dimostrazione',
            'current_period_start' => Support::now(), 'current_period_end' => gmdate('Y-m-d\TH:i:s\Z', strtotime('+1 year')),
            'created_at' => Support::now(), 'updated_at' => Support::now(),
        ]);
        Db::update('accounts', ['intended_package_version_id' => $pv, 'intended_quantity' => $quantita], 'id = :aid', ['aid' => $u['account_id']]);
        Entitlements::forget((int) $u['account_id']);
        return $u;
    }

    private static function struttura(int $acc, array $d): int
    {
        $pid = Properties::create($acc, $d['nome'], $d['citta'], $d['host']);
        Db::update('properties', [
            'region' => $d['regione'], 'checkin_from' => $d['arrivo'], 'checkout_by' => $d['partenza'],
            'host_phone' => $d['telefono'], 'host_whatsapp' => $d['telefono'], 'palette' => $d['palette'],
            'is_demo' => 1, 'wizard_step' => 'fatto',
        ], 'id = :pid', ['pid' => $pid]);
        Properties::setLocales($acc, $pid, $d['lingue']);
        Properties::saveContacts($pid, Conversione::contatti($d['host'], $d['telefono'], $d['telefono']));
        return $pid;
    }

    private static function nucleo(int $pid): int
    {
        return (int) Db::val('SELECT id FROM sections WHERE property_id = ? AND is_core = 1', [$pid]);
    }

    /** @param array<string,array> $testi lingua => campi */
    private static function scrivi(int $pid, int $sid, array $testi, string $titoloIt = ''): void
    {
        // Gli esempi sono scritti come li scriverebbe un host; qui si portano al
        // formato a blocchi, con le stesse regole delle migrazioni.
        $kind = (string) Db::val('SELECT kind FROM sections WHERE id = ?', [$sid]);
        if ($kind === 'checkin') foreach ($testi as $loc => $c) $testi[$loc] = Conversione::partenza($c, (string) $loc);
        if ($kind === 'wifi' && isset($testi['it']['network'])) {
            $testi['it']['networks'] = [['ssid' => $testi['it']['network'], 'password' => $testi['it']['password'] ?? '', 'zone' => '']];
        }
        foreach ($testi as $loc => $campi) {
            // Le righe delle altre lingue si agganciano per posizione a quelle appena salvate in italiano.
            if ($loc !== 'it') {
                $salvati = json_decode((string) Db::val('SELECT data FROM sections WHERE id = ?', [$sid], ''), true) ?: [];
                foreach ($campi as $campo => $righe) {
                    if (!is_array($righe) || !is_array($salvati[$campo] ?? null) || !is_array($righe[0] ?? null)) continue;
                    foreach ($righe as $i => $r) $campi[$campo][$i]['id'] = $salvati[$campo][$i]['id'] ?? '';
                }
            }
            Properties::saveSection($pid, $sid, $loc, $campi + ['title' => $loc === 'it' ? $titoloIt : ''], $loc === 'it');
        }
    }

    public static function popola(): array
    {
        $creati = [];

        // -------------------------------------------- Casa Lucia, Spello (Umbria) — Plus
        // La casa, i locali e i numeri sono di fantasia: la demo lo dichiara.
        $lucia = self::host('Lucia Ferrante', 'lucia@' . self::DOMINIO, 'plus');
        $acc = (int) $lucia['account_id'];
        $pid = self::struttura($acc, [
            'nome' => 'Casa Lucia', 'citta' => 'Spello', 'regione' => 'Umbria', 'host' => 'Lucia',
            'arrivo' => '15:00', 'partenza' => '10:30', 'telefono' => '+39 0742 000000',
            'lingue' => ['it', 'en', 'de'], 'palette' => 'terracotta',
        ]);
        Db::update('properties', [
            'cover_media_id' => self::foto('casa.jpg', $acc, $pid, 'La facciata in pietra con la scalinata e i gerani'),
        ], 'id = :pid', ['pid' => $pid]);

        $core = self::nucleo($pid);
        Db::update('sections', ['media_id' => self::foto('portone.jpg', $acc, $pid, 'Il portone con le rose accanto')], 'id = :sid', ['sid' => $core]);
        self::scrivi($pid, $core, [
            'it' => ['checkin_steps' => ["Il portone d'ingresso è quello rosso, a destra della fontana.",
                                        'Lucia ti aspetta in casa per consegnarti le chiavi e mostrarti dove si trova tutto.'],
                     'checkin_note' => "Se arrivi dopo le 21, scrivici su WhatsApp: ci organizziamo.",
                     'checkout_keys' => 'Lascia le chiavi sul tavolo della cucina e accosta il portone.',
                     'checkout_waste' => "L'umido va nel bidone marrone in cortile.",
                     'checkout_notes' => 'Grazie di essere stati qui. Buon viaggio!'],
            'en' => ['checkin_steps' => ['The front door is the red one, to the right of the fountain.',
                                        'Lucia will be waiting inside to hand you the keys and show you around.'],
                     'checkin_note' => 'If you arrive after 9pm, message us on WhatsApp and we will sort it out.',
                     'checkout_keys' => 'Leave the keys on the kitchen table and pull the front door closed.',
                     'checkout_waste' => 'Food waste goes in the brown bin in the courtyard.',
                     'checkout_notes' => 'Thank you for staying with us. Safe travels!'],
            'de' => ['checkin_steps' => ['Die Haustür ist die rote rechts neben dem Brunnen.',
                                        'Lucia erwartet Sie im Haus, übergibt die Schlüssel und zeigt Ihnen alles.'],
                     'checkout_keys' => 'Lassen Sie die Schlüssel auf dem Küchentisch und ziehen Sie die Haustür zu.'],
        ], 'Check-in & Check-out');

        $wifi = Properties::addSection($acc, $pid, 'wifi');
        self::scrivi($pid, $wifi, [
            'it' => ['network' => 'CasaLucia_5G', 'password' => 'glicine2024',
                     'instructions' => 'Se la rete sparisce, stacca la spina del router e riattaccala dopo un minuto.',
                     'router_location' => "Nell'ingresso, sopra la mensola."],
            'en' => ['instructions' => 'If the network drops, unplug the router and plug it back in after a minute.',
                     'router_location' => 'In the hallway, above the shelf.'],
            'de' => ['instructions' => 'Wenn das Netz ausfällt, ziehen Sie den Stecker des Routers und stecken ihn nach einer Minute wieder ein.',
                     'router_location' => 'Im Flur, über dem Regal.'],
        ], 'Wi-Fi e servizi');

        $regole = Properties::addSection($acc, $pid, 'rules');
        self::scrivi($pid, $regole, [
            'it' => ['flags' => ['smoking' => 'no', 'pets' => 'si', 'parties' => 'no'], 'quiet_from' => '22:00', 'quiet_to' => '08:00',
                     'items' => ['Il borgo dorme presto: dopo le 22 abbassa la voce anche in cortile.', 'Per gli animali, avvisaci prima di arrivare.']],
            'en' => ['items' => ['The village goes to bed early: after 10pm keep your voice down in the courtyard too.', 'If you bring a pet, let us know before you arrive.']],
        ]);

        // Rifiuti ed emergenze: i giorni e i numeri sono di esempio, come la casa.
        $rifiuti = Properties::addSection($acc, $pid, 'waste');
        self::scrivi($pid, $rifiuti, [
            'it' => ['bins' => [
                        ['type' => 'umido', 'days' => [2, 5], 'color' => 'marrone', 'label' => '', 'where' => 'In cortile, a sinistra del cancello'],
                        ['type' => 'plastica', 'days' => [3], 'color' => 'giallo', 'label' => 'Lattine insieme alla plastica', 'where' => 'In cortile'],
                        ['type' => 'carta', 'days' => [4], 'color' => 'blu', 'label' => '', 'where' => 'In cortile'],
                        ['type' => 'vetro', 'days' => [6], 'color' => 'verde', 'label' => '', 'where' => 'Campana in piazza'],
                        ['type' => 'indifferenziato', 'days' => [1], 'color' => 'grigio', 'label' => '', 'where' => 'In cortile']],
                     'note' => 'I bidoni si portano in strada la sera prima.'],
            'en' => ['bins' => [['where' => 'In the courtyard, left of the gate'], ['label' => 'Cans go with the plastic', 'where' => 'In the courtyard'],
                                ['where' => 'In the courtyard'], ['where' => 'Bottle bank in the square'], ['where' => 'In the courtyard']],
                     'note' => 'Put the bins out on the street the evening before.'],
        ]);
        $emergenze = Properties::addSection($acc, $pid, 'emergency');
        self::scrivi($pid, $emergenze, [
            'it' => ['emergency_number' => '112', 'contacts' => [
                        ['name' => 'Lucia, per i problemi in casa', 'phone' => '+39 0742 000000', 'note' => 'Dalle 8 alle 22'],
                        ['name' => 'Farmacia di turno', 'phone' => '', 'note' => 'Il turno è affisso sulla porta di ogni farmacia.']]],
            'en' => ['contacts' => [['name' => 'Lucia, for anything in the house', 'note' => '8am to 10pm'],
                                    ['name' => 'Duty pharmacy', 'note' => 'The rota is posted on the door of every pharmacy.']]],
        ]);

        // Servizi extra: l'ospite li chiede a Lucia su WhatsApp (prezzi di esempio, come la casa).
        $extra = Properties::addSection($acc, $pid, 'extras');
        self::scrivi($pid, $extra, [
            'it' => ['items' => [
                        ['title' => 'Transfer dalla stazione', 'description' => 'Ti veniamo a prendere alla stazione di Spello con i bagagli.', 'price' => '15 € a tratta'],
                        ['title' => 'Colazione in casa', 'description' => 'Pane, marmellate e torta della mattina, lasciati in cucina la sera prima.', 'price' => '8 € a persona']],
                     'note' => 'Da chiedere almeno un giorno prima.'],
            'en' => ['items' => [['title' => 'Station transfer', 'description' => 'We pick you up at Spello station, luggage included.'],
                                 ['title' => 'Breakfast at home', 'description' => 'Bread, jams and the morning cake, left in the kitchen the night before.']],
                     'note' => 'Please ask at least one day ahead.'],
        ]);

        $mangiare = Properties::addSection($acc, $pid, 'eat');
        self::scrivi($pid, $mangiare, [
            'it' => ['intro' => 'Tre posti a piedi. Li abbiamo provati tutti, più di una volta.',
                     'host_note' => "All'Arco prenota per telefono: non rispondono alle mail, ma rispondono sempre."],
            'en' => ['intro' => 'Three places within walking distance. We have tried them all, more than once.',
                     'host_note' => "Book L'Arco by phone: they never answer email, but they always pick up."],
            'de' => ['intro' => 'Drei Lokale zu Fuß. Wir haben sie alle mehr als einmal probiert.'],
        ], 'Dove mangiare e bere');
        foreach ([
            ['Osteria dell\'Arco', 'Trattoria', 'Cucina umbra, strangozzi al tartufo fatti a mano.', 'Vicolo dell\'Arco 3', 6, 'pine', 'osteria.jpg', 'Tavoli apparecchiati nel vicolo',
             ['it' => 'Perfetto per cena', 'en' => 'Perfect for dinner'], ['en' => 'Trattoria', 'de' => 'Trattoria']],
            ['Bar della Fontana', 'Colazione', 'Cornetti caldi e cappuccino al banco.', 'Piazzetta della Fontana 1', 3, 'sea', 'caffe.jpg', 'Una tazzina di espresso sul bancone',
             ['it' => 'Ideale per colazione', 'en' => 'Ideal for breakfast'], ['en' => 'Breakfast', 'de' => 'Frühstück']],
            ['Gelateria delle Rose', 'Gelato', 'Il gusto alle rose vale la camminata in salita.', 'Via dei Fiori 22', 8, 'ochre', 'gelato.jpg', 'Vaschette di gelato dietro il banco',
             ['it' => "Consigliato dall'host", 'en' => 'Host favourite'], ['en' => 'Ice cream', 'de' => 'Eis']],
        ] as [$n, $cat, $desc, $ind, $min, $tono, $img, $alt, $badge, $catTr]) {
            $plid = Properties::savePlace($acc, $pid, $mangiare, null, 'it', true, [
                'name' => $n, 'category' => $cat, 'description' => $desc, 'address' => $ind . ', Spello',
                'maps_url' => 'https://maps.google.com/?q=' . rawurlencode($ind . ', Spello'),
                'walk_minutes' => $min, 'badge' => $badge['it'], 'badge_tone' => $tono,
            ]);
            Properties::savePlace($acc, $pid, $mangiare, $plid, 'en', false, ['category' => $catTr['en'], 'badge' => $badge['en']]);
            Properties::savePlace($acc, $pid, $mangiare, $plid, 'de', false, ['category' => $catTr['de']]);
            Db::update('places', ['media_id' => self::foto($img, $acc, $pid, $alt)], 'id = :pid', ['pid' => $plid]);
        }
        Guide::publish($pid);
        $creati[] = ['Lucia Ferrante', 'lucia@' . self::DOMINIO, 'Plus', 'Casa Lucia — pubblicata'];

        // ------------------- B&B Le Rondini, Puglia — Portfolio per 2 strutture, NON ancora pagato:
        // si configura una struttura (Le Rondini); la seconda c'è col solo nome, bloccata.
        $marco = self::host('Marco Bevilacqua', 'marco@' . self::DOMINIO, 'portfolio', false, 2);
        $acc = (int) $marco['account_id'];
        $pid = self::struttura($acc, [
            'nome' => 'B&B Le Rondini', 'citta' => 'Lecce', 'regione' => 'Puglia', 'host' => 'Marco',
            'arrivo' => '14:00', 'partenza' => '11:00', 'telefono' => '+39 0832 000000',
            'lingue' => ['it', 'en', 'es'], 'palette' => 'mare',
        ]);
        Db::update('properties', ['cover_media_id' => self::foto('soggiorno.jpg', $acc, $pid, 'Il soggiorno con il divano chiaro')],
                   'id = :pid', ['pid' => $pid]);
        self::scrivi($pid, self::nucleo($pid), [
            'it' => ['checkin_steps' => ['Il portone sulla strada resta aperto fino alle 20.', 'Sali al primo piano: Marco ti aspetta alla porta blu.'],
                     'checkout_keys' => 'Lascia le chiavi nella ciotola accanto alla porta.'],
            'en' => ['checkin_steps' => ['The street door stays open until 8pm.', 'Go up to the first floor: Marco will meet you at the blue door.'],
                     'checkout_keys' => 'Leave the keys in the bowl next to the door.'],
            'es' => ['checkin_steps' => ['La puerta de la calle está abierta hasta las 20.', 'Sube al primer piso: Marco os espera en la puerta azul.'],
                     'checkout_keys' => 'Dejad las llaves en el cuenco junto a la puerta.'],
        ], 'Check-in & Check-out');
        $wifi = Properties::addSection($acc, $pid, 'wifi');
        self::scrivi($pid, $wifi, ['it' => ['network' => 'Rondini_Ospiti', 'password' => 'salento2024',
                                            'instructions' => 'La rete arriva in tutte le stanze, meno che sul terrazzo.']], '');
        $mare = Properties::addSection($acc, $pid, 'visit');
        self::scrivi($pid, $mare, ['it' => ['intro' => "Il mare è a venti minuti di macchina. Vai presto la mattina: dopo le dieci il parcheggio è pieno."],
                                   'en' => ['intro' => 'The sea is twenty minutes by car. Go early: after ten the car park is full.']], 'Il mare');
        Properties::savePlace($acc, $pid, $mare, null, 'it', true, [
            'name' => "Torre dell'Orso", 'category' => 'Spiaggia', 'drive_minutes' => 20,
            'maps_url' => 'https://maps.google.com/?q=Torre+dell%27Orso', 'badge' => 'Al mattino presto', 'badge_tone' => 'sea',
        ]);
        $parcheggio = Properties::addSection($acc, $pid, 'parking');
        self::scrivi($pid, $parcheggio, ['it' => ['options' => [
            ['type' => 'pubblico', 'name' => 'Lungo il viale', 'address' => 'Viale Lo Re, Lecce',
             'instructions' => 'Le strisce bianche sono gratuite, quelle blu a pagamento.']],
            'ztl' => 'Il centro storico è ZTL: non entrare in auto, i varchi hanno le telecamere.']], '');
        Guide::publish($pid);
        // La seconda struttura del Portfolio: solo il nome, si attiva dopo il pagamento.
        Properties::create($acc, 'Casa sul Mare', 'Otranto', 'Marco');
        $creati[] = ['Marco Bevilacqua', 'marco@' . self::DOMINIO, 'Portfolio · 2 strutture, non pagato', 'B&B Le Rondini — pubblicata (demo); Casa sul Mare — si attiva dopo il pagamento'];

        // --------------------------------------------- Il Cortile, Sicilia — Essential
        $agnese = self::host('Agnese Ruta', 'agnese@' . self::DOMINIO, 'essential');
        $acc = (int) $agnese['account_id'];
        $pid = self::struttura($acc, [
            'nome' => 'Il Cortile', 'citta' => 'Ortigia', 'regione' => 'Sicilia', 'host' => 'Agnese',
            'arrivo' => '16:00', 'partenza' => '10:00', 'telefono' => '+39 0931 000000', 'lingue' => ['it'], 'palette' => 'oliva',
        ]);
        self::scrivi($pid, self::nucleo($pid), ['it' => ['checkin_steps' => ['Sto ancora scrivendo questa parte.']]], 'Check-in & Check-out');
        Db::update('properties', ['wizard_step' => 'sezioni'], 'id = :pid', ['pid' => $pid]);
        // Volutamente NON pubblicata: serve a vedere lo stato di bozza.
        $creati[] = ['Agnese Ruta', 'agnese@' . self::DOMINIO, 'Essential', 'Il Cortile — ancora in bozza'];

        return $creati;
    }
}
