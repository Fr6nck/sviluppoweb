<?php
/**
 * @var list<array<string,mixed>> $voci
 * @var string $stato
 * @var string $cerca
 */
use ArcoDelVento\Admin\Inbox;

$iniziali = static function (string $nome): string {
    $out = '';
    foreach (array_slice(preg_split('/\s+/u', trim($nome)) ?: [], 0, 2) as $p) {
        $out .= mb_strtoupper(mb_substr($p, 0, 1));
    }

    return $out !== '' ? $out : '·';
};
?>
<section class="adm-carta">
  <div class="adm-carta__testa adm-carta__testa--a-capo">
    <nav class="adm-schede-filtro adm-filtri" aria-label="Filtra per stato">
      <a href="<?= e(adminUrl('richieste')) ?>"<?= $stato === '' && $cerca === '' ? ' aria-current="page"' : '' ?>>Da gestire</a>
      <?php foreach (Inbox::STATI as $chiave => $nome): ?>
        <a href="<?= e(adminUrl('richieste')) ?>?stato=<?= e($chiave) ?>"<?= $stato === $chiave ? ' aria-current="page"' : '' ?>><?= e($nome) ?></a>
      <?php endforeach; ?>
    </nav>
    <span class="adm-etichetta"><?= count($voci) ?> <?= count($voci) === 1 ? 'richiesta' : 'richieste' ?></span>
  </div>

  <?php if ($cerca !== ''): ?>
    <p class="adm-ricerca">
      <?= icona('cerca', 16) ?> Risultati per «<strong><?= e($cerca) ?></strong>», archiviate comprese.
      <a class="adm-link" href="<?= e(adminUrl('richieste')) ?>">Togli la ricerca</a>
    </p>
  <?php endif; ?>

  <?php if ($voci === []): ?>
    <div class="adm-vuoto">
      <?= icona('vassoio', 28) ?>
      <p>Nessuna richiesta<?= $cerca !== '' ? ' trovata' : ($stato !== '' ? ' in questo stato' : '') ?>.</p>
    </div>
  <?php else: ?>
    <div class="adm-tabella-scorre">
      <table class="adm-tabella adm-tabella--righe">
        <caption class="adm-visually-hidden">Richieste arrivate dal sito</caption>
        <thead>
          <tr><th scope="col">Chi</th><th scope="col">Che cosa</th><th scope="col">Arrivata</th><th scope="col">Stato</th></tr>
        </thead>
        <tbody>
          <?php foreach ($voci as $v): $d = (array) ($v['dati'] ?? []); $nome = (string) ($d['nome'] ?? '—'); ?>
            <tr<?= $v['stato'] === 'nuova' ? ' class="adm-riga-nuova"' : '' ?>>
              <td>
                <a class="adm-persona" href="<?= e(adminUrl('richieste/' . rawurlencode((string) $v['id']))) ?>">
                  <span class="adm-persona__sigla" aria-hidden="true"><?= e($iniziali($nome)) ?></span>
                  <span><span class="adm-persona__nome"><?= e($nome) ?></span>
                    <span class="adm-persona__sotto"><?= e((string) $v['id']) ?></span></span>
                </a>
              </td>
              <td>
                <?php if ($v['tipo'] === 'prenotazione'): ?>
                  <span class="adm-tipo adm-tipo--prenotazione"><?= icona('calendario', 13) ?>Prenotazione</span>
                  <?php if ($pg = \ArcoDelVento\Payment\Pagamenti::etichetta($d)): ?><span class="adm-pagamento adm-pagamento--<?= e($pg['tono']) ?>"><?= e($pg['testo']) ?></span><?php endif; ?>
                  <span class="adm-tenue">
                    <?= e((string) ($d['camera'] ?? '')) ?>, <?= e(date('d/m', strtotime((string) $d['arrivo']))) ?>–<?= e(date('d/m/Y', strtotime((string) $d['partenza']))) ?>,
                    <?= (int) ($d['ospiti'] ?? 0) ?> <?= (int) ($d['ospiti'] ?? 0) === 1 ? 'ospite' : 'ospiti' ?>
                  </span>
                <?php else: ?>
                  <span class="adm-tipo"><?= icona('messaggio', 13) ?>Messaggio</span>
                  <span class="adm-tenue"><?= e(mb_strimwidth((string) ($d['oggetto'] ?? ''), 0, 60, '…')) ?></span>
                <?php endif; ?>
              </td>
              <td class="adm-tenue"><?= e(dataOra((string) $v['ricevuta'])) ?></td>
              <td><span class="adm-stato adm-stato--<?= e((string) $v['stato']) ?>"><?= e(Inbox::STATI[$v['stato']] ?? $v['stato']) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
<p class="adm-nota">Le richieste più vecchie di <?= Inbox::CONSERVAZIONE_MESI ?> mesi si cancellano da sole: sono dati personali di chi ha scritto.</p>
