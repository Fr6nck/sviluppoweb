<?php
namespace MHW;

/**
 * Gli abbonamenti e gli eventi di Stripe.
 *
 * Idempotenza: l'evento si segna come elaborato DENTRO la stessa transazione
 * che lo applica. Se qualcosa fallisce a metà, la transazione torna indietro
 * — segno compreso — e Stripe lo rimanda. Se arriva due volte, l'indice unico
 * su (provider, provider_event_id) ferma il secondo, anche se i due arrivi
 * sono contemporanei.
 */
final class Billing
{
    private static function iso(?int $ts): string { return $ts ? gmdate('Y-m-d\TH:i:s\Z', $ts) : ''; }

    /** Le date del periodo: nelle API recenti stanno sulle voci, in quelle vecchie sull'abbonamento. */
    private static function period(array $sub): array
    {
        $item = $sub['items']['data'][0] ?? [];
        return [
            self::iso($sub['current_period_start'] ?? $item['current_period_start'] ?? null),
            self::iso($sub['current_period_end'] ?? $item['current_period_end'] ?? null),
        ];
    }

    /**
     * Portfolio: quante strutture paga l'abbonamento, e quale voce le conta.
     * La voce "struttura aggiuntiva" si riconosce dall'id già noto, dal Price
     * ID configurato o dal prodotto (metadato ruolo=aggiuntiva).
     *
     * @return array{0:?int,1:string} [quantità o null se non ricavabile, id della voce]
     */
    private static function quantityFrom(array $sub, array $pv, string $itemNoto = ''): array
    {
        $voci = $sub['items']['data'] ?? null;
        if (!is_array($voci) || !$voci) return [null, $itemNoto];
        foreach ($voci as $v) {
            $prezzo = $v['price'] ?? [];
            $prodotto = is_array($prezzo['product'] ?? null) ? $prezzo['product'] : [];
            $aggiuntiva = ($itemNoto !== '' && ($v['id'] ?? '') === $itemNoto)
                || (($pv['stripe_extra_price_id'] ?? '') !== '' && ($prezzo['id'] ?? '') === $pv['stripe_extra_price_id'])
                || (($prodotto['metadata']['ruolo'] ?? '') === 'aggiuntiva');
            if ($aggiuntiva) return [1 + max(0, (int) ($v['quantity'] ?? 0)), (string) ($v['id'] ?? '')];
        }
        // Nessuna voce riconosciuta: meglio la quantità dell'ordine (o quella già
        // registrata) che toglierne qualcuna per un dato che non si sa leggere.
        return [null, $itemNoto];
    }

    /** @return string un esito leggibile, per il registro */
    public static function handleEvent(array $event): string
    {
        $id = (string) ($event['id'] ?? '');
        $type = (string) ($event['type'] ?? '');
        if ($id === '' || $type === '') return 'evento-incompleto';
        if (Db::one('SELECT id FROM webhook_events WHERE provider = ? AND provider_event_id = ?', ['stripe', $id])) return 'gia-elaborato';

        $obj = $event['data']['object'] ?? [];

        // Le chiamate di rete si fanno PRIMA della transazione: non si tiene
        // bloccato il database mentre si aspetta Stripe.
        $sub = null;
        if (in_array($type, ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true) && !empty($obj['subscription']) && is_string($obj['subscription'])) {
            $sub = Stripe::retrieveSubscription($obj['subscription']);
        }
        // Cambio di piano pagato (6H): Stripe passa alle voci nuove prima della transazione.
        $cambio = ['ok' => false, 'sub' => null];
        if (in_array($type, ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true) && ($obj['mode'] ?? '') === 'payment'
            && ($obj['metadata']['kind'] ?? '') === 'change' && in_array($obj['payment_status'] ?? '', ['paid', 'no_payment_required'], true)) {
            $cambio = CambioAbbonamento::preparaStripe((int) ($obj['metadata']['order_id'] ?? 0));
        }

        // 9O · Un rimborso: le righe dei rimborsi della charge (nelle API recenti non arrivano dentro l'evento).
        $rimborsi = [];
        if ($type === 'charge.refunded') {
            $rimborsi = (array) ($obj['refunds']['data'] ?? []);
            if (!$rimborsi && !empty($obj['id']) && Stripe::enabled()) {
                try { $rimborsi = (array) (Stripe::call('GET', 'refunds', ['charge' => (string) $obj['id'], 'limit' => '100'])['data'] ?? []); }
                catch (\Throwable $e) { Log::exception($e, 'rimborsi della charge ' . $obj['id']); throw $e; }   // Stripe rimanda l'evento
            }
        }

        try {
            $esito = Db::tx(function () use ($id, $type, $obj, $event, $sub, $cambio, $rimborsi) {
                Db::insert('webhook_events', [
                    'provider' => 'stripe', 'provider_event_id' => $id, 'kind' => $type,
                    'payload' => json_encode($event, JSON_UNESCAPED_UNICODE), 'processed_at' => Support::now(),
                ]);
                return match ($type) {
                    'checkout.session.completed' => ($obj['mode'] ?? '') === 'payment' ? self::onChangePaid($obj, $cambio) : self::onCheckoutCompleted($obj, $sub),
                    // 9O · L'addebito SEPA confermato (o rifiutato) dalla banca: un abbonamento nuovo o un cambio di piano.
                    'checkout.session.async_payment_succeeded' => ($obj['mode'] ?? '') === 'subscription' ? self::onCheckoutCompleted(['payment_status' => 'paid'] + $obj, $sub) : self::onChangePaid($obj, $cambio),
                    'checkout.session.async_payment_failed' => ($obj['mode'] ?? '') === 'subscription' ? self::onSepaFallito($obj) : self::onChangeFailed($obj),
                    'checkout.session.expired' => self::onCheckoutExpired($obj),
                    'invoice.paid', 'invoice.payment_succeeded' => self::onInvoicePaid($obj),
                    'invoice.payment_failed' => self::onInvoiceFailed($obj),
                    'customer.subscription.created', 'customer.subscription.updated' => self::onSubscriptionChanged($obj),
                    'customer.subscription.deleted' => self::onSubscriptionDeleted($obj),
                    'customer.subscription.trial_will_end' => self::onTrialWillEnd($obj),   // 9D: solo nel registro (gli avvisi li mandiamo noi)
                    'charge.refunded' => self::onChargeRefunded($obj, $rimborsi),             // 9E/9O: i rimborsi, da qualunque parte (sito, Stripe, Adamo)
                    'charge.refund.updated', 'refund.updated' => self::onRefundUpdated($obj),  // 9O: un rimborso confermato o fallito
                    default => 'ignorato',
                };
            });
        } catch (\PDOException $e) {
            // Lo stesso evento arrivato in parallelo: l'indice unico ha fermato il secondo.
            if (Db::one('SELECT id FROM webhook_events WHERE provider = ? AND provider_event_id = ?', ['stripe', $id])) return 'gia-elaborato';
            throw $e;
        }
        // Invita un amico: lo sconto su Stripe e l'email partono a transazione chiusa.
        Inviti::dopoEvento();
        Sconti::dopoEvento();   // 9N: lo sconto del rinnovo (uno solo: inviti + giudizio) dopo una fattura pagata
        self::dopoRimborso();   // 9O: un abbonamento rimborsato per intero si chiude anche su Stripe
        return $esito;
    }

