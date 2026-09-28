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
            'fields' => [
                'checkin_steps'   => ['steps', 'Come si entra', 'Un passaggio per riga: dove sono le chiavi, come si apre, dove si parcheggia la valigia.'],
                'checkin_note'    => ['textarea', 'Nota importante', 'Facoltativa. Per esempio: se arrivi dopo le 21, scrivici.'],
                'checkout_keys'   => ['text', 'Alla partenza — chiavi', 'Dove lasciarle.'],
                'checkout_waste'  => ['text', 'Alla partenza — rifiuti', 'Cosa fare dei rifiuti.'],
                'checkout_lights' => ['text', 'Alla partenza — luci', ''],
                'checkout_climate'=> ['text', 'Alla partenza — climatizzazione', 'Riscaldamento e aria condizionata.'],
                'checkout_windows'=> ['text', 'Alla partenza — finestre', ''],
                'checkout_notes'  => ['textarea', 'Note finali', 'Un saluto, un\'ultima raccomandazione.'],
            ],
        ],
        'wifi' => [
            'icon' => 'wifi',
            'fields' => [
                'network'         => ['plain', 'Nome della rete', ''],
                'password'        => ['secret', 'Password', ''],
                'instructions'    => ['textarea', 'Istruzioni', 'Facoltative. Cosa fare se la rete non si vede.'],
                'router_location' => ['text', 'Dove si trova il router', 'Facoltativo.'],
            ],
        ],
        'services' => [
            'icon' => 'washer',
            'fields' => [
                'items' => ['list', 'Servizi disponibili', 'Uno per riga: lavatrice, asciugacapelli, culla, aria condizionata…'],
                'note'  => ['textarea', 'Nota', 'Facoltativa.'],
            ],
        ],
        'rules' => [
            'icon' => 'doc',
            'fields' => [
                'items' => ['list', 'Regole', 'Una per riga: orari del silenzio, fumo, animali, feste.'],
                'note'  => ['textarea', 'Nota', 'Facoltativa.'],
            ],
        ],
        'arrival' => [
            'icon' => 'pin',
            'fields' => [
                'address'  => ['plain', 'Indirizzo', ''],
                'maps_url' => ['url', 'Link a Google Maps', 'Facoltativo. Se manca, si usa l\'indirizzo.'],
                'steps'    => ['steps', 'Indicazioni', 'Un passaggio per riga: dall\'autostrada, dalla stazione, dall\'aeroporto.'],
                'note'     => ['textarea', 'Nota', 'Facoltativa.'],
            ],
        ],
        'transport' => [
            'icon' => 'pin',
            'fields' => [
                'items' => ['list', 'Come muoversi', 'Uno per riga: autobus, taxi, noleggio bici.'],
                'note'  => ['textarea', 'Nota', 'Facoltativa.'],
            ],
        ],
        'parking' => [
            'icon' => 'key',
            'fields' => [
                'parking_type' => ['text', 'Tipo di parcheggio', 'Per esempio: posto riservato in cortile, parcheggio pubblico gratuito.'],
                'address'      => ['plain', 'Indirizzo del parcheggio', ''],
                'maps_url'     => ['url', 'Link a Google Maps', 'Facoltativo.'],
                'instructions' => ['textarea', 'Istruzioni', ''],
                'cost'         => ['text', 'Costo', 'Facoltativo. Per esempio: gratuito, 5 € al giorno.'],
            ],
        ],
        'waste' => [
            'icon' => 'doc',
            'fields' => [
                'items' => ['list', 'Come si differenzia', 'Una voce per riga: umido martedì e venerdì, carta il giovedì…'],
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
            'icon' => 'pin', 'places' => true,
            'fields' => [
                'intro'     => ['textarea', 'Introduzione', ''],
                'host_note' => ['textarea', 'Il tuo consiglio personale', 'Facoltativo.'],
            ],
        ],
        'todo' => [
            'icon' => 'pin', 'places' => true,
            'fields' => [
                'intro'     => ['textarea', 'Introduzione', ''],
                'host_note' => ['textarea', 'Il tuo consiglio personale', 'Facoltativo.'],
            ],
        ],
        'emergency' => [
            'icon' => 'phone',
            'fields' => [
                'emergency_number' => ['plain', 'Numero unico di emergenza', 'In Italia è il 112.'],
                'items'            => ['list', 'Contatti utili', 'Uno per riga: guardia medica, farmacia di turno, il tuo numero per le urgenze.'],
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
    private const PLAIN = ['plain', 'url', 'secret'];

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

    public static function isTranslated(string $type): bool { return !in_array($type, self::PLAIN, true); }

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
            if (in_array($type, ['steps', 'list'], true)) {
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
