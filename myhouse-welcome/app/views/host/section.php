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
      <a class="small indietro" href="<?= b() ?>/pannello/<?= $pid ?><?= $procedura ? '/procedura/sezioni' : '' ?>"><?= Icon::svg('back', 14) ?> <?= $procedura ? 'Torna alla configurazione' : 'Tutte le sezioni' ?></a>
      <div class="row" style="gap:10px">
        <h1 style="font-size:clamp(28px,3.4vw,38px)"><?= $titoloSalvato ?></h1>
        <?php if ($core): ?><span class="badge badge--sea">Sempre inclusa</span><?php endif; ?>
        <?php if (!$core && (int) $s['is_active'] === 0): ?><span class="badge badge--paper">Disattivata</span><?php endif; ?>
        <?php if (($esempioSez = MHW\Demo::esempio((string) $s['kind'], (int) $prop['id']))): /* 7C: la stessa sezione nella guida di esempio */ ?>
          <a class="vedi-esempio" href="<?= Support::e(Support::url($esempioSez)) ?>" target="_blank" rel="noopener"><?= Icon::svg('book', 18) ?>Vedi un esempio</a>
        <?php endif; ?>
      </div>
      <?php if ($intro !== ''): ?><p class="note note--quiet"><?= Support::e($intro) ?></p><?php endif; ?>
      <?php include __DIR__ . '/_privacy_nota.php'; ?>
    </div>

    <?php $inProcedura = false; include __DIR__ . '/_sezione_editor.php'; ?>
    <?php /* 7C: in quali lingue della guida questa sezione non è ancora tradotta */
          $mancaIn = array_keys(array_filter(MHW\Traduttore::mancanti($prop), fn($per) => !empty($per[$sid])));
          if ($mancaIn): ?>
      <p class="manca-in small"><?= Icon::svg('globe', 15) ?> Manca in:
        <?php foreach ($mancaIn as $i => $lm): ?><?= $i ? ', ' : '' ?><a href="<?= b() ?>/pannello/<?= $pid ?>/lingue/<?= Support::e($lm) ?>"><?= Support::e(strtoupper($lm)) ?></a><?php endforeach; ?></p>
    <?php endif; ?>

    <a class="btn btn--ghost phonebtn" href="<?= Support::e(Support::url($src)) ?>" target="_blank" rel="noopener"><?= Icon::svg('eye', 16) ?>Apri l'anteprima</a>
  </div>
  <?php include __DIR__ . '/_telefono.php'; ?>
</div>
