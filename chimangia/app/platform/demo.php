<?php
/**
 * Piattaforma ChiMangia — locale dimostrativo, per provare la piattaforma con dati realistici.
 *
 * Si crea, si ricrea con le date di oggi e si elimina da Super admin → Clienti.
 * - Le email degli accessi finiscono in @demo.invalid: è un dominio riservato, nessuna email parte davvero.
 * - La password è casuale, nuova a ogni generazione, uguale per tutti gli accessi dimostrativi.
 *   Viene conservata cifrata solo per mostrarla al fornitore nella scheda della demo.
 * - Il locale è segnato con «[demo]» nelle note interne: si elimina solo un locale segnato così.
 */
declare(strict_types=1);

defined('CM_ROOT') || exit;

const DEMO_MARK = '[demo]';
const DEMO_DOMAIN = 'demo.invalid';
const DEMO_STAFF = [
    'admin' => ['titolare', 'Anna Martini'],
    'reception' => ['reception', 'Giulia Santoro'],
    'cucina' => ['cucina', 'Pietro Valli'],
];

function demo_email(string $user): string
{
    return $user . '@' . DEMO_DOMAIN;
}

function venue_is_demo(array $venue): bool
{
    return str_starts_with((string) ($venue['notes'] ?? ''), DEMO_MARK);
}

/** Il locale dimostrativo attuale con la sua password, oppure null. */
function demo_info(): ?array
{
    $d = setting('pf_demo', []);
    if (!is_array($d) || empty($d['venue_id'])) {
        return null;
    }
    $venue = venue_get((int) $d['venue_id'], true);
    if (!$venue || !venue_is_demo($venue)) {
        return null;
    }
    return ['venue' => $venue, 'password' => secret_decrypt((string) ($d['secret'] ?? '')), 'created_at' => (string) ($d['created_at'] ?? '')];
}

/** Id del locale dimostrativo (0 se non c'è): serve per tenerlo fuori dai numeri d'insieme. */
function demo_venue_id(): int
{
    $d = setting('pf_demo', []);
    return is_array($d) ? (int) ($d['venue_id'] ?? 0) : 0;
}

/** Elimina il locale dimostrativo e quelli rimasti da generazioni precedenti. Restituisce quanti ne ha eliminati. */
function demo_delete(): int
{
    require_once CM_APP . '/platform/maint.php';
    $ids = [demo_venue_id()];
    $ids = array_merge($ids, array_map('intval', q(
        'SELECT DISTINCT s.venue_id FROM ' . t('staff') . ' s JOIN ' . t('venues') . ' v ON v.id = s.venue_id WHERE s.email LIKE ? AND v.notes LIKE ?',
        ['%@' . DEMO_DOMAIN, DEMO_MARK . '%']
    )->fetchAll(PDO::FETCH_COLUMN)));
    $n = 0;
    foreach (array_unique(array_filter($ids)) as $id) {
        $notes = q_value('SELECT notes FROM ' . t('venues') . ' WHERE id = ?', [$id]);
        if (is_string($notes) && str_starts_with($notes, DEMO_MARK)) {
            venue_delete($id);
            $n++;
        }
    }
    setting_set('pf_demo', []);
    return $n;
}

/** Il piatto va bene per queste diete? (controllo grossolano sul nome, basta per dati di prova coerenti) */
function demo_dish_ok(string $name, array $diets): bool
{
    $ban = [
        'Vegetariano' => ['vitello', 'pollo', 'polpette', 'salsiccia', 'maiale', 'ragù', 'merluzzo', 'bistecca', 'scaloppina'],
        'No maiale' => ['salsiccia', 'maiale', 'ragù'],
        'No pesce' => ['merluzzo'],
        'Senza glutine' => ['pasta', 'penne', 'spaghetti', 'lasagne', 'gnocchi', 'tagliatelle', 'torta salata', 'scaloppina', 'polpette'],
        'Senza lattosio' => ['caprese', 'purè', 'lasagne'],
    ];
    $n = mb_strtolower($name);
    foreach ($diets as $d) {
        foreach ($ban[$d] ?? [] as $word) {
            if (str_contains($n, $word)) {
                return false;
            }
        }
    }
    return true;
}

