<?php use function MHW\t, MHW\tf;
/* 9V · «La casa: foto e descrizione» (Contenuti): la galleria che l'ospite apre toccando la foto di copertina.
   - Le foto: una griglia a 5 per riga con il numero d'ordine. Si sceglie una foto (un radio vero: tastiera e lettori
     di schermo) e i bottoni sotto la spostano prima o dopo, o la tolgono. Con JavaScript (assets/casa.js) si
     trascinano, e niente ricarica la pagina; senza, ogni bottone è un modulo normale.
   - Caricamento multiplo: con JavaScript le foto si riducono nel browser e partono una per volta (mai oltre il limite
     del server); senza, partono tutte insieme con «Carica le foto scelte». Il server le riduce comunque (1600 px, 300 KB).
   - I tre testi con il contatore delle battute (caratteri veri: accenti ed emoji contano uno, come sul server).
   Riceve: $prop, $foto (Casa::foto), $pesoMedio, $err, $scritto (i testi appena rifiutati), $mossa/$verso (la foto
   appena spostata con le frecce: il bottone riprende il fuoco), $pending, $online. */
use function MHW\b;
use MHW\{Support, Csrf, Icon, Casa, Media};
$title = t('La casa: foto e descrizione') . ' — ' . $prop['name'];
$pid = (int) $prop['id'];
$n = count($foto);
$v = fn(string $k) => (string) (($scritto ?? $prop)[$k] ?? '');
$invitoSerie = MHW\I18n::t((string) $prop['default_locale'], 'casa_invite');
$azione = b() . '/pannello/' . $pid . '/casa';
$stato = function (int $n, int $pesoMedio): string {
    $s = tf('{n} di {max} foto', ['{n}' => $n, '{max}' => Casa::MAX_FOTO]);
    if ($n > 1) $s .= ' · ' . t('trascina per ordinarle');
    return $s;
};
$testiJs = ['prepara' => t('Preparo la foto {i} di {n}…'), 'carica' => t('Carico la foto {i} di {n}…'), 'fatto1' => t('1 foto caricata.'), 'fatto' => t('{n} foto caricate.'),
            'fuori1' => t('La galleria ha al massimo {max} foto: 1 è rimasta fuori. Togline una per fare posto.'),
            'fuori' => t('La galleria ha al massimo {max} foto: {n} sono rimaste fuori. Togline qualcuna per fare posto.'),
            'ridotte' => t('ridotte in automatico'), 'da' => t('(da {prima} a {dopo})'), 'media' => t('(in media {peso} l\'una)'),
            'stato' => t('{n} di {max} foto'), 'trascina' => t('trascina per ordinarle'), 'errore' => t('Non è stato possibile caricare la foto: controlla la connessione e riprova.'),
            'scegli' => t('Scegli una foto per spostarla o toglierla.'), 'scelta' => t('Foto {i} di {n}'), 'foto' => t('Foto {i}'),
            'heic' => t('Questa foto è in formato HEIC: da iPhone scegli «Più compatibile» in Impostazioni → Fotocamera → Formati.'),
            'ordine' => t('Ordine salvato.'), 'tolta' => t('Foto tolta dalla galleria.')]; ?>
