<?php
namespace MHW;

/**
 * I codici sconto del primo anno (fase 6E).
 *
 * Un codice nasce in amministrazione e diventa un coupon Stripe con duration=once:
 * su un abbonamento annuale sconta solo la prima fattura, quindi dal rinnovo si
 * paga il prezzo pieno. Su Stripe un coupon non si modifica: per cambiare valore
 * o date si disattiva e se ne crea un altro.
 *
 * Il limite per piano si controlla qui (i prodotti del checkout nascono al volo).
 * Gli utilizzi si contano dalle righe di discount_redemptions, mai da un contatore.
 * Le date sono giorni interi in Europe/Rome, estremi compresi.
 */
final class Sconti
{
    /** Le lettere dei codici generati: niente 0 O 1 I, che si confondono. */
    private const ALFABETO = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public static function disponibili(): bool { return Migrator::tableExists('discount_codes'); }

    public static function oggi(): string { return Eventi::oggi(); }

    /** «MHW-» più 6 caratteri leggibili, mai già usato. */
    public static function genera(): string
    {
        do {
            $c = 'MHW-';
            for ($i = 0; $i < 6; $i++) $c .= self::ALFABETO[random_int(0, strlen(self::ALFABETO) - 1)];
        } while (self::trova($c));
        return $c;
    }

    public static function normalizza(string $codice): string { return strtoupper(trim($codice)); }

    public static function trova(string $codice): ?array
    {
        if (!self::disponibili()) return null;
        return Db::one('SELECT * FROM discount_codes WHERE code = ?', [self::normalizza($codice)]);
    }

    public static function riga(int $id): ?array { return Db::one('SELECT * FROM discount_codes WHERE id = ?', [$id]); }

    public static function utilizzi(int $id): int
    {
        return (int) Db::val('SELECT COUNT(*) FROM discount_redemptions WHERE discount_code_id = ?', [$id], 0);
    }

    /** attivo | programmato | scaduto | esaurito | disattivato | da_sincronizzare */
    public static function stato(array $r): string
    {
        $oggi = self::oggi();
        if (!(int) $r['active']) return 'disattivato';
        if ((string) $r['stripe_coupon_id'] === '') return 'da_sincronizzare';
        if ($oggi > $r['valid_until']) return 'scaduto';
        if ((int) $r['max_uses'] > 0 && self::utilizzi((int) $r['id']) >= (int) $r['max_uses']) return 'esaurito';
        if ($oggi < $r['valid_from']) return 'programmato';
        return 'attivo';
    }

