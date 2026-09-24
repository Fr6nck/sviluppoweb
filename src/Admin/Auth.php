<?php

declare(strict_types=1);

namespace ArcoDelVento\Admin;

use ArcoDelVento\Storage\JsonStore;

/**
 * Chi entra nell'area riservata.
 *
 * Un solo account, quello di chi gestisce la casa. La password non sta da
 * nessuna parte in chiaro: in storage/data/admin.json c'è solo il suo hash.
 *
 * LA PRIMA VOLTA. Finché l'account non esiste, /admin chiede di crearlo — ma
 * solo a chi conosce ADMIN_SETUP_TOKEN, che sta nel file .env. Chi può leggere
 * il .env ha già l'FTP del sito: è la stessa persona che lo sta installando.
 * Senza gettone la creazione è chiusa, così nessuno può arrivare per primo e
 * prendersi il pannello di un sito appena caricato.
 *
 * PASSWORD DIMENTICATA. Si cancella storage/data/admin.json via FTP e si
 * rifà la prima volta, con il gettone.
 *
 * TENTATIVI. Cinque password sbagliate dallo stesso indirizzo in un quarto
 * d'ora chiudono l'accesso a quell'indirizzo per un quarto d'ora. Gli
 * indirizzi si salvano solo come impronta, non in chiaro.
 */
final class Auth
{
    private const SESSIONE      = '_adv_admin';
    private const INATTIVITA    = 7200;     // due ore senza fare niente: si esce
    private const DURATA_MASSIMA = 43200;   // dodici ore in tutto: si rientra
    private const TENTATIVI     = 5;
    private const FINESTRA      = 900;      // un quarto d'ora
    private const LUNGHEZZA_MINIMA = 10;

    /** Un hash vero di una password a caso che nessuno conosce: serve solo a spendere lo stesso tempo. */
    private const HASH_FITTIZIO = '$2y$12$pSKasRQ3.CfVSuDfMK5dP.4VP4n4TIveE3X4eE4sel9pQHTEGP95S';

    public function __construct(
        private readonly JsonStore $store,
        private readonly string $gettoneConfigurazione,
    ) {
    }

    // ------------------------------------------------------------ l'account

    public function hasAccount(): bool
    {
        $a = $this->store->read('admin');

        return !empty($a['utente']) && !empty($a['hash']);
    }

    /** La creazione dell'account è possibile solo con un gettone lungo abbastanza. */
    public function setupAvailable(): bool
    {
        return !$this->hasAccount() && strlen($this->gettoneConfigurazione) >= 16;
    }

    public function setupTokenValid(string $gettone): bool
    {
        return $this->setupAvailable() && hash_equals($this->gettoneConfigurazione, $gettone);
    }

    /** @return list<string> gli errori, vuota se la password va bene */
    public function passwordProblems(string $utente, string $password, string $ripetuta): array
    {
        $problemi = [];
        if (mb_strlen($password) < self::LUNGHEZZA_MINIMA) {
            $problemi[] = 'La password deve avere almeno ' . self::LUNGHEZZA_MINIMA . ' caratteri.';
        }
        if ($password !== $ripetuta) {
            $problemi[] = 'Le due password non coincidono.';
        }
        if ($utente !== '' && mb_strtolower($password) === mb_strtolower($utente)) {
            $problemi[] = 'La password non può essere uguale al nome utente.';
        }

        return $problemi;
    }

    public function createAccount(string $utente, string $password): void
    {
        $this->store->write('admin', [
            'utente'     => $utente,
            'hash'       => password_hash($password, PASSWORD_DEFAULT),
            'creato'     => date('c'),
            'modificato' => date('c'),
        ], false);
    }

    public function changePassword(string $nuova): void
    {
        $a = $this->store->read('admin');
        $a['hash']       = password_hash($nuova, PASSWORD_DEFAULT);
        $a['modificato'] = date('c');
        $this->store->write('admin', $a, false);
    }

    public function username(): string
    {
        return (string) ($this->store->read('admin')['utente'] ?? '');
    }

