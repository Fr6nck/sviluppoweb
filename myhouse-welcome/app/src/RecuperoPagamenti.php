<?php
namespace MHW;

/**
 * Dopo il pagamento la guida si attiva con il webhook firmato di Stripe. Se il webhook non arriva
 * (indirizzo sbagliato, segreto di un'altra modalità, un reindirizzamento, un firewall dell'hosting),
 * il sito chiede a Stripe, con la chiave segreta e da server a server, gli eventi di quella sessione
 * di pagamento e li elabora con Billing::handleEvent, come se fossero arrivati dal webhook: lo stesso
 * evento, la stessa idempotenza (webhook_events). Il browser non prova niente: dà solo il via.
 *
 * Quando: dalla pagina d'attesa dopo 20 secondi senza conferma (al massimo una richiesta ogni 15
 * secondi per ordine) e dai richiami periodici per gli ordini rimasti in sospeso (ultimi 3 giorni).
 *
 * Il registro storage/stripe-webhook.json tiene l'ultimo webhook accettato, l'ultimo rifiutato con il
 * motivo e l'ultimo recupero: li leggono Quadro e Diagnostica per dire perché il webhook non arriva.
 */
final class RecuperoPagamenti
{
    /** Gli eventi che chiudono una sessione di pagamento. */
    public const EVENTI = ['checkout.session.completed', 'checkout.session.async_payment_succeeded'];

    /** Secondi di attesa del webhook, nella pagina dopo il pagamento, prima di chiedere a Stripe. */
    public const ATTESA = 20;

    private static function file(): string { return MHW_APP . '/storage/stripe-webhook.json'; }

    /** @return array{accettato?:array,rifiutato?:array,recuperato?:array} */
    public static function registro(): array
    {
        $f = self::file();
        return is_file($f) ? (array) (json_decode((string) @file_get_contents($f), true) ?: []) : [];
    }

