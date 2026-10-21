<?php
namespace MHW;

/**
 * 9N · 10 bis · Le offerte su misura oltre il massimo del Portfolio (oltre 10 strutture).
 *
 * L'amministrazione prepara l'offerta dal preventivo: strutture, prezzo annuo totale, scadenza, nota, sconto sul rinnovo
 * deciso a mano. Il cliente riceve un link (/offerta/{token}), accetta e paga su Stripe un abbonamento annuale con UN
 * prezzo creato solo per lui (mai nel listino). Al rinnovo vale lo stesso prezzo. Inviti e «Giudizio con sconto» non si
 * applicano da soli: l'amministrazione li vede come promemoria e decide lo sconto del prossimo rinnovo (Sconti::rinnovo).
 *
 * Per un cliente che ha già un'offerta (più strutture dopo): la nuova offerta sostituisce la vecchia subito, con il
 * conguaglio dei giorni che restano (un pagamento a parte, come il cambio di piano della 6H), oppure dal rinnovo.
 */
final class Offerte
{
    public const STATI = ['inviata' => 'Inviata', 'accettata' => 'Accettata', 'scaduta' => 'Scaduta', 'annullata' => 'Annullata'];
    public const MODI = ['nuovo' => 'Nuovo abbonamento', 'subito' => 'Subito, con il conguaglio', 'rinnovo' => 'Dal prossimo rinnovo'];
    /** Lo sconto sul prossimo rinnovo si cambia fino a 7 giorni prima. */
    public const GIORNI_SCONTO = 7;

    public static function disponibili(): bool { return Migrator::tableExists('offerte'); }

    /** La versione del Portfolio in vendita: la base dell'offerta (funzioni e limiti, il prezzo no). */
    public static function versione(): ?array
    {
        foreach (Plans::public() as $o) if (Plans::perProperty($o)) return Plans::currentVersion((int) $o['id']) ?? $o;
        return null;
    }

    /** Da quante strutture si fa un'offerta: una in più del massimo del Portfolio in vendita (11). */
    public static function minimo(): int { return Preventivi::daQuante(); }

    /** Il prezzo di listino per confronto (a scaglioni, come se il Portfolio arrivasse fin lì). */
    public static function listino(int $strutture): int
    {
        $pv = self::versione();
        return $pv ? Plans::price($pv, max(1, $strutture)) : 0;
    }

    /** «640,00» o «1.250» → centesimi; null se non è un importo. */
    public static function euro(string $s): ?int
    {
        $g = str_replace([' ', '€', "\u{00A0}"], '', trim($s));
        if (preg_match('/^\d{1,3}(\.\d{3})+(,\d{1,2})?$/', $g)) $g = str_replace('.', '', $g);
        $g = str_replace(',', '.', $g);
        return $g !== '' && is_numeric($g) && (float) $g >= 0 ? (int) round((float) $g * 100) : null;
    }

    public static function riga(int $id): ?array { return self::disponibili() ? Db::one('SELECT * FROM offerte WHERE id = ?', [$id]) : null; }

    public static function perToken(string $token): ?array
    {
        if (!self::disponibili() || !preg_match('/^[A-Za-z0-9_-]{16,64}$/', $token)) return null;
        $o = Db::one('SELECT * FROM offerte WHERE token = ?', [$token]);
        if ($o && $o['stato'] === 'inviata' && $o['valida_fino'] < gmdate('Y-m-d')) { self::aggiornaScadute(); $o = Db::one('SELECT * FROM offerte WHERE id = ?', [$o['id']]); }
        return $o;
    }

    /** Le offerte di un preventivo o di un account, dalla più recente. */
    public static function di(?int $preventivoId, ?int $accountId = null): array
    {
        if (!self::disponibili()) return [];
        if ($preventivoId) return Db::all('SELECT * FROM offerte WHERE preventivo_id = ? ORDER BY id DESC', [$preventivoId]);
        return Db::all('SELECT * FROM offerte WHERE account_id = ? ORDER BY id DESC', [(int) $accountId]);
    }

