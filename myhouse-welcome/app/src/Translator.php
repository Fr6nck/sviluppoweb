<?php
namespace MHW;

/**
 * Traduzione automatica, opzionale. Senza provider configurato l'applicazione
 * funziona lo stesso: le lingue si compilano a mano.
 *
 * La regola che conta: una traduzione che un umano ha già rivisto non viene
 * MAI sovrascritta da una macchina.
 */
final class Translator
{
    public static function enabled(): bool
    {
        $t = Config::get('translator');
        return $t['provider'] !== '' && $t['api_key'] !== '';
    }

    public static function translate(string $text, string $from, string $to): ?string
    {
        if (!self::enabled() || trim($text) === '') return null;
        $t = Config::get('translator');
        if ($t['provider'] === 'deepl') return self::deepl($text, $from, $to, $t['api_key']);
        if ($t['provider'] === 'libre') return self::libre($text, $from, $to, $t['api_key']);
        return null;
    }

    private static function deepl(string $text, string $from, string $to, string $key): ?string
    {
        $ch = curl_init('https://api-free.deepl.com/v2/translate');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => ['Authorization: DeepL-Auth-Key ' . $key],
            CURLOPT_POSTFIELDS => http_build_query([
                'text' => $text, 'source_lang' => strtoupper($from), 'target_lang' => strtoupper($to),
            ]),
        ]);
        $body = curl_exec($ch); curl_close($ch);
        $j = json_decode((string) $body, true);
        return $j['translations'][0]['text'] ?? null;
    }

    private static function libre(string $text, string $from, string $to, string $key): ?string
    {
        $ch = curl_init('https://libretranslate.com/translate');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode(['q' => $text, 'source' => $from, 'target' => $to, 'api_key' => $key]),
        ]);
        $body = curl_exec($ch); curl_close($ch);
        $j = json_decode((string) $body, true);
        return $j['translatedText'] ?? null;
    }

    /** Riempie solo cio' che manca; salta tutto quello che è già 'reviewed'. */
    public static function fillProperty(int $propertyId, array $locales): array
    {
        $prop = Db::one('SELECT * FROM properties WHERE id = ?', [$propertyId]);
        $src = $prop['default_locale'];
        $done = 0; $skipped = 0;

        foreach (Db::all('SELECT id FROM sections WHERE property_id = ?', [$propertyId]) as $s) {
            $base = Db::one('SELECT * FROM section_translations WHERE section_id = ? AND locale = ?', [$s['id'], $src]);
            if (!$base) continue;
            foreach ($locales as $loc) {
                if ($loc === $src) continue;
                $cur = Db::one('SELECT * FROM section_translations WHERE section_id = ? AND locale = ?', [$s['id'], $loc]);
                if ($cur && $cur['state'] === 'reviewed') { $skipped++; continue; }
                $title = self::translate($base['title'], $src, $loc);
                $body  = self::translate($base['body'], $src, $loc);
                if ($title === null && $body === null) continue;
                $data = [
                    'title' => $title ?? $base['title'], 'body' => $body ?? $base['body'],
                    'state' => 'machine', 'updated_at' => Support::now(),
                ];
                if ($cur) Db::update('section_translations', $data, 'id = :tid', ['tid' => $cur['id']]);
                else Db::insert('section_translations', $data + ['section_id' => $s['id'], 'locale' => $loc]);
                $done++;
            }
        }
        return ['tradotte' => $done, 'saltate_perche_riviste' => $skipped];
    }
}
