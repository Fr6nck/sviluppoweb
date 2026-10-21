<?php
namespace MHW;

/**
 * 9P · L'email «Fino al 55% di sconto» a chi ha appena attivato: Porta un amico + Giudizio con sconto, con i conti fatti
 * sul suo piano (Sconti::netto, la stessa formula del server) e il suo codice d'invito.
 *
 * Parte una volta per account (accounts.email_sconti_at), dal giro dei richiami (Richiami::esegui), 7 giorni dopo
 * l'attivazione: il primo pagamento riuscito (carta, SEPA confermato, bonifico segnato) o l'abbonamento con un codice
 * omaggio. Entro 60 giorni dall'attivazione: chi aggiorna l'applicazione non scrive ai clienti di un anno fa.
 * Mai a: clienti di prova e vetrina (Richiami::manda), abbonamenti dati a mano senza pagamento, offerte su misura (9N),
 * chi ha già 10 inviti andati a buon fine, chi ha detto «non mandarmene più». Con gli inviti spenti resta «in attesa»
 * e parte quando si accendono (Amministrazione → Richiami: «Email sconti in attesa»).
 * Le versioni dell'email: ha già aderito al giudizio (oggetto e conti con il giudizio compreso, quando arriva la richiesta del voto);
 * ha già 1–9 amici («sei al N×5%»); codice omaggio (gli sconti valgono dal primo rinnovo dopo il pagamento).
 * Testi in «Testi e foto» (email.sconti.*), IT/EN; i numeri non sono mai scritti a mano.
 */
final class EmailSconti
{
    public const DOPO_GIORNI = 7;
    public const FINESTRA_GIORNI = 60;

    public static function disponibili(): bool { return Migrator::columnExists('accounts', 'email_sconti_at') && Richiami::disponibili(); }

    /** Quando l'account si è attivato: il primo pagamento riuscito o l'abbonamento con un codice omaggio. Null: mai (o solo a mano). */
    public static function attivazione(int $accountId): ?int
    {
        $pagato = (string) Db::val("SELECT MIN(updated_at) FROM orders WHERE account_id = ? AND status = 'paid'", [$accountId], '');
        $omaggio = Migrator::columnExists('subscriptions', 'omaggio_fino')
            ? (string) Db::val("SELECT MIN(created_at) FROM subscriptions WHERE account_id = ? AND omaggio_fino IS NOT NULL AND omaggio_fino <> ''", [$accountId], '') : '';
        $date = array_filter([$pagato, $omaggio], fn($d) => $d !== '');
        return $date ? (int) min(array_map('strtotime', $date)) : null;
    }

    /**
     * Lo stato dell'email per un account: inviata, programmata (presto), da mandare, in attesa (inviti spenti) o saltata, con il perché.
     * @return array{stato:string,motivo:string,quando:?int}
     */
    public static function stato(array $acc, ?int $adesso = null): array
    {
        $t = $adesso ?? time();
        $aid = (int) $acc['id'];
        if (!empty($acc['email_sconti_at'])) return ['stato' => 'inviata', 'motivo' => 'il ' . Support::date((string) $acc['email_sconti_at']), 'quando' => (int) strtotime((string) $acc['email_sconti_at'])];
        $salta = fn(string $m) => ['stato' => 'saltata', 'motivo' => $m, 'quando' => null];
        $sub = Subscriptions::active($aid);
        if (!$sub) return $salta('nessun abbonamento attivo');
        if ($sub['provider'] === 'dimostrazione') return $salta('abbonamento di esempio');
        if (Richiami::diProva($aid)) return $salta('cliente di prova');
        $email = mb_strtolower(trim((string) Db::val('SELECT email FROM users WHERE id = ?', [(int) $acc['user_id']], '')));
        $vetrinaE = mb_strtolower(trim((string) Config::get('vetrina_email', '')));
        if (($vetrinaE !== '' && $email === $vetrinaE) || str_ends_with($email, '@' . Demo::DOMINIO)) return $salta('account della vetrina o di esempio');
        if (!empty($sub['offerta_id'])) return $salta('offerta su misura');
        if (Migrator::tableExists('referrals') && Inviti::validi($aid) >= Inviti::amiciMassimi()) return $salta('ha già ' . Inviti::amiciMassimi() . ' inviti andati a buon fine');
        $att = self::attivazione($aid);
        if ($att === null) return $salta('attivato a mano senza pagamento');
        if (Db::val("SELECT id FROM email_optout WHERE account_id = ? AND kind = 'sconti'", [$aid])) return $salta('non vuole più riceverla');
        $quando = $att + self::DOPO_GIORNI * 86400;
        if ($t > $att + self::FINESTRA_GIORNI * 86400) return $salta('attivato da più di ' . self::FINESTRA_GIORNI . ' giorni');
        if (!Inviti::disponibili()) return ['stato' => 'attesa', 'motivo' => 'inviti spenti in configurazione', 'quando' => $quando];
        if ($t < $quando) return ['stato' => 'programmata', 'motivo' => 'il ' . Support::date(gmdate('Y-m-d\TH:i:s\Z', $quando)), 'quando' => $quando];
        return ['stato' => 'da_mandare', 'motivo' => 'al prossimo giro', 'quando' => $quando];
    }

