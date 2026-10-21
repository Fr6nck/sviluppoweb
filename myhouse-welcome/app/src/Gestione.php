<?php
namespace MHW;

/**
 * I numeri e i controlli per gestire la piattaforma (Amministrazione).
 *
 * scadenze()  gli abbonamenti che finiscono o si rinnovano a breve, con l'ultimo avviso mandato
 * anomalie()  le cose che non tornano: pagamenti senza abbonamento, rinnovi falliti, guide offline…
 * prospetti() mese per mese: registrazioni, nuovi abbonati, incassi, scadenze non rinnovate;
 *             e adesso: ricavo ricorrente, rinnovi attesi, piani, inviti, codici sconto, traduzioni.
 *
 * Tutto è letto dal database nel momento in cui si apre la pagina. I clienti di esempio
 * (dominio Demo::DOMINIO), i clienti di prova (accounts.is_test, 7F) e gli abbonamenti
 * dimostrativi non entrano nei conti.
 * Importi in centesimi, IVA esclusa.
 */
final class Gestione
{
    private const GIORNO = 86400;

    private static function iso(int $t): string { return gmdate('Y-m-d\TH:i:s\Z', $t); }

    private static function demo(): string { return '%@' . Demo::DOMINIO; }

    private static function conProva(): bool
    {
        static $c = null;
        return $c ??= Migrator::columnExists('accounts', 'is_test');
    }

    /** 7F: « AND $col NOT IN (clienti di prova)», con $col l'id di un account (mai NULL). Vuota prima della migrazione 039. */
    public static function senzaProva(string $col): string
    {
        return self::conProva() ? " AND $col NOT IN (SELECT id FROM accounts WHERE is_test = 1)" : '';
    }

    /** 7F: la colonna «is_test» per gli elenchi (0 prima della migrazione 039). */
    public static function colonnaProva(string $alias = 'a'): string
    {
        return self::conProva() ? "$alias.is_test" : '0';
    }

    /** 7F: la stessa condizione per l'id di un utente. */
    public static function utentiVeri(string $col): string
    {
        return self::conProva() ? " AND $col NOT IN (SELECT user_id FROM accounts WHERE is_test = 1)" : '';
    }

    // ------------------------------------------------------------------ scadenze

    /**
     * Per ogni cliente il suo abbonamento più recente, se finisce tra 30 giorni fa e $giorni da oggi.
     * @return list<array> righe con stato, importo del rinnovo, ultimo avviso e prossimo avviso automatico
     */
    public static function scadenze(int $giorni = 60): array
    {
        $ora = time();
        $righe = Db::all(
            "SELECT s.*, u.email, u.name AS cliente, pk.name AS piano
             FROM subscriptions s JOIN accounts a ON a.id = s.account_id JOIN users u ON u.id = a.user_id
             JOIN package_versions pv ON pv.id = s.package_version_id JOIN packages pk ON pk.id = pv.package_id
             WHERE s.id = (SELECT MAX(s2.id) FROM subscriptions s2 WHERE s2.account_id = s.account_id)
               AND s.provider <> 'dimostrazione' AND u.email NOT LIKE ?" . self::senzaProva('a.id') . " AND s.current_period_end <> ''
               AND s.current_period_end > ? AND s.current_period_end <= ?
             ORDER BY s.current_period_end, s.id",
            [self::demo(), self::iso($ora - 30 * self::GIORNO), self::iso($ora + $giorni * self::GIORNO)]);
        foreach ($righe as &$r) {
            $fine = (int) strtotime((string) $r['current_period_end']);
            $r['giorni'] = (int) floor(($fine - $ora) / self::GIORNO);
            $r['finito'] = $fine <= $ora;
            $r['automatico'] = !$r['finito'] && $r['provider'] === 'stripe' && (int) $r['cancel_at_period_end'] === 0 && in_array($r['status'], ['active', 'trialing'], true);
            // 9N · anche l'omaggio (primo addebito) e il bonifico (si rinnova a mano)
            $r['stato'] = match (true) {
                $r['finito'] => ['Scaduto', 'alert'],
                $r['status'] === 'past_due' => ['Rinnovo non riuscito', 'alert'],
                $r['status'] === 'refunded' => ['Rimborsato: chiuso', 'alert'],   // 9O
                $r['automatico'] && $r['status'] === 'trialing' && !empty($r['omaggio_fino']) => ['In omaggio: primo addebito', 'sea'],
                $r['automatico'] => ['Si rinnova da solo', 'pine'],
                $r['provider'] === 'stripe' => ['Rinnovo fermo (disattivato)', 'ochre'],
                ($r['payment_status'] ?? '') === 'bonifico' => ['Pagato con bonifico: rinnovo a mano', 'ochre'],
                default => ['Attivato dallo staff', 'sea'],
            };
            [$r['importo'], $r['sconto']] = self::importoRinnovo($r);
            $r['avviso'] = self::ultimoAvviso((int) $r['account_id']);
            $r['prossimo'] = self::prossimoAvviso($r, $ora);
            $r['optout'] = Richiami::disponibili()
                ? array_column(Db::all("SELECT kind FROM email_optout WHERE account_id = ? AND kind IN ('rinnovo','scadenza')", [$r['account_id']]), 'kind') : [];
        }
        unset($r);
        return $righe;
    }

    /**
     * [quanto si incassa al prossimo addebito, percentuale di sconto]: il piano programmato (6H) e lo sconto del rinnovo
     * (9N: inviti + giudizio sommati, una volta sul prezzo pieno, Sconti::rinnovo e Sconti::netto). Nei mesi di un omaggio
     * il prossimo addebito è il primo, pieno.
     */
    public static function importoRinnovo(array $s): array
    {
        $pv = Plans::version((int) (!empty($s['next_package_version_id']) ? $s['next_package_version_id'] : $s['package_version_id']));
        if (!$pv) return [0, 0];
        $q = max(1, (int) (!empty($s['next_package_version_id']) ? ($s['next_quantity'] ?? 1) : ($s['quantity'] ?? 1)));
        $prezzo = Offerte::prezzo($s) ?? Plans::price($pv, $q);   // 9N · 10 bis: un'offerta su misura si rinnova al prezzo concordato
        $sconto = Sconti::rinnovo((int) $s['account_id'], $s)['stripe'];
        return [Sconti::netto($prezzo, $sconto), $sconto];
    }

    /** L'ultimo avviso di rinnovo o di scadenza mandato a un cliente: quando, di che tipo, se a mano. */
    public static function ultimoAvviso(int $accountId): ?array
    {
        if (!Richiami::disponibili()) return null;
        $r = Db::one("SELECT kind, ref, sent_at FROM email_log WHERE account_id = ? AND kind IN ('rinnovo','scadenza') ORDER BY sent_at DESC, id DESC", [$accountId]);
        if (!$r) return null;
        $r['manuale'] = str_starts_with((string) $r['ref'], 'manuale-');
        return $r;
    }

    /** Il prossimo avviso che i Richiami manderanno da soli, se ce n'è uno (data ISO). */
    private static function prossimoAvviso(array $r, int $ora): ?string
    {
        $fine = (int) strtotime((string) $r['current_period_end']);
        $tappe = $r['automatico'] ? [$fine - 30 * self::GIORNO, $fine - 7 * self::GIORNO]   // 9D: anche a 7 giorni
               : (in_array($r['status'], ['active', 'trialing', 'canceled'], true) && $r['provider'] !== 'dimostrazione'
                  ? [$fine - 30 * self::GIORNO, $fine - 7 * self::GIORNO, $fine + self::GIORNO] : []);
        foreach ($tappe as $t) if ($t > $ora - self::GIORNO) return self::iso(max($t, $ora));
        return null;
    }

