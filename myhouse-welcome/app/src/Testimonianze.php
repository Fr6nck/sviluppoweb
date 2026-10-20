<?php
namespace MHW;

/**
 * Le testimonianze della landing: nome, struttura, testo, foto, visibile sì/no.
 * Il progetto non ne contiene nessuna: una testimonianza inventata sarebbe una
 * pubblicità ingannevole.
 *
 * Due origini nella stessa tabella:
 *   - scritte dall'amministratore (account_id NULL): come prima, visibili sì/no;
 *   - giudizi dei clienti (7D): voto, testo, foto scelta, consenso. Arrivano «in_attesa»;
 *     si pubblicano solo con il consenso e solo dall'amministrazione; il cliente ritira il
 *     consenso quando vuole e la testimonianza sparisce subito dalla home.
 */
final class Testimonianze
{
    /** Il testo del consenso, salvato con ogni giudizio così com'era quando è stato dato. */
    public const CONSENSO = 'Acconsento che MyHouse Welcome pubblichi sul proprio sito il mio voto, il mio giudizio, il mio nome (senza cognome), '
        . 'il nome e la città della struttura e la foto scelta. Il link della mia guida non viene pubblicato. '
        . 'Posso ritirare il consenso quando voglio da Account & Fatturazione.';
    /** 9L · Mai la foto dell'host: restano la copertina della guida o nessuna foto. */
    public const FOTO = ['copertina' => 'La copertina della guida', 'nessuna' => 'Nessuna foto'];
    /** 9B/9L · Le immagini che il cliente sceglie di mostrare in home (oggi nella scheda, Vetrina::scelte). */
    public const IMMAGINI = ['show_cover' => 'Copertina', 'show_logo' => 'Logo della struttura'];
    /** Giorni dopo la prima pubblicazione per chiedere il giudizio (9B: il riquadro c'è dal primo accesso, il modulo dopo la
        pubblicazione; l'email di richiamo resta a 30 giorni), e dopo quanti torna il riquadro chiuso. */
    public const DOPO_GIORNI = 0;
    public const RICHIAMO_GIORNI = 30;
    public const RITORNO_GIORNI = 60;
    /** La media in home si mostra solo da questo numero di giudizi pubblicati. */
    public const MEDIA_DA = 5;

    public static function disponibili(): bool { return Migrator::tableExists('testimonials'); }

    /** I giudizi dei clienti ci sono (migrazione 034)? */
    public static function giudizi(): bool { return self::disponibili() && Migrator::columnExists('testimonials', 'account_id'); }

    public static function tutte(): array
    {
        if (!self::disponibili()) return [];
        // Quelle scritte dall'amministratore: i giudizi dei clienti hanno il loro elenco (giudiziClienti).
        return Db::all('SELECT * FROM testimonials' . (self::giudizi() ? ' WHERE account_id IS NULL' : '') . ' ORDER BY position, id');
    }

    /**
     * Quelle da mostrare in landing, con l'URL della foto: le visibili scritte dall'amministratore
     * e i giudizi pubblicati con il consenso non ritirato.
     */
    public static function visibili(): array
    {
        if (!self::disponibili()) return [];
        // 9B: i giudizi dei clienti hanno la loro sezione in home («Cosa dicono di noi», Giudizi::perHome); qui restano quelle scritte da noi.
        $dove = self::giudizi()
            ? (Migrator::columnExists('testimonials', 'home_visible') ? 'visible = 1 AND account_id IS NULL'
               : "visible = 1 AND (account_id IS NULL OR (status = 'pubblicata' AND consent_at IS NOT NULL AND withdrawn_at IS NULL))")
            : 'visible = 1';
        $out = [];
        foreach (Db::all("SELECT * FROM testimonials WHERE $dove ORDER BY position, id") as $t) {
            $t['foto'] = self::foto($t);
            $t['rating'] = (int) ($t['rating'] ?? 0);
            $t['city'] = (string) ($t['city'] ?? '');
            $out[] = $t;
        }
        return $out;
    }

