<?php
/* 8J · Termini e condizioni, Informativa sulla privacy e Accordo sul trattamento dei dati (DPA), in italiano
   (/termini, /privacy, /dpa) e in inglese (/en/terms, /en/privacy, /en/dpa). I testi: Testi e foto → Documenti
   legali (TestiSito, lang/legale.php); i dati del titolare e le versioni: Impostazioni → Titolare e documenti legali.
   Il testo in vigore si archivia da solo a ogni apertura (Legale::archivia): «Versioni precedenti» mostra quelli
   sostituiti. Con $archiviata (?versione=…) si mostra una versione vecchia, così com'era, con l'avviso in cima.
   Testi di partenza: vanno rivisti da un legale. */
use function MHW\b;
use MHW\{Support, Legale, TestiSito, LinguaSito, Statistiche, Registri};
$en = LinguaSito::en();
$loc = $en ? 'en' : 'it';
[$nomeIt, $nomeEn, $percorsoIt, $percorsoEn] = Legale::DOCUMENTI[$doc];
$nome = $en ? $nomeEn : $nomeIt;
$qui = $en ? $percorsoEn : $percorsoIt;
$U = fn(string $it, string $inglese) => $en ? $inglese : $it;
$data = fn(string $v) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? ($en ? gmdate('j F Y', (int) strtotime($v)) : Support::date($v)) : $v;
$versione = Legale::versioni()[$doc];
$larghezza = '760px';
$title = $nome;
$B = fn(string $k) => Legale::blocchi($k, $en);
$H = fn(string $k) => TestiSito::html($k, Legale::segnaposto($en));

if (!empty($archiviata)): /* una versione vecchia, così com'era */ ?>
  <div class="note legale__vecchia" role="note">
    <?= $U('Questa è la versione del', 'This is the version of') ?> <b><?= Support::e($data((string) $archiviata['version'])) ?></b>,
    <?= $U('non più in vigore.', 'no longer in force.') ?> <a href="<?= b() . $qui ?>"><?= $U('Leggi la versione in vigore', 'Read the current version') ?></a>
  </div>
  <div class="legale legale--archivio"><?= $archiviata['html'] /* HTML scritto dal sito stesso, archiviato da Legale::archivia o dalla migrazione 042 */ ?></div>
<?php return; endif;

// Le parti del documento: [id, titolo, chiave del testo]
$parti = [];
if ($doc === 'termini') {
    $ancore = [2 => 'unita', 5 => 'rinnovo', 7 => 'recesso', 9 => 'ruoli', 12 => 'inviti', 13 => 'traduzioni', 17 => 'giudizio-sconto', 18 => 'codice-omaggio'];
    // 9B/9D: i punti 17 «Giudizio con sconto» e 18 «Codice omaggio» stanno dopo «Invita un amico» (12), con il loro numero; gli altri non cambiano numero.
    foreach ([1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 17, 18, 13, 14, 15, 16] as $i) $parti[] = [$ancore[$i] ?? 'termini-' . $i, $i . '. ' . TestiSito::html("termini.$i.titolo"), "termini.$i.testo"];
    $parti[] = ['allegato-a', $U('Allegato A · Accordo sul trattamento dei dati', 'Annex A · Data processing agreement'), null];
    $parti[] = ['modulo-recesso', TestiSito::html('termini.b.titolo'), 'termini.b.testo'];
} elseif ($doc === 'privacy') {
    for ($i = 1; $i <= 13; $i++) $parti[] = ['trattamento-' . $i, $i . '. ' . TestiSito::html("privacy.t$i.titolo"), "privacy.t$i.testo"];
    foreach (['fornitori', 'trasferimenti', 'minori', 'diritti'] as $p) $parti[] = [$p, TestiSito::html("privacy.$p.titolo"), "privacy.$p.testo"];
    $parti[] = ['cookie', $U('Cookie', 'Cookies'), null];
    foreach (['modifiche', 'titolare'] as $p) $parti[] = [$p, TestiSito::html("privacy.$p.titolo"), "privacy.$p.testo"];
} else {
    for ($i = 1; $i <= 12; $i++) $parti[] = ['dpa-' . $i, $i . '. ' . TestiSito::html("dpa.$i.titolo"), "dpa.$i.testo"];
}
$precedenti = Legale::precedenti($doc);
$altra = $en ? $percorsoIt : $percorsoEn;

ob_start(); ?>
<h1 style="margin-top:24px"><?= Support::e($nome) ?></h1>
<p class="small muted legale__meta">
  <?= $U('Versione del', 'Version of') ?> <?= Support::e($data($versione)) ?><?php if (Legale::futura($versione)): ?> · <b><?= $U('in vigore dal', 'in force from') ?> <?= Support::e($data($versione)) ?></b><?php endif; ?>
  · <?= $doc === 'dpa' ? $U('Responsabile del trattamento', 'Processor') : ($doc === 'privacy' ? $U('Titolare', 'Controller') : $U('Fornitore', 'Provider')) ?>: <?= Support::e(Legale::rigaTitolare($en)) ?>
