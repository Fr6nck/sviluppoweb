<?php

declare(strict_types=1);

namespace ArcoDelVento\Payment;

use ArcoDelVento\Admin\Inbox;

/**
 * Il pagamento di una prenotazione, dalla richiesta alla conferma.
 *
 * La prenotazione nasce nell'archivio delle richieste (Inbox) con il suo
 * riferimento e un blocco «pagamento»; qui si apre la pagina di SumUp, si
 * chiede a SumUp com'è andata e, la prima volta che risulta pagata, si
 * avvisano struttura e ospite. Una volta sola, anche se la pagina di
 * ritorno e la notifica di SumUp arrivano insieme.
 *
 *     in attesa → pagato
 *               → non riuscito | scaduto  (si può riprovare)
 *               → da controllare          (SumUp dice pagato, ma l'importo non torna)
 */
final class Pagamenti
{
    public const IN_ATTESA   = 'in attesa';
    public const PAGATO      = 'pagato';
    public const NON_RIUSCITO = 'non riuscito';
    public const SCADUTO     = 'scaduto';
    public const DA_CONTROLLARE = 'da controllare';

    /** Quante pagine di pagamento si aprono al massimo per una prenotazione. */
    private const TENTATIVI_MAX = 5;

    /**
     * @param \Closure(string $riferimento, string $lingua): string $ritorno  dove torna l'ospite (indirizzo completo)
     * @param string $notifiche indirizzo completo a cui SumUp avvisa i cambi di stato
     * @param \Closure(array<string,mixed> $voce): void $avvisa  le e-mail di prenotazione pagata
     */
    public function __construct(
        private readonly SumUp $sumup,
        private readonly Inbox $inbox,
        private readonly \Closure $ritorno,
        private readonly string $notifiche,
        private readonly \Closure $avvisa,
    ) {
    }

    /**
     * Apre una pagina di pagamento per la prenotazione e ne restituisce
     * l'indirizzo. Se risulta già pagata, restituisce null.
     *
     * @throws ErrorePagamento
     */
    public function apri(string $riferimento): ?string
    {
        $voce = $this->inbox->find($riferimento) ?? throw new ErrorePagamento('Prenotazione ' . $riferimento . ' non trovata.');
        // Prima di aprirne un'altra, si guarda se una pagina precedente è
        // stata pagata nel frattempo: niente doppio addebito.
        if ($this->verifica($riferimento) === self::PAGATO) {
            return null;
        }
        $p = (array) ($voce['dati']['pagamento'] ?? []);
        $n = count((array) ($p['tentativi'] ?? [])) + 1;
        if ($n > self::TENTATIVI_MAX) {
            throw new ErrorePagamento('Troppi tentativi di pagamento per ' . $riferimento . '.');
        }
        $d = (array) $voce['dati'];
        $descrizione = sprintf(
            'Arco del Vento, %s, %s - %s, %d %s',
            (string) ($d['camera'] ?? ''),
            date('d/m/Y', strtotime((string) $d['arrivo']) ?: time()),
            date('d/m/Y', strtotime((string) $d['partenza']) ?: time()),
            (int) ($d['ospiti'] ?? 1),
            (int) ($d['ospiti'] ?? 1) === 1 ? 'ospite' : 'ospiti'
        );

        $pagina = $this->sumup->crea(
            $riferimento . ($n > 1 ? '-' . $n : ''),
            (float) ($p['importo'] ?? 0),
            (string) ($p['valuta'] ?? 'EUR'),
            $descrizione,
            ($this->ritorno)($riferimento, (string) ($d['lingua'] ?? 'it')),
            $this->notifiche,
        );

        $this->inbox->aggiorna($riferimento, static function (array $v) use ($pagina): array {
            $v['dati']['pagamento']['tentativi'][] = [
                'id' => $pagina['id'], 'url' => $pagina['url'], 'creato' => date('c'), 'stato' => 'PENDING',
            ];
            $v['dati']['pagamento']['stato']      = self::IN_ATTESA;
            $v['dati']['pagamento']['aggiornato'] = date('c');

            return $v;
        });

        return $pagina['url'];
    }

