<?php

declare(strict_types=1);

namespace ArcoDelVento\Controller;

use ArcoDelVento\Admin\Ambiente;
use ArcoDelVento\Admin\Auth;
use ArcoDelVento\Admin\Cruscotto;
use ArcoDelVento\Admin\Form;
use ArcoDelVento\Admin\Inbox;
use ArcoDelVento\Admin\Schema;
use ArcoDelVento\Admin\Testi;
use ArcoDelVento\App;
use ArcoDelVento\Http\Request;
use ArcoDelVento\Http\Response;
use ArcoDelVento\I18n\Routes;
use ArcoDelVento\Mail\MailMessage;
use ArcoDelVento\Media\Elaboratore;
use ArcoDelVento\Media\ErroreImmagine;
use ArcoDelVento\Media\Immagini;
use ArcoDelVento\Storage\ContentOverrides;
use ArcoDelVento\Support\Csrf;
use ArcoDelVento\Support\RoomPresenter;

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

    private readonly Form $form;

    /** La pagina corrente sotto /admin, con la sua query: il pulsante del tema ci torna. */
    private string $percorso = '';

    /** Il cookie del tema: chiaro, scuro, o assente (segue il sistema). */
    private const COOKIE_TEMA = 'adv_tema';

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
        $query  = (string) ($request->server['QUERY_STRING'] ?? '');
        $this->percorso = $resto . ($query !== '' && !$request->isPost() ? '?' . $query : '');

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

        // Il tema si sceglie anche dalla pagina di accesso: prima di tutto il resto.
        if ($pezzi === ['tema'] && $metodo === 'POST') {
            return $this->headers($this->theme($request));
        }

        $auth = $this->app->auth();

        // ------------------------------------------------ la porta del super admin
        // Un indirizzo che non compare da nessuna parte: per chi non ha il suo
        // gettone, o non è lui, è una pagina che non esiste.
        if ($pezzi === ['super']) {
            return $this->headers($this->superDoor($request));
        }

        // ------------------------------------------------ prima volta e accesso
        if (!$auth->hasAccount() && $pezzi === ['configura'] && $metodo === 'POST') {
            return $this->headers($this->setup($request));
        }

        if ($pezzi === ['accesso'] && $metodo === 'POST' && ($auth->hasAccount() || $auth->hasSuper())) {
            return $this->headers($this->login($request));
        }

        $utente = $auth->current();
        if ($utente === null) {
            return $this->headers($auth->hasAccount()
                ? Response::html($this->page('accesso', ['titolo' => 'Accesso']))
                : Response::html($this->page('configura', ['titolo' => 'Prima configurazione', 'disponibile' => $auth->setupAvailable()])));
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
            $pezzi === ['testi']                            => $this->texts($request),
            count($pezzi) === 2 && $pezzi[0] === 'testi'     => $this->textSection($request, $pezzi[1]),
            $pezzi === ['domande']                          => $this->faq($request),
            $pezzi === ['immagini']                         => $this->images(),
            count($pezzi) === 2 && $pezzi[0] === 'immagini'  => $this->image($request, $pezzi[1]),
            $pezzi === ['posta']                            => $this->mail($request),
            $pezzi === ['accesso-sito']                     => $this->siteAccess($request),
            $pezzi === ['account']                          => $this->account($request),
            $pezzi === ['ripristina'] && $metodo === 'POST'  => $this->restore($request),
            // Solo il super admin: per il gestore non esiste, come una pagina qualunque che non c'è.
            $pezzi === ['configurazione'] && $auth->isSuper() => $this->configuration($request),
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
        } elseif ($auth->nomeOccupato($utente)) {
            $errori[] = 'Questo nome utente non è disponibile: scegline un altro.';
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
        $ruolo  = $auth->credentials($utente, (string) $this->in($request, 'password', ''));
        if ($ruolo === null) {
            $auth->recordFailure($ip);

            return Response::html($this->page('accesso', [
                'titolo' => 'Accesso',
                'errori' => ['Nome utente o password non giusti.'],
                'valori' => ['utente' => $utente],
            ]), 401);
        }

        $auth->clearFailures($ip);
        $auth->login($auth->nome($ruolo), $ruolo);

        return Response::redirect($this->url($ruolo === Auth::SUPER && $this->in($request, 'super') === '1' ? 'configurazione' : ''), 303);
    }

    private function logout(): Response
    {
        $this->app->auth()->logout();

        return Response::redirect($this->url(), 303);
    }

    private function account(Request $request): Response
    {
        $auth  = $this->app->auth();
        $nome  = (string) $auth->current();
        $ruolo = (string) $auth->role();
        if (!$request->isPost()) {
            return Response::html($this->page('account', ['titolo' => 'Account', 'utente' => $nome]));
        }

        $errori = [];
        if (!$auth->verifyCurrent((string) $this->in($request, 'attuale', ''))) {
            $auth->recordFailure($this->ip($request));
            $errori[] = 'La password attuale non è giusta.';
        }
        $errori = array_merge($errori, $auth->passwordProblems(
            $nome,
            (string) $this->in($request, 'nuova', ''),
            (string) $this->in($request, 'ripeti', '')
        ));
        if ($errori !== []) {
            return Response::html($this->page('account', [
                'titolo' => 'Account', 'utente' => $nome, 'errori' => $errori,
            ]), 422);
        }

        $auth->changePassword((string) $this->in($request, 'nuova', ''));
        $auth->login($nome, $ruolo);
        $this->flash('ok', 'Password cambiata.');

        return Response::redirect($this->url('account'), 303);
    }

    // =========================================================== super admin

    /**
     * /admin/super, la porta del super admin. La prima volta crea l'account con
     * SUPERADMIN_SETUP_TOKEN; poi è il suo accesso, che porta dritto a
     * Configurazione. Senza gettone e senza account, e per il gestore, è una
     * pagina che non esiste.
     */
    private function superDoor(Request $request): Response
    {
        $auth = $this->app->auth();
        $nonTrovata = fn (): Response => Response::html($this->page('non-trovata', ['titolo' => 'Pagina non trovata']), 404);

        if ($auth->isSuper()) {
            return Response::redirect($this->url('configurazione'), 303);
        }
        if ($auth->current() !== null) {
            return $nonTrovata();   // il gestore: per lui non c'è
        }
        // A chi non è entrato si mostra sempre l'accesso, che ci sia un super
        // admin o no: la risposta non deve dire se l'account esiste. La
        // creazione compare solo con il gettone scritto nel .env.
        if (!$auth->superSetupAvailable()) {
            return Response::html($this->page('accesso', ['titolo' => 'Accesso', 'portaSuper' => true]));
        }
        if (!$request->isPost()) {
            return Response::html($this->page('super', ['titolo' => 'Super admin']));
        }

        $ip = $this->ip($request);
        if ($auth->isLockedOut($ip)) {
            return Response::html($this->page('super', [
                'titolo' => 'Super admin', 'errori' => ['Troppi tentativi. Riprova fra ' . $auth->minutesLocked($ip) . ' minuti.'],
            ]), 429);
        }
        $utente   = mb_strtolower(trim($this->in($request, 'utente')));
        $password = $this->in($request, 'password');
        $errori   = [];
        if (!$auth->superSetupTokenValid($this->in($request, 'gettone'))) {
            $auth->recordFailure($ip);
            $errori[] = 'Il codice non è giusto. È la riga SUPERADMIN_SETUP_TOKEN del file .env.';
        }
        if (!preg_match('/^[a-z0-9._-]{3,40}$/', $utente)) {
            $errori[] = 'Il nome utente: da 3 a 40 caratteri, solo lettere minuscole, cifre, punto, trattino.';
        } elseif ($auth->nomeOccupato($utente)) {
            $errori[] = 'Questo nome è già quello dell\'account del gestore: scegline un altro.';
        }
        $errori = array_merge($errori, $auth->passwordProblems($utente, $password, $this->in($request, 'ripeti')));
        if ($errori !== []) {
            return Response::html($this->page('super', [
                'titolo' => 'Super admin', 'errori' => $errori, 'valori' => ['utente' => $utente],
            ]), 422);
        }

        $auth->createAccount($utente, $password, Auth::SUPER);
        $auth->clearFailures($ip);
        $auth->login($utente, Auth::SUPER);
        $this->flash('ok', 'Super admin creato. Il codice SUPERADMIN_SETUP_TOKEN non serve più: svuotalo qui sotto, nel gruppo «Area riservata e controlli».');

        return Response::redirect($this->url('configurazione'), 303);
    }

    // ========================================================= configurazione

    private function ambiente(): Ambiente
    {
        return new Ambiente((string) $this->app->config('root') . '/.env', $this->app->store());
    }

    /**
     * Il pannello del file .env. Ogni scrittura chiede di nuovo la password
     * del super admin: è il pannello che può spegnere il sito.
     */
    private function configuration(Request $request): Response
    {
        $auth = $this->app->auth();
        $amb  = $this->ambiente();
        $dati = function (array $extra = []) use ($amb, $auth): array {
            $nelFile = $amb->valori();
            $inUso = static fn (string $k): string => Ambiente::daServer($k) ?? ($nelFile[$k] ?? '');

            return $extra + [
                'titolo'   => 'Configurazione',
                'ambiente' => $amb,
                'nelFile'  => $nelFile,
                'avvisi'   => Ambiente::avvisi($inUso, $auth->hasAccount(), $auth->hasSuper()),
                'copie'    => $amb->copie(),
                'registro' => array_slice($amb->registro(), 0, 8),
                'inviati'  => [],
            ];
        };

        if (!$request->isPost()) {
            return Response::html($this->page('configurazione', $dati()));
        }

        $errori = [];
        if (!$auth->verifyCurrent($this->in($request, 'conferma'))) {
            $auth->recordFailure($this->ip($request));
            $errori[] = 'La password di conferma non è giusta: non è stato scritto niente.';
        }
        if (!$amb->scrivibile()) {
            $errori[] = 'Il file .env non si può scrivere dal sito: dagli i permessi di scrittura via FTP (644 al file, 755 alla cartella).';
        }
        $utente = (string) $auth->current();

        if ($this->in($request, 'azione') === 'ripristina') {
            if ($errori === []) {
                try {
                    if ($amb->ripristina($this->in($request, 'versione'), $utente)) {
                        $this->flash('ok', 'Rimessa la versione scelta del file .env. Quella di prima è fra le copie, se serve tornare indietro.');

                        return Response::redirect($this->url('configurazione'), 303);
                    }
                    $errori[] = 'Quella copia non c\'è più.';
                } catch (\RuntimeException $e) {
                    $errori[] = $e->getMessage();
                }
            }

            return Response::html($this->page('configurazione', $dati(['errori' => $errori])), 422);
        }

        $inviati = (array) ($request->post['env'] ?? []);
        [$cambi, $problemi] = $amb->controlla($inviati, (array) ($request->post['svuota'] ?? []));
        $errori = array_merge($errori, $problemi);
        if (isset($cambi['APP_URL']) && $this->in($request, 'conferma_url') !== '1') {
            $errori[] = 'Stai cambiando l\'indirizzo del sito: spunta la conferma sotto il campo. Se l\'indirizzo è sbagliato, link, fogli di stile e pagamento smettono di funzionare.';
        }
        if ($errori !== []) {
            return Response::html($this->page('configurazione', $dati(['errori' => $errori, 'inviati' => $inviati])), 422);
        }
        if ($cambi === []) {
            $this->flash('ok', 'Nessun valore è cambiato: il file .env è rimasto com\'era.');

            return Response::redirect($this->url('configurazione'), 303);
        }

        try {
            $amb->salva($cambi, $utente);
        } catch (\RuntimeException $e) {
            return Response::html($this->page('configurazione', $dati(['errori' => [$e->getMessage()], 'inviati' => $inviati])), 500);
        }
        $this->flash('ok', 'Salvato nel file .env: ' . implode(', ', array_keys($cambi)) . '. Vale dalla prossima pagina che apri. La versione di prima è fra le copie.');

        return Response::redirect($this->url('configurazione'), 303);
    }

    // =============================================================== tema

    /**
     * Chiaro o scuro, per chi usa questo browser. È una preferenza di chi
     * guarda, non un dato del sito: sta in un cookie, non in storage/data.
     */
    private function theme(Request $request): Response
    {
        $tema = (string) $this->in($request, 'tema', '');
        $opzioni = [
            'path'     => Routes::base() . '/admin',
            'secure'   => (($request->server['HTTPS'] ?? '') !== '' && ($request->server['HTTPS'] ?? '') !== 'off')
                          || (($request->server['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'),
            'httponly' => false,   // lo scrive anche admin.js, per cambiare senza ricaricare
            'samesite' => 'Lax',
        ];
        if (in_array($tema, ['chiaro', 'scuro'], true)) {
            setcookie(self::COOKIE_TEMA, $tema, ['expires' => time() + 365 * 86400] + $opzioni);
        } else {
            setcookie(self::COOKIE_TEMA, '', ['expires' => time() - 3600] + $opzioni);
        }
        $ritorno = (string) $this->in($request, 'ritorno', '');
        $ritorno = preg_match('#^[A-Za-z0-9/_.\-]*(\?[A-Za-z0-9_=&%.\-]*)?$#', $ritorno) === 1 ? $ritorno : '';

        return Response::redirect($this->url($ritorno), 303);
    }

    private function tema(): ?string
    {
        $tema = $_COOKIE[self::COOKIE_TEMA] ?? null;

        return in_array($tema, ['chiaro', 'scuro'], true) ? $tema : null;
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
            'pagamentoAttivo'  => $this->app->pagamentoAttivo(),
            'sitoChiuso'       => $this->app->accesso()->attivo(),
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
            if ($this->in($request, 'azione') === 'verifica') {
                $pagamenti = $this->app->pagamenti();
                if ($pagamenti === null) {
                    $this->flash('errore', 'Il pagamento online è spento: nel file .env mancano PAYMENT_PROVIDER, SUMUP_API_KEY o SUMUP_MERCHANT_CODE.');
                } else {
                    $stato = $pagamenti->verifica($id);
                    $this->flash($stato === 'pagato' ? 'ok' : 'errore', 'SumUp dice: ' . match ($stato) {
                        'pagato' => 'pagata. Le e-mail di conferma sono partite, se non erano già partite.',
                        'da controllare' => 'pagata, ma con un importo diverso. Controlla la transazione nel pannello di SumUp.',
                        'non riuscito' => 'pagamento non riuscito.',
                        'scaduto' => 'pagina di pagamento scaduta, senza pagamento.',
                        default => 'non ancora pagata.',
                    });
                }

                return Response::redirect($this->url('richieste/' . rawurlencode($id)), 303);
            }
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

        return Response::html($this->page('richiesta', [
            'titolo' => 'Richiesta ' . $id, 'voce' => $voce, 'pagamentoAttivo' => $this->app->pagamentoAttivo(),
        ]));
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

        // Un campo di tariffa per ogni persona che la camera può ospitare, non
        // solo per quelle a cui oggi si vende: il campo vuoto è il modo di
        // aprirla a una persona in più.
        $massimo = (int) ($camera['occupancy']['capacity'] ?? $camera['occupancy']['max'] ?? 2);
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

    private function texts(Request $request): Response
    {
        $store   = $this->app->store();
        $salvati = [];
        foreach ($this->form->languages() as $lingua) {
            $salvati[$lingua] = $store->read('testi-' . $lingua);
        }

        $famiglie = [];
        foreach (Testi::FAMIGLIE as $f => [$nome, $descrizione]) {
            $famiglie[$f] = ['nome' => $nome, 'descrizione' => $descrizione, 'sezioni' => []];
        }
        foreach (Testi::SEZIONI as $chiave => $def) {
            $voci = $this->strings($def['radici']);
            if ($voci === []) {
                continue;
            }
            $conta = ['importanti' => 0, 'google' => 0, 'secondari' => 0];
            $modificate = 0;
            foreach ($voci as $k) {
                $conta[Testi::livello($k)]++;
                foreach ($salvati as $s) {
                    if (isset($s[$k])) {
                        $modificate++;
                        break;
                    }
                }
            }
            $famiglie[$def['famiglia']]['sezioni'][] = $def + [
                'chiave' => $chiave, 'conta' => $conta, 'voci' => count($voci), 'modificate' => $modificate,
            ];
        }

        // La ricerca: una frase vista sul sito, e dove si cambia.
        $cerca     = trim((string) ($request->query['cerca'] ?? ''));
        $risultati = [];
        if (mb_strlen($cerca) >= 3) {
            $ago = mb_strtolower($cerca);
            $tr  = $this->app->translator();
            foreach (Testi::SEZIONI as $chiave => $def) {
                foreach ($this->strings($def['radici']) as $k) {
                    foreach ($this->form->languages() as $lingua) {
                        $testo = (string) ($salvati[$lingua][$k] ?? Form::get($tr->baseCatalogue($lingua), $k) ?? '');
                        if ($testo !== '' && str_contains(mb_strtolower(str_replace('|', ' ', $testo)), $ago)) {
                            $risultati[] = ['sezione' => $chiave, 'nome_sezione' => $def['nome'], 'chiave' => $k,
                                            'nome' => Testi::nome($k), 'lingua' => $lingua, 'testo' => str_replace('|', ' ', $testo)];
                            break;
                        }
                    }
                    if (count($risultati) >= 40) {
                        break 2;
                    }
                }
            }
        }

        return Response::html($this->page('testi', [
            'titolo' => 'Testi del sito', 'famiglie' => $famiglie, 'cerca' => $cerca, 'risultati' => $risultati,
        ]));
    }

    private function textSection(Request $request, string $sezione): Response
    {
        if (isset(Testi::ALIAS[$sezione])) {
            return Response::redirect($this->url('testi/' . Testi::ALIAS[$sezione]), 301);
        }
        $def = Testi::sezione($sezione);
        if ($def === null) {
            return Response::html($this->page('non-trovata', ['titolo' => 'Sezione non trovata']), 404);
        }
        $chiavi  = $this->strings($def['radici']);
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

        // L'anteprima: la pagina del sito in cui questi testi si vedono.
        $anteprima = [];
        foreach ($lingue as $lingua) {
            if ($def['pagina'] === 'room') {
                $camere = $this->app->rooms()->all();
                $prima  = null;
                foreach ($camere as $c) {
                    if (!empty($c['photographed'])) {
                        $prima = $c;
                        break;
                    }
                }
                $prima ??= $camere[0] ?? null;
                $anteprima[$lingua] = $prima !== null ? RoomPresenter::href($prima, $lingua) : url('rooms', [], [], $lingua);
            } else {
                $anteprima[$lingua] = url($def['pagina'], [], [], $lingua);
            }
        }

        $dati = [
            'titolo' => 'Testi — ' . $def['nome'], 'sezione' => $def['chiave'], 'definizione' => $def,
            'chiavi' => $chiavi, 'base' => $base, 'modificati' => $modificati, 'anteprima' => $anteprima,
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
                if (!array_key_exists($chiave, (array) ($inviati[$lingua] ?? []))) {
                    continue;   // un campo che il modulo non ha mandato resta com'è
                }
                $testo = trim(preg_replace("/\r\n?/", "\n", (string) ($inviati[$lingua][$chiave] ?? '')) ?? '');
                $originale = $base[$lingua][$chiave];
                $dati['modificati'][$lingua][$chiave] = $testo === '' ? null : $testo;
                if ($testo === '' || $testo === $originale) {
                    unset($salvati[$chiave]);
                    continue;
                }
                if (mb_strlen($testo) > 5000) {
                    $errori[] = strtoupper($lingua) . ' «' . Testi::nome($chiave) . '»: al massimo 5000 caratteri.';
                }
                // I segnaposto — :nights, :count — li riempie il sito: se
                // spariscono, la frase perde il numero.
                preg_match_all('/:[a-z_]+/', $originale, $m);
                foreach (array_unique($m[0]) as $segnaposto) {
                    if (!str_contains($testo, $segnaposto)) {
                        $errori[] = strtoupper($lingua) . ' «' . Testi::nome($chiave) . '»: deve contenere ancora '
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

        return Response::redirect($this->url('testi/' . $def['chiave']), 303);
    }

    /**
     * Le chiavi di testo di una o più parti del file di lingua: solo le
     * frasi, non gli elenchi (mesi, giorni, forme dei letti), che sono parte
     * del funzionamento.
     *
     * @param string|list<string> $radici
     * @return list<string>
     */
    private function strings(string|array $radici): array
    {
        $catalogo = $this->app->translator()->baseCatalogue('it');
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
        foreach ((array) $radici as $radice) {
            if (is_array($catalogo[$radice] ?? null)) {
                $cammina($catalogo[$radice], $radice);
            }
        }

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

    // =============================================================== accesso al sito

    /** Il sito chiuso da un codice: acceso o spento, e quale codice. */
    private function siteAccess(Request $request): Response
    {
        $accesso = $this->app->accesso();
        $dati = static fn (array $extra = []): array => $extra + [
            'titolo'        => 'Accesso al sito',
            'sottotitolo'   => 'Il sito chiuso da un codice, finché è in anteprima.',
            'attivo'        => $accesso->attivo(),
            'codice'        => $accesso->codice(),
            'spentoDalFile' => $accesso->spentoDalFile(),
        ];
        if (!$request->isPost()) {
            return Response::html($this->page('accesso-sito', $dati()));
        }

        $attivo = $this->in($request, 'attivo') === '1';
        $codice = trim($this->in($request, 'codice'));
        $errori = [];
        if ($attivo && $codice === '') {
            $errori[] = 'Per chiudere il sito serve un codice.';
        } elseif ($codice !== '' && (mb_strlen($codice) < 4 || mb_strlen($codice) > 60 || preg_match('/[\x00-\x1F]/', $codice) === 1)) {
            $errori[] = 'Il codice: da 4 a 60 caratteri, su una riga.';
        }
        if ($errori !== []) {
            return Response::html($this->page('accesso-sito', $dati([
                'errori' => $errori, 'attivo' => $attivo, 'codice' => $codice,
            ])), 422);
        }

        $accesso->salva($attivo, $codice !== '' ? $codice : $accesso->codice());
        $this->flash('ok', $attivo
            ? 'Salvato: il sito si apre solo con il codice «' . $codice . '».'
            : 'Salvato: il sito è aperto a tutti.');

        return Response::redirect($this->url('accesso-sito'), 303);
    }

    // =============================================================== posta

    /** Quanti indirizzi si possono scrivere nel pannello. */
    private const DESTINATARI_MAX = 5;

    /**
     * A chi arrivano le richieste. Da qui si cambiano solo gli indirizzi che
     * ricevono; la casella che spedisce e la sua password restano nel .env,
     * e la pagina le mostra senza la password.
     */
    private function mail(Request $request): Response
    {
        $store  = $this->app->store();
        $scelti = array_values(array_filter((array) ($store->read('posta')['destinatari'] ?? []), 'is_string'));
        $dati   = fn (array $extra = []): array => $extra + [
            'titolo'      => 'Ricezione e-mail',
            'sottotitolo' => 'A chi arrivano le richieste di prenotazione e i messaggi del sito.',
            'scelti'      => $scelti,
            'effettivi'   => MailMessage::indirizzi($this->app->destinatari()),
            'dalFile'     => MailMessage::indirizzi((string) $this->app->config('mail.to')),
            'trasporto'   => (string) $this->app->config('mail.transport'),
            'mittente'    => (string) $this->app->config('mail.from.address'),
            'smtpHost'    => (string) $this->app->config('mail.smtp.host'),
            'smtpUtente'  => (string) $this->app->config('mail.smtp.user'),
            'massimo'     => self::DESTINATARI_MAX,
        ];

        if (!$request->isPost()) {
            return Response::html($this->page('posta', $dati()));
        }

        if ($this->in($request, 'azione') === 'prova') {
            return $this->mailTest();
        }

        $scritti = array_slice((array) ($request->post['destinatari'] ?? []), 0, self::DESTINATARI_MAX);
        $nuovi   = [];
        $errori  = [];
        foreach ($scritti as $i => $indirizzo) {
            $indirizzo = trim(is_string($indirizzo) ? $indirizzo : '');
            if ($indirizzo === '') {
                continue;
            }
            if (MailMessage::indirizzi($indirizzo) !== [$indirizzo] || mb_strlen($indirizzo) > 190) {
                $errori[] = 'Indirizzo ' . ((int) $i + 1) . ': «' . mb_substr($indirizzo, 0, 60) . '» non è un indirizzo e-mail valido.';
                continue;
            }
            $nuovi[strtolower($indirizzo)] ??= $indirizzo;
        }
        if ($errori !== []) {
            return Response::html($this->page('posta', $dati([
                'errori' => $errori, 'scelti' => array_map(static fn ($v): string => is_string($v) ? trim($v) : '', $scritti),
            ])), 422);
        }

        $store->write('posta', ['destinatari' => array_values($nuovi)]);
        $this->flash('ok', $nuovi === []
            ? 'Nessun indirizzo nel pannello: le richieste arrivano a quelli del file .env (' . ($this->app->config('mail.to') ?: 'nessuno') . ').'
            : 'Salvato. Da adesso le richieste arrivano a: ' . implode(', ', $nuovi) . '. Manda un messaggio di prova per controllare.');

        return Response::redirect($this->url('posta'), 303);
    }

    /** Un messaggio di prova ai destinatari di adesso, al massimo uno al minuto. */
    private function mailTest(): Response
    {
        $ultima = (int) ($_SESSION['_adv_prova_posta'] ?? 0);
        if (time() - $ultima < 60) {
            $this->flash('errore', 'Hai appena mandato un messaggio di prova: aspetta un minuto prima del prossimo.');

            return Response::redirect($this->url('posta'), 303);
        }
        $_SESSION['_adv_prova_posta'] = time();

        $destinatari = $this->app->destinatari();
        $mailer = $this->app->mailer();
        $partito = $mailer->send(new MailMessage(
            to:          $destinatari,
            subject:     '[Arco del Vento] Messaggio di prova',
            body:        "Questo è un messaggio di prova mandato dall'area riservata del sito.\n\n"
                       . "Se lo stai leggendo, le richieste di prenotazione e i messaggi del modulo contatti "
                       . "arrivano a questo indirizzo.\n\nMandato il " . date('d/m/Y \a\l\l\e H:i') . '.',
            fromAddress: (string) $this->app->config('mail.from.address'),
            fromName:    (string) $this->app->config('mail.from.name'),
        ));

        $elenco = implode(', ', MailMessage::indirizzi($destinatari)) ?: 'nessuno';
        if (!$partito) {
            $this->flash('errore', 'Il messaggio non è partito. Controlla nel file .env la casella che spedisce: '
                . 'MAIL_SMTP_HOST, MAIL_SMTP_USER e MAIL_SMTP_PASSWORD, e che MAIL_FROM_ADDRESS sia lo stesso indirizzo dell\'utente.');
        } elseif ($mailer->isPretend()) {
            $this->flash('ok', 'Messaggio di prova per ' . $elenco . ' scritto in storage/mail/, non spedito: nel .env c\'è '
                . 'MAIL_TRANSPORT=' . ((string) $this->app->config('mail.transport') ?: 'log') . ', il modo della copia di prova.');
        } else {
            $this->flash('ok', 'Messaggio di prova spedito a ' . $elenco . '. Se non arriva entro qualche minuto, guarda nella posta indesiderata.');
        }

        return Response::redirect($this->url('posta'), 303);
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
            'inquadrabile' => $media->inquadrabile($chiave), 'inquadratura' => $media->inquadratura($chiave),
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
                // Una foto nuova riparte dal centro: il punto scelto per la
                // vecchia non ha senso sulla nuova.
                if ($media->inquadrabile($chiave)) {
                    $media->salvaInquadratura($chiave, null);
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

            case 'inquadratura':
                if (!$media->inquadrabile($chiave)) {
                    return Response::redirect($this->url($ritorno), 303);
                }
                if ($this->in($request, 'centra') === '1') {
                    $media->salvaInquadratura($chiave, null);
                    $this->flash('ok', 'Inquadratura riportata al centro.');
                } else {
                    $media->salvaInquadratura($chiave, (array) ($request->post['q'] ?? []));
                    $this->flash('ok', 'Inquadratura salvata: è già sul sito.');
                }

                return Response::redirect($this->url($ritorno) . '#inquadratura', 303);

            case 'ripristina':
                if ($media->inquadrabile($chiave)) {
                    $media->salvaInquadratura($chiave, null);
                }
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
            'super'  => $utente !== null && $this->app->auth()->isSuper(),
            'nuove'  => $utente !== null ? $this->app->inbox()->countNew() : 0,
            'daCompletare' => $utente !== null ? count($this->mancanti()) + count($this->camereIncomplete()) : 0,
            'errori' => [],
            'valori' => [],
            'lingue' => $this->form->languages(),
            'vista'  => $vista,
            'tema'   => $this->tema(),
            'percorso' => $this->percorso,
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
