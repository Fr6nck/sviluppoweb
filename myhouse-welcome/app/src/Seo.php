<?php
namespace MHW;

/**
 * SEO e GEO: come le pagine pubbliche si presentano a Google e agli assistenti AI.
 *
 * Le impostazioni stanno in seo_settings (migrazione 029), una riga per chiave; quelle che
 * mancano usano i valori di serie. Da qui escono la testa delle pagine (title, description,
 * robots, canonical, Open Graph, dati strutturati), robots.txt, sitemap.xml e llms.txt.
 * Restano sempre fuori dai motori le guide, il pannello, l'account, l'amministrazione,
 * i pagamenti e i media: noindex su pagine e file, e robots.txt chiude le guide agli assistenti AI (6M).
 */
final class Seo
{
    /** Le pagine pubbliche indicizzabili: chiave => [percorso, nome nell'amministrazione]. */
    public const PAGINE = [
        'home' => ['/', 'Home'],
        'domande' => ['/domande', 'Domande frequenti'],
        'termini' => ['/termini', 'Termini e condizioni'],
        'privacy' => ['/privacy', 'Privacy'],
    ];

    /** Gli assistenti AI: user-agent => [prodotto, azienda]. */
    public const BOT = [
        'GPTBot' => ['ChatGPT', 'OpenAI'],
        'OAI-SearchBot' => ['ricerca di ChatGPT', 'OpenAI'],
        'ClaudeBot' => ['Claude', 'Anthropic'],
        'PerplexityBot' => ['Perplexity', 'Perplexity'],
        'Google-Extended' => ['Gemini', 'Google'],
        'CCBot' => ['Common Crawl', 'Common Crawl'],
    ];

    /** Le parti private: fuori per tutti, motori e assistenti. */
    public const ESCLUSI = ['/pannello', '/admin', '/account', '/pagamento', '/cron', '/installa', '/inviti', '/i/'];

    /**
     * Le guide (6M). Per i motori NON sono in Disallow: devono poterle leggere per vedere il
     * noindex, altrimenti potrebbero elencarne l'indirizzo senza averlo letto. Gli assistenti AI
     * invece non sempre rispettano il noindex, ma rispettano robots.txt: per loro sono chiuse.
     */
    public const GUIDE = ['/g/', '/q/', '/qr/', '/media/'];

    /** Gli assistenti AI che ricevono il blocco delle guide (quelli in BOT si possono anche spegnere del tutto). */
    public const ASSISTENTI = ['GPTBot', 'OAI-SearchBot', 'ChatGPT-User', 'ClaudeBot', 'Claude-User', 'Claude-SearchBot', 'PerplexityBot',
                               'Perplexity-User', 'Google-Extended', 'Applebot-Extended', 'CCBot', 'Bytespider', 'meta-externalagent'];

    public const TITOLO_MIN = 30, TITOLO_MAX = 60, DESCR_MIN = 70, DESCR_MAX = 155, INTRO_MAX = 300;

    private static ?array $salvate = null;

    public static function disponibili(): bool { return Migrator::tableExists('seo_settings'); }

