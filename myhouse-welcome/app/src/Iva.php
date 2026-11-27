<?php
namespace MHW;

/**
 * 9O · I prezzi IVA inclusa per i privati.
 *
 * I prezzi del sito sono IVA esclusa («+ IVA»). L'interruttore «Sono un privato: mostra i prezzi IVA inclusa» (Testi e foto
 * → prezzi.iva_privati) li mostra con l'IVA: 97 € → 118,34 €, con sotto «di cui IVA 22%: 21,34 €». È spento di serie; si
 * ricorda nel browser (localStorage, assets/iva.js) e in sessione (POST /prezzi/iva, anche senza JavaScript).
 *
 * Ogni cifra porta l'imponibile in data-iva-netto: lo script la riscrive quando l'interruttore cambia, e gli script che
 * ricalcolano i prezzi (prezzi.js, questionario.js) aggiornano l'attributo e chiamano mhwIva.aggiorna(). Il conto è uno
 * solo, qui e nello script: IVA = imponibile × aliquota / 100 arrotondata al centesimo, come la calcola Stripe Tax sul
 * totale scontato. L'aliquota viene da Impostazioni → Sito (iva_percentuale, di serie 22). I dati strutturati restano
 * imponibili (valueAddedTaxIncluded = false).
 */
final class Iva
{
    public static function aliquota(): int
    {
        $a = (int) (Config::get('iva_percentuale') ?? 0);
        return $a > 0 && $a <= 100 ? $a : 22;
    }

    /** L'IVA di un imponibile (centesimi). */
    public static function di(int $netto): int { return (int) round($netto * self::aliquota() / 100); }

    /** Il totale con l'IVA. */
    public static function lordo(int $netto): int { return $netto + self::di($netto); }

    /** L'interruttore è acceso per questa visita? (solo la sessione: senza sessione aperta, spento; il browser fa il resto). */
    public static function inclusa(): bool { return session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['iva_inclusa']); }

    public static function imposta(bool $si): void { $_SESSION['iva_inclusa'] = $si ? 1 : 0; }

    /** Una cifra: l'imponibile (o il lordo, con l'interruttore acceso) con l'attributo per lo script. HTML sicuro. */
    public static function cifra(int $netto, string $valuta = 'EUR', string $attributi = '', string $tag = 'span'): string
    {
        return '<' . $tag . ($attributi !== '' ? ' ' . $attributi : '') . ' data-iva-netto="' . $netto . '">'
             . Support::e(Support::money(self::inclusa() ? self::lordo($netto) : $netto, $valuta)) . '</' . $tag . '>';
    }

    /**
     * Il «+ IVA / anno» accanto al prezzo: con l'interruttore acceso diventa «IVA inclusa / anno». Il testo IVA esclusa è quello
     * dentro lo span (lo script lo ricorda), quello IVA inclusa sta in data-lordo. HTML sicuro.
     */
    public static function nota(string $netto, string $lordo): string
    {
        return self::inclusa() ? '<span data-iva-nota data-netto="' . Support::e($netto) . '" data-lordo="' . Support::e($lordo) . '">' . Support::e($lordo) . '</span>'
                               : '<span data-iva-nota data-lordo="' . Support::e($lordo) . '">' . Support::e($netto) . '</span>';
    }

    /** «di cui IVA 22%: 21,34 €» sotto un prezzo principale: visibile solo con l'interruttore acceso. HTML sicuro. */
    public static function dettaglio(int $netto, bool $en, string $valuta = 'EUR'): string
    {
        $et = ($en ? 'of which VAT ' : 'di cui IVA ') . self::aliquota() . '%: ';
        return '<small class="iva-dettaglio" data-iva-dettaglio="' . $netto . '" data-etichetta="' . Support::e($et) . '"' . (self::inclusa() ? '' : ' hidden') . '>'
             . Support::e($et . Support::money(self::di($netto), $valuta)) . '</small>';
    }

    /**
     * L'interruttore: una casella vera dentro un modulo (senza JavaScript «Applica» salva in sessione e ricarica la pagina;
     * con JavaScript cambia subito i prezzi). $torna: la pagina dove tornare.
     */
    public static function interruttore(bool $en, string $torna): string
    {
        $id = 'iva-' . substr(md5($torna . mt_rand()), 0, 6);
        $testo = TestiSito::testo('prezzi.iva_privati') ?: 'Sono un privato: mostra i prezzi IVA inclusa';
        if ($en) $testo = Support::e(TestiSito::get('prezzi.iva_privati', 'en'));
        return '<form method="post" action="' . b() . '/prezzi/iva" class="iva-interruttore" data-iva-interruttore data-aliquota="' . self::aliquota() . '">'
             . Csrf::field() . '<input type="hidden" name="torna" value="' . Support::e($torna) . '">'
             . '<label class="check" for="' . $id . '"><input type="checkbox" id="' . $id . '" name="si" value="1"' . (self::inclusa() ? ' checked' : '') . '> <span>' . $testo . '</span></label>'
             . ' <button class="btn btn--quiet btn--sm iva-interruttore__applica">' . ($en ? 'Apply' : 'Applica') . '</button></form>';
    }
}
