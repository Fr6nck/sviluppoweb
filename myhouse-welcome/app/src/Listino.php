<?php
namespace MHW;

/**
 * 9W · I prezzi nuovi anche per chi è già abbonato (decisione del titolare del 27 novembre 2026): Essential 87 €, Plus 117 €,
 * Portfolio da 167 € per 2 strutture. La migrazione 073 crea le versioni nuove e scrive l'elenco degli abbonamenti da
 * portare ai prezzi nuovi (storage/.listino-da-riallineare.json); il giro dei richiami li porta qui, pochi alla volta.
 *
 *   - Senza cambi programmati: l'abbonamento passa subito alla versione nuova (CambioAbbonamento::listino). Il periodo
 *     pagato resta com'è, il prossimo rinnovo incassa il prezzo nuovo (meno gli sconti dell'account).
 *   - Con una discesa programmata: la discesa resta, verso la versione nuova del piano scelto (stesso giorno, stesse scelte).
 *   - Le vecchie Portfolio 2 e Portfolio 3 (fuori vendita) passano a Portfolio con lo stesso numero di strutture.
 *
 * Solo se il prezzo nuovo è più basso e il piano nuovo dà almeno le stesse cose (Entitlements::perVersione): altrimenti
 * l'abbonamento resta com'è e lo dice il Registro azioni. Mai le offerte su misura né gli abbonamenti dello staff.
 */
final class Listino
{
    public const FILE = '/storage/.listino-da-riallineare.json';
    /** Quanti abbonamenti per giro: ognuno chiama Stripe tre volte. */
    public const PER_GIRO = 15;

    /** C'è ancora qualcosa da fare? (il file della migrazione esiste) */
    public static function disponibile(): bool { return is_file(self::file()); }

    /** Il file della migrazione. */
    public static function file(): string { return MHW_APP . self::FILE; }

    /** Gli abbonamenti ancora da portare ai prezzi nuovi. @return list<int> */
    public static function inAttesa(): array
    {
        $f = self::file();
        return is_file($f) ? array_values(array_map('intval', json_decode((string) @file_get_contents($f), true) ?: [])) : [];
    }

    /**
     * Dove va un abbonamento: la versione in vendita dello stesso piano (o di Portfolio, per le vecchie Portfolio 2 e 3) e
     * le strutture. null = resta com'è (già al prezzo nuovo, prezzo non più basso, o il piano nuovo darebbe di meno).
     * @return array{pv:array,q:int,da:array,qda:int}|null
     */
    public static function destinazione(array $sub): ?array
    {
        $programmato = !empty($sub['next_package_version_id']);
        $da = Plans::version((int) ($programmato ? $sub['next_package_version_id'] : $sub['package_version_id']));
        if (!$da) return null;
        $qda = max(1, (int) ($programmato ? ($sub['next_quantity'] ?? 1) : ($sub['quantity'] ?? 1)));
        $pv = Plans::currentVersion((int) $da['id']);
        $q = $qda;
        if (!$pv && (string) $da['family'] !== '') {
            // Un piano fuori vendita della stessa famiglia (Portfolio 2, Portfolio 3): Portfolio a struttura, con le strutture che aveva.
            $pv = Db::one("SELECT pv.*, p.name, p.code, p.family FROM package_versions pv JOIN packages p ON p.id = pv.package_id
                           WHERE p.family = ? AND p.public = 1 AND p.active = 1 AND pv.is_current = 1 AND pv.per_property = 1 ORDER BY p.sort, p.id", [(string) $da['family']]);
            if ($pv) $q = max((int) $pv['min_quantity'], min((int) $pv['max_quantity'], Entitlements::valore(Entitlements::perVersione((int) $sub['account_id'], (int) $da['id'], $qda), 'properties', 1)));
        }
        if (!$pv || (int) $pv['id'] === (int) $da['id']) return null;
        if (Plans::price($pv, $q) >= Plans::price($da, $qda)) return null;
        // Il piano nuovo deve dare almeno quello che dava il vecchio: limiti uguali o più alti, funzioni accese restano accese.
        $prima = Entitlements::perVersione((int) $sub['account_id'], (int) $da['id'], $qda);
        $dopo = Entitlements::perVersione((int) $sub['account_id'], (int) $pv['id'], $q);
        foreach ($prima as $code => $x) {
            if (Entitlements::valore($dopo, $code) < Entitlements::valore($prima, $code)) return null;
        }
        return ['pv' => $pv, 'q' => $q, 'da' => $da, 'qda' => $qda];
    }

    /**
     * Porta un abbonamento ai prezzi nuovi. @return string 'fatto' | 'programmato' | 'resta' (niente da fare: esce dall'elenco)
     *   | 'dopo' (in arretrato: si riprova al giro dopo). Un errore di Stripe esce come eccezione (si riprova).
     */
    public static function porta(array $sub): string
    {
        if ((string) $sub['provider'] !== 'stripe' || (string) ($sub['provider_subscription_id'] ?? '') === '' || !Stripe::enabled()) return 'resta';
        if (!empty($sub['offerta_id']) || !empty($sub['next_offerta_id'])) return 'resta';
        if (in_array((string) $sub['status'], ['canceled', 'incomplete_expired', 'unpaid'], true)) return 'resta';
        if ((string) $sub['status'] === 'past_due') return 'dopo';
        $d = self::destinazione($sub);
        $uid = (int) Db::val('SELECT user_id FROM accounts WHERE id = ?', [(int) $sub['account_id']], 0) ?: null;
        if (!$d) { Auth::audit('listino.resta', $uid, ['subscription' => (int) $sub['id'], 'pv' => (int) $sub['package_version_id']]); return 'resta'; }
        $voce = ['subscription' => (int) $sub['id'], 'da' => ['pv' => (int) $d['da']['id'], 'q' => $d['qda'], 'prezzo' => Plans::price($d['da'], $d['qda'])],
                 'a' => ['pv' => (int) $d['pv']['id'], 'q' => $d['q'], 'prezzo' => Plans::price($d['pv'], $d['q'])]];
        if (!empty($sub['next_package_version_id'])) {
            CambioAbbonamento::programma($sub, $d['pv'], $d['q'], json_decode((string) ($sub['next_choices'] ?? ''), true) ?: []);
            Auth::audit('listino.programmato', $uid, $voce);
            return 'programmato';
        }
        CambioAbbonamento::listino($sub, $d['pv'], $d['q']);
        Auth::audit('listino.fatto', $uid, $voce);
        return 'fatto';
    }

    /** Dal giro dei richiami: i prossimi PER_GIRO abbonamenti dell'elenco. @return int quanti portati ai prezzi nuovi */
    public static function inSospeso(): int
    {
        $ids = self::inAttesa();
        if (!$ids || !Stripe::enabled()) return 0;
        $restano = array_slice($ids, self::PER_GIRO); $fatti = 0;
        foreach (array_slice($ids, 0, self::PER_GIRO) as $id) {
            $sub = Db::one('SELECT * FROM subscriptions WHERE id = ?', [$id]);
            if (!$sub) continue;
            try {
                $esito = self::porta($sub);
                if ($esito === 'dopo') $restano[] = $id;
                if ($esito === 'fatto' || $esito === 'programmato') $fatti++;
            } catch (\Throwable $e) { Log::exception($e, 'listino: abbonamento ' . $id); $restano[] = $id; }
        }
        $f = self::file();
        if ($restano) @file_put_contents($f, json_encode(array_values($restano)), LOCK_EX); else @unlink($f);
        return $fatti;
    }
}
