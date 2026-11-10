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

class PublishTest extends TestCase
{
    use RefreshDatabase;

    private Venue $checked;

    private Venue $unchecked;

    private Venue $live;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->actingAs(User::factory()->create()->assignRole(Role::SUPER_ADMIN));

        $this->checked = Venue::create(['name' => 'Checked Courts', 'state' => 'VIC', 'record_status' => 'checked']);
        $this->unchecked = Venue::create(['name' => 'Unchecked Courts', 'state' => 'VIC']);
        $this->live = Venue::create(['name' => 'Live Courts', 'state' => 'NSW', 'record_status' => 'checked', 'status' => 'published']);
    }

    public function test_publish_one_and_hide_one(): void
    {
        $this->post('/admin/venues/status', ['status' => 'published', 'ids' => [$this->checked->id]])
            ->assertSessionHas('status', 'Published 1 venue.');
        $this->assertSame('published', $this->checked->fresh()->status);

        $this->post('/admin/venues/status', ['status' => 'hidden', 'ids' => [$this->live->id]])
            ->assertSessionHas('status', 'Hid 1 venue.');
        $this->assertSame('hidden', $this->live->fresh()->status);
        $this->get('/explore/venues/live-courts')->assertNotFound();

        $this->assertTrue(ActivityLog::where('action', 'venue.status_changed')->where('description', 'Hid 1 venue')->exists());
    }

    public function test_only_checked_listings_are_published(): void
    {
        $this->post('/admin/venues/status', ['status' => 'published', 'ids' => [$this->checked->id, $this->unchecked->id, $this->live->id]])
            ->assertSessionHas('status', 'Published 1 venue. Skipped 1 venue that isn’t checked yet. Only checked listings can go live.');

        $this->assertSame('published', $this->checked->fresh()->status);
        $this->assertSame('draft', $this->unchecked->fresh()->status);
    }

    public function test_select_all_matching_the_filters(): void
    {
        Venue::create(['name' => 'Another Checked', 'state' => 'VIC', 'record_status' => 'checked']);

        // Everything in VIC, not just the ticked row; NSW is left alone.
        $this->post('/admin/venues/status?state=VIC', ['status' => 'published', 'all' => 1, 'ids' => [$this->checked->id]])
            ->assertSessionHas('status', 'Published 2 venues. Skipped 1 venue that isn’t checked yet. Only checked listings can go live.');

        $this->post('/admin/venues/status?state=VIC', ['status' => 'draft', 'all' => 1])->assertSessionHas('status', 'Moved back to draft 2 venues.');
        $this->assertSame('published', $this->live->fresh()->status);

        $this->post('/admin/venues/status', ['status' => 'published'])->assertSessionHasErrors('ids');
        $this->post('/admin/venues/status', ['status' => 'deleted', 'ids' => [$this->checked->id]])->assertSessionHasErrors('status');
    }

    public function test_clubs_work_the_same_way(): void
    {
        $club = Club::create(['name' => 'Checked Club', 'state' => 'VIC', 'record_status' => 'checked']);

        $this->post('/admin/clubs/status', ['status' => 'published', 'ids' => [$club->id]])->assertSessionHas('status', 'Published 1 club.');
        $this->get('/admin/clubs')->assertSee('Hide Checked Club from the public site');
        $this->get("/admin/clubs/{$club->slug}")->assertSee('Hide');
    }

    public function test_lists_show_the_controls(): void
    {
        $this->get('/admin/venues')->assertOk()
            ->assertSee('Select every venue on this page')
            ->assertSee('Publish Checked Courts')
            ->assertSee('Hide Live Courts from the public site')
            ->assertSee('Unchecked Courts can’t be published until it’s checked', false)
            ->assertSee('Back to draft');

        $this->get("/admin/venues/{$this->unchecked->slug}")->assertSee('Check before publishing');
    }

    public function test_viewers_cannot_publish(): void
    {
        $this->actingAs(User::factory()->create()->assignRole('Viewer'));

        $this->get('/admin/venues')->assertOk()->assertDontSee('Select every venue on this page');
        $this->post('/admin/venues/status', ['status' => 'published', 'ids' => [$this->checked->id]])->assertForbidden();
        $this->assertSame('draft', $this->checked->fresh()->status);
    }
}
