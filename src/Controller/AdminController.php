<?php

declare(strict_types=1);

namespace ArcoDelVento\Controller;

use ArcoDelVento\Admin\Cruscotto;
use ArcoDelVento\Admin\Form;
use ArcoDelVento\Admin\Inbox;
use ArcoDelVento\Admin\Schema;
use ArcoDelVento\App;
use ArcoDelVento\Http\Request;
use ArcoDelVento\Http\Response;
use ArcoDelVento\I18n\Routes;
use ArcoDelVento\Media\Elaboratore;
use ArcoDelVento\Media\ErroreImmagine;
use ArcoDelVento\Media\Immagini;
use ArcoDelVento\Storage\ContentOverrides;
use ArcoDelVento\Support\Csrf;

/**
 * L'area riservata: /admin.
 *
 * Tutto quello che scrive passa da un modulo con il gettone anti-CSRF e da
 * una sessione aperta con la password. Ogni risposta chiede di non essere
 * memorizzata, indicizzata o incorniciata in un'altra pagina.
 *
 * Le modifiche non toccano mai i file di content/: vanno in storage/data e
 * si sovrappongono (vedi ContentOverrides). Ogni salvataggio tiene la
 * versione precedente, e dal pannello la si rimette con un click.
 */
final class AdminController
{
    private const FLASH = '_adv_admin_flash';

    /** Gli archivi che si possono riportare a una versione precedente. */
    private const RIPRISTINABILI = ['impostazioni', 'camere', 'testi-it', 'testi-en', 'domande'];

    /** Le sezioni dei testi, con il nome che hanno nel pannello. */
    private const SEZIONI_TESTI = [
        'home'      => 'Home',
        'rooms'     => 'Elenco delle camere',
        'room'      => 'Scheda di una camera',
        'property'  => 'La struttura',
        'assisi'    => 'Assisi',
        'places'    => 'I luoghi di Assisi',
        'info'      => 'Informazioni',
        'book'      => 'Prenotazione',
        'contact'   => 'Contatti',
        'footer'    => 'Piè di pagina',
        'nav'       => 'Menu',
        'cta'       => 'Pulsanti e inviti',
        'common'    => 'Parole ricorrenti',
        'privacy'   => 'Privacy',
        'not_found' => 'Pagina non trovata',
        'errors'    => 'Errori dei moduli',
        'amenities' => 'Servizi delle camere',
        'room_types' => 'Tipologie di camera',
        'views'     => 'Viste',
        'bathroom'  => 'Bagno',
        'layouts'   => 'Disposizioni dei letti',
        'beds'      => 'Letti',
    ];

    private readonly Form $form;

    public function __construct(private readonly App $app)
    {
        $this->form = new Form(array_values((array) $app->config('i18n.available')));
    }

    public function handle(Request $request): Response
    {
        $this->app->translator()->setLocale('it');

        $resto  = trim(substr($request->path, strlen('/admin')), '/');
        $pezzi  = $resto === '' ? [] : explode('/', $resto);
        $metodo = $request->isPost() ? 'POST' : 'GET';

        // Un file più grande di post_max_size fa arrivare la richiesta vuota,
        // gettone compreso: senza questo controllo sembrerebbe una sessione
        // scaduta. Si torna alla pagina con il motivo vero; non si scrive niente.
        if ($request->isPost() && $request->post === [] && (int) ($request->server['CONTENT_LENGTH'] ?? 0) > self::byte((string) ini_get('post_max_size'))) {
            $this->flash('errore', 'Il file è troppo grande per il server: al massimo ' . $this->limiteCaricamento() . '. Riducilo e riprova.');

            return $this->headers(Response::redirect($this->url($resto), 303));
        }

        if ($request->isPost() && !Csrf::isValid($this->in($request, '_token'))) {
            // 403 e non 419: il 419 non è un codice HTTP standard, e Apache lo
            // trasforma in un 500 (visto in prova). LiteSpeed potrebbe fare lo stesso.
            return $this->headers(Response::html($this->page('scaduta', [
                'titolo' => 'Sessione scaduta',
            ]), 403));
        }

        $auth = $this->app->auth();

        // ------------------------------------------------ prima volta e accesso
        if (!$auth->hasAccount()) {
            return $this->headers($pezzi === ['configura'] && $metodo === 'POST'
                ? $this->setup($request)
                : Response::html($this->page('configura', ['titolo' => 'Prima configurazione', 'disponibile' => $auth->setupAvailable()])));
        }

        if ($pezzi === ['accesso'] && $metodo === 'POST') {
            return $this->headers($this->login($request));
        }

        $utente = $auth->current();
        if ($utente === null) {
            return $this->headers(Response::html($this->page('accesso', ['titolo' => 'Accesso'])));
        }

        // ------------------------------------------------ dentro
        $risposta = match (true) {
            $pezzi === []                                   => $this->dashboard($request),
            $pezzi === ['esci'] && $metodo === 'POST'        => $this->logout(),
            $pezzi === ['richieste']                        => $this->requests($request),
            count($pezzi) === 2 && $pezzi[0] === 'richieste' => $this->request($request, $pezzi[1]),
            $pezzi === ['struttura']                        => $this->settings($request),
            $pezzi === ['camere']                           => $this->rooms(),
            count($pezzi) === 2 && $pezzi[0] === 'camere'    => $this->room($request, $pezzi[1]),
            $pezzi === ['testi']                            => $this->texts(),
            count($pezzi) === 2 && $pezzi[0] === 'testi'     => $this->textSection($request, $pezzi[1]),
            $pezzi === ['domande']                          => $this->faq($request),
            $pezzi === ['immagini']                         => $this->images(),
            count($pezzi) === 2 && $pezzi[0] === 'immagini'  => $this->image($request, $pezzi[1]),
            $pezzi === ['account']                          => $this->account($request),
            $pezzi === ['ripristina'] && $metodo === 'POST'  => $this->restore($request),
            default                                         => Response::html($this->page('non-trovata', ['titolo' => 'Pagina non trovata']), 404),
        };

        return $this->headers($risposta);
    }

