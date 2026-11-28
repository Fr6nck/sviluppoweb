<?php
/** Super admin · Clienti: numeri d'insieme, elenco dei locali e creazione di un nuovo cliente. */
declare(strict_types=1);

require dirname(__DIR__) . '/app/platform/core.php';
require_once CM_APP . '/platform/maint.php';
pf_boot('super', '../');
require __DIR__ . '/_guard.php';

$new = ['venue' => '', 'kind' => 'ristorante', 'owner' => '', 'email' => '', 'status' => 'prova', 'hotel' => false];
$error = '';

if (is_post()) {
    pf_csrf_check();
    if (post('azione') === 'nuovo') {
        $new = [
            'venue' => clean_text(post('venue'), 160), 'kind' => post('kind'), 'owner' => clean_text(post('owner'), 120),
            'email' => mb_strtolower(post('email')), 'status' => post('status') === 'attivo' ? 'attivo' : 'prova', 'hotel' => post('hotel') === '1',
        ];
        if (mb_strlen($new['venue']) < 2) {
            $error = 'Scrivi il nome del locale.';
        } elseif (!isset(pf_kinds()[$new['kind']])) {
            $error = 'Scegli il tipo di locale.';
        } elseif (mb_strlen($new['owner']) < 2) {
            $error = 'Scrivi nome e cognome del titolare.';
        } elseif (!filter_var($new['email'], FILTER_VALIDATE_EMAIL) || strlen($new['email']) > 190) {
            $error = 'L\'indirizzo email del titolare non è valido.';
        } elseif (q_value('SELECT 1 FROM ' . t('staff') . ' WHERE email = ?', [$new['email']])) {
            $error = 'Con questa email esiste già un utente di un locale.';
        } else {
            [$venueId, $staffId] = venue_create([
                'name' => $new['venue'], 'kind' => $new['kind'], 'email' => $new['email'], 'owner_name' => $new['owner'],
                'password' => random_token(24), 'hotel' => $new['hotel'] || $new['kind'] === 'hotel',
                'status' => $new['status'], 'trial_days' => (int) setting('pf_trial_days', PF_TRIAL_DAYS),
            ]);
            // L'indirizzo lo ha inserito il fornitore: non serve la conferma via email.
            q('UPDATE ' . t('venues') . ' SET email_verified_at = ? WHERE id = ?', [now(), $venueId]);
            $venue = venue_get($venueId, true);
            $staff = q_one('SELECT * FROM ' . t('staff') . ' WHERE id = ?', [$staffId]);
            if ($venue && $staff) {
                super_welcome_send($staff, $venue);
            }
            pf_event($venueId, 'locale_creato', $new['venue'] . ' · ' . pf_kinds()[$new['kind']] . ' · dal fornitore');
            audit('piattaforma_locale_creato', $new['venue'] . ' (' . $new['email'] . ')');
            pf_flash('ok', 'Locale creato. Il titolare riceve un\'email con il collegamento per scegliere la password (vale tre giorni).');
            redirect('cliente.php?id=' . $venueId);
        }
    }
}

/* ---------- Numeri d'insieme ---------- */
$now = now();
$soon = date('Y-m-d H:i:s', time() + 3 * 86400);
$count = static fn (string $where, array $args = []): int => (int) q_value('SELECT COUNT(*) FROM ' . t('venues') . ' WHERE ' . $where, $args);
$active = $count("status = 'attivo'");
$trial = $count("status = 'prova' AND (trial_ends_at IS NULL OR trial_ends_at > ?)", [$now]);
$expiring = $count("status = 'prova' AND trial_ends_at > ? AND trial_ends_at <= ?", [$now, $soon]);
$expired = $count("status = 'prova' AND trial_ends_at IS NOT NULL AND trial_ends_at <= ?", [$now]);
$meals = (int) q_value(
    'SELECT COUNT(*) FROM ' . t('bookings') . " WHERE day BETWEEN ? AND ? AND status IN ('prenotato', 'confermato')",
    [day_add(today(), -30), today()]
);

