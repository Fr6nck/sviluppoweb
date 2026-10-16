<?php
namespace MHW;

/**
 * 9A · Le richieste di preventivo per più di 10 strutture (tabella preventivi, migrazione 043).
 * Dal riquadro «N strutture? Ti facciamo una proposta su misura» della home, di /piano e della
 * registrazione: si salva, si avvisa l'assistenza e si conferma a chi ha scritto. Si leggono in
 * Amministrazione → Vendite → Preventivi; si conservano 12 mesi (Registri::pulisciDati).
 */
final class Preventivi
{
    public const STATI = ['nuovo' => 'Nuovo', 'contattato' => 'Contattato', 'chiuso' => 'Chiuso'];

    public static function disponibili(): bool { return Migrator::tableExists('preventivi'); }

    /** Da quante strutture si chiede il preventivo: una in più del massimo del Portfolio in vendita (11). */
    public static function daQuante(): int
    {
        $pf = null;
        foreach (Plans::public() as $o) if (Plans::perProperty($o)) { $pf = $o; break; }
        return $pf ? (int) $pf['max_quantity'] + 1 : 11;
    }

    /**
     * Salva la richiesta e manda le due email. Lancia un RuntimeException con il motivo da mostrare.
     * @return int l'id della richiesta
     */
    public static function salva(array $in, ?array $acc, string $lingua = 'it'): int
    {
        if (!self::disponibili()) throw new \RuntimeException('Le richieste di preventivo non sono ancora attive: scrivici a ' . Support::contatto() . '.');
        if (!RateLimit::hit('preventivo:' . RateLimit::ipKey(), 5, 3600)) throw new \RuntimeException('Troppe richieste da questa connessione: riprova tra un\'ora.');
        $nome = mb_substr(trim(preg_replace('/\s+/u', ' ', (string) ($in['nome'] ?? ''))), 0, 120);
        $email = mb_strtolower(trim((string) ($in['email'] ?? '')));
        $telefono = mb_substr(trim((string) ($in['telefono'] ?? '')), 0, 40);
        $strutture = (int) ($in['strutture'] ?? 0);
        $messaggio = mb_substr(trim(str_replace("\r", '', (string) ($in['messaggio'] ?? ''))), 0, 2000);
        $en = $lingua === 'en';
        if ($nome === '') throw new \RuntimeException($en ? 'Tell us your name.' : 'Scrivi il tuo nome e cognome.');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new \RuntimeException($en ? 'Check the email address.' : 'Controlla l\'indirizzo email.');
        if ($strutture < self::daQuante() || $strutture > 999) {
            throw new \RuntimeException($en ? 'Quotes are for ' . self::daQuante() . ' properties or more.' : 'Il preventivo è per ' . self::daQuante() . ' strutture o più.');
        }
        $id = Db::insert('preventivi', [
            'account_id' => $acc ? (int) $acc['id'] : null, 'nome' => $nome, 'email' => $email, 'telefono' => $telefono,
            'strutture' => $strutture, 'messaggio' => $messaggio, 'lingua' => $en ? 'en' : 'it', 'stato' => 'nuovo', 'created_at' => Support::now(),
        ]);
        // All'assistenza: tutti i dati. A chi ha scritto: la conferma, nella sua lingua.
        $righe = ['Nome: ' . $nome, 'Email: ' . $email, 'Telefono: ' . ($telefono !== '' ? $telefono : '—'), 'Strutture: ' . $strutture,
                  'Lingua: ' . ($en ? 'inglese' : 'italiano'), 'Account: ' . ($acc ? '#' . (int) $acc['id'] : 'nessuno (visitatore)')];
        if ($messaggio !== '') $righe[] = 'Messaggio: ' . $messaggio;
        if (Support::contatto() !== '') {
            Mailer::manda(Support::contatto(), 'Richiesta di preventivo: ' . $strutture . ' strutture', 'Nuova richiesta di preventivo',
                          ['Qualcuno ha chiesto una proposta per ' . $strutture . ' strutture.', implode("\n", $righe)],
                          ['Apri in Amministrazione', Support::baseUrl() . '/admin/preventivi'], 'Rispondi direttamente a ' . $email . '.');
        }
        if ($en) {
            Mailer::manda($email, 'We received your request for ' . $strutture . ' properties', 'Thanks, ' . explode(' ', $nome)[0] . '.',
                          ['We received your request for a quote for ' . $strutture . ' properties: we will get back to you within one working day with a tailored proposal.',
                           'If you want to add anything in the meantime, just reply to this email.'],
                          null, null, ['MyHouse Welcome · ' . Support::contatto(), 'MyHouse Welcome · ' . Support::contatto()]);
        } else {
            Mailer::manda($email, 'Abbiamo ricevuto la richiesta per ' . $strutture . ' strutture', 'Grazie, ' . explode(' ', $nome)[0] . '.',
                          ['Abbiamo ricevuto la richiesta di preventivo per ' . $strutture . ' strutture: ti rispondiamo entro un giorno lavorativo con una proposta su misura.',
                           'Se nel frattempo vuoi aggiungere qualcosa, rispondi a questa email.'],
                          null, null, ['MyHouse Welcome · ' . Support::contatto(), 'MyHouse Welcome · ' . Support::contatto()]);
        }
        Log::info('Preventivo: richiesta salvata', ['id' => $id, 'strutture' => $strutture]);
        return $id;
    }

    /** Tutte le richieste, le più recenti prima. */
    public static function tutte(): array
    {
        return self::disponibili() ? Db::all('SELECT * FROM preventivi ORDER BY id DESC LIMIT 500') : [];
    }

    public static function cambiaStato(int $id, string $stato): void
    {
        if (!isset(self::STATI[$stato])) throw new \RuntimeException('Stato non valido.');
        Db::update('preventivi', ['stato' => $stato], 'id = :id', ['id' => $id]);
    }
}
