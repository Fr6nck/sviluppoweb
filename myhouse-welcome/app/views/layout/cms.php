<?php
/* Il telaio del pannello: area host e amministrazione.
   A sinistra la barra scura con le voci dell'account e, se si sta lavorando su
   una struttura, il gruppo della guida (Contenuti, Lingue, Aspetto, QR & Link,
   Statistiche, Impostazioni). A destra la tela chiara: in alto dove sei e le
   azioni della guida (Anteprima, Pubblica), sotto la pagina.
   Sul telefono la barra diventa un cassetto (un <details>: si apre anche senza
   JavaScript) e le voci della guida una fila di schede che scorre.
   Senza utente, o durante la procedura di una guida mai pubblicata, niente
   barra: solo il marchio, per non distrarre. */
use function MHW\a; use function MHW\b;
use MHW\{Auth, Support, Csrf, Entitlements, Icon};
$u = Auth::user(); $f = Support::flash();
$admin = ($u['role'] ?? '') === 'admin' && !Auth::isImpersonating();
$iniziale = mb_strtoupper(mb_substr((string) ($u['name'] ?: $u['email'] ?? '?'), 0, 1));
$prop = $prop ?? null; $qui = $qui ?? ''; $nav = $nav ?? '';
$vedeStatistiche = $prop && Entitlements::can((int) $prop['account_id'], 'analytics');
// Durante la procedura di una guida mai pubblicata si vede una sola navigazione:
// i passi. Niente tab della struttura, e la verifica email diventa una riga nei passi.
$soloPassi = $prop && $qui === 'procedura' && $prop['status'] !== 'published';
$conLato = $u && !$soloPassi;

// Le voci, una volta sola: servono alla barra e al cassetto del telefono.
$generale = $admin
  ? [['admin', '/admin', 'Quadro', 'grid'], ['prospetti', '/admin/prospetti', 'Prospetti', 'chart'], ['anomalie', '/admin/anomalie', 'Anomalie', 'warning'],
     ['clienti', '/admin/clienti', 'Clienti', 'people'], ['abbonamenti', '/admin/abbonamenti', 'Abbonamenti', 'card'], ['scadenze', '/admin/scadenze', 'Scadenze', 'calendar'],
     ['guide', '/admin/guide', 'Guide', 'book'], ['pacchetti', '/admin/pacchetti', 'Piani', 'layers'],
     ['sconti', '/admin/sconti', 'Codici sconto', 'euro'],
     ['traduzioni', '/admin/traduzioni', 'Traduzioni', 'globe'], ['testimonianze', '/admin/testimonianze', 'Testimonianze', 'star'],
     ['impostazioni', '/admin/impostazioni', 'Impostazioni', 'key'],
     ['registro', '/admin/registro', 'Registro', 'list'], ['diagnostica', '/admin/diagnostica', 'Diagnostica', 'pulse']]
  : [['guide', '/pannello', 'Le mie guide', 'grid'], ['account', '/account', 'Account & Fatturazione', 'card']];
// Invita un amico: la voce c'è solo per chi può invitare; in amministrazione, solo se gli inviti sono accesi.
if ($admin && MHW\Inviti::disponibili()) array_splice($generale, 9, 0, [['inviti', '/admin/inviti', 'Inviti', 'message']]);
// Per i clienti la voce c'è sempre, con gli inviti accesi: chi non può ancora invitare vede il motivo.
if (!$admin && $u && Auth::account() && MHW\Inviti::disponibili()) $generale[] = ['inviti', '/inviti', 'Invita un amico', 'people'];
$attiva = $admin ? $nav : ($prop ? '' : ($nav ?: 'guide'));
$guida = [];
if ($prop) {
    $pid = (int) $prop['id'];
    $guida = [['contenuti', "/pannello/$pid", 'Contenuti', 'doc'], ['lingue', "/pannello/$pid/lingue", 'Lingue', 'globe'],
              ['aspetto', "/pannello/$pid/aspetto", 'Aspetto', 'palette'], ['qr', "/pannello/$pid/qr", 'QR & Link', 'qr']];
    // Varianti camera: con i piani che le permettono, o se la guida ne ha già.
    if (MHW\Varianti::permesse((int) $prop['account_id']) || MHW\Varianti::diStruttura($pid)) $guida[] = ['varianti', "/pannello/$pid/varianti", 'Varianti camera', 'key'];
    if ($vedeStatistiche) $guida[] = ['statistiche', "/pannello/$pid/statistiche", 'Statistiche', 'chart'];
    $guida[] = ['impostazioni', "/pannello/$pid/impostazioni", 'Impostazioni', 'sliders'];
}
$quiGuida = $qui === 'procedura' ? 'contenuti' : $qui;
$statoGuida = !$prop ? null : ($prop['status'] !== 'published' ? ['ochre', 'Bozza'] : ['pine', 'Pubblicata']);