    private static function onCheckoutCompleted(array $s, ?array $sub): string
    {
        if (($s['mode'] ?? '') !== 'subscription') return 'non-abbonamento';
        $orderId = (int) ($s['metadata']['order_id'] ?? $s['client_reference_id'] ?? 0);
        $o = Db::one('SELECT * FROM orders WHERE id = ?', [$orderId]);
        if (!$o) { Log::error('Webhook: ordine inesistente', ['order' => $orderId]); return 'ordine-sconosciuto'; }
        if ((string) ($s['metadata']['account_id'] ?? $o['account_id']) !== (string) $o['account_id']) {
            Log::error('Webhook: account non coerente con l\'ordine', ['order' => $orderId]);
            return 'incoerente';
        }
        // Pagamenti differiti (9O: addebito SEPA): l'ordine è «in verifica», la guida resta offline; si aspetta
        // checkout.session.async_payment_succeeded o invoice.paid, e il periodo decorre dalla conferma.
        if (!in_array($s['payment_status'] ?? '', ['paid', 'no_payment_required'], true)) {
            if ($o['status'] === 'paid') return 'gia-pagato';
            Db::update('orders', ['status' => 'awaiting', 'updated_at' => Support::now()]
                       + (Migrator::columnExists('orders', 'in_verifica_at') && empty($o['in_verifica_at']) ? ['in_verifica_at' => Support::now()] : []), 'id = :oid', ['oid' => $orderId]);
            Auth::audit('payment.processing', (int) Db::val('SELECT user_id FROM accounts WHERE id = ?', [(int) $o['account_id']], 0) ?: null,
                        ['order' => $orderId, 'cliente' => Codici::cliente((int) $o['account_id']), 'pagamento' => (string) ($o['code'] ?? '')]);
            return 'in-attesa-del-pagamento';
        }
        // Lo sconto davvero concesso lo dice la sessione (total_details.amount_discount).
        $sconto = isset($s['total_details']['amount_discount']) ? (int) $s['total_details']['amount_discount'] : null;
        self::activate($o, $sub ?? ['id' => $s['subscription'] ?? '', 'status' => 'active'], (string) ($s['customer'] ?? ''), $sconto);
        return 'abbonamento-attivato';
    }

    /** 9O · Gli ordini attivati (email «La tua guida è online») e gli addebiti rifiutati, da scrivere a transazione chiusa. */
    private static array $attivati = [];
    private static array $sepaFalliti = [];

    /** Dopo la risposta a Stripe (webhook) o dopo il recupero: «La tua guida è online» o «Pagamento non riuscito». Il giro dei richiami riprende quelle perse. */
    public static function dopoRisposta(): void
    {
        $a = self::$attivati; $f = self::$sepaFalliti; self::$attivati = []; self::$sepaFalliti = [];
        ProgrammaPartner::dopoEvento();   // le email al partner (commissione nuova o stornata)
        foreach (array_unique($a) as $oid) Pagamenti::emailOnline((int) $oid);
        foreach (array_unique($f) as $oid) Pagamenti::emailSepaFallito((int) $oid);
    }

    /** 9O · L'addebito SEPA rifiutato: ordine «non riuscito» (in Anomalie), guida offline, email con «Paga con carta». */
    private static function onSepaFallito(array $s): string
    {
        $orderId = (int) ($s['metadata']['order_id'] ?? $s['client_reference_id'] ?? 0);
        $o = Db::one('SELECT * FROM orders WHERE id = ?', [$orderId]);
        if (!$o || $o['status'] === 'paid') return $o ? 'gia-pagato' : 'ordine-sconosciuto';
        Db::update('orders', ['status' => 'failed', 'updated_at' => Support::now()]
                   + (Migrator::columnExists('orders', 'in_verifica_at') && empty($o['in_verifica_at']) ? ['in_verifica_at' => Support::now()] : []), 'id = :oid', ['oid' => $orderId]);
        Auth::audit('payment.sepa_failed', (int) Db::val('SELECT user_id FROM accounts WHERE id = ?', [(int) $o['account_id']], 0) ?: null,
                    ['order' => $orderId, 'cliente' => Codici::cliente((int) $o['account_id']), 'pagamento' => (string) ($o['code'] ?? '')]);
        self::$sepaFalliti[] = $orderId;
        return 'addebito-non-riuscito';
    }

