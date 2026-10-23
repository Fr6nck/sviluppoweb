<?php
namespace MHW;

/**
 * 9O · Il programma partner con le commissioni (le richieste «Diventa partner» restano in Partner, 9K).
 *
 * - Un partner approvato ha un codice (PM-ROSSI), la sua commissione (%), lo sconto per i suoi clienti sul primo anno (%),
 *   le commissioni sui rinnovi sì/no (si può staccare in ogni momento), IBAN, P.IVA, periodicità della liquidazione.
 * - Il codice si scrive dove si scrivono i codici sconto («Hai un codice?», riconosciuto dal prefisso PM-) o arriva dal link
 *   /?partner=PM-ROSSI (cookie di 30 giorni): lega l'account al partner, una volta sola ed entro il primo pagamento, e dà lo
 *   sconto del partner sul primo anno come sconto di sistema (si somma all'invito; con un codice sconto vale il più alto).
 *   Con un codice omaggio non si applica.
 * - A ogni pagamento riuscito di un suo cliente (primo anno, offerta su misura, rinnovo se i rinnovi sono accesi in quel
 *   momento) matura una commissione = percentuale × imponibile incassato (dopo gli sconti, senza IVA). Un rimborso la storna
 *   (una riga negativa se era già liquidata, da scalare dalla prossima). Le commissioni sono un costo: in Cassa stanno in
 *   una scheda a parte e non toccano gli incassi. Si liquidano con un bonifico su fattura del partner («Segna come pagata»).
 * - Il partner entra in /partner con un link senza password (48 ore) e vede solo struttura, città, piano, date e la sua
 *   commissione: mai email, importi pagati dal cliente o dati di pagamento.
 */
final class ProgrammaPartner
{
    public const PREFISSO = 'PM-';
    public const STATI = ['attivo' => 'Attivo', 'sospeso' => 'Sospeso', 'chiuso' => 'Chiuso'];
    public const PERIODI = ['trimestrale' => 'Trimestrale', 'mensile' => 'Mensile'];
    public const COOKIE = 'mhw_partner';
    public const GIORNI_COOKIE = 30;
    public const ORE_LINK = 48;

    /** Le email da mandare a transazione chiusa (Billing::dopoRisposta). */
    private static array $coda = [];

    public static function disponibili(): bool { return Migrator::tableExists('partner'); }

    public static function sembra(string $codice): bool { return str_starts_with(strtoupper(trim($codice)), self::PREFISSO); }

    public static function normalizza(string $codice): string { return mb_substr(strtoupper(preg_replace('/\s+/', '', $codice)), 0, 24); }

    public static function riga(int $id): ?array { return self::disponibili() ? Db::one('SELECT * FROM partner WHERE id = ?', [$id]) : null; }

    public static function perCodice(string $codice): ?array
    {
        return self::disponibili() ? Db::one('SELECT * FROM partner WHERE codice = ?', [self::normalizza($codice)]) : null;
    }

    /** Il partner di un account (se ce l'ha). */
    public static function diAccount(int $accountId): ?array
    {
        if (!self::disponibili() || !Migrator::columnExists('accounts', 'partner_id')) return null;
        $pid = (int) Db::val('SELECT partner_id FROM accounts WHERE id = ?', [$accountId], 0);
        return $pid ? self::riga($pid) : null;
    }

    public static function tutti(): array { return self::disponibili() ? Db::all('SELECT * FROM partner ORDER BY ragione_sociale, id') : []; }

