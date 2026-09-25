<?php

declare(strict_types=1);

namespace ArcoDelVento\Media;

use ArcoDelVento\Storage\JsonStore;

/**
 * Le immagini del sito che si possono sostituire dall'area riservata.
 *
 * Ogni immagine è un «posto» con un nome — marchio.logotipo, home.foto-2,
 * camera.camera-03 — e un'immagine predefinita, quella che sta nel pacchetto.
 * Caricarne una nuova non tocca la predefinita: la nuova va in
 * public/assets/media/ e in storage/data/immagini.json si scrive quale posto
 * la usa. «Ripristina l'originale» toglie la riga e cancella i file.
 *
 * Il pacchetto non sa niente di queste sostituzioni: stanno sul server, come
 * le altre modifiche fatte dall'area riservata. Per questo i file vanno in
 * una cartella che il pacchetto non ha, e che caricarne uno nuovo non tocca.
 */
final class Immagini
{
    public const ARCHIVIO = 'immagini';
    public const CARTELLA = 'media';

    /** @var array<string, array<string,mixed>>|null */
    private ?array $registrate = null;

    /**
     * @param string $pubblica la cartella public/ del sito
     * @param list<array<string,mixed>> $camere le camere come stanno nei contenuti
     */
    public function __construct(
        private readonly JsonStore $store,
        private readonly string $pubblica,
        private readonly array $camere,
    ) {
    }

    // ============================================================ i posti

