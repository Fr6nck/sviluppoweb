<?php
/**
 * La bacheca: i numeri delle richieste, il grafico, le ultime arrivate, gli
 * arrivi in programma, le camere e quello che manca.
 *
 * Tutti i numeri vengono dalle richieste arrivate dal sito. Le prenotazioni
 * fatte su Booking o al telefono qui non ci sono, e la pagina lo dice dove
 * serve: un grafico che sembra un gestionale e non lo è, trae in inganno.
 *
 * @var \ArcoDelVento\Admin\Cruscotto $cruscotto
 * @var string $periodo  settimana | mesi
 * @var int    $nuove
 * @var list<array<string,mixed>> $ultime
 * @var list<array{label:string,gruppo:string,path:string}> $mancanti
 * @var list<array{ref:string,nome:string,buchi:list<string>}> $camereIncomplete
 * @var list<array<string,mixed>> $camere
 * @var bool  $demo
 */

use ArcoDelVento\Admin\Cruscotto;
use ArcoDelVento\Admin\Grafici as G;
use ArcoDelVento\Admin\Inbox;

$c = $cruscotto;
$schede = [
    [
        'nome' => 'Richieste nuove', 'valore' => $nuove, 'nota' => 'da leggere',
        'serie' => $c->perGiorno(null, 14), 'variazione' => null, 'icona' => 'vassoio', 'tono' => 'mattone',
        'href' => adminUrl('richieste') . '?stato=nuova', 'link' => 'Leggile',
    ],
    [
        'nome' => 'Prenotazioni', 'valore' => $c->totale('prenotazione', 30), 'nota' => 'richieste negli ultimi 30 giorni',
        'serie' => $c->perGiorno('prenotazione', 30), 'variazione' => $c->variazione('prenotazione', 30), 'icona' => 'calendario', 'tono' => 'bruno',
        'href' => adminUrl('richieste'), 'link' => 'Vedi',
    ],
    [
        'nome' => 'Messaggi', 'valore' => $c->totale('messaggio', 30), 'nota' => 'arrivati negli ultimi 30 giorni',
        'serie' => $c->perGiorno('messaggio', 30), 'variazione' => $c->variazione('messaggio', 30), 'icona' => 'messaggio', 'tono' => 'oro',
        'href' => adminUrl('richieste'), 'link' => 'Vedi',
    ],
];

$colonne = $c->colonne($periodo);
$picco   = 0;
foreach ($colonne as $col) {
    $picco = max($picco, $col['prenotazioni'], $col['messaggi']);
}
$massimo = G::tondo(max(1, $picco));
$valore  = $c->valore($c->giorni($periodo));

$notti      = $c->camerePerNotte(30);
$totCamere  = count($camere);
$nottiCamera = array_sum($notti);
$arrivi     = $c->prossimiArrivi(4);
$oggi       = new DateTimeImmutable('today');

$rosa     = immagine('marchio.rosa');
$logo     = immagine('marchio.logotipo');
$logoChiaro = immagine('marchio.logotipo-chiaro');

$iniziali = static function (string $nome): string {
    $pezzi = preg_split('/\s+/u', trim($nome)) ?: [];
    $out = '';
    foreach (array_slice($pezzi, 0, 2) as $p) {
        $out .= mb_strtoupper(mb_substr($p, 0, 1));
    }

    return $out !== '' ? $out : '·';
};
?>

<?php if ($demo): ?>
  <section class="adm-striscia" aria-labelledby="b-calendario">
    <span class="adm-striscia__icona" aria-hidden="true"><?= icona('calendario', 20) ?></span>
    <div>
      <h2 id="b-calendario" class="adm-striscia__titolo">Il calendario è ancora dimostrativo</h2>
      <p>Le date libere che il sito propone non vengono dal tuo calendario: servono a provare il percorso di
         prenotazione. Le richieste sono vere, ma vanno controllate su Booking prima di confermarle.
         Il collegamento al calendario di Booking è il prossimo passo.</p>
    </div>
  </section>
