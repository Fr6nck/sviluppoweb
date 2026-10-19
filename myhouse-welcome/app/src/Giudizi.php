<?php
namespace MHW;

/**
 * 9B · «Ti piace MyHouse Welcome?»: il giudizio con lo sconto (di serie il 5%).
 *
 * Le regole, decise dal titolare e scritte nei Termini («Giudizio con sconto»):
 *   - una sola casella, ovunque: chi aderisce si impegna a lasciare un voto con un commento dopo la pubblicazione
 *     e accetta che possa comparire nella home (nome senza cognome, struttura, città, immagini scelte, nessun link);
 *   - aderisce al pagamento → lo sconto sul primo anno (sconto di sistema, Sconti::sistema, si somma all'invito);
 *   - aderisce dopo, dal pannello → lo sconto sul rinnovo successivo, quando ha lasciato il giudizio (coupon «una
 *     volta» sull'abbonamento Stripe; senza abbonamento, al primo Checkout);
 *   - lo sconto non dipende dal voto; la pubblicazione è a nostra discrezione; il giudizio si ritira quando si vuole:
 *     lo sconto già avuto resta, quello promesso sul rinnovo decade.
 * Gli stati sono in accounts (giudizio_adesione, giudizio_sconto_stato); il giudizio in testimonials (Testimonianze).
 */
final class Giudizi
{
    private const COUPON = 'mhw-giudizio-';
    public const ADESIONI = ['pagamento' => 'Al pagamento', 'pannello' => 'Dal pannello'];
    public const STATI = ['promesso' => 'Promesso', 'applicato_primo_anno' => 'Applicato al primo anno', 'da_applicare_rinnovo' => 'Sul prossimo rinnovo',
                          'applicato_rinnovo' => 'Applicato al rinnovo', 'nessuno' => 'Nessuno'];

    /** Il programma è disponibile (migrazione 044 applicata)? */
    public static function disponibili(): bool
    {
        return Migrator::columnExists('accounts', 'giudizio_adesione') && Testimonianze::giudizi();
    }

    /** L'interruttore «Sconto del 5% per i nuovi clienti» (Impostazioni → Giudizi). */
    public static function attivo(): bool
    {
        return self::disponibili() && !empty((Config::get('giudizi') ?? [])['sconto_attivo']);
    }

    /** La sezione «Cosa dicono di noi» in home è accesa? */
    public static function homeAccesa(): bool
    {
        return self::disponibili() && !empty((Config::get('giudizi') ?? [])['home']);
    }

    /**
     * 9B · Quando si applica lo sconto (Impostazioni → Giudizi): «adesione» = al primo addebito se ha spuntato la casella,
     * anche se il giudizio arriva dopo; «giudizio» = solo dopo il giudizio inviato (al primo addebito se arriva prima,
     * per esempio nei mesi di un codice omaggio, altrimenti al rinnovo).
     */
    public static function quando(): string
    {
        return ((Config::get('giudizi') ?? [])['sconto_quando'] ?? 'adesione') === 'giudizio' ? 'giudizio' : 'adesione';
    }

    /** La riga in più sotto la casella quando lo sconto arriva solo dopo il giudizio. */
    public static function spiegaQuando(): string
    {
        return self::quando() === 'giudizio'
            ? 'Lo sconto parte quando hai lasciato il giudizio: sul primo addebito se lo lasci prima, altrimenti sul rinnovo successivo.'
            : '';
    }

    /** Lo sconto del programma, in percento (Impostazioni → Giudizi; di serie 5). */
    public static function percentuale(): int
    {
        $v = (int) ((Config::get('giudizi') ?? [])['percentuale'] ?? 5);
        return max(1, min(20, $v ?: 5));
    }

    /**
     * 9L · La casella, una sola e con lo stesso testo al pagamento e nel pannello: «Testi e foto» → giudizio.casella.
     * In testo semplice (è anche la prova dell'adesione, salvata con il giudizio); casellaHtml() per mostrarla.
     */
    public static function casella(): string
    {
        return html_entity_decode(strip_tags(self::casellaHtml()), ENT_QUOTES);
    }

