<?php

declare(strict_types=1);

namespace ArcoDelVento\Booking;

use ArcoDelVento\Repository\RoomRepositoryInterface;
use ArcoDelVento\Storage\JsonStore;

/**
 * I calendari delle altre piattaforme (Booking.com, Airbnb), camera per camera.
 *
 * Ogni camera può avere il link iCal del suo calendario su Booking.com
 * (campo «ical_import», scritto dall'area riservata). Il sito lo legge e le
 * notti che lì risultano prenotate le tiene occupate anche qui: così una
 * camera venduta su Booking non si vende una seconda volta dal sito.
 *
 * Si legge quando serve: alla prima ricerca di date dopo OGNI secondi, e
 * quando dal pannello si chiede «Aggiorna adesso». Non c'è un cron da
 * impostare. Se Booking non risponde, restano buone le notti lette l'ultima
 * volta, e il pannello mostra l'errore.
 *
 * In senso inverso, il sito pubblica il suo calendario per ogni camera
 * (/ical/<camera>/<gettone>.ics, vedi IcalController): Booking.com lo importa
 * e blocca le notti prenotate qui. Il gettone è uno per tutte le camere e
 * nasce la prima volta che serve.
 *
 * Di Booking si tengono solo le date, mai i nomi: sono ospiti di un'altra casa.
 *
 * SOSPESO. L'interruttore del channel manager (Admin\InterruttoreChannelManager,
 * una funzione di prova) può sospendere l'importazione: allora le notti di
 * Booking non si leggono e non contano, e i link restano salvati.
 */
final class CalendariEsterni
{
    /** Ogni quanto si rilegge un calendario, in secondi. */
    public const OGNI = 20 * 60;

    /** Quanto si aspetta Booking, in secondi, per tutte le camere insieme. */
    public const TIMEOUT = 6;

    private const ARCHIVIO   = 'calendari-esterni';
    private const BYTE_MAX   = 2 * 1024 * 1024;

    public function __construct(
        private readonly JsonStore $store,
        private readonly RoomRepositoryInterface $rooms,
        private readonly bool $soloHttps = true,
    ) {
    }

    // ============================================================== lettura

    /** Le camere con un calendario collegato: ref => link. @return array<string,string> */
    public function collegate(): array
    {
        $out = [];
        foreach ($this->rooms->all() as $camera) {
            $url = trim((string) ($camera['ical_import'] ?? ''));
            if ($url !== '' && self::problemaUrl($url, $this->soloHttps) === null) {
                $out[(string) $camera['ref']] = $url;
            }
        }

        return $out;
    }

    /**
     * Le notti occupate sulle altre piattaforme, nel formato di
     * Inbox::nottiOccupate(). Prima rilegge i calendari non più freschi.
     *
     * @return list<array{id: string, ref: string, arrivo: string, partenza: string}>
     */
    public function occupate(bool $aggiorna = true): array
    {
        $collegate = $this->collegate();
        if ($collegate === [] || $this->sospeso()) {
            return [];
        }
        if ($aggiorna) {
            $vecchi = [];
            $cache  = $this->cache();
            foreach ($collegate as $ref => $url) {
                $c = $cache[$ref] ?? null;
                if ($c === null || ($c['url'] ?? '') !== $url || time() - (strtotime((string) ($c['tentato'] ?? '')) ?: 0) >= self::OGNI) {
                    $vecchi[$ref] = $url;
                }
            }
            if ($vecchi !== []) {
                $this->leggi($vecchi);
            }
        }

        $oggi = date('Y-m-d');
        $out  = [];
        foreach ($this->cache() as $ref => $c) {
            if (!isset($collegate[$ref])) {
                continue;
            }
            foreach ((array) ($c['periodi'] ?? []) as $p) {
                if (is_array($p) && ($p['partenza'] ?? '') > $oggi) {
                    $out[] = ['id' => 'ical:' . $ref . ':' . $p['arrivo'], 'ref' => $ref, 'arrivo' => (string) $p['arrivo'], 'partenza' => (string) $p['partenza'], 'fonte' => 'booking'];
                }
            }
        }

        return $out;
    }

    /**
     * Rilegge adesso il calendario di una camera (o di tutte). Con $url si
     * legge quel link anche se la camera, in memoria, ne ha ancora un altro:
     * serve subito dopo il salvataggio dal pannello.
     *
     * @return array<string,mixed>|null lo stato della camera chiesta
     */
    public function aggiorna(?string $ref = null, ?string $url = null): ?array
    {
        $collegate = $this->collegate();
        if ($ref !== null && $url !== null) {
            $collegate[$ref] = $url;
        }
        $daLeggere = $ref === null ? $collegate : array_intersect_key($collegate, [$ref => true]);
        if ($daLeggere !== [] && !$this->sospeso()) {
            $this->leggi($daLeggere);
        }

        return $ref !== null ? $this->stato($ref, $collegate[$ref] ?? null) : null;
    }

