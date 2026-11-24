<?php
declare(strict_types=1);

defined('CM_ROOT') || exit;

/**
 * Struttura del database. Ogni istruzione usa CREATE TABLE IF NOT EXISTS,
 * quindi può essere rieseguita in sicurezza durante un aggiornamento.
 *
 * @return list<string>
 */
function schema_statements(string $prefix): array
{
    $p = preg_replace('/[^a-z0-9_]/i', '', $prefix);
    $opt = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    return [
        "CREATE TABLE IF NOT EXISTS `{$p}users` (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            email VARCHAR(190) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            role VARCHAR(20) NOT NULL DEFAULT 'editor',
            active TINYINT(1) NOT NULL DEFAULT 1,
            last_login_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uq_email (email)
        ) $opt",

        "CREATE TABLE IF NOT EXISTS `{$p}login_attempts` (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(190) NOT NULL,
            ip_hash CHAR(64) NOT NULL,
            success TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            KEY idx_email (email, created_at),
            KEY idx_ip (ip_hash, created_at)
        ) $opt",

        "CREATE TABLE IF NOT EXISTS `{$p}password_resets` (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            token_hash CHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            UNIQUE KEY uq_token (token_hash),
            KEY idx_user (user_id)
        ) $opt",

        "CREATE TABLE IF NOT EXISTS `{$p}content` (
            area VARCHAR(40) NOT NULL PRIMARY KEY,
            draft LONGTEXT NOT NULL,
            published LONGTEXT NOT NULL,
            draft_updated_at DATETIME NULL,
            draft_updated_by INT UNSIGNED NULL,
            published_at DATETIME NULL,
            published_by INT UNSIGNED NULL
        ) $opt",

        "CREATE TABLE IF NOT EXISTS `{$p}versions` (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NULL,
            note VARCHAR(255) NOT NULL DEFAULT '',
            data LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL,
            KEY idx_created (created_at)
        ) $opt",

        "CREATE TABLE IF NOT EXISTS `{$p}settings` (
            name VARCHAR(80) NOT NULL PRIMARY KEY,
            value LONGTEXT NOT NULL
        ) $opt",

        "CREATE TABLE IF NOT EXISTS `{$p}leads` (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            business VARCHAR(160) NOT NULL DEFAULT '',
            email VARCHAR(190) NOT NULL,
            phone VARCHAR(40) NOT NULL DEFAULT '',
            clients VARCHAR(40) NOT NULL DEFAULT '',
            message TEXT NOT NULL,
            consent_at DATETIME NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'nuova',
            notes TEXT NOT NULL,
            ip_hash CHAR(64) NOT NULL DEFAULT '',
            user_agent VARCHAR(255) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            status_changed_at DATETIME NOT NULL,
            KEY idx_status (status, created_at),
            KEY idx_created (created_at)
        ) $opt",

        "CREATE TABLE IF NOT EXISTS `{$p}lead_history` (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            lead_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NULL,
            from_status VARCHAR(20) NOT NULL DEFAULT '',
            to_status VARCHAR(20) NOT NULL DEFAULT '',
            note VARCHAR(500) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL,
            KEY idx_lead (lead_id, created_at)
        ) $opt",

        "CREATE TABLE IF NOT EXISTS `{$p}contact_rate` (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            ip_hash CHAR(64) NOT NULL,
            created_at DATETIME NOT NULL,
            KEY idx_ip (ip_hash, created_at)
        ) $opt",

        "CREATE TABLE IF NOT EXISTS `{$p}media` (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            file VARCHAR(120) NOT NULL,
            webp VARCHAR(120) NOT NULL DEFAULT '',
            original_name VARCHAR(190) NOT NULL DEFAULT '',
            mime VARCHAR(40) NOT NULL,
            width INT UNSIGNED NOT NULL DEFAULT 0,
            height INT UNSIGNED NOT NULL DEFAULT 0,
            size INT UNSIGNED NOT NULL DEFAULT 0,
            alt VARCHAR(255) NOT NULL DEFAULT '',
            user_id INT UNSIGNED NULL,
            created_at DATETIME NOT NULL
        ) $opt",

        "CREATE TABLE IF NOT EXISTS `{$p}audit_log` (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NULL,
            action VARCHAR(60) NOT NULL,
            details TEXT NOT NULL,
            ip_hash CHAR(64) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL,
            KEY idx_created (created_at),
            KEY idx_action (action)
        ) $opt",
    ];
}

