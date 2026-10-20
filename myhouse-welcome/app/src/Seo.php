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
        // 8J: l'Accordo sul trattamento dei dati e i documenti legali in inglese.
        'dpa' => ['/dpa', 'Accordo sul trattamento dei dati'],
        'en-termini' => ['/en/terms', 'Termini in inglese'],
        'en-privacy' => ['/en/privacy', 'Privacy in inglese'],
        'en-dpa' => ['/en/dpa', 'Accordo sul trattamento dei dati in inglese'],
        // 7F: le pagine per tipologia, solo in italiano (testi in lang/tipologie.php).
        'case-vacanza' => ['/case-vacanza', 'Per case vacanza'],
        'bed-and-breakfast' => ['/bed-and-breakfast', 'Per B&B'],
        'affittacamere' => ['/affittacamere', 'Per affittacamere'],
        'agriturismi' => ['/agriturismi', 'Per agriturismi'],
        // 9K: chi gestisce più strutture e il programma Partner.
        'piu-strutture' => ['/piu-strutture', 'Per più strutture'],
        'property-manager' => ['/property-manager', 'Per property manager'],
        // 9L: il questionario «Qual è il piano giusto per te?» come pagina (per chi non ha JavaScript, e da condividere).
        'piano' => ['/piano', 'Quale piano scegliere'],
        'en-piano' => ['/en/plan', 'Quale piano scegliere in inglese'],
        // 9L (revisione): tutte le schede approvate di «Le guide dei nostri host».
        'guide-host' => ['/guide-dei-nostri-host', 'Le guide dei nostri host (tutte)'],
    ];

    /** 7F: Blackout Agency, che realizza i siti MyHouse (8J: non più nei dati strutturati: lì c'è il titolare, City Up S.r.l.). */
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

    /** Il meta robots delle pagine indicizzate: anteprime grandi e snippet interi (Google, Bing e le risposte degli assistenti). */
    public const ROBOTS_SI = 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1';

    /** GEO · dove si mandano gli avvisi IndexNow (un solo indirizzo li gira a tutti i motori aderenti). */
    public const INDEXNOW = 'https://api.indexnow.org/indexnow';

    /** GEO · gli argomenti di cui il sito è fonte (Organization.knowsAbout): parole che le persone usano davvero. */
    public const ARGOMENTI = ['welcome book digitale', 'guida digitale per ospiti', 'check-in autonomo', 'case vacanza', 'bed and breakfast', 'affittacamere',
                              'agriturismi', 'affitti brevi', 'QR Code per strutture ricettive', 'ospitalità extralberghiera'];

    /** 7F: i file veri scritti accanto a index.php (nome => metodo che li genera). */
    public const FILE = ['robots.txt' => 'robots', 'sitemap.xml' => 'sitemap', 'llms.txt' => 'llms', 'llms-full.txt' => 'llmsCompleto', 'indexnow.txt' => 'chiaveIndexNow'];

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
            // GEO · la chiave IndexNow (Bing, Yandex, Seznam, Naver): si crea da sola la prima volta che serve.
            'indexnow.chiave' => '',
            // 8H · statistiche delle visite (vuoti = spento): Matomo senza cookie, GA4 e Clarity solo con il consenso.
            'stat.matomo_url' => '', 'stat.matomo_id' => '', 'stat.ga4' => '', 'stat.clarity' => '',
            'p.home.titolo' => 'Guida digitale per case vacanza e B&B | MyHouse Welcome',
            'p.home.descrizione' => 'Il welcome book digitale per case vacanza, B&B, affittacamere e agriturismi: check-in, Wi-Fi e consigli in un link o QR Code. Gratis finché non pubblichi.',
            'p.domande.titolo' => 'Domande frequenti | MyHouse Welcome',
            'p.piano.titolo' => 'Quale piano scegliere | MyHouse Welcome',
            'p.piano.descrizione' => 'Tre domande, una risposta: lingue, sezioni e strutture. Ti diciamo quale piano di MyHouse Welcome ti basta, senza pagare per quello che non usi.',
            'p.domande.descrizione' => 'Le risposte su MyHouse Welcome: prova gratuita, lingue e traduzioni, più strutture, B&B con più camere, fattura, cambio di piano e disdetta.',
            'p.en.titolo' => 'Digital guest guide for holiday rentals | MyHouse Welcome',
            'p.en.descrizione' => 'The digital welcome book for holiday homes, B&Bs, guesthouses and farm stays: check-in, Wi-Fi and local tips in one link or QR code. Free until you publish.',
            'p.en-domande.titolo' => 'Frequently asked questions | MyHouse Welcome',
            'p.guide-host.titolo' => 'Le guide dei nostri host | MyHouse Welcome',
            'p.guide-host.descrizione' => 'Case vacanza, B&B e agriturismi che usano MyHouse Welcome ogni giorno: le strutture degli host che hanno aderito al programma «Giudizio con sconto».',
            'p.en-piano.titolo' => 'Which plan to choose | MyHouse Welcome',
            'p.en-piano.descrizione' => 'Three questions, one answer: languages, sections and properties. We tell you which MyHouse Welcome plan is enough, without paying for what you don\'t use.',
            'p.en-domande.descrizione' => 'Answers about MyHouse Welcome: free trial, languages and translations, several properties, B&Bs with several rooms, invoices, plan changes and cancelling.',
            'p.termini.titolo' => 'Termini e condizioni | MyHouse Welcome',
            'p.termini.descrizione' => 'Le condizioni del servizio MyHouse Welcome: abbonamento annuale, prezzi e rinnovo, cambio di piano, codici sconto e contenuti della guida.',
            'p.privacy.titolo' => 'Informativa sulla privacy | MyHouse Welcome',
            'p.privacy.descrizione' => 'Come MyHouse Welcome tratta i dati di host e ospiti: quali dati raccoglie, perché, per quanto tempo, con quali fornitori e i tuoi diritti.',
            'p.dpa.titolo' => 'Accordo sul trattamento dei dati | MyHouse Welcome',
            'p.dpa.descrizione' => 'L\'accordo sul trattamento dei dati (art. 28 GDPR) tra MyHouse Welcome e gli host: istruzioni, sicurezza, sub-responsabili, violazioni e cancellazione.',
            'p.en-termini.titolo' => 'Terms and conditions | MyHouse Welcome',
            'p.en-termini.descrizione' => 'The terms of the MyHouse Welcome service: yearly subscription, prices and renewal, withdrawal, plan changes, your content and liability.',
            'p.en-privacy.titolo' => 'Privacy policy | MyHouse Welcome',
            'p.en-privacy.descrizione' => 'How MyHouse Welcome processes the data of hosts and guests: which data, why, for how long, with which providers, and your rights.',
            'p.en-dpa.titolo' => 'Data processing agreement | MyHouse Welcome',
            'p.en-dpa.descrizione' => 'The data processing agreement (art. 28 GDPR) between MyHouse Welcome and hosts: instructions, security, sub-processors, breaches and deletion.',
            'az.nome' => 'MyHouse Welcome',
            'az.venditore' => (string) ($l['company'] ?? ''),
            'az.piva' => (string) ($l['company_vat'] ?? ''),
            'az.indirizzo' => (string) ($l['company_city'] ?? ''),
            'az.email' => (string) ($l['contact_email'] ?? ''),
            'az.telefono' => (string) ($l['contact_phone'] ?? ''),
            'az.social' => '',
            'llms.intro' => 'MyHouse Welcome è il welcome book digitale (la guida digitale della casa) per case vacanza, B&B, affittacamere e agriturismi: check-in, Wi-Fi, regole della casa e consigli della zona in un link e un QR Code, che gli ospiti aprono dal telefono senza scaricare app.',
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

    public static function indicizza(string $pagina): bool
    {
        // 9N · «Tutte le guide» esiste solo con la sezione accesa e almeno una scheda: altrimenti fuori da sitemap, llms.txt e IndexNow.
        if ($pagina === 'guide-host' && !Vetrina::guideVisibili()) return false;
        return self::get("p.$pagina.indicizza", '1') === '1';
    }

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
        $h .= '<meta name="robots" content="' . (self::indicizza($pagina) ? self::ROBOTS_SI : 'noindex') . "\">\n";
        if (self::dominio() !== '') $h .= '<link rel="canonical" href="' . $e(self::dominio() . $percorso) . "\">\n";
        // 8E: le pagine che esistono nelle due lingue si indicano a vicenda (x-default è l'italiano).
        $inglese = $pagina === 'en' || str_starts_with($pagina, 'en-');
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
            . '<meta property="og:image:alt" content="' . $e(self::get('az.nome') . ($inglese ? ': the digital welcome book' : ': il welcome book digitale')) . "\">\n"
            . '<meta name="twitter:card" content="summary_large_image">' . "\n"
            . '<meta name="twitter:title" content="' . $e($titolo) . "\">\n"
            . ($descr !== '' ? '<meta name="twitter:description" content="' . $e($descr) . "\">\n" : '');
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
        // GEO · ogni altra pagina dice cos'è, di chi è e dove sta (WebPage + BreadcrumbList).
        if ($pagina !== 'home' && $pagina !== 'en' && isset(self::PAGINE[$pagina])) {
            $j = json_encode(self::paginaWeb($pagina), $f);
            if ($j !== false) $out[] = $j;
        }
        // 7F: le pagine per tipologia hanno le loro 4 domande.
        if (!empty(self::tipologie()[$pagina]['faq'])) {
            $j = json_encode(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'inLanguage' => 'it-IT',
                              'mainEntity' => array_map(fn($x) => ['@type' => 'Question', 'name' => $x[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $x[1]]],
                                                        self::tipologie()[$pagina]['faq'])], $f);
            if ($j !== false) $out[] = $j;
        }
        return $out;
    }

    /** L'indirizzo dell'azienda a pezzi («Via …, 06123 Perugia (PG)»): per Google e per le mappe. */
    public static function indirizzo(): array
    {
        $t = trim(self::get('az.indirizzo'));
        if ($t === '') return [];
        $a = ['@type' => 'PostalAddress', 'streetAddress' => $t, 'addressCountry' => 'IT'];
        if (preg_match('/^(.+?),\s*(\d{5})\s+(.+?)(?:\s*\(([A-Za-z]{2})\))?\s*$/u', $t, $m)) {
            $a = ['@type' => 'PostalAddress', 'streetAddress' => trim($m[1]), 'postalCode' => $m[2], 'addressLocality' => trim($m[3])]
               + (($m[4] ?? '') !== '' ? ['addressRegion' => strtoupper($m[4])] : []) + ['addressCountry' => 'IT'];
        }
        return $a;
    }

    /** WebPage con le briciole (Home › pagina) per le pagine che non sono una home. */
    public static function paginaWeb(string $pagina): array
    {
        $en = $pagina === 'en' || str_starts_with($pagina, 'en-');
        $home = self::assoluto($en ? '/en' : '/');
        $url = self::assoluto(self::PAGINE[$pagina][0]);
        $tp = self::tipologie()[$pagina] ?? null;
        $nome = $tp['h1'] ?? preg_replace('/\s*\|\s*' . preg_quote(self::get('az.nome'), '/') . '$/u', '', self::titolo($pagina, self::PAGINE[$pagina][1]));
        $sito = self::assoluto('/');
        $web = array_filter([
            '@type' => 'WebPage', '@id' => $url . '#pagina', 'url' => $url, 'name' => $nome,
            'description' => trim(self::get("p.$pagina.descrizione")), 'inLanguage' => $en ? 'en-GB' : 'it-IT',
            'isPartOf' => ['@id' => $sito . '#sito'], 'publisher' => ['@id' => $sito . '#organizzazione'],
            'breadcrumb' => ['@id' => $url . '#briciole'],
            // Le pagine per tipologia dicono a chi parlano.
            'audience' => $tp ? ['@type' => 'BusinessAudience', 'audienceType' => (string) ($tp['nome'] ?? $nome)] : '',
        ], fn($v) => $v !== '' && $v !== []);
        return ['@context' => 'https://schema.org', '@graph' => [$web,
            ['@type' => 'BreadcrumbList', '@id' => $url . '#briciole', 'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => self::get('az.nome'), 'item' => $home],
                ['@type' => 'ListItem', 'position' => 2, 'name' => $nome, 'item' => $url],
            ]]]];
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
            'address' => self::indirizzo() ?: '',
            // GEO · come contattarci, in che lingue, dove lavoriamo, di cosa siamo fonte.
            'contactPoint' => self::get('az.email') !== '' || self::get('az.telefono') !== '' ? [array_filter(['@type' => 'ContactPoint', 'contactType' => 'customer support',
                'email' => self::get('az.email'), 'telephone' => self::get('az.telefono'), 'availableLanguage' => ['Italian', 'English'], 'areaServed' => 'IT'])] : '',
            'areaServed' => ['@type' => 'Country', 'name' => 'Italia'],
            'knowsAbout' => self::ARGOMENTI,
            'logo' => self::conOrigine(a('/assets/apple-touch-icon.png')),
            'sameAs' => self::social(),
            // 8J: MyHouse Welcome è un servizio del titolare (City Up S.r.l.), con il suo sito.
            'parentOrganization' => array_filter(['@type' => 'Organization', 'name' => self::get('az.venditore') !== '' ? self::get('az.venditore') : (string) ((Config::get('legal') ?? [])['company'] ?? ''),
                                                  'url' => (string) ((Config::get('legal') ?? [])['company_site'] ?? '')]),
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
             'applicationCategory' => 'BusinessApplication', 'applicationSubCategory' => $en ? 'Digital welcome book for holiday rentals' : 'Welcome book digitale per strutture ricettive',
             'operatingSystem' => 'Web', 'browserRequirements' => 'Requires a modern web browser',
             'featureList' => $en ? ['Guest guide in one link and QR code, no app to download', 'Check-in, Wi-Fi, house rules and local tips', 'Up to six languages for guests',
                                     'Printed QR code that never changes', 'No cookies in the guest guide']
                                  : ['Guida per gli ospiti in un link e un QR Code, senza app da scaricare', 'Check-in, Wi-Fi, regole della casa e consigli della zona',
                                     'Fino a sei lingue per gli ospiti', 'QR Code stampato che non cambia', 'Nessun cookie nella guida degli ospiti'],
             'audience' => ['@type' => 'BusinessAudience', 'audienceType' => $en ? 'Holiday rental hosts, B&Bs, guesthouses and farm stays' : 'Host di case vacanza, B&B, affittacamere e agriturismi'],
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
        $x = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";
        $indicizzato = [];
        foreach (self::PAGINE as $k => [$percorso]) if (self::indicizza($k)) $indicizzato[$percorso] = true;
        foreach (self::PAGINE as $k => [$percorso]) {
            if (!self::indicizza($k)) continue;
            $mod = $data(match ($k) { 'termini', 'en-termini' => (string) ($l['terms_version'] ?? ''), 'privacy', 'en-privacy' => (string) ($l['privacy_version'] ?? ''),
                                      'dpa', 'en-dpa' => (string) ($l['dpa_version'] ?? ''), default => $altre });
            // GEO · la pagina gemella nell'altra lingua (come gli hreflang della testa), solo se anche lei è indicizzata.
            $it = str_starts_with($percorso, '/en') ? LinguaSito::italiano($percorso) : $percorso;
            $alt = '';
            if ($it !== null && isset(LinguaSito::COPPIE[$it], $indicizzato[$it], $indicizzato[LinguaSito::COPPIE[$it]])) {
                foreach (['it' => $it, 'en' => LinguaSito::COPPIE[$it], 'x-default' => $it] as $lin => $pp) {
                    $alt .= '<xhtml:link rel="alternate" hreflang="' . $lin . '" href="' . htmlspecialchars(self::assoluto($pp), ENT_XML1) . '"/>';
                }
            }
            $x .= '  <url><loc>' . htmlspecialchars(self::assoluto($percorso), ENT_XML1) . '</loc>' . ($mod !== '' ? "<lastmod>$mod</lastmod>" : '') . $alt . "</url>\n";
        }
        return $x . "</urlset>\n";
    }

    public static function llms(): string
    {
        $t = '# ' . self::get('az.nome') . "\n\n";
        if (($i = trim(self::get('llms.intro'))) !== '') $t .= '> ' . preg_replace('/\s*\R\s*/', ' ', $i) . "\n\n";
        $fatti = array_filter(array_map('trim', preg_split('/\R/', self::get('llms.fatti')) ?: []));
        if ($fatti) $t .= "## Fatti chiave\n\n" . implode('', array_map(fn($f) => "- $f\n", $fatti)) . "\n";
        $t .= 'Tutte le risposte per intero: ' . self::assoluto('/llms-full.txt') . "\n\n";
        $t .= "## Piani (prezzi annuali, IVA esclusa)\n\n";
        foreach (Plans::public() as $p) {
            $m = fn(int $c) => str_replace("\u{00A0}", ' ', Support::money($c, $p['currency']));
            $riga = '- ' . $p['name'] . ': ';
            if (Plans::perProperty($p)) {
                $riga .= $m((int) $p['price_cents']) . ' la prima struttura, poi '
                    . implode('; ', array_map(fn($x) => $m($x['cents']) . ' ' . Plans::tierLabel($x, (int) $p['max_quantity']), Plans::tiers($p)))
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
        // GEO · le altre pagine utili per decidere.
        $altre = array_filter(['piano' => 'Quale piano scegliere: tre domande, una risposta', 'guide-host' => 'Le guide dei nostri host'], fn($k) => self::indicizza($k), ARRAY_FILTER_USE_KEY);
        if ($altre) {
            $t .= "\n## Altre pagine\n\n";
            foreach ($altre as $k => $nome) $t .= '- [' . $nome . '](' . self::assoluto(self::PAGINE[$k][0]) . ")\n";
        }
        // 7F: i giudizi, solo se ce ne sono di pubblicati (mai numeri inventati).
        $giudizi = Testimonianze::visibili();
        if ($giudizi) {
            $media = Testimonianze::media();
            $t .= "\n## Giudizi\n\n- Sul sito ci sono " . (count($giudizi) === 1 ? '1 testimonianza' : count($giudizi) . ' testimonianze') . ' di host che usano MyHouse Welcome'
                . ($media ? ', con una media di ' . number_format($media['media'], 1, ',', '') . ' su 5 da ' . $media['n'] . ' giudizi' : '') . ': ' . self::assoluto('/') . "\n";
        }
        // 9L: «Le guide dei nostri host», solo se ce ne sono di accese.
        if (($schede = Vetrina::perHome())) {
            $t .= ($giudizi ? '' : "\n## Giudizi\n\n") . '- In home ci ' . (count($schede) === 1 ? 'è 1 guida' : 'sono ' . count($schede) . ' guide') . ' di host che usano MyHouse Welcome («Le guide dei nostri host»), senza link alle guide: ' . self::assoluto('/') . "\n";
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

    /**
     * GEO · llms-full.txt: tutto quello che un assistente AI deve sapere per rispondere su MyHouse Welcome senza aprire
     * altre pagine: llms.txt, poi le domande frequenti con le risposte intere (italiano e inglese) e le pagine per tipologia.
     */
    public static function llmsCompleto(): string
    {
        $pulito = fn(string $x) => trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($x), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $t = self::llms() . "\n## Domande frequenti, con le risposte\n\n";
        foreach (Faq::tutte('it') as $x) $t .= '### ' . $pulito($x['d']) . "\n\n" . $pulito($x['r']) . "\n\n";
        $tipi = array_filter(self::tipologie(), fn($k) => self::indicizza($k), ARRAY_FILTER_USE_KEY);
        if ($tipi) {
            $t .= "## Per chi è, nel dettaglio\n\n";
            foreach ($tipi as $tp) {
                $t .= '### ' . $pulito((string) $tp['h1']) . "\n\n" . self::assoluto((string) $tp['percorso']) . "\n\n";
                foreach (['descrizione', 'intro'] as $c) if (trim((string) ($tp[$c] ?? '')) !== '') $t .= $pulito((string) $tp[$c]) . "\n\n";
                foreach ((array) ($tp['faq'] ?? []) as $q) $t .= '- ' . $pulito((string) $q[0]) . ' ' . $pulito((string) $q[1]) . "\n";
                if (!empty($tp['faq'])) $t .= "\n";
            }
        }
        if (self::indicizza('en') || self::indicizza('en-domande')) {
            $t .= "## Frequently asked questions (English)\n\n";
            foreach (Faq::tutte('en') as $x) $t .= '### ' . $pulito($x['d']) . "\n\n" . $pulito($x['r']) . "\n\n";
        }
        return rtrim($t) . "\n";
    }

    // ------------------------------------------------------------------ IndexNow (GEO)

    /** La chiave IndexNow: quella salvata, o una nuova (32 caratteri esadecimali) salvata adesso. */
    public static function chiaveIndexNow(): string
    {
        $k = trim(self::get('indexnow.chiave'));
        if (preg_match('/^[a-f0-9]{32}$/', $k)) return $k;
        $k = bin2hex(random_bytes(16));
        if (self::disponibili()) self::set(['indexnow.chiave' => $k]);
        return $k;
    }

    private static function statoIndexNow(): string { return MHW_APP . '/storage/indexnow.json'; }

    /** L'ultimo avviso IndexNow. @return array{quando:string,ok:bool,msg:string,n:int}|null */
    public static function esitoIndexNow(): ?array
    {
        $j = is_file(self::statoIndexNow()) ? json_decode((string) @file_get_contents(self::statoIndexNow()), true) : null;
        return is_array($j) ? ['quando' => (string) ($j['quando'] ?? ''), 'ok' => !empty($j['ok']), 'msg' => (string) ($j['msg'] ?? ''), 'n' => (int) ($j['n'] ?? 0)] : null;
    }

    /**
     * GEO · Avvisa Bing (e con lui Yandex, Seznam, Naver; Bing alimenta anche la ricerca di ChatGPT e Copilot) che le
     * pagine pubbliche sono cambiate: un POST con gli indirizzi della sitemap. Solo con il dominio in https, perché gli
     * indirizzi devono essere quelli veri. @return array{0:bool,1:string}
     */
    public static function avvisaIndexNow(): array
    {
        $dom = self::dominio();
        if (!str_starts_with($dom, 'https://')) return [false, 'Prima imposta il dominio con https:// (Motori e assistenti AI): IndexNow vuole gli indirizzi veri del sito.'];
        $urls = [];
        foreach (self::PAGINE as $k => [$percorso]) if (self::indicizza($k)) $urls[] = self::assoluto($percorso);
        $corpo = ['host' => (string) parse_url($dom, PHP_URL_HOST), 'key' => self::chiaveIndexNow(), 'keyLocation' => self::assoluto('/indexnow.txt'), 'urlList' => $urls];
        $ch = curl_init((string) (Config::get('indexnow_endpoint') ?: self::INDEXNOW));
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($corpo, JSON_UNESCAPED_SLASHES), CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10,
                                CURLOPT_HTTPHEADER => ['Content-Type: application/json; charset=utf-8']]);
        $risposta = (string) curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        $ok = in_array($code, [200, 202], true);
        $msg = $ok ? count($urls) . ' pagine segnalate a Bing e agli altri motori IndexNow.'
             : match ($code) {
                 0 => 'IndexNow non risponde' . ($err !== '' ? ': ' . $err : '') . '.',
                 403 => 'IndexNow non trova la chiave: controlla che ' . self::assoluto('/indexnow.txt') . ' si apra (premi «Salva» per scrivere il file).',
                 422 => 'IndexNow rifiuta gli indirizzi: il dominio salvato non è quello del sito.',
                 429 => 'Troppi avvisi in poco tempo: riprova più tardi.',
                 default => 'IndexNow ha risposto ' . $code . ($risposta !== '' ? ': ' . mb_substr(strip_tags($risposta), 0, 160) : '') . '.',
             };
        @file_put_contents(self::statoIndexNow(), json_encode(['quando' => Support::now(), 'ok' => $ok, 'msg' => $msg, 'n' => count($urls)], JSON_UNESCAPED_UNICODE), LOCK_EX);
        if (!$ok) Log::error('Seo::avvisaIndexNow: ' . $msg);
        return [$ok, $msg];
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
        $msg = $ok ? implode(', ', array_keys(self::FILE)) . ' scritti nella cartella principale del sito.'
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
             : ($esito === null ? ['guarda', 'robots.txt, sitemap.xml, llms.txt e llms-full.txt non sono ancora stati scritti come file: premi «Salva».', 'seo-motori']
             : ['ok', 'robots.txt, sitemap.xml, llms.txt e llms-full.txt sono anche file veri nella cartella principale (aggiornati il ' . Support::date($esito['quando']) . ').', 'seo-motori']));
        // GEO · IndexNow e l'indirizzo dell'azienda a pezzi.
        $ix = self::esitoIndexNow();
        $c[] = $ix === null ? ['guarda', 'Bing e gli altri motori IndexNow non sono mai stati avvisati: con il dominio impostato premi «Avvisa i motori».', 'seo-indexnow']
             : ($ix['ok'] ? ['ok', 'IndexNow: ' . $ix['msg'] . ' (' . Support::date($ix['quando']) . ').', 'seo-indexnow'] : ['sistema', 'IndexNow: ' . $ix['msg'], 'seo-indexnow']);
        $ind = self::indirizzo();
        $c[] = isset($ind['postalCode']) ? ['ok', 'Indirizzo dell\'azienda leggibile dalle mappe (' . $ind['addressLocality'] . ', ' . $ind['postalCode'] . ').', 'seo-azienda']
             : ['guarda', 'L\'indirizzo dell\'azienda non è nella forma «Via …, CAP Città (PR)»: Google lo legge come una riga sola.', 'seo-azienda'];
        return array_map(fn($x) => ['stato' => $x[0], 'testo' => $x[1], 'dove' => $x[2]], $c);
    }
}
