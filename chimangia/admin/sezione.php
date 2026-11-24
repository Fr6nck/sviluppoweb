<?php
/** Modifica di una sezione del sito (testi, pulsanti, schede, Mostra/Nascondi). */
declare(strict_types=1);

require dirname(__DIR__) . '/app/admin.php';

$user = require_role('editor');
$area = get_param('s');
$schema = section_schema($area);
if ($schema === null) {
    http_response_code(404);
    admin_message_page('Sezione non trovata', 'La sezione richiesta non esiste.');
}
$self = 'sezione.php?s=' . rawurlencode($area);

$data = content_get($area, 'draft');
$errors = [];
if (is_post()) {
    csrf_check();
    $data = editor_collect($schema, $data, $_POST, $errors);
    editor_save($area, $data, $errors, $user, $self);
}

admin_header($schema['title'], $self);
echo editor_status_html($area);
?>
<form method="post" action="<?= e($self) ?>" class="form" novalidate data-dirty-check>
  <?= csrf_field() ?>
  <?= in_array($area, ['hero', 'contatti'], true) ? editor_tokens_hint() : '' ?>
  <?= editor_groups_html($schema, $data, $errors) ?>
  <?= edit_actions() ?>
</form>
<?php
admin_footer();
