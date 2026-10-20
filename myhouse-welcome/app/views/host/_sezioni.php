<?php use function MHW\tf;
/* L'elenco delle sezioni aggiuntive e il catalogo da cui aggiungerne.
   Si ordinano trascinando la maniglia, oppure dal menu ⋯ con «Sposta su /
   giù» (tastiera e telefono): tutte e due usano le stesse rotte.
   Nella procedura la sezione aperta ($aperta) si compila qui, sotto la sua card.
   Riceve: $prop, $sezioni (solo le aggiuntive), $limite, $attive, $torna ('' | 'procedura'),
           e nella procedura $aperta (dati dell'editor) e $modifica (luogo in modifica). */
use function MHW\b; use function MHW\t;
use MHW\{Support, Csrf, Icon, SectionCatalog};
$pid = (int) $prop['id'];
$torna = $torna ?? '';
$illimitate = $limite >= PHP_INT_MAX;
$pieno = !$illimitate && $attive >= $limite;
$presenti = array_column($sezioni, 'kind');
// Ogni sezione una volta sola; la sezione libera fino a SectionCatalog::LIBERE_MAX.
$libere = count(array_filter($presenti, fn($k) => SectionCatalog::multipla($k)));
$catalogo = array_values(array_filter(SectionCatalog::selectable(), fn($k) => SectionCatalog::multipla($k) ? $libere < SectionCatalog::LIBERE_MAX : !in_array($k, $presenti, true)));
$azione = function (array $s, string $fai, string $etichetta, string $classe = 'menu-riga__voce') use ($pid, $torna): string {
    return '<form method="post" action="' . b() . '/pannello/' . $pid . '/sezioni/' . (int) $s['id'] . '/azione" style="margin:0">'
         . Csrf::field() . '<input type="hidden" name="fai" value="' . $fai . '"><input type="hidden" name="torna" value="' . Support::e($torna) . '">'
         . '<button class="' . $classe . '">' . $etichetta . '</button></form>';
};
$n = count($sezioni); ?>
<div class="stack" style="gap:10px">
  <div class="spread spread--mid">
    <h2 style="font-size:22px"><?= t('Sezioni aggiuntive') ?></h2>
    <?php if (!$illimitate): ?>
      <span class="meter"><i><b style="width:<?= min(100, (int) round($attive / max(1, $limite) * 100)) ?>%"></b></i>
        <?= (int) $attive ?> <?= t('su') ?> <?= (int) $limite ?> <?= t('sezioni attive') ?></span>
    <?php else: ?>
      <span class="small muted"><?= t('Sezioni illimitate con il tuo piano') ?></span>
    <?php endif; ?>
  </div>
  <?php if (!$sezioni): ?>
    <p class="note note--quiet"><?= t('Nessuna sezione aggiuntiva, per ora. Scegline qualcuna qui sotto: di solito si comincia da Wi-Fi e Regole della casa.') ?></p>
  <?php endif; ?>
  <?php if ($sezioni): ?><div class="righe" data-ordina><?php endif; ?>
  <?php foreach ($sezioni as $i => $s): $on = (int) $s['is_active'] === 1;
        $apertaQui = $torna === 'procedura' && !empty($aperta) && (int) $aperta['s']['id'] === (int) $s['id'];
        $modificaUrl = $torna === 'procedura'
            ? b() . '/pannello/' . $pid . '/procedura/sezioni?apri=' . (int) $s['id'] . '#sez-' . (int) $s['id']
            : b() . '/pannello/' . $pid . '/sezioni/' . (int) $s['id']; ?>
    <div class="riga <?= $on ? ($s['empty'] ? 'riga--flag' : '') : 'riga--spenta' ?><?= $apertaQui ? ' riga--aperta' : '' ?>" data-riga id="sez-<?= (int) $s['id'] ?>"
         data-azione="<?= b() ?>/pannello/<?= $pid ?>/sezioni/<?= (int) $s['id'] ?>/azione" data-torna="<?= Support::e($torna) ?>">
      <span class="riga__maniglia" aria-hidden="true" title="<?= MHW\Support::e(t('Trascina per cambiare l\'ordine')) ?>"><?= Icon::svg('grip', 18, 2.6) ?></span>
      <span class="riga__ico"><?= Icon::svg(SectionCatalog::iconaDi($s['kind'], $s['data'] ?? []), 20) ?></span>
      <a class="riga__nome" href="<?= $modificaUrl ?>"<?= $apertaQui ? ' aria-current="true"' : '' ?>><b><?= Support::e($s['title']) ?></b></a>
      <?php if (!$on): ?><span class="badge badge--paper"><?= t('Disattivata') ?></span>
      <?php elseif ($s['empty']): ?><span class="badge badge--terracotta"><?= t('Da compilare') ?></span>
      <?php else: ?><span class="badge badge--pine"><?= t('Pronta') ?></span><?php endif; ?>
      <?php if (!empty($s['da_controllare'])): /* 9M · il parcheggio copiato da un'altra guida: l'indirizzo è diverso */ ?><span class="badge badge--terracotta"><?= t('Da controllare') ?></span><?php endif; ?>
      <?php /* 7C · Novità: un campo nuovo ancora vuoto in questa sezione */
            $tdN = json_decode((string) MHW\Db::val('SELECT data FROM section_translations WHERE section_id = ? AND locale = ?', [$s['id'], $prop['default_locale']], ''), true) ?: [];
            if ($on && MHW\Novita::campiNuovi((int) $prop['account_id'], (string) $s['kind'], (json_decode((string) ($s['data'] ?? ''), true) ?: []) + $tdN)): ?>
        <span class="badge badge--ochre novita-pallino"><span class="dot" aria-hidden="true"></span><?= t('Nuovo') ?></span>
      <?php endif; ?>
      <?php /* 7C: le lingue in cui la sezione non è ancora tradotta, in una sola etichetta ocra («EN da tradurre», «DE, FR da tradurre»):
               una per lingua, sul telefono, spingerebbe fuori il nome della sezione. Porta alla prima lingua da fare. */
            $mancantiL = $mancantiL ?? MHW\Traduttore::mancanti($prop);
            $lingueSez = $on ? array_keys(array_filter($mancantiL, fn($per) => !empty($per[(int) $s['id']]))) : [];
            if ($lingueSez): ?>
        <a class="badge badge--ochre etichetta-tradurre" href="<?= b() ?>/pannello/<?= $pid ?>/lingue/<?= Support::e($lingueSez[0]) ?>"
           title="<?= Support::e(tf('Da tradurre in: {lingue}', ['{lingue}' => implode(', ', array_map('strtoupper', $lingueSez))])) ?>"><?= Support::e(tf('{lingue} da tradurre', ['{lingue}' => implode(', ', array_map('strtoupper', $lingueSez))])) ?></a>
      <?php endif; ?>
      <details class="menu-riga">
        <summary class="icon-btn" aria-label="<?= Support::e(tf('Azioni per {sezione}', ['{sezione}' => $s['title']])) ?>"><span aria-hidden="true">⋯</span></summary>
        <div class="menu-riga__lista">
          <a class="menu-riga__voce" href="<?= $modificaUrl ?>"><?= t('Modifica') ?></a>
          <?= $i > 0 ? $azione($s, 'su', t('Sposta su')) : '' ?>
          <?= $i < $n - 1 ? $azione($s, 'giu', t('Sposta giù')) : '' ?>
          <?= $on ? $azione($s, 'disattiva', t('Disattiva')) : $azione($s, 'attiva', t('Attiva')) ?>
          <hr class="rule">
          <p class="tiny muted" style="padding:4px 12px"><?= t('Eliminare cancella anche contenuti e traduzioni. Per toglierla solo dalla guida, disattivala.') ?></p>
          <?= $azione($s, 'elimina', t('Elimina'), 'menu-riga__voce menu-riga__voce--danger') ?>
        </div>
      </details>
    </div>
    <?php if ($apertaQui): ?>
      <div class="riga-editor" aria-label="<?= Support::e($s['title']) ?>">
        <?php (function (array $v) { extract($v); include __DIR__ . '/_sezione_editor.php'; })([
            'prop' => $prop, 'acc' => $acc, 's' => $aperta['s'], 'titoloSezione' => $aperta['title'], 'dati' => $aperta['dati'],
            'tdati' => $aperta['tdati'], 'places' => $aperta['places'], 'modifica' => $modifica ?? 0, 'err' => null,
            'suggLuoghi' => $aperta['suggLuoghi'], 'suggRighe' => $aperta['suggRighe'],
            'inProcedura' => true, 'procedura' => false]); ?>
        <p><a class="small" href="<?= b() ?>/pannello/<?= $pid ?>/procedura/sezioni#sez-<?= (int) $s['id'] ?>"><?= t('Chiudi l\'editor') ?></a></p>
      </div>
    <?php endif; ?>
  <?php endforeach; ?>
  <?php if ($sezioni): ?></div><?php endif; ?>