</p>
<div class="legale stack" style="margin-top:24px;line-height:1.6">
  <div class="legale__intro"><?= $B($doc . '.intro') ?></div>
  <nav class="legale__indice" aria-label="<?= $U('Indice', 'Contents') ?>">
    <b><?= $U('Indice', 'Contents') ?></b>
    <ul>
      <?php foreach ($parti as [$id, $titolo]): ?><li><a href="#<?= $id ?>"><?= $titolo ?></a></li><?php endforeach; ?>
    </ul>
  </nav>
  <?php foreach ($parti as [$id, $titolo, $chiave]): ?>
    <section class="legale__parte" id="<?= $id ?>" aria-labelledby="<?= $id ?>-t">
      <?php if ($id === 'trattamento-10'): ?><span id="registri"></span><?php endif; ?>
      <h2 id="<?= $id ?>-t"><?= $titolo ?></h2>
      <?php if ($chiave !== null): ?><?= $B($chiave) ?>
      <?php elseif ($id === 'allegato-a'): ?>
        <p><?= $U('L\'Accordo sul trattamento dei dati personali (art. 28 GDPR) è una pagina a sé:', 'The data processing agreement (art. 28 GDPR) is a separate page:') ?>
          <a href="<?= b() . ($en ? '/en/dpa' : '/dpa') ?>"><?= $U('leggi l\'Accordo sul trattamento dei dati', 'read the data processing agreement') ?></a>.</p>
      <?php elseif ($id === 'cookie'): /* 8H · Cookie: Matomo, GA4 e Clarity solo se configurati in SEO e GEO */ $statC = Statistiche::config(); ?>
        <p><?= TestiSito::html('cookie.tecnici') ?></p>
        <?php if (Statistiche::matomo()): ?><p><?= TestiSito::html('cookie.matomo') ?></p><?php endif; ?>
        <?php if ($statC['ga4'] !== ''): ?><p><?= TestiSito::html('cookie.ga4') ?></p><?php endif; ?>
        <?php if ($statC['clarity'] !== ''): ?><p><?= TestiSito::html('cookie.clarity') ?></p><?php endif; ?>
        <?php if (Statistiche::consensoServe()): ?><p><?= TestiSito::html('cookie.revoca') ?> <a href="#" data-consenso-apri><?= TestiSito::html('consenso.preferenze') ?></a></p><?php endif; ?>
        <p><?= TestiSito::html('cookie.guide') ?></p>
      <?php endif; ?>
      <?php if ($id === 'modulo-recesso'):
            $piano = preg_replace(["#</li>#", "#</p>#", '#<br>#'], ["\n", "\n\n", "\n"], $B('termini.b.testo'));
            $piano = trim(html_entity_decode(strip_tags($piano), ENT_QUOTES, 'UTF-8'));
            $email = (string) ((MHW\Config::get('legal') ?? [])['contact_email'] ?? ''); ?>
        <?php if ($email !== ''): ?><p><a class="btn btn--ghost btn--sm" href="mailto:<?= Support::e($email) ?>?subject=<?= rawurlencode($U('Recesso dal contratto MyHouse Welcome', 'Withdrawal from the MyHouse Welcome contract')) ?>&amp;body=<?= rawurlencode($piano) ?>"><?= $U('Compila e manda il modulo per email', 'Fill in and send the form by email') ?></a></p><?php endif; ?>
      <?php endif; ?>
    </section>
  <?php endforeach; ?>
</div>
<?php $documento = (string) ob_get_clean();
// Il testo in vigore (non quello annunciato per il futuro, che non ha ancora sostituito niente) si archivia così com'è.
Legale::archivia($doc, $loc, $versione, $documento);
echo $documento; ?>
<div class="legale__piede small">
  <p><?= $doc !== 'termini' ? '<a href="' . b() . ($en ? '/en/terms' : '/termini') . '">' . $U('Termini e condizioni', 'Terms and conditions') . '</a> · ' : '' ?>
     <?= $doc !== 'privacy' ? '<a href="' . b() . ($en ? '/en/privacy' : '/privacy') . '">' . $U('Informativa sulla privacy', 'Privacy policy') . '</a> · ' : '' ?>
     <?= $doc !== 'dpa' ? '<a href="' . b() . ($en ? '/en/dpa' : '/dpa') . '">' . $U('Accordo sul trattamento dei dati', 'Data processing agreement') . '</a> · ' : '' ?>
     <a href="<?= b() . $altra ?>" hreflang="<?= $en ? 'it' : 'en' ?>" lang="<?= $en ? 'it' : 'en' ?>"><?= $en ? 'Versione italiana' : 'English version' ?></a></p>
  <?php if ($precedenti): ?>
    <details class="legale__versioni" id="versioni">
      <summary><?= $U('Versioni precedenti', 'Previous versions') ?> (<?= count(array_unique(array_column($precedenti, 'version'))) ?>)</summary>
      <ul>
        <?php $viste = []; foreach ($precedenti as $p): if (isset($viste[$p['version']])) continue; $viste[$p['version']] = 1; ?>
          <li><a href="<?= b() . $qui ?>?versione=<?= rawurlencode((string) $p['version']) ?>" rel="nofollow"><?= $U('Versione del', 'Version of') ?> <?= Support::e($data((string) $p['version'])) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </details>
  <?php endif; ?>
</div>
