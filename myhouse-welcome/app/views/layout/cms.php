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
use function MHW\a; use function MHW\b; use function MHW\t;
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
// L'amministrazione in tre gruppi: vendite, clienti, sistema (Inviti solo se sono accesi).
$gruppiAdmin = $admin ? [
    'Vendite' => array_merge([['admin', '/admin', 'Quadro', 'grid'], ['prospetti', '/admin/prospetti', 'Prospetti', 'chart'], ['vendite', '/admin/vendite', 'Vendite per piano', 'layers'], ['marketing', '/admin/marketing', 'Marketing', 'chart'],
                  ['abbonamenti', '/admin/abbonamenti', 'Abbonamenti', 'card'], ['scadenze', '/admin/scadenze', 'Scadenze', 'calendar'], ['pacchetti', '/admin/pacchetti', 'Piani', 'layers'],
                  ['sconti', '/admin/sconti', 'Codici sconto', 'euro'], ['traduzioni', '/admin/traduzioni', 'Traduzioni', 'globe']], MHW\Omaggi::disponibili() ? [['omaggi', '/admin/omaggi', 'Codici omaggio', 'card']] : [],
                  MHW\Inviti::disponibili() ? [['inviti', '/admin/inviti', 'Inviti', 'message']] : [],
                  MHW\Giudizi::disponibili() ? [['giudizi', '/admin/giudizi', 'Giudizi e vetrina', 'star']] : [],
                  MHW\Preventivi::disponibili() ? [['preventivi', '/admin/preventivi', 'Preventivi', 'message']] : [],
                  MHW\Partner::disponibili() ? [['partner', '/admin/partner', 'Partner', 'suitcase']] : []),
    'Clienti' => [['clienti', '/admin/clienti', 'Clienti', 'people'], ['guide', '/admin/guide', 'Guide', 'book'], ['anomalie', '/admin/anomalie', 'Anomalie', 'warning'],
                  ['testimonianze', '/admin/testimonianze', 'Testimonianze', 'star']],
    'Sistema' => [['impostazioni', '/admin/impostazioni', 'Impostazioni', 'key'], ['testi', '/admin/testi', 'Testi e foto', 'doc'], ['seo', '/admin/seo', 'SEO e GEO', 'search'],
                  ['registro', '/admin/registro', 'Registro', 'list'], ['diagnostica', '/admin/diagnostica', 'Diagnostica', 'pulse'], ['cache', '/admin/cache', 'Cache', 'bin']],
] : [];
$generale = $admin ? array_merge(...array_values($gruppiAdmin))
  : [['guide', '/pannello', 'Le mie guide', 'grid'], ['account', '/account', 'Account & Fatturazione', 'card'], ['novita', '/pannello/novita', 'Novità', 'star']];
// Per i clienti la voce c'è sempre, con gli inviti accesi: chi non può ancora invitare vede il motivo.
// 7D: con l'etichetta colorata degli sconti (all'amico / a te).
if (!$admin && $u && Auth::account() && MHW\Inviti::disponibili()) $generale[] = ['inviti', '/inviti', 'Invita un amico', 'people', "\u{2212}" . MHW\Inviti::AMICO . "% / \u{2212}" . MHW\Inviti::PASSO . '%'];
// 9L · «Giudizio con sconto»: una voce sola per il programma (adesione, scheda in home, giudizio, ritiro).
if (!$admin && $u && ($accNav = Auth::account()) && MHW\Vetrina::disponibili() && !MHW\Giudizi::escluso($accNav)
    && (!empty($accNav['giudizio_adesione']) || MHW\Giudizi::attivo())) $generale[] = ['giudizio', '/giudizio', 'Giudizio con sconto', 'star', "\u{2212}" . MHW\Giudizi::percentuale() . '%'];
