<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Club;
use App\Models\ListingReport;
use App\Models\Locality;
use App\Models\Role;
use App\Models\User;
use App\Models\Venue;
use App\Support\Geography;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $now = now();
        Locality::insert(collect([
            ['3088', 'Greensborough', 'VIC', 'Banyule', 'Melbourne', -37.7040, 145.1000],
            ['3088', 'Briar Hill', 'VIC', 'Banyule', 'Melbourne', -37.7080, 145.1150],
            ['3084', 'Heidelberg', 'VIC', 'Banyule', 'Melbourne', -37.7560, 145.0670],
            ['3070', 'Northcote', 'VIC', 'Darebin', 'Melbourne', -37.7690, 144.9990],
            ['3097', 'Kangaroo Ground', 'VIC', 'Nillumbik', 'Melbourne', -37.6900, 145.2200],
            ['3220', 'Geelong', 'VIC', 'Greater Geelong', 'Geelong', -38.1470, 144.3600],
        ])->map(fn ($row) => array_combine(['postcode', 'suburb', 'state', 'council', 'city', 'latitude', 'longitude'], $row) + ['created_at' => $now, 'updated_at' => $now])->all());

        $live = ['record_status' => 'checked', 'status' => 'published'];
        $leisure = Venue::create(['name' => 'Greensborough Leisure', 'suburb' => 'Greensborough', 'postcode' => '3088', 'state' => 'VIC', 'latitude' => -37.7030, 'longitude' => 145.1010, 'indoor_outdoor' => 'indoor', 'court_count' => 6, ...$live]);
        Venue::create(['name' => 'Briar Hill Courts', 'suburb' => 'Briar Hill', 'postcode' => '3088', 'state' => 'VIC', 'latitude' => -37.7090, 'longitude' => 145.1160, 'indoor_outdoor' => 'outdoor', ...$live]);
        Venue::create(['name' => 'Heidelberg Hall', 'suburb' => 'Heidelberg', 'postcode' => '3084', 'state' => 'VIC', 'latitude' => -37.7560, 'longitude' => 145.0670, ...$live]);
        Venue::create(['name' => 'Northcote Stadium', 'suburb' => 'Northcote', 'postcode' => '3070', 'state' => 'VIC', 'latitude' => -37.7690, 'longitude' => 144.9990, ...$live]);
        Venue::create(['name' => 'Geelong Arena', 'suburb' => 'Geelong', 'postcode' => '3220', 'state' => 'VIC', 'latitude' => -38.1470, 'longitude' => 144.3600, ...$live]);
        Venue::create(['name' => 'Balwyn Typo Courts', 'suburb' => 'Balwyn North', 'postcode' => '3014', 'state' => 'VIC', ...$live]);
        Venue::create(['name' => 'Secret Greensborough Draft', 'suburb' => 'Greensborough', 'postcode' => '3088', 'state' => 'VIC']);

        Club::create(['name' => 'Banyule Pickleball Club', 'suburb' => 'Greensborough', 'postcode' => '3088', 'state' => 'VIC', 'levels' => ['beginner'], 'session_times_text' => 'Mon to Wed 7pm and Sat mornings', ...$live])
            ->venues()->attach($leisure, ['is_primary' => true]);
        Club::create(['name' => 'Darebin Dinkers', 'suburb' => 'Northcote', 'postcode' => '3070', 'state' => 'VIC', ...$live]);
    }

    public function test_listings_get_city_and_council_from_their_postcode(): void
    {
        $venue = Venue::firstWhere('name', 'Greensborough Leisure');
        $this->assertSame(['Melbourne', 'Banyule'], [$venue->city, $venue->council]);

        // A typo'd postcode matches nothing; what someone typed is never replaced.
        $this->assertNull(Venue::firstWhere('name', 'Balwyn Typo Courts')->city);
        $typed = Venue::create(['name' => 'Typed', 'suburb' => 'Heidelberg', 'postcode' => '3084', 'state' => 'VIC', 'council' => 'City of Banyule']);
        $this->assertSame(['Melbourne', 'City of Banyule'], [$typed->city, $typed->council]);
    }

    public function test_hub_and_state_pages(): void
    {
        $this->get('/play')->assertOk()
            ->assertSee('Pickleball in Australia')
            ->assertSeeInOrder(['Browse by place', 'State'])
            ->assertSee('href="'.url('/play/vic').'"', false)
            ->assertDontSee('Secret Greensborough Draft');

        $this->get('/play/vic')->assertOk()
            ->assertSee('Pickleball in Victoria')
            ->assertSee('City or region')
            ->assertSee('href="'.url('/play/vic/melbourne').'"', false)
            ->assertSee('href="'.url('/play/vic/geelong').'"', false)
            ->assertSee('All of Australia');
    }

    public function test_city_and_council_pages(): void
    {
        $this->get('/play/vic/melbourne')->assertOk()
            ->assertSee('Pickleball in Melbourne')
            ->assertSee('Council area')
            ->assertSee('href="'.url('/play/vic/melbourne/banyule').'"', false)
            ->assertSee('Northcote Stadium')
            ->assertDontSee('Geelong Arena');

        $this->get('/play/vic/melbourne/banyule')->assertOk()
            ->assertSeeInOrder(['Home', 'Play', 'Victoria', 'Melbourne', 'Banyule'])
            ->assertSeeInOrder(['Council area', 'Banyule', 'Suburb', 'Greensborough'])
            ->assertSee('Heidelberg Hall')
            ->assertDontSee('Northcote Stadium')
            ->assertSee('Distance')
            ->assertSee('Nearby areas')
            ->assertSee('href="'.url('/play/vic/melbourne/darebin').'"', false)
            ->assertSee('All of Melbourne');
    }

    public function test_suburb_page_takes_in_venues_within_5_km(): void
    {
        $this->get('/play/vic/melbourne/banyule/greensborough')->assertOk()
            ->assertSee('Pickleball in Greensborough')
            ->assertSee('within 5 km of Greensborough')
            ->assertSee('Greensborough Leisure')
            ->assertSee('Briar Hill Courts') // about 1.8 km away, in the next suburb
            ->assertSeeInOrder(['Courts near Greensborough', 'Heidelberg Hall']) // about 6 km, so only offered as nearby
            ->assertSee('Plays</span> Mon, Tue, Wed and Sat', false)
            ->assertSee('<link rel="canonical" href="'.url('/play/vic/melbourne/banyule/greensborough').'">', false)
            ->assertSee('"@type":"BreadcrumbList"', false)
            ->assertSee('"@type":"FAQPage"', false)
            ->assertSee('"@type":"ItemList"', false);
    }

    public function test_thin_area_shows_the_nearest_courts_and_the_form(): void
    {
        // Listed in the suburb lookup, but nothing plays there yet.
        $this->get('/play/vic/melbourne/nillumbik/kangaroo-ground')->assertOk()
            ->assertSee('Pickleball in Kangaroo Ground')
            ->assertSee('Courts near Kangaroo Ground')
            ->assertSeeInOrder(['Courts near Kangaroo Ground', 'Briar Hill Courts', 'Greensborough Leisure'])
            ->assertSee('Know a court we’re missing in Kangaroo Ground?', false)
            ->assertSee('<details class="site-card p-6 sm:p-8"  open', false);
    }

    public function test_unknown_places_are_not_found(): void
    {
        $this->get('/play/xx')->assertNotFound();
        $this->get('/play/vic/sydney')->assertNotFound();
        $this->get('/play/vic/melbourne/banyule/nowhere')->assertNotFound();
        $this->get('/play/nsw/melbourne')->assertNotFound();
    }

    public function test_visitors_can_tell_us_about_a_court(): void
    {
        $this->from('/play/vic/melbourne/nillumbik/kangaroo-ground')
            ->post('/reports', ['kind' => 'new_court', 'state' => 'VIC', 'area' => 'Kangaroo Ground', 'details' => '2 courts at the oval', 'email' => 'pat@example.test'])
            ->assertRedirect('/play/vic/melbourne/nillumbik/kangaroo-ground#tell-us')
            ->assertSessionHas('report_sent');
        $this->assertDatabaseHas('listing_reports', ['kind' => 'new_court', 'area' => 'Kangaroo Ground', 'status' => 'open', 'email' => 'pat@example.test']);

        $this->post('/reports', ['kind' => 'new_court', 'details' => ''])->assertSessionHasErrors('details');
        // The hidden field only bots fill in.
        $this->post('/reports', ['kind' => 'new_court', 'details' => 'Buy cheap paddles', 'website' => 'spam.example'])->assertSessionHas('report_sent');
        $this->assertSame(1, ListingReport::count());

        foreach (range(1, 3) as $i) {
            $this->post('/reports', ['kind' => 'new_court', 'details' => "Court number {$i}"]);
        }
        $this->post('/reports', ['kind' => 'new_court', 'details' => 'One too many'])->assertStatus(429);
    }

    public function test_lists_filter_by_location_level(): void
    {
        $this->get('/explore/venues?state=VIC&council=Banyule')
            ->assertSee('Heidelberg Hall')->assertDontSee('Northcote Stadium')
            ->assertSeeInOrder(['Play', 'Victoria', 'Banyule']);
        $this->get('/explore/clubs?state=VIC&city=Melbourne&council=Darebin')->assertSee('Darebin Dinkers')->assertDontSee('Banyule Pickleball Club');
    }

    public function test_home_shows_popular_locations(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('Popular locations')
            ->assertSeeInOrder(['Pickleball in Melbourne', 'Pickleball in Geelong'])
            ->assertSee('href="'.url('/play/vic/melbourne').'"', false);
    }

    public function test_admin_locations_page_and_fill(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create()->assignRole(Role::SUPER_ADMIN);

        // Left blank on purpose, the way old imports did.
        $blank = Venue::firstWhere('name', 'Northcote Stadium');
        $blank->forceFill(['city' => null, 'council' => null])->saveQuietly();

        $this->actingAs($admin)->get('/admin/locations')->assertOk()
            ->assertSee('Venues with all four levels')
            ->assertSee('Balwyn Typo Courts')
            ->assertSee('Northcote Stadium')
            ->assertSee('Postcodes not in the lookup')
            ->assertSee('3014');

        $this->post('/admin/locations/fill')->assertRedirect()->assertSessionHas('status', 'Filled city and council on 1 venues and 0 clubs from their postcodes.');
        $this->assertSame('Darebin', $blank->fresh()->council);
        $this->assertTrue(ActivityLog::where('action', 'directory.locations_filled')->exists());

        $this->get('/dashboard')->assertSee('Location pages ready')->assertSee('Reports to check');

        $viewer = User::factory()->create()->assignRole('Viewer');
        $this->actingAs($viewer)->get('/admin/locations')->assertOk();
        $this->post('/admin/locations/fill')->assertForbidden();
    }

    public function test_search_box_goes_to_the_place_typed(): void
    {
        foreach ([
            'vic' => '/play/vic',
            'Victoria' => '/play/vic',
            'melbourne' => '/play/vic/melbourne',
            'Banyule' => '/play/vic/melbourne/banyule',
            'greensborough vic' => '/play/vic/melbourne/banyule/greensborough',
            '3084' => '/play/vic/melbourne/banyule/heidelberg',
            '' => '/play',
        ] as $typed => $page) {
            $this->get('/play/find?q='.urlencode($typed))->assertRedirect(url($page));
        }

        // Not a place: search the clubs for it.
        $this->get('/play/find?q=Dinkers')->assertRedirect(route('explore.clubs', ['q' => 'Dinkers']));
        $this->get('/play/find?lat=-37.7&lng=145.1')->assertRedirect(route('explore.clubs', ['lat' => '-37.7', 'lng' => '145.1']));

        $this->get('/play')->assertSeeInOrder(['Browse by place', 'State', 'All', 'Victoria']);
        // On a city page: the state and city tabs are chosen, and the councils are the next row.
        $html = $this->get('/play/vic/melbourne')
            ->assertSeeInOrder(['State', 'Victoria', 'City or region', 'Melbourne', 'Council area', 'All', 'Banyule', 'Darebin'])
            ->getContent();
        $this->assertMatchesRegularExpression('#href="'.preg_quote(url('/play/vic'), '#').'" data-keep-scroll="browse"\s+aria-current="true"#', $html);
        $this->assertMatchesRegularExpression('#href="'.preg_quote(url('/play/vic/melbourne'), '#').'" data-keep-scroll="browse"\s+aria-current="page"#', $html);
    }

    public function test_listings_link_to_their_location_pages(): void
    {
        $leisure = Venue::firstWhere('name', 'Greensborough Leisure');
        $suburbPage = url('/play/vic/melbourne/banyule/greensborough');

        $this->get("/explore/venues/{$leisure->slug}")->assertOk()
            ->assertSeeInOrder(['More places to play in', 'Victoria', 'Melbourne', 'Banyule', 'Greensborough'])
            ->assertSee('href="'.$suburbPage.'"', false)
            ->assertSee('href="'.url('/play/vic/melbourne').'"', false);
        $this->get('/explore/clubs/banyule-pickleball-club')->assertSee('href="'.$suburbPage.'"', false);

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->actingAs(User::factory()->create()->assignRole(Role::SUPER_ADMIN));
        $this->get("/admin/venues/{$leisure->slug}")->assertSee('Location pages')->assertSee('/play/vic/melbourne/banyule/greensborough');

        // A level missing: say where it stops and how to fix it.
        $leisure->forceFill(['council' => null])->saveQuietly();
        $this->get("/admin/venues/{$leisure->slug}")->assertSee('Missing council, so it stops at the city page');
    }

    public function test_fill_loads_an_empty_lookup_from_the_shipped_file(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Locality::query()->delete();

        $this->actingAs(User::factory()->create()->assignRole(Role::SUPER_ADMIN))
            ->post('/admin/locations/fill')->assertSessionHas('status', fn (string $status) => str_starts_with($status, 'Loaded '));
        $this->assertGreaterThan(10000, Locality::count());
    }

    public function test_dashboard_still_opens_when_the_database_is_behind_the_code(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create()->assignRole(Role::SUPER_ADMIN);

        // What a server looks like when the new code is deployed but not migrated.
        \Illuminate\Support\Facades\Schema::drop('listing_reports');
        \Illuminate\Support\Facades\DB::table('migrations')->where('migration', '2026_11_10_090000_create_localities_table')->delete();

        $this->actingAs($admin)->get('/dashboard')->assertOk()
            ->assertSee('1 update behind the code')
            ->assertSee(route('admin.database'))
            ->assertDontSee('Reports to check');
        $this->get('/admin/database')->assertOk();
    }

    public function test_admin_resolves_reports(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $report = ListingReport::create(['kind' => 'new_court', 'area' => 'Kangaroo Ground', 'state' => 'VIC', 'details' => '2 courts at the oval']);

        $this->actingAs(User::factory()->create()->assignRole(Role::SUPER_ADMIN));
        $this->get('/admin/reports')->assertOk()->assertSee('2 courts at the oval')->assertSee('Mark resolved');
        $this->patch("/admin/reports/{$report->id}", ['status' => 'resolved'])->assertRedirect();

        $this->assertSame('resolved', $report->fresh()->status);
        $this->get('/admin/reports')->assertDontSee('2 courts at the oval');
        $this->get('/admin/reports?status=resolved')->assertSee('2 courts at the oval')->assertSee('Reopen');
    }

    public function test_locality_import_maps_regions_and_councils(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'loc');
        file_put_contents($file, implode("\n", [
            'postcode,suburb,state,council,sa4,sa3,latitude,longitude',
            '3088,GREENSBOROUGH,VIC,Banyule,Melbourne - North East,Banyule,-37.70,145.10',
            '2617,BELCONNEN,ACT,Unincorporated ACT,Australian Capital Territory,Belconnen,-35.23,149.09',
            '5350,TANUNDA,SA,Barossa,Barossa - Yorke - Mid North,Barossa,-34.52,138.96',
            '4305,IPSWICH,QLD,Ipswich,Ipswich,Ipswich Inner,-27.61,152.76',
            '3204,MCKINNON,VIC,Glen Eira,Melbourne - Inner South,Glen Eira,0,0',
            '3195,MORDIALLOC,VIC,Kingston (Vic.),Melbourne - South East,Kingston,-38.0,145.08',
            '9999,NOWHERE,XX,,,,,',
        ]));

        $this->assertSame(6, Geography::importLocalities($file));
        $this->assertDatabaseHas('localities', ['suburb' => 'Mordialloc', 'council' => 'Kingston']);
        $this->assertDatabaseHas('localities', ['suburb' => 'Greensborough', 'council' => 'Banyule', 'city' => 'Melbourne']);
        $this->assertDatabaseHas('localities', ['suburb' => 'Belconnen', 'council' => 'Belconnen', 'city' => 'Canberra']);
        $this->assertDatabaseHas('localities', ['suburb' => 'Tanunda', 'city' => 'Barossa, Yorke and Mid North']);
        $this->assertDatabaseHas('localities', ['suburb' => 'Ipswich', 'city' => 'Brisbane']);
        $this->assertDatabaseHas('localities', ['suburb' => 'McKinnon', 'latitude' => null]);
        unlink($file);
    }

    public function test_session_days_come_from_the_session_text(): void
    {
        $days = fn (string $text) => (new Club(['session_times_text' => $text]))->sessionDays();

        $this->assertSame(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sun'], $days('Mon to Fri 9am, Sunday 2pm'));
        $this->assertSame(['Tue', 'Thu'], $days('Tuesdays and Thurs nights'));
        $this->assertSame(['Fri', 'Sat', 'Sun'], $days('Friday–Sunday'));
        $this->assertSame([], $days('Monthly social, see our sunshine calendar'));
    }
}
