<?php
namespace MHW;

/**
 * 9Q · Il funnel «guida non pubblicata»: la checklist di una bozza e le due email (giorno 7 e giorno 21).
 *
 * La checklist è una sola (Nome e città · Check-in e check-out · Wi-Fi · Foto di copertina · CIN · Pubblica), calcolata dai
 * dati della struttura, con il passo della procedura dove si sistema ogni voce: la usano l'email del giorno 7 e la scheda
 * del cliente in Amministrazione. Il primo passo mancante è quello dove «ti sei fermato», con il consiglio giusto.
 *
 * Le email partono dal giro dei richiami (Richiami::esegui): giorno 7 → «bozza7», giorno 21 → «bozza21», una volta per
 * struttura (email_log); mai se l'account ha già una guida online, se ha detto «Non ora» o «Non scrivermi più su questo»
 * (email_optout, tipo «bozza»), nei 3 giorni dopo un'altra email del sito; mai a clienti di prova e vetrina (Richiami::manda).
 * Testi in «Testi e foto» (email.bozza7.*, email.bozza21.*), IT/EN.
 */
final class Bozze
{
    public const TIPI = ['bozza7', 'bozza21'];
    public const GIORNI = ['bozza7' => 7, 'bozza21' => 21];
    /** Fino a quanti giorni dopo l'email parte ancora (chi aggiorna l'applicazione non scrive alle bozze di mesi fa). */
    public const FINESTRA = 14;

    /**
     * La checklist di una struttura: [chiave, etichetta, fatto, passo della procedura]. $pannello: la variante delle schede
     * in «Le mie guide» (9N), dove al posto di «Pubblica» c'è «Lingue» (pubblicare è il bottone della scheda).
     * @return list<array{chiave:string,etichetta:string,fatto:bool,passo:string}>
     */
    public static function checklist(array $p, ?string $lingua = null, bool $pannello = false): array
    {
        $t = fn(string $it) => $lingua === null ? Pannello::t($it) : Pannello::tIn($lingua, $it);
        $core = Db::one('SELECT id FROM sections WHERE property_id = ? AND is_core = 1', [(int) $p['id']]);
        $dati = fn(?int $sid) => $sid ? (json_decode((string) Db::val('SELECT data FROM section_translations WHERE section_id = ? AND locale = ?', [$sid, (string) $p['default_locale']], ''), true) ?: []) : [];
        $arrivo = $dati($core ? (int) $core['id'] : null);
        $wifiS = Db::one("SELECT id, data FROM sections WHERE property_id = ? AND kind = 'wifi' AND is_active = 1", [(int) $p['id']]);
        $reti = $wifiS ? (array) ((json_decode((string) $wifiS['data'], true) ?: [])['networks'] ?? []) : [];
        $wifi = (bool) array_filter($reti, fn($r) => trim((string) ($r['ssid'] ?? '')) !== '');
        $lista = [
            ['chiave' => 'nome', 'etichetta' => $t('Nome e città'), 'fatto' => trim((string) $p['name']) !== '' && trim((string) ($p['city'] ?? '')) !== '', 'passo' => 'struttura'],
            ['chiave' => 'arrivo', 'etichetta' => $t('Check-in e check-out'), 'fatto' => (bool) array_filter((array) ($arrivo['checkin_steps'] ?? []), fn($x) => trim((string) $x) !== '') || trim((string) ($arrivo['checkin_note'] ?? '')) !== '', 'passo' => 'arrivo'],
            ['chiave' => 'wifi', 'etichetta' => $t('Wi-Fi'), 'fatto' => $wifi, 'passo' => 'sezioni'],
            ['chiave' => 'copertina', 'etichetta' => $t('Foto di copertina'), 'fatto' => !empty($p['cover_media_id']), 'passo' => 'aspetto'],
            ['chiave' => 'cin', 'etichetta' => 'CIN', 'fatto' => trim((string) ($p['cin'] ?? '')) !== '', 'passo' => 'struttura'],
        ];
        $lista[] = $pannello
            ? ['chiave' => 'lingue', 'etichetta' => $t('Lingue'), 'fatto' => (int) Db::val('SELECT COUNT(*) FROM property_locales WHERE property_id = ?', [(int) $p['id']], 0) > 1, 'passo' => '']
            : ['chiave' => 'pubblica', 'etichetta' => $t('Pubblica'), 'fatto' => ($p['status'] ?? '') === 'published', 'passo' => 'pubblica'];
        return $lista;
    }