    /** Ordine pagato → abbonamento attivo → guida pubblicata. Tutto o niente. $sconto: lo sconto del primo anno secondo Stripe. */
    public static function activate(array $order, array $sub, string $customerId, ?int $sconto = null): void
    {
        if ($order['status'] === 'paid') return;
        if (($order['kind'] ?? 'new') === 'new') self::$attivati[] = (int) $order['id'];   // 9O: l'email parte a transazione chiusa
        $acc = (int) $order['account_id'];
        [$inizio, $fine] = self::period($sub);
        $price = (string) ($sub['items']['data'][0]['price']['id'] ?? '');

        Db::update('orders', ['status' => 'paid', 'updated_at' => Support::now()], 'id = :oid', ['oid' => $order['id']]);
        // 8J: chi acquista (impresa o consumatore) viene dall'ordine; in un cambio di piano resta quello dell'abbonamento di prima.
        $acquirente = [];
        if (Migrator::columnExists('subscriptions', 'acquirente')) {
            $prima = Db::one("SELECT acquirente, recesso_rinuncia FROM subscriptions WHERE account_id = ? AND acquirente <> '' ORDER BY id DESC", [$acc]);
            if (($order['acquirente'] ?? '') !== '') $acquirente = ['acquirente' => $order['acquirente'], 'recesso_rinuncia' => (int) $order['recesso_rinuncia']];
            elseif ($prima) $acquirente = ['acquirente' => $prima['acquirente'], 'recesso_rinuncia' => (int) $prima['recesso_rinuncia']];
        }
        // Un piano nuovo sostituisce il precedente.
        Db::run('UPDATE subscriptions SET status = ?, updated_at = ? WHERE account_id = ? AND status IN (?, ?)',
                ['replaced', Support::now(), $acc, 'active', 'trialing']);

        $pv = Db::one('SELECT * FROM package_versions WHERE id = ?', [$order['package_version_id']]) ?: [];
        $quantita = 1; $voceExtra = '';
        // 9O · Un piano a struttura singola vale sempre 1: un ordine con un'altra quantità si attiva con 1 e resta in Anomalie.
        if (!Plans::perProperty($pv) && (int) ($order['quantity'] ?? 1) !== 1) Log::error('Quantità non valida nell\'ordine pagato', ['order' => (int) $order['id'], 'quantita' => (int) $order['quantity']]);
        if (Plans::perProperty($pv)) {
            [$daStripe, $voceExtra] = self::quantityFrom($sub, $pv);
            // Vale quello che Stripe dice di aver fatto pagare; l'ordine è la riserva.
            $quantita = $daStripe ?? max(1, (int) ($order['quantity'] ?? 1));
        }

        $esistente = ($sub['id'] ?? '') !== '' ? Db::one('SELECT id FROM subscriptions WHERE provider_subscription_id = ?', [$sub['id']]) : null;
        // 9D · Codice omaggio: l'abbonamento nasce «trialing», il primo addebito è la fine dei mesi gratis (trial_end); il codice si brucia qui.
        $omaggio = [];
        if (Migrator::columnExists('subscriptions', 'omaggio_fino')) {
            $fineOmaggio = !empty($sub['trial_end']) ? self::iso((int) $sub['trial_end']) : (($sub['status'] ?? '') === 'trialing' ? $fine : '');
            if (!empty($order['codice_omaggio_id'])) {
                Omaggi::brucia((int) $order['codice_omaggio_id'], $acc);
                if ($fineOmaggio === '') { $c = Omaggi::riga((int) $order['codice_omaggio_id']); $fineOmaggio = $c ? Omaggi::fineIso((int) $c['mesi']) : ''; }
            }
            $omaggio = ['omaggio_fino' => $fineOmaggio !== '' ? $fineOmaggio : null, 'card_last4' => Stripe::enabled() ? Stripe::ultime4($sub, $customerId) : ''];
        }
        $riga = [
            'account_id' => $acc, 'package_version_id' => $order['package_version_id'],
            'status' => in_array($sub['status'] ?? 'active', ['active', 'trialing'], true) ? $sub['status'] : 'active',
            'provider' => 'stripe', 'provider_customer_id' => $customerId,
            'provider_subscription_id' => (string) ($sub['id'] ?? ''), 'provider_price_id' => $price,
            'current_period_start' => $inizio ?: Support::now(),
            'current_period_end' => $fine ?: gmdate('Y-m-d\TH:i:s\Z', strtotime('+1 year')),
            'cancel_at_period_end' => !empty($sub['cancel_at_period_end']) ? 1 : 0,
            'payment_status' => 'paid', 'updated_at' => Support::now(),
            'quantity' => $quantita, 'provider_extra_item_id' => $voceExtra,
        ] + $acquirente + $omaggio;
        // 9I · L'abbonamento porta il codice dell'ordine che l'ha creato.
        if (Migrator::columnExists('subscriptions', 'code') && ($codP = Codici::pagamento((int) $order['id'])) !== '') $riga['code'] = $codP;
        if ($esistente) { Db::update('subscriptions', $riga, 'id = :sid', ['sid' => $esistente['id']]); $subId = (int) $esistente['id']; }
        else $subId = Db::insert('subscriptions', $riga + ['created_at' => Support::now()]);
        Offerte::attivata($order, $subId);   // 9N · 10 bis: l'abbonamento nato da un'offerta su misura

        Db::update('accounts', ['intended_package_version_id' => $order['package_version_id'], 'intended_quantity' => $quantita]
                   + ($customerId !== '' ? ['stripe_customer_id' => $customerId] : []), 'id = :aid', ['aid' => $acc]);
        Db::run('UPDATE package_versions SET sold_count = sold_count + 1 WHERE id = ?', [$order['package_version_id']]);
        Sconti::registra($order, $sconto);   // un utilizzo per ordine, nella stessa transazione
        Inviti::pagato($order);              // se è un amico invitato, l'invito diventa valido
        Giudizi::pagato($order);             // 9B: lo sconto del giudizio sul primo anno è stato applicato
        // 9O · Il cliente di un partner: la commissione sull'imponibile incassato (il prezzo meno lo sconto di Stripe).
        ProgrammaPartner::matura($acc, 'ordine:' . (int) $order['id'], max(0, (int) $order['amount_cents'] - (int) ($sconto ?? $order['discount_cents'] ?? 0)), Codici::pagamento((int) $order['id']));
        Entitlements::forget($acc);

        // La guida che aspettava il pagamento va online adesso.
        if (!empty($order['property_id'])) {
            $p = Db::one('SELECT id FROM properties WHERE id = ? AND account_id = ?', [$order['property_id'], $acc]);
            if ($p) Guide::publish((int) $p['id']);
        }
    }

