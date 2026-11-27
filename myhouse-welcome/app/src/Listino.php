<?php
namespace MHW;

/**
 * 9W · Correzione del 27 novembre 2026: i prezzi restano quelli di prima. La prima versione della migrazione 073 (pacchetto
 * 9W del 27 novembre) aveva abbassato il listino e messo in fila gli abbonamenti attivi per il prezzo nuovo; dove è già
 * girata, la 074 rimette in vendita le versioni di prima e scrive qui (storage/.listino-ripristino.json) gli abbonamenti già
 * spostati. Il giro dei richiami li riporta alla versione di prima, pochi alla volta (ogni abbonamento chiama Stripe), su
 * Stripe senza proporzioni: il periodo pagato resta, il rinnovo incassa il prezzo di prima.
 */
final class Listino
{
    public const FILE_RIPRISTINO = '/storage/.listino-ripristino.json';
    /** Quanti abbonamenti per giro: ognuno chiama Stripe tre volte. */
    public const PER_GIRO = 15;

    /** Gli abbonamenti ancora da riportare al prezzo di prima. @return list<int> */
    public static function inAttesa(): array
    {
        $f = MHW_APP . self::FILE_RIPRISTINO;
        return is_file($f) ? array_values(array_map(fn($v) => (int) ($v['s'] ?? 0), json_decode((string) @file_get_contents($f), true) ?: [])) : [];
    }

    /**
     * 9W · Correzione: chi era già passato al listino nuovo torna alla versione di prima (migrazione 074). Solo se
     * l'abbonamento è ancora dove l'aveva messo il listino; su Stripe senza proporzioni, come all'andata. @return int quanti
     */
    public static function ripristinaInSospeso(): int
    {
        $f = MHW_APP . self::FILE_RIPRISTINO;
        if (!is_file($f) || !Stripe::enabled()) return 0;
        $voci = json_decode((string) @file_get_contents($f), true) ?: [];
        $restano = array_slice($voci, self::PER_GIRO); $fatti = 0;
        foreach (array_slice($voci, 0, self::PER_GIRO) as $v) {
            $sub = Db::one('SELECT * FROM subscriptions WHERE id = ?', [(int) ($v['s'] ?? 0)]);
            $pv = Plans::version((int) ($v['pv'] ?? 0));
            if (!$sub || !$pv || (string) $sub['provider'] !== 'stripe' || in_array((string) $sub['status'], ['canceled', 'incomplete_expired', 'unpaid'], true)) continue;
            if ((string) $sub['status'] === 'past_due') { $restano[] = $v; continue; }
            $uid = (int) Db::val('SELECT user_id FROM accounts WHERE id = ?', [(int) $sub['account_id']], 0) ?: null;
            try {
                if (($v['tipo'] ?? '') === 'programmato' && (int) $sub['next_package_version_id'] === (int) $v['a']) {
                    CambioAbbonamento::programma($sub, $pv, (int) $v['q'], json_decode((string) ($sub['next_choices'] ?? ''), true) ?: []);
                } elseif (($v['tipo'] ?? '') === 'fatto' && (int) $sub['package_version_id'] === (int) $v['a'] && empty($sub['next_package_version_id'])) {
                    CambioAbbonamento::listino($sub, $pv, (int) $v['q']);
                } else continue;   // è cambiato qualcos'altro nel frattempo: non si tocca
                Auth::audit('listino.ripristinato', $uid, ['subscription' => (int) $sub['id'], 'a' => ['pv' => (int) $pv['id'], 'q' => (int) $v['q']], 'tipo' => $v['tipo']]);
                $fatti++;
            } catch (\Throwable $e) { Log::exception($e, 'listino: ripristino abbonamento ' . (int) $sub['id']); $restano[] = $v; }
        }
        if ($restano) @file_put_contents($f, json_encode(array_values($restano)), LOCK_EX); else @unlink($f);
        return $fatti;
    }
}
