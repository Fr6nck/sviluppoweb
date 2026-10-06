<?php
namespace MHW;

/**
 * Amministrazione → Impostazioni: Stripe, posta in uscita e archivio delle foto
 * impostati dal pannello invece che a mano.
 *
 * I valori finiscono in app/config.local.php, il file che config.php legge già
 * dopo le variabili d'ambiente: è lo stesso posto dove andrebbero scritti a mano.
 * Regole:
 *  - un campo impostato da una variabile d'ambiente (e non da config.local.php)
 *    qui si vede ma non si cambia: comanda il server;
 *  - i segreti non si rimostrano mai: si vede solo che ci sono e come finiscono;
 *    lasciare il campo vuoto li conserva;
 *  - per salvare serve la password dell'amministratore;
 *  - il file si riscrive intero con var_export (i commenti scritti a mano non si
 *    conservano: la versione precedente resta in config.local.bak.php).
 */
final class Impostazioni
{
    /**
     * I campi, per gruppo. [percorso => [variabile d'ambiente, etichetta, tipo, aiuto, opzioni]]
     * Tipi: text, secret, email, url, number, choice, check.
     */
    public const GRUPPI = [
        'stripe' => [
            'stripe.secret_key'     => ['STRIPE_SECRET_KEY', 'Chiave segreta', 'secret', 'Stripe → Sviluppatori → Chiavi API. Comincia con sk_live_ (o sk_test_ per le prove).'],
            'stripe.webhook_secret' => ['STRIPE_WEBHOOK_SECRET', 'Segreto del webhook', 'secret', 'Stripe → Sviluppatori → Webhook → il tuo endpoint → «Segreto di firma». Comincia con whsec_.'],
            'stripe.automatic_tax'  => ['STRIPE_AUTOMATIC_TAX', 'Usa Stripe Tax per calcolare l\'IVA', 'check', 'Solo se Stripe Tax è già attivo nel pannello di Stripe.'],
        ],
        'posta' => [
            'mail.transport'  => ['MAIL_TRANSPORT', 'Come parte la posta', 'choice', '', ['smtp' => 'Server SMTP (consigliato)', 'mail' => 'Funzione mail() del server', 'log' => 'Non spedire: scrivi in storage/logs/mail.log']],
            'mail.host'       => ['MAIL_HOST', 'Server SMTP', 'text', 'Per esempio smtp.tuodominio.it: lo trovi nel cPanel, alla voce «Account email».'],
            'mail.port'       => ['MAIL_PORT', 'Porta', 'number', '587 con STARTTLS, 465 con SSL.'],
            'mail.encryption' => ['MAIL_ENCRYPTION', 'Cifratura', 'choice', '', ['tls' => 'STARTTLS (porta 587)', 'ssl' => 'SSL (porta 465)', 'none' => 'Nessuna (sconsigliato)']],
            'mail.user'       => ['MAIL_USER', 'Utente', 'text', 'Di solito l\'indirizzo email completo.'],
            'mail.pass'       => ['MAIL_PASS', 'Password della casella', 'secret', ''],
            'mail.from'       => ['MAIL_FROM', 'Mittente', 'email', 'L\'indirizzo che vedono i clienti. Meglio se è la stessa casella dell\'utente.'],
            'mail.from_name'  => ['MAIL_FROM_NAME', 'Nome del mittente', 'text', ''],
        ],
        'archivio' => [
            'storage.driver'             => ['MHW_STORAGE', 'Dove si salvano i file nuovi', 'choice', '', ['s3' => 'Amazon S3 (consigliato in produzione)', 'local' => 'Sul disco del server']],
            'storage.s3.region'          => ['AWS_REGION', 'Regione', 'text', 'Per esempio eu-south-1 (Milano).'],
            'storage.s3.bucket'          => ['AWS_S3_BUCKET', 'Bucket', 'text', ''],
            'storage.s3.key'             => ['AWS_ACCESS_KEY_ID', 'Access key ID', 'text', 'Di un utente IAM con s3:PutObject, s3:GetObject e s3:DeleteObject su questo bucket.'],
            'storage.s3.secret'          => ['AWS_SECRET_ACCESS_KEY', 'Secret access key', 'secret', ''],
            'storage.s3.public_base_url' => ['AWS_S3_PUBLIC_URL', 'Indirizzo pubblico (CDN)', 'url', 'Facoltativo: solo con CloudFront o un bucket pubblico in lettura. Vuoto = indirizzi firmati a tempo.'],
        ],
    ];

    public const TITOLI = ['stripe' => 'Stripe', 'posta' => 'Posta in uscita', 'archivio' => 'Archivio di foto e PDF'];

    public static function file(): string { return MHW_APP . '/config.local.php'; }

