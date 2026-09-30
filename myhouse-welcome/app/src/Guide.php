<?php
namespace MHW;

/**
 * La guida pubblica non legge mai le tabelle di lavoro: legge un'istantanea
 * congelata al momento della pubblicazione. Così l'host può modificare in pace
 * senza che gli ospiti vedano mezze frasi, e ogni versione resta consultabile.
 *
 * Formato 2 (questa versione): i media sono ID, non URL — su S3 gli URL
 * firmati scadono, quindi si calcolano al momento di mostrare la pagina.
 * Le istantanee pubblicate prima (formato 1) si leggono lo stesso: normalize()
 * le porta al formato nuovo in memoria, senza riscriverle.
 */
final class Guide
{
    public const FORMAT = 2;

    // ------------------------------------------------------------ costruzione

    public static function build(int $propertyId): array
    {
        $p = Db::one('SELECT * FROM properties WHERE id = ?', [$propertyId]);
        if (!$p) throw new \RuntimeException('Struttura inesistente.');

        $acc = (int) $p['account_id'];
        $consentite = Entitlements::allowedLocales($acc);
        $locales = array_values(array_intersect(
            array_column(Db::all('SELECT locale FROM property_locales WHERE property_id = ?', [$propertyId]), 'locale'),
            $consentite));
        if (!in_array($p['default_locale'], $locales, true)) array_unshift($locales, $p['default_locale']);
        $foto = Entitlements::can($acc, 'photos');
        $pdf = Entitlements::can($acc, 'pdf');

        $sections = [];
        foreach (Db::all('SELECT * FROM sections WHERE property_id = ? AND is_active = 1 ORDER BY is_core DESC, position, id', [$propertyId]) as $s) {
            $tr = [];
            foreach (Db::all('SELECT * FROM section_translations WHERE section_id = ?', [$s['id']]) as $t) {
                if (!in_array($t['locale'], $locales, true)) continue;
                $tr[$t['locale']] = ['title' => $t['title'], 'data' => json_decode((string) $t['data'], true) ?: []];
            }
            $places = [];
            if (SectionCatalog::hasPlaces($s['kind'])) {
                foreach (Db::all('SELECT * FROM places WHERE section_id = ? ORDER BY position, id', [$s['id']]) as $pl) {
                    $ptr = [];
                    foreach (Db::all('SELECT * FROM place_translations WHERE place_id = ?', [$pl['id']]) as $pt) {
                        if (!in_array($pt['locale'], $locales, true)) continue;
                        $ptr[$pt['locale']] = ['category' => $pt['category'], 'description' => $pt['description'],
                                               'note' => $pt['note'], 'badge' => $pt['badge']];
                    }
                    $places[] = [
                        'id' => (int) $pl['id'], 'name' => $pl['name'], 'address' => $pl['address'],
                        'maps_url' => $pl['maps_url'], 'phone' => $pl['phone'], 'website' => $pl['website'],
                        'booking_url' => $pl['booking_url'], 'walk_minutes' => (int) $pl['walk_minutes'],
                        'drive_minutes' => (int) $pl['drive_minutes'], 'badge_tone' => $pl['badge_tone'],
                        'image_id' => $foto && $pl['media_id'] ? (int) $pl['media_id'] : null,
                        'tr' => $ptr,
                    ];
                }
            }
            $sections[] = [
                'id' => (int) $s['id'], 'kind' => $s['kind'], 'is_core' => (int) $s['is_core'],
                'data' => json_decode((string) $s['data'], true) ?: [],
                'image_id' => $foto && $s['media_id'] ? (int) $s['media_id'] : null,
                'pdf_id' => $pdf && $s['pdf_media_id'] ? (int) $s['pdf_media_id'] : null,
                'tr' => $tr, 'places' => $places,
            ];
        }

        return [
            'format' => self::FORMAT,
            'property' => [
                'id' => (int) $p['id'], 'name' => $p['name'], 'slug' => $p['slug'], 'city' => $p['city'], 'region' => $p['region'],
                'checkin_from' => $p['checkin_from'], 'checkout_by' => $p['checkout_by'],
                'host_name' => $p['host_name'], 'host_phone' => $p['host_phone'], 'host_whatsapp' => $p['host_whatsapp'],
                'cover_id' => Entitlements::can($acc, 'cover') && $p['cover_media_id'] ? (int) $p['cover_media_id'] : null,
                'logo_id' => Entitlements::can($acc, 'logo') && $p['logo_media_id'] ? (int) $p['logo_media_id'] : null,
                'profile_id' => Entitlements::can($acc, 'profile_image') && $p['profile_media_id'] ? (int) $p['profile_media_id'] : null,
                'palette' => Palette::exists((string) $p['palette']) ? $p['palette'] : Palette::DEFAULT,
                'text_tone' => in_array($p['text_tone'], Palette::tones((string) $p['palette']), true) ? $p['text_tone'] : 'scuro',
                'is_demo' => (int) $p['is_demo'],
                'default_locale' => $p['default_locale'],
            ],
            'locales' => $locales,
            'sections' => $sections,
            'published_at' => Support::now(),
        ];
    }

