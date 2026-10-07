<?php
namespace MHW;

/**
 * Il cambio di piano di un abbonamento attivo (fase 6H), con database e Stripe.
 * Le regole e i conti stanno in CambioPiano; qui ci sono i passaggi.
 *
 *   - SALE (piano o strutture più cari): si paga oggi la differenza a giorni, su una
 *     pagina di Stripe (ordine kind=change). Il piano nuovo vale appena il webhook
 *     conferma il pagamento: prima Stripe (voci nuove, senza proporzioni), poi il sito.
 *     Se Stripe non risponde, l'ordine resta pagato e da applicare: ci riprova
 *     applyPendingChanges() (giro dei richiami e /cron).
 *   - SCENDE: niente rimborsi né addebiti. Su Stripe le voci passano subito ai prezzi
 *     nuovi (il rinnovo incasserà quelli), nel sito resta tutto com'è fino al rinnovo:
 *     subscriptions.next_* dice cosa cambierà, e alRinnovo() lo applica.
 *   - UGUALE, o differenza sotto 1 € → vale subito, senza pagamento.
 *
 * Niente si cancella: al rinnovo le sezioni in più si disattivano, le strutture in più
 * si archiviano, le lingue non comprese escono dalla guida. Tornano con un piano più ricco.
 */
final class CambioAbbonamento
{
    public const STAFF = 'Questo abbonamento è stato attivato dal nostro staff: per cambiarlo scrivici.';
    public const ARRETRATO = 'Prima sistema il pagamento dell\'ultimo rinnovo, poi puoi cambiare piano.';

    /**
     * L'abbonamento che si può cambiare, o il perché no.
     * @return array{sub:?array,pv:?array,quantita:int,motivo:string} motivo '' = si può; 'nessuno' = nessun abbonamento attivo
     */
    public static function stato(int $accountId): array
    {
        $s = Subscriptions::active($accountId);
        if (!$s) {
            // Un rinnovo non pagato: l'abbonamento non vale più, ma c'è ancora, ed è quello da sistemare prima.
            $l = Db::one('SELECT * FROM subscriptions WHERE account_id = ? ORDER BY id DESC', [$accountId]);
            if ($l && $l['status'] === 'past_due' && $l['provider'] === 'stripe') {
                return ['sub' => $l, 'pv' => Plans::version((int) $l['package_version_id']), 'quantita' => max(1, (int) $l['quantity']), 'motivo' => self::ARRETRATO];
            }
            return ['sub' => null, 'pv' => null, 'quantita' => 0, 'motivo' => 'nessuno'];
        }
        $pv = Plans::version((int) $s['package_version_id']);
        $motivo = '';
        if ($s['provider'] !== 'stripe' || (string) $s['provider_subscription_id'] === '') $motivo = self::STAFF;
        elseif ($s['status'] === 'past_due') $motivo = self::ARRETRATO;
        return ['sub' => $s, 'pv' => $pv, 'quantita' => max(1, (int) $s['quantity']), 'motivo' => $motivo];
    }

    /** La versione in vendita di un piano, dal codice (essential, plus, portfolio). */
    public static function versioneDi(string $codice): ?array
    {
        $pk = Db::one('SELECT id FROM packages WHERE code = ? AND active = 1 AND public = 1', [$codice]);
        $pv = $pk ? Db::val('SELECT id FROM package_versions WHERE package_id = ? AND is_current = 1', [$pk['id']]) : null;
        return $pv ? Plans::version((int) $pv) : null;
    }

