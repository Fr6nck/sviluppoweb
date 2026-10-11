<?php
/* Sotto un errore di accesso, registrazione o nuova password (7A): l'assistenza, con l'email usata
   e la pagina già scritte. Mai la password. $aiutoEmail, $aiutoPagina. */
use MHW\Support;
if (Support::contatto() === '') return; ?>
<p class="small aiuto-accesso">Problemi? <a href="<?= Support::e(Support::mailto('Aiuto con l\'accesso',
  "Ciao, ho un problema con l'accesso a MyHouse Welcome.\n\nEmail usata: " . ($aiutoEmail ?? '') . "\nPagina: " . ($aiutoPagina ?? '') . "\nCosa succede:\n")) ?>">Scrivici a <?= Support::e(Support::contatto()) ?></a></p>
