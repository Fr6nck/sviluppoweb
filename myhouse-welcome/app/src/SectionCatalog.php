<?php
namespace MHW;

/**
 * Il catalogo delle sezioni. Ogni tipo dichiara i suoi campi, e da qui nascono
 * sia l'editor dell'host sia la pagina dell'ospite: niente mini-sintassi da
 * imparare, niente dati strutturati nascosti dentro un campo di testo.
 *
 * Tipi di campo:
 *   steps     — passaggi numerati, da tradurre
 *   list      — elenco di voci, da tradurre
 *   text      — una riga, da tradurre
 *   textarea  — un paragrafo, da tradurre
 *   plain     — una riga uguale in ogni lingua (nome rete, indirizzo)
 *   url       — un indirizzo web, uguale in ogni lingua
 *   secret    — come plain, ma si copia con un tocco (password Wi-Fi)
 *   choice    — una scelta tra opzioni fisse, uguale in ogni lingua (etichette nei file lang)
 *   repeater  — righe con sottocampi (più reti Wi-Fi, più contatti…). Ogni riga ha
 *               un id stabile: i sottocampi uguali in ogni lingua stanno in
 *               sections.data[campo], quelli da tradurre in section_translations.data[campo],
 *               e le due parti si uniscono per id. Così riordinare o togliere una riga
 *               non mescola le traduzioni.
 *               Sottotipi: text, textarea (tradotti); plain, secret, url, tel, time,
 *               choice, days, check, image, pdf (uguali in ogni lingua; image e pdf
 *               sono id di media, caricati riga per riga).
 *   checks    — più spunte da un elenco fisso (le dotazioni), uguale in ogni lingua
 *   toggles   — interruttori sì / no / non indicato (fumo, animali…), uguale in ogni lingua
 *   time      — un orario (hh:mm), uguale in ogni lingua
 *
 * `places: true` vuol dire che la sezione contiene schede di luoghi.
 * Check-in & Check-out è il nucleo: c'è sempre, non si disattiva e non conta
 * nel limite delle sezioni del piano.
 */
