<?php
namespace MHW;

/**
 * Invita un amico.
 *
 * Chi ha un abbonamento attivo ha un link personale (/i/CODICE). Chi si registra
 * da quel link ha AMICO% sul primo anno; chi ha invitato ha PASSO% in meno sul
 * prossimo rinnovo per ogni amico che PAGA, fino a MASSIMO%.
 *
 * Lato amico non c'è niente di nuovo su Stripe: lo sconto è una riga «di sistema»
 * in discount_codes, quindi passa dallo stesso percorso dei codici sconto (6E):
 * coupon «una volta» nel checkout, utilizzo registrato dal webhook.
 *
 * Lato chi invita: un coupon «una volta» (mhw-invito-5 … mhw-invito-50) messo
 * sull'abbonamento Stripe. Sconta la prossima fattura, cioè il rinnovo, e poi
 * Stripe lo toglie da solo. A ogni amico in più il coupon si sostituisce con
 * quello della percentuale nuova.
 *
 * Le regole di Billing valgono anche qui: dentro la transazione del webhook si
 * scrive solo sul database (pagato, fattura); Stripe e le email partono dopo
 * (dopoEvento). Se Stripe non risponde, inSospeso() riprova al giro dei Richiami.
 */
final class Inviti
{
    /** Sconto dell'amico sul primo anno, in percento. */
    public const AMICO = 5;
    /** Sconto di chi invita sul prossimo rinnovo, per ogni amico che paga. */
    public const PASSO = 5;
    /** Tetto dello sconto di chi invita, in percento. */
    public const MASSIMO = 50;
    /** Tetto in centesimi allo sconto di chi invita (0 = nessuno). Utile con i Portfolio grandi. */
    public const TETTO_CENTS = 0;

    private const COUPON = 'mhw-invito-';
    private const ALFABETO = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /** Da riallineare su Stripe dopo la transazione: [account di chi invita => account dell'amico appena valido, o 0]. */
    private static array $coda = [];

    /** Acceso dalla configurazione (inviti.attivi, MHW_INVITI=1) e con la migrazione 019 applicata. */
    public static function disponibili(): bool
    {
        return !empty((Config::get('inviti') ?? [])['attivi']) && Migrator::tableExists('referrals');
    }

    public static function amiciMassimi(): int { return intdiv(self::MASSIMO, self::PASSO); }

    /**
     * Invita chi ha un abbonamento attivo, di qualsiasi tipo (7D). Con Stripe lo sconto va da solo
     * sull'abbonamento; con un abbonamento attivato dallo staff si registra e l'amministrazione lo
     * applica a mano al rinnovo (aMano). Gli abbonamenti di esempio no: non si rinnovano.
     */
    public static function puoInvitare(array $acc): bool
    {
        if (!self::disponibili()) return false;
        $s = Subscriptions::active((int) $acc['id']);
        return $s !== null && $s['provider'] !== 'dimostrazione';
    }

    /** Lo sconto di chi invita va applicato a mano: l'abbonamento non è su Stripe. */
    public static function aMano(int $accountId): bool
    {
        $s = Subscriptions::active($accountId);
        return $s !== null && ($s['provider'] !== 'stripe' || (string) $s['provider_subscription_id'] === '');
    }

