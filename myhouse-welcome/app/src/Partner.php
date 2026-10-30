<?php
namespace MHW;

/**
 * 9K · Il programma Partner per property manager e agenzie (pagina /property-manager). Per ora raccoglie i contatti:
 * il modulo «Diventa partner» salva la richiesta (tabella partner_richieste, migrazione 049), avvisa l'email
 * commerciale e conferma a chi ha scritto. Si leggono in Amministrazione → Vendite → Partner, con lo stato
 * (nuovo, contattato, attivo, chiuso); si conservano 12 mesi (Registri::pulisciDati). Codici partner e conteggio
 * delle commissioni arrivano in una fase successiva: qui solo le due percentuali, se decise (Impostazioni → Sito).
 */
final class Partner
{
    public const STATI = ['nuovo' => 'Nuovo', 'contattato' => 'Contattato', 'attivo' => 'Attivo', 'chiuso' => 'Chiuso'];

    public static function disponibili(): bool { return Migrator::tableExists('partner_richieste'); }

    /** La commissione e lo sconto per i clienti del partner, in percento, o null se non sono decisi. */
    public static function commissione(): ?int { return self::percento('commissione'); }
    public static function sconto(): ?int { return self::percento('sconto'); }

    private static function percento(string $k): ?int
    {
        $v = trim((string) ((Config::get('partner') ?? [])[$k] ?? ''));
        return ctype_digit($v) && (int) $v >= 1 && (int) $v <= 100 ? (int) $v : null;
    }

    /**
     * Salva la richiesta e manda le due email. Lancia un RuntimeException con il motivo da mostrare.
     * @return int l'id della richiesta
     */
    public static function salva(array $in): int
    {
        if (!self::disponibili()) throw new \RuntimeException('Il modulo non è ancora attivo: scrivici a ' . Support::commerciale() . '.');
        if (!RateLimit::hit('partner:' . RateLimit::ipKey(), 5, 3600)) throw new \RuntimeException('Troppe richieste da questa connessione: riprova tra un\'ora.');
        $riga = fn(string $k, int $max) => mb_substr(trim(preg_replace('/\s+/u', ' ', (string) ($in[$k] ?? ''))), 0, $max);
        $nome = $riga('nome', 120); $societa = $riga('societa', 160); $telefono = $riga('telefono', 40);
        $strutture = $riga('strutture', 20); $zone = $riga('zone', 255);
        $email = mb_strtolower(trim((string) ($in['email'] ?? '')));
        $messaggio = mb_substr(trim(str_replace("\r", '', (string) ($in['messaggio'] ?? ''))), 0, 2000);
        if ($nome === '') throw new \RuntimeException('Scrivi il tuo nome e cognome.');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new \RuntimeException('Controlla l\'indirizzo email.');
        if ($strutture !== '' && !preg_match('/^\d{1,4}$/', $strutture)) throw new \RuntimeException('Quante strutture gestisci? Scrivi un numero.');
        if (empty($in['consenso'])) throw new \RuntimeException('Per risponderti ci serve il tuo consenso al trattamento dei dati: spunta la casella.');
        $id = Db::insert('partner_richieste', [
            'nome' => $nome, 'societa' => $societa, 'email' => $email, 'telefono' => $telefono, 'strutture' => $strutture,
            'zone' => $zone, 'messaggio' => $messaggio, 'stato' => 'nuovo', 'created_at' => Support::now(), 'updated_at' => Support::now(),
        ]);
        // All'email commerciale tutti i dati, con il Reply-To di chi ha scritto; a lui la conferma, con il Reply-To commerciale.
        $righe = ['Nome: ' . $nome, 'Società: ' . ($societa !== '' ? $societa : '—'), 'Email: ' . $email, 'Telefono: ' . ($telefono !== '' ? $telefono : '—'),
                  'Strutture gestite: ' . ($strutture !== '' ? $strutture : '—'), 'Città o zone: ' . ($zone !== '' ? $zone : '—')];
        if ($messaggio !== '') $righe[] = 'Messaggio: ' . $messaggio;
        $commerciale = Support::commerciale();
        if ($commerciale !== '') {
            Mailer::conRispostaA($email, fn() => Mailer::manda($commerciale, 'Nuovo partner: ' . ($societa !== '' ? $societa : $nome), 'Una richiesta per il programma Partner',
                ['Qualcuno vuole diventare partner (property manager o agenzia).', implode("\n", $righe)],
                ['Apri in Amministrazione', Support::baseUrl() . '/admin/partner'], 'Rispondi direttamente a ' . $email . '.'));
        }
        Mailer::conRispostaA($commerciale, fn() => Mailer::manda($email, 'Abbiamo ricevuto la tua richiesta di partnership', 'Grazie, ' . explode(' ', $nome)[0] . '.',
            ['Abbiamo ricevuto la tua richiesta per il programma Partner di MyHouse Welcome: ti contattiamo entro un giorno lavorativo per conoscerci.',
             'Se nel frattempo vuoi aggiungere qualcosa, rispondi a questa email.'],
            null, null, ['MyHouse Welcome · ' . $commerciale, 'MyHouse Welcome · ' . $commerciale]));
        Log::info('Partner: richiesta salvata', ['id' => $id]);
        return $id;
    }

    /** Tutte le richieste, le più recenti prima. */
    public static function tutte(): array
    {
        return self::disponibili() ? Db::all('SELECT * FROM partner_richieste ORDER BY id DESC LIMIT 500') : [];
    }

    public static function cambiaStato(int $id, string $stato, ?string $nota = null): void
    {
        if (!isset(self::STATI[$stato])) throw new \RuntimeException('Stato non valido.');
        Db::update('partner_richieste', ['stato' => $stato, 'updated_at' => Support::now()] + ($nota !== null ? ['nota' => mb_substr(trim($nota), 0, 255)] : []),
                   'id = :id', ['id' => $id]);
    }
}