final class SectionCatalog
{
    private const K = [
        'checkin' => [
            'icon' => 'home', 'core' => true,
            'intro' => 'Il blocco fondamentale: come si entra e cosa fare prima di partire. È sempre incluso.',
            // La partenza era fatta di cinque caselle fisse (checkout_keys, _waste, _lights,
            // _climate, _windows): dalla 009 è una lista ordinabile. I vecchi campi restano
            // nel JSON ma non si leggono più (Conversione::sezione li porta nella lista).
            'fields' => [
                'arrival_mode'    => ['choice', 'Come si entra', '', 'options' => ['self' => 'Self check-in', 'accoglienza' => 'Ti accolgo io', 'cassetta' => 'Cassetta delle chiavi']],
                'checkin_steps'   => ['steps', 'Passaggi per entrare', 'Un passaggio per riga: dove sono le chiavi, come si apre, dove si parcheggia la valigia.'],
                'late_arrival'    => ['textarea', 'Arrivo tardivo', 'Facoltativo. Cosa fare se si arriva tardi, per esempio dopo le 21.'],
                'documents'       => ['textarea', 'Documenti da mostrare', 'Facoltativo. Per esempio: un documento d\'identità per ogni ospite, per la registrazione obbligatoria.'],
                'tax_amount'      => ['plain', 'Imposta di soggiorno — importo per notte', 'Facoltativo. Per esempio: 2,00 € a persona.'],
                'tax_max_nights'  => ['plain', 'Imposta di soggiorno — per quante notti al massimo', 'Facoltativo. Per esempio: 5.'],
                'tax_notes'       => ['textarea', 'Imposta di soggiorno — esenzioni e pagamento', 'Facoltativo. Per esempio: sotto i 14 anni esenti; in contanti all\'arrivo.'],
                'checkin_note'    => ['textarea', 'Nota importante', 'Facoltativa.'],
                'checkout_steps'  => ['steps', 'Prima di partire', 'Una cosa per riga. Tocca un suggerimento per aggiungerlo, poi scrivilo come preferisci.',
                                      'suggest' => ['keys', 'waste', 'lights', 'climate', 'windows', 'dishwasher', 'towels']],
                'checkout_notes'  => ['textarea', 'Note finali', 'Un saluto, un\'ultima raccomandazione.'],
            ],
        ],
        'wifi' => [
            'icon' => 'wifi',
            // Più reti (dalla 010): una riga per rete. La rete singola di prima
            // (network, password) diventa la prima riga.
            'fields' => [
                'networks'        => ['repeater', 'Reti Wi-Fi', 'Una riga per rete: 2,4 e 5 GHz, piano di sopra, dependance…',
                                      'add' => 'Aggiungi una rete', 'max' => 8, 'sub' => [
                                          'zone'     => ['text', 'Zona', 'Facoltativa. Per esempio: Casa principale, Dependance.'],
                                          'ssid'     => ['plain', 'Nome della rete', ''],
                                          'password' => ['secret', 'Password', ''],
                                      ]],
                'instructions'    => ['textarea', 'Istruzioni', 'Facoltative. Cosa fare se la rete non si vede.'],
                'router_location' => ['text', 'Dove si trova il router', 'Facoltativo.'],
            ],
        ],
        // Servizi (dalla 011): dotazioni da spuntare e istruzioni con foto e PDF. La vecchia
        // lista resta com'era, come «Altre dotazioni».
        'services' => [
            'icon' => 'washer',
            'fields' => [
                'amenities' => ['checks', 'Dotazioni', 'Spunta quello che trovano in casa.', 'options' => [
                    'washer' => 'Lavatrice', 'dryer' => 'Asciugatrice', 'dishwasher' => 'Lavastoviglie', 'hairdryer' => 'Asciugacapelli',
                    'iron' => 'Ferro da stiro', 'crib' => 'Culla', 'highchair' => 'Seggiolone', 'ac' => 'Aria condizionata',
                    'heating' => 'Riscaldamento', 'tv' => 'TV', 'coffee' => 'Macchina del caffè', 'bbq' => 'Barbecue']],
                'manuals' => ['repeater', 'Istruzioni', 'Come si accende la caldaia, come funziona la lavatrice: titolo, passaggi, una foto, un PDF.',
                              'add' => 'Aggiungi un\'istruzione', 'max' => 10, 'sub' => [
                                  'title' => ['text', 'Titolo', 'Per esempio: La caldaia.'],
                                  'steps' => ['textarea', 'Passaggi', 'Uno per riga.', 'lines' => true],
                                  'photo' => ['image', 'Foto', 'Facoltativa.'],
                                  'pdf'   => ['pdf', 'PDF', 'Facoltativo. Per esempio il manuale.'],
                              ]],
                'items' => ['list', 'Altre dotazioni', 'Una per riga.'],
                'note'  => ['textarea', 'Nota', 'Facoltativa.'],
            ],
        ],
        // Regole (dalla 011): interruttori standard, tradotti da soli; la vecchia lista
        // resta com'era, come «Regole aggiuntive».
        'rules' => [
            'icon' => 'doc',
            'fields' => [
                'flags' => ['toggles', 'Le regole principali', 'Sì, no, o lascia «non indicato».', 'options' => [
                    'smoking' => 'Fumo', 'pets' => 'Animali', 'parties' => 'Feste', 'visitors' => 'Visitatori esterni']],
                'quiet_from' => ['time', 'Silenzio dalle', 'Facoltativo.'],
                'quiet_to'   => ['time', 'Silenzio fino alle', 'Facoltativo.'],
                'items' => ['list', 'Regole aggiuntive', 'Una per riga.'],
                'note'  => ['textarea', 'Nota', 'Facoltativa.'],
            ],
        ],
        // Come arrivare (dalla 011): una scheda per mezzo. I vecchi passaggi diventano la prima scheda.
        'arrival' => [
            'icon' => 'pin',
            'fields' => [
                'address'  => ['plain', 'Indirizzo', ''],
                'maps_url' => ['url', 'Link a Google Maps', 'Facoltativo. Se manca, si usa l\'indirizzo.'],
                'routes'   => ['repeater', 'Come arrivare', 'Una scheda per mezzo: in auto, in treno, in aereo, in autobus. L\'ospite legge solo quella che gli serve.',
                               'add' => 'Aggiungi un mezzo', 'max' => 6, 'sub' => [
                                   'mode'  => ['choice', 'Mezzo', '', 'options' => ['' => 'Indicazioni', 'auto' => 'In auto', 'treno' => 'In treno', 'aereo' => 'In aereo', 'autobus' => 'In autobus']],
                                   'steps' => ['textarea', 'Passaggi', 'Uno per riga.', 'lines' => true],
                               ]],
                'note'     => ['textarea', 'Nota', 'Facoltativa.'],
            ],
        ],
        'transport' => [
            'icon' => 'bus',
            'fields' => [
                'items' => ['list', 'Come muoversi', 'Uno per riga: autobus, taxi, noleggio bici.'],
                'note'  => ['textarea', 'Nota', 'Facoltativa.'],
            ],
        ],
        // Parcheggio (dalla 011): più possibilità, una riga ciascuna, e la ZTL a parte.
        // Il parcheggio di prima (tipo, indirizzo, link, istruzioni, costo) diventa la prima riga.
        'parking' => [
            'icon' => 'car',
            'fields' => [
                'options' => ['repeater', 'Dove parcheggiare', 'Una riga per ogni possibilità: posto privato, parcheggio pubblico, garage…',
                              'add' => 'Aggiungi un parcheggio', 'max' => 8, 'sub' => [
                                  'type'         => ['choice', 'Tipo', '', 'options' => ['' => 'Non indicato', 'privato' => 'Privato', 'pubblico' => 'Pubblico gratuito',
                                                                                         'pagamento' => 'A pagamento', 'garage' => 'Garage', 'strada' => 'In strada']],
                                  'name'         => ['text', 'Descrizione', 'Per esempio: posto riservato in cortile.'],
                                  'address'      => ['plain', 'Indirizzo', ''],
                                  'maps_url'     => ['url', 'Link a Google Maps', 'Facoltativo.'],
                                  'cost'         => ['text', 'Costo', 'Facoltativo. Per esempio: gratuito, 5 € al giorno.'],
                                  'instructions' => ['textarea', 'Istruzioni', ''],
                                  'photo'        => ['image', 'Foto', 'Facoltativa.'],
                              ]],
                'ztl' => ['textarea', 'ZTL', 'Facoltativo. Orari e varchi della zona a traffico limitato: evita le multe agli ospiti.'],
            ],
        ],
        // Rifiuti (dalla 011): una riga per tipo, con i giorni, il colore del bidone e dove si
        // trova. Le vecchie voci «una per riga» diventano righe col testo nella descrizione.
        'waste' => [
            'icon' => 'bin',
            'fields' => [
                'bins' => ['repeater', 'Raccolta differenziata', 'Una riga per tipo di rifiuto: i giorni in cui si porta fuori, il colore del bidone, dove si trova.',
                           'add' => 'Aggiungi un tipo di rifiuto', 'max' => 12, 'sub' => [
                               'type'  => ['choice', 'Tipo', '', 'options' => ['altro' => 'Altro', 'umido' => 'Umido', 'carta' => 'Carta', 'plastica' => 'Plastica e metalli',
                                                                              'vetro' => 'Vetro', 'indifferenziato' => 'Indifferenziato']],
                               'label' => ['text', 'Descrizione', 'Facoltativa. Per esempio: «lattine insieme alla plastica».'],
                               'days'  => ['days', 'Giorni in cui si porta fuori', ''],
                               'color' => ['choice', 'Colore del bidone', '', 'options' => ['' => 'Non indicato', 'marrone' => 'Marrone', 'giallo' => 'Giallo', 'blu' => 'Blu',
                                                                                          'verde' => 'Verde', 'grigio' => 'Grigio', 'bianco' => 'Bianco', 'rosso' => 'Rosso', 'arancione' => 'Arancione']],
                               'where' => ['text', 'Dove si trova', 'Facoltativo.'],
                           ]],
                'note'  => ['textarea', 'Nota', 'Facoltativa. Dove sono i bidoni.'],
            ],
        ],
        'eat' => [
            'icon' => 'fork', 'places' => true,
            'fields' => [
                'intro'     => ['textarea', 'Introduzione', 'Una frase che presenta i tuoi consigli.'],
                'host_note' => ['textarea', 'Il tuo consiglio personale', 'Facoltativo. Compare firmato col tuo nome.'],
            ],
        ],
        'visit' => [
            'icon' => 'monument', 'places' => true,
            'fields' => [
                'intro'     => ['textarea', 'Introduzione', ''],
                'host_note' => ['textarea', 'Il tuo consiglio personale', 'Facoltativo.'],
            ],
        ],
        'todo' => [
            'icon' => 'compass', 'places' => true,
            'fields' => [
                'intro'     => ['textarea', 'Introduzione', ''],
                'host_note' => ['textarea', 'Il tuo consiglio personale', 'Facoltativo.'],
            ],
        ],
        'emergency' => [
            'icon' => 'phone',
            'fields' => [
                'emergency_number' => ['plain', 'Numero unico di emergenza', 'In Italia è il 112.'],
                // Dalla 011: righe nome · telefono · nota, con un «Chiama» per riga nella guida.
                'contacts'         => ['repeater', 'Contatti utili', 'Uno per riga: guardia medica, farmacia di turno, il tuo numero per le urgenze.',
                                       'add' => 'Aggiungi un contatto', 'max' => 15,
                                       'presets' => ['emergency_number' => ['phone' => '112'], 'preset_guardia' => [], 'preset_farmacia' => [], 'preset_veterinario' => []],
                                       'sub' => [
                                           'name'  => ['text', 'Nome', ''],
                                           'phone' => ['tel', 'Telefono', ''],
                                           'note'  => ['text', 'Nota', 'Facoltativa. Orari, indirizzo…'],
                                       ]],
                'note'             => ['textarea', 'Nota', 'Facoltativa.'],
            ],
        ],
        'info' => [
            'icon' => 'info',
            'fields' => [
                'items' => ['list', 'Cose da sapere', 'Una per riga.'],
                'note'  => ['textarea', 'Nota', 'Facoltativa.'],
            ],
        ],
    ];

