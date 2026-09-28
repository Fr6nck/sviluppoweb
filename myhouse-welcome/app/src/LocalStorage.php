<?php
namespace MHW;

final class LocalStorage implements Storage
{
    public function __construct(private string $dir) {}

    public function name(): string { return 'local'; }

    /** Sul disco la chiave diventa un nome piatto: /media/{file} ha un segmento solo. */
    public static function fileName(string $key): string { return str_replace('/', '-', $key); }

    public function put(string $key, string $bytes, string $mime, string $disposition = ''): void
    {
        if (!is_dir($this->dir)) mkdir($this->dir, 0775, true);
        if (file_put_contents($this->dir . '/' . self::fileName($key), $bytes, LOCK_EX) === false) {
            throw new \RuntimeException('Scrittura del file non riuscita.');
        }
    }

    public function delete(string $key): void { @unlink($this->dir . '/' . self::fileName($key)); }

    public function url(string $key): string
    {
        return Support::url(Config::get('uploads_url') . '/' . self::fileName($key));
    }
}
