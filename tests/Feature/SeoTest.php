<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\PageMeta;
use App\Models\Role;
use App\Models\User;
use App\Models\Venue;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase;

    private Venue $venue;

    private Club $club;

    protected function setUp(): void
    {
        parent::setUp();

        $live = ['record_status' => 'checked', 'status' => 'published'];
        $this->venue = Venue::create(['name' => 'Pickledome', 'street_address' => '56 Coyne Street', 'suburb' => 'Fadden', 'state' => 'ACT', 'postcode' => '2904', 'city' => 'Canberra', 'council' => 'Tuggeranong',
            'latitude' => -35.41, 'longitude' => 149.13, 'court_count' => 11, 'lights' => true, 'toilets' => true, 'phone' => '02 6100 0000', 'website' => 'https://pickledome.example', ...$live]);
        $this->club = Club::create(['name' => 'Pickleball ACT', 'suburb' => 'Belconnen', 'state' => 'ACT', 'postcode' => '2617', 'city' => 'Canberra', 'council' => 'Belconnen',
            'short_description' => 'Social and competitive play across Canberra.', 'email' => 'info@pickleballact.org', 'instagram' => 'https://instagram.com/pickleballact', 'year_established' => 2018, ...$live]);
        $this->club->venues()->attach($this->venue, ['is_primary' => true]);
        Venue::create(['name' => 'Draft Courts', 'state' => 'ACT', 'suburb' => 'Fadden', 'city' => 'Canberra', 'council' => 'Tuggeranong']);
    }

    /** The page's JSON-LD graph, keyed by @type. */
    private function graph(TestResponse $response): array
    {
        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $response->getContent(), $match);
        $this->assertNotEmpty($match, 'No JSON-LD on the page');
        $data = json_decode($match[1], true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('https://schema.org', $data['@context']);

        return collect($data['@graph'])->keyBy('@type')->all();
    }

    public function test_every_public_page_has_meta_and_structured_data(): void
    {
        foreach (['/', '/play', '/play/act', '/play/act/canberra/tuggeranong', '/explore/clubs', '/explore/venues', '/explore/coaches', '/explore/clubs/pickleball-act', '/explore/venues/pickledome', '/login', '/register'] as $path) {
            $response = $this->get($path)->assertOk();
            $html = $response->getContent();

            $this->assertSame(1, substr_count($html, '<h1'), "{$path} should have one h1");
            foreach (['<meta name="description"', '<link rel="canonical"', '<meta property="og:title"', '<meta property="og:image"', '<meta name="twitter:card"'] as $tag) {
                $this->assertStringContainsString($tag, $html, "{$path} is missing {$tag}");
            }
            $graph = $this->graph($response);
            $this->assertArrayHasKey('Organization', $graph, $path);
            $this->assertArrayHasKey('WebSite', $graph, $path);
        }
    }

    public function test_venue_page_is_a_sports_location_matching_the_page(): void
    {
        $response = $this->get('/explore/venues/pickledome')
            ->assertSee('<title>Pickledome pickleball courts, Fadden · '.config('app.name').'</title>', false)
            ->assertSee('<link rel="canonical" href="'.url('/explore/venues/pickledome').'">', false)
            ->assertSee('<meta name="robots" content="index, follow, max-image-preview:large">', false)
            ->assertSeeInOrder(['Home', 'Venues', 'Pickledome']);
        $graph = $this->graph($response);

        $place = $graph['SportsActivityLocation'];
        $this->assertSame('Pickledome', $place['name']);
        $this->assertSame(['@type' => 'PostalAddress', 'streetAddress' => '56 Coyne Street', 'addressLocality' => 'Fadden', 'addressRegion' => 'ACT', 'postalCode' => '2904', 'addressCountry' => 'AU'], $place['address']);
        $this->assertSame(-35.41, $place['geo']['latitude']);
        $this->assertSame(['Lights', 'Toilets'], array_column($place['amenityFeature'], 'name'));
        $this->assertSame('02 6100 0000', $place['telephone']);
        $this->assertSame(['@id' => $place['@id']], $graph['WebPage']['mainEntity']);
        $this->assertSame(['Home', 'Venues', 'Pickledome'], array_column($graph['BreadcrumbList']['itemListElement'], 'name'));
    }

    public function test_club_page_is_a_sports_club(): void
    {
        $club = $this->graph($this->get('/explore/clubs/pickleball-act'))['SportsClub'];

        $this->assertSame('Pickleball ACT', $club['name']);
        $this->assertSame('Social and competitive play across Canberra.', $club['description']);
        $this->assertSame('info@pickleballact.org', $club['email']);
        $this->assertSame('2018', $club['foundingDate']);
        $this->assertSame(['https://instagram.com/pickleballact'], $club['sameAs']);
        $this->assertSame('Pickledome', $club['containsPlace'][0]['name']);
        $this->assertArrayNotHasKey('telephone', $club); // not on the page, so not in the data
    }

    public function test_location_and_list_pages_are_collections(): void
    {
        $graph = $this->graph($this->get('/play/act/canberra/tuggeranong'));
        $this->assertSame('CollectionPage', $graph['CollectionPage']['@type']);
        $this->assertSame(['Pickledome'], array_column($graph['ItemList']['itemListElement'], 'name'));
        $this->assertArrayHasKey('FAQPage', $graph);
        $this->assertSame(['Home', 'Play', 'Australian Capital Territory', 'Canberra', 'Tuggeranong'], array_column($graph['BreadcrumbList']['itemListElement'], 'name'));

        $this->get('/explore/clubs?state=ACT')
            ->assertSee('<link rel="canonical" href="'.url('/explore/clubs?state=ACT').'">', false)
            ->assertSee('content="index, follow', false);
        // Searches and filters are for people; the area page itself is what gets indexed.
        $this->get('/explore/clubs?state=ACT&beginners=1')
            ->assertSee('<link rel="canonical" href="'.url('/explore/clubs?state=ACT').'">', false)
            ->assertSee('<meta name="robots" content="noindex, follow">', false);
    }

    public function test_drafts_sample_coaches_and_sign_in_stay_out_of_search(): void
    {
        foreach (['/explore/coaches', '/login', '/register'] as $path) {
            $this->get($path)->assertSee('<meta name="robots" content="noindex, follow">', false);
        }

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->actingAs(User::factory()->create()->assignRole(Role::SUPER_ADMIN));
        $this->get('/explore/venues/draft-courts?drafts=1')->assertOk()->assertSee('<meta name="robots" content="noindex, follow">', false);
    }

    public function test_titles_and_descriptions_can_be_edited(): void
    {
        $this->venue->update(['meta_title' => 'Canberra’s biggest indoor pickleball centre', 'meta_description' => 'Eleven courts in Fadden.']);
        $this->get('/explore/venues/pickledome')
            ->assertSee('<title>Canberra’s biggest indoor pickleball centre · '.config('app.name').'</title>', false)
            ->assertSee('<meta name="description" content="Eleven courts in Fadden.">', false);

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->actingAs(User::factory()->create()->assignRole(Role::SUPER_ADMIN));

        $this->post('/admin/seo', ['path' => url('/play/act/'), 'title' => 'Play pickleball in the ACT', 'description' => 'Every court in Canberra.'])->assertSessionHas('status');
        $this->assertDatabaseHas('page_meta', ['path' => 'play/act']);
        $this->get('/play/act')
            ->assertSee('<title>Play pickleball in the ACT · '.config('app.name').'</title>', false)
            ->assertSee('<meta property="og:description" content="Every court in Canberra.">', false);

        $this->post('/admin/seo', ['path' => '/play/act'])->assertSessionHasErrors('path');
        $this->post('/admin/seo', ['path' => '/admin/venues'])->assertSessionHasErrors('path');

        $entry = PageMeta::firstWhere('path', 'play/act');
        $this->put("/admin/seo/{$entry->id}", ['path' => '/play/act', 'title' => '', 'noindex' => 1])->assertSessionHas('status');
        $this->get('/play/act')->assertSee('<title>Pickleball in Australian Capital Territory', false)->assertSee('content="noindex, follow"', false);

        $this->delete("/admin/seo/{$entry->id}");
        $this->get('/play/act')->assertSee('content="index, follow', false);
        $this->get('/admin/seo')->assertOk()->assertSee('No custom entries');

        $this->get("/admin/venues/{$this->venue->slug}/edit")->assertSee('Search and sharing')->assertSee('biggest indoor pickleball centre');
    }

    public function test_page_overrides_survive_a_real_cache(): void
    {
        // The array cache used in tests doesn't serialize; the file and database ones do, and refuse objects.
        config(['cache.default' => 'file']);
        \Illuminate\Support\Facades\Cache::forget('page_meta');
        PageMeta::create(['path' => '/', 'title' => 'Cached home title']);

        $this->get('/')->assertOk()->assertSee('<title>Cached home title', false);
        $this->get('/')->assertOk()->assertSee('<title>Cached home title', false); // second read comes from the cache
        $this->get('/play')->assertOk();

        \Illuminate\Support\Facades\Cache::forget('page_meta');
    }

    public function test_sitemap_lists_what_is_published_and_updates(): void
    {
        $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('<loc>'.url('/sitemaps/venues.xml').'</loc>', false)
            ->assertSee('<loc>'.url('/sitemaps/locations.xml').'</loc>', false);

        $this->get('/sitemaps/venues.xml')->assertSee(url('/explore/venues/pickledome'))->assertDontSee('draft-courts');
        $this->get('/sitemaps/clubs.xml')->assertSee(url('/explore/clubs/pickleball-act'));
        $this->get('/sitemaps/pages.xml')->assertSee('<loc>'.url('/').'</loc>', false)->assertDontSee('coaches');
        $this->get('/sitemaps/locations.xml')
            ->assertSee('<loc>'.url('/play/act').'</loc>', false)
            ->assertSee('<loc>'.url('/play/act/canberra').'</loc>', false)
            ->assertSee('<loc>'.url('/play/act/canberra/tuggeranong/fadden').'</loc>', false)
            ->assertSee('<loc>'.url('/play/act/canberra/belconnen/belconnen').'</loc>', false);
        $this->get('/sitemaps/nothing.xml')->assertNotFound();

        // Publishing a venue puts it in the next request's sitemap, cache or not.
        Venue::firstWhere('name', 'Draft Courts')->update(['record_status' => 'checked', 'status' => 'published']);
        $this->get('/sitemaps/venues.xml')->assertSee('draft-courts');
        $this->venue->update(['status' => 'hidden']);
        $this->get('/sitemaps/venues.xml')->assertDontSee('pickledome');
    }

    public function test_robots_txt(): void
    {
        // Tests run outside production, which must never be indexed.
        $this->get('/robots.txt')->assertOk()->assertSee("User-agent: *\nDisallow: /", false)->assertDontSee('Sitemap:');

        $this->app['env'] = 'production';
        $this->get('/robots.txt')->assertSee('Disallow: /admin')->assertSee('Sitemap: '.url('/sitemap.xml'))->assertDontSee("Disallow: /\n", false);
    }
}