    // ------------------------------------------------------------------ anomalie

    /**
     * Le cose da guardare. Ogni voce: titolo, gravità (alta, media, bassa), cosa fare, righe [testo, dettaglio, link].
     * Solo le voci con almeno una riga; in 'superati' i titoli dei controlli andati bene.
     * @return array{voci:list<array>,superati:list<string>}
     */
    public static function anomalie(): array
    {
        $ora = time(); $voci = []; $superati = [];
        $cliente = fn(array $r) => '/admin/cliente/' . (int) $r['account_id'];
        $chi = fn(array $r) => ($r['cliente'] ?? '') !== '' ? $r['cliente'] . ' · ' . $r['email'] : (string) $r['email'];
        $aggiungi = function (string $titolo, string $gravita, string $cosa, array $righe) use (&$voci, &$superati) {
            if ($righe) $voci[] = ['titolo' => $titolo, 'gravita' => $gravita, 'cosa' => $cosa, 'righe' => $righe];
            else $superati[] = $titolo;
        };
        $utenti = 'JOIN accounts a ON a.id = %s.account_id JOIN users u ON u.id = a.user_id';

        // 1. Pagato, ma nessun abbonamento è nato da quel pagamento.
        $righe = [];
        foreach (Db::all("SELECT o.*, u.email, u.name AS cliente FROM orders o " . sprintf($utenti, 'o') . "
                          WHERE o.status = 'paid' AND o.provider = 'stripe' AND o.kind = 'new' AND o.created_at <= ?
                            AND NOT EXISTS (SELECT 1 FROM subscriptions s WHERE s.account_id = o.account_id AND s.created_at >= o.created_at)
                          ORDER BY o.id DESC LIMIT 50", [self::iso($ora - 3600)]) as $r) {
            $righe[] = [$chi($r), 'ordine #' . (int) $r['id'] . ' del ' . Support::date($r['created_at']) . ' · ' . Support::money((int) $r['amount_cents'], (string) $r['currency']), $cliente($r)];
        }
        $aggiungi('Pagamenti senza abbonamento', 'alta', 'Il pagamento risulta, l\'abbonamento no. Controlla il pagamento su Stripe e, se è giusto, attiva un abbonamento manuale dalla scheda del cliente.', $righe);

        // 2. Differenze pagate (6H) non ancora applicate su Stripe.
        $righe = [];
        foreach (Db::all("SELECT o.*, u.email, u.name AS cliente FROM orders o " . sprintf($utenti, 'o') . "
                          WHERE o.kind = 'change' AND o.status = 'paid' AND o.applied_at IS NULL ORDER BY o.id DESC LIMIT 50") as $r) {
            $righe[] = [$chi($r), 'cambio di piano pagato il ' . Support::date($r['created_at']), $cliente($r)];
        }
        $aggiungi('Cambi di piano pagati da completare', 'alta', 'Il sito riprova da solo a ogni giro dei promemoria. Se restano, controlla le chiavi di Stripe in Impostazioni.', $righe);

        // 2 bis · 9O · Quantità non valida: Essential e Plus valgono per una struttura (il server rifiuta il Checkout, ma lo si vede qui).
        $righe = [];
        foreach (Db::all("SELECT o.*, u.email, u.name AS cliente FROM orders o " . sprintf($utenti, 'o') . " JOIN package_versions pv ON pv.id = o.package_version_id
                          WHERE pv.per_property = 0 AND o.quantity <> 1 AND o.created_at >= ? ORDER BY o.id DESC LIMIT 50", [self::iso($ora - 90 * self::GIORNO)]) as $r) {
            $righe[] = [$chi($r), 'ordine #' . (int) $r['id'] . ' del ' . Support::date($r['created_at']) . ' con ' . (int) $r['quantity'] . ' strutture su un piano da una (' . (string) $r['status'] . ')', $cliente($r)];
        }
        $aggiungi('Quantità non valida', 'alta', 'Un ordine di Essential o Plus con più di una struttura: il sito non apre il pagamento (o attiva una struttura sola). Controlla da dove arriva la richiesta.', $righe);

        // 2 ter · 9O · Addebiti SEPA rifiutati dalla banca (ultimi 30 giorni): il cliente ha l'email con «Paga con carta».
        if (Migrator::columnExists('orders', 'in_verifica_at')) {
            $righe = [];
            foreach (Db::all("SELECT o.*, u.email, u.name AS cliente FROM orders o " . sprintf($utenti, 'o') . "
                              WHERE o.status = 'failed' AND o.in_verifica_at IS NOT NULL AND o.updated_at >= ?
                                AND NOT EXISTS (SELECT 1 FROM orders o2 WHERE o2.account_id = o.account_id AND o2.status = 'paid' AND o2.id > o.id)
                              ORDER BY o.id DESC LIMIT 50", [self::iso($ora - 30 * self::GIORNO)]) as $r) {
                $righe[] = [$chi($r), 'addebito sul conto rifiutato il ' . Support::date($r['updated_at']) . ' · ' . Support::money((int) $r['amount_cents'], (string) $r['currency']) . ' + IVA', $cliente($r)];
            }
            $aggiungi('Addebiti SEPA non riusciti', 'media', 'La banca non ha confermato l\'addebito: il cliente ha ricevuto l\'email con «Paga con carta» e la guida è offline. Sparisce quando paga.', $righe);
        }

        // 3. Rinnovi non riusciti.
        $righe = [];
        foreach (Db::all("SELECT s.*, u.email, u.name AS cliente FROM subscriptions s " . sprintf($utenti, 's') . "
                          WHERE s.status = 'past_due' ORDER BY s.current_period_end LIMIT 50") as $r) {
            $righe[] = [$chi($r), 'rinnovo del ' . Support::date($r['current_period_end']) . ' non pagato', $cliente($r)];
        }
        $aggiungi('Rinnovi non riusciti', 'alta', 'Stripe riprova l\'addebito e scrive al cliente; intanto la guida è offline. Una telefonata spesso risolve.', $righe);

        // 4. Abbonamenti Stripe che dovevano rinnovarsi e risultano ancora attivi oltre la fine: il webhook non è arrivato.
        $righe = [];
        foreach (Db::all("SELECT s.*, u.email, u.name AS cliente FROM subscriptions s " . sprintf($utenti, 's') . "
                          WHERE s.provider = 'stripe' AND s.status IN ('active','trialing') AND s.cancel_at_period_end = 0
                            AND s.current_period_end <> '' AND s.current_period_end < ? ORDER BY s.current_period_end LIMIT 50", [self::iso($ora - 2 * self::GIORNO)]) as $r) {
            $righe[] = [$chi($r), 'doveva rinnovarsi il ' . Support::date($r['current_period_end']), $cliente($r)];
        }
        $aggiungi('Rinnovi senza notizie da Stripe', 'alta', 'Il rinnovo non è arrivato dal webhook. Controlla l\'abbonamento su Stripe e che il webhook mandi invoice.paid e customer.subscription.updated.', $righe);

        // 5. Webhook silenzioso: ordini recenti ma nessun evento ricevuto.
        $ultimo = (string) Db::val('SELECT MAX(processed_at) FROM webhook_events', [], '');
        $ordiniRecenti = (int) Db::val("SELECT COUNT(*) FROM orders WHERE provider = 'stripe' AND created_at >= ?", [self::iso($ora - 14 * self::GIORNO)], 0);
        $aggiungi('Webhook di Stripe silenzioso', 'media', 'Ci sono pagamenti avviati ma nessun evento ricevuto da 14 giorni. In Stripe controlla l\'indirizzo del webhook e il segreto.',
            Stripe::enabled() && $ordiniRecenti > 0 && ($ultimo === '' || $ultimo < self::iso($ora - 14 * self::GIORNO))
                ? [['Ultimo evento: ' . ($ultimo !== '' ? Support::date($ultimo) : 'mai'), $ordiniRecenti . ' pagamenti avviati negli ultimi 14 giorni', '/admin/diagnostica']] : []);

        // 6. Guide pubblicate che gli ospiti non vedono più.
        $righe = [];
        foreach (Db::all("SELECT p.id, p.name, p.account_id, u.email, u.name AS cliente FROM properties p " . sprintf($utenti, 'p') . "
                          WHERE p.status = 'published' AND p.is_demo = 0 AND p.archived_at IS NULL AND u.email NOT LIKE ? ORDER BY p.id", [self::demo()]) as $r) {
            if (Subscriptions::active((int) $r['account_id'])) continue;
            $fine = (string) Db::val('SELECT MAX(current_period_end) FROM subscriptions WHERE account_id = ?', [$r['account_id']], '');
            $righe[] = [$r['name'], $chi($r) . ($fine !== '' ? ' · scaduto il ' . Support::date($fine) : ''), $cliente($r)];
        }
        $aggiungi('Guide pubblicate ma offline', 'media', 'L\'abbonamento è finito: chi inquadra il QR non vede la guida. Da Scadenze puoi mandare un promemoria.', $righe);

        // 7. Clienti che pagano con dati di fatturazione incompleti.
        $righe = [];
        foreach (Db::all("SELECT DISTINCT a.*, u.email, u.name AS cliente, a.id AS account_id FROM subscriptions s JOIN accounts a ON a.id = s.account_id JOIN users u ON u.id = a.user_id
                          WHERE s.provider = 'stripe' AND s.status IN ('active','trialing','past_due') AND u.email NOT LIKE ?", [self::demo()]) as $r) {
            if (!Fatturazione::completa($r)) $righe[] = [$chi($r), 'mancano dati per la fattura elettronica', $cliente($r)];
        }
        $aggiungi('Clienti paganti senza dati di fatturazione completi', 'media', 'Al prossimo pagamento servono: scrivi al cliente o completa tu i dati entrando come cliente.', $righe);

        // 8. Errori del registro tecnico negli ultimi 7 giorni.
        $righe = [];
        foreach (array_slice(array_reverse(self::erroriRecenti(7)), 0, 10) as $e) {
            $righe[] = [(string) ($e['msg'] ?? ''), Support::date((string) ($e['t'] ?? '')) . ' · rif. ' . ($e['ref'] ?? ''), ''];
        }
        $aggiungi('Errori del sito negli ultimi 7 giorni', 'media', 'Il dettaglio è nel registro tecnico del giorno (storage/logs/app-AAAA-MM-GG.log), con lo stesso riferimento. Gli ultimi dieci, dal più recente.', $righe);

        // 9. Traduzioni suggerite non riuscite.
        $righe = [];
        if (Migrator::tableExists('translation_usage')) {
            $n = (int) Db::val("SELECT COUNT(*) FROM translation_usage WHERE outcome = 'errore' AND created_at >= ?", [self::iso($ora - 7 * self::GIORNO)], 0);
            if ($n > 0) $righe[] = [$n . ' richieste non riuscite negli ultimi 7 giorni', 'Amazon Translate', '/admin/traduzioni'];
        }
        $aggiungi('Traduzioni suggerite non riuscite', 'media', 'Di solito sono le chiavi AWS o il permesso translate:TranslateText. Prova la connessione in Impostazioni.', $righe);

        // 10. Codici sconto non ancora su Stripe.
        $righe = [];
        if (Sconti::disponibili() && Stripe::enabled()) {
            foreach (Db::all("SELECT * FROM discount_codes WHERE active = 1 AND stripe_coupon_id = '' AND valid_until >= ? ORDER BY id", [gmdate('Y-m-d')]) as $c) {
                $righe[] = [$c['code'], 'non si può usare finché non è su Stripe', '/admin/sconti/' . (int) $c['id']];
            }
        }
        $aggiungi('Codici sconto da sincronizzare', 'media', 'Apri il codice e premi «Riprova la sincronizzazione».', $righe);

        // 11. Sconti inviti diversi da quelli guadagnati.
        $righe = [];
        if (Inviti::disponibili()) {
            foreach (Db::all("SELECT a.id AS account_id, a.referral_applied_percent AS applicato, u.email, u.name AS cliente FROM accounts a JOIN users u ON u.id = a.user_id
                              WHERE a.referral_applied_percent > 0 OR a.id IN (SELECT referrer_account_id FROM referrals WHERE status = 'valido')") as $r) {
                $giusto = Inviti::percento((int) $r['account_id']);
                if ($giusto !== (int) $r['applicato']) $righe[] = [$chi($r), 'su Stripe ' . (int) $r['applicato'] . '%, guadagnato ' . $giusto . '%', $cliente($r)];
            }
        }
        $aggiungi('Sconti inviti da riallineare su Stripe', 'media', 'Si riallineano da soli al giro dei promemoria. Se restano, controlla le chiavi di Stripe.', $righe);

        // 12. I promemoria automatici non girano.
        $lock = MHW_APP . '/storage/richiami.lock';
        $giro = is_file($lock) ? (int) filemtime($lock) : 0;
        $clienti = (int) Db::val("SELECT COUNT(*) FROM users WHERE role = 'host' AND email NOT LIKE ?", [self::demo()], 0);
        $aggiungi('Promemoria automatici fermi', 'media', 'Partono mentre qualcuno usa il sito o con il cron. Se il sito è poco visitato, imposta il cron ogni ora (vedi DA-CONFIGURARE).',
            $clienti > 0 && $giro < $ora - 2 * self::GIORNO ? [['Ultimo giro: ' . ($giro ? Support::date(self::iso($giro)) : 'mai'), 'rinnovi, scadenze, bozze ed eventi non vengono avvisati', '/admin/scadenze']] : []);

        // 13. Abbonamenti dello staff che finiscono entro 30 giorni: non si rinnovano da soli.
        $righe = [];
        foreach (Db::all("SELECT s.*, u.email, u.name AS cliente FROM subscriptions s " . sprintf($utenti, 's') . "
                          WHERE s.provider = 'manuale' AND s.status IN ('active','trialing') AND s.current_period_end > ? AND s.current_period_end <= ?
                          ORDER BY s.current_period_end", [self::iso($ora), self::iso($ora + 30 * self::GIORNO)]) as $r) {
            $righe[] = [$chi($r), 'finisce il ' . Support::date($r['current_period_end']), $cliente($r)];
        }
        $aggiungi('Abbonamenti dello staff in scadenza', 'bassa', 'Non si rinnovano da soli: decidi se allungarli o lasciarli finire. Il cliente riceve l\'avviso 30 e 7 giorni prima.', $righe);

        // 14. Hanno pagato ma non hanno pubblicato niente da più di 14 giorni.
        $righe = [];
        foreach (Db::all("SELECT s.*, u.email, u.name AS cliente FROM subscriptions s " . sprintf($utenti, 's') . "
                          WHERE s.provider = 'stripe' AND s.status IN ('active','trialing') AND s.created_at <= ? AND u.email NOT LIKE ?
                            AND NOT EXISTS (SELECT 1 FROM properties p WHERE p.account_id = s.account_id AND p.status = 'published' AND p.archived_at IS NULL)",
                         [self::iso($ora - 14 * self::GIORNO), self::demo()]) as $r) {
            $righe[] = [$chi($r), 'abbonato dal ' . Support::date($r['created_at']) . ', nessuna guida online', $cliente($r)];
        }
        $aggiungi('Pagato, ma nessuna guida online', 'bassa', 'Stanno pagando senza usarla: un aiuto a finire la guida evita una disdetta.', $righe);

        // 15. Pagamenti avviati e mai conclusi (ultimi 30 giorni).
        $righe = [];
        foreach (Db::all("SELECT o.*, u.email, u.name AS cliente FROM orders o " . sprintf($utenti, 'o') . "
                          WHERE o.status IN ('pending','expired') AND o.created_at >= ? AND o.created_at <= ? AND u.email NOT LIKE ?
                            AND NOT EXISTS (SELECT 1 FROM orders o2 WHERE o2.account_id = o.account_id AND o2.status = 'paid' AND o2.created_at >= o.created_at)
                          ORDER BY o.id DESC LIMIT 50", [self::iso($ora - 30 * self::GIORNO), self::iso($ora - 2 * self::GIORNO), self::demo()]) as $r) {
            $righe[] = [$chi($r), 'pagamento avviato il ' . Support::date($r['created_at']) . ', mai concluso', $cliente($r)];
        }
        $aggiungi('Pagamenti avviati e abbandonati', 'bassa', 'Si sono fermati sulla pagina di Stripe. Un messaggio può aiutare a capire perché.', $righe);

        // 16. Email non confermate da più di 7 giorni.
        $righe = [];
        foreach (Db::all("SELECT u.email, u.name AS cliente, u.created_at, a.id AS account_id FROM users u JOIN accounts a ON a.user_id = u.id
                          WHERE u.role = 'host' AND u.email_verified_at IS NULL AND u.created_at <= ? AND u.email NOT LIKE ? ORDER BY u.id DESC LIMIT 50",
                         [self::iso($ora - 7 * self::GIORNO), self::demo()]) as $r) {
            $righe[] = [$chi($r), 'registrato il ' . Support::date($r['created_at']), $cliente($r)];
        }
        $aggiungi('Email non confermate da più di 7 giorni', 'bassa', 'Forse l\'email è finita nello spam, o l\'indirizzo è sbagliato.', $righe);

        // 17. Una guida = un'unità ricettiva: guide online senza CIN, o con lo stesso CIN di un'altra guida.
        $righe = []; $visti = [];
        if (Migrator::columnExists('properties', 'cin')) {
            foreach (Db::all("SELECT p.id, p.name, p.cin, p.account_id, p.status, u.email, u.name AS cliente FROM properties p " . sprintf($utenti, 'p') . "
                              WHERE p.is_demo = 0 AND p.archived_at IS NULL AND u.email NOT LIKE ? ORDER BY p.id", [self::demo()]) as $r) {
                $n = Properties::cinNorm((string) $r['cin']);
                if ($n === '') { if ($r['status'] === 'published') $righe[] = [$chi($r), '«' . $r['name'] . '» è online senza CIN', $cliente($r)]; continue; }
                if (isset($visti[$n])) $righe[] = [$chi($r), '«' . $r['name'] . '» ha lo stesso CIN (' . $n . ') di «' . $visti[$n] . '»', $cliente($r)];
                else $visti[$n] = (string) $r['name'];
            }
        }
        $aggiungi('Guide senza CIN o con CIN ripetuto', 'media', 'Ogni guida è un\'unità ricettiva con il suo CIN. Le guide già online senza CIN restano online, ma per ripubblicarle il CIN serve. Un CIN ripetuto può essere una struttura passata a un altro gestore: verifica con il cliente.', $righe);

        // 18. Foto e PDF delle guide su un indirizzo pubblico del bucket (6M): senza scadenza e senza noindex.
        $righe = [];
        if (self::archivioPubblico()) $righe[] = ['Archivio delle foto', self::AVVISO_ARCHIVIO, '/admin/impostazioni'];
        $aggiungi('Foto e PDF delle guide su un indirizzo pubblico', 'media', 'Le foto e i PDF delle guide devono passare dal sito, che li serve con il blocco per i motori.', $righe);

        // 19. Guide online con un codice di accesso (6M): solo informativo, non si tolgono e non si bloccano.
        $righe = [];
        foreach (self::guideConCodici() as $g) {
            $righe[] = [$g['chi'], '«' . $g['nome'] . '»: ' . ($g['confermato'] ? 'confermata a proprio rischio il ' . Support::date($g['confermato']) : 'pubblicata prima del controllo')
                        . ' · guida: ' . $g['link'], $g['cliente']];
        }
        $aggiungi('Guide pubblicate con un codice di accesso', 'bassa', 'Solo per sapere: l\'host ha scelto di pubblicarle. Non vanno tolte né bloccate; se vuoi, suggerisci di comunicare i codici in privato.', $righe);

        $ordine = ['alta' => 0, 'media' => 1, 'bassa' => 2];
        usort($voci, fn($a, $b) => $ordine[$a['gravita']] <=> $ordine[$b['gravita']]);
        return ['voci' => $voci, 'superati' => $superati];
    }

    /** Il testo dell'avviso sull'indirizzo pubblico del bucket (Impostazioni e Anomalie). */
    public const AVVISO_ARCHIVIO = 'Foto e PDF delle guide sono raggiungibili senza scadenza e senza il blocco per i motori: lascia vuoto questo campo.';

    /** Foto e PDF su S3 con un indirizzo pubblico: non passano da /media/ e perdono il noindex. */
    public static function archivioPubblico(): bool
    {
        $c = Config::get('storage') ?? [];
        return ($c['driver'] ?? '') === 's3' && trim((string) ($c['s3']['public_base_url'] ?? '')) !== '';
    }

    /**
     * Le guide online che contengono un codice di accesso, con la data dell'ultima conferma «a mio rischio»
     * (Auth::audit 'guida.codici_confermati'). @return array<int,array{nome:string,chi:string,cliente:string,link:string,confermato:?string}>
     */
    public static function guideConCodici(): array
    {
        $out = [];
        foreach (Db::all("SELECT p.id, p.name, p.slug, p.account_id, u.email, u.name AS cliente FROM properties p JOIN accounts a ON a.id = p.account_id
                          JOIN users u ON u.id = a.user_id WHERE p.status = 'published' AND p.archived_at IS NULL ORDER BY p.id DESC LIMIT 300") as $p) {
            $snap = Guide::published((int) $p['id']);
            if (!$snap || !Sicurezza::campiConCodice(Guide::normalize($snap))) continue;
            $conferma = Db::val("SELECT MAX(created_at) FROM audit_log WHERE action = 'guida.codici_confermati' AND meta LIKE ?", ['%"property":' . (int) $p['id'] . ',%']);
            $out[] = ['nome' => (string) $p['name'], 'chi' => ($p['cliente'] !== '' ? $p['cliente'] . ' · ' : '') . $p['email'],
                      'cliente' => '/admin/cliente/' . (int) $p['account_id'], 'link' => Support::baseUrl() . '/g/' . $p['slug'], 'confermato' => $conferma ?: null];
        }
        return $out;
    }

    /** Quante anomalie per gravità, per il quadro. */
    public static function contaAnomalie(): array
    {
        $n = ['alta' => 0, 'media' => 0, 'bassa' => 0];
        foreach (self::anomalie()['voci'] as $v) $n[$v['gravita']]++;
        return $n;
    }

    /** Le righe ERROR del registro tecnico degli ultimi $giorni giorni (8I: dai file del giorno). */
    public static function erroriRecenti(int $giorni): array
    {
        $da = self::iso(time() - $giorni * self::GIORNO);
        $out = [];
        foreach (Registri::righe('app', $giorni + 1) as $riga) {
            $e = json_decode($riga, true);
            if (is_array($e) && ($e['lvl'] ?? '') === 'ERROR' && (string) ($e['t'] ?? '') >= $da) $out[] = $e;
        }
        return $out;
    }

    // ----------------------------------------------------------------- prospetti

    /** I piani del prospetto vendite, nell'ordine del listino (Portfolio 2 e 3 contano come Portfolio). */
    public const PIANI_VENDITE = ['essential' => 'Essential', 'plus' => 'Plus', 'portfolio' => 'Portfolio'];

    private static function famigliaPiano(string $code): string
    {
        return str_starts_with($code, 'portfolio') ? 'portfolio' : (isset(self::PIANI_VENDITE[$code]) ? $code : 'altro');
    }

    /**
     * Che piani si comprano: ordini pagati su Stripe degli ultimi $mesi mesi, mese per mese e per piano
     * (nuovi abbonamenti e cambi di piano, con l'incasso), gli abbonamenti attivi di adesso per piano,
     * e gli ultimi acquisti. Clienti di esempio esclusi; importi IVA esclusa.
     */
    public static function vendite(int $mesi): array
    {
        $mesi = max(1, min(24, $mesi));
        $primo = strtotime(gmdate('Y-m-01'));
        $vuoto = fn() => array_fill_keys(array_keys(self::PIANI_VENDITE), 0) + ['altro' => 0];
        $perMese = [];
        for ($i = $mesi - 1; $i >= 0; $i--) {
            $m = gmdate('Y-m', strtotime("-$i month", $primo));
            $perMese[$m] = ['mese' => $m, 'nuovi' => $vuoto(), 'incasso' => $vuoto(), 'cambi' => $vuoto()];
        }
        $da = array_key_first($perMese) . '-01';
        $totali = ['nuovi' => $vuoto(), 'incasso' => $vuoto(), 'cambi' => $vuoto(), 'strutture' => $vuoto()];
        $scontiS = self::scontiSessioni();
        foreach (Db::all("SELECT o.kind, o.amount_cents, o.discount_cents, o.provider_session_id, o.quantity, o.created_at, p.code FROM orders o
                          JOIN package_versions pv ON pv.id = o.package_version_id JOIN packages p ON p.id = pv.package_id
                          JOIN accounts a ON a.id = o.account_id JOIN users u ON u.id = a.user_id
                          WHERE o.status = 'paid' AND o.provider = 'stripe' AND o.created_at >= ? AND u.email NOT LIKE ?" . self::senzaProva('a.id'), [$da, self::demo()]) as $o) {
            $m = substr((string) $o['created_at'], 0, 7);
            if (!isset($perMese[$m])) continue;
            $f = self::famigliaPiano((string) $o['code']);
            if ($o['kind'] === 'change') { $perMese[$m]['cambi'][$f]++; $totali['cambi'][$f]++; }
            else { $perMese[$m]['nuovi'][$f]++; $totali['nuovi'][$f]++; $totali['strutture'][$f] += max(1, (int) $o['quantity']); }
            $netto = max(0, (int) $o['amount_cents'] - ($scontiS[(string) $o['provider_session_id']] ?? (int) $o['discount_cents']));   // 9O: dopo lo sconto
            $perMese[$m]['incasso'][$f] += $netto; $totali['incasso'][$f] += $netto;
        }
        // Adesso: abbonamenti attivi per piano (Stripe e staff a parte) e strutture.
        $attivi = [];
        foreach (array_keys(self::PIANI_VENDITE) as $k) $attivi[$k] = ['stripe' => 0, 'staff' => 0, 'strutture' => 0];
        foreach (Db::all("SELECT s.provider, s.quantity, s.account_id, p.code FROM subscriptions s
                          JOIN package_versions pv ON pv.id = s.package_version_id JOIN packages p ON p.id = pv.package_id
                          JOIN accounts a ON a.id = s.account_id JOIN users u ON u.id = a.user_id
                          WHERE s.status IN ('active','trialing') AND s.provider <> 'dimostrazione' AND u.email NOT LIKE ?" . self::senzaProva('a.id'), [self::demo()]) as $s) {
            $f = self::famigliaPiano((string) $s['code']);
            if (!isset($attivi[$f])) continue;
            $attivi[$f][$s['provider'] === 'stripe' ? 'stripe' : 'staff']++;
            $attivi[$f]['strutture'] += max(1, (int) $s['quantity']);
        }
        $ultimi = Db::all("SELECT o.id, o.kind, o.amount_cents, o.quantity, o.created_at, o.account_id, p.code, p.name AS piano, u.email, u.name AS cliente
                           FROM orders o JOIN package_versions pv ON pv.id = o.package_version_id JOIN packages p ON p.id = pv.package_id
                           JOIN accounts a ON a.id = o.account_id JOIN users u ON u.id = a.user_id
                           WHERE o.status = 'paid' AND o.provider = 'stripe' AND u.email NOT LIKE ?" . self::senzaProva('a.id') . " ORDER BY o.created_at DESC, o.id DESC LIMIT 15", [self::demo()]);
        return ['mesi' => array_values($perMese), 'totali' => $totali, 'attivi' => $attivi, 'ultimi' => $ultimi];
    }

    // -------------------------------------------------------------------- cassa

    /** Le vie di pagamento che entrano nell'incasso: Stripe e i bonifici segnati dall'amministrazione (9N). */
    public const VIE_INCASSO = ['stripe' => 'Stripe', 'bonifico' => 'Bonifico'];

    /**
     * 9N · I pagamenti incassati tra $da (compreso) e $a (escluso), date ISO: la fonte unica di Cassa, Prospetti e Quadro.
     * Ordini pagati (Stripe e bonifici: nuovi abbonamenti e cambi di piano), rinnovi (fatture «subscription_cycle» arrivate
     * col webhook, che non passano dagli ordini), conguagli (fatture «subscription_update» pagate subito: strutture Portfolio
     * aggiunte durante l'anno) e rimborsi (tabella rimborsi, con il segno meno). Clienti di esempio e di
     * prova esclusi. Importi in centesimi: imponibile, IVA (da Stripe o scritta per il bonifico; null se Stripe non l'ha
     * detta), totale. @return list<array> dal più vecchio
     */
    /**
     * 9O · Lo sconto davvero concesso da Stripe in ogni sessione di Checkout (total_details.amount_discount), per sessione:
     * l'imponibile incassato di un ordine è il prezzo (orders.amount_cents) meno questo sconto.
     */
    public static function scontiSessioni(): array
    {
        static $s = null;
        if ($s !== null) return $s;
        $s = [];
        foreach (Db::all("SELECT payload FROM webhook_events WHERE provider = 'stripe' AND kind IN ('checkout.session.completed','checkout.session.async_payment_succeeded')") as $r) {
            $x = (json_decode((string) $r['payload'], true) ?: [])['data']['object'] ?? [];
            if (isset($x['id'], $x['total_details']['amount_discount'])) $s[(string) $x['id']] = (int) $x['total_details']['amount_discount'];
        }
        return $s;
    }

    /** 9O · L'imponibile incassato dai pagamenti Stripe (nuovi e cambi), dopo gli sconti. */
    public static function incassatoStripe(string $filtro = ''): int
    {
        $s = self::scontiSessioni(); $tot = 0;
        foreach (Db::all("SELECT amount_cents, discount_cents, provider_session_id FROM orders WHERE status = 'paid' AND provider = 'stripe'" . $filtro) as $o) {
            $tot += max(0, (int) $o['amount_cents'] - ($s[(string) $o['provider_session_id']] ?? (int) $o['discount_cents']));
        }
        return $tot;
    }

    public static function pagamenti(string $da, string $a): array
    {
        $out = [];
        $conIva = Migrator::columnExists('orders', 'iva_cents');
        $conCodice = Migrator::columnExists('orders', 'code');
        // L'IVA e lo sconto dei pagamenti Stripe: dalla sessione di Checkout (total_details.amount_tax e amount_discount).
        // 9O · L'imponibile incassato è il prezzo meno lo sconto davvero concesso da Stripe (invito, giudizio, partner, codice).
        $ivaSessione = []; $scontoSessione = [];
        foreach (Db::all("SELECT payload FROM webhook_events WHERE provider = 'stripe' AND kind IN ('checkout.session.completed','checkout.session.async_payment_succeeded') AND processed_at >= ?",
                         [self::iso((int) strtotime($da) - 40 * self::GIORNO)]) as $r) {
            $s = (json_decode((string) $r['payload'], true) ?: [])['data']['object'] ?? [];
            if (isset($s['id'], $s['total_details']['amount_tax'])) $ivaSessione[(string) $s['id']] = (int) $s['total_details']['amount_tax'];
            if (isset($s['id'], $s['total_details']['amount_discount'])) $scontoSessione[(string) $s['id']] = (int) $s['total_details']['amount_discount'];
        }
        foreach (Db::all("SELECT o.id, o.kind, o.provider, o.amount_cents, o.discount_cents, o.sconti, o.quantity, o.created_at, o.account_id, o.provider_session_id,
                                 " . (Migrator::columnExists('orders', 'offerta_id') ? 'o.offerta_id' : 'NULL AS offerta_id') . ",
                                 " . ($conIva ? 'o.iva_cents, o.riferimento' : "NULL AS iva_cents, '' AS riferimento") . ", " . ($conCodice ? 'o.code' : "'' AS code") . ",
                                 pk.name AS piano, u.email, u.name AS cliente
                          FROM orders o JOIN package_versions pv ON pv.id = o.package_version_id JOIN packages pk ON pk.id = pv.package_id
                          JOIN accounts a ON a.id = o.account_id JOIN users u ON u.id = a.user_id
                          WHERE o.status = 'paid' AND o.provider IN ('stripe','bonifico') AND o.created_at >= ? AND o.created_at < ? AND u.email NOT LIKE ?" . self::senzaProva('a.id') . "
                          ORDER BY o.created_at, o.id", [$da, $a, self::demo()]) as $o) {
            $iva = $o['iva_cents'] !== null ? (int) $o['iva_cents'] : ($ivaSessione[(string) $o['provider_session_id']] ?? null);
            $sconto = $scontoSessione[(string) $o['provider_session_id']] ?? (int) $o['discount_cents'];
            $netto = max(0, (int) $o['amount_cents'] - $sconto);
            $out[] = ['data' => (string) $o['created_at'], 'tipo' => $o['kind'] === 'change' ? 'cambio' : 'nuovo', 'via' => (string) $o['provider'],
                      'imponibile' => $netto, 'iva' => $iva, 'totale' => $netto + (int) $iva,
                      'codice' => (string) $o['code'] !== '' ? (string) $o['code'] : Codici::pagamento((int) $o['id']),
                      'account_id' => (int) $o['account_id'], 'cliente' => (string) $o['cliente'], 'email' => (string) $o['email'],
                      'piano' => (string) $o['piano'] . (!empty($o['offerta_id']) ? ' su misura' : '') . ((int) $o['quantity'] > 1 ? ' · ' . (int) $o['quantity'] . ' strutture' : ''),
                      'sconti' => $sconto > 0 ? trim(Support::money($sconto) . ' ' . (string) $o['sconti']) : '',
                      'riferimento' => (string) $o['riferimento'], 'fattura' => ''];
        }
        // I rinnovi: dalle fatture arrivate col webhook (IVA esclusa nell'imponibile).
        foreach (Db::all("SELECT processed_at, payload FROM webhook_events WHERE provider = 'stripe' AND kind = 'invoice.paid' AND processed_at >= ? AND processed_at < ?", [$da, $a]) as $r) {
            $inv = (json_decode((string) $r['payload'], true) ?: [])['data']['object'] ?? [];
            // 9O · Anche i conguagli pagati (subscription_update con un importo: strutture Portfolio in più a metà anno); i crediti no.
            $motivo = (string) ($inv['billing_reason'] ?? '');
            if ($motivo !== 'subscription_cycle' && !($motivo === 'subscription_update' && (int) ($inv['amount_paid'] ?? 0) > 0)) continue;
            $sub = Db::one("SELECT s.account_id, s.quantity, " . (Migrator::columnExists('subscriptions', 'offerta_id') ? 's.offerta_id' : 'NULL AS offerta_id') . ", "
                            . (Migrator::columnExists('subscriptions', 'omaggio_fino') ? 's.omaggio_fino' : 'NULL AS omaggio_fino') . ", pk.name AS piano, u.email, u.name AS cliente FROM subscriptions s JOIN accounts a ON a.id = s.account_id
                            JOIN users u ON u.id = a.user_id JOIN package_versions pv ON pv.id = s.package_version_id JOIN packages pk ON pk.id = pv.package_id
                            WHERE s.provider_subscription_id = ? ORDER BY s.id DESC", [(string) ($inv['subscription'] ?? '')])
                ?: Db::one('SELECT a.id AS account_id, 1 AS quantity, \'\' AS piano, u.email, u.name AS cliente FROM accounts a JOIN users u ON u.id = a.user_id
                            WHERE a.stripe_customer_id = ? AND a.stripe_customer_id <> \'\'', [(string) ($inv['customer'] ?? '')]);
            if ($sub && (str_ends_with((string) $sub['email'], substr(self::demo(), 1)) || (self::conProva() && (int) Db::val('SELECT is_test FROM accounts WHERE id = ?', [(int) $sub['account_id']], 0)))) continue;
            $netto = (int) ($inv['total_excluding_tax'] ?? $inv['subtotal'] ?? $inv['amount_paid'] ?? 0);
            $totale = (int) ($inv['total'] ?? $inv['amount_paid'] ?? $netto);
            $iva = isset($inv['tax']) ? (int) $inv['tax'] : (isset($inv['total_excluding_tax'], $inv['total']) ? $totale - $netto : null);
            $sconto = array_sum(array_map(fn($d) => (int) ($d['amount'] ?? 0), (array) ($inv['total_discount_amounts'] ?? [])));
            // 9O · Il primo addebito alla fine dei mesi gratis di un codice omaggio è una fattura «di ciclo», ma è un cliente nuovo.
            $inizioF = (int) ($inv['lines']['data'][0]['period']['start'] ?? 0);
            $dopoOmaggio = $motivo === 'subscription_cycle' && !empty($sub['omaggio_fino']) && $inizioF > 0 && abs($inizioF - (int) strtotime((string) $sub['omaggio_fino'])) < 2 * self::GIORNO;
            $out[] = ['data' => (string) $r['processed_at'], 'tipo' => $motivo === 'subscription_update' ? 'cambio' : ($dopoOmaggio ? 'nuovo' : 'rinnovo'), 'via' => 'stripe', 'imponibile' => $netto, 'iva' => $iva, 'totale' => $netto + (int) $iva,
                      'codice' => Codici::perFattura((string) ($inv['id'] ?? '')), 'account_id' => (int) ($sub['account_id'] ?? 0),
                      'cliente' => (string) ($sub['cliente'] ?? ''), 'email' => (string) ($sub['email'] ?? ''),
                      'piano' => (string) ($sub['piano'] ?? '') . (!empty($sub['offerta_id']) ? ' su misura' : '') . ((int) ($sub['quantity'] ?? 1) > 1 ? ' · ' . (int) $sub['quantity'] . ' strutture' : ''),
                      'sconti' => $sconto > 0 ? Support::money($sconto) : '', 'riferimento' => '', 'fattura' => (string) ($inv['number'] ?? $inv['id'] ?? '')];
        }
        // I rimborsi fatti dall'amministrazione (importo IVA compresa, come su Stripe).
        if (Migrator::tableExists('rimborsi')) {
            foreach (Db::all("SELECT r.*, u.email, u.name AS cliente FROM rimborsi r JOIN accounts a ON a.id = r.account_id JOIN users u ON u.id = a.user_id
                              WHERE r.stato <> 'fallito' AND r.created_at >= ? AND r.created_at < ? AND u.email NOT LIKE ?" . self::senzaProva('a.id') . ' ORDER BY r.created_at',
                             [$da, $a, self::demo()]) as $r) {
                $out[] = ['data' => (string) $r['created_at'], 'tipo' => 'rimborso', 'via' => 'stripe', 'imponibile' => 0, 'iva' => 0, 'totale' => -(int) $r['amount_cents'],
                          'codice' => (string) ($r['code'] ?? ''), 'account_id' => (int) $r['account_id'], 'cliente' => (string) $r['cliente'], 'email' => (string) $r['email'],
                          'piano' => '', 'sconti' => '', 'riferimento' => (string) $r['motivo'], 'fattura' => (string) $r['invoice_id']];
            }
        }
        usort($out, fn($x, $y) => strcmp($x['data'], $y['data']));
        return $out;
    }

    /**
     * 9N · La cassa di un anno, mese per mese: nuovi, cambi, rinnovi, imponibile Stripe e bonifici, IVA, totale, rimborsi.
     * Somma di Gestione::pagamenti (la stessa fonte di Prospetti). @return array{mesi:list<array>,totale:array,senza_iva:int}
     */
    public static function cassa(int $anno): array
    {
        $vuoto = ['nuovi' => 0, 'cambi' => 0, 'rinnovi' => 0, 'stripe' => 0, 'bonifico' => 0, 'imponibile' => 0, 'iva' => 0, 'totale' => 0, 'rimborsi' => 0, 'netto' => 0, 'senza_iva' => 0];
        $mesi = [];
        for ($m = 1; $m <= 12; $m++) $mesi[sprintf('%04d-%02d', $anno, $m)] = ['mese' => sprintf('%04d-%02d', $anno, $m)] + $vuoto;
        $totale = $vuoto;
        foreach (self::pagamenti(sprintf('%04d-01-01', $anno), sprintf('%04d-01-01', $anno + 1)) as $p) {
            $k = substr($p['data'], 0, 7);
            if (!isset($mesi[$k])) continue;
            foreach ([&$mesi[$k], &$totale] as &$riga) {
                if ($p['tipo'] === 'rimborso') { $riga['rimborsi'] += -$p['totale']; $riga['netto'] += $p['totale']; continue; }
                $riga[['nuovo' => 'nuovi', 'cambio' => 'cambi', 'rinnovo' => 'rinnovi'][$p['tipo']]]++;
                $riga[$p['via'] === 'bonifico' ? 'bonifico' : 'stripe'] += $p['imponibile'];
                $riga['imponibile'] += $p['imponibile'];
                $riga['iva'] += (int) $p['iva'];
                $riga['totale'] += $p['totale'];
                $riga['netto'] += $p['totale'];
                if ($p['iva'] === null) $riga['senza_iva']++;
            }
            unset($riga);
        }
        return ['mesi' => array_values($mesi), 'totale' => $totale];
    }

    /** Gli ultimi 12 mesi (dal più vecchio), con i conti di ogni mese. */
    public static function mesi(): array
    {
        $mesi = [];
        $primo = strtotime(gmdate('Y-m-01'));
        for ($i = 11; $i >= 0; $i--) {
            $m = gmdate('Y-m', strtotime("-$i month", $primo));
            $mesi[$m] = ['mese' => $m, 'registrati' => 0, 'nuovi' => 0, 'incasso_nuovi' => 0, 'incasso_cambi' => 0, 'incasso_rinnovi' => 0, 'rinnovi' => 0, 'persi' => 0];
        }
        $da = array_key_first($mesi) . '-01';
        $metti = function (string $m, string $k, int $v) use (&$mesi) { if (isset($mesi[$m])) $mesi[$m][$k] += $v; };

        foreach (Db::all("SELECT substr(created_at, 1, 7) AS m, COUNT(*) AS n FROM users WHERE role = 'host' AND email NOT LIKE ? AND created_at >= ?" . self::utentiVeri('id') . " GROUP BY substr(created_at, 1, 7)",
                         [self::demo(), $da]) as $r) $metti($r['m'], 'registrati', (int) $r['n']);
        foreach (Db::all("SELECT MIN(created_at) AS primo FROM orders WHERE status = 'paid' AND provider IN ('stripe','bonifico') AND kind = 'new'" . self::senzaProva('account_id') . " GROUP BY account_id") as $r) {
            $metti(substr((string) $r['primo'], 0, 7), 'nuovi', 1);
        }
        // 9N · Gli incassi dalla stessa fonte della Cassa (Gestione::pagamenti): ordini Stripe e bonifici, rinnovi dalle fatture, IVA esclusa.
        foreach (self::pagamenti($da, self::iso(time() + self::GIORNO)) as $p) {
            if ($p['tipo'] === 'rimborso') continue;
            $m = substr($p['data'], 0, 7);
            $metti($m, ['nuovo' => 'incasso_nuovi', 'cambio' => 'incasso_cambi', 'rinnovo' => 'incasso_rinnovi'][$p['tipo']], $p['imponibile']);
            if ($p['tipo'] === 'rinnovo') $metti($m, 'rinnovi', 1);
        }
        // Persi: abbonamenti finiti nel mese senza che ne sia arrivato un altro dopo.
        foreach (Db::all("SELECT s.id, s.account_id, s.current_period_end FROM subscriptions s JOIN accounts a ON a.id = s.account_id JOIN users u ON u.id = a.user_id
                          WHERE s.provider <> 'dimostrazione' AND u.email NOT LIKE ?" . self::senzaProva('a.id') . " AND s.current_period_end >= ? AND s.current_period_end < ?
                            AND (s.status NOT IN ('active','trialing') OR s.cancel_at_period_end = 1 OR s.provider <> 'stripe')",
                         [self::demo(), $da, self::iso(time())]) as $r) {
            if (Db::val('SELECT id FROM subscriptions WHERE account_id = ? AND id <> ? AND current_period_end > ?', [$r['account_id'], $r['id'], $r['current_period_end']])) continue;
            $metti(substr((string) $r['current_period_end'], 0, 7), 'persi', 1);
        }
        foreach ($mesi as &$m) $m['incasso'] = $m['incasso_nuovi'] + $m['incasso_cambi'] + $m['incasso_rinnovi'];
        unset($m);
        return array_values($mesi);
    }

    /** Il quadro di adesso: ricavo ricorrente, rinnovi attesi, piani, conversione, inviti, sconti, traduzioni. */
    public static function adesso(): array
    {
        $ora = time();
        $attivi = Db::all("SELECT s.*, pk.name AS piano, pk.sort FROM subscriptions s JOIN accounts a ON a.id = s.account_id JOIN users u ON u.id = a.user_id
                           JOIN package_versions pv ON pv.id = s.package_version_id JOIN packages pk ON pk.id = pv.package_id
                           WHERE s.provider = 'stripe' AND s.status IN ('active','trialing') AND s.current_period_end > ? AND u.email NOT LIKE ?" . self::senzaProva('a.id') . " ORDER BY pk.sort",
                          [self::iso($ora), self::demo()]);
        $arr = 0; $aRischio = 0; $piani = []; $rinnovi = ['30' => [0, 0], '90' => [0, 0]];
        foreach ($attivi as $s) {
            $pv = Plans::version((int) $s['package_version_id']);
            $annuo = Offerte::prezzo($s) ?? ($pv ? Plans::price($pv, max(1, (int) ($s['quantity'] ?? 1))) : 0);   // 9N · 10 bis
            $p = &$piani[$s['piano']];
            $p ??= ['piano' => $s['piano'], 'clienti' => 0, 'strutture' => 0, 'arr' => 0, 'disdetti' => 0];
            $p['clienti']++; $p['strutture'] += max(1, (int) ($s['quantity'] ?? 1));
            if ((int) $s['cancel_at_period_end'] === 1) { $aRischio += $annuo; $p['disdetti']++; unset($p); continue; }
            $arr += $annuo; $p['arr'] += $annuo;
            unset($p);
            $giorni = ((int) strtotime((string) $s['current_period_end']) - $ora) / self::GIORNO;
            [$importo] = self::importoRinnovo($s);
            foreach ([30, 90] as $g) if ($giorni <= $g) { $rinnovi[(string) $g][0]++; $rinnovi[(string) $g][1] += $importo; }
        }
        $manuali = (int) Db::val("SELECT COUNT(DISTINCT account_id) FROM subscriptions WHERE provider = 'manuale' AND status IN ('active','trialing') AND current_period_end > ?" . self::senzaProva('account_id'), [self::iso($ora)], 0);

        $registrati = (int) Db::val("SELECT COUNT(*) FROM users WHERE role = 'host' AND email NOT LIKE ?" . self::utentiVeri('id'), [self::demo()], 0);
        $conGuida = (int) Db::val("SELECT COUNT(DISTINCT p.account_id) FROM properties p JOIN accounts a ON a.id = p.account_id JOIN users u ON u.id = a.user_id
                                   WHERE p.is_demo = 0 AND u.email NOT LIKE ?" . self::senzaProva('a.id'), [self::demo()], 0);
        $pagato = (int) Db::val("SELECT COUNT(DISTINCT account_id) FROM orders WHERE status = 'paid' AND provider = 'stripe'" . self::senzaProva('account_id'), [], 0);
        $conversione = [['Registrati', $registrati], ['Hanno creato una guida', $conGuida], ['Hanno pagato almeno una volta', $pagato],
                        ['Abbonati adesso', count(array_unique(array_column($attivi, 'account_id')))]];

        $inviti = null;
        if (Inviti::disponibili() || Migrator::tableExists('referrals')) {
            $stati = array_column(Db::all('SELECT status, COUNT(*) AS n FROM referrals GROUP BY status'), 'n', 'status');
            $concesso = 0;
            foreach (Db::all("SELECT meta FROM audit_log WHERE action = 'referral.redeemed'") as $r) $concesso += (int) ((json_decode((string) $r['meta'], true) ?: [])['discount_cents'] ?? 0);
            $primi = Db::all("SELECT r.referrer_account_id AS account_id, u.email, u.name AS cliente,
                                     SUM(CASE WHEN r.status IN ('valido','usato','oltre') THEN 1 ELSE 0 END) AS paganti, COUNT(*) AS invitati
                              FROM referrals r JOIN accounts a ON a.id = r.referrer_account_id JOIN users u ON u.id = a.user_id
                              GROUP BY r.referrer_account_id, u.email, u.name ORDER BY paganti DESC, invitati DESC LIMIT 10");
            foreach ($primi as &$x) $x['percento'] = Inviti::percento((int) $x['account_id']);
            unset($x);
            $inviti = ['attivi' => Inviti::disponibili(), 'stati' => $stati, 'concesso' => $concesso, 'primi' => $primi,
                       'amico' => (int) Db::val("SELECT COALESCE(SUM(o.discount_cents),0) FROM orders o JOIN referrals r ON r.order_id = o.id WHERE o.status = 'paid'", [], 0)];
        }

        $sconti = null;
        if (Sconti::disponibili()) {
            $sconti = Db::all("SELECT c.id, c.code, c.kind, c.value, COUNT(r.id) AS usi, COALESCE(SUM(r.discount_cents),0) AS cents
                               FROM discount_codes c LEFT JOIN discount_redemptions r ON r.discount_code_id = c.id
                               GROUP BY c.id, c.code, c.kind, c.value HAVING COUNT(r.id) > 0 ORDER BY cents DESC LIMIT 10");
        }

        $traduzioni = null;
        if (Migrator::tableExists('translation_usage')) {
            $anno = 0.0;
            foreach (Db::all("SELECT month, SUM(chars) AS c FROM translation_usage WHERE outcome = 'ok' GROUP BY month ORDER BY month DESC LIMIT 12") as $m) {
                $anno += Traduttore::costoUsd((int) $m['c'], Traduttore::gratuitoAttivo($m['month'] . '-01'));
            }
            $usati = Traduttore::usati();
            $traduzioni = ['caratteri' => $usati, 'mese' => Traduttore::inEuro(Traduttore::costoUsd($usati, Traduttore::gratuitoAttivo())), 'anno' => Traduttore::inEuro($anno)];
        }

        return ['arr' => $arr, 'a_rischio' => $aRischio, 'manuali' => $manuali, 'piani' => array_values($piani), 'rinnovi' => $rinnovi,
                'conversione' => $conversione, 'inviti' => $inviti, 'sconti' => $sconti, 'traduzioni' => $traduzioni];
    }

    // ---------------------------------------------------------------------- CSV

    /** Un CSV per Excel in italiano: punto e virgola, BOM UTF-8. Le celle che iniziano con = + - @ diventano testo. */
    public static function csv(string $nome, array $intestazioni, array $righe): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . preg_replace('/[^a-z0-9-]/', '', $nome) . '-' . gmdate('Y-m-d') . '.csv"');
        header('Cache-Control: no-store');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        $pulisci = fn($v) => is_string($v) && $v !== '' && str_contains('=+-@', $v[0]) && !is_numeric($v) ? "'" . $v : $v;
        fputcsv($out, $intestazioni, ';', '"', '');
        foreach ($righe as $r) fputcsv($out, array_map($pulisci, $r), ';', '"', '');
        fclose($out);
        exit;
    }
}
