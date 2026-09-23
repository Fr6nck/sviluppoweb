<?php

declare(strict_types=1);

namespace ArcoDelVento\Support;

/**
 * Le poche righe di logica che servono a presentare una camera.
 *
 * Stanno qui e non nelle viste perché le stesse frasi — la riga di attributi,
 * l'elenco dei letti, il nome — servono nell'elenco, nella scheda, nella
 * pagina della camera, nella tabella delle tariffe e nell'e-mail di richiesta.
 * Scritte una volta sola restano coerenti dappertutto.
 */
final class RoomPresenter
{
    /** @param array<string,mixed> $room */
    public static function name(array $room, string $locale): string
    {
        return (string) ($room['name'][$locale] ?? $room['name']['it'] ?? $room['ref']);
    }

    /** @param array<string,mixed> $room */
    public static function href(array $room, string $locale): string
    {
        $slug = (string) ($room['slug'][$locale] ?? $room['ref']);

        return \ArcoDelVento\I18n\Routes::url('room', $locale, ['slug' => $slug]);
    }

    /**
     * Il testo alternativo della fotografia: quello scritto per la camera se
     * la fotografia è sua, altrimenti quello del segnaposto. Dice cosa si
     * vede, non cosa è.
     *
     * @param array<string,mixed> $room
     */
    public static function alt(array $room, string $locale): string
    {
        $alt = $room['alt'][$locale] ?? $room['alt']['it'] ?? null;

        return (is_string($alt) && $alt !== '') ? $alt : t('rooms.image_alt');
    }

    /** @param array<string,mixed> $room */
    public static function isPhotographed(array $room): bool
    {
        return (bool) ($room['photographed'] ?? false);
    }

    /**
     * L'esposizione. Dove la fotografia mostra il campanile dalla finestra
     * non c'è niente da confermare: si vede.
     *
     * @param array<string,mixed> $room
     */
    public static function viewLabel(array $room): ?string
    {
        if (($room['view_san_rufino'] ?? null) === true) {
            return t('views.san_rufino');
        }

        return null;
    }

    /** @param array<string,mixed> $room */
    public static function typeLabel(array $room): string
    {
        return t('room_types.' . (string) ($room['type'] ?? 'double'));
    }

    /**
     * «uno matrimoniale e uno singolo» — scritto, non abbreviato in icone.
     *
     * @param array<string,mixed> $room
     */
    public static function beds(array $room): string
    {
        $parti = [];
        foreach ((array) ($room['beds'] ?? []) as $tipo => $quantita) {
            $forme = tlist('beds.' . $tipo);
            if ($forme === []) {
                continue;
            }
            $parti[] = (int) $quantita === 1
                ? (string) $forme[0]
                : str_replace(':count', (string) $quantita, (string) ($forme[1] ?? $forme[0]));
        }

        if ($parti === []) {
            return '';
        }

        // L'ultima congiunzione per esteso: «A, B e C».
        if (count($parti) === 1) {
            return (string) $parti[0];
        }
        $ultimo = array_pop($parti);

        return implode(', ', $parti) . ' ' . (locale() === 'it' ? 'e' : 'and') . ' ' . $ultimo;
    }

    /**
     * La riga di attributi separata da punto medio, come vuole il sistema:
     * «2 ospiti · matrimoniale · bagno privato».
     *
     * @param array<string,mixed> $room
     * @return list<string>
     */
    public static function metaParts(array $room): array
    {
        $parti = [];

        $max     = (int) ($room['occupancy']['max'] ?? 0);
        $parti[] = $max . ' ' . t($max === 1 ? 'common.guest' : 'common.guests');
        $parti[] = self::typeLabel($room);

        if (!empty($room['bathroom']['private'])) {
            $parti[] = t('bathroom.private');
        } elseif (isset($room['bathroom']['private'])) {
            $parti[] = t('bathroom.shared');
        }

        // La metratura si mostra solo se qualcuno l'ha misurata.
        if (!empty($room['size_sqm'])) {
            $parti[] = $room['size_sqm'] . ' m²';
        }

        return $parti;
    }

    /** @param array<string,mixed> $room */
    public static function metaLine(array $room): string
    {
        return implode(' · ', self::metaParts($room));
    }

    /**
     * I servizi come testo separato da punto medio: è la forma che il
     * registro editoriale chiede, dove le pastiglie sarebbero cornice.
     *
     * @param array<string,mixed> $room
     */
    public static function amenitiesLine(array $room, int $limit = 0): string
    {
        $voci = array_map(
            static fn (string $chiave): string => t('amenities.' . $chiave),
            (array) ($room['amenities'] ?? [])
        );

        if ($limit > 0 && count($voci) > $limit) {
            $voci = array_slice($voci, 0, $limit);
        }

        return implode(' · ', $voci);
    }

    /** La tariffa più bassa dimostrativa, per l'elenco. @param array<string,mixed> $room */
    public static function fromRate(array $room): ?int
    {
        $rate = (int) ($room['price']['demo_from'] ?? 0);

        return $rate > 0 ? $rate : null;
    }

    /** @param array<string,mixed> $room */
    public static function rateIsConfirmed(array $room): bool
    {
        return (bool) ($room['price']['confirmed'] ?? false);
    }
}