    /** Il contenuto attuale di config.local.php (vuoto se non c'è). */
    public static function locale(): array
    {
        $f = self::file();
        if (!is_file($f)) return [];
        $v = (static fn() => require $f)();
        return is_array($v) ? $v : [];
    }

    private static function prendi(array $a, string $percorso): mixed
    {
        foreach (explode('.', $percorso) as $k) {
            if (!is_array($a) || !array_key_exists($k, $a)) return null;
            $a = $a[$k];
        }
        return $a;
    }

    private static function metti(array &$a, string $percorso, mixed $v): void
    {
        $chiavi = explode('.', $percorso);
        $ultima = array_pop($chiavi);
        $p = &$a;
        foreach ($chiavi as $k) {
            if (!isset($p[$k]) || !is_array($p[$k])) $p[$k] = [];
            $p = &$p[$k];
        }
        $p[$ultima] = $v;
    }

    private static function togli(array &$a, string $percorso): void
    {
        $chiavi = explode('.', $percorso);
        $ultima = array_pop($chiavi);
        $p = &$a;
        foreach ($chiavi as $k) {
            if (!isset($p[$k]) || !is_array($p[$k])) return;
            $p = &$p[$k];
        }
        unset($p[$ultima]);
    }

    /** Il valore in uso adesso (variabili d'ambiente + config.local.php). */
    public static function valore(string $percorso): mixed
    {
        $parti = explode('.', $percorso);
        return self::prendi([$parti[0] => Config::get($parti[0])], $percorso);
    }

    /** Da dove viene il valore: file (config.local.php), env (variabile d'ambiente, bloccato qui) o predefinito. */
    public static function origine(string $percorso, array $locale): string
    {
        if (self::prendi($locale, $percorso) !== null) return 'file';
        $env = self::GRUPPI[self::gruppoDi($percorso)][$percorso][0];
        $v = getenv($env);
        return $v !== false && $v !== '' ? 'env' : 'predefinito';
    }

    public static function gruppoDi(string $percorso): string
    {
        foreach (self::GRUPPI as $g => $campi) if (isset($campi[$percorso])) return $g;
        throw new \InvalidArgumentException('Campo sconosciuto: ' . $percorso);
    }

    /** Un segreto, senza mostrarlo: «sk_live_ … 4f2a». */
    public static function mascherato(string $v): string
    {
        if ($v === '') return '';
        $prefisso = preg_match('/^(sk_live_|sk_test_|whsec_)/', $v, $m) ? $m[1] : '';
        return $prefisso . '… ' . (mb_strlen($v) > 10 ? mb_substr($v, -4) : '');
    }

    /** Stripe in modalità reale o di prova, dalla chiave. */
    public static function modoStripe(): string
    {
        $k = (string) (Config::get('stripe')['secret_key'] ?? '');
        return str_starts_with($k, 'sk_live_') ? 'live' : (str_starts_with($k, 'sk_test_') ? 'test' : '');
    }

