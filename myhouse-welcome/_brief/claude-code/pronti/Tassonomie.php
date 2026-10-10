<?php
namespace MHW;

/**
 * Gli elenchi fissi della Fase 6: categorie ed etichette dei luoghi per sezione,
 * gruppi delle dotazioni, modi di muoversi, unità dei prezzi.
 * Qui stanno solo le CHIAVI. Le parole, nelle 5 lingue, sono in lang/tassonomie/.
 * Categoria: I18n::t($loc, 'cat.' . $chiave). Etichetta: 'badge.' . $chiave.
 * Dotazione: 'amen_' . $chiave. Muoversi: 'move.' . $chiave. Unità: 'unit.' . $chiave.
 */
final class Tassonomie
{
    /** Categorie dei luoghi, per tipo di sezione. */
    public const CATEGORIE = [
        'visit'  => ['monument', 'church', 'museum', 'village', 'castle', 'archaeology', 'square', 'viewpoint', 'park', 'nature', 'sanctuary', 'palace', 'theatre', 'beach'],
        'todo'   => ['hike', 'bike', 'spa', 'swim', 'tasting', 'cooking', 'tour', 'sport', 'kids', 'market_event', 'adventure', 'riding'],
        'eat'    => ['restaurant', 'trattoria', 'pizzeria', 'osteria', 'bar', 'breakfast', 'wine_bar', 'gelato', 'pastry', 'farm', 'street_food', 'aperitivo'],
        'shop'   => ['grocery', 'supermarket', 'market', 'butcher', 'bakery', 'greengrocer', 'local_products', 'winery', 'tobacconist', 'pharmacy', 'laundry', 'atm', 'crafts', 'newsagent', 'fuel'],
    ];

    /** Etichette («In evidenza») dei luoghi, per tipo di sezione, nell'ordine in cui si propongono. */
    public const ETICHETTE = [
        'visit'  => ['must_see', 'view', 'sunset', 'family', 'free', 'booking', 'rainy', 'quiet', 'accessible', 'host_pick'],
        'todo'   => ['family', 'booking', 'half_day', 'full_day', 'easy', 'challenging', 'rainy', 'host_pick'],
        'eat'    => ['dinner', 'breakfast', 'typical', 'booking', 'veggie', 'gluten_free', 'family', 'view', 'host_pick'],
        'shop'   => ['local', 'sunday', 'late', 'on_foot', 'host_pick'],
    ];

    /** Dotazioni, a gruppi (i titoli dei gruppi si vedono solo nel pannello). */
    public const DOTAZIONI = [
        'Cucina'               => ['dishwasher', 'oven', 'microwave', 'fridge', 'coffee', 'kettle', 'toaster'],
        'Comfort'              => ['ac', 'heating', 'fan', 'fireplace', 'tv', 'desk', 'elevator', 'safe', 'mosquito_nets'],
        'Lavanderia e bagno'   => ['washer', 'dryer', 'iron', 'hairdryer', 'linens', 'towels'],
        'Esterni'              => ['pool', 'garden', 'terrace', 'bbq', 'hot_tub', 'private_parking', 'ev_charger', 'bikes'],
        'Famiglia'             => ['crib', 'highchair', 'playground'],
        'Sicurezza'            => ['first_aid', 'fire_ext'],
    ];

    /** Tipi di «Muoversi in zona». */
    public const MUOVERSI = ['bus', 'taxi', 'car_rental', 'bike_rental', 'scooter_rental', 'shuttle', 'train', 'lifts', 'walk', 'other'];

    /** Unità dei prezzi (servizi extra). */
    public const UNITA = ['per_person', 'per_trip', 'per_day', 'per_night', 'per_stay', 'per_hour', 'on_request'];

    /** Segnaposto di «Perché lo consigli», per tipo di sezione (solo pannello). */
    public const SEGNAPOSTO = [
        'visit' => 'Vai la mattina presto: c\'è meno gente e la luce è più bella.',
        'todo'  => 'Prenota il giorno prima: i posti sono pochi.',
        'eat'   => 'Prenota il tavolo in terrazza, al tramonto.',
        'shop'  => 'Chiedi il pane cotto a legna, arriva alle 11.',
    ];

    public static function categorie(string $kind): array { return self::CATEGORIE[$kind] ?? []; }
    public static function etichette(string $kind): array { return self::ETICHETTE[$kind] ?? []; }

    /** Tutte le chiavi di dotazione, in ordine di gruppo. */
    public static function dotazioni(): array { return array_merge(...array_values(self::DOTAZIONI)); }

    /**
     * La chiave che corrisponde a un testo scritto a mano («Museo», «museum»…),
     * cercando in tutte le lingue. Serve a convertire i dati già inseriti.
     */
    public static function chiaveDaTesto(string $prefisso, array $chiavi, string $testo): string
    {
        $t = mb_strtolower(trim($testo));
        if ($t === '') return '';
        foreach ($chiavi as $k) {
            foreach (I18n::LOCALES as $loc) {
                if (mb_strtolower(I18n::t($loc, $prefisso . $k)) === $t) return $k;
            }
        }
        return '';
    }
}
