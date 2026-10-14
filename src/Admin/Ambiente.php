<?php

declare(strict_types=1);

namespace ArcoDelVento\Admin;

use ArcoDelVento\Mail\MailMessage;
use ArcoDelVento\Storage\JsonStore;
use ArcoDelVento\Support\Env;

/**
 * Il file .env, dal pannello Configurazione (solo super admin).
 *
 * Conosce ogni riga che il sito legge, con un nome da persona, la spiegazione
 * e il controllo del valore. Scrive il file come lo scriverebbe una persona
 * attenta:
 *
 *   - cambia solo le righe dei valori cambiati, e lascia com'è tutto il resto,
 *     commenti compresi; una chiave che nel file non c'è va in fondo;
 *   - prima di scrivere ne mette una copia in storage/data/ambiente/ (le
 *     ultime dieci), da cui si torna indietro con un click;
 *   - scrive in un file accanto e poi lo sostituisce, così il sito non legge
 *     mai un .env scritto a metà; poi lo rilegge e controlla che dica proprio
 *     i valori voluti;
 *   - annota chi ha cambiato quali chiavi, mai i valori.
 *
 * I segreti (password, chiavi, gettoni) non tornano mai nella pagina: si vede
 * solo se ci sono e quanto sono lunghi. Un valore impostato nell'ambiente del
 * server (pannello di Hostinger) vince sul file, e qui si vede ma non si cambia.
 */
final class Ambiente
{
    public const CARTELLA_COPIE = 'ambiente';
    public const REGISTRO       = 'ambiente-registro';
    private const COPIE         = 10;
    private const LUNGHEZZA     = 500;