    /**
     * Com'è messo il calendario di una camera, per il pannello.
     *
     * @return array{url: string, esito: 'ok'|'errore'|'mai'|'sospeso', aggiornato: ?string, tentato: ?string,
     *               errore: ?string, periodi: list<array{arrivo: string, partenza: string}>}|null
     *         null se la camera non ha un calendario
     */
    public function stato(string $ref, ?string $url = null): ?array
    {
        $url ??= $this->collegate()[$ref] ?? null;
        if ($url === null) {
            return null;
        }
        if ($this->sospeso()) {
            return ['url' => $url, 'esito' => 'sospeso', 'aggiornato' => null, 'tentato' => null, 'errore' => null, 'periodi' => []];
        }
        $c = $this->cache()[$ref] ?? [];
        if (($c['url'] ?? '') !== $url) {
            $c = [];
        }
        $oggi = date('Y-m-d');

        return [
            'url'        => $url,
            'esito'      => $c === [] ? 'mai' : (($c['errore'] ?? null) === null ? 'ok' : 'errore'),
            'aggiornato' => $c['aggiornato'] ?? null,
            'tentato'    => $c['tentato'] ?? null,
            'errore'     => $c['errore'] ?? null,
            'periodi'    => array_values(array_filter((array) ($c['periodi'] ?? []), static fn ($p): bool => is_array($p) && ($p['partenza'] ?? '') > $oggi)),
        ];
    }

    /** Il gettone dei calendari pubblicati dal sito: nasce la prima volta. */
    public function gettone(): string
    {
        $g = (string) ($this->store->read(self::ARCHIVIO)['gettone'] ?? '');
        if (preg_match('/^[a-f0-9]{32}$/', $g) !== 1) {
            $nuovo = bin2hex(random_bytes(16));
            $g = (string) $this->store->update(self::ARCHIVIO, static function (array $tutto) use ($nuovo): array {
                if (preg_match('/^[a-f0-9]{32}$/', (string) ($tutto['gettone'] ?? '')) !== 1) {
                    $tutto['gettone'] = $nuovo;
                }

                return $tutto;
            })['gettone'];
        }

        return $g;
    }

    public function soloHttps(): bool
    {
        return $this->soloHttps;
    }

    // ================================================ sospensione (prova)

    /** L'importazione è sospesa dall'interruttore del channel manager? */
    public function sospeso(): bool
    {
        return !empty($this->store->read(self::ARCHIVIO)['sospeso']);
    }

    /** Da quando è sospesa, o null. */
    public function sospesoDal(): ?string
    {
        $tutto = $this->store->read(self::ARCHIVIO);

        return !empty($tutto['sospeso']) ? (string) ($tutto['sospeso_il'] ?? '') : null;
    }

    /**
     * Sospende l'importazione e toglie tutte le notti importate. I link delle
     * camere non si toccano: stanno con le camere, non qui.
     *
     * @return array<string,int> quanti periodi importati ha tolto, camera per camera
     */
    public function sospendi(): array
    {
        $tolti = [];
        $this->store->update(self::ARCHIVIO, static function (array $tutto) use (&$tolti): array {
            foreach ((array) ($tutto['camere'] ?? []) as $ref => $c) {
                if (!is_array($c)) {
                    continue;
                }
                $tolti[(string) $ref] = count((array) ($c['periodi'] ?? []));
                $tutto['camere'][$ref] = ['url' => (string) ($c['url'] ?? ''), 'periodi' => [], 'aggiornato' => null, 'tentato' => null, 'errore' => null];
            }
            $tutto['sospeso']    = true;
            $tutto['sospeso_il'] = date('c');

            return $tutto;
        });

        return $tolti;
    }

    /** Riprende l'importazione: le notti tornano alla prossima lettura (aggiorna()). */
    public function riprendi(): void
    {
        $this->store->update(self::ARCHIVIO, static function (array $tutto): array {
            unset($tutto['sospeso'], $tutto['sospeso_il']);

            return $tutto;
        });
    }

    /**
     * Che cosa non va in un link, detto a chi lo incolla. Null se va bene.
     * Solo https, verso un nome di dominio: il sito non deve farsi mandare a
     * leggere indirizzi interni al server.
     */
    public static function problemaUrl(string $url, bool $soloHttps = true): ?string
    {
        if (mb_strlen($url) > 500) {
            return 'troppo lungo.';
        }
        $parti = parse_url($url);
        $schema = strtolower((string) ($parti['scheme'] ?? ''));
        $host   = strtolower((string) ($parti['host'] ?? ''));
        if ($parti === false || $host === '' || !in_array($schema, ['https', 'http'], true)) {
            return 'serve un indirizzo completo, che cominci con https://.';
        }
        if ($schema === 'http' && $soloHttps) {
            return 'serve un indirizzo https, non http.';
        }
        if ($soloHttps && (filter_var($host, FILTER_VALIDATE_IP) !== false || !str_contains($host, '.') || $host === 'localhost')) {
            return 'serve un nome di dominio, come ical.booking.com.';
        }
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return 'non è un indirizzo valido.';
        }

