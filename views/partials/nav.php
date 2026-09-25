<?php
/**
 * La riga bruna in cima, la testata a pillola e il menu su schermo stretto.
 *
 * Nella testata il logotipo sta al centro: le voci a sinistra, lingua e
 * «Prenota» a destra. Su schermo stretto a sinistra va il pulsante del menu,
 * e il logotipo resta dov'è. La testata resta attaccata in alto mentre si
 * scorre, in vetro chiaro; dopo i primi settanta pixel si stringe e prende
 * l'ombra (lo fa site.js), e una riga mattone sul fondo dice quanto manca
 * alla fine della pagina.
 *
 * La riga in cima porta solo fatti che il titolare ha confermato: il centro
 * storico, l'accoglienza di persona, il parcheggio a Piazza Matteotti, il
 * 2002.
 *
 * @var array $alternative lingua => indirizzo della pagina corrente
 */

$voci = [
    'rooms'    => 'nav.rooms',
    'property' => 'nav.property',
    'assisi'   => 'nav.assisi',
    'info'     => 'nav.info',
    'contact'  => 'nav.contact',
];

$corrente = $paginaCorrente ?? '';
// La pagina di una camera tiene acceso «Camere»: è lì che l'ospite si trova.
$attiva = $corrente === 'room' ? 'rooms' : $corrente;

// Il parcheggio più vicino di cui si conosce la distanza.
$metri = null;
foreach ((array) site('parking_spots', []) as $posto) {
    if (!empty($posto['metres'])) {
        $metri = (int) $posto['metres'];
        break;
    }
}

$contatti = site('contacts');
?>
<div class="adv-infobar" id="inizio">
  <ul class="adv-infobar__voci">
    <li class="adv-infobar__voce"><?= icona('pin', 12) ?><?= te('nav.topbar.center') ?></li>
    <li class="adv-infobar__voce"><?= icona('chiave', 12) ?><?= te('nav.topbar.host') ?></li>
    <?php if ($metri): ?>
      <li class="adv-infobar__voce"><?= icona('parcheggio', 12) ?><?= te('nav.topbar.parking', ['metres' => $metri]) ?></li>
    <?php endif; ?>
    <li class="adv-infobar__voce"><?= icona('casa', 12) ?><?= te('nav.topbar.since') ?></li>
  </ul>
</div>

<header class="adv-testata" data-testata>
  <span class="adv-testata__traccia" aria-hidden="true"><span class="adv-testata__progresso" data-progresso></span></span>

  <div class="adv-testata__sinistra">
    <button class="adv-tondo adv-menu__apri" type="button"
            aria-expanded="false" aria-controls="menu-mobile"
            aria-label="<?= te('common.menu_open') ?>" data-menu-apri>
      <?= icona('menu', 20) ?>
    </button>

    <nav class="adv-nav" aria-label="<?= te('nav.label') ?>">
      <ul class="adv-nav__voci">
        <?php foreach ($voci as $chiave => $etichetta): ?>
          <li>
            <a class="adv-nav__link" href="<?= e(url($chiave)) ?>"<?= $attiva === $chiave ? ' aria-current="page"' : '' ?>><?= te($etichetta) ?></a>
          </li>
        <?php endforeach; ?>
      </ul>
    </nav>
  </div>

  <?php /* Il marchio intero al centro: la rosa dei venti e il logotipo. */ ?>
  <?= component('logo') ?>

  <div class="adv-testata__azioni">
    <?= partial('language-switcher', ['alternative' => $alternative]) ?>

    <a class="adv-btn adv-btn--primario adv-testata__prenota" href="<?= e(url('book')) ?>">
      <?= te('cta.book') ?><?= icona('freccia-su-destra', 14) ?>
    </a>
  </div>
</header>

<?php /* Le stesse voci, in chiaro, per chi non ha JavaScript: su schermo
         stretto il pulsante del menu non c'è, e queste prendono il suo
         posto. Il foglio le spegne appena abilita-js.js segna che il
         JavaScript c'è. */ ?>
<nav class="adv-nav-riserva" aria-label="<?= te('nav.label') ?>">
  <ul class="adv-nav-riserva__voci">
    <?php foreach ($voci as $chiave => $etichetta): ?>
      <li>
        <a class="adv-nav__link" href="<?= e(url($chiave)) ?>"<?= $attiva === $chiave ? ' aria-current="page"' : '' ?>><?= te($etichetta) ?></a>
      </li>
    <?php endforeach; ?>
    <li><a class="adv-nav__link" href="<?= e(url('book')) ?>"><?= te('cta.book') ?></a></li>
  </ul>
</nav>

<?php /* Il menu su schermo stretto: le pagine come un indice numerato, in
         grande, e sotto il pulsante per prenotare, i contatti e la lingua. */ ?>
<div class="adv-menu" id="menu-mobile" hidden data-menu>
  <div class="adv-menu__interno">

    <div class="adv-menu__testa">
      <?= component('logo', ['classe' => 'adv-marchio adv-marchio--menu']) ?>
      <button class="adv-tondo" type="button" aria-label="<?= te('common.menu_close') ?>" data-menu-chiudi>
        <?= icona('chiudi', 20) ?>
      </button>
    </div>

    <nav aria-label="<?= te('nav.label') ?>">
      <ol class="adv-menu__voci">
        <?php $n = 0; foreach ($voci as $chiave => $etichetta): $n++; ?>
          <li class="adv-menu__voce">
            <a class="adv-menu__link" href="<?= e(url($chiave)) ?>"<?= $attiva === $chiave ? ' aria-current="page"' : '' ?>>
              <span class="adv-menu__numero" aria-hidden="true"><?= sprintf('%02d', $n) ?></span><?= te($etichetta) ?>
            </a>
          </li>
        <?php endforeach; ?>
      </ol>
    </nav>

    <div class="adv-menu__piede">
      <a class="adv-btn adv-btn--primario adv-btn--grande adv-btn--pieno" href="<?= e(url('book')) ?>">
        <?= te('cta.book') ?><?= icona('freccia-su-destra', 14) ?>
      </a>
      <p class="adv-menu__contatti">
        <?= component('contact-line', ['tipo' => 'phone', 'valore' => $contatti['phone'] ?? null]) ?>
        <?= component('contact-line', ['tipo' => 'email', 'valore' => $contatti['email'] ?? null]) ?>
      </p>
      <?= partial('language-switcher', ['alternative' => $alternative, 'id' => 'menu']) ?>
    </div>
  </div>
</div>
