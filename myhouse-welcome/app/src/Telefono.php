<?php
namespace MHW;

/**
 * I numeri di telefono scritti dai clienti, con il prefisso internazionale.
 *
 * Gli ospiti arrivano spesso dall'estero: un numero senza prefisso dal loro
 * telefono non funziona, e WhatsApp lo vuole sempre col prefisso. Quindi:
 *   - un numero italiano scritto senza prefisso (cellulare che comincia con 3,
 *     fisso che comincia con 0) diventa +39;
 *   - 0039… diventa +39…; un numero che comincia con + resta com'è;
 *   - i numeri brevi e i numeri speciali restano come sono: 112, 113, 115, 118,
 *     1515, 1522, 116117, i numeri verdi 800/803 e gli altri numeri nazionali
 *     (199, 89x, 84x), che dall'estero non si chiamano comunque;
 *   - un testo che non è un numero (lettere, note) non si tocca.
 * Le cifre si tengono a gruppi come le ha scritte il cliente, con uno spazio
 * al posto di trattini, punti e barre.
 */
final class Telefono
{
    public static function normalizza(string $raw): string
    {
        $t = trim(preg_replace('/\s+/u', ' ', $raw) ?? '');
        if ($t === '' || preg_match('/\p{L}/u', $t)) return $t;
        $cifre = preg_replace('/\D/', '', $t) ?? '';
        if ($cifre === '') return $t;
        $gruppi = trim(preg_replace('/[^\d]+/', ' ', $t) ?? '');
        if (str_starts_with($t, '+')) return '+' . $gruppi;
        if (str_starts_with($cifre, '00') && strlen($cifre) > 6) return '+' . ltrim(preg_replace('/^0\s*0\s*/', '', $gruppi) ?? '');
        if (self::speciale($cifre)) return $t;
        // 39 seguito da un numero italiano, senza il +: 393331234567.
        if (preg_match('/^39[03]\d{6,10}$/', $cifre) && !str_contains($gruppi, ' ')) return '+39 ' . substr($cifre, 2);
        if (preg_match('/^[03]\d{5,10}$/', $cifre)) return '+39 ' . $gruppi;
        return $t;
    }

    /** I numeri che non vogliono il prefisso: brevi (cominciano con 1) e nazionali speciali. */
    public static function speciale(string $cifre): bool
    {
        // In Italia i cellulari cominciano con 3 e i fissi con 0: i numeri brevi (1…), verdi e
        // nazionali (8…, 4…) non sono né l'uno né l'altro.
        return (bool) preg_match('/^[148]/', $cifre);
    }

    /** Per wa.me: solo cifre, con il prefisso (un numero italiano senza prefisso prende il 39). */
    public static function whatsapp(string $raw): string
    {
        $n = self::normalizza($raw);
        return str_starts_with($n, '+') ? (preg_replace('/\D/', '', $n) ?? '') : '';
    }
}
