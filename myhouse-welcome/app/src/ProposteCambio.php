<?php
namespace MHW;

/**
 * 9R · Il cambio di piano proposto dall'amministrazione (Amministrazione → scheda cliente → «Proponi un cambio di piano»):
 * nuovo piano e strutture, quando (subito con il conguaglio, o dal rinnovo), uno sconto in percento (sul conguaglio o sul
 * primo anno del piano nuovo), la validità (di serie 14 giorni) e un messaggio. Il cliente riceve il link (email da assistenza@
 * con Reply-To commerciale@, o il messaggio da copiare su WhatsApp), vede piano, importo scontato e data e conferma:
 * - subito: il Checkout del conguaglio (6H) con il coupon «mhw-cambio-N»; il piano cambia quando Stripe conferma il pagamento;
 * - dal rinnovo: il cambio programmato e lo sconto sul rinnovo con il coupon «mhw-cambio-N», non cumulabile con inviti e
 *   giudizio (vale il più alto); dopo, sui rinnovi seguenti, valgono le regole solite.
 * Senza conferma non cambia niente; passata la validità è «scaduta». Mai accettabile da «Entra come cliente».
 * Stati: inviata · accettata · pagata (subito, conguaglio pagato) · scaduta · annullata.
 */
final class ProposteCambio
{
    public const GIORNI = 14;
    public const SCONTO_MAX = 50;
    public const COUPON = 'mhw-cambio-';
    public const STATI = ['inviata' => 'Inviata', 'accettata' => 'Accettata', 'pagata' => 'Accettata e pagata', 'scaduta' => 'Scaduta', 'annullata' => 'Annullata'];

    public static function disponibili(): bool { return Migrator::tableExists('proposte_cambio'); }

    public static function link(string $token): string { return Support::baseUrl() . '/proposta/' . $token; }

    /** Crea la proposta (stato «inviata»). @return array{id:int,token:string,link:string} */
    public static function crea(int $accountId, string $codice, mixed $strutture, string $quando, int $sconto, int $giorni, string $messaggio, int $adminId): array
    {
        if (!self::disponibili()) throw new \RuntimeException('Manca la migrazione 062.');
        if (Staff::e($accountId)) throw new \RuntimeException('È un account staff: non paga, il piano si cambia da «Attiva come staff».');
        $a = AzioniCliente::anteprimaCambio($accountId, $codice, $strutture);
        $quando = $quando === 'rinnovo' ? 'rinnovo' : 'subito';
        if ($quando === 'subito' && ($a['prev']['tipo'] === CambioPiano::SCENDE || (int) $a['prev']['conguaglio'] <= 0)) throw new \RuntimeException('Senza differenza da pagare (o scendendo di piano) il cambio si propone «dal rinnovo».');
        if ($sconto < 0 || $sconto > self::SCONTO_MAX) throw new \RuntimeException('Lo sconto va da 0 a ' . self::SCONTO_MAX . '%.');
        $giorni = max(1, min(60, $giorni ?: self::GIORNI));
        $token = bin2hex(random_bytes(20));
        $id = Db::insert('proposte_cambio', [
            'account_id' => $accountId, 'package_version_id' => (int) $a['pv']['id'], 'quantita' => $a['q'], 'quando' => $quando, 'sconto_pct' => $sconto,
            'valida_fino' => gmdate('Y-m-d\T23:59:59\Z', time() + $giorni * 86400), 'messaggio' => trim(mb_substr($messaggio, 0, 1000)),
            'token_hash' => hash('sha256', $token), 'stato' => 'inviata', 'creata_da' => $adminId, 'created_at' => Support::now(),
        ]);
        Auth::audit('proposta.creata', (int) Db::val('SELECT user_id FROM accounts WHERE id = ?', [$accountId], 0) ?: null,
                    ['id' => $id, 'pv' => (int) $a['pv']['id'], 'q' => $a['q'], 'quando' => $quando, 'sconto' => $sconto, 'giorni' => $giorni]);
        return ['id' => $id, 'token' => $token, 'link' => self::link($token)];
    }

