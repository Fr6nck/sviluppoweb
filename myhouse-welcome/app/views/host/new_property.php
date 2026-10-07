<?php use MHW\{Support, Csrf, Icon, Plans, SectionCatalog, Copia}; $title = 'Nuova struttura'; $piano = $piano ?? null;
$modo = $modo ?? 'normale'; $origini = $origini ?? []; ?>
<div class="stack stack--lg" style="max-width:560px">
  <?php if ($piano): ?>
    <p class="piano-scelto" role="status"><span><?= Icon::svg('check', 16, 2.2) ?>Piano <b><?= Support::e($piano['name']) ?></b><?= Plans::perProperty($piano) ? ' per ' . (int) $quantita . ' strutture' : '' ?> scelto · non paghi adesso</span>
      <a href="<?= MHW\b() ?>/piano">Cambia</a></p>
  <?php endif; ?>

<?php if ($modo === 'bloccata'): /* Portfolio non pagato: le altre strutture nascono col solo nome */ ?>
  <div class="stack stack--sm">
    <span class="kicker">Portfolio · struttura <?= $have + 1 ?> di <?= (int) $max ?></span>
    <h1>Come si chiama?</h1>
    <p class="lead">Per ora basta il nome. Prima del pagamento si configura una struttura alla volta: questa si sblocca appena attivi il Portfolio,
      e allora potrai anche crearla partendo dalla prima.</p>
  </div>
  <?php if ($err): ?><p class="note note--err" role="alert"><?= Support::e($err) ?></p><?php endif; ?>
  <form method="post" class="stack"><?= Csrf::field() ?>
    <div class="field" style="margin:0"><label for="name">Nome della struttura</label>
      <input id="name" name="name" type="text" required maxlength="120" autocomplete="off" placeholder="Per esempio: Casa sul Mare" autofocus></div>
    <div class="actions"><button class="btn">Crea la struttura</button>
      <a class="btn btn--quiet" href="<?= MHW\b() ?>/pannello">Annulla</a></div>
  </form>

<?php elseif ($modo === 'a-pagamento'): /* Portfolio pagato e pieno: una struttura in più sull'abbonamento */ ?>
  <div class="stack stack--sm">
    <span class="kicker">Portfolio · una struttura in più</span>
    <h1>Aggiungi una struttura.</h1>
    <p class="lead">Il tuo abbonamento comprende <?= (int) $max ?> strutture. Aggiungendone una passa a <?= (int) $costo['quantita'] ?>.</p>
  </div>
  <?php if ($err): ?><p class="note note--err" role="alert"><?= Support::e($err) ?></p><?php endif; ?>
  <div class="panel stack" style="gap:10px">
    <div class="spread spread--mid"><span>Ogni struttura in più</span><b><?= Support::e(Support::money($costo['anno'], $pv['currency'])) ?> <span class="small muted">+ IVA / anno</span></b></div>
    <div class="spread spread--mid"><span>Oggi, per i giorni che restano fino al <?= Support::e(Support::date($costo['fine'])) ?></span>
      <b><?= Support::e(Support::money($costo['ora'], $pv['currency'])) ?> <span class="small muted">+ IVA</span></b></div>
    <p class="small muted">Paghi la differenza sulla pagina sicura di Stripe: la struttura si sblocca appena il pagamento è confermato. Dal rinnovo l'abbonamento costa
      <?= Support::e(Support::money($costo['totale'], $pv['currency'])) ?> + IVA all'anno.</p>
  </div>
  <?php if ($costo['fuori']): ?>
    <p class="note">Hai raggiunto il numero massimo di strutture del Portfolio. Scrivici e troviamo una soluzione.</p>
  <?php else: ?>
    <form method="post" class="stack"><?= Csrf::field() ?>
      <div class="field" style="margin:0"><label for="name">Nome della struttura</label>
        <input id="name" name="name" type="text" required maxlength="120" autocomplete="off" placeholder="Per esempio: Casa sul Mare"></div>
      <div class="field" style="margin:0"><label for="city">Città</label>
        <input id="city" name="city" type="text" maxlength="120" autocomplete="off"></div>
      <label class="check"><input type="checkbox" name="conferma" value="1" required> <span>Confermo: aggiungi una struttura all'abbonamento</span></label>
      <div class="actions"><button class="btn btn--go">Aggiungi e vai al pagamento <span class="go"><?= Icon::svg('arrow', 18, 2) ?></span></button>
        <a class="btn btn--quiet" href="<?= MHW\b() ?>/pannello">Annulla</a></div>
    </form>
  <?php endif; ?>

<?php else: ?>
  <div class="stack stack--sm">
    <span class="kicker">Passo 1 di 5 · Struttura e contatti</span>
    <h1>Come si chiama la tua struttura?</h1>
    <p class="lead">Bastano il nome e la città. Check-in, Wi-Fi, regole e consigli li aggiungi dopo, un passo per volta.
      Puoi fermarti quando vuoi: quello che scrivi resta salvato.</p>
  </div>
  <?php if ($err): ?><p class="note note--err" role="alert"><?= Support::e($err) ?></p><?php endif; ?>
  <?php if ($have >= $max): ?>
    <div class="limit"><p>Hai già <?= (int) $have ?> struttur<?= $have === 1 ? 'a' : 'e' ?>, il massimo del tuo piano.</p>
      <a class="btn btn--sm" href="<?= MHW\b() ?>/piano?passa=portfolio"><?= $piano && Plans::perProperty($piano) ? 'Cambia il numero di strutture' : 'Scopri Portfolio' ?></a></div>
  <?php else: ?>
    <form method="post" class="stack"><?= Csrf::field() ?>
      <div class="field" style="margin:0"><label for="name">Nome della struttura</label>
        <input id="name" name="name" type="text" required maxlength="120" autocomplete="off" placeholder="Per esempio: Casa Lucia" autofocus></div>
      <div class="field" style="margin:0"><label for="city">Città</label>
        <input id="city" name="city" type="text" maxlength="120" autocomplete="off" placeholder="Per esempio: Montepulciano"></div>
      <?php if ($origini): /* Crea da una struttura esistente: facoltativo */ ?>
        <details class="fieldset copia" <?= !empty($_POST['origine']) ? 'open' : '' ?>>
          <summary class="legend" style="cursor:pointer;min-height:32px">Crea da una struttura esistente <span class="small muted">(facoltativo)</span></summary>
          <div class="stack" style="margin-top:12px">
            <div class="field" style="margin:0"><label for="origine">Parti da</label>
              <select id="origine" name="origine">
                <option value="">Nessuna, comincio da zero</option>
                <?php foreach ($origini as $o): ?><option value="<?= (int) $o['id'] ?>"><?= Support::e($o['name'] . ($o['city'] ? ' · ' . $o['city'] : '')) ?></option><?php endforeach; ?>
              </select></div>
            <?php include __DIR__ . '/_copia_scelte.php'; ?>
          </div>
        </details>
      <?php endif; ?>
      <div class="actions"><button class="btn btn--go">Continua <span class="go"><?= Icon::svg('arrow', 18, 2) ?></span></button></div>
    </form>
  <?php endif; ?>
<?php endif; ?>
</div>
