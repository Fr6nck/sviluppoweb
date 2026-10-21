<?php
namespace MHW;

/**
 * L'email di benvenuto: la prima guida dell'account è online (versione 1 pubblicata). Una volta sola per account
 * (email_log, tipo «benvenuto»), di servizio: niente «non mandarmene più». In italiano, o in inglese per chi ha
 * il pannello in inglese (Pannello::linguaDi).
 *
 * Dentro: il link della guida, le tre cose da fare subito (stampare il QR, mandare il link prima dell'arrivo,
 * pubblicare le modifiche), una riga sul programma «Giudizio con sconto» («Testi e foto»: giudizio.email.aderito
 * per chi ha aderito, giudizio.email.invito per chi può ancora farlo) e il bottone per «QR & Link».
 *
 * Parte subito dopo la pubblicazione (rotta di pubblicazione, webhook di Stripe, pagina che aspetta il pagamento);
 * se lì non parte (posta giù), la riprende il giro dei richiami (Richiami::esegui → recenti()) entro FINESTRA giorni.
 * Mai alle guide di esempio, alla vetrina, ai clienti di prova (Richiami::manda), a chi aveva già una guida online.
 */
final class Benvenuto
{
    /** Giorni dalla prima pubblicazione entro cui l'email parte ancora: chi aggiorna l'applicazione non scrive ai clienti di mesi fa. */
    public const FINESTRA = 3;

    /** La prima guida dell'account (versione 1 più vecchia, guide vere), con la data della prima pubblicazione. */
    private static function primaGuida(int $accountId): ?array
    {
        return Db::one('SELECT p.*, v.published_at AS prima_pubblicazione FROM guide_versions v JOIN properties p ON p.id = v.property_id
                        WHERE v.version = 1 AND p.account_id = ? AND p.is_demo = 0 ORDER BY v.published_at, v.id', [$accountId]);
    }

    /** Manda l'email se è il momento (prima guida online da meno di FINESTRA giorni) e non è già partita. Non solleva mai. */
    public static function forse(int $accountId, ?int $t = null): bool
    {
        try {
            if (!Richiami::disponibili() || $accountId <= 0) return false;
            if (Db::one("SELECT id FROM email_log WHERE account_id = ? AND kind = 'benvenuto'", [$accountId])) return false;
            $p = self::primaGuida($accountId);
            if (!$p || $p['status'] !== 'published' || !empty($p['archived_at'])) return false;
            $quando = (int) strtotime((string) $p['prima_pubblicazione']);
            if (($t ?? time()) - $quando > self::FINESTRA * 86400) return false;
            $u = Db::one("SELECT u.*, a.id AS acc_id FROM accounts a JOIN users u ON u.id = a.user_id WHERE a.id = ? AND u.role = 'host'", [$accountId]);
            if (!$u || str_ends_with(mb_strtolower((string) $u['email']), '@' . Demo::DOMINIO)) return false;
            // La vetrina (e chi ha guide di esempio) non riceve il benvenuto.
            if (Db::val('SELECT id FROM properties WHERE account_id = ? AND is_demo > 0', [$accountId])) return false;
            $lingua = Pannello::linguaDi($u, $accountId);
            return Richiami::manda($accountId, (string) $u['email'], (string) $u['name'], 'benvenuto', 'primo', self::testo($accountId, $p, $lingua), $lingua);
        } catch (\Throwable $e) {
            Log::exception($e, 'email di benvenuto');
            return false;
        }
    }

    /** Le prime guide degli ultimi FINESTRA giorni ancora senza benvenuto (il giro dei richiami). @return int quante partite */
    public static function recenti(?int $t = null): int
    {
        if (!Richiami::disponibili()) return 0;
        $da = gmdate('Y-m-d\TH:i:s\Z', ($t ?? time()) - self::FINESTRA * 86400);
        $n = 0;
        foreach (Db::all("SELECT DISTINCT p.account_id FROM guide_versions v JOIN properties p ON p.id = v.property_id
                          WHERE v.version = 1 AND p.is_demo = 0 AND v.published_at > ?
                            AND NOT EXISTS (SELECT 1 FROM email_log e WHERE e.account_id = p.account_id AND e.kind = 'benvenuto')", [$da]) as $r) {
            if (self::forse((int) $r['account_id'], $t)) $n++;
        }
        return $n;
    }

    /** [oggetto, testo, bottone, indirizzo, nota, scelte] per Richiami::manda: il bottone porta a «QR & Link», sotto «Apri la guida». */
    public static function testo(int $accountId, array $p, string $lingua): array
    {
        $en = $lingua === 'en';
        $nome = (string) $p['name'];
        $guida = Support::baseUrl() . '/g/' . $p['slug'];
        $corpo = $en
            ? "welcome to MyHouse Welcome. The guide for $nome is online now: guests open it on their phone, with no app and no sign-up."
            : "ti diamo il benvenuto in MyHouse Welcome. La guida di $nome è online da adesso: gli ospiti la aprono dal telefono, senza app e senza registrarsi.";
        $corpo .= "\n\n" . self::coseDaFare($lingua);
        $riga = self::rigaGiudizio($accountId, $lingua);
        if ($riga !== '') $corpo .= "\n\n" . $riga;
        return [$en ? "The guide for $nome is online" : "La guida di $nome è online", $corpo,
                $en ? 'Open QR & Link' : 'Apri QR & Link', Support::baseUrl() . '/pannello/' . (int) $p['id'] . '/qr',
                $en ? 'Any questions? Just reply to this email: our support team will answer.' : 'Una domanda? Rispondi a questa email: ti risponde l\'assistenza.',
                [[$en ? 'Open the guide' : 'Apri la guida', $guida]]];
    }

    /**
     * La riga sul programma «Giudizio con sconto», dalla sua chiave di «Testi e foto» (una sola, IT e EN), in testo semplice.
     * Chi ha aderito (e non si è ritirato): giudizio.email.aderito. Chi può ancora aderire: giudizio.email.invito. Gli altri: niente.
     */
    public static function rigaGiudizio(int $accountId, string $lingua): string
    {
        if (!Giudizi::disponibili()) return '';
        $acc = Db::one('SELECT * FROM accounts WHERE id = ?', [$accountId]);
        if (!$acc) return '';
        $sch = Vetrina::scheda($accountId);
        if (!empty($acc['giudizio_adesione']) && $sch && empty($sch['ritirato_at'])) $k = 'giudizio.email.aderito';
        elseif (Giudizi::puoAderire($acc) && !(Omaggi::disponibili() && Omaggi::daUsare($acc))) $k = 'giudizio.email.invito';
        else return '';
        $t = TestiSito::get($k, $lingua === 'en' ? 'en' : 'it');
        $t = preg_replace(['/\*\*(.+?)\*\*/s', '/\[([^\]]+)\]\(([^)\s]+)\)/'], ['$1', '$1 ($2)'], $t);
        return trim(strtr((string) $t, ['{giudizio}' => Giudizi::percentuale() . '%', '{link}' => Support::baseUrl() . '/giudizio']));
    }
}
