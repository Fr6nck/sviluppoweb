<?php
namespace MHW;

/**
 * 9D · I codici omaggio: da 1 a 12 mesi gratis, con la carta inserita prima della pubblicazione.
 *
 * Regole decise dal titolare:
 *   - il codice lo genera l'amministrazione (OMAGGIO-XXXXX), vale per una persona sola, solo per i nuovi clienti
 *     (account che non ha mai pagato), scade dopo 14 giorni di serie (modificabile codice per codice);
 *   - si prenota all'account quando viene applicato (accounts.codice_omaggio_id) e si brucia al Checkout riuscito
 *     (webhook, usato_at). Resta prenotato fino alla scadenza; un codice annullato non vale più nemmeno se prenotato;
 *   - oggi il cliente paga 0 €: l'abbonamento nasce «trialing» su Stripe e il primo addebito parte da solo alla fine
 *     dei mesi gratis (subscription_data[trial_end], carta obbligatoria);
 *   - niente sconto «Invita un amico» con l'omaggio: se l'account era arrivato da un invito, l'invito si chiude come
 *     «omaggio» e non conta per chi ha invitato; lo sconto del giudizio (9B) invece resta e va sul primo addebito.
 */
final class Omaggi
{
    public const PREFISSO = 'OMAGGIO-';
    public const GIORNI = 14;   // la scadenza di serie di un codice nuovo
    private const ALFABETO = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';   // niente 0 O 1 I

    public static function disponibili(): bool { return Migrator::tableExists('codici_omaggio') && Migrator::columnExists('accounts', 'codice_omaggio_id'); }

    public static function oggi(): string { return Eventi::oggi(); }

    /** «OMAGGIO-» più 5 caratteri leggibili, mai già usato. */
    public static function genera(): string
    {
        do {
            $c = self::PREFISSO;
            for ($i = 0; $i < 5; $i++) $c .= self::ALFABETO[random_int(0, strlen(self::ALFABETO) - 1)];
        } while (self::trova($c));
        return $c;
    }

    /** Il codice come si scrive: maiuscolo, con il prefisso (si accetta anche senza, come nel link /omaggio/XXXXX). */
    public static function normalizza(string $codice): string
    {
        $c = strtoupper(preg_replace('/\s+/', '', $codice) ?? '');
        if ($c === '') return '';
        return str_starts_with($c, self::PREFISSO) ? $c : self::PREFISSO . $c;
    }

    /** È scritto come un codice omaggio (prefisso OMAGGIO-)? Serve a distinguerlo da un codice di invito o sconto. */
    public static function sembra(string $codice): bool
    {
        return str_starts_with(strtoupper(trim($codice)), self::PREFISSO);
    }

    public static function trova(string $codice): ?array
    {
        if (!self::disponibili()) return null;
        $c = self::normalizza($codice);
        if (!preg_match('/^OMAGGIO-[A-Z0-9]{5}$/', $c)) return null;
        return Db::one('SELECT * FROM codici_omaggio WHERE codice = ?', [$c]);
    }

    public static function riga(int $id): ?array
    {
        return self::disponibili() ? Db::one('SELECT * FROM codici_omaggio WHERE id = ?', [$id]) : null;
    }

    /** La parte dopo il prefisso, per il link breve /omaggio/XXXXX. */
    public static function breve(array $c): string { return substr((string) $c['codice'], strlen(self::PREFISSO)); }

    public static function link(array $c): string { return Support::baseUrl() . '/omaggio/' . self::breve($c); }

    /** valido | usato | scaduto | annullato */
    public static function stato(array $c): string
    {
        if (!empty($c['annullato_at'])) return 'annullato';
        if (!empty($c['usato_at'])) return 'usato';
        if ((string) $c['scade_il'] < self::oggi()) return 'scaduto';
        return 'valido';
    }

