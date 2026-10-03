<?php
namespace MHW;

/**
 * I token delle email: verifica dell'indirizzo e recupero della password.
 * Nel database si conserva solo l'impronta SHA-256: chi legge il database non
 * può usarli. Valgono una volta sola e scadono.
 */
final class Tokens
{
    public const VERIFY = 'verify';
    public const RESET = 'reset';

    public static function issue(int $userId, string $purpose, int $ttlSeconds): string
    {
        // Un nuovo token dello stesso tipo annulla i precedenti non usati.
        Db::run('UPDATE email_tokens SET used_at = ? WHERE user_id = ? AND purpose = ? AND used_at IS NULL',
                [Support::now(), $userId, $purpose]);
        $raw = bin2hex(random_bytes(32));
        Db::insert('email_tokens', [
            'user_id' => $userId, 'purpose' => $purpose, 'token_hash' => hash('sha256', $raw),
            'expires_at' => gmdate('Y-m-d\TH:i:s\Z', time() + $ttlSeconds), 'created_at' => Support::now(),
        ]);
        return $raw;
    }

    /** Controlla senza consumare. @return int|null l'utente */
    public static function peek(string $raw, string $purpose): ?int
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $raw)) return null;
        $t = Db::one('SELECT * FROM email_tokens WHERE token_hash = ? AND purpose = ?', [hash('sha256', $raw), $purpose]);
        if (!$t || $t['used_at'] !== null || strtotime($t['expires_at']) < time()) return null;
        return (int) $t['user_id'];
    }

    /** Consuma il token. @return int|null l'utente */
    public static function consume(string $raw, string $purpose): ?int
    {
        $uid = self::peek($raw, $purpose);
        if ($uid === null) return null;
        // La condizione su used_at rende il consumo atomico: due richieste
        // contemporanee con lo stesso token non passano entrambe.
        $n = Db::run('UPDATE email_tokens SET used_at = ? WHERE token_hash = ? AND used_at IS NULL',
                     [Support::now(), hash('sha256', $raw)]);
        return $n === 1 ? $uid : null;
    }
}
