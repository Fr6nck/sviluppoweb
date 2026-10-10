<?php
/**
 * Un server SMTP finto per le prove: in chiaro, una connessione alla volta.
 *
 *   php smtp-finto.php PORTA CARTELLA
 *
 * A ogni connessione legge CARTELLA/regole.json:
 *   {"offre": ["LOGIN","PLAIN"], "accetta": ["PLAIN"], "utente": "...", "password": "..."}
 *   offre   — i metodi scritti nella risposta a EHLO (vuoto: nessuna riga AUTH)
 *   accetta — i metodi con cui le credenziali giuste passano davvero
 * e scrive in CARTELLA/sessioni.jsonl, per ogni tentativo, metodo ed esito (mai la password),
 * e in CARTELLA/posta.jsonl le email ricevute.
 */
[$_, $porta, $dir] = $argv + [null, '2525', sys_get_temp_dir() . '/smtp-finto'];
@mkdir($dir, 0777, true);
$server = stream_socket_server("tcp://127.0.0.1:$porta", $errno, $errstr);
if (!$server) { fwrite(STDERR, "smtp-finto: $errstr\n"); exit(1); }
$scrivi = fn(string $f, array $r) => file_put_contents("$dir/$f", json_encode($r) . "\n", FILE_APPEND);

while ($c = @stream_socket_accept($server, -1)) {
    $regole = (json_decode((string) @file_get_contents("$dir/regole.json"), true) ?: []) + ['offre' => ['LOGIN', 'PLAIN'], 'accetta' => ['LOGIN', 'PLAIN'], 'utente' => '', 'password' => ''];
    $dentro = false; $da = ''; $a = [];
    $giusti = fn(string $u, string $p) => $u === $regole['utente'] && $p === $regole['password'];
    $esito = function (string $metodo, bool $ok) use ($c, $scrivi, &$dentro, $regole) {
        $ok = $ok && in_array($metodo, $regole['accetta'], true);
        $scrivi('sessioni.jsonl', ['metodo' => $metodo, 'ok' => $ok]);
        if ($ok) { $dentro = true; fwrite($c, "235 2.7.0 Authentication successful\r\n"); }
        else fwrite($c, "535 5.7.8 Error: authentication failed: (reason unavailable)\r\n");
    };
    fwrite($c, "220 smtp.finto ESMTP\r\n");
    while (($riga = fgets($c)) !== false) {
        $riga = rtrim($riga, "\r\n");
        $verbo = strtoupper(strtok($riga, ' ') ?: '');
        if ($verbo === 'EHLO') {
            fwrite($c, "250-smtp.finto\r\n" . ($regole['offre'] ? '250-AUTH ' . implode(' ', $regole['offre']) . "\r\n" : '') . "250 8BITMIME\r\n");
        } elseif ($verbo === 'AUTH') {
            $parti = explode(' ', $riga);
            $metodo = strtoupper($parti[1] ?? '');
            if (!in_array($metodo, $regole['offre'], true)) { fwrite($c, "504 5.5.4 Unrecognized authentication type\r\n"); continue; }
            if ($metodo === 'PLAIN') {
                $dati = isset($parti[2]) ? $parti[2] : (fwrite($c, "334 \r\n") ? rtrim((string) fgets($c), "\r\n") : '');
                $p = explode("\0", (string) base64_decode($dati));
                $esito('PLAIN', $giusti($p[1] ?? '', $p[2] ?? ''));
            } else {
                fwrite($c, "334 VXNlcm5hbWU6\r\n"); $u = (string) base64_decode(rtrim((string) fgets($c), "\r\n"));
                fwrite($c, "334 UGFzc3dvcmQ6\r\n"); $p = (string) base64_decode(rtrim((string) fgets($c), "\r\n"));
                $esito('LOGIN', $giusti($u, $p));
            }
        } elseif ($verbo === 'MAIL') {
            if (!$dentro && $regole['offre']) { fwrite($c, "530 5.7.0 Authentication required\r\n"); continue; }
            $da = $riga; fwrite($c, "250 2.1.0 Ok\r\n");
        } elseif ($verbo === 'RCPT') {
            $a[] = $riga; fwrite($c, "250 2.1.5 Ok\r\n");
        } elseif ($verbo === 'DATA') {
            fwrite($c, "354 End data with <CR><LF>.<CR><LF>\r\n");
            $corpo = '';
            while (($l = fgets($c)) !== false && rtrim($l, "\r\n") !== '.') $corpo .= $l;
            $scrivi('posta.jsonl', ['da' => $da, 'a' => $a, 'byte' => strlen($corpo)]);
            fwrite($c, "250 2.0.0 Ok: queued\r\n");
        } elseif ($verbo === 'QUIT') {
            fwrite($c, "221 2.0.0 Bye\r\n"); break;
        } else {
            fwrite($c, "250 Ok\r\n");
        }
    }
    fclose($c);
}