    public static function casellaHtml(): string
    {
        return TestiSito::html('giudizio.casella', ['giudizio' => self::percentuale() . '%']);
    }

    /** 9L · Lo stesso testo anche dal pannello (una chiave sola). */
    public static function casellaBreve(): string { return self::casella(); }

    /** La riga sotto la casella al pagamento (dove vale lo sconto). */
    public static function spiegone(): string
    {
        return 'Sconto giudizio ' . self::percentuale() . '% applicato al primo anno. Se aderisci dopo, dal pannello, vale sul rinnovo.';
    }

    /** Fuori dal programma: i clienti di prova (7F), gli account di esempio e chi ha la vetrina. */
    public static function escluso(array $acc): bool
    {
        if (!empty($acc['is_test'])) return true;
        $email = (string) Db::val('SELECT email FROM users WHERE id = ?', [(int) $acc['user_id']], '');
        if (str_ends_with($email, '@' . Demo::DOMINIO)) return true;
        return (bool) Db::val('SELECT id FROM properties WHERE account_id = ? AND is_demo > 0', [(int) $acc['id']]);
    }

    /** Può ancora aderire: interruttore acceso, mai aderito, non escluso. */
    public static function puoAderire(array $acc): bool
    {
        return self::attivo() && empty($acc['giudizio_adesione']) && !self::escluso($acc);
    }

    /**
     * L'adesione, al pagamento o dal pannello: lo sconto è «promesso». La prova resta nell'account (data) e nel giudizio (consent_text).
     * 9L · Dal pannello, con lo sconto «all'adesione», vale subito sul prossimo rinnovo (coupon sull'abbonamento attivo; senza
     * abbonamento, al primo pagamento). La scheda in «Le guide dei nostri host» nasce subito (Vetrina::assicura).
     */
    public static function aderisci(array $acc, string $modo): void
    {
        if (!self::disponibili() || !isset(self::ADESIONI[$modo]) || !empty($acc['giudizio_adesione'])) return;
        $subito = $modo === 'pannello' && self::quando() === 'adesione';
        Db::update('accounts', ['giudizio_adesione' => $modo, 'giudizio_adesione_at' => Support::now(), 'giudizio_sconto_stato' => $subito ? 'da_applicare_rinnovo' : 'promesso'], 'id = :aid', ['aid' => (int) $acc['id']]);
        $codice = '';
        try { $codice = Codici::cliente((int) $acc['id']); } catch (\Throwable) {}
        Auth::audit('giudizio.adesione', null, ['account_id' => (int) $acc['id'], 'modo' => $modo, 'codice' => $codice]);
        if ($subito) { try { self::aggiungiCoupon((int) $acc['id']); } catch (\Throwable $e) { Log::exception($e, 'giudizi: coupon sul rinnovo'); } }
        Vetrina::assicura((int) $acc['id']);
    }

    /** La casella tolta prima di pagare: l'adesione al pagamento si annulla (solo finché lo sconto è promesso e nessun giudizio è stato lasciato). */
    public static function rinuncia(array $acc): void
    {
        if (!self::disponibili() || ($acc['giudizio_adesione'] ?? '') !== 'pagamento' || ($acc['giudizio_sconto_stato'] ?? '') !== 'promesso') return;
        if (Testimonianze::diAccount((int) $acc['id'])) return;
        Db::update('accounts', ['giudizio_adesione' => null, 'giudizio_adesione_at' => null, 'giudizio_sconto_stato' => 'nessuno'], 'id = :aid', ['aid' => (int) $acc['id']]);
    }

