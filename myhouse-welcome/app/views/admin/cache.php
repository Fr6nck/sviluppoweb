<?php
/* Amministrazione → Sistema → Cache: ogni cache con la sua misura, l'ultima pulizia e il bottone per svuotarla;
   «Svuota tutto» in cima (codice, foto ridotte, foglio di stile e script, file SEO). La memoria delle traduzioni
   sta a parte: di serie si tolgono solo le frasi mai riusate, tutta solo con la conferma. Riceve $voci (Cache::stato). */
use function MHW\b;
use MHW\{Support, Csrf, Cache};
$title = 'Cache';
$spiega = [
    'codice' => 'Dopo un caricamento via FTP il server può tenersi per qualche minuto il codice vecchio: svuotala se una modifica non si vede.',
    'immagini' => 'Le foto in versione leggera delle schede in home e delle guide dei nostri host. Svuotala se hai sostituito una foto e si vede ancora quella di prima.',
    'risorse' => 'Obbliga tutti i browser a riscaricare il foglio di stile e gli script. Le foto della landing (assets/foto) non cambiano nome: per quelle basta ricaricare la pagina.',
    'seo' => 'Scrive di nuovo robots.txt, sitemap.xml, llms.txt e llms-full.txt accanto a index.php, con i prezzi e i testi di adesso.',
];
$bottone = ['codice' => 'Svuota OPcache', 'immagini' => 'Svuota le foto ridotte', 'risorse' => 'Forza il ricaricamento', 'seo' => 'Riscrivi i file']; ?>
<div class="stack stack--lg" style="max-width:900px">
  <div class="stack stack--sm">
    <h1>Cache.</h1>
    <p class="muted">Quello che il sito tiene da parte per essere veloce. Svuotare non cancella niente dei clienti: tutto si rifà da solo alla prima visita.</p>
  </div>
  <section class="panel stack" style="gap:10px">
    <span class="kicker">Tutto insieme</span>
    <p class="small" style="margin:0">Codice PHP, foto ridotte, foglio di stile e script, file per motori e assistenti. Da fare dopo ogni aggiornamento caricato via FTP. La memoria delle traduzioni resta.</p>
    <form method="post" action="<?= b() ?>/admin/cache/tutto" style="margin:0"><?= Csrf::field() ?><button class="btn btn--sm">Svuota tutto</button></form>
  </section>
  <?php foreach (Cache::TUTTO as $k): $v = $voci[$k]; ?>
    <section class="panel stack" id="<?= $k ?>" style="gap:8px">
      <h2 style="font-size:20px;margin:0"><?= Support::e($v['nome']) ?></h2>
      <p class="small" style="margin:0"><?= Support::e($v['dettaglio']) ?></p>
      <p class="small muted" style="margin:0"><?= Support::e($spiega[$k]) ?><?= $v['ultima'] ? ' Ultima volta: ' . Support::e(Support::date($v['ultima'])) . '.' : '' ?></p>
      <?php if ($v['possibile']): ?>
        <form method="post" action="<?= b() ?>/admin/cache/<?= $k ?>" style="margin:0"><?= Csrf::field() ?><button class="btn btn--ghost btn--sm"><?= Support::e($bottone[$k]) ?></button></form>
      <?php endif; ?>
    </section>
  <?php endforeach; $v = $voci['traduzioni']; ?>
  <section class="panel stack" id="traduzioni" style="gap:8px">
    <h2 style="font-size:20px;margin:0"><?= Support::e($v['nome']) ?></h2>
    <p class="small" style="margin:0"><?= Support::e($v['dettaglio']) ?></p>
    <p class="small muted" style="margin:0">Le frasi già tradotte da Amazon: se un cliente traduce di nuovo la stessa frase non si paga. Non entra in «Svuota tutto».
      Di notte si tolgono da sole quelle mai riusate da più di 12 mesi.<?= $v['ultima'] ? ' Ultima pulizia a mano: ' . Support::e(Support::date($v['ultima'])) . '.' : '' ?></p>
    <?php if ($v['possibile']): ?>
      <form method="post" action="<?= b() ?>/admin/cache/traduzioni" style="margin:0"><?= Csrf::field() ?><button class="btn btn--ghost btn--sm">Togli le frasi mai riusate</button></form>
      <form method="post" action="<?= b() ?>/admin/cache/traduzioni" class="stack" style="gap:8px;margin:0"><?= Csrf::field() ?><input type="hidden" name="tutte" value="1">
        <label class="check small"><input type="checkbox" name="conferma" value="1"> <span>Svuoto tutta la memoria: so che le prossime traduzioni si pagano di nuovo.</span></label>
        <div class="actions"><button class="btn btn--ghost btn--sm">Svuota tutta la memoria</button></div></form>
    <?php endif; ?>
  </section>
</div>
