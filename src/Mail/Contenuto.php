<?php

declare(strict_types=1);

namespace ArcoDelVento\Mail;

use ArcoDelVento\App;

/**
 * Che cosa dice un'e-mail, una volta sola: da qui escono il testo semplice
 * e l'HTML con la grafica del sito (views/mail/messaggio.php).
 *
 * Si compone a blocchi, nell'ordine in cui si leggono:
 *
 *   (new Contenuto('it'))
 *       ->intestazione('Prenotazione confermata', 'Camera 03 · dal 5 al 9 dicembre')
 *       ->saluto('Buongiorno Maria,')
 *       ->paragrafo('…')
 *       ->dati('La prenotazione', [['Arrivo', '5 dicembre'], …])
 *       ->pulsanti([['Aggiungi a Google Calendar', 'https://…', true]]);
 *
 * Il testo semplice dice le stesse cose, con i link scritti per intero.
 */
final class Contenuto
{
    public string $occhiello = '';
    public string $titolo = '';
    public string $anteprima = '';
    public string $saluto = '';
    public string $firma = '';

    /** @var list<array<string,mixed>> */
    public array $blocchi = [];

    /** @var list<string> */
    public array $piede = [];

    public function __construct(public readonly string $lingua = 'it')
    {
    }

    /** L'occhiello sopra il titolo, il titolo, e il testo d'anteprima della casella. */
    public function intestazione(string $titolo, string $anteprima = '', string $occhiello = ''): self
    {
        $this->titolo = $titolo;
        $this->anteprima = $anteprima;
        $this->occhiello = $occhiello;

        return $this;
    }

    public function saluto(string $saluto): self
    {
        $this->saluto = $saluto;

        return $this;
    }

    /** Un riquadro colorato in cima: «attenzione», «prova» o «ok». */
    public function avviso(string $testo, string $livello = 'attenzione'): self
    {
        $this->blocchi[] = ['tipo' => 'avviso', 'testo' => $testo, 'livello' => $livello];

        return $this;
    }

    public function paragrafo(string $testo): self
    {
        if (trim($testo) !== '') {
            $this->blocchi[] = ['tipo' => 'paragrafo', 'testo' => $testo];
        }

        return $this;
    }

    /**
     * Una tabella di dati: etichetta e valore. Le righe con il valore vuoto
     * non si scrivono.
     *
     * @param list<array{0: string, 1: string}> $righe
     */
    public function dati(string $titolo, array $righe): self
    {
        $righe = array_values(array_filter($righe, static fn (array $r): bool => trim((string) $r[1]) !== ''));
        if ($righe !== []) {
            $this->blocchi[] = ['tipo' => 'dati', 'titolo' => $titolo, 'righe' => $righe];
        }

        return $this;
    }

    /**
     * Uno o più pulsanti: testo, indirizzo, e se è quello principale.
     *
     * @param list<array{0: string, 1: string, 2?: bool}> $pulsanti
     */
    public function pulsanti(array $pulsanti, string $titolo = ''): self
    {
        $pulsanti = array_values(array_filter($pulsanti, static fn (array $p): bool => trim((string) $p[1]) !== ''));
        if ($pulsanti !== []) {
            $this->blocchi[] = ['tipo' => 'pulsanti', 'titolo' => $titolo, 'pulsanti' => $pulsanti];
        }

        return $this;
    }

    /** Un testo piccolo, per le cose da sapere ma non da leggere per prime. */
    public function nota(string $testo): self
    {
        if (trim($testo) !== '') {
            $this->blocchi[] = ['tipo' => 'nota', 'testo' => $testo];
        }

        return $this;
    }

    public function firma(string $firma): self
    {
        $this->firma = $firma;

        return $this;
    }

    /** @param list<string> $righe le righe del piè di pagina: indirizzo, telefono, sito */
    public function piede(array $righe): self
    {
        $this->piede = array_values(array_filter($righe, static fn (string $r): bool => trim($r) !== ''));

        return $this;
    }

    /** Il testo semplice. */
    public function testo(): string
    {
        $out = [];
        if ($this->saluto !== '') {
            $out[] = $this->saluto;
            $out[] = '';
        } elseif ($this->titolo !== '') {
            $out[] = $this->titolo;
            $out[] = '';
        }
        foreach ($this->blocchi as $b) {
            switch ($b['tipo']) {
                case 'avviso':
                    $out[] = mb_strtoupper($b['livello'] === 'prova' ? 'Prova' : ($b['livello'] === 'ok' ? 'Ok' : 'Attenzione')) . ': ' . $b['testo'];
                    break;
                case 'paragrafo':
                case 'nota':
                    $out[] = $b['testo'];
                    break;
                case 'dati':
                    if ($b['titolo'] !== '') {
                        $out[] = $b['titolo'];
                    }
                    $largo = max(array_map(static fn (array $r): int => mb_strlen((string) $r[0]), $b['righe'])) + 2;
                    foreach ($b['righe'] as [$etichetta, $valore]) {
                        $prima = true;
                        foreach (explode("\n", (string) $valore) as $riga) {
                            $out[] = ($prima ? self::pad($etichetta . ':', $largo) : str_repeat(' ', $largo)) . $riga;
                            $prima = false;
                        }
                    }
                    break;
                case 'pulsanti':
                    if ($b['titolo'] !== '') {
                        $out[] = $b['titolo'];
                    }
                    foreach ($b['pulsanti'] as $p) {
                        $out[] = $p[0] . ': ' . $p[1];
                    }
                    break;
            }
            $out[] = '';
        }
        if ($this->firma !== '') {
            $out[] = $this->firma;
        }
        if ($this->piede !== []) {
            $out[] = '';
            $out[] = '--';
            array_push($out, ...$this->piede);
        }

        return rtrim(implode("\n", $out)) . "\n";
    }

    /** L'HTML, con la grafica del sito: logo, colori e caratteri. */
    public function html(App $app): string
    {
        $vista = $app->config('root') . '/views/mail/messaggio.php';
        $c = $this;
        // Indirizzi completi, senza le funzioni delle pagine: un'e-mail può partire anche da uno script.
        $base = \ArcoDelVento\I18n\Routes::base() . '/assets/';
        $file = static fn (string $f): string => $app->indirizzoCompleto($base . $f) . (is_file($app->config('root') . '/public/assets/' . $f) ? '?v=' . filemtime($app->config('root') . '/public/assets/' . $f) : '');
        $logo = $file('img/logo/logotipo-email.png');
        $font = static fn (string $f): string => $file('fonts/' . $f);
        $sito = $app->indirizzoCompleto(\ArcoDelVento\I18n\Routes::base() . '/' . $this->lingua . '/');

        ob_start();
        try {
            (static function () use ($vista, $c, $logo, $font, $sito): void {
                require $vista;
            })();
        } finally {
            $html = (string) ob_get_clean();
        }

        return $html;
    }

    private static function pad(string $s, int $largo): string
    {
        return $s . str_repeat(' ', max(1, $largo - mb_strlen($s)));
    }
}
