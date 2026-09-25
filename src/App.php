<?php

declare(strict_types=1);

namespace ArcoDelVento;

use ArcoDelVento\Booking\BookingService;
use ArcoDelVento\Booking\DemoBookingProvider;
use ArcoDelVento\Booking\BookingProviderInterface;
use ArcoDelVento\Database\Connection;
use ArcoDelVento\I18n\Translator;
use ArcoDelVento\Storage\ContentOverrides;
use ArcoDelVento\Storage\JsonStore;
use ArcoDelVento\Mail\LogMailer;
use ArcoDelVento\Mail\MailerInterface;
use ArcoDelVento\Mail\NativeMailer;
use ArcoDelVento\Mail\SmtpMailer;
use ArcoDelVento\Repository\ArrayRoomRepository;
use ArcoDelVento\Repository\PdoRoomRepository;
use ArcoDelVento\Repository\RoomRepositoryInterface;
use ArcoDelVento\View\View;

/**
 * Il contenitore dell'applicazione.
 *
 * Tiene insieme configurazione, traduzioni, contenuti e servizi, e li
 * costruisce solo quando servono davvero. È deliberatamente piccolo: un
 * sito di otto pagine non ha bisogno di un framework, ma ha bisogno che
 * i pezzi sostituibili — database, prenotazioni, e-mail — si sostituiscano
 * in un punto solo, che è questo.
 */
final class App
{
    private static ?self $instance = null;

    /** @var array<string,mixed> */
    private array $services = [];

    /** @param array<string,mixed> $config */
    private function __construct(private readonly array $config, private readonly Translator $translator)
    {
    }

    /** @param array<string,mixed> $config */
    public static function boot(array $config): self
    {
        \ArcoDelVento\I18n\Routes::setBase((string) ($config['app']['base'] ?? ''));

        // I testi modificati dall'area riservata si sovrappongono a quelli dei
        // file di lingua, chiave per chiave.
        $ritocchi = new ContentOverrides(new JsonStore($config['data']));

        $translator = new Translator(
            $config['content'] . '/lang',
            (string) $config['i18n']['default'],
            (string) $config['i18n']['default'],
            $config['i18n']['available'],
            static fn (string $lingua, array $catalogo): array => $ritocchi->translations($lingua, $catalogo),
        );

        return self::$instance = new self($config, $translator);
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            throw new \RuntimeException('App non avviata: chiamare App::boot() prima.');
        }