    /** Gli account da guardare: abbonamento attivo, email non ancora partita, attivati negli ultimi 60 giorni (o mai, per dirlo). */
    public static function elenco(?int $adesso = null): array
    {
        if (!self::disponibili()) return [];
        $t = $adesso ?? time();
        $out = [];
        foreach (Db::all("SELECT DISTINCT a.*, u.email, u.name AS user_name FROM accounts a JOIN users u ON u.id = a.user_id JOIN subscriptions s ON s.account_id = a.id
                          WHERE a.email_sconti_at IS NULL AND s.status IN ('active', 'trialing', 'past_due') AND s.created_at > ? AND u.email NOT LIKE ? ORDER BY a.id",
                         [gmdate('Y-m-d\TH:i:s\Z', $t - (self::FINESTRA_GIORNI + 30) * 86400), '%@' . Demo::DOMINIO]) as $a) {
            $out[] = $a + ['sconti' => self::stato($a, $t)];
        }
        return $out;
    }

    /** Il giro dei richiami: manda quelle dovute. @return int quante partite */
    public static function giro(?int $adesso = null): int
    {
        $n = 0;
        foreach (self::elenco($adesso) as $a) {
            if ($a['sconti']['stato'] !== 'da_mandare') continue;
            try { if (self::manda($a)) $n++; } catch (\Throwable $e) { Log::exception($e, 'email sconti'); }
        }
        return $n;
    }

    /** «Manda adesso» da Amministrazione → Richiami: anche prima dei 7 giorni, mai se saltata o con gli inviti spenti. @return array{0:bool,1:string} */
    public static function mandaAdesso(int $accountId): array
    {
        if (!self::disponibili()) return [false, 'Manca la migrazione 061: apri il sito una volta per aggiornare il database.'];
        $a = Db::one('SELECT a.*, u.email, u.name AS user_name FROM accounts a JOIN users u ON u.id = a.user_id WHERE a.id = ?', [$accountId]);
        if (!$a) return [false, 'Cliente non trovato.'];
        $st = self::stato($a);
        if (!in_array($st['stato'], ['programmata', 'da_mandare'], true)) return [false, 'Non parte: ' . ($st['stato'] === 'inviata' ? 'già inviata ' : '') . $st['motivo'] . '.'];
        $ok = self::manda($a, 'manuale-' . gmdate('YmdHis'));
        return [$ok, $ok ? 'Email «Fino al ' . Sconti::massimoRinnovo()['totale'] . '%» mandata a ' . $a['email'] . '.' : 'L\'email a ' . $a['email'] . ' non è partita: controlla la posta in Impostazioni.'];
    }

    private static function manda(array $a, string $ref = 'primo'): bool
    {
        $aid = (int) $a['id'];
        $lingua = Richiami::lingua($aid);
        $ok = Richiami::manda($aid, (string) $a['email'], (string) $a['user_name'], 'sconti', $ref, self::testo($a, $lingua), $lingua);
        if ($ok) Db::update('accounts', ['email_sconti_at' => Support::now()], 'id = :id', ['id' => $aid]);
        return $ok;
    }

