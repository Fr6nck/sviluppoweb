<?php
/**
 * Le domande di sempre, con <details> nativo: nessuna libreria, nessun
 * JavaScript, e il contenuto è nel DOM, quindi indicizzabile.
 *
 * La prima resta aperta: una fila di righe tutte chiuse non dice a nessuno
 * che sono cliccabili.
 *
 * @var array $domande
 */

$lingua = locale();
?>
<div class="adv-domande">
  <?php foreach ($domande as $i => $d): ?>
    <details class="adv-domanda"<?= $i === 0 ? ' open' : '' ?>>
      <summary>
        <span><?= e($d['q'][$lingua] ?? $d['q']['it']) ?></span>
        <span class="adv-domanda__segno" aria-hidden="true"></span>
      </summary>
      <p class="adv-domanda__risposta">
        <?php
        // Il segno {dc} diventa il marcatore «da confermare». Lo stesso testo
        // alimenta il JSON-LD, così pagina e dato strutturato coincidono.
        $testo = (string) ($d['a'][$lingua] ?? $d['a']['it']);
        $parti = explode('{dc}', $testo);
        foreach ($parti as $k => $parte) {
            echo e($parte);
            if ($k < count($parti) - 1) {
                echo daConfermare();
            }
        }
        ?>
      </p>
    </details>
  <?php endforeach; ?>
</div>
