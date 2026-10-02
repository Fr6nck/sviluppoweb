<?php
/**
 * La pagina del codice d'accesso, finché il sito è in anteprima.
 *
 * È la scheda di accesso dell'area riservata, sopra una fotografia sfocata
 * del sito. La fotografia è un'immagine già sfocata (tools/build-sipario.mjs),
 * non la pagina vera: dietro non c'è testo da leggere nel sorgente o con la
 * modalità lettura del browser. Il sito arriva solo dopo il codice.
 *
 * @var string      $azione  l'indirizzo a cui tornare, lo stesso della richiesta
 * @var string|null $errore  la chiave del messaggio, se il codice non va
 * @var string      $lingua
 * @var string      $altra   l'altra lingua del sito
 */

use ArcoDelVento\Support\Csrf;

$rosa       = immagine('marchio.rosa');
$logo       = immagine('marchio.logotipo');
$logoChiaro = immagine('marchio.logotipo-chiaro');
$larghezza  = static fn (array $l, int $alto, float $rapporto): int => (int) round($alto * (($l['w'] ?? 0) && ($l['h'] ?? 0) ? $l['w'] / $l['h'] : $rapporto));
?>
<!DOCTYPE html>
<html lang="<?= e($lingua) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= te('gate.page_title') ?></title>
<link rel="icon" href="<?= e(asset($rosa['src'] . '-192.png')) ?>" type="image/png">
<link rel="stylesheet" href="<?= e(asset('css/fonts.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/tokens.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body class="adm adm--fuori adm--sipario">
<div class="adm-sipario" aria-hidden="true"></div>

<main id="contenuto" class="adm-fuori">
  <div class="adm-fuori__scheda">
    <p class="adm-fuori__marchio">
      <img src="<?= e(asset($rosa['src'] . '-96.png')) ?>" alt="" width="48" height="48">
      <img class="adm-marchio-scuro" src="<?= e(asset((string) $logo['src'])) ?>" alt="Arco del Vento" height="44" width="<?= $larghezza($logo, 44, 111 / 66) ?>">
      <img class="adm-marchio-chiaro" src="<?= e(asset((string) $logoChiaro['src'])) ?>" alt="Arco del Vento" height="44" width="<?= $larghezza($logoChiaro, 44, 185 / 110) ?>">
    </p>
    <p class="adm-fuori__area"><?= te('gate.eyebrow') ?></p>
    <h1 class="adm-titolo"><?= te('gate.title') ?></h1>
    <p class="adm-intro"><?= te('gate.lead') ?></p>

    <?php if ($errore !== null): ?>
      <p class="adm-avviso adm-avviso--errore" role="alert">
        <?= icona('attenzione', 18, 'adm-avviso__icona') ?><span><?= te($errore) ?></span>
      </p>
    <?php endif; ?>

    <form method="post" action="<?= e($azione) ?>" class="adm-modulo adm-modulo--stretto" novalidate>
      <?= Csrf::field() ?>
      <input type="hidden" name="_accesso" value="1">
      <div class="adm-campo">
        <label for="codice"><?= te('gate.label') ?></label>
        <input type="text" id="codice" name="codice" required autocomplete="off" autocapitalize="none"
               spellcheck="false" autofocus<?= $errore !== null ? ' aria-invalid="true"' : '' ?>>
      </div>
      <p><button type="submit" class="adm-bottone"><?= te('gate.submit') ?><?= icona('freccia-destra', 17) ?></button></p>
      <p class="adm-nota"><?= te('gate.note') ?></p>
    </form>
  </div>
  <p class="adm-fuori__piede">
    <a href="<?= e(url('home', [], [], $altra)) ?>" lang="<?= e($altra) ?>" hreflang="<?= e($altra) ?>"><?= e(t('gate.other_lang', [], $lingua)) ?></a>
  </p>
</main>
</body>
</html>
