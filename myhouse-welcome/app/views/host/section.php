<?php use function MHW\b; use MHW\{Support, Csrf, Media, View, Icon};
$title = ($tr['title'] ?: 'Sezione') . ' — ' . $p['name'];
$topnav = View::render('host/_propnav', ['p' => $p, 'qui' => 'guida'], null);
$foto = Media::url($s['media_id'] ? (int) $s['media_id'] : null);
$capoversi = array_values(array_filter(array_map('trim', preg_split('/\R{2,}/', (string) $tr['body']) ?: [])));
$tipi = ['text' => 'Testo semplice', 'wifi' => 'Wi-Fi', 'checkin' => 'Entrare in casa',
         'places' => 'Consigli sul posto'];
$colori = ['terracotta' => 'Terracotta', 'sea' => 'Mare', 'pine' => 'Pino', 'ochre' => 'Ocra', 'alert' => 'Allarme'];
$sfondo = ['terracotta' => '#b4451f', 'sea' => '#1c5a78', 'pine' => '#1f6b3f',
           'ochre' => '#b07d0c', 'alert' => '#9c2b20'][$s['color']] ?? '#b4451f'; ?>

<p class="small"><a href="<?= b() ?>/pannello/<?= (int) $p['id'] ?>">&larr; <?= Support::e($p['name']) ?></a></p>

<div class="stack stack--sm" style="margin:12px 0 28px">
  <h1><?= Support::e($tr['title'] ?: 'Sezione') ?></h1>
  <p class="lead">Scrivete come parlereste a un ospite seduto in cucina. Una riga vuota separa
    i passaggi: sulla guida diventano riquadri numerati. Un capoverso che comincia con
    <strong>Nota:</strong> diventa invece l'avviso in fondo alla pagina.</p>
</div>

<?php if ($err): ?><p class="note note--err" style="margin-bottom:20px"><?= Support::e($err) ?></p><?php endif; ?>

