<?php
/**
 * Un'immagine del sito: com'è adesso, dove compare, e il modulo per
 * sostituirla, descriverla o tornare all'originale.
 *
 * @var string $chiave
 * @var array<string,mixed> $posto
 * @var array<string,mixed> $img        quello che mostra il sito adesso
 * @var array<string,mixed>|null $registrata  il file caricato, se c'è
 * @var list<string> $lingue
 * @var bool   $scrivibile
 * @var bool   $gd
 * @var string $limite
 */
use ArcoDelVento\Support\Csrf;

$tipo   = (string) $posto['tipo'];
$camera = $posto['camera'] ?? null;
$scuro  = ($posto['sfondo'] ?? '') === 'scuro';
$azione = adminUrl('immagini/' . $chiave);
$vedi   = anteprima($img, $tipo);
// La foto grande, non la miniatura: qui si guarda se è venuta bene.
if ($tipo === 'foto' && is_string($img['src']) && !str_ends_with($img['src'], '.svg')) {
    foreach (['.webp', '.jpg'] as $estensione) {
        if (assetEsiste($img['src'] . $estensione)) {
            $vedi = $img['src'] . $estensione;
            break;
        }
    }
}
$rapporti = array_map(static fn (string $r): string => str_replace('x', ':', $r), (array) ($posto['rapporti'] ?? []));
$conTesti = $tipo === 'foto' && $camera === null && $registrata !== null;
$chiamaCredito = !empty($posto['didascalia']) ? 'Didascalia' : (!empty($posto['credito']) ? 'Didascalia o credito della foto' : null);
?>
<p class="adm-intro"><?= e((string) $posto['dove']) ?></p>

<div class="adm-due">
  <section class="adm-carta" aria-labelledby="i-adesso">
    <div class="adm-carta__testa">
      <h2 id="i-adesso">Adesso sul sito</h2>
      <?php if ($img['sostituita']): ?>
        <span class="adm-tipo adm-tipo--prenotazione"><?= icona('spunta', 12) ?>Caricata da te</span>
      <?php elseif ($vedi !== null): ?>
        <span class="adm-tipo">Originale</span>
      <?php endif; ?>
    </div>

    <?php if ($vedi === null): ?>
      <div class="adm-vuoto">
        <?= icona('immagine', 28) ?>
        <p>Nessuna immagine: <?= !empty($posto['facoltativa']) ? 'è facoltativa, e finché manca sul sito non compare niente.' : 'carica la prima.' ?></p>
      </div>
    <?php elseif ($tipo === 'icona'): ?>
      <div class="adm-vetrina adm-vetrina--icona">
        <div class="adm-vetrina__fondo">
          <?php foreach ([96 => 72, 48 => 32] as $file => $misura): ?>
            <img src="<?= e(asset($img['src'] . '-' . $file . '.png')) ?>" alt="" width="<?= $misura ?>" height="<?= $misura ?>">
          <?php endforeach; ?>
          <span class="adm-vetrina__scheda"><img src="<?= e(asset($img['src'] . '-48.png')) ?>" alt="" width="16" height="16"> Arco del Vento</span>
        </div>
        <div class="adm-vetrina__fondo adm-fondo-scuro">
          <img src="<?= e(asset($img['src'] . '-96.png')) ?>" alt="" width="72" height="72">
        </div>
      </div>
      <p class="adm-nota">In testata, nel piè di pagina e come icona della scheda del browser.</p>
    <?php elseif ($tipo === 'logo'): ?>
      <div class="adm-vetrina">
        <div class="adm-vetrina__fondo<?= $scuro ? ' adm-fondo-scuro' : '' ?>">
          <img src="<?= e(asset($vedi)) ?>" alt="Il logotipo com'è adesso" height="96" width="<?= (int) round(96 * (($img['w'] ?? 111) / max(1, $img['h'] ?? 66))) ?>">
        </div>
      </div>
    <?php else: ?>
      <figure class="adm-vetrina adm-vetrina--foto">
        <img src="<?= e(asset($vedi)) ?>" alt="<?= e((string) ($img['alt'] ?? '')) ?>" loading="eager">
        <?php if ($rapporti !== []): ?>
          <figcaption class="adm-nota">Il sito la usa tagliata in <?= e(implode(', ', $rapporti)) ?>.</figcaption>
        <?php endif; ?>
      </figure>
    <?php endif; ?>

    <?php if ($registrata !== null): ?>
      <p class="adm-tenue adm-caricata">
        Caricata il <?= e(dataOra((string) ($registrata['caricata'] ?? ''))) ?><?php if (!empty($registrata['nome_originale'])): ?>
        · <?= e((string) $registrata['nome_originale']) ?><?php endif; ?>
      </p>
      <?php if (!empty($registrata['avviso'])): ?>
        <p class="adm-avviso adm-avviso--attenzione"><?= icona('attenzione', 18, 'adm-avviso__icona') ?><span><?= e((string) $registrata['avviso']) ?></span></p>
      <?php endif; ?>
    <?php endif; ?>
  </section>

  <section class="adm-carta" aria-labelledby="i-carica">
    <div class="adm-carta__testa">
      <h2 id="i-carica"><?= $vedi === null ? 'Carica l\'immagine' : 'Sostituisci' ?></h2>
    </div>
    <?php if (!$gd || !$scrivibile): ?>
      <p class="adm-avviso adm-avviso--errore"><?= icona('attenzione', 18, 'adm-avviso__icona') ?>
        <span><?= !$gd ? 'Il server non ha la libreria per le immagini (GD): chiedi all\'assistenza di Hostinger di attivarla.' : 'La cartella public/assets/media non è scrivibile: via FTP dalle i permessi 755.' ?></span></p>
    <?php else: ?>
      <form method="post" action="<?= e($azione) ?>" enctype="multipart/form-data" class="adm-carica" data-carica>
        <?= Csrf::field() ?>
        <input type="hidden" name="azione" value="carica">
        <input class="adm-zona__campo" type="file" id="immagine" name="immagine" accept="image/jpeg,image/png,image/webp" required aria-describedby="immagine-consiglio">
        <label class="adm-zona" for="immagine" data-carica-zona>
          <span class="adm-zona__icona" aria-hidden="true"><?= icona('carica', 22) ?></span>
          <span class="adm-zona__titolo">Scegli un file<span class="adm-zona__trascina"> o trascinalo qui</span></span>
          <span class="adm-zona__nota">JPG, PNG o WebP, fino a <?= e($limite) ?></span>
          <img class="adm-zona__anteprima" alt="" hidden data-carica-anteprima>
          <span class="adm-zona__file" data-carica-nome aria-live="polite"></span>
        </label>
        <p class="adm-aiuto" id="immagine-consiglio"><?= e((string) $posto['consiglio']) ?></p>
        <button type="submit" class="adm-bottone"><?= icona('carica', 17) ?>Carica e pubblica</button>
      </form>
    <?php endif; ?>

    <?php if ($camera !== null): ?>
      <p class="adm-nota adm-spazio">La descrizione della foto, per chi non la vede, si cambia nella
        <a href="<?= e(adminUrl('camere/' . rawurlencode((string) $camera))) ?>">pagina della camera</a>.</p>
    <?php endif; ?>
  </section>