    /** Tipi che non si traducono: vivono in sections.data. */
    private const PLAIN = ['plain', 'url', 'secret', 'choice', 'tel', 'time', 'days', 'check', 'checks', 'toggles', 'image', 'pdf'];

    public static function kinds(): array { return array_keys(self::K); }

    /** I tipi selezionabili, cioè tutti tranne il nucleo. */
    public static function selectable(): array
    {
        return array_values(array_filter(self::kinds(), fn($k) => empty(self::K[$k]['core'])));
    }

    public static function exists(string $kind): bool { return isset(self::K[$kind]); }

    public static function get(string $kind): array { return self::K[$kind] ?? self::K['info']; }

    public static function icon(string $kind): string { return self::get($kind)['icon']; }

    public static function hasPlaces(string $kind): bool { return !empty(self::get($kind)['places']); }

    public static function isCore(string $kind): bool { return !empty(self::get($kind)['core']); }

    /** Il titolo predefinito nella lingua dell'ospite. */
    public static function title(string $kind, string $loc): string
    {
        return I18n::t($loc, 'kind.' . $kind);
    }

    /** @return array<string,array{0:string,1:string,2:string}> campo => [tipo, etichetta, aiuto] */
    public static function fields(string $kind): array { return self::get($kind)['fields']; }

