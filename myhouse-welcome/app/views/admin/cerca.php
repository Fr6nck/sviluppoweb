<?php
/* 9I · Il risultato di «Cerca» quando i clienti trovati sono più di uno (con uno solo si apre subito la scheda):
   nome, email, codice, piano, stato e perché è uscito. Riceve $q, $righe. */
use function MHW\b;
use MHW\Support;
$title = 'Cerca'; ?>
<div class="stack stack--lg">
  <div class="stack stack--sm">
    <h1>Cerca.</h1>
    <p class="muted" style="max-width:760px">Codice cliente, struttura o pagamento (anche scritti male), email, nome, telefono, struttura, città, CIN, indirizzo della guida, partita IVA o codice fiscale, ID di Stripe, codice sconto, omaggio o d'invito. Con un solo risultato si apre subito la scheda.</p>
  </div>
  <form method="get" action="<?= b() ?>/admin/cerca" class="row cerca-pagina" role="search" style="gap:8px;flex-wrap:wrap">
    <label for="cerca-pagina" class="sr-only">Cerca un cliente</label>
    <input id="cerca-pagina" name="q" type="search" value="<?= Support::e($q) ?>" autocomplete="off" spellcheck="false" style="flex:1;min-width:220px">
    <button class="btn">Cerca</button>
  </form>
  <?php if ($q === ''): ?>
    <p class="note note--quiet">Scrivi qualcosa da cercare.</p>
  <?php elseif (!$righe): ?>
    <p class="note" role="status">Nessun cliente con «<?= Support::e($q) ?>».</p>
  <?php else: ?>
    <p class="small muted" role="status"><?= count($righe) ?> clienti con «<?= Support::e($q) ?>»<?= count($righe) >= 50 ? ' (i primi 50)' : '' ?>.</p>
    <div class="tablewrap"><table class="data resp">
      <thead><tr><th>Cliente</th><th>Codice</th><th>Piano</th><th>Stato</th><th>Perché</th></tr></thead>
      <tbody><?php foreach ($righe as $r): $href = b() . '/admin/cliente/' . (int) $r['id'] . ($r['evidenzia'] !== '' ? '?evidenzia=' . rawurlencode($r['evidenzia']) . '#' . rawurlencode($r['evidenzia']) : ''); ?>
        <tr><td data-l="Cliente"><a href="<?= Support::e($href) ?>"><b><?= Support::e($r['name'] ?: '—') ?></b></a><br><span class="small muted"><?= Support::e($r['email']) ?></span></td>
          <td data-l="Codice"><code><?= Support::e((string) $r['code']) ?></code></td>
          <td data-l="Piano"><?= Support::e($r['piano']) ?></td>
          <td data-l="Stato" class="small"><?= Support::e($r['stato']) ?></td>
          <td data-l="Perché" class="small"><?= Support::e(implode(' · ', $r['perche'])) ?></td></tr>
      <?php endforeach; ?></tbody></table></div>
  <?php endif; ?>
</div>
