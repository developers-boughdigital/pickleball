<?php

namespace App\Support\Seo;

use App\Models\Club;
use App\Models\Venue;
use Illuminate\Support\Collection;

/**
 * schema.org nodes for JSON-LD, built from the site's own records so the
 * structured data always says what the page shows. The site layout wraps
 * them in one @graph with the Organization, WebSite, WebPage and breadcrumbs.
 */
final class Schema
{
    public const CONTEXT = 'https://schema.org';

    public static function organizationId(): string
    {
        return route('home').'#organization';
    }

    public static function websiteId(): string
    {
        return route('home').'#website';
    }

    public static function organization(): array
    {
        return array_filter([
            '@type' => 'Organization',
            '@id' => self::organizationId(),
            'name' => config('app.name'),
            'url' => route('home'),
            'logo' => asset(config('app.logo') ?: Seo::DEFAULT_IMAGE),
            'description' => 'Pickleball clubs, courts and coaches across Australia.',
            'areaServed' => ['@type' => 'Country', 'name' => 'Australia'],
        ]);
    }

    /** With a search box for Google (the club search takes ?q=). */
    public static function website(): array
    {
        return [
            '@type' => 'WebSite',
            '@id' => self::websiteId(),
            'name' => config('app.name'),
            'url' => route('home'),
            'inLanguage' => 'en-AU',
            'publisher' => ['@id' => self::organizationId()],
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => ['@type' => 'EntryPoint', 'urlTemplate' => route('explore.clubs').'?q={search_term_string}'],
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    /** @param list<array{0: string, 1: string}> $crumbs [name, url] from the top down */
    public static function breadcrumbs(array $crumbs, string $pageUrl): array
    {
        return [
            '@type' => 'BreadcrumbList',
            '@id' => $pageUrl.'#breadcrumb',
            'itemListElement' => collect($crumbs)->values()->map(fn (array $crumb, int $i) => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $crumb[0],
                'item' => $crumb[1],
            ])->all(),
        ];
    }

    /**
     * @param  iterable<array{name: string, url: string}>  $items
     */
    public static function itemList(string $name, iterable $items): array
    {
        return [
            '@type' => 'ItemList',
            'name' => $name,
            'itemListElement' => collect($items)->values()->map(fn (array $item, int $i) => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'url' => $item['url'],
                'name' => $item['name'],
            ])->all(),
        ];
    }

    /** @param iterable<array{0: string, 1: string}> $questions [question, answer] */
    public static function faq(iterable $questions): array
    {
        return [
            '@type' => 'FAQPage',
            'mainEntity' => collect($questions)->values()->map(fn (array $qa) => [
                '@type' => 'Question',
                'name' => $qa[0],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $qa[1]],
            ])->all(),
        ];
    }

    /** A venue is a SportsActivityLocation, schema.org's LocalBusiness for places to play sport. */
    public static function venue(Venue $venue, string $url): array
    {
        return self::clean([
            '@type' => 'SportsActivityLocation',
            '@id' => $url.'#place',
            'name' => $venue->name,
            'url' => $url,
            'description' => $venue->court_count ? "{$venue->court_count} pickleball ".str('court')->plural($venue->court_count).($venue->suburb ? " in {$venue->suburb}" : '').'.' : null,
            'address' => self::address($venue->street_address, $venue->suburb, $venue->state, $venue->postcode),
            'geo' => $venue->latitude !== null && $venue->longitude !== null
                ? ['@type' => 'GeoCoordinates', 'latitude' => $venue->latitude, 'longitude' => $venue->longitude]
                : null,
            'telephone' => $venue->phone,
            'sameAs' => array_values(array_filter([$venue->website])),
            'isAccessibleForFree' => $venue->free_to_use === true ? true : null,
            'publicAccess' => $venue->booking_required === false ? true : null,
            'amenityFeature' => collect(Venue::FACILITIES)
                ->filter(fn (string $label, string $key) => $venue->getAttribute($key) === true)
                ->map(fn (string $label) => ['@type' => 'LocationFeatureSpecification', 'name' => $label, 'value' => true])
                ->values()->all(),
        ]);
    }

    /** @param Collection<int, Venue> $venues the venues shown on the club page */
    public static function club(Club $club, string $url, Collection $venues, callable $venueUrl): array
    {
        return self::clean([
            '@type' => 'SportsClub',
            '@id' => $url.'#club',
            'name' => $club->name,
            'url' => $url,
            'description' => $club->short_description,
            'address' => self::address(null, $club->suburb, $club->state, $club->postcode),
            'email' => $club->email,
            'telephone' => $club->phone,
            'foundingDate' => $club->year_established ? (string) $club->year_established : null,
            'sameAs' => array_values(array_filter(array_map(fn (string $key) => $club->getAttribute($key), array_keys(Club::SOCIALS)))),
            'containsPlace' => $venues->map(fn (Venue $venue) => self::clean([
                '@type' => 'SportsActivityLocation',
                'name' => $venue->name,
                'url' => $venueUrl($venue),
                'address' => self::address($venue->street_address, $venue->suburb, $venue->state, $venue->postcode),
            ]))->values()->all(),
        ]);
    }

    private static function address(?string $street, ?string $suburb, ?string $state, ?string $postcode): ?array
    {
        if (! $suburb && ! $state) {
            return null;
        }

        return self::clean([
            '@type' => 'PostalAddress',
            'streetAddress' => $street,
            'addressLocality' => $suburb,
            'addressRegion' => $state,
            'postalCode' => $postcode,
            'addressCountry' => 'AU',
        ]);
    }

    /** Leaves out empty values, so nothing is claimed that the page doesn't have. */
    private static function clean(array $node): array
    {
        return array_filter($node, fn ($value) => $value !== null && $value !== '' && $value !== []);
    }
}