$attiva = $admin ? $nav : ($prop ? '' : ($nav ?: 'guide'));
$guida = [];
if ($prop) {
    $pid = (int) $prop['id'];
    $guida = [['contenuti', "/pannello/$pid", 'Contenuti', 'doc'], ['lingue', "/pannello/$pid/lingue", 'Lingue', 'globe'],
              ['aspetto', "/pannello/$pid/aspetto", 'Aspetto', 'palette'], ['qr', "/pannello/$pid/qr", 'QR & Link', 'qr']];
    if ($vedeStatistiche) $guida[] = ['statistiche', "/pannello/$pid/statistiche", 'Statistiche', 'chart'];
    $guida[] = ['impostazioni', "/pannello/$pid/impostazioni", 'Impostazioni', 'sliders'];
}
$quiGuida = $qui === 'procedura' ? 'contenuti' : $qui;
$statoGuida = !$prop ? null : ($prop['status'] !== 'published' ? ['ochre', t('Bozza')] : ['pine', t('Pubblicata')]);

// Dove sei: le briciole in alto nella tela.
$briciole = [];
if ($admin) {
    $briciole[] = ['Amministrazione', $nav === 'admin' ? null : '/admin'];
    foreach ($generale as [$k, , $l]) if ($k === $nav && $k !== 'admin') $briciole[] = [$l, null];
} elseif ($u) {
    $briciole[] = [t(['account' => 'Account & Fatturazione', 'inviti' => 'Invita un amico', 'novita' => 'Novità'][$nav] ?? 'Le mie guide'), $prop ? '/pannello' : null];
    if ($prop) {
        $briciole[] = [$prop['name'], null];
        foreach ($guida as [$k, , $l]) if ($k === $quiGuida) $briciole[] = [t($l), null];
        if ($qui === 'procedura') $briciole[] = [t('Configurazione'), null];
    }
}

$voci = function (array $elenco, string $on, string $classe) {
    foreach ($elenco as $voce) {
        [$k, $href, $l, $ico] = $voce; $etichetta = (string) ($voce[4] ?? '');
        $si = $k === $on;
        echo '<a class="' . $classe . ($si ? ' on' : '') . '" href="' . b() . Support::e($href) . '"' . ($si ? ' aria-current="page"' : '') . '>'
           . Icon::svg($ico, 19, 1.8) . '<span>' . Support::e(t($l)) . '</span>'
           . ($etichetta !== '' ? '<em class="lato__etichetta" title="Sconto per il tuo amico / per te">' . Support::e($etichetta) . '</em>' : '') . '</a>';
    }
};
$navigazione = function () use ($admin, $generale, $gruppiAdmin, $attiva, $guida, $quiGuida, $prop, $statoGuida, $voci, $u, $iniziale) { ?>
    <?php if ($admin): ?>
      <?php foreach ($gruppiAdmin as $titoloGruppo => $vociGruppo): ?>
        <nav class="lato__nav lato__nav--fitta" aria-label="Amministrazione: <?= Support::e(strtolower($titoloGruppo)) ?>">
          <span class="lato__gruppo"><?= Support::e($titoloGruppo) ?></span>
          <?php $voci($vociGruppo, $attiva, 'lato__voce'); ?>
        </nav>
      <?php endforeach; ?>
    <?php else: ?>
    <nav class="lato__nav" aria-label="Sezioni dell'account">
      <span class="lato__gruppo">Generale</span>
      <?php $voci($generale, $attiva, 'lato__voce'); ?>
    </nav>
    <?php endif; ?>
    <?php if ($guida): ?>
      <nav class="lato__nav" aria-label="La guida">
        <span class="lato__gruppo lato__gruppo--guida"><span><?= Support::e($prop['name']) ?></span>
          <i class="lato__stato lato__stato--<?= $statoGuida[0] ?>" title="<?= $statoGuida[1] ?>"></i></span>
        <?php $voci($guida, $quiGuida, 'lato__voce'); ?>
        <a class="lato__voce" href="<?= b() ?>/pannello/<?= (int) $prop['id'] ?>/anteprima" target="_blank" rel="noopener">
          <?= Icon::svg('eye', 19, 1.8) ?><span><?= t('Anteprima') ?></span><?= Icon::svg('external', 14, 1.8, 'lato__fuori') ?></a>
        <?php if (($esempio = MHW\Demo::esempio(null, (int) ($prop['id'] ?? 0)))): /* 7C: la vetrina, per vedere una guida finita */ ?>
          <a class="lato__voce" href="<?= Support::e(Support::url($esempio)) ?>" target="_blank" rel="noopener">
            <?= Icon::svg('book', 19, 1.8) ?><span><?= t('Guida di esempio') ?></span><?= Icon::svg('external', 14, 1.8, 'lato__fuori') ?></a>
        <?php endif; ?>
      </nav>
    <?php endif; ?>
    <div class="lato__piede">
      <div class="lato__utente">
        <span class="avatar" aria-hidden="true"><?= Support::e($iniziale) ?></span>
        <span class="lato__chi"><b><?= Support::e($u['name'] ?: t('Il tuo account')) ?></b><span><?= Support::e($u['email']) ?></span></span>
      </div>
      <?php if (!$admin && Support::contatto() !== ''): /* 7A: l'assistenza, con pagina, struttura e account già scritti */
            $paginaAiuto = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH); ?>
        <a class="lato__aiuto" href="<?= Support::e(Support::mailto('Assistenza — ' . ($prop['name'] ?? 'MyHouse Welcome'),
            "Ciao, mi serve aiuto con MyHouse Welcome.\n\nPagina: " . $paginaAiuto . "\nStruttura: " . ($prop['name'] ?? '—') . "\nAccount: " . $u['email'] . "\n\nCosa succede:\n")) ?>">
          <?= Icon::svg('message', 18, 1.8) ?><?= t('Aiuto') ?></a>
      <?php endif; ?>
      <div class="lato__comandi">
        <?php $etScuro = 'Passa al tema scuro'; $etChiaro = 'Passa al tema chiaro'; include __DIR__ . '/_tema-bottone.php'; ?>
        <form method="post" action="<?= b() ?>/esci" style="margin:0"><?= Csrf::field() ?>
          <button class="lato__esci"><?= Icon::svg('logout', 18, 1.8) ?><?= t('Esci') ?></button></form>
      </div>
    </div>
