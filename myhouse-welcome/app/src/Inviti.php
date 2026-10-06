<?php
namespace MHW;

/**
 * Invita un amico.
 *
 * Chi ha un abbonamento attivo ha un link personale (/i/CODICE). Chi si registra
 * da quel link ha AMICO% sul primo anno; chi ha invitato ha PASSO% in meno sul
 * prossimo rinnovo per ogni amico che PAGA, fino a MASSIMO%.
 *
 * Lato amico non c'è niente di nuovo su Stripe: lo sconto è una riga «di sistema»
 * in discount_codes, quindi passa dallo stesso percorso dei codici sconto (6E):
 * coupon «una volta» nel checkout, utilizzo registrato dal webhook.
 *
 * Lato chi invita: un coupon «una volta» (mhw-invito-5 … mhw-invito-50) messo
 * sull'abbonamento Stripe. Sconta la prossima fattura, cioè il rinnovo, e poi
 * Stripe lo toglie da solo. A ogni amico in più il coupon si sostituisce con
 * quello della percentuale nuova.
 *
 * Le regole di Billing valgono anche qui: dentro la transazione del webhook si
 * scrive solo sul database (pagato, fattura); Stripe e le email partono dopo
 * (dopoEvento). Se Stripe non risponde, inSospeso() riprova al giro dei Richiami.
 */
final class Inviti
{
    /** Sconto dell'amico sul primo anno, in percento. */
    public const AMICO = 5;
    /** Sconto di chi invita sul prossimo rinnovo, per ogni amico che paga. */
    public const PASSO = 5;
    /** Tetto dello sconto di chi invita, in percento. */
    public const MASSIMO = 50;
    /** Tetto in centesimi allo sconto di chi invita (0 = nessuno). Utile con i Portfolio grandi. */
    public const TETTO_CENTS = 0;

    private const COUPON = 'mhw-invito-';
    private const ALFABETO = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /** Da riallineare su Stripe dopo la transazione: [account di chi invita => account dell'amico appena valido, o 0]. */
    private static array $coda = [];

    /** Acceso dalla configurazione (inviti.attivi, MHW_INVITI=1) e con la migrazione 019 applicata. */
    public static function disponibili(): bool
    {
        return !empty((Config::get('inviti') ?? [])['attivi']) && Migrator::tableExists('referrals');
    }

    public static function amiciMassimi(): int { return intdiv(self::MASSIMO, self::PASSO); }

    /** Invita chi ha un abbonamento Stripe attivo: è lì che si applica lo sconto. */
    public static function puoInvitare(array $acc): bool
    {
        if (!self::disponibili()) return false;
        $s = Subscriptions::active((int) $acc['id']);
        return $s !== null && $s['provider'] === 'stripe' && (string) $s['provider_subscription_id'] !== '';
    }

    // ------------------------------------------------------------ codice e link

    /** Il codice personale: 6 caratteri leggibili, creato la prima volta che serve. */
    public static function codice(array $acc): string
    {
        $c = (string) (Db::val('SELECT referral_code FROM accounts WHERE id = ?', [(int) $acc['id']], '') ?? '');
        if ($c !== '') return $c;
        do {
            $c = '';
            for ($i = 0; $i < 6; $i++) $c .= self::ALFABETO[random_int(0, strlen(self::ALFABETO) - 1)];
        } while (Db::one('SELECT id FROM accounts WHERE referral_code = ?', [$c]) || Sconti::trova($c));
        Db::update('accounts', ['referral_code' => $c], 'id = :aid', ['aid' => (int) $acc['id']]);
        return $c;
    }

    public static function link(array $acc): string { return Support::baseUrl() . '/i/' . self::codice($acc); }

    /** L'account che invita, dal codice. Con user_name ed email. */
    public static function daCodice(string $codice): ?array
    {
        $c = strtoupper(trim($codice));
        if (!preg_match('/^[A-Z0-9]{6}$/', $c) || !self::disponibili()) return null;
        return Db::one('SELECT a.*, u.name AS user_name, u.email FROM accounts a JOIN users u ON u.id = a.user_id WHERE a.referral_code = ?', [$c]);
    }