        return null;
    }

    // ============================================================== interni

    /** @return array<string, array<string,mixed>> ref => cache */
    private function cache(): array
    {
        return array_filter((array) ($this->store->read(self::ARCHIVIO)['camere'] ?? []), 'is_array');
    }

    /**
     * Scarica i calendari dati, tutti insieme, e scrive quello che ha trovato.
     * Un errore non cancella le notti lette la volta prima: restano, con
     * l'errore accanto.
     *
     * @param array<string,string> $urls ref => link
     */
    private function leggi(array $urls): void
    {
        $adesso = date('c');
        // Prima si segna il tentativo: una seconda richiesta arrivata nel
        // frattempo non riparte a scaricare le stesse cose.
        $this->store->update(self::ARCHIVIO, static function (array $tutto) use ($urls, $adesso): array {
            foreach ($urls as $ref => $url) {
                $c = (array) ($tutto['camere'][$ref] ?? []);
                if (($c['url'] ?? '') !== $url) {
                    $c = ['url' => $url];
                }
                $c['tentato'] = $adesso;
                $tutto['camere'][$ref] = $c;
            }

            return $tutto;
        });

        $esiti = $this->scarica($urls);
        $oggi  = date('Y-m-d');
        $collegate = $this->collegate() + $urls;

        $this->store->update(self::ARCHIVIO, static function (array $tutto) use ($urls, $esiti, $oggi, $adesso, $collegate): array {
            foreach ($urls as $ref => $url) {
                $c = (array) ($tutto['camere'][$ref] ?? ['url' => $url]);
                $e = $esiti[$ref] ?? ['errore' => 'nessuna risposta'];
                if (($e['errore'] ?? null) === null) {
                    $c['periodi']    = Ical::periodi((string) $e['corpo'], $oggi);
                    $c['aggiornato'] = $adesso;
                    $c['errore']     = null;
                } else {
                    $c['errore'] = (string) $e['errore'];
                }
                $c['url'] = $url;
                $tutto['camere'][$ref] = $c;
            }
            // Le camere che non hanno più un calendario non lasciano tracce.
            $tutto['camere'] = array_intersect_key((array) ($tutto['camere'] ?? []), $collegate);

            return $tutto;
        });
    }

    /**
     * @param array<string,string> $urls ref => link
     * @return array<string, array{corpo?: string, errore?: ?string}>
     */
    private function scarica(array $urls): array
    {
        if (!function_exists('curl_multi_init')) {
            return array_map(static fn (): array => ['errore' => 'il server non ha l\'estensione cURL di PHP'], $urls);
        }
        $multi = curl_multi_init();
        $h = [];
        foreach ($urls as $ref => $url) {
            $c = curl_init($url);
            curl_setopt_array($c, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 3,
                CURLOPT_PROTOCOLS      => $this->soloHttps ? CURLPROTO_HTTPS : CURLPROTO_HTTPS | CURLPROTO_HTTP,
                CURLOPT_REDIR_PROTOCOLS => $this->soloHttps ? CURLPROTO_HTTPS : CURLPROTO_HTTPS | CURLPROTO_HTTP,
                CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_TIMEOUT        => self::TIMEOUT,
                CURLOPT_MAXFILESIZE    => self::BYTE_MAX,
                CURLOPT_USERAGENT      => 'ArcoDelVento-iCal/1.0',
                CURLOPT_HTTPHEADER     => ['Accept: text/calendar, text/plain;q=0.8, */*;q=0.5'],
            ]);
            curl_multi_add_handle($multi, $c);
            $h[$ref] = $c;
        }
        do {
            $stato = curl_multi_exec($multi, $attivi);
            if ($attivi) {
                curl_multi_select($multi, 1.0);
            }
        } while ($attivi && $stato === CURLM_OK);

        $out = [];
        foreach ($h as $ref => $c) {
            $corpo  = (string) curl_multi_getcontent($c);
            $codice = (int) curl_getinfo($c, CURLINFO_RESPONSE_CODE);
            $errore = curl_error($c);
            curl_multi_remove_handle($multi, $c);
            curl_close($c);
            $out[$ref] = match (true) {
                $errore !== ''  => ['errore' => 'non risponde: ' . $errore],
                $codice !== 200 => ['errore' => 'ha risposto ' . $codice . ($codice === 404 ? ': il link non esiste più, prendine uno nuovo da Booking.com' : '')],
                !Ical::sembraCalendario($corpo) => ['errore' => 'la risposta non è un calendario iCal: controlla il link'],
                default         => ['corpo' => $corpo, 'errore' => null],
            };
        }
        curl_multi_close($multi);

        return $out;
    }
}
