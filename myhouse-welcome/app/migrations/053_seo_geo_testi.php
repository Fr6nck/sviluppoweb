<?php
/**
 * 053 — SEO e GEO: le descrizioni di serie della home (IT e EN) stavano oltre i 155 caratteri dello snippet di Google
 * (169 e 163) e l'introduzione di llms.txt non diceva «welcome book digitale». Chi ha salvato «SEO e GEO» ha in
 * seo_settings i testi di serie di allora: solo se sono ancora identici a quelli si tolgono, così valgono i nuovi
 * (Seo::predefiniti). Un testo cambiato a mano resta com'è. Una seconda esecuzione non cambia niente.
 */
use MHW\{Db, Migrator};

return function (\PDO $pdo): void {
    if (!Migrator::tableExists('seo_settings')) return;
    $vecchi = [
        'p.home.descrizione' => 'La guida digitale per case vacanza, B&B, affittacamere e agriturismi: check-in, Wi-Fi e consigli in un link e un QR Code. La crei gratis, paghi solo quando la pubblichi.',
        'p.en.descrizione' => 'The digital guide for holiday homes, B&Bs, guesthouses and farm stays: check-in, Wi-Fi and local tips in one link and QR code. Build it free, pay when you publish.',
        'llms.intro' => 'MyHouse Welcome è la guida digitale per case vacanza, B&B, affittacamere e agriturismi: check-in, Wi-Fi, regole della casa e consigli della zona in un link e un QR Code, che gli ospiti aprono dal telefono senza scaricare app.',
    ];
    foreach ($vecchi as $k => $v) Db::run('DELETE FROM seo_settings WHERE chiave = ? AND valore = ?', [$k, $v]);
};
