<?php
namespace MHW;

/**
 * 9F·11 · La lingua del pannello: italiano, oppure inglese quando l'host scrive la guida in una lingua diversa
 * dall'italiano (lingua principale di una sua struttura), salvo l'interruttore «Pannello in: Italiano / English»
 * in Account (users.panel_lang). L'amministrazione resta in italiano.
 *
 * Le viste scrivono t('Testo italiano') (MHW\t): il dizionario lang/pannello-en.php dà l'inglese; una frase che
 * manca resta in italiano. Il catalogo delle sezioni (etichette, aiuti, opzioni) ha il suo file, lang/catalogo-en.php,
 * applicato da SectionCatalog::fields() e simili.
 */
final class Pannello
{
    private static ?string $lingua = null;
    private static ?array $dizionario = null;
    private static bool $area = false;

    /** 9N · Siamo in una pagina del pannello dell'host: i «Testi e foto» (TestiSito) seguono la lingua del pannello. */
    public static function entra(): void { self::$area = true; }

    /** 'en' nel pannello in inglese, altrimenti null (decide la pagina: LinguaSito). */
    public static function linguaTesti(): ?string { return self::$area && self::en() ? 'en' : null; }

    /** 'it' oppure 'en'. */
    public static function lingua(): string
    {
        if (self::$lingua !== null) return self::$lingua;
        $u = Auth::user();
        if (!$u || ($u['role'] ?? '') === 'admin') return self::$lingua = 'it';
        $acc = Auth::account();
        return self::$lingua = self::linguaDi($u, $acc ? (int) $acc['id'] : 0);
    }

    /** La lingua del pannello di un host qualunque (anche fuori dalla sua sessione: le email). */
    public static function linguaDi(array $u, int $accountId): string
    {
        $scelta = (string) ($u['panel_lang'] ?? '');
        if (in_array($scelta, ['it', 'en'], true)) return $scelta;
        // Di serie dalla lingua principale: una guida non in italiano → pannello in inglese.
        if ($accountId && Db::val("SELECT 1 FROM properties WHERE account_id = ? AND archived_at IS NULL AND is_demo < 2 AND default_locale <> 'it' LIMIT 1", [$accountId])) return 'en';
        return 'it';
    }

    public static function en(): bool { return self::lingua() === 'en'; }

    /** Per le prove e dopo un cambio: si ricalcola alla prossima chiamata. */
    public static function reset(): void { self::$lingua = null; }

    /** Imposta la scelta dell'host ('' = di serie dalla lingua principale). */
    public static function scegli(int $userId, string $lingua): void
    {
        if (!Migrator::columnExists('users', 'panel_lang')) return;
        Db::update('users', ['panel_lang' => in_array($lingua, ['it', 'en'], true) ? $lingua : ''], 'id = :id', ['id' => $userId]);
        self::reset();
    }

    public static function dizionario(): array
    {
        if (self::$dizionario === null) { $f = MHW_APP . '/lang/pannello-en.php'; self::$dizionario = is_file($f) ? (array) require $f : []; }
        return self::$dizionario;
    }

    /** Il testo nella lingua del pannello: l'inglese dal dizionario (o quello passato), altrimenti l'italiano. */
    public static function t(string $it, ?string $en = null): string
    {
        if (!self::en()) return $it;
        return $en ?? (self::dizionario()[$it] ?? $it);
    }

    /** 9N · Una frase nella lingua data ('it' o 'en'), per le email agli host fuori dalla loro sessione. */
    public static function tIn(string $lingua, string $it, array $sost = []): string
    {
        return strtr($lingua === 'en' ? (self::dizionario()[$it] ?? $it) : $it, $sost);
    }

    /** 9N · Una data nella lingua del pannello: 26 settembre 2026 / 26 September 2026. */
    public static function data(?string $iso): string
    {
        if (!self::en() || !$iso || !($t = strtotime($iso))) return Support::date($iso);
        return gmdate('j F Y', $t);
    }
}