    /**
     * Pubblica una nuova versione. Non controlla il pagamento: chi la chiama
     * (la rotta di pubblicazione, il webhook) lo ha già fatto.
     */
    public static function publish(int $propertyId): int
    {
        return Db::tx(function () use ($propertyId) {
            $snapshot = self::build($propertyId);
            $next = (int) Db::val('SELECT COALESCE(MAX(version),0)+1 FROM guide_versions WHERE property_id = ?', [$propertyId], 1);
            Db::insert('guide_versions', [
                'property_id' => $propertyId, 'version' => $next,
                'snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
                'published_at' => Support::now(),
            ]);
            Db::update('properties', ['status' => 'published', 'published_at' => Support::now()], 'id = :pid', ['pid' => $propertyId]);
            return $next;
        });
    }

    // ---------------------------------------------------------------- lettura

    public static function published(int $propertyId): ?array
    {
        $row = Db::one('SELECT snapshot FROM guide_versions WHERE property_id = ? ORDER BY version DESC', [$propertyId]);
        return $row ? self::normalize(json_decode($row['snapshot'], true) ?: []) : null;
    }

    /** @return array{property:array,snapshot:array,online:bool}|null */
    public static function bySlug(string $slug): ?array
    {
        $p = Db::one('SELECT * FROM properties WHERE slug = ?', [$slug]);
        if (!$p) return null;
        $snap = self::published((int) $p['id']);
        if (!$snap) return null;
        return ['property' => $p, 'snapshot' => $snap, 'online' => Subscriptions::propertyOnline($p)];
    }

    /** Un'istantanea del formato 1 portata al formato 2, in memoria. */
    public static function normalize(array $s): array
    {
        if (($s['format'] ?? 1) >= 2) return $s;
        $par = fn(string $t) => array_values(array_filter(array_map('trim', preg_split('/\R{2,}/', $t) ?: [])));
        $p = $s['property'] ?? [];
        $p += ['id' => 0, 'palette' => Palette::DEFAULT, 'text_tone' => 'scuro', 'is_demo' => 0, 'logo_id' => null, 'profile_id' => null, 'cover_id' => null];
        $p['cover_url'] = $p['cover'] ?? null;
        $out = [];
        foreach (($s['sections'] ?? []) as $i => $sec) {
            $kind = ['places' => 'eat', 'text' => 'info'][$sec['kind']] ?? $sec['kind'];
            if (!SectionCatalog::exists($kind)) $kind = 'info';
            $data = $kind === 'wifi' ? ['network' => $sec['wifi_ssid'] ?? '', 'password' => $sec['wifi_pass'] ?? ''] : [];
            $tr = [];
            foreach (($sec['tr'] ?? []) as $loc => $t) {
                $pp = $par((string) ($t['body'] ?? ''));
                $tr[$loc] = ['title' => $t['title'] ?? '', 'data' => match ($kind) {
                    'checkin' => ['checkin_steps' => $pp],
                    'wifi' => ['instructions' => implode("\n\n", $pp)],
                    'eat' => ['intro' => $pp[0] ?? '', 'host_note' => implode("\n\n", array_slice($pp, 1))],
                    default => ['items' => $pp],
                }];
            }
            $places = [];
            foreach (($sec['places'] ?? []) as $pl) {
                $places[] = ['id' => 0, 'name' => $pl['name'] ?? '', 'address' => '', 'maps_url' => '', 'phone' => '',
                             'website' => '', 'booking_url' => '', 'walk_minutes' => 0, 'drive_minutes' => 0,
                             'badge_tone' => $pl['badge_tone'] ?? 'pine', 'image_id' => null, 'image_url' => $pl['image'] ?? null,
                             'distance' => $pl['distance'] ?? '',
                             'tr' => [$p['default_locale'] ?? 'it' => ['category' => $pl['category'] ?? '', 'description' => '',
                                     'note' => $pl['note'] ?? '', 'badge' => $pl['badge'] ?? '']]];
            }
            $out[] = ['id' => (int) ($sec['id'] ?? $i), 'kind' => $kind, 'is_core' => $kind === 'checkin' ? 1 : 0,
                      'data' => $data, 'image_id' => null, 'image_url' => $sec['image'] ?? null, 'pdf_id' => null,
                      'tr' => $tr, 'places' => $places];
        }
        return ['format' => 2, 'property' => $p, 'locales' => $s['locales'] ?? ['it'], 'sections' => $out,
                'published_at' => $s['published_at'] ?? ''];
    }