</div>

<?php if ($catalogo): ?>
<div class="stack" style="gap:12px;margin-top:8px">
  <h3 style="font-size:18px"><?= t('Aggiungi una sezione') ?></h3>
  <?php if ($pieno): ?>
    <div class="limit" role="status">
      <p style="max-width:620px"><?= Support::e(tf('Hai già {n} sezioni attive, il massimo del tuo piano. Passa a Plus per averne quante vuoi, oppure disattivane una per liberare un posto.', ['{n}' => (int) $limite])) ?></p>
      <a class="btn btn--sm" href="<?= b() ?>/piano?passa=plus" data-sblocca="sezioni"><?= t('Scopri Plus') ?></a>
    </div>
  <?php endif; ?>
  <?php if ($libere >= SectionCatalog::LIBERE_MAX): ?>
    <p class="small muted"><?= t('Hai già la sezione libera') ?><?= $libere > 1 ? ' (' . $libere . ')' : '' ?><?= t(': in una guida ce n\'è una. Ogni altra sezione si aggiunge una volta sola: una guida è una casa o una struttura, con il suo indirizzo e il suo CIN.') ?></p>
  <?php endif; ?>
  <div class="kinds">
    <?php foreach ($catalogo as $k): ?>
      <div class="kind <?= $pieno ? 'kind--off' : '' ?>">
        <span class="ico"><?= Icon::svg(SectionCatalog::icon($k), 22) ?></span>
        <b><?= Support::e(SectionCatalog::nome($k)) ?></b>
        <?php if (($breve = SectionCatalog::breve($k)) !== ''): ?><span class="small muted"><?= Support::e($breve) ?></span><?php endif; ?>
        <form method="post" action="<?= b() ?>/pannello/<?= $pid ?>/sezioni"><?= Csrf::field() ?>
          <input type="hidden" name="kind" value="<?= Support::e($k) ?>"><input type="hidden" name="torna" value="<?= Support::e($torna) ?>">
          <?php /* 8B: oltre il limite il bottone resta, con il lucchetto, e apre «Con Plus hai tutte le sezioni» */ ?>
          <button class="btn btn--ghost btn--sm<?= $pieno ? ' btn--lucchetto' : '' ?>"<?= $pieno ? ' data-sblocca="sezioni"' : '' ?>><?= Icon::svg($pieno ? 'lock' : 'plus', 15, 2) ?><?= t('Aggiungi') ?><span class="sr-only"> <?= Support::e(SectionCatalog::nome($k)) ?></span></button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php /* «Copia sezioni da…»: solo se c'è un'altra struttura da cui partire. */
if ((int) MHW\Db::val('SELECT COUNT(*) FROM properties WHERE account_id = ? AND id <> ? AND archived_at IS NULL', [$prop['account_id'], $pid], 0) > 0): ?>
  <p style="margin-top:16px"><a class="linkbtn" href="<?= b() ?>/pannello/<?= $pid ?>/copia"><?= Icon::svg('copy', 15) ?> <?= t('Copia sezioni da un\'altra struttura') ?></a></p>
<?php endif; ?>
