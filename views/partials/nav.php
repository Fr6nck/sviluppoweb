<?php
/**
 * La barra di navigazione, variante allineata a sinistra.
 *
 * Il design system ha anche una variante a marchio centrato, ma il suo README
 * è esplicito: quella serve quando la barra apre una pagina sola. Qui le
 * pagine sono otto, e allora il marchio deve riportare a casa dal solito
 * angolo.
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
$attiva   = $corrente === 'room' ? 'rooms' : $corrente;
?>
<header class="adv-testata">
  <div class="adv-contenuto adv-testata__interno">

    <a class="adv-nav__marchio" href="<?= e(url('home')) ?>">
      <?php /* Il nome è già scritto accanto: l'alt vuoto evita che chi usa uno
               screen reader senta «Arco del Vento Arco del Vento Assisi». */ ?>
      <?php /* 96px per uno slot da 46: l'icona a 192 pesava 78 KB su ogni
               pagina del sito per riempirne quarantasei. */ ?>
      <img src="<?= e(asset('img/logo/icona-96.png')) ?>"
           srcset="<?= e(asset('img/logo/icona-48.png')) ?> 1x, <?= e(asset('img/logo/icona-96.png')) ?> 2x"
           width="46" height="46" alt="" loading="eager" decoding="async">
      <span class="adv-nav__nome"><?= te('common.brand') ?><small>ASSISI</small></span>
    </a>

    <nav class="adv-nav adv-nav--principale" aria-label="<?= te('nav.label') ?>">
      <ul class="adv-nav__voci">
        <?php foreach ($voci as $chiave => $etichetta): ?>
          <li>
            <a class="adv-nav__link" href="<?= e(url($chiave)) ?>"<?= $attiva === $chiave ? ' aria-current="page"' : '' ?>>
              <?= te($etichetta) ?>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    </nav>

    <div class="adv-testata__azioni">
      <?= partial('language-switcher', ['alternative' => $alternative]) ?>

      <a class="adv-btn adv-btn--primario adv-btn--sm adv-testata__prenota" href="<?= e(url('book')) ?>">
        <?= te('cta.book') ?>
      </a>

      <button class="adv-tondo adv-menu__apri" type="button"
              aria-expanded="false" aria-controls="menu-mobile"
              aria-label="<?= te('common.menu_open') ?>" data-menu-apri>
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="2" stroke-linecap="round" aria-hidden="true">
          <path d="M4 7h16M4 12h16M4 17h16"/>
        </svg>
      </button>
    </div>
  </div>
</header>

<?php /* Le stesse voci, in chiaro, per chi non ha JavaScript. Stanno nel
         markup di tutti ma le vede solo chi ne ha bisogno: il foglio le
         spegne appena abilita-js.js segna che il JavaScript c'è. */ ?>
<nav class="adv-nav-riserva" aria-label="<?= te('nav.label') ?>">
  <div class="adv-contenuto">
    <ul class="adv-nav-riserva__voci">
      <?php foreach ($voci as $chiave => $etichetta): ?>
        <li>
          <a class="adv-nav__link" href="<?= e(url($chiave)) ?>"<?= $attiva === $chiave ? ' aria-current="page"' : '' ?>>
            <?= te($etichetta) ?>
          </a>
        </li>
      <?php endforeach; ?>
      <li>
        <a class="adv-nav__link" href="<?= e(url('book')) ?>"><?= te('cta.book') ?></a>
      </li>
    </ul>
  </div>
</nav>

<?php
/* Il pannello di navigazione su schermo stretto. Il design system non porta
   un menu mobile: porta l'«Indice», e dice che la navigazione a tutta pagina
   è il suo uso originale. Quindi il menu è un indice numerato, non una pila
   di voci in un cassetto. */ ?>
<div class="adv-menu" id="menu-mobile" hidden data-menu>
  <div class="adv-contenuto adv-menu__interno">

    <div class="adv-menu__testa">
      <span class="adv-sezione__occhiello"><?= te('common.menu') ?></span>
      <button class="adv-tondo" type="button" aria-label="<?= te('common.menu_close') ?>" data-menu-chiudi>
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="2" stroke-linecap="round" aria-hidden="true">
          <path d="M6 6l12 12M18 6L6 18"/>
        </svg>
      </button>
    </div>

    <nav aria-label="<?= te('nav.label') ?>">
      <ol class="adv-indice">
        <?php $n = 0; foreach ($voci as $chiave => $etichetta): $n++; ?>
          <li class="adv-indice__voce">
            <a class="adv-indice__link" href="<?= e(url($chiave)) ?>"<?= $attiva === $chiave ? ' aria-current="page"' : '' ?>>
              <span class="adv-indice__numero"><?= sprintf('%02d', $n) ?></span>
              <span class="adv-indice__etichetta"><?= te($etichetta) ?></span>
            </a>
          </li>
        <?php endforeach; ?>
      </ol>
    </nav>

    <div class="adv-menu__piede">
      <a class="adv-btn adv-btn--primario adv-btn--pieno" href="<?= e(url('book')) ?>"><?= te('cta.book') ?></a>
      <?= partial('language-switcher', ['alternative' => $alternative, 'id' => 'menu']) ?>
    </div>
  </div>
</div>