    /**
     * Lo sconto di sistema sul primo anno (Sconti::sistema): chi ha aderito al pagamento e non l'ha ancora avuto,
     * oppure chi ha aderito dal pannello e ha lasciato il giudizio senza un abbonamento su cui applicarlo.
     */
    public static function scontoDiSistema(array $acc): ?array
    {
        if (!self::disponibili() || empty($acc['giudizio_adesione'])) return null;
        $stato = (string) ($acc['giudizio_sconto_stato'] ?? '');
        // «Solo dopo il giudizio»: la casella al pagamento promette lo sconto, ma sul primo addebito lo si ha solo con il giudizio già inviato.
        $conGiudizio = self::quando() === 'adesione' || (($g = Testimonianze::diAccount((int) $acc['id'])) && $g['withdrawn_at'] === null);
        $vale = ($acc['giudizio_adesione'] === 'pagamento' && $stato === 'promesso' && $conGiudizio) || $stato === 'da_applicare_rinnovo';
        return $vale ? ['tipo' => 'giudizio', 'nome' => 'Giudizio', 'percento' => self::percentuale()] : null;
    }

    /** Da Billing::activate: il primo pagamento con lo sconto del giudizio dentro (orders.sconti). Solo database. */
    public static function pagato(array $order): void
    {
        if (!self::disponibili() || !str_contains((string) ($order['sconti'] ?? ''), 'giudizio:')) return;
        // 9D · Con un codice omaggio il primo addebito è alla fine dei mesi gratis: lo segna la fattura (fattura()), non il Checkout.
        if (!empty($order['codice_omaggio_id'])) return;
        Db::update('accounts', ['giudizio_sconto_stato' => 'applicato_primo_anno', 'giudizio_sconto_at' => Support::now()], 'id = :aid', ['aid' => (int) $order['account_id']]);
    }

    /**
     * Dopo il giudizio lasciato (Testimonianze::salvaGiudizio): chi ha aderito dal pannello ha lo sconto sul prossimo
     * rinnovo. Con un abbonamento Stripe attivo il coupon si aggiunge subito (insieme a quelli che ha già).
     */
    public static function dopoGiudizio(array $acc): void
    {
        if (!self::disponibili() || ($acc['giudizio_sconto_stato'] ?? '') !== 'promesso') return;
        $adesione = (string) ($acc['giudizio_adesione'] ?? '');
        // Adesione al pagamento con «solo dopo il giudizio»: finché non ha pagato lo sconto resta promesso (andrà sul primo
        // addebito come sconto di sistema); con un abbonamento già attivo (o in omaggio) va sul prossimo addebito, col coupon.
        if ($adesione === 'pagamento' && (self::quando() !== 'giudizio' || !Subscriptions::active((int) $acc['id']))) return;
        if ($adesione !== 'pagamento' && $adesione !== 'pannello') return;
        Db::update('accounts', ['giudizio_sconto_stato' => 'da_applicare_rinnovo'], 'id = :aid', ['aid' => (int) $acc['id']]);
        try { self::aggiungiCoupon((int) $acc['id']); } catch (\Throwable $e) { Log::exception($e, 'giudizi: coupon sul rinnovo'); }
    }

    /** Il coupon Stripe «una volta» del giudizio, creato la prima volta. */
    public static function coupon(int $percento): string
    {
        if (!Stripe::enabled()) return '';
        $id = self::COUPON . $percento;
        try { Stripe::call('GET', 'coupons/' . rawurlencode($id)); }
        catch (\RuntimeException) {
            Stripe::call('POST', 'coupons', ['id' => $id, 'percent_off' => (string) $percento, 'duration' => 'once', 'name' => 'Giudizio con sconto'], 'mhw-coupon-' . $id);
        }
        return $id;
    }

    /** Gli sconti di un abbonamento Stripe: [id dello sconto, id del coupon] per ognuno. */
    private static function scontiDi(array $sub): array
    {
        $out = [];
        foreach ((array) ($sub['discounts'] ?? []) as $d) {
            if (!is_array($d)) { $out[] = [(string) $d, '']; continue; }
            $c = $d['coupon'] ?? ($d['source']['coupon'] ?? '');
            $out[] = [(string) ($d['id'] ?? ''), is_array($c) ? (string) ($c['id'] ?? '') : (string) $c];
        }
        return $out;
    }

