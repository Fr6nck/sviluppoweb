<?php
/**
 * L'account di chi è dentro: cambia la sua password. Il super admin vede
 * anche l'account del gestore e gli può dare una password nuova, se l'ha
 * dimenticata.
 *
 * Al gestore non si dice chi ha reimpostato la password: del super admin, per
 * lui, non c'è traccia.
 *
 * @var string     $utente
 * @var array      $mio         l'account di chi è dentro, senza l'hash
 * @var bool       $daCambiare  deve scegliere una password sua prima di fare altro
 * @var array|null $gestore     solo per il super admin: l'account del gestore ([] se non c'è)
 */
use ArcoDelVento\Support\Csrf;

$mio = $mio ?? [];
$quando = static fn (array $a, string $k): string => !empty($a[$k]) ? dataOra((string) $a[$k]) : '—';
?>
<?php if (!empty($daCambiare)): ?>
  <div class="adm-avviso adm-avviso--attenzione" role="status">
    <?= icona('attenzione', 18, 'adm-avviso__icona') ?>
    <div>
      <p><strong>Scegli una password tua per continuare.</strong> La tua password è stata reimpostata
         il <?= e($quando($mio, 'reimpostata')) ?>: quella con cui sei entrato la conosce anche chi te l'ha data.
         Come password attuale scrivi quella.</p>
    </div>
  </div>
<?php endif; ?>

<p class="adm-intro">Sei entrato come <strong><?= e($utente) ?></strong>.
  Ultimo cambio della password: <?= e($quando($mio, 'modificato')) ?>.</p>

<?php if (is_array($gestore ?? null)): ?><div class="adm-due"><?php endif; ?>

<section class="adm-carta" aria-labelledby="a-mia">
  <div class="adm-carta__testa"><h2 id="a-mia">Cambia la password</h2></div>
  <form method="post" action="<?= e(adminUrl('account')) ?>" class="adm-modulo adm-modulo--stretto" novalidate>
    <?= Csrf::field() ?>
    <div class="adm-campo">
      <label for="attuale">Password attuale</label>
      <input type="password" id="attuale" name="attuale" required autocomplete="current-password">
    </div>
    <div class="adm-campo">
      <label for="nuova">Nuova password</label>
      <input type="password" id="nuova" name="nuova" required minlength="10" autocomplete="new-password" aria-describedby="nuova-aiuto">
      <p class="adm-aiuto" id="nuova-aiuto">Almeno 10 caratteri. Le altre sessioni aperte con la vecchia password si chiudono.</p>
    </div>
    <div class="adm-campo">
      <label for="ripeti">Ripeti la nuova password</label>
      <input type="password" id="ripeti" name="ripeti" required minlength="10" autocomplete="new-password">
    </div>
    <p><button type="submit" class="adm-bottone">Cambia la password</button></p>
  </form>
</section>

<?php if (is_array($gestore ?? null)): ?>
  <section class="adm-carta" id="gestore" aria-labelledby="a-gestore">
    <div class="adm-carta__testa"><h2 id="a-gestore">La password del gestore</h2></div>
    <?php if ($gestore === []): ?>
      <p>Il gestore non ha ancora un account: lo crea la prima volta su <code>/admin</code>, con il codice
         <code>ADMIN_SETUP_TOKEN</code> del file <code>.env</code>.</p>
    <?php else: ?>
      <dl class="adm-dati adm-dati--piatta">
        <div><dt>Account</dt><dd><?= e((string) $gestore['utente']) ?></dd></div>
        <div><dt>Ultimo accesso</dt><dd><?= e($quando($gestore, 'ultimo_accesso')) ?></dd></div>
        <div><dt>Password cambiata</dt><dd><?= e($quando($gestore, 'modificato')) ?><?= !empty($gestore['da_cambiare']) ? ' · reimpostata: al prossimo accesso ne sceglie una sua' : (!empty($gestore['reimpostata']) ? ' · reimpostata da qui' : '') ?></dd></div>
      </dl>
      <p>Se <?= e((string) $gestore['utente']) ?> ha dimenticato la password, gliene dai una nuova da qui. Le sue
         sessioni aperte si chiudono subito. La password la comunichi tu, a voce o al telefono: il sito non la
         manda a nessuno.</p>
      <form method="post" action="<?= e(adminUrl('account')) ?>" class="adm-modulo adm-modulo--stretto" novalidate>
        <?= Csrf::field() ?>
        <input type="hidden" name="azione" value="reimposta">
        <div class="adm-campo">
          <label for="nuova-gestore">Nuova password per <?= e((string) $gestore['utente']) ?></label>
          <input type="password" id="nuova-gestore" name="nuova_gestore" required minlength="10" autocomplete="new-password" aria-describedby="nuova-gestore-aiuto">
          <p class="adm-aiuto" id="nuova-gestore-aiuto">Almeno 10 caratteri.</p>
        </div>
        <div class="adm-campo">
          <label for="ripeti-gestore">Ripeti la nuova password</label>
          <input type="password" id="ripeti-gestore" name="ripeti_gestore" required minlength="10" autocomplete="new-password">
        </div>
        <div class="adm-campo adm-campo--spunta">
          <input type="checkbox" id="da-cambiare" name="da_cambiare" value="1" checked aria-describedby="da-cambiare-aiuto">
          <label for="da-cambiare">Al prossimo accesso deve sceglierne una sua</label>
          <p class="adm-aiuto" id="da-cambiare-aiuto">Così la password che conosci tu vale per un accesso solo.</p>
        </div>
        <div class="adm-campo">
          <label for="conferma-reset">La tua password, per conferma</label>
          <input type="password" id="conferma-reset" name="conferma" required autocomplete="current-password">
        </div>
        <p><button type="submit" class="adm-bottone">Reimposta la password di <?= e((string) $gestore['utente']) ?></button></p>
      </form>
    <?php endif; ?>
  </section>
  </div>
<?php endif; ?>

<p class="adm-nota">Dopo due ore senza attività si esce da soli; dopo dodici ore in tutto, si rientra comunque.</p>
