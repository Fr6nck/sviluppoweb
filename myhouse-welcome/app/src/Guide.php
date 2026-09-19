<?php
namespace MHW;

/**
 * La guida pubblica non legge mai le tabelle di lavoro: legge un'istantanea
 * congelata al momento della pubblicazione. Così l'host può modificare in pace
 * senza che gli ospiti vedano mezze frasi, e ogni versione resta consultabile.
 */
final class Guide
{
    public static function publish(int $propertyId): int
    {
        return Db::tx(function () use ($propertyId) {
            $p = Db::one('SELECT * FROM properties WHERE id = ?', [$propertyId]);
            if (!$p) throw new \RuntimeException('Struttura inesistente.');

            $locales = array_column(
                Db::all('SELECT locale FROM property_locales WHERE property_id = ?', [$propertyId]), 'locale');
            if (!$locales) $locales = [$p['default_locale']];

            $sections = [];
            foreach (Db::all('SELECT * FROM sections WHERE property_id = ? ORDER BY position, id', [$propertyId]) as $s) {
                $tr = [];
                foreach (Db::all('SELECT * FROM section_translations WHERE section_id = ?', [$s['id']]) as $t) {
                    if (!in_array($t['locale'], $locales, true)) continue;
                    $tr[$t['locale']] = ['title' => $t['title'], 'body' => $t['body']];
                }
                $places = [];
                foreach (Db::all('SELECT * FROM places WHERE section_id = ? ORDER BY position, id', [$s['id']]) as $pl) {
                    $places[] = [
                        'name' => $pl['name'], 'category' => $pl['category'], 'distance' => $pl['distance'],
                        'note' => $pl['note'], 'badge' => $pl['badge'], 'badge_tone' => $pl['badge_tone'],
                        'image' => Media::url($pl['media_id'] ? (int) $pl['media_id'] : null),
                        'image_alt' => Media::alt($pl['media_id'] ? (int) $pl['media_id'] : null),
                    ];
                }
                $sections[] = [
                    'id' => (int) $s['id'], 'kind' => $s['kind'], 'icon' => $s['icon'], 'color' => $s['color'],
                    'wifi_ssid' => $s['wifi_ssid'], 'wifi_pass' => $s['wifi_pass'], 'door_code' => $s['door_code'],
                    'image' => Media::url($s['media_id'] ? (int) $s['media_id'] : null),
                    'image_alt' => Media::alt($s['media_id'] ? (int) $s['media_id'] : null),
                    'tr' => $tr, 'places' => $places,
                ];
            }

            $snapshot = [
                'property' => [
                    'name' => $p['name'], 'slug' => $p['slug'], 'city' => $p['city'], 'region' => $p['region'],
                    'checkin_from' => $p['checkin_from'], 'checkout_by' => $p['checkout_by'],
                    'host_name' => $p['host_name'], 'host_phone' => $p['host_phone'],
                    'host_whatsapp' => $p['host_whatsapp'],
                    'cover' => Media::url($p['cover_media_id'] ? (int) $p['cover_media_id'] : null),
                    'cover_alt' => Media::alt($p['cover_media_id'] ? (int) $p['cover_media_id'] : null),
                    'default_locale' => $p['default_locale'],
                ],
                'locales' => $locales,
                'sections' => $sections,
                'published_at' => Support::now(),
            ];

            $next = (int) Db::val('SELECT COALESCE(MAX(version),0)+1 FROM guide_versions WHERE property_id = ?', [$propertyId], 1);
            Db::insert('guide_versions', [
                'property_id' => $propertyId, 'version' => $next,
                'snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
                'published_at' => Support::now(),
            ]);
            Db::update('properties', ['status' => 'published'], 'id = :pid', ['pid' => $propertyId]);
            return $next;
        });
    }

    public static function published(int $propertyId): ?array
    {
        $row = Db::one(
            'SELECT snapshot FROM guide_versions WHERE property_id = ? ORDER BY version DESC', [$propertyId]);
        return $row ? json_decode($row['snapshot'], true) : null;
    }

    public static function bySlug(string $slug): ?array
    {
        $p = Db::one('SELECT * FROM properties WHERE slug = ?', [$slug]);
        if (!$p) return null;
        $snap = self::published((int) $p['id']);
        return $snap ? ['property' => $p, 'snapshot' => $snap] : null;
    }

    /** Quante modifiche aspettano di essere pubblicate. */
    public static function pendingChanges(int $propertyId): int
    {
        $snap = self::published($propertyId);
        if (!$snap) return (int) Db::val('SELECT COUNT(*) FROM sections WHERE property_id = ?', [$propertyId], 0);
        $live = json_encode($snap['sections'] ?? []);
        $now  = self::publishPreviewSections($propertyId);
        return $live === json_encode($now) ? 0 : 1;
    }

    private static function publishPreviewSections(int $propertyId): array
    {
        $out = [];
        foreach (Db::all('SELECT * FROM sections WHERE property_id = ? ORDER BY position, id', [$propertyId]) as $s) {
            $tr = [];
            foreach (Db::all('SELECT * FROM section_translations WHERE section_id = ?', [$s['id']]) as $t)
                $tr[$t['locale']] = ['title' => $t['title'], 'body' => $t['body']];
            $out[] = ['id' => (int) $s['id'], 'tr' => $tr];
        }
        return $out;
    }

    public static function track(int $propertyId, string $kind, ?int $sectionId = null, string $locale = ''): void
    {
        Db::insert('analytics_events', [
            'property_id' => $propertyId, 'section_id' => $sectionId, 'locale' => $locale,
            'kind' => $kind, 'day' => Support::today(), 'created_at' => Support::now(),
        ]);
    }
}
