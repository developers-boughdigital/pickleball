<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Role;
use App\Models\User;
use App\Models\Venue;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExploreTest extends TestCase
{
    use RefreshDatabase;

    private Venue $dome;

    private Venue $darwin;

    private Club $act;

    protected function setUp(): void
    {
        parent::setUp();

        $published = ['record_status' => 'checked', 'status' => 'published'];

        $this->dome = Venue::create(['name' => 'Pickledome', 'street_address' => '56 Coyne Street', 'suburb' => 'Fadden', 'state' => 'ACT', 'postcode' => '2904', 'latitude' => -35.410446, 'longitude' => 149.129444, 'court_count' => 11, 'indoor_outdoor' => 'indoor', 'lights' => true, 'toilets' => true, 'court_hire_cents' => 2500, 'notes' => 'Admin note: call Dane', ...$published]);
        $this->darwin = Venue::create(['name' => 'Darwin Waterfront', 'suburb' => 'Darwin City', 'state' => 'NT', 'latitude' => -12.469109, 'longitude' => 130.84438, 'indoor_outdoor' => 'outdoor', ...$published]);
        Venue::create(['name' => 'Secret Draft Venue', 'state' => 'VIC']);

        $this->act = Club::create(['name' => 'Pickleball ACT', 'suburb' => 'Belconnen', 'state' => 'ACT', 'levels' => ['beginner', 'advanced'], 'beginners_welcome' => true, 'casual_fee_cents' => 1400, 'session_times_text' => 'Tue 7–9pm all levels', 'email' => 'info@pickleballact.org', 'enrichment_evidence' => 'Quote from the website', ...$published]);
        $this->act->venues()->attach($this->dome, ['is_primary' => true]);
        Club::create(['name' => 'Top End Dinkers', 'suburb' => 'Darwin', 'state' => 'NT', 'levels' => ['new'], ...$published])->venues()->attach($this->darwin, ['is_primary' => true]);
        Club::create(['name' => 'Secret Draft Club', 'state' => 'VIC']);
    }

    public function test_anyone_can_explore_published_clubs(): void
    {
        $this->get('/explore/clubs')->assertOk()
            ->assertSee('Pickleball ACT')
            ->assertSee('Top End Dinkers')
            ->assertSee('Beginners welcome')
            ->assertSeeText('$14.00 a session')
            ->assertDontSee('Secret Draft Club')
            ->assertDontSee('Preview drafts');

        $this->get('/explore/clubs?state=NT')->assertSee('Top End Dinkers')->assertDontSee('Pickleball ACT');
        $this->get('/explore/clubs?level=advanced')->assertSee('Pickleball ACT')->assertDontSee('Top End Dinkers');
        $this->get('/explore/clubs?beginners=1')->assertSee('Pickleball ACT')->assertDontSee('Top End Dinkers');
        $this->get('/explore/clubs?q=belconnen')->assertSee('Pickleball ACT')->assertDontSee('Top End Dinkers');
        $this->get('/explore/clubs?q=nowhere')->assertSee('No clubs match that search.');
    }

    public function test_venues_tab(): void
    {
        $this->get('/explore/venues')->assertOk()
            ->assertSee('Pickledome')
            ->assertSee('11 courts')
            ->assertSeeText('$25.00 an hour')
            ->assertSee('1 club plays here')
            ->assertDontSee('Secret Draft Venue');

        $this->get('/explore/venues?setting=outdoor')->assertSee('Darwin Waterfront')->assertDontSee('Pickledome');
        $this->get('/explore/venues?lights=1')->assertSee('Pickledome')->assertDontSee('Darwin Waterfront');
    }

    public function test_near_me_sorts_by_distance(): void
    {
        // Standing in Darwin.
        $this->get('/explore/venues?lat=-12.46&lng=130.84')->assertOk()
            ->assertSeeInOrder(['Darwin Waterfront', 'Pickledome'])
            ->assertSee('1.1 km away')
            ->assertSee('nearest first');

        // Standing in Canberra; clubs go by their venues.
        $this->get('/explore/clubs?lat=-35.28&lng=149.13')->assertSeeInOrder(['Pickleball ACT', 'Top End Dinkers']);
        $this->get('/explore/clubs?lat=-12.46&lng=130.84')->assertSeeInOrder(['Top End Dinkers', 'Pickleball ACT']);

        // Nonsense coordinates are ignored.
        $this->get('/explore/clubs?lat=abc&lng=999')->assertOk()->assertDontSee('nearest first');
    }

    public function test_detail_pages_show_public_details_only(): void
    {
        $this->get('/explore/clubs/pickleball-act')->assertOk()
            ->assertSee('Pickleball ACT')
            ->assertSee('Tue 7–9pm all levels')
            ->assertSee('Pickledome')
            ->assertSee('Home venue')
            ->assertSee('info@pickleballact.org')
            ->assertDontSee('Quote from the website');

        $this->get('/explore/venues/pickledome')->assertOk()
            ->assertSee('56 Coyne Street, Fadden, ACT 2904')
            ->assertSee('Show map')
            ->assertSee('Pickleball ACT')
            ->assertDontSee('Admin note');

        $this->get('/explore/clubs/secret-draft-club')->assertNotFound();
        $this->get('/explore/venues/secret-draft-venue')->assertNotFound();
    }

    public function test_drafts_are_hidden_from_visitors_and_their_venues_from_clubs(): void
    {
        $this->act->venues()->attach(Venue::firstWhere('name', 'Secret Draft Venue'));

        $this->get('/explore/clubs/pickleball-act')->assertDontSee('Secret Draft Venue');
        $this->get('/play?drafts=1')->assertDontSee('Secret Draft Club');
        $this->get('/explore/clubs/secret-draft-club?drafts=1')->assertNotFound();
    }

    public function test_admins_can_preview_drafts(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->actingAs(User::factory()->create()->assignRole(Role::SUPER_ADMIN));

        $this->get('/play')->assertSee('Preview drafts')->assertDontSee('Secret Draft Club');
        $this->get('/play?drafts=1')->assertSee('Previewing drafts')->assertSee('Secret Draft Club')->assertSee('Draft');
        $this->get('/explore/clubs/secret-draft-club?drafts=1')->assertOk();
        $this->get('/explore/venues/secret-draft-venue?drafts=1')->assertOk();
    }

    public function test_old_explore_links_go_to_the_lists(): void
    {
        $this->get('/explore?tab=venues&state=NT')->assertRedirect('/explore/venues?state=NT');
        $this->get('/explore?q=act')->assertRedirect('/explore/clubs?q=act');

        // The area page moved to /play, permanently.
        $this->get('/explore')->assertStatus(301)->assertRedirect('/play');
        $this->get('/explore?state=nt')->assertStatus(301)->assertRedirect('/play/nt');
        $this->get('/explore?state=XX')->assertRedirect('/play');
    }

    public function test_lists_filter_by_area_and_fees(): void
    {
        $this->get('/explore/clubs?state=ACT&suburb=belconnen')->assertSee('Pickleball ACT')->assertDontSee('Top End Dinkers');
        $this->get('/explore/clubs?max_fee=15')->assertSee('Pickleball ACT')->assertDontSee('Top End Dinkers');
        $this->get('/explore/clubs?max_fee=10')->assertDontSee('Pickleball ACT');
    }

    public function test_coach_index_filters_the_placeholder_coaches(): void
    {
        Club::firstWhere('name', 'Pickleball ACT')->update(['coaching_available' => true, 'coach_names_text' => 'Head coach Jo, PA Level 2']);

        $this->get('/explore/coaches')->assertOk()
            ->assertSee('Find a coach who fits your game.')
            ->assertSee('9 coaches')
            ->assertSee('Head coach Jo, PA Level 2');

        $this->get('/explore/coaches?levels[]=open')->assertSee('Dev R.')->assertSee('Tom L.')->assertDontSee('Mei W.')->assertSee('Remove');
        $this->get('/explore/coaches?price=under_60&wwcc=1')->assertSee('Mei W.')->assertDontSee('Ann B.')->assertDontSee('Sam M.');
        $this->get('/explore/coaches?where=eltham')->assertSee('1 coach')->assertSee('Sam M.');
        $this->get('/explore/coaches?lat=-37.7140&lng=145.1480&distance=5')->assertSee('Sam M.')->assertSee('0.0 km')->assertDontSee('Tom L.');
        $this->get('/explore/coaches?focus[]=fitness&types[]=corporate&sort=price')->assertSeeInOrder(['Luke P.', 'Tom L.']);
        $this->get('/explore/coaches?levels[]=open&types[]=school')->assertSee('No coaches match those filters.');
    }

    public function test_empty_directory(): void
    {
        Club::query()->update(['status' => 'draft']);
        Venue::query()->update(['status' => 'draft']);

        $this->get('/play')->assertOk()->assertSee('No clubs listed yet.')->assertSee('Check back soon');
    }
}