    // ------------------------------------------------------------ per le viste

    /** L'URL di un'immagine dell'istantanea: dall'ID se c'è, altrimenti quello vecchio. */
    public static function img(array $obj, string $field = 'image'): ?string
    {
        $id = $obj[$field . '_id'] ?? null;
        if ($id) return Media::url((int) $id);
        return $obj[$field . '_url'] ?? null;
    }

    /** Titolo di una sezione nella lingua dell'ospite, con i ripieghi giusti. */
    public static function title(array $sec, string $loc, string $default): string
    {
        $t = trim((string) ($sec['tr'][$loc]['title'] ?? ''));
        if ($t !== '') return $t;
        // Senza traduzione: se l'host ha lasciato il titolo di catalogo, lo si
        // mostra nella lingua dell'ospite; se l'ha scritto lui, si mostra il suo.
        $suo = trim((string) ($sec['tr'][$default]['title'] ?? ''));
        if ($suo === '' || $suo === SectionCatalog::title($sec['kind'], $default)) return SectionCatalog::title($sec['kind'], $loc);
        return $suo;
    }

    /** I campi tradotti di una sezione: la lingua dell'ospite, poi quella della struttura. */
    public static function tdata(array $sec, string $loc, string $default): array
    {
        $d = $sec['tr'][$loc]['data'] ?? null;
        if (is_array($d) && array_filter($d, fn($v) => is_array($v) ? $v : trim((string) $v) !== '')) return $d;
        return $sec['tr'][$default]['data'] ?? [];
    }

    public static function ptr(array $place, string $loc, string $default): array
    {
        $t = $place['tr'][$loc] ?? null;
        $base = $place['tr'][$default] ?? ['category' => '', 'description' => '', 'note' => '', 'badge' => ''];
        if (!$t) return $base;
        foreach ($base as $k => $v) if (trim((string) ($t[$k] ?? '')) === '') $t[$k] = $v;
        return $t;
    }

    // -------------------------------------------------------------- controlli

    /** Le modifiche non ancora pubblicate. 0 se la bozza coincide con la versione online. */
    public static function pendingChanges(int $propertyId): int
    {
        $snap = self::published($propertyId);
        if (!$snap) return 1;
        $ora = self::build($propertyId);
        unset($snap['published_at'], $ora['published_at']);
        return json_encode($snap['sections']) === json_encode($ora['sections'])
            && json_encode($snap['property']) === json_encode($ora['property'])
            && $snap['locales'] === $ora['locales'] ? 0 : 1;
    }

    /**
     * Cosa manca, o cosa supera il piano, prima di poter pubblicare.
     * @return string[]
     */
    public static function problems(int $accountId, int $propertyId): array
    {
        $out = Entitlements::violations($accountId, $propertyId);
        $p = Db::one('SELECT * FROM properties WHERE id = ?', [$propertyId]);
        if (trim((string) $p['name']) === '') $out[] = 'Manca il nome della struttura.';
        if (!empty($p['archived_at'])) $out[] = 'La struttura è archiviata: riattivala dalle tue guide per pubblicarla.';
        $core = Db::one('SELECT * FROM sections WHERE property_id = ? AND is_core = 1', [$propertyId]);
        $t = $core ? Db::one('SELECT data FROM section_translations WHERE section_id = ? AND locale = ?', [$core['id'], $p['default_locale']]) : null;
        $d = json_decode((string) ($t['data'] ?? ''), true) ?: [];
        if (empty($d['checkin_steps']) && trim((string) ($d['checkin_note'] ?? '')) === '') {
            $out[] = 'Scrivi almeno un passaggio in Check-in & Check-out: è la prima cosa che l\'ospite cerca.';
        }
        return $out;
    }

    // ------------------------------------------------------------- statistiche

    /**
     * Un evento anonimo: nessun IP, nessun cookie, nessuna impronta del
     * dispositivo. Solo cosa è stato aperto, in che lingua, e da dove (qr/link).
     */
    public static function track(int $propertyId, string $kind, ?int $sectionId = null, string $locale = '', string $source = ''): void
    {
        if (!in_array($kind, ['guide_view', 'qr_open', 'section_view', 'language_selected'], true)) return;
        try {
            Db::insert('analytics_events', [
                'property_id' => $propertyId, 'section_id' => $sectionId, 'locale' => substr($locale, 0, 5),
                'kind' => $kind, 'source' => substr($source, 0, 20), 'day' => Support::today(), 'created_at' => Support::now(),
            ]);
        } catch (\Throwable $e) { Log::exception($e, 'Guide::track'); }
    }
}
