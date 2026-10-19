<?php
namespace MHW;

/**
 * Stripe, scritto con curl: nessuna libreria da installare.
 *
 * Abbonamenti annuali con rinnovo automatico (Checkout in modalità
 * subscription). I prezzi sono IVA esclusa: il prezzo Stripe ha
 * tax_behavior=exclusive e l'imposta si aggiunge al checkout.
 * Il ritorno dal browser non attiva niente: l'unica fonte di verità è il
 * webhook firmato.
 */
final class Stripe
{
    /** Senza chiave segreta E segreto del webhook non si vende: un pagamento non arriverebbe mai. */
    public static function enabled(): bool
    {
        $s = Config::get('stripe');
        return str_starts_with((string) $s['secret_key'], 'sk_') && (string) $s['webhook_secret'] !== '';
    }

    /**
     * La chiave pubblicabile, solo se è della stessa modalità della chiave segreta (pk_test_ con sk_test_,
     * pk_live_ con sk_live_): altrimenti '' e si paga sulla pagina di Stripe.
     */
    public static function chiavePubblicabile(): string
    {
        $s = Config::get('stripe');
        $pk = trim((string) ($s['publishable_key'] ?? ''));
        $modo = fn(string $k, string $p) => preg_match('/^' . $p . '_(live|test)_/', $k, $m) ? $m[1] : '';
        return $modo($pk, 'pk') !== '' && $modo($pk, 'pk') === $modo((string) $s['secret_key'], 'sk') ? $pk : '';
    }

    /** Il pagamento con la carta nel passo «Pubblica» (Checkout incorporato): serve anche la chiave pubblicabile. */
    public static function incorporato(): bool { return self::enabled() && self::chiavePubblicabile() !== ''; }

