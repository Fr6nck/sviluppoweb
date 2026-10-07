<?php
/* Nome, luogo, orari, contatti dell'host e, nella procedura, la lingua in cui
   si scrive la guida. Riceve: $prop, $dopoPasso, e se c'è la lingua $tutte e $consentite. */
use function MHW\b;
use MHW\{Support, Csrf, Icon, Db};
$dopoPasso = $dopoPasso ?? '';
$tipi = MHW\Properties::TIPOLOGIE;
$contatti = Db::all('SELECT * FROM property_contacts WHERE property_id = ? ORDER BY position, id', [$prop['id']]);
$c = fn(string $k) => Support::e((string) $prop[$k]); ?>
<form method="post" action="<?= b() ?>/pannello/<?= (int) $prop['id'] ?>/impostazioni" class="stack"<?= $dopoPasso !== '' ? ' data-autosave' : '' ?>><?= Csrf::field() ?>
  <fieldset class="fieldset">
    <legend>La struttura</legend>
    <div class="field" style="margin:0"><label for="name">Nome</label><input type="text" id="name" name="name" required maxlength="120" autocomplete="off" value="<?= $c('name') ?>"></div>
    <div class="field" style="margin:0"><span class="label">Tipologia</span>
      <div class="scelte scelte--riga" role="radiogroup" aria-label="Tipologia">
        <?php foreach ($tipi as $k => $et): ?>
          <label class="scelta"><input type="radio" name="property_type" value="<?= Support::e($k) ?>" <?= (string) ($prop['property_type'] ?? '') === $k ? 'checked' : '' ?>>
            <span class="scelta__testo"><?= Support::e($et) ?></span></label>
        <?php endforeach; ?>
      </div>
    </div>
    <?php /* Con «Altro» si scrive che tipo di struttura è. Senza JavaScript il campo si vede sempre. */ ?>
    <div class="field" style="margin:0" id="tipo-altro" data-se-altro>
      <label for="property_type_other">Che tipo di struttura è?</label>
      <input type="text" id="property_type_other" name="property_type_other" maxlength="60" placeholder="Area camper, glamping, ostello…"
             value="<?= Support::e((string) ($prop['property_type_other'] ?? '')) ?>">
    </div>
    <div class="field" style="margin:0"><label for="address">Indirizzo</label>
      <p class="help" style="margin:0 0 6px">Via e numero civico. Precompila «Come arrivare» e il link a Maps: lo scrivi una volta sola.</p>
      <input type="text" id="address" name="address" maxlength="255" autocomplete="off" value="<?= $c('address') ?>"></div>
    <?php /* Una griglia sola a tre colonne: CIN occupa le prime due, così i bordi dei campi
             sono in colonna con CAP, Città e Zona; i campi stanno in basso, allineati anche
             quando l'aiuto sopra va a capo su più righe. */ ?>
    <div class="grid grid-3 campi-allineati">
      <div class="field" style="margin:0"><label for="postal_code">CAP</label><input type="text" id="postal_code" name="postal_code" maxlength="10" inputmode="numeric" autocomplete="off" value="<?= $c('postal_code') ?>"></div>
      <div class="field" style="margin:0"><label for="city">Città</label><input type="text" id="city" name="city" maxlength="120" autocomplete="off" value="<?= $c('city') ?>"></div>
      <div class="field" style="margin:0"><label for="region">Zona o regione</label><input type="text" id="region" name="region" maxlength="120" autocomplete="off" value="<?= $c('region') ?>"></div>
      <div class="field campo-largo" style="margin:0"><label for="cin">CIN <span class="muted">(facoltativo)</span></label>
        <p class="help" style="margin:0 0 6px">Il codice identificativo nazionale degli affitti brevi: compare in piccolo in fondo alla guida.</p>
        <input type="text" id="cin" name="cin" maxlength="40" spellcheck="false" autocapitalize="characters" autocomplete="off" placeholder="IT…" value="<?= $c('cin') ?>"></div>
      <div class="field" style="margin:0"><label for="beds">Posti letto <span class="muted">(facoltativo)</span></label>
        <p class="help" style="margin:0 0 6px">Quante persone può ospitare la struttura.</p>
        <input type="number" id="beds" name="beds" min="0" max="999" step="1" inputmode="numeric" value="<?= (int) ($prop['beds'] ?? 0) ?: '' ?>"></div>
    </div>
  </fieldset>
  <?php if (isset($consentite, $tutte)): $principale = $prop['default_locale'] ?: 'it'; ?>
    <fieldset class="fieldset">
      <legend>In che lingua scrivi la guida?</legend>
      <p class="help">È la lingua principale: quella in cui scrivi tutti i testi. Le altre lingue sono facoltative e le aggiungi alla fine.
        Sceglila prima di scrivere: se la cambi dopo, i testi già scritti restano nella lingua in cui li hai scritti.</p>
      <div class="scelte scelte--riga">
        <?php foreach ($tutte as $code => $nomeL): if (!in_array($code, $consentite, true)) continue; ?>
          <label class="scelta"><input type="radio" name="default_locale" value="<?= Support::e($code) ?>" <?= $code === $principale ? 'checked' : '' ?>>
            <span class="scelta__testo"><?= Support::e($nomeL) ?></span></label>
        <?php endforeach; ?>
      </div>
    </fieldset>
  <?php endif; ?>
  <fieldset class="fieldset">
    <legend>Orari</legend>
    <div class="grid grid-2">
      <div class="field" style="margin:0"><label for="checkin_from">Check-in dalle</label><input id="checkin_from" name="checkin_from" type="time" value="<?= $c('checkin_from') ?>"></div>
      <div class="field" style="margin:0"><label for="checkout_by">Check-out entro le</label><input id="checkout_by" name="checkout_by" type="time" value="<?= $c('checkout_by') ?>"></div>
    </div>
  </fieldset>
  <?php (function (array $rip) { include __DIR__ . '/_ripetitore.php'; })([
      'name' => 'contacts', 'legend' => 'Chi risponde agli ospiti',
      'help' => 'Compaiono nella guida, così l\'ospite chiama o scrive con un tocco. Il primo è quello principale: trascina per cambiare l\'ordine.',
      'sub' => [
          'name' => ['plain', 'Nome', '', 'w' => 5],
          'phone' => ['tel', 'Telefono', '', 'w' => 3],
          'role' => ['choice', 'Ruolo', '', 'w' => 4, 'options' => ['host' => 'Host', 'cohost' => 'Co-host', 'pulizie' => 'Pulizie e chiavi', 'manutenzione' => 'Manutenzione', 'altro' => 'Altro']],
          'whatsapp' => ['check', 'Risponde anche su WhatsApp', '', 'w' => 12],
      ],
      'rows' => array_map(fn($x) => ['id' => 'c' . $x['id']] + $x, $contatti),
      'add' => 'Aggiungi un contatto', 'item' => 'Contatto', 'max' => 8]); ?>
  <?php if ($dopoPasso !== ''):
        $barraAvanti = '<button class="btn btn--go" name="dopo" value="' . Support::e($dopoPasso) . '">Salva e continua <span class="go">' . Icon::svg('arrow', 18, 2) . '</span></button>';
        include __DIR__ . '/_barra_passo.php';
      else: ?>
  <div class="actions">
      <button class="btn">Salva</button>
  </div>
  <?php endif; ?>
</form>