    /** La proposta dal link (con lo stato aggiornato: «scaduta» se è passata la validità). */
    public static function perToken(string $token): ?array
    {
        if (!self::disponibili() || !preg_match('/^[a-f0-9]{40}$/', $token)) return null;
        $p = Db::one('SELECT * FROM proposte_cambio WHERE token_hash = ?', [hash('sha256', $token)]);
        return $p ? self::aggiorna($p) + ['token' => $token] : null;
    }

    /** Lo stato vero: inviata e oltre la validità → scaduta; un conguaglio pagato → pagata. */
    private static function aggiorna(array $p): array
    {
        if ($p['stato'] === 'inviata' && strtotime((string) $p['valida_fino']) < time()) {
            Db::update('proposte_cambio', ['stato' => 'scaduta'], 'id = :id', ['id' => (int) $p['id']]);
            $p['stato'] = 'scaduta';
        }
        if ($p['stato'] === 'accettata' && !empty($p['order_id']) && Db::val("SELECT id FROM orders WHERE id = ? AND status = 'paid'", [(int) $p['order_id']])) {
            Db::update('proposte_cambio', ['stato' => 'pagata'], 'id = :id', ['id' => (int) $p['id']]);
            $p['stato'] = 'pagata';
        }
        return $p;
    }

    /** Le proposte di un cliente, dalla più recente (per la scheda in admin). */
    public static function diAccount(int $accountId): array
    {
        return self::disponibili() ? array_map(fn($p) => self::aggiorna($p), Db::all('SELECT * FROM proposte_cambio WHERE account_id = ? ORDER BY id DESC LIMIT 20', [$accountId])) : [];
    }

    /**
     * I conti della proposta: piano, strutture, quando, prezzo pieno e scontato.
     * @return array{pv:array,q:int,quando:string,data:string,pieno:int,scontato:int,valuta:string,giorni:int}
     */
    public static function conti(array $p): array
    {
        $pv = Plans::version((int) $p['package_version_id']) ?? [];
        $q = max(1, (int) $p['quantita']);
        $sub = Subscriptions::active((int) $p['account_id']);
        $valuta = (string) ($pv['currency'] ?? 'EUR');
        if ($p['quando'] === 'subito' && $sub && ($pvA = Plans::version((int) $sub['package_version_id']))) {
            $prev = CambioAbbonamento::preventivo($sub, $pvA, $pv, $q);
            $pieno = (int) $prev['conguaglio'];
            return ['pv' => $pv, 'q' => $q, 'quando' => 'subito', 'data' => Support::now(), 'pieno' => $pieno, 'scontato' => Sconti::netto($pieno, (int) $p['sconto_pct']),
                    'valuta' => $valuta, 'giorni' => (int) $prev['giorni'], 'fine' => (string) $sub['current_period_end']];
        }
        $pieno = Plans::price($pv, $q);
        return ['pv' => $pv, 'q' => $q, 'quando' => 'rinnovo', 'data' => (string) ($p['rinnovo_del'] ?: ($sub['current_period_end'] ?? '')), 'pieno' => $pieno,
                'scontato' => Sconti::netto($pieno, (int) $p['sconto_pct']), 'valuta' => $valuta, 'giorni' => 0, 'fine' => (string) ($sub['current_period_end'] ?? '')];
    }

