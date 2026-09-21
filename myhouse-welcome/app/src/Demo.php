<?php
namespace MHW;

/**
 * Dati di esempio: tre host con contenuti veri, guide pubblicate e foto.
 *
 * Serve a vedere il prodotto pieno invece che vuoto. Sono account finti:
 * cancellateli prima di aprire il sito al pubblico.
 */
final class Demo
{
    public const PASSWORD = 'dimostrazione1';

    /** Il dominio che marca gli account finti: serve a poterli togliere tutti. */
    public const DOMINIO = 'esempio.it';

    public static function presente(): bool
    {
        return (bool) Db::val('SELECT COUNT(*) FROM users WHERE email LIKE ?', ['%@' . self::DOMINIO], 0);
    }

    /**
     * Toglie di mezzo gli account di esempio, le loro guide e le loro foto.
     * Le chiavi esterne fanno il resto: un utente cancellato porta via account,
     * strutture, sezioni, traduzioni, luoghi, ordini e statistiche.
     */
    public static function rimuovi(): int
    {
        $utenti = Db::all('SELECT id FROM users WHERE email LIKE ?', ['%@' . self::DOMINIO]);
        $dir = Config::get('uploads_dir');
        foreach (glob($dir . '/demo-*') ?: [] as $f) @unlink($f);
        foreach ($utenti as $u) Db::run('DELETE FROM users WHERE id = ?', [$u['id']]);
        return count($utenti);
    }

    /** Le foto di partenza, copiate dentro storage/uploads come se fossero caricate. */
    private static function foto(string $nome, int $accountId, string $alt): ?int
    {
        // Le fotografie stanno fra i file serviti dal web, che a seconda della
        // disposizione sta dentro o accanto alla cartella dell'applicazione.
        $sorgente = null;
        foreach ([defined('MHW_PUBLIC') ? MHW_PUBLIC : null, dirname(__DIR__) . '/public'] as $dove) {
            if ($dove && is_file($dove . '/assets/foto/' . $nome)) { $sorgente = $dove . '/assets/foto/' . $nome; break; }
        }
        if ($sorgente === null) return null;

        $dir = Config::get('uploads_dir');
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $destinazione = 'demo-' . Support::token(6) . '-' . $nome;
        if (!@copy($sorgente, $dir . '/' . $destinazione)) return null;

        $info = @getimagesize($dir . '/' . $destinazione) ?: [0, 0];
        return Db::insert('media', [
            'account_id' => $accountId, 'filename' => $destinazione, 'mime' => 'image/jpeg',
            'bytes' => (int) filesize($dir . '/' . $destinazione),
            'width' => (int) $info[0], 'height' => (int) $info[1],
            'alt' => $alt, 'created_at' => Support::now(),
        ]);
    }

