<?php
/* 9L · Una scheda di «Le guide dei nostri host»: copertina in alto (max 800 px, alt = nome della struttura), logo nel
   quadrato bianco sul bordo (se c'è), poi le stelle ocra e il commento se il giudizio è approvato, altrimenti
   «Guida online da {mese anno}»; sotto il nome dell'host (senza cognome) e «Struttura · Città (PR)». Nessuna foto
   dell'host, nessun link, nessuna area toccabile. Riceve $g (Vetrina::perScheda). Serve anche alle anteprime. */
use MHW\{Support, LinguaSito};
$enS = LinguaSito::en();
$meseS = (string) ($g['online_da'] ?? '') !== '' ? (int) gmdate('n', (int) strtotime((string) $g['online_da'])) : 0;
$annoS = $meseS ? gmdate('Y', (int) strtotime((string) $g['online_da'])) : '';
$nomiMese = $enS ? ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']
                 : ['', 'gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno', 'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre']; ?>
<figure class="dicono-scheda<?= $g['copertina'] ? '' : ' dicono-scheda--senza-copertina' ?>">
  <div class="dicono-scheda__cop">
    <?php if ($g['copertina']): ?><img src="<?= Support::e($g['copertina']) ?>" alt="<?= Support::e($g['property_name']) ?>" loading="lazy" width="800" height="450"><?php endif; ?>
    <?php if ($g['logo']): ?><span class="dicono-scheda__logo"><img src="<?= Support::e($g['logo']) ?>" alt="<?= Support::e(($enS ? 'Logo of ' : 'Logo di ') . $g['property_name']) ?>" loading="lazy" width="56" height="56"></span><?php endif; ?>
  </div>
  <div class="dicono-scheda__corpo">
    <?php if ($g['rating'] > 0): ?>
      <span class="stelline dicono-scheda__voto" role="img" aria-label="<?= (int) $g['rating'] ?> <?= $enS ? 'out of 5' : 'su 5' ?>"><?= str_repeat('★', (int) $g['rating']) . str_repeat('☆', 5 - (int) $g['rating']) ?></span>
      <blockquote><p>«<?= Support::e($g['body']) ?>»</p></blockquote>
      <?php if ($g['risposta'] !== ''): ?><p class="dicono-scheda__risposta"><b>MyHouse Welcome:</b> <?= Support::e($g['risposta']) ?></p><?php endif; ?>
    <?php elseif ($meseS): ?>
      <span class="dicono-scheda__online"><?= $enS ? 'Guide online since ' : 'Guida online da ' ?><?= Support::e($nomiMese[$meseS] . ' ' . $annoS) ?></span>
    <?php endif; ?>
    <figcaption class="dicono-scheda__chi">
      <b><?= Support::e($g['name']) ?></b><span class="small muted"><?= Support::e(trim($g['property_name'] . ($g['luogo'] !== '' ? ' · ' . $g['luogo'] : ''), ' ·')) ?></span>
    </figcaption>
  </div>
</figure>
