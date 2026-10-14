<?php
/**
 * Configurazione: il file .env del sito, voce per voce. Solo per il super
 * admin: per il gestore questa pagina non esiste.
 *
 * I segreti non tornano mai nella pagina: si vede se ci sono e quanto sono
 * lunghi, e si cambiano scrivendo il valore nuovo. Un valore impostato
 * nell'ambiente del server (pannello di Hostinger) vince sul file: si vede,
 * ma da qui non si cambia.
 *
 * @var \ArcoDelVento\Admin\Ambiente $ambiente
 * @var array<string,string>         $nelFile
 * @var list<string>                 $avvisi
 * @var list<array>                  $copie
 * @var list<array>                  $registro
 * @var array<string,mixed>          $inviati  dopo un errore, quello che era stato scritto
 */
use ArcoDelVento\Admin\Ambiente;
use ArcoDelVento\Support\Csrf;

$scrivibile = $ambiente->scrivibile();
$modificato = $ambiente->modificato();
$valoreCampo = static fn (string $k): string => array_key_exists($k, $inviati) && is_string($inviati[$k]) ? $inviati[$k] : ($nelFile[$k] ?? '');
$sconosciute = $ambiente->chiaviSconosciute();
$tipiInput = ['url' => 'url', 'email' => 'email', 'intero' => 'text'];
?>
<p class="adm-intro">Il file <code>.env</code> del sito, voce per voce. Lo vedi solo tu: per il gestore questa pagina
   non esiste. Ogni salvataggio chiede la tua password, mette fra le copie il file com'era e vale dalla pagina
   successiva.</p>

<section class="adm-carta" aria-labelledby="env-file">
  <div class="adm-carta__testa">
    <h2 id="env-file">Il file</h2>
    <?php if ($scrivibile): ?>
      <span class="adm-stato adm-stato--confermata">Scrivibile dal sito</span>
    <?php else: ?>
      <span class="adm-stato adm-stato--rifiutata">Solo lettura</span>
    <?php endif; ?>
  </div>
  <dl class="adm-dati adm-dati--piatta">
    <div><dt>Dove</dt><dd><code>.env</code>, nella cartella del sito accanto a <code>.htaccess</code></dd></div>
    <div><dt>Ultima modifica</dt><dd><?= $modificato !== null ? e(date('d/m/Y, H:i', $modificato)) : 'il file non c\'è ancora: salvando si crea' ?></dd></div>
    <div><dt>Copie di sicurezza</dt><dd><?= count($copie) ?> <?= count($copie) === 1 ? 'versione' : 'versioni' ?> precedenti, in fondo alla pagina</dd></div>
  </dl>
  <?php if (!$scrivibile): ?>
    <p class="adm-avviso adm-avviso--attenzione" role="note">
      <?= icona('attenzione', 18, 'adm-avviso__icona') ?>
      <span>Il sito non può scrivere il file <code>.env</code>: qui si legge soltanto. Via FTP dai al file i permessi
        644 (e 755 alla cartella del sito), poi ricarica la pagina.</span>
    </p>
  <?php endif; ?>
  <?php if ($avvisi !== []): ?>
    <div class="adm-avviso adm-avviso--attenzione" role="note">
      <?= icona('attenzione', 18, 'adm-avviso__icona') ?>
      <div>
        <p><strong>Da guardare</strong></p>
        <ul>
          <?php foreach ($avvisi as $a): ?><li><?= e($a) ?></li><?php endforeach; ?>
        </ul>
      </div>
    </div>
  <?php endif; ?>
</section>

