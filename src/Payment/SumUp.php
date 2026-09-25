<?php

declare(strict_types=1);

namespace ArcoDelVento\Payment;

/**
 * Le due chiamate a SumUp che servono al sito.
 *
 *     POST /v0.1/checkouts        crea il pagamento, con la pagina di SumUp
 *     GET  /v0.1/checkouts/{id}   com'è andato
 *
 * Documentazione: https://developer.sumup.com/online-payments/checkouts/hosted-checkout
 *
 * L'ospite paga su una pagina di SumUp (Hosted Checkout): i dati della carta
 * non passano mai dal sito. Quello che torna dal browser non conta niente:
 * lo stato di un pagamento si chiede sempre a SumUp, da qui, con la chiave.
 */
final class SumUp
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $merchantCode,
        private readonly string $apiUrl = 'https://api.sumup.com',
    ) {
    }

    /**
     * Crea un pagamento e restituisce il suo identificativo e la pagina dove
     * mandare l'ospite. La pagina resta valida trenta minuti.
     *
     * @param string $returnUrl   dove SumUp avvisa il sito che lo stato è cambiato
     * @param string $redirectUrl dove torna l'ospite dopo aver pagato
     * @return array{id: string, url: string}
     * @throws ErrorePagamento
     */
    public function crea(
        string $riferimento,
        float $importo,
        string $valuta,
        string $descrizione,
        string $redirectUrl,
        string $returnUrl,
    ): array {
        $risposta = $this->chiama('POST', '/v0.1/checkouts', [
            'checkout_reference' => substr($riferimento, 0, 64),
            'amount'             => round($importo, 2),
            'currency'           => $valuta,
            'merchant_code'      => $this->merchantCode,
            'description'        => mb_substr($descrizione, 0, 250),
            'redirect_url'       => $redirectUrl,
            'return_url'         => $returnUrl,
            'hosted_checkout'    => ['enabled' => true],
        ]);

        $id  = (string) ($risposta['id'] ?? '');
        $url = (string) ($risposta['hosted_checkout_url'] ?? '');
        if ($id === '' || $url === '') {
            throw new ErrorePagamento('SumUp non ha restituito la pagina di pagamento.');
        }
        if (!$this->paginaDiSumUp($url)) {
            // Il sito manda l'ospite solo su una pagina di SumUp, mai altrove:
            // una risposta che indica un altro indirizzo non si segue.
            throw new ErrorePagamento('SumUp ha indicato una pagina di pagamento su un indirizzo inatteso: ' . parse_url($url, PHP_URL_HOST));
        }

        return ['id' => $id, 'url' => $url];
    }

    /**
     * Com'è andato un pagamento, secondo SumUp.
     *
     * @return array{stato: string, importo: float, valuta: string, riferimento: string, codice: string}
     *         stato: PENDING | PAID | FAILED | EXPIRED
     * @throws ErrorePagamento
     */
    public function stato(string $id): array
    {
        if (preg_match('/^[A-Za-z0-9-]{8,64}$/', $id) !== 1) {
            throw new ErrorePagamento('Identificativo di pagamento non valido.');
        }
        $r = $this->chiama('GET', '/v0.1/checkouts/' . rawurlencode($id));

        return [
            'stato'       => strtoupper((string) ($r['status'] ?? '')),
            'importo'     => (float) ($r['amount'] ?? 0),
            'valuta'      => (string) ($r['currency'] ?? ''),
            'riferimento' => (string) ($r['checkout_reference'] ?? ''),
            'codice'      => (string) ($r['transaction_code'] ?? ''),
        ];
    }

    /**
     * Una pagina di pagamento accettabile: https su un dominio di SumUp. Per
     * le prove in locale vale anche l'indirizzo del finto SumUp configurato.
     */
    private function paginaDiSumUp(string $url): bool
    {
        $parti = parse_url($url);
        $host  = strtolower((string) ($parti['host'] ?? ''));
        $prova = strtolower((string) parse_url($this->apiUrl, PHP_URL_HOST));
        if ($host === '') {
            return false;
        }
        if (($parti['scheme'] ?? '') === 'https' && ($host === 'sumup.com' || str_ends_with($host, '.sumup.com'))) {
            return true;
        }

        return $prova !== '' && $prova !== 'api.sumup.com' && $host === $prova;
    }

    /**
     * @param array<string,mixed>|null $corpo
     * @return array<string,mixed>
     * @throws ErrorePagamento
     */
    private function chiama(string $metodo, string $percorso, ?array $corpo = null): array
    {
        if (!function_exists('curl_init')) {
            throw new ErrorePagamento('Il server non ha l\'estensione cURL di PHP, che serve per parlare con SumUp.');
        }
        $c = curl_init($this->apiUrl . $percorso);
        $intestazioni = [
            'Authorization: Bearer ' . $this->apiKey,
            'Accept: application/json',
        ];
        $opzioni = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $metodo,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_FOLLOWLOCATION => false,
        ];
        if ($corpo !== null) {
            $intestazioni[] = 'Content-Type: application/json';
            $opzioni[CURLOPT_POSTFIELDS] = json_encode($corpo, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }
        $opzioni[CURLOPT_HTTPHEADER] = $intestazioni;
        curl_setopt_array($c, $opzioni);

        $testo  = curl_exec($c);
        $codice = (int) curl_getinfo($c, CURLINFO_RESPONSE_CODE);
        $errore = curl_error($c);
        curl_close($c);

        if ($testo === false) {
            throw new ErrorePagamento('SumUp non risponde: ' . $errore);
        }
        $dati = json_decode((string) $testo, true);
        if ($codice < 200 || $codice >= 300) {
            $motivo = is_array($dati) ? (string) ($dati['message'] ?? $dati['error_message'] ?? $dati['error_code'] ?? '') : '';
            throw new ErrorePagamento(sprintf('SumUp ha risposto %d%s', $codice, $motivo !== '' ? ': ' . $motivo : ''), $codice);
        }
        if (!is_array($dati)) {
            throw new ErrorePagamento('SumUp ha risposto in un formato inatteso.');
        }

        return $dati;
    }
}