    /**
     * Il codice vale per questo account? Restituisce la riga oppure lancia l'errore da mostrare così com'è.
     * Senza account (link aperto da un visitatore) si controllano solo il codice e la sua scadenza.
     */
    public static function valida(string $codice, ?array $acc): array
    {
        $c = self::trova($codice);
        if (!$c || !empty($c['annullato_at'])) throw new \RuntimeException('Questo codice omaggio non esiste o non è più attivo. Controlla con chi te l\'ha dato.');
        $aid = $acc ? (int) $acc['id'] : 0;
        // Chi ha già pagato non è un nuovo cliente: è la prima cosa da dire, qualunque sia lo stato del codice.
        if ($acc && Db::one("SELECT id FROM orders WHERE account_id = ? AND status = 'paid'", [$aid])) {
            throw new \RuntimeException('Il codice omaggio vale solo per i nuovi clienti: il tuo account ha già un abbonamento pagato.');
        }
        if (!empty($c['usato_at']) && (int) $c['usato_da_account_id'] !== $aid) throw new \RuntimeException('Questo codice omaggio è già stato usato.');
        if (!empty($c['usato_at'])) throw new \RuntimeException('Hai già usato questo codice omaggio.');
        if ((string) $c['scade_il'] < self::oggi()) throw new \RuntimeException('Questo codice omaggio è scaduto il ' . Support::date($c['scade_il']) . '.');
        // Prenotato a un altro account: resta suo fino alla scadenza.
        $altro = Db::val('SELECT id FROM accounts WHERE codice_omaggio_id = ? AND id <> ?', [(int) $c['id'], $aid]);
        if ($altro) throw new \RuntimeException('Questo codice omaggio è già stato usato.');
        return $c;
    }

    /**
     * Prenota il codice all'account. Omaggio e invito si escludono: l'invito in corso si chiude come «omaggio» e lo
     * sconto dell'amico decade (non conta per chi ha invitato). Un altro codice omaggio già prenotato viene sostituito.
     */
    public static function applica(array $acc, array $c): void
    {
        $aid = (int) $acc['id'];
        // 9N · La prenotazione in una transazione, con il controllo rifatto dentro e l'indice unico (migrazione 054):
        // due registrazioni nello stesso momento non possono prendere lo stesso codice.
        try {
            Db::tx(function () use ($aid, $c) {
                if (Db::val('SELECT id FROM accounts WHERE codice_omaggio_id = ? AND id <> ?', [(int) $c['id'], $aid])) throw new \RuntimeException('Questo codice omaggio è già stato usato.');
                Db::update('accounts', ['codice_omaggio_id' => (int) $c['id']], 'id = :aid', ['aid' => $aid]);
            });
        } catch (\PDOException) {
            throw new \RuntimeException('Questo codice omaggio è già stato usato.');
        }
        if (Inviti::disponibili()) {
            $r = Db::one("SELECT * FROM referrals WHERE friend_account_id = ? AND status = 'registrato'", [$aid]);
            if ($r) Db::update('referrals', ['status' => 'omaggio'], 'id = :id', ['id' => (int) $r['id']]);
            if (!empty($acc['intended_discount_code_id']) && ($riga = Sconti::riga((int) $acc['intended_discount_code_id'])) && !empty($riga['sistema'])) {
                Db::update('accounts', ['intended_discount_code_id' => null], 'id = :aid', ['aid' => $aid]);
            }
        }
        Auth::audit('omaggio.applicato', (int) ($acc['user_id'] ?? 0) ?: null, ['account_id' => $aid, 'codice' => (string) $c['codice'], 'mesi' => (int) $c['mesi']]);
    }

    /** Il codice prenotato all'account, se vale ancora (non annullato, non scaduto, non bruciato da altri). */
    public static function prenotato(?array $acc): ?array
    {
        if (!$acc || !self::disponibili() || empty($acc['codice_omaggio_id'])) return null;
        $c = self::riga((int) $acc['codice_omaggio_id']);
        if (!$c || !empty($c['annullato_at'])) return null;
        if (!empty($c['usato_at'])) return (int) $c['usato_da_account_id'] === (int) $acc['id'] ? $c : null;
        return (string) $c['scade_il'] < self::oggi() ? null : $c;
    }

    /** L'omaggio ancora da usare (prenotato e non bruciato): quello che il Checkout applicherà. */
    public static function daUsare(?array $acc): ?array
    {
        $c = self::prenotato($acc);
        return $c && empty($c['usato_at']) ? $c : null;
    }

    /** La fine dei mesi gratis: mezzanotte UTC di oggi più N mesi (DateTimeImmutable, non trial_period_days). */
    public static function fine(int $mesi, ?int $da = null): int
    {
        $d = (new \DateTimeImmutable('@' . ($da ?? time())))->setTimezone(new \DateTimeZone('UTC'))->setTime(0, 0, 0);
        return $d->modify('+' . max(1, min(12, $mesi)) . ' months')->getTimestamp();
    }

    public static function fineIso(int $mesi, ?int $da = null): string { return gmdate('Y-m-d\TH:i:s\Z', self::fine($mesi, $da)); }

    /** Dal webhook (Billing::activate): il codice si brucia. Solo database. */
    public static function brucia(int $codiceId, int $accountId): void
    {
        if (!self::disponibili()) return;
        Db::run('UPDATE codici_omaggio SET usato_da_account_id = ?, usato_at = ? WHERE id = ? AND usato_at IS NULL', [$accountId, Support::now(), $codiceId]);
    }