/* ---------- Elenco con ricerca e filtro ---------- */
$text = clean_text(get_param('q'), 80);
$state = get_param('stato');
$page = max(1, (int) get_param('p', '1'));
$per = 50;
$where = ['1 = 1'];
$args = [];
if ($text !== '') {
    $like = '%' . addcslashes($text, '%_\\') . '%';
    $where[] = '(v.name LIKE ? OR v.slug LIKE ? OR v.email LIKE ?)';
    array_push($args, $like, $like, $like);
}
if (isset(pf_statuses()[$state])) {
    $where[] = 'v.status = ?';
    $args[] = $state;
} elseif ($state === 'scadute') {
    $where[] = "v.status = 'prova' AND v.trial_ends_at IS NOT NULL AND v.trial_ends_at <= ?";
    $args[] = $now;
} else {
    $state = '';
}
$sqlWhere = implode(' AND ', $where);
$total = (int) q_value('SELECT COUNT(*) FROM ' . t('venues') . ' v WHERE ' . $sqlWhere, $args);
$pages = max(1, (int) ceil($total / $per));
$page = min($page, $pages);
$rows = q(
    'SELECT v.*, (SELECT COUNT(*) FROM ' . t('guests') . ' g WHERE g.venue_id = v.id) AS guests
     FROM ' . t('venues') . ' v WHERE ' . $sqlWhere . ' ORDER BY v.created_at DESC, v.id DESC LIMIT ' . $per . ' OFFSET ' . (($page - 1) * $per),
    $args
)->fetchAll();
$plans = pf_plans();
$qs = static fn (int $p): string => 'index.php?' . http_build_query(array_filter(['q' => $text, 'stato' => $state, 'p' => $p > 1 ? $p : null], static fn ($x) => $x !== null && $x !== ''));

ui_super_start('I tuoi *clienti*', 'index.php', ['eyebrow' => day_it(today(), 'full'), 'lead' => 'Ogni locale ha le sue funzioni, i suoi permessi e i suoi dati separati.']);

echo '<div class="kpis">'
    . ui_kpi('Locali attivi', $active, 'con il piano avviato', 'navy')
    . ui_kpi('In prova', $trial, $expired > 0 ? $expired . ($expired === 1 ? ' prova scaduta' : ' prove scadute') : 'nessuna prova scaduta')
    . ui_kpi('Prove in scadenza', $expiring, 'entro 3 giorni', $expiring > 0 ? 'zaff' : '')
    . ui_kpi('Pasti prenotati', number_format($meals, 0, ',', '.'), 'negli ultimi 30 giorni')
    . '</div>';
?>
<details class="card card-cream fold"<?= $error !== '' ? ' open' : '' ?>>
  <summary>Nuovo cliente</summary>
  <?php if ($error !== ''): ?><div class="notice notice-error" role="alert"><?= e($error) ?></div><?php endif; ?>
  <form method="post" action="index.php" class="form" novalidate data-once>
    <?= csrf_field() ?>
    <input type="hidden" name="azione" value="nuovo">
    <div class="form-grid">
      <?= ui_field('venue', 'Nome del locale', $new['venue'], ['required' => true, 'attrs' => 'maxlength="160"']) ?>
      <?= ui_field('kind', 'Tipo di locale', $new['kind'], ['type' => 'select', 'options' => pf_kinds()]) ?>
      <?= ui_field('owner', 'Nome e cognome del titolare', $new['owner'], ['required' => true, 'attrs' => 'maxlength="120"']) ?>
      <?= ui_field('email', 'Email del titolare', $new['email'], ['type' => 'email', 'required' => true, 'hint' => 'Riceve il collegamento per scegliere la password.']) ?>
      <?= ui_field('status', 'Parte come', $new['status'], ['type' => 'select', 'options' => ['prova' => 'In prova (' . (int) setting('pf_trial_days', PF_TRIAL_DAYS) . ' giorni)', 'attivo' => 'Attivo da subito']]) ?>
      <?= ui_switch('hotel', 'Modulo Hotel', $new['hotel'], 'Notti di pernotto e camere') ?>
    </div>
    <div class="row"><button type="submit" class="btn btn-dark"><?= icon('plus', 18) ?>Crea il locale</button></div>
  </form>
</details>

