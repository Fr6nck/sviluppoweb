<?php
namespace MHW;

/**
 * 9I · I tre codici che servono a riconoscere al volo un cliente, una struttura, un pagamento.
 *
 *   cliente    MW-7K4P2          accounts.code      5 caratteri da ALFABETO (niente 0, O, 1, I)
 *   struttura  MW-7K4P2-01       properties.code    progressivo a 2 cifre, nell'ordine di creazione
 *   pagamento  MW-7K4P2-2026-1   orders.code        anno + progressivo dell'anno per quel cliente
 *              (subscriptions.code = il codice dell'ordine che l'ha creato; i rinnovi e i conguagli senza
 *              ordine hanno il loro codice nel registro, con l'id della fattura Stripe)
 *
 * Mai cambiano e mai si riusano: ogni codice generato entra nel registro codici_usati (indice unico), che
 * resta anche quando l'account si chiude o la struttura si cancella. I progressivi si contano dal registro.
 * Si generano alla creazione (registrazione, struttura nuova, ordine) e, se manca, la prima volta che servono.
 */
final class Codici
{
    public const ALFABETO = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    public static function disponibili(): bool
    {
        static $si = false;   // una volta pronto, resta pronto (le migrazioni non tolgono colonne)
        return $si || ($si = Migrator::tableExists('codici_usati') && Migrator::columnExists('accounts', 'code'));
    }

    /** Prenota un codice nel registro: false se esiste già (anche di un record cancellato). */
    private static function prenota(string $codice, string $livello, array $legami = []): bool
    {
        try {
            Db::insert('codici_usati', ['codice' => $codice, 'livello' => $livello, 'created_at' => Support::now()] + $legami);
            return true;
        } catch (\Throwable) { return false; }
    }

    /** Il codice cliente dell'account (MW-XXXXX), generato la prima volta. '' se il database non è ancora pronto. */
    public static function cliente(int $accountId): string
    {
        if ($accountId <= 0 || !self::disponibili()) return '';
        $c = (string) Db::val('SELECT code FROM accounts WHERE id = ?', [$accountId], '');
        if ($c !== '') return $c;
        if (!Db::val('SELECT id FROM accounts WHERE id = ?', [$accountId])) return '';
        for ($i = 0; $i < 50; $i++) {
            $nuovo = 'MW-';
            for ($k = 0; $k < 5; $k++) $nuovo .= self::ALFABETO[random_int(0, strlen(self::ALFABETO) - 1)];
            if (!self::prenota($nuovo, 'cliente', ['account_id' => $accountId])) continue;
            Db::run("UPDATE accounts SET code = ? WHERE id = ? AND (code IS NULL OR code = '')", [$nuovo, $accountId]);
            return (string) Db::val('SELECT code FROM accounts WHERE id = ?', [$accountId], $nuovo);   // un'altra richiesta può essere arrivata prima
        }
        throw new \RuntimeException('Non si riesce a generare un codice cliente unico.');
    }

    /** Il prossimo progressivo dopo quelli del registro che cominciano con $prefisso. */
    private static function prossimo(string $prefisso): int
    {
        $max = 0;
        foreach (Db::all('SELECT codice FROM codici_usati WHERE codice LIKE ?', [$prefisso . '%']) as $r) {
            $resto = substr((string) $r['codice'], strlen($prefisso));
            if (ctype_digit($resto)) $max = max($max, (int) $resto);
        }
        return $max + 1;
    }