    /** Un codice nuovo, dal modulo dell'amministrazione. @throws \RuntimeException */
    public static function crea(array $in, int $adminId): int
    {
        $mesi = (int) ($in['mesi'] ?? 0);
        if ($mesi < 1 || $mesi > 12) throw new \RuntimeException('I mesi gratis vanno da 1 a 12.');
        $scade = trim((string) ($in['scade_il'] ?? ''));
        if ($scade === '') $scade = gmdate('Y-m-d', strtotime(self::oggi() . ' +' . self::GIORNI . ' days'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $scade) || !checkdate((int) substr($scade, 5, 2), (int) substr($scade, 8, 2), (int) substr($scade, 0, 4))) {
            throw new \RuntimeException('Scrivi la data entro cui usare il codice, come 2026-10-30.');
        }
        if ($scade < self::oggi()) throw new \RuntimeException('La data di scadenza è già passata.');
        return Db::insert('codici_omaggio', [
            'codice' => self::genera(), 'mesi' => $mesi, 'scade_il' => $scade, 'nota' => mb_substr(trim((string) ($in['nota'] ?? '')), 0, 255),
            'creato_da' => $adminId, 'created_at' => Support::now(),
        ]);
    }

    /** Annulla: non si applica più, nemmeno se prenotato. Chi l'ha già usato conserva i suoi mesi. */
    public static function annulla(int $id): void
    {
        $c = self::riga($id);
        if (!$c) throw new NotFound('Codice non trovato.');
        if (!empty($c['usato_at'])) throw new \RuntimeException('Questo codice è già stato usato: i mesi gratis restano al cliente.');
        Db::update('codici_omaggio', ['annullato_at' => Support::now()], 'id = :id', ['id' => $id]);
        Db::run('UPDATE accounts SET codice_omaggio_id = NULL WHERE codice_omaggio_id = ?', [$id]);
    }

    /** Il messaggio pronto da mandare (Testi e foto → omaggio.messaggio), con mesi, link, codice e scadenza. */
    public static function messaggio(array $c): string
    {
        $t = TestiSito::testo('omaggio.messaggio', ['mesi' => (string) (int) $c['mesi'], 'link' => preg_replace('#^https?://#', '', self::link($c)),
                                                     'codice' => (string) $c['codice'], 'scadenza' => Support::date((string) $c['scade_il']), 'email' => Support::commerciale()]);
        // 9H · La firma con l'email commerciale, se il testo di Testi e foto non la scrive già ({email}).
        $c = Support::commerciale();
        return $c !== '' && !str_contains($t, $c) ? rtrim($t) . "\n\nMyHouse Welcome · " . $c : $t;
    }

    /** Per l'amministrazione: tutti i codici, con chi li ha usati. */
    public static function tutti(): array
    {
        if (!self::disponibili()) return [];
        $righe = Db::all('SELECT c.*, u.name AS usato_da_nome, u.email AS usato_da_email, s.omaggio_fino
                          FROM codici_omaggio c LEFT JOIN accounts a ON a.id = c.usato_da_account_id LEFT JOIN users u ON u.id = a.user_id
                          LEFT JOIN subscriptions s ON s.id = (SELECT MAX(s2.id) FROM subscriptions s2 WHERE s2.account_id = c.usato_da_account_id AND s2.omaggio_fino IS NOT NULL)
                          ORDER BY c.id DESC');
        foreach ($righe as &$r) $r['stato'] = self::stato($r);
        unset($r);
        return $righe;
    }

    /** I numeri per il Quadro e il Marketing: clienti in omaggio adesso, primo addebito entro N giorni, codici diventati paganti. */
    public static function numeri(int $giorni = 30): array
    {
        if (!self::disponibili()) return ['in_omaggio' => 0, 'addebito_vicino' => 0];
        $ora = Support::now();
        return [
            'in_omaggio' => (int) Db::val("SELECT COUNT(DISTINCT account_id) FROM subscriptions WHERE status = 'trialing' AND omaggio_fino IS NOT NULL AND omaggio_fino > ?" . Gestione::senzaProva('account_id'), [$ora], 0),
            'addebito_vicino' => (int) Db::val("SELECT COUNT(DISTINCT account_id) FROM subscriptions WHERE status = 'trialing' AND cancel_at_period_end = 0 AND omaggio_fino IS NOT NULL AND omaggio_fino > ? AND omaggio_fino <= ?" . Gestione::senzaProva('account_id'),
                                              [$ora, gmdate('Y-m-d\TH:i:s\Z', time() + $giorni * 86400)], 0),
        ];
    }
}
