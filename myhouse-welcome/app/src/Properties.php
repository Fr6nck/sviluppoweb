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
    /** Le tipologie della struttura (fase 6: in più Appartamento e Villa o casale; niente più «Non indicata»). */
    public const TIPOLOGIE = ['casa_vacanza' => 'Casa vacanza', 'appartamento' => 'Appartamento', 'bnb' => 'B&B', 'affittacamere' => 'Affittacamere',
                              'agriturismo' => 'Agriturismo', 'villa' => 'Villa o casale', 'altro' => 'Altro'];

    // ------------------------------------------------------------------ CIN
    // Una guida = un'unità ricettiva = un indirizzo e un CIN (Codice Identificativo Nazionale).
    // Il CIN serve per pubblicare ed è unico in tutta la piattaforma; le vetrine demo sono escluse.

    /** Il CIN come lo si confronta: maiuscolo, senza spazi, trattini o «CIN:» davanti. */
    public static function cinNorm(string $cin): string
    {
        $c = strtoupper(preg_replace('/[\s\-\.\/]+/', '', $cin) ?? '');
        return (string) preg_replace('/^CIN:?/', '', $c);
    }

    /** Ha la forma di un CIN: IT, il codice ISTAT del comune (6 cifre) e il resto del codice. */
    public static function cinValido(string $cin): bool
    {
        return (bool) preg_match('/^IT\d{6}[A-Z0-9]{4,12}$/', self::cinNorm($cin));
    }

    /** L'altra guida (di qualsiasi cliente) che usa già questo CIN, o null. */
    public static function cinInUso(string $cin, int $tranne): ?array
    {
        $n = self::cinNorm($cin);
        if ($n === '') return null;
        foreach (Db::all("SELECT id, account_id, name, cin FROM properties WHERE id <> ? AND cin <> '' AND is_demo = 0 AND archived_at IS NULL", [$tranne]) as $p) {
            if (self::cinNorm((string) $p['cin']) === $n) return $p;
        }
        return null;
    }

    /** Il messaggio quando il CIN è già di un'altra guida. */
    public static function cinGiaUsato(array $altra, int $accountId): string
    {
        $mia = (int) $altra['account_id'] === $accountId;
        return 'Questo CIN è già usato ' . ($mia ? 'dalla tua guida «' . $altra['name'] . '»' : 'da un\'altra guida su MyHouse Welcome') . '. '
             . 'Una guida corrisponde a un\'unità ricettiva, con il suo indirizzo e il suo CIN: per più camere della stessa struttura usa le varianti camera.'
             . ($mia ? '' : ' Se la struttura è tua, scrivici a ' . (string) ((Config::get('legal') ?? [])['contact_email'] ?? '') . ' e lo sistemiamo.');
    }

    /** La tipologia da mostrare: con «Altro» il testo scritto dall'host, se c'è. */
    public static function tipologia(array $p): string
    {
        $t = (string) ($p['property_type'] ?? '');
        if ($t === 'altro' && trim((string) ($p['property_type_other'] ?? '')) !== '') return trim((string) $p['property_type_other']);
        return self::TIPOLOGIE[$t] ?? '';
    }

    /** @param int $oltre strutture in più oltre il limite: solo per quella appena chiesta a Stripe, che resta bloccata fino al webhook */
    public static function create(int $accountId, string $name, string $city, string $hostName, int $oltre = 0): int
    {
        $name = trim($name);
        if ($name === '') throw new \RuntimeException('Scrivi il nome della struttura.');
        if (mb_strlen($name) > 120) throw new \RuntimeException('Il nome è troppo lungo.');
        return Db::tx(function () use ($accountId, $name, $city, $hostName, $oltre) {
            $pid = Db::insert('properties', [
                'account_id' => $accountId, 'name' => $name, 'slug' => Support::uniqueSlug($name),
                'city' => mb_substr(trim($city), 0, 120), 'region' => '',
                'host_name' => mb_substr(trim($hostName), 0, 120), 'default_locale' => 'it',
                'palette' => Palette::DEFAULT, 'text_tone' => 'scuro',
                'status' => 'draft', 'wizard_step' => 'struttura', 'created_at' => Support::now(),
            ]);
            // Il limite di strutture si verifica a scrittura fatta, come per le sezioni.
            $max = Entitlements::limit($accountId, 'properties', 1);
            // La vetrina (Demo::VETRINA) non occupa il posto di una struttura del piano.
            if ((int) Db::val('SELECT COUNT(*) FROM properties WHERE account_id = ? AND archived_at IS NULL AND is_demo < 2', [$accountId], 0) > $max + max(0, $oltre)) {
                throw new \RuntimeException($max === 1
                    ? 'Il tuo piano comprende una struttura. Con Portfolio puoi gestirne di più.'
                    : "Il tuo piano comprende $max strutture.");
            }
            Db::insert('property_locales', ['property_id' => $pid, 'locale' => 'it']);
            // Il primo contatto è chi crea la struttura: il numero lo aggiunge dopo.
            if (trim($hostName) !== '') {
                Db::insert('property_contacts', ['property_id' => $pid, 'name' => mb_substr(trim($hostName), 0, 120), 'role' => 'host',
                                                 'phone' => '', 'whatsapp' => 0, 'position' => 0]);
            }
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

    /** L'indirizzo completo della struttura, in una riga: via, CAP città. */
    public static function fullAddress(array $p): string
    {
        $citta = trim(trim((string) ($p['postal_code'] ?? '')) . ' ' . trim((string) ($p['city'] ?? '')));
        return implode(', ', array_filter([trim((string) ($p['address'] ?? '')), $citta]));
    }

    /**
     * Precompila l'indirizzo di «Come arrivare» con quello della struttura, solo
     * se è ancora vuoto: quello che l'host ha scritto lì non si tocca.
     */
    public static function fillArrivalAddress(int $propertyId): void
    {
        $p = Db::one('SELECT * FROM properties WHERE id = ?', [$propertyId]);
        $ind = $p ? self::fullAddress($p) : '';
        if ($ind === '' || trim((string) ($p['address'] ?? '')) === '') return;
        foreach (Db::all("SELECT id, data FROM sections WHERE property_id = ? AND kind = 'arrival'", [$propertyId]) as $s) {
            $d = json_decode((string) $s['data'], true) ?: [];
            if (trim((string) ($d['address'] ?? '')) !== '') continue;
            $d['address'] = $ind;
            Db::update('sections', ['data' => json_encode($d, JSON_UNESCAPED_UNICODE)], 'id = :sid', ['sid' => $s['id']]);
        }
    }

    /**
     * I contatti della struttura, nell'ordine del modulo. Il primo resta anche in
     * host_name / host_phone / host_whatsapp, per chi legge ancora quelle colonne.
     * @param array<int,array> $righe
     */
    public static function saveContacts(int $propertyId, array $righe): void
    {
        $ruoli = ['host', 'cohost', 'pulizie', 'manutenzione', 'altro'];
        $puliti = [];
        foreach (array_values($righe) as $r) {
            if (!is_array($r)) continue;
            $c = ['name' => mb_substr(trim((string) ($r['name'] ?? '')), 0, 120),
                  'role' => in_array($r['role'] ?? '', $ruoli, true) ? (string) $r['role'] : 'altro',
                  'phone' => mb_substr(Telefono::normalizza((string) ($r['phone'] ?? '')), 0, 40),
                  'whatsapp' => !empty($r['whatsapp']) ? 1 : 0];
            if ($c['name'] === '' && $c['phone'] === '') continue;
            $puliti[] = $c;
            if (count($puliti) >= 8) break;
        }
        Db::tx(function () use ($propertyId, $puliti) {
            Db::run('DELETE FROM property_contacts WHERE property_id = ?', [$propertyId]);
            foreach ($puliti as $i => $c) Db::insert('property_contacts', $c + ['property_id' => $propertyId, 'position' => $i]);
            $primo = $puliti[0] ?? ['name' => '', 'phone' => '', 'whatsapp' => 0];
            Db::update('properties', ['host_name' => $primo['name'], 'host_phone' => $primo['phone'],
                                      'host_whatsapp' => $primo['whatsapp'] ? $primo['phone'] : ''], 'id = :pid', ['pid' => $propertyId]);
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
            throw new LimitReached("Hai già $max sezioni attive, il massimo del tuo piano. Passa a Plus per averne quante vuoi, oppure disattivane una per liberare un posto.");
        }
    }

    /** Aggiunge una sezione del catalogo. Il nucleo non si aggiunge: c'è già. */
    public static function addSection(int $accountId, int $propertyId, string $kind): int
    {
        if (!in_array($kind, SectionCatalog::selectable(), true)) throw new \RuntimeException('Tipo di sezione sconosciuto.');
        if (SectionCatalog::hasPlaces($kind) && !Entitlements::can($accountId, 'places')) {
            throw new \RuntimeException('I luoghi consigliati non fanno parte del tuo piano.');
        }
        // Una guida è un'unità ricettiva: ogni sezione una volta sola (un check-in, un Wi-Fi, un indirizzo),
        // e poche sezioni libere. Più camere con Wi-Fi o istruzioni diverse sono «varianti camera».
        $gia = (int) Db::val('SELECT COUNT(*) FROM sections WHERE property_id = ? AND kind = ?', [$propertyId, $kind], 0);
        if (SectionCatalog::multipla($kind) && $gia >= SectionCatalog::LIBERE_MAX) {
            throw new LimitReached('Puoi avere al massimo ' . SectionCatalog::LIBERE_MAX . ' sezioni libere in una guida.');
        }
        if (!SectionCatalog::multipla($kind) && $gia > 0) {
            throw new \RuntimeException('«' . SectionCatalog::nome($kind) . '» c\'è già in questa guida: ogni sezione si aggiunge una volta sola. Per camere con Wi-Fi o istruzioni diverse usa le varianti camera.');
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
            // «Come arrivare» parte dall'indirizzo della struttura: si scrive una volta sola.
            if ($kind === 'arrival') self::fillArrivalAddress($propertyId);
            self::assertWithinLimit($accountId, $propertyId);
            return $sid;
        });
    }

    /** Accende o spegne una sezione. Spegnerla libera un posto; i contenuti restano. */
    public static function setActive(int $accountId, int $propertyId, int $sectionId, bool $on): void
    {
        Db::tx(function () use ($accountId, $propertyId, $sectionId, $on) {
            $s = self::section($propertyId, $sectionId);
            if ((int) $s['is_core'] === 1) throw new \RuntimeException('Check-in & Check-out è sempre inclusa: non si disattiva.');
            Db::update('sections', ['is_active' => $on ? 1 : 0], 'id = :sid', ['sid' => $sectionId]);
            if ($on) self::assertWithinLimit($accountId, $propertyId);
        });
    }

    public static function deleteSection(int $propertyId, int $sectionId): void
    {
        $s = self::section($propertyId, $sectionId);
        if ((int) $s['is_core'] === 1) throw new \RuntimeException('Check-in & Check-out non si elimina.');
        Db::run('DELETE FROM sections WHERE id = ?', [$sectionId]);
        // Con la sezione se ne vanno i suoi file: immagine, PDF, foto e PDF delle righe.
        $aid = (int) Db::val('SELECT account_id FROM properties WHERE id = ?', [$propertyId], 0);
        $ids = array_merge(array_filter([(int) $s['media_id'], (int) $s['pdf_media_id']]),
                           SectionCatalog::mediaIds($s['kind'], json_decode((string) $s['data'], true) ?: []));
        foreach (array_unique($ids) as $mid) Media::rilascia($mid, $aid);
    }

    /**
     * Foto e PDF dentro le righe (istruzioni, parcheggi): prima di salvare la
     * sezione, carica i file nuovi, toglie quelli spuntati con «Togli» e scarta
     * gli id che non sono di questa struttura. Restituisce il modulo con gli id
     * giusti nei campi nascosti; i file non più usati li cancella dopo il
     * salvataggio cleanRowMedia().
     */
    public static function saveRowMedia(int $accountId, int $propertyId, string $kind, array $post, array $files): array
    {
        $nuovi = [];
        try {
            foreach (SectionCatalog::fields($kind) as $campo => $def) {
                if ($def[0] !== 'repeater' || !is_array($post[$campo] ?? null)) continue;
                foreach ($def['sub'] as $sn => $sd) {
                    if (!in_array($sd[0], ['image', 'pdf'], true) || !empty($sd['nascosto'])) continue;
                    // La locandina degli eventi (6G): una zona sola. Un PDF va nel sottocampo compagno
                    // e svuota l'immagine; un'immagine svuota il PDF; «Togli» li svuota tutti e due.
                    if (!empty($sd['locandina'])) {
                        $pn = $sd['locandina'];
                        foreach ($post[$campo] as $k => $r) {
                            if (!is_array($r)) continue;
                            $img = (int) ($r[$sn] ?? 0); $doc = (int) ($r[$pn] ?? 0);
                            $tuo = fn(int $id, string $tipo) => $id && Db::one('SELECT id FROM media WHERE id = ? AND account_id = ? AND property_id = ? AND kind = ?', [$id, $accountId, $propertyId, $tipo]) ? $id : 0;
                            $img = $tuo($img, 'image'); $doc = $tuo($doc, 'pdf');
                            if (!empty($post['rip_togli'][$campo][$k][$sn])) $img = $doc = 0;
                            $f = [];
                            foreach (['name', 'type', 'tmp_name', 'error', 'size'] as $x) $f[$x] = $files['rip_file'][$x][$campo][$k][$sn] ?? null;
                            if ($f['error'] !== null && (int) $f['error'] !== UPLOAD_ERR_NO_FILE) {
                                $alt = (string) ($r['name'] ?? '');
                                $testa = is_string($f['tmp_name']) && is_file($f['tmp_name']) ? (string) file_get_contents($f['tmp_name'], false, null, 0, 5) : '';
                                if ($testa === '%PDF-') {
                                    if (!Entitlements::can($accountId, 'pdf')) throw new \RuntimeException('I PDF nelle sezioni sono disponibili con il piano Plus.');
                                    $doc = $nuovi[] = Media::storePdf($f, $accountId, $propertyId, $alt); $img = 0;
                                } else {
                                    if (!Entitlements::can($accountId, 'photos')) throw new \RuntimeException('Le foto nelle sezioni sono disponibili con il piano Plus.');
                                    $img = $nuovi[] = Media::storeImage($f, $accountId, $propertyId, $alt, 'section'); $doc = 0;
                                }
                            }
                            $post[$campo][$k][$sn] = $img ?: '';
                            $post[$campo][$k][$pn] = $doc ?: '';
                        }
                        continue;
                    }
                    foreach ($post[$campo] as $k => $r) {
                        if (!is_array($r)) continue;
                        $mid = (int) ($r[$sn] ?? 0);
                        if ($mid && !Db::one('SELECT id FROM media WHERE id = ? AND account_id = ? AND property_id = ? AND kind = ?',
                                             [$mid, $accountId, $propertyId, $sd[0]])) $mid = 0;
                        if (!empty($post['rip_togli'][$campo][$k][$sn])) $mid = 0;
                        $f = [];
                        foreach (['name', 'type', 'tmp_name', 'error', 'size'] as $x) $f[$x] = $files['rip_file'][$x][$campo][$k][$sn] ?? null;
                        if ($f['error'] !== null && (int) $f['error'] !== UPLOAD_ERR_NO_FILE) {
                            $alt = (string) ($r['title'] ?? $r['name'] ?? '');
                            if ($sd[0] === 'image') {
                                if (!Entitlements::can($accountId, 'photos')) throw new \RuntimeException('Le foto nelle sezioni sono disponibili con il piano Plus.');
                                $mid = $nuovi[] = Media::storeImage($f, $accountId, $propertyId, $alt, 'section');
                            } else {
                                if (!Entitlements::can($accountId, 'pdf')) throw new \RuntimeException('I PDF nelle sezioni sono disponibili con il piano Plus.');
                                $mid = $nuovi[] = Media::storePdf($f, $accountId, $propertyId, $alt);
                            }
                        }
                        $post[$campo][$k][$sn] = $mid ?: '';
                    }
                }
            }
        } catch (\Throwable $e) {
            foreach ($nuovi as $mid) Media::delete($mid, $accountId);
            throw $e;
        }
        unset($post['rip_togli']);
        return $post;
    }

    /** Dopo il salvataggio: cancella i file delle righe che la sezione non usa più. */
    public static function cleanRowMedia(int $accountId, string $kind, array $prima, array $dopo): void
    {
        foreach (array_diff(SectionCatalog::mediaIds($kind, $prima), SectionCatalog::mediaIds($kind, $dopo)) as $mid) Media::rilascia($mid, $accountId);
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

        // Categoria ed etichetta (fase 6B): una chiave della sezione, oppure il testo scritto a mano
        // («Altro…», «Personalizzata…»). null = il modulo non le ha mandate: restano come sono.
        $catKey = $badgeKey = null;
        if ($isDefault && Migrator::columnExists('places', 'category_key')) {
            if (array_key_exists('category_choice', $in)) {
                $c = (string) $in['category_choice'];
                $catKey = in_array($c, Tassonomie::categorie($s['kind']), true) ? $c : '';
                if ($catKey !== '') $in['category'] = '';
            }
            if (array_key_exists('badge_choice', $in)) {
                $c = (string) $in['badge_choice'];
                $badgeKey = in_array($c, Tassonomie::etichette($s['kind']), true) ? $c : '';
                if ($badgeKey !== '' || $c === '') $in['badge'] = '';   // «Nessuna»: niente bollino
            }
        }
        return Db::tx(function () use ($s, $placeId, $locale, $isDefault, $in, $nome, $catKey, $badgeKey) {
            if ($placeId) {
                $pl = Db::one('SELECT * FROM places WHERE id = ? AND section_id = ?', [$placeId, $s['id']]);
                if (!$pl) throw new NotFound('Luogo non trovato.');
            }
            if ($isDefault) {
                $comuni = [
                    'name' => $nome,
                    'address' => mb_substr(trim((string) ($in['address'] ?? '')), 0, 255),
                    'maps_url' => Support::safeUrl((string) ($in['maps_url'] ?? '')),
                    'phone' => mb_substr(Telefono::normalizza((string) ($in['phone'] ?? '')), 0, 40),
                    'website' => Support::safeUrl((string) ($in['website'] ?? '')),
                    'booking_url' => Support::safeUrl((string) ($in['booking_url'] ?? '')),
                    'walk_minutes' => max(0, min(600, (int) ($in['walk_minutes'] ?? 0))),
                    'drive_minutes' => max(0, min(600, (int) ($in['drive_minutes'] ?? 0))),
                    'badge_tone' => in_array($in['badge_tone'] ?? '', ['pine', 'sea', 'ochre', 'terracotta'], true) ? $in['badge_tone'] : 'pine',
                ];
                if ($catKey !== null) $comuni['category_key'] = $catKey;
                if ($badgeKey !== null) $comuni['badge_key'] = $badgeKey;
                // Le coordinate (dal link di Google Maps) solo se chi chiama le ha lette.
                if (array_key_exists('lat', $in) && Migrator::columnExists('places', 'lat')) {
                    $ok = is_numeric($in['lat'] ?? null) && is_numeric($in['lng'] ?? null) && abs((float) $in['lat']) <= 90 && abs((float) $in['lng']) <= 180;
                    $comuni['lat'] = $ok ? round((float) $in['lat'], 6) : null;
                    $comuni['lng'] = $ok ? round((float) $in['lng'], 6) : null;
                }
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
            // Con la chiave il testo scritto a mano non serve più, in nessuna lingua.
            if ($catKey) Db::run("UPDATE place_translations SET category = '' WHERE place_id = ?", [$placeId]);
            if ($badgeKey) Db::run("UPDATE place_translations SET badge = '' WHERE place_id = ?", [$placeId]);
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
            foreach (SectionCatalog::fields($s['kind']) as $f => $def) {
                $tipo = $def[0];
                if ($tipo === 'repeater') {
                    // Riga per riga, sottocampo per sottocampo.
                    $tr = [];
                    foreach ((array) ($trad[$f] ?? []) as $r) if (is_array($r) && isset($r['id'])) $tr[$r['id']] = $r;
                    foreach ((array) ($orig[$f] ?? []) as $r) {
                        if (!is_array($r)) continue;
                        foreach ($def['sub'] as $sn => $sd) {
                            if (!SectionCatalog::isTranslated($sd[0]) || !$pieno($r[$sn] ?? '')) continue;
                            $tot++;
                            if ($pieno($tr[$r['id'] ?? ''][$sn] ?? '')) $fatti++;
                        }
                    }
                    continue;
                }
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
