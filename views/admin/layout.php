<?php
/**
 * Il telaio dell'area riservata.
 *
 * A sinistra la colonna bianca con il marchio, le sezioni e il riquadro di
 * quello che manca; in alto la barra con la ricerca fra le richieste, il
 * sito, le richieste nuove e l'account. Il resto è la tela chiara su cui
 * stanno le schede.
 *
 * Il marchio in colonna è quello del sito: se dall'area Immagini si cambia
 * la rosa o il logotipo, cambia anche qui.
 *
 * @var string      $titolo
 * @var string      $contenuto
 * @var string|null $utente
 * @var array|null  $flash
 * @var list<string> $errori
 * @var int         $nuove
 * @var int         $daCompletare
 * @var string      $vista
 * @var array|null  $versione data e commit del pacchetto caricato, null nella copia di lavoro
 * @var string|null $tema     chiaro | scuro | null: segue il sistema
 * @var string      $percorso la pagina corrente, sotto /admin
 * @var string      $pushChiave la chiave pubblica VAPID, vuota se il server non può spedire notifiche
 */

use ArcoDelVento\Support\Csrf;

$voci = [
    ''          => ['Bacheca', 'griglia'],
    'richieste' => ['Richieste', 'vassoio'],
    'calendario' => ['Calendario', 'calendario'],
    'struttura' => ['La struttura', 'edificio'],
    'camere'    => ['Camere e tariffe', 'letto'],
    'immagini'  => ['Immagini e logo', 'immagine'],
    'testi'     => ['Testi del sito', 'testo'],
    'domande'   => ['Domande frequenti', 'aiuto'],
];
// Immagini, testi e domande sono del super admin: il gestore non le vede.
if (empty($super)) {
    $voci = array_intersect_key($voci, array_flip(['', 'richieste', 'calendario', 'struttura', 'camere']));
}
$attiva = match ($vista) {
    'bacheca' => '',
    'richiesta', 'richieste' => 'richieste',
    'camera', 'camere' => 'camere',
    // Il gestore apre solo le foto delle camere: per lui sono parte di Camere e tariffe.
    'immagine', 'immagini' => empty($super) ? 'camere' : 'immagini',
    'testi-sezione', 'testi' => 'testi',
    default => $vista,
};
$rosa  = immagine('marchio.rosa');
$logo  = immagine('marchio.logotipo');
$logoChiaro = immagine('marchio.logotipo-chiaro');
$larghezza = static fn (array $l, int $alto, float $rapporto): int => (int) round($alto * (($l['w'] ?? 0) && ($l['h'] ?? 0) ? $l['w'] / $l['h'] : $rapporto));
$pulsanteTema = $this->render('admin/_tema', ['tema' => $tema ?? null, 'ritorno' => $percorso ?? '', 'classe' => '']);
$sito  = url('home', [], [], 'it');
$iniziale = mb_strtoupper(mb_substr((string) $utente, 0, 1)) ?: 'A';
?>
<!DOCTYPE html>
<html lang="it"<?= in_array($tema ?? null, ['chiaro', 'scuro'], true) ? ' data-tema="' . e($tema) . '"' : '' ?>>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($titolo) ?> — Arco del Vento · area riservata</title>
<link rel="icon" href="<?= e(asset($rosa['src'] . '-96.png')) ?>" type="image/png">
<?php /* L'area riservata si installa sul telefono come un'app: manifesto, colori, icona per l'iPhone. */ ?>
<link rel="manifest" href="<?= e(adminUrl('manifest.webmanifest')) ?>">
<meta name="theme-color" content="#f8f1e3" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#16100d" media="(prefers-color-scheme: dark)">
<link rel="apple-touch-icon" href="<?= e(asset('img/logo/app-180.png')) ?>">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Arco del Vento">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<link rel="stylesheet" href="<?= e(asset('css/fonts.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/tokens.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
<script src="<?= e(asset('js/admin.js')) ?>" defer></script>
</head>
<?php if ($utente === null): ?>
<body class="adm adm--fuori">
<main id="contenuto" class="adm-fuori">
  <div class="adm-fuori__scheda">
    <p class="adm-fuori__marchio">
      <img src="<?= e(asset($rosa['src'] . '-96.png')) ?>" alt="" width="48" height="48">
      <img class="adm-marchio-scuro" src="<?= e(asset((string) $logo['src'])) ?>" alt="Arco del Vento" height="44" width="<?= $larghezza($logo, 44, 111 / 66) ?>">
      <img class="adm-marchio-chiaro" src="<?= e(asset((string) $logoChiaro['src'])) ?>" alt="Arco del Vento" height="44" width="<?= $larghezza($logoChiaro, 44, 185 / 110) ?>">
    </p>
    <p class="adm-fuori__area">Area riservata</p>
    <h1 class="adm-titolo"><?= e($titolo) ?></h1>
    <?= $this->render('admin/_avvisi', ['flash' => $flash, 'errori' => $errori, 'vista' => $vista]) ?>
    <?= $contenuto ?>
  </div>
  <p class="adm-fuori__piede"><a href="<?= e($sito) ?>"><?= icona('freccia-sinistra', 14) ?> Torna al sito</a></p>
  <?php /* Dopo la scheda, non prima: il primo pulsante del modulo di accesso resta «Entra». */ ?>
  <div class="adm-fuori__tema"><?= $pulsanteTema ?></div>
</main>
</body>
<?php else: ?>
<body class="adm adm--dentro" data-app-sw="<?= e(adminUrl('sw.js')) ?>" data-app-campo="<?= e(adminUrl()) ?>"
      data-push-chiave="<?= e((string) ($pushChiave ?? '')) ?>" data-push-url="<?= e(adminUrl('notifiche')) ?>">
<a class="adm-salta" href="#contenuto">Salta al contenuto</a>

<div class="adm-telaio">
  <aside class="adm-lato">
    <a class="adm-lato__marchio" href="<?= e(adminUrl()) ?>" aria-label="Arco del Vento, area riservata: bacheca">
      <img class="adm-lato__rosa" src="<?= e(asset($rosa['src'] . '-96.png')) ?>" alt="" width="40" height="40">
      <img class="adm-lato__nome adm-marchio-scuro" src="<?= e(asset((string) $logo['src'])) ?>" alt="" height="42" width="<?= $larghezza($logo, 42, 111 / 66) ?>">
      <img class="adm-lato__nome adm-marchio-chiaro" src="<?= e(asset((string) $logoChiaro['src'])) ?>" alt="" height="42" width="<?= $larghezza($logoChiaro, 42, 185 / 110) ?>">
    </a>

    <nav class="adm-nav" aria-label="Sezioni dell'area riservata">
      <p class="adm-nav__gruppo" aria-hidden="true">Menu</p>
      <ul>
        <?php foreach ($voci as $percorso => [$nome, $icona]): ?>
          <li>
            <a href="<?= e(adminUrl($percorso)) ?>"<?= $attiva === $percorso ? ' aria-current="page"' : '' ?>>
              <?= icona($icona, 20, 'adm-nav__icona') ?>
              <span class="adm-nav__nome"><?= e($nome) ?></span>
              <?php if ($percorso === 'richieste' && $nuove > 0): ?>
                <span class="adm-contatore" aria-label="<?= (int) $nuove ?> nuove"><?= (int) $nuove ?></span>
              <?php endif; ?>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
      <p class="adm-nav__gruppo" aria-hidden="true">Generale</p>
      <ul>
        <?php if (!empty($super)): /* accesso al sito e posta: solo il super admin */ ?>
          <li>
            <a href="<?= e(adminUrl('accesso-sito')) ?>"<?= $attiva === 'accesso-sito' ? ' aria-current="page"' : '' ?>>
              <?= icona('chiave', 20, 'adm-nav__icona') ?><span class="adm-nav__nome">Accesso al sito</span>
            </a>
          </li>
          <li>
            <a href="<?= e(adminUrl('posta')) ?>"<?= $attiva === 'posta' ? ' aria-current="page"' : '' ?>>
              <?= icona('posta', 20, 'adm-nav__icona') ?><span class="adm-nav__nome">Ricezione e-mail</span>
            </a>
          </li>
        <?php endif; ?>
        <li>
          <a href="<?= e(adminUrl('account')) ?>"<?= $attiva === 'account' ? ' aria-current="page"' : '' ?>>
            <?= icona('utente', 20, 'adm-nav__icona') ?><span class="adm-nav__nome">Account</span>
          </a>
        </li>
        <?php if (!empty($super)): /* solo il super admin: il gestore non lo vede */ ?>
          <li>
            <a href="<?= e(adminUrl('configurazione')) ?>"<?= $attiva === 'configurazione' ? ' aria-current="page"' : '' ?>>
              <?= icona('ingranaggio', 20, 'adm-nav__icona') ?><span class="adm-nav__nome">Configurazione</span>
            </a>
          </li>
          <?php /* Funzione di prova, isolata: vedi InterruttoreChannelManager. */ ?>
          <li>
            <a href="<?= e(adminUrl('channel-manager')) ?>"<?= $attiva === 'channel-manager' ? ' aria-current="page"' : '' ?>>
              <?= icona('ripristina', 20, 'adm-nav__icona') ?><span class="adm-nav__nome">Channel manager (prova)</span>
            </a>
          </li>
        <?php endif; ?>
        <li>
          <a href="<?= e($sito) ?>" target="_blank" rel="noopener">
            <?= icona('esterno', 20, 'adm-nav__icona') ?><span class="adm-nav__nome">Vedi il sito</span>
          </a>
        </li>
      </ul>
    </nav>

    <div class="adm-promo">
      <?= icona('rosa', 120, 'adm-promo__rosa') ?>
      <?php if ($daCompletare > 0): ?>
        <p class="adm-promo__titolo">Da completare</p>
        <p class="adm-promo__testo"><strong><?= (int) $daCompletare ?></strong>
          <?= $daCompletare === 1 ? 'dato manca' : 'dati mancano' ?> sul sito: lì non compaiono, o compaiono come «da confermare».</p>
        <a class="adm-promo__bottone" href="<?= e(adminUrl()) ?>#da-completare">Completa ora</a>
      <?php else: ?>
        <p class="adm-promo__titolo">Tutto completo</p>
        <?php if (!empty($super)): ?>
          <p class="adm-promo__testo">Nessun dato manca. Le fotografie si possono sempre cambiare.</p>
          <a class="adm-promo__bottone" href="<?= e(adminUrl('immagini')) ?>">Cambia le foto</a>
        <?php else: ?>
          <p class="adm-promo__testo">Nessun dato manca.</p>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    <form method="post" action="<?= e(adminUrl('esci')) ?>" class="adm-esci">
      <?= Csrf::field() ?>
      <button type="submit" class="adm-esci__bottone"><?= icona('esci', 20) ?><span>Esci</span></button>
    </form>
  </aside>

  <div class="adm-corpo">
    <header class="adm-barra">
      <form class="adm-cerca" role="search" method="get" action="<?= e(adminUrl('richieste')) ?>">
        <label class="adm-visually-hidden" for="adm-cerca">Cerca fra le richieste</label>
        <?= icona('cerca', 18, 'adm-cerca__icona') ?>
        <input type="search" id="adm-cerca" name="q" placeholder="Cerca nelle richieste: nome, e-mail, riferimento…"
               value="<?= e((string) ($cerca ?? '')) ?>" autocomplete="off">
      </form>
      <div class="adm-barra__azioni">
        <a class="adm-pillola" href="<?= e($sito) ?>" target="_blank" rel="noopener"><?= icona('esterno', 16) ?><span>Vedi il sito</span></a>
        <?= $pulsanteTema ?>
        <a class="adm-tondo<?= $nuove > 0 ? ' adm-tondo--segnale' : '' ?>" href="<?= e(adminUrl('richieste')) ?>?stato=nuova"
           aria-label="<?= $nuove > 0 ? (int) $nuove . ($nuove === 1 ? ' richiesta nuova' : ' richieste nuove') : 'Nessuna richiesta nuova' ?>">
          <?= icona('campanella', 19) ?>
        </a>
        <a class="adm-profilo" href="<?= e(adminUrl('account')) ?>" aria-label="Account di <?= e((string) $utente) ?>">
          <span class="adm-profilo__iniziale" aria-hidden="true"><?= e($iniziale) ?></span>
          <span class="adm-profilo__testo" aria-hidden="true">
            <span class="adm-profilo__nome"><?= e((string) $utente) ?></span>
            <span class="adm-profilo__ruolo"><?= !empty($super) ? 'Super admin' : 'Amministratore' ?></span>
          </span>
        </a>
      </div>
    </header>

    <main id="contenuto" class="adm-main" tabindex="-1">
      <div class="adm-intestazione">
        <h1 class="adm-titolo"><?= e($titolo) ?></h1>
        <?php if (!empty($sottotitolo)): ?><p class="adm-sottotitolo"><?= e($sottotitolo) ?></p><?php endif; ?>
      </div>
      <?= $this->render('admin/_avvisi', ['flash' => $flash, 'errori' => $errori, 'vista' => $vista]) ?>
      <?= $contenuto ?>
      <?php /* Quale pacchetto c'è sul server: dopo un caricamento via FTP si guarda qui. */ ?>
      <p class="adm-versione">Versione del sito:
        <?= !empty($versione) ? 'pacchetto del ' . e(dataOra((string) $versione['data'])) . (!empty($versione['commit']) ? ' · ' . e((string) $versione['commit']) : '') : 'copia di lavoro, senza pacchetto' ?></p>
    </main>
  </div>
</div>

<?php /* L'invito a installare l'app e ad attivare le notifiche. Solo qui, dentro l'area
         riservata: lo vede solo chi ha fatto l'accesso. Lo mostra admin-app.js, e solo sul
         telefono; senza JavaScript resta nascosto. */ ?>
<aside class="adm-app" id="adm-app" aria-label="L'area riservata sul telefono" hidden>
  <img class="adm-app__icona" src="<?= e(asset('img/logo/app-192.png')) ?>" alt="" width="52" height="52">
  <div class="adm-app__corpo">
    <div data-app-variante="installa" data-si="Installa" hidden>
      <p class="adm-app__titolo">L'area riservata, come un'app</p>
      <p class="adm-app__testo">Installala sul telefono: si apre con un tocco dalla schermata Home e ti avvisa a ogni nuova prenotazione.</p>
    </div>
    <div data-app-variante="iphone" data-si="" data-no="Ho capito" hidden>
      <p class="adm-app__titolo">L'area riservata, come un'app</p>
      <ol class="adm-app__passi">
        <li>Tocca <span class="adm-app__segno"><?= icona('condividi', 15) ?></span> <strong>Condividi</strong>, nella barra di Safari.</li>
        <li>Scegli <span class="adm-app__segno"><?= icona('aggiungi', 15) ?></span> <strong>Aggiungi alla schermata Home</strong>.</li>
        <li>Apri l'app dall'icona e attiva le notifiche.</li>
      </ol>
    </div>
    <div data-app-variante="notifiche" data-si="Attiva le notifiche" hidden>
      <p class="adm-app__titolo">Attiva le notifiche</p>
      <p class="adm-app__testo">Una notifica per ogni nuova richiesta, prenotazione pagata e messaggio, anche con l'app chiusa.</p>
    </div>
    <p class="adm-app__esito" data-app-esito role="status"></p>
    <div class="adm-app__azioni">
      <button type="button" class="adm-bottone" data-app-si>Installa</button>
      <button type="button" class="adm-bottone adm-bottone--piatto" data-app-no>Non ora</button>
    </div>
  </div>
</aside>
<script src="<?= e(asset('js/admin-app.js')) ?>" defer></script>
</body>
<?php endif; ?>
</html>
