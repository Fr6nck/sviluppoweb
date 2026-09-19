<?php
namespace MHW;

final class Auth
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'httponly' => true, 'samesite' => 'Lax',
                'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            ]);
            session_start();
        }
    }

    public static function register(string $email, string $password, string $name): array
    {
        $email = mb_strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new \RuntimeException('Indirizzo email non valido.');
        if (mb_strlen($password) < 8) throw new \RuntimeException('La password deve avere almeno 8 caratteri.');
        if (Db::one('SELECT id FROM users WHERE email = ?', [$email])) {
            throw new \RuntimeException('Esiste già un account con questa email.');
        }
        return Db::tx(function () use ($email, $password, $name) {
            $uid = Db::insert('users', [
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'name' => $name, 'role' => 'host', 'created_at' => Support::now(),
            ]);
            $aid = Db::insert('accounts', ['user_id' => $uid, 'name' => $name, 'created_at' => Support::now()]);
            return ['user_id' => $uid, 'account_id' => $aid];
        });
    }

    public static function attempt(string $email, string $password): bool
    {
        $u = Db::one('SELECT * FROM users WHERE email = ?', [mb_strtolower(trim($email))]);
        // password_verify su un hash finto: costo simile anche quando l'utente non esiste.
        $hash = $u['password_hash'] ?? '$2y$10$usqI0N8kKZ1xJ3l4b5c6ruJ8Q1sT0uV2wX3yZ4aB5cD6eF7gH8iJK';
        if (!password_verify($password, $hash) || !$u) return false;
        self::login((int) $u['id']);
        return true;
    }

    public static function login(int $userId): void
    {
        session_regenerate_id(true);
        $_SESSION['uid'] = $userId;
        unset($_SESSION['impersonator']);
    }

    public static function logout(): void
    {
        $_SESSION = []; session_destroy(); session_start(); session_regenerate_id(true);
    }

    public static function user(): ?array
    {
        $id = $_SESSION['uid'] ?? null;
        if (!$id) return null;
        return Db::one('SELECT * FROM users WHERE id = ?', [$id]);
    }

    public static function account(): ?array
    {
        $u = self::user(); if (!$u) return null;
        return Db::one('SELECT * FROM accounts WHERE user_id = ?', [$u['id']]);
    }

    public static function requireUser(): array
    {
        $u = self::user();
        if (!$u) Support::redirect('/accedi');
        return $u;
    }

    public static function requireAdmin(): array
    {
        $u = self::requireUser();
        if (($u['role'] ?? '') !== 'admin') { http_response_code(403); exit('Non autorizzato.'); }
        return $u;
    }

    /** L'admin entra senza mai vedere la password del cliente; resta tracciato. */
    public static function impersonate(int $targetUserId): void
    {
        $admin = self::requireAdmin();
        $target = Db::one('SELECT * FROM users WHERE id = ?', [$targetUserId]);
        if (!$target) { http_response_code(404); exit('Utente inesistente.'); }
        Db::insert('audit_log', [
            'actor_user_id' => $admin['id'], 'target_user_id' => $targetUserId,
            'action' => 'impersonate.start', 'meta' => '', 'created_at' => Support::now(),
        ]);
        session_regenerate_id(true);
        $_SESSION['uid'] = $targetUserId;
        $_SESSION['impersonator'] = (int) $admin['id'];
    }

    public static function stopImpersonating(): void
    {
        $adminId = $_SESSION['impersonator'] ?? null;
        if (!$adminId) return;
        Db::insert('audit_log', [
            'actor_user_id' => $adminId, 'target_user_id' => $_SESSION['uid'] ?? null,
            'action' => 'impersonate.stop', 'meta' => '', 'created_at' => Support::now(),
        ]);
        session_regenerate_id(true);
        $_SESSION['uid'] = (int) $adminId;
        unset($_SESSION['impersonator']);
    }

    public static function isImpersonating(): bool { return !empty($_SESSION['impersonator']); }
}
