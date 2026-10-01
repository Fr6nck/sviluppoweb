<?php
/**
 * Rigenera le versioni WebP della foto del borgo dai .jpg, con GD:
 *
 *   borgo.jpg            → borgo-1200.webp (1200 px di larghezza), borgo-2000.webp (2000 px)
 *   borgo-telefono.jpg   → borgo-telefono-600.webp (600 px)
 *
 * Per cambiare la foto si carica il .jpg nuovo con lo STESSO nome sopra quello
 * vecchio, poi si avvia questo strumento:
 *   - da riga di comando:  php app/tools/foto.php [cartella delle foto]
 *   - dal pannello:        Amministrazione → Diagnostica → «Rigenera le foto WebP»
 * Un .jpg più stretto della misura chiesta non si ingrandisce.
 */

if (!function_exists('mhw_rigenera_foto')) {
    /** @return array<int,array{0:string,1:bool,2:string}> [file, riuscito, dettaglio] */
    function mhw_rigenera_foto(string $cartella): array
    {
        $lavori = [['borgo.jpg', 'borgo-1200.webp', 1200], ['borgo.jpg', 'borgo-2000.webp', 2000], ['borgo-telefono.jpg', 'borgo-telefono-600.webp', 600]];
        $esiti = [];
        if (!function_exists('imagewebp')) {
            return [['tutte', false, 'Questo PHP non ha GD con il supporto WebP: chiedi all\'hosting di attivarlo.']];
        }
        foreach ($lavori as [$da, $a, $larghezza]) {
            $sorgente = rtrim($cartella, '/') . '/' . $da;
            $img = is_file($sorgente) ? @imagecreatefromjpeg($sorgente) : false;
            if (!$img) { $esiti[] = [$a, false, "manca $da, o non è un JPEG leggibile"]; continue; }
            $w = imagesx($img); $h = imagesy($img);
            $nw = min($w, $larghezza); $nh = (int) round($h * $nw / $w);
            $out = imagecreatetruecolor($nw, $nh);
            imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
            $ok = imagewebp($out, rtrim($cartella, '/') . '/' . $a, 80);
            imagedestroy($img); imagedestroy($out);
            $esiti[] = [$a, (bool) $ok, $ok ? "{$nw}×{$nh} px da $da" : 'scrittura non riuscita: controlla i permessi della cartella'];
        }
        return $esiti;
    }
}

if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === __FILE__) {
    // La cartella delle foto: quella indicata, oppure accanto all'app (installazione) o in public/ (sorgenti).
    $cartella = $argv[1] ?? (is_dir(dirname(__DIR__, 2) . '/assets/foto') ? dirname(__DIR__, 2) . '/assets/foto' : dirname(__DIR__) . '/public/assets/foto');
    foreach (mhw_rigenera_foto($cartella) as [$file, $ok, $dett]) echo ($ok ? 'ok  ' : 'NO  '), $file, ' — ', $dett, "\n";
}