    /**
     * Tutti i posti, nell'ordine in cui li mostra il pannello.
     *
     * @return array<string, array<string,mixed>>
     */
    public function posti(): array
    {
        $posti = [
            'marchio.rosa' => [
                'gruppo' => 'Marchio', 'nome' => 'La rosa dei venti',
                'dove' => 'Accanto al logotipo in testata, nel menu e nel piè di pagina; è anche l\'icona del sito nel browser.',
                'tipo' => 'icona', 'predefinita' => 'img/logo/icona', 'lato' => 256,
                'consiglio' => 'Quadrata, PNG con lo sfondo trasparente, almeno 512 × 512 px.',
            ],
            'marchio.logotipo' => [
                'gruppo' => 'Marchio', 'nome' => 'Logotipo',
                'dove' => 'Al centro della testata e nel menu, su fondo chiaro.',
                'tipo' => 'logo', 'predefinita' => 'img/logo/logotipo-mattone.svg', 'lato' => 60, 'sfondo' => 'chiaro',
                'dimensioni' => [111, 66], 'altezza' => 200,
                'consiglio' => 'PNG con lo sfondo trasparente, almeno 600 px di larghezza, scritta scura.',
            ],
            'marchio.logotipo-chiaro' => [
                'gruppo' => 'Marchio', 'nome' => 'Logotipo chiaro',
                'dove' => 'Nel piè di pagina, sul fondo color mattone.',
                'tipo' => 'logo', 'predefinita' => 'img/logo/logotipo-avorio.svg', 'lato' => 60, 'sfondo' => 'scuro',
                'dimensioni' => [185, 110], 'altezza' => 260,
                'consiglio' => 'PNG con lo sfondo trasparente, almeno 600 px di larghezza, scritta chiara.',
            ],
        ];

        $slide = [
            1 => ['img/foto/valle-panorama-16x9', 'home.slides.valle', 'home.position.frame_alt'],
            2 => ['img/foto/vicolo-campanile-4x3', 'home.slides.vicolo', 'home.hero.image_alt'],
            3 => ['img/foto/basilica-tramonto-3x4', 'home.slides.basilica', 'home.walk.image_alt'],
            4 => ['img/casa/casa-corridoio-4x3', 'home.slides.corridoio', 'rooms.corridor_alt'],
            5 => [null, 'home.slides.camera', null],
        ];
        foreach ($slide as $n => [$predefinita, $didascalia, $alt]) {
            $posti['home.foto-' . $n] = [
                'gruppo' => 'Home — le fotografie che si alternano', 'nome' => 'Fotografia ' . $n,
                'dove' => $n === 5
                    ? 'In apertura della home, quinta. Se non la cambi mostra una camera con la vista su San Rufino.'
                    : 'In apertura della home, a tutta larghezza.',
                'tipo' => 'foto', 'rapporti' => ['16x9'], 'predefinita' => $predefinita ?? $this->cameraConVista(),
                'lato' => 900, 'didascalia' => $didascalia, 'alt' => $alt,
                'consiglio' => 'Orizzontale, almeno 1600 px di larghezza. Il sito la taglia in 16:9 tenendo il centro.',
            ];
        }

        $pagine = [
            'pagine.centro'    => ['Il centro storico', 'Home, accanto ai luoghi da vedere a piedi.', '3x4', 'img/foto/vicolo-campanile-3x4', 'home.hero.image_alt', null],
            'pagine.corridoio' => ['Il corridoio con la rosa dei venti', 'La struttura e l\'elenco delle camere.', '3x4', 'img/casa/casa-corridoio-3x4', 'rooms.corridor_alt', 'rooms.corridor_caption'],
            'pagine.vicolo'    => ['Un vicolo del centro', 'La struttura, accanto alle camere.', '3x4', 'img/foto/vicolo-campanile-3x4', 'assisi.image_alt', 'home.hero.image_credit'],
            'pagine.panorama'  => ['La valle', 'In apertura della pagina Assisi a piedi.', '16x9', 'img/foto/valle-panorama-16x9', 'home.position.frame_alt', null],
            'pagine.basilica'  => ['La Basilica di San Francesco', 'Assisi a piedi, accanto all\'elenco dei luoghi.', '3x4', 'img/foto/basilica-tramonto-3x4', 'home.walk.image_alt', 'home.walk.image_credit'],
            'pagine.anteprima' => ['Anteprima per i social', 'L\'immagine che compare quando qualcuno condivide un link del sito su WhatsApp, Facebook o simili.', '4x3', 'img/foto/vicolo-campanile-4x3', 'home.hero.image_alt', null],
        ];
        foreach ($pagine as $chiave => [$nome, $dove, $rapporto, $predefinita, $alt, $credito]) {
            $posti[$chiave] = [
                'gruppo' => 'Le pagine', 'nome' => $nome, 'dove' => $dove, 'tipo' => 'foto',
                'rapporti' => [$rapporto], 'predefinita' => $predefinita, 'lato' => 700,
                'alt' => $alt, 'credito' => $credito,
                'consiglio' => $rapporto === '3x4'
                    ? 'Verticale, almeno 1400 px di altezza. Il sito la taglia in 3:4.'
                    : ($rapporto === '16x9' ? 'Orizzontale, almeno 1600 px di larghezza. Il sito la taglia in 16:9.' : 'Almeno 1200 px di larghezza. Il sito la taglia in 4:3.'),
            ];
        }

        foreach ($this->camere as $camera) {
            $ref  = (string) $camera['ref'];
            $nome = (string) ($camera['name']['it'] ?? $ref);
            $posti['camera.' . $ref] = [
                'gruppo' => 'Le camere', 'nome' => $nome . ' — fotografia principale', 'camera' => $ref,
                'dove' => 'La scheda della camera, la sua pagina, l\'elenco e la prenotazione.',
                'tipo' => 'foto', 'rapporti' => ['4x3', '3x2', '16x9', '1x1'], 'fuoco' => [0.55, 0.58],
                'predefinita' => (string) ($camera['images']['card']['src'] ?? ''), 'lato' => 700,
                'consiglio' => 'Orizzontale, almeno 1600 px di larghezza, in luce naturale.',
            ];
            foreach ([1, 2] as $n) {
                $posti['camera.' . $ref . '.galleria-' . $n] = [
                    'gruppo' => 'Le camere', 'nome' => $nome . ' — galleria ' . $n, 'camera' => $ref, 'facoltativa' => true,
                    'dove' => 'Nella galleria della pagina della camera. Facoltativa: se manca, non compare niente.',
                    'tipo' => 'foto', 'rapporti' => ['1x1'], 'predefinita' => null, 'lato' => 700,
                    'consiglio' => 'Un dettaglio o un altro punto di vista della camera. Il sito la taglia quadrata.',
                ];
            }
        }

        return $posti;
    }

    /** @return array<string,mixed>|null */
    public function posto(string $chiave): ?array
    {
        return $this->posti()[$chiave] ?? null;
    }

    // ============================================================ le sostituzioni

    /** @return array<string, array<string,mixed>> */
    public function registrate(): array
    {
        return $this->registrate ??= array_filter(
            $this->store->read(self::ARCHIVIO),
            fn ($r, $k): bool => is_array($r) && is_string($k) && $this->fileCiSono($r),
            ARRAY_FILTER_USE_BOTH
        );
    }

    /** @return array<string,mixed>|null */
    public function registrata(string $chiave): ?array
    {
        return $this->registrate()[$chiave] ?? null;
    }