    /** Il codice della struttura (MW-XXXXX-01), generato la prima volta. */
    public static function struttura(int $propertyId): string
    {
        if ($propertyId <= 0 || !self::disponibili() || !Migrator::columnExists('properties', 'code')) return '';
        $p = Db::one('SELECT id, account_id, code FROM properties WHERE id = ?', [$propertyId]);
        if (!$p) return '';
        if ((string) $p['code'] !== '') return (string) $p['code'];
        $cli = self::cliente((int) $p['account_id']);
        if ($cli === '') return '';
        for ($i = 0; $i < 20; $i++) {
            $nuovo = $cli . '-' . str_pad((string) self::prossimo($cli . '-'), 2, '0', STR_PAD_LEFT);
            // «-01»…«-99» (e oltre: tre cifre); non confonderlo con un pagamento, che ha l'anno a 4 cifre e poi il trattino
            if (!self::prenota($nuovo, 'struttura', ['account_id' => (int) $p['account_id'], 'property_id' => $propertyId])) continue;
            Db::run("UPDATE properties SET code = ? WHERE id = ? AND (code IS NULL OR code = '')", [$nuovo, $propertyId]);
            return (string) Db::val('SELECT code FROM properties WHERE id = ?', [$propertyId], $nuovo);
        }
        throw new \RuntimeException('Non si riesce a generare un codice struttura unico.');
    }

    /** Il codice del pagamento di un ordine (MW-XXXXX-2026-1), generato la prima volta. */
    public static function pagamento(int $orderId): string
    {
        if ($orderId <= 0 || !self::disponibili() || !Migrator::columnExists('orders', 'code')) return '';
        $o = Db::one('SELECT id, account_id, code, created_at FROM orders WHERE id = ?', [$orderId]);
        if (!$o) return '';
        if ((string) $o['code'] !== '') return (string) $o['code'];
        $nuovo = self::nuovoPagamento((int) $o['account_id'], (string) $o['created_at'], ['order_id' => $orderId]);
        if ($nuovo === '') return '';
        Db::run("UPDATE orders SET code = ? WHERE id = ? AND (code IS NULL OR code = '')", [$nuovo, $orderId]);
        return (string) Db::val('SELECT code FROM orders WHERE id = ?', [$orderId], $nuovo);
    }

    /** Il codice di un pagamento senza ordine (rinnovo, conguaglio): uno per fattura Stripe, sempre lo stesso. */
    public static function pagamentoFattura(int $accountId, string $invoiceId, string $quando = ''): string
    {
        if ($invoiceId === '' || !self::disponibili()) return '';
        $c = (string) Db::val("SELECT codice FROM codici_usati WHERE livello = 'pagamento' AND invoice_id = ?", [$invoiceId], '');
        return $c !== '' ? $c : self::nuovoPagamento($accountId, $quando ?: Support::now(), ['invoice_id' => $invoiceId]);
    }

    private static function nuovoPagamento(int $accountId, string $quando, array $legami): string
    {
        $cli = self::cliente($accountId);
        if ($cli === '') return '';
        $anno = preg_match('/^\d{4}/', $quando) ? substr($quando, 0, 4) : gmdate('Y');
        for ($i = 0; $i < 20; $i++) {
            $nuovo = $cli . '-' . $anno . '-' . self::prossimo($cli . '-' . $anno . '-');
            if (self::prenota($nuovo, 'pagamento', ['account_id' => $accountId] + $legami)) return $nuovo;
        }
        throw new \RuntimeException('Non si riesce a generare un codice pagamento unico.');
    }

    /** Il codice della prima struttura dell'account e, se sono di più, l'elenco (per i metadati di Stripe, max 500 caratteri). */
    public static function strutture(int $accountId): string
    {
        if (!self::disponibili()) return '';
        $codici = array_filter(array_map(fn($r) => self::struttura((int) $r['id']),
            Db::all('SELECT id FROM properties WHERE account_id = ? AND is_demo < 2 ORDER BY id', [$accountId])));
        return mb_substr(implode(',', $codici), 0, 500);
    }

