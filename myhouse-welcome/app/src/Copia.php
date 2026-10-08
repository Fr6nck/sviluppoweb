<?php
namespace MHW;

/**
 * Copia da una struttura a un'altra dello stesso account: sezioni (con
 * traduzioni, luoghi e loro traduzioni), aspetto e contatti.
 *
 * Due usi:
 *   - «Crea da una struttura esistente», quando si crea una struttura nuova;
 *   - «Copia sezioni da…», su una struttura che esiste già: per ogni sezione che
 *     c'è già si sceglie se sostituirla o saltarla.
 *
 * Mai copiati: indirizzo, CIN, reti Wi-Fi, passaggi di arrivo (Check-in &
 * Check-out), «Come arrivare», foto di copertina, parcheggio. Sono dati di
 * QUELLA casa: copiarli vorrebbe dire dare agli ospiti la password o la strada
 * sbagliata.
 *
 * Le immagini e i PDF si DUPLICANO nello storage con nomi nuovi: le due guide
 * sono indipendenti, eliminare l'una non tocca i file dell'altra.
 *
 * Tutto in una transazione: se qualcosa fallisce non resta niente a metà, e i
 * file già scritti nello storage si tolgono.
 *
 * Predisposizione (non ancora fatta, di proposito):
 *   - una libreria dei luoghi a livello di account (tabella account_places, con
 *     places.account_place_id): un luogo scritto una volta, usato in più guide;
 *   - sezioni «collegate» (sections.linked_from = id della sezione madre): la
 *     guida mostra i contenuti della madre, e si aggiornano in tutte le guide.
 * Entrambe passerebbero da qui: copiare diventerebbe «collegare».
 */
final class Copia
{
    /** Le sezioni che si possono copiare, nell'ordine in cui si propongono (tutte già spuntate). */
    public const SEZIONI = ['waste', 'eat', 'visit', 'todo', 'shop', 'transport', 'emergency', 'info', 'rules', 'services', 'clima', 'extras', 'events', 'custom'];
    /** Mai copiate: dati della singola casa. */
    public const MAI = ['checkin', 'wifi', 'arrival', 'parking'];

