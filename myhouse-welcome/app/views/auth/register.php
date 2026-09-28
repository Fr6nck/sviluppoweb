<?php use function MHW\b; use MHW\{Support, Csrf}; $title = 'Crea il tuo account'; ?>
<h1>Cominciamo.</h1>
<p class="muted" style="margin-top:14px;line-height:24px">Crea il tuo account e configura gratuitamente la guida.
  Paghi solo quando decidi di pubblicarla.</p>
<?php if ($err): ?><p class="note note--err" style="margin-top:20px" role="alert"><?= Support::e($err) ?></p><?php endif; ?>
<form method="post" class="panel stack" style="margin-top:24px" novalidate><?= Csrf::field() ?>
  <input type="hidden" name="piano" value="<?= (int) $piano ?>">
  <div class="field" style="margin:0"><label for="name">Nome</label>
    <input id="name" name="name" type="text" required maxlength="120" autocomplete="name" value="<?= Support::e($vecchi['name']) ?>" autofocus></div>
  <div class="field" style="margin:0"><label for="email">Email</label>
    <input id="email" name="email" type="email" required autocomplete="email" value="<?= Support::e($vecchi['email']) ?>"></div>
  <div class="field" style="margin:0"><label for="password">Password <span class="muted">— almeno 8 caratteri</span></label>
    <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password"></div>
  <label class="check" style="align-items:flex-start"><input type="checkbox" name="termini" value="1" required style="margin-top:4px">
    <span>Accetto i <a href="<?= b() ?>/termini" target="_blank" rel="noopener">Termini e condizioni</a></span></label>
  <label class="check" style="align-items:flex-start;margin-top:-6px"><input type="checkbox" name="privacy" value="1" required style="margin-top:4px">
    <span>Ho letto l'<a href="<?= b() ?>/privacy" target="_blank" rel="noopener">informativa sulla privacy</a></span></label>
  <button class="btn btn--block">Crea l'account</button>
</form>
<p class="muted small" style="margin-top:20px">Hai già un account? <a href="<?= b() ?>/accedi">Accedi</a>.</p>
