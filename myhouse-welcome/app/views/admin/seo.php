<?php
/* SEO e GEO (6K): come il sito pubblico si presenta a Google e agli assistenti AI.
   Il controllo si ricalcola a ogni apertura; contatori, anteprima stile Google e anteprima
   di robots.txt si aggiornano mentre scrivi (assets/seo.js). Un solo «Salva» in fondo. */
use function MHW\b;
use MHW\{Support, Csrf, Icon, Seo};
$title = 'SEO e GEO';
$controlli = Seo::controlli();
$superati = count(array_filter($controlli, fn($c) => $c['stato'] === 'ok'));
$accese = count(array_filter(array_keys(Seo::PAGINE), [Seo::class, 'indicizza']));
$ammessi = count(array_filter(array_keys(Seo::BOT), [Seo::class, 'botAmmesso']));
$stati = ['ok' => ['ok', 'pine'], 'guarda' => ['da guardare', 'ochre'], 'sistema' => ['da sistemare', 'alert']];
$home = Seo::assoluto('/');
$img = Seo::immagine(true);
$jsonLd = json_encode(Seo::grafoHome(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);
$v = fn(string $k) => Support::e(Seo::get($k)); ?>
<div class="stack stack--lg">
  <div class="stack stack--sm">
    <h1>SEO e GEO.</h1>
    <p class="muted">Come il sito si presenta a Google e agli assistenti come ChatGPT, Claude e Perplexity. Valgono solo per le pagine pubbliche:
      le guide degli ospiti, il pannello e l'amministrazione restano fuori dai motori.</p>
    <?php if (Support::baseDir() !== ''): ?>
      <p class="note note--err" role="status">Il sito è in una sottocartella: motori e assistenti cercano robots.txt e llms.txt solo nella radice del dominio.
        Finché il sito non ha un dominio suo, quei due file non vengono letti.</p>
    <?php endif; ?>
    <?php if (!$pronta): ?><p class="note note--err" role="status">Manca la tabella delle impostazioni: ricarica la pagina per applicare gli aggiornamenti. Intanto valgono i valori di serie.</p><?php endif; ?>
  </div>

  <div class="cifre">
    <?php foreach ([['cifra--pino', 'check', 'Controlli superati', $superati . ' su ' . count($controlli), 'ricalcolati adesso'],
                    ['cifra--mare', 'search', 'Pagine nei motori', $accese . ' su ' . count(Seo::PAGINE), 'home, domande, termini, privacy'],
                    ['cifra--ocra', 'message', 'Assistenti AI ammessi', $ammessi . ' su ' . count(Seo::BOT), 'da robots.txt']] as [$tono, $ico, $et, $val, $nota]): ?>
      <div class="cifra <?= $tono ?>"><span class="cifra__testa"><span class="cifra__ico"><?= Icon::svg($ico, 17) ?></span><?= $et ?></span>
        <b class="cifra__valore"><?= Support::e($val) ?></b><span class="cifra__nota"><?= Support::e($nota) ?></span></div>
    <?php endforeach; ?>
  </div>

  <section class="panel stack" id="seo-controllo" aria-labelledby="seo-controllo-titolo">
    <h2 id="seo-controllo-titolo" style="font-size:22px">Controllo</h2>
    <ul class="seo-controlli">
      <?php foreach ($controlli as $c): [$et, $tono] = $stati[$c['stato']]; ?>
        <li><span class="badge badge--<?= $tono ?>"><?= $et ?></span><span><?= Support::e($c['testo']) ?></span>
          <a class="small" href="#<?= Support::e($c['dove']) ?>">Vai</a></li>
      <?php endforeach; ?>
    </ul>
    <p class="small row" style="gap:16px">
      <a href="https://search.google.com/test/rich-results?url=<?= rawurlencode($home) ?>" target="_blank" rel="noopener">Test dei risultati multimediali di Google <?= Icon::svg('external', 14) ?></a>
      <a href="https://pagespeed.web.dev/analysis?url=<?= rawurlencode($home) ?>" target="_blank" rel="noopener">PageSpeed Insights <?= Icon::svg('external', 14) ?></a>
      <a href="https://search.google.com/search-console" target="_blank" rel="noopener">Search Console <?= Icon::svg('external', 14) ?></a>
    </p>
  </section>

  <form method="post" action="<?= b() ?>/admin/seo" enctype="multipart/form-data" class="stack stack--lg" data-seo><?= Csrf::field() ?>
    <?php foreach (Seo::PAGINE as $k => [$percorso, $nome]): $id = "seo-p-$k"; ?>
      <section class="panel stack" id="<?= $id ?>" aria-labelledby="<?= $id ?>-titolo" data-serp-pagina>
        <div class="spread spread--mid">
          <h2 id="<?= $id ?>-titolo" style="font-size:22px"><?= Support::e($nome) ?> <span class="small muted"><?= Support::e($percorso) ?></span></h2>
          <input type="hidden" name="p_<?= $k ?>_indicizza" value="0">
          <label class="interruttore-check"><input type="checkbox" role="switch" name="p_<?= $k ?>_indicizza" value="1" <?= Seo::indicizza($k) ? 'checked' : '' ?>>
            <span class="interruttore-check__testo">Nei motori di ricerca</span></label>
        </div>
        <div class="seo-pagina">
          <div class="stack">
            <div class="field" style="margin:0">
              <div class="spread"><label for="<?= $id ?>-t">Titolo</label><span class="contatore" data-contatore="<?= $id ?>-t" data-max="<?= Seo::TITOLO_MAX ?>" aria-live="polite"></span></div>
              <input id="<?= $id ?>-t" name="p_<?= $k ?>_titolo" type="text" maxlength="200" value="<?= $v("p.$k.titolo") ?>" data-serp="titolo" aria-describedby="<?= $id ?>-t-aiuto">
              <p class="help" id="<?= $id ?>-t-aiuto">Tra <?= Seo::TITOLO_MIN ?> e <?= Seo::TITOLO_MAX ?> caratteri. Vuoto: il titolo di serie della pagina.</p>
            </div>
            <div class="field" style="margin:0">
              <div class="spread"><label for="<?= $id ?>-d">Descrizione</label><span class="contatore" data-contatore="<?= $id ?>-d" data-max="<?= Seo::DESCR_MAX ?>" aria-live="polite"></span></div>
              <textarea id="<?= $id ?>-d" name="p_<?= $k ?>_descrizione" rows="3" maxlength="400" data-serp="testo" aria-describedby="<?= $id ?>-d-aiuto"><?= $v("p.$k.descrizione") ?></textarea>
              <p class="help" id="<?= $id ?>-d-aiuto">Tra <?= Seo::DESCR_MIN ?> e <?= Seo::DESCR_MAX ?> caratteri: è il testo sotto il titolo nei risultati.</p>
            </div>
          </div>
          <div class="serp" aria-label="Anteprima nei risultati di Google">
            <span class="serp__url"><?= Support::e(Seo::assoluto($percorso)) ?></span>
            <span class="serp__titolo" data-serp-titolo><?= $v("p.$k.titolo") ?></span>
            <span class="serp__testo" data-serp-testo><?= $v("p.$k.descrizione") ?></span>
          </div>
        </div>
        <?php if ($k === 'home'): ?>
          <div class="seo-immagine">
            <img src="<?= Support::e($img['url']) ?>" alt="Immagine per la condivisione" width="240" height="126" loading="lazy">
            <div class="field" style="margin:0">
              <label for="seo-og">Immagine per la condivisione <?= $img['caricata'] ? '' : '<span class="small muted">(ora quella di serie)</span>' ?></label>
              <input id="seo-og" name="og_immagine" type="file" accept="image/jpeg,image/png" aria-describedby="seo-og-aiuto">
              <p class="help" id="seo-og-aiuto">Cambia immagine: JPG o PNG, consigliata 1200 × 630. È quella che compare quando il sito si condivide su WhatsApp, Facebook o LinkedIn.</p>
              <?php if ($img['caricata']): ?><label class="check"><input type="checkbox" name="og_togli" value="1"> <span>Torna all'immagine di serie</span></label><?php endif; ?>
            </div>
          </div>
        <?php endif; ?>
      </section>
    <?php endforeach; ?>

    <section class="panel stack" id="seo-azienda" aria-labelledby="seo-azienda-titolo">
      <h2 id="seo-azienda-titolo" style="font-size:22px">Dati dell'azienda</h2>
      <p class="small muted">Vanno nei dati strutturati della home (Organization) e in llms.txt. Di serie sono quelli del piè di pagina e dei Termini.</p>
      <div class="grid grid-2">
        <?php foreach (['nome' => 'Nome del servizio', 'venditore' => 'Ragione sociale', 'piva' => 'Partita IVA', 'indirizzo' => 'Indirizzo', 'email' => 'Email', 'telefono' => 'Telefono'] as $k => $et): ?>
          <div class="field" style="margin:0"><label for="seo-az-<?= $k ?>"><?= $et ?></label>
            <input id="seo-az-<?= $k ?>" name="az_<?= $k ?>" type="<?= $k === 'email' ? 'email' : ($k === 'telefono' ? 'tel' : 'text') ?>" value="<?= $v("az.$k") ?>"></div>
        <?php endforeach; ?>
      </div>
      <div class="field" style="margin:0"><label for="seo-az-social">Profili social</label>
        <textarea id="seo-az-social" name="az_social" rows="3" aria-describedby="seo-az-social-aiuto"><?= $v('az.social') ?></textarea>
        <p class="help" id="seo-az-social-aiuto">Un indirizzo per riga, con https:// (Instagram, Facebook, LinkedIn…).</p></div>
      <details class="seo-json"><summary>Dati strutturati generati</summary>
        <pre><?= Support::e((string) $jsonLd) ?></pre></details>
    </section>

    <section class="panel stack" id="seo-motori" aria-labelledby="seo-motori-titolo">
      <h2 id="seo-motori-titolo" style="font-size:22px">Motori e assistenti AI</h2>
      <div class="grid grid-2">
        <div class="field" style="margin:0"><label for="seo-dominio">Dominio</label>
          <input id="seo-dominio" name="dominio" type="url" placeholder="https://myhousewelcome.it" value="<?= $v('dominio') ?>" aria-describedby="seo-dominio-aiuto">
          <p class="help" id="seo-dominio-aiuto">Con https:// e senza barra finale. Serve per canonical, sitemap e condivisione.</p></div>
        <div class="field" style="margin:0"><label for="seo-gsc">Verifica Google (Search Console)</label>
          <input id="seo-gsc" name="gsc" type="text" value="<?= $v('gsc') ?>" aria-describedby="seo-gsc-aiuto">
          <p class="help" id="seo-gsc-aiuto">Il codice del meta «google-site-verification»: puoi incollare anche tutto il tag.</p></div>
        <div class="field" style="margin:0"><label for="seo-bing">Verifica Bing</label>
          <input id="seo-bing" name="bing" type="text" value="<?= $v('bing') ?>" aria-describedby="seo-bing-aiuto">
          <p class="help" id="seo-bing-aiuto">Il codice del meta «msvalidate.01».</p></div>
      </div>
      <fieldset class="fieldset" style="margin:0"><legend>Assistenti AI che possono leggere il sito</legend>
        <div class="seo-bot">
          <?php foreach (Seo::BOT as $bot => [$prodotto, $azienda]): ?>
            <input type="hidden" name="bot[<?= Support::e($bot) ?>]" value="0">
            <label class="interruttore-check"><input type="checkbox" role="switch" name="bot[<?= Support::e($bot) ?>]" value="1" data-bot="<?= Support::e($bot) ?>" <?= Seo::botAmmesso($bot) ? 'checked' : '' ?>>
              <span class="interruttore-check__testo"><?= Support::e($bot) ?> <span class="small muted">· <?= Support::e($prodotto) ?><?= $azienda !== $prodotto ? ' (' . Support::e($azienda) . ')' : '' ?></span></span></label>
          <?php endforeach; ?>
        </div>
      </fieldset>
      <div class="field" style="margin:0"><span class="label">robots.txt che ne risulta</span>
        <pre class="seo-anteprima" data-robots data-regole="<?= Support::e(implode('', array_map(fn($x) => "Disallow: $x\n", array_merge(Seo::GUIDE, Seo::ESCLUSI)))) ?>"><?= Support::e(Seo::robots()) ?></pre></div>
    </section>

    <section class="panel stack" id="seo-llms" aria-labelledby="seo-llms-titolo">
      <h2 id="seo-llms-titolo" style="font-size:22px">llms.txt</h2>
      <p class="small muted">Il riassunto del sito per gli assistenti AI. Piani, prezzi e domande vengono dal listino e dalle FAQ.</p>
      <div class="seo-pagina">
        <div class="stack">
          <div class="field" style="margin:0">
            <div class="spread"><label for="seo-intro">Introduzione</label><span class="contatore" data-contatore="seo-intro" data-max="<?= Seo::INTRO_MAX ?>" aria-live="polite"></span></div>
            <textarea id="seo-intro" name="llms_intro" rows="4" maxlength="<?= Seo::INTRO_MAX ?>"><?= $v('llms.intro') ?></textarea></div>
          <div class="field" style="margin:0"><label for="seo-fatti">Fatti chiave</label>
            <textarea id="seo-fatti" name="llms_fatti" rows="8" aria-describedby="seo-fatti-aiuto"><?= $v('llms.fatti') ?></textarea>
            <p class="help" id="seo-fatti-aiuto">Una frase per riga. Solo fatti verificabili: niente slogan.</p></div>
        </div>
        <div class="field" style="margin:0"><span class="label">Anteprima (salvata)</span>
          <pre class="seo-anteprima seo-anteprima--alta"><?= Support::e(Seo::llms()) ?></pre></div>
      </div>
    </section>

    <div class="row"><button class="btn">Salva</button>
      <span class="small muted">Sitemap, robots.txt e llms.txt si aggiornano subito:
        <a href="<?= b() ?>/robots.txt" target="_blank" rel="noopener">robots.txt</a> ·
        <a href="<?= b() ?>/sitemap.xml" target="_blank" rel="noopener">sitemap.xml</a> ·
        <a href="<?= b() ?>/llms.txt" target="_blank" rel="noopener">llms.txt</a></span></div>
  </form>
</div>
<script src="<?= MHW\av('/assets/seo.js') ?>" defer></script>