    /** «4,8 su 5 da 12 host»: solo dai giudizi pubblicati con un voto, e solo da MEDIA_DA in su. */
    public static function media(): ?array
    {
        if (!self::giudizi()) return null;
        // 9L: i giudizi approvati (la scheda in home è un'altra cosa, Vetrina).
        $inHome = Migrator::tableExists('vetrina_schede') ? "status = 'pubblicata'" : (Migrator::columnExists('testimonials', 'home_visible') ? 'home_visible = 1' : "visible = 1 AND status = 'pubblicata'");
        $r = Db::one("SELECT COUNT(*) AS n, AVG(rating) AS m FROM testimonials WHERE $inHome AND account_id IS NOT NULL
                      AND consent_at IS NOT NULL AND withdrawn_at IS NULL AND rating BETWEEN 1 AND 5");
        $n = (int) ($r['n'] ?? 0);
        return $n >= self::MEDIA_DA ? ['media' => round((float) $r['m'], 1), 'n' => $n] : null;
    }

    public static function foto(array $t): ?string
    {
        // Un giudizio di un cliente: la foto scelta, presa dalla sua struttura quando si mostra.
        if (!empty($t['account_id'])) {
            $p = !empty($t['property_id']) ? Db::one('SELECT cover_media_id FROM properties WHERE id = ? AND account_id = ?', [(int) $t['property_id'], (int) $t['account_id']]) : null;
            if (!$p) return null;
            // 9L: mai la foto dell'host (la vecchia scelta «profilo» non mostra niente).
            return ($t['photo_source'] ?? '') === 'copertina' && $p['cover_media_id'] ? Media::url((int) $p['cover_media_id']) : null;
        }
        if ($t['photo_key'] === '') return null;
        try { return Storages::for($t['photo_storage'])->url($t['photo_key']); }
        catch (\Throwable $e) { Log::exception($e, 'Testimonianze::foto'); return null; }
    }

    public static function salva(array $in, ?array $file): int
    {
        $id = (int) ($in['id'] ?? 0);
        $prima = $id ? Db::one('SELECT * FROM testimonials WHERE id = ?', [$id]) : null;
        if ($id && !$prima) throw new NotFound('Testimonianza non trovata.');
        if ($prima && !empty($prima['account_id'])) throw new \RuntimeException('È il giudizio di un cliente: si pubblica o si rifiuta, non si modifica.');
        $dati = [
            'name' => mb_substr(trim((string) ($in['name'] ?? '')), 0, 120),
            'property_name' => mb_substr(trim((string) ($in['property_name'] ?? '')), 0, 160),
            'body' => mb_substr(trim((string) ($in['body'] ?? '')), 0, 800),
            'visible' => !empty($in['visible']) ? 1 : 0,
            'position' => max(0, min(999, (int) ($in['position'] ?? 0))),
        ];
        if ($dati['name'] === '' || $dati['body'] === '') throw new \RuntimeException('Servono almeno il nome e il testo.');
        if ($file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            [$driver, $key] = Media::storeFreeImage($file, 'testimonianze');
            $dati['photo_storage'] = $driver; $dati['photo_key'] = $key;
        } elseif (!empty($in['togli_foto'])) {
            $dati['photo_storage'] = ''; $dati['photo_key'] = '';
        }
        if ($prima && array_key_exists('photo_key', $dati) && $prima['photo_key'] !== '') self::cancellaFoto($prima);
        if ($prima) { Db::update('testimonials', $dati, 'id = :tid', ['tid' => $id]); return $id; }
        return Db::insert('testimonials', $dati + ['created_at' => Support::now()]);
    }

    public static function elimina(int $id): void
    {
        $t = Db::one('SELECT * FROM testimonials WHERE id = ?', [$id]);
        if (!$t) return;
        self::cancellaFoto($t);
        Db::run('DELETE FROM testimonials WHERE id = ?', [$id]);
    }

    private static function cancellaFoto(array $t): void
    {
        if ($t['photo_key'] === '') return;
        try { Storages::for($t['photo_storage'])->delete($t['photo_key']); } catch (\Throwable $e) { Log::exception($e, 'Testimonianze::foto'); }
    }

    // ------------------------------------------------------------ giudizi dei clienti (7D)

    /** L'ultimo giudizio di un account (anche ritirato o rifiutato), o null. */
    public static function diAccount(int $accountId): ?array
    {
        if (!self::giudizi()) return null;
        return Db::one('SELECT * FROM testimonials WHERE account_id = ? ORDER BY id DESC LIMIT 1', [$accountId]);
    }

    /** La struttura di cui parla il giudizio: la prima pubblicata dell'account. */
    public static function struttura(int $accountId): ?array
    {
        return Db::one("SELECT id, name, city, profile_media_id, cover_media_id, published_at FROM properties
                        WHERE account_id = ? AND is_demo = 0 AND archived_at IS NULL AND published_at IS NOT NULL AND published_at <> '' ORDER BY published_at, id LIMIT 1", [$accountId]);
    }

    /** La prima pubblicazione dell'account (la più vecchia), o null se non ha mai pubblicato. */
    public static function primaPubblicazione(int $accountId): ?int
    {
        $d = (string) Db::val("SELECT MIN(published_at) FROM properties WHERE account_id = ? AND is_demo = 0 AND published_at IS NOT NULL AND published_at <> ''", [$accountId], '');
        return $d !== '' ? (int) strtotime($d) : null;
    }

    /**
     * Il riquadro «Ti sta piacendo MyHouse Welcome?» in «Le mie guide»: da 30 giorni dopo la prima
     * pubblicazione, se non ha ancora dato un giudizio. Chiuso, torna una volta sola dopo 60 giorni.
     */
    public static function daChiedere(array $acc, ?int $adesso = null): bool
    {
        if (!self::giudizi() || !Migrator::columnExists('accounts', 'giudizio_chiusure')) return false;
        if (!empty($acc['is_test'])) return false;   // 7F: i clienti di prova non ricevono la richiesta
        $t = $adesso ?? time();
        $aid = (int) $acc['id'];
        // 9B: il riquadro c'è dal primo accesso (spiega il programma); la pubblicazione serve solo per mandare il giudizio.
        if (Giudizi::disponibili()) { if (self::diAccount($aid) || Giudizi::escluso($acc)) return false; }
        else {
            $prima = self::primaPubblicazione($aid);
            if ($prima === null || $t - $prima < self::DOPO_GIORNI * 86400 || self::diAccount($aid)) return false;
        }
        $r = Db::one('SELECT giudizio_chiuso_at, giudizio_chiusure FROM accounts WHERE id = ?', [$aid]) ?: [];
        $n = (int) ($r['giudizio_chiusure'] ?? 0);
        if ($n === 0) return true;
        if ($n >= 2) return false;
        return $t - (int) strtotime((string) ($r['giudizio_chiuso_at'] ?? '')) >= self::RITORNO_GIORNI * 86400;
    }

    public static function chiudiRichiesta(int $accountId): void
    {
        if (!Migrator::columnExists('accounts', 'giudizio_chiusure')) return;
        Db::run('UPDATE accounts SET giudizio_chiusure = giudizio_chiusure + 1, giudizio_chiuso_at = ? WHERE id = ?', [Support::now(), $accountId]);
    }

    /**
     * Il giudizio dal modulo del pannello. Arriva «in_attesa»: lo pubblica solo l'amministrazione,
     * e solo con il consenso. Da 1 a 3 stelle un'email a noi, per capire cosa migliorare.
     * @throws \RuntimeException con il motivo, da mostrare sopra il modulo
     */
    public static function salvaGiudizio(array $acc, array $u, array $in): array
    {
        if (!self::giudizi()) throw new \RuntimeException('I giudizi non sono ancora attivi.');
        $aid = (int) $acc['id'];
        $voto = (int) ($in['rating'] ?? 0);
        $testo = trim(preg_replace('/[\x00-\x09\x0B-\x1F\x7F]/u', '', (string) ($in['body'] ?? '')) ?? '');
        $foto = (string) ($in['photo_source'] ?? 'nessuna');
        if ($voto < 1 || $voto > 5) throw new \RuntimeException('Scegli quante stelle dai, da 1 a 5.');
        if (mb_strlen($testo) < 20) throw new \RuntimeException('Scrivi almeno 20 caratteri: cosa diresti a un altro host?');
        if (mb_strlen($testo) > 400) throw new \RuntimeException('Al massimo 400 caratteri.');
        if (!isset(self::FOTO[$foto])) $foto = 'nessuna';
        $gia = self::diAccount($aid);
        $programma = Giudizi::disponibili();
        if ($programma) {
            // 9L: si modifica quando si vuole (torna «da approvare»); dopo il ritiro dal programma no. Va mandato dopo la prima pubblicazione.
            if (($sch = Vetrina::scheda($aid)) && !empty($sch['ritirato_at'])) throw new \RuntimeException('Ti sei ritirato dal programma «Giudizio con sconto»: il giudizio non si può più lasciare.');
            if (self::primaPubblicazione($aid) === null) throw new \RuntimeException('Il giudizio si lascia dopo aver pubblicato la guida.');
            // «Partecipo» spuntato nel modulo: l'adesione dal pannello (una sola casella, la stessa del pagamento).
            if (!empty($in['partecipo']) && Giudizi::puoAderire($acc)) { Giudizi::aderisci($acc, 'pannello'); $acc = Db::one('SELECT * FROM accounts WHERE id = ?', [$aid]) ?: $acc; }
        } elseif ($gia && $gia['status'] === 'pubblicata' && $gia['withdrawn_at'] === null) {
            throw new \RuntimeException('Il tuo giudizio è già pubblicato. Grazie!');
        }
        $p = self::struttura($aid);
        // 9B: la pubblicazione è parte dell'adesione al programma (base: contratto), non un consenso a parte.
        $consenso = $programma ? !empty($acc['giudizio_adesione']) : !empty($in['consenso']);
        $testoConsenso = $programma ? Giudizi::casella() : self::CONSENSO;   // 9L: un testo solo, al pagamento e nel pannello
        $nome = trim((string) ($u['name'] ?? ''));
        $dati = [
            'account_id' => $aid, 'property_id' => $p ? (int) $p['id'] : null,
            'name' => mb_substr($nome !== '' ? (preg_split('/\s+/', $nome)[0] ?? $nome) : 'Un host', 0, 120),
            'property_name' => mb_substr((string) ($p['name'] ?? ''), 0, 160), 'city' => mb_substr((string) ($p['city'] ?? ''), 0, 120),
            'body' => $testo, 'rating' => $voto, 'photo_source' => $foto, 'status' => 'in_attesa', 'visible' => 0,
            'consent_at' => $consenso ? ($programma && !empty($acc['giudizio_adesione_at']) ? (string) $acc['giudizio_adesione_at'] : Support::now()) : null,
            'consent_text' => $consenso ? $testoConsenso : null, 'withdrawn_at' => null,
            'photo_storage' => '', 'photo_key' => '', 'position' => 0,
        ];
        if ($programma) {
            // 9L: le immagini le sceglie l'host nella scheda (Vetrina::scelte), mai la sua foto; qui solo per lo storico.
            $sc = ($sch = Vetrina::scheda($aid)) ? Vetrina::scelte($sch) : ['copertina' => true, 'logo' => true];
            $ha = $p ? Db::one('SELECT cover_media_id, logo_media_id FROM properties WHERE id = ?', [(int) $p['id']]) : null;
            $dati['show_cover'] = $sc['copertina'] && !empty($ha['cover_media_id']) ? 1 : 0; $dati['show_logo'] = $sc['logo'] && !empty($ha['logo_media_id']) ? 1 : 0; $dati['show_photo'] = 0;
            $dati['home_visible'] = 0;
            if ($programma && $dati['photo_source'] === 'profilo') $dati['photo_source'] = 'nessuna';
        }
        if ($gia && ($programma ? $gia['withdrawn_at'] === null : $gia['status'] === 'in_attesa')) {   // 9L: lo stesso giudizio, di nuovo da approvare
            Db::update('testimonials', $dati, 'id = :tid', ['tid' => (int) $gia['id']]); $id = (int) $gia['id'];
        } else $id = Db::insert('testimonials', $dati + ['created_at' => Support::now()]);
        Auth::audit('testimonial.submitted', null, ['account_id' => $aid, 'rating' => $voto, 'consent' => $consenso]);
        if ($voto <= 3) self::avvisaNoi($u, $dati);
        if ($programma) {
            Giudizi::dopoGiudizio($acc);   // lo sconto «dopo il giudizio» (se scelto)
            // 9L: il giudizio nuovo (o modificato) è «da approvare»: esce da «Cosa dicono di noi» finché non lo riaccendiamo.
            if (($sch = Vetrina::assicura($aid)) && Migrator::columnExists('vetrina_schede', 'giudizio_home'))
                Db::update('vetrina_schede', ['giudizio_home' => 0, 'giudizio_approvato_at' => null, 'giudizio_ritirato_at' => null, 'giudizio_motivo' => null, 'updated_at' => Support::now()], 'id = :id', ['id' => (int) $sch['id']]);
        }
        return ['id' => $id, 'rating' => $voto, 'consenso' => $consenso, 'aderito' => !empty($acc['giudizio_adesione'])];
    }

    /** Da 1 a 3 stelle: il giudizio arriva anche all'indirizzo di contatto, per rispondere. */
    private static function avvisaNoi(array $u, array $d): void
    {
        $a = Support::contatto();
        if ($a === '') return;
        $paragrafi = [
            'Un cliente ha dato ' . $d['rating'] . ' stell' . ($d['rating'] === 1 ? 'a' : 'e') . ' a MyHouse Welcome. Gli abbiamo detto che gli scriviamo per capire cosa migliorare.',
            '«' . $d['body'] . '»',
            'Chi: ' . trim((string) ($u['name'] ?? '')) . ' · ' . (string) ($u['email'] ?? '') . ($d['property_name'] !== '' ? ' · ' . $d['property_name'] . ($d['city'] !== '' ? ', ' . $d['city'] : '') : ''),
        ];
        $link = Support::baseUrl() . (Giudizi::disponibili() ? '/admin/giudizi' : '/admin/testimonianze');
        try {
            Mailer::send($a, 'Giudizio da ' . $d['rating'] . '/5: da richiamare', Mailer::testo('Un giudizio da richiamare', $paragrafi, ['Apri le testimonianze', $link], null),
                         Mailer::layout('Un giudizio da richiamare', $paragrafi, ['Apri le testimonianze', $link], null, null));
        } catch (\Throwable $e) { Log::exception($e, 'Testimonianze::avvisaNoi'); }
    }

    /** Dall'amministrazione: pubblica un giudizio. Solo con il consenso, mai ritirato. */
    public static function pubblica(int $id): bool
    {
        $t = Db::one('SELECT * FROM testimonials WHERE id = ? AND account_id IS NOT NULL', [$id]);
        if (!$t || empty($t['consent_at']) || !empty($t['withdrawn_at'])) return false;
        $dati = ['status' => 'pubblicata', 'visible' => 1];
        if (Migrator::columnExists('testimonials', 'home_visible')) $dati += ['home_visible' => 1, 'published_at' => $t['published_at'] ?: Support::now()];   // 9B: in home
        Db::update('testimonials', $dati, 'id = :tid', ['tid' => $id]);
        return true;
    }

    public static function rifiuta(int $id): void
    {
        $home = Migrator::columnExists('testimonials', 'home_visible') ? ', home_visible = 0' : '';
        Db::run("UPDATE testimonials SET status = 'rifiutata', visible = 0$home WHERE id = ? AND account_id IS NOT NULL", [$id]);
    }

    /** 9B · Dall'amministrazione: in home sì o no (l'interruttore). Solo con l'adesione e non ritirato. */
    public static function inHome(int $id, bool $si): bool
    {
        $t = Db::one('SELECT * FROM testimonials WHERE id = ? AND account_id IS NOT NULL', [$id]);
        if (!$t || !Migrator::columnExists('testimonials', 'home_visible')) return false;
        if ($si && (empty($t['consent_at']) || !empty($t['withdrawn_at']))) return false;
        Db::update('testimonials', $si ? ['home_visible' => 1, 'status' => 'pubblicata', 'visible' => 1, 'published_at' => $t['published_at'] ?: Support::now()]
                                       : ['home_visible' => 0, 'status' => $t['status'] === 'pubblicata' ? 'in_attesa' : $t['status'], 'visible' => 0], 'id = :tid', ['tid' => $id]);
        return true;
    }

    /** Il cliente ritira il consenso: il giudizio sparisce subito dalla home e non si ripubblica. */
    public static function ritira(int $accountId): bool
    {
        if (!self::giudizi()) return false;
        $home = Migrator::columnExists('testimonials', 'home_visible') ? ', home_visible = 0' : '';
        $n = Db::run("UPDATE testimonials SET withdrawn_at = ?, visible = 0$home WHERE account_id = ? AND withdrawn_at IS NULL", [Support::now(), $accountId]);
        return $n > 0;
    }

    /** I giudizi dei clienti per l'amministrazione: prima quelli in attesa. */
    public static function giudiziClienti(string $filtro = 'tutti'): array
    {
        if (!self::giudizi()) return [];
        $home = Migrator::columnExists('testimonials', 'home_visible');
        $dove = match ($filtro) {
            'da_valutare' => $home ? ' AND t.home_visible = 0 AND t.withdrawn_at IS NULL AND t.consent_at IS NOT NULL' : " AND t.status = 'in_attesa'",
            'in_home' => $home ? ' AND t.home_visible = 1 AND t.withdrawn_at IS NULL' : " AND t.status = 'pubblicata'",
            'ritirati' => ' AND t.withdrawn_at IS NOT NULL',
            default => '',
        };
        $acc = $home ? ', a.giudizio_adesione, a.giudizio_adesione_at, a.giudizio_sconto_stato, a.giudizio_sconto_at' : '';
        return Db::all("SELECT t.*, u.email$acc FROM testimonials t LEFT JOIN accounts a ON a.id = t.account_id LEFT JOIN users u ON u.id = a.user_id
                        WHERE t.account_id IS NOT NULL$dove ORDER BY CASE t.status WHEN 'in_attesa' THEN 0 ELSE 1 END, t.id DESC LIMIT 200");
    }
}
