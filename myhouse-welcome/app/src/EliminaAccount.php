<?php
namespace MHW;

/**
 * «Elimina account» (Amministrazione → scheda cliente): l'unico modo di chiudere un account. Il cliente non lo fa da
 * solo: chiede la chiusura per email (Termini, «Recesso e chiusura»), e la fa l'amministrazione da qui.
 *
 * Due casi, decisi dai pagamenti:
 *  - **nessun pagamento live** (AccountProva::pagamentiLive): si cancella tutto, come «Elimina account di prova», e
 *    l'email torna libera;
 *  - **con pagamenti live**: i dati di fatturazione e dei pagamenti si conservano 10 anni (art. 2220 c.c.), quindi
 *    l'account resta, anonimo, solo per quelli. Si cancellano strutture e guide (con foto, PDF, QR, statistiche e
 *    domande degli ospiti), inviti mandati per email, giudizio e scheda, preventivi, email, note e preferenze; l'accesso
 *    sparisce (email «eliminato-…@eliminato.invalid», password casuale, ruolo «eliminato»: chi era dentro esce) e
 *    l'email vera torna libera. Restano: dati di fatturazione, ordini, abbonamenti, rimborsi, codici dei pagamenti,
 *    commissioni partner, inviti (per gli sconti degli altri), documenti accettati, il cliente su Stripe (le fatture).
 *
 * In tutti e due i casi prima si chiudono gli abbonamenti attivi: su Stripe subito, senza conguaglio né rimborso (se va
 * rimborsato qualcosa, si fa prima dal riquadro «Rimborso»). Se Stripe non risponde non si cancella niente.
 * Si conferma con «ELIMINA {codice cliente}», il motivo e la password dell'amministratore; l'email di conferma al
 * cliente parte prima, all'indirizzo vero. Nel Registro azioni resta l'email, che dopo 90 giorni si toglie
 * (AccountProva::pulisci).
 */
final class EliminaAccount
{
    /** Le tabelle con account_id che si svuotano anche quando l'account resta per i dati fiscali. */
    private const PERSONALI = ['assistant_questions', 'email_log', 'email_optout', 'entitlement_overrides', 'feedback', 'interessi_sito', 'novita_viste',
                               'preventivi', 'proposte_cambio', 'referral_invites', 'testimonials', 'translation_usage', 'vetrina_schede'];
    /** Le tabelle delle strutture senza chiave esterna verso properties (le altre si cancellano da sole con la struttura). */
    private const DELLE_STRUTTURE = ['assistant_questions', 'property_channels', 'room_variants', 'sezioni_versioni', 'translation_suggestions', 'translation_usage', 'vetrina_schede', 'testimonials'];
    /** Gli stati di un abbonamento che può ancora addebitare. */
    private const APERTI = ['active', 'trialing', 'past_due', 'unpaid', 'incomplete'];
    public const RUOLO = 'eliminato';
    public const DOMINIO = 'eliminato.invalid';

    public static function disponibile(): bool { return Migrator::columnExists('accounts', 'eliminato_at'); }

    /** L'account è stato chiuso (resta solo per i dati fiscali)? */
    public static function eliminato(array $acc): bool { return !empty($acc['eliminato_at']); }

    /** Gli abbonamenti che possono ancora addebitare, con quanto resta del periodo pagato. */
    public static function abbonamentiAperti(int $accountId): array
    {
        $out = [];
        foreach (Db::all('SELECT * FROM subscriptions WHERE account_id = ? ORDER BY id DESC', [$accountId]) as $s) {
            if (!in_array((string) $s['status'], self::APERTI, true)) continue;
            if ((string) $s['current_period_end'] !== '' && strtotime((string) $s['current_period_end']) < time() && $s['status'] !== 'past_due') continue;
            $out[] = $s;
        }
        return $out;
    }

