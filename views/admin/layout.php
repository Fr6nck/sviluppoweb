<?php
/**
 * Il telaio dell'area riservata.
 *
 * Sobrio di proposito: è uno strumento di lavoro, non una vetrina. Usa i
 * colori e i caratteri del sito — chi lo apre deve riconoscere la casa — ma
 * niente fotografie e niente decorazioni.
 *
 * @var string      $titolo
 * @var string      $contenuto
 * @var string|null $utente
 * @var array|null  $flash
 * @var list<string> $errori
 * @var int         $nuove
 * @var string      $vista
 */

use ArcoDelVento\Support\Csrf;

$voci = [
    ''          => 'Bacheca',
    'richieste' => 'Richieste',
    'struttura' => 'La struttura',
    'camere'    => 'Camere e tariffe',
    'testi'     => 'Testi del sito',
    'domande'   => 'Domande frequenti',
    'account'   => 'Account',
];
$attiva = match ($vista) {
    'bacheca' => '',
    'richiesta', 'richieste' => 'richieste',
    'camera', 'camere' => 'camere',
    'testi-sezione', 'testi' => 'testi',
    default => $vista,
};
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($titolo) ?> — Arco del Vento · area riservata</title>
<link rel="icon" href="<?= e(asset('img/logo/icona-48.png')) ?>" type="image/png">
<link rel="stylesheet" href="<?= e(asset('css/fonts.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/tokens.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body class="adm">
<a class="adm-salta" href="#contenuto">Salta al contenuto</a>

<header class="adm-testata">
  <p class="adm-marchio">
    <img src="<?= e(asset('img/logo/icona-48.png')) ?>" alt="" width="32" height="32">
    <span>Arco del Vento <span class="adm-marchio__area">· area riservata</span></span>
  </p>
  <?php if ($utente !== null): ?>
    <div class="adm-testata__azioni">
      <a class="adm-link" href="<?= e(url('home', [], [], 'it')) ?>" target="_blank" rel="noopener">Vedi il sito<span aria-hidden="true"> ↗</span></a>
      <form method="post" action="<?= e(adminUrl('esci')) ?>" class="adm-esci">
        <?= Csrf::field() ?>
        <button type="submit" class="adm-bottone adm-bottone--piatto">Esci</button>
      </form>
    </div>
  <?php endif; ?>
</header>

<div class="adm-telaio<?= $utente === null ? ' adm-telaio--solo' : '' ?>">
  <?php if ($utente !== null): ?>
    <nav class="adm-nav" aria-label="Sezioni dell'area riservata">
      <ul>
        <?php foreach ($voci as $percorso => $nome): ?>
          <li>
            <a href="<?= e(adminUrl($percorso)) ?>"<?= $attiva === $percorso ? ' aria-current="page"' : '' ?>>
              <?= e($nome) ?>
              <?php if ($percorso === 'richieste' && $nuove > 0): ?>
                <span class="adm-contatore" aria-label="<?= (int) $nuove ?> nuove"><?= (int) $nuove ?></span>
              <?php endif; ?>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    </nav>
  <?php endif; ?>

  <main id="contenuto" class="adm-main" tabindex="-1">
    <h1 class="adm-titolo"><?= e($titolo) ?></h1>

    <?php if (!empty($flash)): ?>
      <p class="adm-avviso adm-avviso--<?= $flash['tipo'] === 'ok' ? 'ok' : 'errore' ?>" role="status"><?= e($flash['testo']) ?></p>
    <?php endif; ?>

    <?php if (!empty($errori)): ?>
      <div class="adm-avviso adm-avviso--errore" role="alert">
        <?php if (in_array($vista, ['struttura', 'camera', 'testi-sezione', 'domande'], true)): ?>
          <p><strong>Non è stato salvato niente.</strong> Da correggere:</p>
        <?php endif; ?>
        <ul>
          <?php foreach ($errori as $errore): ?>
            <li><?= e($errore) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?= $contenuto ?>
  </main>
</div>
</body>
</html>
