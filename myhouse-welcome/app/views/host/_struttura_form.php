<?php use function MHW\tf;
/* Nome, luogo, orari, contatti dell'host e, nella procedura, la lingua in cui
   si scrive la guida. Riceve: $prop, $dopoPasso, e se c'è la lingua $tutte e $consentite. */
use function MHW\b; use function MHW\t;
use MHW\{Support, Csrf, Icon, Db};
$dopoPasso = $dopoPasso ?? '';
$tipi = MHW\Properties::TIPOLOGIE;
$contatti = Db::all('SELECT * FROM property_contacts WHERE property_id = ? ORDER BY position, id', [$prop['id']]);
$c = fn(string $k) => Support::e((string) $prop[$k]); ?>
<form method="post" action="<?= b() ?>/pannello/<?= (int) $prop['id'] ?>/impostazioni" class="stack"<?= $dopoPasso !== '' ? ' data-autosave' : '' ?>><?= Csrf::field() ?>
  <fieldset class="fieldset">
    <legend><?= t('La struttura') ?><?php if (!empty($prop['id']) && ($codS = MHW\Codici::struttura((int) $prop['id'])) !== ''): ?> <span class="small muted codice-struttura"><?= Support::e($codS) ?></span><?php endif; ?></legend>
    <div class="field" style="margin:0"><label for="name"><?= t('Nome') ?></label><input type="text" id="name" name="name" required maxlength="120" autocomplete="off" value="<?= $c('name') ?>"></div>
    <div class="field" style="margin:0"><span class="label"><?= t('Tipologia') ?></span>
      <div class="scelte scelte--riga" role="radiogroup" aria-label="<?= t('Tipologia') ?>">
        <?php foreach ($tipi as $k => $et): ?>
          <label class="scelta"><input type="radio" name="property_type" value="<?= Support::e($k) ?>" <?= (string) ($prop['property_type'] ?? '') === $k ? 'checked' : '' ?>>
            <span class="scelta__testo"><?= Support::e(t($et)) ?></span></label>
        <?php endforeach; ?>
      </div>
    </div>
    <?php /* Con «Altro» si scrive che tipo di struttura è. Senza JavaScript il campo si vede sempre. */ ?>
    <div class="field" style="margin:0" id="tipo-altro" data-se-altro>
      <label for="property_type_other"><?= t('Che tipo di struttura è?') ?></label>
      <input type="text" id="property_type_other" name="property_type_other" maxlength="60" placeholder="<?= MHW\Support::e(t('Area camper, glamping, ostello…')) ?>"
             value="<?= Support::e((string) ($prop['property_type_other'] ?? '')) ?>">
    </div>
    <div class="field" style="margin:0"><label for="address"><?= t('Indirizzo') ?></label>
      <p class="help" style="margin:0 0 6px"><?= t('Via e numero civico. Precompila «Come arrivare» e il link a Maps: lo scrivi una volta sola.') ?></p>
      <input type="text" id="address" name="address" maxlength="255" autocomplete="off" value="<?= $c('address') ?>"></div>
    <?php /* Una griglia sola a tre colonne: CIN occupa le prime due, così i bordi dei campi
             sono in colonna con CAP, Città, Provincia e Regione; i campi stanno in basso, allineati anche
             quando l'aiuto sopra va a capo su più righe. */ ?>
    <div class="grid grid-3 campi-allineati">
      <div class="field" style="margin:0"><label for="postal_code"><?= t('CAP') ?></label><input type="text" id="postal_code" name="postal_code" maxlength="10" inputmode="numeric" autocomplete="off" value="<?= $c('postal_code') ?>"></div>
      <div class="field" style="margin:0"><label for="city"><?= t('Città') ?></label><input type="text" id="city" name="city" maxlength="120" autocomplete="off" value="<?= $c('city') ?>"></div>
      <?php /* 9F · Provincia (sigla con i suggerimenti dalle 107; all'estero testo libero, facoltativo) e Regione (suggerimenti dalle 20):
               la regione si completa da sola dalla sigla (cms.js, data-provincia-regione). */ ?>
      <div class="field" style="margin:0"><label for="province"><?= t('Provincia') ?> <span class="muted"><?= t('(sigla)') ?></span></label>
        <p class="help" id="province-aiuto" style="margin:0 0 6px"><?= t('Sigla: PG, FI, RM… All\'estero: provincia o contea, facoltativa.') ?></p>
        <input type="text" id="province" name="province" maxlength="60" autocomplete="off" list="province-elenco" placeholder="<?= MHW\Support::e(t('PG, FI, RM…')) ?>" style="text-transform:uppercase"
               value="<?= Support::e((string) ($prop['province'] ?? '')) ?>" aria-describedby="province-aiuto" data-provincia-regione="region">
        <datalist id="province-elenco"><?php foreach (MHW\Territorio::province() as $sg => $nomeP): ?><option value="<?= $sg ?>" data-regione="<?= Support::e(MHW\Territorio::regioneDi($sg)) ?>"><?= Support::e($nomeP) ?></option><?php endforeach; ?></datalist>