    /**
     * Il testo per Richiami::manda: [oggetto, corpo, bottone, indirizzo, nota, scelte]. I conti sul piano e sulle strutture
     * del cliente: oggi, con il massimo (10 amici e il giudizio), con 2 e con 5 amici (con il giudizio, se ha già aderito).
     */
    public static function testo(array $acc, string $lingua): array
    {
        $en = $lingua === 'en';
        $aid = (int) $acc['id'];
        $sub = Subscriptions::active($aid) ?? [];
        $pv = Plans::version((int) ($sub['package_version_id'] ?? 0)) ?? [];
        $q = max(1, (int) ($sub['quantity'] ?? 1));
        $prezzo = $pv ? Plans::price($pv, $q) : 0;
        $valuta = (string) ($pv['currency'] ?? 'EUR');
        $m = Sconti::massimoRinnovo();
        $giudizioOn = $m['giudizio'] > 0;
        $aderito = !empty($acc['giudizio_adesione']);
        $amici = Inviti::validi($aid);
        $base = $aderito && $giudizioOn ? $m['giudizio'] : 0;
        $soldi = fn(int $pct) => Support::money(Sconti::netto($prezzo, $pct), $valuta);
        $p = fn(int $n) => $n . '%';
        $s = ['struttura' => (string) (Db::val("SELECT name FROM properties WHERE account_id = ? AND is_demo = 0 AND archived_at IS NULL ORDER BY (status = 'published') DESC, id", [$aid], '') ?: ''),
              'totale' => $p($m['totale']), 'max' => $p($m['max']), 'passo' => $p(Inviti::PASSO), 'amico' => $p(Inviti::AMICO), 'giudizio' => $p($m['giudizio']),
              'amici' => $m['amici'], 'codice' => Inviti::codice($acc), 'n' => $amici, 'percento' => $p(Inviti::percento($aid)),
              'piano' => (string) ($pv['name'] ?? '') . ($q > 1 ? ($en ? " ($q properties)" : " ($q strutture)") : ''), 'prezzo' => Support::money($prezzo, $valuta),
              'minimo' => $soldi($m['totale']), 'due' => $soldi($base + 2 * Inviti::PASSO), 'cinque' => $soldi($base + 5 * Inviti::PASSO)];
        $tx = fn(string $k) => TestiSito::testoSemplice('email.sconti.' . $k, $s, $en ? 'en' : 'it');
        $online = (bool) Db::val("SELECT id FROM properties WHERE account_id = ? AND is_demo = 0 AND status = 'published'", [$aid]);
        $par = [];
        if ($aderito && $giudizioOn) {
            $oggetto = $tx('oggetto_aderito');
            $par[] = $tx('intro_aderito');
            $par[] = $tx('inviti');
        } else {
            $oggetto = $tx('oggetto');
            $par[] = $tx($online && $s['struttura'] !== '' ? 'intro' : 'intro_attivo');
            $par[] = $tx('massimo');
            $par[] = '1 · ' . $tx('inviti');
            if ($giudizioOn) $par[] = '2 · ' . $tx('giudizio');
        }
        if ($amici > 0) $par[] = $tx($amici === 1 ? 'gia_amico' : 'gia_amici');
        $par[] = $tx($aderito && $giudizioOn ? 'conti_aderito' : ($giudizioOn ? 'conti' : 'conti_senza_giudizio'));
        // Il voto si chiede 30 giorni dopo la prima pubblicazione (7D): tra quanti giorni, se è ancora da venire.
        if ($aderito && $giudizioOn && ($prima = Testimonianze::primaPubblicazione($aid)) !== null
            && ($giorni = (int) ceil(($prima + Testimonianze::RICHIAMO_GIORNI * 86400 - time()) / 86400)) > 0) {
            $par[] = strtr($tx('quando_giudizio'), ['{giorni}' => $giorni]);
        }
        if (!empty($sub['omaggio_fino']) && ($sub['status'] ?? '') === 'trialing') $par[] = $tx('omaggio');
        $scelte = [];
        if ($giudizioOn && !$aderito && Giudizi::puoAderire($acc)) $scelte[] = [$tx('bottone_giudizio'), Support::baseUrl() . '/giudizio?da=email_sconti#adesione'];
        return [$oggetto, implode("\n\n", $par), $tx('bottone_inviti'), Support::baseUrl() . '/inviti?da=email_sconti', $tx('nota'), $scelte];
    }
}
