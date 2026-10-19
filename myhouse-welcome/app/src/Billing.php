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
        if ($type === 'checkout.session.completed' && !empty($obj['subscription']) && is_string($obj['subscription'])) {
            $sub = Stripe::retrieveSubscription($obj['subscription']);
        }
        // Cambio di piano pagato (6H): Stripe passa alle voci nuove prima della transazione.
        $cambio = ['ok' => false, 'sub' => null];
        if (in_array($type, ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true) && ($obj['mode'] ?? '') === 'payment'
            && ($obj['metadata']['kind'] ?? '') === 'change' && in_array($obj['payment_status'] ?? '', ['paid', 'no_payment_required'], true)) {
            $cambio = CambioAbbonamento::preparaStripe((int) ($obj['metadata']['order_id'] ?? 0));
        }

        try {
            $esito = Db::tx(function () use ($id, $type, $obj, $event, $sub, $cambio) {
                Db::insert('webhook_events', [
                    'provider' => 'stripe', 'provider_event_id' => $id, 'kind' => $type,
                    'payload' => json_encode($event, JSON_UNESCAPED_UNICODE), 'processed_at' => Support::now(),
                ]);
                return match ($type) {
                    'checkout.session.completed' => ($obj['mode'] ?? '') === 'payment' ? self::onChangePaid($obj, $cambio) : self::onCheckoutCompleted($obj, $sub),
                    'checkout.session.async_payment_succeeded' => self::onChangePaid($obj, $cambio),
                    'checkout.session.async_payment_failed' => self::onChangeFailed($obj),
                    'checkout.session.expired' => self::onCheckoutExpired($obj),
                    'invoice.paid', 'invoice.payment_succeeded' => self::onInvoicePaid($obj),
                    'invoice.payment_failed' => self::onInvoiceFailed($obj),
                    'customer.subscription.created', 'customer.subscription.updated' => self::onSubscriptionChanged($obj),
                    'customer.subscription.deleted' => self::onSubscriptionDeleted($obj),
                    'customer.subscription.trial_will_end' => self::onTrialWillEnd($obj),   // 9D: solo nel registro (gli avvisi li mandiamo noi)
                    'charge.refunded' => self::onChargeRefunded($obj),                        // 9E: il rimborso fatto dall'amministrazione
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
        // Pagamenti differiti (bonifico): si aspetta invoice.paid.
        if (!in_array($s['payment_status'] ?? '', ['paid', 'no_payment_required'], true)) {
            Db::update('orders', ['status' => 'awaiting', 'updated_at' => Support::now()], 'id = :oid', ['oid' => $orderId]);
            return 'in-attesa-del-pagamento';
        }
        // Lo sconto davvero concesso lo dice la sessione (total_details.amount_discount).
        $sconto = isset($s['total_details']['amount_discount']) ? (int) $s['total_details']['amount_discount'] : null;
        self::activate($o, $sub ?? ['id' => $s['subscription'] ?? '', 'status' => 'active'], (string) ($s['customer'] ?? ''), $sconto);
        return 'abbonamento-attivato';
    }

    /** Ordine pagato → abbonamento attivo → guida pubblicata. Tutto o niente. $sconto: lo sconto del primo anno secondo Stripe. */
    public static function activate(array $order, array $sub, string $customerId, ?int $sconto = null): void
    {
        if ($order['status'] === 'paid') return;
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
        if ($esistente) Db::update('subscriptions', $riga, 'id = :sid', ['sid' => $esistente['id']]);
        else Db::insert('subscriptions', $riga + ['created_at' => Support::now()]);

        Db::update('accounts', ['intended_package_version_id' => $order['package_version_id'], 'intended_quantity' => $quantita]
                   + ($customerId !== '' ? ['stripe_customer_id' => $customerId] : []), 'id = :aid', ['aid' => $acc]);
        Db::run('UPDATE package_versions SET sold_count = sold_count + 1 WHERE id = ?', [$order['package_version_id']]);
        Sconti::registra($order, $sconto);   // un utilizzo per ordine, nella stessa transazione
        Inviti::pagato($order);              // se è un amico invitato, l'invito diventa valido
        Giudizi::pagato($order);             // 9B: lo sconto del giudizio sul primo anno è stato applicato
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
        return CambioAbbonamento::pagato($o, $cambio);
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
            'status' => 'active', 'payment_status' => 'paid',
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
        Giudizi::fattura($row, $inv);        // 9B: un rinnovo scontato chiude lo sconto del giudizio promesso sul rinnovo
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
        if (Plans::perProperty($pv) && !$programmato) {
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
            'status' => (string) ($sub['status'] ?? $row['status']),
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
    private static function onChargeRefunded(array $charge): string
    {
        if (!Migrator::tableExists('rimborsi')) return 'ignorato';
        $cents = (int) ($charge['amount_refunded'] ?? 0);
        $invoice = (string) ($charge['invoice'] ?? '');
        $riga = Db::one("SELECT * FROM rimborsi WHERE (charge_id = ? OR (invoice_id = ? AND invoice_id <> '')) AND stato <> 'confermato' ORDER BY id DESC", [(string) ($charge['id'] ?? ''), $invoice]);
        $aid = $riga ? (int) $riga['account_id'] : (int) Db::val('SELECT id FROM accounts WHERE stripe_customer_id = ?', [(string) ($charge['customer'] ?? '')], 0);
        if ($riga) Db::update('rimborsi', ['stato' => 'confermato', 'confermato_at' => Support::now(), 'charge_id' => (string) ($charge['id'] ?? $riga['charge_id'])], 'id = :id', ['id' => (int) $riga['id']]);
        $uid = $aid ? (int) Db::val('SELECT user_id FROM accounts WHERE id = ?', [$aid], 0) : 0;
        Auth::audit('refund.confirmed', $uid ?: null, ['charge' => (string) ($charge['id'] ?? ''), 'invoice' => $invoice, 'amount_refunded' => $cents]);
        // 9H · A noi, all'email amministrazione: la nota di credito si fa in Adamo.
        $u = $uid ? (Db::one('SELECT name, email FROM users WHERE id = ?', [$uid]) ?: ['name' => '', 'email' => '']) : ['name' => '', 'email' => ''];
        Mailer::avvisaAmministrazione('Rimborso confermato da Stripe: ' . Support::money($cents, strtoupper((string) ($charge['currency'] ?? 'eur'))), [
            'Stripe ha confermato un rimborso. Ricordati la nota di credito in Adamo.',
            'Cliente: ' . (trim((string) $u['name']) . ' · ' . (string) $u['email']) . ($aid ? ' (account ' . $aid . ')' : '') . '.',
            'Addebito: ' . (string) ($charge['id'] ?? '—') . ($invoice !== '' ? ' · fattura ' . $invoice : '') . '.'],
            $aid ? ['Apri la scheda del cliente', Support::baseUrl() . '/admin/cliente/' . $aid] : null);
        return 'rimborso-confermato';
    }

    private static function onSubscriptionDeleted(array $sub): string
    {
        $row = Db::one('SELECT * FROM subscriptions WHERE provider_subscription_id = ?', [(string) ($sub['id'] ?? '')]);
        if (!$row) return 'abbonamento-non-ancora-noto';
        // La guida non si cancella e i dati restano: va solo offline, perché
        // Subscriptions::valid non la considera più pagata.
        Db::update('subscriptions', ['status' => 'canceled', 'updated_at' => Support::now()], 'id = :sid', ['sid' => $row['id']]);
        Entitlements::forget((int) $row['account_id']);
        return 'abbonamento-chiuso';
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
