<?php
/* Testi e foto del sito: ogni titolo, paragrafo e bottone della home (e l'intestazione di
   /domande), raggruppati per blocco nell'ordine della pagina, con il testo di serie sempre a
   portata di mano («Ripristina»). In fondo, le foto. Un solo modulo: ogni «Salva» salva tutto.
   Senza JavaScript: un campo lasciato vuoto torna al testo di serie. */
use function MHW\b;
use MHW\{Support, Csrf, Icon, TestiSito};
$title = 'Testi e foto';
$catalogo = TestiSito::catalogo();
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

  <div class="cifre">
    <?php foreach ([['cifra--pino', 'doc', 'Testi cambiati', $cambiati . ' su ' . count($catalogo), 'gli altri sono di serie'],
                    ['cifra--mare', 'drop', 'Foto caricate', $fotoCaricate . ' su ' . count(TestiSito::IMMAGINI), 'le altre sono di serie'],
                    ['cifra--ocra', 'eye', 'La home', 'Apri', 'per vedere il risultato']] as [$tono, $ico, $et, $val, $nota]): ?>
      <div class="cifra <?= $tono ?>"><span class="cifra__testa"><span class="cifra__ico"><?= Icon::svg($ico, 17) ?></span><?= $et ?></span>
        <?php if ($val === 'Apri'): ?><a class="cifra__valore" href="<?= b() ?>/" target="_blank" rel="noopener">Apri <?= Icon::svg('external', 16, 2) ?></a>
        <?php else: ?><b class="cifra__valore"><?= Support::e($val) ?></b><?php endif; ?>
        <span class="cifra__nota"><?= Support::e($nota) ?></span></div>
    <?php endforeach; ?>
  </div>

  <nav class="testi__indice" aria-label="Blocchi della pagina">
    <?php foreach (TestiSito::SEZIONI as $sid => [$nome]): ?><a href="#testi-<?= $sid ?>"><?= Support::e($nome) ?></a><?php endforeach; ?>
    <a href="#testi-foto">Foto</a>
  </nav>

  <form method="post" action="<?= b() ?>/admin/testi" enctype="multipart/form-data" class="stack stack--lg" data-testi><?= Csrf::field() ?>
    <input type="hidden" name="torna" value="" data-torna>
    <?php foreach (TestiSito::SEZIONI as $sid => [$nome, $nota]): $campi = $perSezione[$sid] ?? []; if (!$campi) continue; ?>
      <section class="panel stack" id="testi-<?= $sid ?>" aria-labelledby="testi-<?= $sid ?>-titolo">
        <div class="stack stack--sm">
          <h2 id="testi-<?= $sid ?>-titolo" style="font-size:22px"><?= Support::e($nome) ?></h2>
          <?php if ($nota !== ''): ?><p class="help" style="margin:0"><?= Support::e($nota) ?></p><?php endif; ?>
        </div>
        <?php foreach ($campi as $k => [, $etichetta, $tipo, $serie, $aiuto]):
              $id = 'testo-' . str_replace('.', '-', $k); $valore = TestiSito::get($k); $diverso = $valore !== $serie; ?>
          <div class="field testi__campo<?= $diverso ? ' testi__campo--cambiato' : '' ?>" data-campo>
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
          <div class="field testi__fotocampo" style="margin:0">
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
</div>
<script>
/* «Ripristina» riempie il campo con il testo di serie (data-serie); il bottone compare solo se
   il testo è diverso. «Salva» di un blocco riporta a quel blocco dopo il salvataggio. */
(function () {
  var form = document.querySelector('[data-testi]'); if (!form) return;
  form.querySelectorAll('[data-campo]').forEach(function (c) {
    var campo = c.querySelector('input, textarea'), btn = c.querySelector('[data-ripristina]');
    if (!campo || !btn) return;
    var aggiorna = function () { btn.hidden = campo.value === campo.getAttribute('data-serie'); c.classList.toggle('testi__campo--cambiato', !btn.hidden); };
    campo.addEventListener('input', aggiorna);
    btn.addEventListener('click', function () { campo.value = campo.getAttribute('data-serie'); aggiorna(); campo.focus(); });
  });
  form.querySelectorAll('[data-salva]').forEach(function (b) {
    b.addEventListener('click', function () { form.querySelector('[data-torna]').value = b.getAttribute('data-salva'); });
  });
})();
</script>
