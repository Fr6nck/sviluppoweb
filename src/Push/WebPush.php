<?php

declare(strict_types=1);

namespace ArcoDelVento\Push;

/**
 * Le notifiche push, senza librerie: OpenSSL e cURL di PHP.
 *
 * Due standard:
 *   - RFC 8291 (cifratura): il testo della notifica si cifra per il telefono
 *     che l'ha chiesta, con una chiave usa e getta e il segreto del telefono
 *     (ECDH su P-256, HKDF, AES-128-GCM, «Content-Encoding: aes128gcm»). Il
 *     servizio di push (Google, Apple, Mozilla) lo trasporta senza leggerlo.
 *   - RFC 8292 (VAPID): ogni invio porta un gettone firmato con la chiave del
 *     sito (ES256), così il servizio sa che a spedire è chi ha creato
 *     l'iscrizione.
 *
 * Le chiavi del sito nascono una volta (generaChiavi) e stanno nell'archivio
 * delle notifiche: Admin\Notifiche.
 */
final class WebPush
{
    /** L'intestazione DER di una chiave pubblica P-256 (SubjectPublicKeyInfo), prima dei 65 byte del punto. */
    private const SPKI_P256 = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

    /**
     * Una coppia di chiavi VAPID nuova.
     *
     * @return array{privata: string, pubblica: string} la privata in PEM, la pubblica in base64url (65 byte, non compressa)
     */
    public static function generaChiavi(): array
    {
        $chiave = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        if ($chiave === false || !openssl_pkey_export($chiave, $pem)) {
            throw new \RuntimeException('OpenSSL non crea chiavi EC P-256 su questo server.');
        }

        return ['privata' => $pem, 'pubblica' => self::b64(self::puntoPubblico($chiave))];
    }

    /**
     * Il corpo cifrato di una notifica (RFC 8291, aes128gcm, un solo record).
     *
     * @param string $uaPubblica la chiave p256dh del telefono, 65 byte
     * @param string $auth       il segreto auth del telefono, 16 byte
     * @param string|null $sale      16 byte; casuali se null (fissi solo nelle prove)
     * @param \OpenSSLAsymmetricKey|null $effimera la chiave usa e getta; nuova se null
     */
    public static function cifra(string $testo, string $uaPubblica, string $auth, ?string $sale = null, ?\OpenSSLAsymmetricKey $effimera = null): string
    {
        if (strlen($uaPubblica) !== 65 || $uaPubblica[0] !== "\x04" || strlen($auth) !== 16) {
            throw new \InvalidArgumentException('Chiavi del telefono non valide.');
        }
        $sale ??= random_bytes(16);
        $effimera ??= openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        if (!$effimera instanceof \OpenSSLAsymmetricKey) {
            throw new \RuntimeException('OpenSSL non crea chiavi EC P-256 su questo server.');
        }
        $asPubblica = self::puntoPubblico($effimera);

        $condiviso = openssl_pkey_derive(self::chiaveDaPunto($uaPubblica), $effimera, 32);
        if ($condiviso === false) {
            throw new \RuntimeException('Accordo di chiavi non riuscito.');
        }
        // RFC 8291 §3.4: il segreto del telefono entra come sale, le due chiavi pubbliche nelle info.
        $ikm   = hash_hkdf('sha256', $condiviso, 32, "WebPush: info\x00" . $uaPubblica . $asPubblica, $auth);
        $cek   = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\x00", $sale);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\x00", $sale);

        // Un solo record: il testo, poi il delimitatore 0x02 dell'ultimo record.
        $cifrato = openssl_encrypt($testo . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $etichetta, '', 16);
        if ($cifrato === false) {
            throw new \RuntimeException('Cifratura non riuscita.');
        }

