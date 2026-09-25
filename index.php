<?php
/**
 * Arco del Vento — installazione incompleta.
 *
 * Questo file non viene mai servito quando l'installazione è giusta: con
 * l'.htaccess nella cartella del sito ogni richiesta entra in public/, e da lì
 * risponde public/index.php. Se invece arriva qualcuno qui, vuol dire che
 * quell'.htaccess sul server non c'è — il nome comincia con un punto, il
 * computer lo nasconde, e nel caricamento resta indietro.
 *
 * Senza questo file il server risponderebbe «403 Forbidden» e basta, che non
 * dice a nessuno che cosa manca. Così lo dice, a chi sta installando.
 *
 * Non carica niente dell'applicazione e non stampa nessun percorso del server.
 */

declare(strict_types=1);

http_response_code(503);
header('Content-Type: text/html; charset=UTF-8');
header('X-Robots-Tag: noindex, nofollow');
header('Retry-After: 600');
header('Cache-Control: no-store');
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Arco del Vento — installazione incompleta</title>
<style>
  body {
    margin: 0; min-height: 100vh; display: grid; place-items: center;
    background: #f8f1e3; color: #1c3b35; padding: 24px;
    font-family: -apple-system, "Segoe UI", system-ui, sans-serif; font-size: 16px; line-height: 26px;
  }
  main { max-width: 36rem; }
  h1 { font-family: Georgia, serif; font-weight: 400; font-size: 32px; line-height: 38px; color: #1c3b35; margin: 0 0 16px; }
  p, li { color: #6b6257; }
  code { background: rgba(182, 76, 40, .1); color: #92391c; padding: 1px 6px; border-radius: 4px; font-size: 15px; }
  .occhiello { font-size: 12px; font-weight: 600; letter-spacing: .16em; color: #1c3b35; }
</style>
</head>
<body>
<main>
  <p class="occhiello">ARCO DEL VENTO · INSTALLAZIONE INCOMPLETA</p>
  <h1>Manca il file <code>.htaccess</code> nella cartella del sito</h1>
  <p>I file del sito sono arrivati, ma non quello che dice al server come
     usarli. Il nome comincia con un punto, e molti computer lo nascondono:
     nel caricamento è rimasto indietro.</p>
  <ol>
    <li>Nel pacchetto c'è una copia con un nome normale:
        <code>file-da-rinominare/htaccess-radice.txt</code>.</li>
    <li>Caricala in questa stessa cartella, accanto a <code>public</code>,
        <code>src</code> e <code>.env</code>.</li>
    <li>Sul server rinominala <code>.htaccess</code> — con il punto davanti,
        senza <code>.txt</code>.</li>
    <li>Ricarica questa pagina.</li>
  </ol>
  <p>Le cartelle private del sito restano chiuse anche adesso: ognuna ha il
     suo file che le protegge.</p>
</main>
</body>
</html>
