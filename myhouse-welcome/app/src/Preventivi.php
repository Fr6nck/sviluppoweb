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
    /** 9N · 10 bis: «Offerta inviata», «Accettato» (pagata) e «Da ricontattare» (offerta scaduta) li mette anche il sito. */
    public const STATI = ['nuovo' => 'Nuovo', 'contattato' => 'Contattato', 'offerta' => 'Offerta inviata', 'accettato' => 'Accettato', 'ricontattare' => 'Da ricontattare', 'chiuso' => 'Chiuso'];

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
        if (!self::disponibili()) throw new \RuntimeException('Le richieste di preventivo non sono ancora attive: scrivici a ' . Support::commerciale() . '.');
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
        // 9H · All'email commerciale: tutti i dati. A chi ha scritto: la conferma, nella sua lingua, dal mittente di sempre
        // (assistenza@) con il Reply-To commerciale: se risponde, la risposta arriva a chi segue il preventivo.
        $righe = ['Nome: ' . $nome, 'Email: ' . $email, 'Telefono: ' . ($telefono !== '' ? $telefono : '—'), 'Strutture: ' . $strutture,
                  'Lingua: ' . ($en ? 'inglese' : 'italiano'), 'Account: ' . ($acc ? '#' . (int) $acc['id'] : 'nessuno (visitatore)')];
        if ($messaggio !== '') $righe[] = 'Messaggio: ' . $messaggio;
        $commerciale = Support::commerciale();
        if ($commerciale !== '') {
            // Il Reply-To è chi ha chiesto il preventivo: «Rispondi» gli scrive direttamente.
            Mailer::conRispostaA($email, fn() => Mailer::manda($commerciale, 'Richiesta di preventivo: ' . $strutture . ' strutture', 'Nuova richiesta di preventivo',
                          ['Qualcuno ha chiesto una proposta per ' . $strutture . ' strutture.', implode("\n", $righe)],
                          ['Apri in Amministrazione', Support::baseUrl() . '/admin/preventivi'], 'Rispondi direttamente a ' . $email . '.'));
        }
        Mailer::conRispostaA($commerciale, function () use ($en, $email, $nome, $strutture, $commerciale) {
            if ($en) {
                Mailer::manda($email, 'We received your request for ' . $strutture . ' properties', 'Thanks, ' . explode(' ', $nome)[0] . '.',
                              ['We received your request for a quote for ' . $strutture . ' properties: we will get back to you within one working day with a tailored proposal.',
                               'If you want to add anything in the meantime, just reply to this email.'],
                              null, null, ['MyHouse Welcome · ' . $commerciale, 'MyHouse Welcome · ' . $commerciale]);
            } else {
                Mailer::manda($email, 'Abbiamo ricevuto la richiesta per ' . $strutture . ' strutture', 'Grazie, ' . explode(' ', $nome)[0] . '.',
                              ['Abbiamo ricevuto la richiesta di preventivo per ' . $strutture . ' strutture: ti rispondiamo entro un giorno lavorativo con una proposta su misura.',
                               'Se nel frattempo vuoi aggiungere qualcosa, rispondi a questa email.'],
                              null, null, ['MyHouse Welcome · ' . $commerciale, 'MyHouse Welcome · ' . $commerciale]);
            }
        });
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

    /** 9N · La proposta: importo (IVA esclusa, anche vuoto), nota interna e data per ricontattare (migrazione 057). */
    public static function conProposta(): bool { return self::disponibili() && Migrator::columnExists('preventivi', 'importo_cents'); }

    /**
     * 9N · Salva stato, importo proposto, nota e «ricontattare il». @return array<string,mixed> solo i campi cambiati (per il registro)
     */
    public static function aggiorna(int $id, array $in): array
    {
        $riga = Db::one('SELECT * FROM preventivi WHERE id = ?', [$id]);
        if (!$riga) throw new \RuntimeException('Richiesta non trovata.');
        $stato = (string) ($in['stato'] ?? $riga['stato']);
        if (!isset(self::STATI[$stato])) throw new \RuntimeException('Stato non valido.');
        $nuovi = ['stato' => $stato];
        if (self::conProposta()) {
            $grezzo = str_replace(['€', ' ', '.'], '', trim((string) ($in['importo'] ?? '')));
            $grezzo = str_replace(',', '.', $grezzo);
            if ($grezzo !== '' && (!is_numeric($grezzo) || (float) $grezzo < 0)) throw new \RuntimeException('Scrivi l\'importo proposto in euro, per esempio 1.250,00.');
            $data = trim((string) ($in['ricontatta_il'] ?? ''));
            if ($data !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) throw new \RuntimeException('La data per ricontattare va scritta come AAAA-MM-GG.');
            $nuovi += ['importo_cents' => $grezzo === '' ? null : (int) round((float) $grezzo * 100), 'nota' => mb_substr(trim((string) ($in['nota'] ?? '')), 0, 2000),
                       'ricontatta_il' => $data === '' ? null : $data];
        }
        $cambiati = [];
        foreach ($nuovi as $k => $v) if ((string) ($riga[$k] ?? '') !== (string) $v) $cambiati[$k] = $v;
        if ($cambiati) Db::update('preventivi', $cambiati + (self::conProposta() ? ['aggiornato_at' => Support::now()] : []), 'id = :id', ['id' => $id]);
        return $cambiati;
    }

    /** 9N · Lo storico di una richiesta: le modifiche salvate nel registro azioni, dalla più recente. */
    public static function storico(int $id): array
    {
        return Db::all("SELECT l.created_at, l.meta, u.email AS chi FROM audit_log l LEFT JOIN users u ON u.id = l.actor_user_id
                        WHERE l.action LIKE 'preventivo.%' AND (l.meta LIKE ? OR l.meta LIKE ?) ORDER BY l.id DESC LIMIT 20",
                       ['{"id":' . $id . ',%', '{"id":' . $id . '}']);
    }
}
