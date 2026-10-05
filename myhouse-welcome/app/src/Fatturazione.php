<?php
namespace MHW;

/**
 * I dati di fatturazione italiani dell'account: si chiedono prima del primo
 * pagamento e vanno a Stripe come metadati del cliente (vat, cf, sdi, pec).
 * Le fatture non le genera l'applicazione: le crea Adamo, collegato a Stripe
 * (Adamo → Impostazioni → Integrazioni → Stripe). Adamo legge dal cliente Stripe
 * nome, indirizzo e partita IVA, e dai metadati Fiscal_code, Pec e Fe_code:
 * per questo gli stessi dati partono anche con quei nomi.
 *
 *   azienda — azienda o professionista: partita IVA obbligatoria, codice
 *             fiscale facoltativo (16 caratteri, o 11 cifre per le società),
 *             codice destinatario SDI o PEC obbligatori (uno dei due)
 *   privato — codice fiscale obbligatorio (16 caratteri), SDI e PEC facoltativi
 *
 * Per tutti: intestatario, indirizzo, CAP, città, provincia.
 */
final class Fatturazione
{
    public const TIPI = ['azienda' => 'Azienda o professionista', 'privato' => 'Privato'];
    public const CAMPI = ['billing_type', 'billing_name', 'vat', 'cf', 'sdi', 'pec', 'billing_address', 'billing_postal', 'billing_city', 'billing_province'];

    /** La partita IVA italiana: 11 cifre, l'ultima di controllo. */
    public static function partitaIvaValida(string $v): bool
    {
        if (!preg_match('/^\d{11}$/', $v) || $v === '00000000000') return false;
        $s = 0;
        for ($i = 0; $i < 10; $i++) {
            $d = (int) $v[$i];
            if ($i % 2 === 1) { $d *= 2; if ($d > 9) $d -= 9; }
            $s += $d;
        }
        return (10 - $s % 10) % 10 === (int) $v[10];
    }

    /** Il codice fiscale di una persona: 16 caratteri, l'ultimo di controllo (anche con le omocodie). */
    public static function codiceFiscaleValido(string $v): bool
    {
        if (!preg_match('/^[A-Z]{6}[0-9LMNPQRSTUV]{2}[A-EHLMPR-T][0-9LMNPQRSTUV]{2}[A-Z][0-9LMNPQRSTUV]{3}[A-Z]$/', $v)) return false;
        $dispari = [0 => 1, 1 => 0, 2 => 5, 3 => 7, 4 => 9, 5 => 13, 6 => 15, 7 => 17, 8 => 19, 9 => 21, 10 => 2, 11 => 4, 12 => 18, 13 => 20,
                    14 => 11, 15 => 3, 16 => 6, 17 => 8, 18 => 12, 19 => 14, 20 => 16, 21 => 10, 22 => 22, 23 => 25, 24 => 24, 25 => 23];
        $s = 0;
        for ($i = 0; $i < 15; $i++) {
            $c = $v[$i];
            $n = ctype_digit($c) ? (int) $c : ord($c) - 65;
            $s += $i % 2 === 0 ? $dispari[$n] : $n;
        }
        return chr(65 + $s % 26) === $v[15];
    }

