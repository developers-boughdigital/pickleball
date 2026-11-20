<?php

namespace App\Support\Articles;

use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Support\Str;

/**
 * Cleans article HTML before it is saved. The editor only produces the tags
 * below, but the body is printed unescaped on the public site, so anything
 * else that arrives (a paste from Word, a script) is stripped here.
 */
class Html
{
    private const ALLOWED = ['p', 'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'blockquote', 'strong', 'b', 'em', 'i', 'u', 's', 'a', 'br', 'hr', 'code', 'pre'];

    /** Removed along with everything inside them. */
    private const DROPPED = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'noscript', 'template', 'svg', 'math', 'head', 'title', 'meta', 'link'];

    public static function clean(?string $html): string
    {
        if (blank($html)) {
            return '';
        }

        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8"?><div id="article-root">'.$html.'</div>', LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        $root = $dom->getElementById('article-root');

        if (! $root) {
            return e(strip_tags($html));
        }

        static::cleanChildren($root);

        $clean = '';
        foreach ($root->childNodes as $child) {
            $clean .= $dom->saveHTML($child);
        }

        return trim($clean);
    }

    private static function cleanChildren(DOMNode $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if ($node->nodeType === XML_TEXT_NODE) {
                continue;
            }
            if (! $node instanceof DOMElement || in_array($tag = strtolower($node->tagName), self::DROPPED)) {
                $parent->removeChild($node);

                continue;
            }

            static::cleanChildren($node);

            if (in_array($tag, self::ALLOWED)) {
                static::cleanAttributes($node, $tag);

                continue;
            }

            // An h1 would compete with the page title; anything else unknown is unwrapped and its text kept.
            $replacement = $tag === 'h1' ? $node->ownerDocument->createElement('h2') : $node->ownerDocument->createDocumentFragment();
            while ($node->firstChild) {
                $replacement->appendChild($node->firstChild);
            }
            $replacement->hasChildNodes() ? $parent->replaceChild($replacement, $node) : $parent->removeChild($node);
        }
    }

    private static function cleanAttributes(DOMElement $node, string $tag): void
    {
        $href = $tag === 'a' ? trim($node->getAttribute('href')) : '';

        foreach (iterator_to_array($node->attributes) as $attribute) {
            $node->removeAttribute($attribute->name);
        }

        if ($tag !== 'a') {
            return;
        }

        // Links to web pages, email, phone, or a path on this site. No javascript: or data: links.
        if (preg_match('~^(https?://|mailto:|tel:|/(?!/)|#)~i', $href)) {
            $node->setAttribute('href', $href);

            if (Str::startsWith(strtolower($href), 'http') && ! Str::startsWith($href, config('app.url'))) {
                $node->setAttribute('target', '_blank');
                $node->setAttribute('rel', 'noopener');
            }
        }
    }
}
