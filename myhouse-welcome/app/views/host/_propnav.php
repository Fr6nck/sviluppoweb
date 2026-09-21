<?php /* Le voci della struttura, da mettere dentro la pastiglia del telaio. */
use function MHW\b; use MHW\Support; ?>
<a href="<?= b() ?>/pannello/<?= (int) $p['id'] ?>" class="<?= $qui === 'guida' ? 'on' : '' ?>">La guida</a>
<a href="<?= b() ?>/pannello/<?= (int) $p['id'] ?>/lingue" class="<?= $qui === 'lingue' ? 'on' : '' ?>">Lingue</a>
<a href="<?= b() ?>/pannello/<?= (int) $p['id'] ?>/qr" class="<?= $qui === 'qr' ? 'on' : '' ?>">QR</a>
<a href="<?= b() ?>/pannello/<?= (int) $p['id'] ?>/impostazioni" class="<?= $qui === 'impostazioni' ? 'on' : '' ?>">Impostazioni</a>
