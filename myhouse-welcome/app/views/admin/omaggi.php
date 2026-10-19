<?php
/* 9D · Amministrazione → Vendite → Codici omaggio: «Nuovo codice» (mesi, scadenza di serie 14 giorni, nota), il codice
   appena creato con i tre bottoni (WhatsApp, Email, Copia il link) e il messaggio pronto, l'elenco con lo stato e «Annulla».
   Riceve $righe (Omaggi::tutti), $nuovo (il codice appena creato), $vecchi, $numeri, $scadenzaSerie. */
use function MHW\b;
use MHW\{Support, Csrf, Icon, Omaggi};
$title = 'Codici omaggio';
$v = $vecchi + ['mesi' => '3', 'scade_il' => $scadenzaSerie, 'nota' => ''];
$pillola = ['valido' => ['Valido', 'pine'], 'usato' => ['Usato', 'ochre'], 'scaduto' => ['Scaduto', 'paper'], 'annullato' => ['Annullato', 'paper']];
$bottoni = function (array $c) {
    $msg = Omaggi::messaggio($c); $link = Omaggi::link($c);
    return '<a class="btn btn--sm" href="https://wa.me/?text=' . rawurlencode($msg) . '" target="_blank" rel="noopener">' . Icon::svg('whatsapp', 15) . 'Manda su WhatsApp</a>'
         // L'email la manda il sito: un link mailto: senza programma di posta predefinito (posta nel browser) non fa niente.
         . '<details class="omaggio-email"><summary class="btn btn--ghost btn--sm">' . Icon::svg('message', 15) . 'Manda per email</summary>'
         . '<form method="post" action="' . b() . '/admin/omaggi/' . (int) $c['id'] . '/email" class="omaggio-email__modulo">' . Csrf::field()
         . '<label class="small" for="om-email-' . (int) $c['id'] . '">Email della persona</label>'
         . '<span class="row" style="gap:6px;flex-wrap:nowrap"><input id="om-email-' . (int) $c['id'] . '" name="email" type="email" required maxlength="190" autocomplete="off" placeholder="nome@esempio.it">'
         . '<button class="btn btn--sm">Manda</button></span>'
         . '<a class="tiny" href="mailto:?subject=' . rawurlencode((int) $c['mesi'] . ' mesi gratis di MyHouse Welcome') . '&amp;body=' . rawurlencode($msg) . '">oppure apri il tuo programma di posta</a></form></details>'
         . '<button type="button" class="btn btn--ghost btn--sm" data-copia="' . Support::e($link) . '" data-copiato="Link copiato" hidden>' . Icon::svg('copy', 15) . 'Copia il link</button>';
}; ?>
<div class="stack stack--lg">
  <div class="stack stack--sm">
    <h1>Codici omaggio.</h1>
    <p class="muted" style="max-width:760px">Da 1 a 12 mesi gratis per una persona sola, solo nuovi clienti. Il cliente inserisce la carta prima di pubblicare: oggi paga 0 €, il primo addebito parte da solo alla fine dei mesi gratis, con gli avvisi a 30 e 7 giorni. Il codice si brucia al pagamento e scade dopo <?= Omaggi::GIORNI ?> giorni (modificabile). Non si somma allo sconto «Invita un amico»; lo sconto del giudizio resta.</p>
  </div>

  <div class="cifre">
    <?php foreach ([['cifra--pino', 'check', 'Codici validi', (string) count(array_filter($righe, fn($r) => $r['stato'] === 'valido')), 'da usare entro la data'],
                    ['cifra--mare', 'people', 'In omaggio adesso', (string) $numeri['in_omaggio'], 'clienti con i mesi gratis in corso'],
                    ['cifra--ocra', 'calendar', 'Primo addebito entro 30 giorni', (string) $numeri['addebito_vicino'], 'avvisati per email e nel pannello']] as [$tono, $ico, $et, $val, $nota]): ?>
      <div class="cifra <?= $tono ?>"><span class="cifra__testa"><span class="cifra__ico"><?= Icon::svg($ico, 17) ?></span><?= $et ?></span>
        <b class="cifra__valore"><?= Support::e($val) ?></b><span class="cifra__nota"><?= Support::e($nota) ?></span></div>
    <?php endforeach; ?>
  </div>

  <div class="grid grid-2" style="align-items:start">
    <form method="post" action="<?= b() ?>/admin/omaggi" class="panel stack" id="nuovo" style="gap:12px"><?= Csrf::field() ?>
      <h2 style="font-size:22px;margin:0">Nuovo codice omaggio</h2>
      <p class="small muted" style="margin:0">Una persona sola, solo nuovi clienti. Si brucia al pagamento. Non si somma allo sconto «Invita un amico».</p>
      <div class="grid grid-2">
        <div class="field" style="margin:0"><label for="om-mesi">Mesi gratis</label>
          <select id="om-mesi" name="mesi"><?php for ($m = 1; $m <= 12; $m++): ?><option value="<?= $m ?>"<?= (int) $v['mesi'] === $m ? ' selected' : '' ?>><?= $m ?> <?= $m === 1 ? 'mese' : 'mesi' ?></option><?php endfor; ?></select></div>
        <div class="field" style="margin:0"><label for="om-scade">Da usare entro</label>
          <input id="om-scade" name="scade_il" type="date" value="<?= Support::e((string) $v['scade_il']) ?>" aria-describedby="om-scade-aiuto">
          <p class="help" id="om-scade-aiuto">Di serie <?= Omaggi::GIORNI ?> giorni da oggi.</p></div>
      </div>
      <div class="field" style="margin:0"><label for="om-nota">Nota interna</label>
        <input id="om-nota" name="nota" type="text" maxlength="255" value="<?= Support::e((string) $v['nota']) ?>" placeholder="Es. Tester – B&amp;B Rocca, Gubbio (conosciuto a fiera)"></div>
      <div class="actions"><button class="btn">Crea il codice</button></div>
    </form>

    <?php if ($nuovo): ?>
    <section class="panel stack omaggio-nuovo" style="gap:10px" aria-labelledby="om-nuovo-titolo">
      <span class="badge badge--pine" style="align-self:flex-start">Valido fino al <?= Support::e(Support::date($nuovo['scade_il'])) ?></span>
      <h2 id="om-nuovo-titolo" class="omaggio-nuovo__codice"><?= Support::e($nuovo['codice']) ?></h2>
      <p class="small muted" style="margin:0"><?= (int) $nuovo['mesi'] ?> mesi gratis · link: <code><?= Support::e(preg_replace('#^https?://#', '', Omaggi::link($nuovo))) ?></code></p>
      <div class="row" style="gap:8px;flex-wrap:wrap"><?= $bottoni($nuovo) ?></div>
      <div class="spiega"><?= Support::e(Omaggi::messaggio($nuovo)) ?></div>
      <p class="tiny muted" style="margin:0">Testo modificabile da <a href="<?= b() ?>/admin/testi#omaggio">Testi e foto</a>.</p>
    </section>
    <?php endif; ?>
  </div>

  <?php if (!$righe): ?><p class="note note--quiet">Ancora nessun codice.</p><?php else: ?>
  <div class="tablewrap"><table class="data resp">
    <thead><tr><th>Codice</th><th>Mesi</th><th>Nota</th><th>Scade</th><th>Stato</th><th><span class="sr-only">Azioni</span></th></tr></thead>
    <tbody><?php foreach ($righe as $c): [$st, $tono] = $pillola[$c['stato']]; ?>
      <tr><td data-l="Codice"><code><?= Support::e($c['codice']) ?></code></td>
        <td data-l="Mesi"><?= (int) $c['mesi'] ?></td>
        <td data-l="Nota" class="small"><?= Support::e($c['nota']) ?></td>
        <td data-l="Scade" class="small"><?= Support::e(Support::date($c['scade_il'])) ?></td>
        <td data-l="Stato"><span class="badge badge--<?= $tono ?>"><?= $st ?><?= $c['stato'] === 'usato' ? ' il ' . Support::e(Support::date($c['usato_at'])) : '' ?></span>
          <?php if ($c['stato'] === 'usato'): ?><br><span class="tiny muted"><?= ($lkO = MHW\Codici::link((int) $c['usato_da_account_id'])) !== '' ? 'usato da ' . $lkO . ' · ' : '' ?><a href="<?= b() ?>/admin/cliente/<?= (int) $c['usato_da_account_id'] ?>"><?= Support::e(trim(explode(' ', trim((string) $c['usato_da_nome']))[0] ?? '') ?: $c['usato_da_email']) ?></a><?= !empty($c['omaggio_fino']) ? ' · 1° addebito ' . Support::e(Support::date($c['omaggio_fino'])) : '' ?></span><?php endif; ?></td>
        <td class="az"><?php if ($c['stato'] === 'valido'): ?><div class="row" style="gap:6px;flex-wrap:wrap"><?= $bottoni($c) ?>
          <form method="post" action="<?= b() ?>/admin/omaggi/<?= (int) $c['id'] ?>/annulla" style="margin:0" onsubmit="return confirm('Annullare questo codice? Non si potrà più usare.')"><?= Csrf::field() ?><button class="btn btn--sm btn--danger">Annulla</button></form></div><?php endif; ?></td></tr>
    <?php endforeach; ?></tbody></table></div>
  <?php endif; ?>
</div>
