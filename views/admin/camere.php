<?php
/**
 * @var list<array<string,mixed>> $camere
 * @var list<array> $storico
 */
use ArcoDelVento\Support\RoomPresenter as R;
?>
<p class="adm-intro">Nome, metratura, piano, vista e tariffe di ogni camera. Tipologia, letti e numero massimo di
   ospiti non si cambiano da qui: da loro dipende il motore di prenotazione.</p>

<div class="adm-tabella-scorre">
  <table class="adm-tabella">
    <caption class="adm-visually-hidden">Le camere</caption>
    <thead>
      <tr><th scope="col">Camera</th><th scope="col">Tipologia</th><th scope="col">Tariffe a notte</th><th scope="col">Metratura</th><th scope="col">Fotografia</th></tr>
    </thead>
    <tbody>
      <?php foreach ($camere as $c): ?>
        <tr>
          <td><a href="<?= e(adminUrl('camere/' . rawurlencode((string) $c['ref']))) ?>"><?= e((string) ($c['name']['it'] ?? $c['ref'])) ?></a></td>
          <td><?= e(R::typeLabel($c)) ?></td>
          <td>
            <?php foreach ((array) $c['rates'] as $ospiti => $prezzo): ?>
              <span class="adm-tariffa"><?= (int) $ospiti ?>&nbsp;osp. <strong><?= e(euro((float) $prezzo, 'it')) ?></strong></span>
            <?php endforeach; ?>
          </td>
          <td><?= !empty($c['size_sqm']) ? (int) $c['size_sqm'] . ' m²' : '<span class="adm-dc">da confermare</span>' ?></td>
          <td><?= !empty($c['photographed']) ? 'sì' : '<span class="adm-dc">manca</span>' ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?= $this->render('admin/_storico', ['storico' => $storico, 'nome' => 'camere', 'ritorno' => 'camere']) ?>
