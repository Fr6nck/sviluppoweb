<?php

declare(strict_types=1);

namespace ArcoDelVento\Mail;

/**
 * Intestazioni e corpo di un messaggio, come li vuole la posta.
 *
 * Tre forme, dalla più semplice:
 *   - solo testo: text/plain, come il sito ha sempre spedito;
 *   - testo e HTML: multipart/alternative, il programma di posta mostra
 *     l'HTML e chi non lo legge vede il testo;
 *   - con allegati: multipart/mixed, con dentro le due versioni e poi i file.
 *
 * Testo e HTML viaggiano in quoted-printable: righe corte, accenti sicuri.
 * Gli allegati in base64. Le intestazioni che dipendono dal trasporto (To e
 * Subject per mail(), che li vuole a parte) le aggiunge chi spedisce.
 */
final class Mime
{
    /**
     * @return array{intestazioni: list<string>, corpo: string}
     */
    public static function componi(MailMessage $message): array
    {
        $testo = str_replace(["\r\n", "\r"], "\n", $message->body);

        if ($message->html === '' && $message->allegati === []) {
            return [
                'intestazioni' => ['MIME-Version: 1.0', 'Content-Type: text/plain; charset=UTF-8', 'Content-Transfer-Encoding: 8bit'],
                'corpo'        => str_replace("\n", "\r\n", $testo),
            ];
        }

        $alternativa = null;
        if ($message->html !== '') {
            $confine = self::confine('alt');
            $alternativa = [
                'tipo'  => 'multipart/alternative; boundary="' . $confine . '"',
                'corpo' => '--' . $confine . "\r\n" . self::parteTesto('text/plain', $testo)
                         . "\r\n--" . $confine . "\r\n" . self::parteTesto('text/html', $message->html)
                         . "\r\n--" . $confine . '--',
            ];
        }

        if ($message->allegati === []) {
            return [
                'intestazioni' => ['MIME-Version: 1.0', 'Content-Type: ' . $alternativa['tipo']],
                'corpo'        => $alternativa['corpo'],
            ];
        }

        $confine = self::confine('mix');
        $corpo = '--' . $confine . "\r\n"
               . ($alternativa !== null
                   ? 'Content-Type: ' . $alternativa['tipo'] . "\r\n\r\n" . $alternativa['corpo']
                   : self::parteTesto('text/plain', $testo));
        foreach ($message->allegati as $a) {
            $nome = self::nomeFile((string) $a['nome']);
            $corpo .= "\r\n--" . $confine . "\r\n"
                    . 'Content-Type: ' . self::pulisci((string) $a['tipo']) . '; name="' . $nome . "\"\r\n"
                    . 'Content-Disposition: attachment; filename="' . $nome . "\"\r\n"
                    . "Content-Transfer-Encoding: base64\r\n\r\n"
                    . rtrim(chunk_split(base64_encode((string) $a['contenuto']), 76, "\r\n"));
        }
        $corpo .= "\r\n--" . $confine . '--';

        return [
            'intestazioni' => ['MIME-Version: 1.0', 'Content-Type: multipart/mixed; boundary="' . $confine . '"'],
            'corpo'        => $corpo,
        ];
    }

    private static function parteTesto(string $tipo, string $contenuto): string
    {
        $contenuto = str_replace(["\r\n", "\r"], "\n", $contenuto);

        return 'Content-Type: ' . $tipo . "; charset=UTF-8\r\n"
             . "Content-Transfer-Encoding: quoted-printable\r\n\r\n"
             . quoted_printable_encode(str_replace("\n", "\r\n", $contenuto));
    }

    private static function confine(string $tipo): string
    {
        return '=_adv_' . $tipo . '_' . bin2hex(random_bytes(12));
    }

    /** Un nome di file da intestazione: solo lettere, cifre, punto, trattino. */
    private static function nomeFile(string $nome): string
    {
        $nome = preg_replace('/[^A-Za-z0-9._-]+/', '-', $nome) ?? '';

        return trim($nome, '-') !== '' ? trim($nome, '-') : 'allegato';
    }

    private static function pulisci(string $valore): string
    {
        return trim(str_replace(["\r", "\n", "\0", '"'], '', $valore));
    }
}
