<?php

declare(strict_types=1);

namespace ArcoDelVento\Storage;

/**
 * Un archivio di file JSON in storage/data.
 *
 * Qui scrive l'area riservata: le modifiche ai contenuti, le richieste
 * arrivate dal sito, l'account. Niente database, perché per una casa di
 * cinque camere un database è una cosa in più da installare, da proteggere e
 * da ripristinare — e il sito si carica via FTP.
 *
 * Tre garanzie, perché un file di contenuti scritto a metà è un sito rotto:
 *   - si scrive in un file temporaneo e poi lo si rinomina: chi legge vede o
 *     la versione vecchia o quella nuova, mai una a metà;
 *   - due scritture contemporanee non si pestano: un lucchetto per file;
 *   - prima di sovrascrivere, la versione precedente va in storage/data/storico,
 *     così un errore si annulla con un click invece che con un backup.
 */
final class JsonStore
{
    /** Quante versioni precedenti di ogni file si tengono. */
    private const VERSIONI = 30;

    public function __construct(private readonly string $cartella)
    {
    }

    public function cartella(): string
    {
        return $this->cartella;
    }

    /** @return array<mixed> */
    public function read(string $nome, array $predefinito = []): array
    {
        $file = $this->percorso($nome);
        if (!is_file($file)) {
            return $predefinito;
        }
        $testo = @file_get_contents($file);
        if ($testo === false || $testo === '') {
            return $predefinito;
        }
        $dati = json_decode($testo, true);

        return is_array($dati) ? $dati : $predefinito;
    }

    /** @param array<mixed> $dati */
    public function write(string $nome, array $dati, bool $conStorico = true): void
    {
        $this->preparaCartella();
        $file = $this->percorso($nome);

        $this->conLucchetto($nome, function () use ($file, $nome, $dati, $conStorico): void {
            if ($conStorico && is_file($file)) {
                $this->archivia($nome, $file);
            }
            $json = json_encode($dati, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $tmp  = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
            if (file_put_contents($tmp, $json . "\n") === false) {
                throw new \RuntimeException('Scrittura non riuscita: ' . $nome);
            }
            @chmod($tmp, 0640);
            if (!rename($tmp, $file)) {
                @unlink($tmp);
                throw new \RuntimeException('Scrittura non riuscita: ' . $nome);
            }
        });
    }

    /**
     * Legge, modifica e riscrive sotto lo stesso lucchetto: per le liste a cui
     * si aggiunge (le richieste), dove due arrivi insieme non devono perdersene uno.
     *
     * @param callable(array<mixed>): array<mixed> $modifica
     */
    public function update(string $nome, callable $modifica, bool $conStorico = false): array
    {
        $this->preparaCartella();
        $risultato = [];
        $this->conLucchetto($nome, function () use ($nome, $modifica, $conStorico, &$risultato): void {
            $file = $this->percorso($nome);
            if ($conStorico && is_file($file)) {
                $this->archivia($nome, $file);
            }
            $risultato = $modifica($this->read($nome));
            $json = json_encode($risultato, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $tmp  = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
            file_put_contents($tmp, $json . "\n");
            @chmod($tmp, 0640);
            rename($tmp, $file);
        });

        return $risultato;
    }

    /**
     * Le versioni precedenti di un file, dalla più recente.
     *
     * @return list<array{id:string, quando:int}>
     */
    public function history(string $nome): array
    {
        $cartella = $this->cartella . '/storico';
        $trovate  = glob($cartella . '/' . $this->nomePulito($nome) . '.*.json') ?: [];
        $out = [];
        foreach ($trovate as $f) {
            if (preg_match('/\.(\d{8}-\d{6}-[0-9a-f]{4})\.json$/', $f, $m)) {
                $out[] = ['id' => $m[1], 'quando' => (int) filemtime($f)];
            }
        }
        usort($out, static fn (array $a, array $b): int => strcmp($b['id'], $a['id']));

        return $out;
    }

    /** Rimette una versione precedente. La versione attuale finisce a sua volta nello storico. */
    public function restore(string $nome, string $id): bool
    {
        if (!preg_match('/^\d{8}-\d{6}-[0-9a-f]{4}$/', $id)) {
            return false;
        }
        $vecchio = $this->cartella . '/storico/' . $this->nomePulito($nome) . '.' . $id . '.json';
        if (!is_file($vecchio)) {
            return false;
        }
        $dati = json_decode((string) file_get_contents($vecchio), true);
        if (!is_array($dati)) {
            return false;
        }
        $this->write($nome, $dati);

        return true;
    }

    /** Toglie un file, tenendone la versione nello storico: si può sempre ripristinare. */
    public function delete(string $nome): void
    {
        $file = $this->percorso($nome);
        if (!is_file($file)) {
            return;
        }
        $this->conLucchetto($nome, function () use ($nome, $file): void {
            $this->archivia($nome, $file);
            @unlink($file);
        });
    }

    public function writable(): bool
    {
        $this->preparaCartella();

        return is_dir($this->cartella) && is_writable($this->cartella);
    }

    private function archivia(string $nome, string $file): void
    {
        $storico = $this->cartella . '/storico';
        if (!is_dir($storico)) {
            @mkdir($storico, 0750, true);
        }
        $id = date('Ymd-His') . '-' . bin2hex(random_bytes(2));
        @copy($file, $storico . '/' . $this->nomePulito($nome) . '.' . $id . '.json');

        $versioni = glob($storico . '/' . $this->nomePulito($nome) . '.*.json') ?: [];
        sort($versioni);
        while (count($versioni) > self::VERSIONI) {
            @unlink(array_shift($versioni));
        }
    }

    private function conLucchetto(string $nome, callable $lavoro): void
    {
        $lucchetto = fopen($this->percorso($nome) . '.lock', 'c');
        if ($lucchetto === false) {
            throw new \RuntimeException('Lucchetto non disponibile: ' . $nome);
        }
        try {
            flock($lucchetto, LOCK_EX);
            $lavoro();
        } finally {
            flock($lucchetto, LOCK_UN);
            fclose($lucchetto);
        }
    }

    private function preparaCartella(): void
    {
        if (!is_dir($this->cartella)) {
            @mkdir($this->cartella, 0750, true);
        }
    }

    private function percorso(string $nome): string
    {
        return $this->cartella . '/' . $this->nomePulito($nome) . '.json';
    }

    /** Un nome di file che non può uscire dalla cartella. */
    private function nomePulito(string $nome): string
    {
        $pulito = preg_replace('/[^a-z0-9-]/', '', strtolower($nome));
        if ($pulito === '' || $pulito === null) {
            throw new \InvalidArgumentException('Nome di archivio non valido');
        }

        return $pulito;
    }
}