<section class="card card-white">
  <div class="card-head">
    <h2>Locali</h2>
    <p><?= $total ?> <?= $total === 1 ? 'locale' : 'locali' ?><?= $text !== '' || $state !== '' ? ' con questi filtri' : '' ?></p>
  </div>
  <form method="get" action="index.php" class="row filters" role="search">
    <label class="sr-only" for="q">Cerca per nome, codice o email</label>
    <input class="grow" type="search" id="q" name="q" value="<?= e($text) ?>" placeholder="Cerca per nome, codice o email">
    <label class="sr-only" for="stato">Stato</label>
    <select class="grow" id="stato" name="stato" data-autosubmit>
      <option value="">Tutti gli stati</option>
      <?php foreach (pf_statuses() + ['scadute' => 'Prove scadute'] as $k => $label): ?>
      <option value="<?= e($k) ?>"<?= $state === $k ? ' selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-line"><?= icon('search', 18) ?>Cerca</button>
    <?php if ($text !== '' || $state !== ''): ?><a class="btn btn-small btn-line" href="index.php">Togli i filtri</a><?php endif; ?>
  </form>
  <?php if (!$rows): ?>
    <?= ui_empty($text !== '' || $state !== '' ? 'Nessun locale corrisponde a questi filtri.' : 'Non ci sono ancora locali. Creane uno qui sopra, oppure aspetta la prima registrazione dal sito.', 'building') ?>
  <?php else: ?>
  <div class="table-wrap">
    <table class="table stack-sm">
      <thead><tr><th scope="col">Locale</th><th scope="col">Tipo</th><th scope="col">Stato</th><th scope="col">Piano</th><th scope="col">Prova</th><th scope="col" class="num">Clienti del mese</th><th scope="col" class="num">Ospiti</th><th scope="col">Creato</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $v):
          $limit = (int) ($plans[$v['plan']][1] ?? 0);
          $used = venue_usage((int) $v['id']);
          $days = venue_trial_days($v);
          ?>
        <tr>
          <td data-th="Locale"><a href="cliente.php?id=<?= (int) $v['id'] ?>"><strong><?= e($v['name']) ?></strong></a><span class="sub"><?= e($v['slug']) ?><?= $v['email_verified_at'] === null ? ' · email da confermare' : '' ?></span></td>
          <td data-th="Tipo"><?= e(pf_kinds()[$v['kind']] ?? $v['kind']) ?></td>
          <td data-th="Stato"><?= super_status_tag($v) ?></td>
          <td data-th="Piano"><?= e($plans[$v['plan']][0] ?? $v['plan']) ?><span class="sub"><?= e($v['billing']) ?></span></td>
          <td data-th="Prova"><?php if ($v['status'] === 'prova' && $v['trial_ends_at'] !== null): ?><?= e(data_it((string) $v['trial_ends_at'], false)) ?><span class="sub"><?= $days > 0 ? ($days === 1 ? 'manca 1 giorno' : 'mancano ' . $days . ' giorni') : 'scaduta' ?></span><?php else: ?>—<?php endif; ?></td>
          <td data-th="Clienti del mese" class="num"><?= $limit > 0 && $used > $limit ? ui_tag($used . ' su ' . $limit, 'err') : e($used . ' su ' . $limit) ?></td>
          <td data-th="Ospiti" class="num"><?= (int) $v['guests'] ?></td>
          <td data-th="Creato" class="nowrap"><?= e(data_it((string) $v['created_at'], false)) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pages > 1): ?>
  <nav class="pager" aria-label="Pagine">
    <span>Pagina <?= $page ?> di <?= $pages ?></span>
    <span class="row">
      <?php if ($page > 1): ?><a class="btn btn-small btn-line" href="<?= e($qs($page - 1)) ?>"><?= icon('chev-left', 16) ?>Precedente</a><?php endif; ?>
      <?php if ($page < $pages): ?><a class="btn btn-small btn-line" href="<?= e($qs($page + 1)) ?>">Successiva<?= icon('chev-right', 16) ?></a><?php endif; ?>
    </span>
  </nav>
  <?php endif; ?>
  <?php endif; ?>
</section>
<?php
ui_shell_end();
