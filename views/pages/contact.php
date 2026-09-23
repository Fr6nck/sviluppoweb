<?php
/**
 * I contatti.
 *
 * Il modulo funziona davvero: convalida lato server, messaggi che dicono cosa
 * fare, e Post/Redirect/Get dopo l'invio, così ricaricare non rispedisce.
 *
 * Finché il numero e l'indirizzo e-mail non sono confermati, il sito non
 * inventa un link: mostra il marcatore. Il modulo, invece, c'è e funziona —
 * è la ragione per cui una pagina contatti esiste.
 *
 * @var bool  $inviato
 * @var array $errori
 * @var array $valori
 */

use ArcoDelVento\Support\Csrf;

$contatti = site('contacts');
$errori   = $errori ?? [];
$valori   = $valori ?? [];

$errore = static fn (string $campo): ?string => isset($errori[$campo]) ? t('errors.' . $errori[$campo]) : null;
$valore = static fn (string $campo, string $default = ''): string => (string) ($valori[$campo] ?? $default);
?>

<div class="adv-contenuto">
  <header class="adv-heroT">
    <span class="adv-heroT__occhiello"><?= te('contact.eyebrow') ?></span>
    <h1 class="adv-heroT__titolo">
      <?= te('contact.title') ?> <span class="adv-firma-inline"><?= te('contact.sign') ?></span>
    </h1>
    <p class="adv-heroT__spalla"><?= te('contact.lead') ?></p>
  </header>
</div>

<section class="adv-contenuto adv-editoriale">
  <div class="adv-split">

    <div>
      <?php if ($inviato): ?>
        <?= component('alert', [
            'tipo'   => 'successo',
            'titolo' => t('contact.success_title'),
            'testo'  => t('contact.success_text'),
        ]) ?>
        <?php if (\ArcoDelVento\App::instance()->mailer()->isPretend()): ?>
          <p class="adv-nota"><?= te('contact.demo_note') ?></p>
        <?php endif; ?>
      <?php endif; ?>

      <?php if ($errori !== []): ?>
        <?= component('alert', [
            'tipo'   => 'errore',
            'titolo' => t('contact.error_title'),
            'testo'  => $errore('_form') ?? t('contact.error_text'),
        ]) ?>
      <?php endif; ?>

      <?= component('section-header', [
          'occhiello' => t('contact.eyebrow'),
          'titolo'    => t('contact.form_title'),
          'piccolo'   => true,
          'filetto'   => false,
      ]) ?>

      <form class="adv-modulo" method="post" action="<?= e(url('contact')) ?>" novalidate>
        <?= Csrf::field() ?>

        <fieldset>
          <legend><?= te('contact.form.legend') ?></legend>
          <p class="adv-nota"><?= te('common.required_note') ?></p>

          <div class="adv-modulo__coppia">
            <?= component('field', [
                'nome' => 'nome', 'etichetta' => t('contact.form.name'),
                'valore' => $valore('nome'), 'errore' => $errore('nome'),
                'autocomplete' => 'name', 'obbligatorio' => true,
            ]) ?>
            <?= component('field', [
                'nome' => 'email', 'tipo' => 'email', 'etichetta' => t('contact.form.email'),
                'valore' => $valore('email'), 'errore' => $errore('email'),
                'autocomplete' => 'email', 'obbligatorio' => true,
            ]) ?>
          </div>

          <div class="adv-modulo__coppia">
            <?= component('field', [
                'nome' => 'telefono', 'tipo' => 'tel', 'etichetta' => t('contact.form.phone'),
                'valore' => $valore('telefono'), 'errore' => $errore('telefono'),
                'autocomplete' => 'tel', 'facoltativo' => true,
            ]) ?>
            <?= component('field', [
                'nome' => 'oggetto', 'tipo' => 'select', 'etichetta' => t('contact.form.subject'),
                'valore' => $valore('oggetto', 'info'),
                'opzioni' => tlist('contact.form.subjects'),
            ]) ?>
          </div>

          <?= component('field', [
              'nome' => 'messaggio', 'tipo' => 'textarea', 'etichetta' => t('contact.form.message'),
              'valore' => $valore('messaggio'), 'errore' => $errore('messaggio'),
              'aiuto' => t('contact.form.message_help'), 'obbligatorio' => true,
          ]) ?>

          <?php /* La trappola per i robot: chi compila davvero non la vede,
                   non la incontra tabulando e non la sente annunciata. */ ?>
          <div class="adv-esca" aria-hidden="true">
            <label for="indirizzo_2">Indirizzo 2</label>
            <input type="text" id="indirizzo_2" name="indirizzo_2" tabindex="-1" autocomplete="off">
          </div>

          <div class="adv-modulo__spunta">
            <input type="checkbox" id="privacy" name="privacy" value="1"
                   <?= $valore('privacy') !== '' ? 'checked' : '' ?>
                   <?= $errore('privacy') ? 'aria-invalid="true" aria-describedby="err-privacy"' : '' ?>>
            <label for="privacy">
              <?= te('contact.form.privacy') ?>
              <a href="<?= e(url('privacy')) ?>"><?= te('nav.privacy') ?></a>
            </label>
          </div>
          <?php if ($messaggio = $errore('privacy')): ?>
            <p class="adv-campo__errore" id="err-privacy"><span aria-hidden="true">&#9888;</span><?= e($messaggio) ?></p>
          <?php endif; ?>
        </fieldset>

        <div>
          <button class="adv-btn adv-btn--primario adv-btn--lg" type="submit"><?= te('contact.form.submit') ?></button>
        </div>
      </form>
    </div>

    <aside class="adv-split__nota">
      <h2 class="adv-titolo-md"><?= te('contact.where_title') ?></h2>
      <address class="adv-testo adv-indirizzo">
        <?= te('common.brand_full') ?><br>
        <?= e(site('address.street')) ?><br>
        <?= e(site('address.city')) ?> (<?= e(site('address.province')) ?>) &middot; <?= e(site('address.region')) ?>
      </address>

      <dl class="adv-fatti">
        <div class="adv-fatti__riga">
          <dt><?= te('contact.form.phone') ?></dt>
          <dd><?= component('contact-line', ['tipo' => 'phone', 'valore' => $contatti['phone']]) ?></dd>
        </div>
        <div class="adv-fatti__riga">
          <dt><?= te('contact.form.email') ?></dt>
          <dd><?= component('contact-line', ['tipo' => 'email', 'valore' => $contatti['email']]) ?></dd>
        </div>
        <div class="adv-fatti__riga">
          <dt><?= te('cta.whatsapp') ?></dt>
          <dd><?= component('contact-line', ['tipo' => 'whatsapp', 'valore' => $contatti['whatsapp']]) ?></dd>
        </div>
      </dl>

      <h2 class="adv-titolo-md adv-spazio-sopra"><?= te('contact.how_title') ?></h2>
      <p class="adv-testo"><?= te('assisi.moving_text') ?></p>
      <p class="adv-azione-coda">
        <a class="adv-elenco__link" href="<?= e(url('info')) ?>">
          <?= te('cta.see_info') ?><span aria-hidden="true">&rarr;</span>
        </a>
      </p>
    </aside>

  </div>
</section>
