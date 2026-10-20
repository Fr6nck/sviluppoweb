<?php use function MHW\t, MHW\tf;
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
        ? tf('Da {prezzo} + IVA / anno per {n} strutture', ['{prezzo}' => Support::money(Plans::price($pvS, max(2, (int) $pp['min_quantity'])), (string) $pp['currency']), '{n}' => max(2, (int) $pp['min_quantity'])])
        : tf('{prezzo} + IVA / anno', ['{prezzo}' => Support::money((int) $pp['price_cents'], (string) $pp['currency'])]);
}
$nSez = SectionCatalog::quante();
$nn = ['{n}' => $nSez, '{extra}' => count(MHW\I18n::EXTRA)];
$varianti = [
    'foto' => ['plus', t('Foto e PDF sono compresi in Plus'), [t('Foto e PDF accanto ai testi, in ogni sezione e nelle righe'), tf('Tutte le {n} sezioni, senza limite', $nn), t('Le statistiche di lettura della guida')]],
    'sezioni' => ['plus', t('Con Plus hai tutte le sezioni'), [tf('Tutte le {n} sezioni del catalogo, senza limite', $nn), t('Foto e PDF accanto ai testi'), t('La guida in più lingue, con le traduzioni suggerite')]],
    'lingue' => ['plus', t('Più lingue sono comprese in Plus'), [tf('Sei lingue: la tua, le altre tra italiano, inglese, francese, tedesco e spagnolo, e una a scelta tra {extra}', $nn), t('Le traduzioni suggerite, da controllare e confermare'), tf('Tutte le {n} sezioni, con foto e PDF', $nn)]],
    'firma' => ['plus', t('Nascondere la firma è compreso in Plus'), [t('La guida senza «Guida creata con MyHouse Welcome»'), tf('Tutte le {n} sezioni, con foto e PDF', $nn), t('La guida in più lingue, con le traduzioni suggerite')]],
    'strutture' => ['portfolio', t('Più strutture con Portfolio'), [t('Più strutture dallo stesso account, ognuna con la sua guida e il suo QR'), t('Tutto quello che c\'è in Plus'), t('Le sezioni si copiano da una struttura all\'altra')]],
];
$nomiPiano = ['plus' => 'Plus', 'portfolio' => 'Portfolio']; ?>
<dialog class="sblocca" id="sblocca" aria-labelledby="sblocca-titolo"<?= isset($varianti[$apriSblocca]) ? ' data-apri="' . Support::e($apriSblocca) . '" open' : '' ?>>
  <button type="button" class="sblocca__chiudi" data-sblocca-chiudi aria-label="<?= MHW\Support::e(t('Chiudi')) ?>"><?= Icon::svg('close', 18, 2) ?></button>
  <?php foreach ($varianti as $k => [$piano, $titoloS, $righe]): ?>
    <div class="sblocca__variante" data-variante="<?= $k ?>"<?= $k === $apriSblocca ? '' : ' hidden' ?>>
      <span class="sblocca__lucchetto" aria-hidden="true"><?= Icon::svg('lock', 24, 1.8) ?></span>
      <h2 class="sblocca__titolo"<?= $k === $apriSblocca || ($apriSblocca === '' && $k === 'foto') ? ' id="sblocca-titolo"' : '' ?>><?= Support::e($titoloS) ?></h2>
      <ul class="sblocca__righe"><?php foreach ($righe as $rg): ?><li><?= Icon::svg('check', 16, 2.2) ?><?= Support::e($rg) ?></li><?php endforeach; ?></ul>
      <?php if (isset($prezzi[$piano])): ?><p class="sblocca__prezzo"><?= Support::e($prezzi[$piano]) ?></p><?php endif; ?>
      <div class="sblocca__azioni">
        <a class="btn" href="<?= b() ?>/piano?passa=<?= $piano ?>"><?= Support::e(tf('Passa a {piano}', ['{piano}' => $nomiPiano[$piano]])) ?></a>
        <?php if ($k === 'strutture'): ?>
          <a class="btn btn--ghost" href="<?= Support::e(Support::mailto(t('Informazioni su Portfolio'), t("Ciao, vorrei sapere di più su Portfolio.\n\nQuante strutture vorrei gestire:"))) ?>"><?= t('Chiedi informazioni') ?></a>
        <?php endif; ?>
        <button type="button" class="btn btn--ghost" data-sblocca-chiudi><?= t('Non ora') ?></button>
      </div>
      <?php if ($k === 'strutture'): ?><p class="small muted" style="margin:0"><?= t('Più di 10 strutture? Chiedi informazioni: ti facciamo una proposta.') ?></p><?php endif; ?>
    </div>
  <?php endforeach; ?>
</dialog>
<script src="<?= MHW\av('/assets/sblocca.js') ?>" defer></script>
