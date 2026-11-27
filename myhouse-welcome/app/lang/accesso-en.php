<?php
/**
 * 9N — Registrazione, accesso e password in inglese: i messaggi dei controlli (Auth, routes_public) tradotti per chi è su
 * /en/register, /en/login o ha il pannello in inglese. Chiave = il messaggio italiano esatto; LinguaSito::tr() lo cerca qui.
 */
use MHW\Auth;
return [
    'Scrivi il tuo nome.' => 'Write your name.',
    'Il nome è troppo lungo.' => 'The name is too long.',
    'Questo indirizzo email non è valido.' => 'This email address is not valid.',
    'Esiste già un account con questa email. Accedi, oppure recupera la password.' => 'An account with this email already exists. Log in, or recover your password.',
    'La password deve avere almeno ' . Auth::PASSWORD_MIN . ' caratteri.' => 'The password must be at least ' . Auth::PASSWORD_MIN . ' characters long.',
    'La password può avere al massimo 72 caratteri.' => 'The password can be at most 72 characters long.',
    'La password non può essere uguale alla tua email o al tuo nome.' => 'The password cannot be the same as your email or your name.',
    'Questa password è tra le più usate al mondo: scegline una meno facile da indovinare.' => 'This password is among the most used in the world: choose one that is harder to guess.',
    'Questo codice non esiste o non è più attivo. Controlla con chi te l\'ha dato.' => 'This code does not exist or is no longer active. Check with whoever gave it to you.',
    'Troppe registrazioni da questa connessione. Riprova tra un\'ora.' => 'Too many sign-ups from this connection. Try again in an hour.',
    'Per creare l\'account devi accettare i Termini e l\'Accordo sul trattamento dei dati, dopo aver letto l\'Informativa privacy.' => 'To create the account you must accept the Terms and the Data processing agreement, after reading the Privacy policy.',
    'Troppi tentativi. Aspetta un quarto d\'ora, oppure recupera la password.' => 'Too many attempts. Wait a quarter of an hour, or recover your password.',
    'La password provvisoria è scaduta: chiedi un link per reimpostarla.' => 'The temporary password has expired: ask for a link to reset it.',
    'Email o password non corrispondono.' => 'Email and password do not match.',
    'Le due password non sono uguali.' => 'The two passwords do not match.',
    'Le due password non sono uguali: riscrivile.' => 'The two passwords do not match: write them again.',
    'Questo codice omaggio non esiste o non è più attivo. Controlla con chi te l\'ha dato.' => 'This gift code does not exist or is no longer active. Check with whoever gave it to you.',
    'Questo codice omaggio è già stato usato.' => 'This gift code has already been used.',
    'Hai già usato questo codice omaggio.' => 'You have already used this gift code.',
    'Il codice omaggio vale solo per i nuovi clienti: il tuo account ha già un abbonamento pagato.' => 'The gift code is for new customers only: your account already has a paid subscription.',
    'Troppi tentativi: riprova tra un quarto d\'ora.' => 'Too many attempts: try again in a quarter of an hour.',
    'Scrivi un indirizzo email valido.' => 'Write a valid email address.',
    'Email confermata: ora prepari la tua guida.' => 'Email confirmed: now prepare your guide.',
    'Ti abbiamo già scritto più volte in un\'ora: riprova più tardi.' => 'We have already written to you several times in an hour: try again later.',
    'È lo stesso indirizzo di prima: controlla anche in Spam e Promozioni.' => 'It is the same address as before: check Spam and Promotions too.',
    'Questa email è già usata da un altro account: accedi con quella, oppure recupera la password.' => 'This email is already used by another account: log in with it, or recover your password.',
    'Aspetta qualche secondo prima di rimandarla.' => 'Wait a few seconds before sending it again.',
    'Ti abbiamo già scritto più volte: controlla anche in Spam e Promozioni, oppure riprova tra un\'ora.' => 'We have already written to you several times: check Spam and Promotions too, or try again in an hour.',
    'L\'email non è partita. Riprova tra poco.' => 'The email was not sent. Try again shortly.',
    'Troppe richieste. Riprova tra un\'ora.' => 'Too many requests. Try again in an hour.',
    'Le due password non coincidono.' => 'The two passwords do not match.',
    'Il link è già stato usato.' => 'The link has already been used.',
    'Password cambiata. Accedi con quella nuova.' => 'Password changed. Log in with the new one.',
    'Scegli una password diversa da quella provvisoria.' => 'Choose a password different from the temporary one.',
    'Password cambiata: da adesso entri con quella nuova.' => 'Password changed: from now on you log in with the new one.',
];