    /**
     * Il link /?partner=PM-… (home): il codice resta 30 giorni (cookie) e in sessione; chi è già dentro e non ha pagato lo
     * prende subito. Codici sconosciuti o non attivi: niente.
     */
    public static function daLink(string $codice): ?array
    {
        if (!self::disponibili() || !($p = self::perCodice($codice)) || $p['stato'] !== 'attivo') return null;
        setcookie(self::COOKIE, (string) $p['codice'], ['expires' => time() + self::GIORNI_COOKIE * 86400, 'path' => Support::baseDir() . '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => Auth::httpsAttivo()]);
        $_COOKIE[self::COOKIE] = (string) $p['codice'];
        $_SESSION['partner'] = (string) $p['codice'];
        return $p;
    }

    /** Il partner arrivato dal link (sessione o cookie), se il codice vale ancora: per la fascia in cima a home e registrazione. */
    public static function dalLink(): ?array
    {
        if (!self::disponibili()) return null;
        $c = (string) ($_SESSION['partner'] ?? $_COOKIE[self::COOKIE] ?? '');
        $p = $c !== '' ? self::perCodice($c) : null;
        return $p && $p['stato'] === 'attivo' ? $p : null;
    }

    /** Il link pubblico con il codice già inserito. */
    public static function link(array $p): string { return Support::baseUrl() . '/?partner=' . rawurlencode((string) $p['codice']); }

    /** Un codice nuovo dalla ragione sociale (o dal referente): PM-ROSSI, PM-ROSSI2… */
    public static function genera(string $nome): string
    {
        $base = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nome) ?: ''));
        $parole = preg_split('/\s+/', trim(preg_replace('/\b(s\.?r\.?l\.?s?|s\.?p\.?a\.?|s\.?n\.?c\.?|s\.?a\.?s\.?|property|management|agenzia|immobiliare|di|the)\b/i', '', $nome)));
        $prima = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string) ($parole[0] ?? '')) ?: ''));
        $radice = self::PREFISSO . substr($prima !== '' ? $prima : ($base !== '' ? $base : 'PARTNER'), 0, 12);
        $c = $radice; $n = 2;
        while (Db::one('SELECT id FROM partner WHERE codice = ?', [$c])) $c = $radice . $n++;
        return $c;
    }

    /** I campi del partner, controllati. @throws \RuntimeException con il motivo */
    private static function dati(array $in, bool $nuovo): array
    {
        $riga = fn(string $k, int $max) => mb_substr(trim(preg_replace('/\s+/u', ' ', (string) ($in[$k] ?? ''))), 0, $max);
        $d = ['ragione_sociale' => $riga('ragione_sociale', 160), 'referente' => $riga('referente', 120), 'telefono' => $riga('telefono', 40), 'zona' => $riga('zona', 255),
              'email' => mb_strtolower(trim((string) ($in['email'] ?? ''))), 'iban' => strtoupper(preg_replace('/\s+/', '', (string) ($in['iban'] ?? ''))),
              'piva' => preg_replace('/\D/', '', (string) ($in['piva'] ?? '')), 'periodicita' => (string) ($in['periodicita'] ?? 'trimestrale'),
              'commissione_pct' => (int) ($in['commissione_pct'] ?? 0), 'sconto_clienti_pct' => (int) ($in['sconto_clienti_pct'] ?? 0),
              'rinnovi' => !empty($in['rinnovi']) ? 1 : 0, 'lingua' => ($in['lingua'] ?? 'it') === 'en' ? 'en' : 'it', 'note' => mb_substr(trim((string) ($in['note'] ?? '')), 0, 2000)];
        if ($d['ragione_sociale'] === '' && $d['referente'] === '') throw new \RuntimeException('Scrivi la ragione sociale o il nome del referente.');
        if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) throw new \RuntimeException('Controlla l\'email del partner.');
        if ($d['commissione_pct'] < 1 || $d['commissione_pct'] > 50) throw new \RuntimeException('La commissione va da 1 a 50%.');
        if ($d['sconto_clienti_pct'] < 0 || $d['sconto_clienti_pct'] > 50) throw new \RuntimeException('Lo sconto per i clienti va da 0 a 50%.');
        if (!isset(self::PERIODI[$d['periodicita']])) throw new \RuntimeException('Scegli la periodicità della liquidazione.');
        if ($d['iban'] !== '' && !preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{10,30}$/', $d['iban'])) throw new \RuntimeException('Controlla l\'IBAN (per esempio IT60X0542811101000000123456).');
        if ($d['piva'] !== '' && !Fatturazione::partitaIvaValida($d['piva'])) throw new \RuntimeException('Questa partita IVA non torna: controlla le 11 cifre.');
        return $d;
    }

    /** «Approva e crea il partner»: dalla richiesta (o da zero), con il codice e l'email di benvenuto. */
    public static function crea(array $in, ?int $richiestaId, int $adminId): array
    {
        if (!self::disponibili()) throw new \RuntimeException('Il programma partner non è ancora attivo: manca la migrazione 060.');
        $d = self::dati($in, true);
        if (Db::one("SELECT id FROM partner WHERE email = ? AND stato <> 'chiuso'", [$d['email']])) throw new \RuntimeException('Esiste già un partner con questa email.');
        $codice = trim((string) ($in['codice'] ?? '')) !== '' ? self::normalizza((string) $in['codice']) : self::genera($d['ragione_sociale'] ?: $d['referente']);
        if (!preg_match('/^PM-[A-Z0-9]{2,20}$/', $codice)) throw new \RuntimeException('Il codice parte da PM- e ha solo lettere e cifre (per esempio PM-ROSSI).');
        if (Db::one('SELECT id FROM partner WHERE codice = ?', [$codice])) throw new \RuntimeException('Il codice ' . $codice . ' esiste già.');
        $id = Db::insert('partner', $d + ['richiesta_id' => $richiestaId, 'codice' => $codice, 'stato' => 'attivo', 'created_at' => Support::now(), 'updated_at' => Support::now()]);
        if ($richiestaId && Partner::disponibili()) Partner::cambiaStato($richiestaId, 'attivo');
        Auth::audit('partner.creato', null, ['partner' => $id, 'codice' => $codice, 'commissione' => $d['commissione_pct'], 'sconto' => $d['sconto_clienti_pct'], 'rinnovi' => $d['rinnovi'], 'admin' => $adminId]);
        $p = self::riga($id);
        self::emailBenvenuto($p);
        return $p;
    }

    /** Le modifiche dalla scheda del partner (ognuna nel Registro azioni). */
    public static function aggiorna(int $id, array $in, int $adminId): void
    {
        $p = self::riga($id);
        if (!$p) throw new \RuntimeException('Partner non trovato.');
        $d = self::dati($in + $p, false);
        $cambi = [];
        foreach ($d as $k => $v) if ((string) $p[$k] !== (string) $v) $cambi[$k] = ['da' => $p[$k], 'a' => $v];
        if (!$cambi) return;
        Db::update('partner', $d + ['updated_at' => Support::now()], 'id = :id', ['id' => $id]);
        Auth::audit('partner.modificato', null, ['partner' => $id, 'codice' => $p['codice'], 'cambi' => $cambi, 'admin' => $adminId]);
    }

    public static function stato(int $id, string $stato, int $adminId): void
    {
        $p = self::riga($id);
        if (!$p || !isset(self::STATI[$stato])) throw new \RuntimeException('Stato non valido.');
        Db::update('partner', ['stato' => $stato, 'updated_at' => Support::now()], 'id = :id', ['id' => $id]);
        Auth::audit('partner.stato', null, ['partner' => $id, 'codice' => $p['codice'], 'da' => $p['stato'], 'a' => $stato, 'admin' => $adminId]);
    }

    // ---------------------------------------------------------------- il codice del cliente

    /** Il codice vale per questo account? @throws \RuntimeException con il motivo */
    public static function valida(string $codice, ?array $acc): array
    {
        $p = self::perCodice($codice);
        if (!$p || $p['stato'] !== 'attivo') throw new \RuntimeException('Questo codice partner non esiste o non è più attivo.');
        if ($acc) {
            if (Omaggi::disponibili() && Omaggi::daUsare($acc)) throw new \RuntimeException('Con il codice omaggio il codice partner non si applica.');
            if (Db::one("SELECT id FROM orders WHERE account_id = ? AND status = 'paid'", [(int) $acc['id']])) throw new \RuntimeException('Il codice partner vale entro il primo pagamento.');
            if (!empty($acc['partner_id']) && (int) $acc['partner_id'] !== (int) $p['id']) throw new \RuntimeException('Il tuo account è già legato a un altro partner.');
        }
        return $p;
    }

    /** Lega l'account al partner (una volta sola). @return array il partner */
    public static function applica(array $acc, string $codice): array
    {
        $p = self::valida($codice, $acc);
        if ((int) ($acc['partner_id'] ?? 0) !== (int) $p['id']) {
            Db::update('accounts', ['partner_id' => (int) $p['id'], 'partner_codice_usato_at' => Support::now()], 'id = :aid', ['aid' => (int) $acc['id']]);
            Auth::audit('partner.codice_usato', (int) Db::val('SELECT user_id FROM accounts WHERE id = ?', [(int) $acc['id']], 0) ?: null,
                        ['partner' => (int) $p['id'], 'codice' => $p['codice'], 'cliente' => Codici::cliente((int) $acc['id'])]);
        }
        return $p;
    }

    /** Lo sconto di sistema del partner sul primo anno (Sconti::sistema), o null. */
    public static function scontoDiSistema(?array $acc): ?array
    {
        if (!$acc || empty($acc['partner_id']) || !self::disponibili()) return null;
        $p = self::riga((int) $acc['partner_id']);
        if (!$p || $p['stato'] !== 'attivo' || (int) $p['sconto_clienti_pct'] <= 0) return null;
        if (Db::one("SELECT id FROM orders WHERE account_id = ? AND status = 'paid'", [(int) $acc['id']])) return null;
        return ['tipo' => 'partner', 'nome' => strtr(Pannello::t('Codice partner {codice}'), ['{codice}' => $p['codice']]), 'percento' => (int) $p['sconto_clienti_pct']];
    }

    // ---------------------------------------------------------------- le commissioni

    /**
     * Un pagamento riuscito di un cliente: la commissione matura (dentro la transazione del webhook). Idempotente su $paymentId.
     * Tipo «primo_anno» per il primo pagamento che porta una commissione, poi «rinnovo» (solo se i rinnovi sono accesi adesso).
     * $ciclo: il pagamento apre un periodo (attivazione, rinnovo, bonifico); false per un conguaglio durante il periodo (cambio di
     * piano, strutture in più), che resta del tipo del periodo in cui cade: nel primo anno è ancora «primo_anno».
     */
    public static function matura(int $accountId, string $paymentId, int $imponibile, string $codicePagamento = '', bool $ciclo = true): ?int
    {
        $p = self::diAccount($accountId);
        if (!$p || $p['stato'] !== 'attivo' || $imponibile <= 0 || (int) $p['commissione_pct'] <= 0) return null;
        // 9R · Niente commissione su un pagamento di test di Stripe, di un account staff o escluso dai conti.
        if (Billing::$liveEvento === false || Staff::e($accountId) || isset(Gestione::esclusi()[$paymentId])) return null;
        if (Db::one('SELECT id FROM partner_commissioni WHERE payment_id = ?', [$paymentId])) return null;
        $ultimo = Db::val("SELECT tipo FROM partner_commissioni WHERE account_id = ? AND importo_cents > 0 ORDER BY id DESC", [$accountId]);
        $tipo = $ultimo === null || $ultimo === false ? 'primo_anno' : ($ciclo ? 'rinnovo' : (string) $ultimo);
        if ($tipo === 'rinnovo' && (int) $p['rinnovi'] !== 1) return null;
        $importo = (int) round($imponibile * (int) $p['commissione_pct'] / 100);
        $id = Db::insert('partner_commissioni', ['partner_id' => (int) $p['id'], 'account_id' => $accountId, 'payment_id' => $paymentId, 'codice_pagamento' => $codicePagamento,
                                                 'tipo' => $tipo, 'imponibile_cents' => $imponibile, 'pct' => (int) $p['commissione_pct'], 'importo_cents' => $importo,
                                                 'stato' => 'maturata', 'created_at' => Support::now()]);
        Auth::audit('partner.commissione', null, ['partner' => (int) $p['id'], 'codice' => $p['codice'], 'cliente' => Codici::cliente($accountId), 'tipo' => $tipo, 'imponibile' => $imponibile, 'importo' => $importo]);
        self::$coda[] = ['nuova', $id];
        return $id;
    }

    /**
     * Un rimborso di un cliente con partner: la commissione dell'ultimo pagamento si storna (tutta se il rimborso è totale,
     * in proporzione all'imponibile rimborsato se è parziale). Se era ancora da liquidare e il rimborso è totale si segna
     * «stornata»; altrimenti una riga negativa da scalare dalla prossima liquidazione. Idempotente sull'id del rimborso.
     */
    public static function storna(int $accountId, string $refundId, int $rimborsoLordo, bool $totale): ?int
    {
        if (!self::disponibili() || $refundId === '' || Db::one('SELECT id FROM partner_commissioni WHERE payment_id = ?', ['storno:' . $refundId])) return null;
        $c = Db::one("SELECT * FROM partner_commissioni WHERE account_id = ? AND importo_cents > 0 AND stato <> 'stornata' ORDER BY id DESC", [$accountId]);
        if (!$c) return null;
        $gia = -(int) Db::val('SELECT COALESCE(SUM(importo_cents), 0) FROM partner_commissioni WHERE payment_id LIKE ? AND account_id = ? AND id > ?', ['storno:%', $accountId, (int) $c['id']], 0);
        $resta = max(0, (int) $c['importo_cents'] - $gia);
        if ($resta <= 0) return null;
        $netto = (int) round($rimborsoLordo * 100 / (100 + Iva::aliquota()));
        $importo = $totale ? $resta : min($resta, (int) round($netto * (int) $c['pct'] / 100));
        if ($importo <= 0) return null;
        $p = self::riga((int) $c['partner_id']);
        if ($totale && $c['stato'] === 'maturata' && $gia === 0) {
            Db::update('partner_commissioni', ['stato' => 'stornata', 'stornata_at' => Support::now()], 'id = :id', ['id' => (int) $c['id']]);
            // Una riga a zero tiene l'id del rimborso: lo stesso evento non storna due volte.
            $id = Db::insert('partner_commissioni', ['partner_id' => (int) $c['partner_id'], 'account_id' => $accountId, 'payment_id' => 'storno:' . $refundId, 'codice_pagamento' => (string) $c['codice_pagamento'],
                                                     'tipo' => $c['tipo'], 'imponibile_cents' => 0, 'pct' => (int) $c['pct'], 'importo_cents' => 0, 'stato' => 'stornata', 'stornata_at' => Support::now(), 'created_at' => Support::now()]);
        } else {
            $id = Db::insert('partner_commissioni', ['partner_id' => (int) $c['partner_id'], 'account_id' => $accountId, 'payment_id' => 'storno:' . $refundId, 'codice_pagamento' => (string) $c['codice_pagamento'],
                                                     'tipo' => $c['tipo'], 'imponibile_cents' => -min($netto, (int) $c['imponibile_cents']), 'pct' => (int) $c['pct'], 'importo_cents' => -$importo,
                                                     'stato' => 'maturata', 'created_at' => Support::now()]);
        }
        Auth::audit('partner.storno', null, ['partner' => (int) $c['partner_id'], 'codice' => $p['codice'] ?? '', 'cliente' => Codici::cliente($accountId), 'commissione' => (int) $c['id'], 'importo' => $importo, 'totale' => $totale]);
        self::$coda[] = ['storno', (int) $c['id'], $importo];
        return $id;
    }

    /** A transazione chiusa (Billing::dopoRisposta): le email al partner. Non solleva mai. */
    public static function dopoEvento(): void
    {
        $coda = self::$coda; self::$coda = [];
        foreach ($coda as $x) {
            try {
                if ($x[0] === 'nuova') self::emailCommissione((int) $x[1]);
                else self::emailStorno((int) $x[1], (int) $x[2]);
            } catch (\Throwable $e) { Log::exception($e, 'email al partner'); }
        }
    }

    // ---------------------------------------------------------------- i numeri e le liquidazioni

    /** Il periodo in corso della liquidazione (inizio, AAAA-MM-GG): il trimestre o il mese. */
    public static function inizioPeriodo(string $periodicita, ?int $t = null): string
    {
        $t ??= time(); $m = (int) gmdate('n', $t);
        return $periodicita === 'mensile' ? gmdate('Y-m-01', $t) : gmdate('Y', $t) . '-' . sprintf('%02d', intdiv($m - 1, 3) * 3 + 1) . '-01';
    }

    /** Per partner: le righe maturate (positive e negative) ancora da liquidare. @return list<array{partner:array,righe:array,totale:int,da:string,a:string,scaduto:bool,clienti:int,imponibile:int}> */
    public static function daLiquidare(): array
    {
        if (!self::disponibili()) return [];
        $out = [];
        foreach (self::tutti() as $p) {
            $righe = Db::all("SELECT * FROM partner_commissioni WHERE partner_id = ? AND stato = 'maturata' ORDER BY created_at, id", [(int) $p['id']]);
            $tot = array_sum(array_map(fn($r) => (int) $r['importo_cents'], $righe));
            $da = $righe ? substr((string) $righe[0]['created_at'], 0, 10) : '';
            $out[] = ['partner' => $p, 'righe' => $righe, 'totale' => $tot, 'da' => $da, 'a' => $righe ? substr((string) end($righe)['created_at'], 0, 10) : '',
                      'scaduto' => $righe && $tot > 0 && $da < self::inizioPeriodo((string) $p['periodicita']),
                      'clienti' => count(array_unique(array_map(fn($r) => (int) $r['account_id'], array_filter($righe, fn($r) => (int) $r['importo_cents'] > 0)))),
                      'imponibile' => array_sum(array_map(fn($r) => (int) $r['imponibile_cents'], $righe))];
        }
        return $out;
    }

    /** I numeri della scheda «Commissioni partner» della Cassa. */
    public static function numeri(int $anno): array
    {
        $dl = self::daLiquidare();
        $da = array_filter($dl, fn($x) => $x['totale'] > 0);
        $liquidate = (int) Db::val('SELECT COALESCE(SUM(importo_cents), 0) FROM partner_liquidazioni WHERE data_bonifico >= ? AND data_bonifico < ?', [$anno . '-01-01', ($anno + 1) . '-01-01'], 0);
        $clienti = Migrator::columnExists('accounts', 'partner_id') ? (int) Db::val('SELECT COUNT(DISTINCT s.account_id) FROM subscriptions s JOIN accounts a ON a.id = s.account_id
                   WHERE a.partner_id IS NOT NULL AND s.status IN (\'active\',\'trialing\') AND s.current_period_end > ?', [Support::now()], 0) : 0;
        $imponibile = (int) Db::val('SELECT COALESCE(SUM(imponibile_cents), 0) FROM partner_commissioni WHERE created_at >= ? AND created_at < ? AND stato <> \'stornata\'', [$anno . '-01-01', ($anno + 1) . '-01-01'], 0);
        return ['da_liquidare' => array_sum(array_column($da, 'totale')), 'partner_da_liquidare' => count($da), 'liquidate' => $liquidate, 'clienti' => $clienti, 'imponibile' => $imponibile];
    }

    /** Quanti partner hanno commissioni da liquidare con il periodo scaduto (Quadro, «Da fare oggi»). */
    public static function scaduti(): int { return count(array_filter(self::daLiquidare(), fn($x) => $x['scaduto'])); }

    /** «Segna come pagata»: la liquidazione (bonifico su fattura del partner), le righe liquidate, l'email, il Registro. */
    public static function liquida(int $partnerId, array $in, int $adminId): int
    {
        $p = self::riga($partnerId);
        if (!$p) throw new \RuntimeException('Partner non trovato.');
        $righe = Db::all("SELECT * FROM partner_commissioni WHERE partner_id = ? AND stato = 'maturata' ORDER BY created_at, id", [$partnerId]);
        $tot = array_sum(array_map(fn($r) => (int) $r['importo_cents'], $righe));
        if (!$righe || $tot <= 0) throw new \RuntimeException('Niente da liquidare per questo partner.');
        $data = trim((string) ($in['data'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) || $data > gmdate('Y-m-d')) throw new \RuntimeException('Scrivi la data del bonifico (oggi o prima).');
        $importo = Offerte::euro((string) ($in['importo'] ?? '')) ?? -1;
        if ($importo !== $tot) throw new \RuntimeException('L\'importo del bonifico deve essere quello da liquidare: ' . Support::money($tot) . '.');
        $rif = mb_substr(trim((string) ($in['riferimento'] ?? '')), 0, 120); $fattura = mb_substr(trim((string) ($in['fattura'] ?? '')), 0, 60);
        if ($rif === '') throw new \RuntimeException('Scrivi il riferimento del bonifico (CRO o causale).');
        if ($fattura === '') throw new \RuntimeException('Scrivi il numero della fattura del partner.');
        $id = Db::tx(function () use ($partnerId, $righe, $tot, $data, $rif, $fattura, $adminId) {
            $id = Db::insert('partner_liquidazioni', ['partner_id' => $partnerId, 'periodo_da' => substr((string) $righe[0]['created_at'], 0, 10), 'periodo_a' => substr((string) end($righe)['created_at'], 0, 10),
                                                      'importo_cents' => $tot, 'data_bonifico' => $data, 'riferimento' => $rif, 'fattura_partner' => $fattura, 'creata_da' => $adminId ?: null, 'created_at' => Support::now()]);
            foreach ($righe as $r) Db::update('partner_commissioni', ['stato' => 'liquidata', 'liquidazione_id' => $id], 'id = :id', ['id' => (int) $r['id']]);
            return $id;
        });
        Auth::audit('partner.liquidazione', null, ['partner' => $partnerId, 'codice' => $p['codice'], 'liquidazione' => $id, 'importo' => $tot, 'riferimento' => $rif, 'fattura' => $fattura, 'admin' => $adminId]);
        try { self::emailLiquidazione($id); } catch (\Throwable $e) { Log::exception($e, 'email liquidazione partner'); }
        return $id;
    }

    /**
     * I clienti portati dal partner, per la scheda admin e per /partner. Solo quello che il partner può vedere (più il codice
     * cliente e l'imponibile per l'amministrazione): struttura, città, piano, dal, ultimo pagamento, commissioni e il loro stato.
     */
    public static function clienti(int $partnerId): array
    {
        if (!Migrator::columnExists('accounts', 'partner_id')) return [];
        $out = [];
        foreach (Db::all('SELECT a.id, a.partner_codice_usato_at FROM accounts a WHERE a.partner_id = ? ORDER BY a.partner_codice_usato_at, a.id', [$partnerId]) as $a) {
            $aid = (int) $a['id'];
            $pr = Db::one('SELECT name, city FROM properties WHERE account_id = ? AND is_demo = 0 ORDER BY id', [$aid]) ?: ['name' => '', 'city' => ''];
            $s = Db::one('SELECT s.*, pk.name AS piano FROM subscriptions s JOIN package_versions pv ON pv.id = s.package_version_id JOIN packages pk ON pk.id = pv.package_id
                          WHERE s.account_id = ? ORDER BY s.id DESC', [$aid]);
            $comm = Db::all('SELECT * FROM partner_commissioni WHERE account_id = ? AND partner_id = ? ORDER BY id', [$aid, $partnerId]);
            $ultima = null; foreach ($comm as $c) if ((int) $c['importo_cents'] > 0) $ultima = $c;
            $out[] = ['account_id' => $aid, 'codice_cliente' => Codici::cliente($aid), 'struttura' => (string) $pr['name'], 'citta' => (string) $pr['city'],
                      'piano' => $s ? (string) $s['piano'] . ((int) $s['quantity'] > 1 ? ' · ' . (int) $s['quantity'] : '') : '', 'attivo' => $s && Subscriptions::valid($s),
                      'stato_abbonamento' => (string) ($s['status'] ?? ''), 'dal' => (string) ($a['partner_codice_usato_at'] ?? ''), 'fine' => (string) ($s['current_period_end'] ?? ''),
                      'rinnovo_auto' => $s && (int) $s['cancel_at_period_end'] === 0 && $s['provider'] === 'stripe',
                      'ultimo_pagamento' => $ultima ? (string) $ultima['created_at'] : '', 'ultima' => $ultima, 'commissioni' => $comm,
                      'imponibile' => array_sum(array_map(fn($c) => $c['stato'] === 'stornata' ? 0 : (int) $c['imponibile_cents'], $comm)),
                      'commissione' => array_sum(array_map(fn($c) => $c['stato'] === 'stornata' ? 0 : (int) $c['importo_cents'], $comm))];
        }
        return $out;
    }

    /** I quattro numeri della pagina del partner. */
    public static function riepilogo(int $partnerId): array
    {
        $cl = self::clienti($partnerId);
        $tra90 = gmdate('Y-m-d\TH:i:s\Z', time() + 90 * 86400);
        return ['attivi' => count(array_filter($cl, fn($c) => $c['attivo'])),
                'da_ricevere' => (int) Db::val("SELECT COALESCE(SUM(importo_cents), 0) FROM partner_commissioni WHERE partner_id = ? AND stato = 'maturata'", [$partnerId], 0),
                'ricevuto_anno' => (int) Db::val('SELECT COALESCE(SUM(importo_cents), 0) FROM partner_liquidazioni WHERE partner_id = ? AND data_bonifico >= ?', [$partnerId, gmdate('Y') . '-01-01'], 0),
                'rinnovi_90' => count(array_filter($cl, fn($c) => $c['attivo'] && $c['rinnovo_auto'] && $c['fine'] !== '' && $c['fine'] <= $tra90))];
    }

    // ---------------------------------------------------------------- l'accesso del partner (/partner)

    /** Manda il link per entrare (48 ore, una volta sola). Sempre «ti abbiamo scritto», anche se l'email non è di un partner. */
    public static function mandaLink(string $email): void
    {
        if (!self::disponibili()) return;
        $p = Db::one("SELECT * FROM partner WHERE email = ? AND stato <> 'chiuso' ORDER BY id DESC", [mb_strtolower(trim($email))]);
        if (!$p) return;
        $token = bin2hex(random_bytes(24));
        Db::insert('partner_accessi', ['partner_id' => (int) $p['id'], 'token_hash' => hash('sha256', $token), 'scade_at' => gmdate('Y-m-d\TH:i:s\Z', time() + self::ORE_LINK * 3600), 'created_at' => Support::now()]);
        $en = $p['lingua'] === 'en';
        Mailer::conRispostaA(Support::commerciale(), fn() => Mailer::inLingua($p['lingua'], fn() => Mailer::manda((string) $p['email'],
            $en ? 'Your MyHouse Welcome partner page' : 'La tua pagina partner di MyHouse Welcome', $en ? 'Here is your link.' : 'Ecco il tuo link.',
            [$en ? 'Open your partner page with this link: no password needed. It works once and for ' . self::ORE_LINK . ' hours.' : 'Apri la tua pagina partner con questo link: niente password. Vale una volta sola e per ' . self::ORE_LINK . ' ore.'],
            [$en ? 'Open your partner page' : 'Apri la tua pagina partner', Support::baseUrl() . '/partner/entra/' . $token],
            $en ? 'If you did not ask for it, ignore this email.' : 'Se non l\'hai chiesto tu, ignora questa email.')));
        Auth::audit('partner.link', null, ['partner' => (int) $p['id']]);
    }

    /** Il link aperto: il partner (e il link non vale più), o null. */
    public static function entra(string $token): ?array
    {
        if (!self::disponibili() || !preg_match('/^[a-f0-9]{48}$/', $token)) return null;
        $a = Db::one('SELECT * FROM partner_accessi WHERE token_hash = ?', [hash('sha256', $token)]);
        if (!$a || !empty($a['usato_at']) || (string) $a['scade_at'] < Support::now()) return null;
        Db::update('partner_accessi', ['usato_at' => Support::now()], 'id = :id', ['id' => (int) $a['id']]);
        $p = self::riga((int) $a['partner_id']);
        return $p && $p['stato'] !== 'chiuso' ? $p : null;
    }

    // ---------------------------------------------------------------- le email (da commerciale@)

    private static function manda(array $p, string $oggetto, string $titolo, array $par, ?array $bottone): bool
    {
        $en = $p['lingua'] === 'en';
        $nota = ($en ? 'You receive this email because you are a MyHouse Welcome partner. ' : 'Ricevi questa email perché sei partner di MyHouse Welcome. ') . Support::commerciale();
        return (bool) Mailer::conRispostaA(Support::commerciale(), fn() => Mailer::inLingua($p['lingua'], fn() => Mailer::manda((string) $p['email'], $oggetto, $titolo, $par, $bottone, $nota)));
    }

    public static function emailBenvenuto(array $p): bool
    {
        $en = $p['lingua'] === 'en'; $nome = trim(explode(' ', trim((string) ($p['referente'] ?: $p['ragione_sociale'])))[0] ?? '');
        $sc = (int) $p['sconto_clienti_pct']; $pct = (int) $p['commissione_pct'];
        $par = $en ? [($nome !== '' ? "$nome, welcome" : 'Welcome') . ' to the MyHouse Welcome partner programme. Your code is ' . $p['codice'] . '.',
                      ($sc > 0 ? "Your clients get $sc% off their first year; " : '') . "you get $pct% of every payment they make" . ((int) $p['rinnovi'] ? ', renewals included' : ' in their first year') . ', calculated on the amount actually collected, VAT excluded.',
                      'Your link with the code already filled in: ' . self::link($p),
                      'On your partner page you will find your clients, your commissions, how to present MyHouse Welcome and a sheet to print with your code. We pay commissions by bank transfer against your invoice (' . ($p['periodicita'] === 'mensile' ? 'monthly' : 'quarterly') . ').']
                   : [($nome !== '' ? "$nome, benvenuto" : 'Benvenuto') . ' nel programma partner di MyHouse Welcome. Il tuo codice è ' . $p['codice'] . '.',
                      ($sc > 0 ? "I tuoi clienti hanno il $sc% di sconto sul primo anno; " : '') . "a te il $pct% di ogni pagamento che fanno" . ((int) $p['rinnovi'] ? ', rinnovi compresi' : ' nel primo anno') . ', calcolato sull\'imponibile incassato, senza IVA.',
                      'Il tuo link con il codice già inserito: ' . self::link($p),
                      'Nella tua pagina partner trovi i tuoi clienti, le commissioni, come presentare MyHouse Welcome ai proprietari e un foglio da stampare con il codice. Le commissioni le liquidiamo con bonifico su tua fattura (' . $p['periodicita'] . ').'];
        return self::manda($p, $en ? 'Welcome to the MyHouse Welcome partner programme' : 'Benvenuto nel programma partner di MyHouse Welcome', $en ? 'Your code: ' . $p['codice'] : 'Il tuo codice: ' . $p['codice'],
                           $par, [$en ? 'Open your partner page' : 'Apri la tua pagina partner', Support::baseUrl() . '/partner']);
    }

    private static function emailCommissione(int $id): void
    {
        $c = Db::one('SELECT * FROM partner_commissioni WHERE id = ?', [$id]);
        $p = $c ? self::riga((int) $c['partner_id']) : null;
        if (!$c || !$p) return;
        $cl = array_values(array_filter(self::clienti((int) $p['id']), fn($x) => $x['account_id'] === (int) $c['account_id']))[0] ?? ['struttura' => '', 'citta' => '', 'piano' => ''];
        $en = $p['lingua'] === 'en';
        $chi = trim($cl['struttura'] . ($cl['citta'] !== '' ? ' (' . $cl['citta'] . ')' : '')) ?: ($en ? 'A client' : 'Un cliente');
        $rinnovo = $c['tipo'] === 'rinnovo';
        $par = [$en ? $chi . ($rinnovo ? ' renewed the plan ' : ' activated the plan ') . $cl['piano'] . ' with your code ' . $p['codice'] . '.'
                    : $chi . ($rinnovo ? ' ha rinnovato il piano ' : ' ha attivato il piano ') . $cl['piano'] . ' con il tuo codice ' . $p['codice'] . '.',
                $en ? 'Your commission for this payment is ' . Support::money((int) $c['importo_cents']) . ' (' . (int) $c['pct'] . '% of ' . Support::money((int) $c['imponibile_cents']) . ' net of VAT). You will find it among those to be received on your partner page; we pay it by bank transfer at the next ' . ($p['periodicita'] === 'mensile' ? 'monthly' : 'quarterly') . ' date, against your invoice.'
                    : 'La tua commissione per questo pagamento è di ' . Support::money((int) $c['importo_cents']) . ' (' . (int) $c['pct'] . '% su ' . Support::money((int) $c['imponibile_cents']) . ' di imponibile). La trovi tra quelle da ricevere nella tua pagina partner; la liquidiamo con bonifico alla prossima scadenza ' . $p['periodicita'] . ', su tua fattura.'];
        self::manda($p, $rinnovo ? ($en ? 'A client of yours has renewed' : 'Un tuo cliente ha rinnovato') : ($en ? 'New client from your code' : 'Nuovo cliente dal tuo codice'),
                    $rinnovo ? ($en ? 'Renewal with your code.' : 'Rinnovo con il tuo codice.') : ($en ? 'New client from your code.' : 'Nuovo cliente dal tuo codice.'),
                    $par, [$en ? 'Open your partner page' : 'Apri la tua pagina partner', Support::baseUrl() . '/partner']);
    }

    private static function emailStorno(int $commissioneId, int $importo): void
    {
        $c = Db::one('SELECT * FROM partner_commissioni WHERE id = ?', [$commissioneId]);
        $p = $c ? self::riga((int) $c['partner_id']) : null;
        if (!$c || !$p) return;
        $en = $p['lingua'] === 'en';
        self::manda($p, $en ? 'Commission reversed' : 'Commissione stornata', $en ? 'Commission reversed.' : 'Commissione stornata.',
            [$en ? 'A client of yours has been refunded: we have reversed ' . Support::money($importo) . ' of your commission (payment ' . $c['codice_pagamento'] . ').'
                 : 'Un tuo cliente è stato rimborsato: abbiamo stornato ' . Support::money($importo) . ' della tua commissione (pagamento ' . $c['codice_pagamento'] . ').',
             $en ? 'If it had already been paid to you, we deduct it from the next settlement.' : 'Se ti era già stata liquidata, la scaliamo dalla prossima liquidazione.'],
            [$en ? 'Open your partner page' : 'Apri la tua pagina partner', Support::baseUrl() . '/partner']);
    }

    private static function emailLiquidazione(int $id): void
    {
        $l = Db::one('SELECT * FROM partner_liquidazioni WHERE id = ?', [$id]);
        $p = $l ? self::riga((int) $l['partner_id']) : null;
        if (!$l || !$p) return;
        $en = $p['lingua'] === 'en';
        self::manda($p, $en ? 'Settlement paid' : 'Liquidazione effettuata', $en ? 'Settlement paid.' : 'Liquidazione effettuata.',
            [$en ? 'We have paid you ' . Support::money((int) $l['importo_cents']) . ' for the commissions from ' . $l['periodo_da'] . ' to ' . $l['periodo_a'] . ' (your invoice ' . $l['fattura_partner'] . ').'
                 : 'Ti abbiamo liquidato ' . Support::money((int) $l['importo_cents']) . ' per le commissioni dal ' . Support::date($l['periodo_da']) . ' al ' . Support::date($l['periodo_a']) . ' (tua fattura ' . $l['fattura_partner'] . ').',
             ($en ? 'Bank transfer of ' : 'Bonifico del ') . ($en ? $l['data_bonifico'] : Support::date($l['data_bonifico'])) . ($en ? ', reference ' : ', riferimento ') . $l['riferimento'] . '.'],
            [$en ? 'Open your partner page' : 'Apri la tua pagina partner', Support::baseUrl() . '/partner']);
    }
}
