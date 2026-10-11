<?php
/**
 * I testi del sito: prima la ricerca («dove si cambia questa frase?»), poi le
 * sezioni in tre famiglie — le pagine, le parti comuni, i testi di servizio.
 *
 * @var array<string, array{nome:string, descrizione:string, sezioni:list<array>}> $famiglie
 * @var string $cerca
 * @var list<array> $risultati
 */
?>
<p class="adm-intro">I testi del sito, in italiano e in inglese, divisi come le pagine.
   In ogni pagina trovi prima i <strong>testi importanti</strong>, poi — chiusi — quelli secondari, con l’anteprima
   accanto per vedere dove sta ogni testo.</p>

<form method="get" action="<?= e(adminUrl('testi')) ?>" class="adm-cerca-testi" role="search">
  <label for="cerca-testi">Cerca una frase che vedi sul sito</label>
  <div class="adm-cerca-testi__riga">
    <input type="search" id="cerca-testi" name="cerca" value="<?= e($cerca) ?>" placeholder="per esempio: padrone di casa" minlength="3">
    <button type="submit" class="adm-bottone adm-bottone--piatto"><?= icona('cerca', 15) ?>Cerca</button>
  </div>
</form>

<?php if ($cerca !== ''): ?>
  <section class="adm-risultati" aria-live="polite">
    <?php if (mb_strlen($cerca) < 3): ?>
      <p class="adm-tenue">Scrivi almeno tre lettere.</p>
    <?php elseif ($risultati === []): ?>
      <p class="adm-tenue">Nessun testo contiene «<?= e($cerca) ?>». Alcune parti del sito — orari, parcheggio, tariffe —
         non sono testi: si cambiano in <a href="<?= e(adminUrl('struttura')) ?>">La struttura</a> o in
         <a href="<?= e(adminUrl('camere')) ?>">Camere e tariffe</a>; le domande in
         <a href="<?= e(adminUrl('domande')) ?>">Domande frequenti</a>.</p>
    <?php else: ?>
      <h2 class="adm-risultati__titolo"><?= count($risultati) ?> <?= count($risultati) === 1 ? 'testo trovato' : 'testi trovati' ?></h2>
      <ul class="adm-risultati__lista">
        <?php foreach ($risultati as $r): ?>
          <li>
            <a href="<?= e(adminUrl('testi/' . $r['sezione']) . '#k-' . str_replace(['.', '_'], '-', $r['chiave'])) ?>">
              <span class="adm-risultati__dove"><?= e($r['nome_sezione']) ?> · <?= e($r['nome']) ?> · <?= e(strtoupper($r['lingua'])) ?></span>
              <span class="adm-risultati__testo"><?= e(mb_strimwidth($r['testo'], 0, 180, '…')) ?></span>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
<?php endif; ?>

<?php foreach ($famiglie as $chiaveFamiglia => $f): if ($f['sezioni'] === []) continue; ?>
  <section class="adm-famiglia adm-famiglia--<?= e($chiaveFamiglia) ?>">
    <h2 class="adm-famiglia__titolo"><?= e($f['nome']) ?></h2>
    <p class="adm-famiglia__nota"><?= e($f['descrizione']) ?></p>
    <ul class="adm-sezioni">
      <?php foreach ($f['sezioni'] as $s): ?>
        <li>
          <a href="<?= e(adminUrl('testi/' . $s['chiave'])) ?>"><?= e($s['nome']) ?></a>
          <span class="adm-sezioni__descrizione"><?= e($s['descrizione']) ?></span>
          <span class="adm-tenue">
            <?php if ($s['conta']['importanti'] > 0): ?><?= (int) $s['conta']['importanti'] ?> importanti · <?php endif; ?>
            <?= (int) ($s['conta']['secondari'] + $s['conta']['google']) ?> secondari
            <?php if ($s['modificate'] > 0): ?> · <strong class="adm-sezioni__modificati"><?= (int) $s['modificate'] ?> modificati</strong><?php endif; ?>
          </span>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
<?php endforeach; ?>
