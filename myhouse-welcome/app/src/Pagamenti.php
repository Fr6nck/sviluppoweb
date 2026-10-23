<?php
namespace MHW;

/**
 * 9O · Quello che il cliente vede dei suoi pagamenti.
 *
 * - L'email «La tua guida {struttura} è online» quando il pagamento di un abbonamento nuovo è confermato (carta o addebito
 *   SEPA), una volta per ordine (orders.online_email_at). Se la guida non era ancora pronta: «quando pubblichi va online subito».
 * - L'addebito SEPA «in verifica» (orders.in_verifica_at): il riquadro nel pannello, nella guida e in Account (Testi e foto →
 *   pagamento.verifica), e l'email quando la banca rifiuta l'addebito, con «Paga con carta» (il Checkout dopo un addebito
 *   rifiutato propone solo la carta).
 * - «Pagamenti e fatture» in Account: i pagamenti dell'account con codice, piano, imponibile, IVA, totale, stato e metodo.
 *
 * Queste email non passano dai richiami (Richiami::manda): sono di servizio e partono anche ai clienti di prova; mai alla vetrina.
 */
final class Pagamenti
{
    /** Giorni in cui, dopo un addebito SEPA rifiutato, il pagamento si propone solo con la carta. */
    public const GIORNI_SOLO_CARTA = 30;

    private static function utente(int $accountId): ?array
    {
        $u = Db::one("SELECT u.* FROM accounts a JOIN users u ON u.id = a.user_id WHERE a.id = ?", [$accountId]);
        if (!$u || str_ends_with(mb_strtolower((string) $u['email']), '@' . Demo::DOMINIO)) return null;
        $vetrina = mb_strtolower(trim((string) Config::get('vetrina_email', '')));
        return $vetrina !== '' && mb_strtolower(trim((string) $u['email'])) === $vetrina ? null : $u;
    }

    private static function giorno(string $iso, bool $en): string { return $en && ($t = strtotime($iso)) ? gmdate('j F Y', $t) : Support::date($iso); }

    /** Il pagamento SEPA in verifica dell'account (l'ordine «awaiting» più recente), o null. */
    public static function inVerifica(int $accountId): ?array
    {
        if (!Migrator::columnExists('orders', 'in_verifica_at')) return null;
        return Db::one("SELECT * FROM orders WHERE account_id = ? AND status = 'awaiting' AND in_verifica_at IS NOT NULL ORDER BY id DESC", [$accountId]);
    }

    /** Dopo un addebito SEPA rifiutato (ultimi GIORNI_SOLO_CARTA giorni) il Checkout propone solo la carta. */
    public static function soloCarta(int $accountId): bool
    {
        if (!Migrator::columnExists('orders', 'in_verifica_at')) return false;
        return (bool) Db::val("SELECT id FROM orders WHERE account_id = ? AND status = 'failed' AND in_verifica_at IS NOT NULL AND updated_at >= ?",
                              [$accountId, gmdate('Y-m-d\TH:i:s\Z', time() - self::GIORNI_SOLO_CARTA * 86400)]);
    }

    /** Il testo del riquadro «Pagamento in verifica» (Testi e foto, IT/EN), in HTML sicuro. */
    public static function riquadroVerifica(bool $en): string
    {
        return TestiSito::formatta(TestiSito::get('pagamento.verifica', $en ? 'en' : 'it'));
    }

