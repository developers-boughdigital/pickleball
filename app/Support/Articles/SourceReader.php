<?php

namespace App\Support\Articles;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Psr\Http\Message\UriInterface;
use RuntimeException;
use Throwable;

/** Fetches a source URL an editor pasted and boils the page down to its text for Claude to read. */
class SourceReader
{
    /** Long enough for a full feature article; a page beyond this is cut and the editor is told. */
    public const MAX_CHARS = 40000;

    /**
     * @return array{url: string, title: ?string, text: string, cut: bool, error: ?string}
     */
    public function read(string $url): array
    {
        $source = ['url' => $url, 'title' => null, 'text' => '', 'cut' => false, 'error' => null];

        try {
            if (! $this->isPublic($url)) {
                return [...$source, 'error' => 'That address isn’t a public web page.'];
            }

            $response = Http::timeout(20)
                ->withUserAgent('Mozilla/5.0 (compatible; ArticleResearch/1.0)')
                ->withOptions(['allow_redirects' => [
                    'max' => 4,
                    // A public page mustn't be able to bounce us to something inside our own network.
                    'on_redirect' => function ($request, $response, UriInterface $to) {
                        $this->isPublic((string) $to) || throw new RuntimeException('Redirected somewhere private.');
                    },
                ]])
                ->get($url);

            if (! $response->successful()) {
                return [...$source, 'error' => "The site answered with an error ({$response->status()})."];
            }
            if (! Str::contains($response->header('Content-Type'), ['html', 'text/plain'])) {
                return [...$source, 'error' => 'That link isn’t a web page (it may be a PDF, image or video).'];
            }

            [$title, $text] = static::extract($response->body());
        } catch (Throwable) {
            return [...$source, 'error' => 'We couldn’t reach that page.'];
        }

        if (Str::length($text) < 200) {
            return [...$source, 'title' => $title, 'error' => 'We found almost no text on that page. It may need a login or be built with scripts.'];
        }

        return [...$source, 'title' => $title, 'text' => Str::limit($text, self::MAX_CHARS, ''), 'cut' => Str::length($text) > self::MAX_CHARS];
    }

    /**
     * The page title and its readable text: the <article> or <main> if there is
     * one, without menus, footers, scripts and forms.
     *
     * @return array{0: ?string, 1: string}
     */
    public static function extract(string $html): array
    {
        if (! Str::contains($html, '<')) {
            return [null, trim($html)];
        }

        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8"?>'.$html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        $xpath = new DOMXPath($dom);

        $title = trim((string) $xpath->evaluate('string(//title)')) ?: null;

        foreach (iterator_to_array($xpath->query('//script|//style|//noscript|//nav|//header|//footer|//aside|//form|//svg|//iframe')) as $junk) {
            $junk->parentNode?->removeChild($junk);
        }

        $main = $xpath->query('//article')->item(0) ?? $xpath->query('//main')->item(0) ?? $xpath->query('//body')->item(0);

        // Keep paragraph breaks: a newline after each block, then tidy the spaces.
        foreach ($main ? iterator_to_array($xpath->query('.//p|.//h1|.//h2|.//h3|.//h4|.//li|.//br|.//tr|.//div', $main)) : [] as $block) {
            $block->appendChild($dom->createTextNode("\n"));
        }

        $text = preg_replace(['/[ \t\x{00A0}]+/u', '/\s*\n\s*/'], [' ', "\n"], (string) $main?->textContent);

        return [$title, trim($text)];
    }

    /** http or https, and a host that resolves only to public addresses. */
    protected function isPublic(string $url): bool
    {
        $parts = parse_url($url);
        if (! in_array($parts['scheme'] ?? '', ['http', 'https']) || blank($parts['host'] ?? null)) {
            return false;
        }

        $host = trim($parts['host'], '[]');
        $addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);

        return $addresses !== [] && collect($addresses)->every(
            fn (string $ip) => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false,
        );
    }
}
