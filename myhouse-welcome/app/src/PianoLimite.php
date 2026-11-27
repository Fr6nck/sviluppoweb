<?php
namespace MHW;

/**
 * Un limite che un piano più alto toglie (8B): il pannello apre la finestra «Passa a …»
 * (host/_sblocca.php) invece di mostrare un errore. $funzione: foto, sezioni, lingue, firma,
 * strutture; $piano: plus o portfolio. I limiti che un cambio di piano non toglie (le sezioni
 * libere al massimo, le traduzioni del mese) restano LimitReached con il loro messaggio.
 */
final class PianoLimite extends LimitReached
{
    public function __construct(string $messaggio, public readonly string $funzione, public readonly string $piano = 'plus')
    {
        parent::__construct($messaggio);
    }
}