    /** Il testo della proposta: per l'email e (senza la riga del bottone) per WhatsApp. @return list<string> */
    public static function paragrafi(array $p, string $link = ''): array
    {
        $c = self::conti($p);
        $nome = trim(explode(' ', trim((string) Db::val('SELECT u.name FROM accounts a JOIN users u ON u.id = a.user_id WHERE a.id = ?', [(int) $p['account_id']], '')))[0] ?? '');
        $piano = (string) ($c['pv']['name'] ?? '') . (Plans::perProperty($c['pv']) ? ' (' . $c['q'] . ' strutture)' : '');
        $soldi = fn(int $x) => Support::money($x, $c['valuta']) . ' + IVA';
        $sc = (int) $p['sconto_pct'];
        $par = [($nome !== '' ? 'Ciao ' . $nome . ', ' : '') . 'ti proponiamo di passare a ' . $piano . '.'];
        if (trim((string) $p['messaggio']) !== '') $par[] = trim((string) $p['messaggio']);
        $par[] = $c['quando'] === 'subito'
            ? 'Parte subito: per i ' . $c['giorni'] . ' giorni che restano fino al rinnovo paghi la differenza, ' . ($sc > 0 ? $soldi($c['scontato']) . ' invece di ' . $soldi($c['pieno']) . ' (sconto del ' . $sc . '%)' : $soldi($c['pieno'])) . '. La data di rinnovo non cambia.'
            : 'Parte dal rinnovo del ' . Support::date($c['fine']) . ': il primo anno del piano nuovo costa ' . ($sc > 0 ? $soldi($c['scontato']) . ' invece di ' . $soldi($c['pieno']) . ' (sconto del ' . $sc . '%, al posto degli sconti di inviti e giudizio se sono più bassi)' : $soldi($c['pieno'])) . '. Fino ad allora resti sul piano di adesso.';
        $par[] = 'Vale solo con la tua conferma, entro il ' . Support::date((string) $p['valida_fino']) . '. Senza conferma non cambia niente.';
        if ($link !== '') $par[] = 'Per vedere i dettagli e confermare: ' . $link;
        return $par;
    }

    /** Manda la proposta per email (da assistenza@, Reply-To commerciale@). */
    public static function manda(array $p, string $token): bool
    {
        $u = Db::one('SELECT u.* FROM accounts a JOIN users u ON u.id = a.user_id WHERE a.id = ?', [(int) $p['account_id']]);
        if (!$u) return false;
        $c = self::conti($p);
        $oggetto = 'Una proposta per te: ' . ($c['pv']['name'] ?? '') . ((int) $p['sconto_pct'] > 0 ? ' con il ' . (int) $p['sconto_pct'] . '% di sconto' : '');
        $ok = Mailer::conRispostaA(Support::commerciale(), fn() => Mailer::manda((string) $u['email'], $oggetto, $oggetto, self::paragrafi($p), ['Accetta il cambio', self::link($token)],
                                                                             'Il link è personale. Se non ti interessa, ignora questa email: il tuo piano resta com\'è.'));
        Auth::audit('proposta.mandata', (int) $u['id'], ['id' => (int) $p['id'], 'esito' => (bool) $ok]);
        return (bool) $ok;
    }

    /** Il messaggio da copiare per WhatsApp (stesso link). */
    public static function whatsapp(array $p, string $token): string
    {
        return implode("\n\n", self::paragrafi($p, self::link($token)));
    }

