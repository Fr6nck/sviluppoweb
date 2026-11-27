<?php use function MHW\t, MHW\tf; use MHW\Support; $title = 'QR & Link — ' . $prop['name']; ?>
<div class="stack stack--lg" style="max-width:900px">
  <div class="stack stack--sm">
    <h1><?= t('QR &amp; Link.') ?></h1>
    <?php if ($prop['status'] !== 'published'): ?>
      <p class="note"><?= t('Puoi già scaricare e stampare il QR: funzionerà appena pubblichi la guida.') ?></p>
    <?php elseif (!$online): ?>
      <p class="note note--err"><?= t('La guida è offline perché l\'abbonamento non è attivo. Il QR resta valido: tornerà a funzionare quando lo rinnovi.') ?></p>
    <?php else: ?>
      <p class="lead"><?= t('Stampa il QR e mettilo dove l\'ospite lo vede entrando: sul tavolo, vicino alla porta, sul frigo.') ?></p>
    <?php endif; ?>
  </div>
  <?php include __DIR__ . '/_qr_box.php'; ?>

  <?php /* 8B · I due messaggi da mandare su WhatsApp: benvenuto e fine soggiorno, una versione per lingua,
           salvati come modelli con {link} e {host}. Accanto, l'anteprima come la vede l'ospite su WhatsApp,
           con la scheda del link (immagine, titolo, dominio). */
  $nomiLingue = MHW\Config::get('locales');
  $dominio = (string) parse_url(Support::baseUrl(), PHP_URL_HOST);
  $immagineLink = Support::baseUrl() . '/g/' . $prop['slug'] . '/anteprima.jpg';
  $titoliLink = ['benvenuto' => MHW\I18n::t($prop['default_locale'], 'og_guide_title', $prop['name']), 'saluto' => MHW\I18n::t($prop['default_locale'], 'og_review_title', $prop['name'])]; ?>
  <section class="stack messaggi" style="gap:16px" aria-labelledby="messaggi-titolo">
    <div class="stack stack--sm">
      <h2 id="messaggi-titolo" style="font-size:24px"><?= t('I messaggi da mandare su WhatsApp') ?></h2>
      <p class="help" style="margin:0"><?= t('Copia il messaggio nella lingua dell\'ospite e incollalo su WhatsApp o nella chat di Booking e Airbnb. Il link di benvenuto apre la guida; quello di fine soggiorno apre la pagina in cui l\'ospite dà un voto da 1 a 5.') ?></p>
    </div>
    <?php foreach (MHW\Messaggi::TIPI as $tipo => $titoloMsg): ?>
      <div class="fieldset stack messaggio" style="gap:12px" id="msg-<?= $tipo ?>" data-messaggio-box>
        <h3 class="legend" style="font-size:18px;margin:0"><?= t($titoloMsg) ?></h3>
        <p class="help" style="margin:0"><?= $tipo === 'benvenuto' ? t('Prima dell\'arrivo: nel messaggio di conferma, su WhatsApp o nella chat del portale.') : t('L\'ultimo giorno, o appena l\'ospite è partito: porta alla pagina con le stelle; a chi ne dà 5 mostriamo i bottoni dei portali che hai spuntato in Impostazioni.') ?></p>
        <?php if (count($lingue) > 1): ?>
          <div class="linguette" role="tablist" aria-label="<?= MHW\Support::e(t('Lingua del messaggio')) ?>" data-solo-js hidden>
            <?php foreach ($lingue as $i => $l): ?>
              <button type="button" role="tab" class="linguetta" id="tab-<?= $tipo ?>-<?= $l ?>" aria-controls="pan-<?= $tipo ?>-<?= $l ?>" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>" data-linguetta><?= Support::e($nomiLingue[$l] ?? strtoupper($l)) ?></button>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <?php foreach ($lingue as $i => $l):
              $modello = MHW\Messaggi::modello($prop, $tipo, $l);
              $link = MHW\Messaggi::link($prop, $tipo, $l);
              $firma = MHW\Messaggi::firma($prop);
              $pronto = MHW\Messaggi::testo($prop, $tipo, $l);
              $idT = "msg-$tipo-$l"; ?>
          <div class="messaggio__pannello" id="pan-<?= $tipo ?>-<?= $l ?>" role="tabpanel" aria-labelledby="tab-<?= $tipo ?>-<?= $l ?>" data-pannello<?= $i > 0 ? ' data-dopo' : '' ?>
               data-link="<?= Support::e($link) ?>" data-host="<?= Support::e($firma) ?>">
            <form method="post" action="<?= MHW\b() ?>/pannello/<?= (int) $prop['id'] ?>/messaggi" class="messaggio__modulo"><?= MHW\Csrf::field() ?>
              <input type="hidden" name="tipo" value="<?= $tipo ?>"><input type="hidden" name="lingua" value="<?= $l ?>">
              <div class="field" style="margin:0">
                <label for="<?= $idT ?>"><?= Support::e($nomiLingue[$l] ?? strtoupper($l)) ?><?= MHW\Messaggi::cambiato($prop, $tipo, $l) ? ' <span class="badge badge--ochre">' . t('cambiato') . '</span>' : '' ?></label>
                <textarea id="<?= $idT ?>" name="testo" rows="7" lang="<?= $l ?>" maxlength="<?= MHW\Messaggi::MAX ?>" data-modello aria-describedby="<?= $idT ?>-aiuto"><?= Support::e($modello) ?></textarea>
                <p class="help" id="<?= $idT ?>-aiuto" style="margin:4px 0 0"><?= $tipo === 'saluto' ? t('<code>{link}</code> diventa il link della pagina delle recensioni, <code>{host}</code> il tuo nome.') : t('<code>{link}</code> diventa il link della guida, <code>{host}</code> il tuo nome.') ?></p>
              </div>
              <p class="note note--err messaggio__manca" role="status" data-manca-link<?= str_contains($modello, '{link}') ? ' hidden' : '' ?>><?= t('Nel messaggio manca il link: scrivi') ?> <code><?= t('{link}') ?></code> <?= t('dove deve comparire.') ?></p>
              <div class="row" style="gap:8px">
                <button class="btn btn--sm" name="azione" value="salva"><?= t('Salva') ?></button>
                <?php if (MHW\Messaggi::cambiato($prop, $tipo, $l)): ?><button class="btn btn--ghost btn--sm" name="azione" value="serie"><?= t('Torna al testo di serie') ?></button><?php endif; ?>
                <button type="button" class="btn btn--ghost btn--sm" data-copia-msg data-copiato="<?= t('Messaggio copiato') ?>" hidden><?= MHW\Icon::svg('copy', 15) ?><?= t('Copia') ?></button>
                <a class="btn btn--ghost btn--sm" href="https://wa.me/?text=<?= rawurlencode($pronto) ?>" target="_blank" rel="noopener" data-wa-msg><?= MHW\Icon::svg('whatsapp', 15) ?><?= t('Apri WhatsApp') ?></a>
              </div>
            </form>
            <?php /* L'anteprima stile WhatsApp: il fumetto con il testo pronto e la scheda del link. */ ?>
            <div class="wa-anteprima" aria-label="<?= MHW\Support::e(t('Anteprima su WhatsApp')) ?>">
              <div class="wa-fumetto">
                <a class="wa-scheda" href="<?= Support::e($link) ?>" target="_blank" rel="noopener" tabindex="-1">
                  <img src="<?= Support::e($immagineLink) ?>" alt="" width="1200" height="630" loading="lazy">
                  <span class="wa-scheda__testo"><b><?= Support::e($titoliLink[$tipo]) ?></b><span><?= Support::e(MHW\I18n::t($prop['default_locale'], 'og_guide_desc')) ?></span><span class="wa-scheda__dominio"><?= Support::e($dominio) ?></span></span>
                </a>
                <p class="wa-fumetto__testo" data-anteprima-msg><?= nl2br(preg_replace('#(https?://\S+)#', '<span class="wa-link">$1</span>', Support::e($pronto))) ?></p>
                <span class="wa-fumetto__ora">10:42 <svg width="16" height="11" viewBox="0 0 16 11" aria-hidden="true"><path d="M1 6l3 3 6-7M6 9l1 1 7-8" fill="none" stroke="#53bdeb" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  </section>
</div>
