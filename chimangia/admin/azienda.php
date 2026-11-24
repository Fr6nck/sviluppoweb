<?php
/** Dati aziendali e contatti mostrati nel sito. */
declare(strict_types=1);

require dirname(__DIR__) . '/app/admin.php';

$user = require_role('editor');
$schema = ['title' => 'Dati aziendali', 'groups' => [
    ['Azienda', [
        f('company_name', 'Ragione sociale', 'text'),
        f('address', 'Indirizzo', 'text'),
        f('vat_number', 'Partita IVA', 'text'),
    ]],
    ['Contatti mostrati nel sito', [
        f('email', 'Email', 'email', ['hint' => 'Compare nel pulsante "Scrivici" della sezione contatti.']),
        f('phone', 'Telefono', 'text', ['hint' => 'Compare nel pulsante "Chiamaci". Scrivilo come vuoi che si legga, es. +39 392 006 1600.']),
    ]],
    ['Link social (facoltativi)', [
        f('facebook', 'Facebook', 'text', ['hint' => 'Indirizzo completo https://…; lascia vuoto per non mostrarlo.']),
        f('instagram', 'Instagram', 'text'),
        f('linkedin', 'LinkedIn', 'text'),
        f('youtube', 'YouTube', 'text'),
    ]],
    ['Piè di pagina', [
        f('privacy_label', 'Testo del link alla privacy', 'text'),
        f('cookie_label', 'Testo del link ai cookie', 'text'),
    ]],
]];

$data = content_get('azienda', 'draft');
$errors = [];
if (is_post()) {
    csrf_check();
    $data = editor_collect($schema, $data, $_POST, $errors);
    if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL) && !str_contains($data['email'], '[')) {
        $errors['email'] = 'Indirizzo email non valido.';
    }
    if ($data['vat_number'] !== '' && !preg_match('/^(IT)?\s?\d{11}$/i', $data['vat_number'])) {
        $errors['vat_number'] = 'La partita IVA italiana ha 11 cifre.';
    }
    foreach (['facebook', 'instagram', 'linkedin', 'youtube'] as $k) {
        if ($data[$k] !== '' && !preg_match('#^https://[^\s"<>]+$#i', $data[$k])) {
            $errors[$k] = 'Scrivi l\'indirizzo completo che inizia con https://';
        }
    }
    editor_save('azienda', $data, $errors, $user, 'azienda.php');
}

admin_header('Dati aziendali', 'azienda.php');
echo editor_status_html('azienda');
if (str_contains((string) $data['email'], '[')): ?>
  <div class="notice notice-warn" role="status">L'email è ancora un segnaposto: sostituiscila con l'indirizzo vero prima di pubblicare il sito.</div>
<?php endif; ?>
<p class="hint">Valori iniziali da verificare prima della messa online.</p>
<form method="post" action="azienda.php" class="form" novalidate data-dirty-check>
  <?= csrf_field() ?>
  <?= editor_groups_html($schema, $data, $errors) ?>
  <?= edit_actions() ?>
</form>
<?php
admin_footer();