/**
 * Aggiornamento senza perdita di dati: crea le tabelle mancanti, aggiunge le
 * aree di contenuto e le impostazioni nuove. Parte da sola quando cambia CM_VERSION.
 */
function cm_migrate(): void
{
    $prefix = (string) (cfg('db', [])['prefix'] ?? 'cm_');
    foreach (schema_statements($prefix) as $sql) {
        db()->exec($sql);
    }
    content_seed(null);
    $from = (string) setting('db_version', '1.0.0');
    if (version_compare($from, '1.1.0', '<')) {
        migrate_day_photos();
    }
    if (version_compare($from, '1.2.0', '<')) {
        migrate_hero_full();
    }
    foreach (default_settings() as $name => $value) {
        q('INSERT IGNORE INTO ' . t('settings') . ' (name, value) VALUES (?, ?)', [$name, json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
    }
    setting_set('db_version', CM_VERSION);
    if (function_exists('site_rebuild')) {
        site_rebuild();
    }
}

/**
 * 1.1.0: i blocchi di "Una giornata" che usano ancora le illustrazioni iniziali
 * passano alle fotografie. I blocchi già personalizzati non vengono toccati.
 */
function migrate_day_photos(): void
{
    $map = [
        'menu-telefono' => ['foto-sera', 'Illustrazione: il menù del giorno pubblicato dal telefono', 'Il titolare di una trattoria, a sala chiusa, pubblica il menù dal telefono'],
        'tavolata' => ['foto-pranzo', 'Illustrazione: una tavolata con i piatti prenotati', 'La sala di una trattoria a pranzo, con lavoratori a tavola e un cameriere che serve la pasta'],
        'riepilogo' => ['foto-fine-mese', 'Illustrazione: il riepilogo mensile dei pasti', 'La gestrice di una trattoria guarda soddisfatta il riepilogo stampato, con il portatile aperto'],
    ];
    $row = q_one('SELECT draft, published FROM ' . t('content') . " WHERE area = 'giornata'");
    if (!$row) {
        return;
    }
    $out = [];
    foreach (['draft', 'published'] as $col) {
        $data = json_decode((string) $row[$col], true);
        if (is_array($data) && is_array($data['cards'] ?? null)) {
            foreach ($data['cards'] as &$card) {
                $k = (string) ($card['illustration'] ?? '');
                if (isset($map[$k]) && (int) ($card['image'] ?? 0) === 0) {
                    $card['illustration'] = $map[$k][0];
                    if (($card['alt'] ?? '') === $map[$k][1] || ($card['alt'] ?? '') === '') {
                        $card['alt'] = $map[$k][2];
                    }
                }
            }
            unset($card);
        }
        $out[$col] = is_array($data) ? content_encode($data) : (string) $row[$col];
    }
    q('UPDATE ' . t('content') . " SET draft = ?, published = ? WHERE area = 'giornata'", [$out['draft'], $out['published']]);
}

/**
 * 1.2.0: l'apertura passa allo slider a tutta larghezza. Se le fotografie sono
 * ancora quelle iniziali della 1.1 vengono sostituite con quelle panoramiche.
 */
function migrate_hero_full(): void
{
    $row = q_one('SELECT draft, published FROM ' . t('content') . " WHERE area = 'hero'");
    if (!$row) {
        return;
    }
    $out = [];
    foreach (['draft', 'published'] as $col) {
        $d = json_decode((string) $row[$col], true);
        if (is_array($d) && ($d['photo'] ?? '') === 'cucina' && ($d['photo2'] ?? '') === 'pranzo' && !isset($d['layout'])) {
            $d = array_merge($d, ['layout' => 'full', 'photo' => 'sala', 'photo_alt' => '', 'photo2' => 'sera', 'photo3' => 'fine-mese', 'photo4' => '']);
        }
        $out[$col] = is_array($d) ? content_encode($d) : (string) $row[$col];
    }
    q('UPDATE ' . t('content') . " SET draft = ?, published = ? WHERE area = 'hero'", [$out['draft'], $out['published']]);
}
