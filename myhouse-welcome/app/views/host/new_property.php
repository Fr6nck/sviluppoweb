<?php use function MHW\t, MHW\tf; use MHW\{Support, Csrf, Icon, Plans, SectionCatalog, Copia}; $title = t('Nuova struttura'); $piano = $piano ?? null;
$modo = $modo ?? 'normale'; $origini = $origini ?? []; ?>
<div class="stack stack--lg" style="max-width:560px">
  <?php if ($piano): ?>
    <p class="piano-scelto" role="status"><span><?= Icon::svg('check', 16, 2.2) ?><?= Plans::perProperty($piano) ? tf('Piano {piano} per {n} strutture scelto · non paghi adesso', ['{piano}' => '<b>' . Support::e($piano['name']) . '</b>', '{n}' => (int) $quantita]) : tf('Piano {piano} scelto · non paghi adesso', ['{piano}' => '<b>' . Support::e($piano['name']) . '</b>']) ?></span>
      <a href="<?= MHW\b() ?>/piano"><?= t('Cambia') ?></a></p>
  <?php endif; ?>

<?php if ($modo === 'bloccata'): /* Portfolio non pagato: le altre strutture nascono col solo nome */ ?>
  <div class="stack stack--sm">
    <span class="kicker"><?= Support::e(tf('Portfolio · struttura {n} di {max}', ['{n}' => $have + 1, '{max}' => (int) $max])) ?></span>
    <h1><?= t('Come si chiama?') ?></h1>
    <p class="lead"><?= t('Per ora basta il nome. Prima del pagamento si configura una struttura alla volta: questa si sblocca appena attivi il Portfolio, e allora potrai anche crearla partendo dalla prima.') ?></p>
  </div>
  <?php if ($err): ?><p class="note note--err" role="alert"><?= Support::e(t($err)) ?></p><?php endif; ?>
  <form method="post" class="stack"><?= Csrf::field() ?>
    <div class="field" style="margin:0"><label for="name"><?= t('Nome della struttura') ?></label>
      <input id="name" name="name" type="text" required maxlength="120" autocomplete="off" placeholder="<?= MHW\Support::e(t('Per esempio: Casa sul Mare')) ?>" autofocus></div>
    <div class="actions"><button class="btn"><?= t('Crea la struttura') ?></button>
      <a class="btn btn--quiet" href="<?= MHW\b() ?>/pannello"><?= t('Annulla') ?></a></div>
  </form>

<?php elseif ($modo === 'a-pagamento'): /* Portfolio pagato e pieno: una struttura in più sull'abbonamento */ ?>
  <div class="stack stack--sm">
    <span class="kicker"><?= t('Portfolio · una struttura in più') ?></span>
    <h1><?= t('Aggiungi una struttura.') ?></h1>
    <p class="lead"><?= Support::e(tf('Il tuo abbonamento comprende {max} strutture. Aggiungendone una passa a {n}.', ['{max}' => (int) $max, '{n}' => (int) $costo['quantita']])) ?></p>
  </div>
  <?php if ($err): ?><p class="note note--err" role="alert"><?= Support::e(t($err)) ?></p><?php endif; ?>
  <div class="panel stack" style="gap:10px">
    <div class="spread spread--mid"><span><?= Support::e(tf('La {n}ª struttura', ['{n}' => (int) $costo['quantita']])) ?></span><b><?= Support::e(Support::money($costo['anno'], $pv['currency'])) ?> <span class="small muted"><?= t('+ IVA / anno') ?></span></b></div>
    <div class="spread spread--mid"><span><?= Support::e(tf('Oggi, per i giorni che restano fino al {data}', ['{data}' => MHW\Pannello::data($costo['fine'])])) ?></span>
      <b><?= Support::e(Support::money($costo['ora'], $pv['currency'])) ?> <span class="small muted"><?= t('+ IVA') ?></span></b></div>
    <p class="small muted"><?= Support::e(tf('Paghi la differenza sulla pagina sicura di Stripe: la struttura si sblocca appena il pagamento è confermato. Dal rinnovo l\'abbonamento costa {prezzo} + IVA all\'anno.', ['{prezzo}' => Support::money($costo['totale'], $pv['currency'])])) ?></p>
  </div>
  <?php if ($costo['fuori']): ?>
    <div class="limit"><p><?= Support::e(tf('Hai raggiunto il numero massimo di strutture del Portfolio ({n}). Più di {n} strutture? Ti facciamo una proposta.', ['{n}' => (int) $pv['max_quantity']])) ?></p>
      <a class="btn btn--sm" href="<?= Support::e(Support::mailtoPreventivo()) ?>"><?= t('Chiedi un preventivo') ?></a></div>
  <?php else: ?>
    <form method="post" class="stack"><?= Csrf::field() ?>
      <div class="field" style="margin:0"><label for="name"><?= t('Nome della struttura') ?></label>
        <input id="name" name="name" type="text" required maxlength="120" autocomplete="off" placeholder="<?= MHW\Support::e(t('Per esempio: Casa sul Mare')) ?>"></div>
      <div class="field" style="margin:0"><label for="city"><?= t('Città') ?></label>
        <input id="city" name="city" type="text" maxlength="120" autocomplete="off"></div>
      <label class="check"><input type="checkbox" name="conferma" value="1" required> <span><?= t('Confermo: aggiungi una struttura all\'abbonamento') ?></span></label>
      <div class="actions"><button class="btn btn--go"><?= t('Aggiungi e vai al pagamento') ?> <span class="go"><?= Icon::svg('arrow', 18, 2) ?></span></button>
        <a class="btn btn--quiet" href="<?= MHW\b() ?>/pannello"><?= t('Annulla') ?></a></div>
    </form>
  <?php endif; ?>

