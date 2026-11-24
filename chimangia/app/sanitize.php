<?php
/**
 * Whitelist dell'HTML prodotto dall'editor semplice.
 * Ammessi: p, br, strong, em, a[href], ul, ol, li. Tutto il resto viene tolto
 * (il testo resta, salvo script/style/iframe che spariscono con il contenuto).
 */
declare(strict_types=1);

defined('CM_ROOT') || exit;

function sanitize_html(string $html): string
{
    $html = trim($html);
    if ($html === '') {
        return '';
    }
    $allowed = ['p', 'br', 'strong', 'em', 'a', 'ul', 'ol', 'li'];
    $rename = ['b' => 'strong', 'i' => 'em', 'div' => 'p'];
    $drop = ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'template', 'noscript', 'form', 'input', 'textarea', 'select', 'button', 'head', 'title', 'meta', 'link'];

    $doc = new DOMDocument('1.0', 'UTF-8');
    $prev = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8"><!DOCTYPE html><html><body><div id="cm-root">' . $html . '</div></body></html>', LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    $root = $doc->getElementById('cm-root');
    if (!$root) {
        return e(strip_tags($html));
    }

    $out = new DOMDocument('1.0', 'UTF-8');
    $container = $out->createElement('div');
    $out->appendChild($container);

    $walk = function (DOMNode $src, DOMNode $dst) use (&$walk, $out, $allowed, $rename, $drop): void {
        foreach (iterator_to_array($src->childNodes) as $child) {
            if ($child instanceof DOMText) {
                $dst->appendChild($out->createTextNode($child->nodeValue ?? ''));
                continue;
            }
            if (!$child instanceof DOMElement) {
                continue;
            }
            $tag = strtolower($child->tagName);
            if (in_array($tag, $drop, true)) {
                continue;
            }
            $tag = $rename[$tag] ?? $tag;
            if (!in_array($tag, $allowed, true)) {
                $walk($child, $dst);
                continue;
            }
            $el = $out->createElement($tag);
            if ($tag === 'a') {
                $href = trim((string) $child->getAttribute('href'));
                if (safe_href($href)) {
                    $el->setAttribute('href', $href);
                    if (preg_match('#^https?://#i', $href)) {
                        $el->setAttribute('rel', 'noopener');
                    }
                }
            }
            $dst->appendChild($el);
            $walk($child, $el);
        }
    };
    $walk($root, $container);

    $result = '';
    foreach ($container->childNodes as $n) {
        $result .= $out->saveHTML($n);
    }
    // Paragrafi vuoti e testo nudo alla radice.
    $result = preg_replace('#<p>(\s|<br>)*</p>#', '', $result) ?? '';
    $result = trim($result);
    if ($result !== '' && !preg_match('#^<(p|ul|ol)[\s>]#', $result)) {
        $result = '<p>' . $result . '</p>';
    }
    return $result;
}

/** Destinazioni ammesse per link e pulsanti. */
function safe_href(string $href): bool
{
    if ($href === '') {
        return false;
    }
    if (preg_match('/[\x00-\x1F\x7F]/', $href)) {
        return false;
    }
    return (bool) preg_match('#^(https?://[^\s"<>]+|mailto:[^\s"<>]+|tel:[0-9+().\s-]+|\#[A-Za-z0-9_-]*|/[^\s"<>]*|\.{1,2}/[^\s"<>]*|[a-z0-9_-]+/?(\#[A-Za-z0-9_-]*)?)$#i', $href);
}

/** Testo semplice su una riga, lunghezza massima. */
function clean_text(string $s, int $max = 500): string
{
    $s = str_replace(["\r", "\n", "\t"], ' ', $s);
    $s = preg_replace('/[\x00-\x1F\x7F]/u', '', $s) ?? '';
    $s = trim(preg_replace('/ {2,}/', ' ', $s) ?? '');
    return mb_substr($s, 0, $max);
}

/** Testo su più righe (elenchi "una voce per riga"). */
function clean_lines(string $s, int $max = 3000): string
{
    $s = str_replace("\r", '', $s);
    $s = preg_replace('/[\x00-\x08\x0B-\x1F\x7F]/u', '', $s) ?? '';
    return mb_substr(trim($s), 0, $max);
}

function clean_href(string $s, string $fallback = '#contatti'): string
{
    $s = trim($s);
    return safe_href($s) ? mb_substr($s, 0, 500) : $fallback;
}