    /** Un campo (o sottocampo) da tradurre. Il repeater è misto: si guarda sotto. */
    public static function isTranslated(string $type): bool { return !in_array($type, self::PLAIN, true) && $type !== 'repeater'; }

    /** La definizione completa di un campo (con 'sub', 'options', 'suggest'…). */
    public static function field(string $kind, string $name): ?array { return self::fields($kind)[$name] ?? null; }

    /** Gli id dei media (foto e PDF) dentro le righe di una sezione: da duplicare, da cancellare. */
    public static function mediaIds(string $kind, array $data): array
    {
        $ids = [];
        foreach (self::fields($kind) as $name => $def) {
            if ($def[0] !== 'repeater') continue;
            foreach ($def['sub'] as $sn => $sd) {
                if (!in_array($sd[0], ['image', 'pdf'], true)) continue;
                foreach ((array) ($data[$name] ?? []) as $r) if (is_array($r) && (int) ($r[$sn] ?? 0) > 0) $ids[] = (int) $r[$sn];
            }
        }
        return array_values(array_unique($ids));
    }

    /** Un id di riga breve e stabile. */
    public static function newId(): string { return 'r' . bin2hex(random_bytes(4)); }

    /** Il valore pulito di un (sotto)campo, secondo il tipo. */
    public static function clean(array $def, mixed $raw): mixed
    {
        $type = $def[0];
        return match ($type) {
            'url' => Support::safeUrl(mb_substr(trim((string) $raw), 0, 500)),
            'textarea' => mb_substr(trim((string) $raw), 0, 2000),
            'choice' => isset($def['options'][(string) $raw]) ? (string) $raw : '',
            'tel' => mb_substr(trim((string) $raw), 0, 40),
            'time' => preg_match('/^([01]?\d|2[0-3])[:.][0-5]\d$/', trim((string) $raw)) ? str_pad(str_replace('.', ':', trim((string) $raw)), 5, '0', STR_PAD_LEFT) : '',
            'days' => array_values(array_unique(array_filter(array_map('intval', (array) $raw), fn($d) => $d >= 1 && $d <= 7))),
            'check' => !empty($raw) && $raw !== '0' ? '1' : '',
            'checks' => array_values(array_intersect(array_keys($def['options']), array_map('strval', (array) $raw))),
            'toggles' => array_filter(array_intersect_key(array_map(fn($v) => in_array($v, ['si', 'no'], true) ? $v : '', (array) $raw), $def['options'])),
            'image', 'pdf' => (int) $raw > 0 ? (int) $raw : '',
            'plain', 'secret' => mb_substr(trim((string) $raw), 0, 200),
            default => mb_substr(trim((string) $raw), 0, 300),
        };
    }