    private static function annota(string $chiave, array $dati): void
    {
        $r = self::registro();
        $r[$chiave] = $dati + ['quando' => Support::now()];
        @file_put_contents(self::file(), json_encode($r, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    }

    /** Un webhook con la firma giusta. */
    public static function accettato(string $tipo): void { self::annota('accettato', ['tipo' => $tipo]); }

    /** Un webhook rifiutato, con il motivo (senza firma, firma diversa, orario fuori tolleranza…). */
    public static function rifiutato(string $motivo, bool $firmato): void
    {
        // Al più 20 righe l'ora nel registro tecnico. Una richiesta senza firma non viene da Stripe
        // (un robot che prova l'indirizzo): solo nel registro tecnico, non nel Quadro.
        if (RateLimit::hit('webhook-rifiutato', 20, 3600)) Log::error('Webhook di Stripe rifiutato', ['motivo' => $motivo]);
        if ($firmato) self::annota('rifiutato', ['motivo' => $motivo]);
    }

    /**
     * Chiede a Stripe gli eventi della sessione dell'ordine e li elabora.
     * @return string l'esito: «niente», «attendi», «non-trovato», o quello di Billing::handleEvent
     */
    public static function ordine(array $o): string
    {
        $sessione = (string) ($o['provider_session_id'] ?? '');
        if (($o['status'] ?? '') !== 'pending' || $sessione === '' || !Stripe::enabled()) return 'niente';
        if (!RateLimit::hit('recupero:' . (int) $o['id'], 1, 15)) return 'attendi';
        // Gli eventi dal momento in cui è nato l'ordine (con 10 minuti di margine sugli orologi), al più 300.
        $dal = max(0, (int) strtotime((string) $o['created_at']) - 600);
        $dopo = '';
        for ($pagina = 0; $pagina < 3; $pagina++) {
            $p = ['types' => self::EVENTI, 'created' => ['gte' => $dal], 'limit' => 100] + ($dopo !== '' ? ['starting_after' => $dopo] : []);
            $res = Stripe::call('GET', 'events', $p);
            foreach ((array) ($res['data'] ?? []) as $ev) {
                if (!is_array($ev) || ($ev['data']['object']['id'] ?? '') !== $sessione) continue;
                $esito = Billing::handleEvent($ev);
                if ($esito !== 'gia-elaborato') {
                    Log::error('Pagamento confermato dal recupero: il webhook di Stripe non è arrivato', ['order' => (int) $o['id'], 'evento' => $ev['type'] ?? '', 'esito' => $esito]);
                    self::annota('recuperato', ['ordine' => (int) $o['id'], 'esito' => $esito]);
                }
                return $esito;
            }
            if (empty($res['has_more']) || empty($res['data'])) break;
            $ultimo = end($res['data']);
            $dopo = (string) ($ultimo['id'] ?? '');
            if ($dopo === '') break;
        }
        return 'non-trovato';
    }

    /** Dai richiami: gli ordini ancora in sospeso degli ultimi 3 giorni, fermi da almeno 2 minuti. */
    public static function inSospeso(?int $adesso = null): int
    {
        if (!Stripe::enabled()) return 0;
        $t = $adesso ?? time();
        $iso = fn(int $s) => gmdate('Y-m-d\TH:i:s\Z', $s);
        $n = 0;
        foreach (Db::all("SELECT * FROM orders WHERE status = 'pending' AND provider = 'stripe' AND provider_session_id <> ''
                          AND created_at > ? AND updated_at < ? ORDER BY id DESC LIMIT 20", [$iso($t - 3 * 86400), $iso($t - 120)]) as $o) {
            try {
                if (!in_array(self::ordine($o), ['niente', 'attendi', 'non-trovato'], true)) $n++;
            } catch (\Throwable $e) { Log::exception($e, 'recupero del pagamento ' . $o['id']); }
        }
        return $n;
    }

    /**
     * Per Quadro e Diagnostica: [titolo, testo] se il webhook ha un problema, altrimenti null.
     * Guarda solo gli ultimi 7 giorni: un rifiuto vecchio, seguito da webhook accettati, non conta.
     */
    public static function problema(): ?array
    {
        $r = self::registro();
        $quando = fn(string $k) => isset($r[$k]['quando']) ? (int) strtotime((string) $r[$k]['quando']) : 0;
        $ok = $quando('accettato'); $no = $quando('rifiutato'); $rec = $quando('recuperato');
        $recente = time() - 7 * 86400;
        $dove = 'Stripe → Sviluppatori → Webhook';
        if ($no > $recente && $no > $ok) {
            $motivo = (string) ($r['rifiutato']['motivo'] ?? '');
            return ['Stripe manda il webhook, ma il sito lo rifiuta', 'Ultimo rifiuto: ' . $motivo . '. '
                . (str_contains($motivo, 'orario') ? 'L\'orologio del server è sbagliato di più di 5 minuti: chiedi all\'hosting di sincronizzarlo.'
                   : 'Il «Segreto del webhook» in Impostazioni → Stripe deve essere il «Segreto di firma» (whsec_…) dell\'endpoint in ' . $dove
                     . ', nella stessa modalità (prova o reale) della chiave segreta. Le guide si attivano lo stesso con il recupero da Stripe, ma con qualche secondo di ritardo.')];
        }
        if ($rec > $recente && $rec > $ok) {
            return ['Il webhook di Stripe non arriva', 'L\'ultimo pagamento (ordine ' . (int) ($r['recuperato']['ordine'] ?? 0) . ') è stato confermato dal recupero da Stripe, non dal webhook. '
                . 'In ' . $dove . ' controlla che ci sia un endpoint verso ' . Support::baseUrl() . '/webhook/stripe, nella modalità della chiave segreta, con gli eventi giusti; '
                . 'apri l\'endpoint e guarda l\'esito degli ultimi invii (404: indirizzo sbagliato; 301 o 302: l\'indirizzo reindirizza, usa quello finale con https e www giusti; 403: un firewall dell\'hosting; 400: segreto sbagliato). «Prova la connessione» in Impostazioni → Stripe lo controlla.'];
        }
        return null;
    }

    /**
     * Per «Prova la connessione»: l'endpoint del webhook su Stripe (stessa modalità della chiave).
     * @return string una frase da aggiungere all'esito
     */
    public static function controllaEndpoint(): string
    {
        $atteso = rtrim(Support::baseUrl(), '/') . '/webhook/stripe';
        $norma = fn(string $u) => strtolower(rtrim(preg_replace('#/index\.php(?=/)#', '', $u), '/'));
        try { $lista = (array) (Stripe::call('GET', 'webhook_endpoints', ['limit' => 100])['data'] ?? []); }
        catch (\Throwable $e) { return ' Non ho potuto leggere i webhook su Stripe (' . $e->getMessage() . '): controlla a mano che ci sia un endpoint verso ' . $atteso . '.'; }
        $mio = null;
        foreach ($lista as $ep) if (is_array($ep) && $norma((string) ($ep['url'] ?? '')) === $norma($atteso)) { $mio = $ep; break; }
        if (!$mio) {
            $altri = array_filter(array_map(fn($ep) => (string) ($ep['url'] ?? ''), $lista));
            return ' ATTENZIONE: su Stripe, in questa modalità, non c\'è un webhook verso ' . $atteso . ($altri ? ' (ci sono: ' . implode(', ', $altri) . ')' : '')
                 . '. Senza, le guide si attivano solo con il recupero, in ritardo.';
        }
        if (($mio['status'] ?? 'enabled') !== 'enabled') return ' ATTENZIONE: il webhook verso ' . $atteso . ' su Stripe è disattivato: riattivalo.';
        $eventi = (array) ($mio['enabled_events'] ?? []);
        $mancano = in_array('*', $eventi, true) ? [] : array_diff(['checkout.session.completed', 'checkout.session.expired', 'invoice.paid', 'customer.subscription.updated', 'customer.subscription.deleted'], $eventi);
        return $mancano ? ' ATTENZIONE: al webhook su Stripe mancano gli eventi ' . implode(', ', $mancano) . '.'
                        : ' Il webhook verso ' . $atteso . ' c\'è, è attivo e ha gli eventi principali.';
    }
}
