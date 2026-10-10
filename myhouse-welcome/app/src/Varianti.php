<?php
namespace MHW;

/**
 * Varianti camera: un B&B, un affittacamere o un agriturismo con più camere allo stesso
 * indirizzo è una guida sola (un'unità ricettiva, un CIN). Le camere con un Wi-Fi o con
 * istruzioni di accesso diverse sono varianti: la stessa guida, con il loro nome, il loro
 * Wi-Fi, le loro istruzioni e una nota, un QR e un link propri (/g/{slug}/c/{token}).
 *
 * Ogni variante costa varianti.prezzo_cents all'anno (IVA esclusa), come voce in più
 * dell'abbonamento Stripe. Con un abbonamento dello staff non c'è niente da pagare.
 * Le varianti non stanno nell'istantanea pubblicata: si leggono dal vivo, quindi un
 * Wi-Fi cambiato si vede subito, senza ripubblicare.
 */
final class Varianti
{
    /** Varianti al massimo per guida. */
    public const MAX = 30;

    public static function prezzo(): int { return max(0, (int) ((Config::get('varianti') ?? [])['prezzo_cents'] ?? 1500)); }

    /** Il piano permette di aggiungerne? (Plus e Portfolio) */
    public static function permesse(int $accountId): bool
    {
        return Migrator::tableExists('room_variants') && Entitlements::can($accountId, 'room_variants');
    }

    /** Le varianti di una guida, nell'ordine in cui sono state create. */
    public static function diStruttura(int $propertyId): array
    {
        if (!Migrator::tableExists('room_variants')) return [];
        return Db::all('SELECT * FROM room_variants WHERE property_id = ? AND removed_at IS NULL ORDER BY position, id', [$propertyId]);
    }