    /**
     * Le righe di un repeater pronte da mostrare: la parte comune nell'ordine
     * salvato, più i testi della lingua chiesta, e dove mancano quelli della
     * lingua principale. Le righe tradotte che non esistono più si ignorano.
     */
    public static function rows(array $def, mixed $comuni, mixed $principale, mixed $lingua = []): array
    {
        $perId = function (mixed $righe): array {
            $out = [];
            foreach ((array) $righe as $r) if (is_array($r) && isset($r['id'])) $out[(string) $r['id']] = $r;
            return $out;
        };
        $base = $perId($principale); $tr = $perId($lingua);
        $out = [];
        foreach ((array) $comuni as $r) {
            if (!is_array($r) || !isset($r['id'])) continue;
            $id = (string) $r['id']; $riga = ['id' => $id];
            foreach ($def['sub'] as $sn => $sd) {
                if (self::isTranslated($sd[0])) {
                    $v = trim((string) ($tr[$id][$sn] ?? ''));
                    $riga[$sn] = $v !== '' ? $v : trim((string) ($base[$id][$sn] ?? ''));
                } else {
                    $riga[$sn] = $r[$sn] ?? ($sd[0] === 'days' ? [] : '');
                }
            }
            $out[] = $riga;
        }
        return $out;
    }

