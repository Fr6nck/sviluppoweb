<?php
/* Pacchetti e versioni. Cambiare prezzo o funzioni crea una versione nuova:
   chi ha già comprato resta sulla sua. I testi commerciali si cambiano sul
   pacchetto e compaiono subito sulla landing. */
use function MHW\b;
use MHW\{Support, Csrf, Plans};
$title = 'Pacchetti'; ?>
<div class="stack stack--lg">
  <div class="stack stack--sm">
    <h1>Pacchetti.</h1>
    <p class="muted small">Prezzi IVA esclusa, annuali. Una versione venduta non si modifica mai: si crea la successiva. Il Price ID di Stripe è facoltativo:
      se manca, il checkout usa il prezzo della versione.</p>
  </div>

  <?php
  /* Le funzioni come si leggono: «4 sezioni · 2 lingue · Logo», non «sections=4». */
  $nomiBrevi = ['photos' => 'Foto nelle sezioni', 'pdf' => 'PDF', 'logo' => 'Logo', 'cover' => 'Copertina', 'profile_image' => 'Immagine profilo',
                'palette' => 'Colori', 'places' => 'Luoghi consigliati', 'analytics' => 'Statistiche', 'branding' => 'Marchio personalizzato'];
  $etichettaDi = array_column($features, 'label', 'code');
  $etichette = function (array $v) use ($nomiBrevi, $etichettaDi): array {
      $f = $v['features']; $out = [];
      $n = fn(string $k) => $f[$k] ?? null;
      if (($x = $n('sections')) !== null) $out[] = $x === 'unlimited' ? 'Sezioni illimitate' : ((int) $x === 1 ? '1 sezione' : (int) $x . ' sezioni');
      if (($x = $n('locales')) !== null) $out[] = $x === 'unlimited' ? 'Tutte le lingue' : ((int) $x === 1 ? '1 lingua' : (int) $x . ' lingue');
      foreach ($f as $k => $x) {
          if (in_array($k, ['sections', 'locales', 'properties'], true) || $x === '0' || $x === '') continue;
          $nome = $nomiBrevi[$k] ?? ($etichettaDi[$k] ?? $k);
          $out[] = $x === '1' ? $nome : $nome . ': ' . ($x === 'unlimited' ? 'illimitato' : $x);
      }
      if (Plans::perProperty($v)) $out[] = 'Strutture: quelle acquistate (da ' . (int) $v['min_quantity'] . ')';
      elseif (($x = $n('properties')) !== null) $out[] = $x === 'unlimited' ? 'Strutture illimitate' : ((int) $x === 1 ? '1 struttura' : (int) $x . ' strutture');
      return $out;
  };
  $inVendita = array_filter($packages, fn($p) => (int) $p['public'] === 1);
  $nascosti = array_filter($packages, fn($p) => (int) $p['public'] !== 1); ?>
  <?php foreach ($inVendita as $p) include __DIR__ . '/_pacchetto.php'; ?>

  <?php if ($nascosti): ?>
    <details class="fieldset nascosti">
      <summary class="legend" style="cursor:pointer;min-height:32px">Piani nascosti (<?= count($nascosti) ?>)
        <span class="small muted" style="font-weight:400">· fuori listino: restano per chi li ha già comprati</span></summary>
      <div class="stack" style="margin-top:12px">
        <?php foreach ($nascosti as $p) include __DIR__ . '/_pacchetto.php'; ?>
      </div>
    </details>
  <?php endif; ?>
</div>
