<?php
/**
 * Dati strutturati.
 *
 * Regola di questo file: si dichiara solo ciò che è confermato. Un telefono
 * o una tariffa inventati in JSON-LD non sono un dettaglio tecnico — sono
 * quello che Google mostra nei risultati, e quello che l'ospite legge prima
 * ancora di aprire il sito.
 *
 * @var string $pagina
 * @var string $immagine   indirizzo assoluto dell'immagine della casa
 */

use ArcoDelVento\Support\Html;

$contatti = site('contacts');
$geo      = site('geo');

$struttura = [
    '@context'   => 'https://schema.org',
    '@type'      => 'LodgingBusiness',
    'name'       => site('legal_name'),
    'alternateName' => site('name'),
    'url'        => assoluto(url('home')),
    'image'      => $immagine,
    'address'    => array_filter([
        '@type'           => 'PostalAddress',
        'streetAddress'   => site('address.street'),
        'addressLocality' => site('address.city'),
        'addressRegion'   => site('address.province'),
        'postalCode'      => site('address.postal_code'),
        'addressCountry'  => site('address.country'),
    ]),
    'numberOfRooms' => site('rooms_count'),
    'foundingDate'  => (string) site('established'),
];

// I campi che si aggiungono solo quando esistono davvero.
if (!empty($contatti['phone'])) {
    $struttura['telephone'] = $contatti['phone'];
}
if (!empty($contatti['email'])) {
    $struttura['email'] = $contatti['email'];
}
if (!empty($geo['latitude']) && !empty($geo['longitude'])) {
    $struttura['geo'] = [
        '@type'     => 'GeoCoordinates',
        'latitude'  => $geo['latitude'],
        'longitude' => $geo['longitude'],
    ];
}
// Niente aggregateRating: non ci sono recensioni, e inventarle è vietato
// dalle linee guida di Google prima ancora che dal buon senso.
// Niente priceRange: le tariffe non sono confermate.
?>
<script type="application/ld+json"><?= Html::jsonLd($struttura) ?></script>

<?php
// La FAQ in dato strutturato deve dire esattamente quello che dice la pagina.
if (!empty($domande)) {
    $lingua = locale();
    $voci   = [];
    foreach ($domande as $d) {
        $risposta = str_replace('{dc}', t('common.to_confirm'), (string) ($d['a'][$lingua] ?? $d['a']['it'] ?? ''));
        $voci[]   = [
            '@type'          => 'Question',
            'name'           => (string) ($d['q'][$lingua] ?? $d['q']['it'] ?? ''),
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $risposta],
        ];
    }
    echo '<script type="application/ld+json">'
       . Html::jsonLd(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $voci])
       . '</script>';
}
?>