    /**
     * Carica un file in un posto. Se va, il file precedente del posto si
     * cancella; se non va, non cambia niente.
     *
     * @throws ErroreImmagine
     */
    public function carica(string $chiave, string $fileCaricato, string $nomeOriginale): void
    {
        $posto = $this->posto($chiave) ?? throw new ErroreImmagine('Questa immagine non esiste.');
        $cartella = $this->cartella();

        [$im, $trasparente] = Elaboratore::apri($fileCaricato, (int) ($posto['lato'] ?? 400));
        $nome = preg_replace('/[^a-z0-9]+/', '-', strtolower($chiave)) . '-' . bin2hex(random_bytes(3));

        $record = [
            'tipo'     => $posto['tipo'],
            'caricata' => date('c'),
            'nome_originale' => mb_substr(preg_replace('/[^\p{L}\p{N} ._-]/u', '', $nomeOriginale) ?? '', 0, 80),
        ];
        try {
            if ($posto['tipo'] === 'foto') {
                $record['file'] = Elaboratore::foto($im, $cartella, $nome, $posto['rapporti'], $posto['fuoco'] ?? [0.5, 0.5]);
            } elseif ($posto['tipo'] === 'logo') {
                if (!$trasparente) {
                    $record['avviso'] = 'Il file non ha lo sfondo trasparente: sul sito si vedrà il riquadro dello sfondo.';
                }
                $logo = Elaboratore::logo($im, $cartella, $nome, (int) ($posto['altezza'] ?? 200));
                $record['file'] = $logo['file'];
                $record['w'] = $logo['w'];
                $record['h'] = $logo['h'];
            } else {
                if (!$trasparente) {
                    $record['avviso'] = 'Il file non ha lo sfondo trasparente: la rosa comparirà dentro un quadrato.';
                }
                $record['file'] = Elaboratore::icona($im, $cartella, $nome);
            }
        } catch (\Throwable $e) {
            foreach (glob($cartella . '/' . $nome . '*') ?: [] as $f) {
                @unlink($f);
            }
            throw $e instanceof ErroreImmagine ? $e : new ErroreImmagine('Non sono riuscito a preparare l\'immagine: ' . $e->getMessage());
        }
        $record['base'] = self::CARTELLA . '/' . $nome;

        $precedente = null;
        $this->store->update(self::ARCHIVIO, static function (array $tutte) use ($chiave, $record, &$precedente): array {
            $precedente = $tutte[$chiave] ?? null;
            // Il testo alternativo e il credito restano se c'erano: descrivono
            // il posto, e chi sostituisce la foto li aggiorna accanto.
            if (is_array($precedente)) {
                foreach (['alt', 'credito'] as $campo) {
                    if (isset($precedente[$campo])) {
                        $record[$campo] = $precedente[$campo];
                    }
                }
            }
            $tutte[$chiave] = $record;

            return $tutte;
        }, false);
        if (is_array($precedente)) {
            $this->cancellaFile($precedente);
        }
        $this->registrate = null;
    }

    /** Aggiorna il testo alternativo e il credito di un'immagine sostituita. */
    public function aggiornaTesti(string $chiave, array $alt, array $credito): void
    {
        $this->store->update(self::ARCHIVIO, static function (array $tutte) use ($chiave, $alt, $credito): array {
            if (isset($tutte[$chiave]) && is_array($tutte[$chiave])) {
                $tutte[$chiave]['alt']     = array_filter($alt, static fn ($v) => $v !== '');
                $tutte[$chiave]['credito'] = array_filter($credito, static fn ($v) => $v !== '');
            }

            return $tutte;
        }, false);
        $this->registrate = null;
    }

    /** Torna all'immagine originale e cancella i file caricati. */
    public function ripristina(string $chiave): bool
    {
        $vecchio = null;
        $this->store->update(self::ARCHIVIO, static function (array $tutte) use ($chiave, &$vecchio): array {
            $vecchio = $tutte[$chiave] ?? null;
            unset($tutte[$chiave]);

            return $tutte;
        }, false);
        $this->registrate = null;
        if (is_array($vecchio)) {
            $this->cancellaFile($vecchio);

            return true;
        }

        return false;
    }

    // ============================================================ per le pagine

    /**
     * Che cosa mostrare in un posto: l'immagine caricata o quella originale.
     *
     * Per le foto `src` è il percorso senza estensione, come lo vuole il
     * componente picture; per i loghi è il file; per l'icona è il prefisso a
     * cui si aggiungono «-72.webp», «-192.png» e così via.
     *
     * @return array{src: ?string, sostituita: bool, alt: ?string, credito: ?string, w: ?int, h: ?int, formati: list<string>}
     */
    public function risolvi(string $chiave, string $lingua = 'it'): array
    {
        $posto = $this->posto($chiave);
        $r     = $this->registrata($chiave);
        if ($r === null) {
            $src = $posto['predefinita'] ?? null;
            return [
                'src' => $src, 'sostituita' => false, 'alt' => null, 'credito' => null,
                'w' => $posto['dimensioni'][0] ?? null, 'h' => $posto['dimensioni'][1] ?? null,
                'formati' => $posto['rapporti'] ?? [],
            ];
        }

        $base = (string) $r['base'];
        $src  = match ($r['tipo']) {
            'logo'  => $base . (in_array(basename($base) . '.webp', (array) $r['file'], true) ? '.webp' : '.png'),
            'foto'  => $base . '-' . (($posto['rapporti'] ?? ['4x3'])[0]),
            default => $base,
        };

        return [
            'src'        => $src,
            'sostituita' => true,
            'alt'        => self::inLingua($r['alt'] ?? null, $lingua),
            'credito'    => self::inLingua($r['credito'] ?? null, $lingua),
            'w'          => isset($r['w']) ? (int) $r['w'] : null,
            'h'          => isset($r['h']) ? (int) $r['h'] : null,
            'formati'    => $posto['rapporti'] ?? [],
        ];
    }