    /**
     * Cosa si può copiare dalla struttura di origine, per il modulo.
     * @return array{sezioni:array<int,array{kind:string,titolo:string,esiste:bool}>,logo:bool,contatti:int}
     */
    public static function proposta(int $daPid, ?int $aPid = null): array
    {
        $da = Db::one('SELECT * FROM properties WHERE id = ?', [$daPid]);
        $sezioni = [];
        foreach (Db::all('SELECT s.*, t.title FROM sections s LEFT JOIN section_translations t ON t.section_id = s.id AND t.locale = ?
                          WHERE s.property_id = ? AND s.is_core = 0 ORDER BY s.position, s.id', [$da['default_locale'], $daPid]) as $s) {
            if (!in_array($s['kind'], self::SEZIONI, true)) continue;
            // Le sezioni libere si copiano tutte insieme: una casella sola.
            if (SectionCatalog::multipla($s['kind']) && in_array($s['kind'], array_column($sezioni, 'kind'), true)) continue;
            $sezioni[] = ['kind' => $s['kind'], 'titolo' => (string) ($s['title'] ?: SectionCatalog::title($s['kind'], 'it')),
                          'esiste' => $aPid !== null && (bool) Db::val('SELECT id FROM sections WHERE property_id = ? AND kind = ?', [$aPid, $s['kind']])];
        }
        return ['sezioni' => $sezioni, 'logo' => (bool) $da['logo_media_id'],
                'contatti' => (int) Db::val('SELECT COUNT(*) FROM property_contacts WHERE property_id = ?', [$daPid], 0)];
    }

    /**
     * Esegue la copia.
     * @param string[] $kinds        le sezioni spuntate (solo quelle di self::SEZIONI)
     * @param array<string,string> $esistenti  kind => 'sostituisci' | 'salta', per le sezioni che la destinazione ha già
     * @return array{copiate:string[],saltate:string[],sostituite:string[]}
     */
    public static function esegui(int $accountId, int $daPid, int $aPid, array $kinds, array $esistenti, bool $aspetto, bool $contatti): array
    {
        if ($daPid === $aPid) throw new \RuntimeException('Scegli una struttura diversa da questa.');
        $da = Db::one('SELECT * FROM properties WHERE id = ? AND account_id = ?', [$daPid, $accountId]);
        $a = Db::one('SELECT * FROM properties WHERE id = ? AND account_id = ?', [$aPid, $accountId]);
        if (!$da || !$a) throw new NotFound('Struttura non trovata.');
        // Nell'ordine in cui stanno nella guida di origine.
        $ordine = array_values(array_unique(array_column(Db::all('SELECT kind FROM sections WHERE property_id = ? AND is_core = 0 ORDER BY position, id', [$daPid]), 'kind')));
        $kinds = array_values(array_intersect($ordine, self::SEZIONI, $kinds));
        $foto = Entitlements::can($accountId, 'photos');
        $scritti = [];   // oggetti scritti nello storage: da togliere se qualcosa va storto
        $daCancellare = [];   // media delle sezioni sostituite: si cancellano a copia riuscita

        try {
            $esito = Db::tx(function () use ($accountId, $da, $a, $kinds, $esistenti, $aspetto, $contatti, $foto, &$scritti, &$daCancellare) {
                $out = ['copiate' => [], 'saltate' => [], 'sostituite' => []];
                // Una funzione vera, non una freccia: deve scrivere in $scritti, non in una sua copia.
                $dup = function (?int $mid) use ($accountId, $a, &$scritti): ?int {
                    return $mid ? Media::duplicate($mid, $accountId, (int) $a['id'], $scritti) : null;
                };

                $prossima = (int) Db::val('SELECT COALESCE(MAX(position), 0) + 1 FROM sections WHERE property_id = ?', [$a['id']], 1);
                foreach ($kinds as $kind) {
                    $sorgenti = Db::all('SELECT * FROM sections WHERE property_id = ? AND kind = ? AND is_core = 0 ORDER BY position, id', [$da['id'], $kind]);
                    if (!$sorgenti) continue;
                    $gia = Db::all('SELECT * FROM sections WHERE property_id = ? AND kind = ? AND is_core = 0 ORDER BY position, id', [$a['id'], $kind]);
                    $posizione = null;
                    if ($gia) {
                        // La sezione c'è già: si sostituisce solo se l'host l'ha detto.
                        if (($esistenti[$kind] ?? 'salta') !== 'sostituisci') { $out['saltate'][] = $kind; continue; }
                        $posizione = (int) $gia[0]['position'];
                        foreach ($gia as $g) {
                            $daCancellare = array_merge($daCancellare, self::mediaDellaSezione($g));
                            Db::run('DELETE FROM sections WHERE id = ?', [$g['id']]);   // traduzioni e luoghi vanno con lei
                        }
                        $out['sostituite'][] = $kind;
                    } else {
                        $out['copiate'][] = $kind;
                    }
                    foreach ($sorgenti as $s) self::sezione($s, (int) $a['id'], $posizione ?? $prossima++, $dup);
                }

                // Il limite delle sezioni del piano vale anche per le copie.
                $max = Entitlements::limit($accountId, 'sections', 4);
                $attive = (int) Db::val('SELECT COUNT(*) FROM sections WHERE property_id = ? AND is_core = 0 AND is_active = 1', [$a['id']], 0);
                if ($attive > $max) throw new LimitReached("Il piano comprende $max sezioni attive: con questa copia diventerebbero $attive. Togli qualche sezione dalla scelta.");

                if ($aspetto) {
                    $cambi = ['palette' => $da['palette'], 'text_tone' => $da['text_tone']];
                    if ($da['logo_media_id']) {
                        if ($a['logo_media_id']) $daCancellare[] = (int) $a['logo_media_id'];
                        $cambi['logo_media_id'] = $dup((int) $da['logo_media_id']);
                    }
                    Db::update('properties', $cambi, 'id = :pid', ['pid' => $a['id']]);
                }
                if ($contatti) {
                    $righe = Db::all('SELECT name, role, phone, whatsapp FROM property_contacts WHERE property_id = ? ORDER BY position, id', [$da['id']]);
                    if ($righe) Properties::saveContacts((int) $a['id'], $righe);
                }
                // Le traduzioni copiate servono nelle lingue della guida: si aggiungono quelle che il piano permette.
                $consentite = Entitlements::allowedLocales($accountId);
                foreach (Db::all('SELECT locale FROM property_locales WHERE property_id = ?', [$da['id']]) as $l) {
                    if (in_array($l['locale'], $consentite, true)
                        && !Db::val('SELECT 1 FROM property_locales WHERE property_id = ? AND locale = ?', [$a['id'], $l['locale']])) {
                        Db::insert('property_locales', ['property_id' => $a['id'], 'locale' => $l['locale']]);
                    }
                }
                return $out;
            });
        } catch (\Throwable $e) {
            foreach ($scritti as [$driver, $key]) { try { Storages::for($driver)->delete($key); } catch (\Throwable) {} }
            throw $e;
        }
        // A copia riuscita, i file delle sezioni sostituite (e del logo di prima) non servono più.
        foreach (array_unique($daCancellare) as $mid) Media::rilascia($mid, $accountId);
        return $esito;
    }

    /** Una sezione con le sue traduzioni, i suoi file e i suoi luoghi, in un'altra struttura. */
    private static function sezione(array $s, int $aPid, int $posizione, callable $dup): void
    {
        $dati = json_decode((string) $s['data'], true) ?: [];
        // Foto e PDF dentro le righe (istruzioni dei servizi…): duplicati anche loro.
        foreach (SectionCatalog::fields($s['kind']) as $campo => $def) {
            if ($def[0] !== 'repeater' || !is_array($dati[$campo] ?? null)) continue;
            foreach ($def['sub'] as $sn => $sd) {
                if (!in_array($sd[0], ['image', 'pdf'], true)) continue;
                foreach ($dati[$campo] as $i => $r) {
                    if (is_array($r) && (int) ($r[$sn] ?? 0) > 0) $dati[$campo][$i][$sn] = $dup((int) $r[$sn]) ?? '';
                }
            }
        }
        $riga = $s; unset($riga['id']);
        $riga = ['property_id' => $aPid, 'position' => $posizione, 'data' => json_encode($dati, JSON_UNESCAPED_UNICODE),
                 'media_id' => $dup($s['media_id'] ? (int) $s['media_id'] : null), 'pdf_media_id' => $dup($s['pdf_media_id'] ? (int) $s['pdf_media_id'] : null)] + $riga;
        if (array_key_exists('created_at', $riga)) $riga['created_at'] = Support::now();
        $sid = Db::insert('sections', $riga);
        foreach (Db::all('SELECT * FROM section_translations WHERE section_id = ?', [$s['id']]) as $t) {
            unset($t['id']); $t['section_id'] = $sid;
            Db::insert('section_translations', $t);
        }
        foreach (Db::all('SELECT * FROM places WHERE section_id = ? ORDER BY position, id', [$s['id']]) as $pl) {
            $vecchio = (int) $pl['id']; unset($pl['id']);
            $pl['section_id'] = $sid; $pl['media_id'] = $dup($pl['media_id'] ? (int) $pl['media_id'] : null);
            $nuovo = Db::insert('places', $pl);
            foreach (Db::all('SELECT * FROM place_translations WHERE place_id = ?', [$vecchio]) as $pt) {
                unset($pt['id']); $pt['place_id'] = $nuovo;
                Db::insert('place_translations', $pt);
            }
        }
    }

    /** Tutti i file di una sezione: immagine, PDF, file delle righe, foto dei luoghi. */
    private static function mediaDellaSezione(array $s): array
    {
        $ids = array_filter([(int) $s['media_id'], (int) $s['pdf_media_id']]);
        $ids = array_merge($ids, SectionCatalog::mediaIds($s['kind'], json_decode((string) $s['data'], true) ?: []));
        foreach (Db::all('SELECT media_id FROM places WHERE section_id = ? AND media_id IS NOT NULL', [$s['id']]) as $pl) $ids[] = (int) $pl['media_id'];
        return $ids;
    }
}