    // =============================================================== accesso

    private function setup(Request $request): Response
    {
        $auth = $this->app->auth();
        $ip   = $this->ip($request);

        if (!$auth->setupAvailable()) {
            return Response::html($this->page('configura', ['titolo' => 'Prima configurazione', 'disponibile' => false]), 403);
        }
        if ($auth->isLockedOut($ip)) {
            return Response::html($this->page('configura', [
                'titolo' => 'Prima configurazione', 'disponibile' => true,
                'errori' => ['Troppi tentativi. Riprova fra ' . $auth->minutesLocked($ip) . ' minuti.'],
            ]), 429);
        }

        $utente   = mb_strtolower(trim((string) $this->in($request, 'utente', '')));
        $password = (string) $this->in($request, 'password', '');
        $errori   = [];

        if (!$auth->setupTokenValid((string) $this->in($request, 'gettone', ''))) {
            $auth->recordFailure($ip);
            $errori[] = 'Il codice di configurazione non è giusto. È la riga ADMIN_SETUP_TOKEN del file .env.';
        }
        if (!preg_match('/^[a-z0-9._-]{3,40}$/', $utente)) {
            $errori[] = 'Il nome utente: da 3 a 40 caratteri, solo lettere minuscole, cifre, punto, trattino.';
        }
        $errori = array_merge($errori, $auth->passwordProblems($utente, $password, (string) $this->in($request, 'ripeti', '')));

        if ($errori !== []) {
            return Response::html($this->page('configura', [
                'titolo' => 'Prima configurazione', 'disponibile' => true,
                'errori' => $errori, 'valori' => ['utente' => $utente],
            ]), 422);
        }

        $auth->createAccount($utente, $password);
        $auth->clearFailures($ip);
        $auth->login($utente);
        $this->flash('ok', 'Account creato. Adesso togli il valore di ADMIN_SETUP_TOKEN dal file .env: non serve più.');

        return Response::redirect($this->url(), 303);
    }

    private function login(Request $request): Response
    {
        $auth = $this->app->auth();
        $ip   = $this->ip($request);

        if ($auth->isLockedOut($ip)) {
            return Response::html($this->page('accesso', [
                'titolo' => 'Accesso',
                'errori' => ['Troppi tentativi sbagliati. Riprova fra ' . $auth->minutesLocked($ip) . ' minuti.'],
            ]), 429);
        }

        $utente = trim((string) $this->in($request, 'utente', ''));
        if (!$auth->verify($utente, (string) $this->in($request, 'password', ''))) {
            $auth->recordFailure($ip);

            return Response::html($this->page('accesso', [
                'titolo' => 'Accesso',
                'errori' => ['Nome utente o password non giusti.'],
                'valori' => ['utente' => $utente],
            ]), 401);
        }

        $auth->clearFailures($ip);
        $auth->login($auth->username());

        return Response::redirect($this->url(), 303);
    }

    private function logout(): Response
    {
        $this->app->auth()->logout();

        return Response::redirect($this->url(), 303);
    }

