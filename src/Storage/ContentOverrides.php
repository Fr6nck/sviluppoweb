<?php

declare(strict_types=1);

namespace ArcoDelVento\Storage;

/**
 * Le modifiche fatte dall'area riservata, sovrapposte ai contenuti di partenza.
 *
 * I file in content/ restano quello che sono — il punto di partenza, sotto
 * controllo di versione. Quello che Daniele cambia dall'area riservata sta in
 * storage/data e vince. Due vantaggi: ricaricare i file del sito via FTP non
 * cancella mai una modifica fatta dal pannello, e «torna al testo originale»
 * vuol dire semplicemente togliere la modifica.
 *
 * Forme dei dati:
 *   impostazioni.json   { "contacts.phone": "+39…", "stay.parking": {"it":…,"en":…} }
 *   camere.json         { "camera-01": { "rates": {"1":100,"2":110}, "size_sqm": 18 } }
 *   testi-it.json       { "home.hero.title": "…" }        (e testi-en.json)
 *   domande.json        { "voci": [ … ] }                  (sostituisce tutte le domande)
 */
final class ContentOverrides
{
    public function __construct(private readonly JsonStore $store)
    {
    }

    public function store(): JsonStore
    {
        return $this->store;
    }

    /**
     * @param array<string,mixed> $base
     * @return array<string,mixed>
     */
    public function settings(array $base): array
    {
        foreach ($this->store->read('impostazioni') as $percorso => $valore) {
            $base = self::setPath($base, (string) $percorso, $valore);
        }

        return $base;
    }

    /**
     * @param list<array<string,mixed>> $base
     * @return list<array<string,mixed>>
     */
    public function rooms(array $base): array
    {
        $modifiche = $this->store->read('camere');
        foreach ($base as $i => $camera) {
            $ref = (string) ($camera['ref'] ?? '');
            if ($ref !== '' && isset($modifiche[$ref]) && is_array($modifiche[$ref])) {
                // Campo per campo, e ogni campo per intero: le tariffe si
                // sostituiscono tutte insieme, mai fuse con quelle di partenza —
                // una tariffa tolta dal pannello deve sparire davvero.
                $campi = $modifiche[$ref];
                if (isset($campi['rates']) && is_array($campi['rates'])) {
                    $tariffe = [];
                    foreach ($campi['rates'] as $ospiti => $prezzo) {
                        $tariffe[(int) $ospiti] = (float) $prezzo;
                    }
                    ksort($tariffe);
                    $campi['rates'] = $tariffe;
                }
                $base[$i] = array_replace($camera, $campi);
            }
        }

        return $base;
    }

    /**
     * @param array<string,mixed> $catalogo
     * @return array<string,mixed>
     */
    public function translations(string $lingua, array $catalogo): array
    {
        foreach ($this->store->read('testi-' . $lingua) as $chiave => $testo) {
            if (is_string($testo)) {
                $catalogo = self::setPath($catalogo, (string) $chiave, $testo);
            }
        }

        return $catalogo;
    }

    /**
     * @param list<array<string,mixed>> $base
     * @return list<array<string,mixed>>
     */
    public function faq(array $base): array
    {
        $salvate = $this->store->read('domande');

        return isset($salvate['voci']) && is_array($salvate['voci']) ? array_values($salvate['voci']) : $base;
    }

    /**
     * Imposta un valore a un percorso a punti, creando i livelli che mancano.
     *
     * @param array<string,mixed> $albero
     * @return array<string,mixed>
     */
    public static function setPath(array $albero, string $percorso, mixed $valore): array
    {
        $pezzi = explode('.', $percorso);
        $nodo  = &$albero;
        foreach ($pezzi as $n => $pezzo) {
            if ($n === count($pezzi) - 1) {
                $nodo[$pezzo] = $valore;
                break;
            }
            if (!isset($nodo[$pezzo]) || !is_array($nodo[$pezzo])) {
                $nodo[$pezzo] = [];
            }
            $nodo = &$nodo[$pezzo];
        }
        unset($nodo);

        return $albero;
    }

    /** Il valore a un percorso a punti, o null. */
    public static function getPath(array $albero, string $percorso): mixed
    {
        $nodo = $albero;
        foreach (explode('.', $percorso) as $pezzo) {
            if (!is_array($nodo) || !array_key_exists($pezzo, $nodo)) {
                return null;
            }
            $nodo = $nodo[$pezzo];
        }

        return $nodo;
    }
}
