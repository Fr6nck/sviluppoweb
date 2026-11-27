<?php
/** Domande frequenti: aggiunta, modifica, eliminazione, riordino e Mostra/Nascondi. */
declare(strict_types=1);

require dirname(__DIR__) . '/app/admin.php';

$user = require_role('editor');
$schema = ['title' => 'Domande frequenti', 'groups' => [
    ['Sezione', [
        f('visible', 'Mostra questa sezione nel sito', 'switch'),
        f('title', 'Titolo', 'text', ['rec' => 40]),
    ]],
    ['Fotografia', [
        f('photo', 'Fotografia accanto alle domande', 'photo'),
        f('photo_alt', 'Descrizione della fotografia (testo alternativo)', 'text', ['rec' => 120, 'hint' => 'Vuoto: si usa la descrizione predefinita della foto.']),
    ]],
    ['Domande', [
        ['type' => 'repeater', 'key' => 'items', 'label' => 'Domanda', 'add' => 'Aggiungi una domanda', 'max' => 30, 'fields' => [
            f('visible', 'Mostra questa domanda', 'switch'),
            f('question', 'Domanda', 'text', ['rec' => 80]),
            f('answer', 'Risposta', 'rich', ['rec' => 300]),
        ]],
    ]],
]];

$data = content_get('faq', 'draft');
$errors = [];
if (is_post()) {
    csrf_check();
    $data = editor_collect($schema, $data, $_POST, $errors);
    foreach ($data['items'] as $q) {
        if (trim((string) $q['question']) === '') {
            $errors['items.' . $q['id'] . '.question'] = 'Scrivi la domanda oppure elimina questo elemento.';
        }
    }
    editor_save('faq', $data, $errors, $user, 'faq.php');
}

admin_header('Domande frequenti', 'faq.php');
echo editor_status_html('faq');
?>
<form method="post" action="faq.php" class="form" novalidate data-dirty-check>
  <?= csrf_field() ?>
  <?= editor_groups_html($schema, $data, $errors) ?>
  <?= edit_actions() ?>
</form>
<?php
admin_footer();