    /** @throws \RuntimeException con un messaggio tecnico: va nei log, non all'utente */
    public static function call(string $method, string $path, array $params = [], string $idempotencyKey = ''): array
    {
        $s = Config::get('stripe');
        $url = rtrim((string) $s['api_base'], '/') . '/v1/' . ltrim($path, '/');
        if (($method === 'GET' || $method === 'DELETE') && $params) { $url .= '?' . http_build_query($params); $params = []; }   // come le librerie di Stripe
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_USERPWD => $s['secret_key'] . ':', CURLOPT_TIMEOUT => 25,
            CURLOPT_CUSTOMREQUEST => $method,
            // Con una chiave stabile, la stessa richiesta ripetuta (doppio clic, due
            // schede aperte) non crea due volte lo stesso cliente o lo stesso checkout.
            CURLOPT_HTTPHEADER => $idempotencyKey !== '' ? ['Idempotency-Key: ' . $idempotencyKey] : [],
        ]);
        if ($params) curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($body === false) throw new \RuntimeException('Stripe non raggiungibile: ' . $err);
        $json = json_decode((string) $body, true) ?: [];
        if ($code >= 400) throw new \RuntimeException('Stripe ' . $code . ': ' . ($json['error']['message'] ?? 'errore sconosciuto'));
        return $json;
    }

    /**
     * Il cliente Stripe dell'account, creato la prima volta e poi riusato.
     * Porta con sé i dati di fatturazione: intestatario e indirizzo, e come
     * metadati partita IVA, codice fiscale, SDI e PEC (vat, cf, sdi, pec).
     */
    /** 9I · I codici di un ordine per i metadati di Stripe (vuoti spariscono). */
    private static function codici(array $order): array
    {
        $aid = (int) ($order['account_id'] ?? 0);
        return array_filter(['codice_cliente' => Codici::cliente($aid), 'codice_pagamento' => Codici::pagamento((int) ($order['id'] ?? 0)),
                             'codice_struttura' => Codici::strutture($aid)], fn($v) => $v !== '');
    }

    /** 9I · Metadati su una fattura (anche pagata: Stripe lascia cambiare i metadati). */
    public static function metadatiFattura(string $invoiceId, array $meta): array
    {
        $p = [];
        foreach ($meta as $k => $v) if ((string) $v !== '') $p["metadata[$k]"] = (string) $v;
        return $p ? self::call('POST', 'invoices/' . rawurlencode($invoiceId), $p, 'mhw-fattura-codici-' . $invoiceId . '-' . substr(md5(serialize($p)), 0, 8)) : [];
    }

    public static function ensureCustomer(array $account, array $user): string
    {
        $dati = Fatturazione::anagrafica($account) + Fatturazione::metadati($account);
        if (($account['stripe_customer_id'] ?? '') !== '') {
            self::syncCustomer($account);
            return $account['stripe_customer_id'];
        }
        // La partita IVA come «tax id» del cliente: Stripe la stampa in fattura e Adamo la legge da lì.
        $piva = ($account['billing_type'] ?? '') === 'azienda' ? preg_replace('/\D/', '', (string) ($account['vat'] ?? '')) : '';
        if ($piva !== '') $dati += ['tax_id_data[0][type]' => 'eu_vat', 'tax_id_data[0][value]' => 'IT' . $piva];
        $c = self::call('POST', 'customers', $dati + [
            'email' => $user['email'], 'name' => $user['name'],
            'metadata[account_id]' => (string) $account['id'],
            'metadata[codice_cliente]' => Codici::cliente((int) $account['id']),   // 9I
            'preferred_locales[0]' => 'it',
        ], 'mhw-customer-account-' . $account['id'] . ($dati ? '-' . substr(md5(serialize($dati)), 0, 10) : ''));
        Db::update('accounts', ['stripe_customer_id' => $c['id']], 'id = :aid', ['aid' => $account['id']]);
        return $c['id'];
    }

    /** Aggiorna i dati di fatturazione sul cliente Stripe che esiste già. */
    public static function syncCustomer(array $account): void
    {
        $dati = Fatturazione::anagrafica($account) + Fatturazione::metadati($account);
        if (!$dati || ($account['stripe_customer_id'] ?? '') === '') return;
        // Un dato tolto (la PEC, o la partita IVA passando a persona fisica) va tolto anche su Stripe,
        // altrimenti Adamo legge quello vecchio: un metadato vuoto lo cancella.
        foreach (['vat', 'cf', 'sdi', 'pec', 'billing_type', 'Fiscal_code', 'Pec', 'Fe_code'] as $k) $dati += ['metadata[' . $k . ']' => ''];
        if (($codC = Codici::cliente((int) $account['id'])) !== '') $dati['metadata[codice_cliente]'] = $codC;   // 9I
        $cid = rawurlencode($account['stripe_customer_id']);
        self::call('POST', 'customers/' . $cid, $dati, 'mhw-customer-dati-' . $account['id'] . '-' . substr(md5(serialize($dati)), 0, 10));
        // La partita IVA come «tax id»: si aggiunge se un'azienda la inserisce dopo il primo pagamento,
        // e quella vecchia si toglie se cambia o se il cliente diventa persona fisica.
        $piva = ($account['billing_type'] ?? '') === 'azienda' ? preg_replace('/\D/', '', (string) ($account['vat'] ?? '')) : '';
        try {
            $giusta = $piva !== '' ? 'IT' . $piva : '';
            $presente = false;
            foreach ((array) (self::call('GET', 'customers/' . $cid . '/tax_ids', ['limit' => '20'])['data'] ?? []) as $t) {
                if (($t['type'] ?? '') !== 'eu_vat') continue;
                if (($t['value'] ?? '') === $giusta) { $presente = true; continue; }
                self::call('DELETE', 'customers/' . $cid . '/tax_ids/' . rawurlencode((string) $t['id']));
            }
            if ($giusta !== '' && !$presente) self::call('POST', 'customers/' . $cid . '/tax_ids', ['type' => 'eu_vat', 'value' => $giusta], 'mhw-taxid-' . $account['id'] . '-' . $piva);
        } catch (\Throwable $e) { Log::exception($e, 'Stripe: partita IVA del cliente'); }
    }

    /**
     * 9A · Le voci del Checkout di un abbonamento, dalla versione del listino: la prima struttura (Price ID se c'è,
     * altrimenti descritta qui) e, per il Portfolio, le strutture aggiuntive × quantità. Con gli scaglioni la seconda
     * voce è un Price graduato, che Checkout non sa descrivere al volo: si crea su Stripe la prima volta e si riusa.
     * @return array{0:array,1:array} [i parametri line_items, la versione con i Price ID eventualmente creati]
     */
    public static function vociCheckout(array $pv, array $pkg, int $quantita): array
    {
        $p = ['line_items[0][quantity]' => 1];
        if ((string) ($pv['stripe_price_id'] ?? '') !== '') {
            $p['line_items[0][price]'] = $pv['stripe_price_id'];
        } else {
            // Nessun prezzo creato a mano su Stripe: lo si descrive qui, dalla
            // versione del listino. Il prezzo resta quello del database.
            $p['line_items[0][price_data][currency]'] = strtolower($pv['currency']);
            $p['line_items[0][price_data][unit_amount]'] = (string) $pv['price_cents'];
            $p['line_items[0][price_data][tax_behavior]'] = 'exclusive';
            $p['line_items[0][price_data][recurring][interval]'] = 'year';
            $p['line_items[0][price_data][product_data][name]'] = 'MyHouse Welcome ' . $pkg['name'];
            $p['line_items[0][price_data][product_data][metadata][package]'] = $pkg['code'];
            $p['line_items[0][price_data][product_data][metadata][ruolo]'] = 'base';
        }
        if (Plans::perProperty($pv) && $quantita > 1) {
            if (($pv['stripe_extra_price_id'] ?? '') === '' && count(Plans::tiers($pv)) > 1) $pv['stripe_extra_price_id'] = self::ensureExtraPrice($pv, $pkg);
            $p['line_items[1][quantity]'] = $quantita - 1;
            if (($pv['stripe_extra_price_id'] ?? '') !== '') {
                $p['line_items[1][price]'] = $pv['stripe_extra_price_id'];
            } else {
                $p['line_items[1][price_data][currency]'] = strtolower($pv['currency']);
                $p['line_items[1][price_data][unit_amount]'] = (string) (int) $pv['extra_price_cents'];
                $p['line_items[1][price_data][tax_behavior]'] = 'exclusive';
                $p['line_items[1][price_data][recurring][interval]'] = 'year';
                $p['line_items[1][price_data][product_data][name]'] = 'MyHouse Welcome ' . $pkg['name'] . ' — struttura aggiuntiva';
                $p['line_items[1][price_data][product_data][metadata][package]'] = $pkg['code'];
                $p['line_items[1][price_data][product_data][metadata][ruolo]'] = 'aggiuntiva';
            }
        }
        return [$p, $pv];
    }

    /** 9A · Il file con l'esito dei controlli sui Price (per versione: ok a che ora, o l'ultimo errore). */
    private static function fileVerifica(): string { return MHW_APP . '/storage/.prezzi-stripe.json'; }

    public static function esitiVerifica(): array
    {
        $f = self::fileVerifica();
        return is_file($f) ? (json_decode((string) @file_get_contents($f), true) ?: []) : [];
    }

    private static function segnaVerifica(int $pvId, array $esito): void
    {
        $tutti = self::esitiVerifica();
        $tutti[(string) $pvId] = $esito + ['t' => time()];
        @file_put_contents(self::fileVerifica(), json_encode($tutti), LOCK_EX);
    }

    /**
     * 9A · Controllo 1, prima di aprire il Checkout: i Price su Stripe dicono gli stessi importi del listino.
     * Si rilegge ogni Price ID della versione (base e, per il Portfolio, struttura aggiuntiva con gli scaglioni)
     * e si confronta importo, valuta, periodo annuale, IVA esclusa e, per quello graduato, ogni scaglione.
     * L'esito buono vale un'ora per versione. Un Price descritto al volo (ID vuoto) non ha niente da controllare.
     * @throws \RuntimeException con cosa non torna (va nei registri, non all'utente)
     */
    public static function verificaPrezzi(array $pv): void
    {
        $base = (string) ($pv['stripe_price_id'] ?? ''); $extra = (string) ($pv['stripe_extra_price_id'] ?? '');
        if ($base === '' && $extra === '') return;
        $pvId = (int) $pv['id'];
        $esito = self::esitiVerifica()[(string) $pvId] ?? null;
        if ($esito && !empty($esito['ok']) && time() - (int) $esito['t'] < 3600 && ($esito['ids'] ?? '') === $base . '|' . $extra) return;
        $problemi = [];
        $leggi = fn(string $id) => self::call('GET', 'prices/' . rawurlencode($id), ['expand' => ['tiers']]);
        $campo = function (array $pr, string $nome, mixed $atteso, mixed $suStripe) use (&$problemi): void {
            if ((string) $atteso !== (string) $suStripe) $problemi[] = $nome . ': atteso ' . json_encode($atteso) . ', su Stripe ' . json_encode($suStripe);
        };
        try {
            if ($base !== '') {
                $pr = $leggi($base);
                $campo($pr, 'base unit_amount', (int) $pv['price_cents'], (int) ($pr['unit_amount'] ?? -1));
                $campo($pr, 'base currency', strtolower((string) $pv['currency']), strtolower((string) ($pr['currency'] ?? '')));
                $campo($pr, 'base recurring.interval', 'year', (string) ($pr['recurring']['interval'] ?? ''));
                $campo($pr, 'base recurring.interval_count', 1, (int) ($pr['recurring']['interval_count'] ?? 1));
                $campo($pr, 'base tax_behavior', 'exclusive', (string) ($pr['tax_behavior'] ?? ''));
                $campo($pr, 'base active', true, (bool) ($pr['active'] ?? true));
            }
            if ($extra !== '' && Plans::perProperty($pv)) {
                $pr = $leggi($extra);
                $campo($pr, 'aggiuntiva currency', strtolower((string) $pv['currency']), strtolower((string) ($pr['currency'] ?? '')));
                $campo($pr, 'aggiuntiva recurring.interval', 'year', (string) ($pr['recurring']['interval'] ?? ''));
                $campo($pr, 'aggiuntiva tax_behavior', 'exclusive', (string) ($pr['tax_behavior'] ?? ''));
                $campo($pr, 'aggiuntiva active', true, (bool) ($pr['active'] ?? true));
                $scaglioni = Plans::tiers($pv);
                if (count($scaglioni) > 1) {
                    $campo($pr, 'aggiuntiva billing_scheme', 'tiered', (string) ($pr['billing_scheme'] ?? ''));
                    $campo($pr, 'aggiuntiva tiers_mode', 'graduated', (string) ($pr['tiers_mode'] ?? ''));
                    $tiers = (array) ($pr['tiers'] ?? []);
                    $campo($pr, 'aggiuntiva tiers (quanti)', count($scaglioni), count($tiers));
                    foreach ($scaglioni as $i => $t) {
                        $campo($pr, "aggiuntiva tiers[$i].up_to", $t['a'] === null ? 'inf' : $t['a'] - 1, ($tiers[$i]['up_to'] ?? null) === null ? 'inf' : (int) $tiers[$i]['up_to']);
                        $campo($pr, "aggiuntiva tiers[$i].unit_amount", $t['cents'], (int) ($tiers[$i]['unit_amount'] ?? -1));
                    }
                } else {
                    $campo($pr, 'aggiuntiva unit_amount', (int) $pv['extra_price_cents'], (int) ($pr['unit_amount'] ?? -1));
                }
            }
        } catch (\RuntimeException $e) {
            $problemi[] = 'lettura dei Price: ' . $e->getMessage();
        }
        if ($problemi) {
            $msg = 'Prezzi su Stripe diversi dal listino (versione ' . $pvId . '): ' . implode('; ', $problemi);
            self::segnaVerifica($pvId, ['ok' => false, 'errore' => $msg, 'ids' => $base . '|' . $extra]);
            throw new PrezziNonCoerenti($msg);
        }
        self::segnaVerifica($pvId, ['ok' => true, 'ids' => $base . '|' . $extra]);
    }

    /**
     * 9A · Controllo 2, a sessione creata: si rilegge e si confrontano l'imponibile (amount_subtotal) con il prezzo
     * del listino e il totale IVA esclusa (amount_total meno le tasse) con il prezzo scontato atteso. Se non tornano,
     * la sessione si fa scadere e si lancia l'errore: nessuno paga una cifra diversa da quella mostrata.
     * @throws \RuntimeException
     */
    public static function controllaSessione(string $id, int $prezzo, int $totale, bool $omaggio = false): array
    {
        $s = self::call('GET', 'checkout/sessions/' . rawurlencode($id));
        $sub = (int) ($s['amount_subtotal'] ?? -1);
        $tot = (int) ($s['amount_total'] ?? -1) - (int) ($s['total_details']['amount_tax'] ?? 0);
        // 9D · Con i mesi gratis oggi non si paga niente: la sessione deve dire 0 (l'imponibile Stripe lo dà a 0 o al listino).
        if ($omaggio) { $totale = 0; if ($sub === 0) $prezzo = 0; }
        if ($sub !== $prezzo || $tot !== $totale) {
            try { self::call('POST', 'checkout/sessions/' . rawurlencode($id) . '/expire'); } catch (\Throwable $e) { Log::exception($e, 'Stripe: sessione da far scadere'); }
            throw new PrezziNonCoerenti("Checkout con importi diversi dal listino: imponibile $sub (atteso $prezzo), totale senza IVA $tot (atteso $totale). Sessione $id fatta scadere.");
        }
        return $s;
    }

    /**
     * La sessione di Checkout per un abbonamento annuale. Restituisce l'URL di Stripe.
     * 9A: prima si controllano i Price (verificaPrezzi), poi la sessione creata (controllaSessione); gli sconti del
     * primo anno arrivano dal conto di Sconti::riepilogo (un solo coupon, con la composizione nei metadati).
     */
    public static function checkoutSubscription(array $order, array $pv, array $pkg, array $account, array $user, ?array $riepilogo = null): string
    {
        return (string) self::sessioneAbbonamento($order, $pv, $pkg, $account, $user, $riepilogo, false)['url'];
    }

    /**
     * La stessa sessione, ma incorporata nel passo «Pubblica» (ui_mode=embedded): il modulo della carta di Stripe sta
     * nella nostra pagina, dentro un iframe di Stripe, e i dati della carta non passano mai dal sito. Alla fine Stripe
     * porta il cliente su /pagamento/ok, come la pagina di Stripe; l'abbonamento lo attiva sempre e solo il webhook.
     * @return array{id:string,client_secret:string}
     */
    public static function checkoutIncorporato(array $order, array $pv, array $pkg, array $account, array $user, ?array $riepilogo = null): array
    {
        $res = self::sessioneAbbonamento($order, $pv, $pkg, $account, $user, $riepilogo, true);
        return ['id' => (string) $res['id'], 'client_secret' => (string) ($res['client_secret'] ?? '')];
    }

    /** Una sessione di Checkout: la legge (per il segreto del modulo incorporato e per lo stato). */
    public static function sessione(string $id): array
    {
        return self::call('GET', 'checkout/sessions/' . rawurlencode($id));
    }

    /** Fa scadere una sessione aperta (per esempio quella incorporata, quando il cliente sceglie la pagina di Stripe). */
    public static function scadiSessione(string $id): void
    {
        try { self::call('POST', 'checkout/sessions/' . rawurlencode($id) . '/expire'); }
        catch (\Throwable $e) { Log::exception($e, 'Stripe: sessione da far scadere'); }
    }

    private static function sessioneAbbonamento(array $order, array $pv, array $pkg, array $account, array $user, ?array $riepilogo, bool $incorporato): array
    {
        $base = Support::baseUrl();
        $cfg = Config::get('stripe');
        $quantita = max(1, (int) ($order['quantity'] ?? 1));
        $meta = [
            'order_id' => (string) $order['id'], 'account_id' => (string) $account['id'],
            'package_version_id' => (string) $pv['id'], 'property_id' => (string) ($order['property_id'] ?? ''),
            'quantity' => (string) $quantita,
        ];
        [$voci, $pv] = self::vociCheckout($pv, $pkg, $quantita);
        self::verificaPrezzi($pv);
        $p = [
            'mode' => 'subscription',
            'customer' => self::ensureCustomer($account, $user),
            'client_reference_id' => (string) $order['id'],
            'locale' => 'it',
            // I dati di fatturazione: indirizzo sempre, partita IVA se chi paga è un'azienda.
            'billing_address_collection' => 'required',
            'tax_id_collection[enabled]' => 'true',
            'customer_update[address]' => 'auto',
            'customer_update[name]' => 'auto',
        ] + $voci;
        // Incorporata: un solo indirizzo di ritorno (niente «annulla»: si resta nella nostra pagina). Sulla pagina di Stripe: ok e annullato.
        $p += $incorporato
            ? ['ui_mode' => 'embedded', 'return_url' => $base . '/pagamento/ok?order=' . $order['id']]
            : ['success_url' => $base . '/pagamento/ok?order=' . $order['id'], 'cancel_url' => $base . '/pagamento/annullato?order=' . $order['id']];
        if (!empty($cfg['automatic_tax'])) $p['automatic_tax[enabled]'] = 'true';
        // Gli sconti del primo anno (6E, 7D, 9A): un coupon duration=once, quindi sconta solo la prima fattura.
        // Niente allow_promotion_codes: il codice si sceglie nel sito, non nel checkout.
        $riepilogo ??= Sconti::riepilogo($pv, $quantita, $account);
        $sc = Sconti::perStripe($riepilogo);
        if ($sc['coupon'] !== '') {
            $p['discounts[0][coupon]'] = $sc['coupon'];
            if ($riepilogo['scelta'] === 'manuale') $meta['discount_code'] = (string) $riepilogo['manuale']['code'];
            $meta['sconti'] = $sc['sconti'];
        } elseif (!empty($order['discount_code_id']) && ($c = Sconti::riga((int) $order['discount_code_id'])) && (string) $c['stripe_coupon_id'] !== '') {
            $p['discounts[0][coupon]'] = $c['stripe_coupon_id'];
            $meta['discount_code'] = $c['code'];
        }
        // 9D · Codice omaggio: i primi mesi gratis (trial_end a mezzanotte UTC di oggi + N mesi), carta obbligatoria,
        // abbonamento cancellato se alla fine manca il metodo di pagamento. Il coupon (solo quello del giudizio, una
        // volta) Stripe lo applica alla prima fattura, cioè quella di fine omaggio.
        $omaggio = $riepilogo['omaggio'] ?? null;
        if ($omaggio) {
            $p['subscription_data[trial_end]'] = (string) $omaggio['fine'];
            $p['payment_method_collection'] = 'always';
            $p['subscription_data[trial_settings][end_behavior][missing_payment_method]'] = 'cancel';
            $meta['omaggio_codice'] = (string) $omaggio['codice'];
            $meta['omaggio_mesi'] = (string) $omaggio['mesi'];
        }
        // 9I · I codici: cliente, pagamento (questo ordine), strutture; la descrizione dell'abbonamento è la causale che legge Adamo.
        $meta += self::codici($order);
        if (($meta['codice_pagamento'] ?? '') !== '') $p['subscription_data[description]'] = 'Codice cliente ' . $meta['codice_cliente'] . ' · Pagamento ' . $meta['codice_pagamento'];
        foreach ($meta as $k => $v) { $p["metadata[$k]"] = $v; $p["subscription_data[metadata][$k]"] = $v; }

        $res = self::call('POST', 'checkout/sessions', $p, ($incorporato ? 'mhw-checkout-incorporato-order-' : 'mhw-checkout-order-') . $order['id']);
        Db::update('orders', ['provider_session_id' => $res['id'], 'updated_at' => Support::now()], 'id = :oid', ['oid' => $order['id']]);
        self::controllaSessione((string) $res['id'], $riepilogo['prezzo'], isset($p['discounts[0][coupon]']) ? $riepilogo['totale'] : $riepilogo['prezzo'], $omaggio !== null);
        return $res;
    }

    /**
     * 9A · Per tools/verifica-stripe.php (solo chiavi di prova): una sessione di Checkout senza cliente né ordine, con
     * lo sconto in percento voluto, controllata nei due modi e fatta scadere. @return array{ok:bool,errore:string,sessione:string}
     */
    public static function sessioneDiProva(array $pv, array $pkg, int $quantita, int $percento, int $mesiOmaggio = 0): array
    {
        try {
            [$voci, $pv] = self::vociCheckout($pv, $pkg, $quantita);
            self::verificaPrezzi($pv);
            $prezzo = Plans::price($pv, $quantita);
            $sconto = $percento > 0 ? (int) round($prezzo * $percento / 100) : 0;
            $p = ['mode' => 'subscription', 'success_url' => 'https://example.com/ok', 'cancel_url' => 'https://example.com/no',
                  'billing_address_collection' => 'required', 'tax_id_collection[enabled]' => 'true'] + $voci;
            if ($percento > 0) $p['discounts[0][coupon]'] = Sconti::couponCombinato($percento);
            // 9D · Con l'omaggio: trial_end, carta obbligatoria, e la sessione deve uscire a 0 €.
            if ($mesiOmaggio > 0) {
                $p['subscription_data[trial_end]'] = (string) Omaggi::fine($mesiOmaggio);
                $p['payment_method_collection'] = 'always';
                $p['subscription_data[trial_settings][end_behavior][missing_payment_method]'] = 'cancel';
            }
            $res = self::call('POST', 'checkout/sessions', $p);
            self::controllaSessione((string) $res['id'], $prezzo, $prezzo - $sconto, $mesiOmaggio > 0);
            self::call('POST', 'checkout/sessions/' . rawurlencode((string) $res['id']) . '/expire');
            return ['ok' => true, 'errore' => '', 'sessione' => (string) $res['id']];
        } catch (\Throwable $e) {
            return ['ok' => false, 'errore' => $e->getMessage(), 'sessione' => ''];
        }
    }

    public static function retrieveSubscription(string $id): array
    {
        // Con il prodotto espanso si riconosce la voce "struttura aggiuntiva"; 9D: la carta, per le ultime 4 cifre negli avvisi.
        return self::call('GET', 'subscriptions/' . rawurlencode($id), ['expand' => ['items.data.price.product', 'default_payment_method']]);
    }

    /**
     * 9D · Le ultime 4 cifre della carta dell'abbonamento: dal metodo di pagamento dell'abbonamento, altrimenti da quello
     * predefinito del cliente. Vuoto se non si sa (si scriverà «sul tuo metodo di pagamento»). Rete: solo se serve.
     */
    public static function ultime4(array $sub, string $customerId = ''): string
    {
        $pm = $sub['default_payment_method'] ?? null;
        if (is_array($pm) && ($pm['card']['last4'] ?? '') !== '') return (string) $pm['card']['last4'];
        try {
            if (is_string($pm) && $pm !== '') { $o = self::call('GET', 'payment_methods/' . rawurlencode($pm)); return (string) ($o['card']['last4'] ?? ''); }
            if ($customerId !== '') {
                $c = self::call('GET', 'customers/' . rawurlencode($customerId), ['expand' => ['invoice_settings.default_payment_method']]);
                $cpm = $c['invoice_settings']['default_payment_method'] ?? null;
                if (is_array($cpm)) return (string) ($cpm['card']['last4'] ?? '');
            }
        } catch (\Throwable $e) { Log::exception($e, 'Stripe: ultime 4 cifre della carta'); }
        return '';
    }

    /**
     * 9D · Quanto Stripe addebiterà al prossimo rinnovo (fattura imminente): [imponibile IVA esclusa, sconti, totale con IVA],
     * oppure null se Stripe non risponde (si usa il listino con gli sconti noti).
     */
    public static function prossimaFattura(string $subscriptionId): ?array
    {
        try {
            $inv = self::call('GET', 'invoices/upcoming', ['subscription' => $subscriptionId]);
        } catch (\Throwable $e) { Log::exception($e, 'Stripe: fattura imminente'); return null; }
        $sconto = 0;
        foreach ((array) ($inv['total_discount_amounts'] ?? []) as $d) $sconto += (int) ($d['amount'] ?? 0);
        $netto = isset($inv['total_excluding_tax']) ? (int) $inv['total_excluding_tax'] : (int) ($inv['subtotal'] ?? 0) - $sconto;
        return ['netto' => $netto, 'sconto' => $sconto, 'totale' => (int) ($inv['total'] ?? $netto), 'subtotal' => (int) ($inv['subtotal'] ?? 0)];
    }

    /** 9E · Un rimborso, totale o parziale, dal payment intent di una fattura pagata (idempotente per fattura e importo). */
    public static function rimborsa(string $paymentIntent, int $cents, string $motivo, string $invoiceId): array
    {
        $p = ['payment_intent' => $paymentIntent, 'metadata[invoice]' => $invoiceId];
        if ($cents > 0) $p['amount'] = (string) $cents;
        if (in_array($motivo, ['duplicate', 'requested_by_customer', 'fraudulent'], true)) $p['reason'] = $motivo;
        return self::call('POST', 'refunds', $p, 'mhw-refund-' . $invoiceId . '-' . $cents);
    }

    /** 9E · Le ultime fatture pagate di un cliente, dalla più recente. */
    public static function fatturePagate(string $customerId, int $max = 12): array
    {
        $r = self::call('GET', 'invoices', ['customer' => $customerId, 'status' => 'paid', 'limit' => (string) $max]);
        return (array) ($r['data'] ?? []);
    }

    /**
     * Cambia il numero di strutture aggiuntive di un abbonamento Portfolio.
     * Aumento: conguaglio fatturato subito e cambio applicato SOLO se il
     * pagamento riesce (pending_if_incomplete); il webhook porta la quantità.
     * Riduzione: credito proporzionale sulla prossima fattura.
     */
    public static function updateExtraQuantity(string $subscriptionId, string $itemId, int $extra, bool $aumento): array
    {
        $p = ['items[0][id]' => $itemId, 'items[0][quantity]' => max(0, $extra),
              'proration_behavior' => $aumento ? 'always_invoice' : 'create_prorations'];
        if ($aumento) $p['payment_behavior'] = 'pending_if_incomplete';
        return self::call('POST', 'subscriptions/' . rawurlencode($subscriptionId), $p,
                          'mhw-qty-' . $subscriptionId . '-' . $extra . '-' . gmdate('YmdHi'));
    }

    /**
     * Una struttura in più, aggiunta dal pannello: la voce delle strutture aggiuntive
     * sale di uno, e la parte dell'anno che resta va sulla prossima fattura
     * (proration_behavior=create_prorations). Il numero nuovo lo conferma il webhook.
     */
    public static function addExtraProrated(string $subscriptionId, string $itemId, int $extra): array
    {
        return self::call('POST', 'subscriptions/' . rawurlencode($subscriptionId),
                          ['items[0][id]' => $itemId, 'items[0][quantity]' => max(0, $extra), 'proration_behavior' => 'create_prorations'],
                          'mhw-qty-add-' . $subscriptionId . '-' . $extra);
    }

    /**
     * I Price di Stripe di una versione del listino (cambio di piano, 6H): se mancano si
     * creano dalla versione e si salvano, così l'abbonamento può puntarci. Il prodotto
     * porta il metadato ruolo (base / aggiuntiva) che quantityFrom usa per riconoscere le voci.
     * @return array{0:string,1:string} [Price base, Price della struttura aggiuntiva ('' se il piano non è a strutture)]
     */
    public static function ensurePrices(array $pv, array $pkg): array
    {
        $crea = function (int $cents, string $ruolo, string $nome) use ($pv, $pkg): string {
            $r = self::call('POST', 'prices', [
                'currency' => strtolower((string) $pv['currency']), 'unit_amount' => (string) $cents, 'tax_behavior' => 'exclusive',
                'recurring[interval]' => 'year', 'product_data[name]' => $nome,
                'product_data[metadata][package]' => (string) $pkg['code'], 'product_data[metadata][ruolo]' => $ruolo,
            ], 'mhw-price-' . $pv['id'] . '-' . ($ruolo === 'base' ? 'base' : 'extra'));
            return (string) $r['id'];
        };
        $base = (string) ($pv['stripe_price_id'] ?? '');
        if ($base === '') {
            $base = $crea((int) $pv['price_cents'], 'base', 'MyHouse Welcome ' . $pkg['name']);
            Db::update('package_versions', ['stripe_price_id' => $base], 'id = :id', ['id' => $pv['id']]);
        }
        return [$base, Plans::perProperty($pv) ? self::ensureExtraPrice($pv, $pkg) : ''];
    }

    /**
     * Il Price delle strutture aggiuntive di una versione Portfolio, creato se manca. Con gli
     * scaglioni è un Price graduato: la quantità della voce è il numero di strutture oltre la
     * prima, quindi lo scaglione «dalla 3ª alla 5ª» copre le unità dalla 2 alla 4.
     */
    public static function ensureExtraPrice(array $pv, array $pkg): string
    {
        $extra = (string) ($pv['stripe_extra_price_id'] ?? '');
        if ($extra !== '') return $extra;
        $nome = 'MyHouse Welcome ' . $pkg['name'] . ' — struttura aggiuntiva';
        $p = ['currency' => strtolower((string) $pv['currency']), 'tax_behavior' => 'exclusive', 'recurring[interval]' => 'year',
              'product_data[name]' => $nome, 'product_data[metadata][package]' => (string) $pkg['code'], 'product_data[metadata][ruolo]' => 'aggiuntiva'];
        $scaglioni = Plans::tiers($pv);
        if (count($scaglioni) > 1) {
            $p['billing_scheme'] = 'tiered'; $p['tiers_mode'] = 'graduated';
            foreach ($scaglioni as $i => $t) {
                $p["tiers[$i][up_to]"] = $t['a'] === null ? 'inf' : (string) ($t['a'] - 1);
                $p["tiers[$i][unit_amount]"] = (string) $t['cents'];
            }
        } else {
            $p['unit_amount'] = (string) (int) $pv['extra_price_cents'];
        }
        $r = self::call('POST', 'prices', $p, 'mhw-price-' . $pv['id'] . '-extra' . (count($scaglioni) > 1 ? '-scaglioni' : ''));
        $extra = (string) $r['id'];
        Db::update('package_versions', ['stripe_extra_price_id' => $extra], 'id = :id', ['id' => $pv['id']]);
        return $extra;
    }

    /**
     * La pagina di pagamento della differenza per salire di piano: un pagamento singolo
     * (mode=payment) con la sua fattura. IVA, indirizzo e partita IVA come per l'abbonamento.
     */
    public static function checkoutChange(array $order, string $descrizione, array $account, array $user): string
    {
        $base = Support::baseUrl();
        $meta = ['order_id' => (string) $order['id'], 'account_id' => (string) $account['id'], 'kind' => 'change'];
        $p = [
            'mode' => 'payment',
            'customer' => self::ensureCustomer($account, $user),
            'client_reference_id' => (string) $order['id'],
            'success_url' => $base . '/pagamento/ok?order=' . $order['id'],
            'cancel_url' => $base . '/account/piano',
            'locale' => 'it',
            'line_items[0][quantity]' => 1,
            'line_items[0][price_data][currency]' => strtolower((string) $order['currency']),
            'line_items[0][price_data][unit_amount]' => (string) (int) $order['amount_cents'],
            'line_items[0][price_data][tax_behavior]' => 'exclusive',
            'line_items[0][price_data][product_data][name]' => $descrizione,
            'invoice_creation[enabled]' => 'true',
            'invoice_creation[invoice_data][description]' => $descrizione,
            'invoice_creation[invoice_data][metadata][order_id]' => (string) $order['id'],
            'billing_address_collection' => 'required',
            'tax_id_collection[enabled]' => 'true',
            'customer_update[address]' => 'auto',
            'customer_update[name]' => 'auto',
        ];
        if (!empty(Config::get('stripe')['automatic_tax'])) $p['automatic_tax[enabled]'] = 'true';
        // 9I · I codici nei metadati di Checkout, del pagamento e della fattura, e nella causale (descrizione della fattura).
        $meta += self::codici($order);
        if (($meta['codice_pagamento'] ?? '') !== '') {
            $p['invoice_creation[invoice_data][description]'] .= ' · Codice cliente ' . $meta['codice_cliente'] . ' · Pagamento ' . $meta['codice_pagamento'];
            foreach (['codice_cliente', 'codice_pagamento', 'codice_struttura'] as $k) if (($meta[$k] ?? '') !== '') $p["invoice_creation[invoice_data][metadata][$k]"] = $meta[$k];
        }
        foreach ($meta as $k => $v) { $p["metadata[$k]"] = $v; $p["payment_intent_data[metadata][$k]"] = $v; }
        $res = self::call('POST', 'checkout/sessions', $p, 'mhw-checkout-order-' . $order['id']);
        Db::update('orders', ['provider_session_id' => $res['id'], 'updated_at' => Support::now()], 'id = :oid', ['oid' => $order['id']]);
        return $res['url'];
    }

    /**
     * Porta l'abbonamento alle voci nuove (CambioPiano::voci), SEMPRE senza proporzioni:
     * la differenza è già stata pagata a parte (salita) o non è dovuta (discesa).
     */
    public static function applyChange(string $subscriptionId, array $voci, string $chiave): array
    {
        return self::call('POST', 'subscriptions/' . rawurlencode($subscriptionId),
                          $voci + ['proration_behavior' => 'none', 'expand[0]' => 'items.data.price.product'], $chiave);
    }

    /** Rinnovo automatico acceso o spento. Il servizio pagato resta fino alla fine del periodo. */
    public static function setCancelAtPeriodEnd(string $subscriptionId, bool $cancel): array
    {
        return self::call('POST', 'subscriptions/' . rawurlencode($subscriptionId), ['cancel_at_period_end' => $cancel ? 'true' : 'false']);
    }

    public static function portalUrl(string $customerId): string
    {
        $r = self::call('POST', 'billing_portal/sessions', ['customer' => $customerId, 'return_url' => Support::baseUrl() . '/account']);
        return $r['url'];
    }

    /**
     * Verifica la firma del webhook. Senza questo controllo chiunque potrebbe
     * spedirci un "pagamento riuscito" e regalarsi un abbonamento.
     */
    public static function verifySignature(string $payload, string $header, string $secret, int $tolerance = 300): bool
    {
        return self::motivoFirma($payload, $header, $secret, $tolerance) === null;
    }

    /** Perché la firma del webhook non va (per il registro e il Quadro), o null se va bene. */
    public static function motivoFirma(string $payload, string $header, string $secret, int $tolerance = 300): ?string
    {
        if ($secret === '') return 'manca il segreto del webhook nelle Impostazioni';
        if (trim($header) === '') return 'la richiesta non ha la firma di Stripe (intestazione Stripe-Signature)';
        $parts = [];
        foreach (explode(',', $header) as $piece) {
            $kv = explode('=', trim($piece), 2);
            if (count($kv) === 2) $parts[$kv[0]][] = $kv[1];
        }
        $t = $parts['t'][0] ?? null;
        $sigs = $parts['v1'] ?? [];
        if (!$t || !ctype_digit($t) || !$sigs) return 'la firma di Stripe è incompleta';
        if (abs(time() - (int) $t) > $tolerance) return 'orario della firma lontano più di 5 minuti da quello del server';
        $expected = hash_hmac('sha256', $t . '.' . $payload, $secret);
        foreach ($sigs as $s) if (hash_equals($expected, $s)) return null;
        return 'la firma non corrisponde al segreto del webhook (segreto di un altro endpoint o di un\'altra modalità)';
    }
}