    /**
     * Quanto costa passare da qui a lì.
     * @return array{tipo:string,annuoAttuale:int,annuoNuovo:int,conguaglio:int,giorni:int,fine:string,stesso:bool}
     */
    public static function preventivo(array $sub, array $pvAttuale, array $pvNuova, int $quantita, ?int $adesso = null): array
    {
        $qAtt = max(1, (int) $sub['quantity']);
        $annuoAtt = Plans::price($pvAttuale, $qAtt);
        $annuoNuovo = Plans::price($pvNuova, $quantita);
        $inizio = strtotime((string) $sub['current_period_start']) ?: time();
        $fine = strtotime((string) $sub['current_period_end']) ?: time() + 365 * 86400;
        $ora = $adesso ?? time();
        $stessoPiano = (string) $pvAttuale['code'] === (string) $pvNuova['code'];
        return [
            'tipo' => CambioPiano::tipo($annuoAtt, $annuoNuovo),
            'annuoAttuale' => $annuoAtt, 'annuoNuovo' => $annuoNuovo,
            'conguaglio' => CambioPiano::conguaglio($annuoAtt, $annuoNuovo, $inizio, $fine, $ora),
            'giorni' => CambioPiano::giorniRestanti($inizio, $fine, $ora),
            'fine' => (string) $sub['current_period_end'],
            'stesso' => $stessoPiano && $qAtt === $quantita,
        ];
    }

    /** «MyHouse Welcome — passaggio da Plus a Portfolio (2 strutture)», oppure «— Portfolio da 2 a 3 strutture». */
    public static function descrizione(array $pvAttuale, int $qAtt, array $pvNuova, int $q): string
    {
        $conQ = fn(array $pv, int $n) => $pv['name'] . (Plans::perProperty($pv) ? ' (' . $n . ' struttur' . ($n === 1 ? 'a' : 'e') . ')' : '');
        if ((string) $pvAttuale['code'] === (string) $pvNuova['code']) return 'MyHouse Welcome — ' . $pvNuova['name'] . " da $qAtt a $q strutture";
        return 'MyHouse Welcome — passaggio da ' . $conQ($pvAttuale, $qAtt) . ' a ' . $conQ($pvNuova, $q);
    }

    /** Le voci di Stripe portate alla versione e al numero di strutture dati. Rete: va chiamata fuori dalle transazioni. */
    public static function suStripe(array $sub, array $pvNuova, int $q, string $chiave): array
    {
        $pkg = ['name' => $pvNuova['name'], 'code' => $pvNuova['code']];
        [$base, $extra] = Stripe::ensurePrices($pvNuova, $pkg);
        $s = Stripe::retrieveSubscription((string) $sub['provider_subscription_id']);
        $voci = CambioPiano::voci((array) ($s['items']['data'] ?? []), (string) ($sub['provider_extra_item_id'] ?? ''), $base, $extra, $q);
        return Stripe::applyChange((string) $sub['provider_subscription_id'], $voci, $chiave);
    }

    /** Nel sito: l'abbonamento passa alla versione nuova. Dentro una transazione. */
    private static function aggiornaLocale(array $sub, array $pvNuova, int $q, array $stripeSub, string $perche): void
    {
        $pvNuova = Plans::version((int) $pvNuova['id']) ?: $pvNuova;   // con i Price appena creati da ensurePrices
        $voceExtra = '';
        $prezzoBase = '';
        foreach ((array) ($stripeSub['items']['data'] ?? []) as $v) {
            $prezzo = $v['price'] ?? [];
            $prodotto = is_array($prezzo['product'] ?? null) ? $prezzo['product'] : [];
            $eExtra = (($pvNuova['stripe_extra_price_id'] ?? '') !== '' && ($prezzo['id'] ?? '') === $pvNuova['stripe_extra_price_id'])
                   || (($prodotto['metadata']['ruolo'] ?? '') === 'aggiuntiva');
            if ($eExtra) $voceExtra = (string) ($v['id'] ?? '');
            elseif ($prezzoBase === '') $prezzoBase = (string) ($prezzo['id'] ?? '');
        }
        $prima = ['pv' => (int) $sub['package_version_id'], 'q' => (int) $sub['quantity']];
        Db::update('subscriptions', [
            'package_version_id' => $pvNuova['id'], 'quantity' => $q,
            'provider_price_id' => $prezzoBase !== '' ? $prezzoBase : (string) $sub['provider_price_id'],
            'provider_extra_item_id' => Plans::perProperty($pvNuova) ? $voceExtra : '',
            'next_package_version_id' => null, 'next_quantity' => null, 'next_choices' => null, 'next_requested_at' => null,
            'updated_at' => Support::now(),
        ], 'id = :sid', ['sid' => $sub['id']]);
        Db::update('accounts', ['intended_package_version_id' => $pvNuova['id'], 'intended_quantity' => $q], 'id = :aid', ['aid' => $sub['account_id']]);
        $uid = (int) Db::val('SELECT user_id FROM accounts WHERE id = ?', [$sub['account_id']]);
        Auth::audit('subscription.change', $uid, ['da' => $prima, 'a' => ['pv' => (int) $pvNuova['id'], 'q' => $q], 'come' => $perche]);
        Entitlements::forget((int) $sub['account_id']);
    }

