<?php
/** Super admin · Registro: tutto ciò che accade nella piattaforma, locale per locale. */
declare(strict_types=1);

require dirname(__DIR__) . '/app/platform/core.php';
require_once CM_APP . '/platform/maint.php';
pf_boot('super', '../');
require __DIR__ . '/_guard.php';

$venueParam = get_param('locale');
$text = clean_text(get_param('q'), 80);
$page = max(1, (int) get_param('p', '1'));
$per = 50;
$where = ['1 = 1'];
$args = [];
if ($venueParam === '0') {
    $where[] = 'e.venue_id IS NULL';
} elseif (ctype_digit($venueParam) && (int) $venueParam > 0) {
    $where[] = 'e.venue_id = ?';
    $args[] = (int) $venueParam;
} else {
    $venueParam = '';
}
if ($text !== '') {
    $like = '%' . addcslashes($text, '%_\\') . '%';
    $where[] = '(e.action LIKE ? OR e.details LIKE ? OR e.actor_name LIKE ?)';
    array_push($args, '%' . addcslashes(str_replace(' ', '_', $text), '%\\') . '%', $like, $like);
}
$sqlWhere = implode(' AND ', $where);
$total = (int) q_value('SELECT COUNT(*) FROM ' . t('events') . ' e WHERE ' . $sqlWhere, $args);
$pages = max(1, (int) ceil($total / $per));
$page = min($page, $pages);
$rows = q(
    'SELECT e.*, v.name AS venue FROM ' . t('events') . ' e LEFT JOIN ' . t('venues') . ' v ON v.id = e.venue_id
     WHERE ' . $sqlWhere . ' ORDER BY e.id DESC LIMIT ' . $per . ' OFFSET ' . (($page - 1) * $per),
    $args
)->fetchAll();
$venues = q('SELECT id, name FROM ' . t('venues') . ' ORDER BY name')->fetchAll();
$qs = static fn (int $p): string => 'registro.php?' . http_build_query(array_filter(['locale' => $venueParam, 'q' => $text, 'p' => $p > 1 ? $p : null], static fn ($x) => $x !== null && $x !== ''));

ui_super_start('Registro della *piattaforma*', 'registro.php', ['eyebrow' => $total . ($total === 1 ? ' evento' : ' eventi'), 'lead' => 'Accessi, registrazioni, cambi di stato, funzioni e permessi: chi ha fatto cosa e quando. Gli eventi restano per circa 13 mesi.']);
?>
<section class="card card-white">
  <form method="get" action="registro.php" class="row filters" role="search">
    <label class="sr-only" for="locale">Locale</label>
    <select class="grow" id="locale" name="locale" data-autosubmit>
      <option value="">Tutti i locali</option>
      <option value="0"<?= $venueParam === '0' ? ' selected' : '' ?>>Solo la piattaforma</option>
      <?php foreach ($venues as $ven): ?>
      <option value="<?= (int) $ven['id'] ?>"<?= $venueParam === (string) $ven['id'] ? ' selected' : '' ?>><?= e($ven['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <label class="sr-only" for="q">Cerca nel registro</label>
    <input class="grow" type="search" id="q" name="q" value="<?= e($text) ?>" placeholder="Cerca per azione, dettagli o persona">
    <button type="submit" class="btn btn-line"><?= icon('search', 18) ?>Cerca</button>
    <?php if ($text !== '' || $venueParam !== ''): ?><a class="btn btn-small btn-line" href="registro.php">Togli i filtri</a><?php endif; ?>
  </form>
  <?php if (!$rows): ?>
    <?= ui_empty('Nessun evento con questi filtri.', 'history') ?>
  <?php else: ?>
  <div class="table-wrap">
    <table class="table stack-sm">
      <thead><tr><th scope="col">Quando</th><th scope="col">Locale</th><th scope="col">Chi</th><th scope="col">Azione</th><th scope="col">Dettagli</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $ev): ?>
        <tr>
          <td data-th="Quando" class="nowrap"><?= e(data_it((string) $ev['created_at'])) ?></td>
          <td data-th="Locale"><?php if ($ev['venue_id'] !== null && $ev['venue'] !== null): ?><a href="cliente.php?id=<?= (int) $ev['venue_id'] ?>"><?= e($ev['venue']) ?></a><?php else: ?><span class="muted"><?= $ev['venue_id'] === null ? 'Piattaforma' : 'Locale eliminato' ?></span><?php endif; ?></td>
          <td data-th="Chi"><?= e(super_actor_label($ev)) ?></td>
          <td data-th="Azione"><?= e(super_action_label((string) $ev['action'])) ?></td>
          <td data-th="Dettagli"><?= e((string) $ev['details']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <nav class="pager" aria-label="Pagine">
    <span>Pagina <?= $page ?> di <?= $pages ?></span>
    <span class="row">
      <?php if ($page > 1): ?><a class="btn btn-small btn-line" href="<?= e($qs($page - 1)) ?>"><?= icon('chev-left', 16) ?>Precedente</a><?php endif; ?>
      <?php if ($page < $pages): ?><a class="btn btn-small btn-line" href="<?= e($qs($page + 1)) ?>">Successiva<?= icon('chev-right', 16) ?></a><?php endif; ?>
    </span>
  </nav>
  <?php endif; ?>
</section>
<?php
ui_shell_end();