<div class="sheet">
  <form method="post" enctype="multipart/form-data" class="panel stack"><?= Csrf::field() ?>
    <input type="hidden" name="azione" value="salva">
    <div class="field" style="margin:0"><label for="title">Titolo</label>
      <input id="title" name="title" type="text" value="<?= Support::e($tr['title']) ?>" required></div>
    <div class="field" style="margin:0"><label for="body">Testo</label>
      <textarea id="body" name="body" style="min-height:180px"
        placeholder="Scrivete come parlereste a un ospite."><?= Support::e($tr['body']) ?></textarea></div>

    <div class="grid grid-2">
      <div class="field" style="margin:0"><label for="kind">Tipo</label>
        <select id="kind" name="kind">
          <?php foreach ($tipi as $k => $l): ?>
            <option value="<?= $k ?>" <?= $s['kind'] === $k ? 'selected' : '' ?>><?= $l ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="field" style="margin:0"><label for="color">Colore del riquadro</label>
        <select id="color" name="color">
          <?php foreach ($colori as $c => $l): ?>
            <option value="<?= $c ?>" <?= $s['color'] === $c ? 'selected' : '' ?>><?= $l ?></option>
          <?php endforeach; ?>
        </select></div>
    </div>

    <?php if ($s['kind'] === 'wifi'): ?>
      <hr class="rule">
      <span class="kicker">Quello che gli ospiti copiano al volo</span>
      <div class="grid grid-2">
        <div class="field" style="margin:0"><label for="wifi_ssid">Nome della rete</label>
          <input id="wifi_ssid" name="wifi_ssid" type="text" value="<?= Support::e($s['wifi_ssid']) ?>"></div>
        <div class="field" style="margin:0"><label for="wifi_pass">Password</label>
          <input id="wifi_pass" name="wifi_pass" type="text" value="<?= Support::e($s['wifi_pass']) ?>"></div>
      </div>
    <?php else: ?>
      <input type="hidden" name="wifi_ssid" value="<?= Support::e($s['wifi_ssid']) ?>">
      <input type="hidden" name="wifi_pass" value="<?= Support::e($s['wifi_pass']) ?>">
    <?php endif; ?>

    <?php if ($s['kind'] === 'checkin'): ?>
      <div class="field" style="margin:0"><label for="door_code">Codice della cassetta</label>
        <input id="door_code" name="door_code" type="text" value="<?= Support::e($s['door_code']) ?>"
               placeholder="4729"></div>
    <?php else: ?>
      <input type="hidden" name="door_code" value="<?= Support::e($s['door_code']) ?>">
    <?php endif; ?>

    <div class="field" style="margin:0"><label for="foto">Foto della sezione</label>
      <?php if ($ent['photos']['value'] === '0'): ?>
        <p class="note"><?= Icon::svg('info', 19) ?><span>Le foto sono comprese dal piano Plus in su.</span></p>
      <?php else: ?>
        <?php if ($foto): ?>
          <div class="shot" style="aspect-ratio:16/10;max-width:280px;margin-bottom:12px">
            <img src="<?= Support::e($foto) ?>" alt=""></div>
        <?php endif; ?>
        <input id="foto" name="foto" type="file" accept="image/jpeg,image/png,image/webp">
      <?php endif; ?>
    </div>

    <div class="row" style="margin-top:4px"><button class="btn">Salva la sezione</button></div>
  </form>

  <div class="stack" style="gap:14px">
    <span class="kicker">Anteprima dal telefono</span>
    <div class="preview" style="background:<?= $s['kind'] === 'wifi' ? '#17130d' : 'var(--paper)' ?>;
                                border-color:<?= $s['kind'] === 'wifi' ? '#342d23' : 'var(--line)' ?>">
      <?php if ($foto): ?><div class="preview__shot"><img src="<?= Support::e($foto) ?>" alt=""></div><?php endif; ?>
      <div class="preview__body" style="background:transparent;
           color:<?= $s['kind'] === 'wifi' ? '#f6f0e5' : 'var(--ink)' ?>">
        <span class="preview__title" style="font-family:Gloock,Georgia,serif;font-weight:400;letter-spacing:-.3px">
          <?= Support::e($tr['title'] ?: 'Senza titolo') ?></span>
        <?php if ($s['kind'] === 'wifi' && ($s['wifi_ssid'] || $s['wifi_pass'])): ?>
          <div style="padding:16px;border-radius:18px;background:#211c15;border:1px solid #342d23;
                      display:flex;flex-direction:column;gap:12px">
            <div><span class="kicker" style="color:#b6a891">Rete</span>
              <div style="font-size:16px;font-weight:500"><?= Support::e($s['wifi_ssid'] ?: '—') ?></div></div>
            <div style="height:1px;background:#342d23"></div>
            <div><span class="kicker" style="color:#b6a891">Password</span>
              <div style="font-size:16px;font-weight:500"><?= Support::e($s['wifi_pass'] ?: '—') ?></div></div>
          </div>
        <?php elseif ($s['kind'] === 'checkin' && $s['door_code']): ?>
          <div class="step" style="padding:14px"><span class="n"><?= count($capoversi) + 1 ?></span>
            <span class="bigcode" style="font-size:22px"><?= Support::e(trim(chunk_split($s['door_code'], 1, ' '))) ?></span></div>
        <?php endif; ?>
        <?php foreach (array_slice($capoversi, 0, 2) as $c): ?>
          <p class="tiny" style="color:<?= $s['kind'] === 'wifi' ? '#b6a891' : 'var(--muted)' ?>;line-height:19px">
            <?= Support::e(mb_strimwidth($c, 0, 130, '…')) ?></p>
        <?php endforeach; ?>
        <span style="display:block;height:6px;border-radius:999px;background:<?= $sfondo ?>;width:60px"></span>
      </div>
    </div>
  </div>
</div>

