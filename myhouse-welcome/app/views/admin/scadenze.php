<?php
/* Amministrazione → Scadenze: chi si rinnova, chi scade, chi è già scaduto da poco.
   Per ognuno l'ultimo avviso mandato e il prossimo che parte da solo; «Manda il promemoria»
   lo manda subito, a uno o a più clienti insieme (il testo dipende dallo stato). */
use function MHW\b;
use MHW\{Support, Csrf, Icon, Richiami};
$title = 'Scadenze';
$dentro = array_filter($righe, fn($x) => !$x['finito']);
$conta = fn(callable $f) => count(array_filter($righe, $f));
$incasso = array_sum(array_map(fn($x) => $x['automatico'] ? $x['importo'] : 0, $righe)); ?>
<div class="stack stack--lg">
  <div class="spread spread--mid">
    <div class="stack stack--sm">
      <h1>Scadenze.</h1>
      <p class="muted">Gli abbonamenti che finiscono nei prossimi <?= (int) $giorni ?> giorni e quelli scaduti da meno di 30. Importi IVA esclusa.</p>
    </div>
    <div class="row" style="gap:8px">
      <form method="get" class="row" style="gap:8px;align-items:center">
        <label for="giorni" class="small">Prossimi</label>
        <select id="giorni" name="giorni" onchange="this.form.submit()">
          <?php foreach ([30, 60, 90, 180] as $g): ?><option value="<?= $g ?>"<?= $g === $giorni ? ' selected' : '' ?>><?= $g ?> giorni</option><?php endforeach; ?>
        </select>
        <noscript><button class="btn btn--ghost btn--sm">Mostra</button></noscript>
      </form>
      <a class="btn btn--ghost btn--sm" href="<?= b() ?>/admin/scadenze?giorni=<?= (int) $giorni ?>&amp;formato=csv">Esporta CSV</a>
    </div>
  </div>

  <div class="cifre cifre--4">
    <?php foreach ([['cifra--pino', 'card', 'Si rinnovano da soli', $conta(fn($x) => $x['automatico']), 'incasso atteso ' . Support::money($incasso)],
                    ['cifra--ocra', 'clock', 'Scadono senza rinnovo', $conta(fn($x) => !$x['automatico'] && !$x['finito']), 'rinnovo disattivato o attivati dallo staff'],
                    [$conta(fn($x) => $x['giorni'] <= 7 && !$x['finito']) ? 'cifra--rosa' : 'cifra--carta', 'warning', 'Entro 7 giorni', $conta(fn($x) => $x['giorni'] <= 7 && !$x['finito']), 'da guardare adesso'],
                    [$conta(fn($x) => $x['finito']) ? 'cifra--rosa' : 'cifra--carta', 'ban', 'Scaduti da poco', $conta(fn($x) => $x['finito']), 'ultimi 30 giorni, guide offline']] as [$tono, $ico, $et, $val, $nota]): ?>
      <div class="cifra <?= $tono ?>"><span class="cifra__testa"><span class="cifra__ico"><?= Icon::svg($ico, 17) ?></span><?= Support::e($et) ?></span>
        <b class="cifra__valore"><?= (int) $val ?></b><span class="cifra__nota"><?= Support::e($nota) ?></span></div>
    <?php endforeach; ?>
  </div>

  <details class="panel stack" style="gap:8px">
    <summary style="cursor:pointer;font-weight:600">Cosa parte da solo</summary>
    <ul class="stack small" style="gap:6px;margin:8px 0 0;padding-left:20px">
      <li><b>Si rinnova da solo</b> (Stripe, rinnovo automatico): un avviso 30 giorni prima, con il prezzo del rinnovo, lo sconto inviti e l'eventuale cambio di piano.</li>
      <li><b>Non si rinnova</b> (rinnovo disattivato o abbonamento dello staff): un avviso 30 giorni prima e uno 7 giorni prima, poi un'email il giorno dopo la fine se la guida è offline.</li>
      <li>Ogni avviso parte una volta sola. Chi ha scelto «non mandarmene più» non lo riceve, nemmeno a mano: scrivigli tu.</li>
      <li>Il controllo gira mentre qualcuno usa il sito, al massimo ogni 15 minuti<?= $cron ? ', e con il cron impostato' : '' ?>.
        Ultimo giro: <b><?= $giro !== '' ? Support::e(Support::date($giro)) . ' ' . Support::e(substr($giro, 11, 5)) . ' UTC' : 'mai' ?></b>.</li>
    </ul>
  </details>

  <?php if (!$righe): ?>
    <p class="muted">Nessun abbonamento scade nei prossimi <?= (int) $giorni ?> giorni.</p>
  <?php else: ?>
  <form method="post" action="<?= b() ?>/admin/scadenze/promemoria" class="stack"><?= Csrf::field() ?>
    <input type="hidden" name="torna" value="/admin/scadenze?giorni=<?= (int) $giorni ?>">
    <div class="tablewrap"><table class="data scadenze">
      <thead><tr><th scope="col"><span class="sr-only">Scegli</span></th><th scope="col">Cliente</th><th scope="col">Piano</th><th scope="col">Scadenza</th>
        <th scope="col">Rinnovo</th><th scope="col">Avvisi</th><th scope="col"></th></tr></thead>
      <tbody>
      <?php foreach ($righe as $x): $sid = (int) $x['id']; $bloccato = in_array($x['automatico'] ? 'rinnovo' : 'scadenza', $x['optout'], true); ?>
        <tr<?= $x['finito'] ? ' class="scadenze__finita"' : '' ?>>
          <td><input type="checkbox" name="sub[]" value="<?= $sid ?>" aria-label="Scegli <?= Support::e($x['cliente'] ?: $x['email']) ?>"<?= $bloccato ? ' disabled' : '' ?>></td>
          <td><a href="<?= b() ?>/admin/cliente/<?= (int) $x['account_id'] ?>"><b><?= Support::e($x['cliente'] ?: '—') ?></b></a><br><span class="small muted"><?= Support::e($x['email']) ?></span></td>
          <td><?= Support::e($x['piano']) ?><?= (int) ($x['quantity'] ?? 1) > 1 ? ' · ' . (int) $x['quantity'] . ' strutture' : '' ?>
            <?php if (!empty($x['next_package_version_id']) && ($np = MHW\Plans::version((int) $x['next_package_version_id']))): ?><br><span class="tiny muted">poi <?= Support::e($np['name']) ?></span><?php endif; ?></td>
          <td style="white-space:nowrap"><?= Support::e(Support::date($x['current_period_end'])) ?><br>
            <span class="tiny <?= $x['giorni'] <= 7 ? '' : 'muted' ?>"><?= $x['finito'] ? 'scaduto da ' . abs($x['giorni']) . ' g' : ($x['giorni'] === 0 ? 'oggi' : 'tra ' . $x['giorni'] . ' g') ?></span></td>
          <td><span class="badge badge--<?= $x['stato'][1] ?>"><?= Support::e($x['stato'][0]) ?></span>
            <?php if ($x['automatico']): ?><br><span class="small"><?= Support::e(Support::money($x['importo'])) ?></span><?= $x['sconto'] ? ' <span class="tiny muted">−' . (int) $x['sconto'] . '% inviti</span>' : '' ?><?php endif; ?></td>
          <td class="small">
            <?php if ($x['avviso']): ?>Ultimo: <?= Support::e(Support::date($x['avviso']['sent_at'])) ?><?= $x['avviso']['manuale'] ? ' <span class="tiny muted">a mano</span>' : '' ?><?php else: ?><span class="muted">Nessuno finora</span><?php endif; ?>
            <?php if ($bloccato): ?><br><span class="tiny">Non vuole avvisi</span><?php elseif ($x['prossimo']): ?><br><span class="tiny muted">Prossimo da solo: <?= Support::e(Support::date($x['prossimo'])) ?></span><?php endif; ?></td>
          <td><button class="btn btn--ghost btn--sm" name="solo" value="<?= $sid ?>"<?= $bloccato ? ' disabled' : '' ?>>Manda il promemoria</button></td>
        </tr>
      <?php endforeach; ?>
      </tbody></table></div>
    <div class="actions"><button class="btn btn--sm">Manda il promemoria ai clienti scelti</button>
      <span class="small muted">Il testo cambia da solo: avviso di rinnovo, di scadenza o «la tua guida è offline».</span></div>
  </form>
  <?php endif; ?>
</div>
