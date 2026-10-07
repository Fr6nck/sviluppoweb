<?php
namespace MHW;

/**
 * S3 con la firma Signature Version 4, scritta a mano per non dipendere
 * dall'SDK (che su un hosting via FTP non si può installare).
 * Verificata con i vettori di prova pubblicati da AWS e confrontata con botocore.
 */
final class S3Storage implements Storage
{
    public function __construct(private array $c)
    {
        if (($c['bucket'] ?? '') === '' || ($c['key'] ?? '') === '' || ($c['secret'] ?? '') === '') {
            throw new \RuntimeException('S3 non configurato: servono bucket e credenziali.');
        }
    }

    public function name(): string { return 's3'; }

    /** Endpoint personalizzato = stile a percorso; altrimenti host virtuale del bucket. */
    public function objectUrl(string $key): string
    {
        $enc = self::encodeKey($key);
        if (!empty($this->c['endpoint'])) return rtrim($this->c['endpoint'], '/') . '/' . $this->c['bucket'] . '/' . $enc;
        return 'https://' . $this->c['bucket'] . '.s3.' . $this->c['region'] . '.amazonaws.com/' . $enc;
    }

    public static function encodeKey(string $key): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $key)));
    }

    public function put(string $key, string $bytes, string $mime, string $disposition = ''): void
    {
        $headers = ['content-type' => $mime, 'cache-control' => 'public, max-age=31536000, immutable'];
        if ($disposition !== '') $headers['content-disposition'] = $disposition;
        $this->request('PUT', $key, $bytes, $headers);
    }

    public function delete(string $key): void { $this->request('DELETE', $key, ''); }

    public function get(string $key): string { return $this->request('GET', $key, ''); }

    public function url(string $key): string
    {
        if (!empty($this->c['public_base_url'])) return rtrim($this->c['public_base_url'], '/') . '/' . self::encodeKey($key);
        return self::presign('GET', $this->objectUrl($key), $this->c['region'], $this->c['key'], $this->c['secret'],
                             (int) ($this->c['url_ttl'] ?? 3600), gmdate('Ymd\THis\Z'), (string) ($this->c['token'] ?? ''));
    }

    private function request(string $method, string $key, string $body, array $headers = []): string
    {
        $url = $this->objectUrl($key);
        $amz = gmdate('Ymd\THis\Z');
        $firmate = self::signHeaders($method, $url, $headers, hash('sha256', $body), $this->c['region'],
                                     $this->c['key'], $this->c['secret'], $amz, (string) ($this->c['token'] ?? ''));
        $lista = [];
        foreach ($firmate as $k => $v) if ($k !== 'host') $lista[] = $k . ': ' . $v;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $lista,
        ]);
        if ($method === 'PUT') curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        $risposta = (string) curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($code < 200 || $code >= 300) {
            Log::error('S3 ' . $method . ' non riuscito', ['code' => $code, 'err' => $err, 'body' => mb_substr($risposta, 0, 500)]);
            throw new \RuntimeException('Archiviazione dei file non disponibile al momento.');
        }
        return $risposta;
    }

    // ------------------------------------------------------------- SigV4

    private static function hmac(string $key, string $data, bool $raw = true): string
    {
        return hash_hmac('sha256', $data, $key, $raw);
    }

    private static function signingKey(string $secret, string $date, string $region, string $service = 's3'): string
    {
        $k = self::hmac('AWS4' . $secret, $date);
        $k = self::hmac($k, $region);
        $k = self::hmac($k, $service);
        return self::hmac($k, 'aws4_request');
    }

    private static function canonicalQuery(array $q): string
    {
        $parti = [];
        foreach ($q as $k => $v) $parti[rawurlencode((string) $k)] = rawurlencode((string) $v);
        ksort($parti, SORT_STRING);
        return implode('&', array_map(fn($k, $v) => "$k=$v", array_keys($parti), $parti));
    }

    /** @return array{0:string,1:string,2:array} [percorso canonico, host, query] */
    private static function parts(string $url): array
    {
        $u = parse_url($url);
        $host = $u['host'] . (isset($u['port']) ? ':' . $u['port'] : '');
        $query = [];
        if (!empty($u['query'])) {
            foreach (explode('&', $u['query']) as $pair) {
                [$k, $v] = array_pad(explode('=', $pair, 2), 2, '');
                $query[rawurldecode($k)] = rawurldecode($v);
            }
        }
        // Il percorso arriva già codificato da objectUrl: si usa com'è.
        return [$u['path'] ?? '/', $host, $query];
    }

    /**
     * Firma con le intestazioni. Restituisce le intestazioni da spedire,
     * Authorization compresa. $service: 's3', oppure 'translate' per le traduzioni
     * suggerite (Traduttore): fuori da S3 l'intestazione x-amz-content-sha256 non serve.
     */
    public static function signHeaders(string $method, string $url, array $headers, string $payloadHash,
                                       string $region, string $key, string $secret, string $amzDate, string $token = '', string $service = 's3'): array
    {
        [$path, $host, $query] = self::parts($url);
        $date = substr($amzDate, 0, 8);
        $h = ['host' => $host, 'x-amz-date' => $amzDate];
        if ($service === 's3') $h['x-amz-content-sha256'] = $payloadHash;
        if ($token !== '') $h['x-amz-security-token'] = $token;
        foreach ($headers as $k => $v) $h[strtolower($k)] = trim(preg_replace('/\s+/', ' ', (string) $v) ?? '');
        ksort($h, SORT_STRING);

        $canonHeaders = ''; foreach ($h as $k => $v) $canonHeaders .= "$k:$v\n";
        $signed = implode(';', array_keys($h));
        $canonical = implode("\n", [$method, $path, self::canonicalQuery($query), $canonHeaders, $signed, $payloadHash]);
        $scope = "$date/$region/$service/aws4_request";
        $toSign = "AWS4-HMAC-SHA256\n$amzDate\n$scope\n" . hash('sha256', $canonical);
        $sig = self::hmac(self::signingKey($secret, $date, $region, $service), $toSign, false);

        $h['authorization'] = "AWS4-HMAC-SHA256 Credential=$key/$scope, SignedHeaders=$signed, Signature=$sig";
        return $h;
    }

    /** Un URL firmato a tempo, per leggere un oggetto privato. */
    public static function presign(string $method, string $url, string $region, string $key, string $secret,
                                   int $expires, string $amzDate, string $token = ''): string
    {
        [$path, $host, $query] = self::parts($url);
        $date = substr($amzDate, 0, 8);
        $scope = "$date/$region/s3/aws4_request";
        $query += [
            'X-Amz-Algorithm' => 'AWS4-HMAC-SHA256',
            'X-Amz-Credential' => "$key/$scope",
            'X-Amz-Date' => $amzDate,
            'X-Amz-Expires' => (string) $expires,
            'X-Amz-SignedHeaders' => 'host',
        ];
        if ($token !== '') $query['X-Amz-Security-Token'] = $token;
        $canonical = implode("\n", [$method, $path, self::canonicalQuery($query), "host:$host\n", 'host', 'UNSIGNED-PAYLOAD']);
        $toSign = "AWS4-HMAC-SHA256\n$amzDate\n$scope\n" . hash('sha256', $canonical);
        $sig = self::hmac(self::signingKey($secret, $date, $region), $toSign, false);
        $u = parse_url($url);
        return $u['scheme'] . '://' . $host . $path . '?' . self::canonicalQuery($query) . '&X-Amz-Signature=' . $sig;
    }
}