    /**
     * Legge un modulo inviato e separa i campi uguali in ogni lingua da quelli
     * da tradurre. Solo i campi dichiarati passano: niente chiavi arbitrarie.
     * Solo i campi presenti nel modulo: quelli assenti restano come sono.
     *
     * @return array{0:array,1:array} [dati comuni, dati tradotti]
     */
    public static function fromInput(string $kind, array $in, bool $withPlain = true): array
    {
        $comuni = []; $tradotti = [];
        foreach (self::fields($kind) as $name => [$type]) {
            // Un campo che il modulo non ha mandato non si tocca: così un invio
            // parziale (una foto, un salvataggio automatico) non cancella il resto.
            if (!array_key_exists($name, $in)) continue;
            $raw = $in[$name];
            $def = self::field($kind, $name);
            if ($type === 'repeater') {
                // Le righe nell'ordine del modulo. Nella lingua principale una riga
                // tutta vuota si scarta; nelle traduzioni si tengono tutte le righe.
                $comune = []; $testi = [];
                foreach (array_values(is_array($raw) ? $raw : []) as $r) {
                    if (!is_array($r)) continue;
                    $id = preg_match('/^r[0-9a-f]{4,16}$/', (string) ($r['id'] ?? '')) ? (string) $r['id'] : self::newId();
                    $rc = ['id' => $id]; $rt = ['id' => $id]; $piena = false;
                    foreach ($def['sub'] as $sn => $sd) {
                        if (!array_key_exists($sn, $r) && !in_array($sd[0], ['check', 'days'], true)) continue;
                        $v = self::clean($sd, $r[$sn] ?? '');
                        if (self::isTranslated($sd[0])) $rt[$sn] = $v; else $rc[$sn] = $v;
                        // Una scelta lasciata sulla prima opzione (il valore di partenza) o una
                        // spunta da sola non bastano a fare una riga.
                        if ($sd[0] === 'choice') { if ($v !== '' && $v !== (string) array_key_first($sd['options'])) $piena = true; }
                        elseif ($sd[0] !== 'check' && $v !== '' && $v !== []) $piena = true;
                    }
                    if ($withPlain && !$piena) continue;
                    $comune[] = $rc; $testi[] = $rt;
                    if (count($comune) >= ($def['max'] ?? 30)) break;
                }
                if ($withPlain) $comuni[$name] = $comune;
                $tradotti[$name] = $testi;
            } elseif (in_array($type, ['choice', 'checks', 'toggles', 'time'], true)) {
                if ($withPlain) $comuni[$name] = self::clean($def, $raw);
            } elseif (in_array($type, ['steps', 'list'], true)) {
                $righe = is_array($raw) ? $raw : preg_split('/\R/', (string) $raw);
                $righe = array_values(array_filter(array_map(fn($r) => mb_substr(trim((string) $r), 0, 600), $righe ?: []), fn($r) => $r !== ''));
                $tradotti[$name] = array_slice($righe, 0, 30);
            } elseif ($type === 'url') {
                if ($withPlain) $comuni[$name] = Support::safeUrl(mb_substr((string) $raw, 0, 500));
            } elseif (in_array($type, self::PLAIN, true)) {
                if ($withPlain) $comuni[$name] = mb_substr(trim((string) $raw), 0, 200);
            } elseif ($type === 'textarea') {
                $tradotti[$name] = mb_substr(trim((string) $raw), 0, 2000);
            } else {
                $tradotti[$name] = mb_substr(trim((string) $raw), 0, 300);
            }
        }
        return [$comuni, $tradotti];
    }

    /** Una sezione ha qualcosa da mostrare? (per l'anteprima e la pubblicazione) */
    public static function isEmpty(string $kind, array $data, array $tdata, int $places = 0): bool
    {
        if (self::hasPlaces($kind) && $places > 0) return false;
        foreach ($data + $tdata as $v) {
            if (is_array($v) ? count($v) > 0 : trim((string) $v) !== '') return false;
        }
        return true;
    }
}
