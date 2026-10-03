<?php
namespace MHW;

/**
 * Quante volte si può provare qualcosa in una finestra di tempo. Serve contro
 * chi prova le password a raffica o usa il recupero password per spedire
 * email a chiunque.
 *
 * L'indirizzo IP è REMOTE_ADDR e basta: le intestazioni X-Forwarded-For le
 * scrive chi fa la richiesta, e fidarsene renderebbe il limite aggirabile.
 */
final class RateLimit
{
    /** Registra un tentativo; false se il limite era già stato raggiunto. */
    public static function hit(string $bucket, int $max, int $windowSeconds): bool
    {
        $ora = time();
        if (random_int(1, 50) === 1) Db::run('DELETE FROM rate_limits WHERE created_at < ?', [$ora - 86400]);
        $n = (int) Db::val('SELECT COUNT(*) FROM rate_limits WHERE bucket = ? AND created_at > ?', [$bucket, $ora - $windowSeconds], 0);
        if ($n >= $max) return false;
        Db::insert('rate_limits', ['bucket' => $bucket, 'created_at' => $ora]);
        return true;
    }

    public static function clear(string $bucket): void
    {
        Db::run('DELETE FROM rate_limits WHERE bucket = ?', [$bucket]);
    }

    public static function ip(): string { return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'); }

    /** Una chiave che non conserva l'email in chiaro. */
    public static function key(string $what, string $value): string
    {
        return $what . ':' . substr(hash('sha256', mb_strtolower(trim($value))), 0, 32);
    }
}