</div>
      <div class="field" style="margin:0"><label for="region"><?= t('Regione') ?></label>
        <p class="help" id="region-aiuto" style="margin:0 0 6px"><?= t('Si completa da sola dalla provincia.') ?></p>
        <input type="text" id="region" name="region" maxlength="120" autocomplete="off" list="regioni-elenco" value="<?= $c('region') ?>" aria-describedby="region-aiuto">
        <datalist id="regioni-elenco"><?php foreach (MHW\Territorio::regioni() as $nomeR): ?><option value="<?= Support::e($nomeR) ?>"></option><?php endforeach; ?></datalist>
</div>
      <div class="field campo-largo" style="margin:0"><label for="cin">CIN <span class="muted"><?= t('(serve per pubblicare)') ?></span></label>
        <p class="help" id="cin-aiuto" style="margin:0 0 6px"><?= t('Il Codice Identificativo Nazionale della struttura: obbligatorio per legge, compare in piccolo in fondo alla guida. Ogni guida ha il suo CIN.') ?></p>
        <input type="text" id="cin" name="cin" maxlength="40" spellcheck="false" autocapitalize="characters" autocomplete="off" placeholder="<?= MHW\Support::e(t('IT…')) ?>" aria-describedby="cin-aiuto" value="<?= $c('cin') ?>"></div>
      <div class="field" style="margin:0"><label for="beds"><?= t('Posti letto') ?> <span class="muted"><?= t('(facoltativo)') ?></span></label>
        <p class="help" style="margin:0 0 6px"><?= t('Quante persone può ospitare la struttura.') ?></p>
        <input type="number" id="beds" name="beds" min="0" max="999" step="1" inputmode="numeric" value="<?= (int) ($prop['beds'] ?? 0) ?: '' ?>"></div>
    </div>
  </fieldset>
  <?php /* 9F·11 · La lingua in cui scrivi (da lì dipende la lingua del pannello). 9N · Dopo la scheda della struttura, in un riquadro
           che si apre: l'intro promette «bastano nome e città», e la lingua di serie va bene quasi sempre. */ ?>
  <?php if (isset($tutte)): $principale = $prop['default_locale'] ?: 'it'; ?>
    <details class="fieldset lingua-principale" id="lingua-principale"<?= $principale !== 'it' ? ' open' : '' ?>>
      <summary class="legend"><?= t('In che lingua scrivi la guida?') ?> <span class="small muted" style="font-weight:400">· <?= Support::e(MHW\Config::get('locales')[$principale] ?? strtoupper($principale)) ?></span></summary>
      <p class="help"><?= t('È la lingua principale: quella in cui scrivi tutti i testi. Di serie è l\'italiano; puoi scegliere quella che vuoi.') ?>
        <?= t('Le altre lingue sono facoltative e le aggiungi alla fine. Sceglila prima di scrivere: se la cambi dopo, i testi già scritti restano nella lingua in cui li hai scritti.') ?></p>
      <div class="scelte scelte--riga">
        <?php foreach ($tutte as $code => $nomeL): if (!MHW\I18n::base($code)) continue; ?>
          <label class="scelta"><input type="radio" name="default_locale" value="<?= Support::e($code) ?>" <?= $code === $principale ? 'checked' : '' ?>>
            <span class="scelta__testo" lang="<?= Support::e($code) ?>"><?= Support::e($nomeL) ?></span></label>
        <?php endforeach; ?>
      </div>
      <?php /* 8C: le lingue aggiuntive come principale (Nederlands, 日本語…): con Plus occupa il posto della lingua in più. */
            $aggiuntive = array_filter($tutte, fn($c) => !MHW\I18n::base($c), ARRAY_FILTER_USE_KEY); ?>
      <details class="lingue-altre"<?= isset($aggiuntive[$principale]) ? ' open' : '' ?>>
        <summary><?= t('Un\'altra lingua') ?></summary>
        <div class="scelte scelte--riga" style="margin-top:8px">
          <?php foreach ($aggiuntive as $code => $nomeL): ?>
            <label class="scelta"><input type="radio" name="default_locale" value="<?= Support::e($code) ?>" <?= $code === $principale ? 'checked' : '' ?>>
              <span class="scelta__testo" lang="<?= Support::e($code) ?>"><?= Support::e($nomeL) ?></span></label>
          <?php endforeach; ?>
        </div>
        <p class="small muted" style="margin:8px 0 0"><?= t('I bottoni e i titoli della guida sono tradotti in olandese, portoghese (del Portogallo e del Brasile) e polacco; per le altre lingue l\'ospite li legge in inglese, i tuoi testi restano nella lingua che scegli.') ?></p>
      </details>
      <?php /* 9F·11 · Essential: tutte le lingue, senza traduzioni suggerite; Plus e Portfolio: con le suggerite. Poi «La tua lingua non c'è?» e la lingua del pannello. */
            $aidL = (int) ($prop['account_id'] ?? 0); $suggL = $aidL && MHW\Traduttore::nelPiano($aidL); ?>
      <div class="lingua-come__esempi" style="margin:12px 0 0">
        <div><b><?= t('Con Essential') ?></b><?= t('Scrivi nella tua lingua; gli ospiti la leggono nella tua e in una a scelta. Le traduzioni le scrivi tu.') ?></div>
        <div><b><?= t('Con Plus e Portfolio') ?></b><?= t('Scrivi nella tua lingua; ti suggeriamo le traduzioni in italiano, inglese, francese, tedesco e spagnolo, che approvi tu.') ?></div>
      </div>
      <p class="small muted" style="margin:10px 0 0"><?= t('La tua lingua non c\'è?') ?>
        <?php if (Support::contatto() !== ''): ?><a href="<?= Support::e(Support::mailto(t('Lingua mancante: ') . ($prop['name'] ?? ''), (MHW\Pannello::en() ? "Hi, I would like to write my guide in: " : "Ciao, vorrei scrivere la mia guida in: ") . "\n\n")) ?>"><?= t('Scrivici') ?></a><?php else: ?><?= t('Scrivici') ?><?php endif; ?> <?= t('e la aggiungiamo.') ?>
        · <?= t('Pannello in:') ?> <b><?= MHW\Pannello::en() ? 'English' : 'Italiano' ?></b> <?= t('(lo cambi in Account)') ?></p>
      <?php /* Come funziona se la guida non è in italiano: per la lingua scelta, o la versione per «la tua lingua». */
            if (!empty($prop['account_id'])) { $comeGenerico = $principale === 'it'; include __DIR__ . '/_lingua_come.php'; } ?>
    </details>
  <?php endif; ?>
  <fieldset class="fieldset">
    <legend><?= t('Orari') ?></legend>
    <div class="grid grid-2">
      <div class="field" style="margin:0"><label for="checkin_from"><?= t('Check-in dalle') ?></label><input id="checkin_from" name="checkin_from" type="time" value="<?= $c('checkin_from') ?>"></div>
      <div class="field" style="margin:0"><label for="checkout_by"><?= t('Check-out entro le') ?></label><input id="checkout_by" name="checkout_by" type="time" value="<?= $c('checkout_by') ?>"></div>
    </div>
  </fieldset>
  <?php (function (array $rip) { include __DIR__ . '/_ripetitore.php'; })([
      'name' => 'contacts', 'legend' => t('Chi risponde agli ospiti'),
      'help' => t('Compaiono nella guida, così l\'ospite chiama o scrive con un tocco. Il primo è quello principale: trascina per cambiare l\'ordine. Questi contatti compaiono nella guida e nella pagina delle recensioni: assicurati di poterli pubblicare.'),
      'sub' => [
          'name' => ['plain', t('Nome'), '', 'w' => 5],
          'phone' => ['tel', t('Telefono'), '', 'w' => 3],
          'role' => ['choice', t('Ruolo'), '', 'w' => 4, 'options' => ['host' => 'Host', 'cohost' => 'Co-host', 'pulizie' => t('Pulizie e chiavi'), 'manutenzione' => t('Manutenzione'), 'altro' => t('Altro')]],
          'whatsapp' => ['check', t('Risponde anche su WhatsApp'), '', 'w' => 12],
      ],
      'rows' => array_map(fn($x) => ['id' => 'c' . $x['id']] + $x, $contatti),
      'add' => t('Aggiungi un contatto'), 'item' => t('Contatto'), 'max' => 8]); ?>
  <?php if (array_key_exists('guest_email', $prop)): /* 8C: il bottone «Mandaci un'email» della pagina delle recensioni (da 1 a 4 stelle) */ ?>
    <div class="field" style="max-width:420px">
      <label for="guest_email"><?= t('Email per gli ospiti') ?> <span class="muted"><?= t('(facoltativa)') ?></span></label>
      <p class="help" id="guest-email-aiuto" style="margin:0 0 6px"><?= t('Dove ti scrive un ospite che vuole dirti cosa migliorare. Senza, nella pagina delle recensioni c\'è solo WhatsApp.') ?></p>
      <input type="email" id="guest_email" name="guest_email" maxlength="190" autocomplete="email" aria-describedby="guest-email-aiuto guest-email-pubblico" value="<?= Support::e((string) ($prop['guest_email'] ?? '')) ?>">
      <p class="help" id="guest-email-pubblico" style="margin:6px 0 0"><?= t('Questo contatto compare nella guida e nella pagina delle recensioni: assicurati di poterlo pubblicare.') ?></p>
    </div>
  <?php endif; ?>
  <?php if ($dopoPasso !== ''):
        $barraAvanti = '<button class="btn btn--go" name="dopo" value="' . Support::e($dopoPasso) . '">' . MHW\Pannello::t('Salva e continua') . ' <span class="go">' . Icon::svg('arrow', 18, 2) . '</span></button>';
        include __DIR__ . '/_barra_passo.php';
      else: ?>
  <div class="actions">
      <button class="btn"><?= t('Salva') ?></button>
  </div>
  <?php endif; ?>
</form>