    private static function pacchetto(string $codice): ?array
    {
        return Db::one(
            'SELECT pv.* FROM package_versions pv JOIN packages p ON p.id = pv.package_id
             WHERE p.code = ? AND pv.is_current = 1', [$codice]);
    }

    /** Un host completo: account, abbonamento pagato, struttura, sezioni, lingue. */
    private static function host(string $nome, string $email, string $pacchetto): array
    {
        $u = Auth::register($email, self::PASSWORD, $nome);
        $pv = self::pacchetto($pacchetto);
        if ($pv) {
            $ordine = Db::insert('orders', [
                'account_id' => $u['account_id'], 'package_version_id' => $pv['id'],
                'amount_cents' => $pv['price_cents'], 'currency' => $pv['currency'],
                'status' => 'pending', 'provider' => 'dimostrazione',
                'provider_session_id' => '', 'created_at' => Support::now(),
            ]);
            Billing::markPaid($ordine, 'dimostrazione');
        }
        return $u;
    }

    private static function struttura(int $accountId, array $dati): int
    {
        $pid = Db::insert('properties', [
            'account_id' => $accountId,
            'name' => $dati['nome'], 'slug' => Support::uniqueSlug($dati['nome']),
            'city' => $dati['citta'], 'region' => $dati['regione'],
            'checkin_from' => $dati['arrivo'], 'checkout_by' => $dati['partenza'],
            'host_name' => $dati['host'], 'host_phone' => $dati['telefono'],
            'host_whatsapp' => $dati['telefono'],
            'cover_media_id' => $dati['copertina'],
            'default_locale' => 'it', 'status' => 'draft', 'created_at' => Support::now(),
        ]);
        foreach ($dati['lingue'] as $l) Db::insert('property_locales', ['property_id' => $pid, 'locale' => $l]);
        Db::insert('qr_tokens', ['property_id' => $pid, 'token' => Support::token(9),
                                 'scans' => $dati['scansioni'], 'created_at' => Support::now()]);
        return $pid;
    }

    private static function sezione(int $pid, int $pos, array $s): int
    {
        $sid = Db::insert('sections', [
            'property_id' => $pid, 'kind' => $s['tipo'], 'icon' => $s['tipo'],
            'color' => $s['colore'], 'position' => $pos,
            'wifi_ssid' => $s['ssid'] ?? '', 'wifi_pass' => $s['pass'] ?? '',
            'door_code' => $s['codice'] ?? '', 'media_id' => $s['foto'] ?? null,
            'created_at' => Support::now(),
        ]);
        foreach ($s['testi'] as $locale => [$titolo, $corpo]) {
            Db::insert('section_translations', [
                'section_id' => $sid, 'locale' => $locale, 'title' => $titolo, 'body' => $corpo,
                'state' => $locale === 'it' ? 'reviewed' : ($s['riviste'] ?? false ? 'reviewed' : 'machine'),
                'updated_at' => Support::now(),
            ]);
        }
        return $sid;
    }

    public static function popola(): array
    {
        $creati = [];

        // ---------------------------------------------------- Casa Lucia, Toscana
        $lucia = self::host('Lucia Ferrante', 'lucia@' . self::DOMINIO, 'plus');
        $acc = (int) $lucia['account_id'];
        $pid = self::struttura($acc, [
            'nome' => 'Casa Lucia', 'citta' => 'Montepulciano', 'regione' => 'Toscana',
            'arrivo' => '15:00', 'partenza' => '10:30', 'host' => 'Lucia',
            'telefono' => '+39 0578 000000', 'lingue' => ['it', 'en', 'de'],
            'scansioni' => 128,
            'copertina' => self::foto('casa.jpg', $acc, 'La casa in pietra vista dal vialetto'),
        ]);
        self::sezione($pid, 0, [
            'tipo' => 'checkin', 'colore' => 'terracotta', 'codice' => '4729',
            'foto' => self::foto('portone.jpg', $acc, 'Il portone con le rose accanto'),
            'riviste' => true,
            'testi' => [
                'it' => ['Entrare in casa', "La cassetta delle chiavi è a destra del portone, dietro il glicine, all'altezza della mano.\n\nRuotate la manopola dopo l'ultima cifra, poi tirate: la porta è pesante.\n\nNota: se arrivate dopo le 21, scriveteci — veniamo ad aprire noi."],
                'en' => ['Getting in', "The key box is to the right of the door, behind the wisteria, at hand height.\n\nTurn the knob after the last digit, then pull: the door is heavy.\n\nNota: if you arrive after 9pm, message us — we will come and open up."],
                'de' => ['Ins Haus kommen', "Der Schlüsselkasten ist rechts von der Tür, hinter dem Blauregen, auf Handhöhe.\n\nDrehen Sie den Knauf nach der letzten Ziffer."],
            ],
        ]);
        self::sezione($pid, 1, [
            'tipo' => 'wifi', 'colore' => 'sea', 'ssid' => 'CasaLucia_5G', 'pass' => 'glicine2024',
            'riviste' => true,
            'testi' => [
                'it' => ['Wi-Fi e servizi', "Il router è nell'ingresso, sopra la mensola. Se la rete sparisce staccate la spina e riattaccatela dopo un minuto.\n\nL'umido si porta fuori il martedì e il venerdì, prima delle otto."],
                'en' => ['Wi-Fi and utilities', "The router is in the hallway, above the shelf. If the network drops, unplug it and plug it back in after a minute.\n\nFood waste goes out on Tuesday and Friday, before eight."],
                'de' => ['WLAN und Technik', "Der Router steht im Flur über dem Regal. Wenn das Netz ausfällt, ziehen Sie den Stecker und stecken ihn nach einer Minute wieder ein."],
            ],
        ]);
        $mangiare = self::sezione($pid, 2, [
            'tipo' => 'places', 'colore' => 'pine',
            'testi' => [
                'it' => ['Dove mangiare', "Tre posti a piedi. Li abbiamo provati tutti, più di una volta.\n\nAl Ponte prenotate per telefono: non rispondono alle mail, ma rispondono sempre."],
                'en' => ['Where to eat', "Three places within walking distance. We have tried them all, more than once.\n\nBook Al Ponte by phone: they never answer email, but they always answer the phone."],
                'de' => ['Wo essen', "Drei Lokale zu Fuß erreichbar. Wir haben sie alle mehr als einmal probiert."],
            ],
        ]);
        foreach ([
            ['Osteria del Ponte', 'Trattoria', '450 m · a piedi', 'Si prenota solo per telefono, ma rispondono sempre.', 'Aperto fino alle 23', 'pine', 'osteria.jpg', 'Tavoli apparecchiati nel vicolo'],
            ['Bar Centrale', 'Colazione', '200 m · a piedi', 'Il cornetto finisce presto: andateci entro le nove.', 'Apre alle 7', 'sea', 'caffe.jpg', 'Una tazzina di espresso sul bancone'],
            ['Gelateria Marchetti', 'Gelato', '600 m · a piedi', 'Il pistacchio vale la camminata in salita.', 'Il preferito di Lucia', 'ochre', 'gelato.jpg', 'Vaschette di gelato dietro il banco'],
        ] as $i => [$n, $cat, $dist, $nota, $badge, $tono, $img, $alt]) {
            Db::insert('places', [
                'section_id' => $mangiare, 'name' => $n, 'category' => $cat, 'distance' => $dist,
                'note' => $nota, 'badge' => $badge, 'badge_tone' => $tono,
                'media_id' => self::foto($img, $acc, $alt), 'position' => $i,
            ]);
        }
        Guide::publish($pid);
        for ($g = 0; $g < 30; $g++) {
            $giorno = gmdate('Y-m-d', strtotime("-$g days"));
            for ($k = 0, $n = (int) (2 + ($g % 4)); $k < $n; $k++) {
                Db::insert('analytics_events', ['property_id' => $pid, 'section_id' => null,
                    'locale' => ['it', 'en', 'de'][$k % 3], 'kind' => 'open',
                    'day' => $giorno, 'created_at' => $giorno . 'T10:00:00Z']);
            }
        }
        $creati[] = ['Lucia Ferrante', 'lucia@' . self::DOMINIO, 'Plus', 'Casa Lucia — pubblicata'];

        // ------------------------------------------------- B&B Le Rondini, Puglia
        $marco = self::host('Marco Bevilacqua', 'marco@' . self::DOMINIO, 'pro');
        $acc = (int) $marco['account_id'];
        $pid = self::struttura($acc, [
            'nome' => 'B&B Le Rondini', 'citta' => 'Lecce', 'regione' => 'Puglia',
            'arrivo' => '14:00', 'partenza' => '11:00', 'host' => 'Marco',
            'telefono' => '+39 0832 000000', 'lingue' => ['it', 'en'],
            'scansioni' => 41,
            'copertina' => self::foto('soggiorno.jpg', $acc, 'Il soggiorno con il divano chiaro'),
        ]);
        self::sezione($pid, 0, [
            'tipo' => 'checkin', 'colore' => 'terracotta', 'codice' => '1908', 'riviste' => true,
            'testi' => [
                'it' => ['Arrivo e chiavi', "Il portone sulla strada resta aperto fino alle 20. Le chiavi sono nella cassetta a sinistra della scala, sotto la cassetta della posta."],
                'en' => ['Arrival and keys', "The street door stays open until 8pm. The keys are in the box to the left of the stairs, under the letterbox."],
            ],
        ]);
        self::sezione($pid, 1, [
            'tipo' => 'wifi', 'colore' => 'sea', 'ssid' => 'Rondini_Ospiti', 'pass' => 'salento2024',
            'testi' => [
                'it' => ['Wi-Fi', "La rete prende bene in tutte le stanze, meno che sul terrazzo."],
                'en' => ['Wi-Fi', "The network reaches every room, except the terrace."],
            ],
        ]);
        self::sezione($pid, 2, [
            'tipo' => 'text', 'colore' => 'ochre',
            'testi' => [
                'it' => ['Il mare', "Torre dell'Orso è a venti minuti di macchina. Andateci presto la mattina: dopo le dieci il parcheggio è pieno."],
                'en' => ['The sea', "Torre dell'Orso is twenty minutes by car. Go early: after ten the car park is full."],
            ],
        ]);
        Guide::publish($pid);
        for ($g = 0; $g < 14; $g++) {
            $giorno = gmdate('Y-m-d', strtotime("-$g days"));
            Db::insert('analytics_events', ['property_id' => $pid, 'section_id' => null,
                'locale' => 'it', 'kind' => 'open', 'day' => $giorno, 'created_at' => $giorno . 'T09:00:00Z']);
        }
        $creati[] = ['Marco Bevilacqua', 'marco@' . self::DOMINIO, 'Pro', 'B&B Le Rondini — pubblicata'];

        // --------------------------------------------- Il Cortile, Sicilia (bozza)
        $agnese = self::host('Agnese Ruta', 'agnese@' . self::DOMINIO, 'essential');
        $acc = (int) $agnese['account_id'];
        $pid = self::struttura($acc, [
            'nome' => 'Il Cortile', 'citta' => 'Ortigia', 'regione' => 'Sicilia',
            'arrivo' => '16:00', 'partenza' => '10:00', 'host' => 'Agnese',
            'telefono' => '+39 0931 000000', 'lingue' => ['it'], 'scansioni' => 0,
            'copertina' => null,
        ]);
        self::sezione($pid, 0, [
            'tipo' => 'checkin', 'colore' => 'terracotta', 'codice' => '',
            'testi' => ['it' => ['Entrare in casa', "Sto ancora scrivendo questa parte."]],
        ]);
        // volutamente NON pubblicata: serve a vedere lo stato di bozza
        $creati[] = ['Agnese Ruta', 'agnese@' . self::DOMINIO, 'Essential', 'Il Cortile — ancora in bozza'];

        return $creati;
    }
}