    /**
     * L'email «La tua guida è online» per un ordine nuovo pagato (carta o SEPA). Una volta per ordine; non solleva mai.
     * @return bool partita
     */
    public static function emailOnline(int $orderId): bool
    {
        try {
            if (!Migrator::columnExists('orders', 'online_email_at')) return false;
            $o = Db::one('SELECT * FROM orders WHERE id = ?', [$orderId]);
            if (!$o || $o['status'] !== 'paid' || ($o['kind'] ?? 'new') !== 'new' || !empty($o['online_email_at'])) return false;
            $aid = (int) $o['account_id'];
            $u = self::utente($aid);
            if (!$u) return false;
            $s = Db::one("SELECT * FROM subscriptions WHERE account_id = ? AND status IN ('active','trialing') ORDER BY id DESC", [$aid]);
            $pv = Plans::version((int) $o['package_version_id']);
            $piano = (string) (Db::val('SELECT name FROM packages WHERE id = ?', [(int) ($pv['package_id'] ?? 0)]) ?? '');
            $n = (int) ($s['quantity'] ?? $o['quantity'] ?? 1);
            $p = !empty($o['property_id']) ? Db::one('SELECT * FROM properties WHERE id = ? AND account_id = ?', [(int) $o['property_id'], $aid])
                                           : Db::one('SELECT * FROM properties WHERE account_id = ? AND is_demo = 0 ORDER BY id', [$aid]);
            $online = $p && $p['status'] === 'published';
            $lingua = Pannello::linguaDi($u, $aid); $en = $lingua === 'en';
            $struttura = $p ? (string) $p['name'] : ($en ? 'your property' : 'la tua struttura');
            $fine = $s ? self::giorno((string) $s['current_period_end'], $en) : '';
            // 9R · «Portfolio · 3 strutture · 227 € + IVA» (il prezzo annuale del piano per il numero pagato) e la scomposizione.
            $prezzoP = $pv ? ' · ' . Support::money(Plans::price($pv, max(1, $n)), (string) $pv['currency']) . ($en ? ' + VAT' : ' + IVA') : '';
            $scompP = $pv && $n > 1 ? ' (' . Plans::scomposizione($pv, $n, $en) . ')' : '';
            $pianoRiga = $en ? 'Plan ' . $piano . ($n > 1 ? " · $n properties" : '') . $prezzoP . $scompP . ($fine !== '' ? " · valid until $fine" : '') . ' · it renews automatically, and we remind you 30 and 7 days before.'
                             : 'Piano ' . $piano . ($n > 1 ? " · $n strutture" : '') . $prezzoP . $scompP . ($fine !== '' ? " · valido fino al $fine" : '') . ' · si rinnova da solo, ti avvisiamo 30 e 7 giorni prima.';
            // Con un codice omaggio (9D) oggi non si paga: il primo addebito è alla fine dei mesi gratis.
            $omaggio = $s && $s['status'] === 'trialing' && !empty($s['omaggio_fino']);
            if ($omaggio) $pianoRiga = $en ? 'Plan ' . $piano . ($n > 1 ? ", $n properties" : '') . " · free until $fine, then it renews automatically: we remind you 30 and 7 days before the first charge."
                                           : 'Piano ' . $piano . ($n > 1 ? ", $n strutture" : '') . " · gratis fino al $fine, poi si rinnova da solo: ti avvisiamo 30 e 7 giorni prima del primo addebito.";
            $par = [
                $omaggio ? ($en ? "Your gift code is active" . ($online ? " and the guide for $struttura is published: your guests can already open it from the QR code and the link." : ': when you publish the guide it goes online straight away.')
                                : 'Il codice omaggio è attivo' . ($online ? " e la guida di $struttura è pubblicata: i tuoi ospiti possono già aprirla dal QR e dal link." : ': quando pubblichi la guida va online subito.')) :
                ($online ? ($en ? "The payment went through and the guide for $struttura is published: your guests can already open it from the QR code and the link."
                               : "Il pagamento è andato a buon fine e la guida di $struttura è pubblicata: i tuoi ospiti possono già aprirla dal QR e dal link.")
                        : ($en ? 'The payment went through: when you publish the guide it goes online straight away.'
                               : 'Il pagamento è andato a buon fine: quando pubblichi la guida va online subito.')),
                $pianoRiga,
                $en ? 'The invoice reaches you by email from Adamo, with a courtesy PDF copy; the original is in your tax drawer (cassetto fiscale) or at your recipient code / PEC.'
                    : 'La fattura ti arriva per email da Adamo, con la copia di cortesia in PDF; l\'originale è nel tuo cassetto fiscale (o al tuo codice destinatario/PEC).',
            ];
            $acc = Db::one('SELECT * FROM accounts WHERE id = ?', [$aid]) ?: [];
            // La prima guida dell'account va online con questo pagamento: dentro c'è anche il benvenuto (le tre cose da fare e la
            // riga del programma), che così non parte una seconda volta.
            $benvenuto = $online ? Benvenuto::dovuto($aid) : null;
            if ($benvenuto) {
                array_splice($par, 2, 0, [Benvenuto::coseDaFare($lingua)]);
                $riga = Benvenuto::rigaGiudizio($aid, $lingua);
                if ($riga !== '') $par[] = $riga;
            } elseif (Giudizi::disponibili() && Giudizi::puoAderire($acc) && empty($acc['giudizio_adesione'])) {
                $par[] = $en ? 'Would you like your guide among “Our hosts’ guides”? Join the “Review with a discount” programme from Settings.'
                             : 'Vuoi la tua guida tra «Le guide dei nostri host»? Aderisci al programma «Giudizio con sconto» da Impostazioni.';
            }
            $scelte = $online ? [[$en ? 'Open QR & Link' : 'Apri QR & Link', Support::baseUrl() . '/pannello/' . (int) $p['id'] . '/qr']] : [];
            $bottone = $online ? [$en ? 'Open your guide' : 'Apri la tua guida', Support::baseUrl() . '/g/' . $p['slug']]
                               : [$en ? 'Go to your dashboard' : 'Vai al pannello', Support::baseUrl() . '/pannello'];
            $codice = Codici::cliente($aid);
            $nota = ($codice !== '' ? ($en ? 'Customer code ' : 'Codice cliente ') . $codice . ' · ' : '') . ($en ? 'for any question: ' : 'per qualsiasi domanda: ') . Support::contatto();
            $oggetto = $online ? ($en ? "Your guide $struttura is online" : "La tua guida $struttura è online")
                               : ($omaggio ? ($en ? 'Your gift code is active' : 'Il tuo codice omaggio è attivo') : ($en ? 'Payment confirmed' : 'Pagamento confermato'));
            $titolo = $online ? ($en ? 'It is online.' : 'È online.') : ($omaggio ? ($en ? 'All set.' : 'Tutto pronto.') : ($en ? 'Payment confirmed.' : 'Pagamento confermato.'));
            // Reply-To all'assistenza (la nota dice «per qualsiasi domanda»): non è un avviso di addebito.
            $ok = (bool) Mailer::inLingua($lingua, fn() => Mailer::send((string) $u['email'], $oggetto . ' — MyHouse Welcome',
                Mailer::testo($titolo, $par, $bottone, $nota, null, $scelte), Mailer::layout($titolo, $par, $bottone, $nota, null, $scelte)));
            if ($ok) {
                Db::update('orders', ['online_email_at' => Support::now()], 'id = :oid', ['oid' => $orderId]);
                if ($benvenuto) Benvenuto::segna($aid);
            }
            return $ok;
        } catch (\Throwable $e) {
            Log::exception($e, 'email «La tua guida è online»');
            return false;
        }
    }

