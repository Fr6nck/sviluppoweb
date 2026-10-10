<?php
namespace MHW;

/**
 * Dove finiscono logo, copertine, immagini e PDF.
 *
 * In produzione Amazon S3; su disco solo per lo sviluppo. Le chiavi degli
 * oggetti non si indovinano e sono ordinate per account e struttura:
 *   a12/p5/2026/9f3c…e1.jpg
 * Le credenziali AWS restano sul server: il browser non le vede mai, e non
 * carica mai direttamente sul bucket. Ogni file passa prima dai controlli.
 */
interface Storage
{
    public function put(string $key, string $bytes, string $mime, string $disposition = ''): void;
    public function delete(string $key): void;
    /** I byte di un oggetto salvato (per duplicarlo, per esempio copiando una struttura). */
    public function get(string $key): string;
    public function url(string $key): string;
    public function name(): string;
}