    /**
     * Chiede a SumUp com'è andata e aggiorna la prenotazione. Restituisce lo
     * stato del pagamento. Se è appena risultata pagata, manda le e-mail.
     */
    public function verifica(string $riferimento): string
    {
        $voce = $this->inbox->find($riferimento);
        $p = (array) ($voce['dati']['pagamento'] ?? []);
        if ($voce === null || $p === []) {
            return '';
        }
        if (($p['stato'] ?? '') === self::PAGATO) {
            return self::PAGATO;
        }

        // Dal tentativo più recente al primo: di solito è l'ultimo, ma una
        // pagina vecchia rimasta aperta può essere quella pagata.
        $esiti = [];
        foreach (array_reverse((array) ($p['tentativi'] ?? [])) as $t) {
            if (!is_array($t) || ($t['id'] ?? '') === '' || in_array($t['stato'] ?? '', ['FAILED', 'EXPIRED'], true)) {
                continue;
            }
            try {
                $esito = $this->sumup->stato((string) $t['id']);
            } catch (ErrorePagamento $e) {
                error_log('SumUp, verifica di ' . $riferimento . ': ' . $e->getMessage());
                continue;
            }
            $esiti[(string) $t['id']] = $esito;
            if ($esito['stato'] === 'PAID') {
                break;
            }
        }
        if ($esiti === []) {
            return (string) ($p['stato'] ?? self::IN_ATTESA);
        }

        $daAvvisare = false;
        $dopo = $this->inbox->aggiorna($riferimento, static function (array $v) use ($esiti, &$daAvvisare): array {
            $pag = (array) ($v['dati']['pagamento'] ?? []);
            foreach ((array) ($pag['tentativi'] ?? []) as $i => $t) {
                if (isset($esiti[$t['id'] ?? ''])) {
                    $pag['tentativi'][$i]['stato'] = $esiti[$t['id']]['stato'];
                }
            }
            $pagato = null;
            foreach ($esiti as $id => $e) {
                if ($e['stato'] === 'PAID') {
                    $pagato = ['id' => $id] + $e;
                }
            }
            if (($pag['stato'] ?? '') !== self::PAGATO && $pagato !== null) {
                // L'importo pagato deve essere quello chiesto: se non torna,
                // qualcuno deve guardarlo prima di chiamarla una prenotazione.
                $giusto = abs($pagato['importo'] - (float) ($pag['importo'] ?? 0)) < 0.01
                    && strtoupper($pagato['valuta']) === strtoupper((string) ($pag['valuta'] ?? 'EUR'));
                $pag['stato']     = $giusto ? self::PAGATO : self::DA_CONTROLLARE;
                $pag['pagato_il'] = date('c');
                $pag['pagato']    = $pagato['importo'];
                $pag['codice']    = $pagato['codice'];
                $pag['checkout']  = $pagato['id'];
                if (empty($pag['avvisato'])) {
                    $pag['avvisato'] = true;
                    $daAvvisare = true;
                }
            } elseif (!in_array($pag['stato'] ?? '', [self::PAGATO, self::DA_CONTROLLARE], true)) {
                $ultimo = end($pag['tentativi']);
                $pag['stato'] = match ($ultimo['stato'] ?? '') {
                    'FAILED'  => self::NON_RIUSCITO,
                    'EXPIRED' => self::SCADUTO,
                    default   => self::IN_ATTESA,
                };
            }
            $pag['aggiornato'] = date('c');
            $v['dati']['pagamento'] = $pag;

            return $v;
        });

        if ($daAvvisare && $dopo !== null) {
            try {
                ($this->avvisa)($dopo);
            } catch (\Throwable $e) {
                error_log('Prenotazione ' . $riferimento . ' pagata, ma le e-mail non sono partite: ' . $e->getMessage());
            }
        }

        return (string) ($dopo['dati']['pagamento']['stato'] ?? self::IN_ATTESA);
    }

    /**
     * È una prenotazione vera? Una richiesta senza pagamento online sì (come
     * prima); con il pagamento, solo se è pagata. Una pagina di SumUp aperta
     * e abbandonata non è una prenotazione: non conta fra le nuove, negli
     * arrivi o nel valore.
     *
     * @param array<string,mixed> $dati i dati di una richiesta
     */
    public static function conta(array $dati): bool
    {
        $p = $dati['pagamento'] ?? null;

        return !is_array($p) || in_array($p['stato'] ?? '', [self::PAGATO, self::DA_CONTROLLARE], true);
    }

    /**
     * Lo stato del pagamento come lo mostra il pannello.
     *
     * @param array<string,mixed> $dati
     * @return array{testo: string, tono: string}|null null se la richiesta non ha pagamento online
     */
    public static function etichetta(array $dati): ?array
    {
        $p = $dati['pagamento'] ?? null;
        if (!is_array($p)) {
            return null;
        }
        $tentativi = (array) ($p['tentativi'] ?? []);
        $aperta = (string) (end($tentativi)['creato'] ?? '');
        $ancoraAperta = $aperta !== '' && (time() - (strtotime($aperta) ?: 0)) < 35 * 60;

        return match (true) {
            ($p['stato'] ?? '') === self::PAGATO         => ['testo' => 'Pagata', 'tono' => 'ok'],
            ($p['stato'] ?? '') === self::DA_CONTROLLARE => ['testo' => 'Pagamento da controllare', 'tono' => 'avviso'],
            ($p['stato'] ?? '') === self::IN_ATTESA && $ancoraAperta => ['testo' => 'In pagamento', 'tono' => 'neutro'],
            default => ['testo' => 'Non pagata', 'tono' => 'spento'],
        };
    }

    /** SumUp avvisa che un pagamento è cambiato: si verifica, non ci si fida. */
    public function daNotifica(string $checkoutId): void
    {
        $voce = $this->inbox->perPagamento($checkoutId);
        if ($voce !== null) {
            $this->verifica((string) $voce['id']);
        }
    }
}
