<?php
/* Testi e foto del sito: ogni titolo, paragrafo e bottone della home (e l'intestazione di
   /domande), raggruppati per blocco nell'ordine della pagina, con il testo di serie sempre a
   portata di mano («Ripristina»). In fondo, le foto. Un solo modulo: ogni «Salva» salva tutto.
   Senza JavaScript: un campo lasciato vuoto torna al testo di serie. */
use function MHW\b;
use MHW\{Support, Csrf, Icon, TestiSito};
$title = 'Testi e foto';
$catalogo = TestiSito::catalogo();
$lingua = ($lingua ?? 'it') === 'en' ? 'en' : 'it';   // 8E: la linguetta (la lingua l'ha già impostata la rotta)
$cambiati = count(array_filter(array_keys($catalogo), [TestiSito::class, 'cambiato']));
$fotoCaricate = count(array_filter(array_keys(TestiSito::IMMAGINI), fn($n) => TestiSito::riferimentoImmagine($n) !== ''));
$perSezione = [];
foreach ($catalogo as $k => $d) $perSezione[$d[0]][$k] = $d;
$nomeCampo = fn(string $k) => 't_' . str_replace('.', '_', $k); ?>
<div class="stack stack--lg testi">
  <div class="stack stack--sm">
    <h1>Testi e foto del sito.</h1>
    <p class="muted">Quello che si legge nella home e in cima a «Domande», blocco per blocco, nell'ordine della pagina. Cambia solo quello che vuoi:
      il resto resta com'è. In ogni testo puoi usare <code>**grassetto**</code> e <code>[testo](https://indirizzo)</code>.
      Le domande e risposte, i piani, le testimonianze e i testi legali hanno le loro pagine.</p>
    <?php if (!$pronta): ?><p class="note note--err" role="status">Manca la tabella dei testi: ricarica la pagina per applicare gli aggiornamenti. Intanto valgono i testi di serie.</p><?php endif; ?>
  </div>

  <?php /* 8E: Italiano / English. Le foto sono le stesse per le due lingue. */ ?>
  <nav class="linguette-testi" aria-label="Lingua dei testi">
    <a href="<?= b() ?>/admin/testi"<?= $lingua === 'it' ? ' aria-current="page"' : '' ?>>Italiano</a>
    <a href="<?= b() ?>/admin/testi?lingua=en"<?= $lingua === 'en' ? ' aria-current="page"' : '' ?>>English</a>
  </nav>
  <?php if ($lingua === 'en'): ?><p class="note" role="status" style="margin:0">Stai modificando i testi della home in inglese (<a href="<?= b() ?>/en" target="_blank" rel="noopener">/en</a>) e dell'intestazione di /en/questions. Un campo vuoto torna al testo inglese di serie. Le foto sono le stesse dell'italiano.</p><?php endif; ?>
  <div class="cifre">
    <?php foreach ([['cifra--pino', 'doc', 'Testi cambiati', $cambiati . ' su ' . count($catalogo), 'gli altri sono di serie'],
                    ['cifra--mare', 'drop', 'Foto caricate', $fotoCaricate . ' su ' . count(TestiSito::IMMAGINI), 'le altre sono di serie'],
                    ['cifra--ocra', 'eye', 'La home', 'Apri', 'per vedere il risultato']] as [$tono, $ico, $et, $val, $nota]): ?>
      <div class="cifra <?= $tono ?>"><span class="cifra__testa"><span class="cifra__ico"><?= Icon::svg($ico, 17) ?></span><?= $et ?></span>
        <?php if ($val === 'Apri'): ?><a class="cifra__valore" href="<?= b() . ($lingua === 'en' ? '/en' : '/') ?>" target="_blank" rel="noopener">Apri <?= Icon::svg('external', 16, 2) ?></a>
        <?php else: ?><b class="cifra__valore"><?= Support::e($val) ?></b><?php endif; ?>
        <span class="cifra__nota"><?= Support::e($nota) ?></span></div>
    <?php endforeach; ?>
  </div>

  <nav class="testi__indice" aria-label="Blocchi della pagina">
    <?php foreach (TestiSito::SEZIONI as $sid => [$nome]): ?><a href="#testi-<?= $sid ?>"><?= Support::e($nome) ?></a><?php endforeach; ?>
    <a href="#testi-foto">Foto</a>
  </nav>

  <div class="testi__layout">
  <form method="post" action="<?= b() ?>/admin/testi" enctype="multipart/form-data" class="stack stack--lg testi__modulo" data-testi><?= Csrf::field() ?>
    <input type="hidden" name="torna" value="" data-torna>
    <input type="hidden" name="lingua" value="<?= $lingua ?>">
    <?php foreach (TestiSito::SEZIONI as $sid => [$nome, $nota]): $campi = $perSezione[$sid] ?? []; if (!$campi) continue; ?>
      <section class="panel stack" id="testi-<?= $sid ?>" aria-labelledby="testi-<?= $sid ?>-titolo">
        <div class="stack stack--sm">
          <h2 id="testi-<?= $sid ?>-titolo" style="font-size:22px"><?= Support::e($nome) ?></h2>
          <?php if ($nota !== ''): ?><p class="help" style="margin:0"><?= Support::e($nota) ?></p><?php endif; ?>
        </div>
        <?php foreach ($campi as $k => [, $etichetta, $tipo, , $aiuto]): $serie = TestiSito::serie($k, $lingua);
              $id = 'testo-' . str_replace('.', '-', $k); $valore = TestiSito::get($k); $diverso = $valore !== $serie; ?>
          <div class="field testi__campo<?= $diverso ? ' testi__campo--cambiato' : '' ?>" data-campo data-chiave="<?= Support::e($k) ?>">
            <label for="<?= $id ?>"><?= Support::e($etichetta) ?><?php if ($diverso): ?> <span class="badge badge--ochre testi__badge">cambiato</span><?php endif; ?></label>
            <?php if ($tipo === 'riga'): ?>
              <input id="<?= $id ?>" name="<?= $nomeCampo($k) ?>" type="text" value="<?= Support::e($valore) ?>" maxlength="<?= TestiSito::RIGA_MAX ?>" data-serie="<?= Support::e($serie) ?>">
            <?php else: ?>
              <textarea id="<?= $id ?>" name="<?= $nomeCampo($k) ?>" rows="<?= max(2, min(6, substr_count($serie, "\n") + 1 + (int) (mb_strlen($serie) / 110))) ?>" maxlength="<?= TestiSito::AREA_MAX ?>" data-serie="<?= Support::e($serie) ?>"><?= Support::e($valore) ?></textarea>
            <?php endif; ?>
            <div class="testi__sotto">
              <?php if ($aiuto !== ''): ?><span class="help"><?= Support::e($aiuto) ?></span><?php endif; ?>
              <button type="button" class="linkbtn testi__ripristina" data-ripristina<?= $diverso ? '' : ' hidden' ?>>Ripristina il testo di serie</button>
            </div>
            <?php if ($diverso): ?><p class="small muted testi__serie">Di serie: <?= Support::e($serie) ?></p><?php endif; ?>
          </div>
        <?php endforeach; ?>
        <div class="actions"><button class="btn" data-salva="<?= $sid ?>">Salva</button><span class="small muted">Salva tutta la pagina e torna qui.</span></div>
      </section>
    <?php endforeach; ?>

    <section class="panel stack" id="testi-foto" aria-labelledby="testi-foto-titolo">
      <div class="stack stack--sm">
        <h2 id="testi-foto-titolo" style="font-size:22px">Foto</h2>
        <p class="help" style="margin:0">JPG, PNG o WebP fino a 8 MB; il sito le ridisegna al massimo a 1800 px. L'immagine che compare quando il sito si condivide (WhatsApp, Facebook) si cambia in SEO e GEO.</p>
      </div>
      <div class="grid grid-2 testi__foto">
        <?php foreach (TestiSito::IMMAGINI as $nome => [$etichetta, $file, $consiglio]):
              $img = TestiSito::immagine($nome); $id = 'foto-' . $nome; ?>
          <div class="field testi__fotocampo" style="margin:0" data-fotocampo="<?= $nome ?>" data-serie-url="<?= Support::e((string) TestiSito::immagineSerie($nome)) ?>">
            <label for="<?= $id ?>"><?= Support::e($etichetta) ?><?php if ($img['caricata']): ?> <span class="badge badge--ochre testi__badge">caricata</span><?php endif; ?></label>
            <?php if ($img['url']): ?><img class="testi__anteprima" src="<?= Support::e($img['url']) ?>" alt="" loading="lazy">
            <?php else: ?><span class="testi__anteprima testi__anteprima--vuota">Nessuna foto: la home mostra un disegno al suo posto.</span><?php endif; ?>
            <input id="<?= $id ?>" name="img[<?= $nome ?>]" type="file" accept="image/jpeg,image/png,image/webp" aria-describedby="<?= $id ?>-aiuto">
            <span class="help" id="<?= $id ?>-aiuto"><?= Support::e($consiglio !== '' ? $consiglio : 'Di serie: ' . $file) ?></span>
            <?php if ($img['caricata']): ?><label class="check"><input type="checkbox" name="img_togli[<?= $nome ?>]" value="1"> <span>Torna alla foto di serie</span></label><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="actions"><button class="btn" data-salva="foto">Salva</button></div>
    </section>
  </form>

  <aside class="testi__anteprima-col" aria-label="Anteprima della home">
    <div class="testi__anteprima-testa">
      <span class="small"><b>Anteprima dal vivo</b><br>si aggiorna mentre scrivi; vale quando premi «Salva»</span>
      <span class="testi__viste" role="group" aria-label="Larghezza dell'anteprima">
        <button type="button" class="btn btn--sm btn--ghost" data-vista="telefono" aria-pressed="true">Telefono</button>
        <button type="button" class="btn btn--sm btn--ghost" data-vista="computer" aria-pressed="false">Computer</button>
      </span>
    </div>
    <div class="testi__cornice" data-cornice>
      <iframe src="<?= b() . ($lingua === 'en' ? '/en' : '/') ?>?anteprima=testi" title="Anteprima della home" data-anteprima></iframe>
    </div>
  </aside>
  </div>
