<?php

namespace App\Support\Seo;

use App\Models\PageMeta;

/**
 * What the public layout puts in <head>: the title and description (an
 * override from Admin › SEO wins over what the page writes from its data),
 * canonical, robots, Open Graph and the JSON-LD graph.
 */
final class Seo
{
    /** 1200×630 share image used when a page has none of its own. */
    public const DEFAULT_IMAGE = 'images/og-default.png';

    public const FALLBACK_DESCRIPTION = 'Find pickleball clubs, courts and coaches across Australia: sessions, levels, fees and where to play.';

    public readonly string $title;

    public readonly string $fullTitle;

    public readonly string $description;

    public readonly string $canonical;

    public readonly bool $noindex;

    public readonly string $image;

    /**
     * @param  list<array{0: string, 1: string}>  $breadcrumbs  [name, url], Home first
     * @param  list<array>  $schema  the page's own nodes; the first is what the page is about
     */
    public function __construct(
        ?string $title,
        ?string $description,
        ?string $canonical,
        bool $noindex,
        ?string $image,
        public readonly string $pageType = 'WebPage',
        public readonly array $breadcrumbs = [],
        public readonly array $schema = [],
    ) {
        $override = PageMeta::forPath(request()->path());
        $app = config('app.name');

        $this->title = $override?->title ?: ($title ?: $app);
        $this->fullTitle = str_contains($this->title, $app) ? $this->title : "{$this->title} · {$app}";
        $this->description = $override?->description ?: ($description ?: self::FALLBACK_DESCRIPTION);
        $this->canonical = $canonical ?: url()->current();
        $this->noindex = $noindex || (bool) $override?->noindex;
        $this->image = $image ?: asset(self::DEFAULT_IMAGE);
    }

    /** One JSON-LD document for the whole page. */
    public function jsonLd(): string
    {
        $about = $this->schema[0]['@id'] ?? null;

        $page = array_filter([
            '@type' => $this->pageType,
            '@id' => $this->canonical.'#webpage',
            'url' => $this->canonical,
            'name' => $this->title,
            'description' => $this->description,
            'inLanguage' => 'en-AU',
            'isPartOf' => ['@id' => Schema::websiteId()],
            'primaryImageOfPage' => ['@type' => 'ImageObject', 'url' => $this->image],
            'breadcrumb' => $this->breadcrumbs ? ['@id' => $this->canonical.'#breadcrumb'] : null,
            'mainEntity' => $about ? ['@id' => $about] : null,
        ]);

        $graph = [
            Schema::organization(),
            Schema::website(),
            $page,
            ...($this->breadcrumbs ? [Schema::breadcrumbs($this->breadcrumbs, $this->canonical)] : []),
            ...$this->schema,
        ];

        return json_encode(['@context' => Schema::CONTEXT, '@graph' => $graph], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
    }
}
