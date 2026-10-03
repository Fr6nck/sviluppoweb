<?php
namespace MHW;

/**
 * Le testimonianze della landing: nome, struttura, testo, foto, visibile sì/no.
 * Le scrive solo l'amministratore. Il progetto non ne contiene nessuna: una
 * testimonianza inventata sarebbe una pubblicità ingannevole.
 */
final class Testimonianze
{
    public static function disponibili(): bool { return Migrator::tableExists('testimonials'); }

    public static function tutte(): array
    {
        return self::disponibili() ? Db::all('SELECT * FROM testimonials ORDER BY position, id') : [];
    }

    /** Quelle da mostrare in landing, con l'URL della foto. */
    public static function visibili(): array
    {
        if (!self::disponibili()) return [];
        $out = [];
        foreach (Db::all('SELECT * FROM testimonials WHERE visible = 1 ORDER BY position, id') as $t) {
            $t['foto'] = self::foto($t);
            $out[] = $t;
        }
        return $out;
    }

    public static function foto(array $t): ?string
    {
        if ($t['photo_key'] === '') return null;
        try { return Storages::for($t['photo_storage'])->url($t['photo_key']); }
        catch (\Throwable $e) { Log::exception($e, 'Testimonianze::foto'); return null; }
    }

    public static function salva(array $in, ?array $file): int
    {
        $id = (int) ($in['id'] ?? 0);
        $prima = $id ? Db::one('SELECT * FROM testimonials WHERE id = ?', [$id]) : null;
        if ($id && !$prima) throw new NotFound('Testimonianza non trovata.');
        $dati = [
            'name' => mb_substr(trim((string) ($in['name'] ?? '')), 0, 120),
            'property_name' => mb_substr(trim((string) ($in['property_name'] ?? '')), 0, 160),
            'body' => mb_substr(trim((string) ($in['body'] ?? '')), 0, 800),
            'visible' => !empty($in['visible']) ? 1 : 0,
            'position' => max(0, min(999, (int) ($in['position'] ?? 0))),
        ];
        if ($dati['name'] === '' || $dati['body'] === '') throw new \RuntimeException('Servono almeno il nome e il testo.');
        if ($file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            [$driver, $key] = Media::storeFreeImage($file, 'testimonianze');
            $dati['photo_storage'] = $driver; $dati['photo_key'] = $key;
        } elseif (!empty($in['togli_foto'])) {
            $dati['photo_storage'] = ''; $dati['photo_key'] = '';
        }
        if ($prima && array_key_exists('photo_key', $dati) && $prima['photo_key'] !== '') self::cancellaFoto($prima);
        if ($prima) { Db::update('testimonials', $dati, 'id = :tid', ['tid' => $id]); return $id; }
        return Db::insert('testimonials', $dati + ['created_at' => Support::now()]);
    }

    public static function elimina(int $id): void
    {
        $t = Db::one('SELECT * FROM testimonials WHERE id = ?', [$id]);
        if (!$t) return;
        self::cancellaFoto($t);
        Db::run('DELETE FROM testimonials WHERE id = ?', [$id]);
    }

    private static function cancellaFoto(array $t): void
    {
        if ($t['photo_key'] === '') return;
        try { Storages::for($t['photo_storage'])->delete($t['photo_key']); } catch (\Throwable $e) { Log::exception($e, 'Testimonianze::foto'); }
    }
}