    /**
     * I gruppi, nell'ordine della pagina, e le loro chiavi.
     *
     * tipo: url | scelta | si-no | intero | email | email-lista | testo | segreto | gettone
     *
     * @var array<string, array{nome:string, descrizione:string, voci:array<string,array<string,mixed>>}>
     */
    public const SCHEMA = [
        'sito' => [
            'nome' => 'Il sito',
            'descrizione' => 'Indirizzo, lingue, motori di ricerca e codice d\'accesso dell\'anteprima.',
            'voci' => [
                'APP_URL' => ['nome' => 'Indirizzo del sito', 'tipo' => 'url', 'obbligatorio' => true,
                    'aiuto' => 'Senza barra finale. Se il sito sta in una cartella, la cartella fa parte dell\'indirizzo (https://blackout.in/assisiapartment). Cambiandolo cambiano tutti i link.'],
                'APP_ENV' => ['nome' => 'Ambiente', 'tipo' => 'scelta', 'predefinito' => 'development',
                    'opzioni' => ['production' => 'production — il sito vero', 'development' => 'development — solo sul computer di sviluppo']],
                'APP_DEBUG' => ['nome' => 'Mostra gli errori di PHP nelle pagine', 'tipo' => 'si-no', 'predefinito' => 'false',
                    'aiuto' => 'Solo per lo sviluppo: in produzione mostrerebbe a chiunque i percorsi del server.'],
                'APP_NOINDEX' => ['nome' => 'Fuori dai motori di ricerca', 'tipo' => 'si-no', 'predefinito' => 'false',
                    'aiuto' => 'Sì su una copia di prova; sul dominio vero No, altrimenti Google non lo trova.'],
                'APP_DEFAULT_LOCALE' => ['nome' => 'Lingua principale', 'tipo' => 'scelta', 'predefinito' => 'it',
                    'opzioni' => ['it' => 'Italiano', 'en' => 'English']],
                'APP_LOCALES' => ['nome' => 'Lingue attive', 'tipo' => 'testo', 'predefinito' => 'it,en', 'modello' => '/^[a-z]{2}(,[a-z]{2})*$/',
                    'errore' => 'codici di due lettere separati da virgola, per esempio it,en',
                    'aiuto' => 'Separate da virgola: it,en. «es» si aggiunge quando le traduzioni sono pronte.'],
                'SITE_ACCESS_CODE' => ['nome' => 'Codice d\'accesso di partenza', 'tipo' => 'testo', 'predefinito' => 'Blackout_2026',
                    'aiuto' => 'Il codice dell\'anteprima, finché nell\'area riservata (Accesso al sito) non se ne sceglie un altro. «off» la spegne in ogni caso.'],
            ],
        ],
        'pagamento' => [
            'nome' => 'Pagamento online',
            'descrizione' => 'SumUp: si paga tutto online al momento della prenotazione. Spento, la prenotazione resta una richiesta.',
            'voci' => [
                'PAYMENT_PROVIDER' => ['nome' => 'Pagamento', 'tipo' => 'scelta', 'predefinito' => '',
                    'opzioni' => ['sumup' => 'SumUp'], 'vuoto' => 'Spento',
                    'aiuto' => 'Acceso solo se ci sono anche la chiave e il codice esercente.'],
                'SUMUP_API_KEY' => ['nome' => 'Chiave API segreta di SumUp', 'tipo' => 'segreto',
                    'aiuto' => 'Dal pannello di SumUp, parte sviluppatori. Comincia con sup_sk_.'],
                'SUMUP_MERCHANT_CODE' => ['nome' => 'Codice esercente SumUp', 'tipo' => 'testo', 'modello' => '/^[A-Za-z0-9]{3,32}$/',
                    'errore' => 'lettere e cifre, senza spazi', 'aiuto' => 'Nel profilo del conto SumUp.'],
                'SUMUP_API_URL' => ['nome' => 'Indirizzo dell\'API di SumUp', 'tipo' => 'url',
                    'aiuto' => 'Vuoto = https://api.sumup.com. Si cambia solo per le prove in locale.'],
            ],
        ],
        'prenotazioni' => [
            'nome' => 'Prenotazioni',
            'descrizione' => 'Il calendario che dice quali notti sono libere.',
            'voci' => [
                'BOOKING_PROVIDER' => ['nome' => 'Calendario', 'tipo' => 'scelta', 'predefinito' => 'demo',
                    'opzioni' => ['sito' => 'sito — il calendario del sito (con il pagamento)', 'demo' => 'demo — dimostrativo, notti occupate a caso', 'api' => 'api — un gestionale esterno']],
                'BOOKING_MIN_NIGHTS' => ['nome' => 'Soggiorno minimo imposto', 'tipo' => 'intero', 'min' => 1, 'max' => 30,
                    'aiuto' => 'Vuoto: comanda la regola della casa (1 notte, 2 con il sabato). Si riempie solo se un gestionale ne impone un altro.'],
                'BOOKING_CURRENCY' => ['nome' => 'Valuta', 'tipo' => 'testo', 'predefinito' => 'EUR', 'modello' => '/^[A-Z]{3}$/',
                    'errore' => 'tre lettere maiuscole, per esempio EUR'],
                'BOOKING_API_BASE_URL' => ['nome' => 'Gestionale: indirizzo', 'tipo' => 'url', 'aiuto' => 'Solo con Calendario = api.'],
                'BOOKING_API_KEY' => ['nome' => 'Gestionale: chiave', 'tipo' => 'segreto'],
                'BOOKING_PROPERTY_ID' => ['nome' => 'Gestionale: codice della struttura', 'tipo' => 'testo'],
            ],
        ],
        'posta' => [
            'nome' => 'Posta',
            'descrizione' => 'Come partono le e-mail: richieste, conferme di prenotazione e di pagamento.',
            'voci' => [
                'MAIL_TRANSPORT' => ['nome' => 'Come parte la posta', 'tipo' => 'scelta', 'predefinito' => 'log',
                    'opzioni' => ['smtp' => 'smtp — server di posta (consigliato)', 'mail' => 'mail — la funzione mail() del server', 'log' => 'log — non spedire: scrivi in storage/mail']],
                'MAIL_FROM_ADDRESS' => ['nome' => 'Mittente: indirizzo', 'tipo' => 'email'],
                'MAIL_FROM_NAME' => ['nome' => 'Mittente: nome', 'tipo' => 'testo', 'predefinito' => 'Arco del Vento'],
                'MAIL_TO_ADDRESS' => ['nome' => 'Chi riceve le richieste', 'tipo' => 'email-lista',
                    'aiuto' => 'Più indirizzi separati da virgola. Se nell\'area riservata (Ricezione e-mail) ce ne sono, valgono quelli.'],
                'MAIL_SMTP_HOST' => ['nome' => 'Server SMTP', 'tipo' => 'testo', 'modello' => '/^[A-Za-z0-9.-]+$/',
                    'errore' => 'un nome di server, per esempio smtp.hostinger.com', 'aiuto' => 'Per una casella su Hostinger: smtp.hostinger.com.'],
                'MAIL_SMTP_PORT' => ['nome' => 'Porta SMTP', 'tipo' => 'intero', 'min' => 1, 'max' => 65535, 'predefinito' => '587',
                    'aiuto' => '587 con tls, 465 con ssl.'],
                'MAIL_SMTP_ENCRYPTION' => ['nome' => 'Cifratura', 'tipo' => 'scelta', 'predefinito' => 'tls',
                    'opzioni' => ['tls' => 'tls (porta 587)', 'ssl' => 'ssl (porta 465)', 'none' => 'nessuna (sconsigliato)']],
                'MAIL_SMTP_USER' => ['nome' => 'Utente SMTP', 'tipo' => 'testo', 'aiuto' => 'Di solito l\'indirizzo completo della casella.'],
                'MAIL_SMTP_PASSWORD' => ['nome' => 'Password della casella', 'tipo' => 'segreto'],
            ],
        ],
        'accessi' => [
            'nome' => 'Area riservata e controlli',
            'descrizione' => 'I codici che servono una volta sola. Creato l\'account, si svuotano.',
            'voci' => [
                'ADMIN_SETUP_TOKEN' => ['nome' => 'Codice per creare l\'account del gestore', 'tipo' => 'gettone',
                    'aiuto' => 'Si usa alla prima apertura di /admin. Almeno 16 caratteri.'],
                'SUPERADMIN_SETUP_TOKEN' => ['nome' => 'Codice per creare il super admin', 'tipo' => 'gettone',
                    'aiuto' => 'Si usa una volta, su /admin/super. Almeno 16 caratteri.'],
                'PREFLIGHT_TOKEN' => ['nome' => 'Gettone del controllo finale dal browser', 'tipo' => 'gettone',
                    'aiuto' => 'Per tools/preflight.php aperto dal browser. Dopo il controllo si svuota.'],
            ],
        ],
        'titolare' => [
            'nome' => 'Dati del titolare',
            'descrizione' => 'Dati che servono all\'uso amministrativo e non compaiono sul sito.',
            'voci' => [
                'OWNER_TAX_CODE' => ['nome' => 'Codice fiscale del titolare', 'tipo' => 'segreto',
                    'aiuto' => 'Il sito non lo stampa in nessuna pagina: è un dato personale.'],
            ],
        ],
        'database' => [
            'nome' => 'Database',
            'descrizione' => 'Vuoto = il sito usa i contenuti dei file. Si riempie solo il giorno in cui si passa a MySQL.',
            'voci' => [
                'DB_DSN' => ['nome' => 'Collegamento (DSN)', 'tipo' => 'testo',
                    'aiuto' => 'Per esempio mysql:host=localhost;dbname=arcodelvento;charset=utf8mb4. Riempito, le camere si leggono dal database.'],
                'DB_USER' => ['nome' => 'Utente del database', 'tipo' => 'testo'],
                'DB_PASSWORD' => ['nome' => 'Password del database', 'tipo' => 'segreto'],
            ],
        ],
    ];