</div>
<script>
/* «Ripristina» riempie il campo con il testo di serie (data-serie); il bottone compare solo se
   il testo è diverso. «Salva» di un blocco riporta a quel blocco dopo il salvataggio.
   L'anteprima a destra è la home in un iframe (?anteprima=testi): le riceve i testi mentre si
   scrivono e le foto appena scelte, e scorre al punto che si sta modificando. */
(function () {
  var form = document.querySelector('[data-testi]'); if (!form) return;
  var iframe = document.querySelector('[data-anteprima]'), cornice = document.querySelector('[data-cornice]'), pronta = false, coda = [];
  var manda = function (m) { if (!iframe) return; if (!pronta) { coda.push(m); return; } iframe.contentWindow.postMessage(m, location.origin); };
  window.addEventListener('message', function (e) {
    if (e.origin !== location.origin || !e.data || e.data.tipo !== 'pronta') return;
    pronta = true; coda.forEach(function (m) { iframe.contentWindow.postMessage(m, location.origin); }); coda = [];
  });
  form.querySelectorAll('[data-campo]').forEach(function (c) {
    var campo = c.querySelector('input, textarea'), btn = c.querySelector('[data-ripristina]'), chiave = c.getAttribute('data-chiave');
    if (!campo || !btn) return;
    var aggiorna = function () {
      btn.hidden = campo.value === campo.getAttribute('data-serie'); c.classList.toggle('testi__campo--cambiato', !btn.hidden);
      manda({ tipo: 'testo', chiave: chiave, valore: campo.value.trim() !== '' ? campo.value : campo.getAttribute('data-serie') });
    };
    campo.addEventListener('input', aggiorna);
    campo.addEventListener('focus', function () { manda({ tipo: 'vai', chiave: chiave }); });
    btn.addEventListener('click', function () { campo.value = campo.getAttribute('data-serie'); aggiorna(); campo.focus(); });
  });
  form.querySelectorAll('[data-fotocampo]').forEach(function (c) {
    var nome = c.getAttribute('data-fotocampo'), file = c.querySelector('input[type=file]'), togli = c.querySelector('input[type=checkbox]'), serie = c.getAttribute('data-serie-url');
    if (file) {
      file.addEventListener('focus', function () { manda({ tipo: 'vai', chiave: nome }); });
      file.addEventListener('change', function () {
        var f = file.files && file.files[0]; if (!f || !/^image\//.test(f.type)) return;
        var r = new FileReader(); r.onload = function () { manda({ tipo: 'foto', nome: nome, url: r.result }); if (togli) togli.checked = false; }; r.readAsDataURL(f);
      });
    }
    if (togli && serie) togli.addEventListener('change', function () { if (togli.checked) { file.value = ''; manda({ tipo: 'foto', nome: nome, url: serie }); } });
  });
  form.querySelectorAll('[data-salva]').forEach(function (b) {
    b.addEventListener('click', function () { form.querySelector('[data-torna]').value = b.getAttribute('data-salva'); });
  });
  // Telefono (390 px) o computer (1280 px, rimpicciolito per stare nella colonna).
  var vista = function (quale) {
    if (!iframe || !cornice) return;
    document.querySelectorAll('[data-vista]').forEach(function (b) { b.setAttribute('aria-pressed', b.getAttribute('data-vista') === quale ? 'true' : 'false'); });
    var larga = cornice.clientWidth, alta = cornice.clientHeight;
    var w = quale === 'computer' ? 1280 : 390, s = Math.min(1, (larga - 2) / w);
    iframe.style.width = w + 'px'; iframe.style.height = Math.round(alta / s) + 'px'; iframe.style.transform = 'scale(' + s + ')';
    cornice.setAttribute('data-vista-attiva', quale);
  };
  document.querySelectorAll('[data-vista]').forEach(function (b) { b.addEventListener('click', function () { vista(b.getAttribute('data-vista')); }); });
  window.addEventListener('resize', function () { vista(cornice ? cornice.getAttribute('data-vista-attiva') || 'telefono' : 'telefono'); });
  vista('telefono');
})();
</script>