<div class="editor">
  <div class="stack stack--lg">
    <div class="stack stack--sm">
      <a class="small" href="<?= b() ?>/pannello/<?= $pid ?>"><?= Icon::svg('back', 14) ?> <?= t('Contenuti') ?></a>
      <h1><?= t('La casa: foto e descrizione') ?> <?= Casa::badge(MHW\Pannello::en()) ?></h1>
      <p class="lead"><?= t('Compare quando l\'ospite tocca la foto di copertina.') ?></p>
    </div>
    <?php if ($err): ?><p class="note note--err" role="alert"><?= Support::e($err) ?></p><?php endif; ?>
    <?php if (!$prop['cover_media_id']): ?>
      <p class="note" role="status"><?= Icon::svg('info', 19) ?><span><?= t('La galleria si apre dalla foto di copertina: per ora la guida non ne ha una.') ?> <a href="<?= b() ?>/pannello/<?= $pid ?>/aspetto"><?= t('Aggiungila in Aspetto') ?></a></span></p>
    <?php endif; ?>

    <section class="panel stack" id="foto" aria-labelledby="casa-foto-titolo" data-casa data-max="<?= Casa::MAX_FOTO ?>" data-lato="<?= Casa::LATO ?>"
             data-testi="<?= Support::json_attr($testiJs) ?>" data-peso-medio="<?= (int) $pesoMedio ?>">
      <h2 id="casa-foto-titolo" style="font-size:20px;margin:0"><?= t('Le foto') ?></h2>
      <form method="post" action="<?= $azione ?>" class="stack" style="gap:10px" data-casa-foto><?= Csrf::field() ?>
        <ol class="casa-griglia" data-casa-griglia aria-label="<?= Support::e(t('Le foto della galleria, nell\'ordine in cui le vede l\'ospite')) ?>">
          <?php foreach ($foto as $i => $f): ?>
            <li class="casa-foto" data-foto="<?= (int) $f['id'] ?>">
              <label>
                <input type="radio" name="foto_id" value="<?= (int) $f['id'] ?>"<?= $mossa === (int) $f['id'] ? ' checked' : '' ?>>
                <img src="<?= Support::e((string) Media::ridotta($f['media_id'], Casa::MINIATURA)) ?>" alt="<?= Support::e($f['alt'] !== '' ? $f['alt'] : tf('Foto {i}', ['{i}' => $i + 1])) ?>" width="160" height="160" loading="lazy" draggable="false">
                <b class="casa-foto__n" aria-hidden="true"><?= $i + 1 ?></b>
              </label>
            </li>
          <?php endforeach; ?>
          <li class="casa-foto casa-foto--aggiungi" data-casa-aggiungi<?= $n >= Casa::MAX_FOTO ? ' hidden' : '' ?>>
            <label for="casa-file" title="<?= Support::e(t('Aggiungi foto')) ?>"><?= Icon::svg('plus', 22, 2) ?><span class="sr-only"><?= t('Aggiungi foto') ?></span></label></li>
          <?php for ($k = $n + 1; $k < Casa::MAX_FOTO; $k++): ?><li class="casa-foto casa-foto--vuota" data-casa-vuota aria-hidden="true"></li><?php endfor; ?>
        </ol>
        <p class="casa-stato small muted" data-casa-stato><span data-casa-conta-foto><?= Support::e($stato($n, $pesoMedio)) ?></span>
          <span class="casa-stato__ok" data-casa-ok<?= $n ? '' : ' hidden' ?>>· <?= Icon::svg('check', 14, 2.4) ?> <span data-casa-ridotte><?= t('ridotte in automatico') ?><?= $n ? ' ' . Support::e(tf('(in media {peso} l\'una)', ['{peso}' => Casa::peso($pesoMedio)])) : '' ?></span></span></p>
        <div class="casa-azioni" data-casa-azioni<?= $n ? '' : ' hidden' ?>>
          <span class="small muted" data-casa-scelta><?= t('Scegli una foto per spostarla o toglierla.') ?></span>
          <span class="row" style="gap:8px;flex-wrap:wrap">
            <button class="btn btn--ghost btn--sm" name="azione" value="su" data-casa-su<?= $mossa && $verso === 'su' ? ' autofocus' : '' ?>><?= Icon::svg('back', 15, 2) ?><?= t('Sposta prima') ?></button>
            <button class="btn btn--ghost btn--sm" name="azione" value="giu" data-casa-giu<?= $mossa && $verso === 'giu' ? ' autofocus' : '' ?>><?= t('Sposta dopo') ?><?= Icon::svg('chevron', 15, 2) ?></button>
            <button class="btn btn--quiet btn--sm" name="azione" value="togli" data-casa-togli><?= Icon::svg('bin', 15) ?><?= t('Togli') ?></button>
          </span>
        </div>
      </form>

      <form method="post" action="<?= $azione ?>" enctype="multipart/form-data" class="casa-carica" data-casa-carica<?= $n >= Casa::MAX_FOTO ? ' hidden' : '' ?>><?= Csrf::field() ?>
        <input type="hidden" name="azione" value="carica">
        <div class="field" style="margin:0">
          <label for="casa-file"><?= t('Aggiungi foto') ?></label>
          <?php /* Senza JavaScript il campo del browser; con JavaScript resta (per la tastiera) ma si vede il bottone, nella lingua del sito. */ ?>
          <input type="file" id="casa-file" class="casa-file" name="foto[]" multiple accept="image/jpeg,image/png,image/webp" data-foto-multi aria-describedby="casa-file-aiuto">
          <label class="btn btn--ghost btn--sm casa-file__btn" for="casa-file" data-casa-scegli hidden><?= Icon::svg('plus', 16, 2) ?><?= t('Scegli le foto') ?></label>
          <p class="help" id="casa-file-aiuto" style="margin:6px 0 0"><?= Support::e(tf('Fino a {max} foto, JPG, PNG o WebP, anche dal telefono: puoi sceglierne più di una. Le alleggeriamo noi (lato lungo {lato} px, al massimo 300 KB l\'una).', ['{max}' => Casa::MAX_FOTO, '{lato}' => Casa::LATO])) ?></p>
        </div>
        <div class="row" style="gap:10px;flex-wrap:wrap">
          <button class="btn btn--sm" data-casa-carica-btn><?= t('Carica le foto scelte') ?></button>
        </div>
      </form>
      <?php /* Fuori dal modulo: si legge anche quando la galleria è piena e il modulo sparisce. */ ?>
      <p class="small casa-esito" data-casa-carica-stato role="status" aria-live="polite"></p>
      <p class="note note--quiet" data-casa-piena<?= $n >= Casa::MAX_FOTO ? '' : ' hidden' ?>><?= Support::e(tf('La galleria è piena: {max} foto. Per aggiungerne un\'altra, togline una.', ['{max}' => Casa::MAX_FOTO])) ?></p>
    </section>

    <form method="post" action="<?= $azione ?>" class="panel stack" data-autosave data-casa-testi><?= Csrf::field() ?>
      <input type="hidden" name="azione" value="salva">
      <h2 style="font-size:20px;margin:0"><?= t('I testi') ?></h2>
      <?php foreach ([['casa_invito', 'casa-invito', t('Scritta sulla copertina'), Casa::MAX_INVITO, t('Facoltativa: se la lasci vuota, «{serie}» nella lingua dell\'ospite.'), 'text'],
                      ['casa_titolo', 'casa-titolo', t('Titolo'), Casa::MAX_TITOLO, t('Per esempio «Una casa in pietra nel cuore di Assisi».'), 'text'],
                      ['casa_descrizione', 'casa-descrizione', t('Descrizione'), Casa::MAX_DESCRIZIONE, t('Dopo 4 righe compare «Leggi di più».'), 'textarea']] as [$nome, $id, $et, $max, $aiuto, $tipo]):
            $val = $v($nome); $quante = Casa::battute($tipo === 'textarea' ? Casa::paragrafi($val) : Casa::unaRiga($val)); ?>
        <div class="field" style="margin:0">
          <label for="<?= $id ?>"><?= Support::e($et) ?></label>
          <?php if ($tipo === 'textarea'): ?><textarea id="<?= $id ?>" name="<?= $nome ?>" rows="6" data-casa-campo data-max="<?= $max ?>" aria-describedby="<?= $id ?>-aiuto <?= $id ?>-conta"><?= Support::e($val) ?></textarea>
          <?php else: ?><input type="text" id="<?= $id ?>" name="<?= $nome ?>" value="<?= Support::e($val) ?>" data-casa-campo data-max="<?= $max ?>"<?= $nome === 'casa_invito' ? ' placeholder="' . Support::e($invitoSerie) . '"' : '' ?> aria-describedby="<?= $id ?>-aiuto <?= $id ?>-conta"><?php endif; ?>
          <div class="spread casa-sotto">
            <span class="help" id="<?= $id ?>-aiuto"><?= Support::e(strtr($aiuto, ['{serie}' => $invitoSerie])) ?></span>
            <span class="contatore<?= $quante > $max ? ' contatore--oltre' : '' ?>" id="<?= $id ?>-conta" data-casa-conta="<?= $id ?>"><span class="sr-only"><?= t('Battute') ?>: </span><?= $quante ?> / <?= $max ?></span>
          </div>
        </div>
      <?php endforeach; ?>
      <div class="field interruttore-campo" style="margin:0">
        <label class="interruttore-check"><input type="checkbox" role="switch" name="casa_attiva" value="1" <?= ($scritto !== null ? !empty($scritto['casa_attiva']) : (int) ($prop['casa_attiva'] ?? 1) === 1) ? 'checked' : '' ?> aria-describedby="casa-attiva-aiuto">
          <span class="interruttore-check__testo"><?= t('Mostra la galleria agli ospiti') ?></span></label>
        <p class="help" id="casa-attiva-aiuto"><?= t('Spenta, la copertina della guida resta com\'è e la pagina non si apre: foto e testi restano salvati.') ?></p>
      </div>
      <?php /* Sempre nella pagina (nascosta senza foto): con JavaScript le righe seguono le foto appena caricate. */ ?>
        <details class="casa-alt"<?= $foto ? '' : ' hidden' ?>>
          <summary><?= t('Descrizioni delle foto') ?> <span class="muted small"><?= t('(facoltative, per chi usa un lettore di schermo)') ?></span></summary>
          <div class="stack" style="gap:10px;margin-top:12px" data-casa-alt>
            <?php foreach ($foto as $i => $f): ?>
              <div class="field" style="margin:0" data-casa-alt-riga="<?= (int) $f['id'] ?>"><label for="casa-alt-<?= (int) $f['id'] ?>"><?= Support::e(tf('Foto {i}', ['{i}' => $i + 1])) ?></label>
                <input type="text" id="casa-alt-<?= (int) $f['id'] ?>" name="alt[<?= (int) $f['id'] ?>]" value="<?= Support::e($f['alt']) ?>" maxlength="250" placeholder="<?= Support::e(t('Per esempio: Il soggiorno con il camino')) ?>"></div>
            <?php endforeach; ?>
          </div>
        </details>
      <div class="actions">
        <button class="btn"><?= t('Salva') ?></button>
        <span class="small muted" data-stato-salvataggio aria-live="polite"></span>
      </div>
      <p class="small muted" style="margin:0"><?php if ($prop['status'] === 'published'): ?><?= t('Gli ospiti vedono foto e testi nuovi dopo «Pubblica le modifiche».') ?>
        <?php else: ?><?= t('La galleria si pubblica insieme alla guida.') ?><?php endif; ?>
        <?= t('Le traduzioni si scrivono nella pagina di ogni lingua, come gli altri testi.') ?> <a href="<?= b() ?>/pannello/<?= $pid ?>/lingue"><?= t('Vai a Lingue') ?></a></p>
    </form>

    <a class="btn btn--ghost phonebtn" href="<?= b() ?>/pannello/<?= $pid ?>/anteprima/casa" target="_blank" rel="noopener"><?= Icon::svg('eye', 16) ?><?= t('Apri l\'anteprima') ?></a>
  </div>
  <?php $src = '/pannello/' . $pid . '/anteprima' . (Casa::perIstantanea($prop, [(string) $prop['default_locale']]) ? '/casa' : ''); include __DIR__ . '/_telefono.php'; ?>
</div>
<script src="<?= MHW\av('/assets/casa.js') ?>" defer></script>