    private function account(Request $request): Response
    {
        $auth = $this->app->auth();
        if (!$request->isPost()) {
            return Response::html($this->page('account', ['titolo' => 'Account', 'utente' => $auth->username()]));
        }

        $errori = [];
        if (!$auth->verify($auth->username(), (string) $this->in($request, 'attuale', ''))) {
            $auth->recordFailure($this->ip($request));
            $errori[] = 'La password attuale non è giusta.';
        }
        $errori = array_merge($errori, $auth->passwordProblems(
            $auth->username(),
            (string) $this->in($request, 'nuova', ''),
            (string) $this->in($request, 'ripeti', '')
        ));
        if ($errori !== []) {
            return Response::html($this->page('account', [
                'titolo' => 'Account', 'utente' => $auth->username(), 'errori' => $errori,
            ]), 422);
        }

        $auth->changePassword((string) $this->in($request, 'nuova', ''));
        $auth->login($auth->username());
        $this->flash('ok', 'Password cambiata.');

        return Response::redirect($this->url('account'), 303);
    }

    // =============================================================== bacheca

    private function dashboard(Request $request): Response
    {
        $inbox     = $this->app->inbox();
        $richieste = $inbox->all();
        $periodo   = $request->get('periodo') === 'mesi' ? 'mesi' : 'settimana';

        return Response::html($this->page('bacheca', [
            'titolo'           => 'Bacheca',
            'sottotitolo'      => 'Le richieste arrivate dal sito e quello che resta da fare.',
            'cruscotto'        => new Cruscotto($richieste),
            'periodo'          => $periodo,
            'ultime'           => array_slice($richieste, 0, 6),
            'mancanti'         => $this->mancanti(),
            'camereIncomplete' => $this->camereIncomplete(),
            'camere'           => $this->app->rooms()->all(),
            'demo'             => (string) $this->app->config('booking.provider') === 'demo',
        ]));
    }

    /**
     * I dati della casa che mancano: sul sito sono «da confermare».
     *
     * @return list<array{label:string,gruppo:string,path:string}>
     */
    private function mancanti(): array
    {
        $impostazioni = $this->app->settings();
        $mancanti = [];
        foreach (Schema::settings() as $gruppo => $campi) {
            foreach ($campi as $campo) {
                if ($campo['type'] === 'bool') {
                    continue;
                }
                $v = Form::get($impostazioni, $campo['path']);
                if ($v === null || $v === '' || (is_array($v) && array_filter($v, static fn ($x) => $x !== null && $x !== '') === [])) {
                    $mancanti[] = ['label' => $campo['label'], 'gruppo' => $gruppo, 'path' => $campo['path']];
                }
            }
        }
        // Il CIN prima di tutto: è l'unico obbligo di legge.
        usort($mancanti, static fn (array $a, array $b): int => ($b['path'] === 'legal.cin') <=> ($a['path'] === 'legal.cin'));

        return $mancanti;
    }

    /** @return list<array{ref:string,nome:string,buchi:list<string>}> */
    private function camereIncomplete(): array
    {
        $out = [];
        foreach ($this->app->rooms()->all() as $camera) {
            $buchi = [];
            if (empty($camera['size_sqm'])) {
                $buchi[] = 'metratura';
            }
            if (empty($camera['floor'])) {
                $buchi[] = 'piano';
            }
            if (empty($camera['photographed'])) {
                $buchi[] = 'fotografia';
            }
            if ($buchi !== []) {
                $out[] = ['ref' => (string) $camera['ref'], 'nome' => (string) ($camera['name']['it'] ?? $camera['ref']), 'buchi' => $buchi];
            }
        }

        return $out;
    }

    // =============================================================== richieste

    private function requests(Request $request): Response
    {
        $stato = (string) $this->in($request, 'stato', '');
        $cerca = trim(mb_substr((string) $this->in($request, 'q', ''), 0, 80));
        $voci  = $this->app->inbox()->all();
        if ($cerca !== '') {
            // Si cerca fra tutte, archiviate comprese: chi cerca un nome lo
            // vuole trovare anche se la richiesta è chiusa da mesi.
            $ago  = mb_strtolower($cerca);
            $voci = array_values(array_filter($voci, static function (array $v) use ($ago): bool {
                $d = (array) ($v['dati'] ?? []);
                foreach ([$v['id'] ?? '', $d['nome'] ?? '', $d['email'] ?? '', $d['telefono'] ?? '', $d['camera'] ?? '', $d['oggetto'] ?? '', $d['messaggio'] ?? '', $d['note'] ?? ''] as $campo) {
                    if (is_string($campo) && str_contains(mb_strtolower($campo), $ago)) {
                        return true;
                    }
                }

                return false;
            }));
            $stato = '';
        } elseif ($stato !== '' && isset(Inbox::STATI[$stato])) {
            $voci = array_values(array_filter($voci, static fn (array $v): bool => ($v['stato'] ?? '') === $stato));
        } else {
            // di norma le archiviate non si vedono
            $stato = '';
            $voci  = array_values(array_filter($voci, static fn (array $v): bool => ($v['stato'] ?? '') !== 'archiviata'));
        }

        return Response::html($this->page('richieste', [
            'titolo' => 'Richieste', 'voci' => $voci, 'stato' => $stato, 'cerca' => $cerca,
        ]));
    }

