<?php use function MHW\b; use MHW\{Support, Csrf, Demo, Icon}; $title = 'Clienti'; $nav = 'clienti'; ?>

<div class="spread">
  <h1>Clienti.</h1>
  <form method="get" class="search">
    <span class="sr-only"><label for="q">Cerca un cliente</label></span>
    <?= Icon::svg('search', 16, 1.9) ?>
    <input id="q" name="q" type="search" placeholder="Nome, email o struttura"
           value="<?= Support::e($cerca) ?>">
  </form>
</div>

<div class="stack" style="margin-top:24px;gap:8px">
  <div class="tablehead"><span>Cliente</span><span>Piano</span><span>Stato</span><span></span></div>

  <?php foreach ($rows as $row): ?>
    <div class="tablerow">
      <div class="stack" style="gap:3px;min-width:0">
        <a href="<?= b() ?>/admin/cliente/<?= (int) $row['account_id'] ?>"
           style="font-size:16px;font-weight:500;letter-spacing:-.3px;color:var(--ink)">
          <?= Support::e($row['name'] ?: $row['email']) ?></a>
        <span class="small muted">
          <?= $row['struttura'] ? Support::e($row['struttura']) . ($row['citta'] ? ' · ' . Support::e($row['citta']) : '')
                                : Support::e($row['email']) ?></span>
      </div>
      <span><?= Support::e($row['plan']) ?><?= $row['plan_version'] ? ' <span class="muted small">v' . (int) $row['plan_version'] . '</span>' : '' ?></span>
      <span class="badge badge--<?= Support::e($row['stato'][1]) ?>" style="justify-self:start">
        <?= Support::e($row['stato'][0]) ?></span>
      <form method="post" action="<?= b() ?>/admin/entra/<?= (int) $row['user_id'] ?>"><?= Csrf::field() ?>
        <button class="btn btn--ghost btn--sm"><?= Icon::svg('eye', 15) ?>Entra come <?= Support::e($row['name'] ? 'lui/lei' : 'cliente') ?></button>
      </form>
    </div>
  <?php endforeach; ?>

  <?php if (!$rows): ?>
    <p class="note note--quiet"><?= $cerca === '' ? 'Nessun cliente ancora.' : 'Nessun cliente corrisponde alla ricerca.' ?></p>
  <?php endif; ?>
</div>

<p class="note" style="margin-top:28px;max-width:720px"><?= Icon::svg('warning', 19) ?>
  <span>Entrare come un cliente non richiede la sua password e non la mostra mai.
    Ogni ingresso e ogni uscita restano scritti nel registro.</span></p>

<section class="panel" style="margin-top:28px;max-width:720px">
  <div class="spread spread--mid">
    <div class="stack stack--sm">
      <h2 style="font-size:22px">Clienti di esempio</h2>
      <p class="muted small">Tre host finti con guide vere, foto e statistiche: servono a vedere il
        prodotto pieno. Entrano tutti con la password <strong><?= Support::e(Demo::PASSWORD) ?></strong>.</p>
    </div>
    <?php if ($esempi): ?>
      <form method="post" action="<?= b() ?>/admin/dati-esempio"
            onsubmit="return confirm('Eliminare i tre clienti di esempio, con le loro guide e le loro foto?')">
        <?= Csrf::field() ?>
        <input type="hidden" name="cosa" value="elimina">
        <button class="btn btn--danger btn--sm">Eliminali</button>
      </form>
    <?php else: ?>
      <form method="post" action="<?= b() ?>/admin/dati-esempio"><?= Csrf::field() ?>
        <input type="hidden" name="cosa" value="crea">
        <button class="btn btn--ghost btn--sm">Creali</button>
      </form>
    <?php endif; ?>
  </div>
  <?php if ($esempi): ?>
    <p class="note" style="margin-top:18px"><?= Icon::svg('warning', 19) ?>
      <span>La loro password è scritta qui sopra e nella documentazione:
        <strong>toglieteli prima di aprire il sito al pubblico</strong>.</span></p>
  <?php endif; ?>
</section>
