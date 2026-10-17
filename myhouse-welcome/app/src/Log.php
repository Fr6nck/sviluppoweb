<?php
namespace MHW;

/**
 * Il registro tecnico. Quello che l'utente non deve vedere — nomi di classi,
 * percorsi, risposte di Stripe — finisce qui, in storage/logs/app-AAAA-MM-GG.log
 * (8I: un file al giorno), con un codice che si può citare all'assistenza.
 * Prima di scrivere si mascherano i dati personali (email, IP, telefoni) e i segreti.
 * Quanto restano i file e la copia su S3: Registri.
 */
final class Log
{
    public static function dir(): string
    {
        $d = MHW_APP . '/storage/logs';
        if (!is_dir($d)) @mkdir($d, 0770, true);
        return $d;
    }

    /** 8I: il file del giorno per un tipo di registro: app, mail, accessi-admin. */
    public static function file(string $tipo = 'app', ?int $quando = null): string
    {
        return self::dir() . '/' . $tipo . '-' . gmdate('Y-m-d', $quando ?? time()) . '.log';
    }

    /** @return string il codice di riferimento della riga scritta */
    public static function error(string $message, array $context = []): string
    {
        return self::write('ERROR', $message, $context);
    }

    public static function info(string $message, array $context = []): string
    {
        return self::write('INFO', $message, $context);
    }

    public static function exception(\Throwable $e, string $where = ''): string
    {
        return self::write('ERROR', ($where !== '' ? "$where: " : '') . get_class($e) . ': ' . $e->getMessage(), [
            'file' => $e->getFile() . ':' . $e->getLine(),
        ]);
    }

    /**
     * 8I · Il registro degli accessi degli amministratori (accessi, uscite, entrate negli account dei clienti,
     * cambi di impostazioni): una riga per evento, con l'IP mascherato. Ogni notte se ne fa una copia su S3,
     * dove non si può cancellare per 6 mesi (Registri::caricaS3).
     */
    public static function accesso(string $azione, ?int $utente, string $email = '', array $dettagli = []): void
    {
        $riga = ['t' => Support::now(), 'azione' => $azione, 'utente' => $utente, 'email' => self::maschera($email),
                 'ip' => self::mascheraIp(RateLimit::ip())] + ($dettagli ? ['dettagli' => self::mascheraTutto($dettagli)] : []);
        @file_put_contents(self::file('accessi-admin'), json_encode($riga, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);
    }

    /** 8I · Un IP abbreviato: IPv4 con l'ultimo blocco a 0, IPv6 con i soli primi tre gruppi. */
    public static function mascheraIp(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) return preg_replace('/\.\d+$/', '.0', $ip);
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $g = explode(':', (string) inet_ntop((string) inet_pton($ip)));
            return implode(':', array_slice($g, 0, 3)) . '::';
        }
        return $ip;
    }

    /**
     * 8I · Toglie da un testo i dati personali e i segreti: email → m***@dominio.it, IP abbreviati,
     * telefoni → solo le ultime 3 cifre, token / password / chiavi Stripe → [nascosto].
     */
    public static function maschera(string $t): string
    {
        if ($t === '') return '';
        // Segreti: chiavi Stripe e AWS, token lunghi, «password=…», «token: …».
        $t = preg_replace('/\b(sk|rk|pk)_(live|test)_[A-Za-z0-9]{6,}/', '[nascosto]', $t);
        $t = preg_replace('/\bwhsec_[A-Za-z0-9+\/=]{6,}/', '[nascosto]', $t);
        $t = preg_replace('/\b(AKIA|ASIA)[A-Z0-9]{12,}\b/', '[nascosto]', $t);
        $t = preg_replace('/((?:password|passwd|pass|pwd|token|secret|segreto|api_key|apikey)["\']?\s*[:=]\s*["\']?)[^\s"\',&}]+/i', '$1[nascosto]', $t);
        $t = preg_replace('#(/(?:verifica|password/nuova|email/stop|parere|g)/)[A-Za-z0-9_-]{24,}#', '$1[nascosto]', $t);
        $t = preg_replace('/\b[a-f0-9]{40,}\b/i', '[nascosto]', $t);
        // Email: la prima lettera e il dominio.
        $t = preg_replace_callback('/\b([A-Za-z0-9._%+-])[A-Za-z0-9._%+-]*@([A-Za-z0-9.-]+\.[A-Za-z]{2,})\b/', fn($m) => $m[1] . '***@' . $m[2], $t);
        // IP v6 e v4.
        $t = preg_replace_callback('/\b(?:[0-9a-f]{1,4}:){2,7}[0-9a-f]{0,4}\b/i', fn($m) => filter_var($m[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? self::mascheraIp($m[0]) : $m[0], $t);
        $t = preg_replace_callback('/\b(?:\d{1,3}\.){3}\d{1,3}\b/', fn($m) => filter_var($m[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? self::mascheraIp($m[0]) : $m[0], $t);
        // Telefoni: 9 o più cifre (con +, spazi, punti o trattini), restano le ultime 3.
        $t = preg_replace_callback('/(?<![\w.:\/-])\+?\d[\d .-]{7,}\d(?![\w.:\/-])/', function ($m) {
            $cifre = preg_replace('/\D/', '', $m[0]);
            return strlen($cifre) >= 9 && strlen($cifre) <= 15 ? '***' . substr($cifre, -3) : $m[0];
        }, $t);
        return (string) $t;
    }

    /** maschera() su tutti i testi di un contesto (anche annidato); le chiavi sensibili si nascondono intere. */
    public static function mascheraTutto(array $c): array
    {
        foreach ($c as $k => $v) {
            if (is_string($k) && preg_match('/^(password|pass|pwd|token|secret|segreto|api_key|authorization|cookie)$/i', $k)) { $c[$k] = '[nascosto]'; continue; }
            if (is_array($v)) $c[$k] = self::mascheraTutto($v);
            elseif (is_string($v)) $c[$k] = self::maschera($v);
        }
        return $c;
    }

    private static function write(string $level, string $message, array $context): string
    {
        $ref = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        $riga = json_encode(['t' => Support::now(), 'lvl' => $level, 'ref' => $ref, 'msg' => self::maschera($message)]
                            + ($context ? ['ctx' => self::mascheraTutto($context)] : []),
                            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        @file_put_contents(self::file('app'), $riga . "\n", FILE_APPEND | LOCK_EX);
        return $ref;
    }
}