    public function __construct(
        private readonly string $file,
        private readonly JsonStore $store,
    ) {
    }

    // ================================================================ lettura

    /** @return array<string,mixed>|null */
    public static function voce(string $chiave): ?array
    {
        foreach (self::SCHEMA as $gruppo) {
            if (isset($gruppo['voci'][$chiave])) {
                return $gruppo['voci'][$chiave];
            }
        }

        return null;
    }

    /** @return list<string> */
    public static function chiavi(): array
    {
        $out = [];
        foreach (self::SCHEMA as $gruppo) {
            array_push($out, ...array_keys($gruppo['voci']));
        }

        return $out;
    }

    public static function segreta(string $chiave): bool
    {
        return in_array(self::voce($chiave)['tipo'] ?? '', ['segreto', 'gettone'], true);
    }

    public function esiste(): bool
    {
        return is_file($this->file);
    }

    /** Si può scrivere: il file, o la cartella se il file non c'è ancora. */
    public function scrivibile(): bool
    {
        return $this->esiste() ? is_writable($this->file) : is_writable(dirname($this->file));
    }

    public function modificato(): ?int
    {
        return $this->esiste() ? (int) filemtime($this->file) : null;
    }

    /**
     * I valori scritti nel file, letti come li legge il sito: se una chiave
     * compare due volte, vale l'ultima.
     *
     * @return array<string,string>
     */
    public function valori(): array
    {
        return self::leggiTesto($this->esiste() ? (string) file_get_contents($this->file) : '');
    }