    /** I valori di serie. I dati dell'azienda vengono dalla configurazione, come nel piè di pagina e nei Termini. */
    public static function predefiniti(): array
    {
        $l = Config::get('legal') ?? [];
        $d = [
            'dominio' => '', 'gsc' => '', 'bing' => '', 'og_immagine' => '', 'og_immagine.file' => '',
            'p.home.titolo' => 'Guida digitale per case vacanza e B&B | MyHouse Welcome',
            'p.home.descrizione' => 'La guida digitale per case vacanza, B&B, affittacamere e agriturismi: check-in, Wi-Fi e consigli in un link e un QR Code. La crei gratis, paghi solo quando la pubblichi.',
            'p.domande.titolo' => 'Domande frequenti | MyHouse Welcome',
            'p.domande.descrizione' => 'Le risposte su MyHouse Welcome: prova gratuita, lingue e traduzioni, più strutture, varianti camera, fattura, cambio di piano e disdetta.',
            'p.termini.titolo' => 'Termini e condizioni | MyHouse Welcome',
            'p.termini.descrizione' => 'Le condizioni del servizio MyHouse Welcome: abbonamento annuale, prezzi e rinnovo, cambio di piano, codici sconto e contenuti della guida.',
            'p.privacy.titolo' => 'Informativa sulla privacy | MyHouse Welcome',
            'p.privacy.descrizione' => 'Come MyHouse Welcome tratta i dati di host e ospiti: quali dati raccoglie, perché, con quali fornitori e quali sono i tuoi diritti.',
            'az.nome' => 'MyHouse Welcome',
            'az.venditore' => (string) ($l['company'] ?? ''),
            'az.piva' => (string) ($l['company_vat'] ?? ''),
            'az.indirizzo' => (string) ($l['company_city'] ?? ''),
            'az.email' => (string) ($l['contact_email'] ?? ''),
            'az.telefono' => (string) ($l['contact_phone'] ?? ''),
            'az.social' => '',
            'llms.intro' => 'MyHouse Welcome è la guida digitale per case vacanza, B&B, affittacamere e agriturismi: check-in, Wi-Fi, regole della casa e consigli della zona in un link e un QR Code, che gli ospiti aprono dal telefono senza scaricare app.',
            'llms.fatti' => implode("\n", [
                'La guida si apre nel browser del telefono, da un link o da un QR Code: gli ospiti non scaricano app.',
                'L\'host crea e prepara la guida gratis, senza carta di credito; paga solo quando la pubblica.',
                'I piani sono annuali, con rinnovo automatico che si può disattivare in ogni momento.',
                'Lingue per gli ospiti: italiano e inglese con Essential; anche francese, tedesco e spagnolo con Plus e Portfolio.',
                'Il QR Code stampato non cambia quando si modifica la guida.',
                'Le guide degli ospiti non usano cookie e non compaiono nei motori di ricerca.',
            ]),
        ];
        foreach (array_keys(self::PAGINE) as $p) $d["p.$p.indicizza"] = '1';
        foreach (array_keys(self::BOT) as $b) $d["bot.$b"] = $b === 'CCBot' ? '0' : '1';
        return $d;
    }

    /** Il valore salvato, altrimenti quello di serie, altrimenti $default. */
    public static function get(string $k, string $default = ''): string
    {
        $s = self::salvate();
        if (array_key_exists($k, $s)) return $s[$k];
        return self::predefiniti()[$k] ?? $default;
    }

    /** Salva le coppie chiave => valore (solo chiavi conosciute). */
    public static function set(array $coppie): void
    {
        $note = self::predefiniti();
        $ora = Support::now();
        Db::tx(function () use ($coppie, $note, $ora) {
            foreach ($coppie as $k => $v) {
                if (!array_key_exists($k, $note)) continue;
                $v = (string) $v;
                if (Db::one('SELECT chiave FROM seo_settings WHERE chiave = ?', [$k])) {
                    Db::run('UPDATE seo_settings SET valore = ?, updated_at = ? WHERE chiave = ?', [$v, $ora, $k]);
                } else {
                    Db::run('INSERT INTO seo_settings (chiave, valore, updated_at) VALUES (?, ?, ?)', [$k, $v, $ora]);
                }
            }
        });
        self::$salvate = null;
    }

    private static function salvate(): array
    {
        if (self::$salvate !== null) return self::$salvate;
        // Prima dell'installazione (termini e privacy si aprono anche lì) valgono i valori di serie.
        try {
            if (!self::disponibili()) return self::$salvate = [];
            return self::$salvate = array_column(Db::all('SELECT chiave, valore FROM seo_settings'), 'valore', 'chiave');
        } catch (\Throwable) { return self::$salvate = []; }
    }

    /** L'ultimo salvataggio, per la sitemap. */
    public static function ultimoSalvataggio(): ?string
    {
        return self::disponibili() ? (Db::val('SELECT MAX(updated_at) FROM seo_settings') ?: null) : null;
    }

    // ------------------------------------------------------------------ indirizzi

    /** Il dominio salvato, senza barra finale. */
    public static function dominio(): string { return rtrim(trim(self::get('dominio')), '/'); }

    /** L'indirizzo assoluto di un percorso: con il dominio, se c'è, altrimenti con quello di adesso. */
    public static function assoluto(string $percorso): string
    {
        $d = self::dominio();
        return ($d !== '' ? $d : Support::baseUrl()) . $percorso;
    }

