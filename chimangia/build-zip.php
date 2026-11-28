<?php
/**
 * Crea lo zip pronto per il caricamento via FTP / File Manager.
 *
 * Uso (da riga di comando, nella cartella del progetto):
 *   php build-zip.php
 *
 * Il file viene creato in dist/. I file stanno alla radice dello zip:
 * si estrae direttamente dentro public_html (o nella sottocartella scelta).
 * Esclusi: config.php, file caricati, cache, log, lock d'installazione,
 * materiale di progetto (design/) e file di sviluppo.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if (!class_exists('ZipArchive')) {
    fwrite(STDERR, "Serve l'estensione PHP zip (ZipArchive).\n");
    exit(1);
}

$root = __DIR__;
$version = '1.0.0';
if (preg_match("/define\('CM_VERSION', '([^']+)'\)/", (string) file_get_contents($root . '/app/bootstrap.php'), $m)) {
    $version = $m[1];
}

/** Percorsi relativi esclusi (espressioni regolari sul percorso con barre "/"). */
$exclude = [
    '#^config\.php$#',
    '#^build-zip\.php$#',
    '#^\.git(/|$)#',
    '#^\.gitignore$#',
    '#^\.(idea|vscode)(/|$)#',
    '#^dist(/|$)#',
    '#^design(/|$)#',
    '#^node_modules(/|$)#',
    '#^install/install\.lock$#',
    '#^install/\.htaccess$#',               // creato dall'installazione per bloccarla
    '#^uploads/(?!\.htaccess$|index\.html$).+#',
    '#^storage/(cache|logs)/(?!\.htaccess$|\.gitkeep$).+#',
    '#^storage/sessions[a-z-]*(/|$)#',        // sessioni del pannello, dei locali e degli ospiti
    '#^storage/backups(/|$)#',
    '#^storage/[^/]+\.lock$#',
    '#^(sitemap\.xml|robots\.txt)$#',       // rigenerati dal sito
    '#(^|/)\.DS_Store$#',
    '#(^|/)Thumbs\.db$#',
    '#\.(log|bak|swp|tmp|orig)$#',
    '#(^|/)\.[^/]+\.tmp$#',
];

$outDir = $root . '/dist';
if (!is_dir($outDir) && !mkdir($outDir, 0755, true)) {
    fwrite(STDERR, "Impossibile creare la cartella dist/.\n");
    exit(1);
}
$zipPath = $outDir . '/chimangia-' . $version . '-' . date('Ymd-Hi') . '.zip';

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "Impossibile creare $zipPath\n");
    exit(1);
}

$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);
$files = 0;
$dirs = [];
foreach ($it as $file) {
    $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
    $skip = false;
    foreach ($exclude as $re) {
        if (preg_match($re, $rel)) {
            $skip = true;
            break;
        }
    }
    if ($skip) {
        continue;
    }
    if ($file->isDir()) {
        $dirs[] = $rel;
        continue;
    }
    $zip->addFile($file->getPathname(), $rel);
    $files++;
}
// Cartelle vuote necessarie (uploads/, storage/cache, storage/logs).
foreach ($dirs as $d) {
    if ($zip->locateName($d . '/') === false) {
        $zip->addEmptyDir($d);
    }
}
$zip->close();

$size = number_format(filesize($zipPath) / 1024, 0, ',', '.');
echo "Creato: $zipPath\n$files file, $size KB\n";
