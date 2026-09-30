<?php
/* L'editor di una sezione: campi veri, uno per informazione. Il testo si
   salva mentre scrivi (se il browser lo permette) e comunque col bottone.
   Le immagini e i PDF compaiono solo se il piano li comprende. */
use function MHW\b;
use MHW\{Support, Csrf, Icon, Media, SectionCatalog, Entitlements};
$pid = (int) $prop['id']; $sid = (int) $s['id'];
$nome = SectionCatalog::title($s['kind'], $prop['default_locale']);
$titoloSezione = $title;
$salvato = $title !== '' ? $title : $nome;
$title = $salvato . ' — ' . $prop['name'];
$titoloSalvato = Support::e($salvato);
$core = (int) $s['is_core'] === 1;
$src = '/pannello/' . $pid . '/anteprima/' . $sid;
$intro = SectionCatalog::get($s['kind'])['intro'] ?? ''; ?>

<div class="editor">
  <div class="stack stack--lg">
    <div class="stack stack--sm">
      <a class="small" href="<?= b() ?>/pannello/<?= $pid ?><?= $procedura ? '/procedura/sezioni' : '' ?>"><?= Icon::svg('back', 14) ?> <?= $procedura ? 'Torna alla procedura' : 'Tutte le sezioni' ?></a>
      <div class="row" style="gap:10px">
        <h1 style="font-size:clamp(28px,3.4vw,38px)"><?= $titoloSalvato ?></h1>
        <?php if ($core): ?><span class="badge badge--sea">Sempre inclusa</span><?php endif; ?>
        <?php if (!$core && (int) $s['is_active'] === 0): ?><span class="badge badge--paper">Disattivata</span><?php endif; ?>
      </div>
      <?php if ($intro !== ''): ?><p class="muted"><?= Support::e($intro) ?></p><?php endif; ?>
    </div>

    <?php $inProcedura = false; include __DIR__ . '/_sezione_editor.php'; ?>

    <a class="btn btn--ghost phonebtn" href="<?= Support::e(Support::url($src)) ?>" target="_blank" rel="noopener"><?= Icon::svg('eye', 16) ?>Guarda l'anteprima</a>
  </div>
  <?php include __DIR__ . '/_telefono.php'; ?>
</div>
