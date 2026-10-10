<?php
/* Varianti camera: le camere della stessa struttura che hanno un Wi-Fi o istruzioni di accesso
   diverse. Ognuna ha un QR e un link propri; il resto della guida è lo stesso.
   Riceve: $prop, $varianti, $permesse, $stripe, $oggi, $fine, $lingue, $err, $vecchi, $quanteAccount, $sub. */
use function MHW\b;
use MHW\{Support, Csrf, Icon, Varianti};
$title = 'Varianti camera — ' . $prop['name'];
$nomiLingue = MHW\Config::get('locales');
$prezzo = Support::money(Varianti::prezzo());
$def = (string) $prop['default_locale'];
$campi = function (array $v, string $id) use ($lingue, $nomiLingue, $def): void {
    $testo = function (string $campo, string $l) use ($v): string {
        if (isset($v['_post'])) return (string) (($v['_post'][$campo] ?? [])[$l] ?? '');
        $t = json_decode((string) ($v[$campo] ?? ''), true);
        return is_array($t) ? (string) ($t[$l] ?? '') : '';
    }; ?>
  <div class="grid grid-3">
    <div class="field" style="margin:0"><label for="<?= $id ?>-nome">Nome della camera</label>
      <input type="text" id="<?= $id ?>-nome" name="name" maxlength="80" required placeholder="Camera 2, La Rosa…" value="<?= Support::e((string) ($v['name'] ?? '')) ?>"></div>
    <div class="field" style="margin:0"><label for="<?= $id ?>-ssid">Wi-Fi della camera <span class="muted">(nome della rete)</span></label>
      <input type="text" id="<?= $id ?>-ssid" name="wifi_ssid" maxlength="80" autocomplete="off" spellcheck="false" value="<?= Support::e((string) ($v['wifi_ssid'] ?? '')) ?>"></div>
    <div class="field" style="margin:0"><label for="<?= $id ?>-pw">Password del Wi-Fi</label>
      <input type="text" id="<?= $id ?>-pw" name="wifi_password" maxlength="120" autocomplete="off" spellcheck="false" value="<?= Support::e((string) ($v['wifi_password'] ?? '')) ?>"></div>
  </div>
  <?php foreach ($lingue as $i => $l): $et = count($lingue) > 1 ? ' · ' . ($nomiLingue[$l] ?? strtoupper($l)) : ''; ?>
    <?php if ($i === 1): /* le altre lingue: facoltative, chiuse; senza traduzione l'ospite legge la lingua principale */ ?>
      <details class="stack" style="gap:12px"><summary class="linkbtn" style="cursor:pointer">Le istruzioni nelle altre lingue della guida <span class="muted">(facoltative)</span></summary>
    <?php endif; ?>
    <div class="grid grid-2">
      <div class="field" style="margin:0"><label for="<?= $id ?>-acc-<?= $l ?>">Come si entra in camera<?= Support::e($et) ?></label>
        <textarea id="<?= $id ?>-acc-<?= $l ?>" name="access[<?= $l ?>]" rows="3" maxlength="2000" lang="<?= $l ?>"
                  placeholder="<?= $l === $def ? 'Secondo piano, la porta a destra; la chiave ha il portachiavi verde.' : '' ?>"><?= Support::e($testo('access', $l)) ?></textarea></div>
      <div class="field" style="margin:0"><label for="<?= $id ?>-nota-<?= $l ?>">Nota per chi dorme qui <span class="muted">(facoltativa)</span><?= Support::e($et) ?></label>
        <textarea id="<?= $id ?>-nota-<?= $l ?>" name="note[<?= $l ?>]" rows="3" maxlength="1000" lang="<?= $l ?>"><?= Support::e($testo('note', $l)) ?></textarea></div>
    </div>
  <?php endforeach; ?>
  <?php if (count($lingue) > 1): ?>
      <p class="small muted" style="margin:0">Dove non scrivi niente, l'ospite legge il testo in <?= Support::e($nomiLingue[$def] ?? $def) ?>.</p>
    </details>
  <?php endif;
}; ?>
<div class="stack stack--lg" style="max-width:900px">
  <div class="stack stack--sm">
    <h1>Varianti camera.</h1>
    <p class="lead">Per le camere della stessa struttura con un Wi-Fi o istruzioni di accesso diverse. Ogni variante ha il suo QR:
      chi lo inquadra vede la stessa guida, con il Wi-Fi e le istruzioni della sua camera.</p>
    <p class="small muted">Una guida è un'unità ricettiva, con il suo indirizzo e il suo CIN: le varianti sono camere della stessa struttura, non case diverse.
      Le modifiche alle varianti si vedono subito, senza ripubblicare.</p>
  </div>
  <?php if ($err): ?><p class="note note--err" role="alert"><?= Support::e($err) ?></p><?php endif; ?>

  <?php foreach ($varianti as $i => $v): $vid = (int) $v['id']; $link = Support::baseUrl() . '/q/' . $v['token']; ?>
    <section class="panel stack" id="variante-<?= $vid ?>" aria-labelledby="var-t-<?= $vid ?>" style="gap:14px">
      <div class="spread spread--mid">
        <h2 id="var-t-<?= $vid ?>" style="font-size:22px"><?= Icon::svg('key', 18) ?> <?= Support::e($v['name']) ?></h2>
        <span class="small muted"><?= Support::e($prezzo) ?> + IVA / anno</span>
      </div>
      <div class="row" style="gap:16px;align-items:center;flex-wrap:wrap">
        <img src="data:image/png;base64,<?= base64_encode(MHW\Qr::png($link, 4, 2)) ?>" width="112" height="112" alt="QR di <?= Support::e($v['name']) ?>">
        <div class="stack" style="gap:8px;min-width:0">
          <code class="small" style="word-break:break-all"><?= Support::e($link) ?></code>
          <div class="row" style="gap:8px;flex-wrap:wrap">
            <?php foreach (['pdf' => 'PDF da stampare', 'png' => 'PNG', 'svg' => 'SVG'] as $f => $et): ?>
              <a class="btn btn--ghost btn--sm" href="<?= b() ?>/pannello/<?= (int) $prop['id'] ?>/varianti/<?= $vid ?>/qr.<?= $f ?>"><?= Support::e($et) ?></a>
            <?php endforeach; ?>
            <a class="btn btn--ghost btn--sm" href="<?= Support::e(b() . '/g/' . $prop['slug'] . '/c/' . $v['token']) ?>" target="_blank" rel="noopener">Apri la guida<?= Icon::svg('external', 14) ?></a>
          </div>
        </div>
      </div>
      <details>
        <summary class="linkbtn" style="cursor:pointer">Modifica nome, Wi-Fi e istruzioni</summary>
        <form method="post" action="<?= b() ?>/pannello/<?= (int) $prop['id'] ?>/varianti/<?= $vid ?>" class="stack" style="margin-top:12px;gap:12px"><?= Csrf::field() ?>
          <?php $campi($v, 'v' . $vid); ?>
          <?php /* 6N: aperto (e con la casella obbligatoria) se la variante salvata ha già un codice, anche senza JavaScript.
                   access e note arrivano da room_variants come JSON. */
                $mostra = MHW\Sicurezza::campiVariante(['nome' => (string) $v['name'], 'accesso' => json_decode((string) $v['access'], true) ?: [],
                                                       'nota' => json_decode((string) $v['note'], true) ?: []]) !== [];
                include __DIR__ . '/_codici_variante.php'; ?>
          <div class="actions"><button class="btn btn--sm">Salva la variante</button></div>
        </form>
      </details>
      <form method="post" action="<?= b() ?>/pannello/<?= (int) $prop['id'] ?>/varianti/<?= $vid ?>/togli" style="margin:0"
            onsubmit="return confirm('Togliere «<?= Support::e(addslashes($v['name'])) ?>»? Il suo QR aprirà la guida senza la variante.')"><?= Csrf::field() ?>
        <button class="linkbtn linkbtn--danger"><?= Icon::svg('bin', 15) ?> Togli la variante</button>
      </form>
    </section>
  <?php endforeach; ?>

  <?php if (!$permesse): ?>
    <div class="limit" role="status">
      <p style="max-width:620px">Le varianti camera si aggiungono con Plus o Portfolio, <?= Support::e($prezzo) ?> + IVA l'anno l'una.</p>
      <a class="btn btn--sm" href="<?= b() ?>/account/piano">Cambia piano</a>
    </div>
  <?php elseif (!$sub): ?>
    <p class="note">Le varianti si aggiungono quando l'abbonamento è attivo: pubblica prima la guida.</p>
  <?php elseif (count($varianti) >= Varianti::MAX): ?>
    <p class="note">Hai <?= Varianti::MAX ?> varianti, il massimo per una guida.</p>
  <?php else: ?>
    <section class="fieldset stack" aria-labelledby="var-nuova" style="gap:12px">
      <h2 id="var-nuova" class="legend" style="font-size:18px">Aggiungi una variante</h2>
      <form method="post" action="<?= b() ?>/pannello/<?= (int) $prop['id'] ?>/varianti" class="stack" style="gap:12px"><?= Csrf::field() ?>
        <?php $campi($vecchi ? ['name' => $vecchi['name'] ?? '', 'wifi_ssid' => $vecchi['wifi_ssid'] ?? '', 'wifi_password' => $vecchi['wifi_password'] ?? '', '_post' => $vecchi] : [], 'nuova'); ?>
        <div class="panel stack" style="gap:8px">
          <div class="spread spread--mid"><span>Ogni variante</span><b><?= Support::e($prezzo) ?> <span class="small muted">+ IVA / anno</span></b></div>
          <?php if ($stripe): ?>
            <div class="spread spread--mid"><span>Oggi, per i giorni che restano<?= $fine ? ' fino al ' . Support::e(Support::date($fine)) : '' ?></span>
              <b><?= Support::e(Support::money($oggi)) ?> <span class="small muted">+ IVA</span></b></div>
            <p class="small muted" style="margin:0">Si addebita sul metodo di pagamento dell'abbonamento; la variante nasce solo se il pagamento riesce.
              Dal rinnovo la paghi insieme all'abbonamento<?= $quanteAccount ? ' (ora ne hai ' . (int) $quanteAccount . ')' : '' ?>.</p>
          <?php else: ?>
            <p class="small muted" style="margin:0">Il tuo abbonamento è gestito dal nostro staff: la variante si aggiunge senza pagare adesso, ne parliamo al rinnovo.</p>
          <?php endif; ?>
          <label class="check" style="margin-top:4px"><input type="checkbox" name="conferma" value="1" required>
            <span>Aggiungo la variante a <?= Support::e($prezzo) ?> + IVA l'anno<?= $stripe ? ' e pago oggi la parte che resta di quest\'anno' : '' ?>.</span></label>
        </div>
        <?php $mostra = !empty($codiciVecchi); include __DIR__ . '/_codici_variante.php'; ?>
        <div class="actions"><button class="btn"><?= Icon::svg('plus', 15, 2) ?> Aggiungi la variante</button></div>
      </form>
    </section>
  <?php endif; ?>
</div>