<?php }; ?>
<!doctype html>
<html lang="<?= MHW\Pannello::en() ? 'en' : 'it' ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="<?= Support::ROBOTS_GUIDE ?>">
<title><?= Support::e($title ?? 'MyHouse Welcome') ?></title>
<?php include __DIR__ . '/_tema.php'; ?>
<?php include __DIR__ . '/_icone.php'; ?>
<?php /* 8H: i caratteri dal nostro server (assets/fonts): niente richieste a Google; si anticipa solo Onest, il testo. */ ?>
<link rel="preload" href="<?= MHW\a('/assets/fonts/onest-latin.woff2') ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= MHW\av('/assets/app.css') ?>">
</head>
<body class="cms<?= $conLato ? ' cms--lato' : '' ?>">
<a class="salta" href="#contenuto"><?= t('Vai al contenuto') ?></a>
<?php if (Auth::isImpersonating()): ?>
<div class="impersonating"><div class="wrap">
  <span>Stai guardando l'applicazione con l'account di un cliente<?php if (($accI = Auth::account()) && ($codI = MHW\Codici::cliente((int) $accI['id'])) !== ''): ?> · <b><?= Support::e($codI) ?></b><?php endif; ?>. La sessione finisce da sola entro un'ora.</span>
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
      <?php if ($admin): /* 9I · la ricerca unica, in ogni pagina; «/» la apre da tastiera */ ?>
        <form class="cerca-admin" role="search" method="get" action="<?= b() ?>/admin/cerca">
          <label for="cerca-admin" class="sr-only">Cerca un cliente: codice, email, telefono, CIN, Stripe…</label>
          <input id="cerca-admin" name="q" type="search" placeholder="Cerca: codice, email, telefono, CIN…" value="<?= Support::e($nav === 'clienti' && isset($q) && is_string($q) ? $q : '') ?>" autocomplete="off" spellcheck="false" data-cerca-admin aria-keyshortcuts="/">
          <kbd class="cerca-admin__tasto" aria-hidden="true">/</kbd>
        </form>
      <?php endif; ?>
      <?php if ($prop): ?>
        <div class="tela__azioni">
          <?php if ($qui === 'procedura'): ?>
            <a class="btn btn--quiet btn--sm propbar__dopo" href="<?= b() ?>/pannello/<?= (int) $prop['id'] ?>"><?= t('Continua dopo') ?></a>
          <?php endif; ?>
          <a class="btn btn--ghost btn--sm" href="<?= b() ?>/pannello/<?= (int) $prop['id'] ?>/anteprima" target="_blank" rel="noopener"><?= Icon::svg('eye', 15) ?><span><?= t('Anteprima') ?></span></a>
          <a class="btn btn--sm" href="<?= b() ?>/pannello/<?= (int) $prop['id'] ?>/procedura/pubblica"><?= t('Pubblica') ?></a>
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
    <?php /* 9D · La fascia dei 30 giorni prima dell'addebito (o della scadenza), nel Quadro e in Account: a 7 giorni in evidenza; «Chiudi» per 24 ore. */
          if (!$admin && in_array($nav, ['guide', 'account'], true) && !$prop && ($accF = Auth::account()) && ($fascia = MHW\Richiami::fascia($accF)) && empty($_COOKIE[$fascia['chiave']])): ?>
      <div class="fascia fascia--<?= $fascia['tipo'] ?><?= $fascia['evidenza'] ? ' fascia--evidenza' : '' ?>" role="status">
        <span class="fascia__testo">
          <?php if ($fascia['tipo'] === 'omaggio'): ?>
            <b>La tua prova gratuita finisce il <?= Support::e($fascia['data']) ?>.</b> Da quel giorno parte <?= Support::e($fascia['piano']) ?> a <?= Support::e($fascia['importo']) ?> l'anno<?= $fascia['carta'] !== '' ? ', sulla ' . Support::e($fascia['carta']) : '' ?>. Fino ad allora non paghi nulla.
          <?php elseif ($fascia['tipo'] === 'rinnovo' && $fascia['evidenza']): ?>
            <b>Tra <?= (int) $fascia['giorni'] ?> giorni, il <?= Support::e($fascia['data']) ?>,</b> addebitiamo <?= Support::e($fascia['importo']) ?> per il rinnovo di <?= Support::e($fascia['piano']) ?><?= $fascia['carta'] !== '' ? ' sulla ' . Support::e($fascia['carta']) : '' ?>. Se non vuoi rinnovare, disattivalo entro quella data: la guida resta online fino al <?= Support::e($fascia['data']) ?>.
          <?php elseif ($fascia['tipo'] === 'rinnovo'): ?>
            <b>Rinnovo il <?= Support::e($fascia['data']) ?></b> · <?= Support::e($fascia['piano']) ?> · <?= Support::e($fascia['importo']) ?><?= $fascia['carta'] !== '' ? ' sulla ' . Support::e($fascia['carta']) : '' ?>. Non devi fare nulla.
          <?php else: ?>
            <b>L'abbonamento scade il <?= Support::e($fascia['data']) ?> e non si rinnova.</b> Nessun addebito. Dopo quella data la guida non sarà più visibile.
          <?php endif; ?>
        </span>
        <span class="fascia__azioni">
          <?php if ($fascia['sub']['provider'] === 'stripe' && $fascia['sub']['provider_subscription_id'] !== ''): ?>
            <form method="post" action="<?= b() ?>/account/rinnovo" style="margin:0"><?= Csrf::field() ?><button class="btn btn--sm<?= $fascia['evidenza'] && $fascia['automatico'] ? '' : ' btn--ghost' ?>" name="rinnovo" value="<?= $fascia['automatico'] ? 'no' : 'si' ?>"><?= $fascia['automatico'] ? 'Disattiva il rinnovo' : 'Riattiva il rinnovo' ?></button></form>
          <?php endif; ?>
          <form method="post" action="<?= b() ?>/pannello/avviso-rinnovo/chiudi" style="margin:0"><?= Csrf::field() ?><input type="hidden" name="torna" value="/<?= $nav === 'account' ? 'account' : 'pannello' ?>"><button class="btn btn--quiet btn--sm" aria-label="Chiudi l'avviso per 24 ore">Chiudi</button></form>
        </span>
      </div>
    <?php endif; ?>
