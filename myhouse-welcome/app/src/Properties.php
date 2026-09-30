<?php
namespace MHW;

/**
 * Strutture, sezioni e luoghi: le regole in un posto solo.
 *
 * Ogni limite del piano si controlla qui dentro, non nelle viste: una
 * richiesta costruita a mano (senza passare dai bottoni) incontra gli stessi
 * controlli. Il limite delle sezioni si verifica DOPO la scrittura, dentro la
 * transazione: due richieste parallele non possono sforarlo insieme.
 */
final class Properties
{
    public static function create(int $accountId, string $name, string $city, string $hostName): int
    {
        $name = trim($name);
        if ($name === '') throw new \RuntimeException('Scrivi il nome della struttura.');
        if (mb_strlen($name) > 120) throw new \RuntimeException('Il nome è troppo lungo.');
        return Db::tx(function () use ($accountId, $name, $city, $hostName) {
            $pid = Db::insert('properties', [
                'account_id' => $accountId, 'name' => $name, 'slug' => Support::uniqueSlug($name),
                'city' => mb_substr(trim($city), 0, 120), 'region' => '',
                'host_name' => mb_substr(trim($hostName), 0, 120), 'default_locale' => 'it',
                'palette' => Palette::DEFAULT, 'text_tone' => 'scuro',
                'status' => 'draft', 'wizard_step' => 'struttura', 'created_at' => Support::now(),
            ]);
            // Il limite di strutture si verifica a scrittura fatta, come per le sezioni.
            $max = Entitlements::limit($accountId, 'properties', 1);
            if ((int) Db::val('SELECT COUNT(*) FROM properties WHERE account_id = ? AND archived_at IS NULL', [$accountId], 0) > $max) {
                throw new \RuntimeException($max === 1
                    ? 'Il tuo piano comprende una struttura. Con Portfolio puoi gestirne di più.'
                    : "Il tuo piano comprende $max strutture.");
            }
            Db::insert('property_locales', ['property_id' => $pid, 'locale' => 'it']);
            Db::insert('qr_tokens', ['property_id' => $pid, 'token' => Support::token(9), 'scans' => 0, 'created_at' => Support::now()]);
            $sid = Db::insert('sections', [
                'property_id' => $pid, 'kind' => 'checkin', 'icon' => 'checkin', 'color' => 'terracotta',
                'position' => 0, 'is_core' => 1, 'is_active' => 1, 'data' => '{}', 'created_at' => Support::now(),
            ]);
            Db::insert('section_translations', [
                'section_id' => $sid, 'locale' => 'it', 'title' => SectionCatalog::title('checkin', 'it'),
                'body' => '', 'data' => '{}', 'state' => 'reviewed', 'updated_at' => Support::now(),
            ]);
            return $pid;
        });
    }

    public static function activeCount(int $propertyId): int
    {
        return (int) Db::val('SELECT COUNT(*) FROM sections WHERE property_id = ? AND is_core = 0 AND is_active = 1', [$propertyId], 0);
    }

    private static function assertWithinLimit(int $accountId, int $propertyId): void
    {
        $max = Entitlements::limit($accountId, 'sections', 4);
        if (self::activeCount($propertyId) > $max) {
            throw new LimitReached("Hai utilizzato tutte le $max sezioni incluse nel tuo piano. Passa a Plus per aggiungere tutte le sezioni che vuoi.");
        }
    }

    /** Aggiunge una sezione del catalogo. Il nucleo non si aggiunge: c'è già. */
    public static function addSection(int $accountId, int $propertyId, string $kind): int
    {
        if (!in_array($kind, SectionCatalog::selectable(), true)) throw new \RuntimeException('Tipo di sezione sconosciuto.');
        if (SectionCatalog::hasPlaces($kind) && !Entitlements::can($accountId, 'places')) {
            throw new \RuntimeException('I consigli sul posto non sono compresi nel tuo piano.');
        }
        return Db::tx(function () use ($accountId, $propertyId, $kind) {
            $pos = (int) Db::val('SELECT COALESCE(MAX(position),0)+1 FROM sections WHERE property_id = ?', [$propertyId], 1);
            $sid = Db::insert('sections', [
                'property_id' => $propertyId, 'kind' => $kind, 'icon' => $kind, 'color' => 'terracotta',
                'position' => $pos, 'is_core' => 0, 'is_active' => 1, 'data' => '{}', 'created_at' => Support::now(),
            ]);
            $loc = (string) Db::val('SELECT default_locale FROM properties WHERE id = ?', [$propertyId], 'it');
            Db::insert('section_translations', [
                'section_id' => $sid, 'locale' => $loc, 'title' => SectionCatalog::title($kind, $loc),
                'body' => '', 'data' => '{}', 'state' => 'reviewed', 'updated_at' => Support::now(),
            ]);
            self::assertWithinLimit($accountId, $propertyId);
            return $sid;
        });
    }

