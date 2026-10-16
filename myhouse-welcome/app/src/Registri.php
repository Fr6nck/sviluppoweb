<?php
namespace MHW;

/**
 * 8I · I registri (log) sul nostro server, in app/storage/logs/ (fuori dalla cartella pubblica):
 *   app-AAAA-MM-GG.log            registro tecnico (Log), dati personali mascherati;
 *   mail-AAAA-MM-GG.log, email/   la posta in modalità prova (contiene apposta i link di accesso);
 *   accessi-admin-AAAA-MM-GG.log  gli accessi degli amministratori (Log::accesso).
 * Una volta al giorno (giro dei richiami e /cron): la pulizia (tecnici e accessi dopo N giorni, di serie 90;
 * posta dopo 7), la pulizia dell'IP nel registro delle azioni dopo 12 mesi e la copia dei file del giorno
 * prima su un bucket S3 a parte (Milano), con chiavi che possono solo scrivere: la cancellazione la fa il
 * ciclo di vita del bucket, e gli accessi degli amministratori restano inalterabili per 6 mesi (Object Lock).
 */
final class Registri
{
    public const TIPI = ['app' => 'tecnici', 'mail' => 'posta', 'accessi-admin' => 'accessi-admin'];
    public const GIORNI_POSTA = 7;
    public const MESI_IP_AUDIT = 12;
    /** Gli accessi degli amministratori su S3: inalterabili (Object Lock, Compliance) per 6 mesi. */
    public const GIORNI_BLOCCO = 180;

    /** @return array{giorni:int, bucket:string, regione:string, key:string, secret:string} */
    public static function config(): array
    {
        $c = (array) (Config::get('registri') ?? []);
        return ['giorni' => max(30, min(180, (int) ($c['giorni'] ?? 90) ?: 90)), 'bucket' => trim((string) ($c['bucket'] ?? '')),
                'regione' => trim((string) ($c['regione'] ?? '')) ?: 'eu-south-1', 'key' => trim((string) ($c['key'] ?? '')), 'secret' => (string) ($c['secret'] ?? ''),
                'endpoint' => trim((string) ($c['endpoint'] ?? ''))];   // endpoint: solo per le prove (S3 finto)
    }

    public static function s3Configurato(): bool { $c = self::config(); return $c['bucket'] !== '' && $c['key'] !== '' && $c['secret'] !== ''; }

    /**
     * La chiave per gli IP dei limiti di tentativi (RateLimit::ipKey): generata una volta con random_bytes(32)
     * e tenuta in app/storage (fuori dal web, non nel pacchetto, non si sovrascrive con un aggiornamento).
     */
    public static function chiave(): string
    {
        static $k = null;
        if ($k !== null) return $k;
        $f = MHW_APP . '/storage/.chiave-registri';
        $v = is_file($f) ? trim((string) @file_get_contents($f)) : '';
        if (!preg_match('/^[a-f0-9]{64}$/', $v)) {
            $v = bin2hex(random_bytes(32));
            if (is_dir(dirname($f)) || @mkdir(dirname($f), 0770, true)) { @file_put_contents($f, $v, LOCK_EX); @chmod($f, 0600); }
        }
        return $k = $v;
    }

    /** I file dei registri, dal più vecchio: [percorso => [tipo, giorno AAAA-MM-GG]]. */
    public static function file(): array
    {
        $out = [];
        foreach (glob(Log::dir() . '/*-????-??-??.log') ?: [] as $f) {
            if (preg_match('/^(app|mail|accessi-admin)-(\d{4}-\d{2}-\d{2})\.log$/', basename($f), $m)) $out[$f] = [$m[1], $m[2]];
        }
        uasort($out, fn($a, $b) => strcmp($a[1], $b[1]));
        return $out;
    }

    /** Le righe di un tipo di registro negli ultimi $giorni giorni (dal file più vecchio al più recente). @return string[] */
    public static function righe(string $tipo, int $giorni, int $massimo = 5000): array
    {
        $righe = [];
        for ($i = $giorni - 1; $i >= 0; $i--) {
            $f = Log::file($tipo, time() - $i * 86400);
            if (is_file($f)) foreach (file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $r) $righe[] = $r;
        }
        return array_slice($righe, -$massimo);
    }

    /** Spazio occupato, numero di file e il giorno del più vecchio (per il riquadro in Impostazioni). */
    public static function info(): array
    {
        $byte = 0; $n = 0; $vecchio = null;
        foreach (self::file() as $f => [$tipo, $giorno]) { $byte += (int) @filesize($f); $n++; $vecchio = $vecchio === null || $giorno < $vecchio ? $giorno : $vecchio; }
        foreach (glob(Log::dir() . '/email/*.html') ?: [] as $f) { $byte += (int) @filesize($f); $n++; }
        return ['byte' => $byte, 'file' => $n, 'vecchio' => $vecchio, 'stato' => self::stato()];
    }