        // Intestazione: sale, dimensione del record (4096), lunghezza e chiave pubblica usa e getta.
        return $sale . pack('N', 4096) . chr(65) . $asPubblica . $cifrato . $etichetta;
    }

    /**
     * Il gettone VAPID (JWT firmato ES256) per un servizio di push.
     *
     * @param string $pubblico l'origine del servizio, es. https://fcm.googleapis.com
     * @param string $soggetto un mailto: o un indirizzo https del sito
     */
    public static function gettone(string $pubblico, string $soggetto, string $privataPem, ?int $scade = null): string
    {
        $testa  = self::b64((string) json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $corpo  = self::b64((string) json_encode(['aud' => $pubblico, 'exp' => $scade ?? time() + 12 * 3600, 'sub' => $soggetto], JSON_UNESCAPED_SLASHES));
        $chiave = openssl_pkey_get_private($privataPem);
        if ($chiave === false || !openssl_sign($testa . '.' . $corpo, $firmaDer, $chiave, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('Firma VAPID non riuscita.');
        }

        return $testa . '.' . $corpo . '.' . self::b64(self::firmaGrezza($firmaDer));
    }

    /**
     * Spedisce una notifica a un'iscrizione.
     *
     * @param array{endpoint: string, p256dh: string, auth: string} $iscrizione
     * @param array{privata: string, pubblica: string} $vapid
     * @return array{stato: int, errore: ?string} stato HTTP del servizio (0 se non risponde)
     */
    public static function invia(array $iscrizione, string $testo, array $vapid, string $soggetto, int $ttl = 86400, string $urgenza = 'high', ?string $argomento = null): array
    {
        $endpoint = $iscrizione['endpoint'];
        $parti = parse_url($endpoint);
        $pubblico = ($parti['scheme'] ?? 'https') . '://' . ($parti['host'] ?? '') . (isset($parti['port']) ? ':' . $parti['port'] : '');
        try {
            $corpo = self::cifra($testo, self::da64($iscrizione['p256dh']), self::da64($iscrizione['auth']));
            $jwt   = self::gettone($pubblico, $soggetto, $vapid['privata']);
        } catch (\Throwable $e) {
            return ['stato' => 0, 'errore' => $e->getMessage()];
        }

        $intestazioni = [
            'Content-Type: application/octet-stream',
            'Content-Encoding: aes128gcm',
            'TTL: ' . max(0, $ttl),
            'Urgency: ' . $urgenza,
            'Authorization: vapid t=' . $jwt . ', k=' . $vapid['pubblica'],
        ];
        if ($argomento !== null && preg_match('/^[A-Za-z0-9_-]{1,32}$/', $argomento) === 1) {
            // Una notifica nuova con lo stesso argomento sostituisce quella non ancora consegnata.
            $intestazioni[] = 'Topic: ' . $argomento;
        }
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $corpo,
            CURLOPT_HTTPHEADER     => $intestazioni,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS | CURLPROTO_HTTP,
        ]);
        $risposta = curl_exec($ch);
        $stato = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $errore = $risposta === false ? curl_error($ch) : null;
        curl_close($ch);
        if ($errore === null && ($stato < 200 || $stato >= 300)) {
            $errore = 'il servizio di push ha risposto ' . $stato . (is_string($risposta) && $risposta !== '' ? ': ' . mb_substr(trim(strip_tags($risposta)), 0, 160) : '');
        }

        return ['stato' => $stato, 'errore' => $errore];
    }

    // ------------------------------------------------------------------ interni

    /** I 65 byte del punto pubblico (0x04 || x || y) di una chiave EC. */
    public static function puntoPubblico(\OpenSSLAsymmetricKey $chiave): string
    {
        $d = openssl_pkey_get_details($chiave);
        if (!is_array($d) || !isset($d['ec']['x'], $d['ec']['y'])) {
            throw new \RuntimeException('Chiave EC non leggibile.');
        }

        return "\x04" . str_pad($d['ec']['x'], 32, "\x00", STR_PAD_LEFT) . str_pad($d['ec']['y'], 32, "\x00", STR_PAD_LEFT);
    }

    /** Una chiave pubblica OpenSSL dai 65 byte del punto. */
    public static function chiaveDaPunto(string $punto): \OpenSSLAsymmetricKey
    {
        $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode((string) hex2bin(self::SPKI_P256) . $punto), 64, "\n") . "-----END PUBLIC KEY-----\n";
        $chiave = openssl_pkey_get_public($pem);
        if ($chiave === false) {
            throw new \InvalidArgumentException('Chiave pubblica non valida.');
        }

        return $chiave;
    }

    /**
     * Una chiave privata OpenSSL dai 32 byte segreti e dal punto pubblico
     * (ECPrivateKey, RFC 5915). Serve alle prove con le chiavi della RFC.
     */
    public static function chiavePrivata(string $segreto, string $punto): \OpenSSLAsymmetricKey
    {
        $der = "\x30\x77\x02\x01\x01\x04\x20" . $segreto
             . "\xa0\x0a\x06\x08" . (string) hex2bin('2a8648ce3d030107')
             . "\xa1\x44\x03\x42\x00" . $punto;
        $chiave = openssl_pkey_get_private("-----BEGIN EC PRIVATE KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END EC PRIVATE KEY-----\n");
        if ($chiave === false) {
            throw new \InvalidArgumentException('Chiave privata non valida.');
        }

        return $chiave;
    }

    /** La firma ECDSA da DER (come la dà OpenSSL) a r || s, 64 byte (come la vuole JWT). */
    private static function firmaGrezza(string $der): string
    {
        $pos = 2;
        if (ord($der[1]) & 0x80) {
            $pos += ord($der[1]) & 0x7f;
        }
        $parti = [];
        for ($i = 0; $i < 2; $i++) {
            $lunghezza = ord($der[$pos + 1]);
            $numero = substr($der, $pos + 2, $lunghezza);
            $parti[] = str_pad(ltrim($numero, "\x00"), 32, "\x00", STR_PAD_LEFT);
            $pos += 2 + $lunghezza;
        }

        return $parti[0] . $parti[1];
    }

    public static function b64(string $dati): string
    {
        return rtrim(strtr(base64_encode($dati), '+/', '-_'), '=');
    }

    public static function da64(string $testo): string
    {
        $dati = base64_decode(strtr($testo, '-_', '+/') . str_repeat('=', (4 - strlen($testo) % 4) % 4), true);

        return $dati === false ? '' : $dati;
    }
}
