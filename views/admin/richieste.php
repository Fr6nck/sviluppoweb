<?php
/**
 * @var list<array<string,mixed>> $voci
 * @var string $stato
 */
use ArcoDelVento\Admin\Inbox;
?>
<nav class="adm-filtri" aria-label="Filtra per stato">
  <a href="<?= e(adminUrl('richieste')) ?>"<?= $stato === '' ? ' aria-current="page"' : '' ?>>Da gestire</a>
  <?php foreach (Inbox::STATI as $chiave => $nome): ?>
    <a href="<?= e(adminUrl('richieste')) ?>?stato=<?= e($chiave) ?>"<?= $stato === $chiave ? ' aria-current="page"' : '' ?>><?= e($nome) ?></a>
  <?php endforeach; ?>
</nav>

<?php if ($voci === []): ?>
  <p class="adm-vuoto">Nessuna richiesta<?= $stato !== '' ? ' in questo stato' : '' ?>.</p>
<?php else: ?>
  <div class="adm-tabella-scorre">
    <table class="adm-tabella">
      <caption class="adm-visually-hidden">Richieste arrivate dal sito</caption>
      <thead>
        <tr><th scope="col">Arrivata</th><th scope="col">Chi</th><th scope="col">Che cosa</th><th scope="col">Stato</th></tr>
      </thead>
      <tbody>
        <?php foreach ($voci as $v): $d = (array) ($v['dati'] ?? []); ?>
          <tr<?= $v['stato'] === 'nuova' ? ' class="adm-riga-nuova"' : '' ?>>
            <td><?= e(dataOra((string) $v['ricevuta'])) ?></td>
            <td><a href="<?= e(adminUrl('richieste/' . rawurlencode((string) $v['id']))) ?>"><?= e((string) ($d['nome'] ?? '—')) ?></a></td>
            <td>
              <?php if ($v['tipo'] === 'prenotazione'): ?>
                <?= e((string) ($d['camera'] ?? '')) ?>, <?= e(date('d/m', strtotime((string) $d['arrivo']))) ?>–<?= e(date('d/m/Y', strtotime((string) $d['partenza']))) ?>,
                <?= (int) ($d['ospiti'] ?? 0) ?> <?= (int) ($d['ospiti'] ?? 0) === 1 ? 'ospite' : 'ospiti' ?>
              <?php else: ?>
                Messaggio: <?= e((string) ($d['oggetto'] ?? '')) ?>
              <?php endif; ?>
            </td>
            <td><span class="adm-stato adm-stato--<?= e((string) $v['stato']) ?>"><?= e(Inbox::STATI[$v['stato']] ?? $v['stato']) ?></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
<p class="adm-nota">Le richieste più vecchie di <?= Inbox::CONSERVAZIONE_MESI ?> mesi si cancellano da sole: sono dati personali di chi ha scritto.</p>