    /** «Marco B.»: quanto basta per riconoscersi, senza mostrare il cognome. */
    public static function nomeBreve(string $nome): string
    {
        $p = preg_split('/\s+/', trim($nome)) ?: [];
        if (!$p || $p[0] === '') return 'Un amico';
        return $p[0] . (isset($p[1]) ? ' ' . mb_strtoupper(mb_substr($p[1], 0, 1)) . '.' : '');
    }

    // ---------------------------------------------------------------- lato amico

    /** La riga di sistema in discount_codes che dà all'amico il suo sconto. Nasce alla prima occorrenza. */
    public static function codiceAmico(): ?array
    {
        if (!self::disponibili() || !Sconti::disponibili()) return null;
        $r = Db::one("SELECT * FROM discount_codes WHERE sistema = 1 AND active = 1 AND kind = 'percent' AND value = ? ORDER BY id DESC", [self::AMICO]);
        if (!$r) {
            $code = 'INVITO-AMICO-' . self::AMICO;
            while (Sconti::trova($code)) $code = 'INVITO-AMICO-' . self::AMICO . '-' . self::ALFABETO[random_int(0, 31)] . self::ALFABETO[random_int(0, 31)];
            $id = Db::insert('discount_codes', [
                'code' => $code, 'kind' => 'percent', 'value' => self::AMICO,
                'valid_from' => Sconti::oggi(), 'valid_until' => '2099-12-31', 'max_uses' => 0, 'packages' => '',
                'note' => 'Invita un amico: lo sconto dell\'amico (di sistema)', 'active' => 1, 'sistema' => 1,
                'stripe_coupon_id' => '', 'created_at' => Support::now(),
            ]);
            $r = Sconti::riga($id);
        }
        if ((string) $r['stripe_coupon_id'] === '' && Sconti::sincronizza($r)) $r = Sconti::riga((int) $r['id']);
        return $r;
    }