    public static function indicizza(string $pagina): bool { return self::get("p.$pagina.indicizza", '1') === '1'; }

    public static function botAmmesso(string $bot): bool { return self::get("bot.$bot", '1') === '1'; }

    /** L'immagine per la condivisione: quella caricata (indirizzo rifatto a ogni pagina), altrimenti quella di serie. */
    public static function immagine(bool $locale = false): array
    {
        // $locale: per l'anteprima nell'amministrazione, con l'indirizzo di adesso e non con il dominio.
        $conOrigine = fn(string $u) => $locale && !preg_match('#^https?://#', $u) ? $u : self::conOrigine($u);
        $file = self::get('og_immagine.file');
        if ($file !== '' && str_contains($file, ':')) {
            [$driver, $key] = explode(':', $file, 2);
            try { $u = Storages::for($driver)->url($key); return ['url' => $conOrigine($u), 'caricata' => true]; }
            catch (\Throwable $e) { Log::exception($e, 'Seo::immagine'); }
        }
        if (($u = self::get('og_immagine')) !== '') return ['url' => $conOrigine($u), 'caricata' => true];
        return ['url' => $conOrigine(a('/assets/og.jpg')), 'caricata' => false];
    }

    private static function conOrigine(string $u): string
    {
        if (preg_match('#^https?://#', $u)) return $u;
        $origine = self::dominio() !== '' ? (string) preg_replace('#^(https?://[^/]+).*$#', '$1', self::dominio())
                                          : (string) preg_replace('#^(https?://[^/]+).*$#', '$1', Support::baseUrl());
        return $origine . $u;
    }

    // ------------------------------------------------------------------ testa delle pagine

    /** Il titolo che vede Google: quello salvato, altrimenti quello di serie della vista. */
    public static function titolo(string $pagina, string $titoloDiSerie): string
    {
        $t = trim(self::get("p.$pagina.titolo"));
        return $t !== '' ? $t : $titoloDiSerie;
    }

    /**
     * La testa di una pagina pubblica. Con $pagina (home, domande, termini, privacy): title,
     * description, robots, canonical (solo con il dominio), Open Graph, verifiche (solo home)
     * e dati strutturati. Senza: il titolo di sempre e noindex.
     */
    public static function head(?string $pagina, string $titoloDiSerie): string
    {
        $e = fn(string $s) => Support::e($s);
        if ($pagina === null || !isset(self::PAGINE[$pagina])) {
            return '<title>' . $e($titoloDiSerie) . "</title>\n<meta name=\"robots\" content=\"noindex\">\n";
        }
        $titolo = self::titolo($pagina, $titoloDiSerie);
        $descr = trim(self::get("p.$pagina.descrizione"));
        $percorso = self::PAGINE[$pagina][0];
        $url = self::assoluto($percorso);
        $img = self::immagine();
        $h = '<title>' . $e($titolo) . "</title>\n";
        if ($descr !== '') $h .= '<meta name="description" content="' . $e($descr) . "\">\n";
        $h .= '<meta name="robots" content="' . (self::indicizza($pagina) ? 'index, follow' : 'noindex') . "\">\n";
        if (self::dominio() !== '') $h .= '<link rel="canonical" href="' . $e(self::dominio() . $percorso) . "\">\n";
        $h .= '<meta property="og:type" content="website">' . "\n"
            . '<meta property="og:locale" content="it_IT">' . "\n"
            . '<meta property="og:site_name" content="' . $e(self::get('az.nome')) . "\">\n"
            . '<meta property="og:title" content="' . $e($titolo) . "\">\n"
            . ($descr !== '' ? '<meta property="og:description" content="' . $e($descr) . "\">\n" : '')
            . '<meta property="og:url" content="' . $e($url) . "\">\n"
            . '<meta property="og:image" content="' . $e($img['url']) . "\">\n"
            . ($img['caricata'] ? '' : "<meta property=\"og:image:width\" content=\"1200\">\n<meta property=\"og:image:height\" content=\"630\">\n")
            . '<meta name="twitter:card" content="summary_large_image">' . "\n";
        if ($pagina === 'home') {
            if (($g = trim(self::get('gsc'))) !== '') $h .= '<meta name="google-site-verification" content="' . $e($g) . "\">\n";
            if (($b = trim(self::get('bing'))) !== '') $h .= '<meta name="msvalidate.01" content="' . $e($b) . "\">\n";
        }
        foreach (self::datiStrutturati($pagina) as $json) {
            $h .= '<script type="application/ld+json">' . $json . "</script>\n";
        }
        return $h;
    }