    /** Aggiunge il coupon del giudizio all'abbonamento attivo, conservando gli altri sconti (per esempio quello degli inviti). */
    public static function aggiungiCoupon(int $accountId): bool
    {
        if (!Stripe::enabled()) return false;
        $s = Subscriptions::active($accountId);
        if (!$s || $s['provider'] !== 'stripe' || (string) $s['provider_subscription_id'] === '') return false;
        $sid = (string) $s['provider_subscription_id'];
        $sub = Stripe::call('GET', 'subscriptions/' . rawurlencode($sid), ['expand' => ['discounts']]);
        $p = []; $i = 0;
        foreach (self::scontiDi($sub) as [$did, $cid]) {
            if (str_starts_with($cid, self::COUPON)) return true;   // c'è già
            if ($did !== '') $p['discounts[' . $i++ . '][discount]'] = $did;
        }
        $p['discounts[' . $i . '][coupon]'] = self::coupon(self::percentuale());
        Stripe::call('POST', 'subscriptions/' . rawurlencode($sid), $p);
        return true;
    }

    /** Toglie il coupon del giudizio dall'abbonamento (ritiro prima del rinnovo), lasciando gli altri. */
    public static function togliCoupon(int $accountId): void
    {
        if (!Stripe::enabled()) return;
        $s = Subscriptions::active($accountId);
        if (!$s || $s['provider'] !== 'stripe' || (string) $s['provider_subscription_id'] === '') return;
        $sid = (string) $s['provider_subscription_id'];
        $sub = Stripe::call('GET', 'subscriptions/' . rawurlencode($sid), ['expand' => ['discounts']]);
        $p = []; $i = 0; $trovato = false;
        foreach (self::scontiDi($sub) as [$did, $cid]) {
            if (str_starts_with($cid, self::COUPON)) { $trovato = true; continue; }
            if ($did !== '') $p['discounts[' . $i++ . '][discount]'] = $did;
        }
        if ($trovato) Stripe::call('POST', 'subscriptions/' . rawurlencode($sid), $p ?: ['discounts' => '']);
    }

    /** Da Billing::onInvoicePaid: un rinnovo scontato chiude lo sconto promesso sul rinnovo. Solo database. */
    public static function fattura(array $sub, array $inv): void
    {
        if (!self::disponibili() || ($inv['billing_reason'] ?? '') !== 'subscription_cycle') return;
        $aid = (int) $sub['account_id'];
        $a = Db::one('SELECT giudizio_adesione, giudizio_sconto_stato FROM accounts WHERE id = ?', [$aid]) ?: [];
        $stato = (string) ($a['giudizio_sconto_stato'] ?? '');
        // 9D · Il primo addebito dopo i mesi gratis: lo sconto promesso al pagamento (coupon sulla prima fattura) è applicato al primo anno.
        $primoAddebito = $stato === 'promesso' && ($a['giudizio_adesione'] ?? '') === 'pagamento' && !empty($sub['omaggio_fino']);
        if ($stato !== 'da_applicare_rinnovo' && !$primoAddebito) return;
        $sconto = 0;
        foreach ((array) ($inv['total_discount_amounts'] ?? []) as $d) $sconto += (int) ($d['amount'] ?? 0);
        if ($sconto === 0 && isset($inv['subtotal'], $inv['total_excluding_tax'])) $sconto = max(0, (int) $inv['subtotal'] - (int) $inv['total_excluding_tax']);
        if ($sconto <= 0) { if (!$primoAddebito) Log::error('Giudizi: rinnovo senza lo sconto atteso', ['account' => $aid, 'invoice' => (string) ($inv['id'] ?? '')]); return; }
        Db::update('accounts', ['giudizio_sconto_stato' => $primoAddebito ? 'applicato_primo_anno' : 'applicato_rinnovo', 'giudizio_sconto_at' => Support::now()], 'id = :aid', ['aid' => $aid]);
    }

