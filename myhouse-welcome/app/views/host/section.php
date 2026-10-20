<?php
/* L'editor di una sezione: campi veri, uno per informazione. Il testo si
   salva mentre scrivi (se il browser lo permette) e comunque col bottone.
   Le immagini e i PDF compaiono solo se il piano li comprende. */
use function MHW\b; use function MHW\t;
use MHW\{Support, Csrf, Icon, Media, SectionCatalog, Entitlements};
$pid = (int) $prop['id']; $sid = (int) $s['id'];
$nome = SectionCatalog::title($s['kind'], $prop['default_locale']);
$titoloSezione = $title;
$salvato = $title !== '' ? $title : $nome;
$title = $salvato . ' — ' . $prop['name'];
$titoloSalvato = Support::e($salvato);
$core = (int) $s['is_core'] === 1;
$src = '/pannello/' . $pid . '/anteprima/' . $sid;
$intro = SectionCatalog::intro($s['kind']); ?>

<div class="editor">
  <div class="stack stack--lg">
    <div class="stack stack--sm">
      <a class="small indietro" href="<?= b() ?>/pannello/<?= $pid ?><?= $procedura ? '/procedura/sezioni' : '' ?>"><?= Icon::svg('back', 14) ?> <?= $procedura ? t('Torna alla configurazione') : t('Tutte le sezioni') ?></a>
      <div class="row" style="gap:10px">
        <h1 style="font-size:clamp(28px,3.4vw,38px)"><?= $titoloSalvato ?></h1>
        <?php if ($core): ?><span class="badge badge--sea"><?= t('Sempre inclusa') ?></span><?php endif; ?>
        <?php if (!$core && (int) $s['is_active'] === 0): ?><span class="badge badge--paper"><?= t('Disattivata') ?></span><?php endif; ?>
        <?php if (!empty($s['da_controllare'])): ?><span class="badge badge--terracotta"><?= t('Da controllare') ?></span><?php endif; ?>
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
      <p class="manca-in small"><?= Icon::svg('globe', 15) ?> <?= t('Manca in:') ?>
        <?php foreach ($mancaIn as $i => $lm): ?><?= $i ? ', ' : '' ?><a href="<?= b() ?>/pannello/<?= $pid ?>/lingue/<?= Support::e($lm) ?>"><?= Support::e(strtoupper($lm)) ?></a><?php endforeach; ?></p>
      <?php /* 9F · «Traduci questa sezione»: una lingua per volta, con la barra «Francese · 2 di 5» (solo con JavaScript e con le suggerite nel piano) */
            if (MHW\Traduttore::perche((int) $prop['account_id'], $prop) === ''): $lingueT = array_map(fn($lm) => ['loc' => $lm, 'nome' => ucfirst(MHW\I18n::nomeIt($lm)),
                'url' => b() . '/pannello/' . $pid . '/lingue/' . $lm . '/suggerite.json', 'pagina' => b() . '/pannello/' . $pid . '/lingue/' . $lm], $mancaIn); ?>
        <div class="traduci-sezione" data-traduci-sezione="<?= $sid ?>" data-lingue="<?= Support::e(json_encode($lingueT, JSON_UNESCAPED_UNICODE)) ?>" data-csrf="<?= Support::e(Csrf::token()) ?>" hidden>
          <button type="button" class="btn btn--sm" data-traduci-avvia><?= Icon::svg('globe', 15) ?> <span data-traduci-etichetta><?= t('Traduci questa sezione in') ?> <?= count($mancaIn) ?> <?= t(count($mancaIn) === 1 ? 'lingua' : 'lingue') ?></span></button>
          <div class="traduci-stato" data-traduci-stato hidden role="status" aria-live="polite"><span class="traduci-stato__barra"><i></i></span><p class="small" data-traduci-testo style="margin:0"></p></div>
          <div class="traduci-esito" data-traduci-esito></div>
        </div>
      <?php endif; ?>
    <?php endif; ?>

    <a class="btn btn--ghost phonebtn" href="<?= Support::e(Support::url($src)) ?>" target="_blank" rel="noopener"><?= Icon::svg('eye', 16) ?><?= t('Apri l\'anteprima') ?></a>
  </div>
  <?php include __DIR__ . '/_telefono.php'; ?>
</div>