<?php else: ?>
<header class="topbar"><div class="wrap">
  <a class="brand" href="<?= b() ?>/<?= $u ? ($admin ? 'admin' : 'pannello') : '' ?>"><?= Icon::brand(26) ?><span>myhouse welcome</span></a>
  <div class="row" style="gap:10px">
    <?php if ($soloPassi): ?>
      <a class="btn btn--quiet btn--sm propbar__dopo" href="<?= b() ?>/pannello/<?= (int) $prop['id'] ?>"><?= t('Continua dopo') ?></a>
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
      <p style="max-width:620px"><?= Support::e(MHW\Pannello::t($f['msg'])) ?></p>
      <a class="btn btn--sm" href="<?= b() ?>/piano?passa=plus"><?= MHW\Pannello::t('Scopri Plus') ?></a>
    </div>
  <?php else: ?>
    <p class="note <?= ['err' => 'note--err', 'avviso' => ''][$f['kind']] ?? 'note--ok' ?>" style="margin-bottom:24px" role="status"><?= Support::e(MHW\Pannello::t($f['msg'])) ?></p>
  <?php endif; ?>
<?php endif; ?>
<?php /* 7C · Novità: la prima non ancora chiusa da questo account (non per l'amministrazione). */
      if ($u && ($u['role'] ?? '') !== 'admin' && ($accN = Auth::account()) && ($novita = MHW\Novita::daMostrare((int) $accN['id'], (string) ($u['created_at'] ?? '')))):
        $pidN = !empty($prop['id']) ? (int) $prop['id'] : (int) MHW\Db::val('SELECT id FROM properties WHERE account_id = ? AND archived_at IS NULL ORDER BY id', [$accN['id']], 0);
        $doveN = str_contains((string) $novita['dove'], '{pid}') ? ($pidN ? str_replace('{pid}', (string) $pidN, (string) $novita['dove']) : '') : (string) $novita['dove']; ?>
  <aside class="novita" aria-labelledby="novita-titolo">
    <span class="novita__kicker"><span class="dot" aria-hidden="true"></span>Novità</span>
    <?php /* 8C: la riga in più se la sezione «Aria condizionata» è nata spenta (migrazione 037, account al limite) */
          $notaN = !empty($novita['nota_aria']) && MHW\Db::val("SELECT 1 FROM sections s JOIN properties p ON p.id = s.property_id WHERE p.account_id = ? AND s.kind = 'aria' AND s.is_active = 0", [$accN['id']]) ? (string) $novita['nota_aria'] : ''; ?>
    <div class="novita__testo"><b id="novita-titolo"><?= Support::e($novita['titolo']) ?></b> <span><?= Support::e($novita['testo']) ?></span><?php if ($notaN !== ''): ?> <span class="novita__nota"><?= Support::e($notaN) ?></span><?php endif; ?></div>
    <div class="novita__azioni">
      <?php /* 9F · niente ✕: «Mostrami dove» segna la novità come vista (POST) e porta lì; la fascia resta finché non ne esce un'altra. */ ?>
      <?php if ($doveN !== ''): ?>
        <form method="post" action="<?= b() ?>/pannello/novita/vai" style="margin:0"<?= str_ends_with($doveN, '/anteprima') ? ' target="_blank"' : '' ?>><?= Csrf::field() ?>
          <input type="hidden" name="novita" value="<?= Support::e($novita['id']) ?>"><input type="hidden" name="dove" value="<?= Support::e($doveN) ?>">
          <button class="btn btn--sm">Mostrami dove</button></form>
      <?php endif; ?>
      <a class="novita__tutte" href="<?= b() ?>/pannello/novita">Tutte le novità</a>
    </div>
  </aside>
<?php endif; ?>
<?php /* 8J · Termini, Informativa o DPA da (ri)accettare: la finestra (o la riga, se valgono da una data futura). Mai per l'assistenza. */
      if ($u && !Auth::isImpersonating() && (($daAccettare = MHW\Legale::daAccettare($u)) || ($annunciati = MHW\Legale::annunciati($u)))) {
          $annunciati = $annunciati ?? [];
          include __DIR__ . '/../host/_legale_finestra.php';
      } ?>
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
<script src="<?= MHW\av('/assets/posta.js') ?>" defer></script>
<script src="<?= MHW\av('/assets/foto-leggere.js') ?>" defer></script>
<?php if ($u && !$admin && Auth::account()) include __DIR__ . '/../host/_sblocca.php'; /* 8B: «Passa a Plus/Portfolio», una finestra al posto dell'errore */ ?>
<script src="<?= MHW\av('/assets/codici.js') ?>" defer></script>
</body>
</html>
