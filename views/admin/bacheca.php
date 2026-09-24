<?php
/**
 * @var int   $nuove
 * @var list<array<string,mixed>> $ultime
 * @var list<array{label:string,gruppo:string,path:string}> $mancanti
 * @var list<array{ref:string,nome:string,buchi:list<string>}> $camereIncomplete
 * @var bool  $demo
 */
use ArcoDelVento\Admin\Inbox;
?>
<div class="adm-griglia">
  <section class="adm-riquadro" aria-labelledby="b-richieste">
    <h2 id="b-richieste">Richieste</h2>
    <?php if ($nuove > 0): ?>
      <p class="adm-grande"><?= (int) $nuove ?> <?= $nuove === 1 ? 'nuova' : 'nuove' ?></p>
    <?php else: ?>
      <p>Nessuna richiesta nuova.</p>
    <?php endif; ?>
    <?php if ($ultime !== []): ?>
      <ul class="adm-elenco">
        <?php foreach ($ultime as $v): ?>
          <li>
            <a href="<?= e(adminUrl('richieste/' . rawurlencode((string) $v['id']))) ?>">
              <?= e((string) ($v['dati']['nome'] ?? '—')) ?>
            </a>
            <span class="adm-tenue">· <?= $v['tipo'] === 'prenotazione' ? 'prenotazione' : 'messaggio' ?>
              · <?= e(dataOra((string) $v['ricevuta'])) ?></span>
            <span class="adm-stato adm-stato--<?= e((string) $v['stato']) ?>"><?= e(Inbox::STATI[$v['stato']] ?? $v['stato']) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <p><a class="adm-link" href="<?= e(adminUrl('richieste')) ?>">Tutte le richieste →</a></p>
  </section>

  <section class="adm-riquadro" aria-labelledby="b-mancanti">
    <h2 id="b-mancanti">Da completare</h2>
    <?php if ($mancanti === [] && $camereIncomplete === []): ?>
      <p>Tutti i dati ci sono.</p>
    <?php else: ?>
      <p class="adm-nota">Sul sito questi dati compaiono come «da confermare».</p>
      <ul class="adm-elenco">
        <?php foreach ($mancanti as $m): ?>
          <li<?= $m['path'] === 'legal.cin' ? ' class="adm-urgente"' : '' ?>>
            <a href="<?= e(adminUrl('struttura')) ?>#c-<?= e(preg_replace('/[^a-z0-9]+/', '-', $m['path'])) ?>"><?= e($m['label']) ?></a>
            <?php if ($m['path'] === 'legal.cin'): ?><span class="adm-tenue">· obbligatorio per legge</span><?php endif; ?>
          </li>
        <?php endforeach; ?>
        <?php foreach ($camereIncomplete as $c): ?>
          <li>
            <a href="<?= e(adminUrl('camere/' . rawurlencode($c['ref']))) ?>"><?= e($c['nome']) ?></a>
            <span class="adm-tenue">· <?= e(implode(', ', $c['buchi'])) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php if (array_filter($camereIncomplete, static fn ($c) => in_array('fotografia', $c['buchi'], true))): ?>
        <p class="adm-nota">Le fotografie per ora si aggiungono via FTP: mandale a chi cura il sito.</p>
      <?php endif; ?>
    <?php endif; ?>
  </section>
</div>

<?php if ($demo): ?>
  <section class="adm-riquadro adm-riquadro--avviso" aria-labelledby="b-calendario">
    <h2 id="b-calendario">Il calendario è ancora dimostrativo</h2>
    <p>Le date libere che il sito propone non vengono dal tuo calendario: sono inventate, per provare il
       percorso di prenotazione. Le richieste che arrivano sono vere, ma vanno controllate a mano contro
       Booking prima di confermarle.</p>
    <p>Il collegamento al calendario di Booking è il prossimo passo.</p>
  </section>
<?php endif; ?>
