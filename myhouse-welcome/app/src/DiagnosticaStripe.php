<?php
namespace MHW;

/**
 * 9O · Amministrazione → Diagnostica, sezione «Stripe»: la configurazione di Stripe letta con la chiave del sito.
 *
 * Ogni voce è «ok», «sistemare» (da sistemare) o «manuale» (da controllare a mano), con il link alla pagina di Stripe:
 * Stripe Tax e la registrazione italiana; il webhook (indirizzo del sito, eventi richiesti, segreto); il portale clienti
 * (niente cambio di piano né di dati, disdetta a fine periodo); i Price del listino (Stripe::verificaPrezzi); i metodi di
 * pagamento adatti ai rinnovi; Adaptive Pricing e le email di Stripe ai clienti, che l'API non dice: si confermano a mano
 * («Confermo» salva la data). L'esito resta in cache un'ora (storage/.diagnostica-stripe.json); «Ricontrolla» lo rifà.
 */
final class DiagnosticaStripe
{
    /** Gli eventi che il webhook deve mandare (Impostazioni → Stripe, DA-CONFIGURARE). */
    public const EVENTI = ['checkout.session.completed', 'checkout.session.expired', 'checkout.session.async_payment_succeeded', 'checkout.session.async_payment_failed',
                           'invoice.paid', 'invoice.payment_failed', 'customer.subscription.updated', 'customer.subscription.deleted',
                           'customer.subscription.trial_will_end', 'charge.refunded'];
    /** Consigliato (i rimborsi falliti dopo la conferma): se manca la voce lo dice, ma resta «ok». */
    public const CONSIGLIATI = ['charge.refund.updated'];
    /** I metodi adatti ai rinnovi automatici (i wallet sono carte: Apple Pay e Google Pay). */
    public const METODI_OK = ['card', 'link', 'sepa_debit', 'apple_pay', 'google_pay'];
    public const DURATA = 3600;

    private static function file(): string { return MHW_APP . '/storage/.diagnostica-stripe.json'; }
    private static function fileConferme(): string { return MHW_APP . '/storage/.stripe-controlli.json'; }

    /** L'indirizzo di una pagina della dashboard di Stripe (in modalità di prova con /test). */
    public static function link(string $pagina): string
    {
        return 'https://dashboard.stripe.com' . (Impostazioni::modoStripe() === 'test' ? '/test' : '') . '/' . ltrim($pagina, '/');
    }

    /** Le date dei controlli fatti a mano (email ai clienti, Adaptive Pricing). */
    public static function conferme(): array
    {
        $f = self::fileConferme();
        return is_file($f) ? (json_decode((string) @file_get_contents($f), true) ?: []) : [];
    }

    public static function conferma(string $voce): void
    {
        if (!in_array($voce, ['email', 'adaptive'], true)) return;
        $c = self::conferme(); $c[$voce] = Support::now();
        @file_put_contents(self::fileConferme(), json_encode($c));
        @unlink(self::file());
    }

    /**
     * Le voci, dalla cache se ha meno di un'ora (o rifatte con $forza). @return array{quando:string,voci:array<int,array{titolo:string,stato:string,dettaglio:string,link:string,conferma?:string}>}
     */
    public static function voci(bool $forza = false): array
    {
        $f = self::file();
        if (!$forza && is_file($f) && ($c = json_decode((string) @file_get_contents($f), true)) && time() - (int) strtotime((string) ($c['quando'] ?? '')) < self::DURATA) return $c;
        $r = ['quando' => Support::now(), 'voci' => self::controlla()];
        @file_put_contents($f, json_encode($r, JSON_UNESCAPED_UNICODE));
        return $r;
    }