    /** Il pagamento della differenza per salire di piano (6H). */
    private static function onChangePaid(array $s, array $cambio): string
    {
        if (($s['metadata']['kind'] ?? '') !== 'change') return 'non-abbonamento';
        $o = Db::one("SELECT * FROM orders WHERE id = ? AND kind = 'change'", [(int) ($s['metadata']['order_id'] ?? $s['client_reference_id'] ?? 0)]);
        if (!$o) return 'ordine-sconosciuto';
        if ((string) ($s['metadata']['account_id'] ?? '') !== (string) $o['account_id']) {
            Log::error('Webhook: account non coerente con l\'ordine di cambio', ['order' => $o['id']]);
            return 'incoerente';
        }
        if (!in_array($s['payment_status'] ?? '', ['paid', 'no_payment_required'], true)) {
            Db::update('orders', ['status' => 'awaiting', 'updated_at' => Support::now()], 'id = :oid', ['oid' => $o['id']]);
            return 'cambio-in-attesa-del-pagamento';
        }
        $esito = CambioAbbonamento::pagato($o, $cambio);
        // 9O · Il conguaglio di un cliente con partner: anche lui porta la commissione (sull'imponibile pagato).
        ProgrammaPartner::matura((int) $o['account_id'], 'ordine:' . (int) $o['id'], (int) $o['amount_cents'], Codici::pagamento((int) $o['id']), false);
        return $esito;
    }

    private static function onChangeFailed(array $s): string
    {
        $orderId = (int) ($s['metadata']['order_id'] ?? $s['client_reference_id'] ?? 0);
        Db::run("UPDATE orders SET status = 'failed', updated_at = ? WHERE id = ? AND status IN ('pending', 'awaiting')", [Support::now(), $orderId]);
        return 'cambio-non-pagato';
    }

    private static function onCheckoutExpired(array $s): string
    {
        $orderId = (int) ($s['metadata']['order_id'] ?? $s['client_reference_id'] ?? 0);
        // Solo la sessione in uso chiude l'ordine: quella incorporata, fatta scadere perché il cliente ha scelto la pagina
        // di Stripe, lascia l'ordine aperto sulla sessione nuova.
        $n = Db::run("UPDATE orders SET status = 'expired', updated_at = ? WHERE id = ? AND status = 'pending' AND (provider_session_id = ? OR provider_session_id = '')",
                     [Support::now(), $orderId, (string) ($s['id'] ?? '')]);
        return $n > 0 ? 'checkout-scaduto' : 'sessione-sostituita';
    }

    private static function subscriptionId(array $invoice): string
    {
        // Le API recenti spostano il riferimento dentro parent.subscription_details.
        return (string) ($invoice['subscription'] ?? $invoice['parent']['subscription_details']['subscription'] ?? '');
    }

    private static function onInvoicePaid(array $inv): string
    {
        $sid = self::subscriptionId($inv);
        $row = $sid !== '' ? Db::one('SELECT * FROM subscriptions WHERE provider_subscription_id = ?', [$sid]) : null;
        if (!$row) {
            // La fattura può arrivare prima del checkout completato: sarà lui a creare l'abbonamento.
            // Un ordine rimasto "in attesa" (pagamento differito) si attiva qui.
            $meta = $inv['parent']['subscription_details']['metadata'] ?? $inv['subscription_details']['metadata'] ?? [];
            $o = !empty($meta['order_id']) ? Db::one("SELECT * FROM orders WHERE id = ? AND status = 'awaiting'", [(int) $meta['order_id']]) : null;
            if ($o) {
                $linea = $inv['lines']['data'][0] ?? [];
                self::activate($o, ['id' => $sid, 'status' => 'active', 'items' => ['data' => [[
                    'current_period_start' => $linea['period']['start'] ?? null, 'current_period_end' => $linea['period']['end'] ?? null,
                    'price' => ['id' => $linea['price']['id'] ?? ($linea['pricing']['price_details']['price'] ?? '')]]]]],
                    (string) ($inv['customer'] ?? ''));
                return 'abbonamento-attivato-da-fattura';
            }
            return 'abbonamento-non-ancora-noto';
        }
        $linea = $inv['lines']['data'][0] ?? [];
        Db::update('subscriptions', [
            'status' => $row['status'] === 'refunded' ? 'refunded' : 'active', 'payment_status' => $row['status'] === 'refunded' ? 'refunded' : 'paid',   // 9O
            'current_period_start' => self::iso($linea['period']['start'] ?? null) ?: $row['current_period_start'],
            'current_period_end' => self::iso($linea['period']['end'] ?? null) ?: $row['current_period_end'],
            'updated_at' => Support::now(),
        ], 'id = :sid', ['sid' => $row['id']]);
        // 9D · Le ultime 4 cifre della carta, se la fattura le porta (charge espansa) e ancora non si sapevano.
        if (Migrator::columnExists('subscriptions', 'card_last4') && (string) ($row['card_last4'] ?? '') === '') {
            $l4 = (string) ($inv['charge']['payment_method_details']['card']['last4'] ?? $inv['payment_intent']['payment_method']['card']['last4'] ?? '');
            if ($l4 !== '') Db::update('subscriptions', ['card_last4' => $l4], 'id = :sid', ['sid' => $row['id']]);
        }
        Entitlements::forget((int) $row['account_id']);
        // 9I · Un rinnovo o un conguaglio senza ordine: il suo codice pagamento, scritto anche nei metadati della fattura Stripe.
        if (in_array($inv['billing_reason'] ?? '', ['subscription_cycle', 'subscription_update'], true) && ($inv['id'] ?? '') !== '') {
            $codF = Codici::pagamentoFattura((int) $row['account_id'], (string) $inv['id'], self::iso($inv['created'] ?? null) ?: Support::now());
            if ($codF !== '' && Stripe::enabled()) {
                try { Stripe::metadatiFattura((string) $inv['id'], ['codice_cliente' => Codici::cliente((int) $row['account_id']), 'codice_pagamento' => $codF]); }
                catch (\Throwable $e) { Log::exception($e, 'codice pagamento sulla fattura di rinnovo'); }
            }
        }
        Inviti::fattura($row, $inv);         // un rinnovo scontato chiude gli inviti che lo hanno pagato
        if (in_array($inv['billing_reason'] ?? '', ['subscription_cycle', 'subscription_update'], true)) Sconti::accoda((int) $row['account_id']);   // 9N
        Giudizi::fattura($row, $inv);        // 9B: un rinnovo scontato chiude lo sconto del giudizio promesso sul rinnovo
        // 9O · Il rinnovo (o il primo addebito dopo i mesi gratis) di un cliente con partner, o un conguaglio pagato (strutture
        // Portfolio in più durante l'anno): la commissione sull'imponibile.
        $conguaglio = ($inv['billing_reason'] ?? '') === 'subscription_update' && (int) ($inv['amount_paid'] ?? 0) > 0;
        if ((($inv['billing_reason'] ?? '') === 'subscription_cycle' || $conguaglio) && ($inv['id'] ?? '') !== '') {
            ProgrammaPartner::matura((int) $row['account_id'], 'fattura:' . $inv['id'], (int) ($inv['total_excluding_tax'] ?? $inv['subtotal'] ?? 0), Codici::perFattura((string) $inv['id']), !$conguaglio);
        }
        // 9N · 10 bis: un'offerta su misura nuova che vale dal rinnovo (Stripe ha già il prezzo nuovo).
        if (!empty($row['next_offerta_id']) && ($inv['billing_reason'] ?? '') === 'subscription_cycle') Offerte::alRinnovo(Db::one('SELECT * FROM subscriptions WHERE id = ?', [$row['id']]));
        // Discesa programmata (6H): al rinnovo il sito passa al piano nuovo.
        if (!empty($row['next_package_version_id']) && ($inv['billing_reason'] ?? '') === 'subscription_cycle') {
            CambioAbbonamento::alRinnovo(Db::one('SELECT * FROM subscriptions WHERE id = ?', [$row['id']]));
            return 'rinnovo-pagato-con-cambio-di-piano';
        }
        return 'rinnovo-pagato';
    }