<?php else: ?>
  <div class="stack stack--sm">
    <span class="kicker"><?= t('Passo 1 di 5 · Struttura e contatti') ?></span>
    <h1><?= t('Come si chiama la tua struttura?') ?></h1>
    <p class="lead"><?= t('Bastano il nome e la città. Check-in, Wi-Fi, regole e consigli li aggiungi dopo, un passo per volta. Puoi fermarti quando vuoi: quello che scrivi resta salvato.') ?></p>
  </div>
  <?php if ($err): ?><p class="note note--err" role="alert"><?= Support::e(t($err)) ?></p><?php endif; ?>
  <?php if ($have >= $max): ?>
    <div class="limit"><p><?= Support::e($have === 1 ? t('Hai già 1 struttura, il massimo del tuo piano.') : tf('Hai già {n} strutture, il massimo del tuo piano.', ['{n}' => (int) $have])) ?></p>
      <a class="btn btn--sm" href="<?= MHW\b() ?>/piano?passa=portfolio"><?= $piano && Plans::perProperty($piano) ? t('Cambia il numero di strutture') : t('Scopri Portfolio') ?></a></div>
  <?php else: ?>
    <form method="post" class="stack"><?= Csrf::field() ?>
      <div class="field" style="margin:0"><label for="name"><?= t('Nome della struttura') ?></label>
        <input id="name" name="name" type="text" required maxlength="120" autocomplete="off" placeholder="<?= MHW\Support::e(t('Per esempio: Casa Lucia')) ?>" autofocus></div>
      <div class="field" style="margin:0"><label for="city"><?= t('Città') ?></label>
        <input id="city" name="city" type="text" maxlength="120" autocomplete="off" placeholder="<?= MHW\Support::e(t('Per esempio: Montepulciano')) ?>"></div>
      <?php if ($origini): /* Crea da una struttura esistente: facoltativo */ ?>
        <details class="fieldset copia" <?= !empty($_POST['origine']) ? 'open' : '' ?>>
          <summary class="legend" style="cursor:pointer;min-height:32px"><?= t('Crea da una struttura esistente') ?> <span class="small muted"><?= t('(facoltativo)') ?></span></summary>
          <div class="stack" style="margin-top:12px">
            <div class="field" style="margin:0"><label for="origine"><?= t('Parti da') ?></label>
              <select id="origine" name="origine">
                <option value=""><?= t('Nessuna, comincio da zero') ?></option>
                <?php foreach ($origini as $o): ?><option value="<?= (int) $o['id'] ?>"><?= Support::e($o['name'] . ($o['city'] ? ' · ' . $o['city'] : '')) ?></option><?php endforeach; ?>
              </select></div>
            <?php include __DIR__ . '/_copia_scelte.php'; ?>
          </div>
        </details>
      <?php endif; ?>
      <div class="actions"><button class="btn btn--go"><?= t('Continua') ?> <span class="go"><?= Icon::svg('arrow', 18, 2) ?></span></button></div>
    </form>
  <?php endif; ?>
<?php endif; ?>
</div>