    /** Il giro dei richiami: gli ordini nuovi pagati negli ultimi 2 giorni ancora senza l'email (posta giù al primo tentativo). */
    public static function recenti(?int $t = null): int
    {
        if (!Migrator::columnExists('orders', 'online_email_at')) return 0;
        $n = 0;
        foreach (Db::all("SELECT id FROM orders WHERE status = 'paid' AND provider = 'stripe' AND kind = 'new' AND online_email_at IS NULL AND updated_at >= ?",
                         [gmdate('Y-m-d\TH:i:s\Z', ($t ?? time()) - 2 * 86400)]) as $o) {
            if (self::emailOnline((int) $o['id'])) $n++;
        }
        return $n;
    }

    /** L'addebito SEPA rifiutato dalla banca: l'email con «Paga con carta». Non solleva mai. */
    public static function emailSepaFallito(int $orderId): bool
    {
        try {
            $o = Db::one('SELECT * FROM orders WHERE id = ?', [$orderId]);
            if (!$o) return false;
            $aid = (int) $o['account_id'];
            $u = self::utente($aid);
            if (!$u) return false;
            $lingua = Pannello::linguaDi($u, $aid); $en = $lingua === 'en';
            $dove = !empty($o['property_id']) ? '/pannello/' . (int) $o['property_id'] . '/procedura/pubblica' : '/pannello';
            $par = $en ? ['Your bank did not confirm the direct debit for MyHouse Welcome, so the payment did not go through and the guide is not online yet.',
                          'You can pay by card now: it takes a minute and the guide goes online as soon as the payment is confirmed. Nothing has been charged.']
                       : ['La tua banca non ha confermato l\'addebito sul conto per MyHouse Welcome: il pagamento non è andato a buon fine e la guida non è ancora online.',
                          'Puoi pagare adesso con la carta: ci vuole un minuto e la guida va online appena il pagamento è confermato. Non ti è stato addebitato nulla.'];
            $codice = Codici::cliente($aid);
            $nota = ($codice !== '' ? ($en ? 'Customer code ' : 'Codice cliente ') . $codice . ' · ' : '') . Support::fraseFatture($en);
            return (bool) Mailer::diPagamento(fn() => Mailer::inLingua($lingua, fn() => Mailer::manda((string) $u['email'],
                $en ? 'Payment not completed' : 'Pagamento non riuscito', $en ? 'The direct debit did not go through.' : 'L\'addebito non è andato a buon fine.',
                $par, [$en ? 'Pay by card' : 'Paga con carta', Support::baseUrl() . $dove], $nota)));
        } catch (\Throwable $e) {
            Log::exception($e, 'email addebito SEPA non riuscito');
            return false;
        }
    }