    /** I controlli veri (rete). Una chiamata che non riesce diventa «da sistemare» con l'errore, mai un'eccezione. */
    public static function controlla(): array
    {
        if (!Stripe::enabled()) return [['titolo' => 'Chiavi di Stripe', 'stato' => 'sistemare', 'dettaglio' => 'Mancano la chiave segreta o il segreto del webhook (Impostazioni → Stripe).', 'link' => self::link('apikeys')]];
        $voci = [];
        $prova = function (string $titolo, string $link, callable $fn) use (&$voci): void {
            try { [$stato, $dettaglio] = $fn(); }
            catch (\Throwable $e) { $stato = 'sistemare'; $dettaglio = 'Stripe non ha risposto: ' . $e->getMessage(); }
            $voci[] = ['titolo' => $titolo, 'stato' => $stato, 'dettaglio' => $dettaglio, 'link' => $link];
        };

        $prova('Stripe Tax e registrazione in Italia', self::link('settings/tax'), function (): array {
            $imp = Stripe::call('GET', 'tax/settings');
            $attivo = ($imp['status'] ?? '') === 'active';
            $it = array_filter((array) (Stripe::call('GET', 'tax/registrations', ['limit' => '100'])['data'] ?? []), fn($x) => ($x['country'] ?? '') === 'IT' && ($x['status'] ?? '') === 'active');
            $auto = !empty(Config::get('stripe')['automatic_tax']);
            if ($attivo && $it && $auto) return ['ok', 'Stripe Tax attivo, registrazione IT attiva, il sito chiede il calcolo automatico dell\'IVA a ogni pagamento.'];
            return ['sistemare', implode(' ', array_filter([
                $attivo ? '' : 'Stripe Tax non è attivo (stato: ' . ((string) ($imp['status'] ?? '—')) . ').',
                $it ? '' : 'Manca la registrazione IVA per l\'Italia (Tax → Registrations).',
                $auto ? '' : 'Nel sito il calcolo automatico dell\'IVA è spento (stripe.automatic_tax).']))];
        });

        $prova('Webhook', self::link('webhooks'), function (): array {
            $url = rtrim(Support::baseUrl(), '/') . '/webhook/stripe';
            $trovato = null;
            foreach ((array) (Stripe::call('GET', 'webhook_endpoints', ['limit' => '100'])['data'] ?? []) as $w) if (($w['url'] ?? '') === $url) $trovato = $w;
            if (!$trovato) return ['sistemare', 'Nessun endpoint punta a ' . $url . '.'];
            $ev = (array) ($trovato['enabled_events'] ?? []);
            $tutti = in_array('*', $ev, true);
            $mancano = $tutti ? [] : array_values(array_diff(self::EVENTI, $ev));
            $consigliati = $tutti ? [] : array_values(array_diff(self::CONSIGLIATI, $ev));
            $segreto = (string) (Config::get('stripe')['webhook_secret'] ?? '') !== '';
            $attivo = ($trovato['status'] ?? 'enabled') === 'enabled';
            if (!$mancano && $segreto && $attivo) return ['ok', 'Endpoint ' . $url . ' attivo, con tutti i ' . count(self::EVENTI) . ' eventi richiesti e il segreto nel sito.'
                                                         . ($consigliati ? ' Consigliato anche: ' . implode(', ', $consigliati) . '.' : '')];
            return ['sistemare', implode(' ', array_filter([$attivo ? '' : 'L\'endpoint è disattivato.', $mancano ? 'Mancano gli eventi: ' . implode(', ', $mancano) . '.' : '',
                                                          $segreto ? '' : 'Il segreto del webhook non è nel sito (Impostazioni → Stripe).']))];
        });

        $prova('Portale clienti', self::link('settings/billing/portal'), function (): array {
            $c = (array) ((Stripe::call('GET', 'billing_portal/configurations', ['is_default' => 'true', 'limit' => '1'])['data'] ?? [])[0] ?? []);
            if (!$c) return ['sistemare', 'Nessuna configurazione del portale: il bottone «Cambia il metodo di pagamento» non funziona.'];
            $f = (array) ($c['features'] ?? []);
            $problemi = array_filter([
                !empty($f['subscription_update']['enabled']) ? 'il cambio di piano o di quantità è acceso (va spento: si cambia dal sito)' : '',
                !empty($f['subscription_cancel']['enabled']) && ($f['subscription_cancel']['mode'] ?? '') !== 'at_period_end' ? 'la disdetta è immediata (va «a fine periodo»)' : '',
                !empty($f['customer_update']['enabled']) ? 'il cliente può cambiare i dati di fatturazione (va spento: li cambia nel sito, che li manda ad Adamo)' : '',
            ]);
            return $problemi ? ['sistemare', ucfirst(implode('; ', $problemi)) . '.'] : ['ok', 'Cambio di piano e dati spenti, disdetta a fine periodo.'];
        });

        $prova('Prezzi su Stripe', self::link('products'), function (): array {
            $msg = []; $ko = false; $controllati = 0;
            foreach (Plans::public() as $pv) {
                if ((string) ($pv['stripe_price_id'] ?? '') === '' && (string) ($pv['stripe_extra_price_id'] ?? '') === '') continue;
                $controllati++;
                try { Stripe::verificaPrezzi($pv); }
                catch (\Throwable $e) { $ko = true; $msg[] = (string) ($pv['name'] ?? $pv['id']) . ': ' . $e->getMessage(); }
            }
            if ($ko) return ['sistemare', implode(' · ', $msg)];
            return ['ok', $controllati ? $controllati . ' versioni del listino controllate: importi, anno, IVA esclusa, scaglioni graduati.' : 'Il sito descrive i prezzi a ogni pagamento: nessun Price da controllare.'];
        });

        $prova('Metodi di pagamento', self::link('settings/payment_methods'), function (): array {
            $cfg = [];
            foreach ((array) (Stripe::call('GET', 'payment_method_configurations', ['limit' => '20'])['data'] ?? []) as $x) if (!empty($x['is_default'])) $cfg = (array) $x;
            if (!$cfg) return ['manuale', 'La configurazione predefinita dei metodi non è leggibile: controllala a mano.'];
            $accesi = [];
            // Ogni metodo: display_preference.value è quello che vale davvero («on» = proposto al cliente).
            foreach ($cfg as $k => $v) if (is_array($v) && ($v['display_preference']['value'] ?? '') === 'on') $accesi[] = (string) $k;
            $strani = array_values(array_diff($accesi, self::METODI_OK));
            return $strani ? ['sistemare', 'Metodi non adatti ai rinnovi automatici accesi: ' . implode(', ', $strani) . '. Tieni carta, Apple Pay, Google Pay, Link e SEPA.']
                           : ['ok', 'Accesi: ' . ($accesi ? implode(', ', $accesi) : 'nessuno leggibile') . '.'];
        });

        $c = self::conferme();
        $voci[] = ['titolo' => 'Adaptive Pricing (spento)', 'stato' => isset($c['adaptive']) ? 'ok' : 'manuale', 'conferma' => 'adaptive', 'link' => self::link('settings/adaptive-pricing'),
                   'dettaglio' => isset($c['adaptive']) ? 'Controllato a mano il ' . Support::date($c['adaptive']) . '.' : 'L\'API non lo dice: controlla che sia spento (i prezzi restano in euro) e premi «Confermo».'];
        $voci[] = ['titolo' => 'Email di Stripe ai clienti (spente)', 'stato' => isset($c['email']) ? 'ok' : 'manuale', 'conferma' => 'email', 'link' => self::link('settings/emails'),
                   'dettaglio' => isset($c['email']) ? 'Controllato a mano il ' . Support::date($c['email']) . '.' : 'L\'API non lo dice: ricevute, fatture e avvisi di Stripe vanno spenti (scriviamo noi e Adamo). Controlla e premi «Confermo».'];
        return $voci;
    }
}
