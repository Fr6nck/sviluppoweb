<?php use function MHW\tf;
/* La scelta delle lingue (8C: la principale, poi le altre con le regole del piano in _lingue_scelta.php).
   Quelle fuori dal piano si vedono col lucchetto: niente sorprese dopo.
   Riceve: $prop, $lingueAttive, $tutte, $dopoPasso ('' fuori dalla procedura). */
use function MHW\b; use function MHW\t;
use MHW\{Support, Csrf};
$def = $prop['default_locale']; $dopoPasso = $dopoPasso ?? ''; ?>
<form method="post" action="<?= b() ?>/pannello/<?= (int) $prop['id'] ?>/lingue" class="stack"<?= $dopoPasso !== '' ? ' data-autosave' : '' ?>><?= Csrf::field() ?>
  <fieldset class="fieldset">
    <legend><?= t('Lingue della guida') ?></legend>
    <p class="help"><?= t('Alla prima apertura l\'ospite sceglie la lingua; in cima trova quella del suo telefono, se è tra queste. Le traduzioni le scrivi tu. Con Plus puoi partire da quelle suggerite da un traduttore automatico, da approvare una per una.') ?></p>
    <?php include __DIR__ . '/_lingue_scelta.php'; ?>
  </fieldset>
  <?php if ($dopoPasso !== ''):
        $barraAvanti = '<button class="btn btn--go" name="dopo" value="' . Support::e($dopoPasso) . '">' . MHW\Pannello::t('Salva e continua') . ' <span class="go">' . MHW\Icon::svg('arrow', 18, 2) . '</span></button>';
        include __DIR__ . '/_barra_passo.php';
      else: ?>
  <div class="actions"><button class="btn"><?= t('Salva le lingue') ?></button></div>
  <?php endif; ?>
</form>