    /**
     * Il cliente accetta dal link. Subito: ordine del conguaglio con lo sconto e Checkout (ritorna l'indirizzo di Stripe).
     * Dal rinnovo: il cambio programmato e lo sconto sul rinnovo (ritorna null).
     */
    public static function accetta(array $p, array $acc, array $user): ?string
    {
        if (Auth::isImpersonating()) throw new \RuntimeException('Da «Entra come cliente» non si accetta una proposta: la conferma è solo del cliente.');
        if ((int) $acc['id'] !== (int) $p['account_id']) throw new \RuntimeException('Questa proposta è per un altro account: entra con l\'account a cui è stata mandata.');
        $p = self::aggiorna($p);
        if ($p['stato'] !== 'inviata') throw new \RuntimeException($p['stato'] === 'scaduta' ? 'La proposta è scaduta: scrivici e te la rimandiamo.' : 'La proposta non è più aperta.');
        $sub = Subscriptions::active((int) $acc['id']);
        $pv = Plans::version((int) $p['package_version_id']);
        if (!$sub || !$pv) throw new \RuntimeException('Non hai un abbonamento attivo da cambiare: scrivici.');
        $q = max(1, (int) $p['quantita']);
        $sc = (int) $p['sconto_pct'];
        if ($p['quando'] === 'subito') {
            if (!Fatturazione::completa($acc)) throw new \RuntimeException('Completa prima i dati di fatturazione in Account & Fatturazione: servono per la fattura della differenza.');
            $c = self::conti($p);
            $o = CambioAbbonamento::ordine($acc, $sub, $pv, $q, $c['pieno']);
            $sconto = $c['pieno'] - $c['scontato'];
            if ($sconto > 0) Db::update('orders', ['discount_cents' => $sconto, 'sconti' => 'Cambio proposto −' . $sc . '%'], 'id = :id', ['id' => (int) $o['id']]);
            $url = Stripe::checkoutChange($o, CambioAbbonamento::descrizione(Plans::version((int) $sub['package_version_id']), (int) $sub['quantity'], $pv, $q), $acc, $user,
                                          $sconto > 0 ? self::coupon($sc) : null);
            Db::update('proposte_cambio', ['stato' => 'accettata', 'accettata_at' => Support::now(), 'order_id' => (int) $o['id']], 'id = :id', ['id' => (int) $p['id']]);
            Auth::audit('proposta.accettata', (int) $user['id'], ['id' => (int) $p['id'], 'quando' => 'subito', 'order' => (int) $o['id']]);
            return $url;
        }
        if (!Stripe::enabled() || $sub['provider'] !== 'stripe') throw new \RuntimeException('Il tuo abbonamento non si rinnova da solo: scrivici e facciamo il cambio insieme.');
        CambioAbbonamento::programma($sub, $pv, $q, ['sezioni' => [], 'archivia' => []]);
        Db::update('proposte_cambio', ['stato' => 'accettata', 'accettata_at' => Support::now(), 'rinnovo_del' => (string) $sub['current_period_end']], 'id = :id', ['id' => (int) $p['id']]);
        Auth::audit('proposta.accettata', (int) $user['id'], ['id' => (int) $p['id'], 'quando' => 'rinnovo', 'dal' => (string) $sub['current_period_end']]);
        try { Sconti::sincronizzaRinnovo((int) $acc['id']); } catch (\Throwable $e) { Log::exception($e, 'proposta di cambio: sconto sul rinnovo'); }
        return null;
    }

    /** L'amministrazione ritira una proposta ancora aperta. */
    public static function annulla(int $id, int $accountId): void
    {
        Db::run("UPDATE proposte_cambio SET stato = 'annullata' WHERE id = ? AND account_id = ? AND stato = 'inviata'", [$id, $accountId]);
        Auth::audit('proposta.annullata', (int) Db::val('SELECT user_id FROM accounts WHERE id = ?', [$accountId], 0) ?: null, ['id' => $id]);
    }

    /**
     * Lo sconto della proposta accettata «dal rinnovo» per il rinnovo in arrivo (0 se non c'è): vale finché la fine del periodo
     * è quella del giorno in cui è stata accettata. Dopo il rinnovo non vale più.
     */
    public static function scontoRinnovo(int $accountId, ?array $sub): int
    {
        if (!self::disponibili() || !$sub || (string) ($sub['current_period_end'] ?? '') === '') return 0;
        return (int) Db::val("SELECT MAX(sconto_pct) FROM proposte_cambio WHERE account_id = ? AND quando = 'rinnovo' AND stato = 'accettata' AND rinnovo_del = ?",
                             [$accountId, (string) $sub['current_period_end']], 0);
    }

    /** Il coupon della proposta: «mhw-cambio-N», percentuale una volta sola, creato se manca. */
    public static function coupon(int $percento): string
    {
        $id = self::COUPON . $percento;
        try { Stripe::call('GET', 'coupons/' . rawurlencode($id)); }
        catch (\RuntimeException) {
            Stripe::call('POST', 'coupons', ['id' => $id, 'percent_off' => (string) $percento, 'duration' => 'once', 'name' => 'Cambio di piano proposto'], 'mhw-coupon-' . $id);
        }
        return $id;
    }
}
