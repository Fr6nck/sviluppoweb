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

    /**
     * Lo stesso foglio del PDF in SVG (7C): logo (incorporato), nome, QR, inviti in italiano e inglese della
     * stessa grandezza, marchio. Il nome passa dall'escape.
     */
    public static function foglioSvg(string $text, string $name, ?string $logo = null, ?string $logoMime = null): string
    {
        $qr = self::svg($text);
        $qr = (string) preg_replace('/^<\?xml[^>]*\?>\s*/', '', $qr);
        $qr = (string) preg_replace(['/ width="\d+" height="\d+"/', '/<svg /'], ['', '<svg x="140" y="250" width="320" height="320" '], $qr, 1);
        $e = fn(string $s) => htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $yNome = 150; $img = '';   // 8B: sotto il nome la riga piccola, il QR da 250
        if ($logo !== null && $logo !== '' && in_array($logoMime, ['image/png', 'image/jpeg', 'image/webp'], true)) {
            $img = '<image href="data:' . $logoMime . ';base64,' . base64_encode($logo) . '" x="200" y="40" width="200" height="80" preserveAspectRatio="xMidYMid meet"/>';
            $yNome = 170;
        }
        $t = fn(string $s, int $y, int $size, string $peso = '400', string $colore = '#231b12') =>
            '<text x="300" y="' . $y . '" text-anchor="middle" font-family="Helvetica, Arial, sans-serif" font-size="' . $size . '" font-weight="' . $peso . '" fill="' . $colore . '">' . $e($s) . '</text>';
        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
             . '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 840" width="600" height="840">'
             . '<rect width="100%" height="100%" fill="#ffffff"/>' . $img
             . $t($name, $yNome, 30, '700') . $t(self::SOTTOTITOLO, $yNome + 28, 13, '400', '#6a5b48') . $qr
             . $t(self::INVITI[0], 620, 18, '700') . $t(self::INVITI[1], 646, 18, '700')
             . $t(self::INVITI[2], 684, 18, '400', '#6a5b48') . $t(self::INVITI[3], 710, 18, '400', '#6a5b48')
             . $t('MyHouse Welcome', 792, 11, '700', '#94825f') . $t((string) preg_replace('#^https?://#', '', $text), 810, 10, '400', '#6a5b48')
             . '</svg>' . "\n";
    }

    /** Testo per i font standard del PDF (WinAnsi): accenti italiani compresi. */
    private static function pdfText(string $s): string
    {
        $s = (string) (@iconv('UTF-8', 'Windows-1252//TRANSLIT', $s) ?: preg_replace('/[^\x20-\x7e]/', '?', $s));
        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', ' ', ' '], $s);
    }

    /** Le larghezze di Helvetica e Helvetica-Bold (millesimi di em) dei caratteri da 32 a 126, per centrare il testo. */
    private const HELV = [278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278, 556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 278, 278, 584, 584, 584, 556, 1015, 667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778, 667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 278, 278, 278, 469, 556, 333, 556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556, 556, 556, 556, 333, 500, 278, 556, 500, 722, 500, 500, 500, 334, 260, 334, 584];
    private const HELV_B = [278, 333, 474, 556, 556, 889, 722, 238, 333, 333, 389, 584, 278, 333, 278, 278, 556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 333, 333, 584, 584, 584, 611, 975, 722, 722, 722, 722, 667, 611, 778, 722, 278, 556, 722, 611, 833, 722, 778, 667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 333, 278, 333, 584, 556, 333, 556, 611, 556, 611, 556, 333, 611, 611, 278, 278, 556, 278, 889, 611, 611, 611, 611, 389, 556, 333, 611, 556, 778, 556, 556, 500, 389, 280, 389, 584];

    /** Larghezza di un testo in Helvetica: i caratteri fuori tabella (accenti) contano come una «e». */
    private static function width(string $s, float $size, bool $bold = false): float
    {
        $t = $bold ? self::HELV_B : self::HELV; $w = 0;
        foreach (mb_str_split($s) as $ch) { $o = mb_ord($ch); $w += ($o >= 32 && $o <= 126) ? $t[$o - 32] : $t[ord('e') - 32]; }
        return $w * $size / 1000;
    }

    /** I quattro inviti sotto il QR: italiano e inglese, della stessa grandezza (7C, testi della 8B). */
    public const INVITI = ['Inquadra il QR Code', 'Scan the QR code',
                           'Tutte le informazioni sulla casa, e non solo.', 'All about the house, and more.'];
    /** La riga piccola sotto il nome (8B). */
    public const SOTTOTITOLO = 'La guida digitale della casa · Your digital house guide';

    /**
     * Il logo pronto per il PDF: JPEG su fondo bianco (la trasparenza del PNG diventa bianca), al massimo
     * 600×240 px. @return array{0:string,1:int,2:int}|null [byte JPEG, larghezza, altezza]
     */
    private static function logoJpeg(?string $bytes): ?array
    {
        if ($bytes === null || $bytes === '' || !function_exists('imagecreatefromstring')) return null;
        $img = @imagecreatefromstring($bytes);
        if (!$img) return null;
        $w = imagesx($img); $h = imagesy($img);
        $k = min(1, 600 / $w, 240 / $h); $nw = max(1, (int) round($w * $k)); $nh = max(1, (int) round($h * $k));
        $out = imagecreatetruecolor($nw, $nh);
        imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
        imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        ob_start(); imagejpeg($out, null, 90); $jpg = (string) ob_get_clean();
        return $jpg !== '' ? [$jpg, $nw, $nh] : null;
    }

    /**
     * Un foglio A4 centrato (7C): in alto il logo della struttura (se c'è) e il nome; il QR grande al centro;
     * sotto, in italiano e in inglese con la stessa grandezza, «Inquadra il QR Code» e «La guida della casa…»;
     * l'indirizzo per chi il QR non lo sa usare; in basso, piccolo, il marchio.
     */
    public static function pdf(string $text, string $name, ?string $logo = null): string
    {
        $m = Qr::matrix($text);
        $n = count($m);
        $W = 595.28; $H = 841.89;                     // A4 in punti
        $lato = 320.0; $mod = $lato / $n;
        $x0 = ($W - $lato) / 2; $y0 = 300.0;          // angolo in basso a sinistra del QR
        $logoJ = self::logoJpeg($logo);

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
        $yNome = 680;
        if ($logoJ) {
            [, $lw, $lh] = $logoJ;
            $k = min(200 / $lw, 80 / $lh); $dw = $lw * $k; $dh = $lh * $k;
            $c .= sprintf("q %.2F 0 0 %.2F %.2F %.2F cm /Logo Do Q\n", $dw, $dh, ($W - $dw) / 2, 790 - $dh);
            $yNome = 790 - $dh - 44;
        } else $yNome = 720;
        $c .= $scrivi($name, 28, $yNome, true);
        $grigio = '0.416 0.357 0.282';
        $c .= $scrivi(self::SOTTOTITOLO, 11, $yNome - 24, false, $grigio);
        $c .= $scrivi(self::INVITI[0], 16, 262, true);
        $c .= $scrivi(self::INVITI[1], 16, 240, true);
        $c .= $scrivi(self::INVITI[2], 16, 206, false, $grigio);
        $c .= $scrivi(self::INVITI[3], 16, 184, false, $grigio);
        // In fondo, piccoli: il marchio e l'indirizzo breve della guida, così come lo genera il sito.
        $c .= $scrivi('MyHouse Welcome', 10, 62, true, '0.580 0.510 0.373');
        $c .= $scrivi(preg_replace('#^https?://#', '', $text), 9, 46, false, $grigio);
        $c .= "Q\n";

        $obj = [];
        $obj[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $obj[2] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
        $xo = $logoJ ? ' /XObject << /Logo 8 0 R >>' : '';
        $obj[3] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 $W $H] /Resources << /Font << /F1 5 0 R /F2 6 0 R >>$xo >> /Contents 4 0 R >>";
        $obj[4] = '<< /Length ' . strlen($c) . " >>\nstream\n" . $c . "endstream";
        $obj[5] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $obj[6] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        $obj[7] = '<< /Title (' . self::pdfText('QR — ' . $name) . ') /Producer (MyHouse Welcome) >>';
        if ($logoJ) $obj[8] = '<< /Type /XObject /Subtype /Image /Width ' . $logoJ[1] . ' /Height ' . $logoJ[2]
            . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' . strlen($logoJ[0]) . " >>\nstream\n" . $logoJ[0] . "\nendstream";

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