    /**
     * Le camere con le fotografie caricate al posto di quelle del pacchetto.
     *
     * @param list<array<string,mixed>> $camere
     * @return list<array<string,mixed>>
     */
    public function applicaCamere(array $camere): array
    {
        $registrate = $this->registrate();
        if ($registrate === []) {
            return $camere;
        }
        foreach ($camere as &$camera) {
            $ref = (string) ($camera['ref'] ?? '');
            $principale = $registrate['camera.' . $ref] ?? null;
            if ($principale !== null) {
                $b = (string) $principale['base'];
                $camera['images'] = [
                    'card'    => ['src' => $b . '-4x3', 'ratio' => '4/3'],
                    'list'    => ['src' => $b . '-3x2', 'ratio' => '3/2'],
                    'hero'    => ['src' => $b . '-16x9', 'ratio' => '16/9'],
                    'gallery' => [['src' => $b . '-1x1', 'ratio' => '1/1']],
                ];
                $camera['photographed'] = true;
            }
            foreach ([1, 2] as $n) {
                $extra = $registrate['camera.' . $ref . '.galleria-' . $n] ?? null;
                if ($extra !== null) {
                    $camera['images']['gallery'][] = ['src' => (string) $extra['base'] . '-1x1', 'ratio' => '1/1'];
                }
            }
        }
        unset($camera);

        return $camere;
    }

    // ============================================================ interni

    /** La cartella dei file caricati, creata la prima volta e chiusa agli script. */
    public function cartella(): string
    {
        $cartella = $this->pubblica . '/assets/' . self::CARTELLA;
        if (!is_dir($cartella) && !@mkdir($cartella, 0755, true) && !is_dir($cartella)) {
            throw new ErroreImmagine('Non riesco a creare la cartella public/assets/media: controlla via FTP che public/assets sia scrivibile (permessi 755).');
        }
        if (!is_writable($cartella)) {
            throw new ErroreImmagine('La cartella public/assets/media non è scrivibile: via FTP dalle i permessi 755 (o 775).');
        }
        $guardia = $cartella . '/.htaccess';
        if (!is_file($guardia)) {
            // Qui dentro ci sono solo immagini scritte dal sito: nessuno script
            // deve poter girare, qualunque cosa ci finisca per sbaglio.
            @file_put_contents($guardia, "Options -Indexes -ExecCGI\n"
                . "<FilesMatch \"\\.(?i:php\\d?|phtml|phar|pl|py|cgi|sh|html?|svg)$\">\n    Require all denied\n</FilesMatch>\n"
                . "<IfModule mod_php.c>\n    php_flag engine off\n</IfModule>\n");
        }

        return $cartella;
    }

    public static function cartellaScrivibile(string $pubblica): bool
    {
        $cartella = $pubblica . '/assets/' . self::CARTELLA;

        return is_dir($cartella) ? is_writable($cartella) : is_writable($pubblica . '/assets');
    }

    /** @param array<string,mixed> $r */
    private function fileCiSono(array $r): bool
    {
        $base = (string) ($r['base'] ?? '');
        if (!str_starts_with($base, self::CARTELLA . '/') || !is_array($r['file'] ?? null) || $r['file'] === []) {
            return false;
        }

        return is_file($this->pubblica . '/assets/' . self::CARTELLA . '/' . basename((string) $r['file'][0]));
    }

    /** @param array<string,mixed> $r */
    private function cancellaFile(array $r): void
    {
        $cartella = $this->pubblica . '/assets/' . self::CARTELLA;
        foreach ((array) ($r['file'] ?? []) as $file) {
            $nome = basename((string) $file);
            if ($nome !== '' && $nome[0] !== '.') {
                @unlink($cartella . '/' . $nome);
            }
        }
    }

    private static function inLingua(mixed $valore, string $lingua): ?string
    {
        if (!is_array($valore)) {
            return null;
        }
        $v = $valore[$lingua] ?? null;

        return is_string($v) && trim($v) !== '' ? trim($v) : null;
    }

    /** La prima camera fotografata che guarda su San Rufino: la quinta foto dell'apertura. */
    private function cameraConVista(): ?string
    {
        foreach ($this->camere as $c) {
            if (!empty($c['photographed']) && !empty($c['view'])) {
                return (string) ($c['images']['hero']['src'] ?? '');
            }
        }

        return null;
    }
}