    /** L'indirizzo nel pannello dove si completa una voce. */
    public static function dove(array $p, array $voce): string
    {
        if ($voce['chiave'] === 'lingue') return '/pannello/' . (int) $p['id'] . '/lingue';
        return '/pannello/' . (int) $p['id'] . '/procedura/' . $voce['passo'] . ($voce['chiave'] === 'cin' ? '#cin' : '');
    }

    /** La prima voce mancante (dove «ti sei fermato»). */
    public static function mancante(array $lista): ?array
    {
        foreach ($lista as $v) if (!$v['fatto']) return $v;
        return null;
    }

    /** Il consiglio per la voce mancante (Testi e foto → email.bozza7.consiglio.*). */
    public static function consiglio(string $chiave, string $lingua): string
    {
        $k = 'email.bozza7.consiglio.' . (in_array($chiave, ['nome', 'copertina', 'cin', 'wifi', 'arrivo'], true) ? $chiave : 'pubblica');
        return TestiSito::testoSemplice($k, [], $lingua === 'en' ? 'en' : 'it');
    }

    /** L'account ha già una guida online? (Portfolio: con una guida online, niente email sulle altre bozze.) */
    public static function haGuidaOnline(int $accountId): bool
    {
        return (bool) Db::val("SELECT id FROM properties WHERE account_id = ? AND is_demo = 0 AND status = 'published'", [$accountId]);
    }

    /** Il link «Scegli un orario» (Impostazioni → Sito), o il telefono dell'assistenza; null se non c'è nessuno dei due. */
    public static function chiamata(string $lingua): ?array
    {
        $en = $lingua === 'en';
        $link = trim((string) (Config::get('chiamata_url') ?? ''));
        if ($link !== '' && preg_match('#^https?://#', $link)) return [$en ? 'Pick a time' : 'Scegli un orario', $link];
        $tel = preg_replace('/[^\d+]/', '', (string) ((Config::get('legal') ?? [])['contact_phone'] ?? ''));
        return $tel !== '' ? [$en ? 'Call us' : 'Chiamaci', 'tel:' . $tel] : null;
    }

