<?php
/**
 * Un messaggio in linea, nei quattro stati del sistema.
 *
 * L'errore porta sempre icona e parola: state-errore è un rosso vicino al
 * mattone del marchio, e chi non distingue bene i rossi non deve dipendere
 * dalla tinta. role="alert" solo per ciò che blocca la strada — interrompe
 * lo screen reader, e su un'informazione di servizio è una scortesia.
 *
 * @var string $tipo    successo | avviso | errore | info
 * @var string $titolo
 * @var string $testo
 * @var string $testoHtml
 */

$tipo  = $tipo ?? 'info';
$ruolo = $tipo === 'errore' ? 'alert' : 'status';

$icone = [
    'successo' => '<path d="M20 6L9 17l-5-5"/>',
    'avviso'   => '<path d="M12 9v4M12 17h.01M10.3 3.9L2 18a2 2 0 001.7 3h16.6a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z"/>',
    'errore'   => '<circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/>',
    'info'     => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
];
?>
<div class="adv-avviso adv-avviso--<?= e($tipo) ?>" role="<?= e($ruolo) ?>">
  <svg class="adv-avviso__icona" width="24" height="24" viewBox="0 0 24 24" fill="none"
       stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    <?= $icone[$tipo] ?? $icone['info'] ?>
  </svg>
  <div>
    <?php if (!empty($titolo)): ?><strong class="adv-avviso__titolo"><?= e($titolo) ?></strong><?php endif; ?>
    <?= $testoHtml ?? e($testo ?? '') ?>
  </div>
</div>
