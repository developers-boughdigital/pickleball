<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Club;
use App\Models\ListingClaim;
use App\Models\Role;
use App\Models\User;
use App\Models\Venue;
use App\Notifications\ClaimDecided;
use App\Notifications\ClaimReceived;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Plan step 14: from "Claim this listing" to approval to the claimant editing their club. */
class ClaimTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create()->assignRole(Role::SUPER_ADMIN);
        $this->club = Club::create(['name' => 'Banyule Pickleball Club', 'suburb' => 'Greensborough', 'state' => 'VIC', 'website' => 'https://www.banyulepickleball.org.au',
            'record_status' => 'checked', 'status' => 'published']);
    }

    private function claim(User $user, Club|Venue|null $listing = null, array $data = []): \Illuminate\Testing\TestResponse
    {
        $listing ??= $this->club;
        $kind = $listing instanceof Club ? 'club' : 'venue';

        return $this->actingAs($user)->post("/claim/{$kind}/{$listing->slug}", $data + [
            'role_stated' => 'Secretary',
            'evidence' => 'Listed on banyulepickleball.org.au/committee as secretary.',
            'confirm' => '1',
        ]);
    }

    public function test_new_listings_start_unclaimed_and_the_lists_filter_on_it(): void
    {
        $this->assertSame('unclaimed', $this->club->fresh()->listing_status);
        Club::create(['name' => 'Darebin Dinkers', 'state' => 'VIC', 'listing_status' => 'claimed']);

        $this->actingAs($this->admin)->get('/admin/clubs?listing_status=claimed')->assertOk()
            ->assertSee('Darebin Dinkers')->assertDontSee('Banyule Pickleball Club');
    }

    public function test_the_club_page_offers_a_claim_and_guests_sign_in_first(): void
    {
        $this->get('/explore/clubs/banyule-pickleball-club')
            ->assertSee('Run this club?')
            ->assertSee('href="'.url('/claim/club/banyule-pickleball-club').'"', false);

        // A guest is asked to sign in, and registering brings them back to the form.
        $this->get('/claim/club/banyule-pickleball-club')->assertRedirect('/login');
        $this->post('/register', ['name' => 'Sam Lee', 'email' => 'sam@banyulepickleball.org.au', 'password' => 'secret-pass-123', 'password_confirmation' => 'secret-pass-123'])
            ->assertRedirect(url('/claim/club/banyule-pickleball-club'));
        $this->get('/claim/club/banyule-pickleball-club')->assertOk()->assertSee('How can we check?');
    }

    public function test_sending_a_claim(): void
    {
        $sam = User::factory()->create(['name' => 'Sam Lee', 'email' => 'sam@banyulepickleball.org.au']);

        $this->claim($sam, data: ['evidence' => '', 'confirm' => ''])->assertSessionHasErrors(['evidence', 'confirm']);
        $this->claim($sam)->assertRedirect('/account');

        $claim = ListingClaim::sole();
        $this->assertSame(['pending', 'Secretary', $sam->id], [$claim->status, $claim->role_stated, $claim->user_id]);
        $this->assertSame('pending_claim', $this->club->fresh()->listing_status);
        Notification::assertSentTo($sam, ClaimReceived::class);
        $this->assertTrue(ActivityLog::where('action', 'claim.submitted')->exists());

        // Once is enough.
        $this->claim($sam)->assertRedirect('/account');
        $this->assertSame(1, ListingClaim::count());

        $this->get('/account')->assertSee('Banyule Pickleball Club')->assertSee('Waiting for review');
        $this->get('/claim/club/banyule-pickleball-club')->assertSee('is with our team');
    }

    public function test_only_live_unclaimed_listings_can_be_claimed(): void
    {
        $sam = User::factory()->create();

        $this->club->update(['status' => 'draft']);
        $this->actingAs($sam)->get('/claim/club/banyule-pickleball-club')->assertNotFound();

        $this->club->update(['status' => 'published', 'listing_status' => 'suspended']);
        $this->claim($sam)->assertSessionHasErrors('claim');
        $this->get('/explore/clubs/banyule-pickleball-club')->assertDontSee('Run this club?');

        $this->club->update(['listing_status' => 'claimed']);
        $this->claim($sam)->assertSessionHasErrors('claim');
        $this->get('/explore/clubs/banyule-pickleball-club')->assertSee('Managed by the club');
        $this->assertSame(0, ListingClaim::count());

        $this->actingAs($sam)->get('/claim/team/banyule-pickleball-club')->assertNotFound();
    }

    public function test_approving_makes_the_claimant_club_admin_who_can_then_edit_the_club(): void
    {
        $sam = User::factory()->create(['name' => 'Sam Lee', 'email' => 'sam@banyulepickleball.org.au']);
        $alex = User::factory()->create(['name' => 'Alex Wu', 'email' => 'alex@gmail.com']);
        $this->claim($sam);
        $this->claim($alex);
        [$samsClaim, $alexsClaim] = [ListingClaim::firstWhere('user_id', $sam->id), ListingClaim::firstWhere('user_id', $alex->id)];

        // The queue shows the listing, the claimant, the evidence, and that Sam's email is on the club's domain.
        $this->actingAs($this->admin)->get('/admin/claims')->assertOk()
            ->assertSeeInOrder(['Banyule Pickleball Club', 'Sam Lee', 'Email matches the club', 'Secretary', 'banyulepickleball.org.au/committee'])
            ->assertSee('Alex Wu');
        $this->assertTrue($samsClaim->emailMatchesListing());
        $this->assertFalse($alexsClaim->emailMatchesListing());

        $this->post("/admin/claims/{$samsClaim->id}/approve")->assertSessionHas('status');

        $club = $this->club->fresh();
        $this->assertSame(['claimed', 'claimed'], [$club->listing_status, $club->claim_status]);
        $this->assertSame('admin', $club->listingRoles()->sole()->role);
        $this->assertSame('approved', $samsClaim->fresh()->status);
        // Only one person gets it this way; the other is told why.
        $this->assertSame('rejected', $alexsClaim->fresh()->status);
        Notification::assertSentTo($sam, ClaimDecided::class, fn ($n) => $n->claim->status === 'approved');
        Notification::assertSentTo($alex, ClaimDecided::class, fn ($n) => $n->claim->status === 'rejected');
        $this->get("/admin/clubs/{$club->slug}")->assertSee('Managed by')->assertSee('Sam Lee');

        // Sam edits the club.
        $this->actingAs($sam)->get('/explore/clubs/banyule-pickleball-club')->assertSee('You manage this club');
        $this->get('/account')->assertSee('Club admin')->assertSee('Edit listing');
        $this->get('/manage/club/banyule-pickleball-club')->assertOk()->assertSee('Session times');
        $this->put('/manage/club/banyule-pickleball-club', [
            'short_description' => 'Friendly social play in Melbourne’s north.',
            'session_times_text' => 'Tuesdays 7–9pm',
            'casual_fee' => '12.50',
            'levels' => ['beginner', 'intermediate'],
            'beginners_welcome' => '1',
            'email' => 'hello@banyulepickleball.org.au',
            // Not an owner field: ignored.
            'name' => 'Renamed Club',
            'status' => 'hidden',
        ])->assertSessionHasNoErrors()->assertSessionHas('status', 'Saved. Your changes are live.');

        $club->refresh();
        $this->assertSame(['Friendly social play in Melbourne’s north.', 1250, ['beginner', 'intermediate'], true], [$club->short_description, $club->casual_fee_cents, $club->levels, $club->beginners_welcome]);
        $this->assertSame(['Banyule Pickleball Club', 'published'], [$club->name, $club->status]);
        $log = ActivityLog::firstWhere('action', 'club.owner_updated');
        $this->assertSame(1250, $log->changes['casual_fee_cents']['to']);

        $this->get('/explore/clubs/banyule-pickleball-club')->assertSee('Friendly social play')->assertSee('Tuesdays 7–9pm');

        // Alex doesn't manage it, and a suspended listing locks Sam out too.
        $this->actingAs($alex)->get('/manage/club/banyule-pickleball-club')->assertForbidden();
        $club->update(['listing_status' => 'suspended']);
        $this->actingAs($sam)->get('/manage/club/banyule-pickleball-club')->assertForbidden();
    }

    public function test_rejecting_needs_a_reason_the_claimant_sees(): void
    {
        $sam = User::factory()->create();
        $this->claim($sam);
        $claim = ListingClaim::sole();

        $this->actingAs($this->admin)->post("/admin/claims/{$claim->id}/reject", ['reject_reason' => ''])->assertSessionHasErrors('reject_reason');
        $this->post("/admin/claims/{$claim->id}/reject", ['reject_reason' => 'We couldn’t match you to the club.'])->assertSessionHas('status');

        $this->assertSame('rejected', $claim->fresh()->status);
        $this->assertSame('unclaimed', $this->club->fresh()->listing_status);
        Notification::assertSentTo($sam, ClaimDecided::class);
        $this->get('/admin/claims?status=rejected')->assertSee('We couldn’t match you to the club.');

        // Deciding twice does nothing.
        $this->post("/admin/claims/{$claim->id}/approve")->assertSessionHas('status', 'That claim was already decided.');
        $this->assertSame(0, $this->club->listingRoles()->count());

        $this->actingAs($sam)->get('/account')->assertSee('Rejected')->assertSee('We couldn’t match you to the club.');
        // They can try again with better evidence.
        $this->claim($sam)->assertRedirect('/account');
        $this->assertSame(2, ListingClaim::count());
    }

    public function test_withdrawing_a_claim(): void
    {
        $sam = User::factory()->create();
        $this->claim($sam);
        $claim = ListingClaim::sole();

        $this->actingAs(User::factory()->create())->delete("/claims/{$claim->id}")->assertForbidden();
        $this->actingAs($sam)->delete("/claims/{$claim->id}")->assertRedirect('/account');

        $this->assertSame('withdrawn', $claim->fresh()->status);
        $this->assertSame('unclaimed', $this->club->fresh()->listing_status);
    }

    public function test_venues_can_be_claimed_by_their_manager(): void
    {
        $venue = Venue::create(['name' => 'Pickledome', 'suburb' => 'Fadden', 'state' => 'ACT', 'record_status' => 'checked', 'status' => 'published']);
        $kim = User::factory()->create();

        $this->get('/explore/venues/pickledome')->assertSee('Manage this venue?');
        $this->claim($kim, $venue, ['role_stated' => 'Venue manager'])->assertRedirect('/account');
        $this->actingAs($this->admin)->post('/admin/claims/'.ListingClaim::sole()->id.'/approve');

        $this->assertSame('manager', $venue->listingRoles()->sole()->role);
        $this->actingAs($kim)->put('/manage/venue/pickledome', ['court_count' => '11', 'lights' => '1', 'court_hire' => '30'])->assertSessionHasNoErrors();
        $this->assertSame([11, true, 3000], [$venue->fresh()->court_count, $venue->fresh()->lights, $venue->fresh()->court_hire_cents]);
    }

    public function test_players_get_their_account_page_and_viewers_cannot_decide(): void
    {
        $player = User::factory()->create();
        $this->actingAs($player)->get('/dashboard')->assertRedirect('/account');
        $this->get('/account')->assertOk()->assertSee('Listings you manage');
        $this->get('/explore/clubs')->assertSee('My account')->assertDontSee('>Admin<', false);
        $this->get('/admin/claims')->assertForbidden();

        $this->claim($player);
        $viewer = User::factory()->create()->assignRole('Viewer');
        $this->actingAs($viewer)->get('/admin/claims')->assertOk()->assertSee('Secretary')->assertDontSee('/approve', false);
        $this->post('/admin/claims/'.ListingClaim::sole()->id.'/approve')->assertForbidden();
    }
}
