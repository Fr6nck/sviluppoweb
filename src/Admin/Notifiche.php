<?php

declare(strict_types=1);

namespace ArcoDelVento\Admin;

use ArcoDelVento\App;
use ArcoDelVento\Push\WebPush;
use ArcoDelVento\Storage\JsonStore;

/**
 * Le notifiche sul telefono di chi gestisce il sito: Daniele e il super
 * admin, sui dispositivi dove le hanno attivate (Account → Notifiche).
 *
 * Una notifica parte per una nuova richiesta di prenotazione, per una
 * prenotazione pagata (o con il pagamento da controllare) e per un messaggio
 * del modulo contatti: a tutti i dispositivi iscritti, di tutti e due gli
 * account. Toccandola si apre la richiesta nell'area riservata.
 *
 * Tutto sta in storage/data/notifiche.json: le chiavi VAPID del sito (nascono
 * la prima volta che servono) e le iscrizioni, una per dispositivo, con chi
 * l'ha attivata. Un'iscrizione che il servizio di push dice scaduta (404,
 * 410) si toglie da sola.
 *
 * Il sito spedisce solo ai servizi di push dei browser (Google, Apple,
 * Mozilla, Microsoft), in https: un indirizzo qualunque mandato da un modulo
 * non diventa una richiesta del server verso un altro posto.
 */
final class Notifiche
{
    private const ARCHIVIO = 'notifiche';

    /** Quanti dispositivi per account, e in tutto. */
    public const PER_ACCOUNT = 10;
    public const IN_TUTTO = 30;

    /** @var list<\Closure(): void> le notifiche da spedire dopo aver risposto */
    private static array $coda = [];

    /** I servizi di push dei browser: l'host dell'iscrizione deve finire così. */
    private const SERVIZI = ['fcm.googleapis.com', 'android.googleapis.com', 'push.services.mozilla.com', 'push.apple.com', 'notify.windows.com'];

    public function __construct(
        private readonly App $app,
        private readonly JsonStore $store,
        private readonly bool $consentiHttp = false,
    ) {
    }

    /** Se questo server può spedire notifiche: OpenSSL con le curve ellittiche e cURL. */
    public static function possibili(): bool
    {
        return function_exists('openssl_pkey_derive') && function_exists('curl_init') && in_array('prime256v1', openssl_get_curve_names() ?: [], true);
    }

    /** La chiave pubblica VAPID, base64url: la vuole il browser per iscriversi. */
    public function chiavePubblica(): string
    {
        return $this->vapid()['pubblica'];
    }

    /**
     * Iscrive un dispositivo. Restituisce il problema, o null se è andata.
     */
    public function iscrivi(string $utente, string $endpoint, string $p256dh, string $auth, string $browser): ?string
    {
        $problema = $this->problema($endpoint, $p256dh, $auth);
        if ($problema !== null) {
            return $problema;
        }
        $dispositivo = self::dispositivo($browser);
        $troppi = null;
        $this->store->update(self::ARCHIVIO, function (array $tutto) use ($utente, $endpoint, $p256dh, $auth, $dispositivo, &$troppi): array {
            $altre = array_values(array_filter((array) ($tutto['iscrizioni'] ?? []), static fn ($i): bool => is_array($i) && ($i['endpoint'] ?? '') !== $endpoint));
            $mie = array_filter($altre, static fn (array $i): bool => ($i['utente'] ?? '') === $utente);
            if (count($mie) >= self::PER_ACCOUNT || count($altre) >= self::IN_TUTTO) {
                $troppi = 'Ci sono già troppi dispositivi con le notifiche: togline uno da Account → Notifiche.';

                return $tutto;
            }
            $altre[] = [
                'id' => 'DV-' . strtoupper(bin2hex(random_bytes(4))), 'utente' => $utente, 'endpoint' => $endpoint,
                'p256dh' => $p256dh, 'auth' => $auth, 'dispositivo' => $dispositivo, 'creata' => date('c'), 'usata' => null,
            ];
            $tutto['iscrizioni'] = $altre;

            return $tutto;
        });

        return $troppi;
    }

