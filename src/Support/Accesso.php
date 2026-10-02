<?php

declare(strict_types=1);

namespace ArcoDelVento\Support;

use ArcoDelVento\Storage\JsonStore;

/**
 * Il sito chiuso da un codice d'accesso, per il periodo di anteprima.
 *
 * Non è un sipario dipinto sopra le pagine: finché il browser non ha dato il
 * codice, il server non manda nessuna pagina del sito, solo quella d'accesso.
 * Dato il codice, il browser riceve un cookie firmato che vale trenta giorni;
 * cambiare il codice lo invalida.
 *
 * Da dove viene lo stato, in ordine:
 *   1. SITE_ACCESS_CODE=off nel .env: spento, qualunque cosa dica il pannello;
 *   2. l'area riservata (storage/data/accesso.json): acceso o spento, e il codice;
 *   3. altrimenti acceso, con il codice di config/config.php (o del .env).
 */
final class Accesso
{
    public const COOKIE = 'adv_accesso';
    public const GIORNI = 30;
    private const ARCHIVIO = 'accesso';

    /** @var array<string,mixed>|null */
    private ?array $dati = null;

    public function __construct(
        private readonly JsonStore $store,
        private readonly string $codicePredefinito,
    ) {
    }

    /** Spento dal .env: l'area riservata non lo può riaccendere. */
    public function spentoDalFile(): bool
    {
        return in_array(strtolower($this->codicePredefinito), ['off', 'no', 'false', '0'], true);
    }

    public function attivo(): bool
    {
        if ($this->spentoDalFile()) {
            return false;
        }
        $dati = $this->dati();

        return isset($dati['attivo']) ? (bool) $dati['attivo'] && $this->codice() !== '' : $this->codice() !== '';
    }

    public function codice(): string
    {
        $dal = trim((string) ($this->dati()['codice'] ?? ''));

        return $dal !== '' ? $dal : ($this->spentoDalFile() ? '' : $this->codicePredefinito);
    }

    /** Il codice è stato cambiato dall'area riservata, o è quello di partenza? */
    public function dalPannello(): bool
    {
        return isset($this->dati()['attivo']);
    }

    /**
     * Quello che ha scritto il visitatore è il codice? Senza badare a
     * maiuscole e spazi ai lati: è un codice da dettare al telefono.
     */
    public function giusto(string $inserito): bool
    {
        $codice = $this->codice();

        return $codice !== '' && hash_equals(mb_strtolower($codice), mb_strtolower(trim($inserito)));
    }

    /** Il valore del cookie per il codice di adesso. */
    public function gettone(): string
    {
        return hash_hmac('sha256', 'accesso|' . mb_strtolower($this->codice()), $this->sale());
    }

    public function sbloccato(?string $cookie): bool
    {
        return is_string($cookie) && $cookie !== '' && $this->codice() !== '' && hash_equals($this->gettone(), $cookie);
    }

    public function salva(bool $attivo, string $codice): void
    {
        $this->store->write(self::ARCHIVIO, [
            'attivo'     => $attivo,
            'codice'     => trim($codice),
            'aggiornato' => date('c'),
        ] + ['sale' => $this->sale()], false);
        $this->dati = null;
    }

    /** @return array<string,mixed> */
    private function dati(): array
    {
        return $this->dati ??= $this->store->read(self::ARCHIVIO);
    }

    /**
     * Il segreto con cui si firma il cookie: nasce la prima volta e resta in
     * storage/data. Se non si può scrivere, si ricava dal percorso del sito:
     * meno segreto, ma il cookie resta legato a questo server.
     */
    private function sale(): string
    {
        $sale = (string) ($this->dati()['sale'] ?? '');
        if ($sale !== '') {
            return $sale;
        }
        $sale = bin2hex(random_bytes(16));
        try {
            $this->store->update(self::ARCHIVIO, static function (array $d) use (&$sale): array {
                if (!empty($d['sale'])) {
                    $sale = (string) $d['sale'];

                    return $d;
                }
                $d['sale'] = $sale;

                return $d;
            }, false);
            $this->dati = null;
        } catch (\Throwable) {
            $sale = hash('sha256', __DIR__ . php_uname('n'));
        }

        return $sale;
    }
}