    /**
     * Il testo per Richiami::manda: [oggetto, corpo, bottone, indirizzo, nota, scelte]. «{token}» negli indirizzi è il token
     * dell'email (lo sostituisce manda): «Non ora» e «Non scrivermi più» non chiedono di accedere.
     */
    public static function testo(string $tipo, array $p, string $lingua): array
    {
        $en = $lingua === 'en';
        $s = ['struttura' => (string) $p['name']];
        $tx = function (string $k) use (&$s, $en): string { return TestiSito::testoSemplice($k, $s, $en ? 'en' : 'it'); };   // $s cresce sotto ({strade})
        $codice = Codici::cliente((int) $p['account_id']);   // il piede dell'email lo aggiunge da solo (Mailer); qui serve per l'oggetto della risposta
        if ($tipo === 'bozza7') {
            $lista = self::checklist($p, $lingua);
            $manca = self::mancante($lista);
            $righe = array_map(fn($v) => ($v['fatto'] ? '✓ ' : '○ ') . $v['etichetta'] . ($manca && $v['chiave'] === $manca['chiave'] ? ' ← ' . ($en ? 'you stopped here' : 'ti sei fermato qui') : ''), $lista);
            $corpo = $tx('email.bozza7.intro') . "\n\n" . implode("\n", $righe) . "\n\n" . $tx('email.bozza7.vantaggio');
            if ($manca) $corpo .= "\n\n" . ($en ? 'A tip: ' : 'Un consiglio: ') . self::consiglio($manca['chiave'], $lingua);
            $dove = $manca ? self::dove($p, $manca) : '/pannello/' . (int) $p['id'] . '/procedura/pubblica';
            [$percorso, $ancora] = array_pad(explode('#', $dove, 2), 2, '');
            return [$tx('email.bozza7.oggetto'), $corpo, $en ? 'Pick up where you left off →' : 'Riprendi da dove eri →',
                    Support::baseUrl() . $percorso . '?da=bozza7' . ($ancora !== '' ? '#' . $ancora : ''), $tx('email.bozza.ignora')];
        }
        // Giorno 21: tre strade (la finiamo noi, quindici minuti insieme, non ora).
        $sub = Subscriptions::active((int) $p['account_id']);
        $chiama = self::chiamata($lingua);
        $s['strade'] = $chiama ? ($en ? 'three' : 'tre') : ($en ? 'two' : 'due');
        $corpo = $tx('email.bozza21.intro') . "\n\n" . '1 · ' . $tx('email.bozza21.noi');
        if ($chiama) $corpo .= "\n\n" . '2 · ' . $tx('email.bozza21.chiamata');
        $corpo .= "\n\n" . ($chiama ? '3' : '2') . ' · ' . $tx('email.bozza21.nonora');
        // Chi è nei mesi gratis di un omaggio non ha ancora pagato: per lui nessuna delle due righe.
        if (!$sub || $sub['status'] !== 'trialing') $corpo .= "\n\n" . ($sub ? strtr($tx('email.bozza21.pagato'), ['{dal}' => self::data((string) $sub['current_period_start'], $en), '{al}' => self::data((string) $sub['current_period_end'], $en)])
                                 : $tx('email.bozza21.gratis'));
        $oggettoMail = ($en ? 'Please finish the guide for ' : 'Finite voi la guida di ') . $p['name'] . ($codice !== '' ? ' · ' . $codice : '');
        $scelte = [];
        if ($chiama) $scelte[] = [$chiama[0], str_starts_with($chiama[1], 'tel:') ? $chiama[1] : Support::baseUrl() . '/bozza/chiamata/{token}'];
        $scelte[] = [$en ? 'Not now' : 'Non ora', Support::baseUrl() . '/bozza/non-ora/{token}'];
        return [$tx('email.bozza21.oggetto'), $corpo, $en ? 'Reply and we finish it' : 'Rispondi e la finiamo noi',
                'mailto:' . Support::contatto() . '?subject=' . rawurlencode($oggettoMail), null, $scelte];
    }

    private static function data(string $iso, bool $en): string { return $en && ($t = strtotime($iso)) ? gmdate('j F Y', $t) : Support::date($iso); }

    /** «Bozze da più di 21 giorni» (Amministrazione → Clienti e Quadro): strutture mai pubblicate create da più di 21 giorni, account senza guide online. */
    public static function ferme(int $giorni = 21): array
    {
        $prova = Migrator::columnExists('accounts', 'is_test') ? ' AND a.is_test = 0' : '';
        return Db::all("SELECT p.id, p.name, p.account_id, p.created_at FROM properties p JOIN accounts a ON a.id = p.account_id JOIN users u ON u.id = a.user_id
                        WHERE p.is_demo = 0 AND p.archived_at IS NULL AND p.status <> 'published' AND (p.published_at IS NULL OR p.published_at = '') AND p.created_at <= ?
                          AND u.email NOT LIKE ?$prova AND NOT EXISTS (SELECT 1 FROM properties q WHERE q.account_id = p.account_id AND q.status = 'published' AND q.is_demo = 0)
                        ORDER BY p.created_at", [gmdate('Y-m-d\TH:i:s\Z', time() - $giorni * 86400), '%@' . Demo::DOMINIO]);
    }
}