    /**
     * Cosa succederebbe, per il riquadro della scheda cliente.
     * @return array{modo:string,email:string,codice:string,live:int,aperti:list<array>,righe:list<array{0:string,1:int}>,restano:list<string>,prova:bool}
     */
    public static function riepilogo(int $accountId): array
    {
        $p = AccountProva::riepilogo($accountId);
        $acc = Db::one('SELECT * FROM accounts WHERE id = ?', [$accountId]) ?? [];
        $live = AccountProva::pagamentiLive($accountId);
        $conta = fn(string $sql, array $a) => (int) Db::val($sql, $a, 0);
        $restano = [];
        $righe = $p['righe'] ?? [];
        if ($live > 0) {
            // Con i pagamenti: ordini, abbonamenti e inviti restano; si cancellano anche gli inviti mandati e le domande degli ospiti.
            $t = fn(string $tab) => Migrator::tableExists($tab);
            $righe = array_values(array_filter($righe, fn($x) => !str_starts_with($x[0], 'Ordini') && !str_starts_with($x[0], 'Inviti')));
            $righe[] = ['Inviti mandati per email (gli indirizzi degli amici)', $t('referral_invites') ? $conta('SELECT COUNT(*) FROM referral_invites WHERE account_id = ?', [$accountId]) : 0];
            $righe[] = ['Domande degli ospiti al Concierge AI', $t('assistant_questions') ? $conta('SELECT COUNT(*) FROM assistant_questions WHERE account_id = ?', [$accountId]) : 0];
            $righe[] = ['Accesso (email e password), note dell\'amministrazione, preferenze', 1];
            $fatt = trim(($acc['billing_name'] ?? '') . ' · ' . (($acc['vat'] ?? '') ?: ($acc['cf'] ?? '')), ' ·');
            $restano = ['Dati di fatturazione' . ($fatt !== '' ? ' (' . $fatt . ')' : ' (nessuno inserito)'),
                        'Pagamenti: ' . $conta("SELECT COUNT(*) FROM orders WHERE account_id = ? AND status IN ('paid', 'refunded')", [$accountId]) . ' ordini, '
                            . $conta('SELECT COUNT(*) FROM subscriptions WHERE account_id = ?', [$accountId]) . ' abbonamenti'
                            . (Migrator::tableExists('rimborsi') ? ', ' . $conta('SELECT COUNT(*) FROM rimborsi WHERE account_id = ?', [$accountId]) . ' rimborsi' : ''),
                        'Il codice cliente ' . Codici::cliente($accountId) . ', i codici dei pagamenti e il cliente su Stripe (le fatture)',
                        'Gli inviti dati e ricevuti (servono agli sconti degli altri), i documenti accettati e il Registro azioni'];
        }
        return ['modo' => $live > 0 ? 'conserva' : 'completa', 'email' => (string) ($p['email'] ?? ''), 'codice' => Codici::cliente($accountId), 'live' => $live,
                'aperti' => self::abbonamentiAperti($accountId), 'righe' => $righe, 'restano' => $restano, 'prova' => !empty($acc['is_test'])];
    }