    private function request(Request $request, string $id): Response
    {
        $inbox = $this->app->inbox();
        $voce  = $inbox->find($id);
        if ($voce === null) {
            return Response::html($this->page('non-trovata', ['titolo' => 'Richiesta non trovata']), 404);
        }

        if ($request->isPost()) {
            if ($this->in($request, 'azione') === 'elimina') {
                if ($this->in($request, 'conferma') !== '1') {
                    $this->flash('errore', 'Per eliminare la richiesta spunta «Sì, eliminala».');

                    return Response::redirect($this->url('richieste/' . rawurlencode($id)), 303);
                }
                $inbox->delete($id);
                $this->flash('ok', 'Richiesta ' . $id . ' eliminata.');

                return Response::redirect($this->url('richieste'), 303);
            }
            $inbox->setStatus($id, (string) $this->in($request, 'stato', ''));
            $this->flash('ok', 'Stato aggiornato.');

            return Response::redirect($this->url('richieste/' . rawurlencode($id)), 303);
        }

        // aprirla la segna come letta
        if (($voce['stato'] ?? '') === 'nuova') {
            $inbox->setStatus($id, 'letta');
            $voce['stato'] = 'letta';
        }

        return Response::html($this->page('richiesta', ['titolo' => 'Richiesta ' . $id, 'voce' => $voce]));
    }

    // =============================================================== struttura

    private function settings(Request $request): Response
    {
        $base   = $this->app->baseSettings();
        $attuali = $this->app->settings();
        $schema = Schema::settings();

        if (!$request->isPost()) {
            return Response::html($this->page('struttura', [
                'titolo' => 'La struttura', 'schema' => $schema, 'valori' => $attuali,
                'storico' => $this->app->store()->history('impostazioni'),
            ]));
        }

        $inviati = (array) ($request->post['f'] ?? []);
        $errori  = [];
        $modifiche = [];
        $nuovi   = $attuali;
        foreach ($schema as $campi) {
            foreach ($campi as $campo) {
                $percorso = $campo['path'];
                $valore = $this->form->parse($campo, $inviati[$percorso] ?? null, Form::get($base, $percorso), $errori);
                $nuovi  = ContentOverrides::setPath($nuovi, $percorso, $valore);
                if (!Form::same($valore, Form::get($base, $percorso))) {
                    $modifiche[$percorso] = $valore;
                }
            }
        }

        if ($errori !== []) {
            return Response::html($this->page('struttura', [
                'titolo' => 'La struttura', 'schema' => $schema, 'valori' => $nuovi, 'errori' => $errori,
                'storico' => $this->app->store()->history('impostazioni'),
            ]), 422);
        }

        $this->app->store()->write('impostazioni', $modifiche);
        $this->flash('ok', 'Salvato. Le modifiche sono già sul sito.');

        return Response::redirect($this->url('struttura'), 303);
    }

    // =============================================================== camere

    private function rooms(): Response
    {
        return Response::html($this->page('camere', [
            'titolo' => 'Camere e tariffe', 'camere' => $this->app->rooms()->all(),
            'storico' => $this->app->store()->history('camere'),
        ]));
    }

