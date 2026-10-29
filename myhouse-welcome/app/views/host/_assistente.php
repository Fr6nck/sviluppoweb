<?php use function MHW\t, MHW\tf;
/* 10 · Concierge AI nelle Impostazioni della guida: l'interruttore, i contatti quando la guida non sa, il
   contatore del mese e le domande rimaste senza risposta (da aggiungere alla guida). Con Essential il riquadro
   «Disponibile con Plus». Riceve: $prop, $acc. */
use function MHW\b;
use MHW\{Support, Csrf, Icon, Assistente};
if (!Assistente::disponibile()) return;
$aid = (int) $acc['id']; $pid = (int) $prop['id'];
$perche = Assistente::perche($aid, $prop);
$nelPiano = $perche !== 'piano';
$cont = $nelPiano ? Assistente::contatore($pid, $aid) : null;
$senza = $nelPiano ? Assistente::senzaRisposta($pid, null, 8) : [];
$acceso = (int) ($prop['assistant_enabled'] ?? 1) === 1;
$mesi = fn(string $iso) => MHW\Pannello::data($iso); ?>
<section class="fieldset stack" style="margin-top:24px;gap:12px" id="concierge-ai" aria-labelledby="chiedi-titolo">
  <div class="row" style="gap:10px;align-items:center;flex-wrap:wrap">
    <h2 id="chiedi-titolo" class="legend" style="margin:0;font-size:20px"><?= t('Concierge AI') ?> <a class="aiuto-q" href="<?= b() ?>/domande#assistente" target="_blank" rel="noopener" aria-label="<?= Support::e(t('Come funziona il Concierge AI')) ?>" title="<?= Support::e(t('Come funziona il Concierge AI')) ?>">?</a></h2>
    <?php if (!$nelPiano): ?><span class="badge badge--paper"><?= t('con il piano Plus') ?></span>
    <?php elseif ($perche === 'attivazione'): ?><span class="badge badge--ochre"><?= t('dopo l\'attivazione') ?></span>
    <?php elseif ($perche === 'configurazione'): ?><span class="badge badge--ochre"><?= t('non disponibile al momento') ?></span>
    <?php elseif ($perche === 'limite' || $perche === 'tetto'): ?><span class="badge badge--ochre"><?= t('Domande del mese finite') ?></span>
    <?php elseif ($acceso): ?><span class="badge badge--pine"><?= t('Acceso') ?></span>
    <?php else: ?><span class="badge badge--paper"><?= t('Spento') ?></span><?php endif; ?>
  </div>
  <?php if (!$nelPiano): /* Essential: il riquadro «Disponibile con Plus» */ ?>
    <div class="stack" style="gap:8px">
      <p style="margin:0;font-size:17px"><b><?= t('I tuoi ospiti ti scrivono alle 23 per la password del Wi-Fi?') ?></b></p>
      <p class="help" style="margin:0"><?= t('Con Plus il Concierge AI risponde agli ospiti al posto tuo, nella loro lingua, con quello che hai scritto tu. Se non lo sa, rimanda ai tuoi contatti. E ti dice cosa chiedono gli ospiti, così completi la guida.') ?></p>
      <div class="row" style="gap:10px;align-items:center;flex-wrap:wrap"><a class="btn btn--sm" href="<?= b() ?>/piano?passa=plus"><?= t('Passa a Plus') ?></a>
        <span class="small muted"><?= t('Oggi paghi solo la differenza per i giorni che restano.') ?></span></div>
    </div>
  <?php else: ?>
    <p class="help" style="margin:0"><?= t('Nella guida compare il bottone «Chiedi al Concierge»: l\'ospite scrive una domanda nella sua lingua e il Concierge AI risponde solo con quello che hai scritto nelle sezioni, indicando da quale viene. Se la guida non lo dice, lo scrive e mostra i tuoi contatti. Niente cookie, niente dati dell\'ospite.') ?></p>
    <?php if ($perche === 'attivazione'): ?>
      <p class="note" role="status" style="margin:0"><?= MHW\TestiSito::html('attivazione.spiegone') ?></p>
    <?php elseif ($perche === 'configurazione'): ?>
      <p class="note" role="status" style="margin:0"><?= t('Il Concierge AI non è disponibile in questo momento: ci stiamo lavorando, nella guida il bottone non compare.') ?></p>
    <?php endif; ?>
    <form method="post" action="<?= b() ?>/pannello/<?= $pid ?>/assistente" class="stack" style="gap:10px"><?= Csrf::field() ?>
      <div class="field interruttore-campo" style="margin:0">
        <label class="interruttore-check"><input type="checkbox" role="switch" name="acceso" value="1" <?= $acceso ? 'checked' : '' ?> aria-describedby="chiedi-aiuto">
          <span class="interruttore-check__testo"><?= t('Accendi il Concierge AI su questa guida') ?></span></label>
        <span class="help" id="chiedi-aiuto"><?= tf('Comprese {n} domande al mese per struttura; oltre, il Concierge AI si ferma fino al mese dopo (ti avvisiamo all\'80%).', ['{n}' => number_format((int) ($cont['limite'] ?? 0), 0, ',', '.')]) ?></span>
      </div>
      <label class="check"><input type="checkbox" name="contatti" value="1" <?= (int) ($prop['assistant_contacts'] ?? 1) === 1 ? 'checked' : '' ?>> <span><?= t('Quando la guida non sa rispondere, mostra i miei contatti (WhatsApp o telefono del primo contatto)') ?></span></label>
      <div class="row" style="gap:8px;align-items:center;flex-wrap:wrap"><button class="btn btn--sm"><?= t('Salva') ?></button>
        <?php if ($prop['status'] === 'published' && $perche === '' && $acceso): ?><a class="small" href="<?= b() ?>/g/<?= Support::e($prop['slug']) ?>" target="_blank" rel="noopener"><?= t('Provalo sulla tua guida') ?> <?= Icon::svg('external', 13, 2) ?></a><?php endif; ?></div>
    </form>
    <?php if ($cont): ?>
      <p class="small" style="margin:0"><b><?= tf('Questo mese: {n} domande su {limite}', ['{n}' => number_format((int) $cont['usate'], 0, ',', '.'), '{limite}' => number_format((int) $cont['limite'], 0, ',', '.')]) ?></b>
        <?php if ($cont['limite'] > 0): ?><span class="muted">(<?= (int) $cont['percento'] ?>%)</span><?php endif; ?> · <span class="muted"><?= t('si azzera il primo del mese') ?></span></p>
    <?php endif; ?>
    <?php if ($senza): ?>
      <div class="stack" style="gap:6px;padding:12px 14px;border-radius:16px;background:var(--ochre-soft)">
        <b style="font-size:15px"><?= t('Senza risposta: aggiungile alla guida') ?></b>
        <ul style="margin:0;padding-left:18px;font-size:14px;line-height:21px">
          <?php foreach ($senza as $q): ?><li>«<?= Support::e(mb_substr((string) $q['question'], 0, 140)) ?>» <span class="muted small"><?= Support::e($mesi((string) $q['created_at'])) ?><?= $q['locale'] !== '' ? ' · ' . strtoupper(Support::e((string) $q['locale'])) : '' ?></span></li><?php endforeach; ?>
        </ul>
        <p class="small muted" style="margin:0"><?= t('Due righe nella sezione giusta e la prossima volta il Concierge AI risponde da solo.') ?> <a href="<?= b() ?>/pannello/<?= $pid ?>"><?= t('Apri le sezioni') ?></a></p>
      </div>
    <?php elseif ($cont && $cont['usate'] > 0): ?>
      <p class="small muted" style="margin:0"><?= t('Nessuna domanda è rimasta senza risposta. Le domande sono anonime: qui vedi solo quelle a cui il Concierge AI non ha saputo rispondere.') ?></p>
    <?php endif; ?>
  <?php endif; ?>
</section>
