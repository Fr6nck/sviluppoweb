<?php
namespace MHW;

/**
 * Il QR in forme da stampare: SVG (scala senza perdere nitidezza) e un foglio
 * PDF A4 pronto da appendere. Entrambi scritti a mano dalla matrice del
 * generatore: niente librerie, e niente contenuto dell'utente dentro l'SVG
 * oltre al nome della casa, che passa sempre dall'escape.
 */
final class QrExport
{
    public static function svg(string $text, int $quiet = 4): string
    {
        $m = Qr::matrix($text);
        $n = count($m); $size = $n + $quiet * 2;
        // Una corsa di moduli scuri consecutivi su una riga diventa un solo rettangolo.
        $d = '';
        for ($y = 0; $y < $n; $y++) {
            $x = 0;
            while ($x < $n) {
                if (!$m[$y][$x]) { $x++; continue; }
                $start = $x;
                while ($x < $n && $m[$y][$x]) $x++;
                $d .= 'M' . ($start + $quiet) . ' ' . ($y + $quiet) . 'h' . ($x - $start) . 'v1h-' . ($x - $start) . 'z';
            }
        }
        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
             . '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $size . ' ' . $size . '" width="' . ($size * 10) . '" height="' . ($size * 10) . '" shape-rendering="crispEdges">'
             . '<rect width="100%" height="100%" fill="#ffffff"/>'
             . '<path fill="#231b12" d="' . $d . '"/></svg>' . "\n";
    }

    /** Testo per i font standard del PDF (WinAnsi): accenti italiani compresi. */
    private static function pdfText(string $s): string
    {
        $s = (string) (@iconv('UTF-8', 'Windows-1252//TRANSLIT', $s) ?: preg_replace('/[^\x20-\x7e]/', '?', $s));
        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', ' ', ' '], $s);
    }

    /** Larghezza approssimata di un testo in Helvetica, per centrarlo. */
    private static function width(string $s, float $size, bool $bold = false): float
    {
        return mb_strlen($s) * $size * ($bold ? 0.56 : 0.5);
    }

    /**
     * Un foglio A4: il nome della casa, il QR grande al centro, l'invito a
     * inquadrarlo in italiano e in inglese, e l'indirizzo scritto per esteso
     * per chi il QR non lo sa usare.
     */
    public static function pdf(string $text, string $name): string
    {
        $m = Qr::matrix($text);
        $n = count($m);
        $W = 595.28; $H = 841.89;                     // A4 in punti
        $lato = 340.0; $mod = $lato / $n;
        $x0 = ($W - $lato) / 2; $y0 = 300.0;          // angolo in basso a sinistra del QR

        $c = "q\n1 1 1 rg 0 0 $W $H re f\n";
        $c .= '0.137 0.106 0.071 rg' . "\n";           // #231b12
        for ($y = 0; $y < $n; $y++) {
            $x = 0;
            while ($x < $n) {
                if (!$m[$y][$x]) { $x++; continue; }
                $s = $x; while ($x < $n && $m[$y][$x]) $x++;
                // In PDF l'asse y sale: la riga 0 del QR sta in alto.
                $c .= sprintf("%.3F %.3F %.3F %.3F re\n", $x0 + $s * $mod, $y0 + ($n - 1 - $y) * $mod, ($x - $s) * $mod, $mod);
            }
        }
        $c .= "f\n";

        $scrivi = function (string $t, float $size, float $y, bool $bold = false, string $colore = '0.137 0.106 0.071') use ($W): string {
            $x = max(40, ($W - self::width($t, $size, $bold)) / 2);
            return "BT $colore rg /" . ($bold ? 'F2' : 'F1') . " $size Tf " . sprintf('%.2F %.2F', $x, $y) . ' Td (' . self::pdfText($t) . ") Tj ET\n";
        };
        $c .= $scrivi($name, 30, 720, true);
        $c .= $scrivi('Inquadra il codice per la guida della casa', 15, 680, false, '0.416 0.357 0.282');
        $c .= $scrivi('Scan the code for the house guide', 13, 660, false, '0.416 0.357 0.282');
        $c .= $scrivi('Wi-Fi, check-in, check-out, consigli sul posto', 12, 262, false, '0.416 0.357 0.282');
        $c .= $scrivi($text, 10, 228, false, '0.416 0.357 0.282');
        $c .= $scrivi('myhouse welcome', 10, 60, true, '0.580 0.510 0.373');
        $c .= "Q\n";

        $obj = [];
        $obj[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $obj[2] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
        $obj[3] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 $W $H] /Resources << /Font << /F1 5 0 R /F2 6 0 R >> >> /Contents 4 0 R >>";
        $obj[4] = '<< /Length ' . strlen($c) . " >>\nstream\n" . $c . "endstream";
        $obj[5] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $obj[6] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        $obj[7] = '<< /Title (' . self::pdfText('QR — ' . $name) . ') /Producer (MyHouse Welcome) >>';

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offset = [];
        foreach ($obj as $i => $body) { $offset[$i] = strlen($pdf); $pdf .= "$i 0 obj\n$body\nendobj\n"; }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($obj) + 1) . "\n0000000000 65535 f \n";
        foreach ($obj as $i => $_) $pdf .= sprintf("%010d 00000 n \n", $offset[$i]);
        $pdf .= "trailer\n<< /Size " . (count($obj) + 1) . " /Root 1 0 R /Info 7 0 R >>\nstartxref\n$xref\n%%EOF\n";
        return $pdf;
    }
}
