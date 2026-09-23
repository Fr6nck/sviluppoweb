<?php
/**
 * Una riga di contatto.
 *
 * Finché il dato non è confermato mostra il marcatore invece di un link che
 * non porta da nessuna parte. Appena si compila `contacts` in
 * content/settings.php, la stessa riga diventa un «tel:», un «mailto:» o un
 * link a WhatsApp in tutto il sito, senza toccare nessuna vista.
 *
 * @var string      $tipo   phone | email | whatsapp
 * @var string|null $valore
 */

$etichette = ['phone' => 'cta.call', 'email' => 'contact.form.email', 'whatsapp' => 'cta.whatsapp'];
$etichetta = te($etichette[$tipo] ?? 'contact.form.email');

if (empty($valore)) {
    echo $etichetta . ': ' . daConfermare();
    return;
}

$href = match ($tipo) {
    'phone'    => 'tel:' . preg_replace('/[^\d+]/', '', $valore),
    'email'    => 'mailto:' . $valore,
    'whatsapp' => 'https://wa.me/' . preg_replace('/\D/', '', $valore),
};
?>
<a class="adv-contatto" href="<?= e($href) ?>"<?= $tipo === 'whatsapp' ? ' rel="noopener"' : '' ?>><?= e($valore) ?></a>