    private static function onInvoiceFailed(array $inv): string
    {
        $sid = self::subscriptionId($inv);
        $row = $sid !== '' ? Db::one('SELECT * FROM subscriptions WHERE provider_subscription_id = ?', [$sid]) : null;
        if (!$row) return 'abbonamento-non-ancora-noto';
        Db::update('subscriptions', ['payment_status' => 'failed', 'status' => 'past_due', 'updated_at' => Support::now()],
                   'id = :sid', ['sid' => $row['id']]);
        Entitlements::forget((int) $row['account_id']);
        // 9H · L'avviso a noi va all'email amministrazione (al cliente scrive Stripe, con i suoi tentativi).
        $u = Db::one('SELECT u.name, u.email FROM accounts a JOIN users u ON u.id = a.user_id WHERE a.id = ?', [(int) $row['account_id']]) ?: ['name' => '', 'email' => ''];
        Mailer::avvisaAmministrazione('Pagamento non riuscito: ' . (trim((string) $u['name']) ?: (string) $u['email']), [
            'Il pagamento di un rinnovo non è riuscito. Stripe riprova da solo e avvisa il cliente.',
            'Cliente: ' . trim((string) $u['name']) . ' · ' . (string) $u['email'] . ' (account ' . (int) $row['account_id'] . ').',
            'Fattura Stripe: ' . (string) ($inv['id'] ?? '—') . ' · importo ' . Support::money((int) ($inv['amount_due'] ?? 0), strtoupper((string) ($inv['currency'] ?? 'eur'))) . '.'],
            ['Apri la scheda del cliente', Support::baseUrl() . '/admin/cliente/' . (int) $row['account_id']]);
        return 'pagamento-fallito';
    }

    private static function onSubscriptionChanged(array $sub): string
    {
        $row = Db::one('SELECT * FROM subscriptions WHERE provider_subscription_id = ?', [(string) ($sub['id'] ?? '')]);
        if (!$row) return 'abbonamento-non-ancora-noto';
        [$inizio, $fine] = self::period($sub);
        $pv = Db::one('SELECT * FROM package_versions WHERE id = ?', [$row['package_version_id']]) ?: [];
        $quantita = (int) $row['quantity']; $voce = (string) $row['provider_extra_item_id'];
        // Con una discesa programmata Stripe ha già le voci nuove: nel sito valgono quelle pagate fino al rinnovo.
        $programmato = !empty($row['next_package_version_id']);
        // 9N · 10 bis: con un'offerta su misura Stripe ha una sola voce (il prezzo concordato): le strutture sono quelle dell'offerta.
        if (Plans::perProperty($pv) && !$programmato && empty($row['offerta_id'])) {
            [$daStripe, $voce] = self::quantityFrom($sub, $pv, $voce);
            if ($daStripe !== null) $quantita = $daStripe;
        }
        // 9D · La carta (ultime 4 cifre) e la fine dei mesi gratis, se l'evento li porta.
        $extra = [];
        if (Migrator::columnExists('subscriptions', 'card_last4')) {
            $pm = $sub['default_payment_method'] ?? null;
            if (is_array($pm) && ($pm['card']['last4'] ?? '') !== '') $extra['card_last4'] = (string) $pm['card']['last4'];
            if (!empty($sub['trial_end']) && ($sub['status'] ?? '') === 'trialing') $extra['omaggio_fino'] = self::iso((int) $sub['trial_end']);
        }
        Db::update('subscriptions', [
            'quantity' => $quantita, 'provider_extra_item_id' => $voce,
            'status' => $row['status'] === 'refunded' ? 'refunded' : (string) ($sub['status'] ?? $row['status']),   // 9O: il rimborso totale non si annulla
            'cancel_at_period_end' => !empty($sub['cancel_at_period_end']) ? 1 : 0,
            'current_period_start' => $inizio ?: $row['current_period_start'],
            'current_period_end' => $fine ?: $row['current_period_end'],
            'provider_price_id' => $programmato ? (string) $row['provider_price_id'] : (string) ($sub['items']['data'][0]['price']['id'] ?? $row['provider_price_id']),
            'updated_at' => Support::now(),
        ] + $extra, 'id = :sid', ['sid' => $row['id']]);
        Entitlements::forget((int) $row['account_id']);
        return 'abbonamento-aggiornato';
    }