    /**
     * Scritto come capita («mw 7k4p2», «MW7K4P2», «mw-7k4p2-01», «MW7K4P2 2026 1») → la forma giusta, o '' se non è un codice.
     */
    public static function normalizza(string $q): string
    {
        $s = strtoupper(trim((string) preg_replace('/[^A-Za-z0-9]+/', ' ', $q)));
        if (!preg_match('/^MW ?([' . self::ALFABETO . ']{5})(?![A-Z])(.*)$/', $s, $m)) return '';
        $base = 'MW-' . $m[1];
        preg_match_all('/\d+/', $m[2], $mm);
        $numeri = $mm[0];
        if (preg_match('/[A-Z]/', $m[2])) return '';   // dopo il codice solo numeri
        if (!$numeri) return $base;
        if (count($numeri) >= 2 && strlen($numeri[0]) === 4) return $base . '-' . $numeri[0] . '-' . (int) $numeri[1];
        $n = $numeri[0];
        if (count($numeri) === 1 && strlen($n) <= 3) return $base . '-' . str_pad((string) (int) $n, 2, '0', STR_PAD_LEFT);
        if (count($numeri) === 1 && strlen($n) >= 5 && preg_match('/^(20\d{2})(\d+)$/', $n, $a)) return $base . '-' . $a[1] . '-' . (int) $a[2];
        return '';
    }

    /** 9I · Per l'Amministrazione: il codice cliente come link alla scheda ('' se non c'è). */
    public static function link(int $accountId): string
    {
        $c = $accountId > 0 ? self::cliente($accountId) : '';
        return $c === '' ? '' : '<a class="codice-link" href="' . Support::e(Support::url('/admin/cliente/' . $accountId)) . '"><code>' . Support::e($c) . '</code></a>';
    }

    /** 9I · Il codice pagamento di una fattura Stripe, se il sito l'ha dato (rinnovi, conguagli). */
    public static function perFattura(string $invoiceId): string
    {
        return $invoiceId !== '' && self::disponibili() ? (string) Db::val("SELECT codice FROM codici_usati WHERE livello = 'pagamento' AND invoice_id = ?", [$invoiceId], '') : '';
    }

    // ------------------------------------------------------------------ ricerca unica (Amministrazione)

