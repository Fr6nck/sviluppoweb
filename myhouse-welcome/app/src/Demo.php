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
     * quello dell'agenzia), creata dall'amministrazione: «Casa Checco», ad Assisi, vicino
     * a Piazza Matteotti. Sulla falsariga di Casa Lucia, con nomi, numeri, indicazioni e
     * foto diversi (ricavate da quelle della demo), tutti di fantasia: la guida mostra «Demo».
     * is_demo = VETRINA (2): online senza pagamento come le altre demo, ma non occupa
     * il posto di una struttura del piano, e la landing la preferisce come demo.
     * Foto, lingue e luoghi seguono il piano dell'account (serve Plus).
     */
    public const VETRINA = 2;
    /** Piazza Matteotti ad Assisi (OpenStreetMap): la posizione della vetrina, non un portone vero. */
    public const CHECCO_LAT = 43.07025;
    public const CHECCO_LNG = 12.61966;
    public const CHECCO_MAPS = 'https://www.google.com/maps/search/?api=1&query=43.07025,12.61966';

    public static function vetrina(int $accountId): int
    {
        // «Casa Checco», Assisi: una casa di fantasia in un vicolo di fantasia, a due passi da
        // Piazza Matteotti. La posizione (link di Maps e coordinate della struttura) è quella
        // della piazza, presa da OpenStreetMap, non di un portone vero. Locali,
        // numeri, prezzi ed eventi sono inventati (le date degli eventi partono da oggi); sono
        // veri solo i monumenti pubblici di «Cosa visitare». Ogni sezione è compilata del tutto,
        // per far vedere a chi prova la piattaforma tutto quello che una guida può contenere.
        $acc = $accountId;
        $tel = '+39 075 000 0000';
        $pid = Properties::create($acc, 'Casa Checco', 'Assisi', 'Francesco', 1);
        Db::update('properties', [
            'region' => 'Umbria', 'address' => 'Vicolo dei Gerani 3', 'postal_code' => '06081',
            'checkin_from' => '15:00', 'checkout_by' => '10:30', 'host_phone' => $tel, 'host_whatsapp' => $tel, 'palette' => 'terracotta',
            'is_demo' => self::VETRINA, 'wizard_step' => 'fatto', 'property_type' => 'appartamento',
        ], 'id = :pid', ['pid' => $pid]);
        // La geolocalizzazione: Piazza Matteotti ad Assisi (serve anche a stimare i minuti a piedi dei luoghi).
        if (Migrator::columnExists('properties', 'lat')) Db::update('properties', ['lat' => self::CHECCO_LAT, 'lng' => self::CHECCO_LNG], 'id = :pid', ['pid' => $pid]);
        Properties::setLocales($acc, $pid, array_values(array_intersect(['it', 'en', 'de', 'fr', 'es'], Entitlements::allowedLocales($acc))));
        Properties::saveContacts($pid, [['name' => 'Francesco', 'role' => 'host', 'phone' => $tel, 'whatsapp' => 1],
                                        ['name' => 'Marta, per le pulizie', 'role' => 'altro', 'phone' => '+39 075 000 0001', 'whatsapp' => 0]]);
        $foto = fn(string $nome, string $alt) => self::foto($nome, $acc, $pid, $alt);
        Db::update('properties', ['cover_media_id' => $foto('checco-copertina.jpg', 'La scalinata in pietra con i gerani rossi e la porta in legno in cima')], 'id = :pid', ['pid' => $pid]);
        $giorno = fn(int $n) => (new \DateTimeImmutable(Eventi::oggi()))->modify(($n >= 0 ? '+' : '') . $n . ' days')->format('Y-m-d');

        // -------- Check-in & Check-out
        $core = self::nucleo($pid);
        Db::update('sections', ['media_id' => $foto('checco-ingresso.jpg', 'La porta rossa ad arco con la rosa rampicante sul muro')], 'id = :sid', ['sid' => $core]);
        self::scrivi($pid, $core, [
            'it' => ['arrival_mode' => 'accoglienza',
                     'checkin_steps' => ['Da Piazza Matteotti prendi la scalinata accanto alla fontanella e scendi fino al primo vicolo a destra.',
                                         'Casa Checco è la porta rossa ad arco, al numero 3, con la rosa sul muro.',
                                         'Francesco ti aspetta lì con le chiavi: scrivigli su WhatsApp mezz\'ora prima di arrivare.',
                                         'In casa trovi il quaderno con le istruzioni e la mappa della città.'],
                     'late_arrival' => 'Dopo le 21 lasciamo le chiavi in una cassetta con il codice: te lo mandiamo su WhatsApp il giorno stesso, non lo scriviamo qui.',
                     'documents' => 'Un documento d\'identità per ogni ospite, anche per i bambini: serve per la registrazione obbligatoria. Puoi mandarne una foto su WhatsApp prima dell\'arrivo.',
                     'tax_amount' => '1,50 € a persona (esempio)', 'tax_max_nights' => '5',
                     'tax_notes' => 'Esenti i bambini sotto i 12 anni. Si paga in contanti all\'arrivo: ti lasciamo la ricevuta.',
                     'checkin_note' => 'Il vicolo è pedonale: con l\'auto non si arriva alla porta. Il parcheggio di Piazza Matteotti è a due minuti: guarda la sezione Parcheggio.',
                     'checkout_steps' => ['Chiavi: lasciale nella ciotola di ceramica sul mobile dell\'ingresso.',
                                          'Rifiuti: porta i sacchetti nei bidoni in fondo al vicolo.',
                                          'Aria condizionata e luci: spegnile prima di uscire.',
                                          'Finestre: chiudi le persiane, il vento dal Subasio è forte.',
                                          'Stoviglie: basta metterle in lavastoviglie, la accendiamo noi.'],
                     'checkout_notes' => 'Grazie di aver scelto Casa Checco. Se ti sei trovato bene, una recensione ci aiuta tantissimo.'],
            'en' => ['checkin_steps' => ['From Piazza Matteotti take the steps next to the drinking fountain and go down to the first alley on the right.',
                                         'Casa Checco is the red arched door at number 3, with the rose on the wall.',
                                         'Francesco will meet you there with the keys: message him on WhatsApp half an hour before you arrive.',
                                         'Inside you will find the house notebook and a map of the town.'],
                     'late_arrival' => 'After 9pm we leave the keys in a code box: we send you the code on WhatsApp on the day, we never write it here.',
                     'documents' => 'An ID for every guest, children included: it is required for the mandatory registration. You can send a photo on WhatsApp before you arrive.',
                     'tax_notes' => 'Children under 12 are exempt. Paid in cash on arrival: we leave you a receipt.',
                     'checkin_note' => 'The alley is pedestrian: you cannot drive to the door. The Piazza Matteotti car park is two minutes away: see the Parking section.',
                     'checkout_steps' => ['Keys: leave them in the ceramic bowl on the hall cabinet.', 'Rubbish: take the bags to the bins at the end of the alley.',
                                          'Air conditioning and lights: switch them off before leaving.', 'Windows: close the shutters, the wind from Mount Subasio is strong.',
                                          'Dishes: just load the dishwasher, we will start it.'],
                     'checkout_notes' => 'Thank you for choosing Casa Checco. If you enjoyed your stay, a review helps us a lot.'],
            'de' => ['checkin_steps' => ['Nehmen Sie von der Piazza Matteotti die Treppe neben dem Trinkbrunnen bis zur ersten Gasse rechts.',
                                         'Casa Checco ist die rote Bogentür mit der Nummer 3 und der Rose an der Wand.',
                                         'Francesco erwartet Sie dort mit den Schlüsseln: schreiben Sie ihm eine halbe Stunde vorher auf WhatsApp.',
                                         'Im Haus finden Sie das Hausheft und einen Stadtplan.']],
        ], 'Check-in & Check-out');

        // -------- Wi-Fi, due reti
        $wifi = Properties::addSection($acc, $pid, 'wifi');
        self::scrivi($pid, $wifi, [
            'it' => ['networks' => [['ssid' => 'CasaChecco', 'password' => 'vicolo-dei-gerani', 'zone' => 'Soggiorno e cucina'],
                                    ['ssid' => 'CasaChecco_Camera', 'password' => 'vicolo-dei-gerani', 'zone' => 'Camera da letto e terrazzino']],
                     'instructions' => 'Se la rete non si vede, spegni il router per trenta secondi e riaccendilo. La fibra regge anche le videochiamate di lavoro.',
                     'router_location' => 'Nel mobile basso del soggiorno, accanto alla TV.'],
            'en' => ['networks' => [['zone' => 'Living room and kitchen'], ['zone' => 'Bedroom and small terrace']],
                     'instructions' => 'If the network does not show up, switch the router off for thirty seconds and back on. The fibre handles work video calls too.',
                     'router_location' => 'In the low cabinet in the living room, next to the TV.'],
        ], 'Wi-Fi');

        // -------- Servizi: dotazioni, le tue dotazioni, istruzioni
        $servizi = Properties::addSection($acc, $pid, 'services');
        self::scrivi($pid, $servizi, [
            'it' => ['amenities' => ['dishwasher', 'oven', 'microwave', 'fridge', 'coffee', 'kettle', 'ac', 'heating', 'tv', 'desk', 'washer', 'iron', 'hairdryer', 'linens', 'towels', 'terrace', 'crib', 'first_aid'],
                     'items' => ['Macchina per il caffè a capsule, con le prime capsule incluse', 'Ombrelli all\'ingresso', 'Adattatori per le prese straniere'],
                     'manuals' => [['title' => 'La lavatrice', 'steps' => "Chiudi l'oblò fino al clic.\nDetersivo nella vaschetta di sinistra.\nGira la manopola su «Cotone 40°» e premi Avvio.\nIl ciclo dura un'ora e mezza: lo stendino è sul terrazzino."],
                                   ['title' => 'L\'aria condizionata', 'steps' => "Il telecomando è sul comodino.\nPremi ON, poi il fiocco di neve per raffreddare.\n24 gradi bastano: le mura in pietra tengono il fresco.\nSpegnila quando apri le finestre."]],
                     'note' => 'Lenzuola e asciugamani si cambiano ogni tre notti, o prima se lo chiedi.'],
            'en' => ['items' => ['Capsule coffee machine, first capsules included', 'Umbrellas by the door', 'Adapters for foreign plugs'],
                     'manuals' => [['title' => 'The washing machine', 'steps' => "Close the door until it clicks.\nDetergent in the left drawer.\nTurn the dial to «Cotton 40°» and press Start.\nThe cycle takes an hour and a half: the drying rack is on the terrace."],
                                   ['title' => 'Air conditioning', 'steps' => "The remote is on the bedside table.\nPress ON, then the snowflake to cool.\n24 degrees is enough: the stone walls keep it cool.\nSwitch it off when you open the windows."]],
                     'note' => 'Sheets and towels are changed every three nights, or sooner if you ask.'],
        ], 'Servizi');

        // -------- Servizi extra, con prezzo e unità
        $extra = Properties::addSection($acc, $pid, 'extras');
        self::scrivi($pid, $extra, [
            'it' => ['items' => [
                        ['title' => 'Transfer dalla stazione', 'amount' => '20', 'unit' => 'per_trip', 'description' => 'Ti aspettiamo alla stazione di Assisi, a Santa Maria degli Angeli, con un cartello col tuo nome e ti portiamo fino al parcheggio di Piazza Matteotti.'],
                        ['title' => 'Cesto della colazione', 'amount' => '9', 'unit' => 'per_person', 'description' => 'Pane, torta al testo, marmellate e frutta di stagione, lasciati in cucina la sera prima.', 'price_note' => 'Gratis per i bambini sotto i 6 anni.'],
                        ['title' => 'Check-out tardivo', 'amount' => '', 'unit' => 'on_request', 'description' => 'Fino alle 14, se la casa non è prenotata quel giorno.'],
                        ['title' => 'Degustazione di olio e vini umbri', 'amount' => '25', 'unit' => 'per_person', 'description' => 'Olio nuovo degli uliveti del Subasio e tre vini della zona, con salumi e formaggi, a casa, la sera che scegli.']],
                     'note' => 'Si chiedono su WhatsApp, almeno un giorno prima.'],
            'en' => ['items' => [['title' => 'Station transfer', 'description' => 'We wait for you at Assisi station, in Santa Maria degli Angeli, with your name on a sign and drive you to the Piazza Matteotti car park.'],
                                 ['title' => 'Breakfast basket', 'description' => 'Bread, torta al testo, jams and seasonal fruit, left in the kitchen the night before.', 'price_note' => 'Free for children under 6.'],
                                 ['title' => 'Late check-out', 'description' => 'Until 2pm, if the house is not booked that day.'],
                                 ['title' => 'Umbrian olive oil and wine tasting', 'description' => 'New oil from the Subasio olive groves and three local wines, with cured meats and cheese, at home, on the evening you choose.']],
                     'note' => 'Ask on WhatsApp, at least one day ahead.'],
        ], 'Servizi extra');

        // -------- Regole
        $regole = Properties::addSection($acc, $pid, 'rules');
        self::scrivi($pid, $regole, [
            'it' => ['flags' => ['smoking' => 'no', 'pets' => 'si', 'parties' => 'no', 'visitors' => 'no'], 'quiet_on' => '1', 'quiet_from' => '23:00', 'quiet_to' => '08:00',
                     'items' => ['Il vicolo è abitato: la sera parla a bassa voce anche sulle scale.', 'Gli animali sono i benvenuti, ma non sul divano e sui letti.', 'Niente scarpe col tacco sul pavimento in cotto: ci sono le pantofole.'],
                     'note' => 'Per qualsiasi dubbio, scrivi a Francesco.'],
            'en' => ['items' => ['People live in the alley: please keep your voice down on the stairs in the evening.', 'Pets are welcome, but not on the sofa or the beds.', 'No high heels on the terracotta floor: there are slippers.'],
                     'note' => 'If in doubt, message Francesco.'],
        ], 'Regole della casa');

        // -------- Come arrivare: una scheda per mezzo
        $arrivo = Properties::addSection($acc, $pid, 'arrival');
        self::scrivi($pid, $arrivo, [
            'it' => ['address' => 'Vicolo dei Gerani 3, Assisi (a due passi da Piazza Matteotti)', 'maps_url' => self::CHECCO_MAPS,
                     'routes' => [
                        ['mode' => 'auto', 'steps' => "Dalla superstrada E45/SS75 esci ad Assisi e segui le indicazioni per il centro e Piazza Matteotti.\nIl centro storico è ZTL: non superare i varchi con le telecamere.\nLascia l'auto nel parcheggio di Piazza Matteotti: da lì sono due minuti a piedi."],
                        ['mode' => 'treno', 'steps' => "Scendi alla stazione di Assisi, a Santa Maria degli Angeli, nella pianura.\nL'autobus urbano per il centro parte davanti alla stazione: scendi in Piazza Matteotti, in circa venti minuti.\nIn taxi sono una decina di minuti."],
                        ['mode' => 'aereo', 'steps' => "Dall'aeroporto dell'Umbria sono circa venti minuti in auto o in taxi.\nSe vuoi, prenota il nostro transfer nei Servizi extra."],
                        ['mode' => 'autobus', 'steps' => "Diversi autobus extraurbani fermano ad Assisi: controlla sul biglietto la fermata più vicina al centro.\nDalla fermata prendi l'autobus urbano per Piazza Matteotti."]],
                     'note' => 'Per qualsiasi difficoltà chiama Francesco: ti viene incontro in piazza.'],
            'en' => ['routes' => [['steps' => "Leave the E45/SS75 expressway at the Assisi exit and follow the signs to the centre and Piazza Matteotti.\nThe old town is a restricted traffic zone: do not drive through the camera gates.\nLeave the car in the Piazza Matteotti car park: the house is two minutes away on foot."],
                                  ['steps' => "Get off at Assisi station, in Santa Maria degli Angeli, down on the plain.\nThe town bus to the centre leaves from outside the station: get off at Piazza Matteotti, about twenty minutes.\nBy taxi it takes about ten minutes."],
                                  ['steps' => "From Umbria airport it is about twenty minutes by car or taxi.\nIf you like, book our transfer in the Extra services."],
                                  ['steps' => "Several regional buses stop in Assisi: check the stop nearest the centre on your ticket.\nFrom there take the town bus to Piazza Matteotti."]],
                     'note' => 'If you have any trouble, call Francesco: he will meet you in the square.'],
        ], 'Come arrivare');

        // -------- Parcheggio: tipi, costi (di esempio), minuti a piedi, ZTL
        $parcheggio = Properties::addSection($acc, $pid, 'parking');
        self::scrivi($pid, $parcheggio, [
            'it' => ['options' => [
                        ['type' => 'pagamento', 'name' => 'Parcheggio di Piazza Matteotti', 'address' => 'Piazza Matteotti, Assisi', 'maps_url' => self::CHECCO_MAPS,
                         'cost_hour' => '2', 'cost_day' => '20', 'cost_note' => 'Prezzi di esempio: controlla le tariffe esposte all\'ingresso.', 'walk_minutes' => '2',
                         'instructions' => "Coperto, sotto la piazza: è il più vicino alla casa.\nSi paga alla cassa automatica prima di riprendere l'auto, anche con la carta."],
                        ['type' => 'garage', 'name' => 'Garage convenzionato', 'address' => 'Porta Perlici, Assisi', 'cost_day' => '15', 'cost_note' => 'Prezzo riservato agli ospiti: mostra il messaggio di Francesco.', 'walk_minutes' => '6',
                         'instructions' => 'Posto coperto e custodito. Utile se resti più giorni e non usi l\'auto.'],
                        ['type' => 'pubblico', 'name' => 'Parcheggio libero fuori dalle mura', 'address' => 'Assisi', 'walk_minutes' => '15',
                         'instructions' => 'Gratuito ma in salita: comodo solo se non hai bagagli pesanti.']],
                     'ztl' => 'Il centro storico di Assisi è ZTL, con le telecamere ai varchi. Si entra solo per scaricare i bagagli, e solo se Francesco comunica la tua targa il giorno prima: scrivigliela su WhatsApp.'],
            'en' => ['options' => [['name' => 'Piazza Matteotti car park', 'cost_note' => 'Example prices: check the rates posted at the entrance.',
                                    'instructions' => "Covered, under the square: the closest to the house.\nPay at the machine before collecting your car, cards accepted."],
                                   ['name' => 'Partner garage', 'cost_note' => 'Special price for our guests: show Francesco\'s message.', 'instructions' => 'Covered and staffed. Handy if you stay several days without using the car.'],
                                   ['name' => 'Free parking outside the walls', 'instructions' => 'Free but uphill: only worth it without heavy luggage.']],
                     'ztl' => 'The old town of Assisi is a restricted traffic zone, with cameras at the gates. You may only drive in to unload luggage, and only if Francesco registers your plate the day before: send it to him on WhatsApp.'],
        ], 'Parcheggio');

        // -------- Muoversi in zona
        $muoversi = Properties::addSection($acc, $pid, 'transport');
        self::scrivi($pid, $muoversi, [
            'it' => ['options' => [
                        ['type' => 'walk', 'name' => 'A piedi', 'note' => "Dalla casa: Duomo di San Rufino 3 minuti, Piazza del Comune 8, Basilica di Santa Chiara 6.\nLa Basilica di San Francesco è a venti minuti, in discesa: al ritorno conviene l'autobus."],
                        ['type' => 'bus', 'name' => 'Autobus urbani', 'where' => 'Fermata in Piazza Matteotti, due minuti a piedi.', 'note' => 'Collegano il centro con la stazione e con Santa Maria degli Angeli. Biglietti in tabaccheria.'],
                        ['type' => 'taxi', 'name' => 'Taxi', 'phone' => '+39 075 000 0002', 'note' => 'In centro si chiamano per telefono: Francesco ti lascia il numero aggiornato.'],
                        ['type' => 'bike_rental', 'name' => 'E-bike tra gli uliveti', 'note' => 'Le prenota Francesco: le consegnano a casa la mattina, per la pianura e la ciclabile verso Spello.'],
                        ['type' => 'car_rental', 'name' => 'Noleggio auto', 'where' => 'Vicino alla stazione, a Santa Maria degli Angeli.', 'note' => 'Comodo per Perugia, Spello e il lago Trasimeno.']]],
            'en' => ['options' => [['name' => 'On foot', 'note' => "From the house: San Rufino cathedral 3 minutes, Piazza del Comune 8, Basilica of Saint Clare 6.\nThe Basilica of Saint Francis is twenty minutes downhill: take the bus back."],
                                   ['name' => 'Town buses', 'where' => 'Stop in Piazza Matteotti, two minutes on foot.', 'note' => 'They link the centre with the station and Santa Maria degli Angeli. Tickets at the tobacconist.'],
                                   ['name' => 'Taxi', 'note' => 'In the centre you call them by phone: Francesco gives you the current number.'],
                                   ['name' => 'E-bikes through the olive groves', 'note' => 'Francesco books them: delivered in the morning, for the plain and the cycle path to Spello.'],
                                   ['name' => 'Car hire', 'where' => 'Near the station, in Santa Maria degli Angeli.', 'note' => 'Handy for Perugia, Spello and Lake Trasimeno.']]],
        ], 'Muoversi in zona');

        // -------- Rifiuti
        $rifiuti = Properties::addSection($acc, $pid, 'waste');
        self::scrivi($pid, $rifiuti, [
            'it' => ['bins' => [
                        ['type' => 'umido', 'days' => [1, 3, 5], 'color' => 'marrone', 'label' => '', 'where' => 'Bidoncino sotto il lavello'],
                        ['type' => 'plastica', 'days' => [2], 'color' => 'giallo', 'label' => 'Lattine insieme alla plastica', 'where' => 'Sgabuzzino'],
                        ['type' => 'carta', 'days' => [4], 'color' => 'blu', 'label' => '', 'where' => 'Sgabuzzino'],
                        ['type' => 'vetro', 'days' => [], 'color' => 'verde', 'label' => 'Quando vuoi', 'where' => 'Campana in fondo al vicolo'],
                        ['type' => 'indifferenziato', 'days' => [6], 'color' => 'grigio', 'label' => '', 'where' => 'Sotto il lavello']],
                     'note' => 'I sacchetti si lasciano davanti alla porta entro le 8 del mattino: passa l\'operatore a piedi.'],
            'en' => ['bins' => [['where' => 'Small bin under the sink'], ['label' => 'Cans go with the plastic', 'where' => 'Storeroom'], ['where' => 'Storeroom'],
                                ['label' => 'Any time', 'where' => 'Bottle bank at the end of the alley'], ['where' => 'Under the sink']],
                     'note' => 'Leave the bags outside the door by 8am: the collector comes on foot.'],
        ], 'Rifiuti e raccolta differenziata');

        // -------- Emergenze
        $emergenze = Properties::addSection($acc, $pid, 'emergency');
        self::scrivi($pid, $emergenze, [
            'it' => ['emergency_number' => '112', 'contacts' => [
                        ['name' => 'Francesco, per la casa', 'phone' => $tel, 'note' => 'Dalle 8 alle 23'],
                        ['name' => 'Guardia medica turistica', 'phone' => '', 'note' => 'Il numero aggiornato è sul foglio appeso in cucina.'],
                        ['name' => 'Farmacia di turno', 'phone' => '', 'note' => 'Il turno è affisso sulla porta di ogni farmacia del centro.'],
                        ['name' => 'Veterinario di turno', 'phone' => '', 'note' => 'Chiedi a Francesco: ti dà il numero di quello aperto.']],
                     'note' => 'Il pronto soccorso è all\'ospedale di Assisi, una decina di minuti in auto.'],
            'en' => ['contacts' => [['name' => 'Francesco, for the house', 'note' => '8am to 11pm'], ['name' => 'Tourist medical service', 'note' => 'The current number is on the sheet in the kitchen.'],
                                    ['name' => 'Duty pharmacy', 'note' => 'The rota is posted on the door of every pharmacy in the centre.'],
                                    ['name' => 'Duty vet', 'note' => 'Ask Francesco for the one that is open.']],
                     'note' => 'The emergency department is at Assisi hospital, about ten minutes by car.'],
        ], 'Emergenze e contatti');

        // -------- Informazioni utili
        $info = Properties::addSection($acc, $pid, 'info');
        self::scrivi($pid, $info, [
            'it' => ['items' => ['Per entrare nelle basiliche servono spalle e ginocchia coperte.', 'Il centro è tutto in salita e in discesa: scarpe comode.',
                                 'Molti negozi chiudono tra le 13 e le 15:30.', 'L\'acqua delle fontanelle è buona da bere.', 'Nei giorni di festa la città si riempie: conviene prenotare i ristoranti.'],
                     'note' => 'Nel quaderno in soggiorno ci sono altri consigli scritti dagli ospiti.'],
            'en' => ['items' => ['To enter the basilicas, shoulders and knees must be covered.', 'The town is all uphill and downhill: comfortable shoes.',
                                 'Many shops close between 1pm and 3:30pm.', 'The water from the public fountains is safe to drink.', 'On feast days the town fills up: book restaurants ahead.'],
                     'note' => 'The notebook in the living room has more tips written by other guests.'],
        ], 'Informazioni utili');

        // -------- Sezione libera: la storia della casa
        $storia = Properties::addSection($acc, $pid, 'custom');
        Db::update('sections', ['media_id' => $foto('checco-soggiorno.jpg', 'Il soggiorno con il divano chiaro, la colonna in legno e la cucina in mattoni')], 'id = :sid', ['sid' => $storia]);
        self::scrivi($pid, $storia, [
            'it' => ['icona' => 'book', 'text' => 'La casa era la bottega del nonno di Francesco, «Checco» per tutto il vicolo. Abbiamo tenuto la colonna in legno e i mattoni della volta: il resto è nuovo, pensato per chi viaggia.',
                     'items' => ['La colonna in legno è quella originale della bottega.', 'Il tavolo della cucina è fatto con le vecchie assi del pavimento.', 'Le foto in corridoio sono del vicolo negli anni Sessanta.']],
            'en' => ['text' => 'The house was the workshop of Francesco\'s grandfather, «Checco» to the whole alley. We kept the wooden column and the brick vault: everything else is new, designed for travellers.',
                     'items' => ['The wooden column is the original one from the workshop.', 'The kitchen table is made from the old floorboards.', 'The photos in the hallway show the alley in the sixties.']],
        ], 'La storia della casa');

        // -------- Eventi (di fantasia, con le date da oggi): uno oggi con la locandina, uno tra pochi giorni, uno più avanti che torna ogni anno,
        // un mercato settimanale, uno «a parole» e uno passato (nel pannello mostra «Ripeti nel …», nella guida non si vede).
        $eventi = Properties::addSection($acc, $pid, 'events');   // gli eventi sono di fantasia, i luoghi veri
        $locandina = $foto('checco-locandina.jpg', 'Locandina di Jazz sotto le volte');
        self::scrivi($pid, $eventi, [
            'it' => ['intro' => 'Quello che succede in città mentre sei qui. Gli eventi sono di esempio, come la casa.', 'events' => [
                        ['name' => 'Jazz sotto le volte', 'cat' => 'music', 'when' => 'day', 'date_from' => $giorno(0), 'time_from' => '21:30', 'place' => 'Piazza Matteotti', 'dist_min' => '2', 'dist_mode' => 'walk',
                         'price_kind' => 'free', 'recommended' => '1', 'poster' => $locandina, 'description' => 'Un trio jazz suona sotto le volte della piazza. Porta un cuscino: si sta seduti sui gradini.'],
                        ['name' => 'Mercatino dell\'artigianato', 'cat' => 'market', 'when' => 'range', 'date_from' => $giorno(3), 'date_to' => $giorno(4), 'time_from' => '10:00', 'time_to' => '19:00',
                         'place' => 'Piazza del Comune', 'dist_min' => '8', 'dist_mode' => 'walk', 'price_kind' => 'free', 'description' => 'Ceramiche, tessuti e legno lavorato a mano dagli artigiani della zona.'],
                        ['name' => 'Festa d\'autunno nel borgo', 'cat' => 'festival', 'when' => 'range', 'date_from' => $giorno(25), 'date_to' => $giorno(27), 'yearly' => '1', 'place' => 'Centro storico',
                         'price_kind' => 'paid', 'price' => '5 €', 'url' => 'https://example.org/festa-autunno', 'description' => 'Tre giorni di bancarelle, musica e piatti della tradizione. Il biglietto vale per tutte le sere.'],
                        ['name' => 'Mercato contadino del sabato', 'cat' => 'market', 'when' => 'weekly', 'days' => [6], 'time_from' => '08:00', 'time_to' => '13:00', 'place' => 'Piazza Matteotti', 'dist_min' => '2', 'dist_mode' => 'walk',
                         'description' => 'Frutta, verdura, formaggi e olio direttamente dai produttori.'],
                        ['name' => 'Mostra-mercato dell\'antiquariato', 'cat' => 'exhibition', 'when' => 'other', 'when_text' => 'L\'ultima domenica del mese', 'place' => 'Piazza Santa Chiara', 'dist_min' => '6', 'dist_mode' => 'walk'],
                        ['name' => 'Rievocazione dei rioni', 'cat' => 'history', 'when' => 'day', 'date_from' => $giorno(-40), 'yearly' => '1', 'place' => 'Piazza del Comune', 'description' => 'Sfilata in costume e sfida tra i rioni della città.']]],
            'en' => ['intro' => 'What is on in town while you are here. The events are examples, like the house.', 'events' => [
                        ['name' => 'Jazz under the vaults', 'description' => 'A jazz trio plays under the vaults of the square. Bring a cushion: you sit on the steps.'],
                        ['name' => 'Craft market', 'description' => 'Ceramics, fabrics and woodwork made by local craftspeople.'],
                        ['name' => 'Autumn festival in the old town', 'description' => 'Three days of stalls, music and traditional food. One ticket covers every evening.'],
                        ['name' => 'Saturday farmers\' market', 'description' => 'Fruit, vegetables, cheese and olive oil straight from the producers.'],
                        ['name' => 'Antiques fair', 'when_text' => 'The last Sunday of the month'],
                        ['name' => 'Pageant of the districts', 'description' => 'Costume parade and contest between the districts of the town.']]],
        ], 'Eventi');

        // -------- I luoghi, se il piano li comprende
        if (Entitlements::can($acc, 'places')) {
            $luoghi = function (int $sid, array $elenco) use ($acc, $pid, $foto) {
                foreach ($elenco as $l) {
                    $plid = Properties::savePlace($acc, $pid, $sid, null, 'it', true, [
                        'name' => $l[0], 'category_choice' => $l[1], 'description' => $l[2], 'address' => $l[3] . ', Assisi',
                        'maps_url' => 'https://maps.google.com/?q=' . rawurlencode($l[3] . ', Assisi'),
                        'walk_minutes' => $l[4], 'drive_minutes' => $l[9] ?? '', 'badge_choice' => $l[5], 'badge_tone' => $l[6] ?? 'sea',
                    ]);
                    if (!empty($l[7])) Db::update('places', ['media_id' => $foto($l[7], $l[8])], 'id = :pid', ['pid' => $plid]);
                }
            };
            $mangiare = Properties::addSection($acc, $pid, 'eat');
            self::scrivi($pid, $mangiare, ['it' => ['intro' => 'Quattro posti dove andiamo noi, tutti a piedi da casa.', 'host_note' => 'Alla trattoria chiedi gli strangozzi al tartufo: non sono sempre sul menù.'],
                                           'en' => ['intro' => 'Four places we go to ourselves, all walkable from the house.', 'host_note' => 'At the trattoria, ask for strangozzi with truffle: they are not always on the menu.']], 'Dove mangiare e bere');
            $luoghi($mangiare, [
                ['Trattoria del Vicolo Stretto', 'trattoria', 'Strangozzi fatti a mano e carne alla brace, in una sala con le volte in pietra.', 'Vicolo dei Gerani 9', 3, 'dinner', 'pine', 'checco-trattoria.jpg', 'Tavoli apparecchiati in un vicolo'],
                ['Caffè della Fontanella', 'breakfast', 'Cornetti caldi e cappuccino, con i tavolini all\'aperto.', 'Via dei Gerani 2', 2, 'breakfast', 'sea', 'checco-bar.jpg', 'Una tazzina di espresso sul bancone'],
                ['Gelateria dei Pellegrini', 'gelato', 'Prova il gusto al miele del Subasio e quello all\'olio d\'oliva.', 'Via dei Gerani 12', 5, 'host_pick', 'ochre', 'checco-gelato.jpg', 'Vaschette di gelato colorate'],
                ['Enoteca Tre Calici', 'wine_bar', 'Vini umbri al bicchiere e taglieri, aperta fino a mezzanotte.', 'Via dei Gerani 15', 4, 'typical', 'pine'],
            ]);
            $visitare = Properties::addSection($acc, $pid, 'visit');
            self::scrivi($pid, $visitare, ['it' => ['intro' => 'Le cose da non perdere, in ordine di distanza da casa.', 'host_note' => 'Sali alla Rocca Maggiore al tramonto: si vede tutta la valle fino a Perugia.'],
                                           'en' => ['intro' => 'The things not to miss, in order of distance from the house.', 'host_note' => 'Go up to the Rocca Maggiore at sunset: you can see the whole valley as far as Perugia.']], 'Cosa visitare');
            $luoghi($visitare, [
                ['Duomo di San Rufino', 'church', 'La cattedrale di Assisi, con la facciata romanica: a tre minuti dalla casa.', 'Piazza San Rufino', 3, 'rainy', 'sea'],
                ['Basilica di Santa Chiara', 'church', 'La basilica in pietra bianca e rosa del Subasio, con la terrazza sulla valle.', 'Piazza Santa Chiara', 6, 'quiet', 'pine'],
                ['Piazza del Comune e Tempio di Minerva', 'square', 'Il cuore della città, con le colonne del tempio romano.', 'Piazza del Comune', 8, 'free', 'ochre'],
                ['Rocca Maggiore', 'castle', 'La fortezza in cima alla città, con il panorama su tutta la valle.', 'Via della Rocca', 12, 'view', 'terracotta'],
                ['Basilica di San Francesco', 'church', 'La basilica con gli affreschi di Giotto: conviene andarci la mattina presto.', 'Piazza Inferiore di San Francesco', 20, 'must_see', 'terracotta'],
            ]);
            $fare = Properties::addSection($acc, $pid, 'todo');
            self::scrivi($pid, $fare, ['it' => ['intro' => 'Per riempire le giornate, in città e intorno.'], 'en' => ['intro' => 'To fill your days, in town and around.']], 'Cosa fare');
            $luoghi($fare, [
                ['Visita guidata della città medievale', 'tour', 'Due ore tra vicoli, chiese e piazze, con una guida.', 'Piazza Matteotti', 2, 'booking', 'sea'],
                ['Lezione di cucina umbra', 'cooking', 'Tre ore in cucina per imparare strangozzi e torta al testo, e poi mangiarli.', 'Vicolo dei Gerani 9', 3, 'family', 'terracotta'],
                ['Passeggiata sul Monte Subasio', 'hike', 'Sentieri tra boschi e prati sopra la città: si parte a piedi da Porta Perlici.', 'Porta Perlici', 6, 'half_day', 'pine'],
                ['Giro in e-bike tra gli uliveti', 'bike', 'Pista ciclabile in pianura verso Spello, tra uliveti e vigne.', 'Santa Maria degli Angeli', 0, 'full_day', 'pine', null, null, 15],
            ]);
            $negozi = Properties::addSection($acc, $pid, 'shop');
            self::scrivi($pid, $negozi, ['it' => ['intro' => 'Per la spesa basta scendere di un vicolo.'], 'en' => ['intro' => 'For groceries, just walk down one alley.']], 'Negozi e spesa');
            $luoghi($negozi, [
                ['Bottega di Rosa', 'grocery', 'Salumi, formaggi, pane e il necessario per la colazione.', 'Vicolo dei Gerani 1', 1, 'local'],
                ['Forno del Subasio', 'bakery', 'Torta al testo calda dalle 11 e rocciata, il dolce di Assisi.', 'Via dei Gerani 6', 3, 'on_foot'],
                ['Farmacia del centro', 'pharmacy', 'La più vicina a casa.', 'Via dei Gerani 20', 5, 'late'],
            ]);
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

        // Eventi (6G): date calcolate dal giorno in cui si crea la demo; nomi generici, di fantasia.
        $giorno = fn(int $n) => (new \DateTimeImmutable(Eventi::oggi()))->modify(($n >= 0 ? '+' : '') . $n . ' days')->format('Y-m-d');
        $eventi = Properties::addSection($acc, $pid, 'events');
        self::scrivi($pid, $eventi, [
            'it' => ['intro' => 'Quello che succede in paese mentre sei qui.', 'events' => [
                        ['name' => 'Concerto in piazza', 'cat' => 'music', 'when' => 'day', 'date_from' => $giorno(0), 'time_from' => '21:00', 'place' => 'Piazza della Repubblica', 'dist_min' => '4', 'dist_mode' => 'walk', 'price_kind' => 'free'],
                        ['name' => 'Festa delle infiorate', 'cat' => 'festival', 'when' => 'range', 'date_from' => $giorno(5), 'date_to' => $giorno(6), 'yearly' => '1', 'place' => 'Centro storico', 'price_kind' => 'free', 'recommended' => '1',
                         'description' => 'Le vie del centro si coprono di disegni fatti con i petali. Il momento migliore è all\'alba.'],
                        ['name' => 'Mercato del sabato', 'cat' => 'market', 'when' => 'weekly', 'days' => [6], 'time_from' => '08:00', 'time_to' => '13:00', 'place' => 'Piazza Kennedy', 'dist_min' => '6', 'dist_mode' => 'walk']]],
            'en' => ['intro' => 'What is on in the village while you are here.', 'events' => [
                        ['name' => 'Concert in the square'], ['name' => 'Flower carpet festival', 'description' => 'The streets of the old town are covered in designs made of petals. The best time is at dawn.'],
                        ['name' => 'Saturday market']]],
        ], '');

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