    /** Toglie un dispositivo, per indirizzo (il browser) o per identificativo (l'elenco in Account). */
    public function togli(string $utente, string $endpoint = '', string $id = ''): bool
    {
        $tolto = false;
        $this->store->update(self::ARCHIVIO, static function (array $tutto) use ($utente, $endpoint, $id, &$tolto): array {
            $tutto['iscrizioni'] = array_values(array_filter((array) ($tutto['iscrizioni'] ?? []), static function ($i) use ($utente, $endpoint, $id, &$tolto): bool {
                $questa = is_array($i) && ($i['utente'] ?? '') === $utente
                    && (($endpoint !== '' && ($i['endpoint'] ?? '') === $endpoint) || ($id !== '' && ($i['id'] ?? '') === $id));
                $tolto = $tolto || $questa;

                return is_array($i) && !$questa;
            }));

            return $tutto;
        });

        return $tolto;
    }

    /**
     * I dispositivi di un account, senza le chiavi.
     *
     * @return list<array{id: string, dispositivo: string, creata: string, usata: ?string, tentata: ?string, errore: ?string, endpoint: string}>
     */
    public function diUtente(string $utente): array
    {
        $out = [];
        foreach ((array) ($this->store->read(self::ARCHIVIO)['iscrizioni'] ?? []) as $i) {
            if (is_array($i) && ($i['utente'] ?? '') === $utente) {
                $out[] = ['id' => (string) $i['id'], 'dispositivo' => (string) $i['dispositivo'], 'creata' => (string) $i['creata'],
                          'usata' => $i['usata'] ?? null, 'tentata' => $i['tentata'] ?? null, 'errore' => $i['errore'] ?? null,
                          'endpoint' => (string) $i['endpoint']];
            }
        }

        return $out;
    }

    /**
     * L'ultima notifica che il sito ha provato a spedire: quando, quale, a
     * quanti dispositivi e com'è andata. La mostra Account → Notifiche: dopo
     * una prenotazione si vede se il sito ci ha provato, e perché non è arrivata.
     *
     * @return array{quando: string, titolo: string, dispositivi: int, spedite: int, errori: list<string>}|null
     */
    public function ultima(): ?array
    {
        $u = $this->store->read(self::ARCHIVIO)['ultima'] ?? null;

        return is_array($u) ? $u : null;
    }

