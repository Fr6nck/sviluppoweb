<?php
/** Rotte di amministrazione. $r è il Router creato in public/index.php. */

use MHW\{Assistente, Auth, Billing, Config, Db, Demo, Entitlements, Gestione, Impostazioni, Inviti, Log, Media, Migrator, Plans, RateLimit, Richiami, Stats, Storages, Stripe, Subscriptions, Support, Traduttore, View};

/**
 * Il quadro: quanti clienti, quanto hanno pagato davvero, quanto viene letto
 * quello che scrivono. Numeri interrogati adesso. Gli abbonamenti di prova,
 * manuali e dimostrativi NON entrano nell'incasso.
 */
$r->get('/admin', function () {
    Auth::requireAdmin();
    Demo::vetrinaAutomatica();
    if (($esitoM = MHW\Impostazioni::esitoMittente()) !== '') Support::flash($esitoM);   // 9H, una volta sola
    // 7F: i clienti di prova non entrano nel quadro (9R: e nemmeno gli account staff).
    $np = fn(string $col) => Gestione::senzaProvaNeStaff($col);
    $numeri = [
        'clienti'    => (int) Db::val("SELECT COUNT(*) FROM users WHERE role = 'host' AND email NOT LIKE ?" . Gestione::utentiVeri('id'), ['%@' . Demo::DOMINIO], 0),
        'abbonati'   => (int) Db::val("SELECT COUNT(DISTINCT account_id) FROM subscriptions WHERE status IN ('active','trialing')
                                        AND provider = 'stripe' AND current_period_end > ?" . $np('account_id'), [Support::now()], 0),
        'guide'      => (int) Db::val('SELECT COUNT(*) FROM properties WHERE is_demo = 0' . $np('account_id'), [], 0),
        'pubblicate' => (int) Db::val("SELECT COUNT(*) FROM properties WHERE status = 'published' AND is_demo = 0" . $np('account_id'), [], 0),
        'aperture'   => Stats::totalViews(30),
        'incassato'  => Gestione::incassatoStripe($np('account_id')),   // 9O: dopo gli sconti
        'in_attesa'  => (int) Db::val("SELECT COUNT(*) FROM orders WHERE status IN ('pending','awaiting')" . $np('account_id'), [], 0),
        'rinnovo_off'=> (int) Db::val("SELECT COUNT(*) FROM subscriptions WHERE status = 'active' AND cancel_at_period_end = 1" . $np('account_id'), [], 0),
        'falliti'    => (int) Db::val("SELECT COUNT(*) FROM subscriptions WHERE status = 'past_due'" . $np('account_id'), [], 0),
    ];
    $piani = Db::all(
        "SELECT pk.name, pk.public, pk.active, pv.*,
                (SELECT COUNT(*) FROM subscriptions s WHERE s.package_version_id = pv.id AND s.status IN ('active','trialing')" . $np('s.account_id') . ") AS clienti
         FROM package_versions pv JOIN packages pk ON pk.id = pv.package_id
         WHERE pv.is_current = 1 AND pk.public = 1 AND pk.active = 1 ORDER BY pk.sort, pk.id");
    // 9N · «Clienti attivi» del listino come i numeri sopra: senza clienti di esempio né abbonamenti dimostrativi.
    foreach ($piani as &$pL) $pL['clienti'] = (int) Db::val("SELECT COUNT(*) FROM subscriptions s JOIN accounts a ON a.id = s.account_id JOIN users u ON u.id = a.user_id
        WHERE s.package_version_id = ? AND s.status IN ('active','trialing') AND s.provider <> 'dimostrazione' AND u.email NOT LIKE ?" . $np('a.id'), [$pL['id'], '%@' . Demo::DOMINIO], 0);
    unset($pL);
    $ordini = Db::all(
        'SELECT o.*, pk.name AS package, u.email, u.name AS cliente, a.id AS account_id FROM orders o
         JOIN package_versions pv ON pv.id = o.package_version_id JOIN packages pk ON pk.id = pv.package_id
         JOIN accounts a ON a.id = o.account_id JOIN users u ON u.id = a.user_id WHERE 1 = 1' . $np('a.id') . ' ORDER BY o.id DESC LIMIT 8');

    $avvisi = [];   // 9N · da configurare (un solo riquadro chiuso); le cose da fare oggi stanno in $daFare
    $daFare = [];
    // 9K · I limiti di PHP che fermano le foto prima ancora del sito.
    $bassi = array_filter(Media::limitiServer(), fn($l) => $l[3]);
    if ($bassi) $avvisi[] = ['Il server limita il caricamento delle foto', 'Oggi: ' . implode(', ', array_map(fn($l) => $l[0] . ' = ' . $l[1], $bassi))
        . '. Chiedi all\'hosting (in cPanel: «Seleziona versione PHP» → Opzioni, o «MultiPHP INI Editor») upload_max_filesize = 20M, post_max_size = 20M e memory_limit = 256M. Le foto prese dal browser arrivano già compresse; questi limiti servono a chi carica senza JavaScript o foto molto grandi.', '/admin/diagnostica', 'Apri la diagnostica'];
    // Il webhook di Stripe rifiutato o mai arrivato (le guide si attivano lo stesso con il recupero, ma in ritardo).
    if (Stripe::enabled() && ($pw = MHW\RecuperoPagamenti::problema())) $avvisi[] = [$pw[0], $pw[1], '/admin/impostazioni#stripe', 'Apri Impostazioni → Stripe'];
    if (!Stripe::enabled()) $avvisi[] = ['Stripe non è configurato', 'Senza chiave segreta e segreto del webhook nessuno può pubblicare: i clienti possono preparare la guida ma non pagarla. Inseriscili nelle Impostazioni.', '/admin/impostazioni#stripe'];
    if (Config::get('mail')['transport'] === 'log') $avvisi[] = ['La posta non parte', 'Le email di conferma e di recupero della password finiscono nei registri della posta (storage/logs/mail-AAAA-MM-GG.log). Imposta il server SMTP nelle Impostazioni.', '/admin/impostazioni#posta'];
    // 9H · Il mittente delle email deve stare sul dominio del sito (SPF/DKIM): altrimenti spam o rifiuto.
    $mittente = (string) (Config::get('mail')['from'] ?? ''); $dominioSito = (string) parse_url((string) Config::get('base_url'), PHP_URL_HOST);
    if (MHW\Mailer::mittenteFuoriDominio()) {
        $avvisi[] = ['Il mittente delle email non è sul dominio del sito', 'Le email partono da ' . $mittente . ' ma il sito è su ' . $dominioSito . ': senza SPF e DKIM del dominio del mittente rischiano lo spam o il rifiuto. Usa ' . Support::contatto() . ' (Impostazioni → Posta).', '/admin/impostazioni#posta'];
    }
    if (Config::get('storage')['driver'] !== 's3') $avvisi[] = ['Foto e PDF stanno sul disco del server', 'In produzione usa Amazon S3: bucket e credenziali si inseriscono nelle Impostazioni.', '/admin/impostazioni#archivio'];
    $usati = Traduttore::usati(); $tetto = Traduttore::tetti()['sito'];
    if ($tetto > 0 && $usati > $tetto * 0.8) $avvisi[] = ['Traduzioni suggerite oltre l\'80% del tetto del mese', 'Usati ' . number_format($usati, 0, ',', '.') . ' caratteri su ' . number_format($tetto, 0, ',', '.') . ': al tetto le richieste si fermano fino al primo del mese.', '/admin/traduzioni'];
    // 9F · «Guida di esempio» nel pannello preferisce la vetrina: se non c'è, mostra Casa di Lucia (un cliente di esempio).
    if (Demo::esempio() !== null && !Demo::vetrinaAttuale()) $avvisi[] = ['La guida di esempio è quella dei clienti di esempio: rifai la vetrina', 'Il link «Guida di esempio» del pannello apre una guida dei clienti di esempio perché non c\'è una vetrina pubblicata. Dalla scheda del cliente della vetrina usa «Rifai la vetrina» (o cancella storage/vetrina-automatica.txt per ricrearla da sola).'];
    if (Demo::presente()) $avvisi[] = ['Ci sono ancora i clienti di esempio', 'Sono account veri con una password nota. Toglili prima di aprire al pubblico.'];
    // 9C · Senza base_url i link e le icone nelle email partite dal cron (senza un browser) puntano a localhost.
    if ((string) Config::get('base_url') === '') $avvisi[] = ['Indirizzo del sito non impostato', 'Senza «Indirizzo pubblico del sito» i link e le icone nelle email mandate dal cron puntano a localhost (il marchio è incorporato e si vede comunque). Impostalo in Sito.', '/admin/impostazioni#sito'];
    // 9A · I controlli sui prezzi di Stripe prima del Checkout: un errore ferma i pagamenti di quella versione.
    foreach (Stripe::esitiVerifica() as $pvId => $esito) {
        // 9R · Il controllo della sessione (importi del Checkout diversi dal riepilogo): Adaptive Pricing, IVA inclusa sul Price…
        if ($pvId === 'sessione' && empty($esito['ok'])) { $avvisi[] = ['Il Checkout di Stripe chiede importi diversi dal riepilogo', 'L\'ultimo pagamento è stato fermato: ' . (string) ($esito['errore'] ?? '') . ' Controlla in Stripe che Adaptive Pricing sia spento e che i prezzi siano «IVA esclusa»; poi Amministrazione → Diagnostica → Stripe.', '/admin/diagnostica', 'Apri la Diagnostica']; continue; }
        if (str_starts_with((string) $pvId, 'modo:')) continue;
        if (empty($esito['ok'])) $avvisi[] = ['Prezzi su Stripe diversi dal listino', 'Il pagamento della versione ' . (int) $pvId . ' è fermo: ' . (string) ($esito['errore'] ?? '') . ' Correggi i Price su Stripe o svuota i Price ID nella versione (Piani): il sito li ricrea alla prossima vendita.', '/admin/pacchetti', 'Apri i piani'];
    }
    if (MHW\Preventivi::disponibili() && ($nuovi = (int) Db::val("SELECT COUNT(*) FROM preventivi WHERE stato = 'nuovo'", [], 0)) > 0) {
        $daFare[] = ['Preventivi nuovi', $nuovi, 'risposta promessa entro un giorno lavorativo', '/admin/preventivi'];
    }
    if (MHW\Preventivi::conProposta() && ($richiama = (int) Db::val("SELECT COUNT(*) FROM preventivi WHERE stato <> 'chiuso' AND ricontatta_il IS NOT NULL AND ricontatta_il <= ?", [gmdate('Y-m-d')], 0)) > 0) {
        $daFare[] = ['Preventivi da ricontattare', $richiama, 'la data scelta è oggi o è passata', '/admin/preventivi'];
    }
    if (MHW\Partner::disponibili() && ($nuoviP = (int) Db::val("SELECT COUNT(*) FROM partner_richieste WHERE stato = 'nuovo'", [], 0)) > 0) {
        $daFare[] = ['Richieste di partnership', $nuoviP, 'dal modulo «Diventa partner»', '/admin/partner'];
    }
    // 9L · «Giudizi e vetrina»: gli aderenti e le schede o i giudizi da approvare.
    if (MHW\Vetrina::disponibili()) {
        MHW\Vetrina::sincronizza();
        $nv = MHW\Vetrina::elenco('vetrina')['numeri'];
        if ((int) ($nv['schede_da_approvare'] ?? 0) > 0) $daFare[] = ['Schede da approvare', (int) $nv['schede_da_approvare'], '«Le guide dei nostri host»', '/admin/giudizi'];
        if ((int) ($nv['giudizi_da_approvare'] ?? 0) > 0) $daFare[] = ['Giudizi da approvare', (int) $nv['giudizi_da_approvare'], '«Cosa dicono di noi»', '/admin/giudizi?scheda=giudizi&stelle=tutti'];
    }
    // 9O · Le commissioni dei partner da liquidare: il periodo (trimestre o mese) è finito.
    if (MHW\ProgrammaPartner::disponibili() && ($scadP = MHW\ProgrammaPartner::scaduti()) > 0) {
        $daFare[] = ['Commissioni partner da liquidare', $scadP, 'periodo finito: bonifico su fattura del partner', '/admin/cassa/partner'];
    }
    // 9O · I rimborsi senza la nota di credito fatta in Adamo.
    if (Migrator::columnExists('rimborsi', 'nota_credito_at') && ($note = Db::all("SELECT account_id FROM rimborsi WHERE stato <> 'fallito' AND nota_credito_at IS NULL ORDER BY id"))) {
        $daFare[] = ['Note di credito da fare in Adamo', count($note), 'rimborsi senza la spunta «fatta»', count($note) === 1 ? '/admin/cliente/' . (int) $note[0]['account_id'] . '#rimborsi' : '/admin/cassa?mese=' . gmdate('Y-m')];
    }
    // 9Q · Le bozze ferme da 21 giorni o più (account senza guide online): l'email del giorno 21 propone di finirle noi.
    if (($ferme = count(MHW\Bozze::ferme())) > 0) $daFare[] = [$ferme === 1 ? '1 bozza ferma da 21+ giorni' : "$ferme bozze ferme da 21+ giorni", $ferme, 'da «Entra come cliente» la finiamo noi', '/admin/clienti?stato=bozze21'];
    $anomalie = Gestione::contaAnomalie();
    if ($numeri['falliti'] > 0) $daFare[] = ['Rinnovi falliti', $numeri['falliti'], 'carte da aggiornare: la guida è offline', '/admin/anomalie'];
    $nonConfermate = (int) Db::val("SELECT COUNT(*) FROM users WHERE role = 'host' AND email_verified_at IS NULL AND created_at <= ? AND email NOT LIKE ?" . Gestione::utentiVeri('id'),
                                   [gmdate('Y-m-d\TH:i:s\Z', time() - 7 * 86400), '%@' . Demo::DOMINIO], 0);
    if ($nonConfermate > 0) $daFare[] = ['Email non confermate da più di 7 giorni', $nonConfermate, 'forse nello spam o scritte male', '/admin/anomalie'];
    if ($anomalie['alta'] > 0) $daFare[] = ['Anomalie da guardare subito', $anomalie['alta'], 'pagamenti o rinnovi che non tornano', '/admin/anomalie'];
    // 9D · I clienti in omaggio e quelli il cui primo addebito è vicino.
    $numeri['omaggio'] = MHW\Omaggi::numeri(30);
    $adesso = Gestione::adesso();
    $numeri['arr'] = $adesso['arr'];
    $numeri['rinnovi30'] = $adesso['rinnovi']['30'];
    $numeri['scadono30'] = count(array_filter(Gestione::scadenze(30), fn($x) => !$x['automatico'] && !$x['finito']));
    $numeri['anomalie'] = $anomalie;
    // 9N · I quattro numeri in cima, contati tutti allo stesso modo: senza clienti di esempio, di prova e abbonamenti dimostrativi.
    $attivi = Db::all("SELECT DISTINCT s.account_id FROM subscriptions s JOIN accounts a ON a.id = s.account_id JOIN users u ON u.id = a.user_id
                       WHERE s.status IN ('active','trialing') AND s.provider <> 'dimostrazione' AND s.current_period_end > ? AND u.email NOT LIKE ?" . $np('a.id'),
                      [Support::now(), '%@' . Demo::DOMINIO]);
    $numeri['attivi'] = count($attivi);
    $numeri['online'] = 0;
    foreach (Db::all("SELECT p.* FROM properties p JOIN accounts a ON a.id = p.account_id JOIN users u ON u.id = a.user_id
                      WHERE p.status = 'published' AND p.is_demo = 0 AND p.archived_at IS NULL AND u.email NOT LIKE ?" . $np('a.id'), ['%@' . Demo::DOMINIO]) as $pO) {
        if (Subscriptions::propertyOnline($pO)) $numeri['online']++;
    }
    $mese = gmdate('Y-m');
    $numeri['mese'] = array_sum(array_map(fn($p) => $p['tipo'] === 'rimborso' ? 0 : $p['imponibile'], Gestione::pagamenti($mese . '-01', gmdate('Y-m-01', strtotime($mese . '-01 +1 month')))));

    View::out('admin/dashboard', ['numeri' => $numeri, 'piani' => $piani, 'ordini' => $ordini, 'avvisi' => $avvisi, 'daFare' => $daFare, 'funnel' => Stats::funnel(30),
                                  'esempi' => Demo::presente(), 'nav' => 'admin'], 'layout/cms');
});

// ------------------------------------------------------------------- clienti
/* 9I · «Cerca» in ogni pagina di Amministrazione: un risultato apre la scheda, più risultati un elenco con il perché. */
$r->get('/admin/cerca', function () {
    Auth::requireAdmin();
    $q = trim(mb_substr((string) ($_GET['q'] ?? ''), 0, 200));
    $ris = $q !== '' ? MHW\Codici::trova($q) : [];
    // Nel registro la ricerca, ma non il testo se contiene un'email (privacy).
    if ($q !== '') Auth::audit('admin.search', null, ['q' => str_contains($q, '@') ? '(un indirizzo email)' : mb_substr($q, 0, 80), 'risultati' => count($ris)]);
    if (count($ris) === 1) {
        $ev = (string) $ris[0]['evidenzia'];
        Support::redirect('/admin/cliente/' . (int) $ris[0]['account_id'] . ($ev !== '' ? '?evidenzia=' . rawurlencode($ev) . '#' . $ev : ''));
    }
    $righe = [];
    foreach ($ris as $x) {
        $c = Db::one('SELECT a.id, a.' . (MHW\Codici::disponibili() ? 'code' : 'id AS code') . ', u.name, u.email, u.role FROM accounts a JOIN users u ON u.id = a.user_id WHERE a.id = ?', [$x['account_id']]);
        if (!$c) continue;
        // 10H · un account eliminato si trova ancora (per i pagamenti), senza email
        if ($c['role'] === MHW\EliminaAccount::RUOLO) { $righe[] = ['email' => '', 'perche' => $x['perche'], 'evidenzia' => '', 'piano' => '—', 'stato' => 'Eliminato'] + $c; continue; }
        $sub = Subscriptions::active((int) $c['id']);
        $gov = Subscriptions::governingVersionId((int) $c['id']);
        $righe[] = $c + ['perche' => $x['perche'], 'evidenzia' => $x['evidenzia'], 'piano' => $gov ? (Plans::version($gov)['name'] ?? '—') : '—',
                         'stato' => $sub ? ($sub['status'] === 'past_due' ? 'Pagamento non riuscito' : ($sub['status'] === 'trialing' ? 'In omaggio' : 'Attivo')) : 'Senza abbonamento attivo'];
    }
    View::out('admin/cerca', ['q' => $q, 'righe' => $righe, 'nav' => 'clienti'], 'layout/cms');
});

$r->get('/admin/clienti', function () {
    Auth::requireAdmin();
    $cerca = trim((string) ($_GET['q'] ?? ''));
    $sql = 'SELECT u.id AS user_id, u.email, u.name, u.created_at, u.email_verified_at, a.id AS account_id, ' . Gestione::colonnaProva() . ' AS is_test'
         . (MHW\Staff::disponibili() ? ', a.is_staff' : ', 0 AS is_staff')
         . (MHW\Codici::disponibili() ? ', a.code' : ", '' AS code") . '
            FROM users u JOIN accounts a ON a.user_id = u.id WHERE u.role = ?';
    $args = ['host'];
    if ($cerca !== '') {
        // 9I · La stessa ricerca di «Cerca» (codici, email, telefono, CIN, Stripe…), non più solo nome, email e struttura.
        $idsC = array_map(fn($x) => (int) $x['account_id'], MHW\Codici::trova($cerca, 300));
        $sql .= $idsC ? ' AND a.id IN (' . implode(',', array_fill(0, count($idsC), '?')) . ')' : ' AND 1 = 0';
        array_push($args, ...$idsC);
    }
    $rows = Db::all($sql . ' ORDER BY u.id DESC LIMIT 300', $args);
    // 9N · L'ultimo pagamento e l'incassato di ognuno, dalla stessa fonte della Cassa (clienti di esempio e di prova esclusi).
    $incassi = [];
    foreach (Gestione::pagamenti('2000-01-01', gmdate('Y-m-d\TH:i:s\Z', time() + 86400)) as $pg) {
        $x = &$incassi[$pg['account_id']];
        $x ??= ['ultimo' => '', 'netto' => 0];
        $x['netto'] += $pg['tipo'] === 'rimborso' ? $pg['totale'] : $pg['imponibile'];
        if ($pg['tipo'] !== 'rimborso') $x['ultimo'] = max($x['ultimo'], $pg['data']);
        unset($x);
    }
    foreach ($rows as &$row) {
        $aid = (int) $row['account_id'];
        $sub = Subscriptions::active($aid);
        $gov = Subscriptions::governingVersionId($aid);
        $v = $gov ? Plans::version($gov) : null;
        $row['plan'] = $v['name'] ?? '—';
        $row['plan_version'] = $v['version'] ?? null;
        // 9R · «Portfolio · 3 strutture»: quelle pagate (abbonamento) o, prima del pagamento, quelle scelte.
        $row['plan_q'] = $v && Plans::perProperty($v) ? (int) ($sub['quantity'] ?? Db::val('SELECT intended_quantity FROM accounts WHERE id = ?', [$aid], 1)) : 0;
        $props = Db::all('SELECT name, city, status FROM properties WHERE account_id = ? ORDER BY id', [$aid]);
        $row['struttura'] = $props[0]['name'] ?? '';
        $row['citta'] = $props[0]['city'] ?? '';
        $row['strutture'] = count($props);
        $pubbl = count(array_filter($props, fn($p) => $p['status'] === 'published'));
        // 9N · «Mai attivato»: nessun abbonamento, mai (né pagato né concesso). Gli altri senza abbonamento sono scaduti.
        $maiAttivato = !$sub && !Db::val("SELECT id FROM subscriptions WHERE account_id = ? AND provider <> 'dimostrazione'", [$aid]);
        $row['stato'] = match (true) {
            (bool) $sub && $sub['status'] === 'past_due' => ['Pagamento non riuscito', 'alert', 'fallito'],
            (bool) $sub && $pubbl > 0 => ['Attivo', 'pine', 'attivo'],
            (bool) $sub => [$sub['provider'] === 'stripe' || $sub['provider'] === 'dimostrazione' ? 'Pagato, non pubblicato' : 'Attivato dallo staff, non pubblicato', 'ochre', 'pagato'],
            $maiAttivato => [$props ? 'Mai attivato' : 'Mai attivato · nessuna struttura', 'ochre', 'mai'],
            $pubbl > 0 => ['Scaduto (offline)', 'alert', 'scaduto'],
            default => ['Scaduto', 'alert', 'scaduto'],
        };
        $row['manuale'] = $sub && $sub['provider'] === 'manuale';
        $row['ultimo_pagamento'] = $incassi[$aid]['ultimo'] ?? '';
        $row['incassato'] = $incassi[$aid]['netto'] ?? 0;
        $row['scadenza'] = $sub['current_period_end'] ?? '';
        $row['rinnovo'] = $sub ? ($sub['provider'] !== 'stripe' ? 'staff' : ((int) $sub['cancel_at_period_end'] ? 'disattivato' : 'automatico')) : '';
        // 9D · in omaggio: fino a quando (poi parte l'addebito)
        $row['omaggio'] = $sub && ($sub['status'] ?? '') === 'trialing' && !empty($sub['omaggio_fino']) ? (string) $sub['omaggio_fino'] : '';
    }
    unset($row);
    // Filtri: stato e piano. Si applicano dopo il calcolo, perché lo stato non è una colonna.
    $filtroStato = (string) ($_GET['stato'] ?? ''); $filtroPiano = (string) ($_GET['piano'] ?? '');
    $piani = array_values(array_unique(array_filter(array_column($rows, 'plan'), fn($p) => $p !== '—')));
    // 9N · in più: clienti di prova, attivati dallo staff, mai pagato (nessun incasso, anche se attivati a mano).
    // 9Q · «Bozze da più di 21 giorni»: le stesse dell'email del giorno 21 e del contatore nel Quadro (Bozze::ferme).
    $ferme = $filtroStato === 'bozze21' ? array_flip(array_map(fn($b) => (int) $b['account_id'], MHW\Bozze::ferme())) : [];
    $passa = fn(array $x) => match ($filtroStato) {
        '' => true, 'bozze21' => isset($ferme[(int) $x['account_id']]), 'omaggio' => $x['omaggio'] !== '', 'prova' => !empty($x['is_test']), 'manuale' => $x['manuale'], 'staff' => !empty($x['is_staff']),
        'maipagato' => $x['ultimo_pagamento'] === '' && empty($x['is_test']), default => $x['stato'][2] === $filtroStato };
    $rows = array_values(array_filter($rows, fn($x) => $passa($x) && ($filtroPiano === '' || $x['plan'] === $filtroPiano)));
    if (($_GET['formato'] ?? '') === 'csv') {
        Gestione::csv('clienti', ['Codice', 'Nome', 'Email', 'Email confermata', 'Struttura', 'Città', 'Strutture', 'Piano', 'Stato', 'Scadenza', 'Rinnovo', 'Omaggio fino al',
                                  'Ultimo pagamento', 'Incassato (IVA esclusa, meno i rimborsi)', 'Registrato'],
            array_map(fn($x) => [(string) ($x['code'] ?? ''), $x['name'], $x['email'], $x['email_verified_at'] ? 'sì' : 'no', $x['struttura'], $x['citta'], $x['strutture'], $x['plan'],
                                 $x['stato'][0], $x['scadenza'] ? substr($x['scadenza'], 0, 10) : '', $x['rinnovo'], $x['omaggio'] ? substr($x['omaggio'], 0, 10) : '',
                                 substr($x['ultimo_pagamento'], 0, 10), number_format($x['incassato'] / 100, 2, ',', ''), substr((string) $x['created_at'], 0, 10)], $rows));
    }
    View::out('admin/customers', ['rows' => $rows, 'cerca' => $cerca, 'filtroStato' => $filtroStato, 'filtroPiano' => $filtroPiano, 'piani' => $piani,
                                  'esempi' => Demo::presente(), 'nav' => 'clienti'], 'layout/cms');
});

$r->post('/admin/entra/{uid}', function (array $a) {
    Auth::requireAdmin();
    Auth::impersonate((int) $a['uid']);
    Support::flash('Stai usando l\'account di un cliente. La sua password non è mai stata mostrata; la sessione finisce da sola dopo un\'ora.');
    Support::redirect('/pannello');
});

$r->post('/admin/esci-da-cliente', function () {
    // 9N · si torna alla scheda del cliente da cui si era entrati, non all'elenco
    $aid = Auth::isImpersonating() ? (int) Db::val('SELECT id FROM accounts WHERE user_id = ?', [(int) ($_SESSION['uid'] ?? 0)], 0) : 0;
    Auth::stopImpersonating();
    Support::redirect($aid ? '/admin/cliente/' . $aid : '/admin/clienti');
});

$r->get('/admin/cliente/{aid}', function (array $a) {
    Auth::requireAdmin();
    $pwTemp = Migrator::columnExists('users', 'must_change_password') ? ', u.must_change_password, u.password_temp_until' : '';   // 9E
    $acc = Db::one('SELECT a.*, u.email, u.name AS user_name, u.id AS user_id, u.email_verified_at, u.terms_version,
                           u.terms_accepted_at, u.privacy_version, u.privacy_accepted_at, u.dpa_version, u.dpa_accepted_at, u.created_at AS registrato' . $pwTemp . '
                    FROM accounts a JOIN users u ON u.id = a.user_id WHERE a.id = ?', [(int) $a['aid']]);
    if (!$acc) { http_response_code(404); View::out('pub/404', []); }
    // 10H · Un account eliminato: resta solo la scheda dei dati che la legge chiede di conservare.
    if (MHW\EliminaAccount::disponibile() && MHW\EliminaAccount::eliminato($acc)) View::out('admin/customer_eliminato', MHW\EliminaAccount::scheda($acc) + ['nav' => 'clienti'], 'layout/cms');
    $props = Db::all('SELECT * FROM properties WHERE account_id = ? ORDER BY id', [$acc['id']]);
    foreach ($props as &$p) {
        $p['lingue'] = implode(', ', array_column(Db::all('SELECT locale FROM property_locales WHERE property_id = ?', [$p['id']]), 'locale'));
        $p['online'] = Subscriptions::propertyOnline($p);
        $p['qr'] = Db::one('SELECT token, scans FROM qr_tokens WHERE property_id = ?', [$p['id']]);
    }
    unset($p);
    // 9I · I codici: cliente, strutture, pagamenti (ordini e rinnovi senza ordine), con quello cercato in evidenza.
    $codici = null;
    if (MHW\Codici::disponibili()) {
        $codici = ['cliente' => MHW\Codici::cliente((int) $acc['id']), 'strutture' => [], 'pagamenti' => []];
        foreach ($props as $pC) $codici['strutture'][] = ['id' => (int) $pC['id'], 'code' => MHW\Codici::struttura((int) $pC['id']), 'name' => (string) $pC['name']];
        $codici['pagamenti'] = Db::all("SELECT c.codice, c.order_id, c.invoice_id, c.created_at, o.amount_cents, o.currency, o.status, o.kind
                                        FROM codici_usati c LEFT JOIN orders o ON o.id = c.order_id WHERE c.livello = 'pagamento' AND c.account_id = ? ORDER BY c.id DESC LIMIT 60", [$acc['id']]);
    }
    View::out('admin/customer', [
        'codici' => $codici, 'evidenzia' => preg_replace('/[^A-Za-z0-9-]/', '', (string) ($_GET['evidenzia'] ?? '')),
        'acc' => $acc, 'props' => $props,
        'subs' => Db::all('SELECT s.*, pk.name AS package, pv.version FROM subscriptions s JOIN package_versions pv ON pv.id = s.package_version_id
                           JOIN packages pk ON pk.id = pv.package_id WHERE s.account_id = ? ORDER BY s.id DESC', [$acc['id']]),
        'orders' => Db::all('SELECT o.*, pk.name AS package FROM orders o JOIN package_versions pv ON pv.id = o.package_version_id
                             JOIN packages pk ON pk.id = pv.package_id WHERE o.account_id = ? ORDER BY o.id DESC', [$acc['id']]),
        'ent' => Entitlements::forAccount((int) $acc['id']),
        // 9N · in vendita (pubblici, attivi, versione corrente) prima; le altre in «Precedenti»
        'versioni' => Db::all('SELECT pv.id, pv.version, pk.name, CASE WHEN pk.public = 1 AND pk.active = 1 THEN 1 ELSE 0 END AS in_vendita
                               FROM package_versions pv JOIN packages pk ON pk.id = pv.package_id
                               WHERE pv.is_current = 1 ORDER BY pk.sort, pk.id'),
        'audit' => Db::all('SELECT * FROM audit_log WHERE target_user_id = ? ORDER BY id DESC LIMIT 50', [$acc['user_id']]),
        'email' => Richiami::disponibili() ? Db::all('SELECT kind, ref, sent_at FROM email_log WHERE account_id = ? ORDER BY sent_at DESC, id DESC LIMIT 20', [$acc['id']]) : [],
        'optout' => Richiami::disponibili() ? array_column(Db::all('SELECT kind FROM email_optout WHERE account_id = ?', [$acc['id']]), 'kind') : [],
        'parere' => \MHW\Migrator::tableExists('feedback') ? Db::one('SELECT * FROM feedback WHERE account_id = ?', [$acc['id']]) : null,
        'inviti' => Inviti::disponibili() ? ['stato' => Inviti::stato($acc), 'amici' => Inviti::amici((int) $acc['id']), 'invitato' => Inviti::invitato((int) $acc['id']),
                                              // L'invito ricevuto e annullato (stessi dati di fatturazione…), con il motivo: si vede perché non conta.
                                              'invito_annullato' => Db::one('SELECT r.*, u.name AS referrer_name' . (Migrator::columnExists('referrals', 'motivo') ? '' : ", '' AS motivo") . ' FROM referrals r JOIN accounts a ON a.id = r.referrer_account_id JOIN users u ON u.id = a.user_id WHERE r.friend_account_id = ? AND r.status = ?', [(int) $acc['id'], 'annullato'])] : null,
        // 9G · il contatore dell'anno, il tetto personale e «Aggiungi 250.000»
        'traduzioni' => \MHW\Migrator::tableExists('translation_usage') ? ['mese' => Traduttore::usati((int) $acc['id']), 'anno' => Traduttore::contatore((int) $acc['id']),
                                                                             'override' => \MHW\Migrator::columnExists('accounts', 'translate_cap_override') ? $acc['translate_cap_override'] : null,
                                                                             'nelPiano' => Traduttore::nelPiano((int) $acc['id'])] : null,
        // 10 · «Chiedi alla guida»: le domande del mese, il costo stimato, il limite personale
        'assistente' => Assistente::disponibile() ? ['nelPiano' => Assistente::nelPiano((int) $acc['id']), 'euro' => Assistente::costoMeseEuro(null, (int) $acc['id']),
                                                    'domande' => (int) Db::val("SELECT COUNT(*) FROM assistant_questions WHERE account_id = ? AND month = ? AND outcome IN ('ok', 'no')", [(int) $acc['id'], Assistente::mese()], 0),
                                                    'senza' => (int) Db::val("SELECT COUNT(*) FROM assistant_questions WHERE account_id = ? AND month = ? AND outcome = 'no'", [(int) $acc['id'], Assistente::mese()], 0),
                                                    'override' => Migrator::columnExists('accounts', 'assistant_cap_override') ? $acc['assistant_cap_override'] : null, 'limite' => Assistente::limite((int) $acc['id'])] : null,
        // 9E · i riquadri nuovi: l'anteprima di un cambio (dai parametri), le fatture pagate, i piani in vendita
        'cambio' => (function () use ($acc) {
            if (($_GET['cambio'] ?? '') === '') return null;
            try { return ['ok' => true] + MHW\AzioniCliente::anteprimaCambio((int) $acc['id'], (string) $_GET['cambio'], $_GET['strutture'] ?? null) + ['modo' => (string) ($_GET['modo'] ?? 'subito'), 'motivo' => (string) ($_GET['motivo'] ?? '')]; }
            catch (\RuntimeException $e) { return ['ok' => false, 'errore' => $e->getMessage()]; }
        })(),
        'fatture' => MHW\AzioniCliente::fatture($acc),
        'rimborsiFatti' => Migrator::tableExists('rimborsi') ? Db::all('SELECT * FROM rimborsi WHERE account_id = ? ORDER BY created_at DESC, id DESC', [(int) $acc['id']]) : [],   // 9O
        'pianiVendita' => Plans::public(),
        'omaggio' => MHW\Omaggi::prenotato($acc),
        'nav' => 'clienti',
    ], 'layout/cms');
});

// ------------------------------------------------------------------- 9E · le azioni sul cliente
/* Ferma o riattiva il rinnovo automatico: come lo fa il cliente, con il motivo nel registro e l'email. */
$r->post('/admin/cliente/{aid}/rinnovo', function (array $a) {
    Auth::requireAdmin();
    $accId = (int) $a['aid'];
    try { Support::flash(MHW\AzioniCliente::rinnovo($accId, ($_POST['ferma'] ?? '') === '1', trim((string) ($_POST['motivo'] ?? '')))); }
    catch (\Throwable $e) { Support::flash($e instanceof \RuntimeException && !($e instanceof \PDOException) ? $e->getMessage() : 'Non è stato possibile (codice ' . Log::exception($e, 'admin rinnovo') . ').', 'err'); }
    Support::redirect('/admin/cliente/' . $accId . '#abbonamento');
});

/* 9G · Il tetto annuale delle traduzioni di un cliente: «Aggiungi 250.000» (nota obbligatoria) o un tetto personale. Nel registro. */
$r->post('/admin/cliente/{aid}/traduzioni/tetto', function (array $a) {
    $admin = Auth::requireAdmin();
    $accId = (int) $a['aid'];
    $acc = Db::one('SELECT * FROM accounts WHERE id = ?', [$accId]);
    if (!$acc) { http_response_code(404); View::out('pub/404', []); }
    $nota = trim((string) ($_POST['nota'] ?? ''));
    try {
        if (($_POST['aggiungi'] ?? '') === '1') {
            if ($nota === '') throw new RuntimeException('Scrivi una nota: resta nel registro (perché si aggiungono i caratteri).');
            $nuovo = Traduttore::tettoAccount($accId) + Traduttore::AGGIUNTA;
            Traduttore::alzaTetto($accId, $nuovo);
            Auth::audit('translate.cap_added', (int) $acc['user_id'], ['account' => $accId, 'aggiunti' => Traduttore::AGGIUNTA, 'tetto' => $nuovo, 'nota' => mb_substr($nota, 0, 255), 'admin' => (int) $admin['id']]);
            Support::flash('Aggiunti ' . number_format(Traduttore::AGGIUNTA, 0, ',', '.') . ' caratteri: il tetto di quest\'anno è ' . number_format($nuovo, 0, ',', '.') . '.');
        } else {
            $tetto = (int) preg_replace('/\D/', '', (string) ($_POST['tetto'] ?? ''));
            if ($nota === '') throw new RuntimeException('Scrivi una nota: resta nel registro.');
            Traduttore::alzaTetto($accId, $tetto);
            Auth::audit('translate.cap_override', (int) $acc['user_id'], ['account' => $accId, 'tetto' => $tetto ?: null, 'nota' => mb_substr($nota, 0, 255), 'admin' => (int) $admin['id']]);
            Support::flash($tetto > 0 ? 'Tetto personale: ' . number_format($tetto, 0, ',', '.') . ' caratteri all\'anno.' : 'Tetto personale tolto: vale quello di serie.');
        }
    } catch (\Throwable $e) { Support::flash($e instanceof RuntimeException ? $e->getMessage() : 'Non è stato possibile (codice ' . Log::exception($e, 'admin tetto traduzioni') . ').', 'err'); }
    Support::redirect('/admin/cliente/' . $accId . '#traduzioni');
});

/* Cambia il pacchetto: «anteprima» rimanda alla scheda con il conto; «conferma» fa il cambio (subito con conguaglio, o dal rinnovo). */
// 10 · Il limite mensile personale di «Chiedi alla guida» (vuoto = quello di serie), con la nota nel registro.
$r->post('/admin/cliente/{aid}/assistente/limite', function (array $a) {
    $admin = Auth::requireAdmin();
    $acc = Db::one('SELECT * FROM accounts WHERE id = ?', [(int) $a['aid']]);
    if (!$acc) { http_response_code(404); exit('Non trovato.'); }
    $limite = (int) str_replace('.', '', trim((string) ($_POST['limite'] ?? '')));
    $nota = trim((string) ($_POST['nota'] ?? ''));
    try {
        Assistente::alzaLimite((int) $acc['id'], $limite);
        Auth::audit('assistente.limite', (int) $acc['user_id'], ['account' => (int) $acc['id'], 'limite' => $limite, 'nota' => mb_substr($nota, 0, 255), 'admin' => (int) $admin['id']]);
        Support::flash($limite > 0 ? 'Limite personale salvato: ' . number_format($limite, 0, ',', '.') . ' domande al mese per struttura.' : 'Limite personale tolto: vale quello di serie.');
    } catch (\RuntimeException $e) { Support::flash($e->getMessage(), 'err'); }
    Support::redirect('/admin/cliente/' . $acc['id'] . '#assistente');
});

$r->post('/admin/cliente/{aid}/cambio', function (array $a) {
    Auth::requireAdmin();
    $accId = (int) $a['aid'];
    $codice = (string) ($_POST['piano'] ?? ''); $strutture = $_POST['strutture'] ?? null; $modo = ($_POST['modo'] ?? '') === 'rinnovo' ? 'rinnovo' : 'subito';
    $motivo = trim((string) ($_POST['motivo'] ?? ''));
    if (($_POST['passo'] ?? '') !== 'conferma') {
        Support::redirect('/admin/cliente/' . $accId . '?' . http_build_query(['cambio' => $codice, 'strutture' => $strutture, 'modo' => $modo, 'motivo' => $motivo]) . '#cambio');
    }
    try {
        Support::flash($modo === 'rinnovo' ? MHW\AzioniCliente::cambiaDalRinnovo($accId, $codice, $strutture, $motivo) : MHW\AzioniCliente::cambiaSubito($accId, $codice, $strutture, $motivo));
    } catch (\Throwable $e) {
        Support::flash($e instanceof \RuntimeException && !($e instanceof \PDOException) ? $e->getMessage() : 'Non è stato possibile cambiare piano (codice ' . Log::exception($e, 'admin cambio piano') . ').', 'err');
        Support::redirect('/admin/cliente/' . $accId . '?' . http_build_query(['cambio' => $codice, 'strutture' => $strutture, 'modo' => $modo, 'motivo' => $motivo]) . '#cambio');
    }
    Support::redirect('/admin/cliente/' . $accId . '#abbonamento');
});

/* 9O · «Nota di credito fatta in Adamo» (o tolta la spunta): il promemoria sparisce dalla scheda. */
$r->post('/admin/rimborsi/{id}/nota', function (array $a) {
    Auth::requireAdmin();
    $rb = Migrator::columnExists('rimborsi', 'nota_credito_at') ? Db::one('SELECT * FROM rimborsi WHERE id = ?', [(int) $a['id']]) : null;
    if (!$rb) { http_response_code(404); View::out('pub/404', []); }
    $fatta = ($_POST['fatta'] ?? '1') === '1';
    Db::update('rimborsi', ['nota_credito_at' => $fatta ? Support::now() : null], 'id = :id', ['id' => (int) $rb['id']]);
    Auth::audit('refund.credit_note', (int) Db::val('SELECT user_id FROM accounts WHERE id = ?', [(int) $rb['account_id']], 0) ?: null,
                ['rimborso' => (int) $rb['id'], 'fatta' => $fatta, 'cliente' => MHW\Codici::cliente((int) $rb['account_id'])]);
    Support::flash($fatta ? 'Nota di credito segnata come fatta in Adamo.' : 'Promemoria della nota di credito riaperto.');
    Support::redirect('/admin/cliente/' . (int) $rb['account_id'] . '#rimborsi');
});

/* Rimborso da Stripe, totale o parziale, con il motivo. 9O: totale sull'ultimo pagamento → abbonamento chiuso (dal webhook). */
$r->post('/admin/cliente/{aid}/rimborso', function (array $a) {
    $admin = Auth::requireAdmin();
    $accId = (int) $a['aid'];
    try { Support::flash(MHW\AzioniCliente::rimborsa($accId, (string) ($_POST['invoice'] ?? ''), (string) ($_POST['importo'] ?? ''), (string) ($_POST['motivo'] ?? ''), (string) ($_POST['testo'] ?? ''), (int) $admin['id'])); }
    catch (\Throwable $e) { Support::flash($e instanceof \RuntimeException && !($e instanceof \PDOException) ? $e->getMessage() : 'Il rimborso non è partito (codice ' . Log::exception($e, 'admin rimborso') . ').', 'err'); }
    Support::redirect('/admin/cliente/' . $accId . '#fatture');
});

/* Password: il link per reimpostarla, oppure una provvisoria (mostrata una sola volta, 48 ore, da cambiare al primo accesso). */
$r->post('/admin/cliente/{aid}/password/{cosa}', function (array $a) {
    Auth::requireAdmin();
    $accId = (int) $a['aid'];
    try {
        if ($a['cosa'] === 'link') { Support::flash(MHW\AzioniCliente::linkReset($accId)); }
        elseif ($a['cosa'] === 'provvisoria') { $_SESSION['pw_provvisoria'] = MHW\AzioniCliente::passwordProvvisoria($accId); Support::flash('Password provvisoria impostata: la vedi qui sotto una sola volta. Vale 48 ore; al primo accesso il cliente deve sceglierne una nuova. Il cliente ha ricevuto un\'email di avviso.'); }
        else { http_response_code(404); View::out('pub/404', []); }
    } catch (\Throwable $e) { Support::flash($e instanceof \RuntimeException && !($e instanceof \PDOException) ? $e->getMessage() : 'Non è stato possibile (codice ' . Log::exception($e, 'admin password') . ').', 'err'); }
    Support::redirect('/admin/cliente/' . $accId . '#accesso');
});

/** La nota interna sul cliente: solo per l'amministrazione, mai mostrata al cliente. */
$r->post('/admin/cliente/{aid}/nota', function (array $a) {
    Auth::requireAdmin();
    $accId = (int) $a['aid'];
    $uid = (int) Db::val('SELECT user_id FROM accounts WHERE id = ?', [$accId]);
    if (!$uid) { http_response_code(404); View::out('pub/404', []); }
    $nota = mb_substr(trim((string) ($_POST['nota'] ?? '')), 0, 4000);
    Db::update('accounts', ['admin_note' => $nota, 'admin_note_at' => Support::now()], 'id = :aid', ['aid' => $accId]);
    Auth::audit('account.note', $uid, ['chars' => mb_strlen($nota)]);
    Support::flash($nota === '' ? 'Nota tolta.' : 'Nota salvata.');
    Support::redirect('/admin/cliente/' . $accId . '#nota');
});

$r->post('/admin/cliente/{aid}/override', function (array $a) {
    $admin = Auth::requireAdmin();
    $accId = (int) $a['aid'];
    $f = Db::one('SELECT * FROM features WHERE code = ?', [(string) ($_POST['feature'] ?? '')]);
    if (!$f) { http_response_code(404); View::out('pub/404', []); }
    $val = trim((string) ($_POST['valore'] ?? ''));
    if ($val !== '' && !preg_match('/^(\d{1,4}|unlimited)$/', $val)) {
        Support::flash('Valore non valido: scrivi un numero, 1 per sì e 0 per no, oppure unlimited.', 'err');
        Support::redirect('/admin/cliente/' . $accId);
    }
    $uid = (int) Db::val('SELECT user_id FROM accounts WHERE id = ?', [$accId]);
    if ($val === '') {
        Db::run('DELETE FROM entitlement_overrides WHERE account_id = ? AND feature_id = ?', [$accId, $f['id']]);
        Support::flash('Eccezione rimossa: torna a valere il piano.');
    } else {
        $ex = Db::one('SELECT id FROM entitlement_overrides WHERE account_id = ? AND feature_id = ?', [$accId, $f['id']]);
        if ($ex) Db::update('entitlement_overrides', ['value' => $val, 'note' => (string) ($_POST['nota'] ?? '')], 'id = :oid', ['oid' => $ex['id']]);
        else Db::insert('entitlement_overrides', ['account_id' => $accId, 'feature_id' => $f['id'], 'value' => $val, 'note' => (string) ($_POST['nota'] ?? '')]);
        Support::flash('Eccezione salvata per questo cliente.');
    }
    Auth::audit('entitlement.override', $uid, ['feature' => $f['code'], 'value' => $val]);
    Entitlements::forget($accId);
    Support::redirect('/admin/cliente/' . $accId);
});

/** Un abbonamento concesso a mano: omaggi, demo, pagamenti arrivati per altre vie. */
$r->post('/admin/cliente/{aid}/abbonamento', function (array $a) {
    Auth::requireAdmin();
    $accId = (int) $a['aid'];
    if (!Db::one('SELECT id FROM accounts WHERE id = ?', [$accId])) { http_response_code(404); View::out('pub/404', []); }
    $pv = Db::one('SELECT pv.id, pk.name FROM package_versions pv JOIN packages pk ON pk.id = pv.package_id WHERE pv.id = ?', [(int) ($_POST['pv'] ?? 0)]);
    // 7F: scelte rapide «Gratis 12 mesi» e «Gratis per sempre» (100 anni); «mesi» o nessuna scelta = il numero scritto.
    $durata = (string) ($_POST['durata'] ?? 'mesi');
    $mesi = match ($durata) { '12' => 12, 'sempre' => Billing::PER_SEMPRE, default => max(1, min(36, (int) ($_POST['mesi'] ?? 12))) };
    $nota = trim((string) ($_POST['nota'] ?? ''));
    if (!$pv || $nota === '') {
        Support::flash('Scegli un piano e scrivi il motivo: resta nel registro.', 'err');
        Support::redirect('/admin/cliente/' . $accId . '#abbonamento');
    }
    // 9N · «Pagato fuori Stripe: bonifico»: importo e IVA, data e riferimento → un pagamento con codice MW che entra in Cassa e Prospetti.
    $bonifico = null;
    if (($_POST['pagamento'] ?? '') === 'bonifico') {
        try { $bonifico = Billing::datiBonifico($_POST); }
        catch (\RuntimeException $e) { Support::flash($e->getMessage(), 'err'); Support::redirect('/admin/cliente/' . $accId . '#abbonamento'); }
    }
    $subId = Billing::grantManual($accId, (int) $pv['id'], $mesi, $nota, max(1, (int) ($_POST['strutture'] ?? 1)));
    if ($bonifico) {
        $codice = Billing::segnaBonifico($accId, $subId, $bonifico, $nota);
        Support::flash($pv['name'] . ($mesi === Billing::PER_SEMPRE ? ' attivo per sempre' : " attivo per $mesi mesi") . '. Bonifico di ' . Support::money($bonifico['imponibile'] + $bonifico['iva'])
                       . ' segnato' . ($codice !== '' ? ' con il codice ' . $codice : '') . ': entra in Cassa e Prospetti.');
    } else {
        Support::flash($mesi === Billing::PER_SEMPRE ? $pv['name'] . " gratis per sempre. Non compare nell'incasso."
                                                     : $pv['name'] . " attivo per $mesi mesi. Non compare nell'incasso.");
    }
    Support::redirect('/admin/cliente/' . $accId);
});

/* 7F: «Cliente di prova» sì o no. */
$r->post('/admin/cliente/{aid}/prova', function (array $a) {
    Auth::requireAdmin();
    $accId = (int) $a['aid'];
    $uid = (int) Db::val('SELECT user_id FROM accounts WHERE id = ?', [$accId]);
    if (!$uid || !Migrator::columnExists('accounts', 'is_test')) { http_response_code(404); View::out('pub/404', []); }
    $prova = !empty($_POST['prova']) ? 1 : 0;
    Db::update('accounts', ['is_test' => $prova], 'id = :aid', ['aid' => $accId]);
    Auth::audit('account.test', $uid, ['is_test' => $prova]);
    Support::flash($prova ? 'Segnato come cliente di prova: non entra più nei conti e non riceve richiami automatici.' : 'Non è più un cliente di prova: torna nei conti.');
    Support::redirect('/admin/cliente/' . $accId);
});

/*
 * La guida vetrina (Demo::vetrina) dentro l'account di un cliente vero: una sola per account.
 * Foto, lingue e luoghi seguono il piano: se l'account non ha un abbonamento attivo, si può
 * concedere Plus dimostrativo per 12 mesi (lo stesso abbonamento manuale qui sopra, nel registro).
 */
$r->post('/admin/cliente/{aid}/vetrina', function (array $a) {
    Auth::requireAdmin();
    $accId = (int) $a['aid'];
    $uid = (int) Db::val('SELECT user_id FROM accounts WHERE id = ?', [$accId]);
    if (!$uid) { http_response_code(404); View::out('pub/404', []); }
    // «Rifai»: la vetrina vecchia si toglie e se ne crea una con i dati aggiornati.
    $rifai = ($_POST['rifai'] ?? '') === '1';
    if (Db::val('SELECT id FROM properties WHERE account_id = ? AND is_demo = ?', [$accId, Demo::VETRINA])) {
        if (!$rifai) {
            Support::flash('Questo account ha già una guida vetrina: usa «Rifai la vetrina» per sostituirla.', 'err');
            Support::redirect('/admin/cliente/' . $accId);
        }
        Demo::eliminaVetrina($accId);
    }
    try {
        $pid = Demo::creaVetrina($accId, ($_POST['plus'] ?? '') === '1');
    } catch (\Throwable $e) {
        Log::error('vetrina: ' . $e->getMessage(), ['account' => $accId]);
        Support::flash('La guida vetrina non è stata creata: ' . $e->getMessage(), 'err');
        Support::redirect('/admin/cliente/' . $accId);
    }
    Auth::audit('demo.vetrina', $uid, ['property_id' => $pid]);
    // 9C: l'indirizzo nel messaggio; se non è /g/casa-checco è perché quello è di un cliente vero.
    $slugV = (string) Db::val('SELECT slug FROM properties WHERE id = ?', [$pid], '');
    $dove = ' Indirizzo: ' . Support::baseUrl() . '/g/' . $slugV . ($slugV !== 'casa-checco' ? ' (/g/casa-checco è la guida di un cliente vero: resta sua).' : '.');
    Support::flash(($rifai ? 'Guida vetrina rifatta con i dati aggiornati e pubblicata.' : 'Guida vetrina «Casa Checco» creata e pubblicata. È la demo della landing.') . $dove);
    Support::redirect('/admin/cliente/' . $accId);
});

/* 8D: «Completa le traduzioni della vetrina»: i campi ancora vuoti nelle altre lingue prendono le traduzioni di
   lang/vetrina/{lingua}.php (quelli già scritti non si toccano), poi la vetrina si ripubblica. */
$r->post('/admin/cliente/{aid}/vetrina/traduzioni', function (array $a) {
    Auth::requireAdmin();
    $accId = (int) $a['aid'];
    $pid = (int) Db::val('SELECT id FROM properties WHERE account_id = ? AND is_demo = ?', [$accId, Demo::VETRINA], 0);
    if (!$pid) { Support::flash('Questo account non ha una guida vetrina.', 'err'); Support::redirect('/admin/cliente/' . $accId); }
    $prop = Db::one('SELECT * FROM properties WHERE id = ?', [$pid]);
    $conta = function () use ($prop): int {
        $n = 0;
        foreach (Db::all('SELECT locale FROM property_locales WHERE property_id = ?', [$prop['id']]) as $l) {
            if ($l['locale'] === $prop['default_locale']) continue;
            $n += count(array_filter(MHW\Traduttore::campi($prop, (string) $l['locale']), fn($c) => $c['tradotto'] === ''));
        }
        return $n;
    };
    $prima = $conta();
    try {
        Demo::traduciVetrina($pid);
        MHW\Guide::publish($pid);
    } catch (\Throwable $e) {
        Log::error('vetrina traduzioni: ' . $e->getMessage(), ['account' => $accId]);
        Support::flash('Le traduzioni non sono state completate: ' . $e->getMessage(), 'err');
        Support::redirect('/admin/cliente/' . $accId);
    }
    $dopo = $conta();
    Auth::audit('demo.vetrina_traduzioni', (int) Db::val('SELECT user_id FROM accounts WHERE id = ?', [$accId]), ['property_id' => $pid, 'scritte' => $prima - $dopo]);
    Support::flash(($prima - $dopo) . ' campi tradotti e vetrina ripubblicata.' . ($dopo ? " Ne restano $dopo senza traduzione: per quelli usa «Rifai la vetrina»." : ''));
    Support::redirect('/admin/cliente/' . $accId);
});

$r->post('/admin/dati-esempio', function () {
    Auth::requireAdmin();
    if (($_POST['cosa'] ?? '') === 'elimina') {
        $n = Demo::rimuovi();
        Auth::audit('demo.remove', null, ['accounts' => $n]);
        Support::flash($n . ' clienti di esempio eliminati, con le loro guide e le loro foto.');
    } elseif (Demo::presente()) {
        Support::flash('Ci sono già: toglili prima di ricrearli.', 'err');
    } else {
        $creati = Demo::popola();
        Auth::audit('demo.create', null, ['accounts' => count($creati)]);
        Support::flash('Creati ' . count($creati) . ' clienti di esempio. Password: ' . Demo::PASSWORD . '.');
    }
    Support::redirect('/admin/clienti');
});

// -------------------------------------------------------------- abbonamenti
$r->get('/admin/abbonamenti', function () {
    Auth::requireAdmin();
    // 7F: «Gratuiti» = gli abbonamenti concessi a mano (omaggi, gratis per sempre) e quelli dimostrativi.
    $gratuiti = ($_GET['filtro'] ?? '') === 'gratuiti';
    $prova = Migrator::columnExists('accounts', 'is_test') ? 'a.is_test' : '0';
    $subs = Db::all(
        "SELECT s.*, pk.name AS package, pv.version, pv.price_cents, pv.currency, u.email, u.name AS cliente, a.id AS account_id, $prova AS is_test
         FROM subscriptions s JOIN package_versions pv ON pv.id = s.package_version_id JOIN packages pk ON pk.id = pv.package_id
         JOIN accounts a ON a.id = s.account_id JOIN users u ON u.id = a.user_id"
        . ($gratuiti ? " WHERE s.provider IN ('manuale', 'dimostrazione')" : '') . ' ORDER BY s.id DESC LIMIT 300');
    foreach ($subs as &$s) $s['valido'] = Subscriptions::valid($s, (int) (Config::get('billing')['grace_days'] ?? 0));
    unset($s);
    if (($_GET['formato'] ?? '') === 'csv') {   // 9N
        Gestione::csv($gratuiti ? 'abbonamenti-gratuiti' : 'abbonamenti', ['Cliente', 'Email', 'Piano', 'Versione', 'Strutture', 'Via', 'Stato', 'Valido', 'Inizio', 'Fine', 'Rinnovo', 'Cliente di prova', 'ID Stripe abbonamento'],
            array_map(fn($s) => [$s['cliente'], $s['email'], $s['package'], (int) $s['version'], (int) ($s['quantity'] ?? 1), $s['provider'], $s['status'], $s['valido'] ? 'sì' : 'no',
                                 substr((string) $s['current_period_start'], 0, 10), substr((string) $s['current_period_end'], 0, 10),
                                 $s['provider'] !== 'stripe' ? 'staff' : ((int) $s['cancel_at_period_end'] ? 'disattivato' : 'automatico'), !empty($s['is_test']) ? 'sì' : '',
                                 (string) $s['provider_subscription_id']], $subs));
    }
    View::out('admin/subscriptions', ['subs' => $subs, 'gratuiti' => $gratuiti, 'nav' => 'abbonamenti'], 'layout/cms');
});

// -------------------------------------------------------------------- guide
$r->get('/admin/guide', function () {
    Auth::requireAdmin();
    $guide = Db::all(
        'SELECT p.*, u.email, u.name AS cliente, a.id AS account_id, ' . Gestione::colonnaProva() . ' AS is_test,
                (SELECT token FROM qr_tokens q WHERE q.property_id = p.id) AS qr_token,
                (SELECT scans FROM qr_tokens q WHERE q.property_id = p.id) AS qr_scans,
                (SELECT MAX(version) FROM guide_versions g WHERE g.property_id = p.id) AS versione
         FROM properties p JOIN accounts a ON a.id = p.account_id JOIN users u ON u.id = a.user_id ORDER BY p.id DESC LIMIT 300');
    foreach ($guide as &$g) {
        $g['online'] = Subscriptions::propertyOnline($g);
        $g['lingue'] = implode(', ', array_column(Db::all('SELECT locale FROM property_locales WHERE property_id = ?', [$g['id']]), 'locale'));
    }
    unset($g);
    View::out('admin/guides', ['guide' => $guide, 'nav' => 'guide'], 'layout/cms');
});

$r->get('/admin/registro', function () {
    Auth::requireAdmin();
    $righe = Db::all(
        'SELECT l.*, ua.email AS attore, ut.email AS bersaglio FROM audit_log l
         LEFT JOIN users ua ON ua.id = l.actor_user_id LEFT JOIN users ut ON ut.id = l.target_user_id
         ORDER BY l.id DESC LIMIT 300');
    if (($_GET['formato'] ?? '') === 'csv') {   // 9N · le ultime 5000 azioni
        Gestione::csv('registro-azioni', ['Quando (UTC)', 'Chi', 'Azione', 'Su', 'Dettagli'],
            array_map(fn($l) => [str_replace(['T', 'Z'], [' ', ''], (string) $l['created_at']), $l['attore'] ?: 'sistema', $l['action'], $l['bersaglio'] ?: '', $l['meta']],
                      Db::all('SELECT l.*, ua.email AS attore, ut.email AS bersaglio FROM audit_log l LEFT JOIN users ua ON ua.id = l.actor_user_id
                               LEFT JOIN users ut ON ut.id = l.target_user_id ORDER BY l.id DESC LIMIT 5000')));
    }
    View::out('admin/audit', ['righe' => $righe, 'nav' => 'registro'], 'layout/cms');
});

// ----------------------------------------------------------------- pacchetti
$r->get('/admin/pacchetti', function () {
    Auth::requireAdmin();
    $packages = Db::all('SELECT * FROM packages ORDER BY sort, id');
    foreach ($packages as &$p) {
        $p['versions'] = Db::all('SELECT * FROM package_versions WHERE package_id = ? ORDER BY version DESC', [$p['id']]);
        foreach ($p['versions'] as &$v) {
            $v['features'] = array_column(Db::all(
                'SELECT f.code, pf.value FROM package_features pf JOIN features f ON f.id = pf.feature_id
                 WHERE pf.package_version_id = ?', [$v['id']]), 'value', 'code');
            $v['clienti'] = (int) Db::val("SELECT COUNT(*) FROM subscriptions WHERE package_version_id = ? AND status IN ('active','trialing')", [$v['id']], 0);
        }
        unset($v);
    }
    unset($p);
    View::out('admin/packages', ['packages' => $packages, 'features' => Db::all("SELECT * FROM features WHERE code <> 'room_variants' ORDER BY id"), 'nav' => 'pacchetti'], 'layout/cms');
});

/**
 * Cambiare prezzo o contenuto crea una versione NUOVA: quelle già vendute non
 * si toccano, e gli abbonamenti restano agganciati alla loro. Il testo
 * commerciale (titoli, elenco puntato) invece si aggiorna sul pacchetto.
 */
/* 8E: i testi inglesi della scheda (migrazione 038), solo se il modulo li manda e le colonne ci sono. */
$testiEn = function (): array {
    $out = [];
    if (!Migrator::columnExists('packages', 'tagline_en')) return $out;
    foreach (['tagline_en' => 250, 'headline_en' => 160, 'description_en' => 600, 'bullets_en' => 3000, 'badge_en' => 40, 'cta_label_en' => 60] as $c => $max) {
        if (array_key_exists($c, $_POST)) $out[$c] = mb_substr(trim(str_replace("\r", '', (string) $_POST[$c])), 0, $max);
    }
    return $out;
};
$r->post('/admin/pacchetti/{pid}/nuova-versione', function (array $a) use ($testiEn) {
    Auth::requireAdmin();
    $pkg = Db::one('SELECT * FROM packages WHERE id = ?', [(int) $a['pid']]);
    if (!$pkg) { http_response_code(404); View::out('pub/404', []); }
    $prezzo = (int) round(((float) str_replace(',', '.', (string) ($_POST['prezzo'] ?? '0'))) * 100);
    if ($prezzo < 0 || $prezzo > 10_000_000) { Support::flash('Prezzo non valido.', 'err'); Support::redirect('/admin/pacchetti'); }
    $priceId = trim((string) ($_POST['stripe_price_id'] ?? ''));
    if ($priceId !== '' && !preg_match('/^price_[A-Za-z0-9]+$/', $priceId)) {
        Support::flash('Il Price ID di Stripe comincia con price_.', 'err'); Support::redirect('/admin/pacchetti');
    }
    // Piani a struttura (Portfolio): prezzo per struttura aggiuntiva, minimo, massimo.
    $corrente = Db::one('SELECT * FROM package_versions WHERE package_id = ? AND is_current = 1', [$pkg['id']]) ?: [];
    $aStruttura = ['per_property' => 0, 'extra_price_cents' => 0, 'min_quantity' => 1, 'max_quantity' => 1, 'stripe_extra_price_id' => ''];
    if (Plans::perProperty($corrente)) {
        $extra = (int) round(((float) str_replace(',', '.', (string) ($_POST['prezzo_extra'] ?? '0'))) * 100);
        $min = (int) ($_POST['min_quantita'] ?? 2); $max = (int) ($_POST['max_quantita'] ?? 50);
        $extraId = trim((string) ($_POST['stripe_extra_price_id'] ?? ''));
        if ($extra < 0 || $extra > 10_000_000 || $min < 1 || $max < $min || $max > 500 || ($extraId !== '' && !preg_match('/^price_[A-Za-z0-9]+$/', $extraId))) {
            Support::flash('Controlla prezzo per struttura aggiuntiva, minimo, massimo e Price ID.', 'err'); Support::redirect('/admin/pacchetti');
        }
        $aStruttura = ['per_property' => 1, 'extra_price_cents' => $extra, 'min_quantity' => $min, 'max_quantity' => $max, 'stripe_extra_price_id' => $extraId];
        // Gli scaglioni: una riga per scaglione, «dalla struttura: prezzo» (per esempio «3: 50»).
        // La 2ª struttura costa il prezzo della struttura aggiuntiva.
        if (Migrator::columnExists('package_versions', 'extra_tiers')) {
            $scaglioni = [];
            foreach (preg_split('/\R/', trim((string) ($_POST['scaglioni'] ?? ''))) ?: [] as $riga) {
                if (trim($riga) === '') continue;
                if (!preg_match('/^\s*(\d{1,3})\s*[:=]\s*(\d{1,6}(?:[.,]\d{1,2})?)\s*(€)?\s*$/u', $riga, $m) || (int) $m[1] < 3) {
                    Support::flash('Scaglioni: una riga per scaglione, come «3: 50» (dalla 3ª struttura, 50 € l\'una). Si parte dalla 3ª.', 'err'); Support::redirect('/admin/pacchetti');
                }
                $scaglioni[(int) $m[1]] = (int) round((float) str_replace(',', '.', $m[2]) * 100);
            }
            ksort($scaglioni);
            $aStruttura['extra_tiers'] = $scaglioni ? json_encode(array_merge([['da' => 2, 'cents' => $extra]],
                array_map(fn($da, $c) => ['da' => $da, 'cents' => $c], array_keys($scaglioni), $scaglioni))) : '';
        }
    }
    // 9A · Un Price ID si tiene solo se l'importo (e gli scaglioni, per la voce aggiuntiva) è identico alla versione da cui
    // viene: altrimenti su Stripe si pagherebbero i vecchi importi. Svuotato, il Price nuovo lo crea il sito alla prima vendita.
    $cambiati = [];
    if ($priceId !== '' && $corrente && ($priceId !== (string) $corrente['stripe_price_id'] || (int) $corrente['price_cents'] !== $prezzo)) {
        if ($priceId === (string) $corrente['stripe_price_id']) { $priceId = ''; $cambiati[] = 'base'; }
    }
    if ($aStruttura['stripe_extra_price_id'] !== '' && $corrente && $aStruttura['stripe_extra_price_id'] === (string) $corrente['stripe_extra_price_id']) {
        $stessi = (int) $corrente['extra_price_cents'] === (int) $aStruttura['extra_price_cents']
            && Plans::tiers($corrente) === Plans::tiers($aStruttura + ['price_cents' => $prezzo]);
        if (!$stessi) { $aStruttura['stripe_extra_price_id'] = ''; $cambiati[] = 'struttura aggiuntiva'; }
    }
    $avvisoPrezzo = $cambiati ? ' Prezzo cambiato (' . implode(', ', $cambiati) . '): il Price di Stripe sarà creato nuovo.' : '';
    $vid = Db::tx(function () use ($pkg, $prezzo, $priceId, $aStruttura, $testiEn) {
        $next = (int) Db::val('SELECT COALESCE(MAX(version),0)+1 FROM package_versions WHERE package_id = ?', [$pkg['id']], 1);
        Db::run('UPDATE package_versions SET is_current = 0 WHERE package_id = ?', [$pkg['id']]);
        $vid = Db::insert('package_versions', [
            'package_id' => $pkg['id'], 'version' => $next, 'price_cents' => $prezzo, 'currency' => 'EUR',
            'interval_unit' => 'year', 'is_current' => 1, 'sold_count' => 0, 'stripe_price_id' => $priceId, 'created_at' => Support::now(),
        ] + $aStruttura);
        foreach (Db::all("SELECT * FROM features WHERE code <> 'room_variants' ORDER BY id") as $f) {
            $val = trim((string) ($_POST['f'][$f['code']] ?? '0'));
            if (!preg_match('/^(\d{1,4}|unlimited)$/', $val)) $val = '0';
            Db::insert('package_features', ['package_version_id' => $vid, 'feature_id' => $f['id'], 'value' => $val]);
        }
        Db::update('packages', [
            'name' => trim((string) $_POST['nome']) ?: $pkg['name'],
            'tagline' => trim((string) ($_POST['tagline'] ?? '')), 'headline' => trim((string) ($_POST['headline'] ?? '')),
            'description' => trim((string) ($_POST['description'] ?? '')), 'bullets' => trim((string) ($_POST['bullets'] ?? '')),
            'badge' => trim((string) ($_POST['badge'] ?? '')), 'cta_label' => trim((string) ($_POST['cta_label'] ?? '')),
            'public' => !empty($_POST['public']) ? 1 : 0, 'active' => !empty($_POST['active']) ? 1 : 0,
        ] + $testiEn(), 'id = :pid', ['pid' => $pkg['id']]);
        return $vid;
    });
    Auth::audit('package.new_version', null, ['package' => $pkg['code'], 'version_id' => $vid, 'price_cents' => $prezzo]);
    Entitlements::forget();
    MHW\Seo::scriviFile();   // 7F: i prezzi sono in sitemap (data) e in llms.txt
    Support::flash('Creata una versione nuova di ' . $pkg['name'] . '. Chi era sulla precedente ci resta, con quello che aveva comprato.' . $avvisoPrezzo);
    Support::redirect('/admin/pacchetti');
});

/** Solo il testo commerciale: nessuna versione nuova, i diritti non cambiano. */
$r->post('/admin/pacchetti/{pid}/testo', function (array $a) use ($testiEn) {
    Auth::requireAdmin();
    $pkg = Db::one('SELECT * FROM packages WHERE id = ?', [(int) $a['pid']]);
    if (!$pkg) { http_response_code(404); View::out('pub/404', []); }
    Db::update('packages', [
        'tagline' => trim((string) ($_POST['tagline'] ?? '')), 'headline' => trim((string) ($_POST['headline'] ?? '')),
        'description' => trim((string) ($_POST['description'] ?? '')), 'bullets' => trim((string) ($_POST['bullets'] ?? '')),
        'badge' => trim((string) ($_POST['badge'] ?? '')), 'cta_label' => trim((string) ($_POST['cta_label'] ?? '')),
        'public' => !empty($_POST['public']) ? 1 : 0, 'active' => !empty($_POST['active']) ? 1 : 0,
    ] + $testiEn(), 'id = :pid', ['pid' => $pkg['id']]);
    Auth::audit('package.copy', null, ['package' => $pkg['code']]);
    MHW\Seo::scriviFile();   // 7F: le frasi dei piani sono in llms.txt
    Support::flash('Testi di ' . $pkg['name'] . ' aggiornati.');
    Support::redirect('/admin/pacchetti');
});

// ---------------------------------------------------------------- traduzioni
/**
 * Le traduzioni suggerite (Traduttore): consumi e costi stimati del mese, gli ultimi 12 mesi con gli esiti
 * (ok / cache / limite / errore), chi traduce di più nel mese e nell'anno (con il tetto annuale, 9G), quanto
 * costerebbe tradurre tutto quello che manca. I costi sono stime.
 */
$r->get('/admin/traduzioni', function () {
    Auth::requireAdmin();
    $mese = Traduttore::mese();
    $usati = Traduttore::usati();
    $gratis = Traduttore::gratuitoAttivo();
    $mesi = Db::all("SELECT month, SUM(CASE WHEN outcome = 'ok' THEN chars ELSE 0 END) AS chars, SUM(CASE WHEN outcome = 'ok' THEN 1 ELSE 0 END) AS chiamate,
                            SUM(CASE WHEN outcome = 'cache' THEN 1 ELSE 0 END) AS cache, SUM(CASE WHEN outcome = 'cache' THEN chars ELSE 0 END) AS chars_cache,
                            SUM(CASE WHEN outcome = 'errore' THEN 1 ELSE 0 END) AS errori, SUM(CASE WHEN outcome = 'limite' THEN 1 ELSE 0 END) AS limiti
                     FROM translation_usage GROUP BY month ORDER BY month DESC LIMIT 12");
    $primi = Db::all("SELECT t.account_id, SUM(t.chars) AS chars, u.email, u.name FROM translation_usage t
                      LEFT JOIN accounts a ON a.id = t.account_id LEFT JOIN users u ON u.id = a.user_id
                      WHERE t.month = ? AND t.outcome = 'ok' GROUP BY t.account_id, u.email, u.name ORDER BY chars DESC LIMIT 10", [$mese]);
    // 9G · chi consuma di più negli ultimi 12 mesi, con il tetto annuale di ognuno.
    $daAnno = gmdate('Y-m-d\TH:i:s\Z', strtotime('-12 months'));
    $anno = [];
    foreach (Db::all("SELECT t.account_id, SUM(t.chars) AS chars, u.email, u.name FROM translation_usage t
                      JOIN accounts a ON a.id = t.account_id JOIN users u ON u.id = a.user_id
                      WHERE t.outcome = 'ok' AND t.created_at >= ? GROUP BY t.account_id, u.email, u.name ORDER BY chars DESC LIMIT 10", [$daAnno]) as $x) {
        $anno[] = $x + ['contatore' => Traduttore::contatore((int) $x['account_id'])];
    }
    // Previsione: i clienti con le traduzioni suggerite nel piano, e i caratteri ancora da tradurre nelle lingue accese.
    $clienti = 0; $daTradurre = 0;
    foreach (Db::all('SELECT DISTINCT account_id FROM properties WHERE is_demo = 0 AND archived_at IS NULL') as $x) {
        if (!Traduttore::nelPiano((int) $x['account_id'])) continue;
        $clienti++;
        foreach (Db::all('SELECT * FROM properties WHERE account_id = ? AND is_demo = 0 AND archived_at IS NULL', [$x['account_id']]) as $p) {
            foreach (Db::all('SELECT locale FROM property_locales WHERE property_id = ? AND locale <> ?', [$p['id'], $p['default_locale']]) as $l) {
                foreach (Traduttore::campi($p, $l['locale']) as $c) if ($c['tradotto'] === '') $daTradurre += mb_strlen($c['origine']);
            }
        }
    }
    $errori = Db::all("SELECT t.*, u.email FROM translation_usage t LEFT JOIN accounts a ON a.id = t.account_id LEFT JOIN users u ON u.id = a.user_id
                       WHERE t.outcome IN ('errore', 'limite') ORDER BY t.id DESC LIMIT 8");
    $cache = \MHW\Migrator::tableExists('translation_cache') ? ['righe' => (int) Db::val('SELECT COUNT(*) FROM translation_cache', [], 0), 'riusi' => (int) Db::val('SELECT COALESCE(SUM(hits), 0) FROM translation_cache', [], 0)] : null;
    View::out('admin/traduzioni', ['mese' => $mese, 'usati' => $usati, 'tetti' => Traduttore::tetti(), 'gratis' => $gratis, 'mesi' => $mesi, 'primi' => $primi, 'anno' => $anno,
        'clienti' => $clienti, 'daTradurre' => $daTradurre, 'errori' => $errori, 'configurato' => Traduttore::configurato(), 'cache' => $cache,
        'config' => Traduttore::config(), 'nav' => 'traduzioni'], 'layout/cms');
});

// ---------------------------------------------------------------- 10 · Assistente AI
/* «Chiedi alla guida»: per ogni cliente le domande del mese, quelle senza risposta, i token e il costo stimato dal
   listino delle Impostazioni, lo stato del limite; i totali, gli ultimi errori, il CSV. Le stime non sono la fattura: fa fede la console di Anthropic. */
$meseAssistente = fn(): string => preg_match('/^\d{4}-\d{2}$/', (string) ($_GET['mese'] ?? '')) ? (string) $_GET['mese'] : Assistente::mese();
$r->get('/admin/assistente', function () use ($meseAssistente) {
    Auth::requireAdmin();
    $mese = $meseAssistente();
    $errori = Assistente::disponibile() ? Db::all("SELECT q.*, u.email FROM assistant_questions q LEFT JOIN accounts a ON a.id = q.account_id LEFT JOIN users u ON u.id = a.user_id
                                                   WHERE q.outcome IN ('errore', 'limite') ORDER BY q.id DESC LIMIT 8") : [];
    View::out('admin/assistente', ['mese' => $mese, 'mesi' => Assistente::mesi(), 'righe' => Assistente::perCliente($mese), 'totali' => Assistente::totali($mese), 'errori' => $errori,
        'configurato' => Assistente::configurato(), 'config' => Assistente::config(), 'tetto' => Assistente::tetto(), 'limite' => Assistente::limite(0), 'nav' => 'assistente'], 'layout/cms');
});
$r->get('/admin/assistente/csv', function () use ($meseAssistente) {
    Auth::requireAdmin();
    $mese = $meseAssistente();
    $righe = [];
    foreach (Assistente::perCliente($mese) as $x) {
        $righe[] = [MHW\Codici::cliente((int) $x['account_id']), $x['nome'], $x['email'], $x['piano'], $x['guide'], $x['domande'], $x['senza'], $x['errori'], $x['fermate'], $x['token_in'], $x['token_out'], number_format($x['euro'], 4, ',', ''), $x['limite'], $x['percento'] . '%'];
    }
    Gestione::csv('assistente-' . $mese, ['Codice', 'Cliente', 'Email', 'Piano', 'Guide', 'Domande', 'Senza risposta', 'Errori', 'Fermate dal limite', 'Token in ingresso', 'Token in uscita', 'Costo stimato (€)', 'Limite mensile', 'Del limite'], $righe);
});

// ---------------------------------------------------------------- scadenze
/* Chi scade o si rinnova a breve, con l'ultimo avviso mandato e il prossimo automatico.
   «Manda il promemoria» parte subito, anche per più clienti insieme. */
$r->get('/admin/scadenze', function () {
    Auth::requireAdmin();
    $giorni = in_array((int) ($_GET['giorni'] ?? 30), [30, 60, 90, 180], true) ? (int) ($_GET['giorni'] ?? 30) : 30;
    $righe = Gestione::scadenze($giorni);
    if (($_GET['formato'] ?? '') === 'csv') {
        Gestione::csv('scadenze', ['Cliente', 'Email', 'Piano', 'Strutture', 'Scadenza', 'Giorni', 'Stato', 'Importo atteso (IVA esclusa)', 'Sconto rinnovo %', 'Ultimo avviso', 'Avviso a mano'],
            array_map(fn($x) => [$x['cliente'], $x['email'], $x['piano'], (int) ($x['quantity'] ?? 1), substr((string) $x['current_period_end'], 0, 10), $x['giorni'], $x['stato'][0],
                                 number_format($x['importo'] / 100, 2, ',', ''), $x['sconto'], $x['avviso'] ? substr((string) $x['avviso']['sent_at'], 0, 10) : '',
                                 $x['avviso'] && $x['avviso']['manuale'] ? 'sì' : ''], $righe));
    }
    $lock = MHW_APP . '/storage/richiami.lock';
    View::out('admin/scadenze', ['righe' => $righe, 'giorni' => $giorni, 'giro' => is_file($lock) ? gmdate('Y-m-d\TH:i:s\Z', (int) filemtime($lock)) : '',
                                 'cron' => (string) (Config::get('cron_token') ?? '') !== '', 'nav' => 'scadenze'], 'layout/cms');
});

$r->post('/admin/scadenze/promemoria', function () {
    Auth::requireAdmin();
    // «Manda il promemoria» su una riga manda solo quella; il bottone in fondo, le righe scelte.
    $ids = isset($_POST['solo']) ? [(int) $_POST['solo']] : array_values(array_unique(array_map('intval', (array) ($_POST['sub'] ?? []))));
    $torna = preg_match('#^/admin/(scadenze(\?giorni=\d+)?|cliente/\d+)$#', (string) ($_POST['torna'] ?? '')) ? (string) $_POST['torna'] : '/admin/scadenze';
    if (!$ids) { Support::flash('Scegli almeno un cliente.', 'err'); Support::redirect($torna); }
    $ok = 0; $no = [];
    foreach (array_slice($ids, 0, 100) as $id) {
        [$partito, $msg] = Richiami::promemoriaManuale($id);
        if ($partito) $ok++; else $no[] = $msg;
        Auth::audit('reminder.manual', (int) Db::val('SELECT a.user_id FROM subscriptions s JOIN accounts a ON a.id = s.account_id WHERE s.id = ?', [$id], 0) ?: null,
                    ['subscription' => $id, 'sent' => $partito]);
    }
    if (!$no) Support::flash($ok === 1 ? 'Promemoria mandato.' : "Promemoria mandati: $ok.");
    else Support::flash(($ok ? "Promemoria mandati: $ok. " : '') . 'Non partiti: ' . count($no) . '. ' . implode(' ', array_slice($no, 0, 5)), 'err');
    Support::redirect($torna);
});

/* 9N · Richiami: le email automatiche ai clienti (percorso, rinnovi, scadenze, eventi, inviti, giudizio) degli ultimi 30 giorni,
   per tipo, con chi ha detto «non mandarmene più». Per mandarne uno a mano: Scadenze o la scheda del cliente. */
$r->get('/admin/richiami', function () {
    Auth::requireAdmin();
    $attivi = Richiami::disponibili();
    $da = gmdate('Y-m-d\TH:i:s\Z', time() - 30 * 86400);
    $righe = $attivi ? Db::all('SELECT e.kind, e.ref, e.sent_at, e.account_id, u.email, u.name AS cliente FROM email_log e JOIN accounts a ON a.id = e.account_id
                                JOIN users u ON u.id = a.user_id WHERE e.sent_at >= ? ORDER BY e.sent_at DESC, e.id DESC LIMIT 300', [$da]) : [];
    $perTipo = $attivi ? array_column(Db::all('SELECT kind, COUNT(*) AS n FROM email_log WHERE sent_at >= ? GROUP BY kind', [$da]), 'n', 'kind') : [];
    $noGrazie = $attivi ? array_column(Db::all('SELECT kind, COUNT(*) AS n FROM email_optout GROUP BY kind'), 'n', 'kind') : [];
    if (($_GET['formato'] ?? '') === 'csv') {
        Gestione::csv('richiami', ['Quando (UTC)', 'Tipo', 'Cliente', 'Email', 'Riferimento', 'A mano'],
            array_map(fn($x) => [str_replace(['T', 'Z'], [' ', ''], (string) $x['sent_at']), $x['kind'], $x['cliente'], $x['email'], $x['ref'], str_starts_with((string) $x['ref'], 'manuale-') ? 'sì' : ''], $righe));
    }
    $lock = MHW_APP . '/storage/richiami.lock';
    View::out('admin/richiami', ['righe' => $righe, 'perTipo' => $perTipo, 'noGrazie' => $noGrazie, 'attivi' => $attivi, 'bozze' => Richiami::statoBozze(), 'sconti' => MHW\EmailSconti::elenco(), 'promemoriaGiudizio' => Richiami::statoPromemoriaGiudizio(),
                                 'giro' => is_file($lock) ? gmdate('Y-m-d\TH:i:s\Z', (int) filemtime($lock)) : '', 'cron' => (string) (Config::get('cron_token') ?? '') !== '',
                                 'nav' => 'richiami'], 'layout/cms');
});

/* 9P · «Manda adesso» l'email «Fino al 55%» a un cliente: anche prima dei 7 giorni, mai se saltata o con gli inviti spenti. */
$r->post('/admin/richiami/sconti/{id}', function (array $a) {
    Auth::requireAdmin();
    [$ok, $msg] = MHW\EmailSconti::mandaAdesso((int) $a['id']);
    if ($ok) Auth::audit('richiamo.sconti', (int) Db::val('SELECT user_id FROM accounts WHERE id = ?', [(int) $a['id']], 0) ?: null, ['account_id' => (int) $a['id']]);
    Support::flash($msg, $ok ? 'ok' : 'err');
    Support::redirect('/admin/richiami#sconti');
});

/* 9R · «Manda adesso» il promemoria del giudizio (dopo la pubblicazione o prima del rinnovo) a chi ha aderito senza lasciarlo. */
$r->post('/admin/richiami/giudizio/{id}/{quale}', function (array $a) {
    Auth::requireAdmin();
    [$ok, $msg] = Richiami::mandaPromemoriaGiudizio((int) $a['id'], (string) $a['quale']);
    if ($ok) Auth::audit('richiamo.giudizio', (int) Db::val('SELECT user_id FROM accounts WHERE id = ?', [(int) $a['id']], 0) ?: null, ['account_id' => (int) $a['id'], 'quale' => (string) $a['quale']]);
    Support::flash($msg, $ok ? 'ok' : 'err');
    Support::redirect('/admin/richiami#giudizio');
});

/* 9Q · «La finiamo noi»: dopo aver compilato la guida da «Entra come cliente», si segna nel Registro azioni. */
$r->post('/admin/cliente/{id}/compilata/{pid}', function (array $a) {
    Auth::requireAdmin();
    $acc = Db::one('SELECT * FROM accounts WHERE id = ?', [(int) $a['id']]);
    $p = $acc ? Db::one('SELECT id, name FROM properties WHERE id = ? AND account_id = ?', [(int) $a['pid'], (int) $acc['id']]) : null;
    if (!$p) { http_response_code(404); View::out('pub/404', []); }
    Auth::audit('guida.compilata_da_noi', (int) $acc['user_id'], ['property_id' => (int) $p['id']]);
    Support::flash('Segnato: la guida di ' . $p['name'] . ' è stata compilata da noi.');
    Support::redirect('/admin/cliente/' . (int) $acc['id']);
});

/* 9Q · «Manda adesso» l'email del giorno 7 o 21 per una bozza: le stesse regole del giro automatico, senza aspettare il giorno. */
$r->post('/admin/richiami/bozza/{id}/{tipo}', function (array $a) {
    Auth::requireAdmin();
    [$ok, $msg] = Richiami::mandaBozza((int) $a['id'], (string) $a['tipo']);
    if ($ok) Auth::audit('richiamo.bozza', (int) Db::val('SELECT a.user_id FROM properties p JOIN accounts a ON a.id = p.account_id WHERE p.id = ?', [(int) $a['id']], 0) ?: null,
                         ['property_id' => (int) $a['id'], 'tipo' => (string) $a['tipo']]);
    Support::flash($msg, $ok ? 'ok' : 'err');
    Support::redirect('/admin/richiami#bozze');
});

// ---------------------------------------------------------------- anomalie
$r->get('/admin/anomalie', function () {
    Auth::requireAdmin();
    View::out('admin/anomalie', Gestione::anomalie() + ['nav' => 'anomalie'], 'layout/cms');
});

/* 9R · Portfolio: la quantità pagata su Stripe confrontata con il sito; «Allinea al valore di Stripe». */
$r->post('/admin/anomalie/portfolio/ricontrolla', function () {
    Auth::requireAdmin();
    $esito = Gestione::verificaPortfolio();
    $diversi = count(array_filter($esito, fn($x) => $x['stripe'] !== null && $x['stripe'] !== $x['sito']));
    Support::flash(count($esito) . ' Portfolio controllati su Stripe' . ($diversi ? ': ' . $diversi . ' con la quantità diversa.' : ': tutti allineati.'), $diversi ? 'err' : 'ok');
    Support::redirect('/admin/anomalie');
});
$r->post('/admin/anomalie/portfolio/{sid}/allinea', function (array $a) {
    $admin = Auth::requireAdmin();
    try { Support::flash(Gestione::allineaPortfolio((int) $a['sid'], (int) $admin['id'])); }
    catch (\RuntimeException $e) { Support::flash($e->getMessage(), 'err'); }
    Support::redirect('/admin/anomalie');
});

// --------------------------------------------------------------- prospetti
$r->get('/admin/prospetti', function () {
    Auth::requireAdmin();
    $mesi = Gestione::mesi();
    if (($_GET['formato'] ?? '') === 'csv') {
        $eur = fn(int $c) => number_format($c / 100, 2, ',', '');
        Gestione::csv('prospetti', ['Mese', 'Registrati', 'Nuovi abbonati', 'Incasso nuovi', 'Incasso cambi di piano', 'Rinnovi', 'Incasso rinnovi', 'Incasso totale', 'Persi'],
            array_map(fn($m) => [$m['mese'], $m['registrati'], $m['nuovi'], $eur($m['incasso_nuovi']), $eur($m['incasso_cambi']), $m['rinnovi'], $eur($m['incasso_rinnovi']),
                                 $eur($m['incasso']), $m['persi']], $mesi));
    }
    // «Cosa ti ha fermato?» (7A): quante risposte per motivo.
    $pareri = \MHW\Migrator::tableExists('feedback')
        ? array_column(Db::all('SELECT motivo, COUNT(*) AS n FROM feedback WHERE 1 = 1' . Gestione::senzaProva('account_id') . ' GROUP BY motivo'), 'n', 'motivo') : null;
    // 8A: chi ha completato «Anche il tuo sito» (home e pannello), dal più recente.
    $interessati = \MHW\Migrator::tableExists('interessi_sito')
        ? Db::all('SELECT i.*, u.name AS cliente, u.email FROM interessi_sito i LEFT JOIN accounts a ON a.id = i.account_id LEFT JOIN users u ON u.id = a.user_id
                   WHERE (i.account_id IS NULL OR 1 = 1' . Gestione::senzaProva('i.account_id') . ') ORDER BY i.id DESC LIMIT 100') : null;
    View::out('admin/prospetti', ['mesi' => $mesi, 'adesso' => Gestione::adesso(), 'pareri' => $pareri, 'interessati' => $interessati, 'nav' => 'prospetti'], 'layout/cms');
});

/*
 * 9N · Cassa: gli incassi di un anno mese per mese (nuovi, cambi, rinnovi; Stripe e bonifici; imponibile, IVA, totale,
 * rimborsi), il totale e l'anno precedente; un mese aperto mostra i pagamenti. Stessa fonte di Prospetti (Gestione::pagamenti).
 * CSV: i mesi (?formato=csv) o i pagamenti (?formato=csv&pagamenti=1, dell'anno o del mese scelto).
 */
$r->get('/admin/cassa', function () {
    Auth::requireAdmin();
    $anno = (int) ($_GET['anno'] ?? gmdate('Y'));
    if ($anno < 2020 || $anno > (int) gmdate('Y') + 1) $anno = (int) gmdate('Y');
    $mese = preg_match('/^' . $anno . '-(0[1-9]|1[0-2])$/', (string) ($_GET['mese'] ?? '')) ? (string) $_GET['mese'] : '';
    $eur = fn(?int $c) => $c === null ? '' : number_format($c / 100, 2, ',', '');
    $nomi = ['nuovo' => 'Nuovo abbonamento', 'cambio' => 'Cambio di piano', 'rinnovo' => 'Rinnovo', 'rimborso' => 'Rimborso'];
    if (($_GET['formato'] ?? '') === 'csv') {
        if (!empty($_GET['pagamenti'])) {
            [$da, $a] = $mese !== '' ? [$mese . '-01', gmdate('Y-m-01', strtotime($mese . '-01 +1 month'))] : [$anno . '-01-01', ($anno + 1) . '-01-01'];
            // 9R · Anche i pagamenti esclusi dai conti, con la colonna «Escluso» (sì e il motivo).
            Gestione::csv('cassa-pagamenti-' . ($mese ?: $anno), ['Data', 'Codice', 'Cliente', 'Email', 'Tipo', 'Via', 'Piano', 'Sconti', 'Imponibile', 'IVA', 'Totale', 'Riferimento', 'Fattura', 'Escluso'],
                array_map(fn($p) => [substr($p['data'], 0, 10), $p['codice'], $p['cliente'], $p['email'], $nomi[$p['tipo']], Gestione::VIE_INCASSO[$p['via']] ?? $p['via'], $p['piano'],
                                     $p['sconti'], $eur($p['imponibile']), $eur($p['iva']), $eur($p['totale']), $p['riferimento'], $p['fattura'],
                                     ($p['escluso'] ?? '') !== '' ? 'sì · ' . $p['escluso'] : ''], Gestione::pagamenti($da, $a, true)));
        }
        $c = Gestione::cassa($anno);
        $riga = fn(string $et, array $m) => [$et, $m['nuovi'], $m['cambi'], $m['rinnovi'], $eur($m['stripe']), $eur($m['bonifico']), $eur($m['imponibile']), $eur($m['iva']),
                                             $eur($m['totale']), $eur($m['rimborsi']), $eur($m['netto'])];
        Gestione::csv('cassa-' . $anno, ['Mese', 'Nuovi', 'Cambi di piano', 'Rinnovi', 'Stripe (imponibile)', 'Bonifici (imponibile)', 'Imponibile', 'IVA', 'Totale', 'Rimborsi', 'Netto incassato'],
            [...array_map(fn($m) => $riga($m['mese'], $m), $c['mesi']), $riga('Totale ' . $anno, $c['totale'])]);
    }
    $pagamenti = [];
    if ($mese !== '') $pagamenti = Gestione::pagamenti($mese . '-01', gmdate('Y-m-01', strtotime($mese . '-01 +1 month')), true);
    View::out('admin/cassa', ['anno' => $anno, 'mese' => $mese, 'cassa' => Gestione::cassa($anno), 'prima' => Gestione::cassa($anno - 1)['totale'],
                              'pagamenti' => $pagamenti, 'nomi' => $nomi, 'nav' => 'cassa'], 'layout/cms');
});

/* 9R · «Attiva come staff» (piano, strutture, fino al, «non in vetrina») e «Togli lo staff». */
$r->post('/admin/cliente/{id}/staff', function (array $a) {
    Auth::requireAdmin();
    $aid = (int) $a['id'];
    try {
        if (!empty($_POST['togli'])) { MHW\Staff::togli($aid); Support::flash('Non è più un account staff: torna un cliente normale, senza abbonamento attivo.'); }
        else {
            MHW\Staff::attiva($aid, (int) ($_POST['pv'] ?? 0), (int) ($_POST['strutture'] ?? 1), (string) ($_POST['fino'] ?? ''), empty($_POST['no_vetrina']));
            Support::flash('Account staff attivo: niente pagamenti, fuori dai conti.');
        }
    } catch (\RuntimeException $e) { Support::flash($e->getMessage(), 'err'); }
    Support::redirect('/admin/cliente/' . $aid . '#staff');
});

/* «Collega a un invito»: l'amico che non è rimasto legato al link di chi l'ha invitato (o il cui invito è stato annullato) si lega al codice. */
$r->post('/admin/cliente/{id}/invito', function (array $a) {
    Auth::requireAdmin();
    $aid = (int) $a['id'];
    try {
        $stato = MHW\Inviti::collegaAMano($aid, (string) ($_POST['codice'] ?? ''), (int) Auth::user()['id']);
        Support::flash($stato === 'registrato' ? 'Collegato: l\'invito si accende quando questo cliente paga.' : ($stato === 'oltre' ? 'Collegato, ma chi invita ha già i suoi ' . MHW\Inviti::amiciMassimi() . ' amici: conta oltre il massimo.' : 'Collegato: l\'invito conta e lo sconto di chi invita è aggiornato.'));
    } catch (\RuntimeException $e) { Support::flash($e->getMessage(), 'err'); }
    Support::redirect('/admin/cliente/' . $aid . '#inviti');
});

/* 9R · «Proponi un cambio di piano»: la proposta con lo sconto, per email (bottone «Accetta il cambio») o per WhatsApp. */
$r->post('/admin/cliente/{id}/proposta', function (array $a) {
    $admin = Auth::requireAdmin();
    $aid = (int) $a['id'];
    try {
        $pr = MHW\ProposteCambio::crea($aid, (string) ($_POST['piano'] ?? ''), $_POST['strutture'] ?? null, (string) ($_POST['quando'] ?? 'subito'),
                                       (int) ($_POST['sconto'] ?? 0), (int) ($_POST['giorni'] ?? MHW\ProposteCambio::GIORNI), (string) ($_POST['messaggio'] ?? ''), (int) $admin['id']);
        $riga = Db::one('SELECT * FROM proposte_cambio WHERE id = ?', [$pr['id']]);
        $_SESSION['proposta_wa'] = MHW\ProposteCambio::whatsapp($riga, $pr['token']);
        if (($_POST['invio'] ?? '') === 'email') {
            $ok = MHW\ProposteCambio::manda($riga, $pr['token']);
            Support::flash($ok ? 'Proposta mandata per email. Qui sotto anche il messaggio per WhatsApp.' : 'Proposta creata, ma l\'email non è partita: usa il messaggio per WhatsApp qui sotto.', $ok ? 'ok' : 'err');
        } else Support::flash('Proposta creata: copia il messaggio per WhatsApp qui sotto.');
    } catch (\RuntimeException $e) { Support::flash($e->getMessage(), 'err'); }
    Support::redirect('/admin/cliente/' . $aid . '#proposta');
});
$r->post('/admin/cliente/{id}/proposta/{pid}/annulla', function (array $a) {
    Auth::requireAdmin();
    MHW\ProposteCambio::annulla((int) $a['pid'], (int) $a['id']);
    Support::flash('Proposta annullata: il link non vale più.');
    Support::redirect('/admin/cliente/' . (int) $a['id'] . '#proposta');
});

/* 9R · «Elimina account di prova»: solo senza pagamenti live, con «ELIMINA {codice cliente}». */
$r->post('/admin/cliente/{id}/elimina-prova', function (array $a) {
    $admin = Auth::requireAdmin();
    try {
        $email = MHW\AccountProva::elimina((int) $a['id'], (string) ($_POST['conferma'] ?? ''), (int) $admin['id']);
        Support::flash('Account di prova eliminato: ' . $email . ' è di nuovo libera per registrarsi.');
        Support::redirect('/admin/clienti');
    } catch (\RuntimeException $e) {
        Support::flash($e->getMessage(), 'err');
        Support::redirect('/admin/cliente/' . (int) $a['id'] . '#elimina-prova');
    }
});

/* 10H · «Elimina account»: solo da qui, con «ELIMINA {codice}», il motivo e la password dell'amministratore (EliminaAccount). */
$r->post('/admin/cliente/{id}/elimina', function (array $a) {
    $admin = Auth::requireAdmin();
    $aid = (int) $a['id'];
    if (!RateLimit::hit('elimina-account:' . (int) $admin['id'], 10, 900)) { Support::flash('Troppi tentativi: riprova tra un quarto d\'ora.', 'err'); Support::redirect('/admin/cliente/' . $aid . '#elimina'); }
    $hash = (string) Db::val('SELECT password_hash FROM users WHERE id = ?', [(int) $admin['id']], '');
    if (!password_verify((string) ($_POST['password'] ?? ''), $hash)) { Support::flash('La password non è giusta: non è stato cancellato niente.', 'err'); Support::redirect('/admin/cliente/' . $aid . '#elimina'); }
    try {
        $esito = MHW\EliminaAccount::elimina($aid, (string) ($_POST['conferma'] ?? ''), (string) ($_POST['motivo'] ?? ''), !empty($_POST['avvisa']), (int) $admin['id']);
        $abb = $esito['stripe'] ? ' Abbonamento chiuso su Stripe (' . implode(', ', $esito['stripe']) . ').' : '';
        if ($esito['modo'] === 'completa') { Support::flash('Account eliminato: ' . $esito['email'] . ' è di nuovo libera per registrarsi.' . $abb); Support::redirect('/admin/clienti'); }
        Support::flash('Account eliminato: guide, foto e dati personali cancellati, ' . $esito['email'] . ' di nuovo libera. Restano solo i dati di fatturazione e i pagamenti, per legge.' . $abb);
        Support::redirect('/admin/cliente/' . $aid);
    } catch (\RuntimeException $e) {
        Support::flash($e->getMessage(), 'err');
        Support::redirect('/admin/cliente/' . $aid . '#elimina');
    }
});

/* 9R · «Escludi dai conti» (con il motivo) / «Rimetti» un pagamento della Cassa. */
$r->post('/admin/cassa/esclusione', function () {
    $admin = Auth::requireAdmin();
    $rif = (string) ($_POST['rif'] ?? '');
    try {
        if (!empty($_POST['rimetti'])) { Gestione::rimetti($rif, (int) $admin['id']); Support::flash('Pagamento rimesso nei conti.'); }
        else { Gestione::escludi($rif, (string) ($_POST['motivo'] ?? ''), (int) $admin['id']); Support::flash('Pagamento escluso dai conti: resta nell\'elenco, barrato.'); }
    } catch (\RuntimeException $e) { Support::flash($e->getMessage(), 'err'); }
    [$an, $me] = explode('|', (string) ($_POST['torna'] ?? ''), 2) + ['', ''];
    Support::redirect('/admin/cassa' . (preg_match('/^\d{4}$/', $an) ? '?anno=' . $an . (preg_match('/^\d{4}-\d{2}$/', $me) ? '&mese=' . $me . '#pagamenti' : '') : ''));
});

/* 9O · Cassa → «Commissioni partner»: un costo, a parte dagli incassi. Per partner le commissioni da liquidare, «Segna come pagata», CSV. */
$r->get('/admin/cassa/partner', function () {
    Auth::requireAdmin();
    $anno = (int) gmdate('Y');
    $dl = MHW\ProgrammaPartner::daLiquidare();
    if (($_GET['formato'] ?? '') === 'csv') {
        $eur = fn(int $c) => number_format($c / 100, 2, ',', '');
        $righe = [];
        foreach (Db::all('SELECT c.*, p.codice, p.ragione_sociale FROM partner_commissioni c JOIN partner p ON p.id = c.partner_id ORDER BY c.created_at, c.id') as $c) {
            $righe[] = [substr((string) $c['created_at'], 0, 10), $c['codice'], $c['ragione_sociale'], MHW\Codici::cliente((int) $c['account_id']), $c['codice_pagamento'],
                        $c['tipo'] === 'rinnovo' ? 'Rinnovo' : 'Primo anno', $eur((int) $c['imponibile_cents']), (int) $c['pct'] . '%', $eur((int) $c['importo_cents']), $c['stato'], (string) ($c['liquidazione_id'] ?? '')];
        }
        Gestione::csv('commissioni-partner', ['Data', 'Codice partner', 'Partner', 'Cliente', 'Pagamento', 'Tipo', 'Imponibile', 'Percentuale', 'Commissione', 'Stato', 'Liquidazione'], $righe);
    }
    View::out('admin/cassa_partner', ['dl' => $dl, 'numeri' => MHW\ProgrammaPartner::numeri($anno), 'anno' => $anno,
        'liquidazioni' => MHW\ProgrammaPartner::disponibili() ? Db::all('SELECT l.*, p.codice, p.ragione_sociale FROM partner_liquidazioni l JOIN partner p ON p.id = l.partner_id ORDER BY l.data_bonifico DESC, l.id DESC LIMIT 50') : [],
        'nav' => 'cassa'], 'layout/cms');
});
$r->post('/admin/cassa/partner/{id}/pagata', function (array $a) {
    $admin = Auth::requireAdmin();
    try { MHW\ProgrammaPartner::liquida((int) $a['id'], $_POST, (int) $admin['id']); Support::flash('Liquidazione registrata: le commissioni sono «liquidate» e il partner ha ricevuto l\'email.'); }
    catch (\RuntimeException $e) { Support::flash($e->getMessage(), 'err'); }
    Support::redirect('/admin/cassa/partner');
});

/* 9O · Il programma partner: «Approva e crea il partner» (anche da zero), la scheda con i clienti, le modifiche, lo stato. */
$r->get('/admin/partner/nuovo', function () {
    Auth::requireAdmin();
    $rq = MHW\Partner::disponibili() && !empty($_GET['richiesta']) ? Db::one('SELECT * FROM partner_richieste WHERE id = ?', [(int) $_GET['richiesta']]) : null;
    $v = (array) ($_SESSION['partner_valori'] ?? []); unset($_SESSION['partner_valori']);
    $v += ['ragione_sociale' => (string) ($rq['societa'] ?? ''), 'referente' => (string) ($rq['nome'] ?? ''), 'email' => (string) ($rq['email'] ?? ''), 'telefono' => (string) ($rq['telefono'] ?? ''),
           'zona' => (string) ($rq['zone'] ?? ''), 'commissione_pct' => MHW\Partner::commissione() ?? 15, 'sconto_clienti_pct' => MHW\Partner::sconto() ?? 10, 'rinnovi' => 1,
           'iban' => '', 'piva' => '', 'periodicita' => 'trimestrale', 'lingua' => 'it', 'note' => '', 'codice' => ''];
    if ($v['codice'] === '' && MHW\ProgrammaPartner::disponibili()) $v['codice'] = MHW\ProgrammaPartner::genera((string) ($v['ragione_sociale'] ?: $v['referente']) ?: 'partner');
    View::out('admin/partner_scheda', ['p' => null, 'v' => $v, 'richiesta' => $rq, 'nav' => 'partner'], 'layout/cms');
});
$r->post('/admin/partner/crea', function () {
    $admin = Auth::requireAdmin();
    $rid = (int) ($_POST['richiesta'] ?? 0) ?: null;
    try {
        $p = MHW\ProgrammaPartner::crea($_POST, $rid, (int) $admin['id']);
        Support::flash('Partner creato: codice ' . $p['codice'] . '. Gli abbiamo mandato l\'email di benvenuto con il codice, il link e i materiali.');
        Support::redirect('/admin/partner/p/' . (int) $p['id']);
    } catch (\RuntimeException $e) {
        $_SESSION['partner_valori'] = $_POST;
        Support::flash($e->getMessage(), 'err');
        Support::redirect('/admin/partner/nuovo' . ($rid ? '?richiesta=' . $rid : ''));
    }
});
$r->get('/admin/partner/p/{id}', function (array $a) {
    Auth::requireAdmin();
    $p = MHW\ProgrammaPartner::riga((int) $a['id']);
    if (!$p) { http_response_code(404); View::out('pub/404', []); }
    $storico = array_values(array_filter(Db::all("SELECT * FROM audit_log WHERE action LIKE 'partner.%' ORDER BY id DESC LIMIT 400"),
        fn($x) => (int) ((json_decode((string) $x['meta'], true) ?: [])['partner'] ?? 0) === (int) $p['id']));
    View::out('admin/partner_scheda', ['p' => $p, 'v' => $p, 'richiesta' => null, 'clienti' => MHW\ProgrammaPartner::clienti((int) $p['id']),
        'liquidazioni' => Db::all('SELECT * FROM partner_liquidazioni WHERE partner_id = ? ORDER BY data_bonifico DESC, id DESC', [(int) $p['id']]),
        'storico' => array_slice($storico, 0, 60), 'riep' => MHW\ProgrammaPartner::riepilogo((int) $p['id']), 'nav' => 'partner'], 'layout/cms');
});
$r->post('/admin/partner/p/{id}', function (array $a) {
    $admin = Auth::requireAdmin();
    $id = (int) $a['id'];
    try {
        if (($_POST['azione'] ?? '') === 'materiali') {
            $p = MHW\ProgrammaPartner::riga($id);
            if (!$p) throw new \RuntimeException('Partner non trovato.');
            MHW\ProgrammaPartner::emailBenvenuto($p);
            Auth::audit('partner.materiali', null, ['partner' => $id, 'codice' => $p['codice']]);
            Support::flash('Codice, link e materiali rimandati a ' . $p['email'] . '.');
        } elseif (in_array($_POST['azione'] ?? '', ['attivo', 'sospeso', 'chiuso'], true)) {
            MHW\ProgrammaPartner::stato($id, (string) $_POST['azione'], (int) $admin['id']);
            Support::flash('Partner ' . MHW\ProgrammaPartner::STATI[(string) $_POST['azione']] . '.');
        } else {
            MHW\ProgrammaPartner::aggiorna($id, $_POST, (int) $admin['id']);
            Support::flash('Partner salvato.');
        }
    } catch (\RuntimeException $e) { Support::flash($e->getMessage(), 'err'); }
    Support::redirect('/admin/partner/p/' . $id);
});

/* Vendite per piano: che piani si comprano, mese per mese, con grafici e tabella. */
$r->get('/admin/vendite', function () {
    Auth::requireAdmin();
    $mesi = (int) ($_GET['mesi'] ?? 12);
    if (!in_array($mesi, [3, 6, 12], true)) $mesi = 12;
    $v = Gestione::vendite($mesi);
    if (($_GET['formato'] ?? '') === 'csv') {
        $eur = fn(int $c) => number_format($c / 100, 2, ',', '');
        $righe = [];
        foreach ($v['mesi'] as $m) foreach (Gestione::PIANI_VENDITE as $k => $nome) {
            $righe[] = [$m['mese'], $nome, $m['nuovi'][$k], $m['cambi'][$k], $eur($m['incasso'][$k])];
        }
        Gestione::csv('vendite-per-piano', ['Mese', 'Piano', 'Nuovi abbonamenti', 'Cambi di piano verso', 'Incasso'], $righe);
    }
    View::out('admin/vendite', ['v' => $v, 'mesi' => $mesi, 'nav' => 'vendite'], 'layout/cms');
});

/* 7F: Vendite → Marketing: guide, percorso, sezioni, lingue, guide più visitate, inviti. Solo lettura, clienti di prova esclusi. */
$r->get('/admin/marketing', function () {
    Auth::requireAdmin();
    $periodo = \MHW\Marketing::periodo((string) ($_GET['periodo'] ?? '90'));
    $tabelle = \MHW\Marketing::tabelle($periodo);
    if (($_GET['formato'] ?? '') === 'csv') {
        $k = (string) ($_GET['tabella'] ?? '');
        if (!isset($tabelle[$k])) { http_response_code(404); View::out('pub/404', []); }
        $tab = $tabelle[$k];
        $col = $tab['colonne']; $cli = $tab['cliente'] ?? null;
        if ($cli !== null) $col[$cli] = 'Scheda cliente';
        $righe = array_map(function ($x) use ($cli) {
            if ($cli !== null) $x[$cli] = Support::baseUrl() . '/admin/cliente/' . (int) $x[$cli];
            return $x;
        }, $tab['righe']);
        Gestione::csv('marketing-' . $k . '-' . $periodo, array_map(fn($c) => $c === '' ? 'Voce' : $c, $col), $righe);
    }
    View::out('admin/marketing', ['tabelle' => $tabelle, 'periodo' => $periodo, 'nav' => 'marketing'], 'layout/cms');
});

// --------------------------------------------------------------- diagnostica
/**
 * Queste voci non sono promesse: il server interroga davvero sé stesso,
 * e prova perfino a scaricare il proprio database.
 */
$r->get('/admin/diagnostica', function () {
    Auth::requireAdmin();
    $base = Support::baseUrl();
    // Le sonde cercano i file veri: senza riscrittura degli indirizzi la base
    // finisce con /index.php, che qui va tolto.
    $cartella = preg_replace('#/index\.php$#', '', $base);
    $checks = [];
    $probe = function (string $url): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 6, CURLOPT_FOLLOWLOCATION => false]);
        $body = (string) curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['code' => $code, 'bytes' => strlen($body)];
    };

    // 9K · I limiti di PHP per le foto.
    $limiti = Media::limitiServer();
    $bassiL = array_filter($limiti, fn($l) => $l[3]);
    $checks[] = ['Limiti per il caricamento delle foto', !$bassiL, implode(' · ', array_map(fn($l) => $l[0] . ' = ' . $l[1] . ($l[3] ? ' (sotto ' . $l[2] . ')' : ''), $limiti))
        . ($bassiL ? '. Chiedi all\'hosting upload_max_filesize = 20M, post_max_size = 20M e memory_limit = 256M (cPanel: «Seleziona versione PHP» → Opzioni, o «MultiPHP INI Editor»).' : '.')];

    $inWebroot = str_starts_with(realpath(MHW_APP) ?: '', realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: "\0");
    $checks[] = ['Applicazione fuori dalla cartella pubblica', !$inWebroot,
        $inWebroot ? "L'applicazione sta dentro la cartella pubblica: la protezione dipende da .htaccess, che non tutti i server leggono. Se le righe qui sotto sono «OK» i file sono protetti; per toglierli del tutto dal web segui DA-CONFIGURARE → «L'applicazione fuori dalla cartella pubblica»."
                   : 'Niente del codice è raggiungibile dal web.'];
    if ($inWebroot) {
        $dbFile = basename(\MHW\Db::sqlitePath(Config::get('db')['sqlite_path']));
        $res = $probe($cartella . '/app/storage/' . $dbFile);
        $esposto = $res['code'] === 200 && $res['bytes'] > 0;
        $checks[] = ['Database non scaricabile dal web', !$esposto,
            $esposto ? 'ATTENZIONE: il database risponde dal web. Sposta la cartella app/ sopra la radice pubblica.' : 'Il server rifiuta la richiesta.'];
        // Il server può rifiutare (403/404, con la sua pagina d'errore) oppure eseguirlo: config.php non stampa niente.
        // Solo un 200 con del testo vuol dire che il contenuto esce davvero.
        $res = $probe($cartella . '/app/config.php');
        $esce = $res['code'] === 200 && $res['bytes'] > 0;
        $checks[] = ['config.php non leggibile', !$esce, $esce ? 'ATTENZIONE: config.php restituisce contenuto (risposta ' . $res['code'] . '). Controlla che app/.htaccess sia stato caricato.'
            : ($res['code'] === 200 ? 'Non esce nulla.' : 'Il server rifiuta la richiesta (risposta ' . $res['code'] . ').')];
        // 8I: i registri del giorno (si crea il file, se ancora non c'è, per provarlo davvero).
        foreach (['mail' => 'Posta di prova non leggibile dal web (contiene link di accesso)'] as $tipoLog => $titolo) {
            @touch(MHW\Log::file($tipoLog));
            $res = $probe($cartella . '/app/storage/logs/' . basename(MHW\Log::file($tipoLog)));
            $checks[] = [$titolo, !($res['code'] === 200), $res['code'] === 200 ? 'ATTENZIONE: storage/logs è raggiungibile dal web.' : 'Il server rifiuta la richiesta.'];
        }
        @touch(MHW\Log::file('app'));
        $res = $probe($cartella . '/app/storage/logs/' . basename(MHW\Log::file('app')));
        $checks[] = ['Registro tecnico non leggibile dal web', !($res['code'] === 200 && $res['bytes'] > 0),
            $res['code'] === 200 && $res['bytes'] > 0 ? 'ATTENZIONE: storage/logs è raggiungibile dal web.' : 'Il server rifiuta la richiesta.'];
    }

    $checks[] = ['Estensione GD (immagini e QR)', extension_loaded('gd'), phpversion('gd') ?: 'assente'];
    $checks[] = ['Estensione fileinfo (controllo dei PDF)', extension_loaded('fileinfo'), extension_loaded('fileinfo') ? 'presente' : 'assente'];
    $checks[] = ['Connessione al database', (bool) \MHW\Db::val('SELECT 1'), Config::get('db')['driver']];
    $checks[] = ['Pagamenti Stripe', Stripe::enabled(),
        Stripe::enabled() ? 'chiave segreta e segreto del webhook presenti' : 'mancano STRIPE_SECRET_KEY o STRIPE_WEBHOOK_SECRET: nessuno può pubblicare'];
    $checks[] = ['Indirizzo del webhook', true, 'In Stripe punta il webhook a ' . $base . '/webhook/stripe'];
    // Il registro del webhook: l'ultimo accettato, l'ultimo rifiutato (con il motivo), l'ultimo pagamento confermato dal recupero.
    $rw = MHW\RecuperoPagamenti::registro(); $pw = MHW\RecuperoPagamenti::problema();
    $checks[] = ['Webhook di Stripe', $pw === null, implode(' · ', array_filter([
        isset($rw['accettato']) ? 'ultimo ricevuto ' . $rw['accettato']['quando'] . ' (' . $rw['accettato']['tipo'] . ')' : 'nessun webhook ricevuto finora',
        isset($rw['rifiutato']) ? 'ultimo rifiutato ' . $rw['rifiutato']['quando'] . ': ' . $rw['rifiutato']['motivo'] : '',
        isset($rw['recuperato']) ? 'ultimo confermato dal recupero ' . $rw['recuperato']['quando'] . ' (ordine ' . (int) $rw['recuperato']['ordine'] . ')' : '',
    ]))];
    $mail = Config::get('mail');
    $checks[] = ['Posta in uscita', $mail['transport'] !== 'log',
        $mail['transport'] === 'log' ? 'le email finiscono nei registri della posta (storage/logs/mail-AAAA-MM-GG.log): imposta il server SMTP nelle Impostazioni' : 'trasporto ' . $mail['transport']];
    $st = Config::get('storage');
    $s3ok = false; $s3det = 'MHW_STORAGE non è s3: i file restano sul disco del server';
    if ($st['driver'] === 's3') {
        try { Storages::current(); $s3ok = true; $s3det = 'bucket ' . $st['s3']['bucket'] . ' (' . $st['s3']['region'] . ')'; }
        catch (\Throwable $e) { $s3det = $e->getMessage(); }
    }
    // L'ultimo errore S3 nel registro tecnico: quello che un cliente ha visto come «Archiviazione non disponibile».
    $ultimoS3 = null;
    if ($st['driver'] === 's3') {
        foreach (array_reverse(MHW\Registri::righe('app', 7, 2000)) as $riga) {   // 8I: dai registri degli ultimi 7 giorni
            $j = json_decode($riga, true);
            if (is_array($j) && str_starts_with((string) ($j['msg'] ?? ''), 'S3 ')) { $ultimoS3 = $j; break; }
        }
    }
    if ($ultimoS3) {
        $dett = 'HTTP ' . (int) ($ultimoS3['ctx']['code'] ?? 0) . (($ultimoS3['ctx']['err'] ?? '') !== '' ? ' · ' . $ultimoS3['ctx']['err'] : '')
              . (($ultimoS3['ctx']['body'] ?? '') !== '' ? ' · ' . MHW\S3Storage::codiceDi((string) $ultimoS3['ctx']['body']) : '');
        $cosa = MHW\S3Storage::consiglio($dett);
        $recente = strtotime((string) ($ultimoS3['t'] ?? '')) > time() - 86400;
        if ($recente) $s3ok = false;
        $s3det .= '. Ultimo errore (' . Support::date((string) ($ultimoS3['t'] ?? '')) . '): «' . $dett . '».' . ($cosa !== '' ? ' ' . $cosa : '')
                . ' Prova da Impostazioni → Archivio → «Prova».';
    }
    $checks[] = ['Foto e PDF su Amazon S3', $s3ok, $s3det];
    // 10 · «Chiedi alla guida»: la chiave c'è? (la prova vera è in Impostazioni → Chiedi alla guida)
    $checks[] = ['«Chiedi alla guida» (assistente AI)', Assistente::configurato(), Assistente::configurato()
        ? 'chiave impostata, modello ' . Assistente::modello() . ', ' . Assistente::limite(0) . ' domande al mese per struttura, tetto ' . number_format(Assistente::tetto(), 2, ',', '.') . ' €/mese. Prova la connessione da Impostazioni → Chiedi alla guida.'
        : 'manca la chiave API di Anthropic: i clienti Plus e Portfolio non vedono il bottone nella guida. Impostazioni → Chiedi alla guida.'];
    $checks[] = ['HTTPS', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || str_starts_with($base, 'https'),
        'i cookie di sessione diventano "secure" solo su HTTPS'];
    [$pulitiOk, $pulitiMsg] = Impostazioni::provaIndirizzi(rtrim((string) Config::get('base_url'), '/'));
    $checks[] = ['Indirizzi puliti', $pulitiOk, $pulitiOk
        ? $pulitiMsg . (Config::get('pretty_urls') === true ? ' Accesi in Impostazioni → Sito.' : ' Accendili in Impostazioni → Sito.')
        : $pulitiMsg . ' Finché non va, gli indirizzi restano con index.php e funzionano lo stesso.'];
    // 7F: robots.txt, sitemap.xml e llms.txt anche come file veri accanto a index.php.
    $esitoSeo = MHW\Seo::esitoFile();
    $checks[] = ['File per motori e assistenti (robots.txt, sitemap.xml, llms.txt)', MHW\Seo::scrivibili() && (!$esitoSeo || $esitoSeo['ok']),
        !MHW\Seo::scrivibili() ? 'La cartella principale del sito non è scrivibile: i tre file restano solo come pagine, e senza riscrittura degli indirizzi i motori non li trovano.'
        : ($esitoSeo ? $esitoSeo['msg'] . ' Ultima volta: ' . Support::date($esitoSeo['quando']) . '.' : 'Cartella scrivibile: i file si scrivono al prossimo salvataggio di SEO e GEO.')];
    // 9N · GA4 e Clarity sono nell'informativa (con il consenso): se mancano gli ID il banner non compare e l'informativa promette cose che non ci sono.
    $ga4 = trim(MHW\Seo::get('stat.ga4')); $clarity = trim(MHW\Seo::get('stat.clarity'));
    $checks[] = ['Statistiche con consenso (GA4 e Clarity)', $ga4 !== '' && $clarity !== '', $ga4 !== '' && $clarity !== ''
        ? 'GA4 ' . $ga4 . ' e Clarity ' . $clarity . ' configurati: il banner dei cookie compare ai visitatori e parte solo dopo il consenso.'
        : 'GA4/Clarity dichiarati in privacy ma non configurati' . ($ga4 === '' && $clarity === '' ? '' : ' (manca ' . ($ga4 === '' ? 'GA4' : 'Clarity') . ')') . ': inserisci gli ID in SEO e GEO → Statistiche delle visite, oppure togli i riferimenti dall\'informativa (Testi e foto → Documenti legali).'];
    $checks[] = ['Indirizzo pubblico impostato', Config::get('base_url') !== '',
        Config::get('base_url') !== '' ? Config::get('base_url') : 'MHW_BASE_URL vuoto: QR ed email usano l\'indirizzo della richiesta'];
    $daCompletare = MHW\CambioAbbonamento::inSospeso();
    $checks[] = ['Cambi di piano pagati da completare: ' . $daCompletare, $daCompletare === 0, $daCompletare === 0
        ? 'Nessuno: ogni differenza pagata è già applicata, su Stripe e nel sito.'
        : 'Pagati ma non ancora applicati su Stripe: il sito riprova da solo ogni 15 minuti (o col cron). Se restano, controlla le chiavi di Stripe.'];
    // 7C: con l'esito dell'ultima chiamata ad Amazon, e il messaggio esatto se non è andata.
    $ultimaT = \MHW\Migrator::tableExists('translation_usage') ? Db::one("SELECT outcome, error, created_at FROM translation_usage WHERE outcome IN ('ok', 'errore') ORDER BY id DESC LIMIT 1") : null;
    $tradOk = MHW\Traduttore::configurato() && (!$ultimaT || $ultimaT['outcome'] === 'ok');
    $checks[] = ['Traduzioni suggerite (Amazon Translate)', $tradOk, !MHW\Traduttore::configurato()
        ? 'Non collegate: senza chiavi i clienti Plus e Portfolio leggono «Le traduzioni non sono disponibili in questo momento». Le chiavi si scrivono in Impostazioni → Traduzioni.'
        : ($ultimaT && $ultimaT['outcome'] === 'errore'
            ? 'L\'ultima chiamata (' . Support::date((string) $ultimaT['created_at']) . ') non è riuscita: «' . $ultimaT['error'] . '». ' . MHW\Traduttore::consiglio((string) $ultimaT['error']) . ' Prova di nuovo da Impostazioni → Traduzioni → «Prova».'
            : 'Collegate: un traduttore automatico propone, il cliente approva. Consumi in Amministrazione → Traduzioni.' . ($ultimaT ? ' Ultima chiamata riuscita: ' . Support::date((string) $ultimaT['created_at']) . '.' : ''))];

    // 9O · La sezione «Stripe»: la configurazione letta con la chiave del sito (cache di un'ora, «Ricontrolla»).
    View::out('admin/diagnostics', ['checks' => $checks, 'base' => $base, 'stripe' => MHW\DiagnosticaStripe::voci(), 'nav' => 'diagnostica'], 'layout/cms');
});
$r->post('/admin/diagnostica/stripe', function () {
    Auth::requireAdmin();
    if (($voce = (string) ($_POST['conferma'] ?? '')) !== '') {
        MHW\DiagnosticaStripe::conferma($voce);
        Auth::audit('stripe.controllo_manuale', null, ['voce' => $voce]);
        Support::flash('Segnato come controllato a mano oggi.');
    } else {
        MHW\DiagnosticaStripe::voci(true);
        Support::flash('Configurazione di Stripe ricontrollata adesso.');
    }
    Support::redirect('/admin/diagnostica#stripe');
});

/* Rigenera le foto WebP della landing dai .jpg (app/tools/foto.php). */
$r->post('/admin/diagnostica/foto', function () {
    $admin = Auth::requireAdmin();
    require_once MHW_APP . '/tools/foto.php';
    $esiti = mhw_rigenera_foto(MHW_PUBLIC . '/assets/foto');
    Auth::audit('tools.foto', null, ['esiti' => $esiti]);
    $falliti = array_filter($esiti, fn($e) => !$e[1]);
    Support::flash($falliti ? 'Non tutte: ' . implode('; ', array_map(fn($e) => $e[0] . ' — ' . $e[2], $falliti))
                            : 'Foto WebP rigenerate: ' . implode(', ', array_column($esiti, 0)) . '.', $falliti ? 'err' : 'ok');
    Support::redirect('/admin/diagnostica');
});

// -------------------------------------------------------------------- cache
/* Sistema → Cache: le cache del sito con la loro misura e un bottone per svuotarle (MHW\Cache). La memoria delle
   traduzioni ha due bottoni: le frasi mai riusate, oppure tutta (con la conferma, perché poi si paga di nuovo). */
$r->get('/admin/cache', function () {
    Auth::requireAdmin();
    View::out('admin/cache', ['voci' => MHW\Cache::stato(), 'nav' => 'cache'], 'layout/cms');
});
$r->post('/admin/cache/{voce}', function (array $a) {
    Auth::requireAdmin();
    $voce = (string) $a['voce'];
    if ($voce === 'tutto') {
        $esiti = MHW\Cache::svuotaTutto();
        $falliti = array_filter($esiti, fn($e) => !$e[0]);
        Support::flash($falliti ? 'Non tutto: ' . implode(' ', array_map(fn($e) => $e[1], $falliti))
                                : 'Svuotato: ' . implode(' · ', array_map(fn($v) => MHW\Cache::NOMI[$v], array_keys($esiti))) . '.', $falliti ? 'err' : 'ok');
        Support::redirect('/admin/cache');
    }
    if (!isset(MHW\Cache::NOMI[$voce])) Support::redirect('/admin/cache');
    $tutte = $voce === 'traduzioni' && ($_POST['tutte'] ?? '') === '1';
    if ($tutte && ($_POST['conferma'] ?? '') !== '1') {
        Support::flash('Per svuotare tutta la memoria delle traduzioni spunta la conferma: le frasi tolte, se tornano, si pagano di nuovo.', 'err');
        Support::redirect('/admin/cache#traduzioni');
    }
    [$ok, $msg] = MHW\Cache::svuota($voce, $tutte);
    Support::flash($msg, $ok ? 'ok' : 'err');
    Support::redirect('/admin/cache#' . $voce);
});

// -------------------------------------------------------------- impostazioni
/*
 * Stripe, posta e archivio delle foto dal pannello (Impostazioni scrive in
 * app/config.local.php). Ogni salvataggio chiede la password dell'amministratore.
 */
$impostazioni = function (array $admin, string $gruppo = '', array $errori = [], array $valori = [], int $codice = 200) {
    http_response_code($codice);
    View::out('admin/settings', ['locale' => Impostazioni::locale(), 'gruppo' => $gruppo, 'errori' => $errori, 'valori' => $valori,
                                 'webhook' => Support::baseUrl() . '/webhook/stripe',
                                 'scrivibile' => is_writable(MHW_APP) || is_writable(Impostazioni::file()),
                                 'emailAdmin' => (string) $admin['email'], 'nav' => 'impostazioni'], 'layout/cms');
};

$r->get('/admin/impostazioni', function () use ($impostazioni) {
    $impostazioni(Auth::requireAdmin());
});

$r->post('/admin/impostazioni/{gruppo}', function (array $a) use ($impostazioni) {
    $admin = Auth::requireAdmin();
    $gruppo = (string) $a['gruppo'];
    if (!isset(Impostazioni::GRUPPI[$gruppo])) { http_response_code(404); exit('Non trovato.'); }
    // Quello che l'amministratore ha scritto, segreti esclusi, per non farglielo riscrivere.
    $valori = [];
    foreach (Impostazioni::GRUPPI[$gruppo] as $percorso => $c) {
        if ($c[2] !== 'secret') $valori[Impostazioni::nome($percorso)] = (string) ($_POST[Impostazioni::nome($percorso)] ?? '');
    }
    if (!RateLimit::hit('impostazioni:' . (int) $admin['id'], 10, 900)) {
        $impostazioni($admin, $gruppo, ['_' => 'Troppi tentativi: riprova tra un quarto d\'ora.'], $valori, 429);
        return;
    }
    $hash = (string) Db::val('SELECT password_hash FROM users WHERE id = ?', [(int) $admin['id']], '');
    if (!password_verify((string) ($_POST['password'] ?? ''), $hash)) {
        $impostazioni($admin, $gruppo, ['password' => 'La password non è giusta: le impostazioni non sono state salvate.'], $valori, 422);
        return;
    }
    try {
        $errori = Impostazioni::salva($gruppo, $_POST);
    } catch (\RuntimeException $e) {
        Log::exception($e, 'impostazioni');
        $impostazioni($admin, $gruppo, ['_' => $e->getMessage()], $valori, 500);
        return;
    }
    if ($errori) { $impostazioni($admin, $gruppo, $errori, $valori, 422); return; }
    // Nel registro si scrive cosa è cambiato, mai i valori.
    Auth::audit('impostazioni.' . $gruppo, null, ['campi' => array_keys(Impostazioni::GRUPPI[$gruppo])]);
    Support::flash(Impostazioni::TITOLI[$gruppo] . ': impostazioni salvate. Ora prova la connessione.');
    Support::redirect('/admin/impostazioni#' . $gruppo);
});

// 8I · Registri: lo zip degli ultimi 7 giorni e «Svuota» (con conferma). Solo amministratori; CSRF come ogni POST.
$r->post('/admin/registri/scarica', function () {
    $admin = Auth::requireAdmin();
    try { $f = MHW\Registri::zip(7); }
    catch (\RuntimeException $e) { Support::flash($e->getMessage(), 'err'); Support::redirect('/admin/impostazioni#registri'); }
    Auth::audit('registri.scarica');
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="registri-' . gmdate('Y-m-d') . '.zip"');
    header('Content-Length: ' . filesize($f));
    header('Cache-Control: no-store');
    readfile($f); @unlink($f);
    exit;
});

$r->post('/admin/registri/svuota', function () {
    $admin = Auth::requireAdmin();
    if (($_POST['conferma'] ?? '') !== 'SVUOTA') {
        Support::flash('Per svuotare i registri scrivi SVUOTA nella casella di conferma.', 'err');
        Support::redirect('/admin/impostazioni#registri');
    }
    $n = MHW\Registri::svuota();
    Auth::audit('registri.svuota', null, ['file' => $n]);
    Support::flash('Registri svuotati: ' . $n . ' file tolti. Il registro degli accessi degli amministratori resta.');
    Support::redirect('/admin/impostazioni#registri');
});

$r->post('/admin/impostazioni/{gruppo}/prova', function (array $a) use ($impostazioni) {
    $admin = Auth::requireAdmin();
    $gruppo = (string) $a['gruppo'];
    if (!isset(Impostazioni::GRUPPI[$gruppo])) { http_response_code(404); exit('Non trovato.'); }
    if (!RateLimit::hit('impostazioni-prova:' . (int) $admin['id'], 10, 900)) {
        Support::flash('Troppe prove di fila: riprova tra un quarto d\'ora.', 'err');
        Support::redirect('/admin/impostazioni#' . $gruppo);
    }
    if (!empty($_POST['con_dati'])) {
        // «Prova con questi dati»: i valori del modulo, senza salvarli. Serve la password dell'amministratore,
        // come per salvare: altrimenti un segreto salvato si potrebbe mandare a un server scelto da altri.
        $valori = [];
        foreach (Impostazioni::GRUPPI[$gruppo] as $percorso => $c) {
            if ($c[2] !== 'secret') $valori[Impostazioni::nome($percorso)] = (string) ($_POST[Impostazioni::nome($percorso)] ?? '');
        }
        $hash = (string) Db::val('SELECT password_hash FROM users WHERE id = ?', [(int) $admin['id']], '');
        if (!password_verify((string) ($_POST['password'] ?? ''), $hash)) {
            $impostazioni($admin, $gruppo, ['password' => 'La password non è giusta: la prova non è partita.'], $valori, 422);
            return;
        }
        [$ok, $msg, $errori] = Impostazioni::provaCon($gruppo, $_POST, (string) $admin['email']);
        if ($errori) { $impostazioni($admin, $gruppo, $errori, $valori, 422); return; }
        Support::flash(Impostazioni::TITOLI[$gruppo] . ': ' . $msg . ($ok ? ' Questi dati non sono ancora salvati: premi «Salva».' : ''), $ok ? 'ok' : 'err');
        Support::redirect('/admin/impostazioni#' . $gruppo);
    }
    [$ok, $msg] = Impostazioni::prova($gruppo, (string) $admin['email']);
    Support::flash(Impostazioni::TITOLI[$gruppo] . ': ' . $msg, $ok ? 'ok' : 'err');
    Support::redirect('/admin/impostazioni#' . $gruppo);
});

// ------------------------------------------------------------- testimonianze
/**
 * Le testimonianze della landing: le scrive l'amministratore, una per una, con
 * il consenso di chi parla. Nessuna d'esempio. In landing il blocco compare
 * solo se almeno una è visibile.
 */
// ------------------------------------------------------------- 9B/9L · Giudizi e vetrina
$r->get('/admin/giudizi', function () {
    Auth::requireAdmin();
    MHW\Vetrina::sincronizza();   // le schede di tutti gli aderenti, e quelle da spegnere
    if (($_GET['formato'] ?? '') === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="giudizi-e-vetrina-' . gmdate('Y-m-d') . '.csv"');
        echo MHW\Vetrina::csv(); exit;
    }
    $scheda = isset(MHW\Vetrina::SCHEDE[$_GET['scheda'] ?? '']) ? (string) $_GET['scheda'] : 'vetrina';
    $stelle = ($_GET['stelle'] ?? '5') === 'tutti' ? 'tutti' : '5';   // i giudizi: «5 stelle» di serie
    View::out('admin/giudizi', MHW\Vetrina::elenco($scheda, $stelle) + ['scheda' => $scheda, 'stelle' => $stelle, 'nav' => 'giudizi'], 'layout/cms');
});
/** Dopo un'azione della tabella si torna dov'eri (la scheda e il filtro), solo dentro /admin/giudizi. */
$tornaGiudizi = function (string $di = ''): never {
    $t = (string) ($_POST['torna'] ?? '');
    Support::redirect(preg_match('#^/admin/giudizi(\?[a-z=&]*)?$#', $t) ? $t : ($di !== '' ? $di : '/admin/giudizi'));
};
$r->post('/admin/giudizi/ordine', function () use ($tornaGiudizi) {
    Auth::requireAdmin();
    MHW\Vetrina::ordina(($_POST['tipo'] ?? '') === 'giudizio' ? 'giudizio' : 'scheda', (array) ($_POST['ids'] ?? []));
    Support::flash('Ordine salvato: la home lo segue.');
    $tornaGiudizi();
});
$r->get('/admin/giudizi/{sid}', function (array $a) {
    Auth::requireAdmin();
    $riga = MHW\Vetrina::rigaElenco((int) $a['sid']);
    if (!$riga) { http_response_code(404); View::out('pub/404', []); }
    $s = Db::one('SELECT * FROM vetrina_schede WHERE id = ?', [(int) $riga['id']]);
    View::out('admin/giudizio_scheda', ['r' => $riga, 'card' => MHW\Vetrina::perScheda($s, $riga['p']), 'cardG' => $riga['g'] ? MHW\Vetrina::perScheda($s, $riga['p'], $riga['g'], true) : null,
                                        'storico' => MHW\Vetrina::storico((int) $riga['account_id']), 'nav' => 'giudizi'], 'layout/cms');
});
/* La scheda: acceso = approvata (anche «Rimetti»), spento = ritirata dall'admin (con il motivo, se scritto). */
$r->post('/admin/giudizi/{sid}/home', function (array $a) use ($tornaGiudizi) {
    Auth::requireAdmin();
    $si = !empty($_POST['si']);
    $err = MHW\Vetrina::accendi((int) $a['sid'], $si, (string) ($_POST['motivo'] ?? ''));
    Support::flash($err === '' ? ($si ? 'Scheda approvata: è in vetrina.' : 'Scheda ritirata: il cliente vede «non in home al momento».') : $err, $err === '' ? 'ok' : 'err');
    $tornaGiudizi('/admin/giudizi/' . (int) $a['sid']);
});
/* 9R · «Logo: non mostrare»: il logo caricato dal cliente resta nella guida, nella scheda compare il quadratino con l'arco. */
$r->post('/admin/giudizi/{sid}/logo', function (array $a) use ($tornaGiudizi) {
    Auth::requireAdmin();
    $no = empty($_POST['si']);
    MHW\Vetrina::nascondiLogo((int) $a['sid'], $no);
    Support::flash($no ? 'Logo nascosto: nella scheda c\'è il quadratino con l\'arco.' : 'Logo di nuovo visibile nella scheda.');
    $tornaGiudizi('/admin/giudizi/' . (int) $a['sid']);
});
/* Il giudizio: acceso = in «Cosa dicono di noi», spento = non pubblicato (lo sconto resta). */
$r->post('/admin/giudizi/{sid}/giudizio', function (array $a) use ($tornaGiudizi) {
    Auth::requireAdmin();
    $si = !empty($_POST['si']) || !empty($_POST['approva']);
    $err = MHW\Vetrina::accendiGiudizio((int) $a['sid'], $si, (string) ($_POST['motivo'] ?? ''));
    Support::flash($err === '' ? ($si ? 'Giudizio pubblicato in «Cosa dicono di noi».' : 'Giudizio non pubblicato: lo sconto resta.') : $err, $err === '' ? 'ok' : 'err');
    $tornaGiudizi('/admin/giudizi/' . (int) $a['sid'] . '#giudizio');
});
$r->post('/admin/giudizi/{sid}/sposta', function (array $a) use ($tornaGiudizi) {
    Auth::requireAdmin();
    MHW\Vetrina::sposta(($_POST['tipo'] ?? '') === 'giudizio' ? 'giudizio' : 'scheda', (int) $a['sid'], (int) ($_POST['verso'] ?? 0) < 0 ? -1 : 1);
    $tornaGiudizi();
});
$r->post('/admin/giudizi/{sid}/risposta', function (array $a) {
    Auth::requireAdmin();
    MHW\Vetrina::rispondi((int) $a['sid'], (string) ($_POST['testo'] ?? ''));
    Support::flash('Risposta salvata.');
    Support::redirect('/admin/giudizi/' . (int) $a['sid'] . '#rispondi');
});
$r->post('/admin/giudizi/{sid}/nota', function (array $a) {
    Auth::requireAdmin();
    MHW\Vetrina::nota((int) $a['sid'], (string) ($_POST['testo'] ?? ''));
    Support::flash('Nota salvata.');
    Support::redirect('/admin/giudizi/' . (int) $a['sid'] . '#nota');
});
$r->post('/admin/giudizi/{tid}/elimina', function (array $a) {
    Auth::requireAdmin();
    $t = Db::one('SELECT * FROM testimonials WHERE id = ? AND account_id IS NOT NULL', [(int) $a['tid']]);
    if ($t) { MHW\Testimonianze::elimina((int) $t['id']); Auth::audit('testimonial.delete', null, ['id' => (int) $t['id']]); MHW\Seo::scriviFile(); }
    Support::flash($t ? 'Giudizio eliminato.' : 'Giudizio non trovato.', $t ? 'ok' : 'err');
    Support::redirect('/admin/giudizi');
});

/* 9B · Gli interruttori del programma dalla pagina Giudizi (le stesse impostazioni di Impostazioni → Giudizi con sconto). */
/* 9N · Gli interruttori delle due sezioni pubbliche (in cima a Giudizi e vetrina), scritti in config.local.php e nel registro. */
$r->post('/admin/giudizi/sezione', function () {
    Auth::requireAdmin();
    $quale = (string) ($_POST['quale'] ?? '');
    $percorso = ['guide' => 'vetrina.sezione_attiva', 'dicono' => 'vetrina.dicono_attiva'][$quale] ?? '';
    if ($percorso === '') Support::redirect('/admin/giudizi');
    $si = ($_POST['si'] ?? '') === '1';
    try { Impostazioni::interruttore($percorso, $si); }
    catch (\RuntimeException $e) { Support::flash($e->getMessage(), 'err'); Support::redirect('/admin/giudizi#sezioni'); }
    Auth::audit('vetrina.sezione', null, ['sezione' => $quale, 'accesa' => $si]);
    try { MHW\Seo::scriviFile(); } catch (\Throwable $e) { MHW\Log::exception($e, 'vetrina: file SEO'); }
    $nome = $quale === 'guide' ? '«Le guide dei nostri host»' : '«Cosa dicono di noi»';
    $vuota = $quale === 'guide' ? !MHW\Vetrina::visibili() : !MHW\Vetrina::giudiziHome();
    Support::flash($si ? 'Sezione ' . $nome . ' accesa' . ($vuota ? ', ma senza ' . ($quale === 'guide' ? 'schede' : 'giudizi') . ' approvati resta nascosta.' : ': è sul sito.') : 'Sezione ' . $nome . ' spenta: non si vede più.', $si && $vuota ? 'avviso' : 'ok');
    Support::redirect('/admin/giudizi#sezioni');
});
$r->post('/admin/giudizi/impostazioni', function () {
    $admin = Auth::requireAdmin();
    $hash = (string) Db::val('SELECT password_hash FROM users WHERE id = ?', [(int) $admin['id']], '');
    if (!password_verify((string) ($_POST['password'] ?? ''), $hash)) { Support::flash('La password non è giusta: niente è stato salvato.', 'err'); Support::redirect('/admin/giudizi#programma'); }
    try { $errori = Impostazioni::salva('giudizi', $_POST); }
    catch (\RuntimeException $e) { Support::flash($e->getMessage(), 'err'); Support::redirect('/admin/giudizi#programma'); }
    if ($errori) { Support::flash(implode(' ', $errori), 'err'); Support::redirect('/admin/giudizi#programma'); }
    Auth::audit('impostazioni.giudizi', null, ['campi' => array_keys(Impostazioni::GRUPPI['giudizi'])]);
    Support::flash('Programma aggiornato.');
    Support::redirect('/admin/giudizi#programma');
});

// ------------------------------------------------------------------- 9D · codici omaggio
$r->get('/admin/omaggi', function () {
    Auth::requireAdmin();
    if (!MHW\Omaggi::disponibili()) { http_response_code(404); View::out('pub/404', []); }
    $righe = MHW\Omaggi::tutti();
    $nuovo = isset($_GET['nuovo']) ? MHW\Omaggi::riga((int) $_GET['nuovo']) : null;
    View::out('admin/omaggi', ['righe' => $righe, 'nuovo' => $nuovo, 'vecchi' => $_SESSION['omaggio_vecchi'] ?? [], 'numeri' => MHW\Omaggi::numeri(30),
                               'scadenzaSerie' => gmdate('Y-m-d', strtotime(MHW\Omaggi::oggi() . ' +' . MHW\Omaggi::GIORNI . ' days')), 'nav' => 'omaggi'], 'layout/cms');
    unset($_SESSION['omaggio_vecchi']);
});
$r->post('/admin/omaggi', function () {
    $admin = Auth::requireAdmin();
    try { $id = MHW\Omaggi::crea($_POST, (int) $admin['id']); }
    catch (\RuntimeException $e) { $_SESSION['omaggio_vecchi'] = $_POST; Support::flash($e->getMessage(), 'err'); Support::redirect('/admin/omaggi#nuovo'); }
    $c = MHW\Omaggi::riga($id);
    Auth::audit('omaggio.creato', null, ['codice' => (string) $c['codice'], 'mesi' => (int) $c['mesi'], 'scade_il' => (string) $c['scade_il'], 'nota' => (string) $c['nota']]);
    Support::flash('Codice ' . $c['codice'] . ' creato: ' . (int) $c['mesi'] . ' mesi gratis, da usare entro il ' . Support::date($c['scade_il']) . '. Mandalo con uno dei bottoni.');
    Support::redirect('/admin/omaggi?nuovo=' . $id);
});
/* «Manda per email»: il messaggio del codice parte dal sito (le risposte all'email commerciale). Solo per i codici validi. */
$r->post('/admin/omaggi/{id}/email', function (array $a) {
    $admin = Auth::requireAdmin();
    $c = MHW\Omaggi::riga((int) $a['id']);
    if (!$c || MHW\Omaggi::stato($c) !== 'valido') { Support::flash('Questo codice non è più valido: non si può mandare.', 'err'); Support::redirect('/admin/omaggi'); }
    $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { Support::flash('Scrivi un indirizzo email valido, per esempio nome@esempio.it.', 'err'); Support::redirect('/admin/omaggi'); }
    if (!RateLimit::hit('omaggi-email:' . (int) $admin['id'], 50, 86400)) { Support::flash('Hai mandato molte email oggi: riprova domani, o usa WhatsApp.', 'err'); Support::redirect('/admin/omaggi'); }
    $mesi = (int) $c['mesi'];
    $paragrafi = array_values(array_filter(array_map('trim', preg_split('/\R\s*\R/', MHW\Omaggi::messaggio($c)) ?: []), fn($p) => $p !== ''));
    $ok = MHW\Mailer::conRispostaA(Support::commerciale(), fn() => MHW\Mailer::manda($email, $mesi . ' mesi gratis di MyHouse Welcome', $mesi . ($mesi === 1 ? ' mese gratis' : ' mesi gratis') . ' per te',
        $paragrafi, ['Crea la tua guida', MHW\Omaggi::link($c)]));
    Auth::audit('omaggio.email', null, ['id' => (int) $c['id'], 'codice' => (string) $c['codice'], 'a' => MHW\Inviti::maschera($email), 'esito' => $ok ? 'partita' : 'non partita']);
    Support::flash($ok ? 'Email con il codice ' . $c['codice'] . ' mandata a ' . $email . '. Le risposte arrivano a ' . Support::commerciale() . '.'
                       : 'L\'email non è partita: controlla Amministrazione → Impostazioni → Posta in uscita, o manda il codice su WhatsApp.', $ok ? 'ok' : 'err');
    Support::redirect('/admin/omaggi');
});
$r->post('/admin/omaggi/{id}/annulla', function (array $a) {
    Auth::requireAdmin();
    try { MHW\Omaggi::annulla((int) $a['id']); Auth::audit('omaggio.annullato', null, ['id' => (int) $a['id']]); Support::flash('Codice annullato: non si applica più, nemmeno se era prenotato.'); }
    catch (\RuntimeException $e) { Support::flash($e->getMessage(), 'err'); }
    Support::redirect('/admin/omaggi');
});

$r->get('/admin/testimonianze', function () {
    Auth::requireAdmin();
    // 9B: i giudizi dei clienti hanno la loro pagina (Clienti → Giudizi); qui restano solo quelle scritte da noi.
    View::out('admin/testimonials', ['righe' => MHW\Testimonianze::tutte(), 'giudizi' => MHW\Giudizi::disponibili() ? [] : MHW\Testimonianze::giudiziClienti(), 'nav' => 'testimonianze'], 'layout/cms');
});

/* 7D: i giudizi dei clienti. «Pubblica» solo con il consenso; «Rifiuta» li toglie dalla coda. */
$r->post('/admin/testimonianze/{tid}/pubblica', function (array $a) {
    Auth::requireAdmin();
    $ok = MHW\Testimonianze::pubblica((int) $a['tid']);
    if ($ok) Auth::audit('testimonial.publish', null, ['id' => (int) $a['tid']]);
    Support::flash($ok ? 'Giudizio pubblicato: si vede in home.' : 'Non si può pubblicare: manca il consenso, o è stato ritirato.', $ok ? 'ok' : 'err');
    MHW\Seo::scriviFile();   // 7F: llms.txt dice se ci sono giudizi pubblicati
    Support::redirect('/admin/testimonianze');
});

$r->post('/admin/testimonianze/{tid}/rifiuta', function (array $a) {
    Auth::requireAdmin();
    MHW\Testimonianze::rifiuta((int) $a['tid']);
    Auth::audit('testimonial.reject', null, ['id' => (int) $a['tid']]);
    Support::flash('Giudizio rifiutato: non si pubblica.');
    MHW\Seo::scriviFile();   // 7F: llms.txt dice se ci sono giudizi pubblicati
    Support::redirect('/admin/testimonianze');
});

$r->post('/admin/testimonianze', function () {
    $admin = Auth::requireAdmin();
    try {
        $id = MHW\Testimonianze::salva($_POST, $_FILES['foto'] ?? null);
        Auth::audit('testimonial.save', null, ['id' => $id]);
        Support::flash('Testimonianza salvata.' . (empty($_POST['visible']) ? ' Non è visibile: spunta «Visibile sulla landing» quando vuoi mostrarla.' : ''));
    } catch (\Throwable $e) {
        Support::flash($e instanceof \RuntimeException ? $e->getMessage() : 'Non salvata (codice ' . Log::exception($e, 'testimonianze') . ').', 'err');
    }
    MHW\Seo::scriviFile();   // 7F: llms.txt dice se ci sono giudizi pubblicati
    Support::redirect('/admin/testimonianze');
});

$r->post('/admin/testimonianze/{tid}/elimina', function (array $a) {
    $admin = Auth::requireAdmin();
    MHW\Testimonianze::elimina((int) $a['tid']);
    Auth::audit('testimonial.delete', null, ['id' => (int) $a['tid']]);
    Support::flash('Testimonianza eliminata.');
    MHW\Seo::scriviFile();   // 7F: llms.txt dice se ci sono giudizi pubblicati
    Support::redirect('/admin/testimonianze');
});


// ---------------------------------------------------------- codici sconto (6E)
/*
 * I codici del primo anno: si creano qui e diventano coupon Stripe (duration=once).
 * Dopo la creazione si cambiano solo nota, piani e stato; per valore o date si
 * disattiva e se ne crea un altro. Creazioni e disattivazioni vanno nel registro.
 */
// ------------------------------------------------------------- 9A · preventivi oltre il massimo del Portfolio
// 9K · Le richieste del programma Partner (/property-manager).
$r->get('/admin/partner', function () {
    Auth::requireAdmin();
    if (($_GET['formato'] ?? '') === 'csv') {   // 9N
        Gestione::csv('partner', ['Data', 'Nome', 'Società', 'Email', 'Telefono', 'Strutture', 'Zone', 'Messaggio', 'Stato', 'Nota'],
            array_map(fn($x) => [substr((string) $x['created_at'], 0, 10), $x['nome'], $x['societa'], $x['email'], $x['telefono'], $x['strutture'], $x['zone'],
                                 (string) $x['messaggio'], MHW\Partner::STATI[$x['stato']] ?? $x['stato'], $x['nota']], MHW\Partner::tutte()));
    }
    View::out('admin/partner', ['righe' => MHW\Partner::tutte(), 'attivi' => MHW\Partner::disponibili(), 'partner' => MHW\ProgrammaPartner::tutti(),   // 9O
                                'daLiquidare' => array_column(array_map(fn($x) => [(int) $x['partner']['id'], $x['totale']], MHW\ProgrammaPartner::daLiquidare()), 1, 0), 'nav' => 'partner'], 'layout/cms');
});
$r->post('/admin/partner/{id}/stato', function (array $a) {
    Auth::requireAdmin();
    $riga = MHW\Partner::disponibili() ? Db::one('SELECT * FROM partner_richieste WHERE id = ?', [(int) $a['id']]) : null;
    if (!$riga) { http_response_code(404); View::out('pub/404', []); }
    try {
        MHW\Partner::cambiaStato((int) $riga['id'], (string) ($_POST['stato'] ?? ''), isset($_POST['nota']) ? (string) $_POST['nota'] : null);
        Auth::audit('partner.stato', null, ['id' => (int) $riga['id'], 'stato' => (string) $_POST['stato']]);
        Support::flash('Richiesta partner #' . (int) $riga['id'] . ': ' . MHW\Partner::STATI[(string) $_POST['stato']] . '.');
    } catch (\RuntimeException $e) { Support::flash($e->getMessage(), 'err'); }
    Support::redirect('/admin/partner');
});
$r->get('/admin/preventivi', function () {
    Auth::requireAdmin();
    $righe = MHW\Preventivi::tutte();
    if (($_GET['formato'] ?? '') === 'csv') {   // 9N
        Gestione::csv('preventivi', ['Data', 'Nome', 'Email', 'Telefono', 'Strutture', 'Lingua', 'Messaggio', 'Stato', 'Importo proposto (IVA esclusa)', 'Nota', 'Ricontattare il'],
            array_map(fn($x) => [substr((string) $x['created_at'], 0, 10), $x['nome'], $x['email'], $x['telefono'], (int) $x['strutture'], $x['lingua'], (string) $x['messaggio'],
                                 MHW\Preventivi::STATI[$x['stato']] ?? $x['stato'], isset($x['importo_cents']) ? number_format((int) $x['importo_cents'] / 100, 2, ',', '') : '',
                                 (string) ($x['nota'] ?? ''), (string) ($x['ricontatta_il'] ?? '')], $righe));
    }
    foreach ($righe as &$x) $x['storico'] = MHW\Preventivi::storico((int) $x['id']);
    unset($x);
    View::out('admin/preventivi', ['righe' => $righe, 'attivi' => MHW\Preventivi::disponibili(), 'proposta' => MHW\Preventivi::conProposta(), 'nav' => 'preventivi'], 'layout/cms');
});
$r->post('/admin/preventivi/{id}/stato', function (array $a) {
    Auth::requireAdmin();
    $riga = MHW\Preventivi::disponibili() ? Db::one('SELECT * FROM preventivi WHERE id = ?', [(int) $a['id']]) : null;
    if (!$riga) { http_response_code(404); View::out('pub/404', []); }
    try {
        // 9N · stato, importo proposto, nota e data per ricontattare; nel registro solo quello che cambia (è lo storico).
        $cambiati = MHW\Preventivi::aggiorna((int) $riga['id'], $_POST);
        if ($cambiati) Auth::audit('preventivo.stato', null, ['id' => (int) $riga['id']] + $cambiati);
        Support::flash($cambiati ? 'Richiesta #' . (int) $riga['id'] . ' salvata: ' . MHW\Preventivi::STATI[(string) ($_POST['stato'] ?? $riga['stato'])] . '.' : 'Richiesta #' . (int) $riga['id'] . ': niente da cambiare.');
    } catch (\RuntimeException $e) { Support::flash($e->getMessage(), 'err'); }
    Support::redirect('/admin/preventivi#preventivo-' . (int) $riga['id']);
});

// ------------------------------------------------------- 9N · 10 bis · offerte su misura
/* Il modulo dell'offerta e il suo invio, dal preventivo o dalla scheda del cliente (più strutture). */
$offertaPagina = function (array $dest, string $azione, ?array $attiva) {
    $vecchi = (array) ($_SESSION['offerta_valori'] ?? []); unset($_SESSION['offerta_valori']);
    $n = max(MHW\Offerte::minimo(), (int) ($vecchi['strutture'] ?? $dest['strutture'] ?? 0));
    $v = $vecchi + ['strutture' => $n, 'prezzo' => number_format(MHW\Offerte::listino($n) / 100, 2, ',', '.'), 'valida_fino' => gmdate('Y-m-d', strtotime('+14 days')),
                    'sconto_rinnovo' => 0, 'note' => '', 'modo' => 'rinnovo'];
    $offerte = $dest['preventivo_id'] ? MHW\Offerte::di((int) $dest['preventivo_id']) : MHW\Offerte::di(null, (int) $dest['account_id']);
    View::out('admin/offerta', ['dest' => $dest, 'v' => $v, 'offerte' => $offerte, 'attiva' => $attiva, 'azione' => $azione, 'minimo' => MHW\Offerte::minimo(),
                                'nav' => $dest['preventivo_id'] ? 'preventivi' : 'clienti'], 'layout/cms');
};
$offertaInvia = function (array $dest, string $torna) {
    Auth::requireAdmin();
    $in = ['strutture' => (int) ($_POST['strutture'] ?? 0), 'prezzo' => (string) ($_POST['prezzo'] ?? ''), 'valida_fino' => (string) ($_POST['valida_fino'] ?? ''),
           'sconto_rinnovo' => (int) ($_POST['sconto_rinnovo'] ?? 0), 'note' => (string) ($_POST['note'] ?? ''), 'modo' => (string) ($_POST['modo'] ?? 'nuovo')];
    if (($_POST['azione'] ?? '') === 'anteprima') {
        // L'email com'è, senza salvare né mandare (con un link di prova).
        $finta = ['id' => 0, 'token' => 'anteprima', 'lingua' => $dest['lingua'], 'nome' => $dest['nome'], 'strutture' => max(1, $in['strutture']),
                  'prezzo_cents' => (int) (MHW\Offerte::euro($in['prezzo']) ?? 0), 'valida_fino' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $in['valida_fino']) ? $in['valida_fino'] : gmdate('Y-m-d'),
                  'note' => $in['note'], 'modo' => $dest['account_id'] && MHW\Offerte::attiva((int) $dest['account_id']) && in_array($in['modo'], ['subito', 'rinnovo'], true) ? $in['modo'] : 'nuovo'];
        [$oggetto, $titolo, $par, $bottone, $nota] = MHW\Offerte::email($finta);
        header('Content-Type: text/html; charset=utf-8');
        echo MHW\Mailer::inLingua($dest['lingua'], fn() => MHW\Mailer::layout($titolo, $par, $bottone, $nota, 'Oggetto: ' . htmlspecialchars($oggetto) . ' · anteprima, non mandata'));
        exit;
    }
    try {
        $o = MHW\Offerte::crea($in, $dest, (int) Auth::user()['id']);
        $ok = MHW\Offerte::manda($o);
        Support::flash($ok ? 'Offerta mandata a ' . $dest['email'] . ': ' . (int) $o['strutture'] . ' strutture, ' . Support::money((int) $o['prezzo_cents']) . ' + IVA, valida fino al ' . Support::date($o['valida_fino']) . '.'
                           : 'Offerta salvata, ma l\'email non è partita: controlla la posta in Impostazioni e copia il link dall\'elenco.', $ok ? 'ok' : 'err');
    } catch (\RuntimeException $e) {
        $_SESSION['offerta_valori'] = $in;
        Support::flash($e->getMessage(), 'err');
    }
    Support::redirect($torna);
};
$destPreventivo = function (int $id): array {
    $p = MHW\Preventivi::disponibili() ? Db::one('SELECT * FROM preventivi WHERE id = ?', [$id]) : null;
    if (!$p || !MHW\Offerte::disponibili()) { http_response_code(404); View::out('pub/404', []); }
    $aid = (int) ($p['account_id'] ?? 0);
    if (!$aid && ($trovato = Db::val("SELECT a.id FROM accounts a JOIN users u ON u.id = a.user_id WHERE u.email = ?", [mb_strtolower((string) $p['email'])]))) $aid = (int) $trovato;
    return ['preventivo_id' => (int) $p['id'], 'account_id' => $aid ?: null, 'email' => (string) $p['email'], 'nome' => (string) $p['nome'], 'lingua' => (string) $p['lingua'],
            'strutture' => (int) $p['strutture'], 'titolo' => (string) $p['nome'],
            'sotto' => 'Richiesta del ' . Support::date($p['created_at']) . ': ' . (int) $p['strutture'] . ' strutture · ' . $p['email'] . ($aid ? ' · ' . MHW\Codici::cliente($aid) : ' · non ancora registrato')];
};
$destCliente = function (int $aid): array {
    $c = Db::one('SELECT a.id, u.email, u.name FROM accounts a JOIN users u ON u.id = a.user_id WHERE a.id = ?', [$aid]);
    if (!$c || !MHW\Offerte::disponibili()) { http_response_code(404); View::out('pub/404', []); }
    $att = MHW\Offerte::attiva($aid);
    return ['preventivo_id' => null, 'account_id' => $aid, 'email' => (string) $c['email'], 'nome' => (string) $c['name'],
            'lingua' => MHW\Richiami::lingua($aid) === 'en' ? 'en' : 'it', 'strutture' => $att ? (int) $att['strutture'] + 1 : 0,
            'titolo' => (string) $c['name'], 'sotto' => MHW\Codici::cliente($aid) . ' · ' . $c['email'] . ($att ? ' · offerta attiva: ' . (int) $att['strutture'] . ' strutture' : '')];
};
$r->get('/admin/preventivi/{id}/offerta', function (array $a) use ($offertaPagina, $destPreventivo) {
    Auth::requireAdmin();
    $d = $destPreventivo((int) $a['id']);
    $offertaPagina($d, '/admin/preventivi/' . (int) $a['id'] . '/offerta', $d['account_id'] ? MHW\Offerte::attiva((int) $d['account_id']) : null);
});
$r->post('/admin/preventivi/{id}/offerta', function (array $a) use ($offertaInvia, $destPreventivo) {
    Auth::requireAdmin();
    $offertaInvia($destPreventivo((int) $a['id']), '/admin/preventivi/' . (int) $a['id'] . '/offerta');
});
$r->get('/admin/cliente/{aid}/offerta', function (array $a) use ($offertaPagina, $destCliente) {
    Auth::requireAdmin();
    $offertaPagina($destCliente((int) $a['aid']), '/admin/cliente/' . (int) $a['aid'] . '/offerta', MHW\Offerte::attiva((int) $a['aid']));
});
$r->post('/admin/cliente/{aid}/offerta', function (array $a) use ($offertaInvia, $destCliente) {
    Auth::requireAdmin();
    $offertaInvia($destCliente((int) $a['aid']), '/admin/cliente/' . (int) $a['aid'] . '/offerta');
});
$r->post('/admin/offerte/{id}/annulla', function (array $a) {
    Auth::requireAdmin();
    $o = MHW\Offerte::riga((int) $a['id']);
    if (!$o) { http_response_code(404); View::out('pub/404', []); }
    if ($o['stato'] === 'inviata') { Db::update('offerte', ['stato' => 'annullata'], 'id = :id', ['id' => $o['id']]); Auth::audit('offerta.annullata', null, ['id' => (int) $o['id']]); Support::flash('Offerta annullata: il link non vale più.'); }
    Support::redirect($o['preventivo_id'] ? '/admin/preventivi/' . (int) $o['preventivo_id'] . '/offerta' : '/admin/cliente/' . (int) $o['account_id'] . '/offerta');
});
/* Lo sconto sul prossimo rinnovo di un'offerta su misura, deciso a mano (fino a 7 giorni prima). */
$r->post('/admin/cliente/{aid}/offerta/sconto', function (array $a) {
    $admin = Auth::requireAdmin();
    try {
        $x = MHW\Offerte::impostaSconto((int) $a['aid'], (int) ($_POST['sconto'] ?? 0), (int) $admin['id']);
        Support::flash('Sconto sul prossimo rinnovo: ' . $x['dopo'] . '% → ' . Support::money($x['importo']) . ' + IVA.');
    } catch (\RuntimeException $e) { Support::flash($e->getMessage(), 'err'); }
    Support::redirect('/admin/cliente/' . (int) $a['aid'] . '#offerta');
});

$r->get('/admin/sconti', function () {
    Auth::requireAdmin();
    $righe = MHW\Sconti::disponibili() ? Db::all('SELECT * FROM discount_codes ORDER BY id DESC') : [];
    // La riga di sistema di Invita un amico non è un codice da gestire a mano: sta in Amministrazione → Inviti.
    $righe = array_values(array_filter($righe, fn($c) => empty($c['sistema'])));
    foreach ($righe as &$c) { $c['stato'] = MHW\Sconti::stato($c); $c['usi'] = MHW\Sconti::utilizzi((int) $c['id']); }
    unset($c);
    View::out('admin/sconti', [
        'righe' => $righe, 'piani' => Plans::public(), 'proposto' => MHW\Sconti::disponibili() ? MHW\Sconti::genera() : '',
        'cifre' => ['attivi' => count(array_filter($righe, fn($c) => $c['stato'] === 'attivo')),
                    'usi' => MHW\Sconti::disponibili() ? (int) Db::val('SELECT COUNT(*) FROM discount_redemptions', [], 0) : 0,
                    'euro' => MHW\Sconti::disponibili() ? (int) Db::val('SELECT COALESCE(SUM(discount_cents), 0) FROM discount_redemptions', [], 0) : 0],
        'stripe' => Stripe::enabled(), 'vecchi' => $_SESSION['sconto_modulo'] ?? [], 'nav' => 'sconti',
    ], 'layout/cms');
    unset($_SESSION['sconto_modulo']);
});

$r->post('/admin/sconti', function () {
    Auth::requireAdmin();
    try {
        [$id, $sync] = MHW\Sconti::crea($_POST);
        Auth::audit('discount.create', null, ['id' => $id, 'code' => MHW\Sconti::normalizza((string) $_POST['code'])]);
        Support::flash($sync ? 'Codice creato e attivo anche su Stripe.' : 'Codice salvato, ma non ancora su Stripe: non si può usare finché non lo sincronizzi.', $sync ? 'ok' : 'err');
    } catch (\RuntimeException $e) {
        $_SESSION['sconto_modulo'] = array_intersect_key($_POST, array_flip(['code', 'kind', 'value', 'valid_from', 'valid_until', 'max_uses', 'piani', 'packages', 'note']));
        Support::flash($e->getMessage(), 'err');
    }
    Support::redirect('/admin/sconti');
});

$r->get('/admin/sconti/{id}', function (array $a) {
    Auth::requireAdmin();
    $c = MHW\Sconti::riga((int) $a['id']);
    if (!$c) { http_response_code(404); View::out('pub/404', []); }
    $c['stato'] = MHW\Sconti::stato($c);
    View::out('admin/sconto', [
        'c' => $c, 'piani' => Plans::public(),
        'usi' => Db::all('SELECT r.*, u.email, pk.name AS piano FROM discount_redemptions r JOIN accounts a ON a.id = r.account_id JOIN users u ON u.id = a.user_id
                          JOIN orders o ON o.id = r.order_id JOIN package_versions pv ON pv.id = o.package_version_id JOIN packages pk ON pk.id = pv.package_id
                          WHERE r.discount_code_id = ? ORDER BY r.id DESC', [$c['id']]),
        'nav' => 'sconti',
    ], 'layout/cms');
});

$r->post('/admin/sconti/{id}/{fai}', function (array $a) {
    Auth::requireAdmin();
    $c = MHW\Sconti::riga((int) $a['id']);
    if (!$c) { http_response_code(404); View::out('pub/404', []); }
    $torna = ($_POST['torna'] ?? '') === 'dettaglio' ? '/admin/sconti/' . $c['id'] : '/admin/sconti';
    if (!empty($c['sistema'])) { Support::flash('Questo codice è gestito da Invita un amico: non si modifica da qui.', 'err'); Support::redirect('/admin/sconti'); }
    switch ($a['fai']) {
        case 'disattiva':
            MHW\Sconti::disattiva((int) $c['id']);
            Auth::audit('discount.disable', null, ['id' => (int) $c['id'], 'code' => $c['code']]);
            Support::flash('Codice ' . $c['code'] . ' disattivato. Chi l\'ha già usato conserva il suo sconto.');
            break;
        case 'sincronizza':
            $ok = MHW\Sconti::sincronizza($c);
            Support::flash($ok ? 'Codice attivo su Stripe.' : 'Stripe non ha risposto, o non è configurato: riprova più tardi.', $ok ? 'ok' : 'err');
            break;
        case 'modifica':
            MHW\Sconti::aggiorna((int) $c['id'], $_POST);
            Support::flash('Nota e piani aggiornati.');
            break;
        default: http_response_code(404); View::out('pub/404', []);
    }
    Support::redirect($torna);
});

// ------------------------------------------------------------ Invita un amico
/* Chi ha invitato chi e in che stato è ogni invito. «Annulla» toglie un invito dal
   conto (un rimborso, un abuso) e riallinea lo sconto di chi invita su Stripe. */
$r->get('/admin/inviti', function () {
    Auth::requireAdmin();
    $righe = MHW\Migrator::tableExists('referrals') ? Db::all(
        'SELECT r.*, ur.name AS chi, ur.email AS chi_email, ar.id AS chi_account, uf.name AS amico, uf.email AS amico_email, af.id AS amico_account
         FROM referrals r JOIN accounts ar ON ar.id = r.referrer_account_id JOIN users ur ON ur.id = ar.user_id
         JOIN accounts af ON af.id = r.friend_account_id JOIN users uf ON uf.id = af.user_id ORDER BY r.id DESC LIMIT 300') : [];
    $perEmail = MHW\Migrator::tableExists('referral_invites') ? (int) Db::val('SELECT COUNT(*) FROM referral_invites WHERE sent_at > ?', [gmdate('Y-m-d\TH:i:s\Z', time() - 30 * 86400)], 0) : 0;
    View::out('admin/inviti', ['righe' => $righe, 'attivi' => MHW\Inviti::disponibili(), 'perEmail' => $perEmail,
                               'aMano' => MHW\Inviti::daApplicareAMano(), 'nav' => 'inviti'], 'layout/cms');
});

/* 7D: lo sconto inviti di un abbonamento dello staff, applicato a mano al rinnovo: gli inviti si chiudono. */
$r->post('/admin/inviti/applicato/{aid}', function (array $a) {
    Auth::requireAdmin();
    $n = MHW\Inviti::applicatoAMano((int) $a['aid']);
    Auth::audit('referral.manual_applied', null, ['account_id' => (int) $a['aid'], 'inviti' => $n]);
    Support::flash('Fatto: ' . $n . ' invit' . ($n === 1 ? 'o chiuso' : 'i chiusi') . ' come «già scontato».');
    Support::redirect('/admin/inviti');
});

$r->post('/admin/inviti/{id}/annulla', function (array $a) {
    Auth::requireAdmin();
    MHW\Inviti::annulla((int) $a['id']);
    Auth::audit('referral.cancel', null, ['id' => (int) $a['id']]);
    Support::flash('Invito annullato: non conta più per lo sconto.');
    Support::redirect('/admin/inviti');
});

// ------------------------------------------------------------ SEO e GEO
/* Come le pagine pubbliche si presentano a Google e agli assistenti AI. Il controllo si
   ricalcola a ogni apertura; robots.txt, sitemap.xml e llms.txt si generano a ogni richiesta. */
// ------------------------------------------------------------------- testi e foto del sito
$r->get('/admin/testi', function () {
    Auth::requireAdmin();
    // 8E: la linguetta English mostra e salva i testi di /en (chiavi «en:»); le foto sono le stesse.
    $lingua = ($_GET['lingua'] ?? '') === 'en' ? 'en' : 'it';
    MHW\LinguaSito::imposta($lingua);
    // 8J: la linguetta «Documenti legali» (Termini, Privacy, DPA, novità), in italiano e in inglese.
    $legali = ($_GET['doc'] ?? '') === 'legali';
    View::out('admin/testi', ['nav' => 'testi', 'pronta' => MHW\TestiSito::disponibili(), 'lingua' => $lingua, 'legali' => $legali], 'layout/cms');
});

$r->post('/admin/testi', function () {
    Auth::requireAdmin();
    if (!MHW\TestiSito::disponibili()) { Support::flash('Manca la tabella dei testi: ricarica la pagina per applicare gli aggiornamenti.', 'err'); Support::redirect('/admin/testi'); }
    $lingua = ($_POST['lingua'] ?? '') === 'en' ? 'en' : 'it';
    MHW\LinguaSito::imposta($lingua);
    $c = [];
    foreach (MHW\TestiSito::catalogo() as $k => [, , $tipo]) {
        $campo = 't_' . str_replace('.', '_', $k);
        if (!array_key_exists($campo, $_POST)) continue;
        $v = trim(str_replace("\r", '', (string) $_POST[$campo]));
        $v = mb_substr($v, 0, $tipo === 'riga' ? MHW\TestiSito::RIGA_MAX : ($tipo === 'lungo' ? MHW\TestiSito::LUNGO_MAX : MHW\TestiSito::AREA_MAX));
        if ($tipo === 'riga') $v = preg_replace('/\s+/', ' ', $v);
        $c[$k] = $v;   // vuoto o uguale al testo di serie: TestiSito::set toglie la riga
    }
    // Le foto: JPG, PNG o WebP, ridisegnate come le altre immagini caricate; quella vecchia si cancella.
    $daCancellare = [];
    try {
        foreach (array_keys(MHW\TestiSito::IMMAGINI) as $nome) {
            // Campi file «annidati» (img[telefono]): PHP li mette in $_FILES['img']['error']['telefono'], ecc.
            $errore = (int) ($_FILES['img']['error'][$nome] ?? UPLOAD_ERR_NO_FILE);
            $vecchia = MHW\TestiSito::riferimentoImmagine($nome);
            if ($errore !== UPLOAD_ERR_NO_FILE) {
                $f = ['name' => $_FILES['img']['name'][$nome] ?? '', 'type' => $_FILES['img']['type'][$nome] ?? '', 'tmp_name' => $_FILES['img']['tmp_name'][$nome] ?? '',
                      'error' => $errore, 'size' => $_FILES['img']['size'][$nome] ?? 0];
                [$driver, $key] = Media::storeFreeImage($f, 'sito');
                $c["img.$nome"] = $driver . ':' . $key;
                if ($vecchia !== '') $daCancellare[] = $vecchia;
            } elseif (!empty($_POST['img_togli'][$nome]) && $vecchia !== '') {
                $c["img.$nome"] = '';
                $daCancellare[] = $vecchia;
            }
        }
    } catch (\RuntimeException $e) {
        Support::flash($e->getMessage(), 'err'); Support::redirect('/admin/testi#testi-foto');
    }
    $prima = []; foreach (array_keys($c) as $k) $prima[$k] = str_starts_with($k, 'img.') ? MHW\TestiSito::riferimentoImmagine(substr($k, 4)) : MHW\TestiSito::get($k);
    // I testi nella lingua della linguetta; le foto (img.*) valgono per tutte e due.
    MHW\TestiSito::set(array_filter($c, fn($k) => !str_starts_with($k, 'img.'), ARRAY_FILTER_USE_KEY), $lingua);
    MHW\TestiSito::set(array_filter($c, fn($k) => str_starts_with($k, 'img.'), ARRAY_FILTER_USE_KEY), 'it');
    foreach ($daCancellare as $rif) { [$d, $k] = explode(':', $rif, 2); try { Storages::for($d)->delete($k); } catch (\Throwable $e) { Log::exception($e, 'testi.foto'); } }
    $cambiate = array_values(array_keys(array_filter($c, fn($v, $k) => $prima[$k] !== (str_starts_with($k, 'img.') ? $v : MHW\TestiSito::get($k)), ARRAY_FILTER_USE_BOTH)));
    Auth::audit('testi.save', null, ['cambiate' => $cambiate, 'lingua' => $lingua]);
    $legali = !empty($_POST['legali']);
    $legaliCambiati = array_filter($cambiate, fn($k) => in_array(explode('.', $k)[0], MHW\TestiSito::SEZIONI_LEGALI, true));
    Support::flash(!$cambiate ? 'Niente da cambiare.' : ($legaliCambiati
        ? 'Salvato: i documenti legali sono già aggiornati. Se la modifica è sostanziale, cambia la versione in Impostazioni → Titolare e documenti legali: i clienti ricevono l\'avviso e accettano di nuovo.'
        : ($lingua === 'en' ? 'Salvato: la home inglese (/en) è già aggiornata.' : 'Salvato: la home è già aggiornata.')));
    $q = array_filter(['lingua' => $lingua === 'en' ? 'en' : null, 'doc' => $legali ? 'legali' : null]);
    Support::redirect('/admin/testi' . ($q ? '?' . http_build_query($q) : '') . (($s = (string) ($_POST['torna'] ?? '')) !== '' && preg_match('/^[a-z-]+$/', $s) ? '#testi-' . $s : ''));
});

$r->get('/admin/seo', function () {
    Auth::requireAdmin();
    View::out('admin/seo', ['nav' => 'seo', 'pronta' => MHW\Seo::disponibili()], 'layout/cms');
});

$r->post('/admin/seo', function () {
    Auth::requireAdmin();
    if (!MHW\Seo::disponibili()) { Support::flash('Manca la tabella delle impostazioni SEO: ricarica la pagina per applicare gli aggiornamenti.', 'err'); Support::redirect('/admin/seo'); }
    $in = fn(string $k, int $max) => mb_substr(trim(str_replace("\r", '', (string) ($_POST[$k] ?? ''))), 0, $max);
    $dominio = rtrim($in('dominio', 200), '/');
    if ($dominio !== '' && !preg_match('~^https?://[a-z0-9.-]+(:\d+)?(/[^\s?#]*)?$~i', $dominio)) {
        Support::flash('Il dominio va scritto per intero, per esempio https://myhousewelcome.it.', 'err'); Support::redirect('/admin/seo#seo-motori');
    }
    // Chi incolla tutto il meta di verifica: si tiene solo il codice.
    $codice = function (string $k) use ($in): string {
        $v = $in($k, 300);
        if (preg_match('/content\s*=\s*["\']([^"\']+)["\']/i', $v, $m)) $v = $m[1];
        return preg_replace('/[^A-Za-z0-9_\-]/', '', $v);
    };
    $c = ['dominio' => $dominio, 'gsc' => $codice('gsc'), 'bing' => $codice('bing')];
    // 8H · statistiche delle visite: si salvano solo se tutti i campi sono giusti.
    [$stat, $errStat] = MHW\Statistiche::pulisci(['matomo_url' => $in('stat_matomo_url', 300), 'matomo_id' => $in('stat_matomo_id', 10),
                                                   'ga4' => $in('stat_ga4', 40), 'clarity' => $in('stat_clarity', 40)]);
    if ($errStat !== '') { Support::flash($errStat, 'err'); Support::redirect('/admin/seo#seo-statistiche'); }
    $c += $stat;
    foreach (array_keys(MHW\Seo::PAGINE) as $p) {
        $c["p.$p.titolo"] = preg_replace('/\s+/', ' ', $in("p_{$p}_titolo", 200));
        $c["p.$p.descrizione"] = preg_replace('/\s+/', ' ', $in("p_{$p}_descrizione", 400));
        $c["p.$p.indicizza"] = !empty($_POST["p_{$p}_indicizza"]) ? '1' : '0';
    }
    foreach (['nome' => 120, 'venditore' => 160, 'piva' => 20, 'indirizzo' => 200, 'email' => 160, 'telefono' => 40, 'social' => 2000] as $k => $max) {
        $c["az.$k"] = $in("az_$k", $max);
    }
    foreach (array_keys(MHW\Seo::BOT) as $b) $c["bot.$b"] = !empty($_POST['bot'][$b]) ? '1' : '0';
    $c['llms.intro'] = preg_replace('/\s+/', ' ', $in('llms_intro', MHW\Seo::INTRO_MAX));
    $c['llms.fatti'] = implode("\n", array_filter(array_map('trim', explode("\n", $in('llms_fatti', 4000)))));
    // L'immagine per la condivisione: JPG o PNG, ridisegnata come le altre immagini caricate.
    $vecchia = MHW\Seo::get('og_immagine.file');
    $file = $_FILES['og_immagine'] ?? null;
    try {
        if ($file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $tipo = @getimagesize((string) ($file['tmp_name'] ?? ''))['mime'] ?? '';
            if (!in_array($tipo, ['image/jpeg', 'image/png'], true)) throw new \RuntimeException('L\'immagine per la condivisione va in JPG o PNG.');
            [$driver, $key] = Media::storeFreeImage($file, 'seo');
            $c['og_immagine.file'] = $driver . ':' . $key;
            $c['og_immagine'] = Storages::for($driver)->url($key);
        } elseif (!empty($_POST['og_togli'])) {
            $c['og_immagine.file'] = ''; $c['og_immagine'] = '';
        }
    } catch (\RuntimeException $e) {
        Support::flash($e->getMessage(), 'err'); Support::redirect('/admin/seo#seo-p-home');
    }
    if (array_key_exists('og_immagine.file', $c) && $vecchia !== '' && str_contains($vecchia, ':')) {
        [$d, $k] = explode(':', $vecchia, 2);
        try { Storages::for($d)->delete($k); } catch (\Throwable $e) { Log::exception($e, 'seo.immagine'); }
    }
    $prima = []; foreach (array_keys($c) as $k) $prima[$k] = MHW\Seo::get($k);
    MHW\Seo::set($c);
    Auth::audit('seo.save', null, ['cambiate' => array_values(array_keys(array_filter($c, fn($v, $k) => $prima[$k] !== $v, ARRAY_FILTER_USE_BOTH)))]);
    // 7F: anche come file veri accanto a index.php; se la cartella non è scrivibile, lo si dice.
    [$okFile, $msgFile] = MHW\Seo::scriviFile();
    Support::flash($okFile ? 'Salvato. Sitemap, robots.txt e llms.txt sono già aggiornati, anche come file nella cartella principale del sito.'
                           : 'Salvato. Sitemap, robots.txt e llms.txt sono aggiornati, ma: ' . $msgFile, $okFile ? 'ok' : 'err');
    Support::redirect('/admin/seo');
});
/* GEO · «Avvisa i motori (IndexNow)»: un modulo a sé sotto SEO e GEO. */
$r->post('/admin/seo/indexnow', function () {
    Auth::requireAdmin();
    if (!MHW\Seo::disponibili()) Support::redirect('/admin/seo');
    MHW\Seo::scriviFile();   // prima la chiave, anche come file: IndexNow la va a leggere
    [$ok, $msg] = MHW\Seo::avvisaIndexNow();
    Auth::audit('seo.indexnow', null, ['ok' => $ok, 'esito' => $msg]);
    Support::flash($msg, $ok ? 'ok' : 'err');
    Support::redirect('/admin/seo#seo-indexnow');
});