    /**
     * La guida di chi invita, per l'anteprima dell'invito (/i/CODICE): la prima pubblicata, non
     * archiviata. Solo i campi che si possono mostrare: nome, città, copertina, slug.
     */
    public static function strutturaDi(int $accountId): ?array
    {
        return Db::one("SELECT id, account_id, name, city, cover_media_id, slug, palette FROM properties
                        WHERE account_id = ? AND archived_at IS NULL AND status = 'published' ORDER BY is_demo, id LIMIT 1", [$accountId]);
    }

    /** L'anteprima dell'invito mostra copertina e nome della struttura? (casella in «Invita un amico», di serie sì) */
    public static function anteprimaAccesa(array $acc): bool
    {
        if (!Migrator::columnExists('accounts', 'invite_preview')) return true;
        return (int) Db::val('SELECT invite_preview FROM accounts WHERE id = ?', [(int) $acc['id']], 1) === 1;
    }

    /** Il messaggio pronto da mandare (7D), con struttura, link e codice già scritti. */
    public static function messaggio(array $acc): string
    {
        // 9F · lo stesso testo di partenza di «Invita per email» (Testi e foto → inviti.messaggio_partenza), poi il link e il codice.
        // 9N · Nel pannello in inglese il messaggio parte in inglese.
        return self::testoPartenza() . ' ' . strtr(Pannello::t('Guarda com\'è la mia guida: {link}. Il codice è {codice}.'), ['{link}' => self::link($acc), '{codice}' => self::codice($acc)]);
    }

    /** 9F · Il testo di partenza degli inviti, con la percentuale dell'amico già scritta (modificabile in Testi e foto). */
    public static function testoPartenza(): string
    {
        $loc = Pannello::en() ? 'en' : null;
        $serie = strtr(Pannello::t('Ciao! Uso MyHouse Welcome per la guida digitale della mia struttura e mi trovo bene. Con questo invito hai il {amico}% di sconto sul primo anno: crei la guida gratis e paghi solo quando la pubblichi.'), ['{amico}' => self::AMICO]);
        if (!TestiSito::disponibili()) return $serie;
        $t = trim(html_entity_decode(strip_tags(TestiSito::formatta(TestiSito::get('inviti.messaggio_partenza', $loc), ['amico' => self::AMICO . '%'])), ENT_QUOTES, 'UTF-8'));
        return $t !== '' ? $t : $serie;
    }

    // ------------------------------------------------------------ codice e link

    /** Il codice personale: 6 caratteri leggibili, creato la prima volta che serve. */
    public static function codice(array $acc): string
    {
        $c = (string) (Db::val('SELECT referral_code FROM accounts WHERE id = ?', [(int) $acc['id']], '') ?? '');
        if ($c !== '') return $c;
        do {
            $c = '';
            for ($i = 0; $i < 6; $i++) $c .= self::ALFABETO[random_int(0, strlen(self::ALFABETO) - 1)];
        } while (Db::one('SELECT id FROM accounts WHERE referral_code = ?', [$c]) || Sconti::trova($c));
        Db::update('accounts', ['referral_code' => $c], 'id = :aid', ['aid' => (int) $acc['id']]);
        return $c;
    }

    public static function link(array $acc): string { return Support::baseUrl() . '/i/' . self::codice($acc); }

    /** L'account che invita, dal codice. Con user_name ed email. */
    public static function daCodice(string $codice): ?array
    {
        $c = strtoupper(trim($codice));
        if (!preg_match('/^[A-Z0-9]{6}$/', $c) || !self::disponibili()) return null;
        return Db::one('SELECT a.*, u.name AS user_name, u.email FROM accounts a JOIN users u ON u.id = a.user_id WHERE a.referral_code = ?', [$c]);
    }

    /** «Marco B.»: quanto basta per riconoscersi, senza mostrare il cognome. */
    public static function nomeBreve(string $nome): string
    {
        $p = preg_split('/\s+/', trim($nome)) ?: [];
        if (!$p || $p[0] === '') return 'Un amico';
        return $p[0] . (isset($p[1]) ? ' ' . mb_strtoupper(mb_substr($p[1], 0, 1)) . '.' : '');
    }

    // ---------------------------------------------------------------- lato amico

    /** La riga di sistema in discount_codes che dà all'amico il suo sconto. Nasce alla prima occorrenza. */
    public static function codiceAmico(): ?array
    {
        if (!self::disponibili() || !Sconti::disponibili()) return null;
        $r = Db::one("SELECT * FROM discount_codes WHERE sistema = 1 AND active = 1 AND kind = 'percent' AND value = ? ORDER BY id DESC", [self::AMICO]);
        if (!$r) {
            $code = 'INVITO-AMICO-' . self::AMICO;
            while (Sconti::trova($code)) $code = 'INVITO-AMICO-' . self::AMICO . '-' . self::ALFABETO[random_int(0, 31)] . self::ALFABETO[random_int(0, 31)];
            $id = Db::insert('discount_codes', [
                'code' => $code, 'kind' => 'percent', 'value' => self::AMICO,
                'valid_from' => Sconti::oggi(), 'valid_until' => '2099-12-31', 'max_uses' => 0, 'packages' => '',
                'note' => 'Invita un amico: lo sconto dell\'amico (di sistema)', 'active' => 1, 'sistema' => 1,
                'stripe_coupon_id' => '', 'created_at' => Support::now(),
            ]);
            $r = Sconti::riga($id);
        }
        if ((string) $r['stripe_coupon_id'] === '' && Sconti::sincronizza($r)) $r = Sconti::riga((int) $r['id']);
        return $r;
    }

    /** L'invito di questo account, se è stato invitato (e l'invito non è annullato). Con referrer_name. */
    public static function invitato(int $accountId): ?array
    {
        if (!self::disponibili()) return null;
        return Db::one("SELECT r.*, u.name AS referrer_name FROM referrals r JOIN accounts a ON a.id = r.referrer_account_id
                        JOIN users u ON u.id = a.user_id WHERE r.friend_account_id = ? AND r.status <> 'annullato'", [$accountId]);
    }

    /**
     * Lega l'amico a chi l'ha invitato e gli applica lo sconto. Solo clienti nuovi,
     * un solo invito per account, mai il proprio. Non sostituisce un codice sconto
     * più alto già applicato: i due non si sommano.
     */
    public static function collega(array $amico, array $chiInvita): bool
    {
        $aid = (int) $amico['id']; $rid = (int) $chiInvita['id'];
        if (($motivo = self::motivoNonCollegabile($amico, $chiInvita)) !== null) {
            // Prima si perdeva in silenzio: ora resta nel Registro azioni del cliente (scheda in Amministrazione).
            Auth::audit('invito.scartato', (int) ($amico['user_id'] ?? 0) ?: null, ['amico' => $aid, 'chi' => $rid, 'codice' => (string) ($chiInvita['referral_code'] ?? ''), 'motivo' => $motivo]);
            return false;
        }
        Db::insert('referrals', ['referrer_account_id' => $rid, 'friend_account_id' => $aid, 'status' => 'registrato', 'created_at' => Support::now()]);
        $gia = !empty($amico['intended_discount_code_id']) ? Sconti::riga((int) $amico['intended_discount_code_id']) : null;
        $piuAlto = $gia && !($gia['kind'] === 'percent' && (int) $gia['value'] < self::AMICO);
        if (!$piuAlto && ($c = self::codiceAmico())) {
            Db::update('accounts', ['intended_discount_code_id' => (int) $c['id']], 'id = :aid', ['aid' => $aid]);
        }
        return true;
    }

    /** Perché l'amico non si può legare a questo invito (null = si può). */
    public static function motivoNonCollegabile(array $amico, array $chiInvita): ?string
    {
        $aid = (int) $amico['id']; $rid = (int) $chiInvita['id'];
        if ($aid === $rid) return 'è il suo stesso codice';
        if (!self::puoInvitare($chiInvita)) return 'chi invita non ha un abbonamento attivo';
        if (Omaggi::daUsare($amico)) return 'ha un codice omaggio: non si somma a un invito';   // 9D: omaggio e invito si escludono, vale l'omaggio
        if (Db::one('SELECT id FROM referrals WHERE friend_account_id = ?', [$aid])) return 'ha già un invito';
        if (Db::one("SELECT id FROM orders WHERE account_id = ? AND status = 'paid'", [$aid])) return 'ha già pagato un abbonamento: l\'invito vale solo per il primo';
        return null;
    }

    /**
     * L'amico scrive il codice di invito dove si mette il codice sconto.
     * @return string|null il nome di chi invita; null se non è un codice di invito
     * @throws \RuntimeException con il motivo, da mostrare sotto il campo
     */
    public static function applicaCodice(array $amico, string $codice): ?string
    {
        $chi = self::daCodice($codice);
        if (!$chi) return null;
        $aid = (int) $amico['id'];
        if ((int) $chi['id'] === $aid) throw new \RuntimeException('Questo è il tuo codice di invito: è per i tuoi amici.');
        if (Omaggi::daUsare($amico)) throw new \RuntimeException('Hai già un codice omaggio: non si somma a un invito.');   // 9D
        $gia = Db::one('SELECT * FROM referrals WHERE friend_account_id = ?', [$aid]);
        if ($gia && ((int) $gia['referrer_account_id'] !== (int) $chi['id'] || $gia['status'] !== 'registrato')) {
            throw new \RuntimeException('Hai già usato un invito.');
        }
        if (!$gia) {
            if (!self::puoInvitare($chi)) throw new \RuntimeException('Questo invito non è più attivo.');
            if (!self::collega($amico, $chi)) throw new \RuntimeException('L\'invito vale solo per il primo abbonamento.');
        }
        // Chi scrive il codice vuole questo sconto: prende il posto di un altro codice.
        if (($c = self::codiceAmico())) Db::update('accounts', ['intended_discount_code_id' => (int) $c['id']], 'id = :aid', ['aid' => $aid]);
        return self::nomeBreve((string) $chi['user_name']);
    }

    // ------------------------------------------------ dentro la transazione del webhook

    /** Da Billing::activate: l'amico ha pagato il suo primo abbonamento. Solo database. */
    public static function pagato(array $order): void
    {
        if (!self::disponibili()) return;
        $aid = (int) $order['account_id'];
        $r = Db::one("SELECT * FROM referrals WHERE friend_account_id = ? AND status = 'registrato'", [$aid]);
        if (!$r) return;
        // Conta solo il primo ordine pagato (quello che activate ha appena segnato).
        $rid = (int) $r['referrer_account_id'];
        if ((int) Db::val("SELECT COUNT(*) FROM orders WHERE account_id = ? AND status = 'paid'", [$aid], 0) !== 1) {
            Auth::audit('invito.non_contato', (int) Db::val('SELECT user_id FROM accounts WHERE id = ?', [$aid], 0) ?: null, ['amico' => $aid, 'chi' => $rid, 'ordine' => (int) $order['id'], 'motivo' => 'non è il primo ordine pagato']);
            return;
        }
        $a = Db::one('SELECT vat, cf FROM accounts WHERE id = ?', [$aid]) ?: [];
        $b = Db::one('SELECT vat, cf FROM accounts WHERE id = ?', [$rid]) ?: [];
        $uguale = fn(string $k) => trim((string) ($a[$k] ?? '')) !== '' && strtoupper(trim((string) $a[$k])) === strtoupper(trim((string) ($b[$k] ?? '')));
        $stessi = $uguale('vat') || $uguale('cf');
        $stato = $stessi ? 'annullato' : (self::validi($rid) >= self::amiciMassimi() ? 'oltre' : 'valido');
        $motivo = $stessi ? 'stessi dati di fatturazione di chi invita (' . ($uguale('vat') ? 'partita IVA' : 'codice fiscale') . ')' : '';
        Db::update('referrals', ['status' => $stato, 'order_id' => (int) $order['id'], 'qualified_at' => Support::now()] + (Migrator::columnExists('referrals', 'motivo') ? ['motivo' => $motivo] : []), 'id = :id', ['id' => $r['id']]);
        if ($stessi) {
            Log::error('Inviti: stessi dati di fatturazione di chi invita, invito annullato', ['referral' => (int) $r['id']]);
            Auth::audit('invito.annullato', (int) Db::val('SELECT user_id FROM accounts WHERE id = ?', [$aid], 0) ?: null, ['amico' => $aid, 'chi' => $rid, 'motivo' => $motivo]);
            return;
        }
        self::$coda[$rid] = $aid;
    }

    /**
     * Da Billing::onInvoicePaid, per un abbonamento già noto. Solo database.
     * Un rinnovo scontato chiude gli inviti che lo hanno pagato. Ogni altra fattura
     * dell'abbonamento (il conguaglio di una struttura in più) può aver consumato il
     * coupon «una volta»: si azzera il segno e, dopo la transazione, si riallinea.
     */
    public static function fattura(array $sub, array $inv): void
    {
        if (!self::disponibili()) return;
        $aid = (int) $sub['account_id'];
        $applicato = (int) Db::val('SELECT referral_applied_percent FROM accounts WHERE id = ?', [$aid], 0);
        if ($applicato <= 0) return;
        // 9N · Quanti inviti c'erano davvero nello sconto della fattura: dal coupon del rinnovo e dai metadati, non dal prefisso.
        $comp = Sconti::rinnovoInFattura($inv, $applicato);
        $sconto = $comp['sconto'];
        $rif = (string) ($inv['id'] ?? '');
        if (($inv['billing_reason'] ?? '') === 'subscription_cycle') {
            if ($sconto > 0 && $comp['inviti'] > 0) {
                $n = max(1, intdiv($comp['inviti'], self::PASSO));
                foreach (Db::all("SELECT id FROM referrals WHERE referrer_account_id = ? AND status = 'valido' ORDER BY qualified_at, id LIMIT " . $n, [$aid]) as $r) {
                    Db::update('referrals', ['status' => 'usato', 'used_at' => Support::now(), 'used_ref' => $rif], 'id = :id', ['id' => $r['id']]);
                }
                // Gli inviti oltre il tetto non passano all'anno dopo: si chiudono con questo rinnovo.
                Db::run("UPDATE referrals SET status = 'usato', used_at = ?, used_ref = ? WHERE referrer_account_id = ? AND status = 'oltre'", [Support::now(), $rif, $aid]);
                Auth::audit('referral.redeemed', null, ['account_id' => $aid, 'percent' => $comp['inviti'], 'discount_cents' => $sconto, 'invoice' => $rif]);
            } else {
                Log::error('Inviti: rinnovo senza lo sconto atteso', ['account' => $aid, 'percent' => $applicato, 'invoice' => $rif]);
            }
        }
        Db::update('accounts', ['referral_applied_percent' => 0], 'id = :aid', ['aid' => $aid]);
        if (!isset(self::$coda[$aid])) self::$coda[$aid] = 0;
    }

    // ---------------------------------------------------- dopo la transazione: Stripe

    /** Da Billing::handleEvent, a transazione chiusa. Un errore qui non fa fallire il webhook. */
    public static function dopoEvento(): void
    {
        $coda = self::$coda; self::$coda = [];
        foreach ($coda as $rid => $amico) {
            try { self::sincronizza((int) $rid); } catch (\Throwable $e) { Log::exception($e, 'inviti: sconto su Stripe'); }
            if ($amico) { try { self::avvisa((int) $rid, (int) $amico); } catch (\Throwable $e) { Log::exception($e, 'inviti: email'); } }
        }
    }

    public static function validi(int $accountId): int
    {
        return (int) Db::val("SELECT COUNT(*) FROM referrals WHERE referrer_account_id = ? AND status = 'valido'", [$accountId], 0);
    }

    public static function percento(int $accountId): int { return min(self::MASSIMO, self::PASSO * self::validi($accountId)); }

    /**
     * Porta sull'abbonamento Stripe lo sconto guadagnato. 9N: un solo coupon per il rinnovo (inviti + giudizio, sul prezzo
     * pieno), da Sconti::sincronizzaRinnovo; qui resta il nome per chi la chiamava.
     */
    public static function sincronizza(int $accountId): bool
    {
        if (!self::disponibili()) return false;
        return Sconti::sincronizzaRinnovo($accountId);
    }

    /** Dal giro dei Richiami: gli account il cui sconto su Stripe non è quello guadagnato. */
    public static function inSospeso(): void
    {
        if (!self::disponibili() || !Stripe::enabled()) return;
        $righe = Db::all("SELECT a.id, a.referral_applied_percent AS applicato,
                                 (SELECT COUNT(*) FROM referrals r WHERE r.referrer_account_id = a.id AND r.status = 'valido') AS validi
                          FROM accounts a
                          WHERE a.referral_applied_percent > 0 OR a.id IN (SELECT referrer_account_id FROM referrals WHERE status = 'valido')");
        foreach ($righe as $r) {
            if (min(self::MASSIMO, self::PASSO * (int) $r['validi']) === (int) $r['applicato']) continue;
            try { self::sincronizza((int) $r['id']); } catch (\Throwable $e) { Log::exception($e, 'inviti: riallineamento'); }
        }
    }

    /** L'email a chi ha invitato: una per amico, con il link per non riceverne più (Richiami). */
    private static function avvisa(int $rid, int $amicoId): void
    {
        $u = Db::one('SELECT u.email, u.name FROM accounts a JOIN users u ON u.id = a.user_id WHERE a.id = ?', [$rid]);
        if (!$u || !Richiami::disponibili()) return;
        $amico = self::nomeBreve((string) Db::val('SELECT u.name FROM accounts a JOIN users u ON u.id = a.user_id WHERE a.id = ?', [$amicoId], ''));
        $st = self::stato(['id' => $rid]);
        $mancano = $st['massimo'] - $st['validi'];
        // 9N · Nella lingua del pannello di chi ha invitato.
        $lin = Richiami::lingua($rid);
        $tl = fn(string $it, array $s = []) => Pannello::tIn($lin, $it, $s);
        $data = fn(string $iso) => $lin === 'en' && ($t = strtotime($iso)) ? gmdate('j F Y', $t) : Support::date($iso);
        $x = ['{amico}' => $amico, '{n}' => $st['totale'], '{max}' => self::MASSIMO, '{mancano}' => $mancano];
        $corpo = $tl('{amico} ha pubblicato la sua guida con il tuo invito.', $x) . ' ';
        if ($st['percento'] > 0) {
            $corpo .= $tl($mancano > 0 ? 'Il tuo sconto sul prossimo rinnovo sale al {n}%' : 'Il tuo sconto sul prossimo rinnovo è al {n}%', $x)
                    . ($st['rinnovo'] !== '' && $st['prezzo'] > 0 ? $tl(': il {data} paghi {prezzo} + IVA invece di {pieno}', ['{data}' => $data($st['rinnovo']), '{prezzo}' => Support::money($st['scontato'], $st['valuta']), '{pieno}' => Support::money($st['prezzo'], $st['valuta'])]) : '') . '. ';
        }
        $corpo .= $mancano > 0 ? ($mancano === 1 ? $tl('Ti manca 1 amico per arrivare al {max}% degli inviti.', $x) : $tl('Ti mancano {mancano} amici per arrivare al {max}% degli inviti.', $x))
                               : $tl('Hai raggiunto il massimo degli inviti. I tuoi amici continuano ad avere il loro sconto.');
        // 9L · Il tetto vero è inviti + programma: «fino al 55%», non il 50%.
        if (Giudizi::attivo()) $corpo .= ' ' . $tl('Con il programma «Giudizio con sconto», diverso e cumulabile, un altro {g}%: insieme, fino al {n}% sul prossimo rinnovo.', ['{n}' => Sconti::massimoRinnovo()['totale'], '{g}' => Giudizi::percentuale()]);
        $oggetto = $st['percento'] > 0 ? $tl('{amico} ha pubblicato la sua guida: sei al −{n}%', $x) : $tl('{amico} ha pubblicato la sua guida', $x);
        Richiami::manda($rid, (string) $u['email'], (string) $u['name'], 'inviti', 'amico-' . $amicoId,
                        [$oggetto, $corpo, $tl('Invita un altro amico'), Support::baseUrl() . '/inviti'], $lin);
    }

    // ------------------------------------------------------------------ per le pagine

    /** Quello che il pannello mostra: amici, percentuale, quanto si paga al rinnovo. */
    public static function stato(array $acc): array
    {
        $aid = (int) $acc['id'];
        $validi = self::validi($aid);
        $percento = min(self::MASSIMO, self::PASSO * $validi);
        $s = Subscriptions::active($aid);
        $pv = $s ? (Db::one('SELECT * FROM package_versions WHERE id = ?', [(int) $s['package_version_id']]) ?: null) : null;
        $prezzo = $pv ? Plans::price($pv, max(1, (int) ($s['quantity'] ?? 1))) : 0;
        // 9N · Quanto si paga al rinnovo: inviti + giudizio sommati, una volta sul prezzo pieno (la cifra degli avvisi a 30/7 giorni).
        $totale = $s ? Sconti::rinnovo($aid, $s)['percento'] : $percento;
        $sconto = $prezzo - Sconti::netto($prezzo, $totale);
        return [
            'validi' => $validi, 'massimo' => self::amiciMassimi(), 'percento' => $percento,
            'oltre' => (int) Db::val("SELECT COUNT(*) FROM referrals WHERE referrer_account_id = ? AND status = 'oltre'", [$aid], 0),
            'in_attesa' => (int) Db::val("SELECT COUNT(*) FROM referrals WHERE referrer_account_id = ? AND status = 'registrato'", [$aid], 0),
            'prezzo' => $prezzo, 'sconto' => $sconto, 'scontato' => $prezzo - $sconto, 'valuta' => (string) ($pv['currency'] ?? 'EUR'), 'totale' => $totale,
            'rinnovo' => (string) ($s['current_period_end'] ?? ''),
            'automatico' => $s !== null && (int) $s['cancel_at_period_end'] === 0,
            'a_mano' => $s !== null && ($s['provider'] !== 'stripe' || (string) $s['provider_subscription_id'] === ''),
        ];
    }

    /** 9R · Quanti giorni resta «tratteggiato» un amico che si è solo registrato, e quanti dura l'alone del rettangolo appena acceso. */
    public const GIORNI_ATTESA = 90;
    public const GIORNI_ALONE = 3;

    /**
     * 9R · La barra dei 10 rettangoli: ogni amico che ha attivato (invito «valido») ne accende uno, in ordine, con «−5%»,
     * «−10%»…; il più recente, per GIORNI_ALONE giorni, ha l'alone. Chi si è solo registrato (da meno di GIORNI_ATTESA giorni)
     * occupa i rettangoli seguenti, tratteggiati, «in attesa». Sotto, i numeri sul piano del cliente (Sconti, la formula del
     * server): sconto maturato, quanto risparmia al prossimo rinnovo e quanto paga, e se arrivano tutti e 10. Per lo staff
     * (non paga) gli euro sono null.
     * @return array{caselle:list<array{stato:string,etichetta:string,alone:bool}>,pieni:int,attesa:int,maturato:int,prezzo:int,risparmio:?int,paga:?int,tutti:?int,paga_tutti:?int,valuta:string,staff:bool,ordine:array<int,int>}
     */
    public static function barra(array $acc): array
    {
        $aid = (int) $acc['id'];
        $max = self::amiciMassimi();
        $pieni = Db::all("SELECT id, qualified_at, created_at FROM referrals WHERE referrer_account_id = ? AND status = 'valido' ORDER BY COALESCE(qualified_at, created_at), id", [$aid]);
        $pieni = array_slice($pieni, 0, $max);
        $attesa = Db::all("SELECT id FROM referrals WHERE referrer_account_id = ? AND status = 'registrato' AND created_at > ? ORDER BY created_at, id",
                          [$aid, gmdate('Y-m-d\TH:i:s\Z', time() - self::GIORNI_ATTESA * 86400)]);
        $attesa = array_slice($attesa, 0, max(0, $max - count($pieni)));
        $ultimo = $pieni ? end($pieni) : null;
        $alone = $ultimo && (int) strtotime((string) ($ultimo['qualified_at'] ?: $ultimo['created_at'])) > time() - self::GIORNI_ALONE * 86400;
        $caselle = []; $ordine = [];
        for ($i = 0; $i < $max; $i++) {
            $pct = '−' . (($i + 1) * self::PASSO) . '%';
            if ($i < count($pieni)) { $caselle[] = ['stato' => 'pieno', 'etichetta' => $pct, 'alone' => $alone && $i === count($pieni) - 1]; $ordine[(int) $pieni[$i]['id']] = $i + 1; }
            elseif ($i < count($pieni) + count($attesa)) { $caselle[] = ['stato' => 'attesa', 'etichetta' => Pannello::t('in attesa'), 'alone' => false]; $ordine[(int) $attesa[$i - count($pieni)]['id']] = $i + 1; }
            else $caselle[] = ['stato' => 'vuoto', 'etichetta' => $pct, 'alone' => false];
        }
        $st = self::stato($acc);
        $staff = Staff::e($acc);
        $prezzo = (int) $st['prezzo'];
        $netto = Sconti::netto($prezzo, (int) $st['totale']);
        $nettoTutti = Sconti::netto($prezzo, self::MASSIMO);
        return ['caselle' => $caselle, 'pieni' => count($pieni), 'attesa' => count($attesa), 'maturato' => min(self::MASSIMO, count($pieni) * self::PASSO),
                'prezzo' => $prezzo, 'risparmio' => $staff || !$prezzo ? null : $prezzo - $netto, 'paga' => $staff || !$prezzo ? null : $netto,
                'tutti' => $staff || !$prezzo ? null : $prezzo - $nettoTutti, 'paga_tutti' => $staff || !$prezzo ? null : $nettoTutti,
                'valuta' => (string) $st['valuta'], 'staff' => $staff, 'ordine' => $ordine];
    }

    /**
     * «Chi è entrato» (7D): per ogni amico il nome (solo il nome; senza, l'email mascherata), la sua
     * struttura, lo stato e quanto ha fatto risparmiare sul prossimo rinnovo (il PASSO% del prezzo
     * del tuo rinnovo, solo per gli inviti che contano adesso).
     */
    public static function entrati(array $acc): array
    {
        $aid = (int) $acc['id'];
        $st = self::stato($acc);
        $passo = (int) round($st['prezzo'] * self::PASSO / 100);
        $ordine = self::barra($acc)['ordine'];   // 9R · il numero del rettangolo di ognuno
        $righe = Db::all("SELECT r.id, r.status, r.created_at, r.qualified_at, r.friend_account_id, u.name, u.email,
                                 (SELECT p.name FROM properties p WHERE p.account_id = r.friend_account_id AND p.archived_at IS NULL ORDER BY p.id LIMIT 1) AS struttura
                          FROM referrals r JOIN accounts a ON a.id = r.friend_account_id JOIN users u ON u.id = a.user_id
                          WHERE r.referrer_account_id = ? AND r.status <> 'annullato' ORDER BY r.id DESC LIMIT 50", [$aid]);
        $out = [];
        foreach ($righe as $r) {
            $nome = trim((string) $r['name']);
            $pubblicato = in_array($r['status'], ['valido', 'oltre', 'usato'], true);
            $out[] = [
                'nome' => $nome !== '' ? (preg_split('/\s+/', $nome)[0] ?? $nome) : self::maschera((string) $r['email']),
                'struttura' => (string) ($r['struttura'] ?? ''),
                // 9R · «Ha attivato · −5% per te» (verde) o «Si è registrato · in attesa che attivi» (ocra), con il suo rettangolo.
                'stato' => $pubblicato ? strtr(Pannello::t('Ha attivato · −{n}% per te'), ['{n}' => self::PASSO]) : Pannello::t('Si è registrato · in attesa che attivi'),
                'casella' => $ordine[(int) $r['id']] ?? null,
                'pubblicato' => $pubblicato, 'status' => (string) $r['status'],
                'data' => (string) ($pubblicato ? ($r['qualified_at'] ?: $r['created_at']) : $r['created_at']),
                'risparmio' => $r['status'] === 'valido' ? $passo : 0,
            ];
        }
        return $out;
    }

    /**
     * Per l'amministrazione: chi ha uno sconto inviti da applicare a mano al rinnovo (abbonamento
     * attivato dallo staff), con la percentuale e l'importo calcolati sul prezzo del rinnovo.
     */
    public static function daApplicareAMano(): array
    {
        if (!self::disponibili()) return [];
        $out = [];
        foreach (Db::all("SELECT DISTINCT r.referrer_account_id AS id FROM referrals r WHERE r.status = 'valido'") as $x) {
            $aid = (int) $x['id'];
            if (!self::aMano($aid)) continue;
            $u = Db::one('SELECT u.name, u.email FROM accounts a JOIN users u ON u.id = a.user_id WHERE a.id = ?', [$aid]) ?: [];
            $out[] = ['account_id' => $aid, 'nome' => (string) ($u['name'] ?? ''), 'email' => (string) ($u['email'] ?? '')] + self::stato(['id' => $aid]);
        }
        return $out;
    }

    /** L'amministrazione ha applicato a mano lo sconto al rinnovo: gli inviti che contavano si chiudono. */
    public static function applicatoAMano(int $accountId): int
    {
        $n = 0;
        foreach (Db::all("SELECT id FROM referrals WHERE referrer_account_id = ? AND status IN ('valido', 'oltre')", [$accountId]) as $r) {
            Db::update('referrals', ['status' => 'usato', 'used_at' => Support::now(), 'used_ref' => 'manuale'], 'id = :id', ['id' => $r['id']]);
            $n++;
        }
        return $n;
    }

    // ------------------------------------------------------------ inviti per email

    /** Quanti indirizzi per invio, e quante email al giorno per account. */
    public const EMAIL_PER_INVIO = 10;
    public const EMAIL_AL_GIORNO = 20;

    /** L'impronta di un indirizzo: si conserva questa, non l'indirizzo. */
    public static function impronta(string $email): string { return hash('sha256', mb_strtolower(trim($email))); }

    /** m•••@gmail.com: quanto basta al cliente per riconoscere a chi ha scritto. */
    public static function maschera(string $email): string
    {
        [$nome, $dominio] = explode('@', mb_strtolower(trim($email)), 2) + ['', ''];
        return mb_substr($nome, 0, 1) . "\u{2022}\u{2022}\u{2022}@" . $dominio;
    }

    /**
     * Il sito manda l'invito agli indirizzi scritti dal cliente (separati da virgole, spazi o a capo).
     * Salta senza dirlo uno per uno, per non rivelare chi è già cliente: indirizzi non validi, il
     * proprio, quelli già invitati negli ultimi 30 giorni e chi ha già un account.
     * @return array{mandati:int,saltati:int,limite:bool,errore:string}
     */
    public static function perEmail(array $acc, string $elenco, string $messaggio): array
    {
        $esito = ['mandati' => 0, 'saltati' => 0, 'limite' => false, 'errore' => ''];
        if (!self::puoInvitare($acc) || !Migrator::tableExists('referral_invites')) { $esito['errore'] = 'Gli inviti si attivano con il tuo abbonamento.'; return $esito; }
        $chi = Db::one('SELECT u.email, u.name FROM accounts a JOIN users u ON u.id = a.user_id WHERE a.id = ?', [(int) $acc['id']]);
        $indirizzi = array_values(array_unique(array_filter(array_map(fn($x) => mb_strtolower(trim($x)), preg_split('/[\s,;]+/', $elenco) ?: []))));
        if (!$indirizzi) { $esito['errore'] = 'Scrivi almeno un indirizzo email.'; return $esito; }
        if (count($indirizzi) > self::EMAIL_PER_INVIO) { $esito['errore'] = 'Al massimo ' . self::EMAIL_PER_INVIO . ' indirizzi per volta.'; return $esito; }
        $messaggio = mb_substr(trim(preg_replace('/[\x00-\x09\x0B-\x1F\x7F]/u', '', $messaggio) ?? ''), 0, 400);
        $nome = self::nomeBreve((string) ($chi['name'] ?? ''));
        $link = self::link($acc); $codice = self::codice($acc);
        foreach ($indirizzi as $email) {
            $impronta = self::impronta($email);
            $salta = !filter_var($email, FILTER_VALIDATE_EMAIL) || $email === mb_strtolower((string) ($chi['email'] ?? ''))
                || Db::val('SELECT id FROM referral_invites WHERE account_id = ? AND email_hash = ? AND sent_at > ?', [(int) $acc['id'], $impronta, gmdate('Y-m-d\TH:i:s\Z', time() - 30 * 86400)])
                || Db::val('SELECT id FROM users WHERE LOWER(email) = ?', [$email]);
            if ($salta) { $esito['saltati']++; continue; }
            if (!RateLimit::hit('inviti-email:' . (int) $acc['id'], self::EMAIL_AL_GIORNO, 86400)) { $esito['limite'] = true; break; }
            // 9H · Chi riceve l'invito è un possibile cliente: se risponde, la risposta va all'email commerciale.
            if (!Mailer::conRispostaA(Support::commerciale(), fn() => Mailer::send($email, ...self::testoEmail($nome, $messaggio, $link, $codice)))) { $esito['errore'] = 'La posta non è partita: riprova più tardi.'; break; }
            Db::insert('referral_invites', ['account_id' => (int) $acc['id'], 'email_hash' => $impronta, 'email_mask' => self::maschera($email), 'sent_at' => Support::now()]);
            $esito['mandati']++;
        }
        return $esito;
    }

    /** [oggetto, testo, html] dell'invito. Il messaggio personale va com'è, protetto. */
    private static function testoEmail(string $nome, string $messaggio, string $link, string $codice): array
    {
        $e = fn(string $x) => htmlspecialchars($x, ENT_QUOTES, 'UTF-8');
        $oggetto = $nome . ' ti invita su MyHouse Welcome: ' . "\u{2212}" . self::AMICO . '% sul primo anno';
        $intro = $nome . ' usa MyHouse Welcome per la guida digitale della sua struttura e ti invita a provarla.';
        $sconto = 'Con il suo invito hai il ' . self::AMICO . '% di sconto sul primo anno. Crei la guida gratis e paghi solo quando la pubblichi.';
        $piede = 'Ricevi questa email perché ' . $nome . ' ha scritto il tuo indirizzo nel suo pannello. Non lo conserviamo e non ti scriveremo altro.';
        $paragrafi = array_values(array_filter([$intro, $messaggio !== '' ? '«' . $messaggio . '»' : '', $sconto,
                                                'Oppure scrivi il codice ' . $codice . ' dove si inserisce il codice sconto.']));
        $testo = Mailer::testo('Ciao, ' . $nome . ' ti invita', $paragrafi, ['Crea la tua guida', $link], null, $piede);
        $html = Mailer::layout('Ciao, ' . $nome . ' ti invita', $paragrafi, ['Crea la tua guida', $link], null, $e($piede));
        return [$oggetto, $testo, $html];
    }

    /** Gli inviti mandati per email, dal più recente: indirizzo mascherato, data, e a che punto è l'amico. */
    public static function invitiEmail(int $accountId): array
    {
        if (!Migrator::tableExists('referral_invites')) return [];
        $amici = [];
        foreach (Db::all('SELECT r.status, u.email FROM referrals r JOIN accounts a ON a.id = r.friend_account_id JOIN users u ON u.id = a.user_id WHERE r.referrer_account_id = ?', [$accountId]) as $r) {
            $amici[self::impronta((string) $r['email'])] = $r['status'];
        }
        return array_map(fn($r) => $r + ['stato' => $amici[$r['email_hash']] ?? 'mandato'],
            Db::all('SELECT email_hash, email_mask, sent_at FROM referral_invites WHERE account_id = ? ORDER BY sent_at DESC, id DESC LIMIT 50', [$accountId]));
    }

    /** Gli amici invitati, dal più recente: nome breve, stato, date. Per l'amministrazione anche gli annullati, con il motivo. */
    public static function amici(int $accountId): array
    {
        $motivo = Migrator::columnExists('referrals', 'motivo') ? 'r.motivo' : "'' AS motivo";
        $righe = Db::all("SELECT r.id, r.status, r.created_at, r.qualified_at, r.friend_account_id, $motivo, u.name, u.email FROM referrals r JOIN accounts a ON a.id = r.friend_account_id
                          JOIN users u ON u.id = a.user_id WHERE r.referrer_account_id = ? ORDER BY r.id DESC LIMIT 50", [$accountId]);
        foreach ($righe as &$r) $r['nome'] = self::nomeBreve((string) $r['name']);
        unset($r);
        return $righe;
    }

    /**
     * Dall'amministrazione, scheda del cliente: «Collega a un invito». Per quando il link si è perso per strada (amico
     * registrato senza passare dal link, sessione scaduta, invito annullato per sbaglio): l'amico si lega al codice di chi
     * lo ha invitato. Se ha già pagato, l'invito è subito «valido» (o «oltre» se chi invita ha già i suoi 10) e lo sconto di
     * chi invita va su Stripe; se no resta «registrato» e si accende quando paga. L'amico non riceve il suo 5%: quello
     * si applica solo prima del primo pagamento. Il controllo dei dati di fatturazione uguali qui non c'è: decide chi
     * amministra. @return string lo stato dell'invito
     */
    public static function collegaAMano(int $amicoId, string $codice, int $adminId): string
    {
        $chi = self::daCodice($codice);
        if (!$chi) throw new \RuntimeException('Codice di invito sconosciuto: sono 6 lettere o cifre, lo trovi in «Invita un amico» del cliente che invita.');
        $rid = (int) $chi['id'];
        $amico = Db::one('SELECT * FROM accounts WHERE id = ?', [$amicoId]);
        if (!$amico) throw new \RuntimeException('Cliente inesistente.');
        if ($rid === $amicoId) throw new \RuntimeException('È il codice di questo stesso cliente.');
        if (!self::puoInvitare($chi)) throw new \RuntimeException('Chi invita non ha un abbonamento attivo: l\'invito non può contare.');
        $gia = Db::one('SELECT * FROM referrals WHERE friend_account_id = ?', [$amicoId]);
        if ($gia && $gia['status'] !== 'annullato') throw new \RuntimeException('Questo cliente è già legato a un invito (stato: ' . $gia['status'] . ').');
        $ordine = Db::one("SELECT id FROM orders WHERE account_id = ? AND status = 'paid' AND kind = 'new' ORDER BY id", [$amicoId])
               ?: Db::one("SELECT id FROM orders WHERE account_id = ? AND status = 'paid' ORDER BY id", [$amicoId]);
        $stato = $ordine ? (self::validi($rid) >= self::amiciMassimi() ? 'oltre' : 'valido') : 'registrato';
        $riga = ['referrer_account_id' => $rid, 'status' => $stato, 'order_id' => $ordine ? (int) $ordine['id'] : null,
                 'qualified_at' => $ordine ? Support::now() : null] + (Migrator::columnExists('referrals', 'motivo') ? ['motivo' => 'collegato dall\'amministrazione'] : []);
        if ($gia) Db::update('referrals', $riga, 'id = :id', ['id' => (int) $gia['id']]);
        else Db::insert('referrals', $riga + ['friend_account_id' => $amicoId, 'created_at' => Support::now()]);
        Auth::audit('referral.manual_link', (int) $amico['user_id'] ?: null, ['amico' => $amicoId, 'chi' => $rid, 'codice' => strtoupper(trim($codice)), 'stato' => $stato, 'admin' => $adminId]);
        if ($stato === 'valido') { try { self::sincronizza($rid); } catch (\Throwable $e) { Log::exception($e, 'inviti: collega a mano'); } }
        return $stato;
    }

    /** Dall'amministrazione: un invito non conta più (rimborso, abuso). Uno «oltre» prende il suo posto. */
    public static function annulla(int $id): void
    {
        $r = Db::one('SELECT * FROM referrals WHERE id = ?', [$id]);
        if (!$r || in_array($r['status'], ['usato', 'annullato'], true)) return;
        Db::update('referrals', ['status' => 'annullato'] + (Migrator::columnExists('referrals', 'motivo') ? ['motivo' => 'annullato dall\'amministrazione'] : []), 'id = :id', ['id' => $id]);
        if ($r['status'] === 'valido') {
            $o = Db::one("SELECT id FROM referrals WHERE referrer_account_id = ? AND status = 'oltre' ORDER BY qualified_at, id", [(int) $r['referrer_account_id']]);
            if ($o) Db::update('referrals', ['status' => 'valido'], 'id = :id', ['id' => $o['id']]);
        }
        try { self::sincronizza((int) $r['referrer_account_id']); } catch (\Throwable $e) { Log::exception($e, 'inviti: annulla'); }
    }
}
