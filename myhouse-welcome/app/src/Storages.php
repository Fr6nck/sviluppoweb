<?php
namespace MHW;

final class Storages
{
    private static ?Storage $s = null;

    public static function current(): Storage
    {
        if (self::$s) return self::$s;
        $cfg = Config::get('storage');
        return self::$s = ($cfg['driver'] ?? 'local') === 's3' ? new S3Storage($cfg['s3']) : new LocalStorage($cfg['local_dir']);
    }

    /** Per una riga di media già salvata: il driver con cui è stata scritta. */
    public static function for(string $driver): Storage
    {
        $cfg = Config::get('storage');
        return $driver === 's3' ? new S3Storage($cfg['s3']) : new LocalStorage($cfg['local_dir']);
    }

    public static function newKey(int $accountId, ?int $propertyId, string $ext): string
    {
        return 'a' . $accountId . '/p' . ($propertyId ?? 0) . '/' . gmdate('Y') . '/' . bin2hex(random_bytes(16)) . '.' . $ext;
    }

    public static function reset(): void { self::$s = null; }
}
