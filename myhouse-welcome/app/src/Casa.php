<?php
namespace MHW;

/**
 * 9V · «La casa»: la galleria di foto (al massimo 10) e la descrizione che l'ospite apre toccando la foto di
 * copertina della guida. Inclusa in tutti i piani, senza sovrapprezzo.
 *
 * Regole in un posto solo:
 *  - le foto si riducono al caricamento (Media::storeImage con lo scopo «gallery»: lato lungo 1600 px, al massimo
 *    300 KB) e nel database c'è solo il riferimento al file (property_gallery.media_id);
 *  - il limite di 10 foto e quelli dei testi (titolo 60, descrizione 600, scritta sulla copertina 24 battute) si
 *    controllano qui, sul server: una richiesta costruita a mano incontra gli stessi controlli. Le battute sono i
 *    caratteri veri (mb_strlen): una lettera accentata o un'emoji semplice contano uno, come nel contatore del pannello;
 *  - titolo, descrizione e scritta sono nella lingua principale della guida (properties.casa_*); le altre lingue in
 *    properties.casa_tr (JSON), tradotte dalla pagina di ogni lingua come gli altri testi (Traduttore, tipo «casa»);
 *  - la guida dell'ospite legge tutto dall'istantanea pubblicata (Guide::build): come per le sezioni, le modifiche si
 *    vedono dopo «Pubblica le modifiche». Senza foto e senza descrizione, o con l'interruttore spento, l'istantanea
 *    non ha la chiave «casa» e la copertina resta com'era;
 *  - una foto tolta dal pannello resta nello storage finché la guida pubblicata la mostra (Media::rilascia), poi la
 *    toglie la pubblicazione successiva; con la struttura o l'account se ne vanno tutte (sono righe di media).
 */
final class Casa
{
    public const MAX_FOTO = 10;
    public const MAX_TITOLO = 60;
    public const MAX_DESCRIZIONE = 600;
    public const MAX_INVITO = 24;
    /** Il lato lungo e il peso massimi di una foto della galleria salvata. */
    public const LATO = 1600;
    public const PESO = 300000;   // 300 KB
    /** Il lato della miniatura (Media::ridotta) per la griglia del pannello e per lo srcset della pagina. */
    public const MINIATURA = 480;
    /** Per quanti giorni dalla pubblicazione della funzione il sito mostra il segno «Novità» (Impostazioni → Sito lo cambia). */
    public const NOVITA_GIORNI = 60;
    /** La funzione nel catalogo dei piani. */
    public const FEATURE = 'gallery';

    public static function disponibile(): bool
    {
        // Si ricorda solo il «sì»: durante un aggiornamento la tabella nasce a metà della richiesta (migrazione 072).
        static $c = false;
        return $c = $c || (Migrator::tableExists('property_gallery') && Migrator::columnExists('properties', 'casa_tr'));
    }

    // ------------------------------------------------------------------ foto

    /** @return list<array{id:int,media_id:int,position:int,alt:string}> le foto della galleria, nell'ordine */
    public static function foto(int $pid): array
    {
        if (!self::disponibile()) return [];
        return array_map(fn($f) => ['id' => (int) $f['id'], 'media_id' => (int) $f['media_id'], 'position' => (int) $f['position'], 'alt' => (string) $f['alt']],
                         Db::all('SELECT id, media_id, position, alt FROM property_gallery WHERE property_id = ? ORDER BY position, id', [$pid]));
    }

    public static function quante(int $pid): int
    {
        return self::disponibile() ? (int) Db::val('SELECT COUNT(*) FROM property_gallery WHERE property_id = ?', [$pid], 0) : 0;
    }

    /** @return int[] i file della galleria (per non toglierli come orfani) */
    public static function mediaIds(int $pid): array
    {
        return array_column(self::foto($pid), 'media_id');
    }