<form method="post" action="<?= e(adminUrl('configurazione')) ?>" class="adm-modulo adm-env" novalidate autocomplete="off">
  <?= Csrf::field() ?>
  <input type="hidden" name="azione" value="salva">

  <?php foreach (Ambiente::SCHEMA as $idGruppo => $gruppo): ?>
    <section class="adm-carta adm-env__gruppo" id="g-<?= e($idGruppo) ?>" aria-labelledby="t-<?= e($idGruppo) ?>">
      <div class="adm-carta__testa"><h2 id="t-<?= e($idGruppo) ?>"><?= e($gruppo['nome']) ?></h2></div>
      <p class="adm-nota adm-env__descrizione"><?= e($gruppo['descrizione']) ?></p>
      <div class="adm-env__griglia">
        <?php foreach ($gruppo['voci'] as $chiave => $voce):
            $id       = 'env-' . strtolower(str_replace('_', '-', $chiave));
            $server   = Ambiente::daServer($chiave);
            $segreta  = Ambiente::segreta($chiave);
            $nelPezzo = $nelFile[$chiave] ?? '';
            $spento   = $server !== null || !$scrivibile;
            $aiuto    = (string) ($voce['aiuto'] ?? '');
            $descritto = $id . '-aiuto';
            $valore   = $valoreCampo($chiave); ?>
          <div class="adm-campo adm-env__voce">
            <label for="<?= e($id) ?>"><?= e($voce['nome']) ?> <code class="adm-env__chiave"><?= e($chiave) ?></code></label>

            <?php if ($segreta): ?>
              <input type="password" id="<?= e($id) ?>" name="env[<?= e($chiave) ?>]" autocomplete="new-password"
                     spellcheck="false" aria-describedby="<?= e($descritto) ?>"
                     placeholder="<?= $nelPezzo !== '' ? 'Impostata: lascia vuoto per non cambiarla' : 'Vuota' ?>"<?= $spento ? ' disabled' : '' ?>>
              <p class="adm-env__stato">
                <?php if ($nelPezzo !== ''): ?>
                  <span class="adm-stato adm-stato--confermata">Impostata · <?= mb_strlen($nelPezzo) ?> caratteri</span>
                <?php else: ?>
                  <span class="adm-stato">Vuota</span>
                <?php endif; ?>
              </p>
              <?php if ($nelPezzo !== '' && !$spento): ?>
                <p class="adm-campo--spunta adm-env__svuota">
                  <input type="checkbox" id="<?= e($id) ?>-svuota" name="svuota[<?= e($chiave) ?>]" value="1">
                  <label for="<?= e($id) ?>-svuota">Svuota</label>
                </p>
              <?php endif; ?>

            <?php elseif (in_array($voce['tipo'], ['scelta', 'si-no'], true)):
                $opzioni = $voce['tipo'] === 'si-no' ? ['true' => 'Sì', 'false' => 'No'] : (array) $voce['opzioni'];
                if (empty($voce['obbligatorio'])) {
                    $predefinito = (string) ($voce['predefinito'] ?? '');
                    $opzioni = ['' => $voce['vuoto'] ?? ('Vuoto — vale «' . ($opzioni[$predefinito] ?? $predefinito) . '»')] + $opzioni;
                }
                if (!array_key_exists($valore, $opzioni)) {
                    $opzioni = [$valore => '«' . $valore . '» (scritto così nel file)'] + $opzioni;
                } ?>
              <select id="<?= e($id) ?>" name="env[<?= e($chiave) ?>]" aria-describedby="<?= e($descritto) ?>"<?= $spento ? ' disabled' : '' ?>>
                <?php foreach ($opzioni as $v => $etichetta): ?>
                  <option value="<?= e((string) $v) ?>"<?= (string) $v === $valore ? ' selected' : '' ?>><?= e($etichetta) ?></option>
                <?php endforeach; ?>
              </select>

            <?php else: ?>
              <input type="<?= e($tipiInput[$voce['tipo']] ?? 'text') ?>" id="<?= e($id) ?>" name="env[<?= e($chiave) ?>]"
                     value="<?= e($valore) ?>" autocomplete="off" spellcheck="false" autocapitalize="none"
                     <?= $voce['tipo'] === 'intero' ? 'inputmode="numeric" ' : '' ?>aria-describedby="<?= e($descritto) ?>"
                     <?= !empty($voce['predefinito']) ? 'placeholder="Vuoto — vale «' . e((string) $voce['predefinito']) . '»"' : '' ?><?= $spento ? ' disabled' : '' ?>>
            <?php endif; ?>

            <p class="adm-aiuto" id="<?= e($descritto) ?>">
              <?= e($aiuto) ?>
              <?php if ($server !== null): ?>
                <strong class="adm-env__server">Impostato nell'ambiente del server (pannello di Hostinger)<?= $segreta ? '' : ': vale «' . e($server) . '»' ?>.
                  Vince sul file, e da qui non si cambia.</strong>
              <?php endif; ?>
            </p>

            <?php if ($chiave === 'APP_URL' && !$spento): ?>
              <p class="adm-campo--spunta adm-env__conferma-url">
                <input type="checkbox" id="conferma-url" name="conferma_url" value="1">
                <label for="conferma-url">Sì, cambio l'indirizzo del sito</label>
                <span class="adm-aiuto">Serve solo se l'hai cambiato: un indirizzo sbagliato rompe link, stili e pagamento.</span>
              </p>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>

  <?php if ($sconosciute !== []): ?>
    <section class="adm-carta" aria-labelledby="env-altre">
      <div class="adm-carta__testa"><h2 id="env-altre">Altre righe del file</h2></div>
      <p class="adm-nota">Il pannello non le conosce: restano come sono. Si cambiano via FTP.</p>
      <p class="adm-env__altre"><?php foreach ($sconosciute as $k): ?><code class="adm-env__chiave"><?= e($k) ?></code> <?php endforeach; ?></p>
    </section>
  <?php endif; ?>

  <?php if ($scrivibile): ?>
    <section class="adm-carta adm-env__salva" aria-labelledby="env-salva">
      <h2 id="env-salva" class="adm-visually-hidden">Salva</h2>
      <div class="adm-campo">
        <label for="conferma">La tua password, per confermare</label>
        <input type="password" id="conferma" name="conferma" required autocomplete="new-password" aria-describedby="conferma-aiuto">
        <p class="adm-visually-hidden" id="conferma-aiuto">Quella del tuo account super admin. Si scrivono solo i valori cambiati.</p>
      </div>
      <p><button type="submit" class="adm-bottone"><?= icona('spunta', 16) ?>Salva nel file .env</button></p>
    </section>
  <?php endif; ?>