// Dove sei: le briciole in alto nella tela.
$briciole = [];
if ($admin) {
    $briciole[] = ['Amministrazione', $nav === 'admin' ? null : '/admin'];
    foreach ($generale as [$k, , $l]) if ($k === $nav && $k !== 'admin') $briciole[] = [$l, null];
} elseif ($u) {
    $briciole[] = [['account' => 'Account & Fatturazione', 'inviti' => 'Invita un amico'][$nav] ?? 'Le mie guide', $prop ? '/pannello' : null];
    if ($prop) {
        $briciole[] = [$prop['name'], null];
        foreach ($guida as [$k, , $l]) if ($k === $quiGuida) $briciole[] = [$l, null];
        if ($qui === 'procedura') $briciole[] = ['Configurazione', null];
    }
}

$voci = function (array $elenco, string $on, string $classe) {
    foreach ($elenco as [$k, $href, $l, $ico]) {
        $si = $k === $on;
        echo '<a class="' . $classe . ($si ? ' on' : '') . '" href="' . b() . Support::e($href) . '"' . ($si ? ' aria-current="page"' : '') . '>'
           . Icon::svg($ico, 19, 1.8) . '<span>' . Support::e($l) . '</span></a>';
    }
};
$navigazione = function () use ($admin, $generale, $attiva, $guida, $quiGuida, $prop, $statoGuida, $voci, $u, $iniziale) { ?>
    <nav class="lato__nav<?= $admin ? ' lato__nav--fitta' : '' ?>" aria-label="<?= $admin ? 'Amministrazione' : 'Sezioni dell\'account' ?>">
      <span class="lato__gruppo"><?= $admin ? 'Amministrazione' : 'Generale' ?></span>
      <?php $voci($generale, $attiva, 'lato__voce'); ?>
    </nav>
    <?php if ($guida): ?>
      <nav class="lato__nav" aria-label="La guida">
        <span class="lato__gruppo lato__gruppo--guida"><span><?= Support::e($prop['name']) ?></span>
          <i class="lato__stato lato__stato--<?= $statoGuida[0] ?>" title="<?= $statoGuida[1] ?>"></i></span>
        <?php $voci($guida, $quiGuida, 'lato__voce'); ?>
        <a class="lato__voce" href="<?= b() ?>/pannello/<?= (int) $prop['id'] ?>/anteprima" target="_blank" rel="noopener">
          <?= Icon::svg('eye', 19, 1.8) ?><span>Anteprima</span><?= Icon::svg('external', 14, 1.8, 'lato__fuori') ?></a>
      </nav>
    <?php endif; ?>
    <div class="lato__piede">
      <div class="lato__utente">
        <span class="avatar" aria-hidden="true"><?= Support::e($iniziale) ?></span>
        <span class="lato__chi"><b><?= Support::e($u['name'] ?: 'Il tuo account') ?></b><span><?= Support::e($u['email']) ?></span></span>
      </div>
      <div class="lato__comandi">
        <?php $etScuro = 'Passa al tema scuro'; $etChiaro = 'Passa al tema chiaro'; include __DIR__ . '/_tema-bottone.php'; ?>
        <form method="post" action="<?= b() ?>/esci" style="margin:0"><?= Csrf::field() ?>
          <button class="lato__esci"><?= Icon::svg('logout', 18, 1.8) ?>Esci</button></form>
      </div>
    </div>
<?php }; ?>
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= Support::e($title ?? 'MyHouse Welcome') ?></title>
<?php include __DIR__ . '/_tema.php'; ?>
<?php include __DIR__ . '/_icone.php'; ?>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Gloock&family=Onest:wght@300..800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= MHW\av('/assets/app.css') ?>">
</head>
<body class="cms<?= $conLato ? ' cms--lato' : '' ?>">
<a class="salta" href="#contenuto">Vai al contenuto</a>
<?php if (Auth::isImpersonating()): ?>
<div class="impersonating"><div class="wrap">
  <span>Stai guardando l'applicazione con l'account di un cliente. La sessione finisce da sola entro un'ora.</span>
  <form method="post" action="<?= b() ?>/admin/esci-da-cliente"><?= Csrf::field() ?><button class="btn">Torna al mio account</button></form>
</div></div>
<?php endif; ?>