    private function room(Request $request, string $ref): Response
    {
        $camera = $this->app->rooms()->findByRef($ref);
        $base   = null;
        foreach ($this->app->baseRooms() as $c) {
            if (($c['ref'] ?? null) === $ref) {
                $base = $c;
            }
        }
        if ($camera === null || $base === null) {
            return Response::html($this->page('non-trovata', ['titolo' => 'Camera non trovata']), 404);
        }

        $massimo = (int) ($camera['occupancy']['max'] ?? 2);
        $schema  = Schema::room($massimo);

        if (!$request->isPost()) {
            return Response::html($this->page('camera', [
                'titolo' => (string) ($camera['name']['it'] ?? $ref), 'camera' => $camera, 'schema' => $schema,
                'valori' => $camera,
            ]));
        }

        $inviati = (array) ($request->post['f'] ?? []);
        $errori  = [];
        $nuova   = $camera;
        $tariffe = [];
        foreach ($schema as $campo) {
            $percorso = $campo['path'];
            if (str_starts_with($percorso, 'rates.')) {
                $prezzo = $this->form->parse($campo, $inviati[$percorso] ?? null, null, $errori);
                if ($prezzo !== null) {
                    $tariffe[(int) substr($percorso, 6)] = $prezzo;
                }
                continue;
            }
            $valore = $this->form->parse($campo, $inviati[$percorso] ?? null, $base[$percorso] ?? null, $errori);
            if ($percorso === 'view' && $valore === '') {
                $valore = null;
            }
            $nuova[$percorso] = $valore;
        }
        if ($tariffe === []) {
            $errori[] = 'Serve almeno una tariffa: senza, la camera non si può prenotare.';
        }
        ksort($tariffe);
        $nuova['rates'] = $tariffe;

        if ($errori !== []) {
            return Response::html($this->page('camera', [
                'titolo' => (string) ($camera['name']['it'] ?? $ref), 'camera' => $camera, 'schema' => $schema,
                'valori' => $nuova, 'errori' => $errori,
            ]), 422);
        }

        $modifiche = [];
        foreach (['name', 'size_sqm', 'floor', 'view', 'alt', 'rates'] as $campo) {
            if (!Form::same($nuova[$campo] ?? null, $base[$campo] ?? null)) {
                $modifiche[$campo] = $nuova[$campo];
            }
        }
        $store = $this->app->store();
        $tutte = $store->read('camere');
        if ($modifiche === []) {
            unset($tutte[$ref]);
        } else {
            $tutte[$ref] = $modifiche;
        }
        $store->write('camere', $tutte);
        $this->flash('ok', 'Camera salvata. Le modifiche sono già sul sito.');

        return Response::redirect($this->url('camere/' . rawurlencode($ref)), 303);
    }

    // =============================================================== testi

    private function texts(): Response
    {
        $sezioni = [];
        foreach (self::SEZIONI_TESTI as $chiave => $nome) {
            $voci = $this->strings($chiave);
            if ($voci === []) {
                continue;
            }
            $modificate = 0;
            foreach ($this->form->languages() as $lingua) {
                foreach ($this->app->store()->read('testi-' . $lingua) as $k => $_) {
                    if (str_starts_with((string) $k, $chiave . '.')) {
                        $modificate++;
                    }
                }
            }
            $sezioni[] = ['chiave' => $chiave, 'nome' => $nome, 'voci' => count($voci), 'modificate' => $modificate];
        }

        return Response::html($this->page('testi', ['titolo' => 'Testi del sito', 'sezioni' => $sezioni]));
    }

    private function textSection(Request $request, string $sezione): Response
    {
        if (!isset(self::SEZIONI_TESTI[$sezione])) {
            return Response::html($this->page('non-trovata', ['titolo' => 'Sezione non trovata']), 404);
        }
        $chiavi  = $this->strings($sezione);
        $lingue  = $this->form->languages();
        $store   = $this->app->store();
        $tr      = $this->app->translator();

        $base = $modificati = [];
        foreach ($lingue as $lingua) {
            $catalogo = $tr->baseCatalogue($lingua);
            $salvati  = $store->read('testi-' . $lingua);
            foreach ($chiavi as $chiave) {
                $base[$lingua][$chiave]       = (string) (Form::get($catalogo, $chiave) ?? '');
                $modificati[$lingua][$chiave] = isset($salvati[$chiave]) ? (string) $salvati[$chiave] : null;
            }
        }

        $dati = [
            'titolo' => 'Testi — ' . self::SEZIONI_TESTI[$sezione], 'sezione' => $sezione,
            'chiavi' => $chiavi, 'base' => $base, 'modificati' => $modificati,
            'storico' => array_merge(
                array_map(static fn (array $v): array => $v + ['nome' => 'testi-it', 'lingua' => 'IT'], $store->history('testi-it')),
                array_map(static fn (array $v): array => $v + ['nome' => 'testi-en', 'lingua' => 'EN'], $store->history('testi-en')),
            ),
        ];

        if (!$request->isPost()) {
            return Response::html($this->page('testi-sezione', $dati));
        }

        $inviati = (array) ($request->post['t'] ?? []);
        $errori  = [];
        $nuovi   = [];
        foreach ($lingue as $lingua) {
            $salvati = $store->read('testi-' . $lingua);
            foreach ($chiavi as $chiave) {
                $testo = trim(preg_replace("/\r\n?/", "\n", (string) ($inviati[$lingua][$chiave] ?? '')) ?? '');
                $originale = $base[$lingua][$chiave];
                $dati['modificati'][$lingua][$chiave] = $testo === '' ? null : $testo;
                if ($testo === '' || $testo === $originale) {
                    unset($salvati[$chiave]);
                    continue;
                }
                if (mb_strlen($testo) > 5000) {
                    $errori[] = strtoupper($lingua) . ' ' . $chiave . ': al massimo 5000 caratteri.';
                }
                // I segnaposto — :nights, :count — li riempie il sito: se
                // spariscono, la frase perde il numero.
                preg_match_all('/:[a-z_]+/', $originale, $m);
                foreach (array_unique($m[0]) as $segnaposto) {
                    if (!str_contains($testo, $segnaposto)) {
                        $errori[] = strtoupper($lingua) . ' «' . mb_substr($originale, 0, 40) . '…»: deve contenere ancora '
                                  . $segnaposto . ', che il sito sostituisce con il valore.';
                    }
                }
                $salvati[$chiave] = $testo;
            }
            $nuovi[$lingua] = $salvati;
        }

        if ($errori !== []) {
            return Response::html($this->page('testi-sezione', $dati + ['errori' => $errori]), 422);
        }
        foreach ($nuovi as $lingua => $salvati) {
            if ($salvati != $store->read('testi-' . $lingua)) {
                $store->write('testi-' . $lingua, $salvati);
            }
        }
        $this->flash('ok', 'Testi salvati. Sono già sul sito.');

        return Response::redirect($this->url('testi/' . $sezione), 303);
    }

