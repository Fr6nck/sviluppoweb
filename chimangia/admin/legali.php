<?php
/** Pagine legali: Privacy e Cookie. */
declare(strict_types=1);

require dirname(__DIR__) . '/app/admin.php';

$user = require_role('editor');
$area = get_param('p') === 'cookie' ? 'cookie' : 'privacy';
$label = $area === 'privacy' ? 'Pagina Privacy' : 'Pagina Cookie';
$self = 'legali.php?p=' . $area;
$schema = ['title' => $label, 'groups' => [
    ['Contenuto', [
        f('title', 'Titolo della pagina', 'text', ['rec' => 50]),
        f('body', 'Testo', 'rich', ['hint' => 'Per i sottotitoli usa il grassetto su una riga a sé.']),
    ]],
]];

$data = content_get($area, 'draft');
$errors = [];
if (is_post()) {
    csrf_check();
    $data = editor_collect($schema, $data, $_POST, $errors);
    if (trim((string) $data['title']) === '') {
        $errors['title'] = 'Il titolo non può essere vuoto.';
    }
    editor_save($area, $data, $errors, $user, $self, $area);
}

admin_header($label, $self);
echo editor_status_html($area);
if (str_contains((string) $data['body'], 'DA COMPLETARE')): ?>
  <div class="notice notice-warn" role="status">Il testo è ancora il segnaposto iniziale: va completato (o fatto verificare dal consulente) prima della messa online.</div>
<?php endif; ?>
<p class="hint">Indirizzo pubblico: <a href="../<?= e($area) ?>/" target="_blank" rel="noopener"><?= e(site_url($area . '/')) ?></a></p>
<form method="post" action="<?= e($self) ?>" class="form" novalidate data-dirty-check>
  <?= csrf_field() ?>
  <?= editor_groups_html($schema, $data, $errors) ?>
  <?= edit_actions() ?>
</form>
<?php
admin_footer();