    /**
     * Spedisce una notifica a tutti i dispositivi iscritti (o solo a quelli di
     * un account). Non lancia mai: una notifica che non parte non deve
     * fermare una prenotazione.
     *
     * @return array{spedite: int, errori: list<string>}
     */
    public function invia(string $titolo, string $testo, string $url, string $argomento = '', ?string $soloUtente = null): array
    {
        $esito = ['spedite' => 0, 'errori' => []];
        $adesso = date('c');
        $iscrizioni = [];
        try {
            $iscrizioni = array_values(array_filter((array) ($this->store->read(self::ARCHIVIO)['iscrizioni'] ?? []), static fn ($i): bool => is_array($i)
                && ($soloUtente === null || ($i['utente'] ?? '') === $soloUtente)));
            if ($iscrizioni === [] || !self::possibili()) {
                $this->ricorda($titolo, $adesso, 0, $esito, []);

                return $esito;
            }
            $vapid = $this->vapid();
            $carico = (string) json_encode([
                't' => mb_substr($titolo, 0, 120), 'b' => mb_substr($testo, 0, 240), 'u' => $url, 'g' => $argomento,
                'i' => $this->app->indirizzoCompleto(\ArcoDelVento\I18n\Routes::base() . '/assets/img/logo/app-192.png'),
                'd' => $this->app->indirizzoCompleto(\ArcoDelVento\I18n\Routes::base() . '/assets/img/logo/app-badge-96.png'),
                // Le richieste da leggere: il numero sull'icona dell'app (il «bollo»).
                'n' => $this->daLeggere(),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $soggetto = $this->soggetto();
            $esiti = [];
            foreach ($iscrizioni as $i) {
                if (($problema = $this->problema((string) $i['endpoint'], (string) $i['p256dh'], (string) $i['auth'])) !== null) {
                    $esiti[$i['endpoint']] = $problema;
                    $esito['errori'][] = $i['dispositivo'] . ': ' . $problema;
                    continue;
                }
                $r = WebPush::invia($i, $carico, $vapid, $soggetto, 86400, 'high', $argomento !== '' ? preg_replace('/[^A-Za-z0-9_-]/', '', $argomento) : null);
                if ($r['errore'] === null) {
                    $esito['spedite']++;
                    $esiti[$i['endpoint']] = null;
                } elseif (in_array($r['stato'], [404, 410], true)) {
                    $esiti[$i['endpoint']] = false;
                } else {
                    $esiti[$i['endpoint']] = (string) $r['errore'];
                    $esito['errori'][] = $i['dispositivo'] . ': ' . $r['errore'];
                    error_log('Notifica non spedita (' . $i['dispositivo'] . '): ' . $r['errore']);
                }
            }
            $this->ricorda($titolo, $adesso, count($iscrizioni), $esito, $esiti);
        } catch (\Throwable $e) {
            $esito['errori'][] = $e->getMessage();
            error_log('Notifiche non spedite: ' . $e->getMessage());
            try {
                $this->ricorda($titolo, $adesso, count($iscrizioni), $esito, []);
            } catch (\Throwable) {
            }
        }

        return $esito;
    }

    // ------------------------------------------------------- le notifiche del sito

    /** Una nuova richiesta di prenotazione (pagamento online spento). */
    public function richiesta(string $riferimento, string $nome, string $camera, string $arrivo, string $partenza, int $ospiti): void
    {
        $this->dopo(fn () => $this->invia(
            'Nuova richiesta · ' . $camera,
            $nome . ' · ' . self::date($arrivo, $partenza) . ' · ' . $ospiti . ($ospiti === 1 ? ' ospite' : ' ospiti'),
            $this->admin('richieste/' . rawurlencode($riferimento)),
            $riferimento,
        ));
    }

    /** @param array<string,mixed> $voce una prenotazione pagata (o da controllare) */
    public function pagata(array $voce): void
    {
        $d = (array) ($voce['dati'] ?? []);
        $p = (array) ($d['pagamento'] ?? []);
        $prefisso = !empty($p['prova']) ? 'PROVA · ' : '';
        $titolo = ($p['stato'] ?? '') === 'da controllare' ? 'Pagamento da controllare' : 'Prenotazione pagata';

        $this->dopo(fn () => $this->invia(
            $prefisso . $titolo . ' · ' . ($d['camera'] ?? ''),
            ($d['nome'] ?? '') . ' · ' . self::date((string) ($d['arrivo'] ?? ''), (string) ($d['partenza'] ?? ''))
                . ' · € ' . number_format((float) ($p['pagato'] ?? 0), 2, ',', '.') . ' su SumUp',
            $this->admin('richieste/' . rawurlencode((string) ($voce['id'] ?? ''))),
            (string) ($voce['id'] ?? ''),
        ));
    }

    /** Un messaggio dal modulo contatti. */
    public function messaggio(string $oggetto, string $nome, string $testo, ?string $id): void
    {
        $this->dopo(fn () => $this->invia(
            'Nuovo messaggio · ' . $oggetto,
            $nome . ': ' . mb_substr(trim(preg_replace('/\s+/', ' ', $testo) ?? ''), 0, 120),
            $this->admin($id !== null ? 'richieste/' . rawurlencode($id) : 'richieste'),
            (string) ($id ?? ''),
        ));
    }

    /** La notifica di prova, ai dispositivi di un account: subito, perché l'esito si mostra. */
    public function prova(string $utente): array
    {
        return $this->invia(
            'Notifiche attive',
            'Su questo dispositivo arrivano le nuove richieste, le prenotazioni pagate e i messaggi del sito.',
            $this->admin('account'),
            'prova',
            $utente,
        );
    }

    // ------------------------------------------------------------------ interni

    /**
     * Scrive com'è andata: per ogni dispositivo l'ultimo tentativo (null se è
     * arrivato al servizio di push, il motivo se no; false se il servizio dice
     * che l'iscrizione non c'è più, e allora si toglie), e l'ultima notifica del sito.
     *
     * @param array{spedite: int, errori: list<string>} $esito
     * @param array<string, string|false|null> $esiti endpoint => esito
     */
    private function ricorda(string $titolo, string $adesso, int $dispositivi, array $esito, array $esiti): void
    {
        $this->store->update(self::ARCHIVIO, static function (array $tutto) use ($titolo, $adesso, $dispositivi, $esito, $esiti): array {
            $tutto['iscrizioni'] = array_values(array_filter(array_map(static function ($i) use ($esiti, $adesso) {
                if (!is_array($i) || !array_key_exists($i['endpoint'] ?? '', $esiti)) {
                    return $i;
                }
                $e = $esiti[$i['endpoint']];
                if ($e === false) {
                    return null;
                }
                $i['tentata'] = $adesso;
                $i['errore'] = $e === null ? null : mb_substr($e, 0, 300);
                if ($e === null) {
                    $i['usata'] = $adesso;
                }

                return $i;
            }, (array) ($tutto['iscrizioni'] ?? [])), 'is_array'));
            $tutto['ultima'] = ['quando' => $adesso, 'titolo' => mb_substr($titolo, 0, 120), 'dispositivi' => $dispositivi,
                                'spedite' => $esito['spedite'], 'errori' => array_slice($esito['errori'], 0, 5)];

            return $tutto;
        });
    }

    /** Quante richieste ci sono da leggere, per il numero sull'icona. */
    private function daLeggere(): int
    {
        try {
            return $this->app->inbox()->countNew();
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Spedisce dopo aver risposto: chi prenota non aspetta i servizi di push.
     * In fondo alla richiesta si chiude la risposta (fastcgi_finish_request,
     * o litespeed_finish_request su Hostinger), si chiude la sessione, che
     * altrimenti terrebbe in attesa la pagina dopo, e solo allora si spedisce.
     * Da uno script (php in riga di comando) spedisce subito.
     *
     * @param \Closure(): mixed $spedisci
     */
    private function dopo(\Closure $spedisci): void
    {
        if (PHP_SAPI === 'cli') {
            $spedisci();

            return;
        }
        if (self::$coda === []) {
            register_shutdown_function(static function (): void {
                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_write_close();
                }
                if (function_exists('fastcgi_finish_request')) {
                    fastcgi_finish_request();
                } elseif (function_exists('litespeed_finish_request')) {
                    litespeed_finish_request();
                }
                foreach (self::$coda as $f) {
                    try {
                        $f();
                    } catch (\Throwable $e) {
                        error_log('Notifiche non spedite: ' . $e->getMessage());
                    }
                }
                self::$coda = [];
            });
        }
        self::$coda[] = static function () use ($spedisci): void {
            $spedisci();
        };
    }

    /** «Pixel 8 · Chrome», «iPhone · Safari»… dal browser che si iscrive. */
    public static function dispositivo(string $ua): string
    {
        $cosa = match (true) {
            str_contains($ua, 'iPhone') => 'iPhone',
            str_contains($ua, 'iPad') => 'iPad',
            str_contains($ua, 'Android') => (preg_match('/Android [\d.]+; ([^;)]+?)(?: Build|\))/', $ua, $m) === 1 && !in_array(trim($m[1]), ['K', 'wv'], true) ? trim($m[1]) : 'Android'),
            str_contains($ua, 'Macintosh') => 'Mac',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Linux') => 'Linux',
            default => 'Dispositivo',
        };
        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'Firefox/') || str_contains($ua, 'FxiOS') => 'Firefox',
            str_contains($ua, 'SamsungBrowser') => 'Samsung Internet',
            str_contains($ua, 'Chrome/') || str_contains($ua, 'CriOS') => 'Chrome',
            str_contains($ua, 'Safari/') || str_contains($ua, 'iPhone') => 'Safari',
            default => '',
        };

        return mb_substr($cosa . ($browser !== '' ? ' · ' . $browser : ''), 0, 60);
    }

    /** Che cosa non va in un'iscrizione, o null. */
    private function problema(string $endpoint, string $p256dh, string $auth): ?string
    {
        $parti = parse_url($endpoint);
        $host = strtolower((string) ($parti['host'] ?? ''));
        $schema = strtolower((string) ($parti['scheme'] ?? ''));
        $diProva = $this->consentiHttp && $schema === 'http' && in_array($host, ['127.0.0.1', 'localhost'], true);
        $servizio = $schema === 'https' && array_filter(self::SERVIZI, static fn (string $s): bool => $host === $s || str_ends_with($host, '.' . $s)) !== [];
        if (strlen($endpoint) > 1000 || (!$servizio && !$diProva)) {
            return 'Questo browser usa un servizio di notifiche che il sito non conosce.';
        }
        $chiave = WebPush::da64($p256dh);
        if (strlen($chiave) !== 65 || $chiave[0] !== "\x04" || strlen(WebPush::da64($auth)) !== 16) {
            return 'L\'iscrizione del browser non è valida: riprova.';
        }
        try {
            WebPush::chiaveDaPunto($chiave);
        } catch (\Throwable) {
            return 'L\'iscrizione del browser non è valida: riprova.';
        }

        return null;
    }

    /** @return array{privata: string, pubblica: string} */
    private function vapid(): array
    {
        $v = (array) ($this->store->read(self::ARCHIVIO)['vapid'] ?? []);
        if (!isset($v['privata'], $v['pubblica']) || !is_string($v['privata']) || openssl_pkey_get_private($v['privata']) === false) {
            $nuove = WebPush::generaChiavi();
            $v = (array) $this->store->update(self::ARCHIVIO, static function (array $tutto) use ($nuove): array {
                if (!isset($tutto['vapid']['privata']) || openssl_pkey_get_private((string) $tutto['vapid']['privata']) === false) {
                    $tutto['vapid'] = $nuove;
                }

                return $tutto;
            })['vapid'];
        }

        return ['privata' => (string) $v['privata'], 'pubblica' => (string) $v['pubblica']];
    }

    /** Chi spedisce, per i servizi di push: l'indirizzo del sito in https, altrimenti la casella della struttura. */
    private function soggetto(): string
    {
        $sito = (string) $this->app->config('app.url');
        if (preg_match('#^https://[^/]+#i', $sito, $m) === 1) {
            return $m[0];
        }
        $email = (string) ($this->app->settings()['contacts']['email'] ?? '');

        return 'mailto:' . ($email !== '' ? $email : 'info@arcodelvento.it');
    }

    private function admin(string $sotto): string
    {
        return $this->app->indirizzoCompleto(\ArcoDelVento\I18n\Routes::base() . '/admin/' . ltrim($sotto, '/'));
    }

    /** «21–24 dic» o «30 nov – 2 dic». */
    private static function date(string $arrivo, string $partenza): string
    {
        $mesi = ['gen', 'feb', 'mar', 'apr', 'mag', 'giu', 'lug', 'ago', 'set', 'ott', 'nov', 'dic'];
        $a = strtotime($arrivo);
        $p = strtotime($partenza);
        if (!$a || !$p) {
            return $arrivo . ' → ' . $partenza;
        }

        return date('n', $a) === date('n', $p)
            ? date('j', $a) . '–' . date('j', $p) . ' ' . $mesi[(int) date('n', $p) - 1]
            : date('j', $a) . ' ' . $mesi[(int) date('n', $a) - 1] . ' – ' . date('j', $p) . ' ' . $mesi[(int) date('n', $p) - 1];
    }
}