<?php if ($s['kind'] === 'places'): ?>
<section style="margin-top:44px">
  <div class="spread spread--mid"><h2>I luoghi</h2>
    <span class="small muted"><?= count($places) ?> consigli</span></div>

  <?php if ($ent['places']['value'] === '0'): ?>
    <p class="note" style="margin-top:16px"><?= Icon::svg('info', 19) ?>
      <span>I consigli sul posto sono compresi dal piano Plus in su.</span></p>
  <?php else: ?>
    <div class="sheet" style="margin-top:16px">
      <div class="stack" style="gap:12px">
        <?php foreach ($places as $pl): $u = Media::url($pl['media_id'] ? (int) $pl['media_id'] : null); ?>
          <div class="place">
            <?php if ($u): ?><span class="thumb"><img src="<?= Support::e($u) ?>" alt=""></span><?php endif; ?>
            <span class="grow stack" style="gap:5px">
              <b><?= Support::e($pl['name']) ?></b>
              <span class="meta"><?= Support::e(trim($pl['category'] . ' · ' . $pl['distance'], ' ·')) ?></span>
              <?php if ($pl['badge']): ?>
                <span class="badge badge--<?= $pl['badge_tone'] === 'ochre' ? 'ochre-strong' : Support::e($pl['badge_tone']) ?>"
                      style="align-self:flex-start"><?= Support::e($pl['badge']) ?></span>
              <?php endif; ?>
            </span>
            <form method="post" style="margin:0"><?= Csrf::field() ?>
              <input type="hidden" name="azione" value="elimina-luogo">
              <input type="hidden" name="place_id" value="<?= (int) $pl['id'] ?>">
              <button class="btn btn--quiet btn--sm">Togli</button></form>
          </div>
        <?php endforeach; ?>
        <?php if (!$places): ?>
          <p class="note note--quiet">Nessun luogo ancora. Tre bastano: uno per la cena,
            uno per la colazione, uno per il gelato.</p>
        <?php endif; ?>
      </div>

      <form method="post" enctype="multipart/form-data" class="panel stack"><?= Csrf::field() ?>
        <input type="hidden" name="azione" value="luogo">
        <span class="kicker">Aggiungi un luogo</span>
        <div class="field" style="margin:0"><label for="nome">Nome</label>
          <input id="nome" name="nome" type="text" required placeholder="Osteria del Ponte"></div>
        <div class="field" style="margin:0"><label for="categoria">Categoria</label>
          <input id="categoria" name="categoria" type="text" placeholder="Trattoria"></div>
        <div class="field" style="margin:0"><label for="distanza">Distanza</label>
          <input id="distanza" name="distanza" type="text" placeholder="450 m · a piedi"></div>
        <div class="field" style="margin:0"><label for="badge">Etichetta</label>
          <input id="badge" name="badge" type="text" placeholder="Aperto fino alle 23"></div>
        <div class="field" style="margin:0"><label for="tono">Tono dell'etichetta</label>
          <select id="tono" name="tono">
            <?php foreach (['pine' => 'Verde', 'sea' => 'Blu', 'ochre' => 'Ocra',
                            'terracotta' => 'Terracotta', 'alert' => 'Rosso'] as $k => $l): ?>
              <option value="<?= $k ?>"><?= $l ?></option><?php endforeach; ?>
          </select></div>
        <div class="field" style="margin:0"><label for="nota">Nota</label>
          <input id="nota" name="nota" type="text" placeholder="Si prenota solo per telefono."></div>
        <div class="field" style="margin:0"><label for="fotoluogo">Foto</label>
          <input id="fotoluogo" name="foto" type="file" accept="image/jpeg,image/png,image/webp"></div>
        <button class="btn btn--ghost">Aggiungi il luogo</button>
      </form>
    </div>
  <?php endif; ?>
</section>
<?php endif; ?>

<form method="post" style="margin-top:44px"
      onsubmit="return confirm('Eliminare questa sezione? Non si torna indietro.')"><?= Csrf::field() ?>
  <input type="hidden" name="azione" value="elimina">
  <button class="btn btn--danger btn--sm">Elimina la sezione</button>
</form>