    /**
     * Trova i clienti da quello che si ha in mano: codice (cliente, struttura, pagamento, anche scritto male), email,
     * nome, telefono (solo cifre, anche senza +39), nome della struttura, città, CIN, indirizzo della guida (/g/… o
     * l'URL intero), partita IVA e codice fiscale, ID di Stripe (cus_, sub_, in_, pi_, ch_, cs_), codice sconto,
     * codice omaggio o codice d'invito. Query con parametri; uguaglianza sui codici e sugli ID, LIKE solo sui testi.
     * @return list<array{account_id:int,perche:list<string>,evidenzia:string}> al massimo $limite, i più sicuri prima
     */
    public static function trova(string $q, int $limite = 50): array
    {
        $q = trim(mb_substr($q, 0, 200));
        if ($q === '') return [];
        $trovati = [];
        $metti = function (int $aid, string $perche, string $evidenzia = '', int $peso = 1) use (&$trovati): void {
            if ($aid <= 0) return;
            $trovati[$aid] ??= ['account_id' => $aid, 'perche' => [], 'evidenzia' => '', 'peso' => 0];
            if (!in_array($perche, $trovati[$aid]['perche'], true)) $trovati[$aid]['perche'][] = $perche;
            if ($evidenzia !== '' && $trovati[$aid]['evidenzia'] === '') $trovati[$aid]['evidenzia'] = $evidenzia;
            $trovati[$aid]['peso'] = max($trovati[$aid]['peso'], $peso);
        };
        $colonna = fn(string $t, string $c) => Migrator::tableExists($t) && Migrator::columnExists($t, $c);
        $su = strtoupper($q);

        // 1. I codici MW-… (9N: anche senza «MW», per esempio «7K4P2» o «7k4p2-2026-1»; e l'inizio di un codice cliente, «MW-7K4»)
        $cod = self::normalizza($q);
        if ($cod === '' && preg_match('/^\s*[' . self::ALFABETO . ']{5}\b/i', $q)) $cod = self::normalizza('MW ' . $q);
        if (self::disponibili() && preg_match('/^(?:MW[\s-]?)?([' . self::ALFABETO . ']{2,4})$/', strtoupper(trim($q)), $mP) && Migrator::columnExists('accounts', 'code')) {
            foreach (Db::all('SELECT id FROM accounts WHERE code LIKE ? ORDER BY id DESC LIMIT 50', ['MW-' . $mP[1] . '%']) as $r) $metti((int) $r['id'], 'il codice cliente inizia così', '', 7);
        }
        if ($cod !== '' && self::disponibili()) {
            $trattini = substr_count($cod, '-');
            if ($trattini === 1) foreach (Db::all('SELECT id FROM accounts WHERE code = ?', [$cod]) as $r) $metti((int) $r['id'], 'trovato per codice cliente', '', 9);
            if ($trattini === 2) foreach (Db::all('SELECT id, account_id FROM properties WHERE code = ?', [$cod]) as $r) $metti((int) $r['account_id'], 'trovato per codice struttura', 'struttura-' . $r['id'], 9);
            if ($trattini === 3) {
                foreach (Db::all('SELECT account_id FROM codici_usati WHERE codice = ? AND account_id IS NOT NULL', [$cod]) as $r) $metti((int) $r['account_id'], 'trovato per codice pagamento', 'pagamento-' . $cod, 9);
            }
        }
        // 2. Gli ID di Stripe: uguaglianza esatta; per fatture, pagamenti e addebiti anche nei rimborsi e negli eventi ricevuti.
        if (preg_match('/^(cus|sub|in|pi|ch|cs|re)_[A-Za-z0-9]{4,}$/', $q, $mS)) {
            $tipo = $mS[1];
            if ($tipo === 'cus') {
                foreach (Db::all('SELECT id FROM accounts WHERE stripe_customer_id = ?', [$q]) as $r) $metti((int) $r['id'], 'trovato per cliente Stripe', '', 8);
                foreach (Db::all('SELECT account_id FROM subscriptions WHERE provider_customer_id = ?', [$q]) as $r) $metti((int) $r['account_id'], 'trovato per cliente Stripe', '', 8);
            }
            if ($tipo === 'sub') foreach (Db::all('SELECT account_id FROM subscriptions WHERE provider_subscription_id = ?', [$q]) as $r) $metti((int) $r['account_id'], 'trovato per abbonamento Stripe', '', 8);
            if ($tipo === 'cs') foreach (Db::all('SELECT account_id FROM orders WHERE provider_session_id = ?', [$q]) as $r) $metti((int) $r['account_id'], 'trovato per sessione di pagamento Stripe', '', 8);
            if (in_array($tipo, ['in', 'pi', 'ch', 're'], true)) {
                if (Migrator::tableExists('rimborsi')) foreach (Db::all('SELECT account_id FROM rimborsi WHERE invoice_id = ? OR charge_id = ? OR refund_id = ?', [$q, $q, $q]) as $r) $metti((int) $r['account_id'], 'trovato per rimborso Stripe', '', 8);
                if (self::disponibili()) foreach (Db::all('SELECT account_id FROM codici_usati WHERE invoice_id = ?', [$q]) as $r) $metti((int) $r['account_id'], 'trovato per fattura Stripe', '', 8);
                // Negli eventi ricevuti da Stripe: l'evento che nomina l'ID dice il cliente Stripe, e da lì l'account.
                foreach (Db::all('SELECT payload FROM webhook_events WHERE payload LIKE ? ORDER BY id DESC LIMIT 5', ['%"' . $q . '"%']) as $ev) {
                    $cus = preg_match('/"customer":"(cus_[A-Za-z0-9]+)"/', (string) $ev['payload'], $mc) ? $mc[1] : '';
                    if ($cus !== '') foreach (Db::all('SELECT id FROM accounts WHERE stripe_customer_id = ?', [$cus]) as $r) $metti((int) $r['id'], 'trovato per pagamento Stripe', '', 8);
                }
            }
        }
        // 3. L'indirizzo della guida: /g/slug, anche dentro un URL intero; o lo slug da solo.
        $slug = preg_match('#/g/([a-z0-9-]+)#i', $q, $mG) ? strtolower($mG[1]) : (preg_match('/^[a-z0-9]+(-[a-z0-9]+)+$/', $q) ? $q : '');
        if ($slug !== '') foreach (Db::all('SELECT id, account_id FROM properties WHERE slug = ?', [$slug]) as $r) $metti((int) $r['account_id'], 'trovato per indirizzo della guida', 'struttura-' . $r['id'], 8);
        // 4. CIN (uguale, senza spazi), partita IVA (senza IT) e codice fiscale.
        $compatto = strtoupper((string) preg_replace('/\s+/', '', $q));
        if (strlen($compatto) >= 8) {
            foreach (Db::all('SELECT id, account_id FROM properties WHERE UPPER(cin) = ?', [$compatto]) as $r) $metti((int) $r['account_id'], 'trovato per CIN', 'struttura-' . $r['id'], 8);
            $piva = preg_replace('/^IT/', '', $compatto);
            if (ctype_digit((string) $piva) && strlen((string) $piva) === 11) foreach (Db::all('SELECT id FROM accounts WHERE vat = ? OR cf = ?', [$piva, $piva]) as $r) $metti((int) $r['id'], 'trovato per partita IVA', '', 8);
            if (preg_match('/^[A-Z0-9]{16}$/', $compatto)) foreach (Db::all('SELECT id FROM accounts WHERE UPPER(cf) = ?', [$compatto]) as $r) $metti((int) $r['id'], 'trovato per codice fiscale', '', 8);
        }
        // 5. Codice sconto usato, codice omaggio, codice d'invito.
        if (preg_match('/^[A-Z0-9][A-Z0-9-]{3,39}$/', $su)) {
            if (Migrator::tableExists('discount_redemptions')) foreach (Db::all('SELECT r.account_id FROM discount_redemptions r JOIN discount_codes d ON d.id = r.discount_code_id WHERE UPPER(d.code) = ?', [$su]) as $r) $metti((int) $r['account_id'], 'ha usato il codice sconto ' . $su, '', 7);
            foreach (Db::all('SELECT o.account_id FROM orders o JOIN discount_codes d ON d.id = o.discount_code_id WHERE UPPER(d.code) = ?', [$su]) as $r) $metti((int) $r['account_id'], 'ha usato il codice sconto ' . $su, '', 7);
            if (Migrator::tableExists('codici_omaggio')) foreach (Db::all('SELECT usato_da_account_id FROM codici_omaggio WHERE UPPER(codice) = ? AND usato_da_account_id IS NOT NULL', [$su]) as $r) $metti((int) $r['usato_da_account_id'], 'ha usato il codice omaggio ' . $su, '', 7);
            if ($colonna('accounts', 'referral_code')) {
                foreach (Db::all('SELECT id FROM accounts WHERE UPPER(referral_code) = ?', [$su]) as $r) {
                    $metti((int) $r['id'], 'è il suo codice d\'invito', '', 7);
                    if (Migrator::tableExists('referrals')) foreach (Db::all('SELECT friend_account_id FROM referrals WHERE referrer_account_id = ? AND friend_account_id IS NOT NULL', [(int) $r['id']]) as $f) $metti((int) $f['friend_account_id'], 'ha usato il codice d\'invito ' . $su, '', 6);
                }
            }
        }
        // 6. Email (anche l'email in attesa di conferma e la PEC).
        if (str_contains($q, '@')) {
            $l = '%' . mb_strtolower($q) . '%';
            foreach (Db::all("SELECT a.id FROM users u JOIN accounts a ON a.user_id = u.id WHERE LOWER(u.email) LIKE ? OR LOWER(COALESCE(u.pending_email, '')) LIKE ? OR LOWER(COALESCE(a.pec, '')) LIKE ?", [$l, $l, $l]) as $r) {
                $metti((int) $r['id'], 'trovato per email', '', 6);
            }
        }
        // 7. Telefono: solo le cifre; «+39 333…», «0039 333…» e «333…» si trovano a vicenda.
        $cifre = preg_replace('/\D/', '', $q);
        if (strlen((string) $cifre) >= 6 && strlen((string) $cifre) <= 15 && preg_match('/^[\d\s+().\/-]+$/', $q)) {
            $cifre = preg_replace('/^(00)?39(?=\d{6,})/', '', (string) $cifre);
            $pulito = fn(string $c) => "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE($c, ''), ' ', ''), '+', ''), '-', ''), '.', ''), '/', ''), '(', '')";
            foreach (Db::all('SELECT id, account_id FROM properties WHERE ' . $pulito('host_phone') . ' LIKE ? OR ' . $pulito('host_whatsapp') . ' LIKE ?', ['%' . $cifre . '%', '%' . $cifre . '%']) as $r) $metti((int) $r['account_id'], 'trovato per telefono', 'struttura-' . $r['id'], 6);
            if (Migrator::tableExists('property_contacts')) foreach (Db::all('SELECT p.id, p.account_id FROM property_contacts c JOIN properties p ON p.id = c.property_id WHERE ' . $pulito('c.phone') . ' LIKE ? OR ' . $pulito('c.whatsapp') . ' LIKE ?', ['%' . $cifre . '%', '%' . $cifre . '%']) as $r) $metti((int) $r['account_id'], 'trovato per telefono', 'struttura-' . $r['id'], 6);
            if (Migrator::tableExists('preventivi')) foreach (Db::all('SELECT account_id FROM preventivi WHERE account_id IS NOT NULL AND ' . $pulito('telefono') . ' LIKE ?', ['%' . $cifre . '%']) as $r) $metti((int) $r['account_id'], 'trovato per telefono (preventivo)', '', 6);
        }
        // 8. I testi: nome, intestatario, nome della struttura, città (almeno 2 caratteri).
        if (mb_strlen($q) >= 2 && !str_contains($q, '@')) {
            $l = '%' . mb_strtolower($q) . '%';
            foreach (Db::all("SELECT a.id FROM users u JOIN accounts a ON a.user_id = u.id WHERE LOWER(u.name) LIKE ? OR LOWER(COALESCE(a.billing_name, '')) LIKE ?", [$l, $l]) as $r) $metti((int) $r['id'], 'trovato per nome', '', 4);
            foreach (Db::all('SELECT id, account_id FROM properties WHERE LOWER(name) LIKE ?', [$l]) as $r) $metti((int) $r['account_id'], 'trovato per nome della struttura', 'struttura-' . $r['id'], 4);
            foreach (Db::all('SELECT id, account_id FROM properties WHERE LOWER(city) LIKE ?', [$l]) as $r) $metti((int) $r['account_id'], 'trovato per città', '', 3);
        }
        // Solo i clienti (non gli amministratori), i più sicuri prima.
        $ids = array_keys($trovati);
        if ($ids) {
            $host = array_column(Db::all('SELECT a.id FROM accounts a JOIN users u ON u.id = a.user_id WHERE u.role = ? AND a.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')', array_merge(['host'], $ids)), 'id');
            $trovati = array_intersect_key($trovati, array_flip(array_map('intval', $host)));
        }
        uasort($trovati, fn($a, $b) => $b['peso'] <=> $a['peso'] ?: $b['account_id'] <=> $a['account_id']);
        return array_map(fn($t) => ['account_id' => $t['account_id'], 'perche' => $t['perche'], 'evidenzia' => $t['evidenzia']], array_values(array_slice($trovati, 0, $limite, true)));
    }
}