<?php endif; ?>

<div class="adm-bacheca">
  <div class="adm-bacheca__principale">

    <ul class="adm-schede" aria-label="In breve">
      <?php foreach ($schede as $i => $s): ?>
        <li class="adm-scheda adm-scheda--<?= e($s['tono']) ?>">
          <div class="adm-scheda__testa">
            <span class="adm-scheda__icona" aria-hidden="true"><?= icona($s['icona'], 18) ?></span>
            <span class="adm-scheda__nome"><?= e($s['nome']) ?></span>
            <a class="adm-scheda__link" href="<?= e($s['href']) ?>" aria-label="<?= e($s['link'] . ': ' . mb_strtolower($s['nome'])) ?>"><?= icona('freccia-su-destra', 16) ?></a>
          </div>
          <div class="adm-scheda__corpo">
            <p class="adm-scheda__valore"><?= (int) $s['valore'] ?></p>
            <?= G::scintilla($s['serie'], 'adm-scintilla--' . $s['tono'], 'sc-' . $i) ?>
          </div>
          <p class="adm-scheda__nota">
            <?php if ($s['variazione'] !== null): $su = $s['variazione'] >= 0; ?>
              <span class="adm-variazione adm-variazione--<?= $su ? 'su' : 'giu' ?>">
                <?= icona($su ? 'sale' : 'scende', 13) ?><?= ($su ? '+' : '−') . e(number_format(abs($s['variazione']), 0, ',', '.')) ?>%
              </span>
              <span class="adm-visually-hidden">rispetto ai 30 giorni prima,</span>
            <?php endif; ?>
            <?= e($s['nota']) ?>
          </p>
        </li>
      <?php endforeach; ?>
    </ul>

    <section class="adm-carta adm-carta--grafico" aria-labelledby="b-grafico">
      <div class="adm-carta__testa">
        <h2 id="b-grafico">Richieste arrivate</h2>
        <nav class="adm-interruttore" aria-label="Periodo del grafico">
          <a href="<?= e(adminUrl()) ?>?periodo=settimana"<?= $periodo === 'settimana' ? ' aria-current="true"' : '' ?>>7 giorni</a>
          <a href="<?= e(adminUrl()) ?>?periodo=mesi"<?= $periodo === 'mesi' ? ' aria-current="true"' : '' ?>>6 mesi</a>
        </nav>
      </div>

      <div class="adm-grafico">
        <div class="adm-grafico__riepilogo">
          <p class="adm-grafico__etichetta">Valore delle prenotazioni richieste</p>
          <p class="adm-grafico__valore"><?= e(euro($valore, 'it')) ?></p>
          <p class="adm-nota">La somma dei totali calcolati dal sito<?= $periodo === 'mesi' ? ' negli ultimi sei mesi' : ' negli ultimi sette giorni' ?>,
             rifiutate escluse. Non è un incasso: sono richieste.</p>
          <ul class="adm-legenda">
            <li><span class="adm-legenda__segno adm-legenda__segno--pieno" aria-hidden="true"></span>Prenotazioni</li>
            <li><span class="adm-legenda__segno adm-legenda__segno--righe" aria-hidden="true"></span>Messaggi</li>
          </ul>
        </div>

        <div class="adm-barre" aria-hidden="true">
          <div class="adm-barre__asse">
            <span><?= $massimo ?></span><span><?= intdiv($massimo, 2) ?></span><span>0</span>
          </div>
          <div class="adm-barre__area">
            <?php foreach ($colonne as $col): ?>
              <div class="adm-barre__colonna<?= $col['oggi'] ? ' adm-barre__colonna--oggi' : '' ?>">
                <div class="adm-barre__coppia">
                  <span class="adm-colonnina adm-colonnina--pieno <?= G::altezza($col['prenotazioni'], $massimo) ?>" data-valore="<?= (int) $col['prenotazioni'] ?>"></span>
                  <span class="adm-colonnina adm-colonnina--righe <?= G::altezza($col['messaggi'], $massimo) ?>" data-valore="<?= (int) $col['messaggi'] ?>"></span>
                </div>
                <span class="adm-barre__etichetta"><?= e($col['etichetta']) ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <table class="adm-visually-hidden">
          <caption>Richieste arrivate, <?= $periodo === 'mesi' ? 'mese per mese' : 'giorno per giorno' ?></caption>
          <thead><tr><th scope="col"><?= $periodo === 'mesi' ? 'Mese' : 'Giorno' ?></th><th scope="col">Prenotazioni</th><th scope="col">Messaggi</th></tr></thead>
          <tbody>
            <?php foreach ($colonne as $col): ?>
              <tr><th scope="row"><?= e($col['etichetta']) ?></th><td><?= (int) $col['prenotazioni'] ?></td><td><?= (int) $col['messaggi'] ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

    <section class="adm-carta" aria-labelledby="b-ultime">
      <div class="adm-carta__testa">
        <h2 id="b-ultime">Ultime richieste</h2>
        <a class="adm-pillola adm-pillola--chiara" href="<?= e(adminUrl('richieste')) ?>">Tutte<?= icona('freccia-destra', 15) ?></a>
      </div>
      <?php if ($ultime === []): ?>
        <div class="adm-vuoto">
          <?= icona('vassoio', 28) ?>
          <p>Ancora nessuna richiesta. Quando qualcuno scrive o chiede una camera dal sito, compare qui e arriva anche per e-mail.</p>
        </div>
      <?php else: ?>
        <div class="adm-tabella-scorre">
          <table class="adm-tabella adm-tabella--righe">
            <caption class="adm-visually-hidden">Le ultime richieste arrivate dal sito</caption>
            <thead>
              <tr><th scope="col">Chi</th><th scope="col">Che cosa</th><th scope="col">Arrivata</th><th scope="col">Stato</th></tr>
            </thead>
            <tbody>
              <?php foreach ($ultime as $v): $d = (array) ($v['dati'] ?? []); $nome = (string) ($d['nome'] ?? '—'); ?>
                <tr<?= $v['stato'] === 'nuova' ? ' class="adm-riga-nuova"' : '' ?>>
                  <td>
                    <a class="adm-persona" href="<?= e(adminUrl('richieste/' . rawurlencode((string) $v['id']))) ?>">
                      <span class="adm-persona__sigla" aria-hidden="true"><?= e($iniziali($nome)) ?></span>
                      <span><span class="adm-persona__nome"><?= e($nome) ?></span>
                        <span class="adm-persona__sotto"><?= e((string) ($d['email'] ?? '')) ?></span></span>
                    </a>
                  </td>
                  <td>
                    <?php if ($v['tipo'] === 'prenotazione'): ?>
                      <span class="adm-tipo adm-tipo--prenotazione"><?= icona('calendario', 13) ?>Prenotazione</span>
                      <span class="adm-tenue"><?= e((string) ($d['camera'] ?? '')) ?> · <?= e(Cruscotto::breve((string) ($d['arrivo'] ?? ''))) ?>–<?= e(Cruscotto::breve((string) ($d['partenza'] ?? ''))) ?></span>
                    <?php else: ?>
                      <span class="adm-tipo"><?= icona('messaggio', 13) ?>Messaggio</span>
                      <span class="adm-tenue"><?= e(mb_strimwidth((string) ($d['oggetto'] ?? ''), 0, 40, '…')) ?></span>
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

    <section class="adm-carta" id="da-completare" aria-labelledby="b-mancanti" tabindex="-1">
      <div class="adm-carta__testa">
        <h2 id="b-mancanti">Da completare</h2>
        <?php if ($mancanti !== [] || $camereIncomplete !== []): ?>
          <span class="adm-etichetta"><?= count($mancanti) + count($camereIncomplete) ?> in sospeso</span>
        <?php endif; ?>
      </div>
      <?php if ($mancanti === [] && $camereIncomplete === []): ?>
        <p class="adm-tutto"><?= icona('spunta', 18) ?> Tutti i dati ci sono.</p>
      <?php else: ?>
        <p class="adm-nota">Sul sito questi dati compaiono come «da confermare». Meglio vuoto che sbagliato: riempili quando li hai certi.</p>
        <?php
        $compiti = [];
        foreach ($mancanti as $m) {
            $cin = $m['path'] === 'legal.cin';
            $compiti[] = [
                'href' => adminUrl('struttura') . '#c-' . preg_replace('/[^a-z0-9]+/', '-', $m['path']),
                'nome' => $m['label'], 'dove' => $cin ? 'obbligatorio per legge' : $m['gruppo'],
                'icona' => $cin ? 'attenzione' : 'matita', 'urgente' => $cin,
            ];
        }
        foreach ($camereIncomplete as $ci) {
            $soloFoto = $ci['buchi'] === ['fotografia'];
            $compiti[] = [
                'href' => $soloFoto ? adminUrl('immagini/camera.' . $ci['ref']) : adminUrl('camere/' . rawurlencode($ci['ref'])),
                'nome' => $ci['nome'], 'dove' => 'manca: ' . implode(', ', $ci['buchi']),
                'icona' => $soloFoto ? 'immagine' : 'letto', 'urgente' => false,
            ];
        }
        $visibili = 8;
        $voceCompito = static function (array $c): string {
            return '<li class="adm-compito' . ($c['urgente'] ? ' adm-compito--urgente adm-urgente' : '') . '"><a href="' . e($c['href']) . '">'
                . '<span class="adm-compito__icona" aria-hidden="true">' . icona($c['icona'], 15) . '</span>'
                . '<span>' . e($c['nome']) . '<span class="adm-compito__dove">' . e($c['dove']) . '</span></span></a></li>';
        };
        ?>
        <ul class="adm-compiti">
          <?php foreach (array_slice($compiti, 0, $visibili) as $compito): ?>
            <?= $voceCompito($compito) ?>
          <?php endforeach; ?>
        </ul>
        <?php if (count($compiti) > $visibili): ?>
          <details class="adm-altri">
            <summary>Altri <?= count($compiti) - $visibili ?></summary>
            <ul class="adm-compiti">
              <?php foreach (array_slice($compiti, $visibili) as $compito): ?>
                <?= $voceCompito($compito) ?>
              <?php endforeach; ?>
            </ul>
          </details>
        <?php endif; ?>
      <?php endif; ?>
    </section>
  </div>

  <aside class="adm-bacheca__lato" aria-label="Arrivi, camere e marchio">
    <section class="adm-carta" aria-labelledby="b-notti">
      <div class="adm-carta__testa">
        <h2 id="b-notti">Prossimi 30 giorni</h2>
      </div>
      <p class="adm-numero"><?= (int) $nottiCamera ?> <span><?= $nottiCamera === 1 ? 'notte-camera' : 'notti-camera' ?></span></p>
      <p class="adm-nota">Chieste o confermate dal sito, su <?= $totCamere * 30 ?> (<?= $totCamere ?> camere × 30 notti).
         Le prenotazioni di Booking qui non ci sono.</p>
      <div class="adm-linea-cornice"><?= G::linea($notti, max(1, $totCamere), 'g-notti') ?></div>
      <div class="adm-linea-assi" aria-hidden="true">
        <span>oggi</span><span><?= e(Cruscotto::breve($oggi->modify('+15 days')->format('Y-m-d'))) ?></span><span><?= e(Cruscotto::breve($oggi->modify('+29 days')->format('Y-m-d'))) ?></span>
      </div>

      <h3 class="adm-sottotitolo-carta">Prossimi arrivi</h3>
      <?php if ($arrivi === []): ?>
        <p class="adm-tenue">Nessun arrivo in programma fra le richieste.</p>
      <?php else: ?>
        <ul class="adm-arrivi">
          <?php foreach ($arrivi as $a): $d = (array) $a['dati']; [$g, $m] = explode(' ', Cruscotto::breve((string) $d['arrivo'])) + ['', '']; ?>
            <li>
              <a href="<?= e(adminUrl('richieste/' . rawurlencode((string) $a['id']))) ?>">
                <span class="adm-data"><strong><?= e($g) ?></strong><?= e($m) ?></span>
                <span class="adm-arrivi__chi">
                  <span class="adm-arrivi__nome"><?= e((string) ($d['nome'] ?? '—')) ?></span>
                  <span class="adm-arrivi__sotto"><?= e((string) ($d['camera'] ?? '')) ?> · <?= (int) ($d['notti'] ?? 0) ?> <?= (int) ($d['notti'] ?? 0) === 1 ? 'notte' : 'notti' ?></span>
                </span>
                <span class="adm-stato adm-stato--<?= e((string) $a['stato']) ?>"><?= e(Inbox::STATI[$a['stato']] ?? $a['stato']) ?></span>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <section class="adm-carta" aria-labelledby="b-camere">
      <div class="adm-carta__testa">
        <h2 id="b-camere">Le camere</h2>
        <a class="adm-pillola adm-pillola--chiara" href="<?= e(adminUrl('camere')) ?>">Tariffe<?= icona('freccia-destra', 15) ?></a>
      </div>
      <ul class="adm-camere">
        <?php foreach ($camere as $cam): $ref = (string) $cam['ref']; $mini = anteprima(immagine('camera.' . $ref), 'foto'); $tariffe = array_map('floatval', (array) ($cam['rates'] ?? [])); ?>
          <li>
            <a href="<?= e(adminUrl('camere/' . rawurlencode($ref))) ?>">
              <?php if ($mini !== null): ?>
                <img class="adm-camere__foto" src="<?= e(asset($mini)) ?>" alt="" width="48" height="48" loading="lazy">
              <?php else: ?>
                <span class="adm-camere__foto" aria-hidden="true"></span>
              <?php endif; ?>
              <span class="adm-camere__testo">
                <span class="adm-camere__nome"><?= e((string) ($cam['name']['it'] ?? $ref)) ?></span>
                <span class="adm-camere__sotto"><?= $tariffe !== [] ? 'da ' . e(euro(min($tariffe), 'it')) . ' a notte' : 'senza tariffa' ?></span>
              </span>
              <?php if (!empty($cam['photographed'])): ?>
                <span class="adm-segno adm-segno--ok" title="Fotografata"><?= icona('spunta', 14) ?><span class="adm-visually-hidden">fotografata</span></span>
              <?php else: ?>
                <span class="adm-segno" title="Foto da fare"><?= icona('immagine', 14) ?><span class="adm-visually-hidden">foto da fare</span></span>
              <?php endif; ?>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>

    <section class="adm-carta adm-carta--marchio" aria-labelledby="b-marchio">
      <div class="adm-carta__testa">
        <h2 id="b-marchio">Il marchio</h2>
      </div>
      <div class="adm-marchi">
        <div class="adm-marchi__chiaro">
          <img src="<?= e(asset($rosa['src'] . '-96.png')) ?>" alt="" width="40" height="40">
          <img src="<?= e(asset((string) $logo['src'])) ?>" alt="Il logotipo su fondo chiaro" height="40" width="<?= (int) round(40 * (($logo['w'] ?? 111) / max(1, $logo['h'] ?? 66))) ?>">
        </div>
        <div class="adm-marchi__scuro">
          <img src="<?= e(asset((string) $logoChiaro['src'])) ?>" alt="Il logotipo chiaro, su fondo mattone" height="40" width="<?= (int) round(40 * (($logoChiaro['w'] ?? 185) / max(1, $logoChiaro['h'] ?? 110))) ?>">
        </div>
      </div>
      <p class="adm-nota">Logo, icona e fotografie del sito si cambiano da qui, senza FTP.</p>
      <a class="adm-bottone adm-bottone--pieno-largo" href="<?= e(adminUrl('immagini')) ?>"><?= icona('immagine', 17) ?>Immagini e logo</a>
    </section>
  </aside>
</div>