    /**
     * Elimina (o chiude, con i pagamenti) l'account. La password l'ha già controllata la rotta.
     * @return array{modo:string,email:string,stripe:list<string>} l'email liberata e gli abbonamenti chiusi su Stripe
     */
    public static function elimina(int $accountId, string $conferma, string $motivo, bool $avvisa, int $adminId): array
    {
        if (!self::disponibile()) throw new \RuntimeException('Il database non è ancora aggiornato: apri il sito una volta per fare le migrazioni.');
        $acc = Db::one('SELECT a.*, u.email, u.name AS user_name, u.role FROM accounts a JOIN users u ON u.id = a.user_id WHERE a.id = ?', [$accountId]);
        if (!$acc) throw new \RuntimeException('Account inesistente.');
        if (self::eliminato($acc)) throw new \RuntimeException('Questo account è già stato eliminato.');
        if (($acc['role'] ?? '') === 'admin') throw new \RuntimeException('Un account di amministrazione non si elimina da qui.');
        $codice = Codici::cliente($accountId);
        if (preg_replace('/\s+/', ' ', trim($conferma)) !== 'ELIMINA ' . $codice) throw new \RuntimeException('Per eliminare scrivi esattamente «ELIMINA ' . $codice . '».');
        $motivo = trim(preg_replace('/\s+/', ' ', $motivo) ?? '');
        if (mb_strlen($motivo) < 5) throw new \RuntimeException('Scrivi il motivo (per esempio «richiesta del cliente per email del 28 ottobre»): resta nel Registro azioni.');
        $r = self::riepilogo($accountId);

        // 1 · Gli abbonamenti che possono ancora addebitare: su Stripe si chiudono subito. Un errore ferma tutto.
        $chiusi = [];
        foreach ($r['aperti'] as $s) {
            $sid = trim((string) ($s['provider_subscription_id'] ?? ''));
            if ($s['provider'] === 'stripe' && $sid !== '' && (Stripe::enabled() || $r['modo'] === 'conserva')) {
                // Senza Stripe configurato un abbonamento con pagamenti veri non si può chiudere da qui (senza pagamenti live non addebita).
                if (!Stripe::enabled()) throw new \RuntimeException('Stripe non è configurato e c\'è un abbonamento attivo (' . $sid . '): chiudilo prima da Stripe, poi elimina l\'account.');
                try { Stripe::call('DELETE', 'subscriptions/' . rawurlencode($sid), ['prorate' => 'false', 'invoice_now' => 'false']); }
                catch (\Throwable $e) {
                    // Già chiuso su Stripe: va bene lo stesso.
                    if (!str_contains($e->getMessage(), 'No such subscription') && !str_contains($e->getMessage(), 'canceled')) {
                        Log::exception($e, 'elimina account: abbonamento ' . $sid);
                        throw new \RuntimeException('Stripe non ha chiuso l\'abbonamento ' . $sid . ': «' . $e->getMessage() . '». Non è stato cancellato niente: riprova, o chiudilo da Stripe.');
                    }
                }
                $chiusi[] = $sid;
            }
            Db::update('subscriptions', ['status' => 'canceled', 'cancel_at_period_end' => 0, 'updated_at' => Support::now()], 'id = :id', ['id' => (int) $s['id']]);
        }
        Db::run("UPDATE orders SET status = 'canceled', updated_at = ? WHERE account_id = ? AND status = 'pending'", [Support::now(), $accountId]);
        Entitlements::forget($accountId);

        // 2 · L'email di conferma si prepara adesso (lingua, nome, indirizzo vero) e parte solo a cancellazione riuscita.
        $mail = $avvisa && !$r['prova'] ? self::conferma($acc, $r['modo']) : null;
        $manda = function () use ($mail) {
            if ($mail) try { $mail(); } catch (\Throwable $e) { Log::exception($e, 'elimina account: email di conferma'); }
        };

        // 3 · Senza pagamenti live: tutto, come per un account di prova.
        if ($r['modo'] === 'completa') {
            $email = AccountProva::elimina($accountId, $conferma, $adminId, 'account.eliminato', ['motivo' => $motivo, 'stripe_abbonamenti' => $chiusi]);
            $manda();
            return ['modo' => 'completa', 'email' => $email, 'stripe' => $chiusi];
        }

        // 4 · Con pagamenti live: via i contenuti e l'accesso, restano i dati fiscali.
        $userId = (int) $acc['user_id'];
        $cancellati = array_map(fn($x) => str_starts_with($x[0], 'Accesso') ? $x[0] : $x[0] . ': ' . $x[1], $r['righe']);
        self::cancellaFile($accountId);
        Db::tx(function () use ($accountId, $userId) {
            self::cancellaStrutture($accountId);
            foreach (self::PERSONALI as $tab) {
                if (Migrator::tableExists($tab) && Migrator::columnExists($tab, 'account_id')) Db::run("DELETE FROM $tab WHERE account_id = ?", [$accountId]);
            }
            foreach (['email_tokens', 'password_resets'] as $tab) if (Migrator::tableExists($tab)) Db::run("DELETE FROM $tab WHERE user_id = ?", [$userId]);
            $vuoti = ['name' => 'Account eliminato', 'admin_note' => null, 'admin_note_at' => null, 'invite_preview' => 0, 'referral_code' => null,
                      'translation_trial_until' => null, 'translation_trial_by_admin' => 0, 'staff_no_vetrina' => 0,
                      'intended_package_version_id' => null, 'intended_discount_code_id' => null, 'giudizio_adesione' => null, 'giudizio_adesione_at' => null,
                      'giudizio_chiuso_at' => null, 'translate_cap_override' => null, 'assistant_cap_override' => null, 'is_staff' => 0, 'staff_fino' => null,
                      'eliminato_at' => Support::now()];
            Db::update('accounts', array_filter($vuoti, fn($k) => Migrator::columnExists('accounts', $k), ARRAY_FILTER_USE_KEY), 'id = :id', ['id' => $accountId]);
            Db::update('users', ['email' => 'eliminato-' . $userId . '@' . self::DOMINIO, 'name' => 'Account eliminato', 'role' => self::RUOLO,
                                 'password_hash' => password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT)]
                                + (Migrator::columnExists('users', 'pending_email') ? ['pending_email' => null] : [])
                                + (Migrator::columnExists('users', 'must_change_password') ? ['must_change_password' => 0, 'password_temp_until' => null] : []),
                       'id = :id', ['id' => $userId]);
        });
        try { Vetrina::sincronizza(); } catch (\Throwable $e) { Log::exception($e, 'elimina account: vetrina'); }
        $manda();
        Auth::audit('account.eliminato', $userId, ['cliente' => $codice, 'email' => $r['email'], 'admin' => $adminId, 'modo' => 'conserva', 'motivo' => mb_substr($motivo, 0, 255),
                                                'stripe_abbonamenti' => $chiusi, 'cancellati' => $cancellati, 'conservati' => $r['restano']]);
        return ['modo' => 'conserva', 'email' => $r['email'], 'stripe' => $chiusi];
    }

    /** La scheda di un account eliminato: i dati che restano per legge, il motivo e chi l'ha eliminato. */
    public static function scheda(array $acc): array
    {
        $aid = (int) $acc['id'];
        $reg = Db::one("SELECT l.*, u.name AS admin_name, u.email AS admin_email FROM audit_log l LEFT JOIN users u ON u.id = l.actor_user_id
                        WHERE l.action = 'account.eliminato' AND l.target_user_id = ? ORDER BY l.id DESC LIMIT 1", [(int) $acc['user_id']]);
        $meta = $reg ? (json_decode((string) $reg['meta'], true) ?: []) : [];
        $ord = Db::all('SELECT o.*, pk.name AS package FROM orders o LEFT JOIN package_versions pv ON pv.id = o.package_version_id LEFT JOIN packages pk ON pk.id = pv.package_id
                        WHERE o.account_id = ? ORDER BY o.id DESC', [$aid]);
        $subs = Db::all('SELECT s.*, pk.name AS package FROM subscriptions s LEFT JOIN package_versions pv ON pv.id = s.package_version_id LEFT JOIN packages pk ON pk.id = pv.package_id
                         WHERE s.account_id = ? ORDER BY s.id DESC', [$aid]);
        $quando = (string) $acc['eliminato_at'];
        return ['acc' => $acc, 'codice' => Codici::cliente($aid), 'quando' => $quando, 'fino' => gmdate('Y-m-d', strtotime($quando . ' +10 years')),
                'motivo' => (string) ($meta['motivo'] ?? ''), 'admin' => $reg ? trim((string) ($reg['admin_name'] ?: $reg['admin_email'])) : '',
                'stripe' => (array) ($meta['stripe_abbonamenti'] ?? []), 'cancellati' => (array) ($meta['cancellati'] ?? []),
                'orders' => $ord, 'subs' => $subs,
                'rimborsi' => Migrator::tableExists('rimborsi') ? Db::all('SELECT * FROM rimborsi WHERE account_id = ? ORDER BY id DESC', [$aid]) : [],
                'pagamenti' => Codici::disponibili() ? Db::all("SELECT codice, order_id, invoice_id, created_at FROM codici_usati WHERE livello = 'pagamento' AND account_id = ? ORDER BY id DESC", [$aid]) : []];
    }

    /** I file di foto e PDF dell'account (sul disco o su S3): fuori dalla transazione. */
    public static function cancellaFile(int $accountId): void
    {
        foreach (Db::all('SELECT id FROM media WHERE account_id = ?', [$accountId]) as $m) {
            try { Media::delete((int) $m['id'], $accountId); } catch (\Throwable $e) { Log::exception($e, 'elimina account: foto ' . $m['id']); }
        }
    }

    /** Strutture e guide, con le tabelle che non hanno la chiave esterna (le altre vanno con la struttura). */
    public static function cancellaStrutture(int $accountId): void
    {
        $ids = array_map('intval', array_column(Db::all('SELECT id FROM properties WHERE account_id = ?', [$accountId]), 'id'));
        if ($ids) {
            $in = implode(',', $ids);
            foreach (self::DELLE_STRUTTURE as $tab) {
                if (Migrator::tableExists($tab) && Migrator::columnExists($tab, 'property_id')) Db::run("DELETE FROM $tab WHERE property_id IN ($in)");
            }
        }
        Db::run('DELETE FROM properties WHERE account_id = ?', [$accountId]);
    }

    /** L'email al cliente: l'account è chiuso, cosa si è cancellato e cosa resta per legge. Nella lingua del suo pannello. @return callable l'invio */
    private static function conferma(array $acc, string $modo): callable
    {
        $u = Db::one('SELECT * FROM users WHERE id = ?', [(int) $acc['user_id']]) ?? [];
        $lin = Pannello::linguaDi($u, (int) $acc['id']);
        $tl = fn(string $it, array $s = []) => Pannello::tIn($lin, $it, $s);
        $nome = trim(explode(' ', trim((string) ($acc['user_name'] ?? '')))[0] ?? '');
        $x = ['{nome}' => $nome, '{codice}' => Codici::cliente((int) $acc['id']), '{email}' => Support::contatto()];
        $par = [$tl($nome !== '' ? 'Ciao {nome}, abbiamo chiuso il tuo account MyHouse Welcome, come ci hai chiesto.' : 'Ciao, abbiamo chiuso il tuo account MyHouse Welcome, come ci hai chiesto.', $x),
                $tl('Le guide sono offline e il QR non apre più niente; testi, foto e PDF sono cancellati. L\'abbonamento è chiuso: non ci saranno altri addebiti.')];
        $par[] = $modo === 'conserva'
            ? $tl('Per legge conserviamo per 10 anni solo i dati di fatturazione e dei pagamenti (codice cliente {codice}); le fatture restano nel tuo cassetto fiscale. Con questa email puoi registrarti di nuovo quando vuoi.', $x)
            : $tl('Non abbiamo conservato altro. Con questa email puoi registrarti di nuovo quando vuoi.', $x);
        $par[] = $tl('Se non hai chiesto tu la chiusura, scrivici subito a {email}.', $x);
        $a = (string) $acc['email'];
        return fn() => Mailer::inLingua($lin, fn() => Mailer::manda($a, $tl('Il tuo account è chiuso'), $tl('Il tuo account è chiuso'), $par));
    }
}