    /** Il cliente si ritira dal programma: lo sconto già avuto resta, quello promesso (sul rinnovo o sul primo pagamento) decade. */
    public static function ritirato(array $acc): void
    {
        if (!self::disponibili()) return;
        if (in_array($acc['giudizio_sconto_stato'] ?? '', ['da_applicare_rinnovo', 'promesso'], true)) {
            Db::update('accounts', ['giudizio_sconto_stato' => 'nessuno'], 'id = :aid', ['aid' => (int) $acc['id']]);
            try { self::togliCoupon((int) $acc['id']); } catch (\Throwable $e) { Log::exception($e, 'giudizi: coupon tolto'); }
        }
    }

    /** La data del prossimo rinnovo, se c'è un abbonamento, per «Sconto del 5% sul rinnovo del …». */
    public static function prossimoRinnovo(int $accountId): ?string
    {
        $s = Subscriptions::active($accountId);
        return $s && !empty($s['current_period_end']) ? (string) $s['current_period_end'] : null;
    }

    /** Una riga sullo stato dello sconto, per il pannello e l'amministrazione. */
    public static function scontoTesto(array $acc): string
    {
        $p = self::percentuale() . '%';
        return match ((string) ($acc['giudizio_sconto_stato'] ?? 'nessuno')) {
            'promesso' => ($acc['giudizio_adesione'] ?? '') === 'pagamento'
                ? (self::quando() === 'giudizio' ? $p . ' dopo il giudizio: sul primo addebito o sul rinnovo'
                   : ((($s = Subscriptions::active((int) $acc['id'])) && !empty($s['omaggio_fino'])) ? $p . ' sul primo addebito del ' . Support::date((string) $s['omaggio_fino']) : $p . ' sul primo anno, al pagamento'))
                : $p . ' sul prossimo rinnovo, dopo il giudizio',
            'applicato_primo_anno' => $p . ' già applicato al primo anno',
            'da_applicare_rinnovo' => ($r = self::prossimoRinnovo((int) $acc['id'])) ? $p . ' sul rinnovo del ' . Support::date($r) : $p . ' sul prossimo pagamento',
            'applicato_rinnovo' => $p . ' applicato al rinnovo' . (!empty($acc['giudizio_sconto_at']) ? ' del ' . Support::date($acc['giudizio_sconto_at']) : ''),
            default => '',
        };
    }

    /**
     * Il riquadro «Ti piace MyHouse Welcome?» del pannello e di Account: in che stato è.
     * @return array{stato:string,giudizio:?array,pubblicata:bool,aderito:bool,sconto:string}
     *   stato: nascosto | aderito | invito | senza_sconto | fatto
     */
    public static function riquadro(array $acc): array
    {
        $g = Testimonianze::diAccount((int) $acc['id']);
        $pubblicata = Testimonianze::primaPubblicazione((int) $acc['id']) !== null;
        $base = ['giudizio' => $g, 'pubblicata' => $pubblicata, 'aderito' => !empty($acc['giudizio_adesione']), 'sconto' => self::scontoTesto($acc)];
        if (!self::disponibili() || self::escluso($acc)) return $base + ['stato' => 'nascosto'];
        if ($g) return $base + ['stato' => 'fatto'];
        if (!empty($acc['giudizio_adesione'])) return $base + ['stato' => 'aderito'];
        return $base + ['stato' => self::attivo() ? 'invito' : 'senza_sconto'];
    }

    // ------------------------------------------------------------ la home

    /** 9L · Le schede di «Le guide dei nostri host» (Vetrina): copertina e logo, mai la foto dell'host. */
    public static function perHome(int $max = 6): array { return Vetrina::perHome($max); }
}
