<?php
/* 9V-bis · «Modifica Casa Checco»: dalla scheda del cliente della vetrina e da Testi e foto si entra nel pannello della
   guida dimostrativa già sulla pagina giusta (come «Entra come cliente»: un'ora al massimo, tutto nel Registro azioni).
   Riceve: $vet (id, name, user_id della guida della home), $da ('testi' per tornare a Testi e foto, '' altrimenti). */
use function MHW\b;
use MHW\{Support, Csrf, Icon};
$pidV = (int) $vet['id'];
$porte = ['/pannello/' . $pidV . '/casa' => ['photo', 'Foto e descrizione («La casa»)'],
          '/pannello/' . $pidV . '/procedura/aspetto' => ['palette', 'Copertina, foto del profilo e logo'],
          '/pannello/' . $pidV => ['layers', 'Tutta la guida (testi e sezioni)']]; ?>
<div class="stack" style="gap:8px" data-vetrina-modifica>
  <p class="small" style="margin:0"><b>Modifica «<?= Support::e((string) $vet['name']) ?>»</b>: si apre il suo pannello, come lo vede il cliente, sulla pagina che scegli.</p>
  <div class="row" style="gap:8px;flex-wrap:wrap">
    <?php $primo = true; foreach ($porte as $vai => [$icona, $testo]): ?>
      <form method="post" action="<?= b() ?>/admin/entra/<?= (int) $vet['user_id'] ?>" style="margin:0"><?= Csrf::field() ?>
        <input type="hidden" name="vai" value="<?= Support::e($vai) ?>"><?php if (($da ?? '') !== ''): ?><input type="hidden" name="da" value="<?= Support::e($da) ?>"><?php endif; ?>
        <button class="btn btn--sm<?= $primo ? '' : ' btn--ghost' ?>"><?= Icon::svg($icona, 15) ?><?= Support::e($testo) ?></button></form>
    <?php $primo = false; endforeach; ?>
  </div>
  <p class="small muted" style="margin:0">Togli, aggiungi o riordina le foto lì. La home le mostra dopo <b>«Pubblica in home»</b> (nella barra rossa in alto); «Torna al mio account» riporta qui.</p>
</div>