    /** Un cambio che vale subito (prezzo uguale, o differenza sotto 1 €). */
    public static function subito(array $sub, array $pvNuova, int $q): void
    {
        $s = self::suStripe($sub, $pvNuova, $q, 'mhw-change-subito-' . $sub['id'] . '-' . $pvNuova['id'] . '-' . $q . '-' . gmdate('YmdHi'));
        Db::tx(fn() => self::aggiornaLocale($sub, $pvNuova, $q, $s, 'subito'));
    }

    /** L'ordine per la differenza: si riusa quello uguale ancora in attesa da meno di 30 minuti. */
    public static function ordine(array $account, array $sub, array $pvNuova, int $q, int $cents): array
    {
        $da = gmdate('Y-m-d\TH:i:s\Z', time() - 1800);
        $uguale = Db::one("SELECT * FROM orders WHERE account_id = ? AND kind = 'change' AND status = 'pending' AND package_version_id = ?
                           AND quantity = ? AND amount_cents = ? AND created_at >= ? ORDER BY id DESC", [$account['id'], $pvNuova['id'], $q, $cents, $da]);
        if ($uguale) return $uguale;
        $id = Db::insert('orders', [
            'account_id' => $account['id'], 'package_version_id' => $pvNuova['id'], 'amount_cents' => $cents,
            'currency' => (string) $pvNuova['currency'], 'status' => 'pending', 'provider' => 'stripe', 'provider_session_id' => '',
            'quantity' => $q, 'kind' => 'change', 'from_package_version_id' => (int) $sub['package_version_id'], 'from_quantity' => (int) $sub['quantity'],
            'created_at' => Support::now(), 'updated_at' => Support::now(),
        ]);
        return Db::one('SELECT * FROM orders WHERE id = ?', [$id]);
    }

    /**
     * Webhook, PRIMA della transazione: porta Stripe al piano pagato con l'ordine.
     * @return array{ok:bool,sub:?array} ok=false se Stripe non ha risposto (si riproverà)
     */
    public static function preparaStripe(int $orderId): array
    {
        $o = Db::one("SELECT * FROM orders WHERE id = ? AND kind = 'change'", [$orderId]);
        if (!$o || !empty($o['applied_at'])) return ['ok' => false, 'sub' => null];
        $sub = Subscriptions::active((int) $o['account_id']);
        $pv = Plans::version((int) $o['package_version_id']);
        if (!$sub || !$pv || $sub['provider'] !== 'stripe') return ['ok' => false, 'sub' => null];
        try {
            return ['ok' => true, 'sub' => self::suStripe($sub, $pv, max(1, (int) $o['quantity']), 'mhw-change-' . $o['id'])];
        } catch (\Throwable $e) {
            Log::exception($e, 'cambio di piano su Stripe, ordine ' . $o['id']);
            return ['ok' => false, 'sub' => null];
        }
    }

    /** Webhook, DENTRO la transazione: ordine pagato e, se Stripe è già allineato, cambio applicato. */
    public static function pagato(array $order, array $prep): string
    {
        if ($order['status'] !== 'paid') Db::update('orders', ['status' => 'paid', 'updated_at' => Support::now()], 'id = :oid', ['oid' => $order['id']]);
        if (!empty($order['applied_at'])) return 'cambio-gia-applicato';
        if (!$prep['ok']) return 'cambio-pagato-da-completare';
        $sub = Subscriptions::active((int) $order['account_id']);
        $pv = Plans::version((int) $order['package_version_id']);
        if (!$sub || !$pv) return 'cambio-pagato-da-completare';
        self::aggiornaLocale($sub, $pv, max(1, (int) $order['quantity']), (array) $prep['sub'], 'pagamento');
        Db::update('orders', ['applied_at' => Support::now()], 'id = :oid', ['oid' => $order['id']]);
        return 'cambio-applicato';
    }

    /** I cambi pagati ma non ancora applicati (Stripe non aveva risposto): si riprova. @return int quanti applicati */
    public static function applyPendingChanges(): int
    {
        if (!Migrator::columnExists('orders', 'applied_at') || !Stripe::enabled()) return 0;
        $fatti = 0;
        foreach (Db::all("SELECT * FROM orders WHERE kind = 'change' AND status = 'paid' AND applied_at IS NULL ORDER BY id LIMIT 20") as $o) {
            $prep = self::preparaStripe((int) $o['id']);
            if (!$prep['ok']) continue;
            Db::tx(function () use ($o, $prep) { self::pagato($o, $prep); });
            $fatti++;
        }
        return $fatti;
    }

    public static function inSospeso(): int
    {
        if (!Migrator::columnExists('orders', 'applied_at')) return 0;
        return (int) Db::val("SELECT COUNT(*) FROM orders WHERE kind = 'change' AND status = 'paid' AND applied_at IS NULL", [], 0);
    }

    /** Scendere: Stripe passa subito ai prezzi nuovi, il sito al rinnovo. $scelte: ['sezioni' => [pid => [sid…]], 'archivia' => [pid…]] */
    public static function programma(array $sub, array $pvNuova, int $q, array $scelte): void
    {
        self::suStripe($sub, $pvNuova, $q, 'mhw-change-giu-' . $sub['id'] . '-' . $pvNuova['id'] . '-' . $q . '-' . gmdate('YmdHi'));
        Db::tx(function () use ($sub, $pvNuova, $q, $scelte) {
            Db::update('subscriptions', ['next_package_version_id' => $pvNuova['id'], 'next_quantity' => $q,
                'next_choices' => json_encode($scelte), 'next_requested_at' => Support::now(), 'updated_at' => Support::now()], 'id = :sid', ['sid' => $sub['id']]);
            $uid = (int) Db::val('SELECT user_id FROM accounts WHERE id = ?', [$sub['account_id']]);
            Auth::audit('subscription.change_scheduled', $uid, ['a' => ['pv' => (int) $pvNuova['id'], 'q' => $q], 'dal' => $sub['current_period_end']]);
        });
    }

    /** Annulla una discesa programmata: Stripe torna al piano attuale, il sito dimentica il cambio. */
    public static function annulla(array $sub): void
    {
        $pv = Plans::version((int) $sub['package_version_id']);
        if (!$pv) throw new \RuntimeException('Piano non trovato.');
        self::suStripe($sub, $pv, max(1, (int) $sub['quantity']), 'mhw-change-annulla-' . $sub['id'] . '-' . gmdate('YmdHi'));
        Db::update('subscriptions', ['next_package_version_id' => null, 'next_quantity' => null, 'next_choices' => null, 'next_requested_at' => null,
                                     'updated_at' => Support::now()], 'id = :sid', ['sid' => $sub['id']]);
        $uid = (int) Db::val('SELECT user_id FROM accounts WHERE id = ?', [$sub['account_id']]);
        Auth::audit('subscription.change_canceled', $uid, []);
    }

    /**
     * Cosa cambierà scendendo, per la pagina di conferma: sezioni da scegliere per
     * struttura, strutture da archiviare, e le funzioni che non ci saranno più.
     */
    public static function cosaCambia(int $accountId, array $pvNuova, int $q): array
    {
        $dopo = Entitlements::perVersione($accountId, (int) $pvNuova['id'], $q);
        $ora = Entitlements::forAccount($accountId);
        $maxSez = Entitlements::valore($dopo, 'sections', 4);
        $maxProp = Entitlements::valore($dopo, 'properties', 1);
        $strutture = Db::all('SELECT id, name, city, status FROM properties WHERE account_id = ? AND archived_at IS NULL AND is_demo < 2 ORDER BY id', [$accountId]);
        $sezioni = [];
        foreach ($strutture as $p) {
            $attive = Db::all('SELECT s.id, s.kind, t.title FROM sections s LEFT JOIN section_translations t ON t.section_id = s.id AND t.locale = ?
                               WHERE s.property_id = ? AND s.is_core = 0 AND s.is_active = 1 ORDER BY s.position, s.id',
                              [Db::val('SELECT default_locale FROM properties WHERE id = ?', [$p['id']], 'it'), $p['id']]);
            if (count($attive) > $maxSez) $sezioni[] = ['struttura' => $p, 'sezioni' => $attive];
        }
        $perde = [];
        $lingueOra = Entitlements::valore($ora, 'locales', 1); $lingueDopo = Entitlements::valore($dopo, 'locales', 1);
        if ($lingueDopo < $lingueOra) {
            $tutte = Config::get('locales');
            $restano = array_slice(array_values($tutte), 0, max(1, $lingueDopo));
            $perde[] = 'Lingue: restano ' . implode(' e ', $restano) . '. Le traduzioni nelle altre restano salvate, ma la guida non le mostra.';
        }
        $funzioni = ['photos' => 'Foto e PDF nelle sezioni: restano salvati, la guida non li mostra.', 'profile_image' => 'Foto profilo: resta salvata, la guida non la mostra.',
                     'analytics' => 'Statistiche di lettura.', 'hide_branding' => 'La firma «Guida creata con MyHouse Welcome» torna visibile.',
                     'auto_translation' => 'Traduzioni suggerite: quelle approvate restano.'];
        foreach ($funzioni as $k => $testo) {
            if (Entitlements::valore($ora, $k) > 0 && Entitlements::valore($dopo, $k) === 0) $perde[] = $testo;
        }
        return ['maxSezioni' => $maxSez, 'sezioni' => $sezioni, 'maxStrutture' => $maxProp, 'strutture' => $strutture,
                'daArchiviare' => max(0, count($strutture) - $maxProp), 'perde' => $perde];
    }

    /**
     * Al rinnovo, se c'è una discesa programmata: il sito passa al piano nuovo.
     * Dentro la transazione del webhook (invoice.paid con billing_reason=subscription_cycle).
     * @return string[] le frasi di cosa è cambiato, per l'email
     */
    public static function alRinnovo(array $row): array
    {
        $pv = Plans::version((int) $row['next_package_version_id']);
        if (!$pv) return [];
        $q = max(1, (int) ($row['next_quantity'] ?? 1));
        $aid = (int) $row['account_id'];
        $scelte = json_decode((string) ($row['next_choices'] ?? ''), true) ?: [];
        $dopo = Entitlements::perVersione($aid, (int) $pv['id'], $q);
        $cambiato = [];

        // Strutture: prima quelle scelte, poi (se non bastano) le ultime create.
        $maxProp = Entitlements::valore($dopo, 'properties', 1);
        $ids = array_map('intval', array_column(Db::all('SELECT id FROM properties WHERE account_id = ? AND archived_at IS NULL AND is_demo < 2 ORDER BY id', [$aid]), 'id'));
        $daTogliere = max(0, count($ids) - $maxProp);
        if ($daTogliere > 0) {
            $scelteP = array_values(array_intersect(array_map('intval', (array) ($scelte['archivia'] ?? [])), $ids));
            $via = array_slice(array_values(array_unique(array_merge($scelteP, array_reverse($ids)))), 0, $daTogliere);
            foreach ($via as $pid) Db::update('properties', ['archived_at' => Support::now()], 'id = :pid', ['pid' => $pid]);
            $cambiato[] = count($via) === 1 ? 'Una struttura è stata archiviata: contenuti e QR restano, la riattivi quando vuoi.' : count($via) . ' strutture sono state archiviate: contenuti e QR restano.';
        }
        // Sezioni oltre il limite: spente, nell'ordine scelto.
        $maxSez = Entitlements::valore($dopo, 'sections', 4);
        $spente = 0;
        foreach (Db::all('SELECT id FROM properties WHERE account_id = ? AND archived_at IS NULL', [$aid]) as $p) {
            $attive = array_map('intval', array_column(Db::all('SELECT id FROM sections WHERE property_id = ? AND is_core = 0 AND is_active = 1 ORDER BY position, id', [$p['id']]), 'id'));
            $r = CambioPiano::sezioni($attive, (array) ($scelte['sezioni'][$p['id']] ?? []), $maxSez);
            foreach ($r['spegnere'] as $sid) { Db::update('sections', ['is_active' => 0], 'id = :sid', ['sid' => $sid]); $spente++; }
        }
        if ($spente) $cambiato[] = ($spente === 1 ? 'Una sezione è stata disattivata' : "$spente sezioni sono state disattivate") . ': restano salvate, le riattivi quando vuoi.';
        // Lingue non comprese: escono dalla guida (la principale resta).
        $nLingue = Entitlements::valore($dopo, 'locales', 1);
        $tutte = array_keys(Config::get('locales'));
        $consentite = $nLingue >= count($tutte) ? $tutte : array_slice($tutte, 0, max(1, $nLingue));
        $tolte = 0;
        foreach (Db::all('SELECT id, default_locale FROM properties WHERE account_id = ?', [$aid]) as $p) {
            foreach (Db::all('SELECT locale FROM property_locales WHERE property_id = ?', [$p['id']]) as $l) {
                if ($l['locale'] === $p['default_locale'] || in_array($l['locale'], $consentite, true)) continue;
                Db::run('DELETE FROM property_locales WHERE property_id = ? AND locale = ?', [$p['id'], $l['locale']]);
                $tolte++;
            }
        }
        if ($tolte) $cambiato[] = 'Le lingue non comprese non compaiono più nella guida: le traduzioni restano salvate.';

        // Il piano: lo stesso aggiornamento della salita, senza chiamate (Stripe era già passato ai prezzi nuovi).
        Db::update('subscriptions', ['package_version_id' => $pv['id'], 'quantity' => $q, 'next_package_version_id' => null, 'next_quantity' => null,
                                     'next_choices' => null, 'next_requested_at' => null, 'updated_at' => Support::now()]
                                    + (Plans::perProperty($pv) ? [] : ['provider_extra_item_id' => '']), 'id = :sid', ['sid' => $row['id']]);
        Db::update('accounts', ['intended_package_version_id' => $pv['id'], 'intended_quantity' => $q], 'id = :aid', ['aid' => $aid]);
        Entitlements::forget($aid);
        $uid = (int) Db::val('SELECT user_id FROM accounts WHERE id = ?', [$aid]);
        Auth::audit('subscription.change', $uid, ['da' => ['pv' => (int) $row['package_version_id'], 'q' => (int) $row['quantity']], 'a' => ['pv' => (int) $pv['id'], 'q' => $q], 'come' => 'rinnovo']);
        // Le guide pubblicate si ripubblicano con le regole nuove (foto e PDF non compresi spariscono dalla guida, restano salvati).
        foreach (Db::all("SELECT id FROM properties WHERE account_id = ? AND status = 'published' AND archived_at IS NULL", [$aid]) as $p) Guide::publish((int) $p['id']);

        $u = Db::one('SELECT u.email, u.name FROM accounts a JOIN users u ON u.id = a.user_id WHERE a.id = ?', [$aid]);
        if ($u) {
            try {
                Mailer::send((string) $u['email'], 'Da oggi sei su ' . $pv['name'] . ' — MyHouse Welcome',
                    'Ciao ' . ($u['name'] ?: '') . ",\n\ncome avevi chiesto, con il rinnovo il tuo abbonamento è passato a " . $pv['name']
                    . (Plans::perProperty($pv) ? " ($q strutture)" : '') . ': ' . Support::money(Plans::price($pv, $q), (string) $pv['currency']) . " + IVA all'anno.\n\n"
                    . ($cambiato ? "Cosa è cambiato:\n- " . implode("\n- ", $cambiato) . "\n\n" : '')
                    . "Niente è stato cancellato: se torni a un piano più ricco, ritrovi tutto.\n\n" . Support::baseUrl() . "/account\n\nMyHouse Welcome");
            } catch (\Throwable $e) { Log::exception($e, 'email cambio piano'); }
        }
        return $cambiato;
    }
}