    /** Verifica le credenziali, senza toccare la sessione. */
    public function verify(string $utente, string $password): bool
    {
        $a = $this->store->read('admin');
        // Anche con il nome sbagliato si calcola un hash: i tempi di risposta
        // non devono dire se il nome utente esiste.
        $hash = (string) ($a['hash'] ?? self::HASH_FITTIZIO);
        $ok   = password_verify($password, $hash);

        return $ok && hash_equals(mb_strtolower((string) ($a['utente'] ?? '')), mb_strtolower($utente));
    }

    // ------------------------------------------------------------ la sessione

    public function login(string $utente): void
    {
        // Un identificativo nuovo a ogni ingresso: quello di prima, se qualcuno
        // l'avesse visto, non vale più.
        session_regenerate_id(true);
        $_SESSION[self::SESSIONE] = ['utente' => $utente, 'dal' => time(), 'ultimo' => time()];
        $this->salvaAccesso();
    }

    public function logout(): void
    {
        unset($_SESSION[self::SESSIONE]);
        session_regenerate_id(true);
    }

    /** Il nome di chi è dentro, o null. Rinnova l'ultima attività. */
    public function current(): ?string
    {
        $s = $_SESSION[self::SESSIONE] ?? null;
        if (!is_array($s) || empty($s['utente'])) {
            return null;
        }
        $adesso = time();
        if ($adesso - (int) $s['ultimo'] > self::INATTIVITA || $adesso - (int) $s['dal'] > self::DURATA_MASSIMA) {
            unset($_SESSION[self::SESSIONE]);

            return null;
        }
        $_SESSION[self::SESSIONE]['ultimo'] = $adesso;

        return (string) $s['utente'];
    }

    // ------------------------------------------------------------ i tentativi

    public function isLockedOut(string $ip): bool
    {
        $voce = $this->tentativi()[$this->impronta($ip)] ?? null;

        return is_array($voce) && (int) ($voce['bloccato_fino'] ?? 0) > time();
    }

    public function minutesLocked(string $ip): int
    {
        $voce = $this->tentativi()[$this->impronta($ip)] ?? [];

        return max(1, (int) ceil(((int) ($voce['bloccato_fino'] ?? 0) - time()) / 60));
    }

    public function recordFailure(string $ip): void
    {
        $chiave = $this->impronta($ip);
        $this->store->update('accessi', function (array $tutti) use ($chiave): array {
            $adesso = time();
            // si tiene solo quello che conta ancora
            $tutti = array_filter($tutti, static fn ($v): bool => is_array($v)
                && ((int) ($v['bloccato_fino'] ?? 0) > $adesso || $adesso - (int) ($v['primo'] ?? 0) < self::FINESTRA));
            $v = $tutti[$chiave] ?? ['falliti' => 0, 'primo' => $adesso];
            if ($adesso - (int) $v['primo'] >= self::FINESTRA) {
                $v = ['falliti' => 0, 'primo' => $adesso];
            }
            $v['falliti']++;
            if ($v['falliti'] >= self::TENTATIVI) {
                $v['bloccato_fino'] = $adesso + self::FINESTRA;
            }
            $tutti[$chiave] = $v;

            return $tutti;
        });
        // Una risposta lenta per chi sbaglia: poco per una persona, molto per
        // un programma che prova mille password.
        usleep(800000);
    }

    public function clearFailures(string $ip): void
    {
        $chiave = $this->impronta($ip);
        $this->store->update('accessi', static function (array $tutti) use ($chiave): array {
            unset($tutti[$chiave]);

            return $tutti;
        });
    }

    /** @return array<string,mixed> */
    private function tentativi(): array
    {
        return $this->store->read('accessi');
    }

    private function impronta(string $ip): string
    {
        return substr(hash('sha256', 'adv|' . $ip . '|' . $this->gettoneConfigurazione), 0, 24);
    }

    private function salvaAccesso(): void
    {
        $a = $this->store->read('admin');
        $a['ultimo_accesso'] = date('c');
        $this->store->write('admin', $a, false);
    }
}