    /** 9D · Tre giorni prima della fine dei mesi gratis: Stripe avvisa; gli avvisi al cliente li manda Richiami (30 e 7 giorni). */
    private static function onTrialWillEnd(array $sub): string
    {
        $row = Db::one('SELECT * FROM subscriptions WHERE provider_subscription_id = ?', [(string) ($sub['id'] ?? '')]);
        if (!$row) return 'abbonamento-non-ancora-noto';
        $uid = (int) Db::val('SELECT user_id FROM accounts WHERE id = ?', [(int) $row['account_id']], 0);
        Auth::audit('subscription.trial_will_end', $uid ?: null, ['subscription' => (string) ($sub['id'] ?? ''), 'trial_end' => self::iso($sub['trial_end'] ?? null)]);
        return 'fine-omaggio-in-arrivo';
    }

    /** 9E · Il rimborso è arrivato: la riga in rimborsi (se c'è) si conferma, e resta nel registro. */
    /** 9O · Gli abbonamenti rimborsati per intero, da chiudere su Stripe a transazione chiusa. */
    private static array $daChiudere = [];

    /**
     * 9E/9O · charge.refunded: i rimborsi di un pagamento, partiti da qualunque parte. Una riga in `rimborsi` per ogni rimborso
     * (idempotente sull'id del rimborso: quello fatto dalla scheda del cliente c'è già e si conferma), storno in Cassa, scheda
     * cliente e Registro azioni. Rimborso totale del pagamento del periodo in corso: abbonamento «rimborsato», guida offline e
     * abbonamento chiuso su Stripe; parziale: solo la registrazione.
     */
    private static function onChargeRefunded(array $charge, array $rimborsi = []): string
    {
        if (!Migrator::tableExists('rimborsi')) return 'ignorato';
        $chargeId = (string) ($charge['id'] ?? '');
        $cents = (int) ($charge['amount_refunded'] ?? 0);
        $invoice = (string) ($charge['invoice'] ?? '');
        $nota = Migrator::columnExists('rimborsi', 'origine');
        $aid = (int) Db::val("SELECT account_id FROM rimborsi WHERE charge_id = ? AND charge_id <> '' ORDER BY id DESC", [$chargeId], 0)
            ?: (int) Db::val("SELECT id FROM accounts WHERE stripe_customer_id = ? AND stripe_customer_id <> ''", [(string) ($charge['customer'] ?? '')], 0);
        if (!$rimborsi && $cents > 0) $rimborsi = [['id' => '', 'amount' => $cents, 'status' => 'succeeded', 'created' => time(), 'metadata' => []]];
        $nuovi = 0; $confermati = 0; $perPartner = [];
        foreach ($rimborsi as $rf) {
            $rid = (string) ($rf['id'] ?? '');
            $stato = (string) ($rf['status'] ?? 'succeeded');
            if (in_array($stato, ['failed', 'canceled'], true)) continue;
            $perPartner[] = [$rid !== '' ? $rid : 'ch:' . $chargeId . ':' . (int) ($rf['amount'] ?? 0), (int) ($rf['amount'] ?? 0)];   // 9O: lo storno della commissione
            if ($invoice === '') $invoice = (string) ($rf['metadata']['invoice'] ?? '');
            // Il rimborso fatto dalla scheda del cliente (9E): c'è già, con l'id del rimborso; senza id (vecchie righe) per fattura e importo.
            $riga = $rid !== '' ? Db::one('SELECT * FROM rimborsi WHERE refund_id = ?', [$rid]) : null;
            $riga ??= Db::one("SELECT * FROM rimborsi WHERE refund_id = '' AND stato <> 'confermato' AND amount_cents = ? AND ((charge_id = ? AND charge_id <> '') OR (invoice_id = ? AND invoice_id <> '')) ORDER BY id",
                              [(int) ($rf['amount'] ?? 0), $chargeId, $invoice]);
            if ($riga) {
                $aid = $aid ?: (int) $riga['account_id'];
                if ($riga['stato'] !== 'confermato') {   // charge.refunded: Stripe l'ha fatto (anche se il rimborso è ancora «pending» verso la banca)
                    Db::update('rimborsi', ['stato' => 'confermato', 'confermato_at' => Support::now(), 'charge_id' => $chargeId ?: $riga['charge_id'], 'refund_id' => $rid ?: $riga['refund_id']], 'id = :id', ['id' => (int) $riga['id']]);
                    $confermati++;
                }
                continue;
            }
            if (!$aid) { Log::error('Rimborso da Stripe per un cliente sconosciuto', ['charge' => $chargeId, 'refund' => $rid]); continue; }
            // Partito da Stripe (dashboard) o da Adamo: lo dicono i metadati, se Adamo li scrive.
            $meta = mb_strtolower(json_encode($rf['metadata'] ?? [], JSON_UNESCAPED_UNICODE) . ' ' . (string) ($rf['description'] ?? ''));
            $origine = str_contains($meta, 'adamo') ? 'adamo' : 'stripe';
            $quando = !empty($rf['created']) ? gmdate('Y-m-d\TH:i:s\Z', (int) $rf['created']) : Support::now();
            $codice = $invoice !== '' ? Codici::pagamentoFattura($aid, $invoice) : '';
            $id = Db::insert('rimborsi', ['account_id' => $aid, 'invoice_id' => $invoice, 'charge_id' => $chargeId, 'refund_id' => $rid, 'amount_cents' => (int) ($rf['amount'] ?? 0),
                                          'motivo' => mb_substr((string) ($rf['reason'] ?? '') ?: $origine, 0, 40), 'testo' => '', 'stato' => 'confermato',
                                          'creato_da' => null, 'created_at' => $quando, 'confermato_at' => Support::now()]
                                         + (Migrator::columnExists('rimborsi', 'code') ? ['code' => $codice] : [])
                                         + ($nota ? ['origine' => $origine, 'nota_credito_at' => $origine === 'adamo' ? Support::now() : null] : []));
            $uid = (int) Db::val('SELECT user_id FROM accounts WHERE id = ?', [$aid], 0);
            Auth::audit('refund.received', $uid ?: null, ['rimborso' => $id, 'origine' => $origine, 'refund' => $rid, 'charge' => $chargeId, 'invoice' => $invoice,
                                                         'amount' => (int) ($rf['amount'] ?? 0), 'cliente' => Codici::cliente($aid), 'pagamento' => $codice]);
            $nuovi++;
        }
        $uid = $aid ? (int) Db::val('SELECT user_id FROM accounts WHERE id = ?', [$aid], 0) : 0;
        Auth::audit('refund.confirmed', $uid ?: null, ['charge' => $chargeId, 'invoice' => $invoice, 'amount_refunded' => $cents, 'nuovi' => $nuovi, 'confermati' => $confermati]);
        // Totale: l'abbonamento pagato da questo addebito (il periodo in corso) è rimborsato, la guida va offline.
        $totale = !empty($charge['refunded']) || ($cents > 0 && $cents >= (int) ($charge['amount'] ?? PHP_INT_MAX));
        // 9O · Il cliente di un partner: la commissione si storna (idempotente sull'id del rimborso).
        if ($aid) foreach ($perPartner as [$ridP, $centsP]) ProgrammaPartner::storna($aid, $ridP, $centsP, $totale);
        $chiuso = false;
        if ($totale && $aid) {
            $quando = (int) ($charge['created'] ?? time());
            foreach (Db::all("SELECT * FROM subscriptions WHERE account_id = ? AND provider = 'stripe' AND status <> 'refunded' ORDER BY id DESC", [$aid]) as $s) {
                $inizio = (int) strtotime((string) ($s['current_period_start'] ?: $s['created_at']));
                if (!Subscriptions::valid($s) || $quando < $inizio - 2 * 86400) continue;
                Db::update('subscriptions', ['status' => 'refunded', 'payment_status' => 'refunded', 'cancel_at_period_end' => 0, 'updated_at' => Support::now()], 'id = :sid', ['sid' => $s['id']]);
                Entitlements::forget($aid);
                Auth::audit('subscription.refunded', $uid ?: null, ['subscription' => (int) $s['id'], 'charge' => $chargeId, 'cliente' => Codici::cliente($aid)]);
                if ((string) $s['provider_subscription_id'] !== '') self::$daChiudere[] = (string) $s['provider_subscription_id'];
                $chiuso = true;
                break;
            }
        }
        // 9H · A noi, all'email amministrazione: la nota di credito si fa in Adamo (se non è partito da Adamo).
        $u = $uid ? (Db::one('SELECT name, email FROM users WHERE id = ?', [$uid]) ?: ['name' => '', 'email' => '']) : ['name' => '', 'email' => ''];
        Mailer::avvisaAmministrazione('Rimborso confermato da Stripe: ' . Support::money($cents, strtoupper((string) ($charge['currency'] ?? 'eur'))), [
            'Stripe ha confermato un rimborso' . ($totale ? ' totale' : ' parziale') . '. Ricordati la nota di credito in Adamo, se non l\'ha già fatta Adamo: nella scheda del cliente c\'è il promemoria finché non la segni come fatta.',
            'Cliente: ' . (trim((string) $u['name']) . ' · ' . (string) $u['email']) . ($aid ? ' (' . Codici::cliente($aid) . ')' : '') . '.',
            'Addebito: ' . ($chargeId ?: '—') . ($invoice !== '' ? ' · fattura ' . $invoice : '') . '.' . ($chiuso ? ' L\'abbonamento è chiuso e la guida è offline.' : '')],
            $aid ? ['Apri la scheda del cliente', Support::baseUrl() . '/admin/cliente/' . $aid] : null);
        return $chiuso ? 'rimborso-totale-abbonamento-chiuso' : ($nuovi ? 'rimborso-registrato' : 'rimborso-confermato');
    }

