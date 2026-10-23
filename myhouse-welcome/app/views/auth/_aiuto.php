<?php
/* Sotto un errore di accesso, registrazione o nuova password (7A): l'assistenza, con l'email usata
   e la pagina già scritte. Mai la password. $aiutoEmail, $aiutoPagina. */
use MHW\{Support, LinguaSito};
if (Support::contatto() === '') return; $enA = LinguaSito::en(); ?>
<p class="small aiuto-accesso"><?= $enA ? 'Problems?' : 'Problemi?' ?> <a href="<?= Support::e($enA ? Support::mailto('Help with logging in', "Hello, I have a problem logging in to MyHouse Welcome.\n\nEmail used: " . ($aiutoEmail ?? '') . "\nPage: " . ($aiutoPagina ?? '') . "\nWhat happens:\n")
  : Support::mailto('Aiuto con l\'accesso', "Ciao, ho un problema con l'accesso a MyHouse Welcome.\n\nEmail usata: " . ($aiutoEmail ?? '') . "\nPagina: " . ($aiutoPagina ?? '') . "\nCosa succede:\n")) ?>"><?= $enA ? 'Write to us at' : 'Scrivici a' ?> <?= Support::e(Support::contatto()) ?></a></p>
