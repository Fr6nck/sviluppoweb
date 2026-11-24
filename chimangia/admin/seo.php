<?php
/** SEO: title, description, immagine Open Graph, dati strutturati. */
declare(strict_types=1);

require dirname(__DIR__) . '/app/admin.php';

$user = require_role('editor');
$schema = ['title' => 'SEO', 'groups' => [
    ['Risultati di ricerca', [
        f('title', 'Titolo della pagina (title)', 'text', ['rec' => 60, 'hint' => 'Compare nella scheda del browser e come titolo nei risultati di Google.']),
        f('description', 'Descrizione (meta description)', 'textarea', ['rec' => 155]),
    ]],
    ['Condivisione sui social (Open Graph)', [
        f('og_image', 'Immagine di condivisione', 'media', ['hint' => 'Consigliata 1200 × 630 px. Se non ne scegli una si usa l\'immagine predefinita con il logo.']),
    ]],
    ['Dati strutturati (SoftwareApplication)', [
        f('app_name', 'Nome del software', 'text'),
        f('app_description', 'Descrizione del software', 'textarea', ['rec' => 200]),
        f('app_category', 'Categoria', 'text', ['hint' => 'Valore schema.org, di solito BusinessApplication.']),
    ]],
]];

$data = content_get('seo', 'draft');
$errors = [];
if (is_post()) {
    csrf_check();
    $data = editor_collect($schema, $data, $_POST, $errors);
    if (trim((string) $data['title']) === '') {
        $errors['title'] = 'Il titolo non può essere vuoto.';
    }
    editor_save('seo', $data, $errors, $user, 'seo.php');
}

admin_header('SEO', 'seo.php');
echo editor_status_html('seo');
?>
<form method="post" action="seo.php" class="form" novalidate data-dirty-check>
  <?= csrf_field() ?>
  <?= editor_groups_html($schema, $data, $errors) ?>
  <?php if ((int) $data['og_image'] > 0 && media_get((int) $data['og_image'])): ?>
    <p><img class="preview-img" src="../<?= e(media_url((int) $data['og_image'])) ?>" alt="Anteprima: <?= e(media_alt((int) $data['og_image'])) ?>"></p>
  <?php else: ?>
    <p><img class="preview-img" src="../assets/img/og-default.png" alt="Immagine di condivisione predefinita con il logo ChiMangia"></p>
  <?php endif; ?>
  <section class="panel">
    <h2>Generati in automatico</h2>
    <ul>
      <li>Le offerte nei dati strutturati vengono dai prezzi dei pacchetti visibili.</li>
      <li><a href="../sitemap.xml" target="_blank" rel="noopener">sitemap.xml</a> e <a href="../robots.txt" target="_blank" rel="noopener">robots.txt</a> si rigenerano a ogni pubblicazione.</li>
      <li>Il pannello e l'anteprima non vengono mai indicizzati (noindex).</li>
    </ul>
  </section>
  <?= edit_actions() ?>
</form>
<?php
admin_footer();