    /** 9O · charge.refund.updated: un rimborso fallito o annullato non vale più (in Cassa non compare); uno riuscito si conferma. */
    private static function onRefundUpdated(array $rf): string
    {
        if (!Migrator::tableExists('rimborsi') || empty($rf['id'])) return 'ignorato';
        $riga = Db::one('SELECT * FROM rimborsi WHERE refund_id = ?', [(string) $rf['id']]);
        if (!$riga) return 'rimborso-non-ancora-noto';
        $stato = (string) ($rf['status'] ?? '');
        if (in_array($stato, ['failed', 'canceled'], true) && $riga['stato'] !== 'fallito') {
            Db::update('rimborsi', ['stato' => 'fallito'], 'id = :id', ['id' => (int) $riga['id']]);
            Auth::audit('refund.failed', null, ['rimborso' => (int) $riga['id'], 'refund' => (string) $rf['id'], 'cliente' => Codici::cliente((int) $riga['account_id'])]);
            return 'rimborso-fallito';
        }
        if ($stato === 'succeeded' && $riga['stato'] !== 'confermato') {
            Db::update('rimborsi', ['stato' => 'confermato', 'confermato_at' => Support::now()], 'id = :id', ['id' => (int) $riga['id']]);
            return 'rimborso-confermato';
        }
        return 'rimborso-invariato';
    }

    /** A transazione chiusa: l'abbonamento rimborsato per intero si chiude subito su Stripe (niente rinnovo). Un errore resta nei log. */
    private static function dopoRimborso(): void
    {
        $lista = self::$daChiudere; self::$daChiudere = [];
        foreach (array_unique($lista) as $sid) {
            try { Stripe::call('DELETE', 'subscriptions/' . rawurlencode($sid), ['prorate' => 'false', 'invoice_now' => 'false']); }
            catch (\Throwable $e) { Log::exception($e, 'chiusura su Stripe dell\'abbonamento rimborsato ' . $sid); }
        }
    }

    private static function onSubscriptionDeleted(array $sub): string
    {
        $row = Db::one('SELECT * FROM subscriptions WHERE provider_subscription_id = ?', [(string) ($sub['id'] ?? '')]);
        if (!$row) return 'abbonamento-non-ancora-noto';
        // La guida non si cancella e i dati restano: va solo offline, perché
        // Subscriptions::valid non la considera più pagata. 9O: un abbonamento rimborsato resta «rimborsato».
        if ($row['status'] !== 'refunded') Db::update('subscriptions', ['status' => 'canceled', 'updated_at' => Support::now()], 'id = :sid', ['sid' => $row['id']]);
        Entitlements::forget((int) $row['account_id']);
        return 'abbonamento-chiuso';
    }

