<?php
/**
 * Tutte le immagini che si possono sostituire, divise come il sito.
 *
 * @var array<string, list<array{chiave:string, posto:array<string,mixed>, img:array<string,mixed>}>> $gruppi
 * @var bool   $scrivibile
 * @var bool   $gd
 * @var string $limite
 */

$ancore = ['Marchio' => 'marchio', 'Le pagine' => 'pagine', 'Le camere' => 'camere'];
$sostituite = 0;
foreach ($gruppi as $voci) {
    foreach ($voci as $v) {
        $sostituite += $v['img']['sostituita'] ? 1 : 0;
    }
}
?>
<p class="adm-intro">Il logo, la rosa dei venti e le fotografie del sito. Scegli un'immagine, carica quella nuova:
   il sito la ritaglia, la alleggerisce e la mette al suo posto. L'originale non si perde: si rimette con un click.</p>

<?php if (!$gd || !$scrivibile): ?>
  <div class="adm-avviso adm-avviso--errore" role="alert">
    <?= icona('attenzione', 18, 'adm-avviso__icona') ?>
    <div>
      <?php if (!$gd): ?>
        <p>Il server non ha la libreria per le immagini (GD): senza, non si possono caricare immagini. Chiedi all'assistenza di Hostinger di attivare l'estensione GD di PHP.</p>
      <?php endif; ?>
      <?php if (!$scrivibile): ?>
        <p>La cartella <code>public/assets/media</code> non è scrivibile. Via FTP creala, se manca, e dalle i permessi 755.</p>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>

<ul class="adm-riassunto" aria-label="In breve">
  <li><strong><?= array_sum(array_map('count', $gruppi)) ?></strong> immagini sostituibili</li>
  <li><strong><?= $sostituite ?></strong> <?= $sostituite === 1 ? 'caricata da te' : 'caricate da te' ?></li>
  <li>File fino a <strong><?= e($limite) ?></strong>, JPG, PNG o WebP</li>
</ul>

<?php foreach ($gruppi as $gruppo => $voci): $ancora = $ancore[$gruppo] ?? 'home'; ?>
  <section class="adm-carta" id="<?= e($ancora) ?>" aria-labelledby="gi-<?= e($ancora) ?>">
    <div class="adm-carta__testa">
      <h2 id="gi-<?= e($ancora) ?>"><?= e($gruppo) ?></h2>
    </div>
    <ul class="adm-immagini<?= $ancora === 'marchio' ? ' adm-immagini--marchio' : '' ?>">
      <?php foreach ($voci as $v):
          $posto = $v['posto'];
          $mini  = anteprima($v['img'], (string) $posto['tipo']);
          $scuro = ($posto['sfondo'] ?? '') === 'scuro';
      ?>
        <li>
          <a class="adm-immagine-voce" href="<?= e(adminUrl('immagini/' . $v['chiave'])) ?>">
            <span class="adm-immagine-voce__cornice adm-immagine-voce__cornice--<?= e((string) $posto['tipo']) ?><?= $scuro ? ' adm-fondo-scuro' : '' ?>">
              <?php if ($mini !== null): ?>
                <img src="<?= e(asset($mini)) ?>" alt="" loading="lazy">
              <?php else: ?>
                <span class="adm-immagine-voce__vuota"><?= icona('piu', 22) ?></span>
              <?php endif; ?>
            </span>
            <span class="adm-immagine-voce__nome"><?= e((string) $posto['nome']) ?></span>
            <span class="adm-immagine-voce__stato">
              <?php if ($v['img']['sostituita']): ?>
                <span class="adm-tipo adm-tipo--prenotazione"><?= icona('spunta', 12) ?>Caricata da te</span>
              <?php elseif (!empty($posto['facoltativa'])): ?>
                <span class="adm-tipo">Facoltativa · vuota</span>
              <?php else: ?>
                <span class="adm-tipo">Originale</span>
              <?php endif; ?>
            </span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
<?php endforeach; ?>
