<?php
namespace MHW;

/**
 * «Già in <struttura>»: dentro l'editor di una sezione, i luoghi e le righe che
 * il cliente ha già scritto nelle altre strutture dello stesso account, da
 * riusare con un tocco (punto 4 del controllo di ottobre 2026).
 *
 * Regole:
 *  - solo se l'account ha almeno 2 strutture non archiviate;
 *  - mai per le sezioni di Copia::MAI (Check-in & Check-out, Wi-Fi, Come
 *    arrivare, Parcheggio): sono dati di quella struttura;
 *  - non si propone quello che c'è già con lo stesso nome;
 *  - si portano testi, traduzioni, categoria, etichetta, colore; la foto (e il
 *    PDF di una riga) si DUPLICA, come in Copia: le due guide restano
 *    indipendenti; senza foto nel piano, la foto non si porta;
 *  - i minuti a piedi si ricalcolano dalle coordinate (link di Maps del luogo e
 *    della struttura); i minuti in auto e le distanze delle righe restano vuoti.
 * Nessuna tabella nuova: si legge dalle sezioni dello stesso tipo.
 */
final class Suggerimenti
{
    /** Al massimo quante proposte per sezione (le più recenti). */
    public const MASSIMO = 40;
    /** Sottocampi delle righe che dipendono dalla struttura: non si portano. */
    private const PROPRI = ['walk_minutes', 'drive_minutes', 'dist_min'];

    public static function attivi(int $accountId, string $kind): bool
    {
        if (in_array($kind, Copia::MAI, true)) return false;
        return (int) Db::val('SELECT COUNT(*) FROM properties WHERE account_id = ? AND archived_at IS NULL', [$accountId], 0) >= 2;
    }

    /** Le sezioni dello stesso tipo nelle altre strutture dell'account (non archiviate). */
    private static function altre(int $accountId, int $propertyId, string $kind): array
    {
        return Db::all("SELECT s.*, p.name AS struttura FROM sections s JOIN properties p ON p.id = s.property_id
                        WHERE p.account_id = ? AND p.id <> ? AND p.archived_at IS NULL AND s.kind = ? ORDER BY s.id DESC",
                       [$accountId, $propertyId, $kind]);
    }

