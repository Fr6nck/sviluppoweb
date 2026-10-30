<?php
/**
 * 069 — 9T · «Concierge AI»: il nome di prodotto dell'assistente che risponde agli ospiti (prima «Chiedi alla guida»).
 *  1. La funzione ai_concierge si chiama «Concierge AI» (se ha ancora il nome di serie della 067).
 *  2. Le voci di Plus e Portfolio nel listino: la riga di serie della 067 diventa quella nuova (se nessuno l'ha cambiata).
 *  3. I testi della sezione in home (Testi e foto, assistente.*) salvati uguali a quelli di serie vecchi si tolgono:
 *     valgono quelli nuovi («Risponde ai tuoi ospiti, a qualsiasi ora.»). Quelli scritti a mano restano.
 * Solo testi, SQLite e MySQL. Una seconda esecuzione non cambia niente.
 */
use MHW\{Db, Migrator};

return function (\PDO $pdo): void {
    if (Migrator::tableExists('features')) {
        Db::run("UPDATE features SET label = 'Concierge AI' WHERE code = 'ai_concierge' AND label IN ('Chiedi alla guida (assistente AI)', 'Concierge con intelligenza artificiale')");
    }
    if (Migrator::tableExists('packages')) {
        $righe = [
            'plus' => [['Chiedi alla guida: l\'assistente che risponde agli ospiti con le tue informazioni, in ogni lingua', 'Concierge AI: risponde ai tuoi ospiti 24 ore su 24, nella loro lingua, con le informazioni della tua guida'],
                       ['Ask the guide: the assistant that answers guests with your information, in any language', 'Concierge AI: answers your guests 24/7, in their language, with the information in your guide']],
            'portfolio' => [['Chiedi alla guida in ogni struttura, con le sue informazioni', 'Concierge AI in ogni struttura, con le sue informazioni'],
                            ['Ask the guide in every property, with its own information', 'Concierge AI in every property, with its own information']],
        ];
        $en = Migrator::columnExists('packages', 'bullets_en');
        foreach ($righe as $code => [[$vIt, $nIt], [$vEn, $nEn]]) {
            $p = Db::one('SELECT * FROM packages WHERE code = ?', [$code]);
            if (!$p) continue;
            $dati = [];
            if (str_contains((string) $p['bullets'], $vIt)) $dati['bullets'] = str_replace($vIt, $nIt, (string) $p['bullets']);
            if ($en && str_contains((string) ($p['bullets_en'] ?? ''), $vEn)) $dati['bullets_en'] = str_replace($vEn, $nEn, (string) $p['bullets_en']);
            if ($dati) Db::update('packages', $dati, 'id = :id', ['id' => (int) $p['id']]);
        }
    }
    if (Migrator::tableExists('site_texts')) {
        $vecchie = [
            'assistente.kicker' => 'Chiedi alla guida', 'en:assistente.kicker' => 'Ask the guide',
            'assistente.titolo' => 'La guida risponde. Tu dormi.', 'en:assistente.titolo' => 'The guide answers. You sleep.',
            'assistente.testo' => 'L\'ospite scrive una domanda, nella sua lingua, e l\'assistente risponde con quello che hai scritto nella guida: Wi-Fi, check-out, parcheggio, i tuoi consigli. Sotto ogni risposta c\'è la sezione da cui viene. Se la guida non lo dice, lo ammette e rimanda ai tuoi contatti.',
            'en:assistente.testo' => 'Guests type a question, in their language, and the assistant answers with what you wrote in the guide: Wi-Fi, check-out, parking, your tips. Under every answer is the section it comes from. If the guide doesn\'t say, it admits it and points to your contacts.',
            'assistente.punti' => "**Solo le tue informazioni.** Niente inventato: legge la guida e basta.\n**In ogni lingua.** Domanda in tedesco, risposta in tedesco, anche se la guida è in italiano.\n**Ti dice cosa manca.** Le domande senza risposta le trovi nel pannello: due righe e la prossima volta risponde da sola.",
            'en:assistente.punti' => "**Only your information.** Nothing made up: it reads the guide and nothing else.\n**In every language.** A question in German, an answer in German, even if the guide is in Italian.\n**It tells you what is missing.** Unanswered questions are in your dashboard: two lines and next time it answers by itself.",
            'assistente.nota' => 'Compreso in Plus e Portfolio, fino a {domande} domande al mese per struttura. Nessun cookie, nessun dato dell\'ospite.',
            'en:assistente.nota' => 'Included in Plus and Portfolio, up to {domande} questions a month per property. No cookies, no guest data.',
            'assistente.esempio.d' => 'A che ora è il check-out?', 'en:assistente.esempio.d' => 'What time is check-out?',
            'assistente.esempio.r' => 'Entro le {ora}. Lo trovi nella sezione «Check-in & Check-out».', 'en:assistente.esempio.r' => 'By {ora}. You will find it in the "Check-in & Check-out" section.',
            'assistente.esempio.fonte' => 'Dalla sezione «Check-in & Check-out»', 'en:assistente.esempio.fonte' => 'From the section "Check-in & Check-out"',
        ];
        $st = $pdo->prepare('DELETE FROM site_texts WHERE chiave = ? AND valore = ?');
        foreach ($vecchie as $chiave => $testo) $st->execute([$chiave, $testo]);
    }
    // 4. SEO e GEO: la descrizione della home e il fatto su llms.txt salvati uguali a quelli di serie vecchi.
    if (Migrator::tableExists('seo_settings')) {
        $st = $pdo->prepare('DELETE FROM seo_settings WHERE chiave = ? AND valore = ?');
        $st->execute(['p.home.descrizione', 'Il welcome book digitale per case vacanza, B&B, affittacamere e agriturismi: check-in, Wi-Fi e consigli in un link o QR Code. Gratis finché non pubblichi.']);
        $st->execute(['p.en.descrizione', 'The digital welcome book for holiday homes, B&Bs, guesthouses and farm stays: check-in, Wi-Fi and local tips in one link or QR code. Free until you publish.']);
        $fatti = Db::val("SELECT valore FROM seo_settings WHERE chiave = 'llms.fatti'");
        $vecchio = 'Con Plus e Portfolio la guida ha «Chiedi alla guida»: un assistente che risponde alle domande degli ospiti usando solo il testo della guida, nella loro lingua, e rimanda ai contatti dell\'host quando la guida non lo dice.';
        if (is_string($fatti) && str_contains($fatti, $vecchio)) {
            Db::run("UPDATE seo_settings SET valore = ? WHERE chiave = 'llms.fatti'", [str_replace($vecchio, 'Concierge AI per case vacanza e B&B: con Plus e Portfolio risponde ai tuoi ospiti 24 ore su 24, nella loro lingua, usando solo le informazioni della guida, e rimanda ai contatti dell\'host quando la guida non lo dice.', $fatti)]);
        }
    }
};