    /**
     * «Pagamenti e fatture»: i pagamenti dell'account (ordini pagati, in verifica o non riusciti; rinnovi dai webhook; rimborsi),
     * dal più recente. Ogni riga: data, codice, piano, imponibile, iva (null se non nota), totale, stato, metodo.
     */
    public static function elenco(int $accountId): array
    {
        $out = [];
        $metodi = [];
        // Il metodo e l'IVA dei pagamenti Stripe: dalla sessione di Checkout (payment_method_types, total_details.amount_tax).
        foreach (Db::all("SELECT kind, payload FROM webhook_events WHERE provider = 'stripe' AND kind IN ('checkout.session.completed','checkout.session.async_payment_succeeded')") as $r) {
            $s = (json_decode((string) $r['payload'], true) ?: [])['data']['object'] ?? [];
            if ((string) ($s['metadata']['account_id'] ?? '') !== (string) $accountId) continue;
            $oid = (int) ($s['metadata']['order_id'] ?? 0);
            $metodi[$oid] = ['iva' => isset($s['total_details']['amount_tax']) ? (int) $s['total_details']['amount_tax'] : ($metodi[$oid]['iva'] ?? null),
                             'sconto' => isset($s['total_details']['amount_discount']) ? (int) $s['total_details']['amount_discount'] : ($metodi[$oid]['sconto'] ?? null),
                             'sepa' => $r['kind'] === 'checkout.session.async_payment_succeeded' || !empty($metodi[$oid]['sepa'])];
        }
        $conIva = Migrator::columnExists('orders', 'iva_cents');
        foreach (Db::all("SELECT o.*, pk.name AS piano FROM orders o JOIN package_versions pv ON pv.id = o.package_version_id JOIN packages pk ON pk.id = pv.package_id
                          WHERE o.account_id = ? AND o.provider IN ('stripe','bonifico') AND o.status IN ('paid','awaiting','failed') ORDER BY o.created_at DESC, o.id DESC", [$accountId]) as $o) {
            if ($o['status'] === 'failed' && empty($o['in_verifica_at'])) continue;   // un Checkout chiuso senza pagare non è un pagamento
            $iva = $conIva && $o['iva_cents'] !== null ? (int) $o['iva_cents'] : ($metodi[(int) $o['id']]['iva'] ?? null);
            $netto = max(0, (int) $o['amount_cents'] - (int) ($metodi[(int) $o['id']]['sconto'] ?? $o['discount_cents'] ?? 0));   // l'imponibile dopo lo sconto
            $sepa = !empty($o['in_verifica_at']) || !empty($metodi[(int) $o['id']]['sepa']);
            $out[] = ['data' => (string) $o['created_at'], 'codice' => (string) ($o['code'] ?? '') ?: Codici::pagamento((int) $o['id']),
                      'piano' => (string) $o['piano'] . ((int) $o['quantity'] > 1 ? ' · ' . (int) $o['quantity'] : ''), 'tipo' => ($o['kind'] ?? 'new') === 'change' ? 'cambio' : 'nuovo',
                      'imponibile' => $netto, 'iva' => $iva, 'totale' => $iva !== null ? $netto + $iva : null,
                      'stato' => ['paid' => 'pagato', 'awaiting' => 'in verifica', 'failed' => 'non riuscito'][$o['status']],
                      'metodo' => $o['provider'] === 'bonifico' ? 'bonifico' : ($sepa ? 'sepa' : 'carta')];
        }
        // I rinnovi: dalle fatture pagate arrivate col webhook (come la Cassa).
        $subs = array_column(Db::all("SELECT provider_subscription_id FROM subscriptions WHERE account_id = ? AND provider_subscription_id <> ''", [$accountId]), 'provider_subscription_id');
        if ($subs) {
            foreach (Db::all("SELECT processed_at, payload FROM webhook_events WHERE provider = 'stripe' AND kind = 'invoice.paid' ORDER BY processed_at DESC") as $r) {
                $inv = (json_decode((string) $r['payload'], true) ?: [])['data']['object'] ?? [];
                $motivo = (string) ($inv['billing_reason'] ?? '');   // i rinnovi e i conguagli pagati (strutture in più durante l'anno)
                if ($motivo !== 'subscription_cycle' && !($motivo === 'subscription_update' && (int) ($inv['amount_paid'] ?? 0) > 0)) continue;
                $sid = (string) ($inv['subscription'] ?? $inv['parent']['subscription_details']['subscription'] ?? '');
                if (!in_array($sid, $subs, true)) continue;
                $netto = (int) ($inv['total_excluding_tax'] ?? $inv['subtotal'] ?? 0);
                $totale = (int) ($inv['total'] ?? $inv['amount_paid'] ?? $netto);
                $out[] = ['data' => (string) $r['processed_at'], 'codice' => Codici::perFattura((string) ($inv['id'] ?? '')), 'piano' => '', 'tipo' => $motivo === 'subscription_update' ? 'cambio' : 'rinnovo',
                          'imponibile' => $netto, 'iva' => isset($inv['tax']) ? (int) $inv['tax'] : $totale - $netto, 'totale' => $netto + (isset($inv['tax']) ? (int) $inv['tax'] : $totale - $netto),
                          'stato' => 'pagato', 'metodo' => ''];
            }
        }
        if (Migrator::tableExists('rimborsi')) {
            foreach (Db::all("SELECT * FROM rimborsi WHERE account_id = ? AND stato <> 'fallito'", [$accountId]) as $rb) {
                $out[] = ['data' => (string) $rb['created_at'], 'codice' => (string) ($rb['code'] ?? ''), 'piano' => '', 'tipo' => 'rimborso', 'imponibile' => null, 'iva' => null,
                          'totale' => -(int) $rb['amount_cents'], 'stato' => $rb['stato'] === 'confermato' ? 'rimborsato' : 'rimborso in corso', 'metodo' => ''];
            }
        }
        usort($out, fn($a, $b) => strcmp($b['data'], $a['data']));
        return $out;
    }
}