    private static function chiave(string $nome): string { return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $nome) ?? '')); }

    /**
     * I luoghi da proporre in questa sezione.
     * @return array<int,array{id:int,name:string,categoria:string,struttura:string}>
     */
    public static function luoghi(int $accountId, int $propertyId, int $sectionId, string $kind): array
    {
        if (!self::attivi($accountId, $kind) || !SectionCatalog::hasPlaces($kind)) return [];
        $ci = [];
        foreach (Db::all('SELECT name FROM places WHERE section_id = ?', [$sectionId]) as $x) $ci[self::chiave($x['name'])] = true;
        $out = [];
        foreach (self::altre($accountId, $propertyId, $kind) as $s) {
            foreach (Db::all('SELECT id, name, category_key, category FROM places WHERE section_id = ? ORDER BY id DESC', [$s['id']]) as $pl) {
                $k = self::chiave((string) $pl['name']);
                if ($k === '' || isset($ci[$k])) continue;
                $ci[$k] = true;   // lo stesso luogo in due altre strutture si propone una volta
                $out[] = ['id' => (int) $pl['id'], 'name' => (string) $pl['name'], 'struttura' => (string) $s['struttura'],
                          'categoria' => $pl['category_key'] ? I18n::t('it', 'cat.' . $pl['category_key']) : (string) $pl['category']];
                if (count($out) >= self::MASSIMO) return $out;
            }
        }
        return $out;
    }

    /** Il nome con cui si riconosce una riga: il primo testo della riga (nome, titolo…). */
    private static function etichetta(array $def, array $comune, array $tradotta): string
    {
        foreach ($def['sub'] as $sn => $sd) {
            if (in_array($sd[0], ['text', 'plain'], true) && empty($sd['cifre'])) {
                $v = trim((string) ($tradotta[$sn] ?? $comune[$sn] ?? ''));
                if ($v !== '') return $v;
            }
        }
        return '';
    }

    /**
     * Le righe da proporre, per campo ripetuto della sezione.
     * @return array<string,array<int,array{etichetta:string,struttura:string,sid:int,riga:string}>>
     */
    public static function righe(int $accountId, int $propertyId, int $sectionId, string $kind, string $locale): array
    {
        if (!self::attivi($accountId, $kind)) return [];
        $mia = Properties::section($propertyId, $sectionId);
        $miaDati = json_decode((string) $mia['data'], true) ?: [];
        $miaTr = json_decode((string) Db::val('SELECT data FROM section_translations WHERE section_id = ? AND locale = ?', [$sectionId, $locale], '{}'), true) ?: [];
        $out = [];
        foreach (SectionCatalog::fields($kind) as $nome => $d) {
            if ($d[0] !== 'repeater') continue;
            $def = SectionCatalog::field($kind, $nome);
            $ci = []; $mieTr = [];
            foreach ((array) ($miaTr[$nome] ?? []) as $t) if (isset($t['id'])) $mieTr[$t['id']] = $t;
            foreach ((array) ($miaDati[$nome] ?? []) as $r) {
                $k = self::chiave(self::etichetta($def, (array) $r, (array) ($mieTr[$r['id'] ?? ''] ?? [])));
                if ($k !== '') $ci[$k] = true;
            }
            foreach (self::altre($accountId, $propertyId, $kind) as $s) {
                $dati = json_decode((string) $s['data'], true) ?: [];
                $tr = json_decode((string) Db::val('SELECT data FROM section_translations WHERE section_id = ? AND locale = ?', [$s['id'], $locale], '{}'), true) ?: [];
                $trPerId = [];
                foreach ((array) ($tr[$nome] ?? []) as $t) if (isset($t['id'])) $trPerId[$t['id']] = $t;
                foreach ((array) ($dati[$nome] ?? []) as $r) {
                    $et = self::etichetta($def, (array) $r, $trPerId[$r['id'] ?? ''] ?? []);
                    $k = self::chiave($et);
                    if ($k === '' || isset($ci[$k])) continue;
                    $ci[$k] = true;
                    $out[$nome][] = ['etichetta' => $et, 'struttura' => (string) $s['struttura'], 'sid' => (int) $s['id'], 'riga' => (string) $r['id']];
                    if (count($out[$nome]) >= self::MASSIMO) break 2;
                }
            }
        }
        return $out;
    }

    /** Il luogo di un'altra struttura dell'account, copiato in questa sezione. @return int l'id del luogo nuovo */
    public static function copiaLuogo(int $accountId, array $prop, int $sectionId, int $placeId): int
    {
        $s = Properties::section((int) $prop['id'], $sectionId);
        $da = Db::one('SELECT pl.*, s.kind, p.account_id, p.id AS da_pid FROM places pl JOIN sections s ON s.id = pl.section_id
                       JOIN properties p ON p.id = s.property_id WHERE pl.id = ?', [$placeId]);
        if (!$da || (int) $da['account_id'] !== $accountId || $da['kind'] !== $s['kind'] || !self::attivi($accountId, $s['kind'])) {
            throw new NotFound('Luogo non trovato.');
        }
        $scritti = [];
        try {
            return Db::tx(function () use ($accountId, $prop, $s, $da, &$scritti) {
                $riga = array_diff_key($da, array_flip(['id', 'section_id', 'kind', 'account_id', 'da_pid', 'media_id', 'position', 'walk_minutes', 'drive_minutes']));
                [$plat, $plng] = Mappe::struttura($prop);
                $riga['walk_minutes'] = isset($da['lat'], $da['lng']) ? (int) (Mappe::minutiAPiedi($plat, $plng, (float) $da['lat'], (float) $da['lng']) ?? 0) : 0;
                $riga['drive_minutes'] = 0;
                $riga['section_id'] = $s['id'];
                $riga['position'] = (int) Db::val('SELECT COUNT(*) FROM places WHERE section_id = ?', [$s['id']], 0);
                $riga['media_id'] = $da['media_id'] && Entitlements::can($accountId, 'photos')
                    ? Media::duplicate((int) $da['media_id'], $accountId, (int) $prop['id'], $scritti) : null;
                $nuovo = Db::insert('places', $riga);
                // Le traduzioni nelle lingue che questa guida ha (le altre non si vedrebbero).
                foreach (Db::all('SELECT t.* FROM place_translations t JOIN property_locales l ON l.locale = t.locale AND l.property_id = ?
                                  WHERE t.place_id = ?', [$prop['id'], $da['id']]) as $t) {
                    unset($t['id']); $t['place_id'] = $nuovo;
                    Db::insert('place_translations', $t);
                }
                return $nuovo;
            });
        } catch (\Throwable $e) {
            foreach ($scritti as [$driver, $key]) { try { Storages::for($driver)->delete($key); } catch (\Throwable) {} }
            throw $e;
        }
    }

    /** Una riga di un campo ripetuto di un'altra struttura, aggiunta in fondo a questa sezione. @return string l'id della riga nuova */
    public static function copiaRiga(int $accountId, array $prop, int $sectionId, string $campo, int $daSectionId, string $rigaId): string
    {
        $s = Properties::section((int) $prop['id'], $sectionId);
        $da = Db::one('SELECT s.*, p.account_id FROM sections s JOIN properties p ON p.id = s.property_id WHERE s.id = ?', [$daSectionId]);
        $def = SectionCatalog::field($s['kind'], $campo);
        if (!$da || (int) $da['account_id'] !== $accountId || $da['kind'] !== $s['kind'] || ($def[0] ?? '') !== 'repeater' || !self::attivi($accountId, $s['kind'])) {
            throw new NotFound('Riga non trovata.');
        }
        $datiDa = json_decode((string) $da['data'], true) ?: [];
        $sorgente = null;
        foreach ((array) ($datiDa[$campo] ?? []) as $r) if (($r['id'] ?? '') === $rigaId) $sorgente = $r;
        if (!$sorgente) throw new NotFound('Riga non trovata.');
        $dati = json_decode((string) $s['data'], true) ?: [];
        if (count((array) ($dati[$campo] ?? [])) >= ($def['max'] ?? 30)) throw new \RuntimeException('Questa sezione ha già il numero massimo di righe.');
        $nuovoId = SectionCatalog::newId();
        $scritti = [];
        try {
            return Db::tx(function () use ($accountId, $prop, $s, $da, $def, $campo, $rigaId, $sorgente, $dati, $nuovoId, &$scritti) {
                $riga = ['id' => $nuovoId];
                foreach ($def['sub'] as $sn => $sd) {
                    if (!array_key_exists($sn, $sorgente)) continue;
                    if (in_array($sn, self::PROPRI, true)) { $riga[$sn] = ''; continue; }
                    if (in_array($sd[0], ['image', 'pdf'], true)) {
                        $puo = Entitlements::can($accountId, $sd[0] === 'image' ? 'photos' : 'pdf');
                        $riga[$sn] = $sorgente[$sn] && $puo ? (Media::duplicate((int) $sorgente[$sn], $accountId, (int) $prop['id'], $scritti) ?? '') : '';
                        continue;
                    }
                    $riga[$sn] = $sorgente[$sn];
                }
                $dati[$campo] = array_values(array_merge((array) ($dati[$campo] ?? []), [$riga]));
                Db::update('sections', ['data' => json_encode($dati, JSON_UNESCAPED_UNICODE)], 'id = :sid', ['sid' => $s['id']]);
                // Le traduzioni della riga, nelle lingue che questa guida ha.
                foreach (Db::all('SELECT t.locale, t.data FROM section_translations t JOIN property_locales l ON l.locale = t.locale AND l.property_id = ?
                                  WHERE t.section_id = ?', [$prop['id'], $da['id']]) as $t) {
                    $trDa = json_decode((string) $t['data'], true) ?: [];
                    $rt = null;
                    foreach ((array) ($trDa[$campo] ?? []) as $x) if (($x['id'] ?? '') === $rigaId) $rt = $x;
                    if (!$rt) continue;
                    $rt['id'] = $nuovoId;
                    $qui = Db::one('SELECT id, data FROM section_translations WHERE section_id = ? AND locale = ?', [$s['id'], $t['locale']]);
                    $trQui = $qui ? (json_decode((string) $qui['data'], true) ?: []) : [];
                    // Le righe tradotte stanno nello stesso ordine di quelle comuni: si allinea con righe vuote se mancano.
                    $lista = array_values((array) ($trQui[$campo] ?? []));
                    while (count($lista) < count($dati[$campo]) - 1) $lista[] = ['id' => $dati[$campo][count($lista)]['id']];
                    $lista[] = $rt;
                    $trQui[$campo] = $lista;
                    if ($qui) Db::update('section_translations', ['data' => json_encode($trQui, JSON_UNESCAPED_UNICODE), 'updated_at' => Support::now()], 'id = :tid', ['tid' => $qui['id']]);
                    else Db::insert('section_translations', ['section_id' => $s['id'], 'locale' => $t['locale'], 'data' => json_encode($trQui, JSON_UNESCAPED_UNICODE),
                                                             'body' => '', 'state' => 'reviewed', 'updated_at' => Support::now()]);
                }
                return $nuovoId;
            });
        } catch (\Throwable $e) {
            foreach ($scritti as [$driver, $key]) { try { Storages::for($driver)->delete($key); } catch (\Throwable) {} }
            throw $e;
        }
    }
}