    /** I codici dei pacchetti per cui vale (vuoto = tutti). @return string[] */
    public static function pacchetti(array $r): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $r['packages']))));
    }

    private static function giorno(string $d): string { return Support::date($d); }

    /**
     * Il codice vale per questo account e questo piano? Restituisce la riga, oppure
     * lancia un errore con il motivo, da mostrare così com'è all'host.
     * Senza $pv (link con il codice, prima di scegliere il piano) il piano non si controlla.
     */
    public static function valida(string $codice, ?array $account, ?array $pv, int $quantita = 1): array
    {
        $r = self::trova($codice);
        if (!$r) throw new \RuntimeException('Questo codice non esiste. Controlla di averlo scritto bene.');
        // Lo sconto dell'amico (Inviti) è una riga di sistema: vale solo per chi è stato invitato.
        if (!empty($r['sistema']) && (!$account || !Inviti::invitato((int) $account['id']))) {
            throw new \RuntimeException('Questo codice non esiste. Controlla di averlo scritto bene.');
        }
        $oggi = self::oggi();
        if (!(int) $r['active']) throw new \RuntimeException('Questo codice non è più attivo.');
        if ($oggi > $r['valid_until']) throw new \RuntimeException('Questo codice è scaduto il ' . self::giorno($r['valid_until']) . '.');
        if ($oggi < $r['valid_from']) throw new \RuntimeException('Questo codice sarà valido dal ' . self::giorno($r['valid_from']) . '.');
        if ((int) $r['max_uses'] > 0 && self::utilizzi((int) $r['id']) >= (int) $r['max_uses']) {
            throw new \RuntimeException('Questo codice ha raggiunto il numero massimo di utilizzi.');
        }
        if ((string) $r['stripe_coupon_id'] === '') throw new \RuntimeException('Questo codice non è ancora utilizzabile: riprova tra qualche ora o scrivici.');
        if ($pv) {
            $pkg = Db::one('SELECT p.code, p.name FROM packages p JOIN package_versions pv ON pv.package_id = p.id WHERE pv.id = ?', [(int) $pv['id']]);
            $ammessi = self::pacchetti($r);
            if ($ammessi && $pkg && !in_array($pkg['code'], $ammessi, true)) throw new \RuntimeException('Questo codice non vale per il piano ' . $pkg['name'] . '.');
            // Un importo fisso non può portare il prezzo sotto 1 €: lo dice calcola(), qui basta il piano.
        }
        if ($account && Db::one("SELECT id FROM orders WHERE account_id = ? AND status = 'paid'", [(int) $account['id']])) {
            throw new \RuntimeException('I codici sconto valgono solo per il primo abbonamento.');
        }
        return $r;
    }

    /** Lo sconto in centesimi su un prezzo: mai più del prezzo meno 1 €. */
    public static function calcola(array $r, int $prezzo): int
    {
        $s = $r['kind'] === 'percent' ? (int) round($prezzo * (int) $r['value'] / 100) : (int) $r['value'];
        return max(0, min($s, $prezzo - 100));
    }

    /** «−20%» oppure «−15 €». */
    public static function etichetta(array $r): string
    {
        return "\u{2212}" . ($r['kind'] === 'percent' ? (int) $r['value'] . '%' : Support::money((int) $r['value']));
    }

    /**
     * Il codice applicato all'account, se vale ancora per questo piano: riga, sconto,
     * prezzo pieno e prezzo del primo anno. Null se non c'è o non vale più.
     */
    public static function applicato(array $account, array $pv, int $quantita = 1): ?array
    {
        if (!self::disponibili() || empty($account['intended_discount_code_id'])) return null;
        $r = self::riga((int) $account['intended_discount_code_id']);
        if (!$r) return null;
        try { self::valida($r['code'], $account, $pv, $quantita); } catch (\RuntimeException) { return null; }
        $prezzo = Plans::price($pv, $quantita);
        $sconto = self::calcola($r, $prezzo);
        return ['riga' => $r, 'sconto' => $sconto, 'prezzo' => $prezzo, 'scontato' => $prezzo - $sconto];
    }

    /** Il coupon su Stripe. Se Stripe non c'è o non risponde, il codice resta «da sincronizzare». */
    public static function sincronizza(array $r): bool
    {
        if ((string) $r['stripe_coupon_id'] !== '' || !Stripe::enabled()) return (string) $r['stripe_coupon_id'] !== '';
        $fine = (new \DateTimeImmutable($r['valid_until'] . ' 23:59:59', new \DateTimeZone('Europe/Rome')))->getTimestamp();
        $p = ['duration' => 'once', 'name' => $r['code'], 'redeem_by' => (string) $fine, 'metadata[discount_code_id]' => (string) $r['id']];
        if (!empty($r['sistema'])) { unset($p['redeem_by']); $p['name'] = 'Invito di un amico'; }   // la riga degli Inviti non scade
        if ($r['kind'] === 'percent') $p['percent_off'] = (string) (int) $r['value'];
        else { $p['amount_off'] = (string) (int) $r['value']; $p['currency'] = 'eur'; }
        if ((int) $r['max_uses'] > 0) $p['max_redemptions'] = (string) (int) $r['max_uses'];
        try {
            $c = Stripe::call('POST', 'coupons', $p, 'mhw-coupon-' . $r['id']);
            Db::update('discount_codes', ['stripe_coupon_id' => (string) ($c['id'] ?? ''), 'stripe_synced_at' => Support::now()], 'id = :id', ['id' => $r['id']]);
            return (string) ($c['id'] ?? '') !== '';
        } catch (\Throwable $e) {
            Log::error('Sconti: coupon non creato su Stripe', ['codice' => $r['code'], 'errore' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Un codice nuovo, dal modulo dell'amministrazione. Controlla tutto, salva,
     * poi prova a creare il coupon su Stripe.
     * @return array{0:int,1:bool} [id, sincronizzato]
     */
    public static function crea(array $in): array
    {
        $code = self::normalizza((string) ($in['code'] ?? ''));
        if (!preg_match('/^[A-Z0-9-]{4,24}$/', $code)) throw new \RuntimeException('Il codice va da 4 a 24 caratteri: lettere, cifre e trattino.');
        if (self::trova($code)) throw new \RuntimeException('Questo codice esiste già.');
        $kind = ($in['kind'] ?? '') === 'amount' ? 'amount' : 'percent';
        $grezzo = str_replace(',', '.', trim((string) ($in['value'] ?? '')));
        if (!is_numeric($grezzo) || (float) $grezzo <= 0) throw new \RuntimeException('Scrivi il valore dello sconto.');
        $value = $kind === 'percent' ? (int) $grezzo : (int) round((float) $grezzo * 100);
        if ($kind === 'percent' && ($value < 1 || $value > 100 || (float) $grezzo != $value)) throw new \RuntimeException('La percentuale va da 1 a 100.');
        $da = (string) ($in['valid_from'] ?? ''); $a = (string) ($in['valid_until'] ?? '');
        $ok = fn(string $d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && checkdate((int) substr($d, 5, 2), (int) substr($d, 8, 2), (int) substr($d, 0, 4));
        if (!$ok($da) || !$ok($a)) throw new \RuntimeException('Scrivi le date di inizio e di fine.');
        if ($a < self::oggi()) throw new \RuntimeException('La data di fine è già passata.');
        if ($a < $da) throw new \RuntimeException('La data di fine viene prima di quella di inizio.');
        $max = trim((string) ($in['max_uses'] ?? '')) === '' ? 0 : (int) $in['max_uses'];
        if ($max < 0) throw new \RuntimeException('Gli utilizzi massimi non possono essere negativi.');
        $tutti = array_column(Db::all("SELECT p.code FROM packages p WHERE p.public = 1"), 'code');
        $scelti = ($in['piani'] ?? 'tutti') === 'tutti' ? [] : array_values(array_intersect($tutti, array_map('strval', (array) ($in['packages'] ?? []))));
        if (($in['piani'] ?? 'tutti') !== 'tutti' && !$scelti) throw new \RuntimeException('Scegli almeno un piano, oppure «Tutti i piani».');
        if ($kind === 'amount') {
            // L'importo deve restare sotto il prezzo del piano meno caro fra quelli scelti.
            $prezzi = array_map(fn($o) => (int) $o['price_cents'], array_filter(Plans::public(), fn($o) => !$scelti || in_array($o['code'], $scelti, true)));
            if ($prezzi && $value >= min($prezzi)) throw new \RuntimeException('L\'importo deve essere minore del prezzo del piano meno caro (' . Support::money(min($prezzi)) . ').');
        }
        $id = Db::insert('discount_codes', [
            'code' => $code, 'kind' => $kind, 'value' => $value, 'valid_from' => $da, 'valid_until' => $a, 'max_uses' => $max,
            'packages' => implode(',', $scelti), 'note' => mb_substr(trim((string) ($in['note'] ?? '')), 0, 255),
            'active' => 1, 'stripe_coupon_id' => '', 'created_at' => Support::now(),
        ]);
        return [$id, self::sincronizza(self::riga($id))];
    }

    /** Disattiva: il coupon su Stripe si cancella, chi ha già pagato conserva il suo sconto. */
    public static function disattiva(int $id): void
    {
        $r = self::riga($id);
        if (!$r) throw new NotFound('Codice non trovato.');
        if ((string) $r['stripe_coupon_id'] !== '' && Stripe::enabled()) {
            try { Stripe::call('DELETE', 'coupons/' . rawurlencode($r['stripe_coupon_id'])); }
            catch (\Throwable $e) { Log::error('Sconti: coupon non cancellato su Stripe', ['codice' => $r['code'], 'errore' => $e->getMessage()]); }
        }
        Db::update('discount_codes', ['active' => 0], 'id = :id', ['id' => $id]);
        Db::run('UPDATE accounts SET intended_discount_code_id = NULL WHERE intended_discount_code_id = ?', [$id]);
    }

    /** Nota, piani e stato sono le sole cose che si cambiano dopo (il coupon Stripe resta quello). */
    public static function aggiorna(int $id, array $in): void
    {
        $tutti = array_column(Db::all('SELECT code FROM packages WHERE public = 1'), 'code');
        $scelti = ($in['piani'] ?? 'tutti') === 'tutti' ? [] : array_values(array_intersect($tutti, array_map('strval', (array) ($in['packages'] ?? []))));
        Db::update('discount_codes', ['note' => mb_substr(trim((string) ($in['note'] ?? '')), 0, 255), 'packages' => implode(',', $scelti)], 'id = :id', ['id' => $id]);
    }

    /**
     * Dopo il pagamento, nella stessa transazione dell'attivazione: un utilizzo per ordine.
     * $centesimi è lo sconto che dice Stripe (total_details.amount_discount); se manca, quello calcolato.
     */
    public static function registra(array $order, ?int $centesimi = null): void
    {
        if (!self::disponibili() || empty($order['discount_code_id'])) return;
        if (Db::one('SELECT id FROM discount_redemptions WHERE order_id = ?', [(int) $order['id']])) return;
        $r = self::riga((int) $order['discount_code_id']);
        if (!$r) return;
        if ($centesimi === null) {
            $pv = Db::one('SELECT * FROM package_versions WHERE id = ?', [(int) $order['package_version_id']]) ?: [];
            $centesimi = $pv ? self::calcola($r, Plans::price($pv, max(1, (int) ($order['quantity'] ?? 1)))) : 0;
        } elseif (($order['sconti'] ?? '') !== '' && !empty($r['sistema'])) {
            // 9A · Coupon combinato (invito + giudizio): all'invito spetta la sua parte, in proporzione ai percenti.
            $parti = []; $totale = 0;
            foreach (explode(',', (string) $order['sconti']) as $pezzo) { [$t, $n] = array_pad(explode(':', $pezzo, 2), 2, '0'); $parti[$t] = (int) $n; $totale += (int) $n; }
            if ($totale > 0 && isset($parti['invito'])) $centesimi = (int) round($centesimi * $parti['invito'] / $totale);
        }
        Db::insert('discount_redemptions', ['discount_code_id' => $r['id'], 'account_id' => (int) $order['account_id'], 'order_id' => (int) $order['id'],
                                            'discount_cents' => $centesimi, 'created_at' => Support::now()]);
        Db::update('orders', ['discount_cents' => $centesimi], 'id = :oid', ['oid' => $order['id']]);
        Db::update('accounts', ['intended_discount_code_id' => null], 'id = :aid', ['aid' => $order['account_id']]);
    }

    // ------------------------------------------------------------ 9A · gli sconti del primo anno, sommati

    /**
     * 9A · Gli sconti «di sistema» che spettano all'account sul primo anno: l'invito di un amico (Inviti) e il
     * giudizio con sconto (Giudizi, 9B). Si sommano tra loro. @return list<array{tipo:string,nome:string,percento:int}>
     */
    public static function sistema(?array $acc): array
    {
        $out = [];
        // 9D · Con un codice omaggio lo sconto dell'invito non si applica (omaggio e invito si escludono); il giudizio resta.
        $omaggio = $acc ? Omaggi::daUsare($acc) : null;
        if ($acc && !$omaggio && Inviti::disponibili() && ($i = Inviti::invitato((int) $acc['id'])) && $i['status'] === 'registrato'
            && !Db::one("SELECT id FROM orders WHERE account_id = ? AND status = 'paid'", [(int) $acc['id']])) {
            $out[] = ['tipo' => 'invito', 'nome' => 'Invito di ' . (explode(' ', trim((string) $i['referrer_name']))[0] ?: 'un amico'), 'percento' => Inviti::AMICO];
        }
        if ($acc && ($g = Giudizi::scontoDiSistema($acc))) $out[] = $g;
        return $out;
    }

    /**
     * 9A · Il conto del primo anno per un piano e una quantità: prezzo pieno, una riga per ogni sconto, totale.
     * Gli sconti di sistema si sommano (percentuale sul totale, arrotondata una volta sola, come Stripe); un codice
     * sconto scritto a mano non si somma a niente: vale il più alto, e il riepilogo lo dice (nota).
     * $sistemaExtra: sconti di sistema noti dalla pagina (il visitatore arrivato da un invito, prima dell'account).
     * 9D · Con un codice omaggio ('omaggio': codice, mesi, fine): oggi si paga 0 €, il totale del primo anno è quello del
     * primo addebito alla fine dei mesi gratis; vale solo lo sconto del giudizio, niente invito né codici scritti a mano.
     * @return array{prezzo:int,righe:list<array{tipo:string,nome:string,percento:int,cents:int}>,sconto:int,totale:int,percento:int,
     *               manuale:?array,scelta:string,nota:string,sistema:list<array>,omaggio:?array}
     */
    public static function riepilogo(array $pv, int $q, ?array $acc, array $sistemaExtra = []): array
    {
        $prezzo = Plans::price($pv, max(1, $q));
        $omaggio = $acc ? Omaggi::daUsare($acc) : null;
        $sistema = array_merge(self::sistema($acc), $omaggio ? [] : $sistemaExtra);
        if ($omaggio) $sistema = array_values(array_filter($sistema, fn($s) => $s['tipo'] === 'giudizio'));
        $visti = []; $sistema = array_values(array_filter($sistema, function ($s) use (&$visti) { if (isset($visti[$s['tipo']])) return false; $visti[$s['tipo']] = 1; return true; }));
        $percento = min(100, array_sum(array_column($sistema, 'percento')));
        $scontoSistema = $percento > 0 ? max(0, min((int) round($prezzo * $percento / 100), $prezzo - 100)) : 0;
        $manuale = null; $scontoManuale = 0;
        if (!$omaggio && $acc && self::disponibili() && !empty($acc['intended_discount_code_id']) && ($r = self::riga((int) $acc['intended_discount_code_id'])) && empty($r['sistema'])) {
            try { self::valida($r['code'], $acc, $pv, $q); $manuale = $r; $scontoManuale = self::calcola($r, $prezzo); } catch (\RuntimeException) { $manuale = null; }
        }
        $righe = []; $nota = ''; $scelta = 'nessuna';
        if ($manuale && $scontoManuale > $scontoSistema) {
            $scelta = 'manuale';
            $righe[] = ['tipo' => 'codice', 'nome' => 'Codice ' . $manuale['code'], 'percento' => $manuale['kind'] === 'percent' ? (int) $manuale['value'] : 0, 'cents' => $scontoManuale];
            if ($sistema) $nota = 'Il codice ' . $manuale['code'] . ' non si somma agli altri sconti: vale il più alto.';
            $sconto = $scontoManuale;
        } elseif ($sistema) {
            $scelta = 'sistema';
            $resto = $scontoSistema;
            foreach ($sistema as $i => $s) {
                $c = $i === count($sistema) - 1 ? $resto : (int) round($prezzo * $s['percento'] / 100);
                $resto -= $c;
                $righe[] = $s + ['cents' => $c];
            }
            if ($manuale) $nota = 'Il codice ' . $manuale['code'] . ' non si somma agli altri sconti e vale meno: non si applica.';
            $sconto = $scontoSistema;
        } else {
            $sconto = 0;
        }
        if ($omaggio && $acc && !empty($acc['intended_discount_code_id'])) $nota = 'Con il codice omaggio non si applicano altri codici sconto né l\'invito di un amico.';
        return ['prezzo' => $prezzo, 'righe' => $righe, 'sconto' => $sconto, 'totale' => $prezzo - $sconto,
                'percento' => $scelta === 'sistema' ? $percento : ($scelta === 'manuale' && $manuale['kind'] === 'percent' ? (int) $manuale['value'] : 0),
                'manuale' => $manuale, 'scelta' => $scelta, 'nota' => $nota, 'sistema' => $sistema,
                'omaggio' => $omaggio ? ['id' => (int) $omaggio['id'], 'codice' => (string) $omaggio['codice'], 'mesi' => (int) $omaggio['mesi'], 'fine' => Omaggi::fine((int) $omaggio['mesi'])] : null];
    }

    /**
     * 9A · Cosa passare a Stripe per il conto di riepilogo(): il coupon (uno solo: Checkout non ne prende di più),
     * la riga di discount_codes da registrare come usata (il codice, o la riga di sistema dell'invito) e la
     * composizione degli sconti per i metadati («invito:5,giudizio:5»). Chiama Stripe solo se serve il coupon combinato.
     * @return array{coupon:string,discount_code_id:?int,sconti:string}
     */
    public static function perStripe(array $riep): array
    {
        $vuoto = ['coupon' => '', 'discount_code_id' => null, 'sconti' => ''];
        if ($riep['scelta'] === 'manuale') {
            $r = $riep['manuale'];
            return ['coupon' => (string) $r['stripe_coupon_id'], 'discount_code_id' => (int) $r['id'], 'sconti' => 'codice:' . $r['code']];
        }
        if ($riep['scelta'] !== 'sistema' || $riep['percento'] <= 0) return $vuoto;
        $sconti = implode(',', array_map(fn($s) => $s['tipo'] . ':' . $s['percento'], $riep['sistema']));
        $invito = null;
        foreach ($riep['sistema'] as $s) if ($s['tipo'] === 'invito') $invito = Inviti::codiceAmico();
        $coupon = '';
        if (count($riep['sistema']) === 1) {
            $coupon = $riep['sistema'][0]['tipo'] === 'invito' ? (string) ($invito['stripe_coupon_id'] ?? '') : Giudizi::coupon($riep['percento']);
        } else {
            $coupon = self::couponCombinato($riep['percento']);
        }
        return ['coupon' => $coupon, 'discount_code_id' => $invito ? (int) $invito['id'] : null, 'sconti' => $sconti];
    }

    /** 9A · Il coupon «Sconti primo anno» di una percentuale somma: id fisso, una volta sola, creato se manca. */
    public static function couponCombinato(int $percento): string
    {
        if (!Stripe::enabled()) return '';
        $id = 'mhw-primo-anno-' . $percento;
        try { Stripe::call('GET', 'coupons/' . rawurlencode($id)); }
        catch (\RuntimeException) {
            Stripe::call('POST', 'coupons', ['id' => $id, 'percent_off' => (string) $percento, 'duration' => 'once', 'name' => 'Sconti primo anno'], 'mhw-coupon-' . $id);
        }
        return $id;
    }

    /** Lo sconto avuto dall'account, per «Account & Fatturazione». */
    public static function avuto(int $accountId): ?array
    {
        if (!self::disponibili()) return null;
        return Db::one('SELECT r.*, c.code FROM discount_redemptions r JOIN discount_codes c ON c.id = r.discount_code_id
                        WHERE r.account_id = ? ORDER BY r.id DESC', [$accountId]);
    }
}