    /**
     * Le chiavi di testo di una sezione: solo le frasi, non gli elenchi (mesi,
     * giorni, forme dei letti), che sono parte del funzionamento.
     *
     * @return list<string>
     */
    private function strings(string $sezione): array
    {
        $catalogo = $this->app->translator()->baseCatalogue('it');
        $radice   = $catalogo[$sezione] ?? null;
        if (!is_array($radice)) {
            return [];
        }
        $out = [];
        $cammina = static function (array $nodo, string $prefisso) use (&$cammina, &$out): void {
            if (array_is_list($nodo)) {
                return;
            }
            foreach ($nodo as $k => $v) {
                $chiave = $prefisso . '.' . $k;
                if (is_string($v)) {
                    $out[] = $chiave;
                } elseif (is_array($v)) {
                    $cammina($v, $chiave);
                }
            }
        };
        $cammina($radice, $sezione);

        return $out;
    }

    // =============================================================== domande

    private function faq(Request $request): Response
    {
        $voci   = $this->app->faq();
        $lingue = $this->form->languages();

        if (!$request->isPost()) {
            return Response::html($this->page('domande', [
                'titolo' => 'Domande frequenti', 'voci' => $voci,
                'storico' => $this->app->store()->history('domande'),
            ]));
        }

        $righe  = (array) ($request->post['d'] ?? []);
        $errori = [];
        $nuove  = [];
        $usati  = [];
        foreach ($righe as $i => $riga) {
            if (!is_array($riga) || ($riga['elimina'] ?? '') === '1') {
                continue;
            }
            $q = $a = [];
            $vuota = true;
            foreach ($lingue as $lingua) {
                $q[$lingua] = trim((string) ($riga['q'][$lingua] ?? ''));
                $a[$lingua] = trim(preg_replace("/\r\n?/", "\n", (string) ($riga['a'][$lingua] ?? '')) ?? '');
                $vuota = $vuota && $q[$lingua] === '' && $a[$lingua] === '';
            }
            if ($vuota) {
                continue;   // la riga per una domanda nuova, lasciata vuota
            }
            foreach ($lingue as $lingua) {
                if ($q[$lingua] === '' || $a[$lingua] === '') {
                    $errori[] = 'Domanda ' . ((int) $i + 1) . ': servono domanda e risposta in tutte le lingue ('
                              . implode(', ', array_map('strtoupper', $lingue)) . ').';
                    break;
                }
            }
            $id = preg_replace('/[^a-z0-9-]/', '', (string) ($riga['id'] ?? '')) ?: $this->slug($q['it'] ?? ('domanda-' . $i));
            while (isset($usati[$id])) {
                $id .= '-2';
            }
            $usati[$id] = true;
            $nuove[] = [
                'id'        => $id,
                'confirmed' => ($riga['confermata'] ?? '') === '1',
                'ordine'    => (int) ($riga['ordine'] ?? ($i + 1)),
                'q'         => $q,
                'a'         => $a,
            ];
        }
        usort($nuove, static fn (array $x, array $y): int => $x['ordine'] <=> $y['ordine']);
        $nuove = array_map(static function (array $v): array {
            unset($v['ordine']);

            return $v;
        }, $nuove);

        if ($errori !== []) {
            return Response::html($this->page('domande', [
                'titolo' => 'Domande frequenti', 'voci' => $nuove, 'errori' => $errori,
                'storico' => $this->app->store()->history('domande'),
            ]), 422);
        }

        $this->app->store()->write('domande', ['voci' => $nuove]);
        $this->flash('ok', 'Domande salvate. Sono già sul sito.');

        return Response::redirect($this->url('domande'), 303);
    }

    // =============================================================== immagini

