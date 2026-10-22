<?php
/* 9R · La pagina del cambio di piano proposto dall'amministrazione (/proposta/{token}): piano e strutture, importo scontato
   (conguaglio subito, o primo anno dal rinnovo), data, scadenza della proposta, «Accetta il cambio». Si conferma solo da
   cliente, dentro il proprio account: da «Entra come cliente» il bottone non c'è. Niente indicizzazione (nessuna $seoPagina).
   Riceve $p (la proposta), $c (ProposteCambio::conti), $acc (l'account dentro, o null), $suo, $impersona. */
use function MHW\b;
use MHW\{Support, Csrf, Plans};
$title = 'Una proposta per il tuo piano';
$soldi = fn(int $x) => Support::money($x, $c['valuta']);
$sc = (int) $p['sconto_pct'];
$aperta = $p['stato'] === 'inviata'; ?>
<div class="stack stack--lg" style="max-width:720px;margin:0 auto">
  <section class="panel stack" aria-labelledby="prop-titolo">
    <span class="kicker" id="prop-titolo">Una proposta per il tuo piano</span>
    <h1 style="margin:0"><?= Support::e((string) ($c['pv']['name'] ?? '')) ?><?= Plans::perProperty($c['pv']) ? ' · ' . (int) $c['q'] . ' strutture' : '' ?></h1>
    <p style="margin:0"><b style="font-size:40px;line-height:1"><?= Support::e($soldi($c['scontato'])) ?></b> <span class="muted">+ IVA</span>
      <?php if ($sc > 0): ?><s class="muted"><?= Support::e($soldi($c['pieno'])) ?></s> <span class="pillola pillola--ocra">−<?= $sc ?>%</span><?php endif; ?></p>
    <p class="small" style="margin:0"><?= $c['quando'] === 'subito'
        ? 'Parte subito: è la differenza per i ' . (int) $c['giorni'] . ' giorni che restano fino al rinnovo del ' . Support::e(Support::date($c['fine'])) . '. La data di rinnovo non cambia.'
        : 'Parte dal rinnovo del ' . Support::e(Support::date($c['data'])) . ': è il primo anno del piano nuovo. Lo sconto non si somma a quelli di inviti e giudizio: vale il più alto. Fino ad allora resti sul piano di adesso.' ?></p>
    <?php if (trim((string) $p['messaggio']) !== ''): ?><p class="small" style="margin:0;white-space:pre-line"><?= Support::e((string) $p['messaggio']) ?></p><?php endif; ?>
    <?php if (!$aperta): ?>
      <p class="note" role="status"><span><?= Support::e(['accettata' => 'Hai già accettato questa proposta.', 'pagata' => 'Hai già accettato e pagato questa proposta.', 'scaduta' => 'La proposta è scaduta: scrivici e te la rimandiamo.', 'annullata' => 'La proposta non è più valida.'][$p['stato']] ?? 'La proposta non è più aperta.') ?></span></p>
    <?php elseif (!$acc): ?>
      <p class="small muted" style="margin:0">Valida fino al <?= Support::e(Support::date((string) $p['valida_fino'])) ?>. Vale solo con la tua conferma: entra nel tuo account per accettarla.</p>
      <div class="actions"><a class="btn" href="<?= b() ?>/accedi">Entra e conferma</a></div>
    <?php elseif (!$suo): ?>
      <p class="note note--err" role="alert"><span>Questa proposta è per un altro account: esci ed entra con l'account a cui l'abbiamo mandata.</span></p>
    <?php elseif ($impersona): ?>
      <p class="note" role="status"><span>Sei dentro come amministratore («Entra come cliente»): la proposta la conferma solo il cliente.</span></p>
    <?php else: ?>
      <p class="small muted" style="margin:0">Valida fino al <?= Support::e(Support::date((string) $p['valida_fino'])) ?>. Senza la tua conferma non cambia niente.</p>
      <form method="post" action="<?= b() ?>/proposta/<?= Support::e($p['token']) ?>/accetta" class="actions" style="margin:0"><?= Csrf::field() ?>
        <button class="btn"><?= $c['quando'] === 'subito' ? 'Accetta e paga la differenza' : 'Accetta il cambio dal rinnovo' ?> →</button></form>
      <p class="tiny muted" style="margin:0">Regole complete nei <a href="<?= b() ?>/termini">Termini</a>, punto «Cambio di piano».</p>
    <?php endif; ?>
  </section>
</div>
