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

    /**
     * 9R · Il giudizio c'è (voto e commento lasciati, non ritirati)? Senza, lo sconto del 5% sul rinnovo non matura.
     */
    public static function conGiudizio(int $accountId): bool
    {
        $g = Testimonianze::diAccount($accountId);
        return $g !== null && empty($g['withdrawn_at']);
    }

    /**
     * 9R (pacchetto 16) · Il 5% del giudizio si usa UNA SOLA VOLTA per account, mai ogni anno:
     * - adesione al pagamento (o dal pannello prima del primo pagamento, che conta come «al pagamento»): solo sul primo anno,
     *   subito; al rinnovo non si applica, con o senza giudizio;
     * - adesione dopo il pagamento: solo sul rinnovo successivo, se a quel rinnovo il giudizio c'è (altrimenti sul primo
     *   rinnovo dopo il giudizio). Applicato una volta («applicato_primo_anno» / «applicato_rinnovo»), non si ripete più.
     * scontoDisponibile: adesione non ritirata E sconto non ancora applicato E (al pagamento → solo nel primo pagamento,
     * $primoPagamento; dopo → giudizio lasciato).
     */
    public static function scontoDisponibile(array $acc, bool $primoPagamento = false): bool
    {
        if (!self::disponibili() || empty($acc['giudizio_adesione'])) return false;
        if (in_array((string) ($acc['giudizio_sconto_stato'] ?? ''), ['applicato_primo_anno', 'applicato_rinnovo', 'nessuno'], true)) return false;
        $sch = Vetrina::disponibili() ? Vetrina::scheda((int) $acc['id']) : null;
        if ($sch && !empty($sch['ritirato_at'])) return false;
        if ($acc['giudizio_adesione'] === 'pagamento') return $primoPagamento;
        return self::conGiudizio((int) $acc['id']);
    }

    /** @deprecated 9R · il nome di prima: vedi scontoDisponibile. */
    public static function scontoAttivo(array $acc, bool $primoAnno = false): bool { return self::scontoDisponibile($acc, $primoAnno); }

    /** 9R · Il 5% è già stato usato (sul primo anno o su un rinnovo): non torna più. */
    public static function scontoUsato(array $acc): bool
    {
        return in_array((string) ($acc['giudizio_sconto_stato'] ?? ''), ['applicato_primo_anno', 'applicato_rinnovo'], true);
    }

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

    /** 9N · Almeno una delle due sezioni pubbliche è accesa dall'admin (Vetrina::sezioneGuide, Vetrina::sezioneDicono). */
    public static function homeAccesa(): bool
    {
        return Vetrina::sezioneGuide() || Vetrina::sezioneDicono();
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
        // 9N · Nel pannello in inglese la casella è in inglese (ed è quella la prova salvata dell'adesione).
        return TestiSito::formatta(TestiSito::get('giudizio.casella', Pannello::en() ? 'en' : null), ['giudizio' => self::percentuale() . '%']);
    }

    /**
     * 9R · La riga in piccolo sotto la casella (privacy, si somma agli amici, quando vale, ritiro): «Testi e foto» →
     * giudizio.casella_nota. $alPagamento: «sul primo anno»; dal pannello «sul prossimo rinnovo». Il link ai Termini lo mette la vista.
     */
    public static function casellaNota(bool $alPagamento, ?array $acc = null): string
    {
        $en = Pannello::en();
        // 9R · Dal pannello, chi non ha ancora pagato aderisce «al pagamento»: il 5% va sul primo anno.
        $acc ??= Auth::account();
        if (!$alPagamento && $acc && !Subscriptions::active((int) $acc['id'])) $alPagamento = true;
        $quando = $alPagamento ? ($en ? 'on the first year' : 'sul primo anno') : ($en ? 'on the first renewal after your review' : 'sul primo rinnovo dopo il tuo giudizio');
        return TestiSito::formatta(TestiSito::get('giudizio.casella_nota', $en ? 'en' : null), ['quando' => $quando, 'giudizio' => self::percentuale() . '%']);
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

    /** 9N · Perché un account è fuori dal programma, in parole semplici (pagina /giudizio). '' se non è escluso. */
    public static function motivoEscluso(array $acc): string
    {
        if (!empty($acc['is_test'])) return Pannello::t('è un account di prova del nostro staff');
        $email = (string) Db::val('SELECT email FROM users WHERE id = ?', [(int) $acc['user_id']], '');
        if (str_ends_with($email, '@' . Demo::DOMINIO)) return Pannello::t('è un account di esempio');
        if (Db::val('SELECT id FROM properties WHERE account_id = ? AND is_demo > 0', [(int) $acc['id']])) return Pannello::t('gestisce la guida di esempio del sito (la vetrina)');
        return '';
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
        // 9R · Dal pannello prima del primo pagamento conta come «al pagamento»: il 5% sul primo anno (e la casella al
        // pagamento compare già spuntata, «Hai già aderito»).
        if ($modo === 'pannello' && !Subscriptions::active((int) $acc['id']) && !Db::val("SELECT id FROM orders WHERE account_id = ? AND status = 'paid'", [(int) $acc['id']])) $modo = 'pagamento';
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
        // 9R · Il 5% «sul rinnovo» (aderito dal pannello) vale come sconto del primo pagamento solo con il giudizio lasciato.
        $vale = ($acc['giudizio_adesione'] === 'pagamento' && $stato === 'promesso' && $conGiudizio) || ($stato === 'da_applicare_rinnovo' && self::conGiudizio((int) $acc['id']));
        return $vale ? ['tipo' => 'giudizio', 'nome' => Pannello::t('Giudizio'), 'percento' => self::percentuale()] : null;
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
        if (!self::disponibili()) return;
        // 9R · Il 5% si usa una volta: chi l'ha avuto sul primo anno (aderito al pagamento) lascia il giudizio e basta, il
        // rinnovo resta senza. Chi ha aderito dopo il pagamento è «sul prossimo rinnovo»: con il giudizio il coupon lo comprende.
        if (self::scontoUsato($acc)) return;
        if (($acc['giudizio_sconto_stato'] ?? '') === 'da_applicare_rinnovo' && !empty($acc['giudizio_adesione'])) {
            try { self::aggiungiCoupon((int) $acc['id']); } catch (\Throwable $e) { Log::exception($e, 'giudizi: coupon sul rinnovo'); }
            return;
        }
        if (($acc['giudizio_sconto_stato'] ?? '') !== 'promesso') return;
        $adesione = (string) ($acc['giudizio_adesione'] ?? '');
        // Adesione al pagamento con «solo dopo il giudizio»: finché non ha pagato lo sconto resta promesso (andrà sul primo
        // addebito come sconto di sistema); con un abbonamento già attivo (o in omaggio) va sul prossimo addebito, col coupon.
        if ($adesione === 'pagamento' && (self::quando() !== 'giudizio' || !Subscriptions::active((int) $acc['id']))) return;
        if ($adesione !== 'pagamento' && $adesione !== 'pannello') return;
        Db::update('accounts', ['giudizio_sconto_stato' => 'da_applicare_rinnovo'], 'id = :aid', ['aid' => (int) $acc['id']]);
        try { self::aggiungiCoupon((int) $acc['id']); } catch (\Throwable $e) { Log::exception($e, 'giudizi: coupon sul rinnovo'); }
    }

    /** Il coupon Stripe «una volta» del giudizio da solo: 9N, solo per il primo anno (Sconti::perStripe); il rinnovo usa mhw-rinnovo-N. */
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

    /** 9N · Lo sconto del giudizio sul rinnovo: un solo coupon con gli inviti, sommati sul prezzo pieno (Sconti::sincronizzaRinnovo). */
    public static function aggiungiCoupon(int $accountId): bool { return Sconti::sincronizzaRinnovo($accountId); }

    /** 9N · Ritiro o adesione decaduta: il coupon del rinnovo si ricalcola senza il giudizio. */
    public static function togliCoupon(int $accountId): void { Sconti::sincronizzaRinnovo($accountId); }

    /**
     * Da Billing::onInvoicePaid: un rinnovo con lo sconto del giudizio dentro (coupon e metadati, non il prefisso) chiude lo
     * sconto promesso sul rinnovo. 9N · Il primo addebito dopo i mesi di un omaggio è pieno: l'adesione (al pagamento o dal
     * pannello) passa sul primo rinnovo. Solo database; lo sconto su Stripe si riallinea dopo (Sconti::accoda).
     */
    public static function fattura(array $sub, array $inv): void
    {
        if (!self::disponibili() || ($inv['billing_reason'] ?? '') !== 'subscription_cycle') return;
        $aid = (int) $sub['account_id'];
        $a = Db::one('SELECT giudizio_adesione, giudizio_sconto_stato FROM accounts WHERE id = ?', [$aid]) ?: [];
        $stato = (string) ($a['giudizio_sconto_stato'] ?? '');
        if (empty($a['giudizio_adesione'])) return;
        $comp = Sconti::rinnovoInFattura($inv, 0, $stato === 'da_applicare_rinnovo' ? self::percentuale() : 0);
        $primoDopoOmaggio = ($sub['status'] ?? '') === 'trialing' && !empty($sub['omaggio_fino']);
        if ($primoDopoOmaggio) {
            // Un abbonamento di prima della 9N con il coupon del giudizio sulla prima fattura: applicato al primo anno.
            if ($stato === 'promesso' && $comp['giudizio'] > 0) {
                Db::update('accounts', ['giudizio_sconto_stato' => 'applicato_primo_anno', 'giudizio_sconto_at' => Support::now()], 'id = :aid', ['aid' => $aid]);
                return;
            }
            $conGiudizio = self::quando() === 'adesione' || (($g = Testimonianze::diAccount($aid)) && $g['withdrawn_at'] === null);
            if ($stato === 'promesso' && $conGiudizio) Db::update('accounts', ['giudizio_sconto_stato' => 'da_applicare_rinnovo'], 'id = :aid', ['aid' => $aid]);
            Sconti::accoda($aid);
            return;
        }
        if ($stato !== 'da_applicare_rinnovo') return;
        // 9R · Senza giudizio il 5% non si applica a questo rinnovo: resta «sul prossimo rinnovo», dopo il giudizio.
        if ($comp['giudizio'] <= 0) { if (self::conGiudizio($aid)) Log::error('Giudizi: rinnovo senza lo sconto atteso', ['account' => $aid, 'invoice' => (string) ($inv['id'] ?? '')]); return; }
        Db::update('accounts', ['giudizio_sconto_stato' => 'applicato_rinnovo', 'giudizio_sconto_at' => Support::now()], 'id = :aid', ['aid' => $aid]);
        Sconti::accoda($aid);
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
        // 9N · Nella lingua del pannello dell'host (l'amministrazione resta in italiano): frasi intere con {n} e {data}.
        $f = fn(string $it, string $data = '') => strtr(Pannello::t($it), ['{n}' => self::percentuale(), '{data}' => Pannello::data($data)]);
        // 9N · Nei mesi di un codice omaggio il primo addebito è pieno: lo sconto vale dal primo rinnovo dopo quel pagamento.
        $s = Subscriptions::active((int) $acc['id']);
        $omaggio = $s && ($s['status'] ?? '') === 'trialing' && !empty($s['omaggio_fino']);
        $dopoOmaggio = $omaggio ? $f('{n}% dal primo rinnovo dopo il primo pagamento (il primo addebito è il {data})', (string) $s['omaggio_fino']) : $f('{n}% dal primo rinnovo dopo il primo pagamento');
        return match ((string) ($acc['giudizio_sconto_stato'] ?? 'nessuno')) {
            'promesso' => $omaggio ? $dopoOmaggio : (($acc['giudizio_adesione'] ?? '') === 'pagamento'
                ? (self::quando() === 'giudizio' ? $f('{n}% dopo il giudizio: sul primo addebito o sul rinnovo') : $f('{n}% sul primo anno, al pagamento'))
                : $f('{n}% sul prossimo rinnovo, dopo il giudizio')),
            'applicato_primo_anno' => $f('{n}% già applicato al primo anno'),
            'da_applicare_rinnovo' => $omaggio ? $dopoOmaggio : (($r = self::prossimoRinnovo((int) $acc['id'])) ? $f('{n}% sul rinnovo del {data}', $r) : $f('{n}% sul prossimo pagamento')),
            'applicato_rinnovo' => !empty($acc['giudizio_sconto_at']) ? $f('{n}% applicato al rinnovo del {data}', (string) $acc['giudizio_sconto_at']) : $f('{n}% applicato al rinnovo'),
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