    /** L'esito dell'ultima copia su S3. */
    public static function stato(): ?array
    {
        $f = Log::dir() . '/.registri-s3.json';
        $j = is_file($f) ? json_decode((string) @file_get_contents($f), true) : null;
        return is_array($j) ? $j : null;
    }

    /**
     * Una volta al giorno: i vecchi app.log e mail.log divisi per giorno, la pulizia e la copia su S3 dei file di ieri.
     * Si segna il giorno fatto in un file: due giri nella stessa giornata non rifanno niente.
     */
    public static function giornaliero(bool $forza = false): void
    {
        $segno = Log::dir() . '/.registri-giorno';
        $oggi = gmdate('Y-m-d');
        if (!$forza && is_file($segno) && trim((string) @file_get_contents($segno)) === $oggi) return;
        @file_put_contents($segno, $oggi, LOCK_EX);
        try { self::dividiVecchi(); } catch (\Throwable $e) { Log::exception($e, 'registri: file vecchi'); }
        try { self::pulisci(); } catch (\Throwable $e) { Log::exception($e, 'registri: pulizia'); }
        try { self::pulisciAudit(); } catch (\Throwable $e) { Log::exception($e, 'registri: registro delle azioni'); }
        try { self::pulisciDati(); } catch (\Throwable $e) { Log::exception($e, 'registri: dati scaduti'); }
        if (self::s3Configurato()) {
            try { self::caricaS3(gmdate('Y-m-d', time() - 86400)); } catch (\Throwable $e) { Log::exception($e, 'registri: copia su S3'); }
        }
    }

    /**
     * 8J · I tempi dell'informativa privacy che il sito applica da solo: le risposte di «Anche il tuo sito» dopo 12 mesi,
     * le email (cifrate) degli inviti dopo 24 mesi.
     */
    public static function pulisciDati(?int $adesso = null): void
    {
        $t = $adesso ?? time();
        $iso = fn(int $s) => gmdate('Y-m-d\TH:i:s\Z', $s);
        if (Migrator::tableExists('interessi_sito')) Db::run('DELETE FROM interessi_sito WHERE created_at < ?', [$iso(strtotime('-12 months', $t))]);
        if (Migrator::tableExists('referral_invites')) Db::run('DELETE FROM referral_invites WHERE sent_at < ?', [$iso(strtotime('-24 months', $t))]);
    }

