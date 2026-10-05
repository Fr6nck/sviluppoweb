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

    /**
     * La «vetrina»: una guida dimostrativa completa dentro un account vero (per esempio
     * quello dell'agenzia), creata dall'amministrazione. Sulla falsariga di Casa Lucia,
     * con nomi, numeri e indicazioni diversi, tutti di fantasia: la guida mostra «Demo».
     * is_demo = VETRINA (2): online senza pagamento come le altre demo, ma non occupa
     * il posto di una struttura del piano, e la landing la preferisce come demo.
     * Foto, lingue e luoghi seguono il piano dell'account (serve Plus).
     */
    public const VETRINA = 2;

    public static function vetrina(int $accountId): int
    {
        $acc = $accountId;
        $pid = Properties::create($acc, 'Casa dei Gerani', 'Bevagna', 'Giulia', 1);
        Db::update('properties', [
            'region' => 'Umbria', 'checkin_from' => '16:00', 'checkout_by' => '10:00',
            'host_phone' => '+39 0742 000111', 'host_whatsapp' => '+39 0742 000111', 'palette' => 'oliva',
            'is_demo' => self::VETRINA, 'wizard_step' => 'fatto', 'property_type' => 'casa_vacanza',
        ], 'id = :pid', ['pid' => $pid]);
        Properties::setLocales($acc, $pid, array_values(array_intersect(['it', 'en'], Entitlements::allowedLocales($acc))));
        Properties::saveContacts($pid, Conversione::contatti('Giulia', '+39 0742 000111', '+39 0742 000111'));
        Db::update('properties', [
            'cover_media_id' => self::foto('borgo.jpg', $acc, $pid, 'La scalinata in pietra con i gerani rossi, tra le case del borgo'),
        ], 'id = :pid', ['pid' => $pid]);

        $core = self::nucleo($pid);
        Db::update('sections', ['media_id' => self::foto('soggiorno.jpg', $acc, $pid, 'Il soggiorno con il divano chiaro e la cucina in mattoni')], 'id = :sid', ['sid' => $core]);
        self::scrivi($pid, $core, [
            'it' => ['checkin_steps' => ['Sali la scalinata con i gerani: la porta di casa è quella in legno, in cima.',
                                        'Giulia ti aspetta sul pianerottolo con le chiavi e ti fa vedere la casa.'],
                     'checkin_note' => 'Arrivi dopo le 20? Scrivi a Giulia su WhatsApp: troviamo un orario.',
                     'checkout_notes' => 'Grazie di aver scelto Casa dei Gerani. Torna a trovarci!'],
            'en' => ['checkin_steps' => ['Climb the stone steps with the geraniums: the front door is the wooden one at the top.',
                                        'Giulia will meet you on the landing with the keys and show you around.'],
                     'checkin_note' => 'Arriving after 8pm? Message Giulia on WhatsApp and we will find a time.',
                     'checkout_notes' => 'Thank you for choosing Casa dei Gerani. Come back soon!'],
        ], 'Check-in & Check-out');
        // La partenza come lista ordinata (dalla 009): già nel formato nuovo.
        Properties::saveSection($pid, $core, 'it', ['checkout_steps' => ['Chiavi: lasciale nella ciotola di ceramica accanto alla porta.',
            'Rifiuti: il vetro va nella campana in fondo alla via.', 'Finestre: chiudi le persiane della camera.']], true);

        $wifi = Properties::addSection($acc, $pid, 'wifi');
        self::scrivi($pid, $wifi, [
            'it' => ['network' => 'Gerani_Ospiti', 'password' => 'scalinata2026',
                     'instructions' => 'Il segnale è più forte in soggiorno. Se cade, spegni il router per trenta secondi.',
                     'router_location' => 'Sulla libreria del soggiorno, dietro i libri di cucina.'],
            'en' => ['instructions' => 'The signal is strongest in the living room. If it drops, switch the router off for thirty seconds.',
                     'router_location' => 'On the living room bookcase, behind the cookbooks.'],
        ], 'Wi-Fi');

        $regole = Properties::addSection($acc, $pid, 'rules');
        self::scrivi($pid, $regole, [
            'it' => ['flags' => ['smoking' => 'no', 'pets' => 'no', 'parties' => 'no', 'visitors' => 'si'], 'quiet_on' => '1', 'quiet_from' => '23:00', 'quiet_to' => '08:30',
                     'items' => ['La scalinata è di tutti: niente valigie lasciate sui gradini.', 'In cucina c\'è tutto: lascia le stoviglie lavate.']],
            'en' => ['items' => ['The stone steps are shared: please do not leave luggage on them.', 'The kitchen has everything: please leave the dishes washed.']],
        ]);

        $rifiuti = Properties::addSection($acc, $pid, 'waste');
        self::scrivi($pid, $rifiuti, [
            'it' => ['bins' => [
                        ['type' => 'umido', 'days' => [1, 4], 'color' => 'marrone', 'label' => '', 'where' => 'Sotto il lavello'],
                        ['type' => 'plastica', 'days' => [2], 'color' => 'giallo', 'label' => '', 'where' => 'Nello sgabuzzino'],
                        ['type' => 'carta', 'days' => [5], 'color' => 'blu', 'label' => '', 'where' => 'Nello sgabuzzino'],
                        ['type' => 'vetro', 'days' => [], 'color' => 'verde', 'label' => 'Quando vuoi', 'where' => 'Campana in fondo alla via']],
                     'note' => 'I sacchetti si lasciano davanti alla porta entro le 7 del mattino.'],
            'en' => ['bins' => [['where' => 'Under the sink'], ['where' => 'In the storeroom'], ['where' => 'In the storeroom'],
                                ['label' => 'Any time', 'where' => 'Bottle bank at the end of the street']],
                     'note' => 'Leave the bags outside the door by 7am.'],
        ]);

        $emergenze = Properties::addSection($acc, $pid, 'emergency');
        self::scrivi($pid, $emergenze, [
            'it' => ['emergency_number' => '112', 'contacts' => [
                        ['name' => 'Giulia, per la casa', 'phone' => '+39 0742 000111', 'note' => 'Dalle 9 alle 21'],
                        ['name' => 'Guardia medica', 'phone' => '', 'note' => 'Il numero è sul foglio appeso in cucina.']]],
            'en' => ['contacts' => [['name' => 'Giulia, for the house', 'note' => '9am to 9pm'],
                                    ['name' => 'Out-of-hours doctor', 'note' => 'The number is on the sheet in the kitchen.']]],
        ]);

        $parcheggio = Properties::addSection($acc, $pid, 'parking');
        self::scrivi($pid, $parcheggio, ['it' => ['options' => [
            ['type' => 'pubblico', 'name' => 'Parcheggio fuori dalle mura', 'address' => 'Porta Foligno, Bevagna', 'walk_minutes' => '5',
             'instructions' => 'Lascia l\'auto fuori dalle mura e sali a piedi: in centro non si parcheggia.']],
            'ztl' => 'Il centro storico è zona a traffico limitato: con l\'auto puoi solo scaricare i bagagli, in 15 minuti.']], '');

        $extra = Properties::addSection($acc, $pid, 'extras');
        self::scrivi($pid, $extra, [
            'it' => ['items' => [
                        ['title' => 'Cesto della colazione', 'description' => 'Pane, miele e frutta di stagione, lasciati in cucina la sera prima.', 'amount' => '10', 'unit' => 'per_person'],
                        ['title' => 'Degustazione di olio in casa', 'description' => 'Tre oli della zona da assaggiare con il pane caldo.', 'amount' => '12', 'unit' => 'per_person']],
                     'note' => 'Da chiedere entro le 18 del giorno prima.'],
            'en' => ['items' => [['title' => 'Breakfast basket', 'description' => 'Bread, honey and seasonal fruit, left in the kitchen the night before.'],
                                 ['title' => 'Olive oil tasting at home', 'description' => 'Three local oils to taste with warm bread.']],
                     'note' => 'Please ask by 6pm the day before.'],
        ]);

        $muoversi = Properties::addSection($acc, $pid, 'transport');
        self::scrivi($pid, $muoversi, [
            'it' => ['options' => [
                        ['type' => 'bus', 'name' => 'Autobus per Foligno', 'where' => 'La fermata è fuori da Porta Foligno.', 'note' => 'Corse più rare la domenica.'],
                        ['type' => 'walk', 'name' => 'Il borgo a piedi', 'note' => 'In venti minuti fai il giro delle mura.']]],
            'en' => ['options' => [
                        ['name' => 'Bus to Foligno', 'where' => 'The stop is outside Porta Foligno.', 'note' => 'Fewer buses on Sundays.'],
                        ['name' => 'The village on foot', 'note' => 'The walk around the walls takes twenty minutes.']]],
        ]);

        // I luoghi solo se il piano li comprende: senza, la guida resta completa nel resto.
        if (Entitlements::can($acc, 'places')) {
            $mangiare = Properties::addSection($acc, $pid, 'eat');
            self::scrivi($pid, $mangiare, [
                'it' => ['intro' => 'Tre indirizzi a due passi dalla scalinata, scelti da Giulia.'],
                'en' => ['intro' => 'Three places a short walk from the steps, picked by Giulia.'],
            ], 'Dove mangiare');
            foreach ([
                ['Trattoria delle Tre Arcate', 'trattoria', 'Pasta fatta in casa e carne alla brace.', 'Vicolo delle Arcate 2', 4, 'pine', 'osteria.jpg', 'Tavoli apparecchiati nel vicolo', 'dinner'],
                ['Caffè del Loggiato', 'breakfast', 'Cappuccino e crostata sotto il loggiato.', 'Piazza del Loggiato 5', 2, 'sea', 'caffe.jpg', 'Una tazzina di espresso sul bancone', 'breakfast'],
                ['Gelateria Ai Gerani', 'gelato', 'Prova il gusto al miele e noci.', 'Via delle Scale 9', 3, 'ochre', 'gelato.jpg', 'Vaschette di gelato dietro il banco', 'host_pick'],
            ] as [$n, $cat, $desc, $ind, $min, $tono, $img, $alt, $badge]) {
                $plid = Properties::savePlace($acc, $pid, $mangiare, null, 'it', true, [
                    'name' => $n, 'category_choice' => $cat, 'description' => $desc, 'address' => $ind . ', Bevagna',
                    'maps_url' => 'https://maps.google.com/?q=' . rawurlencode($ind . ', Bevagna'),
                    'walk_minutes' => $min, 'badge_choice' => $badge, 'badge_tone' => $tono,
                ]);
                Db::update('places', ['media_id' => self::foto($img, $acc, $pid, $alt)], 'id = :pid', ['pid' => $plid]);
            }
            $negozi = Properties::addSection($acc, $pid, 'shop');
            self::scrivi($pid, $negozi, ['it' => ['intro' => 'Per la spesa basta scendere la scalinata.'],
                                         'en' => ['intro' => 'For groceries, just walk down the steps.']], '');
            foreach ([['Bottega di Anna', 'grocery', 'Salumi, formaggi e verdura dell\'orto.', 'Via delle Scale 3', 1, 'local'],
                      ['Forno di Piazza', 'bakery', 'Pane casereccio e biscotti all\'anice.', 'Piazza del Loggiato 1', 2, 'on_foot']] as [$n, $cat, $desc, $ind, $min, $badge]) {
                Properties::savePlace($acc, $pid, $negozi, null, 'it', true, [
                    'name' => $n, 'category_choice' => $cat, 'description' => $desc, 'address' => $ind . ', Bevagna',
                    'maps_url' => 'https://maps.google.com/?q=' . rawurlencode($ind . ', Bevagna'),
                    'walk_minutes' => $min, 'badge_choice' => $badge, 'badge_tone' => 'sea',
                ]);
            }
        }
        Guide::publish($pid);
        return $pid;
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
            'cover_media_id' => self::foto('casa.jpg', $acc, $pid, 'Il portone in legno ad arco, tra la pietra, i fiori rampicanti e i vasi di terracotta'),
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
                        ['title' => 'Transfer dalla stazione', 'description' => 'Ti veniamo a prendere alla stazione di Spello con i bagagli.', 'amount' => '15', 'unit' => 'per_trip'],
                        ['title' => 'Colazione in casa', 'description' => 'Pane, marmellate e torta della mattina, lasciati in cucina la sera prima.', 'amount' => '8', 'unit' => 'per_person']],
                     'note' => 'Da chiedere almeno un giorno prima.'],
            'en' => ['items' => [['title' => 'Station transfer', 'description' => 'We pick you up at Spello station, luggage included.'],
                                 ['title' => 'Breakfast at home', 'description' => 'Bread, jams and the morning cake, left in the kitchen the night before.']],
                     'note' => 'Please ask at least one day ahead.'],
        ]);

        // Muoversi in zona (fase 6C): una scheda per modo, senza nomi di aziende.
        $muoversi = Properties::addSection($acc, $pid, 'transport');
        self::scrivi($pid, $muoversi, [
            'it' => ['options' => [
                        ['type' => 'bus', 'name' => 'Autobus per Assisi e Foligno', 'where' => 'La fermata è in piazza, sotto le mura.', 'note' => 'Biglietti in tabaccheria, non a bordo.'],
                        ['type' => 'walk', 'name' => 'Il centro a piedi', 'note' => 'Le salite sono ripide: scarpe comode.'],
                        ['type' => 'bike_rental', 'name' => 'Bici elettriche', 'note' => 'Chiedi a Lucia: te ne prenota una per il giorno dopo.']]],
            'en' => ['options' => [
                        ['name' => 'Bus to Assisi and Foligno', 'where' => 'The stop is in the square, below the walls.', 'note' => 'Tickets at the tobacconist, not on board.'],
                        ['name' => 'The old town on foot', 'note' => 'The climbs are steep: comfortable shoes.'],
                        ['name' => 'E-bikes', 'note' => 'Ask Lucia: she books one for the next day.']]],
        ]);

        $mangiare = Properties::addSection($acc, $pid, 'eat');
        self::scrivi($pid, $mangiare, [
            'it' => ['intro' => 'Tre posti a piedi. Li abbiamo provati tutti, più di una volta.',
                     'host_note' => "All'Arco prenota per telefono: non rispondono alle mail, ma rispondono sempre."],
            'en' => ['intro' => 'Three places within walking distance. We have tried them all, more than once.',
                     'host_note' => "Book L'Arco by phone: they never answer email, but they always pick up."],
            'de' => ['intro' => 'Drei Lokale zu Fuß. Wir haben sie alle mehr als einmal probiert.'],
        ], 'Dove mangiare e bere');
        // Categoria ed etichetta dalle tassonomie (fase 6B): si traducono da sole in ogni lingua.
        foreach ([
            ['Osteria dell\'Arco', 'trattoria', 'Cucina umbra, strangozzi al tartufo fatti a mano.', 'Vicolo dell\'Arco 3', 6, 'pine', 'osteria.jpg', 'Tavoli apparecchiati nel vicolo', 'dinner'],
            ['Bar della Fontana', 'breakfast', 'Cornetti caldi e cappuccino al banco.', 'Piazzetta della Fontana 1', 3, 'sea', 'caffe.jpg', 'Una tazzina di espresso sul bancone', 'breakfast'],
            ['Gelateria delle Rose', 'gelato', 'Il gusto alle rose vale la camminata in salita.', 'Via dei Fiori 22', 8, 'ochre', 'gelato.jpg', 'Vaschette di gelato dietro il banco', 'host_pick'],
        ] as [$n, $cat, $desc, $ind, $min, $tono, $img, $alt, $badge]) {
            $plid = Properties::savePlace($acc, $pid, $mangiare, null, 'it', true, [
                'name' => $n, 'category_choice' => $cat, 'description' => $desc, 'address' => $ind . ', Spello',
                'maps_url' => 'https://maps.google.com/?q=' . rawurlencode($ind . ', Spello'),
                'walk_minutes' => $min, 'badge_choice' => $badge, 'badge_tone' => $tono,
            ]);
            Db::update('places', ['media_id' => self::foto($img, $acc, $pid, $alt)], 'id = :pid', ['pid' => $plid]);
        }
        // Negozi e spesa (fase 6B): due negozi con nomi di fantasia.
        $negozi = Properties::addSection($acc, $pid, 'shop');
        self::scrivi($pid, $negozi, [
            'it' => ['intro' => 'Per la spesa non serve la macchina: tutto è a pochi passi, in salita.'],
            'en' => ['intro' => 'No car needed for groceries: everything is a short walk away, uphill.'],
            'de' => ['intro' => 'Für den Einkauf braucht man kein Auto: alles ist nur ein paar Schritte entfernt, bergauf.'],
        ], '');
        foreach ([
            ['Alimentari da Rita', 'grocery', 'Formaggi, salumi umbri e la frutta di stagione.', 'Via del Pozzo 7', 4, 'local'],
            ['Forno del Borgo', 'bakery', 'Pane cotto a legna e torta al testo.', 'Via della Torre 2', 5, 'on_foot'],
        ] as [$n, $cat, $desc, $ind, $min, $badge]) {
            Properties::savePlace($acc, $pid, $negozi, null, 'it', true, [
                'name' => $n, 'category_choice' => $cat, 'description' => $desc, 'address' => $ind . ', Spello',
                'maps_url' => 'https://maps.google.com/?q=' . rawurlencode($ind . ', Spello'),
                'walk_minutes' => $min, 'badge_choice' => $badge, 'badge_tone' => 'sea',
            ]);
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
            'name' => "Torre dell'Orso", 'category_choice' => 'beach', 'drive_minutes' => 20,
            'maps_url' => 'https://maps.google.com/?q=Torre+dell%27Orso', 'badge' => 'Al mattino presto', 'badge_tone' => 'sea',
        ]);
        $parcheggio = Properties::addSection($acc, $pid, 'parking');
        self::scrivi($pid, $parcheggio, ['it' => ['options' => [
            ['type' => 'pubblico', 'name' => 'Lungo il viale', 'address' => 'Viale Lo Re, Lecce', 'walk_minutes' => '4',
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
