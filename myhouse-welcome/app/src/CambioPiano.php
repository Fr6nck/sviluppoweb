<?php
namespace MHW;

/**
 * Il cambio di piano di un abbonamento attivo. Qui ci sono solo le regole e i
 * conti, senza database e senza rete: così si possono provare da soli.
 *
 * Una regola sola, per piani e numero di strutture:
 *   - il prezzo annuale SALE  → si paga oggi la differenza per i giorni che
 *     restano fino al rinnovo, e il cambio vale appena il pagamento è confermato;
 *   - il prezzo annuale SCENDE → nessun rimborso e nessun addebito: il cambio
 *     parte dal prossimo rinnovo, fino ad allora resta il piano pagato.
 */
final class CambioPiano
{
    public const SALE = 'sale';
    public const SCENDE = 'scende';
    public const UGUALE = 'uguale';
    /** Sotto questa cifra (in centesimi) il conguaglio non si fa pagare: il cambio è gratuito. */
    public const MINIMO = 100;

    /** Che cosa succede passando da un prezzo annuale all'altro (in centesimi). */
    public static function tipo(int $annuoAttuale, int $annuoNuovo): string
    {
        return $annuoNuovo <=> $annuoAttuale ? ($annuoNuovo > $annuoAttuale ? self::SALE : self::SCENDE) : self::UGUALE;
    }

    /** I giorni del periodo pagato (365 o 366). */
    public static function giorniTotali(int $inizio, int $fine): int
    {
        return max(1, (int) round(($fine - $inizio) / 86400));
    }

    /** I giorni che restano fino al rinnovo, contando quello di oggi. Mai più del periodo, mai meno di zero. */
    public static function giorniRestanti(int $inizio, int $fine, int $adesso): int
    {
        if ($adesso >= $fine) return 0;
        return min(self::giorniTotali($inizio, $fine), (int) ceil(($fine - max($adesso, $inizio)) / 86400));
    }

    /**
     * Il conguaglio da pagare oggi, in centesimi e senza IVA: la differenza
     * tra i due prezzi annuali, in proporzione ai giorni che restano.
     * Zero se il prezzo non sale o se la cifra è sotto il minimo.
     */
    public static function conguaglio(int $annuoAttuale, int $annuoNuovo, int $inizio, int $fine, int $adesso): int
    {
        $diff = $annuoNuovo - $annuoAttuale;
        if ($diff <= 0) return 0;
        $cents = (int) round($diff * self::giorniRestanti($inizio, $fine, $adesso) / self::giorniTotali($inizio, $fine));
        return $cents < self::MINIMO ? 0 : $cents;
    }

    /**
     * Le voci da mandare a Stripe (POST /v1/subscriptions/{id}) per portare
     * l'abbonamento al piano nuovo. Si usa sempre con proration_behavior=none:
     * i soldi sono già stati incassati a parte (salita) o non sono dovuti (discesa).
     *
     * @param array  $vociAttuali   items.data dell'abbonamento letto da Stripe
     * @param string $idVoceExtra   l'id della voce «strutture aggiuntive», o '' se non c'è
     * @param string $prezzoBase    il Price del piano nuovo
     * @param string $prezzoExtra   il Price della struttura aggiuntiva del piano nuovo ('' se il piano non è a strutture)
     * @param int    $quantita      il numero di strutture del piano nuovo (1 per i piani singoli)
     * @return array<string,string> i parametri items[...] pronti per Stripe::call
     */
    public static function voci(array $vociAttuali, string $idVoceExtra, string $prezzoBase, string $prezzoExtra, int $quantita): array
    {
        $base = '';
        foreach ($vociAttuali as $v) {
            $id = (string) ($v['id'] ?? '');
            if ($id !== '' && $id !== $idVoceExtra) { $base = $id; break; }
        }
        if ($base === '') throw new \RuntimeException('Abbonamento senza la voce principale.');
        $p = ['items[0][id]' => $base, 'items[0][price]' => $prezzoBase, 'items[0][quantity]' => '1'];
        $extra = $prezzoExtra !== '' ? max(0, $quantita - 1) : 0;
        if ($extra > 0) {
            if ($idVoceExtra !== '') $p['items[1][id]'] = $idVoceExtra;
            $p['items[1][price]'] = $prezzoExtra;
            $p['items[1][quantity]'] = (string) $extra;
        } elseif ($idVoceExtra !== '') {
            $p['items[1][id]'] = $idVoceExtra;
            $p['items[1][deleted]'] = 'true';
        }
        return $p;
    }

    /**
     * Quali sezioni restano attive quando il piano nuovo ne comprende meno:
     * prima quelle scelte dall'host, poi le altre nell'ordine in cui sono, fino al limite.
     *
     * @param int[] $attive  gli id delle sezioni attive (non quella fissa), nell'ordine della guida
     * @param int[] $scelte  gli id che l'host ha chiesto di tenere
     * @return array{tenere:int[],spegnere:int[]}
     */
    public static function sezioni(array $attive, array $scelte, int $limite): array
    {
        $attive = array_values(array_map('intval', $attive));
        if ($limite >= count($attive)) return ['tenere' => $attive, 'spegnere' => []];
        $prima = array_values(array_intersect(array_map('intval', $scelte), $attive));
        $tenere = array_slice(array_values(array_unique(array_merge($prima, $attive))), 0, max(0, $limite));
        return ['tenere' => array_values(array_intersect($attive, $tenere)), 'spegnere' => array_values(array_diff($attive, $tenere))];
    }
}
