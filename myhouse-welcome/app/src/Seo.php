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
        // 8E: la home e le domande in inglese.
        'en' => ['/en', 'Home in inglese'],
        'en-domande' => ['/en/questions', 'Domande in inglese'],
        'termini' => ['/termini', 'Termini e condizioni'],
        'privacy' => ['/privacy', 'Privacy'],
        // 7F: le pagine per tipologia, solo in italiano (testi in lang/tipologie.php).
        'case-vacanza' => ['/case-vacanza', 'Per case vacanza'],
        'bed-and-breakfast' => ['/bed-and-breakfast', 'Per B&B'],
        'affittacamere' => ['/affittacamere', 'Per affittacamere'],
        'agriturismi' => ['/agriturismi', 'Per agriturismi'],
    ];

    /** 7F: il gruppo che pubblica MyHouse Welcome (dati strutturati: sameAs e parentOrganization). */
    public const GRUPPO = 'https://blackout.in';

    /** 7F: i testi delle pagine per tipologia, per chiave (case-vacanza, bed-and-breakfast, affittacamere, agriturismi). */
    public static function tipologie(): array
    {
        static $t = null;
        return $t ??= (array) require MHW_APP . '/lang/tipologie.php';
    }

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

    /** 7F: i file veri scritti accanto a index.php (nome => metodo che li genera). */
    public const FILE = ['robots.txt' => 'robots', 'sitemap.xml' => 'sitemap', 'llms.txt' => 'llms'];

    /** 7F: la risposta della rotta /indirizzi-puliti, la sonda di Impostazioni::provaIndirizzi. */
    public const SONDA = 'MHW indirizzi puliti';

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
            'p.domande.descrizione' => 'Le risposte su MyHouse Welcome: prova gratuita, lingue e traduzioni, più strutture, B&B con più camere, fattura, cambio di piano e disdetta.',
            'p.en.titolo' => 'Digital guest guide for holiday rentals | MyHouse Welcome',
            'p.en.descrizione' => 'The digital guide for holiday homes, B&Bs, guesthouses and farm stays: check-in, Wi-Fi and local tips in one link and QR code. Build it free, pay when you publish.',
            'p.en-domande.titolo' => 'Frequently asked questions | MyHouse Welcome',
            'p.en-domande.descrizione' => 'Answers about MyHouse Welcome: free trial, languages and translations, several properties, B&Bs with several rooms, invoices, plan changes and cancelling.',
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
                'Lingue per gli ospiti: con Essential la lingua dell\'host e una a scelta; con Plus e Portfolio sei (italiano, inglese, francese, tedesco, spagnolo e una a scelta tra dieci, tra cui il portoghese del Portogallo e quello del Brasile).',
                'Il QR Code stampato non cambia quando si modifica la guida.',
                'Le guide degli ospiti non usano cookie e non compaiono nei motori di ricerca.',
            ]),
        ];
        foreach (self::tipologie() as $k => $tp) { $d["p.$k.titolo"] = $tp['titolo']; $d["p.$k.descrizione"] = $tp['descrizione']; }
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

    /**
     * L'indirizzo assoluto di un percorso: con il dominio, se c'è, altrimenti con quello di adesso.
     * 7F: con gli indirizzi puliti accesi mai index.php (canonical, og:url, sitemap, robots.txt, llms.txt,
     * dati strutturati), anche se il dominio salvato lo contiene o la richiesta è arrivata con index.php.
     */
    public static function assoluto(string $percorso): string
    {
        $d = self::dominio();
        $base = $d !== '' ? $d : Support::baseUrl();
        if (Config::get('pretty_urls') === true) $base = (string) preg_replace('#/index\.php$#', '', $base);
        return $base . $percorso;
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
        // 8E: le pagine che esistono nelle due lingue si indicano a vicenda (x-default è l'italiano).
        $inglese = in_array($pagina, ['en', 'en-domande'], true);
        $it = $inglese ? LinguaSito::italiano($percorso) : $percorso;
        if ($it !== null && isset(LinguaSito::COPPIE[$it])) {
            $h .= '<link rel="alternate" hreflang="it" href="' . $e(self::assoluto($it)) . "\">\n"
                . '<link rel="alternate" hreflang="en" href="' . $e(self::assoluto(LinguaSito::COPPIE[$it])) . "\">\n"
                . '<link rel="alternate" hreflang="x-default" href="' . $e(self::assoluto($it)) . "\">\n";
        }
        $h .= '<meta property="og:type" content="website">' . "\n"
            . '<meta property="og:locale" content="' . ($inglese ? 'en_GB' : 'it_IT') . "\">\n"
            . '<meta property="og:site_name" content="' . $e(self::get('az.nome')) . "\">\n"
            . '<meta property="og:title" content="' . $e($titolo) . "\">\n"
            . ($descr !== '' ? '<meta property="og:description" content="' . $e($descr) . "\">\n" : '')
            . '<meta property="og:url" content="' . $e($url) . "\">\n"
            . '<meta property="og:image" content="' . $e($img['url']) . "\">\n"
            . ($img['caricata'] ? '' : "<meta property=\"og:image:width\" content=\"1200\">\n<meta property=\"og:image:height\" content=\"630\">\n")
            . '<meta name="twitter:card" content="summary_large_image">' . "\n";
        if ($pagina === 'home') {   // le verifiche solo sulla home italiana
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
        if ($pagina === 'home' || $pagina === 'en') {
            $j = json_encode(self::grafoHome($pagina === 'en' ? 'en' : 'it'), $f);
            if ($j !== false) $out[] = $j;
        }
        if (in_array($pagina, ['home', 'domande', 'en', 'en-domande'], true)) {
            $j = json_encode(self::faqPage(str_starts_with($pagina, 'en') ? 'en' : 'it'), $f);
            if ($j !== false) $out[] = $j;
        }
        // 7F: le pagine per tipologia hanno le loro 4 domande.
        if (isset(self::tipologie()[$pagina])) {
            $j = json_encode(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'inLanguage' => 'it-IT',
                              'mainEntity' => array_map(fn($x) => ['@type' => 'Question', 'name' => $x[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $x[1]]],
                                                        self::tipologie()[$pagina]['faq'])], $f);
            if ($j !== false) $out[] = $j;
        }
        return $out;
    }

    /** Organization, WebSite e SoftwareApplication con un'offerta per piano pubblico in vendita. */
    public static function grafoHome(string $loc = 'it'): array
    {
        $home = self::assoluto('/');
        $en = $loc === 'en';
        $org = array_filter([
            '@type' => 'Organization', '@id' => $home . '#organizzazione',
            'name' => self::get('az.nome'), 'url' => $home,
            'legalName' => self::get('az.venditore'),
            'vatID' => self::get('az.piva') !== '' ? 'IT' . preg_replace('/^IT/i', '', preg_replace('/\s+/', '', self::get('az.piva'))) : '',
            'email' => self::get('az.email'), 'telephone' => self::get('az.telefono'),
            'address' => self::get('az.indirizzo') !== '' ? ['@type' => 'PostalAddress', 'streetAddress' => self::get('az.indirizzo'), 'addressCountry' => 'IT'] : '',
            'logo' => self::conOrigine(a('/assets/apple-touch-icon.png')),
            'sameAs' => array_values(array_unique(array_merge(self::social(), [self::GRUPPO]))),
            // 7F: MyHouse Welcome è un progetto di Blackout Agency (blackout.in).
            'parentOrganization' => ['@type' => 'Organization', 'name' => self::get('az.venditore') !== '' ? self::get('az.venditore') : 'Blackout Agency', 'url' => self::GRUPPO],
        ], fn($v) => $v !== '' && $v !== []);
        if (!is_file(MHW_PUBLIC . '/assets/apple-touch-icon.png')) unset($org['logo']);
        $offerte = [];
        foreach (Plans::public() as $p) {
            $q = Plans::perProperty($p) ? max(1, (int) $p['min_quantity']) : 1;
            $prezzo = number_format(Plans::price($p, $q) / 100, 2, '.', '');
            $offerte[] = [
                '@type' => 'Offer', 'name' => $p['name'] . ($q > 1 ? ' (' . $q . ($en ? ' properties)' : ' strutture)') : ''),
                'price' => $prezzo, 'priceCurrency' => 'EUR', 'url' => ($en ? self::assoluto('/en') : $home) . '#piani',
                'priceSpecification' => ['@type' => 'UnitPriceSpecification', 'price' => $prezzo, 'priceCurrency' => 'EUR',
                                         'valueAddedTaxIncluded' => false, 'unitText' => $en ? 'year' : 'anno'],
            ];
        }
        return ['@context' => 'https://schema.org', '@graph' => [
            $org,
            ['@type' => 'WebSite', '@id' => $home . '#sito', 'name' => self::get('az.nome'), 'url' => $home, 'inLanguage' => ['it-IT', 'en-GB'],
             'publisher' => ['@id' => $home . '#organizzazione']],
            ['@type' => 'SoftwareApplication', 'name' => self::get('az.nome'), 'url' => $en ? self::assoluto('/en') : $home, 'inLanguage' => $en ? 'en-GB' : 'it-IT',
             'applicationCategory' => 'BusinessApplication', 'operatingSystem' => 'Web',
             'description' => self::get($en ? 'p.en.descrizione' : 'p.home.descrizione'),
             'publisher' => ['@id' => $home . '#organizzazione'], 'offers' => $offerte],
        ]];
    }

    public static function faqPage(string $loc = 'it'): array
    {
        return ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'inLanguage' => $loc === 'en' ? 'en-GB' : 'it-IT',
                'mainEntity' => array_map(fn($x) => ['@type' => 'Question', 'name' => $x['d'],
                                                     'acceptedAnswer' => ['@type' => 'Answer', 'text' => $x['r']]], Faq::tutte($loc))];
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
        // 7F: le pagine per tipologia.
        $tipi = array_filter(self::tipologie(), fn($k) => self::indicizza($k), ARRAY_FILTER_USE_KEY);
        if ($tipi) {
            $t .= "\n## Per chi è\n\n";
            foreach ($tipi as $k => $tp) $t .= '- [' . $tp['h1'] . '](' . self::assoluto($tp['percorso']) . ")\n";
        }
        // 7F: i giudizi, solo se ce ne sono di pubblicati (mai numeri inventati).
        $giudizi = Testimonianze::visibili();
        if ($giudizi) {
            $media = Testimonianze::media();
            $t .= "\n## Giudizi\n\n- Sul sito ci sono " . (count($giudizi) === 1 ? '1 testimonianza' : count($giudizi) . ' testimonianze') . ' di host che usano MyHouse Welcome'
                . ($media ? ', con una media di ' . number_format($media['media'], 1, ',', '') . ' su 5 da ' . $media['n'] . ' giudizi' : '') . ': ' . self::assoluto('/') . "\n";
        }
        // 8E: la home e le domande in inglese.
        if (self::indicizza('en') || self::indicizza('en-domande')) {
            $t .= "\n## English\n\n";
            if (self::indicizza('en')) $t .= '- [MyHouse Welcome, the digital guest guide](' . self::assoluto('/en') . ")\n";
            if (self::indicizza('en-domande')) $t .= '- [Frequently asked questions](' . self::assoluto('/en/questions') . ")\n";
        }
        $t .= "\n## Contatti\n\n- Sito: " . self::assoluto('/') . "\n";
        foreach (['az.email' => 'Email', 'az.telefono' => 'Telefono'] as $k => $n) if (self::get($k) !== '') $t .= "- $n: " . self::get($k) . "\n";
        $chi = implode(', ', array_filter([self::get('az.venditore'), self::get('az.indirizzo'), self::get('az.piva') !== '' ? 'P.IVA ' . self::get('az.piva') : '']));
        if ($chi !== '') $t .= "- Fornitore: $chi\n";
        foreach (self::social() as $s) $t .= "- $s\n";
        return $t;
    }

    // ------------------------------------------------------------------ file veri (7F)

    private static function statoFile(): string { return MHW_APP . '/storage/seo-file.json'; }

    /** La cartella principale del sito, accanto a index.php ('' se non si sa: riga di comando senza MHW_PUBLIC). */
    public static function cartella(): string { return defined('MHW_PUBLIC') ? (string) MHW_PUBLIC : ''; }

    /** Si possono scrivere i tre file? (la cartella, e i file se ci sono già) */
    public static function scrivibili(): bool
    {
        $d = self::cartella();
        if ($d === '' || !is_dir($d)) return false;
        foreach (array_keys(self::FILE) as $n) if (is_file("$d/$n") && !is_writable("$d/$n")) return false;
        return is_writable($d);
    }

    /**
     * Scrive robots.txt, sitemap.xml e llms.txt nella cartella principale, così il server li dà anche dove
     * la riscrittura degli indirizzi non funziona. Chiamata dal salvataggio di «SEO e GEO», dai cambi dei
     * piani, dalle testimonianze e una volta al giorno dal giro dei Richiami.
     * $daRichiesta: false (giro automatico) = si scrive solo con il dominio o l'indirizzo pubblico impostati,
     * perché altrimenti gli indirizzi verrebbero dalla richiesta di chiunque (intestazione Host).
     * @return array{0:bool,1:string} [riuscito, messaggio]
     */
    public static function scriviFile(bool $daRichiesta = true): array
    {
        if (!self::disponibili()) return [false, 'Manca la tabella delle impostazioni SEO.'];
        if (!$daRichiesta && self::dominio() === '' && (string) (Config::get('base_url') ?? '') === '') {
            return [false, 'Senza dominio né indirizzo pubblico i file si scrivono solo dall\'amministrazione.'];
        }
        $d = self::cartella();
        $errori = [];
        foreach (self::FILE as $nome => $metodo) {
            try { $testo = self::$metodo(); } catch (\Throwable $e) { Log::exception($e, "Seo::scriviFile $nome"); $errori[] = $nome; continue; }
            $f = "$d/$nome";
            if ($d !== '' && is_file($f) && @file_get_contents($f) === $testo) continue;
            $tmp = "$f." . bin2hex(random_bytes(4)) . '.tmp';
            if ($d === '' || @file_put_contents($tmp, $testo, LOCK_EX) === false || !@rename($tmp, $f)) { @unlink($tmp); $errori[] = $nome; }
            else @chmod($f, 0644);
        }
        $ok = !$errori;
        $msg = $ok ? 'robots.txt, sitemap.xml e llms.txt scritti nella cartella principale del sito.'
                   : 'Non riesco a scrivere ' . implode(', ', $errori) . ' nella cartella principale del sito (' . ($d ?: '?') . '): rendila scrivibile, oppure i motori li leggono solo dove la riscrittura degli indirizzi funziona.';
        @file_put_contents(self::statoFile(), json_encode(['quando' => Support::now(), 'ok' => $ok, 'msg' => $msg], JSON_UNESCAPED_UNICODE), LOCK_EX);
        if (!$ok) Log::error('Seo::scriviFile: ' . $msg);
        return [$ok, $msg];
    }

    /** Una volta al giorno (dal giro dei Richiami): riscrive i file se l'ultima volta è di più di 24 ore fa. */
    public static function forseFile(): void
    {
        $f = self::statoFile();
        if (is_file($f) && time() - (int) @filemtime($f) < 86400) return;
        try { self::scriviFile(false); } catch (\Throwable $e) { Log::exception($e, 'Seo::forseFile'); }
    }

    /** L'ultimo esito della scrittura dei file. @return array{quando:string,ok:bool,msg:string}|null */
    public static function esitoFile(): ?array
    {
        $f = self::statoFile();
        $j = is_file($f) ? json_decode((string) @file_get_contents($f), true) : null;
        return is_array($j) ? ['quando' => (string) ($j['quando'] ?? ''), 'ok' => !empty($j['ok']), 'msg' => (string) ($j['msg'] ?? '')] : null;
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
        // 7F: i file veri accanto a index.php.
        $esito = self::esitoFile();
        $c[] = !self::scrivibili() ? ['sistema', 'La cartella principale del sito non è scrivibile: robots.txt, sitemap.xml e llms.txt non si possono scrivere come file. Dove la riscrittura degli indirizzi non funziona, i motori non li trovano.', 'seo-motori']
             : ($esito && !$esito['ok'] ? ['sistema', $esito['msg'], 'seo-motori']
             : ($esito === null ? ['guarda', 'robots.txt, sitemap.xml e llms.txt non sono ancora stati scritti come file: premi «Salva».', 'seo-motori']
             : ['ok', 'robots.txt, sitemap.xml e llms.txt sono anche file veri nella cartella principale (aggiornati il ' . Support::date($esito['quando']) . ').', 'seo-motori']));
        return array_map(fn($x) => ['stato' => $x[0], 'testo' => $x[1], 'dove' => $x[2]], $c);
    }
}