    private function images(): Response
    {
        $media  = $this->app->media();
        $gruppi = [];
        foreach ($media->posti() as $chiave => $posto) {
            $gruppi[$posto['gruppo']][] = ['chiave' => $chiave, 'posto' => $posto, 'img' => $media->risolvi($chiave)];
        }

        return Response::html($this->page('immagini', [
            'titolo' => 'Immagini e logo', 'gruppi' => $gruppi,
            'sottotitolo' => 'Cambia il logo, l\'icona e le fotografie del sito, senza FTP.',
        ] + $this->statoCaricamento()));
    }

    private function image(Request $request, string $chiave): Response
    {
        $media = $this->app->media();
        $posto = $media->posto($chiave);
        if ($posto === null) {
            return Response::html($this->page('non-trovata', ['titolo' => 'Immagine non trovata']), 404);
        }
        $dati = fn (array $extra = []): array => [
            'titolo' => (string) $posto['nome'], 'sottotitolo' => (string) $posto['gruppo'], 'chiave' => $chiave, 'posto' => $posto,
            'img' => $media->risolvi($chiave), 'registrata' => $media->registrata($chiave),
        ] + $extra + $this->statoCaricamento();

        if (!$request->isPost()) {
            return Response::html($this->page('immagine', $dati()));
        }

        $ritorno = 'immagini/' . rawurlencode($chiave);
        switch ($this->in($request, 'azione')) {
            case 'carica':
                $file = $_FILES['immagine'] ?? null;
                $errore = $this->erroreCaricamento(is_array($file) ? $file : null);
                if ($errore === null) {
                    try {
                        $media->carica($chiave, (string) $file['tmp_name'], (string) ($file['name'] ?? ''));
                    } catch (ErroreImmagine $e) {
                        $errore = $e->getMessage();
                    }
                }
                if ($errore !== null) {
                    return Response::html($this->page('immagine', $dati(['errori' => [$errore]])), 422);
                }
                $avviso = $media->registrata($chiave)['avviso'] ?? null;
                $this->flash('ok', 'Immagine caricata: è già sul sito.'
                    . ($avviso ? ' Attenzione: ' . $avviso : '')
                    . (isset($posto['camera']) ? ' Se la foto mostra qualcosa di diverso, aggiorna la descrizione nella pagina della camera.'
                        : (!empty($posto['alt']) ? ' Scrivi qui sotto che cosa mostra, per chi non la vede.' : '')));

                return Response::redirect($this->url($ritorno), 303);

            case 'testi':
                if ($media->registrata($chiave) === null) {
                    return Response::redirect($this->url($ritorno), 303);
                }
                $alt = $credito = [];
                $errori = [];
                foreach ($this->form->languages() as $lingua) {
                    $alt[$lingua]     = trim((string) ($request->post['alt'][$lingua] ?? ''));
                    $credito[$lingua] = trim((string) ($request->post['credito'][$lingua] ?? ''));
                    if (mb_strlen($alt[$lingua]) > 250 || mb_strlen($credito[$lingua]) > 160) {
                        $errori[] = strtoupper($lingua) . ': la descrizione al massimo 250 caratteri, la didascalia 160.';
                    }
                }
                if ($errori !== []) {
                    return Response::html($this->page('immagine', $dati(['errori' => $errori])), 422);
                }
                $media->aggiornaTesti($chiave, $alt, $credito);
                $this->flash('ok', 'Descrizione salvata.');

                return Response::redirect($this->url($ritorno), 303);

            case 'ripristina':
                $this->flash('ok', $media->ripristina($chiave)
                    ? 'Tornata l\'immagine originale. Il file caricato è stato cancellato.'
                    : 'Questa immagine era già l\'originale.');

                return Response::redirect($this->url($ritorno), 303);
        }

        return Response::redirect($this->url($ritorno), 303);
    }