<?php if ($conLato): ?>
<div class="app">
  <aside class="lato" aria-label="Navigazione">
    <a class="lato__marchio" href="<?= b() ?>/<?= $admin ? 'admin' : 'pannello' ?>"><?= Icon::brand(26) ?><span>myhouse welcome</span></a>
    <?php if ($admin): ?><span class="tag lato__tag">Amministrazione</span><?php endif; ?>
    <?php $navigazione(); ?>
  </aside>

  <div class="tela">
    <header class="tela__testa">
      <div class="tela__telefono">
        <a class="brand" href="<?= b() ?>/<?= $admin ? 'admin' : 'pannello' ?>"><?= Icon::brand(24) ?><span>myhouse welcome</span></a>
        <details class="cassetto">
          <summary class="icon-btn" aria-label="Menu"><?= Icon::svg('menu', 20, 2) ?></summary>
          <div class="cassetto__pannello lato">
            <div class="cassetto__testa">
              <span class="lato__marchio"><?= Icon::brand(24) ?><span>myhouse welcome</span></span>
              <button type="button" class="icon-btn cassetto__chiudi" aria-label="Chiudi il menu" onclick="this.closest('details').open=false"><?= Icon::svg('close', 18, 2) ?></button>
            </div>
            <?php $navigazione(); ?>
          </div>
        </details>
      </div>
      <nav class="briciole" aria-label="Sei qui">
        <?php foreach ($briciole as $i => [$l, $href]): ?>
          <?php if ($i): ?><span class="briciole__sep" aria-hidden="true"><?= Icon::svg('chevron', 14, 2) ?></span><?php endif; ?>
          <?php if ($href): ?><a href="<?= b() . $href ?>"><?= Support::e($l) ?></a><?php else: ?><span<?= $i === count($briciole) - 1 ? ' aria-current="page"' : '' ?>><?= Support::e($l) ?></span><?php endif; ?>
        <?php endforeach; ?>
      </nav>
      <?php if ($prop): ?>
        <div class="tela__azioni">
          <?php if ($qui === 'procedura'): ?>
            <a class="btn btn--quiet btn--sm propbar__dopo" href="<?= b() ?>/pannello/<?= (int) $prop['id'] ?>">Continua dopo</a>
          <?php endif; ?>
          <a class="btn btn--ghost btn--sm" href="<?= b() ?>/pannello/<?= (int) $prop['id'] ?>/anteprima" target="_blank" rel="noopener"><?= Icon::svg('eye', 15) ?><span>Anteprima</span></a>
          <a class="btn btn--sm" href="<?= b() ?>/pannello/<?= (int) $prop['id'] ?>/procedura/pubblica">Pubblica</a>
        </div>
      <?php endif; ?>
    </header>

    <?php if ($guida): /* sul telefono: le voci della guida a portata di pollice */ ?>
      <nav class="schede-guida" aria-label="La guida, sezioni">
        <?php $voci($guida, $quiGuida, 'schede-guida__voce'); ?>
      </nav>
    <?php endif; ?>

    <?php if (!$admin && !Auth::isVerified($u)): ?>
      <div class="banner banner--info">
        <span><?= Icon::svg('info', 18) ?>Conferma la tua email (<?= Support::e($u['email']) ?>): puoi preparare la guida, ma per pubblicarla serve la conferma.</span>
        <form method="post" action="<?= b() ?>/verifica/invia"><?= Csrf::field() ?><button class="btn btn--ghost btn--sm">Mandami di nuovo l'email</button></form>
      </div>
    <?php endif; ?>
<?php else: ?>
<header class="topbar"><div class="wrap">
  <a class="brand" href="<?= b() ?>/<?= $u ? ($admin ? 'admin' : 'pannello') : '' ?>"><?= Icon::brand(26) ?><span>myhouse welcome</span></a>
  <div class="row" style="gap:10px">
    <?php if ($soloPassi): ?>
      <a class="btn btn--quiet btn--sm propbar__dopo" href="<?= b() ?>/pannello/<?= (int) $prop['id'] ?>">Continua dopo</a>
    <?php endif; ?>
    <?php include __DIR__ . '/_tema-bottone.php'; ?>
  </div>
</div></header>
<?php if ($soloPassi): ?><div class="propbar"><div class="wrap"><span class="propbar__name"><?= Support::e($prop['name']) ?></span></div></div><?php endif; ?>
<?php endif; ?>

<main id="contenuto" tabindex="-1" class="<?= $conLato ? 'tela__corpo' : 'wrap' ?>"<?= $conLato ? '' : ' style="padding-top:32px;padding-bottom:64px"' ?>>
<?php if ($f): ?>
  <?php if ($f['kind'] === 'limite'): ?>
    <div class="limit" style="margin-bottom:24px" role="status">
      <p style="max-width:620px"><?= Support::e($f['msg']) ?></p>
      <a class="btn btn--sm" href="<?= b() ?>/piano?passa=plus">Scopri Plus</a>
    </div>
  <?php else: ?>
    <p class="note <?= ['err' => 'note--err', 'avviso' => ''][$f['kind']] ?? 'note--ok' ?>" style="margin-bottom:24px" role="status"><?= Support::e($f['msg']) ?></p>
  <?php endif; ?>
<?php endif; ?>
<?= $content ?>
</main>
<?php if ($conLato): ?>
  </div>
</div>
<script>
/* Il cassetto del telefono si chiude con Esc, scegliendo una voce o toccando fuori. */
(function () {
  var c = document.querySelector('.cassetto'); if (!c) return;
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && c.open) { c.open = false; c.querySelector('summary').focus(); } });
  document.addEventListener('click', function (e) {
    if (c.open && (!c.querySelector('.cassetto__pannello').contains(e.target) && !c.querySelector('summary').contains(e.target) || e.target.closest('.cassetto__pannello a'))) c.open = false;
  });
})();
</script>
<?php endif; ?>
<script src="<?= MHW\av('/assets/cms.js') ?>" defer></script>
</body>
</html>
