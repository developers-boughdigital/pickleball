<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Venue;
use App\Support\Area;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * /sitemap.xml and one sitemap per type, built from what's published. The cache
 * key changes whenever a listing is published, hidden, edited or deleted (see
 * DirectoryListing::directoryVersion()), so a new page shows up straight away.
 */
class SitemapController extends Controller
{
    public const TYPES = ['pages', 'locations', 'venues', 'clubs'];

    public function index(): Response
    {
        $lastmod = $this->lastModified();
        $body = view('sitemap.index', [
            'sitemaps' => collect(self::TYPES)->map(fn (string $type) => ['loc' => route('sitemap.type', $type), 'lastmod' => $lastmod]),
        ])->render();

        return $this->xml($body);
    }

    public function show(string $type): Response
    {
        abort_unless(in_array($type, self::TYPES), 404);

        $body = Cache::remember("sitemap:{$type}:{$this->fingerprint()}", now()->addDay(), fn () => view('sitemap.urls', [
            'urls' => match ($type) {
                'pages' => $this->pages(),
                'locations' => $this->locations(),
                'venues' => $this->listings(Venue::class, 'explore.venue'),
                'clubs' => $this->listings(Club::class, 'explore.club'),
            },
        ])->render());

        return $this->xml($body);
    }

    public function robots(): Response
    {
        // Only the live site should be in search results; staging and laptops say keep out.
        $lines = app()->isProduction()
            ? ['User-agent: *', 'Disallow: /admin', 'Disallow: /dashboard', 'Disallow: /install', 'Disallow: /reports', '', 'Sitemap: '.route('sitemap')]
            : ['User-agent: *', 'Disallow: /'];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /** @return Collection<int, array{loc: string, lastmod?: ?string}> */
    private function pages(): Collection
    {
        return collect(['home', 'play', 'explore.clubs', 'explore.venues'])->map(fn (string $route) => ['loc' => route($route), 'lastmod' => $this->lastModified()]);
    }

    /** Every state, city, council and suburb that has something published in it. */
    private function locations(): Collection
    {
        $rows = collect([Venue::class, Club::class])->flatMap(fn (string $model) => $model::query()->visible()->whereNotNull('state')
            ->select('state', 'city', 'council', 'suburb')->selectRaw('max(updated_at) as updated_at')
            ->groupBy('state', 'city', 'council', 'suburb')->toBase()->get());

        $areas = [];
        foreach ($rows as $row) {
            // Each level only exists when the one above it does.
            $levels = [$row->state, $row->city, $row->city ? $row->council : null, $row->city && $row->council ? $row->suburb : null];
            for ($depth = 1; $depth <= 4 && $levels[$depth - 1] !== null; $depth++) {
                $url = Area::make(...array_pad(array_slice($levels, 0, $depth), 4, null))->url();
                $areas[$url] = max($areas[$url] ?? '', (string) $row->updated_at);
            }
        }

        return collect($areas)->map(fn (string $updated, string $url) => ['loc' => $url, 'lastmod' => $this->w3c($updated)])->sortKeys()->values();
    }

    private function listings(string $model, string $route): Collection
    {
        return $model::query()->visible()->orderBy('slug')->get(['slug', 'updated_at'])
            ->map(fn ($listing) => ['loc' => route($route, $listing), 'lastmod' => $listing->updated_at?->toW3cString()]);
    }

    /** The directory version (bumped on every save or delete), plus counts for changes made without model events. */
    private function fingerprint(): string
    {
        return md5(Venue::directoryVersion().collect([Venue::class, Club::class])->map(fn (string $model) => $model::withTrashed()
            ->selectRaw("count(*) as total, sum(case when status = 'published' and deleted_at is null then 1 else 0 end) as live, max(updated_at) as updated, max(deleted_at) as deleted")
            ->toBase()->first())->toJson());
    }

    private function lastModified(): ?string
    {
        return $this->w3c(collect([Venue::class, Club::class])->map(fn (string $model) => (string) $model::query()->visible()->max('updated_at'))->max());
    }

    private function w3c(?string $timestamp): ?string
    {
        return $timestamp ? Carbon::parse($timestamp)->toW3cString() : null;
    }

    private function xml(string $body): Response
    {
        return response($body, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
