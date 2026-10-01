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
 *
 * Formato 3: contatti duplicabili, dati della struttura (indirizzo, CIN…),
 * partenza come lista, più reti Wi-Fi. Le istantanee di formato 2 si
 * convertono al volo con Conversione, la stessa usata dalle migrazioni.
 */
final class Guide
{
    public const FORMAT = 3;

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
            // Se una migrazione vecchia pubblica prima che girino le conversioni (009, 010…),
            // l'istantanea esce comunque nel formato nuovo: la conversione è idempotente.
            $testi = array_map(fn($t) => $t['data'], $tr);
            [$datiSezione, $testi] = Conversione::sezione((string) $s['kind'], json_decode((string) $s['data'], true) ?: [], $testi);
            foreach ($testi as $l => $d) $tr[$l]['data'] = $d;
            $sections[] = [
                'id' => (int) $s['id'], 'kind' => $s['kind'], 'is_core' => (int) $s['is_core'],
                'data' => $datiSezione,
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
                'property_type' => (string) ($p['property_type'] ?? ''), 'address' => (string) ($p['address'] ?? ''),
                'postal_code' => (string) ($p['postal_code'] ?? ''), 'cin' => (string) ($p['cin'] ?? ''),
                // Una migrazione vecchia può pubblicare prima che esista la tabella dei contatti (la 008):
                // allora i contatti vengono dalle colonne di prima, come per le istantanee vecchie.
                'contacts' => Migrator::tableExists('property_contacts')
                    ? array_map(fn($c) => ['name' => $c['name'], 'role' => $c['role'], 'phone' => $c['phone'], 'whatsapp' => (int) $c['whatsapp']],
                        Db::all('SELECT * FROM property_contacts WHERE property_id = ? ORDER BY position, id', [$propertyId]))
                    : Conversione::contatti((string) $p['host_name'], (string) $p['host_phone'], (string) $p['host_whatsapp']),
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

    /** Un'istantanea dei formati precedenti portata al formato attuale, in memoria. */
    public static function normalize(array $s): array
    {
        $f = (int) ($s['format'] ?? 1);
        if ($f >= self::FORMAT) return $s;
        if ($f < 2) $s = self::daFormato1($s);
        return self::daFormato2($s);
    }

    /** Formato 2 → 3: contatti, dati della struttura, partenza a lista, più reti Wi-Fi. */
    private static function daFormato2(array $s): array
    {
        $p = $s['property'] ?? [];
        $p += ['property_type' => '', 'address' => '', 'postal_code' => '', 'cin' => ''];
        if (!isset($p['contacts'])) {
            $p['contacts'] = Conversione::contatti((string) ($p['host_name'] ?? ''), (string) ($p['host_phone'] ?? ''), (string) ($p['host_whatsapp'] ?? ''));
        }
        $s['property'] = $p;
        foreach (($s['sections'] ?? []) as $i => $sec) {
            $testi = [];
            foreach (($sec['tr'] ?? []) as $loc => $t) $testi[$loc] = $t['data'] ?? [];
            [$dati, $testi] = Conversione::sezione((string) $sec['kind'], $sec['data'] ?? [], $testi);
            $s['sections'][$i]['data'] = $dati;
            foreach ($testi as $loc => $d) $s['sections'][$i]['tr'][$loc]['data'] = $d;
        }
        $s['format'] = self::FORMAT;
        return $s;
    }

    /** Formato 1 → 2: i media erano URL, le sezioni testo libero. */
    private static function daFormato1(array $s): array
    {
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

    /** Le righe di un repeater nella lingua dell'ospite (ripiego sulla principale, riga per riga). */
    public static function rows(array $sec, string $field, string $loc, string $default): array
    {
        $def = SectionCatalog::field((string) $sec['kind'], $field);
        if (!$def || $def[0] !== 'repeater') return [];
        return SectionCatalog::rows($def, $sec['data'][$field] ?? [], $sec['tr'][$default]['data'][$field] ?? [], $sec['tr'][$loc]['data'][$field] ?? []);
    }

    /**
     * I campi tradotti di una sezione, campo per campo: quello scritto nella
     * lingua dell'ospite, altrimenti quello della lingua principale. Una
     * traduzione a metà non fa sparire i campi non ancora tradotti.
     */
    public static function tdata(array $sec, string $loc, string $default): array
    {
        $base = $sec['tr'][$default]['data'] ?? [];
        if (!is_array($base)) $base = [];
        $d = $sec['tr'][$loc]['data'] ?? null;
        if ($loc === $default || !is_array($d)) return $base;
        foreach ($d as $k => $v) {
            $pieno = is_array($v) ? (bool) array_filter($v, fn($x) => trim((string) $x) !== '') : trim((string) $v) !== '';
            if ($pieno) $base[$k] = $v;
        }
        return $base;
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
        // Le istantanee convertite al volo hanno le chiavi in un altro ordine: si confronta il contenuto.
        $ordina = function (mixed $v) use (&$ordina): mixed {
            if (!is_array($v)) return $v;
            if (!array_is_list($v)) ksort($v);
            return array_map($ordina, $v);
        };
        return json_encode($ordina($snap['sections'])) === json_encode($ordina($ora['sections']))
            && json_encode($ordina($snap['property'])) === json_encode($ordina($ora['property']))
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
