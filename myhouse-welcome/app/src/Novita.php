<?php
namespace MHW;

/**
 * Le novità per le guide già fatte (7C), da lang/novita.php. La fascia nel pannello mostra la prima non ancora
 * chiusa dall'account; i campi nuovi ancora vuoti si segnano in Contenuti e nell'editor finché non si
 * compilano o non si preme «Ho visto». Chiusure e «Ho visto» stanno in novita_viste (per account).
 */
final class Novita
{
    /** @return list<array> tutte le novità, nell'ordine del file */
    public static function tutte(): array
    {
        static $n = null;
        if ($n === null) { $f = MHW_APP . '/lang/novita.php'; $n = is_file($f) ? (array) require $f : []; }
        return $n;
    }

    public static function disponibili(): bool { return Migrator::tableExists('novita_viste'); }

    /** @return array<string,true> gli id chiusi o visti dall'account */
    private static function viste(int $accountId): array
    {
        if (!self::disponibili()) return [];
        return array_fill_keys(array_column(Db::all('SELECT novita_id FROM novita_viste WHERE account_id = ?', [$accountId]), 'novita_id'), true);
    }

    /**
     * La novità da mostrare nella fascia, o null. 9F: si vede sempre e solo l'ULTIMA (la prima del file); non si chiude
     * con una ✕, ma «Mostrami dove» la segna come vista e la fascia sparisce finché non ne esce un'altra. Gli account
     * creati dopo la data non la vedono: per loro non è una novità. Le novità passate restano nella pagina «Novità».
     */
    public static function daMostrare(int $accountId, string $creato = ''): ?array
    {
        if (!self::disponibili()) return null;
        $viste = self::viste($accountId);
        foreach (self::tutte() as $n) {
            if ($creato !== '' && substr($creato, 0, 10) > (string) $n['data']) continue;
            return isset($viste[$n['id']]) ? null : $n;
        }
        return null;
    }

    /** 9F · Le novità per la pagina «Novità», dalla più recente, con «vista» per quelle già aperte con «Mostrami dove». */
    public static function elenco(int $accountId): array
    {
        $viste = self::viste($accountId);
        return array_map(fn($n) => $n + ['vista' => isset($viste[$n['id']])], self::tutte());
    }

    /** Chiude una novità (o segna «Ho visto» con un id «novità:tipo»). */
    public static function segna(int $accountId, string $id): void
    {
        if (!self::disponibili() || $id === '' || mb_strlen($id) > 60) return;
        try { Db::insert('novita_viste', ['account_id' => $accountId, 'novita_id' => $id, 'created_at' => Support::now()]); }
        catch (\Throwable) { }   // già segnata
    }

    /** È un id che esiste (una novità, o «novità:tipo» di una novità con campi per quel tipo)? */
    public static function esiste(string $id): bool
    {
        [$nid, $kind] = array_pad(explode(':', $id, 2), 2, '');
        foreach (self::tutte() as $n) if ($n['id'] === $nid) return $kind === '' || isset($n['campi'][$kind]);
        return false;
    }

    /**
     * I campi nuovi di un tipo di sezione ancora vuoti e non «visti» dall'account.
     * @param array $dati i dati della sezione (comuni e tradotti insieme) @return string[]
     */
    public static function campiNuovi(int $accountId, string $kind, array $dati): array
    {
        if (!self::disponibili()) return [];
        $viste = self::viste($accountId); $out = [];
        foreach (self::tutte() as $n) {
            if (isset($viste[$n['id'] . ':' . $kind])) continue;
            foreach ((array) ($n['campi'][$kind] ?? []) as $campo) {
                $v = $dati[$campo] ?? '';
                if (is_array($v) ? !array_filter($v, fn($x) => trim((string) (is_array($x) ? implode('', $x) : $x)) !== '') : trim((string) $v) === '') $out[] = $campo;
            }
        }
        return array_values(array_unique($out));
    }

    /** L'id «novità:tipo» per «Ho visto» nell'editor di una sezione (la prima novità con campi per quel tipo). */
    public static function idCampi(string $kind): ?string
    {
        foreach (self::tutte() as $n) if (!empty($n['campi'][$kind])) return $n['id'] . ':' . $kind;
        return null;
    }
}