    /**
     * 9N · I dati di un bonifico scritti nell'attivazione manuale: imponibile e IVA in euro («127,00»), data (AAAA-MM-GG,
     * non nel futuro), riferimento (CRO o causale). @return array{imponibile:int,iva:int,data:string,riferimento:string}
     */
    public static function datiBonifico(array $in): array
    {
        $euro = function (string $campo, string $nome, bool $zero) use ($in): int {
            $g = str_replace([' ', '€'], '', trim((string) ($in[$campo] ?? '')));
            if (preg_match('/^\d{1,3}(\.\d{3})+(,\d{1,2})?$/', $g)) $g = str_replace('.', '', $g);
            $g = str_replace(',', '.', $g);
            if ($g === '' || !is_numeric($g) || (float) $g < 0 || (!$zero && (float) $g <= 0)) throw new \RuntimeException("Scrivi $nome in euro, per esempio 127,00.");
            return (int) round((float) $g * 100);
        };
        $imponibile = $euro('bonifico_importo', "l'importo del bonifico (IVA esclusa)", false);
        $iva = $euro('bonifico_iva', "l'IVA del bonifico (0 se non c'è)", true);
        $data = trim((string) ($in['bonifico_data'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) || !checkdate((int) substr($data, 5, 2), (int) substr($data, 8, 2), (int) substr($data, 0, 4)) || $data > gmdate('Y-m-d')) {
            throw new \RuntimeException('Scrivi la data in cui è arrivato il bonifico (non nel futuro).');
        }
        $rif = mb_substr(trim((string) ($in['bonifico_riferimento'] ?? '')), 0, 120);
        if ($rif === '') throw new \RuntimeException('Scrivi il riferimento del bonifico (CRO o causale): serve per ritrovarlo in banca.');
        return ['imponibile' => $imponibile, 'iva' => $iva, 'data' => $data, 'riferimento' => $rif];
    }

    /**
     * 9N · Il bonifico diventa un pagamento: una riga in orders (provider «bonifico», pagata, alla data del bonifico) con il
     * suo codice MW-XXXXX-AAAA-N; l'abbonamento manuale appena creato lo segna come pagato con bonifico. @return string il codice
     */
    public static function segnaBonifico(int $accountId, int $subscriptionId, array $b, string $nota): string
    {
        $sub = Db::one('SELECT * FROM subscriptions WHERE id = ?', [$subscriptionId]) ?: [];
        $quando = $b['data'] . 'T12:00:00Z';
        $riga = ['account_id' => $accountId, 'package_version_id' => (int) ($sub['package_version_id'] ?? 0), 'amount_cents' => $b['imponibile'], 'currency' => 'EUR',
                 'status' => 'paid', 'provider' => 'bonifico', 'provider_session_id' => '', 'created_at' => $quando, 'updated_at' => Support::now(),
                 'quantity' => max(1, (int) ($sub['quantity'] ?? 1)), 'kind' => 'new'];
        if (Migrator::columnExists('orders', 'iva_cents')) $riga += ['iva_cents' => $b['iva'], 'riferimento' => $b['riferimento']];
        $orderId = Db::insert('orders', $riga);
        Db::update('subscriptions', ['payment_status' => 'bonifico'], 'id = :id', ['id' => $subscriptionId]);
        $codice = Codici::pagamento($orderId);
        $uid = (int) Db::val('SELECT user_id FROM accounts WHERE id = ?', [$accountId]);
        Auth::audit('payment.bank_transfer', $uid ?: null, ['order' => $orderId, 'codice' => $codice, 'imponibile' => $b['imponibile'], 'iva' => $b['iva'],
                                                            'data' => $b['data'], 'riferimento' => $b['riferimento'], 'subscription' => $subscriptionId, 'nota' => $nota]);
        ProgrammaPartner::matura($accountId, 'ordine:' . $orderId, (int) $b['imponibile'], $codice);   // 9O: anche il bonifico porta la commissione
        try { ProgrammaPartner::dopoEvento(); } catch (\Throwable) { }
        return $codice;
    }

    /** 7F: «Gratis per sempre» = un abbonamento manuale di 100 anni. */
    public const PER_SEMPRE = 1200;

    /**
     * Un abbonamento concesso a mano dall'amministratore: per demo, omaggi,
     * pagamenti arrivati per altre vie. Resta scritto nel registro.
     */
    public static function grantManual(int $accountId, int $packageVersionId, int $months, string $note, int $quantity = 1): int
    {
        $pv = Db::one('SELECT * FROM package_versions WHERE id = ?', [$packageVersionId]) ?: [];
        $quantity = Plans::quantity($pv, $quantity) ?? (int) ($pv['min_quantity'] ?? 1);
        return Db::tx(function () use ($accountId, $packageVersionId, $months, $note, $quantity) {
            Db::run('UPDATE subscriptions SET status = ?, updated_at = ? WHERE account_id = ? AND status IN (?, ?)',
                    ['replaced', Support::now(), $accountId, 'active', 'trialing']);
            $id = Db::insert('subscriptions', [
                'account_id' => $accountId, 'package_version_id' => $packageVersionId, 'status' => 'active',
                'provider' => 'manuale', 'current_period_start' => Support::now(),
                'current_period_end' => gmdate('Y-m-d\TH:i:s\Z', strtotime('+' . max(1, $months) . ' months')),
                'payment_status' => 'manuale', 'created_at' => Support::now(), 'updated_at' => Support::now(),
                'quantity' => $quantity,
            ]);
            Db::update('accounts', ['intended_package_version_id' => $packageVersionId, 'intended_quantity' => $quantity], 'id = :aid', ['aid' => $accountId]);
            $uid = (int) Db::val('SELECT user_id FROM accounts WHERE id = ?', [$accountId]);
            Auth::audit('subscription.manual', $uid, ['package_version_id' => $packageVersionId, 'months' => $months, 'note' => $note, 'quantity' => $quantity]);
            Entitlements::forget($accountId);
            return $id;
        });
    }
}
