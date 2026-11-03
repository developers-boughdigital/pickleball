<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Club;
use App\Models\Role;
use App\Models\User;
use App\Models\Venue;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClubTest extends TestCase
{
    use RefreshDatabase;

    private array $form = [
        'name' => 'Pickleball ACT',
        'suburb' => 'Belconnen',
        'postcode' => '2617',
        'state' => 'ACT',
        'short_description' => 'Canberra’s largest pickleball community.',
        'levels' => ['beginner', 'advanced'],
        'session_types_text' => 'Social, Competitive',
        'session_times_text' => 'Tue 7–9pm all levels',
        'beginners_welcome' => '1',
        'juniors' => '0',
        'pa_affiliated' => '1',
        'membership_fee' => '80',
        'casual_fee' => '14.50',
        'booking_method' => 'book_online',
        'email' => 'info@pickleballact.org',
        'website' => 'https://www.pickleballact.org',
        'main_venue' => 'v0001',
        'other_venues_text' => 'V0002, v0003',
        'record_status' => 'to_check',
        'claim_status' => 'not_contacted',
        'profile_tier' => '2',
        'status' => 'draft',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        foreach (['V0001' => 'Belconnen Community Centre', 'V0002' => 'Pickledome', 'V0003' => 'Shirley Smith High School'] as $ref => $name) {
            Venue::create(['ref' => $ref, 'name' => $name, 'state' => 'ACT']);
        }
    }

    private function signInAs(?string $role): User
    {
        $user = User::factory()->create();
        if ($role) {
            $user->assignRole($role);
        }
        $this->actingAs($user);

        return $user;
    }

    public function test_permissions(): void
    {
        $club = Club::create(['name' => 'Pickleball ACT']);

        $this->signInAs('Viewer');
        $this->get('/admin/clubs')->assertOk()->assertSee('Pickleball ACT')->assertDontSee('Add club');
        $this->get(route('admin.clubs.show', $club))->assertOk();
        $this->get('/admin/clubs/create')->assertForbidden();
        $this->put(route('admin.clubs.update', $club), $this->form)->assertForbidden();
        $this->delete(route('admin.clubs.destroy', $club))->assertForbidden();
        $this->get('/admin/clubs/export')->assertForbidden();
        $this->get('/dashboard')->assertSee(route('admin.clubs.index'))->assertDontSee(route('admin.import'));

        $this->signInAs(null);
        $this->get('/admin/clubs')->assertForbidden();
        $this->get('/dashboard')->assertDontSee(route('admin.clubs.index'));
    }

    public function test_adding_a_club_with_its_venues(): void
    {
        $this->signInAs(Role::SUPER_ADMIN);

        $this->get('/admin/clubs/create')->assertOk()->assertSee('C0001');

        $this->post('/admin/clubs', $this->form)->assertRedirect('/admin/clubs/pickleball-act');

        $club = Club::firstWhere('name', 'Pickleball ACT');
        $this->assertSame('C0001', $club->ref);
        $this->assertSame('draft', $club->status);
        $this->assertSame(['beginner', 'advanced'], $club->levels);
        $this->assertSame(['social', 'competitive'], $club->session_types);
        $this->assertSame(8000, $club->membership_fee_cents);
        $this->assertSame(1450, $club->casual_fee_cents);
        $this->assertTrue($club->beginners_welcome);
        $this->assertFalse($club->juniors);
        $this->assertNull($club->seniors);
        $this->assertSame('V0001', $club->primaryVenue()->ref);
        $this->assertEqualsCanonicalizing(['V0001', 'V0002', 'V0003'], $club->venues->pluck('ref')->all());
        $this->assertTrue(ActivityLog::where('action', 'club.created')->exists());

        $this->get('/admin/clubs/pickleball-act')->assertOk()
            ->assertSeeInOrder(['Where they play', 'Belconnen Community Centre', 'Main venue'])
            ->assertSee('Beginner, Advanced')
            ->assertSee('$14.50')
            ->assertSee('Book online');

        // The venue page lists the club too.
        $this->get(route('admin.venues.show', Venue::firstWhere('ref', 'V0002')))->assertSee('Pickleball ACT');
    }

    public function test_validation(): void
    {
        $this->signInAs(Role::SUPER_ADMIN);

        $this->post('/admin/clubs', [...$this->form, 'email' => 'not-an-email', 'levels' => ['expert'], 'main_venue' => 'V9999', 'other_venues_text' => 'V0002, V8888', 'status' => 'published'])
            ->assertSessionHasErrors(['email', 'levels.0', 'main_venue', 'other_venues.1', 'status']);

        $this->assertSame(0, Club::count());
    }

    public function test_editing_venues_slug_and_deleting(): void
    {
        $this->signInAs(Role::SUPER_ADMIN);
        $this->post('/admin/clubs', $this->form);
        $club = Club::firstWhere('name', 'Pickleball ACT');

        $this->get(route('admin.clubs.edit', $club))->assertOk()
            ->assertSee('value="V0001"', false)
            ->assertSee('value="V0002, V0003"', false)
            ->assertSee('value="14.50"', false);

        // Rename, swap the main venue, drop one venue, untick every level.
        $this->put(route('admin.clubs.update', $club), [...$this->form, 'name' => 'Pickleball Canberra', 'main_venue' => 'V0002', 'other_venues_text' => 'V0001', 'levels' => null])
            ->assertRedirect('/admin/clubs/pickleball-act');

        $club->refresh()->load('venues');
        $this->assertSame('pickleball-act', $club->slug);
        $this->assertNull($club->levels);
        $this->assertSame('V0002', $club->primaryVenue()->ref);
        $this->assertEqualsCanonicalizing(['V0001', 'V0002'], $club->venues->pluck('ref')->all());
        $this->assertSame(
            ['from' => 'V0001 (main), V0002, V0003', 'to' => 'V0002 (main), V0001'],
            ActivityLog::firstWhere('action', 'club.updated')->changes['venues'],
        );

        $this->delete(route('admin.clubs.destroy', $club))->assertRedirect('/admin/clubs');
        $this->assertSoftDeleted($club);
    }

    public function test_publishing_needs_a_checked_record(): void
    {
        $this->signInAs(Role::SUPER_ADMIN);

        $this->post('/admin/clubs', [...$this->form, 'record_status' => 'checked', 'status' => 'published']);
        $club = Club::firstWhere('name', 'Pickleball ACT');
        $this->assertSame('published', $club->status);

        $club->update(['record_status' => 'needs_follow_up']);
        $this->assertSame('draft', $club->fresh()->status);
    }

    public function test_search_and_filters(): void
    {
        Club::create(['name' => 'Pickleball ACT', 'suburb' => 'Belconnen', 'state' => 'ACT', 'record_status' => 'checked']);
        Club::create(['name' => 'Darwin Dinkers', 'suburb' => 'Darwin', 'state' => 'NT', 'email' => 'hello@dinkers.example']);
        $this->signInAs('Viewer');

        $this->get('/admin/clubs?search=dinkers.example')->assertSee('Darwin Dinkers')->assertDontSee('Pickleball ACT');
        $this->get('/admin/clubs?state=ACT')->assertSee('Pickleball ACT')->assertDontSee('Darwin Dinkers');
        $this->get('/admin/clubs?record_status=checked')->assertSee('Pickleball ACT')->assertDontSee('Darwin Dinkers');
    }
}
