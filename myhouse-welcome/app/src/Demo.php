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
     * a Piazza Matteotti. Sulla falsariga di Casa Lucia: la casa, il vicolo, i padroni di casa,
     * i telefoni e i locali sono di fantasia (la guida mostra «Demo»); la città è vera, con
     * monumenti, musei, parcheggi, autobus, imposta di soggiorno ed eventi ricorrenti di Assisi.
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
        // Piazza Matteotti. La posizione (link di Maps e coordinate) è quella della piazza, presa
        // da OpenStreetMap, non di un portone vero. Di fantasia anche i telefoni (075 000 …),
        // i servizi della casa e i locali di «Dove mangiare» e «Negozi»: non si usano nomi di
        // locali veri. È vero tutto il resto, preso dalle fonti pubbliche (2026): parcheggi e
        // tariffe, linea C dell'autobus, imposta di soggiorno, monumenti e musei con orari e
        // prezzi, sentieri, e gli eventi ricorrenti della città con la prossima data da oggi.
        // Gli orari cambiano con le stagioni: la guida invita a controllarli.
        $acc = $accountId;
        $tel = '+39 075 000 0000';
        $pid = Properties::create($acc, 'Casa Checco', 'Assisi', 'Francesco', 1);
        Db::update('properties', [
            'region' => 'Umbria', 'address' => 'Vicolo dei Gerani 3', 'postal_code' => '06081',
            'checkin_from' => '15:00', 'checkout_by' => '10:30', 'host_phone' => $tel, 'host_whatsapp' => $tel, 'palette' => 'terracotta',
            'is_demo' => self::VETRINA, 'wizard_step' => 'fatto', 'property_type' => 'appartamento',
        ], 'id = :pid', ['pid' => $pid]);
        // I dati della scheda: CIN e posti letto di esempio (il CIN dice «DEMO»: non è un codice vero),
        // i link per le recensioni (le pagine di Assisi delle piattaforme, la casa non esiste) e il codice
        // sconto per la prossima volta. Niente link a un sito per prenotare: sarebbe di qualcun altro.
        $campiCasa = ['cin' => 'IT054001C2DEMO0001', 'beds' => 4,
                      'review_google' => 'https://www.google.com/maps/search/?api=1&query=Piazza+Matteotti+Assisi',
                      'review_booking' => 'https://www.booking.com/city/it/assisi.it.html',
                      'review_airbnb' => 'https://www.airbnb.it/assisi-italia/stays', 'direct_code' => 'CHECCO10'];
        $campiCasa = array_filter($campiCasa, fn($c) => Migrator::columnExists('properties', $c), ARRAY_FILTER_USE_KEY);
        if ($campiCasa) Db::update('properties', $campiCasa, 'id = :pid', ['pid' => $pid]);
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
                     'tax_amount' => '4 € a persona per notte', 'tax_max_nights' => '3',
                     'tax_notes' => 'È l\'imposta di soggiorno del Comune di Assisi per le locazioni turistiche (tariffe 2026): 3 € a notte se il soggiorno costa fino a 60,99 € a notte, 4 € fino a 100,99 €, 6 € oltre. Si paga solo per le prime 3 notti di fila; esenti i bambini sotto i 12 anni. Si paga in contanti all\'arrivo: ti lasciamo la ricevuta.',
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
                     'tax_notes' => 'This is the Assisi town council tourist tax for holiday rentals (2026 rates): €3 a night if your stay costs up to €60.99 a night, €4 up to €100.99, €6 above that. Only the first 3 consecutive nights are charged; children under 12 are exempt. Paid in cash on arrival: we leave you a receipt.',
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
                                   ['title' => 'La macchina del caffè', 'steps' => "Riempi il serbatoio d'acqua sul retro.\nAccendi: quando la luce smette di lampeggiare è pronta.\nMetti la capsula, chiudi la leva e premi la tazzina piccola o grande.\nLe capsule usate si buttano nell'indifferenziato."]],
                     'note' => "Lenzuola e asciugamani si cambiano ogni tre notti, o prima se lo chiedi.\nRiscaldamento e aria condizionata hanno la loro sezione, con gli orari."],
            'en' => ['items' => ['Capsule coffee machine, first capsules included', 'Umbrellas by the door', 'Adapters for foreign plugs'],
                     'manuals' => [['title' => 'The washing machine', 'steps' => "Close the door until it clicks.\nDetergent in the left drawer.\nTurn the dial to «Cotton 40°» and press Start.\nThe cycle takes an hour and a half: the drying rack is on the terrace."],
                                   ['title' => 'The coffee machine', 'steps' => "Fill the water tank at the back.\nSwitch it on: it is ready when the light stops blinking.\nInsert a capsule, close the lever and press the small or large cup.\nUsed capsules go in the general waste."]],
                     'note' => "Sheets and towels are changed every three nights, or sooner if you ask.\nHeating and air conditioning have their own section, with the times."],
        ], 'Servizi');

        // -------- Riscaldamento e aria condizionata: il riscaldamento va da solo, il condizionatore lo accende l'ospite
        $clima = Properties::addSection($acc, $pid, 'clima');
        self::scrivi($pid, $clima, [
            'it' => ['heating_mode' => 'auto',
                     'heating_times' => [['from' => '06:30', 'to' => '09:30', 'days' => []], ['from' => '17:00', 'to' => '22:30', 'days' => []]],
                     'heating_temp' => '20', 'heating_period' => 'Da metà ottobre a metà aprile, nei giorni freddi.',
                     'heating_how' => ['La caldaia è nell\'armadio del bagno: non serve toccarla.',
                                       'Se senti freddo, apri del tutto la manopola del termosifone (numero 5).',
                                       'Se fa troppo caldo, girala verso 2 invece di aprire la finestra.'],
                     'ac_mode' => 'libero', 'ac_temp' => '26',
                     'ac_how' => ['Il telecomando è sul comodino della camera.', 'Premi ON, poi il fiocco di neve per raffreddare.', 'Chiudi le finestre e spegnilo quando esci.'],
                     'risparmio' => '1',
                     'note' => 'Le mura in pietra tengono il fresco d\'estate e il caldo d\'inverno: di solito bastano poche ore di condizionatore.'],
            'en' => ['heating_period' => 'From mid October to mid April, on cold days.',
                     'heating_how' => ['The boiler is in the bathroom cupboard: no need to touch it.',
                                       'If you feel cold, open the radiator knob all the way (number 5).',
                                       'If it is too warm, turn it down to 2 instead of opening the window.'],
                     'ac_how' => ['The remote is on the bedroom bedside table.', 'Press ON, then the snowflake to cool.', 'Close the windows and switch it off when you go out.'],
                     'note' => 'The stone walls keep the house cool in summer and warm in winter: a few hours of air conditioning are usually enough.'],
        ], 'Riscaldamento e aria condizionata');

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
                        ['mode' => 'auto', 'steps' => "Dalla superstrada SS75 (Perugia–Foligno) esci ad Assisi e segui le indicazioni per il centro e poi per il parcheggio di Piazza Matteotti, nella parte alta della città.\nIl centro storico è ZTL: non superare i varchi con le telecamere.\nLascia l'auto nel parcheggio di Piazza Matteotti (2 € l'ora, 14 € al giorno): da lì sono due minuti a piedi."],
                        ['mode' => 'treno', 'steps' => "Scendi alla stazione di Assisi, a Santa Maria degli Angeli, nella pianura.\nDavanti alla stazione prendi l'autobus della linea C di Busitalia: sale al centro storico e ha il capolinea proprio in Piazza Matteotti.\nIl biglietto costa 1,30 € in tabaccheria (anche al bar della stazione) o 1,50 € a bordo.\nIn taxi sono una decina di minuti."],
                        ['mode' => 'aereo', 'steps' => "L'aeroporto più vicino è quello dell'Umbria, Perugia «San Francesco d'Assisi», a circa venti minuti in auto o in taxi.\nDa Roma Fiumicino conviene il treno per Assisi, cambiando a Roma Termini (e a volte anche a Foligno).\nSe vuoi, prenota il nostro transfer nei Servizi extra."],
                        ['mode' => 'autobus', 'steps' => "Gli autobus da Perugia arrivano alla stazione di Assisi o in centro: controlla la fermata sul biglietto.\nDalla stazione prendi la linea C fino al capolinea di Piazza Matteotti."]],
                     'note' => 'Per qualsiasi difficoltà chiama Francesco: ti viene incontro in piazza.'],
            'en' => ['routes' => [['steps' => "Leave the SS75 expressway (Perugia–Foligno) at the Assisi exit and follow the signs to the centre, then to the Piazza Matteotti car park, in the upper part of town.\nThe old town is a restricted traffic zone: do not drive through the camera gates.\nLeave the car in the Piazza Matteotti car park (€2 an hour, €14 a day): the house is two minutes away on foot."],
                                  ['steps' => "Get off at Assisi station, in Santa Maria degli Angeli, down on the plain.\nOutside the station take Busitalia line C: it climbs to the old town and terminates right in Piazza Matteotti.\nTickets cost €1.30 at the tobacconist (also at the station bar) or €1.50 on board.\nBy taxi it takes about ten minutes."],
                                  ['steps' => "The nearest airport is Umbria's, Perugia «San Francesco d'Assisi», about twenty minutes by car or taxi.\nFrom Rome Fiumicino take the train to Assisi, changing at Roma Termini (and sometimes at Foligno too).\nIf you like, book our transfer in the Extra services."],
                                  ['steps' => "Buses from Perugia stop at Assisi station or in the centre: check the stop on your ticket.\nFrom the station take line C to the last stop, Piazza Matteotti."]],
                     'note' => 'If you have any trouble, call Francesco: he will meet you in the square.'],
        ], 'Come arrivare');

        // -------- Parcheggio: i parcheggi comunali veri, con le tariffe 2026 (2 € l'ora, 14 € al giorno)
        $parcheggio = Properties::addSection($acc, $pid, 'parking');
        self::scrivi($pid, $parcheggio, [
            'it' => ['options' => [
                        ['type' => 'pagamento', 'name' => 'Parcheggio di Piazza Matteotti', 'address' => 'Piazza Matteotti, Assisi', 'maps_url' => self::CHECCO_MAPS,
                         'cost_hour' => '2', 'cost_day' => '14', 'cost_note' => 'Tariffe 2026: controlla quelle esposte all\'ingresso, possono cambiare.', 'walk_minutes' => '2',
                         'instructions' => "Sotto la piazza, 390 posti: è il più vicino alla casa, accanto al Duomo di San Rufino e all'anfiteatro romano.\nSi paga alla cassa automatica prima di riprendere l'auto, anche con la carta."],
                        ['type' => 'pagamento', 'name' => 'Parcheggio Mojano', 'address' => 'Viale Vittorio Emanuele II, Assisi', 'cost_hour' => '2', 'cost_day' => '14', 'walk_minutes' => '12',
                         'instructions' => "Se il Matteotti è pieno. Coperto, su tre piani: le scale mobili salgono fino a Piazza Santa Chiara, e da lì alla casa sono otto minuti in salita."],
                        ['type' => 'pagamento', 'name' => 'Parcheggio Porta Nuova', 'address' => 'Piazza Porta Nuova, Assisi', 'cost_hour' => '2', 'cost_day' => '14', 'walk_minutes' => '12',
                         'instructions' => 'Aperto giorno e notte, appena fuori dalla porta: si entra in città accanto a Santa Chiara.'],
                        ['type' => 'pubblico', 'name' => 'Parcheggio gratuito di San Giacomo', 'address' => 'Via Egidio Albornoz, Assisi', 'walk_minutes' => '30',
                         'instructions' => 'Gratuito, di fronte al cimitero, ma dalla parte opposta della città, vicino alla Basilica di San Francesco: alla casa sono una trentina di minuti a piedi, quasi tutti in salita. Conviene solo senza bagagli.']],
                     'ztl' => 'Il centro storico di Assisi è ZTL, con le telecamere ai varchi. Si entra solo per scaricare i bagagli, al massimo per 60 minuti, e con il permesso sul cruscotto: Francesco lo chiede per te alla Polizia Locale se gli scrivi la targa su WhatsApp il giorno prima.'],
            'en' => ['options' => [['name' => 'Piazza Matteotti car park', 'cost_note' => '2026 rates: check the ones posted at the entrance, they may change.',
                                    'instructions' => "Under the square, 390 spaces: the closest to the house, next to San Rufino cathedral and the Roman amphitheatre.\nPay at the machine before collecting your car, cards accepted."],
                                   ['name' => 'Mojano car park', 'instructions' => 'If Matteotti is full. Covered, on three floors: the escalators go up to Piazza Santa Chiara, and from there the house is eight minutes uphill.'],
                                   ['name' => 'Porta Nuova car park', 'instructions' => 'Open day and night, just outside the gate: you walk into town next to Santa Chiara.'],
                                   ['name' => 'San Giacomo free car park', 'instructions' => 'Free, opposite the cemetery, but on the other side of town, near the Basilica of Saint Francis: the house is about thirty minutes on foot, mostly uphill. Only worth it without luggage.']],
                     'ztl' => 'The old town of Assisi is a restricted traffic zone, with cameras at the gates. You may only drive in to unload luggage, for 60 minutes at most, with the permit on the dashboard: Francesco requests it from the local police if you send him your plate on WhatsApp the day before.'],
        ], 'Parcheggio');

        // -------- Muoversi in zona
        $muoversi = Properties::addSection($acc, $pid, 'transport');
        self::scrivi($pid, $muoversi, [
            'it' => ['options' => [
                        ['type' => 'walk', 'name' => 'A piedi', 'note' => "Dalla casa: Duomo di San Rufino 3 minuti, Basilica di Santa Chiara 8, Piazza del Comune 8, Rocca Maggiore 12.\nLa Basilica di San Francesco è a venticinque minuti, in discesa: al ritorno conviene l'autobus."],
                        ['type' => 'bus', 'name' => 'Linea C Busitalia', 'where' => 'Capolinea in Piazza Matteotti, due minuti a piedi.', 'url' => 'https://www.fsbusitalia.it',
                         'note' => "Scende a San Francesco, a Santa Maria degli Angeli e alla stazione, e torna su fino a Piazza Matteotti.\nBiglietto 1,30 € in tabaccheria, 1,50 € a bordo. Gli orari cambiano tra feriali e festivi: controllali sul sito."],
                        ['type' => 'lifts', 'name' => 'Scale mobili di Mojano', 'where' => 'Dal parcheggio Mojano a Piazza Santa Chiara.', 'note' => 'Utili per risalire senza fatica dalla parte bassa della città.'],
                        ['type' => 'taxi', 'name' => 'Radio Taxi Assisi', 'phone' => '+39 075 813100', 'note' => 'In centro i taxi si chiamano per telefono.'],
                        ['type' => 'bike_rental', 'name' => 'E-bike per la pianura', 'note' => 'Le prenota Francesco a Santa Maria degli Angeli: in pianura c\'è la ciclovia verso Spello e Foligno.'],
                        ['type' => 'car_rental', 'name' => 'Noleggio auto', 'where' => 'A Santa Maria degli Angeli, vicino alla stazione.', 'note' => 'Comodo per Spello, Perugia, Gubbio e il lago Trasimeno.']]],
            'en' => ['options' => [['name' => 'On foot', 'note' => "From the house: San Rufino cathedral 3 minutes, Basilica of Saint Clare 8, Piazza del Comune 8, Rocca Maggiore 12.\nThe Basilica of Saint Francis is twenty-five minutes downhill: take the bus back."],
                                   ['name' => 'Busitalia line C', 'where' => 'Last stop in Piazza Matteotti, two minutes on foot.',
                                    'note' => "It goes down to San Francesco, Santa Maria degli Angeli and the station, and back up to Piazza Matteotti.\nTickets €1.30 at the tobacconist, €1.50 on board. Timetables differ on weekdays and holidays: check them online."],
                                   ['name' => 'Mojano escalators', 'where' => 'From the Mojano car park to Piazza Santa Chiara.', 'note' => 'Handy for getting back up from the lower part of town without effort.'],
                                   ['name' => 'Radio Taxi Assisi', 'note' => 'In the centre taxis are called by phone.'],
                                   ['name' => 'E-bikes for the plain', 'note' => 'Francesco books them in Santa Maria degli Angeli: on the plain there is the cycle route to Spello and Foligno.'],
                                   ['name' => 'Car hire', 'where' => 'In Santa Maria degli Angeli, near the station.', 'note' => 'Handy for Spello, Perugia, Gubbio and Lake Trasimeno.']]],
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

        // -------- Eventi: gli appuntamenti veri e ricorrenti di Assisi, con la prossima data da oggi
        // (se quest'anno sono già passati, quelli dell'anno dopo). La locandina è quella che la
        // casa ha preparato per il mercato del sabato.
        $oggi = Eventi::oggi();
        $prossimo = function (string $da, string $a) use ($oggi): array {
            $y = (int) substr($oggi, 0, 4);
            if ("$y-$a" < $oggi) $y++;
            return ["$y-$da", "$y-$a"];
        };
        // Calendimaggio: dal primo mercoledì di maggio al sabato (6–9 maggio 2026, 5–8 maggio 2027).
        $calendimaggio = function (int $y): array {
            $mer = (new \DateTimeImmutable("$y-05-01"))->modify('-1 day')->modify('next wednesday');
            return [$mer->format('Y-m-d'), $mer->modify('+3 days')->format('Y-m-d')];
        };
        $cm = $calendimaggio((int) substr($oggi, 0, 4));
        if ($cm[1] < $oggi) $cm = $calendimaggio((int) substr($oggi, 0, 4) + 1);
        [$perdono, $santaChiara, $sanRufino, $sanFrancesco] = [$prossimo('08-01', '08-02'), $prossimo('08-11', '08-11'), $prossimo('08-11', '08-12'), $prossimo('10-03', '10-04')];
        $eventi = Properties::addSection($acc, $pid, 'events');
        $locandina = $foto('checco-locandina.jpg', 'Locandina del mercato del sabato in Piazza Matteotti');
        self::scrivi($pid, $eventi, [
            'it' => ['intro' => 'Gli appuntamenti di Assisi che tornano ogni anno, e il mercato sotto casa. Le date esatte e i programmi escono di anno in anno: controllali sui siti prima di partire.', 'events' => [
                        ['name' => 'Mercato del sabato', 'cat' => 'market', 'when' => 'weekly', 'days' => [6], 'time_from' => '08:00', 'time_to' => '13:00', 'place' => 'Piazza Matteotti', 'dist_min' => '2', 'dist_mode' => 'walk',
                         'price_kind' => 'free', 'recommended' => '1', 'poster' => $locandina, 'description' => 'Il mercato settimanale della città alta: frutta, verdura, formaggi, olio e banchi di ogni genere. Si arriva in due minuti, senza prendere l\'auto.'],
                        ['name' => 'Festa di San Francesco', 'cat' => 'religious', 'when' => 'range', 'date_from' => $sanFrancesco[0], 'date_to' => $sanFrancesco[1], 'yearly' => '1', 'place' => 'Basilica di San Francesco', 'dist_min' => '25', 'dist_mode' => 'walk',
                         'price_kind' => 'free', 'url' => 'https://www.sanfrancescoassisi.org', 'description' => 'La festa del patrono d\'Italia: il 3 ottobre la sera del Transito, il 4 la messa solenne e l\'accensione della lampada votiva da parte di una regione italiana. Dal 2026 il 4 ottobre è di nuovo festa nazionale: la città si riempie, prenota tutto per tempo.'],
                        ['name' => 'Calendimaggio', 'cat' => 'history', 'when' => 'range', 'date_from' => $cm[0], 'date_to' => $cm[1], 'yearly' => '1', 'place' => 'Piazza del Comune e centro storico', 'dist_min' => '8', 'dist_mode' => 'walk',
                         'url' => 'https://www.calendimaggiodiassisi.com', 'description' => 'La festa di primavera: per quattro giorni la Nobilissima Parte de Sopra e la Magnifica Parte de Sotto si sfidano con cortei, scene di vita medievale e canti. Casa Checco è nella Parte de Sopra. Alcuni spettacoli in piazza sono a pagamento.'],
                        ['name' => 'Festa del Perdono', 'cat' => 'religious', 'when' => 'range', 'date_from' => $perdono[0], 'date_to' => $perdono[1], 'yearly' => '1', 'place' => 'Porziuncola, Santa Maria degli Angeli', 'dist_min' => '15', 'dist_mode' => 'car',
                         'price_kind' => 'free', 'description' => 'Il Perdono di Assisi, voluto da San Francesco: dal mezzogiorno del 1° agosto alla sera del 2 i pellegrini arrivano alla Porziuncola, dentro la Basilica di Santa Maria degli Angeli. Si scende con la linea C.'],
                        ['name' => 'Solennità di Santa Chiara', 'cat' => 'religious', 'when' => 'day', 'date_from' => $santaChiara[0], 'yearly' => '1', 'place' => 'Basilica di Santa Chiara', 'dist_min' => '8', 'dist_mode' => 'walk',
                         'price_kind' => 'free', 'description' => 'La festa di Santa Chiara, nella basilica dove riposa: messe durante tutta la giornata.'],
                        ['name' => 'Festa di San Rufino, patrono di Assisi', 'cat' => 'religious', 'when' => 'range', 'date_from' => $sanRufino[0], 'date_to' => $sanRufino[1], 'yearly' => '1', 'time_from' => '21:00', 'place' => 'Cattedrale di San Rufino', 'dist_min' => '3', 'dist_mode' => 'walk',
                         'price_kind' => 'free', 'recommended' => '1', 'description' => 'La sera dell\'11 agosto veglia in cattedrale e processione per le vie della città fino a Piazza del Comune, con la benedizione della città; il 12 il pontificale e, la sera, il concerto in onore del patrono. La cattedrale è a tre minuti da casa.']]],
            'en' => ['intro' => 'The Assisi events that come back every year, and the market by the house. Exact dates and programmes are announced each year: check the websites before you travel.', 'events' => [
                        ['name' => 'Saturday market', 'description' => 'The weekly market of the upper town: fruit, vegetables, cheese, olive oil and all sorts of stalls. Two minutes away, no need for the car.'],
                        ['name' => 'Feast of Saint Francis', 'description' => 'The feast of Italy\'s patron saint: on 3 October the evening of the Transitus, on the 4th the solemn mass and the lighting of the votive lamp by one of the Italian regions. Since 2026, 4 October is a national holiday again: the town fills up, book everything early.'],
                        ['name' => 'Calendimaggio', 'description' => 'The spring festival: for four days the Nobilissima Parte de Sopra and the Magnifica Parte de Sotto compete with parades, scenes of medieval life and songs. Casa Checco is in the Parte de Sopra. Some shows in the square are ticketed.'],
                        ['name' => 'Feast of the Pardon', 'description' => 'The Pardon of Assisi, wanted by Saint Francis: from midday on 1 August to the evening of the 2nd pilgrims come to the Porziuncola, inside the Basilica of Santa Maria degli Angeli. Take line C down.'],
                        ['name' => 'Feast of Saint Clare', 'description' => 'The feast of Saint Clare, in the basilica where she rests: masses throughout the day.'],
                        ['name' => 'Feast of San Rufino, patron of Assisi', 'description' => 'On the evening of 11 August a vigil in the cathedral and a procession through the streets to Piazza del Comune, with the blessing of the town; on the 12th the solemn mass and, in the evening, a concert for the patron saint. The cathedral is three minutes from the house.']]],
        ], 'Eventi');

        // -------- I luoghi, se il piano li comprende. «Cosa visitare» e «Cosa fare» sono luoghi veri,
        // con orari e prezzi 2026 delle fonti ufficiali; «Dove mangiare» e «Negozi» sono di fantasia
        // (niente nomi di locali veri), nel vicolo di fantasia della casa.
        if (Entitlements::can($acc, 'places')) {
            // [nome, categoria, descrizione, indirizzo, minuti a piedi, etichetta, tono, foto, alt, minuti in auto, altro]
            // altro: web, tel, nota, en => [description, note]
            $luoghi = function (int $sid, array $elenco) use ($acc, $pid, $foto) {
                foreach ($elenco as $l) {
                    $x = $l[10] ?? [];
                    $plid = Properties::savePlace($acc, $pid, $sid, null, 'it', true, [
                        'name' => $l[0], 'category_choice' => $l[1], 'description' => $l[2], 'address' => $l[3] . ', Assisi',
                        'maps_url' => 'https://maps.google.com/?q=' . rawurlencode($l[3] . ', Assisi'),
                        'walk_minutes' => $l[4], 'drive_minutes' => $l[9] ?? '', 'badge_choice' => $l[5], 'badge_tone' => $l[6] ?? 'sea',
                        'website' => $x['web'] ?? '', 'phone' => $x['tel'] ?? '', 'note' => $x['nota'] ?? '',
                    ]);
                    if (!empty($x['en'])) Properties::savePlace($acc, $pid, $sid, $plid, 'en', false, ['description' => $x['en'][0], 'note' => $x['en'][1] ?? '']);
                    if (!empty($l[7])) Db::update('places', ['media_id' => $foto($l[7], $l[8])], 'id = :pid', ['pid' => $plid]);
                }
            };
            $mangiare = Properties::addSection($acc, $pid, 'eat');
            self::scrivi($pid, $mangiare, ['it' => ['intro' => 'Quattro posti dove andiamo noi, tutti a piedi da casa. Da provare: strangozzi al tartufo, torta al testo e, per dolce, la rocciata.', 'host_note' => 'Alla trattoria chiedi gli strangozzi al tartufo: non sono sempre sul menù.'],
                                           'en' => ['intro' => 'Four places we go to ourselves, all walkable from the house. Try strangozzi with truffle, torta al testo and, for dessert, rocciata.', 'host_note' => 'At the trattoria, ask for strangozzi with truffle: they are not always on the menu.']], 'Dove mangiare e bere');
            $luoghi($mangiare, [
                ['Trattoria del Vicolo Stretto', 'trattoria', 'Strangozzi fatti a mano e carne alla brace, in una sala con le volte in pietra.', 'Vicolo dei Gerani 9', 3, 'dinner', 'pine', 'checco-trattoria.jpg', 'Tavoli apparecchiati in un vicolo', null,
                 ['nota' => 'Chiusa il martedì. La sera conviene prenotare.', 'en' => ['Handmade strangozzi and grilled meat, in a stone-vaulted dining room.', 'Closed on Tuesdays. Book for dinner.']]],
                ['Caffè della Fontanella', 'breakfast', 'Cornetti caldi e cappuccino, con i tavolini all\'aperto.', 'Vicolo dei Gerani 2', 2, 'breakfast', 'sea', 'checco-bar.jpg', 'Una tazzina di espresso sul bancone', null,
                 ['nota' => 'Dalle 7 alle 13.', 'en' => ['Warm croissants and cappuccino, with tables outside.', '7am to 1pm.']]],
                ['Gelateria dei Pellegrini', 'gelato', 'Prova il gusto al miele del Subasio e quello all\'olio d\'oliva.', 'Vicolo dei Gerani 12', 5, 'host_pick', 'ochre', 'checco-gelato.jpg', 'Vaschette di gelato colorate', null,
                 ['en' => ['Try the Subasio honey and the olive oil flavours.']]],
                ['Enoteca Tre Calici', 'wine_bar', 'Vini umbri al bicchiere, dal Sagrantino di Montefalco al Grechetto, con taglieri di salumi e formaggi.', 'Vicolo dei Gerani 15', 4, 'typical', 'pine', null, null, null,
                 ['nota' => 'Aperta fino a mezzanotte.', 'en' => ['Umbrian wines by the glass, from Montefalco Sagrantino to Grechetto, with boards of cured meats and cheese.', 'Open until midnight.']]],
            ]);
            $visitare = Properties::addSection($acc, $pid, 'visit');
            self::scrivi($pid, $visitare, ['it' => ['intro' => 'Le cose da non perdere, in ordine di distanza da casa. Orari e prezzi sono quelli del 2026: cambiano con le stagioni, controllali prima di andare. Con il biglietto cumulativo (10 €) entri alla Rocca Maggiore, al Foro Romano e alla Pinacoteca.', 'host_note' => 'Sali alla Rocca Maggiore al tramonto: si vede tutta la valle fino a Perugia.'],
                                           'en' => ['intro' => 'The things not to miss, in order of distance from the house. Opening times and prices are for 2026: they change with the seasons, check before you go. The combined ticket (€10) covers the Rocca Maggiore, the Roman Forum and the Pinacoteca.', 'host_note' => 'Go up to the Rocca Maggiore at sunset: you can see the whole valley as far as Perugia.']], 'Cosa visitare');
            $luoghi($visitare, [
                ['Anfiteatro romano', 'archaeology', 'Non c\'è un\'arena da visitare: le case medievali sono state costruite sopra le gradinate, e la loro curva disegna ancora l\'ovale dell\'anfiteatro. Si vede bene passeggiando tra Piazza Matteotti e Porta Perlici.', 'Via Porta Perlici', 3, 'free', 'ochre', null, null, null,
                 ['web' => 'https://www.visit-assisi.it', 'en' => ['There is no arena to visit: medieval houses were built on the tiers, and their curve still traces the oval of the amphitheatre. Best seen walking between Piazza Matteotti and Porta Perlici.']]],
                ['Cattedrale di San Rufino', 'church', 'Il duomo di Assisi, con la facciata romanica e i tre rosoni. Dentro c\'è il fonte dove furono battezzati San Francesco e Santa Chiara.', 'Piazza San Rufino', 3, 'free', 'sea', null, null, null,
                 ['web' => 'https://www.assisimuseodiocesano.it', 'en' => ['The cathedral of Assisi, with its Romanesque façade and three rose windows. Inside is the font where Saint Francis and Saint Clare were baptised.']]],
                ['Museo Diocesano e Cripta di San Rufino', 'museum', 'Accanto alla cattedrale: la cripta della chiesa più antica e i dipinti della diocesi. Biglietto 3,50 €.', 'Piazza San Rufino 3', 3, 'rainy', 'pine', null, null, null,
                 ['web' => 'https://www.assisimuseodiocesano.it', 'tel' => '+39 075 812712', 'nota' => 'Lunedì–venerdì 10–13 e 15–18, sabato 10–18, domenica 11–18.',
                  'en' => ['Next to the cathedral: the crypt of the older church and the diocese\'s paintings. Ticket €3.50.', 'Monday–Friday 10am–1pm and 3–6pm, Saturday 10am–6pm, Sunday 11am–6pm.']]],
                ['Basilica di Santa Chiara', 'church', 'In pietra bianca e rosa del Subasio. Dentro c\'è il Crocifisso di San Damiano, quello che parlò a Francesco, e nella cripta riposa Santa Chiara. Dalla piazza davanti, la vista sulla valle.', 'Piazza Santa Chiara', 8, 'view', 'pine', null, null, null,
                 ['en' => ['Built in the white and pink stone of Mount Subasio. Inside is the San Damiano Crucifix, the one that spoke to Francis, and Saint Clare rests in the crypt. The square in front looks over the valley.']]],
                ['Piazza del Comune e Tempio di Minerva', 'square', 'Il cuore della città: le sei colonne del tempio romano del I secolo a.C., la Torre del Popolo e la fontana dei tre leoni.', 'Piazza del Comune', 8, 'free', 'ochre', null, null, null,
                 ['en' => ['The heart of the town: the six columns of the 1st-century BC Roman temple, the Torre del Popolo and the three-lion fountain.']]],
                ['Foro Romano e Collezione Archeologica', 'museum', 'Sotto Piazza del Comune: si scende dalla cripta di San Niccolò e si cammina sul lastricato del foro romano. Biglietto 5 €, ridotto 3 €.', 'Via Portica 2', 9, 'rainy', 'sea', null, null, null,
                 ['web' => 'https://www.visit-assisi.it', 'nota' => 'Marzo–giugno, settembre e ottobre 10–18; luglio e agosto 10–19; da novembre a febbraio 10–17, chiuso il martedì.',
                  'en' => ['Beneath Piazza del Comune: you go down through the crypt of San Niccolò and walk on the paving of the Roman forum. Ticket €5, reduced €3.', 'March–June, September and October 10am–6pm; July and August 10am–7pm; November to February 10am–5pm, closed on Tuesdays.']]],
                ['Rocca Maggiore', 'castle', 'La fortezza in cima alla città: dai camminamenti si vedono tutta la valle e il Subasio. Biglietto 8 €, ridotto 6 €.', 'Via della Rocca', 12, 'sunset', 'terracotta', null, null, null,
                 ['web' => 'https://www.visit-assisi.it', 'nota' => 'Marzo e ottobre 10–18; aprile, maggio e settembre 10–19; giugno–agosto 10–20; novembre–febbraio 10–17. La biglietteria chiude 45 minuti prima.',
                  'en' => ['The fortress at the top of the town: from the walkways you see the whole valley and Mount Subasio. Ticket €8, reduced €6.', 'March and October 10am–6pm; April, May and September 10am–7pm; June–August 10am–8pm; November–February 10am–5pm. Last tickets 45 minutes before closing.']]],
                ['Basilica di San Francesco', 'church', 'Due chiese una sopra l\'altra: nella superiore le Storie di San Francesco attribuite a Giotto, nell\'inferiore gli affreschi di Cimabue, Simone Martini e Lorenzetti, e la tomba del santo. Ingresso libero.', 'Piazza Inferiore di San Francesco', 25, 'must_see', 'terracotta', null, null, null,
                 ['web' => 'https://www.sanfrancescoassisi.org', 'nota' => 'Spalle e ginocchia coperte; dentro non si fotografa. La mattina presto c\'è meno gente.',
                  'en' => ['Two churches one above the other: in the upper one the Life of Saint Francis attributed to Giotto, in the lower one frescoes by Cimabue, Simone Martini and Lorenzetti, and the saint\'s tomb. Free entry.', 'Shoulders and knees covered; no photos inside. Fewer people early in the morning.']]],
                ['Santuario di San Damiano', 'sanctuary', 'La chiesetta dove Francesco sentì il Crocifisso parlargli e dove Santa Chiara visse con le sorelle: chiostro e dormitorio come allora. Ingresso libero.', 'Via San Damiano', 30, 'quiet', 'pine', null, null, 10,
                 ['en' => ['The small church where Francis heard the Crucifix speak and where Saint Clare lived with her sisters: cloister and dormitory as they were. Free entry.'], 'nota' => 'Si scende a piedi da Porta Nuova in un quarto d\'ora, tra gli ulivi.']],
                ['Basilica di Santa Maria degli Angeli e Porziuncola', 'church', 'Nella pianura, la grande basilica che custodisce la Porziuncola, la chiesetta di San Francesco, e la Cappella del Transito dove morì.', 'Piazza Porziuncola, Santa Maria degli Angeli', 0, 'must_see', 'sea', null, null, 15,
                 ['web' => 'https://www.porziuncola.org', 'nota' => 'Con la linea C dell\'autobus, da Piazza Matteotti.', 'en' => ['Down on the plain, the great basilica that holds the Porziuncola, the little church of Saint Francis, and the Chapel of the Transitus where he died.', 'Take bus line C from Piazza Matteotti.']]],
            ]);
            $fare = Properties::addSection($acc, $pid, 'todo');
            self::scrivi($pid, $fare, ['it' => ['intro' => 'Per riempire le giornate, in città e sul Subasio, che comincia appena fuori dalle mura.'], 'en' => ['intro' => 'To fill your days, in town and on Mount Subasio, which starts just outside the walls.']], 'Cosa fare');
            $luoghi($fare, [
                ['A piedi all\'Eremo delle Carceri', 'hike', 'Il sentiero 350 parte da Porta Cappuccini e sale nel bosco del Subasio fino all\'eremo dove Francesco si ritirava a pregare: circa 3,5 km e 430 metri di dislivello, un\'ora abbondante all\'andata. Ingresso libero all\'eremo.', 'Porta Cappuccini', 6, 'challenging', 'pine', null, null, null,
                 ['nota' => 'Scarpe da trekking e acqua: lungo il sentiero non ci sono fontane.', 'en' => ['Trail 350 starts at Porta Cappuccini and climbs through the Subasio woods to the hermitage where Francis withdrew to pray: about 3.5 km and 430 metres of ascent, a good hour up. Free entry to the hermitage.', 'Hiking shoes and water: there are no fountains along the trail.']]],
                ['Bosco di San Francesco (FAI)', 'hike', 'Un sentiero di circa 1,5 km scende dalla Basilica Superiore nel bosco fino al Tescio, tra il monastero di Santa Croce, un mulino medievale e il Terzo Paradiso di Pistoletto. Biglietto 6 €, ridotto 3 € (6–18 anni), gratis per gli iscritti FAI.', 'Piazza Superiore di San Francesco', 25, 'half_day', 'pine', null, null, null,
                 ['web' => 'https://fondoambiente.it/luoghi/bosco-di-san-francesco', 'en' => ['A path of about 1.5 km goes down from the Upper Basilica through the woods to the Tescio stream, past the Santa Croce monastery, a medieval mill and Pistoletto\'s Third Paradise. Ticket €6, reduced €3 (ages 6–18), free for FAI members.']]],
                ['Calendimaggio e vita medievale', 'tour', 'Se non sei qui a maggio, le sedi delle due Parti e i loro vicoli raccontano lo stesso la festa: chiedi all\'ufficio turistico di Piazza del Comune le visite guidate della città medievale.', 'Piazza del Comune', 8, 'booking', 'sea', null, null, null,
                 ['web' => 'https://www.calendimaggiodiassisi.com', 'en' => ['If you are not here in May, the headquarters of the two Parti and their alleys still tell the story of the festival: ask the tourist office in Piazza del Comune about guided tours of the medieval town.']]],
                ['Prati del Monte Subasio', 'hike', 'In auto si sale oltre l\'Eremo fino ai prati in cima al monte, a 1.290 metri: passeggiate facili e la vista sulla valle umbra, fino al Trasimeno nelle giornate limpide.', 'Monte Subasio', 0, 'easy', 'pine', null, null, 30,
                 ['en' => ['By car you drive past the Hermitage to the meadows at the top of the mountain, at 1,290 metres: easy walks and views over the Umbrian valley, as far as Lake Trasimeno on clear days.']]],
                ['In bici sulla ciclovia Assisi–Spoleto', 'bike', 'In pianura, tra uliveti e campi, verso Spello e Foligno. Le e-bike le prenota Francesco a Santa Maria degli Angeli.', 'Santa Maria degli Angeli', 0, 'full_day', 'sea', null, null, 15,
                 ['en' => ['On the flat, through olive groves and fields, towards Spello and Foligno. Francesco books e-bikes in Santa Maria degli Angeli.']]],
            ]);
            $negozi = Properties::addSection($acc, $pid, 'shop');
            self::scrivi($pid, $negozi, ['it' => ['intro' => 'Per la spesa basta scendere di un vicolo. Il sabato mattina c\'è anche il mercato in Piazza Matteotti.'], 'en' => ['intro' => 'For groceries, just walk down one alley. On Saturday mornings there is also the market in Piazza Matteotti.']], 'Negozi e spesa');
            $luoghi($negozi, [
                ['Bottega di Rosa', 'grocery', 'Salumi, formaggi, pane e il necessario per la colazione.', 'Vicolo dei Gerani 1', 1, 'local', 'sea', null, null, null, ['en' => ['Cured meats, cheese, bread and everything for breakfast.']]],
                ['Forno del Subasio', 'bakery', 'Torta al testo calda dalle 11 e rocciata, il dolce di Assisi con mele, noci e uvetta.', 'Vicolo dei Gerani 6', 3, 'on_foot', 'sea', null, null, null, ['en' => ['Warm torta al testo from 11am and rocciata, the Assisi pastry with apples, walnuts and raisins.']]],
                ['Mercato del sabato', 'market', 'Il mercato settimanale: frutta, verdura, formaggi e olio dai produttori della zona. Il sabato dalle 8 alle 13.', 'Piazza Matteotti', 2, 'local', 'ochre', null, null, null, ['en' => ['The weekly market: fruit, vegetables, cheese and olive oil from local producers. Saturdays 8am to 1pm.']]],
                ['Farmacia', 'pharmacy', 'La più vicina è in centro, verso Piazza del Comune: il turno di notte è affisso sulla porta di ogni farmacia.', 'Piazza del Comune', 8, 'late', 'sea', null, null, null, ['en' => ['The nearest one is in the centre, towards Piazza del Comune: the night rota is posted on every pharmacy door.']]],
            ]);
        }
        self::traduciVetrina($pid);
        Guide::publish($pid);
        return $pid;
    }

    /**
     * Le altre lingue della vetrina: ogni campo ancora da tradurre prende la traduzione del suo
     * testo italiano da lang/vetrina/{lingua}.php. Quello che lì non c'è resta da tradurre.
     */
    private static function traduciVetrina(int $pid): void
    {
        $prop = Db::one('SELECT * FROM properties WHERE id = ?', [$pid]);
        foreach (Db::all('SELECT locale FROM property_locales WHERE property_id = ?', [$pid]) as $l) {
            $loc = (string) $l['locale'];
            $f = MHW_APP . '/lang/vetrina/' . $loc . '.php';
            if ($loc === $prop['default_locale'] || !is_file($f)) continue;
            $testi = require $f;
            foreach (Traduttore::campi($prop, $loc) as $c) {
                if ($c['tradotto'] === '' && isset($testi[$c['origine']])) Traduttore::scrivi($c['tipo'], (int) $c['id'], $loc, $c['path'], $testi[$c['origine']]);
            }
        }
    }

    /**
     * Crea la vetrina nell'account: prima, se l'account non ha un abbonamento attivo e $plus è
     * vero, concede Plus dimostrativo per 12 mesi (senza piano la guida uscirebbe senza foto,
     * senza inglese e senza luoghi). Se la creazione si interrompe, toglie quello che ha lasciato.
     */
    public static function creaVetrina(int $accountId, bool $plus): int
    {
        if ($plus && !Subscriptions::active($accountId)) {
            $pv = (int) Db::val("SELECT pv.id FROM package_versions pv JOIN packages p ON p.id = pv.package_id WHERE p.code = 'plus' AND pv.is_current = 1", [], 0);
            if ($pv) Billing::grantManual($accountId, $pv, 12, 'Guida vetrina: Plus dimostrativo');
        }
        Entitlements::forget($accountId);
        try {
            return self::vetrina($accountId);
        } catch (\Throwable $e) {
            self::eliminaVetrina($accountId);
            throw $e;
        }
    }

    /** Toglie la vetrina dell'account, con le sue foto. Le altre strutture non si toccano. */
    public static function eliminaVetrina(int $accountId): int
    {
        $n = 0;
        foreach (Db::all('SELECT id FROM properties WHERE account_id = ? AND is_demo = ?', [$accountId, self::VETRINA]) as $p) {
            foreach (Db::all('SELECT id FROM media WHERE property_id = ?', [$p['id']]) as $m) Media::delete((int) $m['id'], $accountId);
            Db::run('DELETE FROM properties WHERE id = ?', [$p['id']]);
            $n++;
        }
        return $n;
    }

    /**
     * La vetrina si crea da sola, una volta: nell'account con l'email di config 'vetrina_email'
     * (MHW_VETRINA_EMAIL), alla prima apertura della landing o del Quadro dopo l'aggiornamento.
     * Non tocca una vetrina che c'è già. Il file storage/vetrina-automatica.txt dice che è stato
     * fatto (o perché no): cancellandolo si riprova. Se l'account non esiste ancora, si riprova
     * alla visita dopo.
     */
    public static function vetrinaAutomatica(): void
    {
        $email = mb_strtolower(trim((string) Config::get('vetrina_email', '')));
        $segno = MHW_APP . '/storage/vetrina-automatica.txt';
        if ($email === '' || is_file($segno)) return;
        try {
            $acc = Db::one("SELECT a.id, u.id AS uid FROM users u JOIN accounts a ON a.user_id = u.id WHERE u.email = ? AND u.role = 'host'", [$email]);
        } catch (\Throwable $e) { return; }
        if (!$acc) return;
        $f = @fopen($segno, 'x');   // il primo che arriva: un'altra richiesta nello stesso momento non la crea due volte
        if (!$f) return;
        try {
            if (Db::val('SELECT id FROM properties WHERE account_id = ? AND is_demo = ?', [$acc['id'], self::VETRINA])) {
                fwrite($f, Support::now() . " c'era già una vetrina: non toccata\n");
                return;
            }
            $pid = self::creaVetrina((int) $acc['id'], true);
            Auth::audit('demo.vetrina', (int) $acc['uid'], ['property_id' => $pid, 'automatica' => true]);
            fwrite($f, Support::now() . " Casa Checco creata (struttura $pid)\n");
        } catch (\Throwable $e) {
            Log::error('vetrina automatica: ' . $e->getMessage(), ['account' => $acc['id']]);
            fwrite($f, Support::now() . ' non riuscita: ' . $e->getMessage() . "\n");
        } finally {
            fclose($f);
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