    /** Una volta: app.log e mail.log di prima della 8I divisi nei file del giorno, secondo la data di ogni riga. */
    public static function dividiVecchi(): void
    {
        foreach (['app', 'mail'] as $tipo) {
            $vecchio = Log::dir() . "/$tipo.log";
            if (!is_file($vecchio)) continue;
            $h = @fopen($vecchio, 'r');
            if (!$h) continue;
            $ultimo = gmdate('Y-m-d');
            while (($r = fgets($h)) !== false) {
                if (trim($r) === '') continue;
                $j = json_decode($r, true);
                $giorno = is_array($j) && preg_match('/^\d{4}-\d{2}-\d{2}/', (string) ($j['t'] ?? ''), $m) ? $m[0] : $ultimo;
                $ultimo = $giorno;
                // Il registro tecnico di prima non era mascherato: lo si maschera adesso (la posta no, contiene apposta i link).
                if ($tipo === 'app' && is_array($j)) {
                    $j['msg'] = Log::maschera((string) ($j['msg'] ?? ''));
                    if (isset($j['ctx']) && is_array($j['ctx'])) $j['ctx'] = Log::mascheraTutto($j['ctx']);
                    $r = json_encode($j, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
                }
                @file_put_contents(Log::dir() . "/$tipo-$giorno.log", $r, FILE_APPEND | LOCK_EX);
            }
            fclose($h);
            @unlink($vecchio);
        }
    }

    /** I registri tecnici e degli accessi più vecchi di N giorni (di serie 90); la posta di prova dopo 7. */
    public static function pulisci(?int $ora = null): int
    {
        $ora ??= time();
        $giorni = self::config()['giorni'];
        $via = 0;
        foreach (self::file() as $f => [$tipo, $giorno]) {
            $eta = ($ora - (int) strtotime($giorno . ' 00:00:00 UTC')) / 86400;
            $limite = $tipo === 'mail' ? self::GIORNI_POSTA : $giorni;
            if ($eta > $limite && @unlink($f)) $via++;
        }
        foreach (glob(Log::dir() . '/email/*.html') ?: [] as $f) {
            if ($ora - (int) @filemtime($f) > self::GIORNI_POSTA * 86400 && @unlink($f)) $via++;
        }
        return $via;
    }

    /**
     * Il registro delle azioni (audit_log) non si cancella: alcune righe servono al funzionamento. Dopo 12 mesi
     * si toglie l'IP dal campo meta; quelli più recenti si tengono abbreviati (anche quelli scritti prima della 8I).
     */
    public static function pulisciAudit(): void
    {
        if (!Migrator::tableExists('audit_log')) return;
        $soglia = gmdate('Y-m-d\TH:i:s\Z', time() - self::MESI_IP_AUDIT * 30 * 86400);
        foreach (Db::all("SELECT id, meta, created_at FROM audit_log WHERE meta LIKE '%\"ip\"%'") as $r) {
            $m = json_decode((string) $r['meta'], true);
            if (!is_array($m) || !array_key_exists('ip', $m)) continue;
            if ((string) $r['created_at'] < $soglia) unset($m['ip']);
            else { $ab = Log::mascheraIp((string) $m['ip']); if ($ab === $m['ip']) continue; $m['ip'] = $ab; }
            Db::update('audit_log', ['meta' => $m ? json_encode($m, JSON_UNESCAPED_UNICODE) : ''], 'id = :id', ['id' => $r['id']]);
        }
    }

    /**
     * La copia dei file di un giorno sul bucket dei registri: tecnici/, posta/, accessi-admin/ + il nome del file.
     * Solo PutObject (le chiavi non possono cancellare); Content-MD5 perché il bucket ha Object Lock.
     * @return array{ok:bool, msg:string}
     */
    public static function caricaS3(string $giorno): array
    {
        $c = self::config();
        $s3 = new S3Storage(['bucket' => $c['bucket'], 'region' => $c['regione'], 'key' => $c['key'], 'secret' => $c['secret'], 'endpoint' => $c['endpoint']]);
        $fatti = 0; $errore = '';
        foreach (self::TIPI as $tipo => $prefisso) {
            $f = Log::dir() . "/$tipo-$giorno.log";
            if (!is_file($f)) continue;
            try { $s3->putRegistro($prefisso . '/' . basename($f), (string) file_get_contents($f), $tipo === 'accessi-admin' ? self::GIORNI_BLOCCO : 0); $fatti++; }
            catch (\Throwable $e) { $errore = S3Storage::$ultimoErrore ?: $e->getMessage(); }
        }
        $esito = ['quando' => Support::now(), 'giorno' => $giorno, 'ok' => $errore === '', 'file' => $fatti,
                  'msg' => $errore === '' ? ($fatti ? "Caricati $fatti file del $giorno." : "Nessun file del $giorno da caricare.") : 'Non riuscito: ' . $errore];
        @file_put_contents(Log::dir() . '/.registri-s3.json', json_encode($esito, JSON_UNESCAPED_UNICODE), LOCK_EX);
        return ['ok' => $esito['ok'], 'msg' => $esito['msg']];
    }

    /** Lo zip dei registri degli ultimi 7 giorni (per «Scarica»): il percorso di un file temporaneo. */
    public static function zip(int $giorni = 7): string
    {
        if (!class_exists(\ZipArchive::class)) throw new \RuntimeException('Sul server manca l\'estensione zip di PHP: non si può preparare lo zip.');
        $tmp = tempnam(sys_get_temp_dir(), 'registri');
        $z = new \ZipArchive();
        if ($z->open($tmp, \ZipArchive::OVERWRITE) !== true) throw new \RuntimeException('Non si riesce a preparare lo zip.');
        $da = gmdate('Y-m-d', time() - ($giorni - 1) * 86400);
        foreach (self::file() as $f => [$tipo, $giorno]) if ($giorno >= $da) $z->addFile($f, basename($f));
        foreach (glob(Log::dir() . '/email/*.html') ?: [] as $f) if (filemtime($f) >= time() - $giorni * 86400) $z->addFile($f, 'email/' . basename($f));
        if ($z->numFiles === 0) $z->addFromString('LEGGIMI.txt', "Nessun registro negli ultimi $giorni giorni.\n");
        $z->close();
        return $tmp;
    }

    /**
     * «Svuota i registri»: registri tecnici e posta di prova. Il registro degli accessi degli amministratori no:
     * è la traccia di chi ha fatto cosa, e un amministratore non deve poterla cancellare (resta anche la copia su S3).
     */
    public static function svuota(): int
    {
        $via = 0;
        foreach (self::file() as $f => [$tipo, $giorno]) if ($tipo !== 'accessi-admin' && @unlink($f)) $via++;
        foreach (glob(Log::dir() . '/email/*.html') ?: [] as $f) if (@unlink($f)) $via++;
        return $via;
    }
}
