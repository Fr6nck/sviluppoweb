<?php
/* Termini e privacy. Testo di PARTENZA: va rivisto da un legale e completato con
   i dati dell'attività prima di vendere. Le versioni stanno in config.php
   (legal.terms_version, legal.privacy_version): cambiale quando cambia il testo,
   e ogni accettazione resterà legata alla versione letta. */
use MHW\{Support, Config};
$l = Config::get('legal');
// I dati dell'attività vengono dalla configurazione: niente segnaposto da riempire a mano.
$chi = implode(' · ', array_filter([
    $l['company'], ($l['company_vat'] ?? '') !== '' ? 'P.IVA ' . $l['company_vat'] : '', $l['company_city'] ?? '',
]));
$contatti = implode(' · ', array_filter([$l['contact_email'], ($l['contact_phone'] ?? '') !== '' ? 'Tel. ' . $l['contact_phone'] : '']));
$larghezza = '760px';
$title = $doc === 'termini' ? 'Termini e condizioni' : 'Informativa sulla privacy'; ?>
<p class="note" role="note"><span><strong>Testo di partenza.</strong> Prima di aprire le vendite questo documento va rivisto
  da un legale.</span></p>

<?php if ($doc === 'termini'): ?>
<h1 style="margin-top:24px">Termini e condizioni</h1>
<p class="small muted" style="margin-top:8px">Versione <?= Support::e($l['terms_version']) ?> · Fornitore: <?= Support::e($chi) ?> · <?= Support::e($contatti) ?></p>
<div class="stack" style="margin-top:24px;line-height:1.6">
  <h2 style="font-size:22px">1. Il servizio</h2>
  <p>MyHouse Welcome permette a chi gestisce una struttura ricettiva di creare una guida digitale per i propri ospiti,
    raggiungibile da un link e da un codice QR. Gli ospiti non si registrano e non installano applicazioni.</p>
  <h2 style="font-size:22px">2. Account</h2>
  <p>Per usare il servizio crei un account con un indirizzo email che confermi. Sei responsabile della riservatezza
    della password e di ciò che accade con il tuo account.</p>
  <h2 style="font-size:22px">3. Prova gratuita della configurazione e pubblicazione</h2>
  <p>Puoi creare e configurare la guida gratuitamente. La guida diventa visibile agli ospiti solo dopo l'attivazione
    di un abbonamento a pagamento.</p>
  <h2 style="font-size:22px">4. Abbonamento, prezzi e rinnovo</h2>
  <p>I piani sono annuali e si rinnovano automaticamente alla scadenza. I prezzi sono indicati IVA esclusa; l'imposta
    è calcolata al pagamento. Puoi disattivare il rinnovo in qualsiasi momento da Account &amp; Fatturazione: il servizio
    resta attivo fino alla fine del periodo già pagato. Alla scadenza senza rinnovo la guida smette di essere visibile
    agli ospiti; i tuoi contenuti restano conservati nell'account.</p>
  <h2 style="font-size:22px">5. Modifiche ai piani</h2>
  <p>Le condizioni del piano che hai acquistato restano quelle in vigore al momento dell'acquisto per tutto il periodo
    pagato. Eventuali modifiche al listino si applicano ai nuovi acquisti.</p>
  <h2 style="font-size:22px">6. I tuoi contenuti</h2>
  <p>I testi, le immagini e i documenti che pubblichi restano tuoi. Garantisci di avere il diritto di usarli e che non
    violano diritti di terzi. Non pubblicare nella guida codici di accesso, password di allarmi o altri dati la cui
    diffusione potrebbe mettere a rischio la sicurezza della struttura.</p>
  <h2 style="font-size:22px">7. Disponibilità</h2>
  <p>Ci impegniamo a mantenere il servizio disponibile, senza poter garantire l'assenza di interruzioni.</p>
  <h2 style="font-size:22px">8. Recesso e chiusura dell'account</h2>
  <p>[Da completare con un legale: diritto di recesso, rimborsi, chiusura dell'account e cancellazione dei dati.]</p>
  <h2 style="font-size:22px">9. Legge applicabile e foro competente</h2>
  <p>[Da completare con un legale.]</p>
</div>
<?php else: ?>
<h1 style="margin-top:24px">Informativa sulla privacy</h1>
<p class="small muted" style="margin-top:8px">Versione <?= Support::e($l['privacy_version']) ?> · Titolare: <?= Support::e($chi) ?> · <?= Support::e($contatti) ?></p>
<div class="stack" style="margin-top:24px;line-height:1.6">
  <h2 style="font-size:22px">Chi tratta i dati</h2>
  <p>Il titolare del trattamento è <?= Support::e($chi) ?>. Contatti: <?= Support::e($contatti) ?>.</p>
  <h2 style="font-size:22px">Dati degli host</h2>
  <p>Nome, email, password (conservata solo in forma cifrata con hash), dati di fatturazione raccolti da Stripe al
    momento del pagamento, contenuti della guida, data e versione dei documenti accettati. Servono a fornire il
    servizio, gestire l'abbonamento e adempiere agli obblighi fiscali.</p>
  <h2 style="font-size:22px">Dati degli ospiti</h2>
  <p>La guida non chiede agli ospiti di registrarsi e non imposta cookie. Registriamo solo eventi anonimi — apertura
    della guida, apertura dal QR, sezione consultata, lingua — senza indirizzo IP e senza identificare il dispositivo,
    per offrire all'host statistiche di lettura aggregate.</p>
  <h2 style="font-size:22px">Fornitori</h2>
  <p>Pagamenti: Stripe. Archiviazione di immagini e documenti: Amazon Web Services (S3). Invio delle email: il
    fornitore di posta configurato. [Completare con sedi, garanzie per il trasferimento dei dati e nomine a responsabile.]</p>
  <h2 style="font-size:22px">Conservazione</h2>
  <p>[Da completare: tempi di conservazione dei dati dell'account, dei dati di fatturazione e delle statistiche.]</p>
  <h2 style="font-size:22px">I tuoi diritti</h2>
  <p>Puoi chiedere accesso, rettifica, cancellazione, limitazione, portabilità e opporti al trattamento scrivendo a
    <?= Support::e($l['contact_email']) ?>. Puoi proporre reclamo al Garante per la protezione dei dati personali.</p>
</div>
<?php endif; ?>
