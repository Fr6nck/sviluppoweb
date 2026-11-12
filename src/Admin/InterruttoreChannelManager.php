<?php

declare(strict_types=1);

namespace ArcoDelVento\Admin;

use ArcoDelVento\App;

/**
 * L'interruttore del channel manager — FUNZIONE DI PROVA.
 *
 * Dal pannello di amministrazione (solo il super admin, /admin/channel-manager)
 * scollega il calendario iCal di Booking.com da tutte le camere, e lo
 * ricollega.
 *
 * Scollegato, l'importazione è sospesa e le notti importate da Booking si
 * tolgono: la disponibilità è quella del solo sito, come se su Booking nessuno
 * avesse prenotato. I link iCal delle camere restano salvati: ricollegando, si
 * rileggono subito. Le prenotazioni dirette e i blocchi manuali non si toccano
 * mai: si elencano, e cosa farne lo decide una persona.
 *
 * È isolata apposta, per toglierla quando non serve più. Si tolgono: questo
 * file, views/admin/channel-manager.php, la rotta «channel-manager» e il
 * metodo channelManager() in AdminController, la voce di menu in
 * views/admin/layout.php e la striscia «scollegato» nella bacheca e nel
 * calendario. Il README lo ripete, sotto «Interruttore del channel manager».
 */
final class InterruttoreChannelManager
{
    public function __construct(private readonly App $app)
    {
    }

    public function collegato(): bool
    {
        return !$this->app->calendari()->sospeso();
    }

    /** Da quando è scollegato, o null. */
    public function scollegatoDal(): ?string
    {
        return $this->app->calendari()->sospesoDal();
    }

    /**
     * Scollega: sospende l'importazione e toglie le notti importate.
     *
     * @return array{tolti: array<string,int>, restano: list<array<string,mixed>>}
     */
    public function scollega(): array
    {
        return ['tolti' => $this->app->calendari()->sospendi(), 'restano' => $this->restano()];
    }

    /**
     * Ricollega e rilegge subito tutti i calendari.
     *
     * @return array<string, array<string,mixed>|null> lo stato di ogni camera collegata, dopo la lettura
     */
    public function ricollega(): array
    {
        $calendari = $this->app->calendari();
        $calendari->riprendi();
        $calendari->aggiorna();
        $stati = [];
        foreach (array_keys($calendari->collegate()) as $ref) {
            $stati[$ref] = $calendari->stato($ref);
        }

        return $stati;
    }

    /**
     * Le notti occupate che non vengono dal channel manager: prenotazioni
     * dirette e blocchi manuali, da oggi in poi. Lo scollegamento non le tocca.
     *
     * @return list<array<string,mixed>>
     */
    public function restano(): array
    {
        $oggi = date('Y-m-d');
        $out = array_values(array_filter(
            $this->app->nottiOccupate(),
            static fn (array $n): bool => ($n['fonte'] ?? '') !== 'booking' && (string) $n['partenza'] > $oggi
        ));
        usort($out, static fn (array $a, array $b): int => [$a['ref'], $a['arrivo']] <=> [$b['ref'], $b['arrivo']]);

        return $out;
    }
}
