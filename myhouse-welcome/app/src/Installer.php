<?php
namespace MHW;

final class Installer
{
    public static function installed(): bool
    {
        try { Db::val('SELECT COUNT(*) FROM users'); return true; }
        catch (\Throwable) { return false; }
    }

    public static function install(string $adminEmail, string $adminPass): array
    {
        $sql = (string) file_get_contents(MHW_APP . '/migrations/001_schema.sql');
        // Le righe di commento vanno tolte DENTRO ogni blocco: scartare il blocco
        // intero perché comincia con "--" farebbe sparire la tabella che segue.
        $clean = implode("\n", array_filter(
            array_map(fn(string $l) => str_starts_with(trim($l), '--') ? '' : $l, explode("\n", $sql)),
            fn(string $l) => trim($l) !== ''
        ));
        $statements = array_filter(array_map('trim', explode(';', $clean)), fn($s) => $s !== '');

        // Una transazione: se un pezzo fallisce non resta un database a metà.
        Db::tx(function ($pdo) use ($statements) {
            foreach ($statements as $stmt) $pdo->exec($stmt);
        });
        self::seedCatalogue();
        $u = Auth::register($adminEmail, $adminPass, 'Amministrazione');
        Db::update('users', ['role' => 'admin'], 'id = :uid', ['uid' => $u['user_id']]);
        return $u;
    }

    /** Le feature, i tre pacchetti e la loro prima versione. */
    public static function seedCatalogue(): void
    {
        $features = [
            ['sections',   'Numero di sezioni',              'int',  '8'],
            ['locales',    'Lingue pubblicabili',            'int',  '1'],
            ['photos',     'Foto nelle sezioni',             'bool', '0'],
            ['places',     'Consigli sul posto',             'bool', '0'],
            ['properties', 'Strutture sullo stesso account', 'int',  '1'],
            ['branding',   'Colori e logo personalizzati',   'bool', '0'],
            ['analytics',  'Statistiche di lettura',         'bool', '0'],
        ];
        foreach ($features as [$code, $label, $kind, $def]) {
            if (!Db::one('SELECT id FROM features WHERE code = ?', [$code]))
                Db::insert('features', ['code' => $code, 'label' => $label, 'kind' => $kind, 'default_value' => $def]);
        }
        $fid = fn(string $c) => (int) Db::val('SELECT id FROM features WHERE code = ?', [$c]);

        $packages = [
            ['essential', 'Essential', 'Una casa, una lingua.',        0, 3900,
             ['sections' => '8',         'locales' => '1', 'photos' => '0', 'places' => '0', 'properties' => '1', 'branding' => '0', 'analytics' => '0']],
            ['plus',      'Plus',      'Quattro lingue, senza pensarci.', 1, 7900,
             ['sections' => 'unlimited', 'locales' => '4', 'photos' => '1', 'places' => '1', 'properties' => '1', 'branding' => '0', 'analytics' => '1']],
            ['pro',       'Pro',       'Più case, un marchio solo.',   2, 14900,
             ['sections' => 'unlimited', 'locales' => '4', 'photos' => '1', 'places' => '1', 'properties' => '10', 'branding' => '1', 'analytics' => '1']],
        ];
        foreach ($packages as [$code, $name, $tagline, $sort, $price, $feats]) {
            if (Db::one('SELECT id FROM packages WHERE code = ?', [$code])) continue;
            $pid = Db::insert('packages', ['code' => $code, 'name' => $name, 'tagline' => $tagline, 'sort' => $sort, 'active' => 1]);
            $vid = Db::insert('package_versions', [
                'package_id' => $pid, 'version' => 1, 'price_cents' => $price, 'currency' => 'EUR',
                'interval_unit' => 'year', 'is_current' => 1, 'sold_count' => 0, 'created_at' => Support::now(),
            ]);
            foreach ($feats as $fc => $val)
                Db::insert('package_features', ['package_version_id' => $vid, 'feature_id' => $fid($fc), 'value' => $val]);
        }
    }
}
