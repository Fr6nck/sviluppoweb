<?php use function MHW\b; use MHW\LinguaSito; $U = [LinguaSito::class, 't']; $title = $U('Conferma email', 'Confirm email'); ?>
<h1><?= $U('Link non valido.', 'Invalid link.') ?></h1>
<p class="muted" style="margin-top:14px"><?= $U('Questo link di conferma è scaduto o è già stato usato. Accedi al tuo account:
  da lì puoi farti mandare un\'email nuova.', 'This confirmation link has expired or has already been used. Log in to your account: from there you can have a new email sent.') ?></p>
<p style="margin-top:20px"><a class="btn" href="<?= b() ?>/accedi"><?= $U('Accedi', 'Log in') ?></a> <a class="btn btn--ghost" href="<?= b() ?>/en/login" lang="en">Log in (English)</a></p>