</form>

<section class="adm-carta" id="copie" aria-labelledby="env-copie">
  <div class="adm-carta__testa"><h2 id="env-copie">Copie di sicurezza</h2></div>
  <?php if ($copie === []): ?>
    <p class="adm-tenue">Ancora nessuna: la prima si fa al primo salvataggio, con il file com'era prima.</p>
  <?php else: ?>
    <form method="post" action="<?= e(adminUrl('configurazione')) ?>#copie" class="adm-modulo" novalidate>
      <?= Csrf::field() ?>
      <input type="hidden" name="azione" value="ripristina">
      <fieldset class="adm-env__copie">
        <legend class="adm-visually-hidden">Versione da rimettere</legend>
        <?php foreach ($copie as $i => $c): ?>
          <p class="adm-campo--spunta">
            <input type="radio" id="copia-<?= $i ?>" name="versione" value="<?= e($c['nome']) ?>"<?= $i === 0 ? ' checked' : '' ?><?= $scrivibile ? '' : ' disabled' ?>>
            <label for="copia-<?= $i ?>">Il file del <?= e($c['quando'] !== '' ? dataOra($c['quando']) : $c['nome']) ?></label>
            <span class="adm-aiuto">
              <?php if ($c['chiavi'] !== []): ?>com'era prima di cambiare <?= e(implode(', ', $c['chiavi'])) ?><?= $c['utente'] !== '' ? ' (' . e($c['utente']) . ')' : '' ?><?php else: ?>com'era prima di un ripristino<?php endif; ?>
            </span>
          </p>
        <?php endforeach; ?>
      </fieldset>
      <?php if ($scrivibile): ?>
        <div class="adm-campo">
          <label for="conferma-copia">La tua password, per confermare</label>
          <input type="password" id="conferma-copia" name="conferma" required autocomplete="new-password">
        </div>
        <p><button type="submit" class="adm-bottone adm-bottone--piatto"><?= icona('ripristina', 16) ?>Rimetti questa versione</button></p>
      <?php endif; ?>
    </form>
  <?php endif; ?>
</section>

<?php if ($registro !== []): ?>
  <section class="adm-carta" aria-labelledby="env-registro">
    <div class="adm-carta__testa"><h2 id="env-registro">Ultime modifiche</h2></div>
    <div class="adm-tabella-scorre">
      <table class="adm-tabella">
        <caption class="adm-visually-hidden">Chi ha cambiato il file .env, e quali voci</caption>
        <thead><tr><th scope="col">Quando</th><th scope="col">Chi</th><th scope="col">Che cosa</th></tr></thead>
        <tbody>
          <?php foreach ($registro as $r): ?>
            <tr>
              <td class="adm-tenue"><?= e(dataOra((string) ($r['quando'] ?? ''))) ?></td>
              <td><?= e((string) ($r['utente'] ?? '')) ?></td>
              <td><?= ($r['azione'] ?? '') === 'ripristino' ? 'Ripristino: ' : '' ?><?= e(implode(', ', (array) ($r['chiavi'] ?? [])) ?: '—') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="adm-nota">Il registro tiene i nomi delle voci, mai i valori.</p>
  </section>
<?php endif; ?>
