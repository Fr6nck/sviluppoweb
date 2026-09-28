<?php
namespace MHW;

final class Auth
{
    /** Un'impersonazione dura al massimo un'ora, poi si torna amministratori da soli. */
    public const IMPERSONATION_TTL = 3600;

    public static function start(): void
    {
        if (session_status() !== PHP_SESSION_NONE) return;

        // Parecchi hosting condivisi hanno una cartella di sessione non
        // scrivibile: il cookie regge ma il CONTENUTO si perde a ogni
        // richiesta, e ogni modulo viene respinto come "sessione scaduta".
        // Ce la teniamo in casa, dove sappiamo di poter scrivere.
        $propria = MHW_APP . '/storage/sessions';
        if (!is_dir($propria)) @mkdir($propria, 0770, true);
        if (is_dir($propria) && is_writable($propria)) session_save_path($propria);

        // Un identificativo di sessione inventato da fuori viene rifiutato.
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_name('mhw_sess');
        session_set_cookie_params([
            'path' => Support::baseDir() . '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => self::httpsAttivo(),
        ]);
        session_start();
    }

    /** Dietro un proxy $_SERVER['HTTPS'] può mancare: guardiamo anche l'intestazione inoltrata. */
    public static function httpsAttivo(): bool
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return true;
        if (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') return true;
        return ((int) ($_SERVER['SERVER_PORT'] ?? 0)) === 443;
    }

    public static function register(string $email, string $password, string $name): array
    {
        $email = mb_strtolower(trim($email));
        $name = trim($name);
        if ($name === '') throw new \RuntimeException('Scrivi il tuo nome.');
        if (mb_strlen($name) > 120) throw new \RuntimeException('Il nome è troppo lungo.');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new \RuntimeException('Questo indirizzo email non è valido.');
        if (mb_strlen($password) < 8) throw new \RuntimeException('La password deve avere almeno 8 caratteri.');
        if (mb_strlen($password) > 200) throw new \RuntimeException('La password è troppo lunga.');
        if (Db::one('SELECT id FROM users WHERE email = ?', [$email])) {
            throw new \RuntimeException('Esiste già un account con questa email. Accedi, oppure recupera la password.');
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

    /** Termini e privacy: la versione letta e il momento, per ciascuno. */
    public static function recordConsent(int $userId): void
    {
        $legal = Config::get('legal');
        Db::update('users', [
            'terms_version' => $legal['terms_version'], 'terms_accepted_at' => Support::now(),
            'privacy_version' => $legal['privacy_version'], 'privacy_accepted_at' => Support::now(),
        ], 'id = :uid', ['uid' => $userId]);
    }

    public static function sendVerification(int $userId): bool
    {
        $u = Db::one('SELECT * FROM users WHERE id = ?', [$userId]);
        if (!$u || $u['email_verified_at']) return false;
        $token = Tokens::issue($userId, Tokens::VERIFY, 48 * 3600);
        $link = Support::baseUrl() . '/verifica/' . $token;
        $nome = $u['name'] ?: 'ciao';
        return Mailer::send($u['email'], 'Conferma la tua email — MyHouse Welcome',
            "Ciao $nome,\n\nconferma il tuo indirizzo email aprendo questo link:\n\n$link\n\n"
            . "Il link vale 48 ore. Finché non confermi puoi preparare la guida, ma non pubblicarla.\n\n"
            . "Se non hai creato tu l'account, ignora questo messaggio.\n\nMyHouse Welcome");
    }

    public static function sendPasswordReset(string $email): void
    {
        $u = Db::one('SELECT * FROM users WHERE email = ?', [mb_strtolower(trim($email))]);
        // Che l'account esista o no, fuori non si vede nessuna differenza.
        if (!$u) return;
        $token = Tokens::issue((int) $u['id'], Tokens::RESET, 3600);
        $link = Support::baseUrl() . '/password/nuova/' . $token;
        Mailer::send($u['email'], 'Scegli una nuova password — MyHouse Welcome',
            "Ciao,\n\nqualcuno ha chiesto di cambiare la password del tuo account MyHouse Welcome.\n"
            . "Se sei stato tu, apri questo link entro un'ora:\n\n$link\n\n"
            . "Se non sei stato tu, ignora questo messaggio: la password resta quella di prima.\n\nMyHouse Welcome");
    }

    public static function isVerified(?array $user): bool
    {
        return $user !== null && !empty($user['email_verified_at']);
    }

    public static function attempt(string $email, string $password): bool
    {
        $u = Db::one('SELECT * FROM users WHERE email = ?', [mb_strtolower(trim($email))]);
        // password_verify su un hash finto: costo simile anche quando l'utente non esiste.
        $hash = $u['password_hash'] ?? '$2y$10$usqI0N8kKZ1xJ3l4b5c6ruJ8Q1sT0uV2wX3yZ4aB5cD6eF7gH8iJK';
        if (!password_verify($password, $hash) || !$u) return false;
        if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
            Db::update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], 'id = :uid', ['uid' => $u['id']]);
        }
        self::login((int) $u['id']);
        return true;
    }

    public static function login(int $userId): void
    {
        session_regenerate_id(true);
        $_SESSION['uid'] = $userId;
        unset($_SESSION['impersonator'], $_SESSION['impersonation_until']);
    }

    public static function logout(): void
    {
        $_SESSION = []; session_destroy(); session_start(); session_regenerate_id(true);
    }

    public static function user(): ?array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) return null;
        if (self::isImpersonating() && time() > (int) ($_SESSION['impersonation_until'] ?? 0)) {
            self::stopImpersonating('impersonate.expired');
        }
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
        if ($target['role'] === 'admin') { http_response_code(403); exit('Non si entra nell\'account di un altro amministratore.'); }
        Db::insert('audit_log', [
            'actor_user_id' => $admin['id'], 'target_user_id' => $targetUserId,
            'action' => 'impersonate.start', 'meta' => json_encode(['ip' => RateLimit::ip()]), 'created_at' => Support::now(),
        ]);
        session_regenerate_id(true);
        $_SESSION['uid'] = $targetUserId;
        $_SESSION['impersonator'] = (int) $admin['id'];
        $_SESSION['impersonation_until'] = time() + self::IMPERSONATION_TTL;
    }

    public static function stopImpersonating(string $motivo = 'impersonate.stop'): void
    {
        $adminId = $_SESSION['impersonator'] ?? null;
        if (!$adminId) return;
        Db::insert('audit_log', [
            'actor_user_id' => $adminId, 'target_user_id' => $_SESSION['uid'] ?? null,
            'action' => $motivo, 'meta' => '', 'created_at' => Support::now(),
        ]);
        session_regenerate_id(true);
        $_SESSION['uid'] = (int) $adminId;
        unset($_SESSION['impersonator'], $_SESSION['impersonation_until']);
    }

    public static function isImpersonating(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['impersonator']);
    }

    /** Chi sta agendo davvero: l'amministratore, se sta impersonando. */
    public static function actorId(): ?int
    {
        if (self::isImpersonating()) return (int) $_SESSION['impersonator'];
        return isset($_SESSION['uid']) ? (int) $_SESSION['uid'] : null;
    }

    public static function audit(string $action, ?int $targetUserId = null, array $meta = []): void
    {
        Db::insert('audit_log', [
            'actor_user_id' => self::actorId(), 'target_user_id' => $targetUserId,
            'action' => $action, 'meta' => $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : '',
            'created_at' => Support::now(),
        ]);
    }
}
