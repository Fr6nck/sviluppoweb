<?php
/** Libreria immagini: caricamento, testo alternativo, eliminazione con avviso se in uso. */
declare(strict_types=1);

require dirname(__DIR__) . '/app/admin.php';

$user = require_role('editor');
$errors = [];
$confirmDelete = null;

if (is_post()) {
    csrf_check();
    $action = post('azione');
    if ($action === 'carica') {
        [$newId, $err] = media_upload($_FILES['file'] ?? [], post('alt'), (int) $user['id']);
        if ($err !== null) {
            $errors[str_contains($err, 'alternativo') ? 'alt' : 'file'] = $err;
            flash('error', 'Immagine non caricata: ' . $err);
        } else {
            audit('media_caricato', '#' . $newId);
            flash('ok', 'Immagine caricata e ottimizzata.');
            redirect('media.php');
        }
    } elseif ($action === 'alt') {
        $id = (int) post('id');
        $alt = clean_text(post('alt'), 255);
        if ($alt === '') {
            flash('error', 'Il testo alternativo non può essere vuoto.');
        } elseif (media_get($id)) {
            q('UPDATE ' . t('media') . ' SET alt = ? WHERE id = ?', [$alt, $id]);
            flash('ok', 'Testo alternativo aggiornato. Pubblica di nuovo il sito se l\'immagine è già in uso.');
        }
        redirect('media.php');
    } elseif ($action === 'elimina') {
        $id = (int) post('id');
        $m = media_get($id);
        if ($m) {
            $uses = media_usages($id);
            if ($uses && post('conferma') !== '1') {
                $confirmDelete = ['media' => $m, 'uses' => $uses];
            } else {
                media_delete($id);
                audit('media_eliminato', '#' . $id . ($uses ? ' (era in uso)' : ''));
                if ($uses) {
                    site_rebuild();
                }
                flash('ok', 'Immagine eliminata.' . ($uses ? ' Dove era usata ora compare l\'illustrazione o l\'immagine predefinita.' : ''));
                redirect('media.php');
            }
        }
    }
}

admin_header('Media', 'media.php');

if ($confirmDelete): ?>
  <section class="notice notice-warn" role="alert">
    <h2>Questa immagine è in uso</h2>
    <p>L'immagine "<?= e($confirmDelete['media']['alt']) ?>" è usata in:</p>
    <ul><?php foreach ($confirmDelete['uses'] as $u): ?><li><?= e($u) ?></li><?php endforeach; ?></ul>
    <p>Se la elimini, in quei punti il sito userà l'illustrazione o l'immagine predefinita.</p>
    <form method="post" action="media.php" class="actions-bar">
      <?= csrf_field() ?>
      <input type="hidden" name="azione" value="elimina">
      <input type="hidden" name="id" value="<?= (int) $confirmDelete['media']['id'] ?>">
      <input type="hidden" name="conferma" value="1">
      <button type="submit" class="btn btn-danger">Elimina comunque</button>
      <a class="btn btn-line" href="media.php">Annulla</a>
    </form>
  </section>
<?php endif; ?>

<form method="post" action="media.php" enctype="multipart/form-data" class="form panel" novalidate>
  <?= csrf_field() ?>
  <input type="hidden" name="azione" value="carica">
  <input type="hidden" name="MAX_FILE_SIZE" value="<?= CM_MEDIA_MAX_BYTES ?>">
  <h2>Carica un'immagine</h2>
  <div class="field">
    <label for="f-file">File immagine <span class="req" aria-hidden="true">*</span></label>
    <input type="file" id="f-file" name="file" accept="image/jpeg,image/png,image/webp" required aria-describedby="f-file-hint<?= isset($errors['file']) ? ' f-file-err' : '' ?>"<?= isset($errors['file']) ? ' aria-invalid="true"' : '' ?>>
    <p class="hint" id="f-file-hint">JPG, PNG o WebP fino a 5 MB. L'immagine viene ridimensionata a 1600 px e salvata anche in WebP. Gli SVG non sono ammessi.</p>
    <?php if (isset($errors['file'])): ?><p class="field-error" id="f-file-err"><?= e($errors['file']) ?></p><?php endif; ?>
  </div>
  <?= field('alt', 'Testo alternativo', post('alt'), ['required' => true, 'rec' => 120, 'hint' => 'Descrivi cosa mostra l\'immagine, per chi non può vederla. Obbligatorio.', 'error' => $errors['alt'] ?? '']) ?>
  <div class="actions-bar"><button type="submit" class="btn btn-dark">Carica</button></div>
</form>

<h2>Libreria</h2>
<?php $all = media_all(); if (!$all): ?>
  <p>Non ci sono ancora immagini caricate.</p>
<?php else: ?>
  <ul class="media-grid">
    <?php foreach ($all as $m): $uses = media_usages((int) $m['id']); ?>
      <li class="media-card">
        <img src="../uploads/<?= e($m['webp'] ?: $m['file']) ?>" alt="<?= e($m['alt']) ?>" loading="lazy" width="<?= (int) $m['width'] ?>" height="<?= (int) $m['height'] ?>">
        <div class="meta">
          <strong>#<?= (int) $m['id'] ?> · <?= e($m['alt']) ?></strong>
          <?= (int) $m['width'] ?> × <?= (int) $m['height'] ?> px · <?= e(number_format($m['size'] / 1024, 0, ',', '.')) ?> KB<br>
          <?= $uses ? 'In uso: ' . e(implode('; ', $uses)) : 'Non in uso' ?>
          <form method="post" action="media.php" class="form">
            <?= csrf_field() ?>
            <input type="hidden" name="azione" value="alt">
            <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
            <?= field('alt', 'Testo alternativo', $m['alt'], ['id' => 'alt-' . (int) $m['id']]) ?>
            <button type="submit" class="btn btn-line btn-small">Salva testo</button>
          </form>
          <form method="post" action="media.php">
            <?= csrf_field() ?>
            <input type="hidden" name="azione" value="elimina">
            <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
            <button type="submit" class="btn btn-danger btn-small"<?= $uses ? '' : ' data-confirm="Eliminare questa immagine?"' ?>>Elimina</button>
          </form>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif;
admin_footer();