    /** L'invito di questo account, se è stato invitato (e l'invito non è annullato). Con referrer_name. */
    public static function invitato(int $accountId): ?array
    {
        if (!self::disponibili()) return null;
        return Db::one("SELECT r.*, u.name AS referrer_name FROM referrals r JOIN accounts a ON a.id = r.referrer_account_id
                        JOIN users u ON u.id = a.user_id WHERE r.friend_account_id = ? AND r.status <> 'annullato'", [$accountId]);
    }

    /**
     * Lega l'amico a chi l'ha invitato e gli applica lo sconto. Solo clienti nuovi,
     * un solo invito per account, mai il proprio. Non sostituisce un codice sconto
     * più alto già applicato: i due non si sommano.
     */
    public static function collega(array $amico, array $chiInvita): bool
    {
        $aid = (int) $amico['id']; $rid = (int) $chiInvita['id'];
        if ($aid === $rid || !self::puoInvitare($chiInvita)) return false;
        if (Db::one('SELECT id FROM referrals WHERE friend_account_id = ?', [$aid])) return false;
        if (Db::one("SELECT id FROM orders WHERE account_id = ? AND status = 'paid'", [$aid])) return false;
        Db::insert('referrals', ['referrer_account_id' => $rid, 'friend_account_id' => $aid, 'status' => 'registrato', 'created_at' => Support::now()]);
        $gia = !empty($amico['intended_discount_code_id']) ? Sconti::riga((int) $amico['intended_discount_code_id']) : null;
        $piuAlto = $gia && !($gia['kind'] === 'percent' && (int) $gia['value'] < self::AMICO);
        if (!$piuAlto && ($c = self::codiceAmico())) {
            Db::update('accounts', ['intended_discount_code_id' => (int) $c['id']], 'id = :aid', ['aid' => $aid]);
        }
        return true;
    }

    /**
     * L'amico scrive il codice di invito dove si mette il codice sconto.
     * @return string|null il nome di chi invita; null se non è un codice di invito
     * @throws \RuntimeException con il motivo, da mostrare sotto il campo
     */
    public static function applicaCodice(array $amico, string $codice): ?string
    {
        $chi = self::daCodice($codice);
        if (!$chi) return null;
        $aid = (int) $amico['id'];
        if ((int) $chi['id'] === $aid) throw new \RuntimeException('Questo è il tuo codice di invito: è per i tuoi amici.');
        $gia = Db::one('SELECT * FROM referrals WHERE friend_account_id = ?', [$aid]);
        if ($gia && ((int) $gia['referrer_account_id'] !== (int) $chi['id'] || $gia['status'] !== 'registrato')) {
            throw new \RuntimeException('Hai già usato un invito.');
        }
        if (!$gia) {
            if (!self::puoInvitare($chi)) throw new \RuntimeException('Questo invito non è più attivo.');
            if (!self::collega($amico, $chi)) throw new \RuntimeException('L\'invito vale solo per il primo abbonamento.');
        }
        // Chi scrive il codice vuole questo sconto: prende il posto di un altro codice.
        if (($c = self::codiceAmico())) Db::update('accounts', ['intended_discount_code_id' => (int) $c['id']], 'id = :aid', ['aid' => $aid]);
        return self::nomeBreve((string) $chi['user_name']);
    }

    // ------------------------------------------------ dentro la transazione del webhook

    /** Da Billing::activate: l'amico ha pagato il suo primo abbonamento. Solo database. */
    public static function pagato(array $order): void
    {
        if (!self::disponibili()) return;
        $aid = (int) $order['account_id'];
        $r = Db::one("SELECT * FROM referrals WHERE friend_account_id = ? AND status = 'registrato'", [$aid]);
        if (!$r) return;
        // Conta solo il primo ordine pagato (quello che activate ha appena segnato).
        if ((int) Db::val("SELECT COUNT(*) FROM orders WHERE account_id = ? AND status = 'paid'", [$aid], 0) !== 1) return;
        $rid = (int) $r['referrer_account_id'];
        $a = Db::one('SELECT vat, cf FROM accounts WHERE id = ?', [$aid]) ?: [];
        $b = Db::one('SELECT vat, cf FROM accounts WHERE id = ?', [$rid]) ?: [];
        $uguale = fn(string $k) => trim((string) ($a[$k] ?? '')) !== '' && strtoupper(trim((string) $a[$k])) === strtoupper(trim((string) ($b[$k] ?? '')));
        $stessi = $uguale('vat') || $uguale('cf');
        $stato = $stessi ? 'annullato' : (self::validi($rid) >= self::amiciMassimi() ? 'oltre' : 'valido');
        Db::update('referrals', ['status' => $stato, 'order_id' => (int) $order['id'], 'qualified_at' => Support::now()], 'id = :id', ['id' => $r['id']]);
        if ($stessi) { Log::error('Inviti: stessi dati di fatturazione di chi invita, invito annullato', ['referral' => (int) $r['id']]); return; }
        self::$coda[$rid] = $aid;
    }

    /**
     * Da Billing::onInvoicePaid, per un abbonamento già noto. Solo database.
     * Un rinnovo scontato chiude gli inviti che lo hanno pagato. Ogni altra fattura
     * dell'abbonamento (il conguaglio di una struttura in più) può aver consumato il
     * coupon «una volta»: si azzera il segno e, dopo la transazione, si riallinea.
     */
    public static function fattura(array $sub, array $inv): void
    {
        if (!self::disponibili()) return;
        $aid = (int) $sub['account_id'];
        $applicato = (int) Db::val('SELECT referral_applied_percent FROM accounts WHERE id = ?', [$aid], 0);
        if ($applicato <= 0) return;
        $sconto = 0;
        foreach ((array) ($inv['total_discount_amounts'] ?? []) as $d) $sconto += (int) ($d['amount'] ?? 0);
        if ($sconto === 0 && isset($inv['subtotal'], $inv['total_excluding_tax'])) $sconto = max(0, (int) $inv['subtotal'] - (int) $inv['total_excluding_tax']);
        $rif = (string) ($inv['id'] ?? '');
        if (($inv['billing_reason'] ?? '') === 'subscription_cycle') {
            if ($sconto > 0) {
                $n = max(1, intdiv($applicato, self::PASSO));
                foreach (Db::all("SELECT id FROM referrals WHERE referrer_account_id = ? AND status = 'valido' ORDER BY qualified_at, id LIMIT " . $n, [$aid]) as $r) {
                    Db::update('referrals', ['status' => 'usato', 'used_at' => Support::now(), 'used_ref' => $rif], 'id = :id', ['id' => $r['id']]);
                }
                // Gli inviti oltre il tetto non passano all'anno dopo: si chiudono con questo rinnovo.
                Db::run("UPDATE referrals SET status = 'usato', used_at = ?, used_ref = ? WHERE referrer_account_id = ? AND status = 'oltre'", [Support::now(), $rif, $aid]);
                Auth::audit('referral.redeemed', null, ['account_id' => $aid, 'percent' => $applicato, 'discount_cents' => $sconto, 'invoice' => $rif]);
            } else {
                Log::error('Inviti: rinnovo senza lo sconto atteso', ['account' => $aid, 'percent' => $applicato, 'invoice' => $rif]);
            }
        }
        Db::update('accounts', ['referral_applied_percent' => 0], 'id = :aid', ['aid' => $aid]);
        if (!isset(self::$coda[$aid])) self::$coda[$aid] = 0;
    }

    // ---------------------------------------------------- dopo la transazione: Stripe

    /** Da Billing::handleEvent, a transazione chiusa. Un errore qui non fa fallire il webhook. */
    public static function dopoEvento(): void
    {
        $coda = self::$coda; self::$coda = [];
        foreach ($coda as $rid => $amico) {
            try { self::sincronizza((int) $rid); } catch (\Throwable $e) { Log::exception($e, 'inviti: sconto su Stripe'); }
            if ($amico) { try { self::avvisa((int) $rid, (int) $amico); } catch (\Throwable $e) { Log::exception($e, 'inviti: email'); } }
        }
    }

    public static function validi(int $accountId): int
    {
        return (int) Db::val("SELECT COUNT(*) FROM referrals WHERE referrer_account_id = ? AND status = 'valido'", [$accountId], 0);
    }

    public static function percento(int $accountId): int { return min(self::MASSIMO, self::PASSO * self::validi($accountId)); }

    /**
     * Porta sull'abbonamento Stripe lo sconto guadagnato: toglie il coupon inviti che
     * c'è, mette quello giusto, lascia stare gli altri sconti. Chiamate di rete:
     * mai dentro una transazione. Si può ripetere: se è già a posto non scrive niente.
     */
    public static function sincronizza(int $accountId): bool
    {
        if (!self::disponibili() || !Stripe::enabled()) return false;
        $s = Subscriptions::active($accountId);
        if (!$s || $s['provider'] !== 'stripe' || (string) $s['provider_subscription_id'] === '') return false;
        $voluto = self::percento($accountId);
        $sid = (string) $s['provider_subscription_id'];
        $sub = Stripe::call('GET', 'subscriptions/' . rawurlencode($sid), ['expand' => ['discounts']]);
        $tieni = []; $presente = '';
        foreach ((array) ($sub['discounts'] ?? []) as $d) {
            if (!is_array($d)) { $tieni[] = (string) $d; continue; }
            // Nelle API recenti il coupon sta in source.coupon, in quelle vecchie in coupon.
            $c = $d['coupon'] ?? ($d['source']['coupon'] ?? '');
            $cid = is_array($c) ? (string) ($c['id'] ?? '') : (string) $c;
            if (str_starts_with($cid, self::COUPON)) $presente = $cid; else $tieni[] = (string) ($d['id'] ?? '');
        }
        $coupon = $voluto > 0 ? self::coupon($voluto, $s) : '';
        if ($presente !== $coupon) {
            $p = []; $i = 0;
            foreach (array_filter($tieni) as $did) $p['discounts[' . $i++ . '][discount]'] = $did;
            if ($coupon !== '') $p['discounts[' . $i . '][coupon]'] = $coupon;
            Stripe::call('POST', 'subscriptions/' . rawurlencode($sid), $p ?: ['discounts' => '']);
        }
        Db::update('accounts', ['referral_applied_percent' => $voluto], 'id = :aid', ['aid' => $accountId]);
        return true;
    }

    /** Il coupon Stripe di una percentuale: id fisso, creato la prima volta. Con il tetto in euro, un importo fisso. */
    private static function coupon(int $percento, array $sub): string
    {
        $id = self::COUPON . $percento; $p = ['percent_off' => (string) $percento];
        if (self::TETTO_CENTS > 0) {
            $pv = Db::one('SELECT * FROM package_versions WHERE id = ?', [(int) $sub['package_version_id']]) ?: [];
            $prezzo = $pv ? Plans::price($pv, max(1, (int) ($sub['quantity'] ?? 1))) : 0;
            if ((int) round($prezzo * $percento / 100) > self::TETTO_CENTS) {
                $id = self::COUPON . 'tetto-' . self::TETTO_CENTS; $p = ['amount_off' => (string) self::TETTO_CENTS, 'currency' => 'eur'];
            }
        }
        try { Stripe::call('GET', 'coupons/' . rawurlencode($id)); }
        catch (\RuntimeException) {
            Stripe::call('POST', 'coupons', $p + ['id' => $id, 'duration' => 'once', 'name' => 'Invita un amico'], 'mhw-coupon-' . $id);
        }
        return $id;
    }

    /** Dal giro dei Richiami: gli account il cui sconto su Stripe non è quello guadagnato. */
    public static function inSospeso(): void
    {
        if (!self::disponibili() || !Stripe::enabled()) return;
        $righe = Db::all("SELECT a.id, a.referral_applied_percent AS applicato,
                                 (SELECT COUNT(*) FROM referrals r WHERE r.referrer_account_id = a.id AND r.status = 'valido') AS validi
                          FROM accounts a
                          WHERE a.referral_applied_percent > 0 OR a.id IN (SELECT referrer_account_id FROM referrals WHERE status = 'valido')");
        foreach ($righe as $r) {
            if (min(self::MASSIMO, self::PASSO * (int) $r['validi']) === (int) $r['applicato']) continue;
            try { self::sincronizza((int) $r['id']); } catch (\Throwable $e) { Log::exception($e, 'inviti: riallineamento'); }
        }
    }

    /** L'email a chi ha invitato: una per amico, con il link per non riceverne più (Richiami). */
    private static function avvisa(int $rid, int $amicoId): void
    {
        $u = Db::one('SELECT u.email, u.name FROM accounts a JOIN users u ON u.id = a.user_id WHERE a.id = ?', [$rid]);
        if (!$u || !Richiami::disponibili()) return;
        $amico = self::nomeBreve((string) Db::val('SELECT u.name FROM accounts a JOIN users u ON u.id = a.user_id WHERE a.id = ?', [$amicoId], ''));
        $st = self::stato(['id' => $rid]);
        $mancano = $st['massimo'] - $st['validi'];
        $corpo = $amico . ' ha pubblicato la sua guida con il tuo invito. ';
        if ($st['percento'] > 0) {
            $corpo .= 'Il tuo sconto sul prossimo rinnovo ' . ($mancano > 0 ? 'sale al ' : 'è al ') . $st['percento'] . '%'
                    . ($st['rinnovo'] !== '' && $st['prezzo'] > 0 ? ': il ' . Support::date($st['rinnovo']) . ' paghi ' . Support::money($st['scontato'], $st['valuta'])
                       . ' + IVA invece di ' . Support::money($st['prezzo'], $st['valuta']) : '') . '. ';
        }
        $corpo .= $mancano > 0 ? ($mancano === 1 ? 'Ti manca 1 amico' : 'Ti mancano ' . $mancano . ' amici') . ' per arrivare a metà prezzo.'
                               : 'Hai raggiunto il massimo. I tuoi amici continuano ad avere il loro sconto.';
        $oggetto = $amico . ' ha pubblicato la sua guida' . ($st['percento'] > 0 ? ': sei al −' . $st['percento'] . '%' : '');
        Richiami::manda($rid, (string) $u['email'], (string) $u['name'], 'inviti', 'amico-' . $amicoId,
                        [$oggetto, $corpo, 'Invita un altro amico', Support::baseUrl() . '/inviti']);
    }

    // ------------------------------------------------------------------ per le pagine

    /** Quello che il pannello mostra: amici, percentuale, quanto si paga al rinnovo. */
    public static function stato(array $acc): array
    {
        $aid = (int) $acc['id'];
        $validi = self::validi($aid);
        $percento = min(self::MASSIMO, self::PASSO * $validi);
        $s = Subscriptions::active($aid);
        $pv = $s ? (Db::one('SELECT * FROM package_versions WHERE id = ?', [(int) $s['package_version_id']]) ?: null) : null;
        $prezzo = $pv ? Plans::price($pv, max(1, (int) ($s['quantity'] ?? 1))) : 0;
        $sconto = (int) round($prezzo * $percento / 100);
        if (self::TETTO_CENTS > 0) $sconto = min($sconto, self::TETTO_CENTS);
        return [
            'validi' => $validi, 'massimo' => self::amiciMassimi(), 'percento' => $percento,
            'oltre' => (int) Db::val("SELECT COUNT(*) FROM referrals WHERE referrer_account_id = ? AND status = 'oltre'", [$aid], 0),
            'in_attesa' => (int) Db::val("SELECT COUNT(*) FROM referrals WHERE referrer_account_id = ? AND status = 'registrato'", [$aid], 0),
            'prezzo' => $prezzo, 'sconto' => $sconto, 'scontato' => $prezzo - $sconto, 'valuta' => (string) ($pv['currency'] ?? 'EUR'),
            'rinnovo' => (string) ($s['current_period_end'] ?? ''),
            'automatico' => $s !== null && (int) $s['cancel_at_period_end'] === 0,
        ];
    }

    /** Gli amici invitati, dal più recente: nome breve, stato, date. */
    public static function amici(int $accountId): array
    {
        $righe = Db::all("SELECT r.id, r.status, r.created_at, r.qualified_at, u.name FROM referrals r JOIN accounts a ON a.id = r.friend_account_id
                          JOIN users u ON u.id = a.user_id WHERE r.referrer_account_id = ? AND r.status <> 'annullato' ORDER BY r.id DESC LIMIT 50", [$accountId]);
        foreach ($righe as &$r) $r['nome'] = self::nomeBreve((string) $r['name']);
        unset($r);
        return $righe;
    }

    /** Dall'amministrazione: un invito non conta più (rimborso, abuso). Uno «oltre» prende il suo posto. */
    public static function annulla(int $id): void
    {
        $r = Db::one('SELECT * FROM referrals WHERE id = ?', [$id]);
        if (!$r || in_array($r['status'], ['usato', 'annullato'], true)) return;
        Db::update('referrals', ['status' => 'annullato'], 'id = :id', ['id' => $id]);
        if ($r['status'] === 'valido') {
            $o = Db::one("SELECT id FROM referrals WHERE referrer_account_id = ? AND status = 'oltre' ORDER BY qualified_at, id", [(int) $r['referrer_account_id']]);
            if ($o) Db::update('referrals', ['status' => 'valido'], 'id = :id', ['id' => $o['id']]);
        }
        try { self::sincronizza((int) $r['referrer_account_id']); } catch (\Throwable $e) { Log::exception($e, 'inviti: annulla'); }
    }
}