        return self::$instance;
    }

    /** Accesso puntato alla configurazione: config('mail.transport'). */
    public function config(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->config;
        }
        $value = $this->config;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public function translator(): Translator
    {
        return $this->translator;
    }

    public function locale(): string
    {
        return $this->translator->locale();
    }

    public function view(): View
    {
        return $this->services['view'] ??= new View((string) $this->config['views']);
    }

    /** L'archivio dell'area riservata, in storage/data. */
    public function store(): JsonStore
    {
        return $this->services['store'] ??= new JsonStore($this->config['data']);
    }

    public function auth(): \ArcoDelVento\Admin\Auth
    {
        return $this->services['auth'] ??= new \ArcoDelVento\Admin\Auth(
            $this->store(),
            (string) $this->config('admin.setup_token', ''),
        );
    }

    /** Le richieste arrivate dal sito, per l'area riservata. */
    public function inbox(): \ArcoDelVento\Admin\Inbox
    {
        return $this->services['inbox'] ??= new \ArcoDelVento\Admin\Inbox($this->store());
    }

    /** Le modifiche dell'area riservata, sovrapposte ai file di content/. */
    /** Le immagini sostituibili dall'area riservata. */
    public function media(): \ArcoDelVento\Media\Immagini
    {
        return $this->services['media'] ??= new \ArcoDelVento\Media\Immagini(
            $this->store(),
            $this->config['root'] . '/public',
            $this->overrides()->rooms($this->baseRooms()),
        );
    }

    public function overrides(): ContentOverrides
    {
        return $this->services['overrides'] ??= new ContentOverrides($this->store());
    }

    /** Le impostazioni del sito: indirizzo, contatti, orari — con le modifiche del pannello. */
    public function settings(): array
    {
        return $this->services['settings'] ??= $this->overrides()->settings(
            require $this->config['content'] . '/settings.php'
        );
    }

    /** Le impostazioni come stanno nel file, senza le modifiche del pannello. */
    public function baseSettings(): array
    {
        return require $this->config['content'] . '/settings.php';
    }

    /** @return list<array<string,mixed>> le camere come stanno nel file */
    public function baseRooms(): array
    {
        return require $this->config['content'] . '/rooms.php';
    }

    /** @return list<array<string,mixed>> le domande frequenti, con le modifiche del pannello */
    public function faq(): array
    {
        return $this->services['faq'] ??= $this->overrides()->faq(require $this->config['content'] . '/faq.php');
    }

    public function rooms(): RoomRepositoryInterface
    {
        return $this->services['rooms'] ??= $this->makeRoomRepository();
    }

    private function makeRoomRepository(): RoomRepositoryInterface
    {
        // Finché DB_DSN è vuoto il sito gira sui contenuti in content/rooms.php.
        // Compilato il DSN, le stesse camere arrivano da MySQL senza che una
        // sola vista debba cambiare: il contratto è RoomRepositoryInterface.
        $connection = $this->database();
        if ($connection !== null) {
            return new PdoRoomRepository($connection);
        }

        return new ArrayRoomRepository($this->media()->applicaCamere($this->overrides()->rooms($this->baseRooms())));
    }

    public function database(): ?\PDO
    {
        if (array_key_exists('pdo', $this->services)) {
            return $this->services['pdo'];
        }

        return $this->services['pdo'] = Connection::open(
            (string) $this->config('database.dsn'),
            (string) $this->config('database.user'),
            (string) $this->config('database.password'),
        );
    }

    public function bookingProvider(): BookingProviderInterface
    {
        return $this->services['booking.provider'] ??= match ((string) $this->config('booking.provider')) {
            // Un provider reale si aggiunge qui, come terzo caso, e implementa
            // BookingProviderInterface. Il resto del sito non se ne accorge.
            default => new DemoBookingProvider(
                $this->rooms(),
                $this->minNights(),
                (string) $this->config('booking.currency'),
            ),
        };
    }

    /**
     * Il soggiorno minimo in notti.
     *
     * Comanda content/settings.php, perché è una regola della casa. BOOKING_MIN_NIGHTS
     * esiste solo per il giorno in cui un gestionale ne imponga un'altra: finché
     * resta vuota non se ne accorge nessuno. In mancanza di tutto è 1 — non
     * imporre un minimo è l'unico ripiego che non inventa una regola.
     */
    public function minNights(): int
    {
        $env = $this->config('booking.min_nights');
        if ($env !== null && (int) $env > 0) {
            return (int) $env;
        }

        return max(1, (int) ($this->settings()['stay']['min_nights']['default'] ?? 1));
    }

    public function booking(): BookingService
    {
        return $this->services['booking'] ??= new BookingService(
            $this->bookingProvider(),
            $this->rooms(),
            $this->minNights(),
            // La regola del sabato è un dato della casa, non una scelta di
            // configurazione: sta in content/settings.php insieme agli altri.
            (int) ($this->settings()['stay']['min_nights']['saturday'] ?? 1),
        );
    }

    public function mailer(): MailerInterface
    {
        return $this->services['mailer'] ??= match ((string) $this->config('mail.transport')) {
            'mail'  => new NativeMailer(),
            'smtp'  => new SmtpMailer($this->config('mail.smtp')),
            default => new LogMailer($this->config['storage'] . '/mail'),
        };
    }
}