    /**
     * Che cosa è andato storto nel caricamento, detto a chi carica.
     *
     * @param array<string,mixed>|null $file
     */
    private function erroreCaricamento(?array $file): ?string
    {
        $codice = is_array($file) ? (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;

        return match (true) {
            $codice === UPLOAD_ERR_NO_FILE => 'Scegli prima un file.',
            $codice === UPLOAD_ERR_INI_SIZE, $codice === UPLOAD_ERR_FORM_SIZE
                => 'Il file è troppo grande per il server: al massimo ' . $this->limiteCaricamento() . '. Riducilo e riprova.',
            $codice === UPLOAD_ERR_PARTIAL => 'Il file è arrivato a metà: la connessione si è interrotta. Riprova.',
            $codice !== UPLOAD_ERR_OK => 'Il server non ha potuto ricevere il file (errore ' . $codice . '). Se si ripete, chiedi all\'assistenza di Hostinger.',
            !is_uploaded_file((string) ($file['tmp_name'] ?? '')) => 'Il file non è arrivato. Riprova.',
            default => null,
        };
    }

    /** @return array{scrivibile: bool, gd: bool, webp: bool, limite: string} */
    private function statoCaricamento(): array
    {
        return [
            'scrivibile' => Immagini::cartellaScrivibile($this->app->config('root') . '/public'),
            'gd'         => Elaboratore::disponibile(),
            'webp'       => Elaboratore::scriveWebp(),
            'limite'     => $this->limiteCaricamento(),
        ];
    }

    /** Il file più grande che il server accetta, detto in MB. */
    private function limiteCaricamento(): string
    {
        $limite = min(
            self::byte((string) ini_get('upload_max_filesize')) ?: PHP_INT_MAX,
            self::byte((string) ini_get('post_max_size')) ?: PHP_INT_MAX,
            Elaboratore::MAX_BYTE
        );

        return rtrim(rtrim(number_format($limite / 1048576, 1, ',', ''), '0'), ',') . ' MB';
    }

    /** «8M» → 8388608. Zero vuol dire senza limite. */
    private static function byte(string $valore): int
    {
        $valore = trim($valore);
        $numero = (int) $valore;

        return match (strtolower(substr($valore, -1))) {
            'g' => $numero * 1024 ** 3,
            'm' => $numero * 1024 ** 2,
            'k' => $numero * 1024,
            default => $numero,
        };
    }

    // =============================================================== storico

    private function restore(Request $request): Response
    {
        $nome   = (string) $this->in($request, 'nome', '');
        $id     = (string) $this->in($request, 'versione', '');
        $ritorno = (string) $this->in($request, 'ritorno', '');
        if (!in_array($nome, self::RIPRISTINABILI, true) || !$this->app->store()->restore($nome, $id)) {
            $this->flash('errore', 'Versione non trovata.');
        } else {
            $this->flash('ok', 'Versione del ' . $this->quando($id) . ' ripristinata. La versione di prima resta nello storico.');
        }
        $ritorno = preg_match('#^[a-z/_-]*$#', $ritorno) ? $ritorno : '';

        return Response::redirect($this->url($ritorno), 303);
    }

    // =============================================================== interni

    /** @param array<string,mixed> $dati */
    private function page(string $vista, array $dati): string
    {
        $flash = $_SESSION[self::FLASH] ?? null;
        unset($_SESSION[self::FLASH]);
        $utente = $this->app->auth()->current();

        $dati += [
            'flash'  => $flash,
            'utente' => $utente,
            'nuove'  => $utente !== null ? $this->app->inbox()->countNew() : 0,
            'daCompletare' => $utente !== null ? count($this->mancanti()) + count($this->camereIncomplete()) : 0,
            'errori' => [],
            'valori' => [],
            'lingue' => $this->form->languages(),
            'vista'  => $vista,
        ];
        $dati['contenuto'] = $this->app->view()->render('admin/' . $vista, $dati);

        return $this->app->view()->render('admin/layout', $dati);
    }

    private function headers(Response $r): Response
    {
        return $r->withHeader('Cache-Control', 'no-store, private')
                 ->withHeader('X-Robots-Tag', 'noindex, nofollow')
                 ->withHeader('X-Frame-Options', 'DENY')
                 ->withHeader('Referrer-Policy', 'same-origin')
                 ->withHeader('X-LiteSpeed-Cache-Control', 'no-cache');
    }

    private function flash(string $tipo, string $testo): void
    {
        $_SESSION[self::FLASH] = ['tipo' => $tipo, 'testo' => $testo];
    }

    private function url(string $sotto = ''): string
    {
        return Routes::base() . '/admin' . ($sotto !== '' ? '/' . ltrim($sotto, '/') : '');
    }

    /** Un valore del modulo (in POST) o dell'indirizzo (in GET), sempre come testo. */
    private function in(Request $request, string $chiave, string $predefinito = ''): string
    {
        $valore = $request->all()[$chiave] ?? $predefinito;

        return is_string($valore) ? $valore : $predefinito;
    }

    private function ip(Request $request): string
    {
        return (string) ($request->server['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    private function slug(string $testo): string
    {
        $t = strtolower((string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $testo));
        $t = trim((string) preg_replace('/[^a-z0-9]+/', '-', $t), '-');

        return substr($t !== '' ? $t : 'domanda', 0, 40);
    }

    private function quando(string $id): string
    {
        return preg_match('/^(\d{4})(\d{2})(\d{2})-(\d{2})(\d{2})/', $id, $m)
            ? "{$m[3]}/{$m[2]}/{$m[1]} alle {$m[4]}:{$m[5]}"
            : $id;
    }
}