    /** I blocchi JSON-LD della pagina, già codificati (vuoto se json_encode fallisce). @return string[] */
    public static function datiStrutturati(string $pagina, int $flag = 0): array
    {
        $f = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | $flag;
        $out = [];
        if ($pagina === 'home') {
            $j = json_encode(self::grafoHome(), $f);
            if ($j !== false) $out[] = $j;
        }
        if ($pagina === 'home' || $pagina === 'domande') {
            $j = json_encode(self::faqPage(), $f);
            if ($j !== false) $out[] = $j;
        }
        return $out;
    }

    /** Organization, WebSite e SoftwareApplication con un'offerta per piano pubblico in vendita. */
    public static function grafoHome(): array
    {
        $home = self::assoluto('/');
        $org = array_filter([
            '@type' => 'Organization', '@id' => $home . '#organizzazione',
            'name' => self::get('az.nome'), 'url' => $home,
            'legalName' => self::get('az.venditore'),
            'vatID' => self::get('az.piva') !== '' ? 'IT' . preg_replace('/^IT/i', '', preg_replace('/\s+/', '', self::get('az.piva'))) : '',
            'email' => self::get('az.email'), 'telephone' => self::get('az.telefono'),
            'address' => self::get('az.indirizzo') !== '' ? ['@type' => 'PostalAddress', 'streetAddress' => self::get('az.indirizzo'), 'addressCountry' => 'IT'] : '',
            'logo' => self::conOrigine(a('/assets/apple-touch-icon.png')),
            'sameAs' => self::social(),
        ], fn($v) => $v !== '' && $v !== []);
        if (!is_file(MHW_PUBLIC . '/assets/apple-touch-icon.png')) unset($org['logo']);
        $offerte = [];
        foreach (Plans::public() as $p) {
            $q = Plans::perProperty($p) ? max(1, (int) $p['min_quantity']) : 1;
            $prezzo = number_format(Plans::price($p, $q) / 100, 2, '.', '');
            $offerte[] = [
                '@type' => 'Offer', 'name' => $p['name'] . ($q > 1 ? ' (' . $q . ' strutture)' : ''),
                'price' => $prezzo, 'priceCurrency' => 'EUR', 'url' => $home . '#piani',
                'priceSpecification' => ['@type' => 'UnitPriceSpecification', 'price' => $prezzo, 'priceCurrency' => 'EUR',
                                         'valueAddedTaxIncluded' => false, 'unitText' => 'anno'],
            ];
        }
        return ['@context' => 'https://schema.org', '@graph' => [
            $org,
            ['@type' => 'WebSite', '@id' => $home . '#sito', 'name' => self::get('az.nome'), 'url' => $home, 'inLanguage' => 'it-IT',
             'publisher' => ['@id' => $home . '#organizzazione']],
            ['@type' => 'SoftwareApplication', 'name' => self::get('az.nome'), 'url' => $home,
             'applicationCategory' => 'BusinessApplication', 'operatingSystem' => 'Web',
             'description' => self::get('p.home.descrizione'),
             'publisher' => ['@id' => $home . '#organizzazione'], 'offers' => $offerte],
        ]];
    }

    public static function faqPage(): array
    {
        return ['@context' => 'https://schema.org', '@type' => 'FAQPage',
                'mainEntity' => array_map(fn($x) => ['@type' => 'Question', 'name' => $x['d'],
                                                     'acceptedAnswer' => ['@type' => 'Answer', 'text' => $x['r']]], Faq::tutte())];
    }

    /** Un indirizzo per riga, solo quelli http(s). @return string[] */
    public static function social(): array
    {
        $r = array_map('trim', preg_split('/\R/', self::get('az.social')) ?: []);
        return array_values(array_filter($r, fn($u) => (bool) preg_match('#^https?://\S+$#', $u)));
    }

    // ------------------------------------------------------------------ file per motori e assistenti

