<?php
/* 9B · Una scheda di «Cosa dicono di noi»: copertina in alto (se scelta), logo nel quadrato bianco (se scelto), stelle,
   testo tra «», foto tonda + nome + «Struttura · Città». Nessun link, nessuna area toccabile. Riceve $g (con 'immagini'). */
use MHW\{Support, LinguaSito};
$imS = $g['immagini'] ?? ['copertina' => null, 'foto' => null, 'logo' => null];
$enS = LinguaSito::en(); ?>
<figure class="dicono-scheda<?= $imS['copertina'] ? '' : ' dicono-scheda--senza-copertina' ?>">
  <?php if ($imS['copertina'] || $imS['logo']): ?>
    <div class="dicono-scheda__cop">
      <?php if ($imS['copertina']): ?><img src="<?= Support::e($imS['copertina']) ?>" alt="<?= Support::e(($enS ? 'Cover of ' : 'Copertina di ') . $g['property_name']) ?>" loading="lazy" width="800" height="450"><?php endif; ?>
      <?php if ($imS['logo']): ?><span class="dicono-scheda__logo"><img src="<?= Support::e($imS['logo']) ?>" alt="<?= Support::e(($enS ? 'Logo of ' : 'Logo di ') . $g['property_name']) ?>" loading="lazy" width="56" height="56"></span><?php endif; ?>
    </div>
  <?php endif; ?>
  <div class="dicono-scheda__corpo">
    <span class="stelline dicono-scheda__voto" role="img" aria-label="<?= (int) $g['rating'] ?> <?= $enS ? 'out of 5' : 'su 5' ?>"><?= str_repeat('★', (int) $g['rating']) . str_repeat('☆', 5 - (int) $g['rating']) ?></span>
    <blockquote><p>«<?= Support::e($g['body']) ?>»</p></blockquote>
    <figcaption class="dicono-scheda__chi">
      <?php if ($imS['foto']): ?><img src="<?= Support::e($imS['foto']) ?>" alt="<?= Support::e(($enS ? 'Photo of ' : 'Foto di ') . $g['name']) ?>" width="44" height="44" loading="lazy"><?php else: ?><span class="dicono-scheda__iniziale" aria-hidden="true"><?= Support::e(mb_strtoupper(mb_substr((string) $g['name'], 0, 1))) ?></span><?php endif; ?>
      <span><b><?= Support::e($g['name']) ?></b><span class="small muted"><?= Support::e(trim($g['property_name'] . ($g['city'] !== '' ? ' · ' . $g['city'] : ''), ' ·')) ?></span></span>
    </figcaption>
  </div>
</figure>
