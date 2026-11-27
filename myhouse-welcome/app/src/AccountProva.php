<?php
namespace MHW;

/**
 * 9R · «Elimina account di prova» (Amministrazione → scheda cliente). Solo per un account senza pagamenti live andati a buon
 * fine: un pagamento con le chiavi live (o di prima, quando non si sapeva) resta un pagamento, e quell'account non si elimina
 * (i dati delle fatture si conservano 10 anni: si ferma il rinnovo). Si conferma scrivendo «ELIMINA {codice cliente}»; si cancella tutto in una transazione (account,
 * strutture e guide con le foto, inviti dati e ricevuti, adesione al giudizio e scheda, ordini non pagati, email programmate),
 * poi il cliente Stripe se c'è. L'email torna libera per una nuova registrazione. Nel Registro azioni resta l'email cancellata,
 * che dopo 90 giorni si toglie anche da lì (pulisci()).
 */
final class AccountProva
{
    public const GIORNI_REGISTRO = 90;

    /** Le tabelle con account_id che si svuotano (quelle con la chiave esterna si cancellano da sole con l'account). */
    private const TABELLE = ['assistant_questions', 'codici_usati', 'discount_redemptions', 'email_log', 'email_optout', 'feedback', 'interessi_sito', 'novita_viste', 'offerte',
                             'pagamenti_esclusi', 'partner_commissioni', 'preventivi', 'proposte_cambio', 'referral_invites', 'rimborsi', 'testimonials',
                             'translation_usage', 'vetrina_schede'];

    /** I pagamenti live andati a buon fine (ordini pagati con le chiavi live o di prima, fatture live pagate). */
    public static function pagamentiLive(int $accountId): int
    {
        $live = Migrator::columnExists('orders', 'livemode') ? ' AND (livemode IS NULL OR livemode <> 0)' : '';
        $n = (int) Db::val("SELECT COUNT(*) FROM orders WHERE account_id = ? AND status IN ('paid', 'refunded') AND provider IN ('stripe', 'bonifico')" . $live, [$accountId], 0);
        $subs = array_filter(array_column(Db::all("SELECT provider_subscription_id FROM subscriptions WHERE account_id = ? AND provider_subscription_id IS NOT NULL AND provider_subscription_id <> ''", [$accountId]), 'provider_subscription_id'));
        foreach ($subs as $sid) {
            foreach (Db::all("SELECT payload FROM webhook_events WHERE provider = 'stripe' AND kind = 'invoice.paid' AND payload LIKE ?", ['%' . $sid . '%']) as $r) {
                $ev = json_decode((string) $r['payload'], true) ?: [];
                if ((string) ($ev['data']['object']['subscription'] ?? '') === $sid && ($ev['livemode'] ?? null) !== false && (int) ($ev['data']['object']['amount_paid'] ?? 0) > 0) $n++;
            }
        }
        return $n;
    }

    /** Cosa si cancellerebbe, per la pagina di conferma. @return array{puo:bool,perche:string,righe:list<array{0:string,1:int}>} */
    public static function riepilogo(int $accountId): array
    {
        $acc = Db::one('SELECT a.*, u.email FROM accounts a JOIN users u ON u.id = a.user_id WHERE a.id = ?', [$accountId]);
        if (!$acc) return ['puo' => false, 'perche' => 'Account inesistente.', 'righe' => []];
        $live = self::pagamentiLive($accountId);
        $conta = fn(string $sql, ?array $p = null) => (int) Db::val($sql, $p ?? [$accountId], 0);
        $t = fn(string $tab) => Migrator::tableExists($tab);
        $righe = [
            ['Strutture e guide (con sezioni, lingue, QR e statistiche)', $conta('SELECT COUNT(*) FROM properties WHERE account_id = ?')],
            ['Foto e file caricati', $conta('SELECT COUNT(*) FROM media WHERE account_id = ?')],
            ['Inviti dati e ricevuti', $t('referrals') ? $conta('SELECT COUNT(*) FROM referrals WHERE referrer_account_id = ? OR friend_account_id = ?', [$accountId, $accountId]) : 0],
            ['Adesione al giudizio, scheda e giudizio', (!empty($acc['giudizio_adesione']) ? 1 : 0) + ($t('testimonials') ? $conta('SELECT COUNT(*) FROM testimonials WHERE account_id = ?') : 0)],
            ['Ordini (non pagati o di prova) e abbonamenti', $conta('SELECT COUNT(*) FROM orders WHERE account_id = ?') + $conta('SELECT COUNT(*) FROM subscriptions WHERE account_id = ?')],
            ['Email mandate e programmate', $t('email_log') ? $conta('SELECT COUNT(*) FROM email_log WHERE account_id = ?') : 0],
        ];
        $perche = $live > 0 ? 'Ha ' . $live . ($live === 1 ? ' pagamento live andato' : ' pagamenti live andati') . ' a buon fine: un pagamento vero resta un pagamento e l\'account non si elimina (i dati delle fatture si conservano 10 anni). Per chiuderlo: ferma il rinnovo da «Abbonamento».' : '';
        return ['puo' => $live === 0, 'perche' => $perche, 'righe' => $righe, 'email' => (string) $acc['email'], 'stripe' => (string) ($acc['stripe_customer_id'] ?? '')];
    }