    public static function robots(): string
    {
        $regole = fn(array $percorsi) => implode('', array_map(fn($p) => "Disallow: $p\n", $percorsi));
        $t = "User-agent: *\n" . $regole(self::ESCLUSI);
        foreach (array_unique(array_merge(self::ASSISTENTI, array_keys(self::BOT))) as $b) {
            $spento = isset(self::BOT[$b]) && !self::botAmmesso($b);
            $t .= "\nUser-agent: $b\n" . ($spento ? "Disallow: /\n" : $regole(array_merge(self::GUIDE, self::ESCLUSI)));
        }
        return $t . "\nSitemap: " . self::assoluto('/sitemap.xml') . "\n";
    }

    public static function sitemap(): string
    {
        $l = Config::get('legal') ?? [];
        $listino = Db::val('SELECT MAX(pv.created_at) FROM package_versions pv JOIN packages p ON p.id = pv.package_id WHERE pv.is_current = 1 AND p.public = 1');
        $altre = max((string) self::ultimoSalvataggio(), (string) $listino);
        $data = fn(string $d) => preg_match('/^\d{4}-\d{2}(-\d{2})?/', $d, $m) ? $m[0] : '';
        $x = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach (self::PAGINE as $k => [$percorso]) {
            if (!self::indicizza($k)) continue;
            $mod = $data(match ($k) { 'termini' => (string) ($l['terms_version'] ?? ''), 'privacy' => (string) ($l['privacy_version'] ?? ''), default => $altre });
            $x .= '  <url><loc>' . htmlspecialchars(self::assoluto($percorso), ENT_XML1) . '</loc>' . ($mod !== '' ? "<lastmod>$mod</lastmod>" : '') . "</url>\n";
        }
        return $x . "</urlset>\n";
    }

    public static function llms(): string
    {
        $t = '# ' . self::get('az.nome') . "\n\n";
        if (($i = trim(self::get('llms.intro'))) !== '') $t .= '> ' . preg_replace('/\s*\R\s*/', ' ', $i) . "\n\n";
        $fatti = array_filter(array_map('trim', preg_split('/\R/', self::get('llms.fatti')) ?: []));
        if ($fatti) $t .= "## Fatti chiave\n\n" . implode('', array_map(fn($f) => "- $f\n", $fatti)) . "\n";
        $t .= "## Piani (prezzi annuali, IVA esclusa)\n\n";
        foreach (Plans::public() as $p) {
            $m = fn(int $c) => str_replace("\u{00A0}", ' ', Support::money($c, $p['currency']));
            $riga = '- ' . $p['name'] . ': ';
            if (Plans::perProperty($p)) {
                $riga .= $m((int) $p['price_cents']) . ' la prima struttura, poi '
                    . implode('; ', array_map(fn($x) => $m($x['cents']) . ' ' . Plans::tierLabel($x), Plans::tiers($p)))
                    . ' per ogni struttura in più; da ' . (int) $p['min_quantity'] . ' a ' . (int) $p['max_quantity'] . ' strutture'
                    . ' (con ' . (int) $p['min_quantity'] . ': ' . $m(Plans::price($p, max(1, (int) $p['min_quantity']))) . ')';
            } else {
                $riga .= $m((int) $p['price_cents']);
            }
            $perChi = lcfirst(rtrim(trim((string) ($p['tagline'] ?? '')), '.'));
            if ($perChi !== '') $riga .= '. Ideale ' . (str_starts_with($perChi, 'per ') ? '' : 'per ') . $perChi;
            $t .= $riga . ".\n";
        }
        $t .= "\n## Domande frequenti\n\n";
        foreach (Faq::tutte() as $x) $t .= '- [' . $x['d'] . '](' . self::assoluto('/domande') . '#' . $x['id'] . ")\n";
        $t .= "\n## Contatti\n\n- Sito: " . self::assoluto('/') . "\n";
        foreach (['az.email' => 'Email', 'az.telefono' => 'Telefono'] as $k => $n) if (self::get($k) !== '') $t .= "- $n: " . self::get($k) . "\n";
        $chi = implode(', ', array_filter([self::get('az.venditore'), self::get('az.indirizzo'), self::get('az.piva') !== '' ? 'P.IVA ' . self::get('az.piva') : '']));
        if ($chi !== '') $t .= "- Fornitore: $chi\n";
        foreach (self::social() as $s) $t .= "- $s\n";
        return $t;
    }

