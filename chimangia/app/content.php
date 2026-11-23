<?php
declare(strict_types=1);

defined('CM_ROOT') || exit;

require_once CM_APP . '/defaults.php';

/**
 * Aree di contenuto. Ogni area ha una bozza e una versione pubblicata.
 * 'role' indica chi può modificarla: editor = Redattore e Amministratore.
 */
function content_areas(): array
{
    return [
        'header'        => ['label' => 'Intestazione', 'page' => 'sezione.php?s=header'],
        'hero'          => ['label' => 'Apertura', 'page' => 'sezione.php?s=hero'],
        'problema'      => ['label' => 'Il problema', 'page' => 'sezione.php?s=problema'],
        'come_funziona' => ['label' => 'Come funziona', 'page' => 'sezione.php?s=come_funziona'],
        'giornata'      => ['label' => 'Una giornata', 'page' => 'sezione.php?s=giornata'],
        'funzioni'      => ['label' => 'Funzioni', 'page' => 'sezione.php?s=funzioni'],
        'per_chi'       => ['label' => 'Per chi è', 'page' => 'sezione.php?s=per_chi'],
        'prezzi'        => ['label' => 'Prezzi', 'page' => 'prezzi.php'],
        'faq'           => ['label' => 'Domande frequenti', 'page' => 'faq.php'],
        'contatti'      => ['label' => 'Contatti (sezione)', 'page' => 'sezione.php?s=contatti'],
        'azienda'       => ['label' => 'Dati aziendali', 'page' => 'azienda.php'],
        'seo'           => ['label' => 'SEO', 'page' => 'seo.php'],
        'privacy'       => ['label' => 'Pagina Privacy', 'page' => 'legali.php?p=privacy'],
        'cookie'        => ['label' => 'Pagina Cookie', 'page' => 'legali.php?p=cookie'],
    ];
}

/** Inserisce i contenuti iniziali per le aree mancanti (installazione e aggiornamenti). */
function content_seed(?int $userId = null): void
{
    $now = now();
    foreach (default_content() as $area => $data) {
        $json = content_encode($data);
        q(
            'INSERT IGNORE INTO ' . t('content') . ' (area, draft, published, draft_updated_at, draft_updated_by, published_at, published_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$area, $json, $json, $now, $userId, $now, $userId]
        );
    }
}

function content_encode(array $data): string
{
    return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}

/**
 * Restituisce tutte le aree in versione 'published' o 'draft',
 * completate con i valori predefiniti per le chiavi mancanti.
 */
function content_all(string $which = 'published'): array
{
    $col = $which === 'draft' ? 'draft' : 'published';
    $rows = q('SELECT area, ' . $col . ' AS data FROM ' . t('content'))->fetchAll();
    $stored = [];
    foreach ($rows as $r) {
        $d = json_decode((string) $r['data'], true);
        $stored[$r['area']] = is_array($d) ? $d : [];
    }
    $out = [];
    foreach (default_content() as $area => $def) {
        $out[$area] = array_replace($def, $stored[$area] ?? []);
    }
    return $out;
}

function content_get(string $area, string $which = 'draft'): array
{
    $col = $which === 'draft' ? 'draft' : 'published';
    $raw = q_value('SELECT ' . $col . ' FROM ' . t('content') . ' WHERE area = ?', [$area]);
    $d = is_string($raw) ? json_decode($raw, true) : null;
    $def = default_content()[$area] ?? [];
    return array_replace($def, is_array($d) ? $d : []);
}

function content_row(string $area): ?array
{
    return q_one('SELECT * FROM ' . t('content') . ' WHERE area = ?', [$area]);
}

function content_save_draft(string $area, array $data, int $userId): void
{
    q(
        'INSERT INTO ' . t('content') . ' (area, draft, published, draft_updated_at, draft_updated_by)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE draft = VALUES(draft), draft_updated_at = VALUES(draft_updated_at), draft_updated_by = VALUES(draft_updated_by)',
        [$area, content_encode($data), content_encode(default_content()[$area] ?? []), now(), $userId]
    );
}

/** Aree con bozza diversa dalla versione pubblicata. */
function content_unpublished_areas(): array
{
    $out = [];
    foreach (q('SELECT area, draft, published FROM ' . t('content'))->fetchAll() as $r) {
        if (json_decode((string) $r['draft'], true) != json_decode((string) $r['published'], true)) {
            $out[] = $r['area'];
        }
    }
    return $out;
}

/**
 * Pubblica le aree indicate (o tutte se null), salva una versione completa
 * nello storico, rigenera la cache HTML, sitemap e robots.
 */
function content_publish(?array $areas, int $userId, string $note = ''): int
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $now = now();
        if ($areas === null) {
            q('UPDATE ' . t('content') . ' SET published = draft, published_at = ?, published_by = ?', [$now, $userId]);
        } else {
            foreach ($areas as $area) {
                q('UPDATE ' . t('content') . ' SET published = draft, published_at = ?, published_by = ? WHERE area = ?', [$now, $userId, $area]);
            }
        }
        $snapshot = content_all('published');
        q(
            'INSERT INTO ' . t('versions') . ' (user_id, note, data, created_at) VALUES (?, ?, ?, ?)',
            [$userId, mb_substr($note, 0, 255), content_encode($snapshot), $now]
        );
        $versionId = (int) $pdo->lastInsertId();
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    setting_set('last_publish_at', $now);
    if (function_exists('site_rebuild')) {
        site_rebuild();
    }
    return $versionId;
}

/** Ripristino: la versione scelta diventa la nuova bozza di tutte le aree. */
function content_restore_to_draft(int $versionId, int $userId): bool
{
    $raw = q_value('SELECT data FROM ' . t('versions') . ' WHERE id = ?', [$versionId]);
    $data = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($data)) {
        return false;
    }
    foreach (default_content() as $area => $def) {
        if (isset($data[$area]) && is_array($data[$area])) {
            content_save_draft($area, array_replace($def, $data[$area]), $userId);
        }
    }
    return true;
}

/* ---------- Offerta di lancio e segnaposto nei testi ---------- */

/** L'offerta è attiva e non scaduta? */
function offer_is_active(array $prezzi, ?int $today = null): bool
{
    if (empty($prezzi['offer_active'])) {
        return false;
    }
    $end = trim((string) ($prezzi['offer_end'] ?? ''));
    if ($end !== '') {
        $endTs = strtotime($end . ' 23:59:59');
        if ($endTs !== false && ($today ?? time()) > $endTs) {
            return false;
        }
    }
    return true;
}

function visible_packages(array $prezzi): array
{
    return array_values(array_filter($prezzi['packages'] ?? [], static fn ($p) => !empty($p['visible'])));
}

/** Valori dei segnaposto {sconto} e {prezzo_da}. */
function content_tokens(array $all): array
{
    $prezzi = $all['prezzi'] ?? [];
    $offer = offer_is_active($prezzi);
    $min = null;
    foreach (visible_packages($prezzi) as $p) {
        $v = (float) ($offer ? ($p['monthly_launch'] ?? 0) : ($p['monthly_full'] ?? 0));
        if ($v > 0 && ($min === null || $v < $min)) {
            $min = $v;
        }
    }
    $pct = (float) ($prezzi['offer_percent'] ?? 0);
    return [
        '{sconto}' => rtrim(rtrim(number_format($pct, 1, ',', ''), '0'), ',') . '%',
        '{prezzo_da}' => $min !== null ? euro($min) : '',
    ];
}

function apply_tokens(string $text, array $tokens): string
{
    return strtr($text, $tokens);
}
