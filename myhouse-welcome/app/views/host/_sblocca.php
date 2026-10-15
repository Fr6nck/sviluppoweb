<?php
/* «{Funzione} è compresa in {Plus|Portfolio}» (8B): una finestra, non un errore. La includono le pagine
   del pannello del cliente (layout cms). Si apre quando l'host tocca una funzione che il suo piano non ha
   (un elemento con data-sblocca="foto|sezioni|lingue|firma|strutture", assets/sblocca.js), oppure da
   sola dopo un limite controllato dal server (?sblocca=… nell'indirizzo, o $sblocca dalla rotta).
   Il server continua a controllare i limiti come prima. Il bottone pieno porta al cambio di piano
   (/piano?passa=…: chi è abbonato passa dalla conferma con la differenza da pagare). */
use function MHW\b;
use MHW\{Support, Icon, Plans, SectionCatalog};
$apriSblocca = (string) ($sblocca ?? ($_GET['sblocca'] ?? ''));
$prezzi = [];
foreach (Plans::public() as $pp) {
    if (!in_array($pp['code'], ['plus', 'portfolio'], true)) continue;
    $pvS = ['price_cents' => $pp['price_cents'], 'per_property' => $pp['per_property'], 'extra_price_cents' => $pp['extra_price_cents'],
            'min_quantity' => $pp['min_quantity'], 'extra_tiers' => $pp['extra_tiers'] ?? null];
    $prezzi[$pp['code']] = Plans::perProperty($pvS)
        ? 'Da ' . Support::money(Plans::price($pvS, max(2, (int) $pp['min_quantity'])), (string) $pp['currency']) . ' + IVA / anno per ' . max(2, (int) $pp['min_quantity']) . ' strutture'
        : Plans::priceLabel((int) $pp['price_cents'], (string) $pp['currency']);
}
$nSez = SectionCatalog::quante();
$varianti = [
    'foto' => ['plus', 'Foto e PDF sono compresi in Plus', ['Foto e PDF accanto ai testi, in ogni sezione e nelle righe', "Tutte le $nSez sezioni, senza limite", 'Le statistiche di lettura della guida']],
    'sezioni' => ['plus', 'Con Plus hai tutte le sezioni', ["Tutte le $nSez sezioni del catalogo, senza limite", 'Foto e PDF accanto ai testi', 'La guida in più lingue, con le traduzioni suggerite']],
    'lingue' => ['plus', 'Più lingue sono comprese in Plus', ['Le lingue di base della guida e una lingua in più a scelta', 'Le traduzioni suggerite, da controllare e confermare', "Tutte le $nSez sezioni, con foto e PDF"]],
    'firma' => ['plus', 'Nascondere la firma è compreso in Plus', ['La guida senza «Guida creata con MyHouse Welcome»', "Tutte le $nSez sezioni, con foto e PDF", 'La guida in più lingue, con le traduzioni suggerite']],
    'strutture' => ['portfolio', 'Più strutture con Portfolio', ['Più strutture dallo stesso account, ognuna con la sua guida e il suo QR', 'Tutto quello che c\'è in Plus', 'Le sezioni si copiano da una struttura all\'altra']],
];
$nomiPiano = ['plus' => 'Plus', 'portfolio' => 'Portfolio']; ?>
<dialog class="sblocca" id="sblocca" aria-labelledby="sblocca-titolo"<?= isset($varianti[$apriSblocca]) ? ' data-apri="' . Support::e($apriSblocca) . '" open' : '' ?>>
  <button type="button" class="sblocca__chiudi" data-sblocca-chiudi aria-label="Chiudi"><?= Icon::svg('close', 18, 2) ?></button>
  <?php foreach ($varianti as $k => [$piano, $titoloS, $righe]): ?>
    <div class="sblocca__variante" data-variante="<?= $k ?>"<?= $k === $apriSblocca ? '' : ' hidden' ?>>
      <span class="sblocca__lucchetto" aria-hidden="true"><?= Icon::svg('lock', 24, 1.8) ?></span>
      <h2 class="sblocca__titolo"<?= $k === $apriSblocca || ($apriSblocca === '' && $k === 'foto') ? ' id="sblocca-titolo"' : '' ?>><?= Support::e($titoloS) ?></h2>
      <ul class="sblocca__righe"><?php foreach ($righe as $rg): ?><li><?= Icon::svg('check', 16, 2.2) ?><?= Support::e($rg) ?></li><?php endforeach; ?></ul>
      <?php if (isset($prezzi[$piano])): ?><p class="sblocca__prezzo"><?= Support::e($prezzi[$piano]) ?></p><?php endif; ?>
      <div class="sblocca__azioni">
        <a class="btn" href="<?= b() ?>/piano?passa=<?= $piano ?>">Passa a <?= $nomiPiano[$piano] ?></a>
        <?php if ($k === 'strutture'): ?>
          <a class="btn btn--ghost" href="<?= Support::e(Support::mailto('Informazioni su Portfolio', "Ciao, vorrei sapere di più su Portfolio.\n\nQuante strutture vorrei gestire:")) ?>">Chiedi informazioni</a>
        <?php endif; ?>
        <button type="button" class="btn btn--ghost" data-sblocca-chiudi>Non ora</button>
      </div>
      <?php if ($k === 'strutture'): ?><p class="small muted" style="margin:0">Più di 10 strutture? Chiedi informazioni: ti facciamo una proposta.</p><?php endif; ?>
    </div>
  <?php endforeach; ?>
</dialog>
<script src="<?= MHW\av('/assets/sblocca.js') ?>" defer></script>