    /** Accende o spegne una sezione. Spegnerla libera un posto; i contenuti restano. */
    public static function setActive(int $accountId, int $propertyId, int $sectionId, bool $on): void
    {
        Db::tx(function () use ($accountId, $propertyId, $sectionId, $on) {
            $s = self::section($propertyId, $sectionId);
            if ((int) $s['is_core'] === 1) throw new \RuntimeException('Check-in & Check-out è sempre incluso e non si disattiva.');
            Db::update('sections', ['is_active' => $on ? 1 : 0], 'id = :sid', ['sid' => $sectionId]);
            if ($on) self::assertWithinLimit($accountId, $propertyId);
        });
    }

    public static function deleteSection(int $propertyId, int $sectionId): void
    {
        $s = self::section($propertyId, $sectionId);
        if ((int) $s['is_core'] === 1) throw new \RuntimeException('Check-in & Check-out non si elimina.');
        Db::run('DELETE FROM sections WHERE id = ?', [$sectionId]);
    }

    /** Sposta su o giù fra le sezioni aggiuntive. Il nucleo resta sempre in testa. */
    public static function move(int $propertyId, int $sectionId, string $dir): void
    {
        $s = self::section($propertyId, $sectionId);
        if ((int) $s['is_core'] === 1) return;
        Db::tx(function () use ($propertyId, $s, $dir) {
            $tutte = Db::all('SELECT id FROM sections WHERE property_id = ? AND is_core = 0 ORDER BY position, id', [$propertyId]);
            $ids = array_map('intval', array_column($tutte, 'id'));
            $i = array_search((int) $s['id'], $ids, true);
            $j = $dir === 'su' ? $i - 1 : $i + 1;
            if ($i === false || $j < 0 || $j >= count($ids)) return;
            [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
            foreach ($ids as $pos => $id) Db::update('sections', ['position' => $pos + 1], 'id = :sid', ['sid' => $id]);
        });
    }

    public static function section(int $propertyId, int $sectionId): array
    {
        $s = Db::one('SELECT * FROM sections WHERE id = ? AND property_id = ?', [$sectionId, $propertyId]);
        if (!$s) throw new NotFound('Sezione non trovata.');
        return $s;
    }

    /**
     * Salva i contenuti di una sezione in una lingua. I campi uguali in ogni
     * lingua (rete, indirizzo, link) si salvano solo dalla lingua principale.
     */
    public static function saveSection(int $propertyId, int $sectionId, string $locale, array $in, bool $isDefault): void
    {
        $s = self::section($propertyId, $sectionId);
        [$comuni, $tradotti] = SectionCatalog::fromInput($s['kind'], $in, $isDefault);
        // Un modulo senza il campo titolo (la procedura guidata) non lo tocca.
        $titolo = null;
        if (array_key_exists('title', $in)) {
            $titolo = mb_substr(trim((string) $in['title']), 0, 120);
            if ($titolo === '') $titolo = SectionCatalog::title($s['kind'], $locale);
        }
        Db::tx(function () use ($s, $locale, $comuni, $tradotti, $titolo, $isDefault) {
            if ($isDefault) {
                $prima = json_decode((string) $s['data'], true) ?: [];
                Db::update('sections', ['data' => json_encode($comuni + $prima, JSON_UNESCAPED_UNICODE)], 'id = :sid', ['sid' => $s['id']]);
            }
            $t = Db::one('SELECT id, data FROM section_translations WHERE section_id = ? AND locale = ?', [$s['id'], $locale]);
            $tradotti += $t ? (json_decode((string) $t['data'], true) ?: []) : [];
            $riga = ['data' => json_encode($tradotti, JSON_UNESCAPED_UNICODE), 'state' => 'reviewed', 'updated_at' => Support::now()];
            if ($titolo !== null || !$t) $riga['title'] = $titolo ?? SectionCatalog::title($s['kind'], $locale);
            if ($t) Db::update('section_translations', $riga, 'id = :tid', ['tid' => $t['id']]);
            else Db::insert('section_translations', $riga + ['section_id' => $s['id'], 'locale' => $locale, 'body' => '']);
        });
    }

    // ------------------------------------------------------------------ luoghi

    public static function savePlace(int $accountId, int $propertyId, int $sectionId, ?int $placeId, string $locale, bool $isDefault, array $in): int
    {
        $s = self::section($propertyId, $sectionId);
        if (!SectionCatalog::hasPlaces($s['kind'])) throw new \RuntimeException('Questa sezione non contiene luoghi.');
        $nome = mb_substr(trim((string) ($in['name'] ?? '')), 0, 160);
        if ($isDefault && $nome === '') throw new \RuntimeException('Scrivi il nome del luogo.');

        return Db::tx(function () use ($s, $placeId, $locale, $isDefault, $in, $nome) {
            if ($placeId) {
                $pl = Db::one('SELECT * FROM places WHERE id = ? AND section_id = ?', [$placeId, $s['id']]);
                if (!$pl) throw new NotFound('Luogo non trovato.');
            }
            if ($isDefault) {
                $comuni = [
                    'name' => $nome,
                    'address' => mb_substr(trim((string) ($in['address'] ?? '')), 0, 255),
                    'maps_url' => Support::safeUrl((string) ($in['maps_url'] ?? '')),
                    'phone' => mb_substr(trim((string) ($in['phone'] ?? '')), 0, 40),
                    'website' => Support::safeUrl((string) ($in['website'] ?? '')),
                    'booking_url' => Support::safeUrl((string) ($in['booking_url'] ?? '')),
                    'walk_minutes' => max(0, min(600, (int) ($in['walk_minutes'] ?? 0))),
                    'drive_minutes' => max(0, min(600, (int) ($in['drive_minutes'] ?? 0))),
                    'badge_tone' => in_array($in['badge_tone'] ?? '', ['pine', 'sea', 'ochre', 'terracotta'], true) ? $in['badge_tone'] : 'pine',
                ];
                if ($placeId) Db::update('places', $comuni, 'id = :pid', ['pid' => $placeId]);
                else {
                    $comuni += ['section_id' => $s['id'], 'category' => '', 'distance' => '', 'note' => '', 'badge' => '',
                                'position' => (int) Db::val('SELECT COUNT(*) FROM places WHERE section_id = ?', [$s['id']], 0)];
                    $placeId = Db::insert('places', $comuni);
                }
            }
            $tr = [
                'category' => mb_substr(trim((string) ($in['category'] ?? '')), 0, 80),
                'description' => mb_substr(trim((string) ($in['description'] ?? '')), 0, 600),
                'note' => mb_substr(trim((string) ($in['note'] ?? '')), 0, 400),
                'badge' => mb_substr(trim((string) ($in['badge'] ?? '')), 0, 80),
            ];
            $esiste = Db::one('SELECT id FROM place_translations WHERE place_id = ? AND locale = ?', [$placeId, $locale]);
            if ($esiste) Db::update('place_translations', $tr, 'id = :tid', ['tid' => $esiste['id']]);
            else Db::insert('place_translations', $tr + ['place_id' => $placeId, 'locale' => $locale]);
            return (int) $placeId;
        });
    }

    public static function deletePlace(int $propertyId, int $sectionId, int $placeId): void
    {
        self::section($propertyId, $sectionId);
        Db::run('DELETE FROM places WHERE id = ? AND section_id = ?', [$placeId, $sectionId]);
    }

    public static function movePlace(int $propertyId, int $sectionId, int $placeId, string $dir): void
    {
        self::section($propertyId, $sectionId);
        Db::tx(function () use ($sectionId, $placeId, $dir) {
            $ids = array_map('intval', array_column(Db::all('SELECT id FROM places WHERE section_id = ? ORDER BY position, id', [$sectionId]), 'id'));
            $i = array_search($placeId, $ids, true);
            $j = $dir === 'su' ? $i - 1 : $i + 1;
            if ($i === false || $j < 0 || $j >= count($ids)) return;
            [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
            foreach ($ids as $pos => $id) Db::update('places', ['position' => $pos], 'id = :pid', ['pid' => $id]);
        });
    }

    // ----------------------------------------------------------------- lingue

    /** Le lingue da pubblicare: solo quelle del piano, e sempre quella principale. */
    /**
     * Quanto è tradotta una lingua, campo per campo: si contano i testi scritti
     * nella lingua principale (titoli esclusi, che hanno già la traduzione di
     * catalogo) e quanti di questi hanno la loro versione.
     * @return array{0:int,1:int} [tradotti, da tradurre in tutto]
     */
    public static function translationCoverage(int $propertyId, string $locale): array
    {
        $p = Db::one('SELECT default_locale FROM properties WHERE id = ?', [$propertyId]);
        $pieno = fn($v) => is_array($v) ? (bool) array_filter($v, fn($x) => trim((string) $x) !== '') : trim((string) $v) !== '';
        $fatti = 0; $tot = 0;
        foreach (Db::all('SELECT id, kind FROM sections WHERE property_id = ? AND is_active = 1', [$propertyId]) as $s) {
            $orig = json_decode((string) Db::val('SELECT data FROM section_translations WHERE section_id = ? AND locale = ?', [$s['id'], $p['default_locale']]), true) ?: [];
            $trad = json_decode((string) Db::val('SELECT data FROM section_translations WHERE section_id = ? AND locale = ?', [$s['id'], $locale]), true) ?: [];
            foreach (SectionCatalog::fields($s['kind']) as $f => [$tipo]) {
                if (!SectionCatalog::isTranslated($tipo) || !$pieno($orig[$f] ?? '')) continue;
                $tot++;
                if ($pieno($trad[$f] ?? '')) $fatti++;
            }
            foreach (Db::all('SELECT id FROM places WHERE section_id = ?', [$s['id']]) as $pl) {
                $o = Db::one('SELECT * FROM place_translations WHERE place_id = ? AND locale = ?', [$pl['id'], $p['default_locale']]) ?: [];
                $t = Db::one('SELECT * FROM place_translations WHERE place_id = ? AND locale = ?', [$pl['id'], $locale]) ?: [];
                foreach (['category', 'description', 'note', 'badge'] as $f) {
                    if (!$pieno($o[$f] ?? '')) continue;
                    $tot++;
                    if ($pieno($t[$f] ?? '')) $fatti++;
                }
            }
        }
        return [$fatti, $tot];
    }

    /**
     * La lingua principale: quella in cui l'host scrive. Deve essere compresa
     * nel piano. La vecchia resta tra le lingue attive, così i testi già scritti
     * non spariscono: diventano una traduzione.
     */
    public static function setDefaultLocale(int $accountId, int $propertyId, string $locale): void
    {
        if (!in_array($locale, Entitlements::allowedLocales($accountId), true)) {
            throw new \RuntimeException('Il tuo piano non comprende questa lingua.');
        }
        Db::tx(function () use ($propertyId, $locale) {
            Db::update('properties', ['default_locale' => $locale], 'id = :pid', ['pid' => $propertyId]);
            if (!Db::one('SELECT property_id FROM property_locales WHERE property_id = ? AND locale = ?', [$propertyId, $locale])) {
                Db::insert('property_locales', ['property_id' => $propertyId, 'locale' => $locale]);
            }
        });
    }

    public static function setLocales(int $accountId, int $propertyId, array $want): array
    {
        $p = Db::one('SELECT default_locale FROM properties WHERE id = ?', [$propertyId]);
        $consentite = Entitlements::allowedLocales($accountId);
        $chieste = array_values(array_unique(array_filter($want, 'is_string')));
        $fuori = array_diff($chieste, $consentite);
        if ($fuori) throw new \RuntimeException('Il tuo piano non comprende queste lingue.');
        if (!in_array($p['default_locale'], $chieste, true)) $chieste[] = $p['default_locale'];
        Db::tx(function () use ($propertyId, $chieste) {
            Db::run('DELETE FROM property_locales WHERE property_id = ?', [$propertyId]);
            foreach ($chieste as $l) Db::insert('property_locales', ['property_id' => $propertyId, 'locale' => $l]);
        });
        return $chieste;
    }
}