    /**
     * Salva un gruppo. $in: i campi del modulo (nome = percorso con i punti
     * cambiati in due trattini bassi), più 'togli' per i segreti da cancellare.
     * @return array<string,string> errori per campo; vuoto = salvato
     */
    public static function salva(string $gruppo, array $in): array
    {
        if (!isset(self::GRUPPI[$gruppo])) return ['_' => 'Gruppo sconosciuto.'];
        $locale = self::locale();
        $nuovo = $locale;
        $togli = (array) ($in['togli'] ?? []);
        $errori = [];
        $effettivi = [];
        foreach (self::GRUPPI[$gruppo] as $percorso => $c) {
            [$env, $etichetta, $tipo] = $c;
            $nome = self::nome($percorso);
            if (self::origine($percorso, $locale) === 'env') { $effettivi[$percorso] = self::valore($percorso); continue; }
            $grezzo = preg_replace('/[\x00-\x1F\x7F]/', '', trim((string) ($in[$nome] ?? ''))) ?? '';
            if ($tipo === 'check') {
                $v = !empty($in[$nome]);
                self::metti($nuovo, $percorso, $v);
                $effettivi[$percorso] = $v;
                continue;
            }
            if ($tipo === 'secret') {
                if (!empty($togli[$nome])) { self::togli($nuovo, $percorso); $effettivi[$percorso] = ''; continue; }
                if ($grezzo === '') { $effettivi[$percorso] = (string) self::valore($percorso); continue; }   // vuoto = resta com'è
            }
            $errore = match (true) {
                $grezzo === '' => '',
                $percorso === 'stripe.secret_key' && !preg_match('/^sk_(live|test)_[A-Za-z0-9_]{10,}$/', $grezzo) => 'La chiave segreta comincia con sk_live_ (o sk_test_). Non usare la chiave pubblicabile pk_.',
                $percorso === 'stripe.webhook_secret' && !preg_match('/^whsec_[A-Za-z0-9_+\/=]{10,}$/', $grezzo) => 'Il segreto del webhook comincia con whsec_.',
                $tipo === 'choice' && !isset($c[4][$grezzo]) => 'Scegli una delle opzioni.',
                $tipo === 'number' && (!ctype_digit($grezzo) || (int) $grezzo < 1 || (int) $grezzo > 65535) => 'Una porta è un numero tra 1 e 65535.',
                $tipo === 'email' && !filter_var($grezzo, FILTER_VALIDATE_EMAIL) => 'Questo indirizzo email non sembra valido.',
                $tipo === 'url' && !preg_match('#^https://[^\s/]+#', $grezzo) => 'Scrivi l\'indirizzo completo, con https://.',
                $percorso === 'storage.s3.region' && !preg_match('/^[a-z]{2}(-[a-z]+)+-\d$/', $grezzo) => 'La regione si scrive come eu-south-1.',
                $percorso === 'storage.s3.bucket' && !preg_match('/^[a-z0-9][a-z0-9.\-]{1,61}[a-z0-9]$/', $grezzo) => 'Il nome del bucket ha solo lettere minuscole, numeri, punti e trattini.',
                $percorso === 'storage.s3.key' && !preg_match('/^[A-Z0-9]{16,128}$/', $grezzo) => 'L\'access key ID è fatto di lettere maiuscole e numeri, per esempio AKIA….',
                $percorso === 'mail.host' && !preg_match('/^[A-Za-z0-9.\-]+$/', $grezzo) => 'Scrivi solo il nome del server, senza http:// né porta.',
                default => '',
            };
            if ($errore !== '') { $errori[$nome] = $errore; continue; }
            $v = $tipo === 'number' ? (int) $grezzo : $grezzo;
            if ($grezzo === '') self::togli($nuovo, $percorso); else self::metti($nuovo, $percorso, $v);
            $effettivi[$percorso] = $grezzo === '' ? '' : $v;
        }
        // Le regole che tengono insieme i campi: niente SMTP senza server, niente S3 senza credenziali.
        if (!$errori && $gruppo === 'posta' && ($effettivi['mail.transport'] ?? '') === 'smtp') {
            foreach (['mail.host' => 'Serve il server SMTP.', 'mail.from' => 'Serve l\'indirizzo del mittente.'] as $p => $msg) {
                if ((string) ($effettivi[$p] ?? '') === '') $errori[self::nome($p)] = $msg;
            }
        }
        if (!$errori && $gruppo === 'archivio' && ($effettivi['storage.driver'] ?? '') === 's3') {
            foreach (['storage.s3.region' => 'Serve la regione.', 'storage.s3.bucket' => 'Serve il bucket.', 'storage.s3.key' => 'Serve l\'access key ID.', 'storage.s3.secret' => 'Serve la secret access key.'] as $p => $msg) {
                if ((string) ($effettivi[$p] ?? '') === '') $errori[self::nome($p)] = $msg;
            }
        }
        if (!$errori && $gruppo === 'stripe' && ($effettivi['stripe.secret_key'] ?? '') !== '' && ($effettivi['stripe.webhook_secret'] ?? '') === '') {
            $errori[self::nome('stripe.webhook_secret')] = 'Serve anche il segreto del webhook: senza, i pagamenti non attivano le guide.';
        }
        if ($errori) return $errori;
        self::scrivi($nuovo);
        return [];
    }

    /** Il nome del campo nel modulo: i punti diventano due trattini bassi. */
    public static function nome(string $percorso): string { return str_replace('.', '__', $percorso); }

    private static function scrivi(array $dati): void
    {
        $f = self::file();
        $dir = dirname($f);
        if (!is_writable($dir) && !(is_file($f) && is_writable($f))) {
            throw new \RuntimeException('Non si riesce a scrivere app/config.local.php: dai i permessi di scrittura alla cartella app/ (o al file), oppure crealo a mano dal modello config.local.esempio.php.');
        }
        $testo = "<?php\n// Impostazioni di questo server: segreti compresi. Escluso dal repository e dal pacchetto.\n"
               . "// Lo riscrive anche Amministrazione → Impostazioni: i commenti scritti a mano non si conservano\n"
               . "// (la versione precedente resta in config.local.bak.php).\n"
               . 'return ' . var_export($dati, true) . ";\n";
        if (is_file($f)) @copy($f, $dir . '/config.local.bak.php');
        $tmp = $dir . '/.config.local.' . bin2hex(random_bytes(6)) . '.php';
        if (@file_put_contents($tmp, $testo, LOCK_EX) === false) throw new \RuntimeException('Non si riesce a scrivere nella cartella app/: controlla i permessi.');
        @chmod($tmp, 0640);
        if (!@rename($tmp, $f)) {
            if (@file_put_contents($f, $testo, LOCK_EX) === false) { @unlink($tmp); throw new \RuntimeException('Non si riesce a sostituire app/config.local.php: controlla i permessi.'); }
            @unlink($tmp);
        }
        if (function_exists('opcache_invalidate')) @opcache_invalidate($f, true);
    }

