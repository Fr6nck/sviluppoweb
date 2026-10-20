<?php use function MHW\t, MHW\tf;
/* 9F · «Una lingua in più, oltre alle cinque di base» (mockup lingua-traduzione, riquadro 1): a cosa serve la lingua in più,
   con due esempi e la riga sui bottoni della guida. I testi stanno in Testi e foto (TestiSito, sezione «Pannello»).
   Con Essential il riquadro dice «2 lingue: la tua e una a scelta», coerente con Entitlements::regolaLingue (8C).
   Riceve $prop (account_id, default_locale) e, facoltativo, $comeGenerico (dal primo passo della procedura: versione breve). */
use MHW\{Support, I18n, Entitlements, TestiSito};
$aidC = (int) $prop['account_id'];
$locC = (string) ($prop['default_locale'] ?: 'it');
$xC = Entitlements::limit($aidC, 'lingua_extra', 0);
$nC = Entitlements::limit($aidC, 'locales', 1);
$conBottoni = array_values(array_filter(I18n::EXTRA, [I18n::class, 'interfaccia']));
$nomiBott = array_map(fn($l) => mb_strtolower((string) (MHW\Config::get('locales')[$l] ?? $l)), $conBottoni);
$elenco = fn(array $l) => count($l) > 1 ? implode(', ', array_slice($l, 0, -1)) . ' e ' . end($l) : (string) ($l[0] ?? '');
$locT = MHW\Pannello::en() ? 'en' : null;   // 9F·11: in inglese quando il pannello è in inglese (chiavi «en:» di Testi e foto, o lang/sito-en.php)
$grezzo = fn(string $k) => TestiSito::disponibili() ? TestiSito::get($k, $locT) : TestiSito::serie($k, $locT);
$sostT = fn(array $sost) => array_combine(array_map(fn($x) => '{' . $x . '}', array_keys($sost)), array_map('strval', array_values($sost)));
$t = fn(string $k, array $sost = []) => strtr((string) preg_replace(['/\*\*(.+?)\*\*/s', '/\[([^\]]+)\]\(([^)\s]+)\)/'], ['$1', '$1'], $grezzo($k)), $sostT($sost));
$h = fn(string $k, array $sost = []) => TestiSito::formatta($grezzo($k), $sost);
$esempio = function (string $k) use ($t): array { $r = preg_split('/\R/', trim($t($k)), 2); return [(string) ($r[0] ?? ''), (string) ($r[1] ?? '')]; };
[$e1t, $e1] = $esempio('lingue.extra.esempio1'); [$e2t, $e2] = $esempio('lingue.extra.esempio2');
$breve = !empty($comeGenerico); ?>
<section class="lingua-come lingua-come--aperta" aria-labelledby="lingua-come-titolo">
  <h2 id="lingua-come-titolo" class="lingua-come__titolo"><?= Support::e($t('lingue.extra.titolo')) ?></h2>
  <?php if ($xC <= 0): /* Essential: due lingue in tutto */ ?>
    <p><?= Support::e($t('lingue.extra.essential')) ?></p>
  <?php else: ?>
    <p><?= $h('lingue.extra.testo') ?></p>
  <?php endif; ?>
  <?php if (!$breve): ?>
    <div class="lingua-come__esempi">
      <div><b><?= Support::e($e1t) ?></b><?= Support::e($e1) ?></div>
      <div><b><?= Support::e($e2t) ?></b><?= Support::e($e2) ?></div>
    </div>
  <?php endif; ?>
  <p class="small muted" style="margin:0"><?= Support::e($t('lingue.extra.nota', ['bottoni' => $elenco($nomiBott)])) ?>
    <?php if ($locC !== 'it' && !I18n::interfaccia($locC)): $nomeL = MHW\Pannello::en() ? (string) (MHW\Config::get('locales')[$locC] ?? $locC) : I18n::nomeIt($locC); ?> <?= Support::e(ucfirst($nomeL)) ?>: <?= MHW\Pannello::t('i bottoni in inglese, i tuoi testi in', 'buttons in English, your texts in') ?> <?= Support::e($nomeL) ?>.<?php endif; ?></p>
</section>