    /**
     * Prepara l'offerta. $in: strutture, prezzo (euro scritti), valida_fino (AAAA-MM-GG), sconto_rinnovo (%), note, modo.
     * $dest: preventivo_id, account_id, email, nome, lingua. Lancia un RuntimeException con il motivo da mostrare.
     */
    public static function crea(array $in, array $dest, int $adminId): array
    {
        if (!self::disponibili()) throw new \RuntimeException('Le offerte non sono ancora attive: manca la migrazione 058.');
        $n = (int) ($in['strutture'] ?? 0);
        if ($n < self::minimo()) throw new \RuntimeException('Un\'offerta su misura parte da ' . self::minimo() . ' strutture: fino a ' . (self::minimo() - 1) . ' vale il listino.');
        if ($n > 500) throw new \RuntimeException('Scrivi un numero di strutture fino a 500.');
        $prezzo = self::euro((string) ($in['prezzo'] ?? ''));
        if ($prezzo === null || $prezzo < 100) throw new \RuntimeException('Scrivi il prezzo annuo totale in euro, IVA esclusa (per esempio 640,00).');
        $fino = trim((string) ($in['valida_fino'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fino) || !checkdate((int) substr($fino, 5, 2), (int) substr($fino, 8, 2), (int) substr($fino, 0, 4)) || $fino < gmdate('Y-m-d')) {
            throw new \RuntimeException('Scegli fino a quando vale l\'offerta (oggi o dopo).');
        }
        $sconto = (int) ($in['sconto_rinnovo'] ?? 0);
        if ($sconto < 0 || $sconto > 90) throw new \RuntimeException('Lo sconto sul rinnovo va da 0 a 90%.');
        $email = mb_strtolower(trim((string) ($dest['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new \RuntimeException('Manca un indirizzo email valido per mandare l\'offerta.');
        $modo = in_array($in['modo'] ?? '', ['subito', 'rinnovo'], true) && !empty($dest['account_id']) && self::attiva((int) $dest['account_id']) ? (string) $in['modo'] : 'nuovo';
        $id = Db::insert('offerte', [
            'preventivo_id' => $dest['preventivo_id'] ?? null, 'account_id' => $dest['account_id'] ?? null, 'email' => $email,
            'nome' => mb_substr(trim((string) ($dest['nome'] ?? '')), 0, 120), 'lingua' => ($dest['lingua'] ?? 'it') === 'en' ? 'en' : 'it',
            'strutture' => $n, 'prezzo_cents' => $prezzo, 'valida_fino' => $fino, 'token' => Support::token(24), 'stato' => 'inviata',
            'sconto_rinnovo_pct' => $sconto, 'note' => mb_substr(trim((string) ($in['note'] ?? '')), 0, 1000), 'modo' => $modo,
            'creata_da' => $adminId ?: null, 'created_at' => Support::now(),
        ]);
        // Un'offerta nuova sullo stesso preventivo sostituisce quelle ancora aperte.
        if (!empty($dest['preventivo_id'])) {
            Db::run("UPDATE offerte SET stato = 'annullata' WHERE preventivo_id = ? AND id <> ? AND stato = 'inviata'", [(int) $dest['preventivo_id'], $id]);
            if (Preventivi::disponibili()) Db::update('preventivi', ['stato' => 'offerta'], 'id = :id', ['id' => (int) $dest['preventivo_id']]);
        }
        Auth::audit('offerta.creata', null, ['id' => $id, 'strutture' => $n, 'prezzo' => $prezzo, 'valida_fino' => $fino, 'modo' => $modo,
                                             'preventivo' => $dest['preventivo_id'] ?? null, 'account_id' => $dest['account_id'] ?? null]);
        return Db::one('SELECT * FROM offerte WHERE id = ?', [$id]);
    }

    /** L'offerta che un account paga adesso, se c'è (abbonamento attivo con offerta_id). */
    public static function attiva(int $accountId): ?array
    {
        if (!self::disponibili() || !Migrator::columnExists('subscriptions', 'offerta_id')) return null;
        $s = Subscriptions::active($accountId);
        return $s && !empty($s['offerta_id']) ? self::riga((int) $s['offerta_id']) : null;
    }

    /** L'offerta di un abbonamento (la riga di subscriptions). */
    public static function diAbbonamento(array $sub): ?array
    {
        return !empty($sub['offerta_id']) ? self::riga((int) $sub['offerta_id']) : null;
    }

    /** Il prezzo annuo di un abbonamento su offerta (quello che si paga al rinnovo), o null se è di listino. */
    public static function prezzo(array $sub): ?int
    {
        $prossima = !empty($sub['next_offerta_id']) ? self::riga((int) $sub['next_offerta_id']) : null;
        $o = $prossima ?? self::diAbbonamento($sub);
        return $o ? (int) $o['prezzo_cents'] : null;
    }

    /** Lo sconto sul prossimo rinnovo deciso dall'amministrazione (0 se nessuno). */
    public static function scontoRinnovo(array $sub): int
    {
        $o = self::diAbbonamento($sub);
        return $o ? max(0, (int) $o['sconto_rinnovo_pct']) : 0;
    }

    /** Gli sconti del programma che varrebbero (promemoria per l'amministrazione, mai applicati da soli). */
    public static function promemoria(int $accountId): array
    {
        $amici = Inviti::disponibili() || Migrator::tableExists('referrals') ? Inviti::validi($accountId) : 0;
        $acc = Db::one('SELECT giudizio_adesione FROM accounts WHERE id = ?', [$accountId]) ?: [];
        $aderito = Migrator::columnExists('accounts', 'giudizio_adesione') && !empty($acc['giudizio_adesione']);
        return ['amici' => $amici, 'inviti_pct' => min(Inviti::MASSIMO, Inviti::PASSO * $amici), 'giudizio' => $aderito, 'giudizio_pct' => $aderito ? Giudizi::percentuale() : 0];
    }

    /**
     * Lo sconto sul prossimo rinnovo, deciso a mano: si cambia fino a 7 giorni prima del rinnovo; il sito lo porta su Stripe
     * con il coupon del rinnovo (mhw-rinnovo-N, lo stesso del punto 1) e l'avviso dei 30 giorni mostra l'importo scontato.
     */
    public static function impostaSconto(int $accountId, int $pct, int $adminId): array
    {
        $s = Subscriptions::active($accountId);
        $o = $s ? self::diAbbonamento($s) : null;
        if (!$o) throw new \RuntimeException('Questo cliente non ha un\'offerta su misura attiva.');
        if ($pct < 0 || $pct > 90) throw new \RuntimeException('Lo sconto va da 0 a 90%.');
        $fine = (int) strtotime((string) $s['current_period_end']);
        if ($fine - time() < self::GIORNI_SCONTO * 86400) throw new \RuntimeException('Mancano meno di ' . self::GIORNI_SCONTO . ' giorni al rinnovo: lo sconto non si cambia più per questo rinnovo.');
        $prima = (int) $o['sconto_rinnovo_pct'];
        Db::update('offerte', ['sconto_rinnovo_pct' => $pct, 'sconto_at' => Support::now()], 'id = :id', ['id' => $o['id']]);
        $uid = (int) Db::val('SELECT user_id FROM accounts WHERE id = ?', [$accountId], 0);
        Auth::audit('offerta.sconto_rinnovo', $uid ?: null, ['offerta' => (int) $o['id'], 'da' => $prima, 'a' => $pct, 'admin' => $adminId]);
        try { Sconti::sincronizzaRinnovo($accountId); } catch (\Throwable $e) { Log::exception($e, 'offerta: sconto sul rinnovo su Stripe'); }
        return ['prima' => $prima, 'dopo' => $pct, 'importo' => Sconti::netto((int) $o['prezzo_cents'], $pct)];
    }

    // ------------------------------------------------------------ l'email e la pagina

    /** I testi dell'email dell'offerta, IT/EN: [oggetto, titolo, paragrafi, bottone, nota]. */
    public static function email(array $o): array
    {
        $en = $o['lingua'] === 'en';
        $prezzo = Support::money((int) $o['prezzo_cents']);
        $data = $en ? gmdate('j F Y', (int) strtotime($o['valida_fino'])) : Support::date($o['valida_fino']);
        $nome = trim(explode(' ', trim((string) $o['nome']))[0] ?? '');
        $link = Support::baseUrl() . '/offerta/' . $o['token'];
        $n = (int) $o['strutture'];
        if ($en) {
            $par = [($nome !== '' ? "$nome, here" : 'Here') . " is our proposal for your $n properties: $prezzo + VAT a year, all of Portfolio (one guide and one QR code per property, translations included, master guide, statistics).",
                    $o['modo'] === 'nuovo' ? 'The same price applies at renewal. The offer is valid until ' . $data . '.'
                        : ($o['modo'] === 'subito' ? 'It replaces your current offer straight away: you only pay the difference for the days left until renewal. Valid until ' . $data . '.'
                                                   : 'It replaces your current offer from your next renewal. Valid until ' . $data . '.')];
            if (trim((string) $o['note']) !== '') $par[] = trim((string) $o['note']);
            return ["Your tailored Portfolio offer: $n properties", 'Your tailored Portfolio offer', $par, ['See the offer and accept', $link],
                    'Payment is on Stripe, like our other plans. Annual automatic renewal, which you can switch off whenever you like.'];
        }
        $par = [($nome !== '' ? "$nome, ecco" : 'Ecco') . " la proposta per le tue $n strutture: $prezzo + IVA all'anno, tutto Portfolio (una guida e un QR per ogni struttura, traduzioni comprese, scheda campione, statistiche).",
                $o['modo'] === 'nuovo' ? 'Stesso prezzo anche al rinnovo. L\'offerta vale fino al ' . $data . '.'
                    : ($o['modo'] === 'subito' ? 'Sostituisce subito l\'offerta di adesso: paghi solo la differenza per i giorni che restano fino al rinnovo. Vale fino al ' . $data . '.'
                                               : 'Sostituisce l\'offerta di adesso dal prossimo rinnovo. Vale fino al ' . $data . '.')];
        if (trim((string) $o['note']) !== '') $par[] = trim((string) $o['note']);
        return ["La tua offerta Portfolio su misura: $n strutture", 'La tua offerta Portfolio su misura', $par, ['Vedi l\'offerta e accetta', $link],
                'Il pagamento è su Stripe, come per gli altri piani. Rinnovo automatico annuale, disattivabile quando vuoi.'];
    }

    /** Manda l'email dell'offerta (da assistenza@, Reply-To commerciale@). */
    public static function manda(array $o): bool
    {
        [$oggetto, $titolo, $par, $bottone, $nota] = self::email($o);
        $ok = Mailer::conRispostaA(Support::commerciale(), fn() => Mailer::inLingua($o['lingua'], fn() => Mailer::manda((string) $o['email'], $oggetto, $titolo, $par, $bottone, $nota)));
        Auth::audit('offerta.mandata', null, ['id' => (int) $o['id'], 'email' => (string) $o['email'], 'esito' => $ok]);
        return (bool) $ok;
    }

    /** Le offerte scadute senza pagamento: stato «scaduta», il preventivo torna «da ricontattare». */
    public static function aggiornaScadute(): int
    {
        if (!self::disponibili()) return 0;
        $n = 0;
        foreach (Db::all("SELECT * FROM offerte WHERE stato = 'inviata' AND valida_fino < ?", [gmdate('Y-m-d')]) as $o) {
            Db::update('offerte', ['stato' => 'scaduta'], 'id = :id', ['id' => $o['id']]);
            if (!empty($o['preventivo_id']) && Preventivi::disponibili()) {
                Db::run("UPDATE preventivi SET stato = 'ricontattare' WHERE id = ? AND stato NOT IN ('accettato','chiuso')", [(int) $o['preventivo_id']]);
            }
            Auth::audit('offerta.scaduta', null, ['id' => (int) $o['id'], 'preventivo' => $o['preventivo_id']]);
            $n++;
        }
        return $n;
    }

    // ------------------------------------------------------------------ il pagamento

    /**
     * «Accetta e paga». Nuovo abbonamento: ordine Portfolio con N strutture al prezzo dell'offerta e Checkout di Stripe.
     * Cliente con un'offerta già attiva: dal rinnovo (Stripe passa al prezzo nuovo senza addebiti adesso) o subito, con il
     * conguaglio pagato a parte (come la 6H). @return string l'indirizzo dove andare
     */
    public static function accetta(array $o, array $acc, array $user): string
    {
        if ($o['stato'] !== 'inviata') throw new \RuntimeException($o['stato'] === 'accettata' ? 'Questa offerta è già stata accettata.' : 'Questa offerta non è più valida: scrivici e te ne prepariamo una nuova.');
        if (!empty($o['account_id']) && (int) $o['account_id'] !== (int) $acc['id']) throw new \RuntimeException('Questa offerta è per un altro account: accedi con l\'email a cui è arrivata.');
        if (!Stripe::enabled()) throw new \RuntimeException('I pagamenti non sono ancora attivi: riprova più tardi.');
        if (!Fatturazione::completa($acc)) throw new \RuntimeException('Prima del pagamento servono i dati di fatturazione: compilali in Account & Fatturazione.');
        $pv = self::versione();
        if (!$pv) throw new \RuntimeException('Il Portfolio non è in vendita in questo momento: scrivici.');
        if (empty($o['account_id'])) Db::update('offerte', ['account_id' => (int) $acc['id']], 'id = :id', ['id' => $o['id']]);
        $o['account_id'] = (int) $acc['id'];
        $sub = Subscriptions::active((int) $acc['id']);
        if ($sub && $sub['provider'] === 'stripe' && !empty($sub['offerta_id']) && $o['modo'] !== 'nuovo') return self::cambia($o, $sub, $acc, $user);
        if ($sub && $sub['provider'] === 'stripe' && in_array($sub['status'], ['active', 'trialing'], true)) {
            throw new \RuntimeException('Hai già un abbonamento attivo: scrivici e prepariamo il passaggio all\'offerta senza pagare due volte.');
        }
        $pkg = Db::one('SELECT * FROM packages WHERE id = ?', [$pv['package_id']]);
        $oid = Db::insert('orders', [
            'account_id' => (int) $acc['id'], 'package_version_id' => (int) $pv['id'], 'property_id' => null, 'quantity' => (int) $o['strutture'],
            'amount_cents' => (int) $o['prezzo_cents'], 'currency' => (string) $pv['currency'], 'status' => 'pending', 'provider' => 'stripe',
            'provider_session_id' => '', 'created_at' => Support::now(), 'updated_at' => Support::now(), 'offerta_id' => (int) $o['id'],
        ] + (Migrator::columnExists('orders', 'acquirente') ? ['acquirente' => 'impresa', 'recesso_rinuncia' => 0] : []));
        Codici::pagamento($oid);
        Db::update('accounts', ['intended_package_version_id' => (int) $pv['id'], 'intended_quantity' => (int) $o['strutture']], 'id = :aid', ['aid' => (int) $acc['id']]);
        try { return Stripe::checkoutOfferta(Db::one('SELECT * FROM orders WHERE id = ?', [$oid]), $o, $pv, $pkg, $acc, $user); }
        catch (\Throwable $e) { Db::update('orders', ['status' => 'failed', 'updated_at' => Support::now()], 'id = :oid', ['oid' => $oid]); throw $e; }
    }

    /** Dentro Billing::activate: l'abbonamento nato da un'offerta. Solo database. */
    public static function attivata(array $order, int $subId): void
    {
        if (empty($order['offerta_id']) || !self::disponibili()) return;
        $o = self::riga((int) $order['offerta_id']);
        if (!$o) return;
        Db::update('subscriptions', ['offerta_id' => (int) $o['id']], 'id = :sid', ['sid' => $subId]);
        Db::update('offerte', ['stato' => 'accettata', 'accettata_at' => Support::now(), 'account_id' => (int) $order['account_id']], 'id = :id', ['id' => $o['id']]);
        if (!empty($o['preventivo_id']) && Preventivi::disponibili()) Db::update('preventivi', ['stato' => 'accettato'], 'id = :id', ['id' => (int) $o['preventivo_id']]);
        $uid = (int) Db::val('SELECT user_id FROM accounts WHERE id = ?', [(int) $order['account_id']], 0);
        Auth::audit('offerta.accettata', $uid ?: null, ['id' => (int) $o['id'], 'order' => (int) $order['id'], 'strutture' => (int) $o['strutture'], 'prezzo' => (int) $o['prezzo_cents']]);
    }

    /** Il Price di Stripe dell'offerta: uno per offerta, annuale, IVA esclusa, mai nel listino. Rete. */
    private static function prezzoStripe(array $o, array $pv): string
    {
        $r = Stripe::call('POST', 'prices', [
            'currency' => strtolower((string) $pv['currency']), 'unit_amount' => (string) (int) $o['prezzo_cents'], 'tax_behavior' => 'exclusive',
            'recurring[interval]' => 'year', 'product_data[name]' => 'MyHouse Welcome Portfolio su misura · ' . (int) $o['strutture'] . ' strutture',
            'product_data[metadata][ruolo]' => 'offerta', 'product_data[metadata][offerta]' => (string) $o['id'], 'metadata[offerta]' => (string) $o['id'],
        ], 'mhw-offerta-prezzo-' . $o['id']);
        return (string) ($r['id'] ?? '');
    }

    /** Il passaggio a una nuova offerta per chi ne ha già una: dal rinnovo o subito con il conguaglio. */
    private static function cambia(array $o, array $sub, array $acc, array $user): string
    {
        $vecchia = self::diAbbonamento($sub);
        $inizio = (int) strtotime((string) $sub['current_period_start']); $fine = (int) strtotime((string) $sub['current_period_end']);
        $conguaglio = $o['modo'] === 'subito' ? CambioPiano::conguaglio((int) ($vecchia['prezzo_cents'] ?? 0), (int) $o['prezzo_cents'], $inizio, $fine, time()) : 0;
        if ($o['modo'] === 'rinnovo' || $conguaglio < 100) {
            self::suStripe($o, $sub, 'mhw-offerta-' . $o['id']);
            Db::tx(function () use ($o, $sub) {
                if ($o['modo'] === 'rinnovo') Db::update('subscriptions', ['next_offerta_id' => (int) $o['id'], 'updated_at' => Support::now()], 'id = :sid', ['sid' => $sub['id']]);
                else self::applicaLocale($sub, $o, 'subito');
                Db::update('offerte', ['stato' => 'accettata', 'accettata_at' => Support::now()], 'id = :id', ['id' => $o['id']]);
                Auth::audit('offerta.accettata', null, ['id' => (int) $o['id'], 'modo' => $o['modo'], 'account_id' => (int) $sub['account_id']]);
            });
            Support::flash($o['modo'] === 'rinnovo' ? 'Offerta accettata: dal rinnovo del ' . Support::date((string) $sub['current_period_end']) . ' passi a ' . (int) $o['strutture'] . ' strutture.'
                                                    : 'Offerta accettata: hai già ' . (int) $o['strutture'] . ' strutture.');
            return '/account';
        }
        $pv = self::versione();
        $oid = Db::insert('orders', [
            'account_id' => (int) $acc['id'], 'package_version_id' => (int) $pv['id'], 'amount_cents' => $conguaglio, 'currency' => (string) $pv['currency'],
            'status' => 'pending', 'provider' => 'stripe', 'provider_session_id' => '', 'quantity' => (int) $o['strutture'], 'kind' => 'change',
            'from_package_version_id' => (int) $sub['package_version_id'], 'from_quantity' => (int) $sub['quantity'], 'offerta_id' => (int) $o['id'],
            'created_at' => Support::now(), 'updated_at' => Support::now(),
        ]);
        Codici::pagamento($oid);
        return Stripe::checkoutChange(Db::one('SELECT * FROM orders WHERE id = ?', [$oid]),
                                      'MyHouse Welcome Portfolio su misura · da ' . (int) $sub['quantity'] . ' a ' . (int) $o['strutture'] . ' strutture (conguaglio fino al rinnovo)', $acc, $user);
    }

    /** Stripe passa al prezzo della nuova offerta, senza proporzioni (il conguaglio, se c'è, è pagato a parte). Rete. */
    public static function suStripe(array $o, array $sub, string $chiave): array
    {
        $pv = self::versione() ?? [];
        $prezzo = self::prezzoStripe($o, $pv + ['currency' => 'EUR']);
        $s = Stripe::retrieveSubscription((string) $sub['provider_subscription_id']);
        $voce = (string) ($s['items']['data'][0]['id'] ?? '');
        $p = ['items[0][id]' => $voce, 'items[0][price]' => $prezzo, 'items[0][quantity]' => 1];
        foreach (array_slice((array) ($s['items']['data'] ?? []), 1) as $i => $v) { $p['items[' . ($i + 1) . '][id]'] = (string) $v['id']; $p['items[' . ($i + 1) . '][deleted]'] = 'true'; }
        return Stripe::applyChange((string) $sub['provider_subscription_id'], $p, $chiave);
    }

    /** Nel sito: l'abbonamento passa alla nuova offerta (strutture e offerta). Dentro una transazione. */
    public static function applicaLocale(array $sub, array $o, string $perche): void
    {
        Db::update('subscriptions', ['offerta_id' => (int) $o['id'], 'next_offerta_id' => null, 'quantity' => (int) $o['strutture'], 'updated_at' => Support::now()], 'id = :sid', ['sid' => $sub['id']]);
        Db::update('accounts', ['intended_quantity' => (int) $o['strutture']], 'id = :aid', ['aid' => (int) $sub['account_id']]);
        $uid = (int) Db::val('SELECT user_id FROM accounts WHERE id = ?', [(int) $sub['account_id']], 0);
        Auth::audit('subscription.change', $uid ?: null, ['da' => ['q' => (int) $sub['quantity'], 'offerta' => (int) ($sub['offerta_id'] ?? 0)], 'a' => ['q' => (int) $o['strutture'], 'offerta' => (int) $o['id']], 'come' => $perche]);
        Entitlements::forget((int) $sub['account_id']);
    }

    /** Webhook del cambio pagato (ordine con offerta_id), PRIMA della transazione: Stripe al prezzo nuovo. */
    public static function preparaCambio(array $order): array
    {
        $sub = Subscriptions::active((int) $order['account_id']);
        $o = self::riga((int) $order['offerta_id']);
        if (!$sub || !$o || $sub['provider'] !== 'stripe') return ['ok' => false, 'sub' => null];
        try { return ['ok' => true, 'sub' => self::suStripe($o, $sub, 'mhw-offerta-cambio-' . $order['id'])]; }
        catch (\Throwable $e) { Log::exception($e, 'offerta: cambio su Stripe, ordine ' . $order['id']); return ['ok' => false, 'sub' => null]; }
    }

    /** Webhook del cambio pagato, DENTRO la transazione. */
    public static function cambioPagato(array $order, array $prep): string
    {
        if (!empty($order['applied_at'])) return 'cambio-gia-applicato';
        if (!$prep['ok']) return 'cambio-pagato-da-completare';
        $sub = Subscriptions::active((int) $order['account_id']);
        $o = self::riga((int) $order['offerta_id']);
        if (!$sub || !$o) return 'cambio-pagato-da-completare';
        self::applicaLocale($sub, $o, 'pagamento');
        Db::update('offerte', ['stato' => 'accettata', 'accettata_at' => Support::now()], 'id = :id', ['id' => $o['id']]);
        Db::update('orders', ['applied_at' => Support::now()], 'id = :oid', ['oid' => $order['id']]);
        return 'offerta-cambiata';
    }

    /** Al rinnovo (invoice.paid subscription_cycle), se c'è un'offerta che vale da qui: strutture e offerta nuove. */
    public static function alRinnovo(array $row): void
    {
        $o = self::riga((int) $row['next_offerta_id']);
        if ($o) self::applicaLocale($row, $o, 'rinnovo');
    }
}