    /**
     * Elimina l'account di prova. $conferma deve essere «ELIMINA {codice cliente}». Lo usa anche «Elimina account»
     * (EliminaAccount) per un account senza pagamenti live, con la sua azione nel Registro e il motivo.
     * @return string l'email liberata
     */
    public static function elimina(int $accountId, string $conferma, int $adminId, string $azione = 'account.eliminato_prova', array $extra = []): string
    {
        $r = self::riepilogo($accountId);
        if (!$r['puo']) throw new \RuntimeException($r['perche'] ?: 'Non si può eliminare.');
        $codice = Codici::cliente($accountId);
        if (preg_replace('/\s+/', ' ', trim($conferma)) !== 'ELIMINA ' . $codice) throw new \RuntimeException('Per eliminare scrivi esattamente «ELIMINA ' . $codice . '».');
        $acc = Db::one('SELECT * FROM accounts WHERE id = ?', [$accountId]);
        $userId = (int) $acc['user_id'];
        // Prima i file delle foto (fuori dalla transazione: sono sul disco o su S3), poi le righe.
        foreach (Db::all('SELECT id FROM media WHERE account_id = ?', [$accountId]) as $m) {
            try { Media::delete((int) $m['id'], $accountId); } catch (\Throwable $e) { Log::exception($e, 'elimina account di prova: foto ' . $m['id']); }
        }
        Db::tx(function () use ($accountId, $userId) {
            foreach (self::TABELLE as $tab) {
                if (Migrator::tableExists($tab) && Migrator::columnExists($tab, 'account_id')) Db::run("DELETE FROM $tab WHERE account_id = ?", [$accountId]);
            }
            if (Migrator::tableExists('referrals')) Db::run('DELETE FROM referrals WHERE referrer_account_id = ? OR friend_account_id = ?', [$accountId, $accountId]);
            foreach (['legal_acceptances'] as $tab) if (Migrator::tableExists($tab)) Db::run("DELETE FROM $tab WHERE user_id = ?", [$userId]);
            EliminaAccount::cancellaStrutture($accountId);   // con le tabelle delle strutture senza chiave esterna
            Db::run('DELETE FROM users WHERE id = ?', [$userId]);   // account, abbonamenti, ordini, foto e permessi vanno con l'utente (chiavi esterne)
        });
        // Il cliente Stripe, se c'è: senza pagamenti live non serve più (un errore non ferma l'eliminazione, si scrive nel log).
        $stripeEsito = '';
        if ($r['stripe'] !== '' && Stripe::enabled()) {
            try { Stripe::call('DELETE', 'customers/' . rawurlencode($r['stripe'])); $stripeEsito = 'cancellato'; }
            catch (\Throwable $e) { Log::exception($e, 'elimina account di prova: cliente Stripe'); $stripeEsito = 'non cancellato: ' . $e->getMessage(); }
        }
        Auth::audit($azione, null, ['cliente' => $codice, 'email' => $r['email'], 'admin' => $adminId] + $extra + ['stripe' => $stripeEsito,
                                    'cancellati' => array_map(fn($x) => $x[0] . ': ' . $x[1], $r['righe'])]);
        return $r['email'];
    }

    /** Dopo 90 giorni l'email dell'account eliminato (di prova o no) si toglie anche dal Registro azioni. Dal giro dei richiami. */
    public static function pulisci(): int
    {
        $n = 0;
        $prima = gmdate('Y-m-d\TH:i:s\Z', time() - self::GIORNI_REGISTRO * 86400);
        foreach (Db::all("SELECT id, meta FROM audit_log WHERE action IN ('account.eliminato_prova', 'account.eliminato') AND created_at < ? AND meta LIKE ?", [$prima, '%"email"%']) as $r) {
            $m = json_decode((string) $r['meta'], true) ?: [];
            if (($m['email'] ?? '') === '—') continue;
            $m['email'] = '—';
            Db::update('audit_log', ['meta' => json_encode($m, JSON_UNESCAPED_UNICODE)], 'id = :id', ['id' => (int) $r['id']]);
            $n++;
        }
        return $n;
    }
}
