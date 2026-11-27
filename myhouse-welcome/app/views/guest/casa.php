<?php
/* 9V · «La casa»: la pagina che si apre toccando la foto di copertina. Le foto scorrono con il dito (CSS scroll-snap,
   nessuna libreria): contatore «3 / 8», puntini, frecce sul computer, tasti freccia; un tocco apre la foto a tutto
   schermo (il <dialog> delle foto delle sezioni: si chiude con Esc e con ✕). Sotto, il titolo e la descrizione, chiusa
   a 4 righe con «Leggi di più» / «Mostra meno» solo se è più lunga; senza JavaScript il testo si legge intero e le
   foto scorrono lo stesso. La prima foto si carica subito, le altre quando servono. In fondo la barra «Chiedi» /
   «Contatta». Pagina della guida: non indicizzata, senza cookie, senza prenotazioni.
   Riceve: $snap, $loc, $base, $casa (Casa::perGuida). */
use MHW\{Support, Icon, I18n, Media, Casa};
$pr = $snap['property']; $def = $pr['default_locale'];
$title = $casa['titolo'] . ' — ' . $pr['name'];
$coda = '/casa'; $indietro = true; $indietroTesto = true;
$n = $casa['n']; ?>

<?php include __DIR__ . '/_top.php'; ?>

<?php if ($n): ?>
<section class="casa-slide" data-casa-slide aria-roledescription="carousel" aria-label="<?= Support::e(I18n::t($loc, 'casa_gallery')) ?>">
  <div class="casa-slide__pista" data-casa-pista tabindex="0">
    <?php foreach ($casa['foto'] as $i => $f):
          $piena = Media::url((int) $f['id']); if (!$piena) continue;
          $m = Media::row((int) $f['id']) ?? []; $w = (int) ($m['width'] ?? 0); $h = (int) ($m['height'] ?? 0);
          $mini = Media::ridotta((int) $f['id'], Casa::MINIATURA);
          $alt = Casa::alt($f, $i + 1, $n, (string) $pr['name'], $loc); ?>
      <figure class="casa-slide__foto" data-casa-foto role="group" aria-roledescription="slide" aria-label="<?= Support::e(I18n::t($loc, 'casa_counter', $i + 1, $n)) ?>">
        <a href="<?= Support::e($piena) ?>" data-zoom aria-label="<?= Support::e(I18n::t($loc, 'casa_open')) ?>">
          <img src="<?= Support::e($piena) ?>"<?php if ($mini && $w > Casa::MINIATURA): ?> srcset="<?= Support::e($mini) ?> <?= Casa::MINIATURA ?>w, <?= Support::e($piena) ?> <?= $w ?>w" sizes="(min-width: 430px) 380px, 90vw"<?php endif; ?>
               alt="<?= Support::e($alt) ?>"<?= $w && $h ? ' width="' . $w . '" height="' . $h . '"' : '' ?> <?= $i === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?> decoding="async"></a>
      </figure>
    <?php endforeach; ?>
  </div>
  <?php if ($n > 1): ?>
    <span class="casa-slide__conta" data-casa-conta aria-hidden="true" hidden>1 / <?= $n ?></span>
    <button type="button" class="casa-slide__freccia casa-slide__freccia--prima" data-casa-prima aria-label="<?= Support::e(I18n::t($loc, 'casa_prev')) ?>" hidden><?= Icon::svg('back', 20, 2.2) ?></button>
    <button type="button" class="casa-slide__freccia casa-slide__freccia--dopo" data-casa-dopo aria-label="<?= Support::e(I18n::t($loc, 'casa_next')) ?>" hidden><?= Icon::svg('chevron', 20, 2.2) ?></button>
    <div class="casa-slide__puntini" data-casa-puntini aria-hidden="true" hidden><?php for ($i = 0; $i < $n; $i++): ?><i<?= $i === 0 ? ' class="on"' : '' ?>></i><?php endfor; ?></div>
    <p class="sr-only" data-casa-voce aria-live="polite" data-testo="<?= Support::e(str_replace('987654', '{i}', I18n::t($loc, 'casa_counter', 987654, $n))) ?>"></p>
  <?php endif; ?>
</section>
<?php endif; ?>

<h1 class="guest-title casa-titolo"><?= Support::e($casa['titolo']) ?></h1>
<?php if ($casa['descrizione'] !== ''): ?>
  <script>document.documentElement.classList.add('js')</script>
  <div class="casa-testo" data-casa-testo>
    <p class="casa-testo__p" id="casa-descrizione"><?= nl2br(Support::e($casa['descrizione'])) ?></p>
    <button type="button" class="casa-testo__piu" data-casa-piu aria-expanded="false" aria-controls="casa-descrizione" hidden
            data-piu="<?= Support::e(I18n::t($loc, 'casa_more')) ?>" data-meno="<?= Support::e(I18n::t($loc, 'casa_less')) ?>"><?= Support::e(I18n::t($loc, 'casa_more')) ?></button>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/_contatti.php'; ?>
