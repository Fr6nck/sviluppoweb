<?php
/* La scheda di un cliente: chi è, cosa ha comprato (versione compresa),
   cosa può fare e perché, le sue guide, i pagamenti, il registro. */
use function MHW\b;
use MHW\{Support, Csrf, Db};
$title = ($acc['user_name'] ?: $acc['email']) . ' — Cliente';
$etichette = array_column(Db::all('SELECT code, label FROM features ORDER BY id'), 'label', 'code');
$fonte = ['package' => 'piano pagato', 'intended' => 'piano scelto', 'override' => 'eccezione', 'default' => 'predefinito'];
include __DIR__ . '/_stati.php'; ?>
<div class="stack stack--lg">
  <div class="spread">
    <div class="stack stack--sm">
      <a class="small" href="<?= b() ?>/admin/clienti">← Clienti</a>
      <h1><?= Support::e($acc['user_name'] ?: $acc['email']) ?>.<?php if (!empty($acc['is_test'])): ?> <span class="badge badge--sea" style="vertical-align:middle">Prova</span><?php endif; ?></h1>
      <p class="small muted"><?= Support::e($acc['email']) ?> · registrato il <?= Support::e(Support::date($acc['registrato'])) ?> ·
        <?= $acc['email_verified_at'] ? 'email confermata' : '<b>email non confermata</b>' ?></p>
    </div>
    <form method="post" action="<?= b() ?>/admin/entra/<?= (int) $acc['user_id'] ?>" style="margin:0"><?= Csrf::field() ?>
      <button class="btn btn--ghost btn--sm" aria-label="Entra come cliente"><span class="btn__lungo">Entra come cliente</span><span class="btn__corto" aria-hidden="true">Entra</span></button></form>
  </div>

  <?php /* 9I · I tre livelli di codici, con «Copia»; quello trovato con «Cerca» in evidenza. */
        if (!empty($codici)): $ev = (string) ($evidenzia ?? ''); $copia = fn(string $c) => '<button type="button" class="btn btn--ghost btn--sm" data-copia="' . Support::e($c) . '" data-copiato="Copiato" hidden>Copia</button>'; ?>
  <section class="panel stack codici-cliente" style="gap:10px" aria-labelledby="codici-titolo">
    <div class="row" style="gap:8px;align-items:center;flex-wrap:wrap"><span class="kicker" id="codici-titolo">Codice cliente</span>
      <code class="codici-cliente__codice"><?= Support::e($codici['cliente']) ?></code><?= $copia($codici['cliente']) ?></div>
    <?php if ($codici['strutture']): ?>
      <div class="stack" style="gap:4px"><span class="small muted">Strutture</span>
        <?php foreach ($codici['strutture'] as $cs): $idEv = 'struttura-' . $cs['id']; ?>
          <div class="row codici-cliente__riga<?= $ev === $idEv ? ' codici-cliente__riga--evidenza' : '' ?>" id="<?= $idEv ?>" style="gap:8px;align-items:center;flex-wrap:wrap">
            <code><?= Support::e($cs['code']) ?></code><span class="small"><?= Support::e($cs['name']) ?></span><?= $copia($cs['code']) ?></div>
        <?php endforeach; ?></div>
    <?php endif; ?>
    <?php if ($codici['pagamenti']): $statiO = ['paid' => 'pagato', 'pending' => 'in attesa', 'awaiting' => 'in verifica', 'failed' => 'non riuscito', 'canceled' => 'annullato', 'expired' => 'scaduto']; ?>
      <details class="stack" style="gap:4px"<?= str_starts_with($ev, 'pagamento-') ? ' open' : '' ?>><summary class="small muted">Pagamenti (<?= count($codici['pagamenti']) ?>)</summary>
        <?php foreach ($codici['pagamenti'] as $cp): $idEv = 'pagamento-' . $cp['codice']; ?>
          <div class="row codici-cliente__riga<?= $ev === $idEv ? ' codici-cliente__riga--evidenza' : '' ?>" id="<?= Support::e($idEv) ?>" style="gap:8px;align-items:center;flex-wrap:wrap">
            <code><?= Support::e($cp['codice']) ?></code>
            <span class="small"><?= $cp['order_id'] ? ($cp['kind'] === 'change' ? 'Conguaglio' : 'Ordine') . ' · ' . Support::e(Support::money((int) $cp['amount_cents'], (string) $cp['currency'])) . ' · ' . Support::e($statiO[$cp['status']] ?? (string) $cp['status'])
                                                 : 'Rinnovo · fattura ' . Support::e((string) $cp['invoice_id']) ?> · <?= Support::e(Support::date((string) $cp['created_at'])) ?></span><?= $copia($cp['codice']) ?></div>
        <?php endforeach; ?></details>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <?php /* 7F: «Cliente di prova»: fuori da Quadro, Vendite, Prospetti, Scadenze e Marketing, niente richiami automatici né richiesta di giudizio. */
        if (array_key_exists('is_test', $acc)): ?>
  <form method="post" action="<?= b() ?>/admin/cliente/<?= (int) $acc['id'] ?>/prova" class="panel spread spread--mid cliente-prova" style="gap:12px;flex-wrap:wrap"><?= Csrf::field() ?>
    <label class="check" style="margin:0"><input type="checkbox" name="prova" value="1"<?= !empty($acc['is_test']) ? ' checked' : '' ?>>
      <span><b>Cliente di prova</b><br><span class="small muted">Non entra in Quadro, Vendite, Prospetti, Scadenze e Marketing; non riceve richiami automatici né la richiesta di giudizio. Per i tuoi account di collaudo e per chi usa la piattaforma gratis.</span></span></label>
    <button class="btn btn--ghost btn--sm">Salva</button>
  </form>
  <?php endif; ?>

  <?php $completa = MHW\Fatturazione::completa($acc); $tipoF = (string) ($acc['billing_type'] ?? ''); ?>
  <div class="grid grid-2" style="align-items:start">
    <section class="panel stack nota-interna" id="nota" style="gap:8px" aria-labelledby="nota-titolo">
      <span class="kicker" id="nota-titolo">Nota interna</span>
      <form method="post" action="<?= b() ?>/admin/cliente/<?= (int) $acc['id'] ?>/nota" class="stack" style="gap:8px"><?= Csrf::field() ?>
        <label for="nota-testo" class="sr-only">Nota interna sul cliente</label>
        <textarea id="nota-testo" name="nota" maxlength="4000" placeholder="Telefonate, accordi, solleciti. Il cliente non la vede."><?= Support::e((string) ($acc['admin_note'] ?? '')) ?></textarea>
        <div class="spread spread--mid"><span class="tiny muted"><?= !empty($acc['admin_note_at']) ? 'Aggiornata il ' . Support::e(Support::date($acc['admin_note_at'])) : 'Solo per l\'amministrazione.' ?></span>
          <button class="btn btn--ghost btn--sm">Salva la nota</button></div>
      </form>
    </section>
    <section class="panel stack" style="gap:6px" aria-labelledby="fatt-titolo">
      <div class="spread spread--mid"><span class="kicker" id="fatt-titolo">Fatturazione</span>
        <span class="badge badge--<?= $completa ? 'pine' : 'ochre' ?>"><?= $completa ? 'Completa' : 'Incompleta' ?></span></div>
      <?php if (($acc['billing_name'] ?? '') === ''): ?><p class="small muted">Dati non ancora inseriti.</p><?php else: ?>
        <p class="small" style="margin:0"><b><?= Support::e($acc['billing_name']) ?></b><?= $tipoF !== '' ? ' · ' . Support::e(MHW\Fatturazione::TIPI[$tipoF] ?? $tipoF) : '' ?></p>
        <p class="small" style="margin:0"><?= ($acc['vat'] ?? '') !== '' ? 'P.IVA ' . Support::e($acc['vat']) . ' · ' : '' ?><?= ($acc['cf'] ?? '') !== '' ? 'CF ' . Support::e($acc['cf']) : '' ?></p>
        <p class="small" style="margin:0"><?= ($acc['sdi'] ?? '') !== '' ? 'SDI ' . Support::e($acc['sdi']) : '' ?><?= ($acc['pec'] ?? '') !== '' ? ' · PEC ' . Support::e($acc['pec']) : '' ?></p>
        <p class="small muted" style="margin:0"><?= Support::e(trim(($acc['billing_address'] ?? '') . ', ' . ($acc['billing_postal'] ?? '') . ' ' . ($acc['billing_city'] ?? '') . ' ' . ($acc['billing_province'] ?? ''), ' ,')) ?></p>
      <?php endif; ?>
    </section>
  </div>

  <div class="grid grid-2" style="align-items:start">
    <section class="panel stack" style="gap:8px">
      <span class="kicker">Consensi</span>
      <p class="small">Termini: versione <?= Support::e($acc['terms_version'] ?: '—') ?>, accettati il <?= Support::e(Support::date($acc['terms_accepted_at'] ?? '')) ?: '—' ?></p>
      <p class="small">Privacy: versione <?= Support::e($acc['privacy_version'] ?: '—') ?>, presa visione il <?= Support::e(Support::date($acc['privacy_accepted_at'] ?? '')) ?: '—' ?></p>
      <?php /* 8J: l'Accordo sul trattamento dei dati (Allegato A), e se deve ancora accettare la versione in vigore */ ?>
      <p class="small">Accordo sul trattamento dei dati: versione <?= Support::e(($acc['dpa_version'] ?? '') ?: '—') ?>, accettato il <?= Support::e(Support::date($acc['dpa_accepted_at'] ?? '')) ?: '—' ?></p>
      <?php if (MHW\Legale::daAccettare(['role' => 'host'] + $acc)): ?><p class="small"><span class="badge badge--alert">Da accettare</span> le versioni in vigore: al prossimo accesso vede la finestra, e fino ad allora il pannello è in sola lettura.</p><?php endif; ?>
    </section>
    <section class="panel stack" style="gap:8px">
      <span class="kicker">Stripe</span>
      <p class="small">Cliente su Stripe: <code><?= Support::e($acc['stripe_customer_id'] ?: '—') ?></code></p>
      <p class="small">Piano scelto: <?= $acc['intended_package_version_id'] ? 'versione #' . (int) $acc['intended_package_version_id'] : '—' ?></p>
      <?php if (!empty($omaggio)): /* 9D · il codice omaggio prenotato o usato */ ?>
        <p class="small">Codice omaggio: <code><?= Support::e($omaggio['codice']) ?></code> · <?= (int) $omaggio['mesi'] ?> mesi<?= !empty($omaggio['usato_at']) ? ', usato il ' . Support::e(Support::date($omaggio['usato_at'])) : ', prenotato (vale fino al ' . Support::e(Support::date($omaggio['scade_il'])) . ')' ?></p>
      <?php endif; ?>
    </section>
  </div>

  <?php /* La guida vetrina: una demo completa dentro questo account (nomi e dati di fantasia). */
        $vetrina = null; foreach ($props as $p) if ((int) $p['is_demo'] === MHW\Demo::VETRINA) $vetrina = $p;
        $conPiano = (bool) MHW\Subscriptions::active((int) $acc['id']); ?>
  <section class="panel stack" style="gap:10px" aria-labelledby="vetrina-titolo">
    <h2 id="vetrina-titolo" style="font-size:22px">Guida vetrina</h2>
    <?php if ($vetrina): ?>
      <p class="small">«<?= Support::e($vetrina['name']) ?>» è la guida vetrina di questo account: è online come demo, non conta nel limite di strutture ed è la demo della landing.</p>
      <?php /* 9C: l'indirizzo attuale (sempre da Support::baseUrl), con «Apri» e «Copia». */ $urlV = Support::baseUrl() . '/g/' . $vetrina['slug']; ?>
      <div class="row" style="gap:8px;flex-wrap:wrap;align-items:center"><code class="impostazioni__url"><?= Support::e($urlV) ?></code>
        <a class="btn btn--ghost btn--sm" href="<?= Support::e($urlV) ?>" target="_blank" rel="noopener">Apri</a>
        <button type="button" class="btn btn--ghost btn--sm" data-copia="<?= Support::e($urlV) ?>" data-copiato="Copiato" hidden><?= MHW\Icon::svg('copy', 15) ?>Copia</button></div>
      <?php if ($vetrina['slug'] !== 'casa-checco'): ?><p class="small muted" style="margin:0">/g/casa-checco è la guida di un cliente vero: la vetrina tiene questo indirizzo.</p><?php endif; ?>
      <form method="post" action="<?= b() ?>/admin/cliente/<?= (int) $acc['id'] ?>/vetrina" class="stack" style="gap:8px"><?= Csrf::field() ?>
        <input type="hidden" name="rifai" value="1">
        <p class="small muted">«Rifai la vetrina» la toglie e ne crea una nuova con i dati aggiornati (eventi con le prossime date, foto, luoghi). Le modifiche fatte a mano sulla vetrina si perdono; le altre strutture dell'account non si toccano.</p>
        <button class="btn btn--ghost btn--sm" style="align-self:flex-start">Rifai la vetrina</button>
      </form>
      <form method="post" action="<?= b() ?>/admin/cliente/<?= (int) $acc['id'] ?>/vetrina/traduzioni" class="stack" style="gap:8px"><?= Csrf::field() ?>
        <p class="small muted">«Completa le traduzioni della vetrina» scrive solo i campi ancora vuoti in inglese, francese, tedesco e spagnolo, senza toccare il resto, e ripubblica.</p>
        <button class="btn btn--ghost btn--sm" style="align-self:flex-start">Completa le traduzioni della vetrina</button>
      </form>
    <?php else: ?>
      <p class="small">Una guida dimostrativa completa sulla falsariga di Casa Lucia, con nomi, numeri, indicazioni e foto diversi, tutti di fantasia: «Casa Checco», ad Assisi, vicino a Piazza Matteotti. Tutte le sezioni sono compilate, eventi compresi. Si pubblica subito come demo, non occupa il posto di una struttura e diventa la demo della landing. Il cliente la modifica dal suo pannello.</p>
      <form method="post" action="<?= b() ?>/admin/cliente/<?= (int) $acc['id'] ?>/vetrina" class="stack" style="gap:10px"><?= Csrf::field() ?>
        <?php if (!$conPiano): ?>
          <label class="check"><input type="checkbox" name="plus" value="1" checked> <span>Concedi Plus dimostrativo per 12 mesi: senza un piano la guida esce senza foto, senza inglese e senza luoghi.</span></label>
        <?php endif; ?>
        <button class="btn btn--sm" style="align-self:flex-start">Crea la guida vetrina</button>
      </form>
    <?php endif; ?>
  </section>

  <?php /* 9E · Le azioni sul cliente: fermare il rinnovo, cambiare il pacchetto, rimborsare, password. Ogni azione: motivo nel registro ed email al cliente. */
        $subAtt = MHW\Subscriptions::active((int) $acc['id']); $stripeAtt = $subAtt && $subAtt['provider'] === 'stripe' && $subAtt['provider_subscription_id'] !== '';
        $pianoAtt = $subAtt ? MHW\Plans::version((int) $subAtt['package_version_id']) : null; $qAtt = max(1, (int) ($subAtt['quantity'] ?? 1));
        $inOmaggioA = $subAtt && $subAtt['status'] === 'trialing' && !empty($subAtt['omaggio_fino']); ?>
  <?php /* 9N · 10 bis · L'offerta su misura: prezzo concordato, rinnovo, promemoria degli sconti del programma, sconto deciso a mano. */
        $offAtt = MHW\Offerte::disponibili() ? MHW\Offerte::attiva((int) $acc['id']) : null;
        if ($offAtt): $subO = MHW\Subscriptions::active((int) $acc['id']); $memo = MHW\Offerte::promemoria((int) $acc['id']);
              $giorniO = (int) floor((strtotime((string) $subO['current_period_end']) - time()) / 86400); $prossimaO = !empty($subO['next_offerta_id']) ? MHW\Offerte::riga((int) $subO['next_offerta_id']) : null; ?>
  <section class="panel stack" id="offerta" style="gap:10px" aria-labelledby="offerta-titolo">
    <h2 id="offerta-titolo" style="font-size:22px;margin:0">Portfolio su misura · <?= (int) $offAtt['strutture'] ?> strutture · <?= Support::e(Support::money((int) $offAtt['prezzo_cents'])) ?> + IVA <span class="badge badge--pine">attivo</span></h2>
    <p class="muted small" style="margin:0">Rinnovo il <?= Support::e(Support::date((string) $subO['current_period_end'])) ?> · prezzo concordato bloccato<?= $prossimaO ? ' · dal rinnovo: ' . (int) $prossimaO['strutture'] . ' strutture a ' . Support::e(Support::money((int) $prossimaO['prezzo_cents'])) . ' + IVA' : '' ?></p>
    <div class="tablewrap"><table class="data"><tbody>
      <tr><td>Inviti andati a buon fine (promemoria)</td><td style="text-align:right"><?= (int) $memo['amici'] ?> amici · varrebbero <?= (int) $memo['inviti_pct'] ?>%</td></tr>
      <tr><td>Giudizio con sconto (promemoria)</td><td style="text-align:right"><?= $memo['giudizio'] ? 'aderito · varrebbe ' . (int) $memo['giudizio_pct'] . '%' : 'non aderito' ?></td></tr>
      <tr><td><b>Sconto sul prossimo rinnovo (deciso da te)</b></td><td style="text-align:right">
        <form method="post" action="<?= b() ?>/admin/cliente/<?= (int) $acc['id'] ?>/offerta/sconto" class="row" style="gap:6px;justify-content:flex-end;align-items:center"><?= Csrf::field() ?>
          <label class="sr-only" for="of-sc">Sconto sul prossimo rinnovo (%)</label>
          <input id="of-sc" name="sconto" type="number" min="0" max="90" value="<?= (int) $offAtt['sconto_rinnovo_pct'] ?>" style="width:80px"<?= $giorniO < MHW\Offerte::GIORNI_SCONTO ? ' disabled' : '' ?>> %
          → <b><?= Support::e(Support::money(MHW\Sconti::netto((int) ($prossimaO['prezzo_cents'] ?? $offAtt['prezzo_cents']), (int) $offAtt['sconto_rinnovo_pct']))) ?></b> + IVA
          <button class="btn btn--ghost btn--sm"<?= $giorniO < MHW\Offerte::GIORNI_SCONTO ? ' disabled' : '' ?>>Salva</button></form></td></tr>
    </tbody></table></div>
    <p class="small muted" style="margin:0">Lo sconto vale per ogni rinnovo finché non lo cambi (0 lo toglie) e si può cambiare fino a <?= MHW\Offerte::GIORNI_SCONTO ?> giorni prima del rinnovo; l'avviso dei 30 giorni al cliente mostra l'importo già scontato. Ogni modifica va nel Registro azioni.
      <a href="<?= b() ?>/admin/cliente/<?= (int) $acc['id'] ?>/offerta">Nuova offerta (più strutture)</a></p>
  </section>
  <?php endif; ?>

  <div class="grid grid-2" style="align-items:start" id="abbonamento">
    <section class="panel stack" style="gap:8px" aria-labelledby="abb-titolo">
      <h2 id="abb-titolo" style="font-size:19px;margin:0">Abbonamento</h2>
      <?php if (!$subAtt): ?><p class="small muted" style="margin:0">Nessun abbonamento attivo: qui sotto puoi attivarne uno a mano.</p>
      <?php else: $addA = $stripeAtt && !(int) $subAtt['cancel_at_period_end'] ? MHW\Richiami::prossimoAddebito($subAtt) : null; ?>
        <p class="small" style="margin:0"><?= Support::e($pianoAtt['name'] ?? '') ?><?= $qAtt > 1 ? ' · ' . $qAtt . ' strutture' : '' ?> · <?= $inOmaggioA ? 'in omaggio fino al ' . Support::e(Support::date($subAtt['omaggio_fino'])) : Support::e($statoAbbonamento[$subAtt['status']] ?? $subAtt['status']) ?>
          · <?= (int) $subAtt['cancel_at_period_end'] ? 'rinnovo fermato: finisce il ' . Support::e(Support::date($subAtt['current_period_end'])) : ($stripeAtt ? ($inOmaggioA ? 'primo addebito' : 'rinnovo automatico') . ' il ' . Support::e(Support::date($subAtt['current_period_end'])) . ($addA ? ' · ' . Support::e(Support::money($addA['netto'], $addA['valuta'])) . ' + IVA' : '') : 'attivato dallo staff fino al ' . Support::e(Support::date($subAtt['current_period_end']))) ?>
          <?= ($subAtt['card_last4'] ?? '') !== '' ? ' · carta •••• ' . Support::e($subAtt['card_last4']) : '' ?></p>
        <?php if ($stripeAtt): ?>
          <form method="post" action="<?= b() ?>/admin/cliente/<?= (int) $acc['id'] ?>/rinnovo" class="row" style="gap:8px;flex-wrap:wrap;align-items:flex-end"><?= Csrf::field() ?>
            <input type="hidden" name="ferma" value="<?= (int) $subAtt['cancel_at_period_end'] ? '0' : '1' ?>">
            <div class="field" style="margin:0;flex:1 1 180px"><label for="rin-motivo" class="small">Motivo (resta nel registro)</label><input id="rin-motivo" name="motivo" type="text" required maxlength="200" placeholder="Es. chiesto per telefono il <?= gmdate('d/m') ?>"></div>
            <button class="btn btn--ghost btn--sm"><?= (int) $subAtt['cancel_at_period_end'] ? 'Riattiva il rinnovo' : 'Ferma il rinnovo' ?></button>
          </form>
          <p class="small muted" style="margin:0"><?= (int) $subAtt['cancel_at_period_end'] ? 'Riattivando, il prossimo addebito è il ' . Support::e(Support::date($subAtt['current_period_end'])) . '. Il cliente riceve un\'email.' : 'Resta attivo fino al ' . Support::e(Support::date($subAtt['current_period_end'])) . ', poi la guida va offline. Nessun addebito. Il cliente riceve un\'email.' ?></p>
        <?php endif; ?>
      <?php endif; ?>
    </section>
    <section class="panel stack" style="gap:8px" id="cambio" aria-labelledby="cambio-titolo">
      <h2 id="cambio-titolo" style="font-size:19px;margin:0">Cambia il pacchetto</h2>
      <?php if (!$stripeAtt): ?><p class="small muted" style="margin:0">Solo per un abbonamento Stripe attivo (quelli dallo staff si cambiano con «Attiva un abbonamento manuale»).</p>
      <?php else: $cb = $cambio ?? null; $ok = $cb && !empty($cb['ok']); ?>
        <p class="small muted" style="margin:0">Per chi non ci riesce da solo. Anteprima prima di confermare.</p>
        <?php if ($cb && !$ok): ?><p class="note note--err" role="alert" style="margin:0"><?= Support::e($cb['errore']) ?></p><?php endif; ?>
        <form method="post" action="<?= b() ?>/admin/cliente/<?= (int) $acc['id'] ?>/cambio" class="stack" style="gap:8px"><?= Csrf::field() ?>
          <div class="row" style="gap:8px;flex-wrap:wrap;align-items:flex-end">
            <div class="field" style="margin:0;flex:1 1 160px"><label for="cb-piano" class="small">Piano</label>
              <select id="cb-piano" name="piano"><?php foreach ($pianiVendita as $o): ?><option value="<?= Support::e($o['code']) ?>"<?= ($cb['pv']['code'] ?? '') === $o['code'] ? ' selected' : '' ?>><?= Support::e($o['name']) ?></option><?php endforeach; ?></select></div>
            <div class="field" style="margin:0;width:7em"><label for="cb-strutture" class="small">Strutture</label><input id="cb-strutture" name="strutture" type="number" min="1" max="10" value="<?= (int) ($cb['q'] ?? $qAtt) ?>"></div>
            <button class="btn btn--ghost btn--sm" name="passo" value="anteprima">Anteprima</button>
          </div>
          <?php if ($ok): $pr = $cb['prev']; $sale = $pr['tipo'] !== MHW\CambioPiano::SCENDE; ?>
            <div class="stack" style="gap:6px">
              <?php if ($sale && $pr['conguaglio'] > 0): ?>
                <label class="check" style="align-items:flex-start"><input type="radio" name="modo" value="subito"<?= $cb['modo'] !== 'rinnovo' ? ' checked' : '' ?>><span><b>Subito, con conguaglio</b>: il cliente riceve il link e paga la differenza (<?= Support::e(Support::money($pr['conguaglio'], (string) $cb['pv']['currency'])) ?> + IVA per i <?= (int) $pr['giorni'] ?> giorni che restano). Il cambio parte quando paga.</span></label>
              <?php elseif ($sale): ?>
                <label class="check" style="align-items:flex-start"><input type="radio" name="modo" value="subito"<?= $cb['modo'] !== 'rinnovo' ? ' checked' : '' ?>><span><b>Subito</b>: nessuna differenza da pagare.</span></label>
              <?php else: ?>
                <p class="small muted" style="margin:0">Scendere di piano si fa dal rinnovo: niente rimborsi, nessun addebito.</p>
              <?php endif; ?>
              <label class="check" style="align-items:flex-start"><input type="radio" name="modo" value="rinnovo"<?= $cb['modo'] === 'rinnovo' || !$sale ? ' checked' : '' ?>><span><b>Dal rinnovo</b> (<?= Support::e(Support::date($pr['fine'])) ?>): paga <?= Support::e(Support::money($cb['addebito']['listino'], (string) $cb['pv']['currency'])) ?> + IVA<?= $cb['addebito']['percento'] > 0 ? ' meno gli sconti maturati (' . (int) $cb['addebito']['percento'] . '%) = ' . Support::e(Support::money($cb['addebito']['netto'], (string) $cb['pv']['currency'])) . ' + IVA' : '' ?>.</span></label>
              <?php if (!empty($cb['cambia']['perde'])): ?><p class="small muted" style="margin:0">Cosa perde: <?= Support::e(implode(' ', $cb['cambia']['perde'])) ?></p><?php endif; ?>
            </div>
            <div class="field" style="margin:0"><label for="cb-motivo" class="small">Motivo (resta nel registro)</label><input id="cb-motivo" name="motivo" type="text" required maxlength="200" value="<?= Support::e((string) $cb['motivo']) ?>" placeholder="Es. chiesto per telefono il <?= gmdate('d/m') ?>"></div>
            <div class="actions"><button class="btn btn--sm" name="passo" value="conferma">Conferma e avvisa il cliente</button></div>
          <?php endif; ?>
        </form>
      <?php endif; ?>
      <?php if ($stripeAtt && MHW\ProposteCambio::disponibili()): $propC = MHW\ProposteCambio::diAccount((int) $acc['id']); /* 9R · proposto da noi, vale solo con la conferma del cliente */ ?>
        <details class="proposta-cambio" id="proposta"<?= !empty($_SESSION['proposta_wa']) ? ' open' : '' ?>>
          <summary><b>Proponi un cambio di piano</b> <span class="small muted">· con uno sconto, vale solo se il cliente conferma dal suo link</span></summary>
          <?php if (!empty($_SESSION['proposta_wa'])): $waP = (string) $_SESSION['proposta_wa']; unset($_SESSION['proposta_wa']); ?>
            <div class="spiega" role="status"><span class="small">Il messaggio per WhatsApp (stesso link dell'email). Mostrato solo adesso: il link non si può ricostruire.</span>
              <label for="prop-wa" class="sr-only">Messaggio per WhatsApp</label><textarea id="prop-wa" rows="7" readonly style="width:100%"><?= Support::e($waP) ?></textarea>
              <button type="button" class="btn btn--ghost btn--sm" data-copia-da="prop-wa">Copia il messaggio per WhatsApp</button></div>
          <?php endif; ?>
          <form method="post" action="<?= b() ?>/admin/cliente/<?= (int) $acc['id'] ?>/proposta" class="stack" style="gap:8px;margin-top:8px"><?= Csrf::field() ?>
            <div class="row" style="gap:8px;flex-wrap:wrap;align-items:flex-end">
              <div class="field" style="margin:0;flex:1 1 160px"><label for="pr-piano" class="small">Nuovo piano</label>
                <select id="pr-piano" name="piano"><?php foreach ($pianiVendita as $o): ?><option value="<?= Support::e($o['code']) ?>"><?= Support::e($o['name']) ?></option><?php endforeach; ?></select></div>
              <div class="field" style="margin:0;width:7em"><label for="pr-strutture" class="small">Strutture</label><input id="pr-strutture" name="strutture" type="number" min="1" max="10" value="<?= (int) $qAtt ?>"></div>
              <div class="field" style="margin:0;width:8em"><label for="pr-sconto" class="small">Sconto %</label><input id="pr-sconto" name="sconto" type="number" min="0" max="<?= MHW\ProposteCambio::SCONTO_MAX ?>" value="10"></div>
              <div class="field" style="margin:0;width:8em"><label for="pr-giorni" class="small">Valida (giorni)</label><input id="pr-giorni" name="giorni" type="number" min="1" max="60" value="<?= MHW\ProposteCambio::GIORNI ?>"></div>
            </div>
            <fieldset class="row" style="gap:12px;border:0;padding:0;margin:0"><legend class="small">Quando</legend>
              <label class="check"><input type="radio" name="quando" value="subito" checked> <span>Subito, con il conguaglio scontato</span></label>
              <label class="check"><input type="radio" name="quando" value="rinnovo"> <span>Dal rinnovo, sconto sul primo anno del piano nuovo</span></label></fieldset>
            <div class="field" style="margin:0"><label for="pr-msg" class="small">Messaggio (facoltativo, compare nell'email)</label><textarea id="pr-msg" name="messaggio" rows="2" maxlength="1000"></textarea></div>
            <div class="row" style="gap:8px;flex-wrap:wrap"><button class="btn btn--sm" name="invio" value="email">Manda per email</button>
              <button class="btn btn--ghost btn--sm" name="invio" value="whatsapp">Prepara il messaggio per WhatsApp</button></div>
          </form>
          <?php if ($propC): ?>
          <div class="tablewrap" style="margin-top:8px"><table class="data">
            <thead><tr><th>Proposta</th><th>Stato</th><th>Valida fino al</th><th><span class="sr-only">Azioni</span></th></tr></thead>
            <tbody><?php foreach ($propC as $pc): $pvP = MHW\Plans::version((int) $pc['package_version_id']); ?>
              <tr><td class="small"><?= Support::e(($pvP['name'] ?? '?') . ((int) $pc['quantita'] > 1 ? ' · ' . (int) $pc['quantita'] . ' strutture' : '') . ' · ' . ($pc['quando'] === 'subito' ? 'subito' : 'dal rinnovo') . ' · −' . (int) $pc['sconto_pct'] . '%') ?><br><span class="tiny muted">del <?= Support::e(Support::date((string) $pc['created_at'])) ?></span></td>
                <td class="small"><?= Support::e(MHW\ProposteCambio::STATI[$pc['stato']] ?? $pc['stato']) ?><?= $pc['accettata_at'] ? ' il ' . Support::e(Support::date((string) $pc['accettata_at'])) : '' ?></td>
                <td class="small"><?= Support::e(Support::date((string) $pc['valida_fino'])) ?></td>
                <td><?php if ($pc['stato'] === 'inviata'): ?><form method="post" action="<?= b() ?>/admin/cliente/<?= (int) $acc['id'] ?>/proposta/<?= (int) $pc['id'] ?>/annulla" style="margin:0"><?= Csrf::field() ?><button class="linkbtn">Annulla</button></form><?php endif; ?></td></tr>
            <?php endforeach; ?></tbody></table></div>
          <?php endif; ?>
        </details>
      <?php endif; ?>
    </section>
  </div>
  <div class="grid grid-2" style="align-items:start">
    <section class="panel stack" style="gap:8px" id="fatture" aria-labelledby="fatt2-titolo">
      <h2 id="fatt2-titolo" style="font-size:19px;margin:0">Fatture e rimborsi</h2>
      <?php if (!$fatture): ?><p class="small muted" style="margin:0"><?= MHW\Stripe::enabled() && $acc['stripe_customer_id'] !== '' ? 'Nessuna fattura pagata su Stripe.' : 'Nessun cliente Stripe ancora.' ?></p>
      <?php else: ?>
        <div class="tablewrap"><table class="data"><thead><tr><th>Data</th><th>Fattura</th><th>Importo</th><th>Stato</th></tr></thead><tbody>
          <?php foreach ($fatture as $f): ?>
            <tr><td class="small"><?= Support::e(Support::date($f['data'])) ?></td><td><code><?= Support::e($f['numero']) ?></code><?php if (($cpF = MHW\Codici::perFattura((string) $f['id'])) !== ''): ?><br><span class="tiny muted"><?= Support::e($cpF) ?></span><?php endif; ?></td>
              <td><?= Support::e(Support::money($f['netto'], $f['valuta'])) ?> + IVA</td>
              <td class="small"><?= $f['rimborsato'] > 0 ? '<span class="badge badge--ochre">Rimborsata ' . Support::e(Support::money($f['rimborsato'], $f['valuta'])) . '</span>' : 'Pagata' ?></td></tr>
          <?php endforeach; ?></tbody></table></div>
        <form method="post" action="<?= b() ?>/admin/cliente/<?= (int) $acc['id'] ?>/rimborso" class="spiega stack" style="gap:8px" onsubmit="return confirm('Confermi il rimborso su Stripe?')"><?= Csrf::field() ?>
          <div class="row" style="gap:8px;flex-wrap:wrap;align-items:flex-end">
            <div class="field" style="margin:0;flex:1 1 200px"><label for="rb-inv" class="small">Fattura</label>
              <select id="rb-inv" name="invoice"><?php foreach ($fatture as $f): ?><option value="<?= Support::e($f['id']) ?>"><?= Support::e($f['numero']) ?> · <?= Support::e(Support::money($f['totale'], $f['valuta'])) ?> con IVA</option><?php endforeach; ?></select></div>
            <div class="field" style="margin:0;width:8em"><label for="rb-importo" class="small">Rimborsa €</label><input id="rb-importo" name="importo" type="text" inputmode="decimal" placeholder="tutto"></div>
            <div class="field" style="margin:0;flex:1 1 160px"><label for="rb-motivo" class="small">Motivo</label>
              <select id="rb-motivo" name="motivo"><?php foreach (MHW\AzioniCliente::MOTIVI_RIMBORSO as $k => $et): ?><option value="<?= $k ?>"><?= Support::e($et) ?></option><?php endforeach; ?></select></div>
          </div>
          <div class="field" style="margin:0"><label for="rb-testo" class="small">Dettaglio (obbligatorio con «altro»)</label><input id="rb-testo" name="testo" type="text" maxlength="255"></div>
          <p class="tiny muted" style="margin:0">Parziale: l'abbonamento non cambia. Totale sul pagamento del periodo in corso: quando Stripe lo conferma l'abbonamento si chiude («Rimborsato») e la guida va offline. Nota di credito da fare in Adamo. Il cliente riceve un'email con importo e tempi (5–10 giorni).</p>
          <div class="actions"><button class="btn btn--sm btn--danger">Conferma il rimborso</button></div>
        </form>
      <?php endif; ?>
      <?php if (!empty($rimborsiFatti)): /* 9O · i rimborsi da qualunque parte (sito, Stripe, Adamo), con il promemoria della nota di credito */
        $origini = ['admin' => 'dal sito', 'stripe' => 'da Stripe', 'adamo' => 'da Adamo']; ?>
        <h3 id="rimborsi" style="font-size:16px;margin:6px 0 0">Rimborsi</h3>
        <div class="tablewrap"><table class="data"><thead><tr><th>Data</th><th>Importo</th><th>Da dove</th><th>Stato</th><th>Nota di credito</th></tr></thead><tbody>
          <?php foreach ($rimborsiFatti as $rb): $daAdamo = ($rb['origine'] ?? 'admin') === 'adamo'; ?>
            <tr><td class="small"><?= Support::e(Support::date($rb['created_at'])) ?><?= (string) ($rb['code'] ?? '') !== '' ? '<br><span class="tiny muted">' . Support::e($rb['code']) . '</span>' : '' ?></td>
              <td><?= Support::e(Support::money((int) $rb['amount_cents'])) ?> <span class="tiny muted">con IVA</span></td>
              <td class="small"><?= Support::e($origini[$rb['origine'] ?? 'admin'] ?? (string) $rb['origine']) ?></td>
              <td class="small"><?= Support::e(['confermato' => 'Confermato', 'richiesto' => 'In corso', 'fallito' => 'Fallito'][$rb['stato']] ?? $rb['stato']) ?></td>
              <td class="small"><?php if ($rb['stato'] === 'fallito'): ?>—<?php elseif (!empty($rb['nota_credito_at'])): ?><?= $daAdamo ? 'La fa Adamo' : 'Fatta il ' . Support::e(Support::date($rb['nota_credito_at'])) ?>
                <?php else: ?><span class="badge badge--alert">Da fare in Adamo</span>
                  <form method="post" action="<?= b() ?>/admin/rimborsi/<?= (int) $rb['id'] ?>/nota" style="display:inline"><?= Csrf::field() ?><input type="hidden" name="fatta" value="1"><button class="linkbtn">Segna come fatta</button></form>
                <?php endif; ?></td></tr>
          <?php endforeach; ?></tbody></table></div>
      <?php endif; ?>
    </section>
    <section class="panel stack" style="gap:8px" id="accesso" aria-labelledby="acc-titolo">
      <h2 id="acc-titolo" style="font-size:19px;margin:0">Accesso</h2>
      <p class="small muted" style="margin:0">La password non si vede mai. Il link vale un'ora; la provvisoria 48 ore e va cambiata al primo accesso.</p>
      <div class="row" style="gap:8px;flex-wrap:wrap">
        <form method="post" action="<?= b() ?>/admin/cliente/<?= (int) $acc['id'] ?>/password/link" style="margin:0"><?= Csrf::field() ?><button class="btn btn--ghost btn--sm">Manda il link per reimpostarla</button></form>
        <form method="post" action="<?= b() ?>/admin/cliente/<?= (int) $acc['id'] ?>/password/provvisoria" style="margin:0" onsubmit="return confirm('Impostare una password provvisoria? Quella attuale smette di valere.')"><?= Csrf::field() ?><button class="btn btn--ghost btn--sm">Imposta una password provvisoria</button></form>
      </div>
      <?php if (!empty($_SESSION['pw_provvisoria'])): $pwP = (string) $_SESSION['pw_provvisoria']; unset($_SESSION['pw_provvisoria']); ?>
        <div class="spiega" role="status">Password provvisoria: <b class="pw-provvisoria"><?= Support::e($pwP) ?></b><br><span class="small">Mostrata solo adesso. Vale 48 ore; al primo accesso il cliente deve sceglierne una nuova. Il cliente ha ricevuto un'email di avviso.</span></div>
      <?php endif; ?>
      <?php if (!empty($acc['must_change_password']) || !empty($acc['password_temp_until'])): ?>
        <p class="small muted" style="margin:0"><?= !empty($acc['must_change_password']) ? 'Password provvisoria in corso' : '' ?><?= !empty($acc['password_temp_until']) ? ' (valida fino al ' . Support::e(Support::date($acc['password_temp_until'])) . ')' : '' ?>.</p>
      <?php endif; ?>
    </section>
    <?php $elP = MHW\AccountProva::riepilogo((int) $acc['id']); $codP = MHW\Codici::cliente((int) $acc['id']); /* 9R · solo senza pagamenti live */ ?>
    <?php if (MHW\Staff::disponibili()): $staffOn = MHW\Staff::e($acc); /* 9R · tester interni: niente pagamenti, niente Stripe */ ?>
    <section class="panel stack" style="gap:8px" id="staff" aria-labelledby="staff-titolo">
      <h2 id="staff-titolo" style="font-size:19px;margin:0">Account staff <?= $staffOn ? '<span class="badge badge--pine">Staff</span>' : '' ?></h2>
      <?php if ($staffOn): ?>
        <p class="small" style="margin:0">Fino al <b><?= Support::e(Support::date((string) $acc['staff_fino'])) ?></b><?= (int) $acc['staff_no_vetrina'] === 1 ? ' · non in vetrina' : '' ?>. Niente pagamenti, fatture né Stripe; fuori da Cassa, Scadenze, richiami di pagamento, commissioni e statistiche.</p>
        <form method="post" action="<?= b() ?>/admin/cliente/<?= (int) $acc['id'] ?>/staff" style="margin:0"><?= Csrf::field() ?><input type="hidden" name="togli" value="1"><button class="btn btn--ghost btn--sm">Togli lo staff</button></form>
      <?php else: ?>
        <p class="small muted" style="margin:0">Per chi prova il sistema dall'interno: può fare tutto come un cliente, senza pagare. Diverso dal cliente di prova.</p>
        <form method="post" action="<?= b() ?>/admin/cliente/<?= (int) $acc['id'] ?>/staff" class="row" style="gap:8px;align-items:flex-end;flex-wrap:wrap;margin:0"><?= Csrf::field() ?>
          <div class="field" style="margin:0;flex:1 1 140px"><label for="st-piano" class="small">Piano</label>
            <select id="st-piano" name="pv"><?php foreach ($pianiVendita as $o): ?><option value="<?= (int) $o['pv_id'] ?>"<?= $o['code'] === 'plus' ? ' selected' : '' ?>><?= Support::e($o['name']) ?></option><?php endforeach; ?></select></div>
          <div class="field" style="margin:0;width:7em"><label for="st-q" class="small">Strutture</label><input id="st-q" name="strutture" type="number" min="1" max="50" value="<?= max(1, (int) $qAtt) ?>"></div>
          <div class="field" style="margin:0"><label for="st-fino" class="small">Valido fino al</label><input id="st-fino" name="fino" type="date" required min="<?= gmdate('Y-m-d', time() + 86400) ?>" value="<?= gmdate('Y-m-d', strtotime('+1 year')) ?>"></div>
          <label class="check small"><input type="checkbox" name="no_vetrina" value="1"> <span>Non in vetrina</span></label>
          <button class="btn btn--sm">Attiva come staff</button>
        </form>
      <?php endif; ?>
    </section>
    <?php endif; ?>
    <?php /* 10H · «Elimina account», solo da qui: senza pagamenti live si cancella tutto; con pagamenti veri restano 10 anni i dati
             di fatturazione e i pagamenti, il resto si cancella (EliminaAccount). Conferma, motivo, password dell'amministratore. */
          if (MHW\EliminaAccount::disponibile()): $el = MHW\EliminaAccount::riepilogo((int) $acc['id']); ?>
    <section class="panel stack elimina-account" style="gap:8px" id="elimina" aria-labelledby="el-titolo">
      <h2 id="el-titolo" style="font-size:19px;margin:0">Elimina account</h2>
      <?php if ($el['modo'] === 'completa'): ?>
        <p class="small" style="margin:0">Nessun pagamento live: si cancella tutto. L'email <b><?= Support::e($el['email']) ?></b> torna libera per una nuova registrazione<?= $elP['stripe'] !== '' ? ' e il cliente Stripe si cancella' : '' ?>. Non si torna indietro.</p>
      <?php else: ?>
        <p class="small" style="margin:0">Ha <?= (int) $el['live'] ?> <?= $el['live'] === 1 ? 'pagamento live' : 'pagamenti live' ?>: per legge si conservano 10 anni i dati di fatturazione e i pagamenti, quindi l'account resta, anonimo, solo per quelli. Tutto il resto si cancella e l'email <b><?= Support::e($el['email']) ?></b> torna libera. Non si torna indietro.</p>
      <?php endif; ?>
      <p class="small" style="margin:0"><b>Si cancella:</b></p>
      <ul class="small" style="margin:0;padding-left:18px"><?php foreach ($el['righe'] as [$etP, $nP]): ?><li><?= Support::e($etP) ?><?= str_starts_with($etP, 'Accesso') ? '' : ': <b>' . (int) $nP . '</b>' ?></li><?php endforeach; ?></ul>
      <?php if ($el['restano']): ?>
        <p class="small" style="margin:0"><b>Restano:</b></p>
        <ul class="small" style="margin:0;padding-left:18px"><?php foreach ($el['restano'] as $rest): ?><li><?= Support::e($rest) ?></li><?php endforeach; ?></ul>
      <?php endif; ?>
      <?php foreach ($el['aperti'] as $sA): ?>
        <p class="spiega small" style="margin:0">Abbonamento <?= Support::e($sA['provider'] === 'stripe' ? 'Stripe' : (string) $sA['provider']) ?> <?= Support::e($sA['status'] === 'trialing' ? 'in omaggio' : ($sA['status'] === 'past_due' ? 'con un pagamento non riuscito' : 'attivo')) ?><?= (string) $sA['current_period_end'] !== '' ? ' fino al ' . Support::e(Support::date((string) $sA['current_period_end'])) : '' ?>: si chiude subito<?= $sA['provider'] === 'stripe' ? ' su Stripe' : '' ?>, senza conguaglio né rimborso. Se va rimborsato qualcosa, fallo prima da <a href="#fatture">«Fatture e rimborsi»</a>.</p>
      <?php endforeach; ?>
      <form method="post" action="<?= b() ?>/admin/cliente/<?= (int) $acc['id'] ?>/elimina" class="stack" style="gap:8px;margin:0"
            onsubmit="return confirm('Eliminare l\'account <?= Support::e($el['codice']) ?>? Non si torna indietro.')"><?= Csrf::field() ?>
        <div class="field" style="margin:0"><label for="el-conferma" class="small">Scrivi «ELIMINA <?= Support::e($el['codice']) ?>»</label>
          <input id="el-conferma" name="conferma" type="text" required autocomplete="off" spellcheck="false"></div>
        <div class="field" style="margin:0"><label for="el-motivo" class="small">Motivo (resta nel Registro azioni)</label>
          <input id="el-motivo" name="motivo" type="text" required minlength="5" maxlength="255" placeholder="Per esempio: richiesta del cliente per email del 28 ottobre"></div>
        <div class="field" style="margin:0"><label for="el-password" class="small">La tua password</label>
          <input id="el-password" name="password" type="password" required autocomplete="current-password"></div>
        <label class="check small"><input type="checkbox" name="avvisa" value="1"<?= $el['prova'] ? '' : ' checked' ?>> <span>Manda al cliente l'email di conferma (a <?= Support::e($el['email']) ?>)</span></label>
        <div class="actions"><button class="btn btn--sm btn--danger">Elimina account</button></div>
      </form>
    </section>
    <?php endif; ?>
  </div>

  <section class="stack" style="gap:10px">
    <h2 style="font-size:22px">Strutture e guide</h2>
    <?php if (!$props): ?><p class="small muted">Nessuna struttura.</p><?php else: ?>
    <div class="tablewrap"><table class="data">
      <thead><tr><th>Struttura</th><th>Guida</th><th>Lingue</th><th>QR</th><th>Pubblicata</th></tr></thead>
      <tbody><?php foreach ($props as $p): ?>
        <tr><td><b><?= Support::e($p['name']) ?></b><br><span class="small muted"><?= Support::e($p['city']) ?></span></td>
          <td><?= $p['status'] === 'published' ? ($p['online'] ? '<span class="badge badge--pine">Online</span>' : '<span class="badge badge--alert">Offline</span>') : '<span class="badge badge--ochre">Bozza</span>' ?>
            <br><a class="small" href="<?= b() ?>/g/<?= Support::e($p['slug']) ?>" target="_blank" rel="noopener">/g/<?= Support::e($p['slug']) ?></a>
            <?php /* 9Q · Le email sulla bozza e «la finiamo noi»: si compila da «Entra come cliente», poi si segna qui (Registro azioni). */
                  $compilata = MHW\Db::val("SELECT created_at FROM audit_log WHERE action = 'guida.compilata_da_noi' AND meta LIKE ?", ['%"property_id":' . (int) $p['id'] . '}%']);
                  foreach (MHW\Bozze::GIORNI as $tb => $gg): if (($inv = MHW\Db::val('SELECT sent_at FROM email_log WHERE account_id = ? AND kind = ? AND ref = ?', [$acc['id'], $tb, (string) $p['id']]))): ?>
              <br><span class="tiny muted">Email sulla bozza del giorno <?= $gg ?>: <?= Support::e(Support::date((string) $inv)) ?></span><?php endif; endforeach; ?>
            <?php if ($compilata): ?><br><span class="tiny">Guida compilata da noi il <?= Support::e(Support::date((string) $compilata)) ?></span>
            <?php elseif ($p['status'] !== 'published'): ?>
              <form method="post" action="<?= b() ?>/admin/cliente/<?= (int) $acc['id'] ?>/compilata/<?= (int) $p['id'] ?>" style="margin-top:4px"><?= Csrf::field() ?><button class="linkbtn small">Segna «compilata da noi»</button></form>
            <?php endif; ?></td>
          <td><?= Support::e($p['lingue']) ?></td>
          <td><code><?= Support::e($p['qr']['token'] ?? '—') ?></code></td>
          <td class="small"><?= Support::e(Support::date($p['published_at'] ?? '')) ?: '—' ?></td></tr>
      <?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
  </section>

  <section class="stack" style="gap:10px">
    <h2 style="font-size:22px">Abbonamenti</h2>
    <?php if (!$subs): ?><p class="small muted">Nessun abbonamento.</p><?php else: ?>
    <?php /* Cambio di piano (6H): quello programmato e l'ultimo fatto. */
          foreach ($subs as $s) if (!empty($s['next_package_version_id']) && ($np = MHW\Plans::version((int) $s['next_package_version_id']))): ?>
      <p class="note" role="status">Cambio programmato: <b><?= Support::e($np['name']) ?></b><?= (int) ($s['next_quantity'] ?? 1) > 1 ? ' · ' . (int) $s['next_quantity'] . ' strutture' : '' ?> dal <?= Support::e(Support::date($s['current_period_end'])) ?>.</p>
    <?php endif; ?>
    <?php if (($ultimoCambio = MHW\Db::one("SELECT created_at, meta FROM audit_log WHERE action = 'subscription.change' AND target_user_id = ? ORDER BY id DESC", [$acc['user_id']]))):
          $m = json_decode((string) $ultimoCambio['meta'], true) ?: []; $da = MHW\Plans::version((int) ($m['da']['pv'] ?? 0)); $a = MHW\Plans::version((int) ($m['a']['pv'] ?? 0)); ?>
      <p class="small muted">Ultimo cambio di piano: <?= Support::e(Support::date($ultimoCambio['created_at'])) ?>, da <?= Support::e($da['name'] ?? '?') ?> (<?= (int) ($m['da']['q'] ?? 1) ?>) a <?= Support::e($a['name'] ?? '?') ?> (<?= (int) ($m['a']['q'] ?? 1) ?>)<?= ($m['come'] ?? '') === 'rinnovo' ? ', al rinnovo' : '' ?>.</p>
    <?php endif; ?>
    <div class="tablewrap"><table class="data">
      <thead><tr><th>Piano</th><th>Stato</th><th>Periodo</th><th>Rinnovo</th><th>Acquirente</th><th>Stripe</th></tr></thead>
      <tbody><?php foreach ($subs as $s): ?>
        <tr><td><?= Support::e($s['package']) ?> <span class="tiny muted">v<?= (int) $s['version'] ?></span><?= (int) ($s['quantity'] ?? 1) > 1 ? ' · ' . (int) $s['quantity'] . ' strutture' : '' ?><br><span class="tiny muted"><?= Support::e($viaPagamento[$s['provider']] ?? $s['provider']) ?></span></td>
          <td><?= Support::e($statoAbbonamento[$s['status']] ?? $s['status']) ?><?= $s['payment_status'] ? '<br><span class="tiny muted">' . Support::e($statoPagamento[$s['payment_status']] ?? $s['payment_status']) . '</span>' : '' ?></td>
          <td class="small"><?= Support::e(Support::date($s['current_period_start'])) ?> → <?= Support::e(Support::date($s['current_period_end'])) ?></td>
          <td class="small"><?= (int) $s['cancel_at_period_end'] ? 'disattivato' : 'automatico' ?></td>
          <td class="small"><?= match ($s['acquirente'] ?? '') { 'impresa' => 'Impresa o professionista', 'consumatore' => 'Privato (consumatore)<br><span class="tiny muted">' . ((int) ($s['recesso_rinuncia'] ?? 0) ? 'ha chiesto l\'avvio subito' : 'senza richiesta di avvio subito') . '</span>', default => '—' } ?></td>
          <td><code><?= Support::e($s['provider_subscription_id'] ?: '—') ?></code><br><code><?= Support::e($s['provider_price_id'] ?: '') ?></code></td></tr>
      <?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
    <details class="fieldset">
      <summary class="legend" style="cursor:pointer;min-height:32px">Attiva un abbonamento manuale</summary>
      <form method="post" action="<?= b() ?>/admin/cliente/<?= (int) $acc['id'] ?>/abbonamento" class="stack" style="margin-top:12px"><?= Csrf::field() ?>
        <p class="small muted">Per omaggi, clienti gratuiti o pagamenti arrivati per altre vie. Non passa da Stripe e resta nel registro azioni.
          Senza incasso non entra nei conti; con un bonifico diventa un pagamento con il suo codice, in Cassa, Prospetti e Scadenze.</p>
        <fieldset class="fieldset" style="margin:0"><legend class="legend">Pagato fuori Stripe</legend>
          <div class="row" style="gap:16px;flex-wrap:wrap">
            <label class="check"><input type="radio" name="pagamento" value="nessuno" checked data-bonifico="0"> <span>Nessun incasso (omaggio, gratuito)</span></label>
            <label class="check"><input type="radio" name="pagamento" value="bonifico" data-bonifico="1"> <span>Bonifico ricevuto</span></label>
          </div>
          <div class="grid grid-4 bonifico-campi" style="margin-top:10px">
            <div class="field" style="margin:0"><label for="b-imp">Importo (€, IVA esclusa)</label><input id="b-imp" name="bonifico_importo" type="text" inputmode="decimal" placeholder="127,00"></div>
            <div class="field" style="margin:0"><label for="b-iva">IVA (€)</label><input id="b-iva" name="bonifico_iva" type="text" inputmode="decimal" placeholder="27,94"></div>
            <div class="field" style="margin:0"><label for="b-data">Data del bonifico</label><input id="b-data" name="bonifico_data" type="date" max="<?= gmdate('Y-m-d') ?>"></div>
            <div class="field" style="margin:0"><label for="b-rif">Riferimento (CRO o causale)</label><input id="b-rif" name="bonifico_riferimento" type="text" maxlength="120"></div>
          </div>
          <p class="tiny muted" style="margin:6px 0 0">I campi del bonifico contano solo con «Bonifico ricevuto». La fattura si fa in Adamo, come per i pagamenti Stripe.</p>
        </fieldset>
        <?php /* 7F: le scelte rapide. «Per sempre» è una scadenza lontana (100 anni): niente avvisi di scadenza. */ ?>
        <fieldset class="fieldset" style="margin:0"><legend class="legend">Durata</legend>
          <div class="row" style="gap:16px;flex-wrap:wrap">
            <label class="check"><input type="radio" name="durata" value="12"> <span>12 mesi</span></label>
            <label class="check"><input type="radio" name="durata" value="sempre"> <span>Per sempre</span></label>
            <label class="check"><input type="radio" name="durata" value="mesi" checked> <span>I mesi scritti qui sotto</span></label>
          </div></fieldset>
        <div class="grid grid-4">
          <div class="field" style="margin:0"><label for="pv">Piano</label><select id="pv" name="pv">
            <?php /* 9N · solo i piani in vendita; le versioni non più in vendita in un gruppo a parte */
                  foreach ([1 => null, 0 => 'Precedenti (non più in vendita)'] as $inV => $gruppo):
                    $elenco = array_filter($versioni, fn($v) => (int) ($v['in_vendita'] ?? 1) === $inV); if (!$elenco) continue; ?>
              <?php if ($gruppo): ?><optgroup label="<?= Support::e($gruppo) ?>"><?php endif; ?>
              <?php foreach ($elenco as $v): ?><option value="<?= (int) $v['id'] ?>"><?= Support::e($v['name']) ?><?= $gruppo ? ' v' . (int) $v['version'] : '' ?></option><?php endforeach; ?>
              <?php if ($gruppo): ?></optgroup><?php endif; ?>
            <?php endforeach; ?></select></div>
          <div class="field" style="margin:0"><label for="mesi">Mesi</label><input id="mesi" name="mesi" type="number" min="1" max="36" value="12"></div>
          <div class="field" style="margin:0"><label for="strutture">Strutture <span class="muted">(Portfolio)</span></label><input id="strutture" name="strutture" type="number" min="1" max="500" value="2"></div>
          <div class="field" style="margin:0"><label for="nota">Motivo</label><input type="text" id="nota" name="nota" required maxlength="200"></div>
        </div>
        <div class="actions"><button class="btn btn--sm">Attiva</button></div>
      </form>
    </details>
  </section>

  <?php /* Avvisi di rinnovo e scadenza: l'ultimo abbonamento, il promemoria a mano, le email mandate. */
        $ultimo = $subs[0] ?? null;
        $nomiEmail = ['arrivo' => 'Check-in da compilare', 'sezioni' => 'Sezioni da aggiungere', 'pubblica' => 'Guida da pubblicare', 'rinnovo' => 'Rinnovo in arrivo',
                      'scadenza' => 'Scadenza', 'eventi' => 'Eventi passati', 'inviti' => 'Inviti',
                      'conferma' => 'Email da confermare', 'pagamento' => 'Pagamento da riprendere', 'parere' => 'Cosa ti ha fermato?',
                      'giudizio' => 'Richiesta di giudizio', 'benvenuto' => 'Benvenuto', 'bozza7' => 'Bozza · giorno 7', 'bozza21' => 'Bozza · giorno 21', 'bozza' => 'Email sulla bozza',
                      'sconti' => 'Fino al ' . MHW\Sconti::massimoRinnovo()['totale'] . '%']; ?>
  <section class="stack" style="gap:10px" id="promemoria" aria-labelledby="prom-titolo">
    <h2 id="prom-titolo" style="font-size:22px">Avvisi e promemoria</h2>
    <?php if ($ultimo && $ultimo['provider'] !== 'dimostrazione' && $ultimo['current_period_end'] !== ''): ?>
      <form method="post" action="<?= b() ?>/admin/scadenze/promemoria" class="panel spread spread--mid" style="gap:12px"><?= Csrf::field() ?>
        <input type="hidden" name="torna" value="/admin/cliente/<?= (int) $acc['id'] ?>">
        <span class="small"><?= strtotime((string) $ultimo['current_period_end']) > time() ? 'L\'abbonamento ' . ($ultimo['provider'] === 'stripe' && !(int) $ultimo['cancel_at_period_end'] ? 'si rinnova' : 'finisce') . ' il ' : 'L\'abbonamento è finito il ' ?>
          <b><?= Support::e(Support::date($ultimo['current_period_end'])) ?></b>. Il promemoria sceglie da solo il testo: rinnovo, scadenza o guida offline.</span>
        <button class="btn btn--ghost btn--sm" name="solo" value="<?= (int) $ultimo['id'] ?>">Manda il promemoria adesso</button>
      </form>
    <?php endif; ?>
    <?php if (MHW\EmailSconti::disponibili()): $stSc = MHW\EmailSconti::stato($acc); /* 9P · l'email «Fino al 55%»: quando è partita, o perché no */ ?>
      <p class="small" style="margin:0">Email «Fino al <?= (int) MHW\Sconti::massimoRinnovo()['totale'] ?>%»: <b><?= Support::e(['inviata' => 'inviata', 'programmata' => 'programmata', 'da_mandare' => 'da mandare', 'attesa' => 'in attesa', 'saltata' => 'non parte'][$stSc['stato']] ?? $stSc['stato']) ?></b> <?= Support::e($stSc['motivo']) ?>.</p>
    <?php endif; ?>
    <?php if ($optout): ?><p class="small">Non vuole ricevere: <?= Support::e(implode(', ', array_map(fn($k) => mb_strtolower($nomiEmail[$k] ?? $k), $optout))) ?>.</p><?php endif; ?>
    <?php if (!empty($parere)): ?>
      <div class="panel stack" style="gap:6px" id="parere">
        <span class="kicker">Cosa ti ha fermato?</span>
        <p class="small" style="margin:0"><b><?= Support::e(MHW\Richiami::MOTIVI[$parere['motivo']] ?? $parere['motivo']) ?></b>
          <span class="muted">· <?= Support::e(Support::date($parere['updated_at'] ?: $parere['created_at'])) ?></span></p>
        <?php if (trim((string) $parere['testo']) !== ''): ?><p class="small" style="margin:0;white-space:pre-line"><?= Support::e($parere['testo']) ?></p><?php endif; ?>
      </div>
    <?php endif; ?>
    <?php if (!$email): ?><p class="small muted">Nessuna email di promemoria finora.</p><?php else: ?>
    <div class="tablewrap"><table class="data">
      <thead><tr><th>Data</th><th>Email</th><th>Come</th></tr></thead>
      <tbody><?php foreach ($email as $m): ?>
        <tr><td class="small"><?= Support::e(Support::date($m['sent_at'])) ?></td><td><?= Support::e($nomiEmail[$m['kind']] ?? $m['kind']) ?></td>
          <td class="small muted"><?= str_starts_with((string) $m['ref'], 'manuale-') ? 'a mano' : 'automatica' ?></td></tr>
      <?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
  </section>

  <?php if ($inviti || $traduzioni): ?>
  <div class="grid grid-2" style="align-items:start">
    <?php if ($inviti): $iv = $inviti['stato']; ?>
      <section class="panel stack" style="gap:8px" aria-labelledby="inv-titolo" id="inviti">
        <span class="kicker" id="inv-titolo">Porta un amico</span>
        <p class="small" style="margin:0">Amici che hanno pagato: <b><?= (int) $iv['validi'] ?></b> su <?= (int) $iv['massimo'] ?> · in attesa <?= (int) $iv['in_attesa'] ?>
          · sconto sul prossimo rinnovo <b><?= (int) $iv['percento'] ?>%</b><?= $iv['sconto'] > 0 ? ' (−' . Support::e(Support::money($iv['sconto'], $iv['valuta'])) . ')' : '' ?></p>
        <?php if ($inviti['invitato']): ?><p class="small" style="margin:0">Invitato da <b><?= Support::e($inviti['invitato']['referrer_name']) ?></b> · <?= Support::e(['registrato' => 'in attesa che paghi', 'valido' => 'ha pagato: conta', 'usato' => 'sconto già usato', 'oltre' => 'oltre il tetto'][$inviti['invitato']['status']] ?? $inviti['invitato']['status']) ?>.</p>
        <?php if ($inviti['invitato']['status'] === 'registrato' && MHW\Inviti::contabilePerProva((int) $acc['id'], (int) $inviti['invitato']['referrer_account_id'])): /* 9T · solo tra account staff o di prova */ ?>
          <form method="post" action="<?= b() ?>/admin/cliente/<?= (int) $acc['id'] ?>/invito-prova" class="actions" style="margin:0;gap:8px;align-items:end"><?= Csrf::field() ?>
            <div class="field" style="margin:0"><label for="inv-prova-motivo" class="small">Motivo (va nel Registro azioni)</label><input id="inv-prova-motivo" name="motivo" type="text" maxlength="200" minlength="3" required placeholder="prova della barra degli inviti"></div>
            <button class="btn btn--sm btn--ghost" type="submit">Conta l'invito (prova)</button></form>
          <p class="tiny muted" style="margin:0">Solo per le prove: chi invita o questo cliente è staff o di prova. L'invito conta come se avesse pagato.</p>
        <?php endif; ?>
        <?php elseif (!empty($inviti['invito_annullato'])): ?><p class="small" style="margin:0">Invitato da <b><?= Support::e($inviti['invito_annullato']['referrer_name']) ?></b> · <b>invito annullato</b><?= $inviti['invito_annullato']['motivo'] !== '' ? ': ' . Support::e($inviti['invito_annullato']['motivo']) : '' ?>.</p>
        <?php endif; ?>
        <?php if ($inviti['amici']): ?>
          <ul class="stack small" style="gap:4px;margin:0;padding-left:18px">
            <?php foreach (array_slice($inviti['amici'], 0, 10) as $f): ?><li><a href="<?= b() ?>/admin/cliente/<?= (int) $f['friend_account_id'] ?>"><?= Support::e($f['name'] ?: $f['email']) ?></a> · <?= Support::e(['registrato' => 'in attesa', 'valido' => 'ha pagato', 'usato' => 'sconto già usato', 'oltre' => 'oltre il tetto', 'annullato' => 'annullato'][$f['status']] ?? $f['status']) ?><?= $f['status'] === 'annullato' && $f['motivo'] !== '' ? ': ' . Support::e($f['motivo']) : '' ?></li><?php endforeach; ?>
          </ul>
        <?php endif; ?>
        <?php if (!$inviti['invitato']): /* L'invito perso per strada (registrato senza il link, sessione scaduta, annullato per sbaglio): si lega a mano al codice di chi invita. */ ?>
          <form method="post" action="<?= b() ?>/admin/cliente/<?= (int) $acc['id'] ?>/invito" class="actions" style="margin:0;gap:8px;align-items:end"><?= Csrf::field() ?>
            <div class="field" style="margin:0"><label for="inv-codice" class="small">Collega a un invito · codice di chi lo ha invitato</label><input id="inv-codice" name="codice" type="text" maxlength="6" pattern="[A-Za-z0-9]{6}" style="width:9em;text-transform:uppercase" required></div>
            <button class="btn btn--sm btn--ghost" type="submit">Collega</button></form>
          <p class="tiny muted" style="margin:0">Se ha già pagato, l'invito conta subito per chi lo ha invitato; il suo 5% invece no (vale solo prima del primo pagamento). Niente controllo sui dati di fatturazione uguali: decidi tu.</p>
        <?php endif; ?>
        <a class="small" href="<?= b() ?>/admin/inviti">Tutti gli inviti</a>
      </section>
    <?php endif; ?>
    <?php if (!empty($assistente)): /* 10 · Concierge AI: le domande del mese, il costo stimato, il limite personale */ ?>
      <section class="panel stack" id="assistente" style="gap:8px" aria-labelledby="as-titolo">
        <span class="kicker" id="as-titolo">Concierge AI</span>
        <p class="small" style="margin:0">Questo mese <b><?= (int) $assistente['domande'] ?></b> domande (<?= (int) $assistente['senza'] ?> senza risposta), circa <b><?= number_format((float) $assistente['euro'], 2, ',', '.') ?> €</b> stimati.
          Limite: <b><?= number_format((int) $assistente['limite'], 0, ',', '.') ?></b> domande al mese per struttura<?= $assistente['override'] !== null && (int) $assistente['override'] > 0 ? ' (personale)' : '' ?><?= $assistente['nelPiano'] ? '' : ' · <span class="muted">il piano non la comprende</span>' ?>.</p>
        <form method="post" action="<?= b() ?>/admin/cliente/<?= (int) $acc['id'] ?>/assistente/limite" class="row" style="gap:8px;flex-wrap:wrap;align-items:flex-end"><?= Csrf::field() ?>
          <div class="field" style="margin:0;max-width:11rem"><label for="as-limite" class="small">Limite personale <span class="muted">(vuoto = di serie)</span></label>
            <input id="as-limite" name="limite" type="text" inputmode="numeric" value="<?= $assistente['override'] !== null && (int) $assistente['override'] > 0 ? (int) $assistente['override'] : '' ?>"></div>
          <div class="field" style="margin:0;flex:1 1 12rem"><label for="as-nota" class="small">Nota <span class="muted">(resta nel registro)</span></label>
            <input id="as-nota" name="nota" type="text" maxlength="255" placeholder="Per esempio: struttura grande, ha chiesto più domande"></div>
          <button class="btn btn--ghost btn--sm">Salva il limite</button>
        </form>
        <a class="small" href="<?= b() ?>/admin/assistente">Concierge AI, tutti i clienti</a>
      </section>
    <?php endif; ?>
    <?php if ($traduzioni): $annoC = $traduzioni['anno']; $nC = fn(int $x) => number_format($x, 0, ',', '.'); ?>
      <section class="panel stack" id="traduzioni" style="gap:8px" aria-labelledby="tr-titolo">
        <span class="kicker" id="tr-titolo">Traduzioni suggerite</span>
        <?php /* 9G · «Traduzioni: 123.000 / 500.000 caratteri quest'anno», «Aggiungi 250.000» con la nota, il tetto personale */ ?>
        <p class="small" style="margin:0">Traduzioni: <b><?= $nC((int) $annoC['usati']) ?> / <?= $nC((int) $annoC['tetto']) ?></b> caratteri quest'anno (<?= (int) $annoC['percento'] ?>%, si azzera il <?= Support::e(Support::date($annoC['azzera'])) ?>)<?= $traduzioni['nelPiano'] ? '' : ' · <span class="muted">il piano non le comprende</span>' ?>.
          Questo mese <?= $nC((int) $traduzioni['mese']) ?> caratteri, circa <?= number_format(MHW\Traduttore::inEuro(MHW\Traduttore::costoUsd((int) $traduzioni['mese'], false)), 2, ',', '.') ?> € senza piano gratuito AWS.</p>
        <form method="post" action="<?= b() ?>/admin/cliente/<?= (int) $acc['id'] ?>/traduzioni/tetto" class="stack" style="gap:8px"><?= Csrf::field() ?>
          <div class="field" style="margin:0"><label for="tr-nota">Nota <span class="muted">(obbligatoria, resta nel registro)</span></label>
            <input id="tr-nota" name="nota" type="text" maxlength="255" placeholder="Per esempio: ha chiesto di riattivarle, guida grande"></div>
          <div class="row" style="gap:8px;flex-wrap:wrap;align-items:flex-end">
            <button class="btn btn--sm" name="aggiungi" value="1">Aggiungi <?= $nC(MHW\Traduttore::AGGIUNTA) ?></button>
            <div class="field" style="margin:0;max-width:12rem"><label for="tr-tetto" class="small">Tetto personale <span class="muted">(vuoto = di serie)</span></label>
              <input id="tr-tetto" name="tetto" type="text" inputmode="numeric" value="<?= $traduzioni['override'] !== null ? (int) $traduzioni['override'] : '' ?>"></div>
            <button class="btn btn--ghost btn--sm" name="salva" value="1">Salva il tetto</button>
          </div>
        </form>
        <a class="small" href="<?= b() ?>/admin/traduzioni">Tutte le traduzioni</a>
      </section>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <section class="stack" style="gap:10px">
    <h2 style="font-size:22px">Cosa può fare</h2>
    <p class="small muted">Per ogni funzione, il valore che vale adesso e da dove arriva. Un'eccezione ha la precedenza sul piano: per toglierla, svuota il campo e salva. Scrivi 1 per sì e 0 per no, un numero per i limiti, unlimited per «senza limite».</p>
    <div class="tablewrap"><table class="data">
      <thead><tr><th>Funzione</th><th>Valore</th><th>Fonte</th><th>Eccezione</th></tr></thead>
      <tbody><?php foreach ($ent as $code => $e): ?>
        <tr><td><?= Support::e($etichette[$code] ?? $code) ?><br><code class="tiny muted"><?= Support::e($code) ?></code></td>
          <td><b><?= Support::e($e['value']) ?></b></td><td class="small"><?= Support::e($fonte[$e['source']] ?? $e['source']) ?></td>
          <td><form method="post" action="<?= b() ?>/admin/cliente/<?= (int) $acc['id'] ?>/override" class="row" style="gap:6px;flex-wrap:nowrap"><?= Csrf::field() ?>
            <input type="hidden" name="feature" value="<?= Support::e($code) ?>">
            <input type="text" name="valore" value="<?= $e['source'] === 'override' ? Support::e($e['value']) : '' ?>" style="width:110px" aria-label="Eccezione per <?= Support::e($code) ?>">
            <input type="text" name="nota" placeholder="Motivo" style="width:140px" aria-label="Motivo">
            <button class="btn btn--ghost btn--sm">Salva</button></form></td></tr>
      <?php endforeach; ?></tbody></table></div>
  </section>

  <section class="stack" style="gap:10px">
    <h2 style="font-size:22px">Ordini</h2>
    <?php if (!$orders): ?><p class="small muted">Nessun ordine.</p><?php else: ?>
    <div class="tablewrap"><table class="data">
      <thead><tr><th>Data</th><th>Piano</th><th>Importo</th><th>Stato</th><th>Sessione Stripe</th></tr></thead>
      <tbody><?php foreach ($orders as $o): ?>
        <tr><td class="small"><?= Support::e(Support::date($o['created_at'])) ?></td><td><?= Support::e($o['package']) ?></td>
          <td><?= Support::e(Support::money((int) $o['amount_cents'], $o['currency'])) ?></td><td><?= Support::e($statoOrdine[$o['status']] ?? $o['status']) ?><?= !empty($o['in_verifica_at']) ? '<br><span class="tiny muted">addebito SEPA dal ' . Support::e(Support::date((string) $o['in_verifica_at'])) . '</span>' : '' ?></td>
          <td><code><?= Support::e($o['provider_session_id'] ?: '—') ?></code></td></tr>
      <?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
  </section>

  <section class="stack" style="gap:10px">
    <h2 style="font-size:22px">Registro azioni</h2>
    <?php if (!$audit): ?><p class="small muted">Niente da segnalare.</p><?php else: ?>
    <div class="tablewrap"><table class="data"><tbody><?php foreach ($audit as $l): ?>
      <tr><td class="small" style="white-space:nowrap"><?= Support::e(str_replace(['T', 'Z'], [' ', ''], $l['created_at'])) ?></td><td><code><?= Support::e($l['action']) ?></code></td>
        <td class="small muted"><?= Support::e($l['meta']) ?></td></tr>
    <?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
  </section>
</div>