    /** Dalla risposta del server di posta, cosa controllare: in parole semplici, con la risposta originale in fondo. */
    public static function consiglioPosta(string $errore): string
    {
        $e = mb_strtolower($errore);
        $consiglio = match (true) {
            $errore === '' => 'Controlla server, porta, cifratura, utente e password.',
            str_contains($e, 'accesso rifiutato') || preg_match('/\b(535|534|530)\b/', $e) === 1
                => 'Il server ha rifiutato utente o password. L\'utente è l\'indirizzo completo della casella; riscrivi la password della casella (non quella del pannello) e controlla che il browser non l\'abbia riempita da solo con un\'altra.',
            str_contains($e, 'connessione a') => 'Il sito non raggiunge il server di posta: controlla nome del server e porta (587 con STARTTLS, 465 con SSL). Se la porta è giusta, l\'hosting potrebbe bloccarla: prova l\'altra.',
            str_contains($e, 'starttls') || str_contains($e, 'ssl') || str_contains($e, 'crypto') => 'La connessione cifrata non è riuscita: prova SSL sulla porta 465 invece di STARTTLS sulla 587.',
            preg_match('/\b(550|551|553|554)\b/', $e) === 1 => 'Il server ha rifiutato il mittente o il destinatario: il mittente deve essere la stessa casella dell\'utente (o un suo alias).',
            preg_match('/\b(421|450|451|452)\b/', $e) === 1 => 'Il server è momentaneamente occupato o ha limitato gli invii: riprova tra qualche minuto.',
            default => 'Controlla server, porta, cifratura, utente e password.',
        };
        return $consiglio . ($errore !== '' ? ' Risposta del server: «' . mb_substr($errore, 0, 300) . '».' : '');
    }

    /**
     * Prova la connessione con la configurazione in uso.
     * @return array{0:bool,1:string} [riuscita, messaggio per l'amministratore]
     */
    public static function prova(string $gruppo, string $emailAdmin): array
    {
        try {
            switch ($gruppo) {
                case 'stripe':
                    if (!Stripe::enabled()) return [false, 'Stripe non è ancora configurato: servono la chiave segreta e il segreto del webhook.'];
                    Stripe::call('GET', 'balance');
                    return [true, 'Stripe risponde: la chiave è valida (' . (self::modoStripe() === 'live' ? 'modalità reale' : 'modalità di prova') . '). Il segreto del webhook si verifica al primo pagamento: controlla in Stripe che gli eventi arrivino con esito 200.'];
                case 'posta':
                    $t = (string) Config::get('mail')['transport'];
                    $ok = Mailer::send($emailAdmin, 'Prova della posta di MyHouse Welcome', "Se leggi questa email, la posta in uscita funziona.\n\nInviata da Amministrazione → Impostazioni il " . gmdate('d/m/Y H:i') . ' (UTC).');
                    if (!$ok) return [false, 'L\'email di prova non è partita. ' . self::consiglioPosta(Mailer::ultimoErrore())];
                    return [true, $t === 'log' ? 'La posta è in modalità prova: l\'email è finita in storage/logs/mail.log, non nella casella.' : 'Email di prova spedita a ' . $emailAdmin . ': controlla la casella (anche lo spam).'];
                case 'archivio':
                    $cfg = Config::get('storage');
                    if (($cfg['driver'] ?? '') !== 's3') return [true, 'Foto e PDF si salvano sul disco del server: non c\'è niente da collegare.'];
                    $s3 = new S3Storage($cfg['s3']);
                    $chiave = '_prova/' . bin2hex(random_bytes(8)) . '.txt';
                    $s3->put($chiave, 'prova', 'text/plain');
                    $letto = $s3->get($chiave);
                    $s3->delete($chiave);
                    return $letto === 'prova' ? [true, 'Il bucket risponde: scrittura, lettura e cancellazione riuscite.'] : [false, 'Il bucket ha accettato il file ma ne ha restituito un altro contenuto.'];
            }
        } catch (\Throwable $e) {
            Log::exception($e, 'impostazioni: prova ' . $gruppo);
            return [false, 'La prova non è riuscita: ' . $e->getMessage()];
        }
        return [false, 'Gruppo sconosciuto.'];
    }
}