</div>

<?php if ($conTesti): ?>
  <section class="adm-carta" aria-labelledby="i-testi">
    <div class="adm-carta__testa">
      <h2 id="i-testi">Che cosa mostra</h2>
    </div>
    <form method="post" action="<?= e($azione) ?>" class="adm-modulo" novalidate>
      <?= Csrf::field() ?>
      <input type="hidden" name="azione" value="testi">
      <fieldset class="adm-campo">
        <legend>Descrizione per chi non vede l'immagine</legend>
        <?php foreach ($lingue as $lingua): ?>
          <div class="adm-lingua">
            <label for="alt-<?= e($lingua) ?>"><span class="adm-lingua__sigla"><?= e(strtoupper($lingua)) ?></span></label>
            <input type="text" id="alt-<?= e($lingua) ?>" name="alt[<?= e($lingua) ?>]" maxlength="250"
                   value="<?= e((string) ($registrata['alt'][$lingua] ?? '')) ?>">
          </div>
        <?php endforeach; ?>
        <p class="adm-aiuto">Una frase semplice: «La valle umbra vista dalla finestra al tramonto». Se lasci vuoto,
           i lettori di schermo la saltano.</p>
      </fieldset>
      <?php if ($chiamaCredito !== null): ?>
        <fieldset class="adm-campo">
          <legend><?= e($chiamaCredito) ?></legend>
          <?php foreach ($lingue as $lingua): ?>
            <div class="adm-lingua">
              <label for="credito-<?= e($lingua) ?>"><span class="adm-lingua__sigla"><?= e(strtoupper($lingua)) ?></span></label>
              <input type="text" id="credito-<?= e($lingua) ?>" name="credito[<?= e($lingua) ?>]" maxlength="160"
                     value="<?= e((string) ($registrata['credito'][$lingua] ?? '')) ?>">
            </div>
          <?php endforeach; ?>
          <p class="adm-aiuto">Compare sotto la foto. Se la foto non è tua, scrivi di chi è: «Foto di …».
             Vuoto: sotto la foto non compare niente.</p>
        </fieldset>
      <?php endif; ?>
      <button type="submit" class="adm-bottone">Salva la descrizione</button>
    </form>
  </section>
<?php endif; ?>

<?php if ($registrata !== null): ?>
  <details class="adm-pericolo">
    <summary><?= !empty($posto['facoltativa']) ? 'Togli questa immagine' : 'Torna all\'immagine originale' ?></summary>
    <form method="post" action="<?= e($azione) ?>" class="adm-modulo">
      <?= Csrf::field() ?>
      <input type="hidden" name="azione" value="ripristina">
      <p><?= !empty($posto['facoltativa'])
          ? 'L\'immagine sparisce dal sito e il file caricato si cancella.'
          : 'Sul sito torna l\'immagine che c\'era all\'inizio, e il file caricato si cancella.' ?></p>
      <button type="submit" class="adm-bottone adm-bottone--piatto"><?= icona('ripristina', 17) ?><?= !empty($posto['facoltativa']) ? 'Togli l\'immagine' : 'Ripristina l\'originale' ?></button>
    </form>
  </details>
<?php endif; ?>

<p><a class="adm-link" href="<?= e(adminUrl('immagini')) ?>"><?= icona('freccia-sinistra', 15) ?> Tutte le immagini</a></p>