    /** @return array<string,string> */
    private static function leggiTesto(string $testo): array
    {
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', $testo) ?: [] as $riga) {
            $coppia = Env::parseLine($riga);
            if ($coppia !== null) {
                $out[$coppia[0]] = $coppia[1];
            }
        }

        return $out;
    }

    /** Le chiavi del file che il pannello non conosce: si mostrano solo per nome. */
    public function chiaviSconosciute(): array
    {
        return array_values(array_diff(array_keys($this->valori()), self::chiavi()));
    }

    /** Il valore impostato nell'ambiente del server, che vince sul file. */
    public static function daServer(string $chiave): ?string
    {
        return Env::fromServer($chiave);
    }

    // =============================================================== controllo

    /**
     * Dal modulo ai valori da scrivere. Restituisce [cambi, errori]: cambi
     * contiene solo le chiavi il cui valore cambia rispetto al file.
     *
     * @param array<string,mixed> $inviati  env[CHIAVE] => valore
     * @param array<string,mixed> $svuota   svuota[CHIAVE] => 1, per i segreti
     * @return array{0:array<string,string>, 1:list<string>}
     */
    public function controlla(array $inviati, array $svuota): array
    {
        $attuali = $this->valori();
        $cambi = [];
        $errori = [];

        foreach (self::chiavi() as $chiave) {
            $voce = (array) self::voce($chiave);
            if (self::daServer($chiave) !== null) {
                continue;   // comanda il server: qui non si cambia
            }
            $vecchio = $attuali[$chiave] ?? '';
            $arrivato = $inviati[$chiave] ?? null;
            if (!is_string($arrivato) && $arrivato !== null) {
                $errori[] = $voce['nome'] . ': valore non valido.';
                continue;
            }

            if (self::segreta($chiave)) {
                if ($arrivato !== null && trim($arrivato) !== '') {
                    $nuovo = trim($arrivato);
                } elseif (!empty($svuota[$chiave])) {
                    $nuovo = '';
                } else {
                    continue;   // vuoto = resta quello che c'è
                }
            } else {
                if ($arrivato === null) {
                    continue;   // il modulo non l'ha mandato
                }
                $nuovo = trim($arrivato);
            }

            // Quello che nessuno ha toccato non si controlla: un valore strano
            // già nel file non deve impedire di salvare le altre voci.
            if ($nuovo === $vecchio) {
                continue;
            }
            $problema = self::problema($voce, $nuovo);
            if ($problema !== null) {
                $errori[] = $voce['nome'] . ' (' . $chiave . '): ' . $problema . '.';
                continue;
            }
            $nuovo = self::normalizza($voce, $nuovo);
            if ($nuovo !== $vecchio) {
                $cambi[$chiave] = $nuovo;
            }
        }

        return [$cambi, $errori];
    }

    /** Che cosa non va in un valore, o null. */
    private static function problema(array $voce, string $v): ?string
    {
        if (preg_match('/[\x00-\x1F\x7F]/', $v)) {
            return 'contiene un a capo o un carattere che non si vede';
        }
        if (mb_strlen($v) > self::LUNGHEZZA) {
            return 'al massimo ' . self::LUNGHEZZA . ' caratteri';
        }
        if ($v === '') {
            return !empty($voce['obbligatorio']) ? 'non può restare vuoto' : null;
        }

        switch ($voce['tipo']) {
            case 'url':
                $parti = parse_url(rtrim($v, '/'));
                if (!filter_var(rtrim($v, '/'), FILTER_VALIDATE_URL) || !is_array($parti)
                    || !in_array(strtolower((string) ($parti['scheme'] ?? '')), ['http', 'https'], true)
                    || isset($parti['query']) || isset($parti['fragment'])) {
                    return 'un indirizzo intero, che comincia con https://, senza ? né #';
                }
                return null;
            case 'scelta':
                return array_key_exists($v, (array) $voce['opzioni']) ? null : 'scegli uno dei valori della lista';
            case 'si-no':
                return in_array($v, ['true', 'false'], true) ? null : 'scegli Sì o No';
            case 'intero':
                if (!preg_match('/^\d+$/', $v) || (int) $v < (int) ($voce['min'] ?? 0) || (int) $v > (int) ($voce['max'] ?? PHP_INT_MAX)) {
                    return 'un numero da ' . (int) ($voce['min'] ?? 0) . ' a ' . (int) ($voce['max'] ?? PHP_INT_MAX);
                }
                return null;
            case 'email':
                return filter_var($v, FILTER_VALIDATE_EMAIL) ? null : 'un indirizzo e-mail valido';
            case 'email-lista':
                $pezzi = array_filter(array_map('trim', preg_split('/[,;]/', $v) ?: []), static fn (string $p): bool => $p !== '');
                return $pezzi !== [] && count(MailMessage::indirizzi($v)) === count($pezzi)
                    ? null : 'indirizzi e-mail validi, separati da virgola';
            case 'gettone':
                return mb_strlen($v) >= 16 ? null : 'almeno 16 caratteri, scelti a caso';
            default:
                if (isset($voce['modello']) && !preg_match((string) $voce['modello'], $v)) {
                    return (string) ($voce['errore'] ?? 'valore non valido');
                }
                return null;
        }
    }

    private static function normalizza(array $voce, string $v): string
    {
        return match ($voce['tipo']) {
            'url' => rtrim($v, '/'),
            'email-lista' => implode(', ', MailMessage::indirizzi($v)),
            default => $v,
        };
    }

    // =============================================================== scrittura

    /**
     * Scrive i cambi nel file: copia di sicurezza, scrittura, verifica, registro.
     *
     * @param array<string,string> $cambi
     * @throws \RuntimeException se il file non si può scrivere o non torna
     */
    public function salva(array $cambi, string $utente): void
    {
        if ($cambi === []) {
            return;
        }
        $prima = $this->esiste() ? (string) file_get_contents($this->file) : '';
        $dopo  = self::applica($prima, $cambi);

        // Prima di toccare il file: rilette, le righe nuove devono dire
        // esattamente i valori voluti, e le altre restare quelle di prima.
        $attesi = array_replace(self::leggiTesto($prima), $cambi);
        $letti  = self::leggiTesto($dopo);
        ksort($attesi);
        ksort($letti);
        if ($attesi !== $letti) {
            throw new \RuntimeException('Il file non tornava come doveva: non è stato scritto niente.');
        }

        $copia = $this->esiste() ? $this->copia($prima) : null;
        $this->scrivi($dopo);
        $this->annota($utente, 'modifica', array_keys($cambi), $copia);
    }

    /**
     * Il testo del file con i valori nuovi. Si cambiano solo le righe di quelle
     * chiavi (tutte, se una chiave compare più volte); il commento in coda a
     * una riga resta in coda, o va sopra se il valore nuovo è fra virgolette.
     *
     * @param array<string,string> $cambi
     */
    public static function applica(string $testo, array $cambi): string
    {
        $aCapo = str_contains($testo, "\r\n") ? "\r\n" : "\n";
        $righe = $testo === '' ? [] : (preg_split('/\r\n|\r|\n/', $testo) ?: []);
        if ($righe !== [] && end($righe) === '') {
            array_pop($righe);
        }

        $fatte = [];
        $out = [];
        foreach ($righe as $riga) {
            $coppia = Env::parseLine($riga);
            if ($coppia === null || !array_key_exists($coppia[0], $cambi)) {
                $out[] = $riga;
                continue;
            }
            $chiave = $coppia[0];
            $nuova  = self::riga($chiave, $cambi[$chiave]);
            $coda   = self::commentoInCoda($riga);
            if ($coda !== '') {
                if (self::fraVirgolette($cambi[$chiave])) {
                    $out[] = '# ' . ltrim(ltrim($coda), '# ');
                } else {
                    $nuova .= $coda;
                }
            }
            $out[] = $nuova;
            $fatte[$chiave] = true;
        }

        $mancanti = array_diff_key($cambi, $fatte);
        if ($mancanti !== []) {
            $out[] = '';
            $out[] = '# Aggiunte dall\'area riservata (Configurazione), ' . date('d/m/Y');
            foreach ($mancanti as $chiave => $valore) {
                $out[] = self::riga($chiave, $valore);
            }
        }

        return implode($aCapo, $out) . $aCapo;
    }

    /** Una riga CHIAVE=valore, fra virgolette solo se serve perché il sito la rilegga uguale. */
    public static function riga(string $chiave, string $valore): string
    {
        return $chiave . '=' . (self::fraVirgolette($valore) ? '"' . $valore . '"' : $valore);
    }

    private static function fraVirgolette(string $valore): bool
    {
        return $valore !== '' && preg_match('/[\s#"\']/', $valore) === 1;
    }

    /** «   # spiegazione» in coda a una riga con valore non quotato, o «». */
    private static function commentoInCoda(string $riga): string
    {
        $resto = ltrim(explode('=', $riga, 2)[1] ?? '');
        if ($resto === '' || $resto[0] === '"' || $resto[0] === "'") {
            return '';
        }
        if ($resto[0] === '#') {
            return ' ' . $resto;
        }
        $p = strpos($resto, ' #');
        if ($p === false) {
            return '';
        }
        while ($p > 0 && $resto[$p - 1] === ' ') {
            $p--;
        }

        return substr($resto, $p);
    }

    /** Scrive in un file accanto e lo mette al posto del vecchio: mai un .env a metà. */
    private function scrivi(string $testo): void
    {
        $cartella = dirname($this->file);
        $permessi = $this->esiste() ? (fileperms($this->file) & 0777) : 0640;
        $provvisorio = $cartella . '/.env.nuovo-' . bin2hex(random_bytes(4));

        if (@file_put_contents($provvisorio, $testo, LOCK_EX) !== strlen($testo)) {
            @unlink($provvisorio);
            // La cartella non si scrive ma il file sì: si scrive lui, sotto chiave.
            if (!$this->esiste() || @file_put_contents($this->file, $testo, LOCK_EX) !== strlen($testo)) {
                throw new \RuntimeException('Il file .env non si può scrivere: controlla i permessi via FTP.');
            }
            clearstatcache(true, $this->file);

            return;
        }
        @chmod($provvisorio, $permessi);
        if (!@rename($provvisorio, $this->file)) {
            @unlink($provvisorio);
            if (@file_put_contents($this->file, $testo, LOCK_EX) !== strlen($testo)) {
                throw new \RuntimeException('Il file .env non si può scrivere: controlla i permessi via FTP.');
            }
        }
        clearstatcache(true, $this->file);
    }

    // ========================================================= copie e registro

    private function cartellaCopie(): string
    {
        return $this->store->cartella() . '/' . self::CARTELLA_COPIE;
    }

    /** Una copia del file com'era, leggibile solo dal sito. Si tengono le ultime dieci. */
    private function copia(string $testo): string
    {
        $cartella = $this->cartellaCopie();
        if (!is_dir($cartella) && !@mkdir($cartella, 0700, true) && !is_dir($cartella)) {
            throw new \RuntimeException('Non si riesce a creare la cartella delle copie: controlla che storage/data sia scrivibile.');
        }
        $nome = 'env-' . date('Ymd-His') . '-' . bin2hex(random_bytes(2)) . '.txt';
        if (@file_put_contents($cartella . '/' . $nome, $testo, LOCK_EX) !== strlen($testo)) {
            throw new \RuntimeException('Non si riesce a salvare la copia di sicurezza: non è stato scritto niente.');
        }
        @chmod($cartella . '/' . $nome, 0600);

        $tutte = $this->nomiCopie();
        foreach (array_slice($tutte, self::COPIE) as $vecchia) {
            @unlink($cartella . '/' . $vecchia);
        }

        return $nome;
    }

    /** @return list<string> dalla più recente */
    private function nomiCopie(): array
    {
        $nomi = array_values(array_filter(
            scandir($this->cartellaCopie()) ?: [],
            static fn (string $n): bool => preg_match('/^env-\d{8}-\d{6}-[0-9a-f]{4}\.txt$/', $n) === 1
        ));
        rsort($nomi);

        return $nomi;
    }

    /**
     * Le copie, dalla più recente: quando è stata fatta e che cosa è cambiato
     * subito dopo (le chiavi, dal registro).
     *
     * @return list<array{nome:string, quando:string, chiavi:list<string>, utente:string}>
     */
    public function copie(): array
    {
        if (!is_dir($this->cartellaCopie())) {
            return [];
        }
        $perCopia = [];
        foreach ($this->registro() as $voce) {
            if (!empty($voce['copia'])) {
                $perCopia[(string) $voce['copia']] = $voce;
            }
        }
        $out = [];
        foreach ($this->nomiCopie() as $nome) {
            $d = \DateTimeImmutable::createFromFormat('Ymd-His', substr($nome, 4, 15));
            $out[] = [
                'nome'   => $nome,
                'quando' => $d ? $d->format('c') : '',
                'chiavi' => (array) ($perCopia[$nome]['chiavi'] ?? []),
                'utente' => (string) ($perCopia[$nome]['utente'] ?? ''),
            ];
        }

        return $out;
    }

    /** Rimette una copia. Anche il file di adesso, prima, va fra le copie. */
    public function ripristina(string $nome, string $utente): bool
    {
        if (!in_array($nome, $this->nomiCopie(), true)) {
            return false;
        }
        $testo = (string) file_get_contents($this->cartellaCopie() . '/' . $nome);
        $prima = $this->esiste() ? (string) file_get_contents($this->file) : '';
        $cambiate = [];
        $a = self::leggiTesto($prima);
        $b = self::leggiTesto($testo);
        foreach (array_unique(array_merge(array_keys($a), array_keys($b))) as $k) {
            if (($a[$k] ?? null) !== ($b[$k] ?? null)) {
                $cambiate[] = $k;
            }
        }
        $copia = $this->esiste() ? $this->copia($prima) : null;
        $this->scrivi($testo);
        $this->annota($utente, 'ripristino', $cambiate, $copia, $nome);

        return true;
    }

    /** @param list<string> $chiavi */
    private function annota(string $utente, string $azione, array $chiavi, ?string $copia, ?string $da = null): void
    {
        $this->store->update(self::REGISTRO, static function (array $voci) use ($utente, $azione, $chiavi, $copia, $da): array {
            array_unshift($voci, array_filter([
                'quando' => date('c'), 'utente' => $utente, 'azione' => $azione,
                'chiavi' => array_values($chiavi), 'copia' => $copia, 'da' => $da,
            ], static fn ($v): bool => $v !== null));

            return array_slice(array_values($voci), 0, 100);
        });
    }

    /** @return list<array<string,mixed>> dalla più recente */
    public function registro(): array
    {
        return array_values(array_filter($this->store->read(self::REGISTRO), 'is_array'));
    }

    // ================================================================= avvisi

    /**
     * Le combinazioni che non fanno quello che uno si aspetta, sui valori in uso.
     *
     * @param callable(string):string $valore  il valore in uso di una chiave
     * @return list<string>
     */
    public static function avvisi(callable $valore, bool $conGestore, bool $conSuper): array
    {
        $avvisi = [];
        $vero = static fn (string $k): bool => in_array(strtolower($valore($k)), ['1', 'true', 'yes', 'on'], true);
        $produzione = $valore('APP_ENV') === 'production';

        if ($produzione && $vero('APP_DEBUG')) {
            $avvisi[] = 'APP_DEBUG è acceso in produzione: gli errori di PHP si vedrebbero nelle pagine, con i percorsi del server.';
        }
        if ($produzione && !str_starts_with($valore('APP_URL'), 'https://')) {
            $avvisi[] = 'L\'indirizzo del sito non è in https: SumUp e i cookie sicuri lo vogliono.';
        }
        if (strtolower($valore('PAYMENT_PROVIDER')) === 'sumup') {
            if ($valore('SUMUP_API_KEY') === '' || $valore('SUMUP_MERCHANT_CODE') === '') {
                $avvisi[] = 'Pagamento SumUp scelto ma spento: mancano la chiave o il codice esercente.';
            } elseif (!str_starts_with($valore('SUMUP_API_KEY'), 'sup_sk_')) {
                $avvisi[] = 'La chiave SumUp non comincia con sup_sk_: controlla di aver copiato quella segreta, per intero.';
            }
            if ($valore('BOOKING_PROVIDER') !== 'sito') {
                $avvisi[] = 'Con il pagamento acceso il calendario dovrebbe essere «sito»: con «' . ($valore('BOOKING_PROVIDER') ?: 'demo') . '» si incasserebbe su date non verificate.';
            }
            if ($valore('MAIL_TRANSPORT') !== 'smtp') {
                $avvisi[] = 'Con il pagamento acceso la posta dovrebbe essere smtp: le conferme di pagamento partono per e-mail.';
            }
        }
        if ($valore('MAIL_TRANSPORT') === 'smtp' && ($valore('MAIL_SMTP_HOST') === '' || $valore('MAIL_SMTP_PASSWORD') === '')) {
            $avvisi[] = 'Posta smtp senza server o senza password: nessuna e-mail partirà.';
        }
        if (in_array($valore('MAIL_TRANSPORT'), ['', 'log'], true) && $produzione) {
            $avvisi[] = 'La posta è su «log»: le e-mail non partono, restano in storage/mail.';
        }
        if ($conGestore && $valore('ADMIN_SETUP_TOKEN') !== '') {
            $avvisi[] = 'L\'account del gestore c\'è già: il codice ADMIN_SETUP_TOKEN si può svuotare.';
        }
        if ($conSuper && $valore('SUPERADMIN_SETUP_TOKEN') !== '') {
            $avvisi[] = 'Il super admin c\'è già: il codice SUPERADMIN_SETUP_TOKEN si può svuotare.';
        }

        return $avvisi;
    }
}