    /** Quante varianti ha l'account, in tutte le guide. */
    public static function contaAccount(int $accountId): int
    {
        if (!Migrator::tableExists('room_variants')) return 0;
        return (int) Db::val('SELECT COUNT(*) FROM room_variants v JOIN properties p ON p.id = v.property_id
                              WHERE p.account_id = ? AND v.removed_at IS NULL', [$accountId], 0);
    }

    /** La variante di un link o di un QR, se è di questa guida, c'è ancora e il piano la permette. */
    public static function perToken(int $propertyId, int $accountId, string $token): ?array
    {
        if (!preg_match('/^[A-Za-z0-9_-]{6,24}$/', $token) || !self::permesse($accountId)) return null;
        return Db::one('SELECT * FROM room_variants WHERE token = ? AND property_id = ? AND removed_at IS NULL', [$token, $propertyId]);
    }

    /** Un testo per lingua (access, note): quello della lingua, altrimenti quello della lingua principale. */
    public static function testo(array $v, string $campo, string $loc, string $def): string
    {
        $t = json_decode((string) ($v[$campo] ?? ''), true);
        if (!is_array($t)) return '';
        $x = trim((string) ($t[$loc] ?? ''));
        return $x !== '' ? $x : trim((string) ($t[$def] ?? ''));
    }

    /** I campi dal modulo: nome, Wi-Fi, e accesso e nota per ogni lingua della guida. */
    public static function dati(array $in, array $lingue): array
    {
        $nome = mb_substr(trim((string) ($in['name'] ?? '')), 0, 80);
        if ($nome === '') throw new \RuntimeException('Scrivi il nome della camera, per esempio «Camera 2» o «La Rosa».');
        $perLingua = function (string $campo, int $max) use ($in, $lingue): string {
            $out = [];
            foreach ($lingue as $l) {
                $v = trim((string) (($in[$campo] ?? [])[$l] ?? ''));
                if ($v !== '') $out[$l] = mb_substr($v, 0, $max);
            }
            return json_encode($out, JSON_UNESCAPED_UNICODE);
        };
        return ['name' => $nome, 'wifi_ssid' => mb_substr(trim((string) ($in['wifi_ssid'] ?? '')), 0, 80),
                'wifi_password' => mb_substr(trim((string) ($in['wifi_password'] ?? '')), 0, 120),
                'access' => $perLingua('access', 2000), 'note' => $perLingua('note', 1000)];
    }

    /**
     * Aggiunge una variante. Con un abbonamento Stripe si paga subito la parte dell'anno che
     * resta: la variante nasce solo se il pagamento riesce. @return int l'id
     */
    public static function aggiungi(array $account, array $property, array $dati): int
    {
        $aid = (int) $account['id'];
        if (!self::permesse($aid)) throw new \RuntimeException('Le varianti camera si aggiungono con Plus o Portfolio.');
        if (count(self::diStruttura((int) $property['id'])) >= self::MAX) throw new LimitReached('Una guida ha al massimo ' . self::MAX . ' varianti camera.');
        $sub = Subscriptions::active($aid);
        if (!$sub) throw new \RuntimeException('Le varianti camera si aggiungono quando l\'abbonamento è attivo: pubblica prima la guida.');
        $stripe = $sub['provider'] === 'stripe' && (string) $sub['provider_subscription_id'] !== '';
        if ($stripe) {
            if (!Stripe::enabled()) throw new \RuntimeException('I pagamenti non sono attivi in questo momento: riprova più tardi.');
            if ((int) $sub['cancel_at_period_end'] === 1) throw new \RuntimeException('Il rinnovo automatico è disattivato: riattivalo in Account & Fatturazione per aggiungere varianti.');
            if (!Fatturazione::completa($account)) throw new \RuntimeException('Prima di pagare servono i dati di fatturazione: compilali in Account & Fatturazione.');
            $quante = self::contaAccount($aid) + 1;
            $item = (string) ($sub['provider_variant_item_id'] ?? '');
            $r = Stripe::setVariantQuantity((string) $sub['provider_subscription_id'], $item, $quante, true);
            if (!empty($r['pending_update'])) throw new \RuntimeException('Il pagamento della variante non è andato a buon fine: controlla il metodo di pagamento in Account & Fatturazione e riprova.');
            $nuovo = Stripe::variantItem($r) ?: $item;
            if ($nuovo !== '' && $nuovo !== $item) Db::update('subscriptions', ['provider_variant_item_id' => $nuovo], 'id = :sid', ['sid' => $sub['id']]);
        }
        $pos = (int) Db::val('SELECT COALESCE(MAX(position),0)+1 FROM room_variants WHERE property_id = ?', [$property['id']], 1);
        return Db::insert('room_variants', $dati + ['property_id' => $property['id'], 'token' => self::token(), 'position' => $pos, 'created_at' => Support::now()]);
    }

    /** Toglie una variante: il suo QR torna alla guida senza variante; su Stripe, credito sulla prossima fattura. */
    public static function togli(array $account, array $variante): void
    {
        $aid = (int) $account['id'];
        Db::update('room_variants', ['removed_at' => Support::now()], 'id = :vid', ['vid' => $variante['id']]);
        $sub = Subscriptions::active($aid);
        if (!$sub || $sub['provider'] !== 'stripe' || (string) ($sub['provider_variant_item_id'] ?? '') === '' || !Stripe::enabled()) return;
        try {
            $quante = self::contaAccount($aid);
            Stripe::setVariantQuantity((string) $sub['provider_subscription_id'], (string) $sub['provider_variant_item_id'], $quante, false);
            if ($quante === 0) Db::update('subscriptions', ['provider_variant_item_id' => ''], 'id = :sid', ['sid' => $sub['id']]);
        } catch (\Throwable $e) {
            // La variante è comunque tolta dalla guida: il conteggio su Stripe si riallinea a mano (Anomalie).
            Log::exception($e, 'varianti: togli su Stripe');
        }
    }

    /** Quanto si paga oggi per una variante in più: la parte dell'anno che resta fino al rinnovo. */
    public static function quotaOggi(array $sub): int
    {
        $inizio = strtotime((string) $sub['current_period_start']) ?: time();
        $fine = strtotime((string) $sub['current_period_end']) ?: time();
        return CambioPiano::conguaglio(0, self::prezzo(), $inizio, $fine, time());
    }

    /** Un token per QR e link: lo stesso alfabeto di quelli delle guide, mai ripetuto. */
    private static function token(): string
    {
        do { $t = Support::token(9); }
        while (Db::val('SELECT 1 FROM room_variants WHERE token = ?', [$t]) || Db::val('SELECT 1 FROM qr_tokens WHERE token = ?', [$t]));
        return $t;
    }
}
