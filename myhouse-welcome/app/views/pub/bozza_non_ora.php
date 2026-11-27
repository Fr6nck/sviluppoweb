<?php /* 9Q · «Non ora» dall'email del giorno 21: niente più email sulla bozza. Riceve $en. */
use function MHW\b; $title = $en ? 'Not now' : 'Non ora'; ?>
<h1><?= $en ? 'All right.' : 'Va bene.' ?></h1>
<p class="muted" style="margin-top:14px;line-height:24px"><?= $en ? 'We won\'t write to you about this again. The draft stays in your dashboard: you can pick it up whenever you like.'
                                                                  : 'Non ti scriviamo più su questo. La bozza resta nel tuo pannello: la riprendi quando vuoi.' ?></p>
<p style="margin-top:20px"><a class="btn btn--ghost" href="<?= b() ?>/pannello"><?= $en ? 'Open the dashboard' : 'Apri il pannello' ?></a></p>
