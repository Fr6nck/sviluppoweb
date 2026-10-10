<?php
namespace MHW;

/**
 * Il registro tecnico. Quello che l'utente non deve vedere — nomi di classi,
 * percorsi, risposte di Stripe — finisce qui, in storage/logs/app.log, con un
 * codice che si può citare all'assistenza.
 */
final class Log
{
    public static function dir(): string
    {
        $d = MHW_APP . '/storage/logs';
        if (!is_dir($d)) @mkdir($d, 0770, true);
        return $d;
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

    private static function write(string $level, string $message, array $context): string
    {
        $ref = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        $riga = json_encode(['t' => Support::now(), 'lvl' => $level, 'ref' => $ref, 'msg' => $message] + ($context ? ['ctx' => $context] : []),
                            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        @file_put_contents(self::dir() . '/app.log', $riga . "\n", FILE_APPEND | LOCK_EX);
        return $ref;
    }
}