    /**
     * Pulisce e controlla quello che arriva dal modulo.
     * @return array{0:array<string,string>,1:array<string,string>} [dati puliti, errori per campo]
     */
    public static function valida(array $in): array
    {
        $su = fn(string $k) => strtoupper(preg_replace('/\s+/', '', (string) ($in[$k] ?? '')));
        $d = [
            'billing_type' => isset(self::TIPI[$in['billing_type'] ?? '']) ? (string) $in['billing_type'] : '',
            'billing_name' => mb_substr(trim((string) ($in['billing_name'] ?? '')), 0, 160),
            'vat' => preg_replace('/^IT/', '', $su('vat')),
            'cf' => $su('cf'),
            'sdi' => $su('sdi'),
            'pec' => mb_strtolower(trim((string) ($in['pec'] ?? ''))),
            'billing_address' => mb_substr(trim((string) ($in['billing_address'] ?? '')), 0, 255),
            'billing_postal' => preg_replace('/\s+/', '', (string) ($in['billing_postal'] ?? '')),
            'billing_city' => mb_substr(trim((string) ($in['billing_city'] ?? '')), 0, 120),
            'billing_province' => $su('billing_province'),
        ];
        $e = [];
        if ($d['billing_type'] === '') $e['billing_type'] = 'Scegli se fatturiamo a un\'azienda o a un privato.';
        $azienda = $d['billing_type'] === 'azienda';
        if ($d['billing_name'] === '') $e['billing_name'] = $azienda ? 'Scrivi la ragione sociale o il tuo nome.' : 'Scrivi nome e cognome.';
        if ($azienda && !self::partitaIvaValida($d['vat'])) {
            $e['vat'] = $d['vat'] === '' ? 'Scrivi la partita IVA.' : 'Questa partita IVA non torna: sono 11 cifre, controlla di averle scritte tutte.';
        } elseif (!$azienda && $d['vat'] !== '' && !self::partitaIvaValida($d['vat'])) {
            $e['vat'] = 'Questa partita IVA non torna: sono 11 cifre.';
        }
        if ($d['cf'] !== '') {
            $ok = self::codiceFiscaleValido($d['cf']) || ($azienda && self::partitaIvaValida($d['cf']));
            if (!$ok) $e['cf'] = $azienda ? 'Il codice fiscale è di 16 caratteri, o di 11 cifre per le società.' : 'Questo codice fiscale non torna: sono 16 caratteri.';
        } elseif (!$azienda && $d['billing_type'] !== '') {
            $e['cf'] = 'Scrivi il codice fiscale.';
        }
        if ($d['sdi'] !== '' && !preg_match('/^[A-Z0-9]{7}$/', $d['sdi'])) $e['sdi'] = 'Il codice destinatario è di 7 caratteri, lettere e numeri.';
        if ($d['pec'] !== '' && !filter_var($d['pec'], FILTER_VALIDATE_EMAIL)) $e['pec'] = 'Questo indirizzo PEC non sembra valido.';
        if ($azienda && $d['sdi'] === '' && $d['pec'] === '') $e['sdi'] = 'Serve il codice destinatario SDI oppure la PEC.';
        if ($d['billing_address'] === '') $e['billing_address'] = 'Scrivi l\'indirizzo.';
        if (!preg_match('/^\d{5}$/', $d['billing_postal'])) $e['billing_postal'] = 'Il CAP è di 5 cifre.';
        if ($d['billing_city'] === '') $e['billing_city'] = 'Scrivi la città.';
        if (!preg_match('/^[A-Z]{2}$/', $d['billing_province'])) $e['billing_province'] = 'La provincia è la sigla di 2 lettere, per esempio PG.';
        return [$d, $e];
    }

    /** I dati salvati bastano per pagare? */
    public static function completa(array $account): bool
    {
        if (!array_key_exists('billing_type', $account)) return true;   // database non ancora aggiornato: non si blocca
        return self::valida($account)[1] === [];
    }

    /** I metadati per il cliente Stripe: solo quelli compilati. */
    public static function metadati(array $account): array
    {
        $m = [];
        foreach (['vat', 'cf', 'sdi', 'pec', 'billing_type'] as $k) {
            if (trim((string) ($account[$k] ?? '')) !== '') $m['metadata[' . $k . ']'] = (string) $account[$k];
        }
        // Per Adamo (fattura elettronica): codice fiscale (per un'azienda senza, la partita IVA),
        // PEC, e codice destinatario: senza SDI vale 0000000 (la fattura arriva nel cassetto
        // fiscale del cliente, o alla PEC se c'è).
        $tipo = (string) ($account['billing_type'] ?? '');
        if ($tipo !== '') {
            $cf = trim((string) ($account['cf'] ?? '')) ?: ($tipo === 'azienda' ? trim((string) ($account['vat'] ?? '')) : '');
            if ($cf !== '') $m['metadata[Fiscal_code]'] = strtoupper($cf);
            if (trim((string) ($account['pec'] ?? '')) !== '') $m['metadata[Pec]'] = trim((string) $account['pec']);
            $m['metadata[Fe_code]'] = trim((string) ($account['sdi'] ?? '')) !== '' ? strtoupper(trim((string) $account['sdi'])) : '0000000';
        }
        return $m;
    }

    /** Nome e indirizzo per il cliente Stripe (sulla fattura che emette Stripe). */
    public static function anagrafica(array $account): array
    {
        if (trim((string) ($account['billing_name'] ?? '')) === '') return [];
        return [
            'name' => (string) $account['billing_name'],
            'address[line1]' => (string) $account['billing_address'], 'address[postal_code]' => (string) $account['billing_postal'],
            'address[city]' => (string) $account['billing_city'], 'address[state]' => (string) $account['billing_province'],
            'address[country]' => 'IT',
        ];
    }
}