    // ------------------------------------------------------------------ controllo

    /**
     * Il controllo della pagina di amministrazione, ricalcolato a ogni apertura.
     * @return array<int,array{stato:string,testo:string,dove:string}> stato: ok | guarda | sistema
     */
    public static function controlli(): array
    {
        $c = [];
        $dom = self::dominio();
        $c[] = $dom === '' ? ['sistema', 'Il dominio non è impostato: canonical e sitemap usano l\'indirizzo di adesso.', 'seo-motori']
             : (str_starts_with($dom, 'https://') ? ['ok', 'Dominio impostato, con https.', 'seo-motori']
                                                  : ['sistema', 'Il dominio non usa https.', 'seo-motori']);
        $host = (string) parse_url(Support::baseUrl(), PHP_URL_HOST);
        $hostDom = (string) parse_url($dom, PHP_URL_HOST);
        $c[] = $dom === '' ? ['guarda', 'Senza dominio non si può confrontare con l\'indirizzo di adesso (' . $host . ').', 'seo-motori']
             : (strcasecmp($host, $hostDom) === 0 ? ['ok', 'Il dominio è quello da cui stai lavorando.', 'seo-motori']
                                                   : ['guarda', 'Il dominio (' . $hostDom . ') è diverso dall\'indirizzo di adesso (' . $host . ').', 'seo-motori']);
        $c[] = trim(self::get('gsc')) !== '' ? ['ok', 'Verifica di Search Console presente.', 'seo-motori']
                                             : ['guarda', 'Manca il codice di verifica di Search Console.', 'seo-motori'];
        foreach (self::PAGINE as $k => [, $nome]) {
            if (!self::indicizza($k)) continue;
            $t = mb_strlen(self::titolo($k, $nome)); $d = mb_strlen(trim(self::get("p.$k.descrizione")));
            $c[] = $t >= self::TITOLO_MIN && $t <= self::TITOLO_MAX ? ['ok', "$nome: titolo di $t caratteri.", "seo-p-$k"]
                 : ['sistema', "$nome: il titolo ha $t caratteri (tra " . self::TITOLO_MIN . ' e ' . self::TITOLO_MAX . ').', "seo-p-$k"];
            $c[] = $d >= self::DESCR_MIN && $d <= self::DESCR_MAX ? ['ok', "$nome: descrizione di $d caratteri.", "seo-p-$k"]
                 : ['sistema', "$nome: la descrizione ha $d caratteri (tra " . self::DESCR_MIN . ' e ' . self::DESCR_MAX . ').', "seo-p-$k"];
        }
        $c[] = self::immagine()['caricata'] ? ['ok', 'Immagine per la condivisione caricata.', 'seo-p-home']
                                            : ['guarda', 'Per la condivisione si usa l\'immagine di serie: caricane una tua (1200 × 630).', 'seo-p-home'];
        $errore = '';
        foreach (['home', 'domande'] as $p) {
            if (count(self::datiStrutturati($p)) < ($p === 'home' ? 2 : 1)) $errore = json_last_error_msg();
        }
        $c[] = $errore === '' ? ['ok', 'Dati strutturati generati senza errori.', 'seo-azienda']
                              : ['sistema', 'I dati strutturati non si generano: ' . $errore . '.', 'seo-azienda'];
        $ammessi = count(array_filter(array_keys(self::BOT), [self::class, 'botAmmesso']));
        $c[] = $ammessi > 0 ? ['ok', "Assistenti AI ammessi: $ammessi su " . count(self::BOT) . '.', 'seo-motori']
                            : ['sistema', 'Nessun assistente AI è ammesso: il sito non comparirà nelle loro risposte.', 'seo-motori'];
        $c[] = Support::baseDir() === '' ? ['ok', 'Il sito è nella radice del dominio: robots.txt e llms.txt si trovano.', 'seo-motori']
                                         : ['sistema', 'Il sito è in una sottocartella: robots.txt e llms.txt non vengono letti.', 'seo-motori'];
        return array_map(fn($x) => ['stato' => $x[0], 'testo' => $x[1], 'dove' => $x[2]], $c);
    }
}