/**
 * Crea (o ricrea) il locale dimostrativo. Restituisce ['venue_id' => , 'slug' => , 'password' => ].
 * Le date sono relative a oggi: tre settimane di storico, oggi, e i prossimi giorni.
 */
function demo_create(): array
{
    demo_delete();
    $emails = array_map(static fn ($s) => demo_email($s[0]), DEMO_STAFF);
    $taken = q_value('SELECT email FROM ' . t('staff') . ' WHERE email IN (?, ?, ?)', array_values($emails));
    if ($taken) {
        throw new RuntimeException('L\'indirizzo ' . $taken . ' è già usato da un altro locale: eliminalo prima di creare la demo.');
    }

    $password = 'Demo' . random_int(100000, 999999);
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $today = today();
    $at = static fn (int $offset, string $time = '09:00:00'): string => day_add($today, $offset) . ' ' . $time;

    [$vid, $ownerId] = venue_create([
        'name' => 'Locanda Demo', 'kind' => 'hotel', 'email' => $emails['admin'], 'owner_name' => DEMO_STAFF['admin'][1],
        'password' => $password, 'hotel' => true, 'status' => 'attivo', 'plan' => 'p80',
    ]);
    q(
        'UPDATE ' . t('venues') . ' SET email_verified_at = ?, address = ?, notes = ?, created_at = ? WHERE id = ?',
        [now(), 'Via della Prova 1, Perugia', DEMO_MARK . ' Locale dimostrativo con dati di prova. Si ricrea o si elimina da Super admin → Clienti.', $at(-30), $vid]
    );
    venue_patch($vid, 'settings', [
        'formulas' => [
            ['id' => 'base', 'name' => 'Base', 'desc' => pf_formula_types()['scelta'][0], 'price' => 12.5, 'type' => 'scelta'],
            ['id' => 'completo', 'name' => 'Completo', 'desc' => pf_formula_types()['completo'][0], 'price' => 14.5, 'type' => 'completo'],
        ],
        'drinks' => [['id' => 'acqua', 'label' => 'Acqua', 'price' => 0], ['id' => 'vino', 'label' => 'Calice di vino', 'price' => 3], ['id' => 'bibita', 'label' => 'Bibita', 'price' => 2.5]],
        'extras' => [['id' => 'dolce', 'label' => 'Dolce', 'price' => 4], ['id' => 'caffe', 'label' => 'Caffè', 'price' => 1], ['id' => 'amaro', 'label' => 'Ammazzacaffè', 'price' => 3]],
        'welcome' => 'Benvenuto alla Locanda Demo: qui prenoti pranzo, cena e le notti. È un locale di prova, i dati non sono reali.',
        'guide_done' => true,
    ]);
    $v = venue_get($vid, true);

    $pdo = db();
    $pdo->beginTransaction();
    try {
        /* ---------- Staff ---------- */
        q('UPDATE ' . t('staff') . ' SET created_at = ?, last_login_at = ? WHERE id = ?', [$at(-30), $at(0, '08:10:00'), $ownerId]);
        foreach (['reception', 'cucina'] as $role) {
            q(
                'INSERT INTO ' . t('staff') . ' (venue_id, name, email, password_hash, role, is_owner, active, last_login_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 0, 1, ?, ?, ?)',
                [$vid, DEMO_STAFF[$role][1], $emails[$role], $hash, $role, $at(0, '07:45:00'), $at(-29), $at(-29)]
            );
        }

        /* ---------- Società e camere ---------- */
        $co = [];
        foreach ([
            'edil' => ['Edil Rossi Costruzioni', 'Squadra fuori dalle 7:00', 1],
            'elettro' => ['Elettro Impianti Sud', '', 1],
            'termo' => ['Termoidraulica Bastia', 'Pranzo al sacco il sabato', 1],
            'ponteggi' => ['Ponteggi Umbria', '', 1],
            'doppione' => ['Edilrossi srl', '', 0],   // scritta da un ospite nuovo: la reception la unisce a Edil Rossi
        ] as $k => [$name, $note, $ok]) {
            q('INSERT INTO ' . t('companies') . ' (venue_id, name, note, contact, verified, created_at) VALUES (?, ?, ?, ?, ?, ?)', [$vid, $name, $note, '', $ok, $at($ok ? -28 : -1)]);
            $co[$k] = (int) $pdo->lastInsertId();
        }
        $room = [];
        foreach (['1' => 2, '2' => 2, '3' => 1, '5' => 2, '7' => 3, '8' => 1] as $name => $beds) {
            q('INSERT INTO ' . t('rooms') . ' (venue_id, name, beds, note, active) VALUES (?, ?, ?, ?, 1)', [$vid, (string) $name, $beds, $name === '8' ? 'Al piano terra' : '']);
            $room[(string) $name] = (int) $pdo->lastInsertId();
        }

        /* ---------- Ospiti ----------
         * tipo: stay = in trasferta (notti e pasti), day = solo pranzo nei giorni feriali, misto = pranzo feriale e qualche cena.
         * soggiorni: intervalli di notti, in giorni da oggi. */
        $guests = [
            ['Marco', 'Bianchi', 'edil', ['No maiale'], '', '', 'stay', [[-20, -15], [-6, 4]], '1'],
            ['Giuseppe', 'Conti', 'edil', [], '', 'Porzioni abbondanti', 'stay', [[-20, -15], [-6, 4]], '1'],
            ['Andrea', 'Galli', 'edil', [], '', '', 'stay', [[-20, -15], [-6, 4]], '2'],
            ['Stefano', 'Moretti', 'edil', ['Senza lattosio'], '', '', 'stay', [[-20, -15], [-6, 4]], '2'],
            ['Luca', 'Ferri', 'elettro', ['Vegetariano'], '', '', 'stay', [[-13, -9], [-2, 2]], '7'],
            ['Paolo', 'Russo', 'elettro', [], '', 'Porzioni abbondanti', 'stay', [[-13, -9], [-2, 2]], '7'],
            ['Davide', 'Greco', 'elettro', ['Senza glutine'], '', '', 'stay', [[-13, -9], [-2, 2]], '7'],
            ['Matteo', 'Lombardi', 'termo', [], '', '', 'stay', [[-3, 5]], '3'],
            ['Simone', 'Marini', 'termo', [], 'Allergia alla frutta secca', '', 'stay', [[1, 3]], null],   // notti chieste, camera da assegnare
            ['Roberto', 'Costa', 'ponteggi', [], '', '', 'day', [], null],
            ['Fabio', 'Rinaldi', 'ponteggi', ['No pesce'], '', '', 'day', [], null],
            ['Elena', 'Fontana', null, [], '', '', 'misto', [], null],
        ];
        $ids = [];
        foreach ($guests as $i => [$first, $last, $company, $diets, $needs, $knote]) {
            $created = $first === 'Simone' ? -2 : -27 + $i;
            q(
                'INSERT INTO ' . t('guests') . ' (venue_id, first_name, last_name, phone, email, password_hash, company_id, diets, needs, kitchen_note, staff_note, status, consent_at, last_login_at, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$vid, $first, $last, sprintf('30000000%02d', $i + 1), '', $hash, $company ? $co[$company] : null, implode('|', $diets), $needs, $knote, '', 'confermato',
                    $diets || $needs !== '' ? $at($created) : null, $at(0, sprintf('07:%02d:00', 10 + $i * 3)), $at($created), $at($created)]
            );
            $ids[$i] = (int) $pdo->lastInsertId();
        }
        // Due registrazioni nuove, da confermare in reception.
        $pending = [
            ['Antonio', 'Serra', $co['doppione'], '', [[1, 2]]],
            ['Chiara', 'Ricci', $co['ponteggi'], 'Vegetariano', []],
        ];
        $pendingIds = [];
        foreach ($pending as $j => [$first, $last, $companyId, $diet]) {
            q(
                'INSERT INTO ' . t('guests') . ' (venue_id, first_name, last_name, phone, email, password_hash, company_id, diets, needs, kitchen_note, staff_note, status, consent_at, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$vid, $first, $last, sprintf('30000000%02d', 21 + $j), '', $hash, $companyId, $diet, '', '', '', 'attesa', $diet !== '' ? now() : null, $at(0, '07:30:00'), $at(0, '07:30:00')]
            );
            $pendingIds[$j] = (int) $pdo->lastInsertId();
        }

        /* ---------- Notti ---------- */
        $inStay = static function (array $stays, int $off): bool {
            foreach ($stays as [$a, $b]) {
                if ($off >= $a && $off <= $b) {
                    return true;
                }
            }
            return false;
        };
        foreach ($guests as $i => $g) {
            foreach ($g[7] as [$a, $b]) {
                for ($off = $a; $off <= $b; $off++) {
                    $wait = $g[8] === null;
                    q(
                        'INSERT INTO ' . t('nights') . ' (venue_id, guest_id, day, room_id, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
                        [$vid, $ids[$i], day_add($today, $off), $wait ? null : $room[$g[8]], $wait ? 'richiesta' : 'confermata', $at(min($a, 0) - 1), $at(min($a, 0) - 1)]
                    );
                }
            }
        }
        foreach ($pending[0][4] as [$a, $b]) {
            for ($off = $a; $off <= $b; $off++) {
                q('INSERT INTO ' . t('nights') . ' (venue_id, guest_id, day, room_id, status, created_at, updated_at) VALUES (?, ?, ?, NULL, ?, ?, ?)', [$vid, $pendingIds[0], day_add($today, $off), 'richiesta', now(), now()]);
            }
        }

        /* ---------- Menù ---------- */
        $pool = [
            'primo' => ["Penne all'arrabbiata", 'Risotto ai funghi', 'Minestrone di verdure', 'Pasta al pomodoro', 'Zuppa di legumi', 'Lasagne al forno', 'Spaghetti aglio, olio e peperoncino', 'Gnocchi al ragù', 'Pasta e ceci', 'Tagliatelle al ragù'],
            'secondo' => ['Arrosto di vitello', 'Petto di pollo alla griglia', 'Frittata di verdure', 'Scaloppina al limone', 'Caprese', 'Polpette al sugo', 'Merluzzo al forno', 'Salsiccia e fagioli', 'Bistecca di maiale', 'Torta salata'],
            'contorno' => ['Patate al forno', 'Insalata mista', 'Verdure grigliate', 'Spinaci saltati', "Fagiolini all'olio", 'Purè di patate'],
        ];
        $menus = [];
        for ($off = -21; $off <= 2; $off++) {
            $day = day_add($today, $off);
            foreach (['pranzo', 'cena'] as $meal) {
                if ($off === 2 && $meal === 'cena') {
                    continue;   // dopodomani a cena il menù non c'è ancora
                }
                $data = menu_blank($v);
                foreach (['primo' => 3, 'secondo' => 3, 'contorno' => 2] as $course => $n) {
                    $list = $pool[$course];
                    usort($list, static fn ($x, $y) => crc32($day . $meal . $x) <=> crc32($day . $meal . $y));
                    // Almeno un primo senza glutine e un secondo vegetariano, così ogni dieta trova qualcosa.
                    $must = $course === 'primo' ? 'Risotto ai funghi' : ($course === 'secondo' ? 'Frittata di verdure' : null);
                    $picked = array_slice($list, 0, $n);
                    if ($must !== null && !in_array($must, $picked, true) && !in_array($course === 'primo' ? 'Minestrone di verdure' : 'Caprese', $picked, true)) {
                        $picked[$n - 1] = $must;
                    }
                    $data['courses'][$course] = array_map(static fn ($name) => ['id' => substr(md5($day . $meal . $name), 0, 8), 'name' => $name, 'price' => 0], $picked);
                }
                $menu = ['day' => $day, 'meal' => $meal, 'mode' => 'scelta', 'cutoff' => null, 'status' => 'bozza', 'data' => $data, 'show_prices' => 0];
                $cut = meal_cutoff($v, $day, $meal, $menu);
                $status = $off < 0 ? 'chiuso' : ($off === 0 ? (time() >= $cut ? 'chiuso' : 'pubblicato') : ($off === 1 ? 'pubblicato' : 'bozza'));
                $published = $status !== 'bozza' ? $at($off - 1, '18:30:00') : null;
                q(
                    'INSERT INTO ' . t('menus') . ' (venue_id, day, meal, status, mode, cutoff, show_prices, data, photo, note, published_at, closed_at, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, NULL, 0, ?, ?, ?, ?, ?, ?, ?)',
                    [$vid, $day, $meal, $status, 'scelta', json_encode($data, JSON_UNESCAPED_UNICODE), '', '', $published,
                        $status === 'chiuso' ? date('Y-m-d H:i:s', min($cut, time())) : null, $at($off - 1, '18:00:00'), $at($off - 1, '18:30:00')]
                );
                $menu['status'] = $status;
                $menus[$day . '|' . $meal] = $menu;
            }
        }

        /* ---------- Prenotazioni ---------- */
        $choose = static function (array $menu, array $diets, string $seed) use ($v): array {
            $h = crc32($seed);
            $courses = $menu['data']['courses'];
            $dish = [];
            foreach ($courses as $course => $list) {
                $ok = array_values(array_filter($list, static fn ($d) => demo_dish_ok((string) $d['name'], $diets)));
                if ($ok) {
                    $dish[$course] = $ok[($h >> (3 + strlen($course))) % count($ok)]['id'];
                }
            }
            // Formula e percorso: il primo che trova un piatto adatto per ogni portata richiesta.
            $tries = [['completo', 0], ['base', 0], ['base', 1]];
            $start = $h % 3;
            for ($k = 0; $k < 3; $k++) {
                [$fid, $path] = $tries[($start + $k) % 3];
                $need = $fid === 'completo' ? ['primo', 'secondo', 'contorno'] : ($path === 0 ? ['primo'] : ['secondo', 'contorno']);
                if (count(array_intersect_key($dish, array_flip($need))) === count($need)) {
                    $drink = ($h >> 9) % 6 === 0 ? 'vino' : (($h >> 9) % 9 === 1 ? 'bibita' : 'acqua');
                    $extras = array_values(array_filter([($h >> 12) % 3 !== 0 ? 'caffe' : null, ($h >> 14) % 4 === 0 ? 'dolce' : null, ($h >> 16) % 9 === 0 ? 'amaro' : null]));
                    [$c] = choices_read($v, $menu, ['formula' => $fid, 'path' => $path, 'dish' => $dish, 'drink' => $drink, 'extras' => $extras]);
                    if (is_array($c)) {
                        return [json_encode($c, JSON_UNESCAPED_UNICODE), choices_amount($v, $c)];
                    }
                }
            }
            return ['', null];
        };
        $eats = static function (array $g, int $off, string $meal) use ($inStay, $today): bool {
            $dow = (int) date('N', (int) strtotime(day_add($today, $off)));
            return match ($g[6]) {
                'stay' => $meal === 'cena' ? $inStay($g[7], $off) : ($inStay($g[7], $off - 1) && $dow !== 7),
                'day' => $meal === 'pranzo' && $dow <= 5,
                default => $meal === 'pranzo' ? $dow <= 5 : in_array($dow, [2, 4], true),
            };
        };
        $book = static function (int $guestId, string $day, string $meal, string $status, string $choices, ?float $amount, string $variant, string $vstatus, ?string $answered, string $created) use ($vid): void {
            q(
                'INSERT INTO ' . t('bookings') . ' (venue_id, guest_id, day, meal, status, choices, amount, variant, variant_status, source, answered_at, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$vid, $guestId, $day, $meal, $status, $choices, $amount, $variant, $vstatus, 'ospite', $answered, $created, $answered ?? $created]
            );
        };
        $variants = [
            'Davide|-2|cena' => ['Secondo senza panatura', 'approvata'],
            'Giuseppe|-5|pranzo' => ['Doppia porzione di pasta', 'approvata'],
            'Luca|-1|pranzo' => ['Pasta senza formaggio', 'rifiutata'],
            'Marco|1|cena' => ['Penne in bianco con burro e parmigiano', 'attesa'],
        ];
        for ($off = -21; $off <= 4; $off++) {
            $day = day_add($today, $off);
            foreach (['pranzo', 'cena'] as $meal) {
                $menu = $menus[$day . '|' . $meal] ?? null;
                foreach ($guests as $i => $g) {
                    if (!$eats($g, $off, $meal)) {
                        continue;
                    }
                    $seed = $g[0] . $day . $meal;
                    $h = crc32($seed);
                    $created = $at(min($off, 0) - 2, '20:00:00');
                    $var = $variants[$g[0] . '|' . $off . '|' . $meal] ?? null;
                    if ($off < 0) {
                        if ($h % 17 === 0 && !$var) {
                            $book($ids[$i], $day, $meal, 'disdetto', '', null, '', '', null, $created);
                        } elseif ($h % 29 === 1 && !$var) {
                            $book($ids[$i], $day, $meal, 'scaduto', '', null, '', '', null, $created);
                        } else {
                            [$c, $amount] = $choose($menu, $g[3], $seed);
                            $book($ids[$i], $day, $meal, 'confermato', $c, $amount, $var[0] ?? '', $var[1] ?? '', $at($off, $meal === 'pranzo' ? '08:20:00' : '13:40:00'), $created);
                        }
                        continue;
                    }
                    if ($h % 17 === 0 && !$var) {
                        continue;   // questo pasto non l'ha prenotato
                    }
                    $answer = $menu && $menu['status'] !== 'bozza'
                        && ($menu['status'] === 'chiuso' || $var || ($off === 0 ? $h % 3 !== 0 : $h % 2 === 0));
                    if ($answer) {
                        [$c, $amount] = $choose($menu, $g[3], $seed);
                        $book($ids[$i], $day, $meal, 'confermato', $c, $amount, $var[0] ?? '', $var[1] ?? '', date('Y-m-d H:i:s', min(time(), strtotime($at($off - ($meal === 'pranzo' ? 1 : 0), $meal === 'pranzo' ? '21:10:00' : '09:15:00')))), $created);
                    } else {
                        $book($ids[$i], $day, $meal, 'prenotato', '', null, '', '', null, $created);
                    }
                }
            }
        }
        // Le registrazioni nuove hanno già segnato qualche pasto (contano in cucina dopo la conferma).
        foreach ([[0, 1, 'cena'], [0, 2, 'cena'], [1, 1, 'pranzo'], [1, 2, 'pranzo'], [1, 3, 'pranzo']] as [$j, $off, $meal]) {
            $book($pendingIds[$j], day_add($today, $off), $meal, 'prenotato', '', null, '', '', null, now());
        }
        $bookingId = static fn (int $guestId, string $day, string $meal): ?int => ($b = booking_get($guestId, $day, $meal)) ? (int) $b['id'] : null;

        /* ---------- Richieste ---------- */
        $gid = static function (string $first) use ($guests, $ids): int {
            foreach ($guests as $i => $g) {
                if ($g[0] === $first) {
                    return $ids[$i];
                }
            }
            return 0;
        };
        $req = static function (int $guestId, string $kind, ?int $booking, string $day, string $meal, string $body, string $status, string $note, ?int $by, string $created) use ($vid): void {
            q(
                'INSERT INTO ' . t('requests') . ' (venue_id, guest_id, kind, booking_id, day, meal, body, status, staff_note, handled_by, handled_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$vid, $guestId, $kind, $booking, $day, $meal, $body, $status, $note, $by, $status === 'attesa' ? null : $created, $created]
            );
        };
        $reception = (int) q_value('SELECT id FROM ' . t('staff') . ' WHERE venue_id = ? AND role = ?', [$vid, 'reception']);
        foreach ($variants as $key => [$text, $vstatus]) {
            [$first, $off, $meal] = explode('|', $key);
            $day = day_add($today, (int) $off);
            $req($gid($first), 'variante', $bookingId($gid($first), $day, $meal), $day, $meal, $text, $vstatus === 'attesa' ? 'attesa' : ($vstatus === 'approvata' ? 'approvata' : 'rifiutata'),
                $vstatus === 'rifiutata' ? 'La pasta esce già condita' : '', $vstatus === 'attesa' ? null : $reception, $vstatus === 'attesa' ? now() : $at((int) $off, '09:00:00'));
        }
        // Notte da disdire (già confermata) e, se il pasto di oggi è già chiuso, una disdetta e un'aggiunta fuori orario.
        $req($gid('Matteo'), 'notte', null, day_add($today, 5), '', '', 'attesa', '', null, now());
        $late = ($menus[$today . '|cena']['status'] ?? '') === 'chiuso' ? 'cena' : (($menus[$today . '|pranzo']['status'] ?? '') === 'chiuso' ? 'pranzo' : null);
        if ($late !== null) {
            $b = $bookingId($gid('Giuseppe'), $today, $late);
            if ($b) {
                $req($gid('Giuseppe'), 'disdetta', $b, $today, $late, '', 'attesa', '', null, now());
            }
            if (!booking_active(booking_get($gid('Elena'), $today, $late))) {
                $req($gid('Elena'), 'aggiunta', $bookingId($gid('Elena'), $today, $late), $today, $late, '', 'attesa', '', null, now());
            }
        }

        /* ---------- Avvisi agli ospiti ---------- */
        $notice = static function (int $guestId, string $title, string $body, string $href, ?string $read, string $created) use ($vid): void {
            q('INSERT INTO ' . t('notices') . ' (venue_id, guest_id, title, body, href, read_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)', [$vid, $guestId, $title, $body, $href, $read, $created]);
        };
        $tomorrow = day_add($today, 1);
        foreach ($guests as $i => $g) {
            if ($g[0] !== 'Simone') {
                $notice($ids[$i], 'Registrazione confermata', 'Da ora le tue prenotazioni arrivano in cucina.', 'pasti.php', $at(-26), $at(-27 + $i));
            }
            foreach (['pranzo', 'cena'] as $meal) {
                $b = booking_get($ids[$i], $tomorrow, $meal);
                if ($b && $b['status'] === 'prenotato') {
                    $notice($ids[$i], 'È pronto il menù ' . ($meal === 'pranzo' ? 'del pranzo' : 'della cena') . ' di domani', 'Guardalo e rispondi entro le ' . date('H:i', meal_cutoff($v, $tomorrow, $meal)) . ': dopo, il pasto viene disdetto.', 'menu.php?g=' . $tomorrow . '&p=' . $meal, null, $at(0, '18:30:00') > now() ? now() : $at(0, '18:30:00'));
                }
            }
        }
        $notice($gid('Davide'), 'Variante approvata: cena di ' . day_it(day_add($today, -2), 'short'), '', 'pasti.php', $at(-2, '15:00:00'), $at(-2, '14:00:00'));
        $notice($gid('Luca'), 'Variante non disponibile: pranzo di ieri', 'Per chiarimenti chiama la reception.', 'pasti.php', null, $at(-1, '09:30:00'));

        /* ---------- Registro ---------- */
        foreach ([
            [-30, 'locale_creato', 'Locanda Demo · locale dimostrativo', 'sistema', null, ''],
            [-29, 'staff_aggiunto', DEMO_STAFF['reception'][1] . ' · Reception', 'staff', $ownerId, DEMO_STAFF['admin'][1]],
            [-29, 'staff_aggiunto', DEMO_STAFF['cucina'][1] . ' · Cucina', 'staff', $ownerId, DEMO_STAFF['admin'][1]],
            [-1, 'menu_pubblicato', 'Pranzo ' . $today, 'staff', $ownerId, DEMO_STAFF['admin'][1]],
            [0, 'menu_pubblicato', 'Cena ' . $tomorrow, 'staff', $ownerId, DEMO_STAFF['admin'][1]],
        ] as [$off, $action, $details, $actor, $actorId, $actorName]) {
            q('INSERT INTO ' . t('events') . ' (venue_id, actor, actor_id, actor_name, action, details, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)', [$vid, $actor, $actorId, $actorName, $action, $details, $at($off, '18:30:00') > now() ? now() : $at($off, '18:30:00')]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        require_once CM_APP . '/platform/maint.php';
        venue_delete($vid);
        throw $e;
    }

    setting_set('pf_demo', ['venue_id' => $vid, 'secret' => secret_encrypt($password), 'created_at' => now()]);
    return ['venue_id' => $vid, 'slug' => (string) $v['slug'], 'password' => $password];
}