    /** I file di un campo «foto[]» del modulo, uno per voce (come li manda il browser con «multiple»). @return list<array> */
    public static function elencoFile(array $campo): array
    {
        if (!isset($campo['name'])) return [];
        if (!is_array($campo['name'])) return ($campo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE ? [] : [$campo];
        $out = [];
        foreach ($campo['name'] as $i => $nome) {
            if (($campo['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
            $out[] = ['name' => $nome, 'type' => $campo['type'][$i] ?? '', 'tmp_name' => $campo['tmp_name'][$i] ?? '',
                      'error' => $campo['error'][$i] ?? UPLOAD_ERR_NO_FILE, 'size' => $campo['size'][$i] ?? 0];
        }
        return $out;
    }

    /**
     * Carica più foto insieme. Entrano quelle che ci stanno (10 in tutto); delle altre si dice quante sono rimaste fuori.
     * Il limite si ricontrolla dentro la transazione, a scrittura fatta: due richieste insieme non lo superano.
     * @param list<array> $file da self::elencoFile()
     * @return array{caricate:list<array{id:int,media_id:int,bytes:int}>,fuori:int,errori:list<string>,prima:int,dopo:int}
     *         prima/dopo: i byte arrivati e quelli salvati (per «da 6,2 MB a 240 KB»)
     */
    public static function carica(int $accountId, array $prop, array $file): array
    {
        $pid = (int) $prop['id'];
        $esito = ['caricate' => [], 'fuori' => 0, 'errori' => [], 'prima' => 0, 'dopo' => 0];
        foreach ($file as $f) {
            if (self::quante($pid) >= self::MAX_FOTO) { $esito['fuori']++; continue; }
            try {
                $mid = Media::storeImage($f, $accountId, $pid, '', 'gallery');
            } catch (\RuntimeException $e) {
                $esito['errori'][] = trim((string) ($f['name'] ?? '')) !== '' ? '«' . mb_substr((string) $f['name'], 0, 60) . '»: ' . $e->getMessage() : $e->getMessage();
                continue;
            }
            $gid = Db::tx(function () use ($pid, $mid) {
                $pos = (int) Db::val('SELECT COALESCE(MAX(position), 0) + 1 FROM property_gallery WHERE property_id = ?', [$pid], 1);
                $gid = Db::insert('property_gallery', ['property_id' => $pid, 'media_id' => $mid, 'position' => $pos, 'alt' => '', 'created_at' => Support::now()]);
                return self::quante($pid) > self::MAX_FOTO ? 0 : $gid;
            });
            if (!$gid) {   // arrivata insieme a un'altra richiesta: oltre le 10 non resta
                Db::run('DELETE FROM property_gallery WHERE property_id = ? AND media_id = ?', [$pid, $mid]);
                Media::delete($mid, $accountId);
                $esito['fuori']++;
                continue;
            }
            $bytes = (int) (Media::row($mid)['bytes'] ?? 0);
            $esito['caricate'][] = ['id' => $gid, 'media_id' => $mid, 'bytes' => $bytes];
            $esito['prima'] += (int) ($f['size'] ?? 0); $esito['dopo'] += $bytes;
        }
        return $esito;
    }

    /** Il messaggio dopo un caricamento: quante sono entrate, quante sono rimaste fuori, cosa non è andato. [testo, tipo del flash] */
    public static function messaggio(array $esito): array
    {
        $n = count($esito['caricate']);
        $parti = [];
        if ($n) $parti[] = $n === 1 ? Pannello::t('1 foto caricata.') : strtr(Pannello::t('{n} foto caricate.'), ['{n}' => $n]);
        if ($esito['fuori']) {
            $parti[] = strtr(Pannello::t($esito['fuori'] === 1 ? 'La galleria ha al massimo {max} foto: 1 è rimasta fuori. Togline una per fare posto.'
                                                             : 'La galleria ha al massimo {max} foto: {n} sono rimaste fuori. Togline qualcuna per fare posto.'),
                             ['{max}' => self::MAX_FOTO, '{n}' => $esito['fuori']]);
        }
        foreach ($esito['errori'] as $e) $parti[] = $e;
        if (!$parti) $parti[] = Pannello::t('Nessuna foto scelta.');
        return [implode(' ', $parti), $esito['errori'] || (!$n && !$esito['fuori']) ? 'err' : ($esito['fuori'] ? 'avviso' : 'ok')];
    }

    /** Un peso in chiaro: «240 KB», «2,6 MB». */
    public static function peso(int $byte): string
    {
        return $byte < 1048576 ? max(1, (int) round($byte / 1024)) . ' KB' : rtrim(rtrim(number_format($byte / 1048576, 1, ',', ''), '0'), ',') . ' MB';
    }

    /** Toglie una foto dalla galleria (il file resta finché la guida pubblicata lo mostra). */
    public static function togli(int $accountId, int $pid, int $gid): bool
    {
        $f = Db::one('SELECT id, media_id FROM property_gallery WHERE id = ? AND property_id = ?', [$gid, $pid]);
        if (!$f) return false;
        Db::run('DELETE FROM property_gallery WHERE id = ?', [$gid]);
        Media::rilascia((int) $f['media_id'], $accountId);
        self::rinumera($pid);
        return true;
    }

    /** Le posizioni tornano 1, 2, 3… nell'ordine di adesso. */
    private static function rinumera(int $pid): void
    {
        $n = 0;
        foreach (Db::all('SELECT id FROM property_gallery WHERE property_id = ? ORDER BY position, id', [$pid]) as $f) {
            Db::update('property_gallery', ['position' => ++$n], 'id = :id', ['id' => (int) $f['id']]);
        }
    }

    /** L'ordine nuovo, dall'elenco degli id (trascinando): gli id di un'altra struttura si ignorano, quelli che mancano restano in fondo. */
    public static function ordina(int $pid, array $ids): void
    {
        $miei = array_map('intval', array_column(Db::all('SELECT id FROM property_gallery WHERE property_id = ? ORDER BY position, id', [$pid]), 'id'));
        $nuovo = array_values(array_unique(array_filter(array_map('intval', $ids), fn($id) => in_array($id, $miei, true))));
        foreach ($miei as $id) if (!in_array($id, $nuovo, true)) $nuovo[] = $id;
        Db::tx(function () use ($nuovo) {
            foreach ($nuovo as $i => $id) Db::update('property_gallery', ['position' => $i + 1], 'id = :id', ['id' => $id]);
        });
    }

    /** Sposta una foto di un posto (le frecce: tastiera, telefono, senza JavaScript). $dir: 'su' (prima) | 'giu' (dopo). */
    public static function sposta(int $pid, int $gid, string $dir): void
    {
        $ids = array_map('intval', array_column(Db::all('SELECT id FROM property_gallery WHERE property_id = ? ORDER BY position, id', [$pid]), 'id'));
        $i = array_search($gid, $ids, true);
        if ($i === false) return;
        $j = $dir === 'su' ? $i - 1 : $i + 1;
        if ($j < 0 || $j >= count($ids)) return;
        [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
        self::ordina($pid, $ids);
    }

    /** Le descrizioni delle foto (per chi usa un lettore di schermo), facoltative: [id della foto => testo]. */
    public static function salvaAlt(int $pid, array $alt): void
    {
        foreach ($alt as $gid => $t) {
            Db::update('property_gallery', ['alt' => mb_substr(self::unaRiga((string) $t), 0, 250)], 'id = :id AND property_id = :pid', ['id' => (int) $gid, 'pid' => $pid]);
        }
    }

    // ----------------------------------------------------------------- testi

    /** Un testo su una riga sola: spazi e a capo diventano uno spazio. */
    public static function unaRiga(string $t): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', self::normale($t)));
    }

    /** La descrizione: gli a capo restano (al massimo una riga vuota di fila), gli spazi in fondo alle righe no. */
    public static function paragrafi(string $t): string
    {
        $t = str_replace(["\r\n", "\r"], "\n", self::normale($t));
        $t = (string) preg_replace('/[ \t]+\n/u', "\n", $t);
        return trim((string) preg_replace('/\n{3,}/u', "\n\n", $t));
    }

    /** Le lettere accentate scritte in due pezzi (lettera + accento) diventano un carattere solo, dove il server lo sa fare. */
    private static function normale(string $t): string
    {
        if (!mb_check_encoding($t, 'UTF-8')) $t = mb_convert_encoding($t, 'UTF-8', 'UTF-8');
        return class_exists(\Normalizer::class) ? (\Normalizer::normalize($t, \Normalizer::FORM_C) ?: $t) : $t;
    }

    /** Le battute di un testo, come le conta il contatore del pannello: i caratteri veri, un a capo vale uno. */
    public static function battute(string $t): int { return mb_strlen($t); }

    /**
     * Controlla i tre testi (già ripuliti): oltre il limite non si salva, e il messaggio dice di quanto.
     * @return array{casa_titolo:string,casa_descrizione:string,casa_invito:string}
     */
    public static function testi(array $in): array
    {
        $t = ['casa_invito' => self::unaRiga((string) ($in['casa_invito'] ?? '')), 'casa_titolo' => self::unaRiga((string) ($in['casa_titolo'] ?? '')),
              'casa_descrizione' => self::paragrafi((string) ($in['casa_descrizione'] ?? ''))];
        $limiti = ['casa_invito' => [self::MAX_INVITO, 'La scritta sulla copertina è troppo lunga: {n} battute, il massimo è {max}. Accorciala e salva di nuovo.'],
                   'casa_titolo' => [self::MAX_TITOLO, 'Il titolo è troppo lungo: {n} battute, il massimo è {max}. Accorcialo e salva di nuovo.'],
                   'casa_descrizione' => [self::MAX_DESCRIZIONE, 'La descrizione è troppo lunga: {n} battute, il massimo è {max}. Accorciala e salva di nuovo.']];
        foreach ($limiti as $k => [$max, $msg]) {
            if (($n = self::battute($t[$k])) > $max) throw new \RuntimeException(strtr(Pannello::t($msg), ['{n}' => $n, '{max}' => $max]));
        }
        return $t;
    }

    /** Salva i testi nella lingua principale e l'interruttore. Oltre i limiti non salva niente (RuntimeException). */
    public static function salva(int $pid, array $in): void
    {
        $t = self::testi($in);
        Db::update('properties', $t + ['casa_attiva' => !empty($in['casa_attiva']) ? 1 : 0], 'id = :pid', ['pid' => $pid]);
    }

    /** Le traduzioni salvate: [lingua => [titolo, descrizione, invito]]. */
    public static function tradotti(array $prop): array
    {
        $tr = json_decode((string) ($prop['casa_tr'] ?? ''), true);
        return is_array($tr) ? $tr : [];
    }

    /** I tre campi che si traducono: [nome nel percorso => [colonna, battute, tipo del campo, etichetta]]. */
    public const CAMPI = ['invito' => ['casa_invito', self::MAX_INVITO, 'text', 'Scritta sulla copertina'],
                          'titolo' => ['casa_titolo', self::MAX_TITOLO, 'text', 'Titolo'],
                          'descrizione' => ['casa_descrizione', self::MAX_DESCRIZIONE, 'textarea', 'Descrizione']];

    /** Scrive la traduzione di un campo in una lingua ('' la toglie). Oltre il limite si taglia: la traduzione automatica non deve bloccare. */
    public static function scriviTraduzione(int $pid, string $loc, string $campo, string $v): void
    {
        if (!isset(self::CAMPI[$campo]) || !self::disponibile()) return;
        $p = Db::one('SELECT casa_tr FROM properties WHERE id = ?', [$pid]);
        if (!$p) return;
        $tr = self::tradotti($p);
        $v = mb_substr($campo === 'descrizione' ? self::paragrafi($v) : self::unaRiga($v), 0, self::CAMPI[$campo][1]);
        if ($v === '') unset($tr[$loc][$campo]); else $tr[$loc][$campo] = $v;
        if (empty($tr[$loc])) unset($tr[$loc]);
        Db::update('properties', ['casa_tr' => $tr ? json_encode($tr, JSON_UNESCAPED_UNICODE) : null], 'id = :pid', ['pid' => $pid]);
    }

    /** La scritta sulla copertina l'ha scritta l'host (non è vuota né quella di serie nella sua lingua)? */
    public static function invitoSuo(array $prop): bool
    {
        $i = trim((string) ($prop['casa_invito'] ?? ''));
        return $i !== '' && $i !== I18n::t((string) ($prop['default_locale'] ?? 'it'), 'casa_invite');
    }

    // ------------------------------------------------------- guida dell'ospite

    /**
     * La parte «casa» dell'istantanea (Guide::build), o null se non c'è niente da mostrare: interruttore spento, oppure
     * né una foto né la descrizione. Senza questa chiave la guida è identica a prima (anche per «modifiche da pubblicare»).
     */
    public static function perIstantanea(array $prop, array $locales): ?array
    {
        if (!self::disponibile() || (int) ($prop['casa_attiva'] ?? 1) !== 1) return null;
        $foto = array_map(fn($f) => ['id' => $f['media_id'], 'alt' => $f['alt']], self::foto((int) $prop['id']));
        $descr = trim((string) ($prop['casa_descrizione'] ?? ''));
        if (!$foto && $descr === '') return null;
        $def = (string) $prop['default_locale'];
        $tr = [$def => array_filter(['titolo' => trim((string) ($prop['casa_titolo'] ?? '')), 'descrizione' => $descr,
                                     'invito' => self::invitoSuo($prop) ? trim((string) $prop['casa_invito']) : ''], fn($v) => $v !== '')];
        foreach (self::tradotti($prop) as $loc => $t) {
            if ($loc === $def || !in_array($loc, $locales, true) || !is_array($t)) continue;
            $t = array_filter(array_intersect_key(array_map(fn($v) => trim((string) $v), $t), self::CAMPI), fn($v) => $v !== '');
            if ($t) $tr[$loc] = $t;
        }
        return ['foto' => $foto, 'tr' => $tr];
    }

    /**
     * Quello che serve alle pagine dell'ospite, dall'istantanea: le foto con gli indirizzi, i testi nella sua lingua
     * (campo per campo, con il ripiego sulla lingua principale) e la scritta della copertina. null = niente galleria.
     * @return array{foto:list<array{id:int,alt:string}>,n:int,titolo:string,descrizione:string,invito:string,pastiglia:string}|null
     */
    public static function perGuida(array $pr, string $loc): ?array
    {
        $c = $pr['casa'] ?? null;
        if (!is_array($c)) return null;
        $def = (string) ($pr['default_locale'] ?? 'it');
        $foto = array_values(array_filter((array) ($c['foto'] ?? []), fn($f) => !empty($f['id'])));
        $t = fn(string $k) => trim((string) ($c['tr'][$loc][$k] ?? '')) ?: trim((string) ($c['tr'][$def][$k] ?? ''));
        $descr = $t('descrizione');
        if (!$foto && $descr === '') return null;
        $invito = $t('invito') ?: I18n::t($loc, 'casa_invite');
        $n = count($foto);
        return ['foto' => $foto, 'n' => $n, 'titolo' => $t('titolo') ?: I18n::t($loc, 'casa_title', (string) ($pr['name'] ?? '')), 'descrizione' => $descr,
                'invito' => $invito, 'pastiglia' => $n ? $invito . ' · ' . I18n::t($loc, $n === 1 ? 'casa_photo' : 'casa_photos', $n) : $invito];
    }

    /** Il testo alternativo di una foto della galleria: quello scritto dall'host, altrimenti «Casa Checco, foto 3 di 8». */
    public static function alt(array $foto, int $i, int $n, string $nome, string $loc): string
    {
        return trim((string) ($foto['alt'] ?? '')) ?: I18n::t($loc, 'casa_photo_alt', $nome, $i, $n);
    }

    // ------------------------------------------------------------------ copia

    /**
     * Copia galleria e testi da una struttura a un'altra dello stesso account (Copia): le foto si duplicano nello storage
     * con $dup (Media::duplicate), così le due guide restano indipendenti. Se la struttura di arrivo ha già una foto o la
     * descrizione non si tocca niente. @return bool true se ha copiato
     */
    public static function copia(array $da, array $a, callable $dup): bool
    {
        if (!self::disponibile()) return false;
        $foto = self::foto((int) $da['id']);
        if (!$foto && trim((string) ($da['casa_descrizione'] ?? '')) === '') return false;
        if (self::quante((int) $a['id']) || trim((string) ($a['casa_descrizione'] ?? '')) !== '') return false;
        $pos = 0;
        foreach ($foto as $f) {
            $mid = $dup($f['media_id']);
            if ($mid) Db::insert('property_gallery', ['property_id' => (int) $a['id'], 'media_id' => $mid, 'position' => ++$pos, 'alt' => $f['alt'], 'created_at' => Support::now()]);
        }
        // Le traduzioni viaggiano solo per le lingue che la guida di arrivo ha (le aggiunge Copia, dopo, se il piano le permette).
        Db::update('properties', ['casa_titolo' => (string) ($da['casa_titolo'] ?? ''), 'casa_descrizione' => $da['casa_descrizione'] ?? null,
                                  'casa_invito' => (string) ($da['casa_invito'] ?? ''), 'casa_attiva' => (int) ($da['casa_attiva'] ?? 1),
                                  'casa_tr' => (string) $da['default_locale'] === (string) $a['default_locale'] ? ($da['casa_tr'] ?? null) : null], 'id = :pid', ['pid' => (int) $a['id']]);
        return true;
    }

    /** Per il modulo «Cosa copiare»: la struttura di origine ha una galleria o una descrizione? Quante foto? */
    public static function daCopiare(array $da): ?int
    {
        if (!self::disponibile()) return null;
        $n = self::quante((int) $da['id']);
        return $n || trim((string) ($da['casa_descrizione'] ?? '')) !== '' ? $n : null;
    }

    // ------------------------------------------------------- il segno «Novità»

    /**
     * Fino a che giorno (compreso) il sito mostra «Novità» accanto alla galleria: la data scritta in Impostazioni → Sito,
     * altrimenti 60 giorni dal giorno in cui la funzione è arrivata (la migrazione 072). Per toglierlo subito basta una data passata.
     */
    public static function novitaFino(): string
    {
        $v = trim((string) ((array) Config::get('novita', []))['casa_fino'] ?? '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) return $v;
        static $serie = null;
        if ($serie === null) {
            $serie = '';
            try {
                $q = (string) Db::val("SELECT applied_at FROM schema_migrations WHERE name = '072_la_casa_9v.php'", [], '');
                if ($q !== '' && ($t = strtotime($q))) $serie = gmdate('Y-m-d', $t + self::NOVITA_GIORNI * 86400);
            } catch (\Throwable) { }
        }
        return $serie;
    }

    /** Oggi il segno «Novità» si mostra? ($oggi: per le prove.) */
    public static function novita(?string $oggi = null): bool
    {
        $fino = self::novitaFino();
        return $fino !== '' && ($oggi ?? Support::today()) <= $fino;
    }

    /** La pagina è in inglese? (Sito in /en, o pannello in inglese: come i «Testi e foto».) */
    private static function inglese(): bool { return (Pannello::linguaTesti() ?? LinguaSito::get()) === 'en'; }

    /** La voce nelle schede dei piani e nel questionario («La casa in foto: galleria fino a 10 immagini e descrizione»), con «Novità» accanto finché vale. HTML. */
    public static function voce(): string
    {
        return TestiSito::testo('casa.voce') . (($b = self::badge(self::inglese())) !== '' ? ' ' . $b : '');
    }

    /** Il segno «Novità» (un solo componente, .badge--novita): '' quando è scaduto. */
    public static function badge(?bool $en = null): string
    {
        if (!self::novita()) return '';
        $en ??= LinguaSito::en();
        return '<span class="badge badge--novita">' . ($en ? 'New' : 'Novità') . '</span>';
    }
}
